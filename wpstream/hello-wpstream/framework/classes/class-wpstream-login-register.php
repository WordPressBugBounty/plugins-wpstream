<?php
/**
 * Login register
 *
 * Front-end login / registration / forgot-password handler for the
 * hello-wpstream theme. Registers AJAX endpoints for login, registration and
 * password reset, and provides methods that build the login/register/forgot
 * form markup. Implemented as a singleton.
 *
 * @package wpstream-theme
 */

/**
 * WpStream_Login_Register
 *
 * Singleton that wires up the auth AJAX handlers and renders the auth forms.
 */
class WpStream_Login_Register {


	/**
	 * Facebook status
	 *
	 * @var string
	 */
	private $facebook_status = 'no';

	/**
	 * Google status
	 *
	 * @var string
	 */
	private $google_status = 'no';

	/**
	 * Twitter status
	 *
	 * @var string
	 */
	private $twiter_status = 'no';

	/**
	 * Enable user pass status
	 *
	 * @var string
	 */
	private $enable_user_pass_status = 'yes';

	/**
	 * Terms condition lin
	 *
	 * @var string
	 */
	private $terms_conditions_link = '#';

	/**
	 * Captcha
	 *
	 * @var string
	 */
	private $use_captcha = 'yes';

	/**
	 * Instance
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Constructor
	 */
	private function __construct() {
		// Private constructor to prevent direct instantiation.

		// AJAX login endpoint for both logged-in and logged-out visitors.
		add_action( 'wp_ajax_handle_login', array( $this, 'handle_login' ) );
		add_action( 'wp_ajax_nopriv_handle_login', array( $this, 'handle_login' ) );

		// AJAX registration endpoint for both logged-in and logged-out visitors.
		add_action( 'wp_ajax_handle_register', array( $this, 'handle_register' ) );
		add_action( 'wp_ajax_nopriv_handle_register', array( $this, 'handle_register' ) );

		// AJAX forgot-password endpoint for both logged-in and logged-out visitors.
		add_action( 'wp_ajax_handle_forgot_pass', array( $this, 'handle_forgot_pass' ) );
		add_action( 'wp_ajax_nopriv_handle_forgot_pass', array( $this, 'handle_forgot_pass' ) );

		// Runs in <head> to process password-reset links landing on the site.
		add_action( 'wp_head', array( $this, 'wpstream_theme_hook_javascript' ) );
	}


