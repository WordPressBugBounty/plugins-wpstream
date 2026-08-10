/*
 * WpStream admin onboarding / "Quick Start" wizard.
 *
 * Drives the guided onboarding experience shown in wp-admin: registering or
 * logging into a WpStream account (with an Altcha proof-of-work captcha on
 * non-HTTPS sites), then creating a first Free-To-View or Pay-Per-View live
 * channel or Video-On-Demand. After a channel/VOD is created the user is
 * redirected to its edit screen where a series of positioned "help bubbles"
 * (defined in BubbleFreeVod) walk them through turning the channel on and going
 * live. Progress through the wizard is reported to the onboarding telemetry via
 * wpstream_track_onboarding_step(). Everything is wrapped in an IIFE (ONBOARD)
 * whose init() runs on DOMContentLoaded.
 */

// Correlation id for this onboarding run, shared across the created-channel redirect.
let sessionId = '';
// Cached Altcha captcha payload (base64) once the proof-of-work is solved.
let wpstreamCaptchaPayload = '';

// Parse the current page's query string.
const urlParams = new URLSearchParams(window.location.search);
// On the onboarding landing page, mint a fresh session id; elsewhere reuse the one passed along.
if ( urlParams.get('page') === 'wpstream_onboard' ) {
	// Prefer the native UUID generator when available (secure contexts).
	if ( typeof crypto !== 'undefined' && crypto.randomUUID ) {
		sessionId = crypto.randomUUID();
	} else {
		// Fallback UUIDv4 generator for environments without crypto.randomUUID.
		sessionId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function( c ) {
			var r = Math.random() * 16 | 0;
			return ( c === 'x' ? r : ( r & 0x3 | 0x8 ) ).toString( 16 );
		} );
	}
} else {
	// Continuation of an existing wizard run: take the id from the URL.
	sessionId = urlParams.get('session_id');
}

