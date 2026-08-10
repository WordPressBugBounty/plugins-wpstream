/*global $, jQuery, wpstream_start_streaming_vars*/
/*
 * start_streaming.js
 *
 * Front-end controller for the WpStream "start streaming" dashboard, where a
 * broadcaster manages their FTV/PPV live channels and VODs. It binds the
 * Start/Stop buttons, polls channel status over AJAX, reveals the OBS/RTMP
 * credentials and Larix mobile QR links when a channel goes live, drives the
 * per-event settings modals (low-latency / adaptive-bitrate / encrypt), the
 * webcaster (browser broadcaster) launcher, copy-to-clipboard helpers, and the
 * onboarding bubble hints.
 *
 * Relies on the localized `wpstream_start_streaming_vars` object (admin URL,
 * i18n strings, feature flags) and reports onboarding telemetry via the
 * optional global wpstream_track_onboarding_step().
 */
// Registry of active setInterval poll timers, keyed by show id (and "stop"+id).
var counters={};

// post_type can be FTV or PPV channel or VOD
var post_type = null;

/**
 * Document-ready bootstrap: resolve the current post type and wire up every
 * interactive behavior on the start-streaming dashboard.
 */
jQuery(document).ready(function ($) {
    "use strict";

	// Resolve which channel/VOD type this page manages from the URL.
	wpstream_set_current_post_type();
    // Begin polling any channels already flagged as pending/live.
    wpstream_check_live_connections();
    // Wire the settings/broadcast modal open/close behavior.
    wpestate_start_modal_actions();
    // Wire the error modal's dismiss button.
    wpestate_start_modal_error_actions();
    // Enable hover tooltips.
    wpstream_tooltip();
    // Enable copy-to-clipboard buttons.
    wpstream_copy_to_clipboard();
    // Commenting the following line because we are using the new webcaster
    // Bind the webcaster (browser broadcaster) launcher.
    wpstream_webcaster_actions();
    // Persist per-event streaming option toggles on change.
    wpstream_save_options_actions();
	// Persist the per-event "use global settings" toggle.
	wpstream_save_use_global_event_options();
    // Bind all Start/Stop channel buttons.
    wpstream_bind_start_and_stop();

    // Guard stats/webcaster icons while they are inactive.
    wpstream_bind_stats_link();
    // Wire the general (global) settings toggles.
    wpstream_adjust_settings_general();

});

/**
 * Fire an onboarding telemetry event, but only if the tracking helper exists.
 *
 * @param {string} action       Event/action name.
 * @param {string} step         Current onboarding step identifier.
 * @param {string} element_type Element kind that triggered it (default 'button').
 * @param {string} element_name Optional element name/detail.
 */
function wpstream_safe_track_onboarding(action, step, element_type = 'button', element_name = '') {
	// Guard: the onboarding tracker is only present during onboarding.
	if (typeof wpstream_track_onboarding_step === 'function') {
		// Forward the event to the tracker.
		wpstream_track_onboarding_step(action, step, element_type, element_name);
	}
}

/**
 * Determine the post type managed by this page from the ?branch= query param
 * and store it in the module-level `post_type`.
 */
function wpstream_set_current_post_type() {
	// Read the query string of the current URL.
	let params = (new URL(document.location)).searchParams;
	// Extract the numeric ?branch= selector.
	let branch= params.get( 'branch' );
	branch = parseInt( branch );

	// A non-numeric branch means no specific post type.
	if( isNaN( branch ) ) {
		post_type = null;
	}

	// Map the branch number to a WpStream post type.
	switch ( branch ) {
		// Branch 1 -> free live channel.
		case 1:
			post_type = 'ftv_channel';
			break;
		// Branch 2 -> pay-per-view live channel.
		case 2:
			post_type = 'ppv_channel';
			break;
		// Branch 3 -> free VOD.
		case 3:
			post_type = 'ftv_vod';
			break;
		// Branch 4 -> pay-per-view VOD.
		case 4:
			post_type = 'ppv_vod';
			break;
		// Anything else -> no type.
		default:
			post_type = null;
	}
}

/*
*
* genereal settings - low latency vs bitrate vs encrypt
*
*/


/**
 * Wire the global (theme-options) streaming toggles so that low-latency /
 * adaptive-bitrate and encryption remain mutually exclusive.
 */
function wpstream_adjust_settings_general(){
   
    // On any global option slider click:
    jQuery('.theme_options_tab_wpstream .wpstream_slider').on('click',function(event){
        // Keep the click from bubbling to parent handlers.
        event.stopPropagation();
        // Locate the toggle's row and its checkbox input.
        var parent = jQuery(this).parent();
        var input_element = parent.find('.wpstream_event_option_item');
        // The ajax name identifies which setting was toggled.
        var attr_value = input_element.attr('data-attr-ajaxname');
      

        // Turning on low-latency or adaptive-bitrate disables encryption.
        if( attr_value === 'adaptive_bitrate' || attr_value==='low_latency'){
            
            // Defer briefly so the checkbox state settles first.
            var timer = setTimeout(function() {
               
                // If either speed option is now checked, clear the encrypt option.
                if( jQuery('.wpstream-setting-adaptive_bitrate .wpstream_event_option_item').is(':checked') || 
                    jQuery('.wpstream-setting-low_latency .wpstream_event_option_item').is(':checked') ){
                    jQuery('.wpstream-setting-encrypt .wpstream_event_option_item') .removeAttr('checked'); 
                }   
            }, 300);

        }

        // Turning on encryption disables the speed options.
        if( attr_value === 'encrypt'){
            // Defer briefly so the checkbox state settles first.
            var timer = setTimeout(function() {
               
                // If encrypt is now checked, clear the speed options.
                if( jQuery('.wpstream-setting-encrypt .wpstream_event_option_item').is(':checked') ){
                    jQuery('.wpstream-setting-low_latency .wpstream_event_option_item') .removeAttr('checked'); 
                    jQuery('.wpstream-setting-adaptive_bitrate .wpstream_event_option_item') .removeAttr('checked'); 
                }
            }, 300);

        }

    });
}










/*
*
* Bind block stats link
*
*/

/**
 * Prevent clicks on statistics/webcaster icons while they are marked inactive.
 */
function wpstream_bind_stats_link(){
 
    // For each stats/webcaster icon:
    jQuery('.wpstream_statistics_channel,.start_webcaster').each(function(event){
        // Cache the icon element.
        var selected_icon = jQuery(this);
            // Intercept its clicks.
            selected_icon.on('click',function(event){
                // When inactive, swallow the click entirely.
                if(selected_icon.hasClass('wpstream_inactive_icon')){
                    event.preventDefault();
                    event.stopPropagation();
                }
                
            });
        
    })


}