	/**
	 * Get instance
	 *
	 * @return WpStream_Login_Register The shared singleton instance.
	 */
	public static function get_instance() {
		// Lazily create the single instance on first use.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Javascript hook
	 */
	public function wpstream_theme_hook_javascript() {
		global $wpdb;

		// Only act when the request looks like a password-reset link (?action=reset_pwd&key=...).
		if ( isset( $_GET['key'] ) && isset( $_GET['action'] ) && 'reset_pwd' === $_GET['action'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// Read the activation key from the query string.
			$reset_key = sanitize_text_field( wp_unslash( $_GET['key'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Read the target login from the query string when present.
			if ( isset( $_GET['login'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$user_login = sanitize_text_field( wp_unslash( $_GET['login'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			// Try the object cache first, keyed by reset key + login.
			$user_data = wp_cache_get( 'user_data_' . $reset_key . '_' . $user_login, 'user_data' );

			// Cache miss: look up the user by matching activation key and login.
			if ( false === $user_data ) {
				$user_data = $wpdb->get_row( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->prepare(
						"SELECT ID, user_login, user_email FROM {$wpdb->users}
                        WHERE user_activation_key = %s AND user_login = %s",
						$reset_key,
						$user_login
					)
				);

				// Cache the result (including a null miss) under the same key.
				wp_cache_set( 'user_data_' . $reset_key . '_' . $user_login, $user_data, 'user_data' );
			}

			// Only proceed when the key + login matched a real user.
			if ( ! empty( $user_data ) ) {
				// Use the authoritative login/email from the DB row.
				$user_login = $user_data->user_login;
				$user_email = $user_data->user_email;

				// Guard again on key + user data before mutating the password.
				if ( ! empty( $reset_key ) && ! empty( $user_data ) ) {
					// Generate a new 7-char password and apply it to the account.
					$new_password = wp_generate_password( 7, false );
					wp_set_password( $new_password, $user_data->ID );
					// mailing the reset details to the user.
					// Build the (default) message body containing the new credentials.
					$message  = esc_html__( 'Your new password for the account at:', 'hello-wpstream' ) . "\r\n\r\n";
					$message .= get_bloginfo( 'name' ) . "\r\n\r\n";
					// translators: Username placeholder in email message.
					$message .= sprintf( esc_html__( 'Username: %s', 'hello-wpstream' ), $user_login ) . "\r\n\r\n";
					// translators: Password placeholder in email message.
					$message .= sprintf( esc_html__( 'Password: %s', 'hello-wpstream' ), $new_password ) . "\r\n\r\n";
					$message .= esc_html__( 'You can now login with your new password at: ', 'hello-wpstream' ) . get_option( 'siteurl' ) . '/' . "\r\n\r\n";

					// Assemble the From / Reply-To / X-Mailer headers.
					$headers = 'From: ' . wpstream_theme_return_sending_email() . "\r\n" .
						'Reply-To: ' . wpstream_theme_return_sending_email() . "\r\n" .
						'X-Mailer: PHP/' . phpversion();

					// Extra data passed alongside the email (the new password).
					$arguments = array(
						'user_pass' => $new_password,
					);

					// Subject and body actually sent (body is overwritten with a placeholder here).
					$subject    = esc_html__( 'Password Reseted', 'hello-wpstream' );
					$message    = 'Text will be editable from theme admin :your new pass ' . $new_password;
					$email_type = 'html';
					wpstream_theme_send_emails( $user_email, $subject, $message, $email_type );

					// Success notice (assigned but not printed; the generic notice below is shown instead).
					$mess = '<div class="login-alert">' . esc_html__( 'A new password was sent via email!', 'hello-wpstream' ) . '</div>';

				} else {
					// Reject when the key/user checks fail.
					exit( 'Not a Valid Key.' );
				}
			}// end if empty

			// Print a generic confirmation notice regardless of the lookup outcome.
			$mes = '<div class="login_alert_full">' . esc_html__( 'We have just sent you a new password. Please check your email!', 'hello-wpstream' ) . '</div>';
			print esc_html( $mes );
		}
	}

	/**
	 * Forgot password
	 */
	public function handle_forgot_pass() {
		// Verify the AJAX nonce before processing the reset request.
		check_ajax_referer( 'login_ajax_nonce', 'security' );
		global $wpdb;

		// Read the submitted email/username to reset.
		if ( isset( $_POST['forgot_email'] ) ) {
			$forgot_email = sanitize_text_field( wp_unslash( $_POST['forgot_email'] ) );
		}

		// Reject an empty field with a JSON error.
		if ( '' === $forgot_email ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__(
						'Email field is empty!',
						'hello-wpstream'
					),
				)
			);
			wp_die();
		}

		// Normalize the input for lookup.
		$user_input = trim( $forgot_email );

		// An '@' means treat the input as an email; otherwise treat it as a login.
		if ( strpos( $user_input, '@' ) ) {
			// Look the user up by email; reject unknown users and administrators.
			$user_data = get_user_by( 'email', $user_input );
			if ( empty( $user_data ) || isset( $user_data->caps['administrator'] ) ) {
				echo wp_json_encode(
					array(
						'success' => false,
						'message' => esc_html__(
							'Invalid E-mail address!',
							'hello-wpstream'
						),
					)
				);
				wp_die();
			}
		} else {
			// Look the user up by login; reject unknown users and administrators.
			$user_data = get_user_by( 'login', $user_input );
			if ( empty( $user_data ) || isset( $user_data->caps['administrator'] ) ) {
				echo wp_json_encode(
					array(
						'success' => false,
						'message' => esc_html__(
							'We did not found a username with this email!',
							'hello-wpstream'
						),
					)
				);
				wp_die();
			}
		}
		// Resolved account login/email used to build the reset link.
		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;

		// Reuse a cached activation key, or generate and persist a new one.
		$key = wp_cache_get( 'user_activation_key_' . $user_login, 'users' );
		if ( false === $key ) {
			// generate reset key.
			$key = wp_generate_password( 20, false );
			$wpdb->update( $wpdb->users, array( 'user_activation_key' => $key ), array( 'user_login' => $user_login ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_cache_set( 'user_activation_key_' . $user_login, $key, 'users' );
		}

		// Build the reset link handled by wpstream_theme_hook_javascript().
		$reset_link = $this->wpstream_tg_validate_url() . "action=reset_pwd&key=$key&login=" . rawurlencode( $user_login );

		// Email the reset link to the account owner.
		$subject    = esc_html__( 'Password Reset Request', 'hello-wpstream' );
		$message    = 'Text will be editable from theme admin - > your reset link ' . $reset_link;
		$email_type = 'html';
		wpstream_theme_send_emails( $user_email, $subject, $message, $email_type, $reply_to = '', $extra_headers = '' );

		// Report success to the client.
		echo wp_json_encode(
			array(
				'success' => true,
				'message' => esc_html__(
					'We have just sent you an email with Password reset instructions.',
					'hello-wpstream'
				),
			)
		);
		wp_die();
	}

	/**
	 * Tg validate url
	 */
	public function wpstream_tg_validate_url() {

		// Start from the site home URL.
		$page_url = esc_url( home_url( '/' ) );
		// Detect whether the URL already contains a query string.
		$urlget   = strpos( $page_url, '?' );
		// Choose '?' to start a query string, or '&' to append to an existing one.
		if ( false === $urlget ) {
			$concate = '?';
		} else {
			$concate = '&';
		}
		// Return the base URL ready for reset params to be appended.
		return $page_url . $concate;
	}

	/**
	 * Handle register
	 */
	public function handle_register() {
		// Verify the AJAX nonce before processing the registration.
		check_ajax_referer( 'login_ajax_nonce', 'security' );
		// Short-circuit if the visitor is somehow already logged in.
		if ( is_user_logged_in() ) {
			echo wp_json_encode(
				array(
					'success' => true,
					'message' => esc_html__(
						'You are already logged in! redirecting...',
						'hello-wpstream'
					),
				)
			);
			wp_die();
		}

		// Captcha is hardcoded off here, so the whole block below is skipped.
		$use_captcha = 'no';
		if ( 'yes' === $use_captcha ) {
			// Require a captcha value to be present.
			if ( ! isset( $_POST['captcha'] ) || '' === $_POST['captcha'] ) {

				echo wp_json_encode(
					array(
						'success' => false,
						'message' => esc_html__( 'Wrong captcha', 'hello-wpstream' ),
					)
				);
				wp_die();
			}

			// Verify the captcha response with Google reCAPTCHA (secret is a placeholder here).
			$secret   = 'from optpms';
			$cappval  = sanitize_text_field( wp_unslash( $_POST['captcha'] ) );
			$response = $this->wpstream_theme_return_recapthca( $secret, $cappval );

			// Reject when reCAPTCHA reports failure.
			if ( false === $response['success'] ) {
				echo wp_json_encode(
					array(
						'register' => false,
						'message'  => esc_html__( 'Captcha Invalidated - Refresh and try again.', 'hello-wpstream' ),
					)
				);
				wp_die();
			}
		}

		// Read and trim the submitted email.
		if ( isset( $_POST['user_email_register'] ) ) {
			$user_email = trim( sanitize_text_field( wp_unslash( $_POST['user_email_register'] ) ) );
		}

		// Read and trim the submitted username.
		if ( isset( $_POST['user_login_register'] ) ) {
			$user_name = trim( sanitize_text_field( wp_unslash( $_POST['user_login_register'] ) ) );
		}

		// This flow always requires a user-chosen password.
		$enable_user_pass_status = 'yes';

		// Reject usernames containing anything other than letters, digits or underscore.
		if ( preg_match( '/^[0-9A-Za-z_]+$/', $user_name ) === 0 ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Invalid username (do not use special characters or spaces)!', 'hello-wpstream' ),
				)
			);
			wp_die();
		}

		// Reject when either required field is empty.
		if ( '' === $user_email || '' === $user_name ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Username and/or Email field is empty!', 'hello-wpstream' ),
				)
			);
			wp_die();
		}

		// Reject malformed email addresses.
		if ( filter_var( $user_email, FILTER_VALIDATE_EMAIL ) === false ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'The email doesn\'t look right !', 'hello-wpstream' ),
				)
			);
			wp_die();
		}

		// Reject when the email domain has no DNS records.
		$domain = mb_substr( strrchr( $user_email, '@' ), 1 );
		if ( '' !== $domain && ! checkdnsrr( $domain ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'The email\'s domain doesn\'t look right.', 'hello-wpstream' ),
				)
			);
			wp_die();
		}

		// Reject when the username is already taken.
		$user_id = username_exists( $user_name );
		if ( $user_id ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Username already exists.  Please choose a new one.', 'hello-wpstream' ),
				)
			);
			wp_die();
		}

		// Validate the password pair when user-chosen passwords are enabled.
		if ( 'yes' === $enable_user_pass_status ) {
			// Read and trim the password.
			if ( isset( $_POST['user_pass'] ) ) {
				$user_pass = trim( sanitize_text_field( wp_unslash( $_POST['user_pass'] ) ) );
			}

			// Read and trim the password confirmation.
			if ( isset( $_POST['user_pass_retype'] ) ) {
				$user_pass_retype = trim( sanitize_text_field( wp_unslash( $_POST['user_pass_retype'] ) ) );
			}

			// Reject when either password field is empty.
			if ( '' === $user_pass || '' === $user_pass_retype ) {
				echo wp_json_encode(
					array(
						'success' => false,
						'message' => esc_html__( 'One of the password field is empty!', 'hello-wpstream' ),
					)
				);
				wp_die();
			}

			// Reject when the two passwords differ.
			if ( $user_pass !== $user_pass_retype ) {
				echo wp_json_encode(
					array(
						'success' => false,
						'message' => esc_html__( 'Passwords do not match', 'hello-wpstream' ),
					)
				);
				wp_die();
			}
		}

		// Create the account only when the username is free and the email is unused.
		if ( ! $user_id && email_exists( $user_email ) === false ) {
			// Use the chosen password, or auto-generate one when passwords are disabled.
			if ( 'yes' === $enable_user_pass_status ) {
				$user_password = $user_pass; // no so random now!.
			} else {
				$user_password = wp_generate_password( $length = 12, $include_standard_special_chars = false ); //phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
			}

			// Create the WordPress user.
			$user_id = wp_create_user( $user_name, $user_password, $user_email );

			// Report creation failure, or the appropriate success message.
			if ( is_wp_error( $user_id ) ) {
				echo wp_json_encode(
					array(
						'success' => false,
						'message' => esc_html__( 'Something went wrong. Please try again or check with site administrator!', 'hello-wpstream' ),
					)
				);
				wp_die();
			} elseif ( 'yes' === $enable_user_pass_status ) {
				// User chose their own password: they can log in immediately.
				echo wp_json_encode(
					array(
						'success' => true,
						'message' => esc_html__( 'Your account was created and you can login now!', 'hello-wpstream' ),
					)
				);
				wp_die();
			} else {
				// Password was auto-generated and emailed to the user.
				echo wp_json_encode(
					array(
						'success' => true,
						'message' => esc_html__( 'An email with the generated password was sent!', 'hello-wpstream' ),
					)
				);
				wp_die();
			}
		} else {

			// Fall-through: the email is already registered.
			echo wp_json_encode(
				array(
					'register' => false,
					'message'  => esc_html__( 'Email already exists.  Please choose a new one.', 'hello-wpstream' ),
				)
			);
			wp_die();
		}

		// Safety net to end the AJAX request.
		wp_die();
	}

