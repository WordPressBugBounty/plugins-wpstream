/* Quick Start account connection and navigation to the shared creation forms. */
let wpstreamCaptchaPayload = '';
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        wpstream_account_form();
        wpstream_policy_privacy_checkbox();
        if (window.location.protocol !== 'https:') wpstream_fetchCaptcha();
        // A card waiting for a login does nothing when used. Bound first, so it also stops the handlers below.
        document.querySelectorAll('.intent[aria-disabled="true"]').forEach(function (card) {
            card.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();
            });
        });
        for (const [id, url] of [['choose-video', wpstreamonboarding_js_vars.upload_url]]) {
            document.getElementById(id)?.addEventListener('click', function () {
                window.location.assign(url);
            });
        }
        const intent = new URLSearchParams(window.location.search).get('library_new');
        const choice = document.getElementById(intent === 'channel' ? 'choose-live' : intent === 'video' ? 'choose-video' : '');
        if (choice) choice.click();
    });
function wpstream_is_register_mode() {
		return jQuery('#register').attr('aria-pressed') === 'true';
	}

	/**
	 * Enable or disable the shared submit button. Sign-in mode is always enabled;
	 * register mode needs the privacy box ticked and, on non-HTTPS, the
	 * proof-of-work payload ready (HTTPS relies on the Altcha widget instead).
	 *
	 * @return {void}
	 */
	function wpstream_sync_submit_state() {
		// wp-config.php owns the credentials: the server disabled the tabs and the
		// submit, and nothing here may re-enable them.
		if ( jQuery('#register').is(':disabled') ) {
			return;
		}
		jQuery('#register-consent-cue').prop('hidden', jQuery('#wpstream_register_privacy').is(':checked'));
		var powReady = window.location.protocol !== 'https:'
			? wpstreamCaptchaPayload !== ''
			: true;
		var blocked = wpstream_is_register_mode()
			&& ( !jQuery('#wpstream_register_privacy').is(':checked') || !powReady );
		jQuery('#connect-button').prop('disabled', blocked);
	}

	/**
	 * Gate the submit button on the privacy checkbox (register mode only).
	 *
	 * @return {void}
	 */
	function wpstream_policy_privacy_checkbox() {
		// Re-evaluate whenever the privacy checkbox changes.
		jQuery('#wpstream_register_privacy').on('change', wpstream_sync_submit_state);
		// Apply the initial state.
		wpstream_sync_submit_state();
	}