/*
*
* Bind Start and Stop channel
*
*/
    

/**
 * Bind click handlers to every Start button and every Stop button on the page.
 */
function wpstream_bind_start_and_stop(){
    
    // Bind each Start button.
    jQuery('.start_event.wpstream_button').each(function(element){
        // Cache this button.
        var start_button=jQuery(this);    
        // Attach the start-channel behavior.
        wpstream_bind_start_event(start_button);
    });


    // Bind each Stop button.
    jQuery('.wpstream_stop_event').each(function(element){
        // Cache this button.
        var stop_button=jQuery(this);
        // Attach the stop-channel behavior.
        wpstream_bind_stop_event(stop_button);
    });

}


/*
*
* Bind start  channel action
*
*/
// Onboarding flag forwarded to the start-channel AJAX call (set elsewhere).
var start_onboarding='';
/**
 * Bind the "start channel" click behavior to a Start button: warn for basic
 * plans, request live credentials over AJAX, and begin polling channel status.
 *
 * @param {jQuery} button The Start button element.
 */
function wpstream_bind_start_event(button){
   
    // On click:
    button.click(function(event){
        // Telemetry: a stream start was attempted.
        wpstream_safe_track_onboarding('stream_attempted', 'wpstream_' + post_type);
		wpstream_safe_track_onboarding('start_stream_clicked', 'wpstream_' + post_type);
        // Basic-plan users get a usage warning before starting.
        if (wpstream_start_streaming_vars.is_basic_streaming) {
	        // Warn about streaming-hours consumption...
	        if (wpstream_start_streaming_vars.use_streaming_hours) {
		        // ...and abort if they decline.
		        if (!confirm(wpstream_start_streaming_vars.basic_streaming_warning)) {
			        return false;
		        }
	        } else {
		        // Otherwise warn about traffic consumption and abort if they decline.
		        if (!confirm(wpstream_start_streaming_vars.basic_streaming_warning_traffic)) {
			        return false;
		        }
	        }
        }
        // Stop the default button/link action.
        event.preventDefault();
        // Prevent double-submits by unbinding the handler.
        button.unbind('click');
        // admin-ajax.php endpoint.
        var ajaxurl             =   wpstream_start_streaming_vars.admin_url + 'admin-ajax.php';
        // Cache the clicked button.
        var acesta              =   jQuery(this);
        // Notification area for this event row.
        var notification_area   =   jQuery(this).parent().find('.event_list_unit_notification');
        // Server-notification container.
        var curent_content      =   jQuery(this).parent().find('.server_notification');
        // Whether to record this broadcast.
        var is_record           =   false;
        // Whether to encrypt this broadcast.
        var is_encrypt          =   false;
        // The channel/show id.
        var show_id             =   parseFloat( jQuery(this).attr('data-show-id') );
        // CSRF nonce for the AJAX call.
        var nonce               =   jQuery('#wpstream_start_event_nonce').val();
        // The button's parent container.
        var parent              =   jQuery(this).parent();
        
        // Read the "record" toggle.
        if( jQuery(this).parent().find('.record_event').is(":checked") ){
            is_record   =   true;
        }
        
        // Read the "encrypt" toggle.
        if( jQuery(this).parent().find('.encrypt_event').is(":checked") ){
            is_encrypt   =   true;
        }
        // Disable the settings icon while the channel is starting.
        parent.find('.wpstream_show_settings').addClass('wpstream_inactive_icon');
        // Show the "turning on" spinner/label on the button.
        jQuery(this).addClass('wpstream_turning_on').empty().html(wpstream_start_streaming_vars.start_streaming_action+'<div class="wpstream_loader"></div><div class="wpstream_tooltip">'+wpstream_start_streaming_vars.turning_on_tooltip+'</div>');
        // Update the status label to "turning on".
        parent.find('.wpstream_channel_status').text(wpstream_start_streaming_vars.channel_turning_on);

        // Ask the server for live credentials / to start the channel.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            timeout: 300000,

            data: {
                'action'            :   'wpstream_give_me_live_uri',
                'security'          :   nonce,
                'show_id'           :   show_id,
                'is_record'         :   is_record,
                'is_encrypt'        :   is_encrypt,
                'start_onboarding'  :   start_onboarding,
               
                
            },
            // On success:
            success: function (data) {
                
              
       
                // API reachable.
                if(data.conected===true){
             
                        // err1: generic start failure.
                        if(data.event_data==='err1'){
							wpstream_safe_track_onboarding('stream_start_failed', 'wpstream_' + post_type, 'system', 'err1');
                        
                            wpstream_show_error_on_start(wpstream_start_streaming_vars.error1,parent)
                        
                        // Event creation failed on the backend.
                        }else if(data.event_data ==='failed event creation'){
							wpstream_safe_track_onboarding('stream_start_failed', 'wpstream_' + post_type, 'system', 'failed_event_creation');
                        
                            wpstream_show_error_on_start(wpstream_start_streaming_vars.failed_event_creation,parent)
                        
                        // Success: begin polling this channel's status every 10s.
                        }else{
                            // Clear any stale server notification.
                            curent_content.empty();
                            // Poll live status; store the timer so it can be cleared later.
                            var counter =  setInterval( function (){
								wpstream_check_live_connections_on_start(parent,show_id,data.event_data,data)
                            },10000);
                            counters['stop'+show_id]=counter;
                    
                        }
                        
            
                // API not connected: show the returned error.
                }else{
					wpstream_safe_track_onboarding('stream_start_failed', 'wpstream_' + post_type, 'system', 'api_not_connected');
                    wpstream_show_error_on_start(data.error,parent)
                }
                
            },
            // On transport error, report telemetry only.
            error: function (jqXHR,textStatus,errorThrown) {             
				wpstream_safe_track_onboarding('stream_start_failed', 'wpstream_' + post_type, 'system', 'ajax_error');
            }
        });
        
    });
    
}


/*
*
* Bind Stop channel action
*
*/

/**
 * Bind the "stop channel" click behavior to a Stop button: confirm, request the
 * channel be turned off over AJAX, and poll until it reports stopped.
 *
 * @param {jQuery} button The Stop button element.
 */