	/**
	 * Return reCAPTCHA verification result.
	 *
	 * @param string $secret The secret key for reCAPTCHA.
	 * @param string $captcha The reCAPTCHA response from the user.
	 * @return array|null Returns an array containing the verification result or null on failure.
	 */
	public function wpstream_theme_return_recapthca( $secret, $captcha ) {
		// Resolve the caller's IP to send to the verification endpoint.
		$remoteip = sanitize_text_field( wp_unslash( wpstream_get_ip_address() ) );

		// Build the form-encoded body for Google's siteverify endpoint.
		$url       = 'https://www.google.com/recaptcha/api/siteverify';
		$post_data = http_build_query(
			array(
				'secret'   => $secret,
				'response' => $captcha,
				'remoteip' => $remoteip,
			),
			'',
			'&'
		);

		// POST the verification request.
		$response = wp_safe_remote_post(
			$url,
			array(
				'body'    => $post_data,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
			)
		);

		// On a transport error, surface the message in the expected array shape.
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}

		// Decode and return Google's JSON verification result.
		$response_body = wp_remote_retrieve_body( $response );
		$resulting     = json_decode( $response_body, true );

		return $resulting;
	}




	// This function checks multiple server variables for the IP address and validates it, providing a more robust and secure way to get the user's IP address.
	/**
	 * Resolve the visitor's public IP address from the server variables.
	 *
	 * @return string A valid public IP, or an empty string if none is found.
	 */
	public function wpstream_get_ip_address() {
		// Candidate server keys, ordered from proxy-supplied to the direct connection.
		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		);

		// Check each candidate header in priority order.
		foreach ($ip_keys as $key) {
			if (array_key_exists($key, $_SERVER) === true) {
				// A header may hold a comma-separated list; inspect each entry.
				foreach (explode(',', $_SERVER[$key]) as $ip) {
					$ip = trim($ip);
					// Return the first public (non-private, non-reserved) valid IP.
					if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
						return $ip;
					}
				}
			}
		}

		return ''; // If no valid IP is found
	}





	/**
	 * Handle login
	 */
	public function handle_login() {
		// Verify the AJAX nonce before attempting sign-on.
		check_ajax_referer( 'login_ajax_nonce', 'security' );
		// Short-circuit if the visitor is already logged in.
		if ( is_user_logged_in() ) {
			echo wp_json_encode(
				array(
					'success' => true,
					'message' => esc_html__(
						'You are already logged in! redirecting...',
						'hello-wpstream'
					),
				)
			);
			wp_die();
		}

		// Read the submitted username.
		if ( isset( $_POST['login_user'] ) ) {
			$login_user = sanitize_text_field( wp_unslash( $_POST['login_user'] ) );
		}

		// Read the submitted password.
		if ( isset( $_POST['login_pwd'] ) ) {
			$login_pwd = sanitize_text_field( wp_unslash( $_POST['login_pwd'] ) );
		}

		// Read the "is popup" flag echoed back to the client on success.
		if ( isset( $_POST['ispop'] ) ) {
			$ispop = intval( $_POST['ispop'] );
		}

		// Reject when either credential field is empty.
		if ( '' === $login_user || '' === $login_pwd ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__(
						'Username and/or Password field is empty!',
						'hello-wpstream'
					),
				)
			);
			wp_die();
		}

		// Ensure a PHP session exists (used elsewhere by the social flows).
		$vsessionid = session_id();
		if ( empty( $vsessionid ) ) {
			session_name( 'PHPSESSID' );
			session_start();
		}

		// Clear any stale auth cookie, then attempt to sign the user in.
		wp_clear_auth_cookie();
		$info                  = array();
		$info['user_login']    = $login_user;
		$info['user_password'] = $login_pwd;
		$info['remember']      = false;
		$user_signon           = wp_signon( $info, true );

		// On bad credentials return an error; otherwise finalize the login.
		if ( is_wp_error( $user_signon ) ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__(
						'Wrong username or password!',
						'hello-wpstream'
					),
				)
			);
		} else {
			// Promote the signed-on user to the current user for this request.
			wp_set_current_user( $user_signon->ID );
			do_action( 'set_current_user' );
			wp_get_current_user();
			// Return success plus the popup flag and new user id for the client.
			echo wp_json_encode(
				array(
					'success' => true,
					'ispop'   => $ispop,
					'newuser' => $user_signon->ID,
					'message' => esc_html__( 'Login successful, redirecting...', 'hello-wpstream' ),
				)
			);

		}

		// End the AJAX request.
		wp_die();
	}

	/**
	 * Generate login register forgot form
	 */
	public function generate_login_register_forgot_form() {
		// Concatenate the three forms inside a shared wrapper.
		$retun_string  = '<div class="login_register_forgot_wrapper">';
		$retun_string .= $this->generate_login_form();
		$retun_string .= $this->generate_register_form();
		$retun_string .= $this->generate_forgot_form();
		// Hidden nonce field shared by the AJAX login/register/forgot calls.
		$retun_string .= '<input type="hidden" class="wpstream-theme_security-login-topbar" name="security-login-topbar"  value="' . wp_create_nonce( 'login_ajax_nonce' ) . '">';
		$retun_string .= '</div>';

		return $retun_string;
	}

	/**
	 * Section controls
	 *
	 * @return string Markup with the register/forgot/login toggle links.
	 */
	private function section_controls() {
		// Buffer the markup so it can be returned as a string.
		$display_string = '';
		ob_start();
		?>
		<!-- Toggle links that switch between the login / register / forgot panels. -->
		<div class="login_sections_control">
			<div class="wpstream-theme_register_link"><?php esc_html_e( 'Register here!', 'hello-wpstream' ); ?></div>
			<div class="wpstream-theme_forgot_pass_link"><?php esc_html_e( 'Forgot password?', 'hello-wpstream' ); ?></div>
			<div class="wpstream-theme_login_link"><?php esc_html_e( 'Back to login', 'hello-wpstream' ); ?></div>

			<!-- Tracks whether the form is shown inside a popup. -->
			<input type="hidden" name="loginpop" id="loginpop" value="0">
		</div>
		<?php
		// Capture and return the buffered markup.
		$display_string = ob_get_contents();
		ob_end_clean();
		return $display_string;
	}


	/**
	 * Login form
	 */
	private function generate_login_form() {

		// Buffer the markup so it can be returned as a string.
		$display_string = '';
		ob_start();
		?>
		<!-- Login panel: username/password fields plus optional social buttons. -->
		<div class="wpstream-theme_login_form">
			<span class="h5 offcanvas-title wpstream-offcanvas-title"><?php esc_html_e( 'Log In to WpStream', 'hello-wpstream' ); ?></span>

			<!-- Prompt linking across to the register panel. -->
			<div class="wpstream-theme_login-div-title-topbar">
				<?php esc_html_e( 'Don’t have an account yet?', 'hello-wpstream' ); ?>

				<div class="wpstream-theme_register_link"><?php esc_html_e( 'Sign Up for free', 'hello-wpstream' ); ?></div>
			</div>

			<!-- Container where JS injects login error/success messages. -->
			<div class="wpstream-theme_login_alert"></div>

			<!-- Username field. -->
			<input type="text" class="form-control wpstream-theme_login_user" name="log"
					autofocus
					placeholder="<?php esc_attr_e( 'Username', 'hello-wpstream' ); ?>"/>

			<!-- Password field with a show/hide toggle. -->
			<div class="password_holder">
				<input type="password" class="form-control wpstream-theme_login_pwd"
						name="pwd"
						placeholder="<?php esc_attr_e( 'Password', 'hello-wpstream' ); ?>"/>
				<div class="show_hide_password hide">
					<i class=" far fa-eye-slash "></i>
				</div>
			</div>
			<!-- Tracks whether the form is shown inside a popup. -->
			<input type="hidden" name="loginpop" id="loginpop_wd" value="0">

			<!-- Link to switch to the forgot-password panel. -->
			<div class="wpstream-theme_forgot_pass_link_wrap">
				<div class="wpstream-theme_forgot_pass_link"><?php esc_html_e( 'Forgot password?', 'hello-wpstream' ); ?></div>
			</div>

			<!-- Submit button (handled via AJAX). -->
			<button class="wpstream_submit_button wpstream-theme_wp_login_button">
				<?php esc_html_e( 'Login', 'hello-wpstream' ); ?>
			</button>

			<!-- Social login buttons, shown only when a provider is enabled. -->
			<div class="login-links">

				<?php
				// Render the "or" divider and social buttons when any provider is on.
				if ( 'yes' === $this->facebook_status || 'yes' === $this->google_status || 'yes' === $this->twiter_status ) {
					echo '<div class="or_social">' . esc_html__( 'or', 'hello-wpstream' ) . '</div>';
					// Delegate to the social login object when it is available.
					if ( class_exists( 'wpstream_theme_Social_Login' ) ) {
						global $wpstream_theme_social_login;
						$wpstream_theme_social_login->display_form( 'topbar', 0 );
					}
				}

				?>
			</div>
		</div>

		<?php

		// Capture and return the buffered markup.
		$display_string = ob_get_contents();
		ob_end_clean();
		return $display_string;
	}

	/**
	 * Register form
	 */
	public function generate_register_form() {

		// Buffer the markup so it can be returned as a string.
		$display_string = '';
		ob_start();
		?>

		<!-- Registration panel: username/email, optional password pair, terms and captcha. -->
		<div class="wpstream-theme_register_form">
			<span class="h5 offcanvas-title wpstream-offcanvas-title"><?php esc_html_e( 'Create an Account', 'hello-wpstream' ); ?></span>

			<!-- Prompt linking back to the login panel. -->
			<div class="wpstream-theme_register-div-title">
				<?php esc_html_e( 'Have an account?', 'hello-wpstream' ); ?>
				<div class="wpstream-theme_login_link"><?php esc_html_e( 'Log in', 'hello-wpstream' ); ?></div>
			</div>

			<!-- Container where JS injects registration error/success messages. -->
			<div class="wpstream-theme_register_alert"></div>

			<!-- Desired username and email. -->
			<input type="text" name="user_login_register"
					class="form-control wpstream-theme_user_login_register"
					autofocus placeholder="<?php esc_attr_e( 'Username', 'hello-wpstream' ); ?>"/>
			<input type="email" name="user_email_register"
					class="form-control wpstream-theme_user_email_register"
					placeholder="<?php esc_attr_e( 'Email', 'hello-wpstream' ); ?>"/>

			<?php
			// Only collect a password when user-chosen passwords are enabled.
			if ( 'yes' === $this->enable_user_pass_status ) {
				?>
				<!-- Password field with show/hide toggle. -->
				<div class="password_holder">
					<input type="password" name="user_password"
							class="wpstream-theme_user_password form-control"
							placeholder="<?php esc_attr_e( 'Password', 'hello-wpstream' ); ?>"/>

					<div class="show_hide_password hide">
						<i class=" far fa-eye-slash "></i>
					</div>
				</div>

				<!-- Password confirmation field with show/hide toggle. -->
				<div class="password_holder">
					<input type="password" name="user_password_retype"
							class="wpstream-theme_user_password_retype form-control"
							placeholder="<?php esc_attr_e( 'Retype Password', 'hello-wpstream' ); ?>"/>

					<div class="show_hide_password hide">
						<i class=" far fa-eye-slash "></i>
					</div>
				</div>

				<?php
			}
			?>


			<!-- Terms & conditions checkbox linking to the configured terms page. -->
			<input type="checkbox" name="terms" id="wpstream-theme_user_terms_register" class="wpstream-theme_user_terms_register"/>
			<label id="user_terms_register_label" for="wpstream-theme_user_terms_register">
				<?php esc_html_e( 'I agree with ', 'hello-wpstream' ); ?>
				<a href="<?php print esc_url( $this->terms_conditions_link ); ?>" target="_blank"
					class="wpstream-theme_user_terms_register_link">
					<?php esc_html_e( 'terms & conditions', 'hello-wpstream' ); ?>
				</a>
			</label>

			<?php
			// Render the reCAPTCHA mount point when captcha is enabled.
			if ( 'yes' === $this->use_captcha ) {
				print '<div id="top_register_menu" style="float:left;transform:scale(0.75);-webkit-transform:scale(0.75);transform-origin:0 0;-webkit-transform-origin:0 0;"></div>';
			}
			?>

			<!-- When passwords are auto-generated, tell the user it will be emailed. -->
			<?php if ( 'yes' !== $this->enable_user_pass_status ) { ?>
				<p id="reg_passmail"><?php esc_html_e( 'A password will be e-mailed to you', 'hello-wpstream' ); ?></p>
			<?php } ?>

			<!-- Submit button (handled via AJAX). -->
			<button class="wpstream_submit_button wpstream-theme_wp_register_button">
				<?php esc_html_e( 'Register', 'hello-wpstream' ); ?>
			</button>

		</div>

		<?php
		// Capture and return the buffered markup.
		$display_string = ob_get_contents();
		ob_end_clean();
		return $display_string;
	}

	/**
	 * Forgot form
	 */
	public function generate_forgot_form() {
		// Buffer the markup so it can be returned as a string.
		$display_string = '';
		ob_start();
		?>


		<!-- Forgot-password panel: single email field posted to the reset AJAX endpoint. -->
		<div class="wpstream-theme_forgot_form">
			<span class="h5 offcanvas-title wpstream-offcanvas-title"><?php esc_html_e( 'Reset Password', 'hello-wpstream' ); ?></span>

			<!-- Link back to the login panel. -->
			<div class="wpstream-theme_forgot-div-title">
				<?php esc_html_e( 'Back to', 'hello-wpstream' ); ?>
				<div class="wpstream-theme_login_link"><?php esc_html_e( 'Log in', 'hello-wpstream' ); ?></div>
			</div>

			<!-- Container where JS injects reset error/success messages. -->
			<div class="wpstream-theme_forgot_alert"></div>

			<!-- Email/username to send the reset link to. -->
			<input type="email" class="form-control wpstream-theme_forgot_email"
					name="forgot_email"
					autofocus placeholder="<?php esc_attr_e( 'Enter Your Email Address', 'hello-wpstream' ); ?>"
					size="20"/>

			<!-- Optional current post id ($post is not in scope here, so usually empty). -->
			<input type="hidden" class="wpstream-theme_forgot_email_postid" value="
						<?php
						if ( isset( $post->ID ) ) {
							echo intval( $post->ID );
						}
						?>
			">

			<!-- Submit button (handled via AJAX). -->
			<button class="wpstream_submit_button wpstream-theme_wp_forgot_button">
				<?php esc_html_e( 'Reset Password', 'hello-wpstream' ); ?>
			</button>
		</div>

		<?php
		// Capture and return the buffered markup.
		$display_string = ob_get_contents();
		ob_end_clean();
		return $display_string;
	}
}

// Instantiate the singleton and expose it globally so templates can call its form methods.
global $login_register_object;
$login_register_object = WpStream_Login_Register::get_instance();