function wpstream_account_form() {
		var form = jQuery('.wpstream-account-form');
		// No account form on this page (e.g. the entry screen when already connected).
		if ( form.length === 0 ) {
			return;
		}

		// Remember the sign-in label so switching tabs can restore it.
		jQuery('#connect-button').attr('data-signin-label', jQuery('#connect-button').text());

		// Tabs: sign-in and register share one form, so just toggle fields and labels.
		jQuery('#signin').on('click', function() { wpstream_set_account_mode(false); });
		jQuery('#register').on('click', function() { wpstream_set_account_mode(true); });

		// Entry screen: Sign up opens the Create an account tab and moves to its first field.
		jQuery('#signup-button').on('click', function() {
			wpstream_set_account_mode(true);
			jQuery('#wpstream_register_email').trigger('focus');
		});

		// One submit handler for the button and the Enter key.
		form.on('submit', function(e) {
			if ( wpstream_is_register_mode() ) {
				e.preventDefault();
				wpstream_on_board_actual_register();
				return;
			}
			e.preventDefault();
			wpstream_onboard_actual_login();
		});
	}

	/**
	 * Switch the account form between sign-in and register mode.
	 *
	 * @param {boolean} register True for "Create an account", false for "Sign in".
	 * @return {void}
	 */
	function wpstream_set_account_mode( register ) {
		// Show only the active mode's fields.
		jQuery('#wpstream-signin-fields').prop('hidden', register);
		jQuery('#wpstream-register-fields').prop('hidden', !register);
		// Reflect the active tab and relabel the submit button.
		jQuery('#signin').attr('aria-pressed', String(!register));
		jQuery('#register').attr('aria-pressed', String(register));
		jQuery('#signup-prompt').prop('hidden', register);
		jQuery('#connect-button').text(register ? 'Create account' : jQuery('#connect-button').attr('data-signin-label'));
		// Clear any message left from the other mode.
		jQuery('.wpstream_onboarding_notification').removeClass('onboarding_error').empty().removeClass('is-visible');
		// Register mode re-applies the privacy / captcha gate.
		wpstream_sync_submit_state();
	}

    /**
     * Perform the actual login AJAX call and reload into the connected state on success.
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
        jQuery('.wpstream_onboarding_notification').removeClass('onboarding_error').text('Sending data. Please Stand by...').addClass('is-visible');

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
                    jQuery('.wpstream_onboarding_notification').addClass('onboarding_error').text(data.error).addClass('is-visible');
                }else{
                    // On success, confirm and reload so the server renders the connected state.
                    jQuery('.wpstream_onboarding_notification').text('Login successful, please stand by...').addClass('is-visible');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                }


            },
            error: function (errorThrown) {

            }
        });
    }

function wpstream_on_board_actual_register(){
            // Prevent double submits while this request is in flight.
            var button=jQuery('#connect-button');
            button.addClass('wpstream-request-pending');

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
                jQuery('.wpstream_onboarding_notification').addClass('onboarding_error').text('Please fill all the fields!').addClass('is-visible');
                button.removeClass('wpstream-request-pending');
                return;
            }

			// Require agreement to the privacy policy.
			if( !wpstream_privacy_checkbox ){
				jQuery('.wpstream_onboarding_notification').addClass('onboarding_error').text('You must agree to the Privacy Policy to continue.').addClass('is-visible');
				button.removeClass('wpstream-request-pending');
				return;
			}


            // Show an in-progress notification.
            jQuery('.wpstream_onboarding_notification').removeClass('onboarding_error').text('Sending data. Please Stand by...').addClass('is-visible');

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
	                'wpstream_register_privacy' :   'on',
                    'security'                  :   nonce
                },
                success: function (data) {

                    if(data.success){
                        // Registration ok but the returned token was unusable.
                        if(data.token==='false' || data.token===false){
                            jQuery('.wpstream_onboarding_notification').addClass('onboarding_error').text('We couldn\'t authenticate with your new credentials').addClass('is-visible');
                        }else{
                            // Registration and authentication succeeded: confirm and reload into the connected state.
                            jQuery('.wpstream_onboarding_notification').text('Registration successful, please stand by...').addClass('is-visible');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        }
                    }else{
                        // Registration failed: show the server's message.
                        jQuery('.wpstream_onboarding_notification').addClass('onboarding_error').text(data.message).addClass('is-visible');
                        // wpstream_fetchCaptcha();

                    }
                    // Re-enable the button.
                    button.removeClass('wpstream-request-pending');

                },
                error: function (errorThrown) {
                    // Re-enable the button on a transport error.
                    button.removeClass('wpstream-request-pending');
                }
            });
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
		// Request a fresh challenge from the server, presenting the account nonce the handler verifies.
		var nonce = jQuery('#wpstream_onboarding_nonce').val();
		fetch( wpstream_admin_control_vars.admin_url + 'admin-ajax.php?action=wpstream_get_captcha_challenge&security=' + encodeURIComponent( nonce ) )
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
						// The PoW is ready: re-evaluate the submit gate.
						wpstream_sync_submit_state();
					} else {
						// Could not solve within the range: offer a retry.
						console.error( 'Security check: could not solve PoW challenge' );
						wpstream_captcha_show_retry();
					}
				} else {
					// Malformed/failed challenge: offer a retry.
					console.error( 'Security check init failed', result );
					wpstream_captcha_show_retry();
				}
			} )
			.catch( function( error ) {
				// Network/parse error: offer a retry.
				console.error( 'Security check error', error );
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
		// Block registration until a captcha is solved (the payload is still empty).
		wpstream_sync_submit_state();
		// Insert the retry notice only once.
		if ( jQuery('#wpstream_captcha_retry').length === 0 ) {
			jQuery('#connect-button').before(
				'<div id="wpstream_captcha_retry" class="wpstream-captcha-retry">' +
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



})();