function wpstream_bind_stop_event(button){
    // On click:
    button.click(function(event){
		// Telemetry: stop clicked.
		wpstream_safe_track_onboarding( 'stop_stream_clicked', 'wpstream_' + post_type );


        // Confirm before stopping.
        if(!confirm(wpstream_start_streaming_vars.turn_off_confirm)){
            return false;
        }
        // Prevent double-submits by unbinding the handler.
        button.unbind('click');


        // admin-ajax.php endpoint.
        var ajaxurl             =   wpstream_start_streaming_vars.admin_url + 'admin-ajax.php';
        // The channel/show id.
        var show_id             =   parseFloat( jQuery(this).attr('data-show-id') );
        // CSRF nonce for the AJAX call.
        var nonce               =   jQuery('#wpstream_start_event_nonce').val();
        // The button's parent container.
        var parent              =   jQuery(this).parent();
        // Cache the clicked button.
        var thisButton          =   jQuery(this);
      
        // Flip the button out of its stop state...
        thisButton.removeClass('wpstream_stop_event');
        // ...and into a "turning off" spinner/label.
        thisButton.addClass('wpstream_turning_on').empty().html(wpstream_start_streaming_vars.stop_streaming_action+'<div class="wpstream_loader"></div><div class="wpstream_tooltip">'+wpstream_start_streaming_vars.turning_off_tooltip+'</div>');
        // Update the status label to "turning off".
        parent.find('.wpstream_channel_status').text(wpstream_start_streaming_vars.channel_turning_off);
        // Disable the webcaster/pro/live-data icons while stopping.
        parent.find('.start_webcaster').addClass('wpstream_inactive_icon');
        parent.find('.wpstream_stream_pro').addClass('wpstream_inactive_icon');
        parent.find('.wpstream_live_data').addClass('wpstream_inactive_icon');



        // Ask the server to turn off the channel.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            timeout: 300000,

            data: {
                'action'            :   'wpstream_turn_of_channel',
                'security'          :   nonce,
                'show_id'           :   show_id
               
                
            },
            // On success:
            success: function (data) {

                // API reachable: poll until the channel reports stopped.
                if(data.conected===true){
                    
                    // Prevent further clicks on the button.
                    thisButton.unbind('click');
         
                    // Poll status every 10s; store the timer.
                    var counter =  setInterval( function (){ 
                        wpstream_check_live_connections_on_start(parent,show_id,'',data)},10000);
                        counters["stop"+show_id]=counter;

                // API not connected: show the error.
                }else{
               
                    wpstream_show_error_on_stop(data.error,parent)
                }
                
            },
            // Transport error: no-op.
            error: function (jqXHR,textStatus,errorThrown) {
             
            }
        });
        
    
    
    });
    
}


/*
*
* Enable copy to clipboard
*
*/

/**
 * Enable copy-to-clipboard for the live URI and stream key within a container.
 *
 * @param {jQuery|Element} parent Container holding the copy buttons and text.
 */
function wpstream_enable_cliboard(parent){
    
    // Copy the live URI when its button is clicked.
    jQuery(parent).find('.copy_live_uri').click(function(){
      
        // Read the URI text.
        var value_uri = jQuery(parent).find('.wpstream_live_uri_text').text();
        // Use a throwaway input to run execCommand('copy').
        var temp = jQuery("<input>");
        // Attach it to the document.
        jQuery("body").append(temp);
        // Select its value.
        jQuery(temp).val(value_uri).select();
        // Copy to clipboard.
        document.execCommand("copy");
        // Clean up the throwaway input.
        jQuery(temp).remove();
        
    });
    
    // Copy the stream key when its button is clicked.
    jQuery(parent).find('.copy_live_key').click(function(){
        // Read the key text.
        var value_uri = jQuery(parent).find('.wpstream_live_key_text').text();
        // Throwaway input for the copy command.
        var temp = jQuery("<input>");
        // Attach it to the document.
        jQuery("body").append(temp);
        // Select its value.
        jQuery(temp).val(value_uri).select();
        // Copy to clipboard.
        document.execCommand("copy");
        // Clean up the throwaway input.
        jQuery(temp).remove();
        
    });
}


/*
 *
 * Check live events/channels on starts 
 * 
 * 
 * 
*/


/**
 * Poll callback used right after Start: when the channel becomes active, reveal
 * the OBS/RTMP credentials, Larix mobile links and QR codes, switch to slow
 * (60s) database polling, and handle stopped/error states.
 *
 * @param {jQuery} parent    The event row container.
 * @param {number} show_id   Channel/show id.
 * @param {string} server_id Backend server id for the event.
 * @param {Object} data      Original AJAX payload from the start request.
 */
function wpstream_check_live_connections_on_start( parent,show_id,server_id,data){
      
    // server_url is dns change
  
    // Query the channel status; the callback fires with the result.
    wpstream_check_event_status_in_js(show_id,'wpstream_check_live_connections_on_start',
        function(server_status){
      
            // Channel is live:
            if(server_status.status==='active' ){
                // Store the webcaster URL on the launcher.
                parent.find('.start_webcaster').attr('data-webcaster-url',server_status.webcaster_url);
                // Show the OBS ingest URI.
                parent.find('.wpstream_live_uri_text').text(server_status.obs_uri);
                // Show the OBS stream key.
                parent.find('.wpstream_live_key_text').text(server_status.obs_stream);

                // Reveal the "live" action buttons.
                wpstream_event_ready_make_actions_visible(parent);

                // Build the combined RTMP URL for Larix.
                var larix_rtmp= server_status.obs_uri+server_status.obs_stream;
                // Display it.
                parent.find('.wpstream_larix_rtmp').text(larix_rtmp); 

                // Build the Larix deep-link (mobile app config).
                var   larix_qr ='larix://set/v1?conn[][url]='+encodeURIComponent(larix_rtmp);
                // Point the mobile-start link at it.
                parent.find('.wpstream_start_with_larix_mobile').attr('href',larix_qr); 

                // Display the RTMP URL in the info and test fields.
                parent.find('.wpstream_larix_rtmp').text(larix_rtmp); 
                parent.find('.larrix_test').text(larix_rtmp); 
              

                // Generate the QR locally (SEC-05): the RTMP URL + secret stream key
                // encoded in larix_qr must never be sent to a third-party image service.
                wpstreamRenderQr( parent.find('.print_qrcode'), larix_qr );
                
                // Link the live-data button to the stats URL.
                parent.parent().find('.wpstream_live_data').attr('href',server_status.live_data_url);         
                // Stop the fast (10s) start poll.
                clearInterval( counters["stop"+show_id]);

				// Clear any existing slow poll for this channel.
				if (counters[show_id]) {
					clearInterval(counters[show_id]);
				}
				// Read the server id from context.
				var server_id   =   jQuery(this).attr('data-server-id');
				// Start slow (60s) database status polling.
				counters[show_id] = setInterval( function (){
					wpstream_check_live_connections_from_database(parent,show_id,server_id);
				},60000);

                // Fire optional integration notifications.
                if (typeof wpstream_integration_notifications === 'function') {
                    wpstream_integration_notifications(show_id);
                }

                
            // Channel stopped:
            }else if(server_status.status==='stopped' ){    
             // (debug log)
             console.log('stopped status from _on_start');
                // Stop the fast poll.
                clearInterval( counters["stop"+show_id]);
                // Restore the stopped-state UI.
                wpstream_event_stopped_make_actions(parent);

            // Channel errored:
            }else if(server_status.status==='error' ){
                // Telemetry: start failed with an error status.
                wpstream_safe_track_onboarding('stream_start_failed', 'wpstream_' + post_type, 'system', 'status_error');
               
                // Stop the fast poll.
                clearInterval( counters["stop"+show_id]);
                // Show a failure message in the status area.
                var curent_content = parent.find('.wpstream_channel_status');
                curent_content.html('<div class="wpstream_channel_status not_ready_to_stream"><span class="dashicons dashicons-dismiss"></span>'+wpstream_start_streaming_vars.failed_event_creation+'</div>');
            }
    });

}