// Onboarding module: self-invoking so its helpers stay private; init() is hooked below.
const ONBOARD=(function(){

    // Start the wizard once the DOM is ready.
    document.addEventListener('DOMContentLoaded',init);
    // Help-bubble definitions, indexed by branch (0=free live, 1=paid live, 2=free VOD, 3=paid VOD).
    // Each entry is an ordered list of steps describing the bubble's target selector,
    // title/content HTML, offset (left/right/top) and arrow style.
    const BubbleFreeVod = [
        //free live stream
        [
            {id: 1, 
                selector: "#menu-posts-wpstream_product", 
                title: "Hooray! You just created your first Free-To-View Channel.",
                content:"You can check out all your FTV Channels or create new ones from this menu.<div class='onboard_line_break'></div>Just look for ‘Free-To-View Live Channels’.",
                left:"175px",
                right:"-30px",
                top:"-30px",
                arrow:"boarding-left-arrow"},
            {id: 2, 
                selector: "#wpstream-sidebar-meta", 
                title: "Control your Live Channel",
                content:"Look for the 'Live Streaming' box to the right of the page.",
                left:"-430px",
                right:"60px",
                top:"0px",
                arrow:"boarding-right-arrow"},
            {id: 3, 
                selector: ".wpstream_show_settings_wrapper", 
                title: "Channel Settings",
                content:'Clicking on the <div class="wpstream_sample_icon_settings"></div> icon will let you access the channel settings.<div class="onboard_line_break"></div> You can adjust preferences for <strong>recording, autoplay</strong>, etc.',
                left:"-444px",
                right:"0px",
                top:"100px",
                arrow:"boarding-right-arrow"},
            {id: 4, 
                selector: ".wpstream_stream_browser_wrapper", 
                title: "Turn it ON",
                content:"To Go Live, first TURN ON your channel. Go ahead, click the big green button below! <div id=\"wpstream_onboarding_start_chanel\">TURN ON</div> Turning on may take a minute or so. You can wait or move on to the next step.",
                left:"-444px",
                right:"auto",
                top:"0px",
                arrow:"boarding-right-arrow"},  
                
            {id: 5, 
                selector: ".wpstream_show_settings_wrapper", 
                title: "Your Channel Page",
                content:"This is the link to the page with your live stream. Viewers will be able to watch your live stream here.<div class='onboard_line_break'></div>To see it, click <div id=\"wpstream_onboarding_open_chanel\" class=\"wpstream_sample_icon_settings wpstream_sample_icon_open_channel\"></div> Page will open in a new tab.",
                left:"-400px",
                right:"auto",
                top:"100px",
                arrow:"boarding-right-arrow"},    
            {id: 6, 
                selector: ".wpstream_stream_browser_wrapper", 
                title: "Go LIVE",
                content:"To go live, first turn on your channnel.<div class='onboard_line_break'></div> If it's already <strong>Turning ON</strong>, wait until <strong>Channel is ON</strong>",
                left:"-444px",
                right:"auto",
                top:"58px",
                arrow:"boarding-right-arrow"}
        ]  ,
        // paid live stream
        [
            {id: 1, 
                selector: "#menu-posts-product", 
                title: "Hooray! You just created your first Pay-Per-View Channel.",
                content:"You can check out all your PPV Channels or create new ones from this menu. Just look for ‘Products’.",
                left:"175px",
                right:"-30px",
                top:"-30px",
                arrow:"boarding-left-arrow"},
            {id: 2, 
                selector: "#normal-sortables", 
                title: "A Pay-Per-View Channel is a Custom Woocommerce Product",
                content:"Note the type of the product: <strong>Live Channel</strong> </br>This sets it apart from other products you may want to sell.",
                left:"345px",
                right:"-30px",
                top:"-35px",
                arrow:"boarding-left-arrow"},
            {id: 3, 
                selector: "#normal-sortables", 
                title: "Set the Pay-Per-View price here",
                content:"Your users will have to purchase the product in order to be allowed to watch the live stream.",
                left:"550px",
                right:"-30px",
                top:"25px",
                arrow:"boarding-left-arrow"},
            {id: 4, 
                selector: "#wpstream-sidebar-meta", 
                title: "Control your channel from here",
                content:"Look for the 'Live Streaming' box on the side bar.",
                left:"-430px",
                right:"60px",
                top:"0px",
                arrow:"boarding-right-arrow"},
            {id: 5, 
                selector: ".wpstream_show_settings_wrapper", 
                title: "Channel Settings",
                content:'Clicking on the <div class="wpstream_sample_icon_settings"></div> icon will let you access the channel settings.<div class="onboard_line_break"></div> You can adjust preferences for <strong>recording, autoplay</strong>, etc.',
                left:"-444px",
                right:"0px",
                top:"100px",
                arrow:"boarding-right-arrow"},
            {id: 6, 
                selector: ".wpstream_stream_browser_wrapper", 
                title: "Turn it ON",
                content:"To Go Live, first TURN ON your channel. Go ahead, click the big green button below!<div id=\"wpstream_onboarding_start_chanel\">TURN ON</div> Turning on may take a minute or so. You can wait or move on to the next step.",
                left:"-444px",
                right:"auto",
                top:"0px",
                arrow:"boarding-right-arrow"},  
            {id: 7, 
                selector: ".wpstream_show_settings_wrapper", 
                title: "Your Channel Page",
                content:"This is the link to the page with your live stream. Viewers will be able to watch your live stream here.<div class='onboard_line_break'></div>To see it, click <div id=\"wpstream_onboarding_open_chanel\" class=\"wpstream_sample_icon_settings wpstream_sample_icon_open_channel\"></div> Page will open in a new tab.",
                left:"-385px",
                right:"auto",
                top:"105px",
                arrow:"boarding-right-arrow"},    
            {id: 8, 
                selector: ".wpstream_stream_browser_wrapper", 
                title: "Go LIVE",
                content:"To go live, first turn on your channnel.<div class='onboard_line_break'></div> If it's already <strong>Turning ON</strong>, wait until <strong>Channel is ON</strong>",
                left:"-430px",
                right:"auto",
                top:"50px",
                arrow:"boarding-right-arrow"}
        ] ,
        // free vod
        [
            {id: 1, 
                selector: "#menu-posts-wpstream_product_vod", 
                title: "Hooray! You just created your first FTV Video-On-Demand.",
                content:"You can check out all your FTV VODs or create new ones from this menu. Just look for 'Free-To-View VODs'",
                left:"175px",
                right:"-30px",
                top:"-30px",
                arrow:"boarding-left-arrow"},
            {id: 2, 
                selector: "#add_wpstream_product_metaboxes-sectionid", 
                title: "The FTV VOD can be a Recording, self hosted, or external video",
                content:"Recordings are stored in the WpStream cloud. <div class='onboard_line_break'></div> Self hosted videos are videos in your WordPress Media Library.<div class='onboard_line_break'></div>External videos are videos hosted on YouTube or Vimeo.",
                left:"245px",
                right:"-30px",
                top:"30px",
                arrow:"boarding-left-arrow"},
            {id: 3, 
                selector: "#add_wpstream_product_metaboxes-sectionid", 
                title: "Choose a specific recording to create the VOD from",
                content:"You can create new recordings by recording a live channel or uploading video files directly.",
                left:"245px",
                right:"-30px",
                top:"100px",
                arrow:"boarding-left-arrow"},
            {id: 4, 
                selector: "#post-body", 
                title: "The VOD Page",
                content:"This is the page that your <strong>viewers</strong> see when they <strong>watch</strong> your VOD.<div class='onboard_line_break'></div>  To see it yourself, click the permalink. Page will open in a new tab.  <div id=\"wpstream_onboarding_view_vod\">View VOD Page</div>",
                left:"350px",
                right:"0px",
                top:"-10px",
                arrow:"boarding-left-arrow"},
    
            /*{id: 5, 
                selector: "#post-body", 
                title: "And Thats it",
                content:"That's it , you users will be free to view this Video on Demand",
                left:"350px",
                right:"0px",
                top:"155px",
                arrow:""},
                */
        ] ,
        //paid vod
        [
            {id: 1, 
                selector: "#menu-posts-product", 
                title: "Hooray! You just created your first PPV Video-On-Demand.",
                content:"You can check out all your PPV VODs or create new ones from this menu. Just look for ‘Products’",
                left:"175px",
                right:"-30px",
                top:"-30px",
                arrow:"boarding-left-arrow"},
            {id: 2, 
                selector: "#normal-sortables", 
                title: "A Pay-Per-View VOD is a Custom Woocommerce Product",
                content:"Note the type of the product: <strong>Video On Demand</strong>.<div class='onboard_line_break'></div>This sets it apart from other products you may want to sell.",
                left:"345px",
                right:"-30px",
                top:"-35px",
                arrow:"boarding-left-arrow"},
            {id: 3, 
                selector: "#normal-sortables", 
                title: "Set the Pay-Per-View price here",
                content:"Your users will have to purchase the product in order to be allowed to watch the VOD stream. ",
                left:"550px",
                right:"-30px",
                top:"25px",
                arrow:"boarding-left-arrow"},
            {id: 4, 
                selector: "#normal-sortables", 
                title: "Choose a specific recording to create the VOD from ",
                content:"You can create new recordings by recording a live channel or uploading video files directly." ,
                left:"700px",
                right:"-30px",
                top:"135px",
                arrow:"boarding-left-arrow"},
            {id: 5, 
                selector: "#post-body", 
                title: "The VOD Page",
                content:"This is the page that your <strong>viewers</strong> see when they <strong>watch</strong> your VOD.<div class='onboard_line_break'></div>To see it yourself, click the permalink. Page will open in a new tab.<div class='onboard_line_break'></div><div id=\"wpstream_onboarding_view_vod\">View VOD Page</div>.",
                left:"350px",
                right:"0px",
                top:"-10px",
                arrow:"boarding-left-arrow"},
            {id: 4, 
                selector: "#post-body", 
                title: "Your PPV VOD is now for sale.",
                content:"Upon successful purchase, your viewers will be able to watch it as many times as they like.",
                left:"350px",
                right:"-200px",
                top:"25px",
                arrow:""},
    
    
            
        ]
    
    ];
   
    /**
     * Entry point: wire up every part of the onboarding wizard on page load.
     *
     * @return {void}
     */
    function init(){
	    // Disable the register button until the privacy box (and captcha, if needed) is satisfied.
	    wpstream_policy_privacy_checkbox();
	    // On non-HTTPS sites, block registration and fetch/solve the Altcha captcha first.
	    if ( window.location.protocol !== 'https:' ) {
		    jQuery('.wpstream_onboard_register').attr('disabled', true);
		    wpstream_fetchCaptcha();
	    }
        // Skip straight to step 2 if the user already holds an API token.
        wpstream_by_pass_login();
        // Bind the login form.
        wpstream_onboard_login();
        // Bind the "create VOD" step's recording-list loader.
        wpstream_get_videos_list();
        // Bind the registration form.
        wpstream_onboard_register();
        // Bind the quick-start trigger / modal open.
        wpstream_main_on_boarding_function();
        // Bind the login/register/next-step navigation clicks.
        wpstream_on_boarding_click_actions();
        // Bind the "create free channel" step.
        wpstream_create_free_channel_action();
        // Bind the "create PPV channel" step.
        wpstream_create_ppv_channel_action();
        // Bind the "create free VOD" step.
        wpstream_create_free_vod_action();
        // Bind the "create PPV VOD" step.
        wpstream_create_ppv_vod_action();
        // Initialise the in-page help-bubble tour (only runs on onboard=yes pages).
        wpstream_local_modal_onboarding();
        // Bind the "close the initial onboarding" control.
        wpstream_on_boarding_initial_close();
        // Bind the initial-step "prev" and related buttons.
        wpstream_onboard_initial_bubble_prev_action();

    }

	/**
	 * Keep the register button disabled until the privacy checkbox is ticked
	 * (and, on non-HTTPS, until the proof-of-work payload is ready).
	 *
	 * @return {void}
	 */
	function wpstream_policy_privacy_checkbox() {
		// Start disabled.
		jQuery('.wpstream_onboard_register').attr('disabled', true);

		// Re-evaluate whenever the privacy checkbox changes.
		jQuery('#wpstream_register_privacy').on('change', function() {
			// On HTTP the captcha payload must also be present; on HTTPS it's not required.
			var powReady = window.location.protocol !== 'https:'
				? wpstreamCaptchaPayload !== ''
				: true;
			// Enable only when the box is checked and the PoW (if any) is ready.
			jQuery('.wpstream_onboard_register').attr('disabled', !this.checked || !powReady );
		});
	}

    /*
    *  Start browser broadcaster
    *
    **/
    /**
     * Once the channel is streaming, make the camera icon clickable so it opens
     * the browser broadcaster.
     *
     * @return {void}
     */
    function wpstream_start_camera(){

        // Only when the channel is live (the button shows a "stop" state).
        if( jQuery('.event_list_unit .wpstream_button').hasClass('wpstream_stop_event') ){
            // Make the camera icon look clickable.
            jQuery('.wpstream_sample_icon_camera').css('cursor','pointer');

            // Clicking the icon triggers the real broadcaster launch, then unbinds itself.
            jQuery('.wpstream_sample_icon_camera').on('click',function(){
                jQuery('.start_webcaster').trigger('click');
                jQuery(this).unbind('click');

            })
        }
    }


    /*
    *   ByPass Login if already in
    *
    **/
    /**
     * If the user already has an API token, skip the login/register steps and
     * jump directly to step 2; otherwise show the register/login controls.
     *
     * @return {void}
     */
    function wpstream_by_pass_login(){
        // Presence of the token marker means the account is already connected.
        if(jQuery('#wpstream_have_token').length >0 ){

            // Jump straight to step 2.
            var nextThing = 'wpstream_step_2';
            jQuery('.wpstream_step_wrapper').hide();
            jQuery('#'+nextThing).show();


        }else{
            // No token: show the registration wrapper and the "log in instead" action.
            jQuery('.wpstream_on_board_register_wrapper').show();
            jQuery('#wpstream_onboarding_action_login').show();
        }
        // Hide the account-status placeholder either way.
        jQuery('.wpstream_check_account_status').hide();
    }



    /*
    * On Board Login
    *
    */

	/**
	 * Bind the onboarding login form: click the login button or press Enter in
	 * the username/password fields to submit.
	 *
	 * @return {void}
	 */
	function wpstream_onboard_login() {
		// Submit on login-button click.
		jQuery('.wpstream_onboard_login').on('click',function(){
			wpstream_onboard_actual_login();
		});


		// Also submit when Enter is pressed in either credential field.
		jQuery('.wpstream_on_board_login_wrapper #api_username,.wpstream_on_board_login_wrapper #api_password').keydown(function (e) {
			if (e.keyCode === 13) {
				e.preventDefault();
				wpstream_onboard_actual_login();
			}
		});
	}

    /**
     * Perform the actual login AJAX call and advance to step 2 on success.
     *
     * @return {void}
     */
    function wpstream_onboard_actual_login(){
        // Read the API credentials the user entered.
        var api_username    =   jQuery('#api_username').val();
        var api_password    =   jQuery('#api_password').val();
        // Build the admin-ajax endpoint URL and read the onboarding nonce.
        var ajaxurl  =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var nonce           =   jQuery('#wpstream_onboarding_nonce').val();


        // Show an in-progress notification.
        jQuery('.wpstream_onboarding_notification').removeClass('onboarding_error').text('Sending data. Please Stand by...').show();

        // Attempt to log in with the supplied credentials.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action'                    :   'wpstream_on_board_login',
                'api_username'              :   api_username,
                'api_password'              :   api_password,
                'security'                  :   nonce
            },
            success: function (data) {
                // On failure, surface the returned error message.
                if(!data.success){
                    jQuery('.wpstream_onboarding_notification').addClass('onboarding_error').text(data.error).show();
                }else{
                    // On success, confirm and advance to step 2 after a short pause.
                    jQuery('.wpstream_onboarding_notification').text('Login successful, please stand by...').show();
                    setTimeout(function() {

                        var nextThing = 'wpstream_step_2';
                        jQuery('.wpstream_onboarding_notification').empty().hide();
                        jQuery('.wpstream_step_wrapper').hide();
                        jQuery('#'+nextThing).show();

                    }, 2500);
                }


            },
            error: function (errorThrown) {

            }
        });

		// Report the login attempt to onboarding telemetry.
		wpstream_track_onboarding_step( 'account_login', 'login_step', 'button', 'account_login_button' );
    }

    /**
     * On the "create VOD" step, fetch the account's available recordings and
     * populate the recording dropdown (free or PPV).
     *
     * @return {void}
     */
    function wpstream_get_videos_list() {
        // Nonce and endpoint for the recording-list request.
        var nonce   = jQuery('#wpstream_onboarding_video_list_nonce').val();
        var ajaxurl = wpstream_admin_control_vars.admin_url + 'admin-ajax.php';

	    // Load the list when the user reaches the create-VOD step.
	    jQuery('.wpstream_step_2_create_vod').on('click', function() {
            // check the current object
            var data_control = 'wpstream_onboard_vod_free';
            // Ask the server for the list of available recordings.
            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: {
                    'action': 'wpstream_get_videos_list',
                    'security': nonce
                },
                success: function(data) {
                    if(data.success) {
                        // Treat null/undefined/empty/empty-array as "no recordings".
                        if ( data.videos === null ||
                            data.videos === undefined ||
                            data.videos === '' ||
                            Array.isArray(data.videos) && data.videos.length === 0
                        ) {
                            // No videos found
                            wpstream_no_videos_notice(data_control === 'wpstream_onboard_vod_free' ? '' : '_for_ppv');
                        } else {
                            // Build the dropdown and reveal it, for either the free or PPV flow.
                            if ( data_control === 'wpstream_onboard_vod_free' ) {
                                wpstream_create_videos_list_content(data.videos, '');
                                jQuery('#wpstream_onboard_vod_free > .spinner').css('display', 'none');
                                jQuery('#wpstream_onboard_vod_free > .wpstream-step-container').css('display', 'block');
                            } else {
                                wpstream_create_videos_list_content(data.videos, '_for_ppv');
                                jQuery('#wpstream_onboard_vod_ppv > .spinner').css('display', 'none');
                                jQuery('#wpstream_onboard_vod_ppv > .wpstream-step-container').css('display', 'block');
                            }
                        }
                    }
                },
                error: function(data) {
                    // Show a generic retrieval-failed error.
                    jQuery('.wpstream_onboarding_notification').addClass('onboarding_error').text('Couldn\'t retrieve data.').show();
                }
            })
        })
    }

    /**
     * Build the recording <select> dropdown from a map of recordings and inject
     * it into the relevant (free or PPV) container.
     *
     * @param {Object} data         Map of recording key => display name.
     * @param {string} data_control '' for the free-VOD flow, '_for_ppv' for PPV.
     * @return {void}
     */
    function wpstream_create_videos_list_content( data, data_control ) {
        // if data_control is empty, it means it's for free vod
        var container = data_control === '' ? jQuery('#wpstream_free_vod_dropdown_videos_list') : jQuery('#wpstream_ppv_vod_dropdown_videos_list');
        // Clear any previous contents.
        container.empty();
        // Add the "choose a recording" label.
        var label = jQuery('<label value="">' + wpstream_admin_control_vars.choose_recording + '</label>');
        container.append(label);
        // Create the select element (id suffixed for the PPV variant).
        var select = jQuery('<select name="wpstream_free_vod_file_name" id="wpstream_free_vod_file_name' + data_control + '">');
        // Add a placeholder first option.
        var option = jQuery('<option value="">' + wpstream_admin_control_vars.select_recording + '</option>');
        select.append(option);
        // Add one option per available recording.
        for (let key in data) {
            let option = jQuery('<option value="' + key + '">' + data[key] + '</option>');
            select.append(option);
        }
        select.append('</select>');
        // Insert the finished dropdown into the container.
        container.append(select);
    }

    /**
     * Show the "no recordings available" warning and hide the VOD-creation steps.
     *
     * @param {string} data_control Unused flag distinguishing free vs PPV callers.
     * @return {void}
     */
    function wpstream_no_videos_notice(data_control) {
        // Hide both VOD-creation panels.
        jQuery('#wpstream_onboard_vod_free, #wpstream_onboard_vod_ppv').css('display', 'none');
        // Reveal the "no recordings" warning.
        jQuery('.wpstream_warning_onboarding').css('display', 'block');
    }



    /*
    * On Board Register
    *
    */
    /**
     * Bind the registration form: submit on register-button click or on Enter
     * in the email/password/captcha fields.
     *
     * @return {void}
     */
    function wpstream_onboard_register(){

        // Submit on register-button click.
        jQuery('.wpstream_onboard_register').on('click',function(){
            wpstream_on_board_actual_register();
        });


        // Also submit when Enter is pressed in any registration field.
        jQuery('#wpstream_register_email, #wpstream_register_password,#wpstream_register_captcha').keydown(function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                wpstream_on_board_actual_register();
            }
        });

    }


    /**
     * Validate and submit the registration request, then advance to step 2 on
     * success. Includes the Altcha captcha payload on non-HTTPS sites.
     *
     * @return {void}
     */
    function wpstream_on_board_actual_register(){
            // Prevent double submits while this request is in flight.
            var button=jQuery(this);
            button.css('pointer-events','none');

            // Read the new-account email and password.
            var wpstream_register_email         =   jQuery('#wpstream_register_email').val();
            var wpstream_register_password      =   jQuery('#wpstream_register_password').val();
            var ajaxurl                         =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
			// Captcha payload: our solved PoW on HTTP, or the Altcha widget value on HTTPS.
			var wpstream_altcha = window.location.protocol !== 'https:'
				? wpstreamCaptchaPayload
			: jQuery('input[name="altcha"]').val();
            var nonce                           =   jQuery('#wpstream_onboarding_nonce').val();
			// Whether the privacy policy checkbox is ticked.
			var wpstream_privacy_checkbox       =   jQuery('#wpstream_register_privacy').is(':checked');


            // Require both email and password.
            if( wpstream_register_email ==='' || wpstream_register_password==='' ){
                jQuery('.wpstream_onboarding_notification').addClass('onboarding_error').text('Please fill all the fields!').show();
                button.css('pointer-events','auto');
                return;
            }

			// Require agreement to the privacy policy.
			if( !wpstream_privacy_checkbox ){
				jQuery('.wpstream_onboarding_notification').addClass('onboarding_error').text('You must agree to the Privacy Policy to continue.').show();
				button.css('pointer-events','auto');
				return;
			}


            // Show an in-progress notification.
            jQuery('.wpstream_onboarding_notification').removeClass('onboarding_error').text('Sending data. Please Stand by...').show();

            // Submit the registration request.
            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: {
                    'action'                    :   'wpstream_on_board_register',
                    'wpstream_register_email'   :   wpstream_register_email,
                    'wpstream_register_password':   wpstream_register_password,
	                'wpstream_altcha'           :   wpstream_altcha,
                    'security'                  :   nonce
                },
                success: function (data) {

                    if(data.success){
                        // Registration ok but the returned token was unusable.
                        if(data.token==='false' || data.token===false){
                            jQuery('.wpstream_onboarding_notification').addClass('onboarding_error').text('We couldn\'t authenticate with your new credentials').show();
                        }else{
                            // Registration and authentication succeeded: confirm and advance to step 2.
                            jQuery('.wpstream_onboarding_notification').text('Registration successful, please stand by...').show();
	                        wpstream_track_onboarding_step( 'register_account', 'wpstream_step_1' );
                            setTimeout(function() {

                                var nextThing = 'wpstream_step_2';
                                jQuery('.wpstream_step_wrapper').hide();
                                jQuery('.wpstream_onboarding_notification').empty().hide();
                                jQuery('#'+nextThing).show();

                            }, 2500);
                        }
                    }else{
                        // Registration failed: show the server's message.
                        jQuery('.wpstream_onboarding_notification').addClass('onboarding_error').text(data.message).show();
                        // wpstream_fetchCaptcha();

                    }
                    // Re-enable the button.
                    button.css('pointer-events','auto');

                },
                error: function (errorThrown) {
                    // Re-enable the button on a transport error.
                    button.css('pointer-events','auto');
                }
            });

	        // Report the registration attempt to onboarding telemetry.
	        wpstream_track_onboarding_step('register_account', 'register_step', 'button', 'register_account_button');
    }



    /*
    * Check and Start the on Board
    *
    */

	/**
	 * Compute the hex SHA-256 digest of a string. Uses the native SubtleCrypto
	 * API when available (secure contexts) and falls back to a pure-JS
	 * implementation on non-HTTPS pages where crypto.subtle is unavailable.
	 *
	 * @param {string} message The input string to hash.
	 * @return {Promise<string>} Lower-case hex digest (empty string on failure).
	 */
	async function sha256(message) {
		// Fast path: use the browser's built-in SHA-256 when available.
		if ( typeof crypto !== 'undefined' && crypto.subtle ) {
			const msgBuffer = new TextEncoder().encode(message);
			const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
			const hashArray = Array.from(new Uint8Array(hashBuffer));
			return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
		}
		// Pure-JS SHA-256 fallback for non-secure (HTTP) contexts
		function rightRotate(value, amount) {
			return (value >>> amount) | (value << (32 - amount));
		}
		var mathPow = Math.pow;
		var maxWord = mathPow(2, 32);
		var i, j;
		var result = '';
		var words = [];
		var asciiBitLength = message.length * 8;
		var hash = sha256.h = sha256.h || [];
		var k = sha256.k = sha256.k || [];
		var primeCounter = k.length;
		var isComposite = {};
		for (var candidate = 2; primeCounter < 64; candidate++) {
			if (!isComposite[candidate]) {
				for (i = 0; i < 313; i += candidate) {
					isComposite[i] = candidate;
				}
				hash[primeCounter] = (mathPow(candidate, .5) * maxWord) | 0;
				k[primeCounter++] = (mathPow(candidate, 1/3) * maxWord) | 0;
			}
		}
		message += '\x80';
		while (message.length % 64 - 56) message += '\x00';
		for (i = 0; i < message.length; i++) {
			j = message.charCodeAt(i);
			if (j >> 8) return '';
			words[i >> 2] |= j << ((3 - i) % 4) * 8;
		}
		words[words.length] = ((asciiBitLength / maxWord) | 0);
		words[words.length] = (asciiBitLength | 0);
		for (j = 0; j < words.length;) {
			var w = words.slice(j, j += 16);
			var oldHash = hash.slice(0);
			hash = sha256.h.slice(0);
			for (i = 0; i < 64; i++) {
				var i2 = i + j - 16;
				var w15 = w[i - 15], w2 = w[i - 2];
				var a = hash[0], e = hash[4];
				var temp1 = hash[7]
					+ (rightRotate(e, 6) ^ rightRotate(e, 11) ^ rightRotate(e, 25))
					+ ((e & hash[5]) ^ (~e & hash[6]))
					+ k[i]
					+ (w[i] = (i < 16) ? w[i] : (
						w[i - 16]
						+ (rightRotate(w15, 7) ^ rightRotate(w15, 18) ^ (w15 >>> 3))
						+ w[i - 7]
						+ (rightRotate(w2, 17) ^ rightRotate(w2, 19) ^ (w2 >>> 10))
					) | 0);
				var temp2 = (rightRotate(a, 2) ^ rightRotate(a, 13) ^ rightRotate(a, 22))
					+ ((a & hash[1]) ^ (a & hash[2]) ^ (hash[1] & hash[2]));
				hash = [(temp1 + temp2) | 0].concat(hash);
				hash[4] = (hash[4] + temp1) | 0;
				hash.length = 8;
			}
			for (i = 0; i < 8; i++) {
				hash[i] = (hash[i] + oldHash[i]) | 0;
			}
		}
		for (i = 0; i < 8; i++) {
			for (j = 3; j + 1; j--) {
				var b = (hash[i] >> (j * 8)) & 255;
				result += ((b < 16) ? 0 : '') + b.toString(16);
			}
		}
		return result;
	}

	/**
	 * Solve an Altcha proof-of-work: brute-force the integer whose
	 * sha256(salt + number) equals the given challenge hash.
	 *
	 * @param {string} salt      The challenge salt.
	 * @param {string} challenge The target hex digest to match.
	 * @param {number} maxNumber Upper bound for the brute-force search.
	 * @return {Promise<number|null>} The solving number, or null if none found.
	 */
	async function wpstream_solve_pow(salt, challenge, maxNumber) {
		// Altcha PoW: find a number where sha256(salt + number) === challenge
		for ( var number = 0; number <= maxNumber; number++ ) {
			var hash = await sha256( salt + number );
			if ( hash === challenge ) {
				return number;
			}
		}
		// No match within the search range.
		return null;
	}

	/**
	 * Fetch an Altcha captcha challenge, solve its proof-of-work, and store the
	 * URL-safe base64 payload used later by the registration request. Enables
	 * the register button once solved (if privacy is agreed); otherwise shows a
	 * retry link.
	 *
	 * @return {Promise<void>}
	 */
	async function wpstream_fetchCaptcha () {
		// Request a fresh challenge from the server.
		fetch( wpstream_admin_control_vars.admin_url + 'admin-ajax.php?action=wpstream_get_captcha_challenge' )
			.then( response => response.json() )
			.then( async function( result ) {
				// Only proceed if the challenge has all required fields.
				if ( result.algorithm && result.challenge && result.salt && result.signature ) {
					// Brute-force the proof-of-work solution.
					const solution = await wpstream_solve_pow( result.salt, result.challenge, 50000 );
					if ( solution !== null ) {
						// Assemble the Altcha payload the server expects.
						var altchaPayload = {
							algorithm : result.algorithm,
							challenge : result.challenge,
							number    : solution,
							salt      : result.salt,
							signature : result.signature
						};
						// URL-safe base64 so http_build_query doesn't corrupt + characters
						var payloadBase64 = btoa( JSON.stringify( altchaPayload ) )
							.replace( /\+/g, '-' )
							.replace( /\//g, '_' )
							.replace( /=+$/, '' );
						// Cache it for the register request.
						wpstreamCaptchaPayload = payloadBase64;
						// If the privacy box is already ticked, unblock the register button.
						if ( jQuery('#wpstream_register_privacy').is(':checked') ) {
							jQuery('.wpstream_onboard_register').attr( 'disabled', false );
						}
					} else {
						// Could not solve within the range: offer a retry.
						console.log( 'Security check: could not solve PoW challenge' );
						wpstream_captcha_show_retry();
					}
				} else {
					// Malformed/failed challenge: offer a retry.
					console.log( 'Security check init failed', result );
					wpstream_captcha_show_retry();
				}
			} )
			.catch( function( error ) {
				// Network/parse error: offer a retry.
				console.log( 'Security check error', error );
				wpstream_captcha_show_retry();
			} );
	}

	/**
	 * Disable registration and show a one-time "security check failed, retry"
	 * link that re-fetches the captcha when clicked.
	 *
	 * @return {void}
	 */
	function wpstream_captcha_show_retry() {
		// Block registration until a captcha is solved.
		jQuery('.wpstream_onboard_register').attr( 'disabled', true );
		// Insert the retry notice only once.
		if ( jQuery('#wpstream_captcha_retry').length === 0 ) {
			jQuery('.wpstream_onboard_register').before(
				'<div id="wpstream_captcha_retry" style="margin-bottom:8px;color:#c00;">' +
				'Security check failed. <a href="#" id="wpstream_captcha_retry_link">Click here to retry.</a>' +
				'</div>'
			);
			// Retry link: remove the notice and fetch a new challenge.
			jQuery(document).on( 'click', '#wpstream_captcha_retry_link', function(e) {
				e.preventDefault();
				jQuery('#wpstream_captcha_retry').remove();
				wpstream_fetchCaptcha();
			});
		}
	}


    /**
     * Inject a server-rendered captcha widget and its id into the form, clearing
     * the manual captcha input. (Legacy captcha handler.)
     *
     * @param {Object} response Server response with `capthca` markup and `capthca_id`.
     * @return {void}
     */
    function wpstream_process_capthca(response){


        // Replace the captcha container with the returned markup.
        jQuery('#wpstream_capthca').empty().append(response.capthca);
        // Store the captcha id in the hidden field.
        jQuery('#wpstream_register_captcha_id').val(response.capthca_id);
        // Clear the manual captcha text input.
        jQuery('#wpstream_register_captcha').empty();

    }




    /*
    * Check and Start the on Board
    *
    */


    /**
     * Bind the "Quick Start" trigger to open the onboarding modal, and auto-open
     * the modal when the page was loaded with ?onboard_start=yes.
     *
     * @return {void}
     */
    function wpstream_main_on_boarding_function(){

        // Quick-start button: open the modal and show step 1.
        jQuery('#wpstream_trigger_quick_start').on('click',function(){

            jQuery(".wpstream_on_boarding_wrapper").show();
            jQuery(".wpstream_modal_background_onboard").show();

            // Reset to the registration/step-1 view.
            jQuery('.wpstream_on_board_login_wrapper,#wpstream_onboarding_action_register,.wpstream_onboarding_notification').hide();
            jQuery('#wpstream_step_1,.wpstream_close_initial_onboarding,.wpstream_on_board_register_wrapper').show();
            wpstream_by_pass_login();
        });


        // Auto-open the modal when arriving via ?onboard_start=yes.
        let params = (new URL(document.location)).searchParams;
        let onboard = params.get('onboard_start');

        // Otherwise do nothing further.
        if(onboard!=='yes'){
            return;
        }

        // Show the modal automatically.
        jQuery('.wpstream_on_boarding_wrapper').show();
        jQuery('.wpstream_modal_background_onboard').show();



    }

    /*
    * WpStream on Boarding CLick actions
    *
    */


    /**
     * Bind the modal's navigation links: switch between the login and register
     * views, and advance to the next wizard step.
     *
     * @return {void}
     */
    function wpstream_on_boarding_click_actions(){
        // "Already have an account" -> show the login view.
        jQuery('#wpstream_onboarding_action_login').on('click',function(){
            jQuery(this).hide();
            jQuery('.wpstream_onboarding_notification').hide();
            jQuery('.wpstream_on_board_register_wrapper').hide();
            jQuery('.wpstream_on_board_login_wrapper').show();
            jQuery('#wpstream_onboarding_action_register').show();
			wpstream_track_onboarding_step( 'already_have_account', 'register_step', 'link' );
        });

        // "Back to registration" -> show the register view.
        jQuery('#wpstream_onboarding_action_register').on('click',function(){
            jQuery(this).hide();
            jQuery('.wpstream_onboarding_notification').hide();
            jQuery('.wpstream_on_board_login_wrapper').hide();
            jQuery('.wpstream_on_board_register_wrapper').show();
            jQuery('#wpstream_onboarding_action_login').show();
	        wpstream_track_onboarding_step( 'back_to_registration', 'login_step', 'link' );
        });


        // Generic "next step" buttons: move to the step named in data-nextthing.
        jQuery('.wpstream_action_next_step').on('click',function(){
            var nextThing = jQuery(this).attr('data-nextthing');
            jQuery('.wpstream_step_wrapper').hide();
            jQuery('#'+nextThing).show();
			var buttonStep = jQuery(this).parent().attr('id');

			// Report the step transition to telemetry.
			wpstream_track_onboarding_step( 'step_button_press', onboarding_step_to_string(buttonStep) + '_step', 'button', onboarding_step_to_string(nextThing) + '_button'  );
        });
    }


    /*
    * Create free Channel action
    *
    */

    /**
     * Bind the "create free channel" step: submit on button click or Enter in
     * the channel-name field.
     *
     * @return {void}
     */
    function wpstream_create_free_channel_action(){
        // Submit on create-channel button click.
        jQuery('#wpstream_on_board_create_channel').on('click',function(){
            wpstream_actual_create_free_channel_action();
        });

        // Also submit when Enter is pressed in the channel-name field.
        jQuery('#wpstream_onboarding_channel_name').keydown(function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                wpstream_actual_create_free_channel_action();
            }
        });


    }

    /**
     * Create a Free-To-View live channel via AJAX and, on success, redirect to
     * its edit screen (carrying the onboarding session id).
     *
     * @return {void}
     */
    function wpstream_actual_create_free_channel_action(){
        // Show progress and disable the button to prevent double submits.
        jQuery('#wpstream_onboard_live_notice').removeClass('onboarding_error').text('Creating your FTV live channel. Please Stand by...').show();
        jQuery('#wpstream_on_board_create_channel').prop('disabled', true);
        // Read the channel name and request essentials.
        var channel_name    =   jQuery('#wpstream_onboarding_channel_name').val();
        var ajaxurl  =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var nonce           =   jQuery('#wpstream_onboarding_nonce').val();

        // Ask the server to create the free channel.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action'                    :   'wpstream_on_board_create_channel',
                'channel_name'              :   channel_name,
                'security'                  :   nonce
            },
            success: function (data) {

                if( data.success ) {
                    // Decode the returned edit link and append the session id, then redirect.
                    var new_link = data.link;
                    var decoded = new_link.replace(/&amp;/g, '&');
					var redirectUrl = new URL(decoded, window.location.origin);
	                redirectUrl.searchParams.append('session_id', sessionId);
                    window.location.href = redirectUrl.toString();
                } else {
                    // Show the create error and re-enable the button.
                    jQuery('#wpstream_onboard_live_notice').empty().addClass('onboarding_error').show().text(wpstream_admin_control_vars.channel_create_error)
                    jQuery('#wpstream_on_board_create_channel').prop('disabled', false);
                }
            },
            error: function ( errorThrown ) {
                // Show the create error and re-enable the button on a transport error.
                jQuery('#wpstream_onboard_live_notice').empty().addClass('onboarding_error').show().text(wpstream_admin_control_vars.channel_create_error)
                jQuery('#wpstream_on_board_create_channel').prop('disabled', false);
            }
        });

	    // Report the create action to telemetry.
	    wpstream_track_onboarding_step( 'create_free_channel_button', 'create_free_channel' );
    }

    /*
    * Create PPV Channel action
    *
    */

    /**
     * Bind the "create PPV channel" step: submit on button click or Enter in the
     * name/price fields.
     *
     * @return {void}
     */
    function wpstream_create_ppv_channel_action(){
        // Submit on create-PPV-channel button click.
        jQuery('#wpstream_onboard_live_ppv_action').on('click',function(){
            wpstream_actual_create_ppv_channel_action();
        });


        // Also submit when Enter is pressed in the name or price field.
        jQuery('#wpstream_onboarding_channel_name_ppv,#wpstream_onboarding_event_price_ppv').keydown(function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                wpstream_actual_create_ppv_channel_action();
            }
        });

    }


    /**
     * Create a Pay-Per-View live channel via AJAX and, on success, redirect to
     * its edit screen.
     *
     * @return {void}
     */
    function wpstream_actual_create_ppv_channel_action(){
        // Show progress and disable the button to prevent double submits.
        jQuery('#wpstream_onboard_live_ppv_notice').removeClass('onboarding_error').text('Creating your Pay Per View live channel. Please Stand by...').show();
        jQuery('#wpstream_onboard_live_ppv_action').prop('disabled', true);
        // Read the channel name, price and request essentials.
        var channel_name    =   jQuery('#wpstream_onboarding_channel_name_ppv').val();
        var channel_price   =   jQuery('#wpstream_onboarding_event_price_ppv').val();
        var ajaxurl         =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var nonce           =   jQuery('#wpstream_onboarding_nonce').val();


        // Ask the server to create the PPV channel (a WooCommerce product).
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action'                    :   'wpstream_on_board_create_channel_ppv',
                'channel_name'              :   channel_name,
                'channel_price'             :   channel_price,
                'security'                  :   nonce
            },
            success: function (data) {

                if( data.success ) {
                    // Decode the returned edit link and redirect to it.
                    var new_link = data.link;
                    var decoded = new_link.replace(/&amp;/g, '&');
                    window.location.href=decodeURI(decoded);
                } else {
                    // Show the create error and re-enable the button.
                    jQuery('#wpstream_onboard_live_ppv_notice').empty().addClass('onboarding_error').show().text(wpstream_admin_control_vars.channel_create_error)
                    jQuery('#wpstream_onboard_live_ppv_action').prop('disabled', false);
                }

            },
            error: function (errorThrown) {
                // Show the create error and re-enable the button on a transport error.
                jQuery('#wpstream_onboard_live_ppv_notice').empty().addClass('onboarding_error').show().text(wpstream_admin_control_vars.channel_create_error)
                jQuery('#wpstream_onboard_live_ppv_action').prop('disabled', false);
            }
        });

		// Report the create action to telemetry.
		wpstream_track_onboarding_step( 'create_ppv_channel_button', 'create_ppv_channel' );
    }


    /*
    * Create Free VOD Action
    *
    */


    /**
     * Bind the "create free VOD" step: submit on button click or Enter in the
     * name/recording fields.
     *
     * @return {void}
     */
    function wpstream_create_free_vod_action(){
        // Submit on create-VOD button click.
        jQuery('#wpstream_onboard_vod_free_action').on('click',function(){
            wpstream_actual_create_free_vod_action();
        });

        // Also submit when Enter is pressed in the name or recording field.
        jQuery('#wpstream_onboarding_vod_name, #wpstream_free_vod_file_name').keydown(function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                wpstream_actual_create_free_vod_action();
            }
        });
    }


    /**
     * Create a Free-To-View VOD from a selected recording via AJAX and redirect
     * to its edit screen on success.
     *
     * @return {void}
     */
    function wpstream_actual_create_free_vod_action(){
        // Show progress and disable the button to prevent double submits.
        jQuery('#wpstream_onboard_vod_free_notice').removeClass('onboarding_error').text('Creating your VOD. Please Stand by...').show();
        jQuery('#wpstream_onboard_vod_free_action').prop('disabled', true);
        // Read the VOD name, chosen recording, and request essentials.
        var channel_name    =   jQuery('#wpstream_onboarding_vod_name').val();
        var file_name       =   jQuery('#wpstream_free_vod_file_name').val();
        var ajaxurl         =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var nonce           =   jQuery('#wpstream_onboarding_nonce').val();


        // A recording must be selected before we can create the VOD.
        if(file_name===''){
            jQuery('#wpstream_onboard_vod_free_notice').empty().addClass('onboarding_error').show().text('Please select a recording from the list')
            jQuery('#wpstream_onboard_vod_free_action').prop('disabled', false);
            return;
        }

        // Ask the server to create the free VOD.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action'                    :   'wpstream_on_board_create_free_vod',
                'channel_name'              :   channel_name,
                'file_name'                 :   file_name,
                'security'                  :   nonce
            },
            success: function (data) {
                if( data.success ) {
                    // Decode the returned edit link and redirect to it.
                    var new_link = data.link;
                    var decoded = new_link.replace(/&amp;/g, '&');
                    window.location.href=decodeURI(decoded);
                } else {
                    // Show the create error and re-enable the button.
                    jQuery('#wpstream_onboard_vod_free_notice').empty().addClass('onboarding_error').show().text(wpstream_admin_control_vars.channel_create_error)
                    jQuery('#wpstream_onboard_vod_free_action').prop('disabled', false);
                }
            },
            error: function (errorThrown) {
                // Show the create error and re-enable the button on a transport error.
                jQuery('#wpstream_onboard_vod_free_notice').empty().addClass('onboarding_error').show().text(wpstream_admin_control_vars.channel_create_error)
                jQuery('#wpstream_onboard_vod_free_action').prop('disabled', false);
            }
        });
    }


    /*
    * Create PPV VOD Action
    *
    */


    /**
     * Bind the "create PPV VOD" step: submit on button click or Enter in the
     * name/recording/price fields.
     *
     * @return {void}
     */
    function wpstream_create_ppv_vod_action(){
        // Submit on create-PPV-VOD button click.
        jQuery('#wpstream_onboard_vod_ppv_action').on('click',function(){
            wpstream_actual_create_ppv_vod_action();
        });

        // Also submit when Enter is pressed in the name, recording or price field.
        jQuery('#wpstream_onboarding_ppv_vod_name, #wpstream_free_vod_file_name_for_ppv,#wpstream_onboarding_vod_price').keydown(function (e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                wpstream_actual_create_ppv_vod_action();
            }
        });


    }

    /**
     * Create a Pay-Per-View VOD from a selected recording via AJAX and redirect
     * to its edit screen on success.
     *
     * @return {void}
     */
    function wpstream_actual_create_ppv_vod_action(){
        // Show progress and disable the button to prevent double submits.
        jQuery('#wpstream_onboard_vod_ppv_notice').removeClass('onboarding_error').text('Creating your VOD. Please Stand by...').show();
        jQuery('#wpstream_onboard_vod_ppv_action').prop('disabled', true);

        // Read the VOD name, chosen recording, price, and request essentials.
        var channel_name    =   jQuery('#wpstream_onboarding_ppv_vod_name').val();
        var file_name       =   jQuery('#wpstream_free_vod_file_name_for_ppv').val();
        var vod_price       =   jQuery('#wpstream_onboarding_vod_price').val()
        var ajaxurl  =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var nonce           =   jQuery('#wpstream_onboarding_nonce').val();


        // A recording must be selected before we can create the VOD.
        if( file_name === '' ) {
            jQuery('#wpstream_onboard_vod_ppv_notice').empty().addClass('onboarding_error').show().text('Please select a recording from the list')
            jQuery('#wpstream_onboard_vod_ppv_action').prop('disabled', false);
            return;
        }


        // Ask the server to create the PPV VOD (a WooCommerce product).
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action'                    :   'wpstream_on_board_create_ppv_vod',
                'channel_name'              :   channel_name,
                'file_name'                 :   file_name,
                'vod_price'                 :   vod_price,
                'security'                  :   nonce
            },
            success: function (data) {
                if( data.success ) {
                    // Decode the returned edit link and redirect to it.
                    var new_link = data.link;
                    var decoded = new_link.replace(/&amp;/g, '&');
                    window.location.href=decodeURI(decoded);
                } else {
                    // Show the create error and re-enable the button.
                    jQuery('#wpstream_onboard_vod_ppv_notice').empty().addClass('onboarding_error').show().text(wpstream_admin_control_vars.channel_create_error)
                    jQuery('#wpstream_onboard_vod_ppv_action').prop('disabled', false);
                }
            },
            error: function (errorThrown) {
                // Show a generic error and re-enable the button on a transport error.
                jQuery('#wpstream_onboard_vod_ppv_notice').empty().addClass('onboarding_error').show().text('Something did not work .Please try again.')
                jQuery('#wpstream_onboard_vod_ppv_action').prop('disabled', false);
            }
        });

		// Report the create action to telemetry.
		wpstream_track_onboarding_step( 'create_ppv_vod', 'create_vod' );
    }

    /*
    * Start Modal on Particular Pages
    *
    */

    /**
     * On a channel/VOD edit screen reached with ?onboard=yes&branch=N, build the
     * help-bubble overlay for that branch and start the guided tour.
     *
     * @return {void}
     */
    function wpstream_local_modal_onboarding(){
        // Only run when the page was opened with ?onboard=yes.
        let params = (new URL(document.location)).searchParams;
        let onboard = params.get('onboard');

        // Not an onboarding page: do nothing.
        if(onboard!=='yes'){
            return;
        }

        // Which bubble branch to show (1-based in the URL).
        let branch= params.get('branch');
        branch=parseInt(branch);

        // Bail out if the branch is not a number.
        if(isNaN(branch)){
            return;
        }

        // Convert to the 0-based index used by BubbleFreeVod.
        branch=branch-1;


        // Build the help-bubble markup (title/content are filled in later per step).
        var help_bubble_to_insert='<div id="wpstream_onboard_bubble" data-bubble-step="0"><div class="wpstream_close_onboarding"></div>'+
        '<h2 id="wpstream_onboard_bubble_tile"></h2>'+ 
        '<p id="wpstream_onboard_bubble_content"></p>'+
        '<span class="wpstream_onboard_bubble_prev">Prev</span>'+
        '<span class="wpstream_onboard_bubble_next">Next</span>'+
        '<span class="wpstream_onboard_bubble_finish">Finish</span>'+
        // '<ul class="wpstream_round_bubble">';
        // for (let step = 0; step < BubbleFreeVod[branch].length ; step++) {
        //   help_bubble_to_insert=help_bubble_to_insert+'<li></li>';    
        // }

        //help_bubble_to_insert=help_bubble_to_insert+'</ul>'+
        '</div><div class="wpstream_modal_background_onboard2"></div>';

        // Insert the bubble + backdrop into the admin content area.
        jQuery('#wpcontent').append(help_bubble_to_insert);

        // Render the first bubble for this branch.
        wpstream_show_bubble(0,branch);
        // Hide the WooCommerce header so it doesn't overlap the bubble.
        jQuery('.woocommerce-layout__header').hide();
        // Reveal the backdrop and bubble.
        jQuery('.wpstream_modal_background_onboard2,#wpstream_onboard_bubble').show();

        // Wire up the close controls and the next/prev bubble navigation.
        wpstream_on_boarding_close();
        wpstream_bubble_controls(branch);

    }


    /*
    * Close initial on boarding
    *
    */

    /**
     * Bind the initial-onboarding close control: record which step was open,
     * hide the wizard, and show a "you can run this again later" acknowledgement.
     *
     * @return {void}
     */
    function wpstream_on_boarding_initial_close(){

        jQuery('.wpstream_close_initial_onboarding').on('click',function(){
            var parent_modal=jQuery(this).parent();
	        // Find the currently visible step and report the close event for it.
	        parent_modal.find('.wpstream_step_wrapper').each(function() {
		        if (jQuery(this).css('display') === 'block') {
			        var current_step = jQuery(this).attr('id');
			        wpstream_track_onboarding_step( 'close_onboarding', onboarding_step_to_string(current_step) + '_step' );
		        }
	        });
            // Hide the close control and all step panels.
            parent_modal.find('.wpstream_close_initial_onboarding,.wpstream_step_wrapper').hide();
            // Show the "run again later" warning with a final acknowledge button.
            parent_modal.find('.wpstream_close_onboarding_warning').show().html('You can run the wizard again at any time.</br> In the left menu navigate to WpStream -> WpStream Quick Start <div id="wpstrean_close_modal_ack" class="wpstrean_close_modal_ack_action">Okay, Close it now!</div>');

            // Wire up the acknowledge button.
            wpstrean_close_modal_ack_function();
        });
    }


    /*
    * Close ACK
    *
    */
    /**
     * Bind the final "Okay, close it now" acknowledgement: dismiss the modal,
     * backdrops and any help bubble.
     *
     * @return {void}
     */
    function wpstrean_close_modal_ack_function(){
        jQuery('.wpstrean_close_modal_ack_action').on('click',function(){

			// Report the acknowledgement (note: passes strings, see close-modal logic).
			wpstream_onboarding_close_modal_logic( 'close_onboarding_acknowledge', 'wpstream_close_notice' );
            // Hide the acknowledgement's parent and all onboarding chrome.
            var parent_modal=jQuery(this).parent();
            parent_modal.hide();
            jQuery('.wpstream_modal_background_onboard2,.wpstream_modal_background_onboard').hide();
            jQuery('.wpstream_on_boarding_wrapper').hide();
            jQuery('#wpstream_onboard_bubble').hide();
        });
    }




    /*
    * Iniial next prev actions
    *
    */

    /**
     * Bind the initial-step "prev" button and a few auxiliary actions (retry,
     * install helper plugin, upload video).
     *
     * @return {void}
     */
    function wpstream_onboard_initial_bubble_prev_action(){
        // "Prev" button: go back to the step named in data-step.
        jQuery('.wpstream_onboard_initial_bubble_prev').on('click',function(){
            var prev_step = jQuery(this).attr('data-step');
            jQuery('.wpstream_step_wrapper').hide();
            jQuery("#"+prev_step).show();

			// Report the back navigation from the current step.
			var current_step = jQuery(this).parent().parent().attr('id');
			wpstream_track_onboarding_step( 'prev_button_click' , onboarding_step_to_string(current_step) + '_step' );
        });


        // "Try again" button: reload the page.
        jQuery('.wpstream_onboarding_tryagain').on('click',function(){
            location.reload();
        });

        // "Install plugin" button: open the plugin URL in a new tab.
        jQuery('.wpstream_install_plugin').on('click',function(){
            var url = wpstreamonboarding_js_vars.plugin_url;
            window.open(url, '_blank');
        });

        // "Upload video" button: open the upload URL in a new tab.
        jQuery('.wpstream_upload_video').on('click',function(){
            var url = wpstreamonboarding_js_vars.upload_url;
            window.open(url, '_blank');
        });

    }



    /*
    * Show MOdal/Bubble for local pages
    *
    */

    /**
     * Render a specific help bubble: move it next to its target element, fill in
     * its title/content/arrow/position, toggle the next vs finish button, scroll
     * it into view, and (re)bind the per-step action handlers.
     *
     * @param {number} current_bubble_step Index of the step within the branch.
     * @param {number} branch              Branch index into BubbleFreeVod.
     * @return {void}
     */
    function wpstream_show_bubble(current_bubble_step,branch){

        // Detach the bubble from its current position so it can be re-parented.
        var current_bubble      =   jQuery('#wpstream_onboard_bubble');
        current_bubble.detach();

        // Pull this step's definition and its fields.
        var new_info        =   BubbleFreeVod[branch][current_bubble_step];
        var new_title       =   new_info.title;
        var new_selector    =   new_info.selector;
        var new_content     =   new_info.content;
        var new_css         =   new_info.css;
        var new_left        =   new_info.left;
        var new_top         =   new_info.top;
        var new_right       =   new_info.right;
        var new_arrow       =   new_info.arrow;

        // Fill in title and content.
        current_bubble.find('#wpstream_onboard_bubble_tile').html(new_title);
        current_bubble.find('#wpstream_onboard_bubble_content').html(new_content);
        // Record the current step index on the bubble.
        current_bubble.attr('data-bubble-step',current_bubble_step);
        // Reset then apply the arrow direction class.
        current_bubble.removeClass('boarding-left-arrow boarding-right-arrow');
        current_bubble.addClass(new_arrow);
        // Position the bubble.
        current_bubble.css({
            'left':new_left,
            'right':new_right,
            'top':new_top
        });


        // Show "next" if another step follows, otherwise show "finish".
        if( BubbleFreeVod[branch].hasOwnProperty(parseInt(current_bubble_step+1)) ){
            current_bubble.find('.wpstream_onboard_bubble_next').show();
            current_bubble.find('.wpstream_onboard_bubble_finish').hide();
        }else{
            current_bubble.find('.wpstream_onboard_bubble_next').hide();
            current_bubble.find('.wpstream_onboard_bubble_finish').show().css('display','inline-block');
        }



        // Attach the bubble next to this step's target element.
        jQuery(new_selector).append(current_bubble);

        // Scroll the target (with some headroom) into view.
        jQuery('html, body').animate({
            scrollTop: jQuery(new_selector).offset().top-200
        }, 1000);

        // (Re)bind the per-step interactive triggers used by some bubbles.
        wpstream_on_boarding_trigger_event_start_channel();
        wpstream_on_boarding_trigger_event_open_channel();
        wpstream_on_boarding_open_vod_page();
        wpstream_start_camera();

    }


    /*
    * Trigger Start Event
    *
    */

    /**
     * Keep the "turn on channel" bubble in sync with the channel's live state:
     * update the bubble copy while turning on, once on, and when ready to go
     * live, or wire up the TURN ON button when the channel is still off.
     *
     * @return {void}
     */
    function wpstream_on_boarding_trigger_event_start_channel(){
        // Case 1: the channel is in the middle of turning on.
        if(jQuery('.start_event').hasClass('wpstream_turning_on')){

            // Hide the in-bubble TURN ON button.
            jQuery('#wpstream_onboarding_start_chanel').hide();
            var bubble_Step =jQuery('#wpstream_onboard_bubble').attr('data-bubble-step');


            // The relevant step index differs between the free and WooCommerce (PPV) layouts.
            var check_against='3';
            if(jQuery('#woocommerce-product-data').length>0){
                check_against='5';
            }


            // On the matching step, show the "turning on" copy.
            if(bubble_Step===check_against ){
                jQuery('#wpstream_onboard_bubble_tile').text('Turning ON ');
                jQuery('#wpstream_onboard_bubble_content').text('Good, the channel is now turning on. This may take a minute or so. You can wait or move on to the next step.');
            }

        }else if( jQuery('.event_list_unit .wpstream_button').hasClass('wpstream_stop_event') ){
            // Case 2: the channel is fully on (button now offers to stop it).
            jQuery('#wpstream_onboarding_start_chanel').hide();

            // Step indices for the "channel on" and "go live" bubbles (layout dependent).
            var bubble_Step =jQuery('#wpstream_onboard_bubble').attr('data-bubble-step');
            var check_against='3';
            check_against_camera_icon='5';
            if(jQuery('#woocommerce-product-data').length>0){
                check_against='5';
                check_against_camera_icon='7';
            }


            // On the "channel on" step, show the ready-to-go-live copy.
            if(bubble_Step===check_against){
                jQuery('#wpstream_onboard_bubble_tile').text('Channel is now ON');
                jQuery('#wpstream_onboard_bubble_content').text('You are ready to GO LIVE. Click Next to see how.');
            }


            // On the "go live" step, prompt the camera icon and enable it.
            if(bubble_Step===check_against_camera_icon){
                jQuery('#wpstream_onboard_bubble_tile').html('Go LIVE');
                jQuery('#wpstream_onboard_bubble_content').html('Go Live now, click the <div class=\"wpstream_sample_icon_settings wpstream_sample_icon_camera\"></div> icon. The broadcast app will open in a new window.');
                wpstream_start_camera();
            }




        }else {
            // Case 3: the channel is off. Show the TURN ON button.
            jQuery('#wpstream_onboarding_start_chanel').show();

            // Clicking it starts the channel and updates the copy to "turning on".
            jQuery('#wpstream_onboarding_start_chanel').on('click',function(){
                start_onboarding = 'yes';
                jQuery('.start_event.wpstream_button').trigger('click');
                jQuery('#wpstream_onboarding_start_chanel').unbind('click');
                jQuery('#wpstream_onboard_bubble_tile').text('Turning ON');
                jQuery('#wpstream_onboard_bubble_content').text('Good, the channel is now turning on. This may take a minute or so. You can wait or move on to the next step.');
            })

        }

    }


    /*
    * Trigger open page
    *
    */

    /**
     * Bind the in-bubble "open channel" action to open the channel's public page
     * in a new tab.
     *
     * @return {void}
     */
    function wpstream_on_boarding_trigger_event_open_channel(){
        jQuery('#wpstream_onboarding_open_chanel').on('click',function(){
            // Open the channel's public "view" link in a new tab.
            var link = jQuery('.wpstream_view_channel').attr('href');
            window.open(link, '_blank');
        })
    }


    /*
    * Trigger VOD Page
    *
    */

    /**
     * Bind the in-bubble "view VOD page" action to open the VOD's permalink in a
     * new tab.
     *
     * @return {void}
     */
    function wpstream_on_boarding_open_vod_page(){
        jQuery('#wpstream_onboarding_view_vod').on('click',function(event){
            // Don't let the click bubble up to the surrounding bubble.
            event.stopPropagation();
            // Open the post's sample-permalink in a new tab.
            var link = jQuery('#sample-permalink a').attr('href');
            window.open(link, '_blank');

        });
    }



    /*
    * Close Modal
    *
    */
    /**
     * Bind the bubble's close/finish controls (and the ESC key) to the shared
     * close-modal logic.
     *
     * @return {void}
     */
    function wpstream_on_boarding_close(){
        // Close/finish buttons run the close logic.
        jQuery('.wpstream_close_onboarding,.wpstream_onboard_bubble_finish').on('click',function(){
			wpstream_onboarding_close_modal_logic(this);
        });
		// ESC key inside the wizard also closes it.
		jQuery('.wpstream_on_boarding_wrapper').keydown(function(e) {
			if ( e.keyCode === 27 ) { // ESC key
				wpstream_onboarding_close_modal_logic(this);
			}
		});
    }

	/**
	 * Turn the current bubble into a "You did it!" closing card with a final
	 * acknowledge button and report the close event.
	 *
	 * @param {Element} context The element/DOM node whose parent holds the bubble.
	 * @return {void}
	 */
	function wpstream_onboarding_close_modal_logic(context) {
		// Report the close to telemetry.
		wpstream_track_onboarding_step( 'close_onboarding', 'close_modal_acknowledge' );
		// Hide the finish button.
		jQuery('.wpstream_onboard_bubble_finish').hide();
		var parent_modal=jQuery(context).parent();

		// Hide the close control and prev/next; swap in the success title.
		parent_modal.find('.wpstream_close_onboarding').hide();
		parent_modal.find('#wpstream_onboard_bubble_tile').html('You did it!');
		parent_modal.find('.wpstream_onboard_bubble_prev,.wpstream_onboard_bubble_next').hide();

		// Replace the content with the "run again later" note and acknowledge button.
		parent_modal.find('#wpstream_onboard_bubble_content').html('You can run the wizard again at any time. </br>In the left menu navigate to WpStream -> WpStream Quick Start </br> <div id="wpstrean_close_modal_ack" class="wpstrean_close_modal_ack_action">Okay, Close it now!</div>');
		// parent_modal.find('#wpstream_onboard_bubble_content').after('<div id="wpstrean_close_modal_ack" class="wpstrean_close_modal_ack_action">Okay, Close it now!</div>');

		// Wire up the acknowledge button.
		wpstrean_close_modal_ack_function();
	}


    /*
    * Bubble/Modal Controls
    * 
    */


    /**
     * Bind the bubble next/prev controls to move through the tour, clamping to
     * the branch bounds (going below the first step re-opens the account modal)
     * and reporting each navigation to telemetry.
     *
     * @param {number} branch Branch index into BubbleFreeVod.
     * @return {void}
     */
    function wpstream_bubble_controls(branch){

        jQuery('.wpstream_onboard_bubble_next,.wpstream_onboard_bubble_prev').on('click',function(){

            // Read and parse the current step index.
            var current_bubble_step =   jQuery('#wpstream_onboard_bubble').attr('data-bubble-step');

            current_bubble_step     =   parseInt(current_bubble_step,10);
            // Advance or go back, reporting the direction to telemetry.
            if( jQuery(this).hasClass('wpstream_onboard_bubble_next')){
                current_bubble_step++;
	            wpstream_track_onboarding_step( 'onboard_wpstream_navigation_' + branch_to_string(wpstreamonboarding_js_vars.branch), 'onboarding_step_' + current_bubble_step, 'button', 'next' );
            }else{
                current_bubble_step--;
				wpstream_track_onboarding_step( 'onboard_wpstream_navigation_' + branch_to_string(wpstreamonboarding_js_vars.branch), 'onboarding_step_' + current_bubble_step, 'button', 'prev' );
            }

            // Going back past the first step returns to the account/quick-start modal.
            if( current_bubble_step<0){
                current_bubble_step=0;
                var url      = window.location.href;
                jQuery('#wpstream_onboard_bubble,.wpstream_modal_background_onboard2').hide();

                jQuery('.wpstream_on_boarding_wrapper').show();
                jQuery('.wpstream_modal_background_onboard').show();

            }

            // Clamp the step to the last bubble in this branch.
            var max_length = BubbleFreeVod[branch].length-1;

            if( current_bubble_step>max_length){
                current_bubble_step=max_length;
            }

            // Render the resulting bubble.
            wpstream_show_bubble(current_bubble_step,branch)



        })
    }


    // The module exposes no public API; all behaviour is bound internally.
    return {

    }
})();


// Placeholder DOMContentLoaded listener (currently no-op).
window.addEventListener('DOMContentLoaded', function() {

});