/*
 * check channell id status
 * 
 * @param {type} channel_id
 * @param {type} callback
 * @returns {undefined}
 * 
 * 
*/

/**
 * Query a channel's current status via admin-ajax and route the result to the
 * supplied callbacks.
 *
 * @param {number}   channel_id      Channel id to check.
 * @param {string}   notes           Caller tag (for server-side logging).
 * @param {Function} successCallback Receives the status object, or false when
 *                                   the status is not yet resolved.
 * @param {Function} errorCallback   Called on AJAX transport error.
 */
function wpstream_check_event_status_in_js(channel_id,notes,successCallback, errorCallback){

    // admin-ajax.php endpoint.
    var ajaxurl = wpstream_start_streaming_vars.admin_url + 'admin-ajax.php';
	// CSRF nonce for the AJAX call.
	var nonce = jQuery('#wpstream_start_event_nonce').val();

    // Request the channel status.
    jQuery.ajax({
        type: 'POST',
        dataType: 'json',
        url: ajaxurl,
        timeout: 3000000,

        data: {
            'action'            :   'wpstream_check_event_status',
            'channel_id'        :   channel_id,
            'notes'             :   notes,
	        'nonce'             :   nonce
        },
        // On success:
        success: function (data) {

            // The response object.
            var obj = data;
            // Its status field.
            var channel_status = obj.status;

            // Resolved active/stopped: pass the object through.
            if(channel_status=='active' || channel_status=='stopped'  ){
                successCallback(obj);
            // Error status: also pass it through.
            }else if(channel_status=='error'){
                successCallback(obj);
            // Not yet resolved: signal "not ready" with false.
            }else{
                successCallback(false);
            }
            
        // On transport error, invoke the error callback.
        }, error: function (jqXHR,textStatus,errorThrown) {
            errorCallback();
        }
  });
}


/*
*
* Make actions visible on event if ready
*
*
*/
/**
 * Switch an event row into its "live" state: turn the Start button into a Stop
 * button, toggle icon availability, update the status label, and advance the
 * onboarding bubble.
 *
 * @param {jQuery} parent The event row container.
 */
function wpstream_event_ready_make_actions_visible(parent){

    // The current Start button.
    var actionButton = parent.find('.start_event');
    // Mark the row as started.
    parent.addClass('wpstream_show_started');
    // Drop the start handler...
    actionButton.unbind('click');
    // ...and bind the stop handler instead.
    wpstream_bind_stop_event(actionButton);
    
    // Repaint the button as a Stop button.
    actionButton.addClass('wpstream_stop_event').removeClass('start_event').html(wpstream_start_streaming_vars.stop_streaming+'<div class="wpstream_tooltip">'+wpstream_start_streaming_vars.turned_off_tooltip+'</div>');
    // Enable the row's action icons.
    parent.find('.wpstream-button-icon').removeClass('wpstream_inactive_icon');
    // Disable the settings icon while live.
    parent.find('.wpstream_show_settings').addClass('wpstream_inactive_icon');
    // Update each status label.
    const channelStatus = parent.find('.wpstream_channel_status');
	// For each status element:
	channelStatus.each(function( index, element ) {
		// Leave failure labels hidden.
		if ( jQuery(element).hasClass('not_ready_to_stream') ) {
			jQuery(element).hide();
			return;
		}
		// Otherwise show the "on" label...
		jQuery(element).text(wpstream_start_streaming_vars.channel_on);
		// ...and fade it in if it was hidden.
		if ( jQuery(element).css('display') === 'none' ) {
			jQuery(element).fadeIn(200);
		}
	});
	// Reveal the Stop button if it was hidden.
	const stopEventButton = parent.find('.wpstream_stop_event');
	if ( stopEventButton.css('display') === 'none' ) {
		stopEventButton.fadeIn(200);
	}

    //for onboarding
    // Onboarding bubble step targets.
    var check_against='3';
    var check_against_camera_icon='5';
    // Steps differ on the WooCommerce product screen.
    if(jQuery('#woocommerce-product-data').length>0){
        check_against='5'
        check_against_camera_icon='7';
    }       
   
    
    // The current onboarding bubble step.
    var bubble_Step =jQuery('#wpstream_onboard_bubble').attr('data-bubble-step');

    // At the "channel on" step, update the bubble text.
    if(bubble_Step===check_against){
        jQuery('#wpstream_onboard_bubble_tile').text('Channel is now ON');
        jQuery('#wpstream_onboard_bubble_content').text('You are ready to GO LIVE. Click Next to see how.');
    }
    // At the "go live" step, prompt the user to open the broadcaster.
    if(bubble_Step===check_against_camera_icon){
        jQuery('#wpstream_onboard_bubble_tile').html('Go LIVE');
        jQuery('#wpstream_onboard_bubble_content').html('To Go Live now, click the <div class=\"wpstream_sample_icon_settings wpstream_sample_icon_camera\"></div> icon. The broadcast app will open in a new window.');
        
        // Once live, make the sample camera icon clickable.
        if( jQuery('.event_list_unit .wpstream_button').hasClass('wpstream_stop_event') ){
            // Show a pointer cursor.
            jQuery('.wpstream_sample_icon_camera').css('cursor','pointer');
          
            // Clicking it triggers the real webcaster launch.
            jQuery('.wpstream_sample_icon_camera').on('click',function(){
                jQuery('.start_webcaster').trigger('click');
                jQuery(this).unbind('click');

            })   
        }

    }

	// console.log('adding pending trigger');
	// parent.addClass('pending_trigger');
	// console.log('adding check for status');
	// // wpstream_check_live_connections();
	// console.log('parent ' , parent);
}

/**
 * Put an event row into an error state: hide the Start button and status label
 * and show a "failed fetching" notice.
 *
 * @param {jQuery} parent The event row container.
 */
function wpstream_event_error_make_actions_visible(parent){
    // The Start button.
    var actionButton = parent.find('.start_event');
    // The status label(s).
    const channelStatus = parent.find('.wpstream_channel_status');
    // Drop the click handler.
    actionButton.unbind('click');
    // Hide the button.
    actionButton.hide();
    // Hide the status label.
    channelStatus.hide();

    // Show a fetch-failure message.
    parent.find('.server_notification').html('<div class="wpstream_channel_status not_ready_to_stream"><span class="dashicons dashicons-dismiss"></span>'+wpstream_start_streaming_vars.failed_fetching+'</div>');
}


/*
*
* Make actions visible on event if stopped
*
*
*/
/**
 * Restore an event row to its stopped/off state: turn the button back into a
 * Start button and reset icon availability and the status label.
 *
 * @param {jQuery} parent The event row container.
 */
function wpstream_event_stopped_make_actions(parent){

    // The button currently in its "turning on" state.
    var  actionButton = parent.find('.wpstream_turning_on');
    // Re-bind the start handler.
    wpstream_bind_start_event(actionButton);
    // Clear the started marker.
    parent.removeClass('wpstream_show_started');
    // Drop the transitional class...
    actionButton.removeClass('wpstream_turning_on');
    // ...and restore the Start class.
    actionButton.addClass('start_event');
    // Repaint the Start label/tooltip.
    actionButton.html( wpstream_start_streaming_vars.start_streaming+'<div class="wpstream_tooltip">'+wpstream_start_streaming_vars.turned_on_tooltip+'</div>');

    // Enable the action icons.
    parent.find('.wpstream-button-icon').removeClass('wpstream_inactive_icon');
    // Disable the pro icon.
    parent.find('.wpstream_stream_pro').addClass('wpstream_inactive_icon');
    // Disable the webcaster icon.
    parent.find('.start_webcaster').addClass('wpstream_inactive_icon');
    // Disable the statistics icon.
    parent.find('.wpstream_statistics_channel').addClass('wpstream_inactive_icon');

    // Set the status label to "off".
    parent.find('.wpstream_channel_status').text(wpstream_start_streaming_vars.channel_off);
}

/*
*
* Make actions visible on event if stopped
*
*
*/
/**
 * Like wpstream_event_stopped_make_actions, but used after a status poll finds
 * the channel stopped; also handles the case where the button is still in its
 * Stop state.
 *
 * @param {jQuery} parent The event row container.
 */
function wpstream_event_stopped_after_status_check(parent){

	// Prefer the "turning on" button...
	var actionButton = parent.find('.wpstream_turning_on');
	// ...else fall back to the Stop button.
	if (actionButton.length === 0) {
		actionButton = parent.find('.wpstream_stop_event');
	}
	// Drop existing handlers.
	actionButton.unbind('click');
	// Re-bind the start handler.
	wpstream_bind_start_event(actionButton);
	// Clear the started marker.
	parent.removeClass('wpstream_show_started');
	// Drop the transitional/stop classes...
	actionButton.removeClass('wpstream_turning_on wpstream_stop_event');
	// ...and restore the Start class.
	actionButton.addClass('start_event');
	// Repaint the Start label/tooltip.
	actionButton.html( wpstream_start_streaming_vars.start_streaming+'<div class="wpstream_tooltip">'+wpstream_start_streaming_vars.turned_on_tooltip+'</div>');

	// Enable the action icons.
	parent.find('.wpstream-button-icon').removeClass('wpstream_inactive_icon');
	// Disable the pro icon.
	parent.find('.wpstream_stream_pro').addClass('wpstream_inactive_icon');
	// Disable the webcaster icon.
	parent.find('.start_webcaster').addClass('wpstream_inactive_icon');
	// Disable the statistics icon.
	parent.find('.wpstream_statistics_channel').addClass('wpstream_inactive_icon');

	// Set the status label to "off".
	parent.find('.wpstream_channel_status').text(wpstream_start_streaming_vars.channel_off);
}



/**
 * Show the error modal for a failed start and reset the row to stopped state.
 *
 * @param {string} text   Error message to display.
 * @param {jQuery} parent The event row container.
 */
function wpstream_show_error_on_start(text,parent){
    //You don't have enough data to start a new event!
    // Set the modal message.
    jQuery('.wpstream_error_content').text(text);
    // Show the modal backdrop.
    jQuery('.wpstream_modal_background').show();
    // Show the error modal.
    jQuery('.wpstream_error_modal_notification').show();
    // Reset the row to stopped state.
    wpstream_event_stopped_make_actions(parent);

}

/**
 * Show the error modal for a failed stop and keep the row in its live state.
 *
 * @param {string} text   Error message to display.
 * @param {jQuery} parent The event row container.
 */
function wpstream_show_error_on_stop(text,parent){
    //You don't have enough data to start a new event!
    // Set the modal message.
    jQuery('.wpstream_error_content').text(text);
    // Show the modal backdrop.
    jQuery('.wpstream_modal_background').show();
    // Show the error modal.
    jQuery('.wpstream_error_modal_notification').show();
    // Keep the row in its live state.
    wpstream_event_ready_make_actions_visible(parent);

}


/*
*
* Streaming modal trigger functions
*  
*
*/
/**
 * Wire the settings/broadcast modals: close buttons, Escape-to-close, the modal
 * triggers, and the external-broadcast option switcher.
 */
function wpestate_start_modal_actions(){

    // Close button hides the modal and its backdrop.
    jQuery('.wpstream_close_modal').on('click',function(event){
        jQuery(this).parent().hide();
        jQuery('.wpstream_modal_background').hide();
    });

    // Escape key closes any open modal.
    document.addEventListener('keydown', function(event) {
        // 27 = Escape.
        if(event.keyCode == 27){
            jQuery('.wpstream_modal_form').hide();
            jQuery('.wpstream_modal_background').hide();
        }
    });


    // A modal trigger opens its target modal.
    jQuery('.wpstream-trigger-modal').on('click',function(event){

        // Ignore triggers that are inactive.
        if( jQuery(this).hasClass('wpstream_inactive_icon') ) {
            return;
        };

        // Show the backdrop.
        jQuery('.wpstream_modal_background').show();
        // Which modal to open.
        var modal_class=jQuery(this).attr('data-modal');
		// Telemetry: modal opened.
		wpstream_safe_track_onboarding( 'open_modal', 'wpstream_' + post_type, 'button', modal_class );
        // Find the owning event row.
        var parent =jQuery(this).closest('.event_list_unit');
        // Show the backdrop.
        jQuery('.wpstream_modal_background').show();
        // Reveal the target modal as a flex column.
        parent.find("."+modal_class).css('display','flex').css('flex-direction','column');
    })

    // Switch which external-software instructions are shown.
    jQuery('.wpstream_external_broadcast_options').change(function(event){
        // Selected option value.
        var new_option = jQuery(this).val();
        // Its container.
        var parent = jQuery(this).parent();

        // Hide all option panels...
        parent.find('.external_software_streaming').hide();
        // ...then show the selected one.
        parent.find('.'+new_option).show();
    });


}



/*
*
* Tooltips for buttons
*  
*
*/ 

/**
 * Show the appropriate tooltip on hover, choosing the disabled-variant tooltip
 * for inactive icons.
 */
function wpstream_tooltip(){

    // On hover in:
    jQuery( ".wpstream_tooltip_wrapper" ).hover(
    function() {

        // Inactive: show the "disabled" tooltip.
        if(jQuery( this ).hasClass('wpstream_inactive_icon')){
            jQuery( this ).find('.wpstream_tooltip_disabled').css('opacity',1);
        // Active: show the normal tooltip.
        }else{
            jQuery( this ).find('.wpstream_tooltip').css('opacity',1);
        }


    // On hover out: hide both tooltips.
    }, function() {
        jQuery( this ).find('.wpstream_tooltip').css('opacity',0);
        jQuery( this ).find('.wpstream_tooltip_disabled').css('opacity',0);
    }
);
}

/*
*
* Modal errors
*  
*
*/ 


/**
 * Wire the error modal's OK button to close it and clear its message.
 */
function wpestate_start_modal_error_actions(){
    // On OK:
    jQuery('.wpstream_error_ok').on('click',function(event){
        // Hide the modal.
        jQuery(this).parent().hide();
        // Hide the backdrop.
        jQuery('.wpstream_modal_background').hide();
        // Clear the message.
        jQuery(this).parent().find('.wpstream_error_content').text('');
    });
}




/*
*
* Copy to Clipboard
*  
*
*/ 

/**
 * Enable copy-to-clipboard for the live URI (with a "Copied" tooltip) and the
 * stream key, using each button's sibling text.
 */
function wpstream_copy_to_clipboard(){
       
    // Copy the live URI on click.
    jQuery('.copy_live_uri').on('click',function(){
        // Read the URI text.
        var value_uri = jQuery(this).parent().find('.wpstream_live_uri_text').text();
        // Throwaway input for execCommand('copy').
        var temp = jQuery("<input>");
        // Attach it to the document.
        jQuery("body").append(temp);
        // Select its value.
        jQuery(temp).val(value_uri).select();
        // Copy to clipboard.
        document.execCommand("copy");
        // Clean up the throwaway input.
        jQuery(temp).remove();

	    // Show "Copied" tooltip
	    // Build the tooltip element.
	    var tooltip = jQuery('<div class="wpstream_copy_tooltip">Copied</div>');
	    // Style it.
	    tooltip.css({
		    position: 'absolute',
		    background: '#333',
		    color: '#fff',
		    padding: '5px 10px',
		    borderRadius: '3px',
		    fontSize: '12px',
		    zIndex: 9999,
		    pointerEvents: 'none'
	    });

	    // Position it above the button, horizontally centered.
	    var offset = jQuery(this).offset();
	    tooltip.css({
		    top: offset.top - 35,
		    left: offset.left + (jQuery(this).outerWidth() / 2) - (tooltip.outerWidth() / 2)
	    });

	    // Insert it into the page.
	    jQuery('body').append(tooltip);
	    // Fade it in.
	    tooltip.fadeIn(200);

	    // Auto-dismiss after 1.5s.
	    setTimeout(function() {
		    tooltip.fadeOut(200, function() {
			    tooltip.remove();
		    });
	    }, 1500);
    });
    
    // Copy the stream key on click.
    jQuery('.copy_live_key').on('click',function(){
        // Read the key text.
        var value_uri = jQuery(this).parent().find('.wpstream_live_key_text').text();
        // Throwaway input for the copy command.
        var temp = jQuery("<input>");
        // Attach it to the document.
        jQuery("body").append(temp);
        // Select its value.
        jQuery(temp).val(value_uri).select();
        // Copy to clipboard.
        document.execCommand("copy");
        // Clean up the throwaway input.
        jQuery(temp).remove();
    });
}

/*
*
* Webcaster button action
*  
*
*/ 

/**
 * Wire the webcaster (browser broadcaster) launcher: check for a WHIP URL and
 * open the new in-browser broadcaster, falling back to the legacy webcaster
 * window when unavailable.
 */
function wpstream_webcaster_actions(){
    // On launcher click:
    jQuery('.start_webcaster').on('click',function(event){
        // Ignore while the launcher is inactive.
        if(jQuery(this).hasClass('wpstream_inactive_icon')){
            return;
        }
		// Telemetry: webcaster opened.
		wpstream_safe_track_onboarding( 'open_webcaster', 'wpstream_' + post_type );
        // Cache the launcher.
        var $this = jQuery(this);
        // admin-ajax.php endpoint.
        var ajaxurl = wpstream_start_streaming_vars.admin_url + 'admin-ajax.php';
        // The channel id from the row.
        var channelId      = jQuery(this).closest('.event_list_unit').data('show-id');
        // Will hold the WHIP publish URL.
        var whipUrl = '';
		// Open a blank popup now (within the click) to avoid popup blockers.
		var pendingPopup = window.open('', '_blank', 'location=yes,scrollbars=yes,status=yes');
		// CSRF nonce for the AJAX call.
		var nonce = jQuery('#wpstream_start_event_nonce').val();

        // Ask the server whether a WHIP URL is available.
        jQuery.ajax({
            type: 'POST',
            dataType: 'json',
            url: ajaxurl,
            timeout: 3000000,
            data: {
                'action': 'wpstream_check_whipurl',
                'channel_id': channelId,
	            'nonce': nonce,
            },
            // On success:
            success: function (data) {
                // WHIP available.
                if( data.success == true ){
                    // The WHIP publish URL.
                    whipUrl = data.whip_url;

	                // Build the new broadcaster URL for this channel.
	                if ( whipUrl !== '' ) {
		                // Open the new broadcaster in a new window
		                // New broadcaster page + channel id.
		                var broadcasterUrl = wpstream_start_streaming_vars.broadcaster_url + channelId;
						// During onboarding, pass an onboarding flag...
						if ( wpstream_start_streaming_vars.is_onboarding == 'yes' ) {
							broadcasterUrl += '/?onboarding=1';
                            // ...and hand the popup an onboarding payload via its name.
                            if (pendingPopup) {
                                // Carry the current session id.
                                var popupSessionId = (new URLSearchParams(window.location.search)).get('session_id') || '';
                                // Payload the broadcaster reads from window.name.
                                var popupPayload = {
                                    wpstream_onboarding_popup_payload: {
                                        session_id: popupSessionId,
                                    }
                                };

                                // Stash it on the popup (JSON-encoded).
                                try {
                                    pendingPopup.name = JSON.stringify(popupPayload);
                                // On failure, clear the name.
                                } catch (e) {
                                    pendingPopup.name = '';
                                }
                            }
						}
		                // window.open(broadcasterUrl, 'wpstream_broadcaster_' + channelId, 'fullscreen=yes');
		                // Navigate the pre-opened popup to the broadcaster.
		                if (pendingPopup) {
			                pendingPopup.location.href = broadcasterUrl;
		                // No popup available (blocked): nothing to navigate.
		                } else {
							// (defensive) close the popup if present.
							if (pendingPopup) {
								pendingPopup.close();
							}
		                }
	                }
                // No WHIP: fall back to the legacy webcaster window.
                } else {
	                // Legacy webcaster URL from the launcher.
	                var caster_url = $this.attr('data-webcaster-url');
	                // Collapse the external-software panel.
	                $this.parent().find('.external_software_streaming').slideUp()
	                // Open the legacy webcaster.
	                window.open(caster_url, '_blank', 'location=yes,scrollbars=yes,status=yes');
                }
            },
            // On AJAX error, fall back to the legacy webcaster.
            error: function() {
                // fallback to the old broadcaster if the AJAX request fails
                // Legacy webcaster URL from the launcher.
                var caster_url = $this.attr('data-webcaster-url');
                // Collapse the external-software panel.
                $this.parent().find('.external_software_streaming').slideUp()
                // Open the legacy webcaster.
                window.open(caster_url, '_blank', 'location=yes,scrollbars=yes,status=yes');
            }
        });
    })
}



/*
*
*  per event - low latency vs bitrate vs encrypt
*
*/


/**
 * Per-event variant of the mutual-exclusion logic: toggling low-latency /
 * adaptive-bitrate clears encrypt and vice-versa, for a single event's modal.
 *
 * @param {jQuery} input_element The toggled option input.
 */
function wpstream_adjust_settings(input_element){

   
   
    // Which setting was toggled.
    var attr_value = input_element.attr('data-attr-ajaxname');
    // (debug log)
    console.log(attr_value);

 

    // Speed options are mutually exclusive with encryption.
    if( attr_value === 'adaptive_bitrate' || attr_value==='low_latency'){
        // (debug log)
        console.log('perform ');

        // Defer briefly so the checkbox state settles first.
        var timer = setTimeout(function() {
           
            // If either speed option is checked, clear encrypt.
            if( jQuery('.wpstream-setting-adaptive_bitrate .wpstream_event_option_item').is(':checked') || 
                jQuery('.wpstream-setting-low_latency .wpstream_event_option_item').is(':checked') ){
                jQuery('.wpstream-setting-encrypt .wpstream_event_option_item') .removeAttr('checked');           
            }      
        }, 300);

    }

    // Toggling encrypt clears the speed options.
    if( attr_value === 'encrypt'){
        // (debug log)
        console.log('perform2 ');

        // Defer briefly so the checkbox state settles first.
        var timer = setTimeout(function() {
           
            // If encrypt is checked, clear the speed options.
            if( jQuery('.wpstream-setting-encrypt .wpstream_event_option_item').is(':checked') ){
                   
                jQuery('.wpstream-setting-low_latency .wpstream_event_option_item') .removeAttr('checked'); 
                jQuery('.wpstream-setting-adaptive_bitrate .wpstream_event_option_item') .removeAttr('checked'); 
           
            }

           
        }, 300);

    }

}

/*
*
* Save options actions
*  
*
*/ 

/**
 * Persist per-event streaming option toggles: when a modal option changes,
 * gather all option states and save them via AJAX.
 */
function wpstream_save_options_actions(){
    // On any per-event option click:
    jQuery('.wpestate_settings_modal .wpstream_event_option_item').on('click',function(){

        // Apply the mutual-exclusion rules.
        wpstream_adjust_settings(jQuery(this));

        // The option group container.
        var holder  =   jQuery(this).parents('.wpstream_event_streaming_local');
        // The event's show id.
        var show_id =   jQuery(this).parents('.event_list_unit').find('.start_event').attr('data-show-id');
        // Accumulator for option name -> 0/1.
        var optionarray ={};
        // CSRF nonce for the AJAX call.
        var nonce               =   jQuery('#wpstream_start_event_nonce').val();
        // Defer so toggled states settle before reading them.
        var timer = setTimeout(function() {
            // Collect every option's checked state.
            holder.find('.wpstream_event_option_item').each(function(){
                optionarray[jQuery(this).attr('data-attr-ajaxname')]=jQuery(this).prop("checked") ? 1 : 0 ;
            });


            // JSON snapshot of the options (the object is what is actually sent below).
            var myJSON = JSON.stringify(optionarray);
            // Save the option set for this event.
            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                timeout: 300000,
                data: {
                    'action'            :   'wpstream_update_local_event_settings',
                    'show_id'           :   show_id,
                    'option'            :   optionarray,
                    'security'          :   nonce
                                
                },
                // Success: no UI change needed.
                success: function (data) {
                },
                // Error: ignored.
                error: function (jqXHR,textStatus,errorThrown) {
                }
                
            });
        }, 300);
        
    });
    
}

/**
 * Persist the per-event "use global settings" toggle and enable/disable the
 * individual option inputs accordingly.
 */
function wpstream_save_use_global_event_options() {
	// On toggle click:
	jQuery('.wpestate_settings_modal #local_event_options_enabled').on('click',function(){
		// CSRF nonce for the AJAX call.
		var nonce   = jQuery('#wpstream_start_event_nonce').val();
		// The event's show id.
		var show_id = jQuery(this).parents('.event_list_unit').find('.start_event').attr('data-show-id');

		// Local checked state as 1/0.
		var use_global_settings = jQuery(this).prop("checked") ? 1 : 0 ;
		// Save the preference.
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			timeout: 300000,
			data: {
				'action'     : 'wpstream_update_use_global_event_options',
				'show_id'    : show_id,
				'use_global' : jQuery(this).prop("checked") ? 0 : 1 ,
				'security'   : nonce
			},
			// Enable/disable the option inputs based on the toggle.
			success: function (data) {
				jQuery('.wpestate_settings_modal .wpstream_event_option_item').each(function(){
					jQuery(this).prop("disabled", !jQuery('#local_event_options_enabled').prop("checked"));
				});
			},
			// Log on error.
			error: function (jqXHR,textStatus,errorThrown) {
				console.log('error');
			}
		});
	});
}

/*
*
* Function to check live connections
*  
*
*/ 
 
/**
 * On page load, find event rows flagged as pending and begin polling their
 * status from the database on a 60s interval.
 */
function wpstream_check_live_connections(){
    // was .pending_streaming.pending_trigger
    // Any rows waiting to go live?
    if( jQuery('.event_list_unit.pending_trigger').length>0 ){
        // For each pending row:
        jQuery('.event_list_unit.pending_trigger').each(function(){
            // Cache the row.
            var acesta      =   jQuery(this);
            // Its show id.
            var show_id     =   jQuery(this).attr('data-show-id');
            // Its server id.
            var server_id   =   jQuery(this).attr('data-server-id');

            // Poll immediately once.
            wpstream_check_live_connections_from_database(acesta,show_id,server_id);
            // Then poll every 60s.
            var counter_long     =   '';
            counter_long =  setInterval( function (){
				wpstream_check_live_connections_from_database(acesta,show_id,server_id)
            },60000);
            // Remember the timer by show id.
            counters[show_id]=counter_long;

        });
    }
 
}



  
/*
*
* check live connection
*
*
*/

    
/**
 * Slow (database-backed) status poll: when the channel is active, refresh the
 * OBS credentials, Larix links and QR code; handle stopped/error states.
 *
 * @param {jQuery} acesta     The event row container.
 * @param {number} channel_id Channel id to check.
 * @param {string} server_id  Backend server id.
 */
function wpstream_check_live_connections_from_database( acesta,channel_id,server_id){
    // Query channel status; the callback handles the result.
    var server_status = wpstream_check_event_status_in_js(channel_id,'wpstream_check_live_connections_from_database',
        function(server_status){

            // Channel is live:
            if(server_status.status==='active' ){
                
                // Store the webcaster URL on the launcher.
                acesta.find('.wpstream_ready_to_stream .start_webcaster').attr('data-webcaster-url',server_status.webcaster_url);
                // Show the OBS ingest URI.
                acesta.find('.wpstream_live_uri_text').text(server_status.obs_uri);
                // Show the OBS stream key.
                acesta.find('.wpstream_live_key_text').text(server_status.obs_stream);
            
                // Reveal the live action buttons.
                wpstream_event_ready_make_actions_visible( acesta );

                // Build the combined RTMP URL for Larix.
                var larix_rtmp= server_status.obs_uri+server_status.obs_stream;
                // Display it.
                acesta.find('.wpstream_larix_rtmp').text(larix_rtmp);

                // Also show it in the test field.
                acesta.find('.larrix_test').text(larix_rtmp);

                // Build the Larix mobile deep-link.
                var   larix_qr ='larix://set/v1?conn[][url]='+encodeURIComponent(larix_rtmp);
                // Point the mobile-start link at it.
                acesta.find('.wpstream_start_with_larix_mobile').attr('href',larix_qr); 
                
             
                // Generate the QR locally (SEC-05): the RTMP URL + secret stream key
                // encoded in larix_qr must never be sent to a third-party image service.
                wpstreamRenderQr( acesta.find('.print_qrcode'), larix_qr );
                // Link the live-data button.
                acesta.find('.wpstream_live_data').attr('href',server_status.live_data_url);
                // Stop any fast start poll.
                clearInterval( counters["stop"+channel_id]);
         
            // Channel stopped:
            }else if(server_status.status==='stopped' ){
	            // Stop the fast poll.
	            clearInterval( counters["stop"+channel_id]);
	            // Restore the stopped-state UI.
	            wpstream_event_stopped_after_status_check(acesta);
            // Channel errored:
            }else if(server_status.status==='error' ){
                
                // Stop the fast poll.
                clearInterval( counters["stop"+channel_id]);
                // Target the status area...
                var curent_content = acesta.find('.wpstream_channel_status ');
                
                // ...and show a failure message.
                curent_content.html('<div class="wpstream_channel_status not_ready_to_stream"><span class="dashicons dashicons-dismiss"></span>'+wpstream_start_streaming_vars.failed_event_creation+'</div>');
            }
        },
        // On AJAX error, put the row into its error state.
        function(){
            wpstream_event_error_make_actions_visible(acesta);
        }
        );

}



/*
*
* Check event/channel status
*
*
*/

    
/**
 * Ping a URL cross-domain (JSONP) and report reachability via a callback.
 *
 * @param {string}   url_param The URL to probe.
 * @param {Function} callback  Receives true when reachable (HTTP 200/400).
 */
function wpstream_check_server_status(url_param,callback) {

    // The URL to probe.
    var url = url_param ;
    // (unused) status placeholder.
    var status='';
    // Fire a cache-disabled, cross-domain JSONP probe.
    jQuery.ajax({
        url: url,
        type: "get",
        cache: false,
        dataType: 'jsonp', // it is for supporting crossdomain
        crossDomain : true,
        asynchronous : false,
        timeout : 1500, // set a timeout in milliseconds
        callback:'',
        // On completion, inspect the HTTP status.
        complete : function(xhr) {
    
            // Treat 200/400 as reachable.
            if(xhr.status == "200" || xhr.status == "400") {
                callback(true);
            }
            // Anything else is treated as unreachable.
            else {
                callback(false);
            }
        }
    });
}

/**
 * Convert megabits to gigabits, rounded to one decimal, clamped at 0.
 *
 * @param {number} megabits Value in megabits.
 * @return {number} Value in gigabits (>= 0).
 */
function wpstream_convert_mb_to_gb(megabits) {
	// Divide by 1000 to get gigabits.
	let gigabit = megabits / 1000;
	// Round to one decimal place.
	gigabit = parseFloat( gigabit.toFixed( 1 ) );

	// Never return a negative value.
	if ( gigabit < 0 ) {
		return 0
	}
// Return the converted value.

	return gigabit;
}