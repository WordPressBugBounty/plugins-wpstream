<?php
/**
 * Login register
 *
 * @package wpstream-theme
 */

/**
 * WpStream_Login_Register
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

		add_action( 'wp_ajax_handle_login', array( $this, 'handle_login' ) );
		add_action( 'wp_ajax_nopriv_handle_login', array( $this, 'handle_login' ) );

		add_action( 'wp_ajax_handle_register', array( $this, 'handle_register' ) );
		add_action( 'wp_ajax_nopriv_handle_register', array( $this, 'handle_register' ) );

		add_action( 'wp_ajax_handle_forgot_pass', array( $this, 'handle_forgot_pass' ) );
		add_action( 'wp_ajax_nopriv_handle_forgot_pass', array( $this, 'handle_forgot_pass' ) );

		add_action( 'wp_head', array( $this, 'wpstream_theme_hook_javascript' ) );
	}


	/**
	 * Get instance
	 */
	public static function get_instance() {
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

		if ( isset( $_GET['key'] ) && isset( $_GET['action'] ) && 'reset_pwd' === $_GET['action'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$reset_key = sanitize_text_field( wp_unslash( $_GET['key'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( isset( $_GET['login'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$user_login = sanitize_text_field( wp_unslash( $_GET['login'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			$user_data = wp_cache_get( 'user_data_' . $reset_key . '_' . $user_login, 'user_data' );

			if ( false === $user_data ) {
				$user_data = $wpdb->get_row( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->prepare(
						"SELECT ID, user_login, user_email FROM {$wpdb->users}
                        WHERE user_activation_key = %s AND user_login = %s",
						$reset_key,
						$user_login
					)
				);

				wp_cache_set( 'user_data_' . $reset_key . '_' . $user_login, $user_data, 'user_data' );
			}

			if ( ! empty( $user_data ) ) {
				$user_login = $user_data->user_login;
				$user_email = $user_data->user_email;

				if ( ! empty( $reset_key ) && ! empty( $user_data ) ) {
					$new_password = wp_generate_password( 7, false );
					wp_set_password( $new_password, $user_data->ID );
					// mailing the reset details to the user.
					$message  = esc_html__( 'Your new password for the account at:', 'hello-wpstream' ) . "\r\n\r\n";
					$message .= get_bloginfo( 'name' ) . "\r\n\r\n";
					// translators: Username placeholder in email message.
					$message .= sprintf( esc_html__( 'Username: %s', 'hello-wpstream' ), $user_login ) . "\r\n\r\n";
					// translators: Password placeholder in email message.
					$message .= sprintf( esc_html__( 'Password: %s', 'hello-wpstream' ), $new_password ) . "\r\n\r\n";
					$message .= esc_html__( 'You can now login with your new password at: ', 'hello-wpstream' ) . get_option( 'siteurl' ) . '/' . "\r\n\r\n";

					$headers = 'From: ' . wpstream_theme_return_sending_email() . "\r\n" .
						'Reply-To: ' . wpstream_theme_return_sending_email() . "\r\n" .
						'X-Mailer: PHP/' . phpversion();

					$arguments = array(
						'user_pass' => $new_password,
					);

					$subject    = esc_html__( 'Password Reseted', 'hello-wpstream' );
					$message    = 'Text will be editable from theme admin :your new pass ' . $new_password;
					$email_type = 'html';
					wpstream_theme_send_emails( $user_email, $subject, $message, $email_type );

					$mess = '<div class="login-alert">' . esc_html__( 'A new password was sent via email!', 'hello-wpstream' ) . '</div>';

				} else {
					exit( 'Not a Valid Key.' );
				}
			}// end if empty

			$mes = '<div class="login_alert_full">' . esc_html__( 'We have just sent you a new password. Please check your email!', 'hello-wpstream' ) . '</div>';
			print esc_html( $mes );
		}
	}

	/**
	 * Forgot password
	 */
	public function handle_forgot_pass() {
		check_ajax_referer( 'login_ajax_nonce', 'security' );
		global $wpdb;

		if ( isset( $_POST['forgot_email'] ) ) {
			$forgot_email = sanitize_text_field( wp_unslash( $_POST['forgot_email'] ) );
		}

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

		$user_input = trim( $forgot_email );

		if ( strpos( $user_input, '@' ) ) {
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
		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;

		$key = wp_cache_get( 'user_activation_key_' . $user_login, 'users' );
		if ( false === $key ) {
			// generate reset key.
			$key = wp_generate_password( 20, false );
			$wpdb->update( $wpdb->users, array( 'user_activation_key' => $key ), array( 'user_login' => $user_login ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_cache_set( 'user_activation_key_' . $user_login, $key, 'users' );
		}

		$reset_link = $this->wpstream_tg_validate_url() . "action=reset_pwd&key=$key&login=" . rawurlencode( $user_login );

		$subject    = esc_html__( 'Password Reset Request', 'hello-wpstream' );
		$message    = 'Text will be editable from theme admin - > your reset link ' . $reset_link;
		$email_type = 'html';
		wpstream_theme_send_emails( $user_email, $subject, $message, $email_type, $reply_to = '', $extra_headers = '' );

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

		$page_url = esc_url( home_url( '/' ) );
		$urlget   = strpos( $page_url, '?' );
		if ( false === $urlget ) {
			$concate = '?';
		} else {
			$concate = '&';
		}
		return $page_url . $concate;
	}

	/**
	 * Handle register
	 */
	public function handle_register() {
		check_ajax_referer( 'login_ajax_nonce', 'security' );
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

		$use_captcha = 'no';
		if ( 'yes' === $use_captcha ) {
			if ( ! isset( $_POST['captcha'] ) || '' === $_POST['captcha'] ) {

				echo wp_json_encode(
					array(
						'success' => false,
						'message' => esc_html__( 'Wrong captcha', 'hello-wpstream' ),
					)
				);
				wp_die();
			}

			$secret   = 'from optpms';
			$cappval  = sanitize_text_field( wp_unslash( $_POST['captcha'] ) );
			$response = $this->wpstream_theme_return_recapthca( $secret, $cappval );

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

		if ( isset( $_POST['user_email_register'] ) ) {
			$user_email = trim( sanitize_text_field( wp_unslash( $_POST['user_email_register'] ) ) );
		}

		if ( isset( $_POST['user_login_register'] ) ) {
			$user_name = trim( sanitize_text_field( wp_unslash( $_POST['user_login_register'] ) ) );
		}

		$enable_user_pass_status = 'yes';

		if ( preg_match( '/^[0-9A-Za-z_]+$/', $user_name ) === 0 ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Invalid username (do not use special characters or spaces)!', 'hello-wpstream' ),
				)
			);
			wp_die();
		}

		if ( '' === $user_email || '' === $user_name ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Username and/or Email field is empty!', 'hello-wpstream' ),
				)
			);
			wp_die();
		}

		if ( filter_var( $user_email, FILTER_VALIDATE_EMAIL ) === false ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'The email doesn\'t look right !', 'hello-wpstream' ),
				)
			);
			wp_die();
		}

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

		if ( 'yes' === $enable_user_pass_status ) {
			if ( isset( $_POST['user_pass'] ) ) {
				$user_pass = trim( sanitize_text_field( wp_unslash( $_POST['user_pass'] ) ) );
			}

			if ( isset( $_POST['user_pass_retype'] ) ) {
				$user_pass_retype = trim( sanitize_text_field( wp_unslash( $_POST['user_pass_retype'] ) ) );
			}

			if ( '' === $user_pass || '' === $user_pass_retype ) {
				echo wp_json_encode(
					array(
						'success' => false,
						'message' => esc_html__( 'One of the password field is empty!', 'hello-wpstream' ),
					)
				);
				wp_die();
			}

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

		if ( ! $user_id && email_exists( $user_email ) === false ) {
			if ( 'yes' === $enable_user_pass_status ) {
				$user_password = $user_pass; // no so random now!.
			} else {
				$user_password = wp_generate_password( $length = 12, $include_standard_special_chars = false ); //phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
			}

			$user_id = wp_create_user( $user_name, $user_password, $user_email );

			if ( is_wp_error( $user_id ) ) {
				echo wp_json_encode(
					array(
						'success' => false,
						'message' => esc_html__( 'Something went wrong. Please try again or check with site administrator!', 'hello-wpstream' ),
					)
				);
				wp_die();
			} elseif ( 'yes' === $enable_user_pass_status ) {
				echo wp_json_encode(
					array(
						'success' => true,
						'message' => esc_html__( 'Your account was created and you can login now!', 'hello-wpstream' ),
					)
				);
				wp_die();
			} else {
				echo wp_json_encode(
					array(
						'success' => true,
						'message' => esc_html__( 'An email with the generated password was sent!', 'hello-wpstream' ),
					)
				);
				wp_die();
			}
		} else {

			echo wp_json_encode(
				array(
					'register' => false,
					'message'  => esc_html__( 'Email already exists.  Please choose a new one.', 'hello-wpstream' ),
				)
			);
			wp_die();
		}

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
		$remoteip = sanitize_text_field( wp_unslash( wpstream_get_ip_address() ) );

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

		$response = wp_safe_remote_post(
			$url,
			array(
				'body'    => $post_data,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}

		$response_body = wp_remote_retrieve_body( $response );
		$resulting     = json_decode( $response_body, true );

		return $resulting;
	}




	// This function checks multiple server variables for the IP address and validates it, providing a more robust and secure way to get the user's IP address.
	public function wpstream_get_ip_address() {
		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		);

		foreach ($ip_keys as $key) {
			if (array_key_exists($key, $_SERVER) === true) {
				foreach (explode(',', $_SERVER[$key]) as $ip) {
					$ip = trim($ip);
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
		check_ajax_referer( 'login_ajax_nonce', 'security' );
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

		if ( isset( $_POST['login_user'] ) ) {
			$login_user = sanitize_text_field( wp_unslash( $_POST['login_user'] ) );
		}

		if ( isset( $_POST['login_pwd'] ) ) {
			$login_pwd = sanitize_text_field( wp_unslash( $_POST['login_pwd'] ) );
		}

		if ( isset( $_POST['ispop'] ) ) {
			$ispop = intval( $_POST['ispop'] );
		}

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

		$vsessionid = session_id();
		if ( empty( $vsessionid ) ) {
			session_name( 'PHPSESSID' );
			session_start();
		}

		wp_clear_auth_cookie();
		$info                  = array();
		$info['user_login']    = $login_user;
		$info['user_password'] = $login_pwd;
		$info['remember']      = false;
		$user_signon           = wp_signon( $info, true );

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
			wp_set_current_user( $user_signon->ID );
			do_action( 'set_current_user' );
			wp_get_current_user();
			echo wp_json_encode(
				array(
					'success' => true,
					'ispop'   => $ispop,
					'newuser' => $user_signon->ID,
					'message' => esc_html__( 'Login successful, redirecting...', 'hello-wpstream' ),
				)
			);

		}

		wp_die();
	}

	/**
	 * Generate login register forgot form
	 */
	public function generate_login_register_forgot_form() {
		$retun_string  = '<div class="login_register_forgot_wrapper">';
		$retun_string .= $this->generate_login_form();
		$retun_string .= $this->generate_register_form();
		$retun_string .= $this->generate_forgot_form();
		$retun_string .= '<input type="hidden" class="wpstream-theme_security-login-topbar" name="security-login-topbar"  value="' . wp_create_nonce( 'login_ajax_nonce' ) . '">';
		$retun_string .= '</div>';

		return $retun_string;
	}

	/**
	 * Section controls
	 */
	private function section_controls() {
		$display_string = '';
		ob_start();
		?>
		<div class="login_sections_control">
			<div class="wpstream-theme_register_link"><?php esc_html_e( 'Register here!', 'hello-wpstream' ); ?></div>
			<div class="wpstream-theme_forgot_pass_link"><?php esc_html_e( 'Forgot password?', 'hello-wpstream' ); ?></div>
			<div class="wpstream-theme_login_link"><?php esc_html_e( 'Back to login', 'hello-wpstream' ); ?></div>

			<input type="hidden" name="loginpop" id="loginpop" value="0">
		</div>
		<?php
		$display_string = ob_get_contents();
		ob_end_clean();
		return $display_string;
	}


	/**
	 * Login form
	 */
	private function generate_login_form() {

		$display_string = '';
		ob_start();
		?>
		<div class="wpstream-theme_login_form">
			<span class="h5 offcanvas-title wpstream-offcanvas-title"><?php esc_html_e( 'Log In to WpStream', 'hello-wpstream' ); ?></span>

			<div class="wpstream-theme_login-div-title-topbar">
				<?php esc_html_e( 'Don’t have an account yet?', 'hello-wpstream' ); ?>

				<div class="wpstream-theme_register_link"><?php esc_html_e( 'Sign Up for free', 'hello-wpstream' ); ?></div>
			</div>

			<div class="wpstream-theme_login_alert"></div>

			<input type="text" class="form-control wpstream-theme_login_user" name="log"
					autofocus
					placeholder="<?php esc_attr_e( 'Username', 'hello-wpstream' ); ?>"/>

			<div class="password_holder">
				<input type="password" class="form-control wpstream-theme_login_pwd"
						name="pwd"
						placeholder="<?php esc_attr_e( 'Password', 'hello-wpstream' ); ?>"/>
				<div class="show_hide_password hide">
					<i class=" far fa-eye-slash "></i>
				</div>
			</div>
			<input type="hidden" name="loginpop" id="loginpop_wd" value="0">

			<div class="wpstream-theme_forgot_pass_link_wrap">
				<div class="wpstream-theme_forgot_pass_link"><?php esc_html_e( 'Forgot password?', 'hello-wpstream' ); ?></div>
			</div>

			<button class="wpstream_submit_button wpstream-theme_wp_login_button">
				<?php esc_html_e( 'Login', 'hello-wpstream' ); ?>
			</button>

			<div class="login-links">

				<?php
				if ( 'yes' === $this->facebook_status || 'yes' === $this->google_status || 'yes' === $this->twiter_status ) {
					echo '<div class="or_social">' . esc_html__( 'or', 'hello-wpstream' ) . '</div>';
					if ( class_exists( 'wpstream_theme_Social_Login' ) ) {
						global $wpstream_theme_social_login;
						$wpstream_theme_social_login->display_form( 'topbar', 0 );
					}
				}

				?>
			</div>
		</div>

		<?php

		$display_string = ob_get_contents();
		ob_end_clean();
		return $display_string;
	}

	/**
	 * Register form
	 */
	public function generate_register_form() {

		$display_string = '';
		ob_start();
		?>

		<div class="wpstream-theme_register_form">
			<span class="h5 offcanvas-title wpstream-offcanvas-title"><?php esc_html_e( 'Create an Account', 'hello-wpstream' ); ?></span>

			<div class="wpstream-theme_register-div-title">
				<?php esc_html_e( 'Have an account?', 'hello-wpstream' ); ?>
				<div class="wpstream-theme_login_link"><?php esc_html_e( 'Log in', 'hello-wpstream' ); ?></div>
			</div>

			<div class="wpstream-theme_register_alert"></div>

			<input type="text" name="user_login_register"
					class="form-control wpstream-theme_user_login_register"
					autofocus placeholder="<?php esc_attr_e( 'Username', 'hello-wpstream' ); ?>"/>
			<input type="email" name="user_email_register"
					class="form-control wpstream-theme_user_email_register"
					placeholder="<?php esc_attr_e( 'Email', 'hello-wpstream' ); ?>"/>

			<?php

			if ( 'yes' === $this->enable_user_pass_status ) {
				?>
				<div class="password_holder">
					<input type="password" name="user_password"
							class="wpstream-theme_user_password form-control"
							placeholder="<?php esc_attr_e( 'Password', 'hello-wpstream' ); ?>"/>

					<div class="show_hide_password hide">
						<i class=" far fa-eye-slash "></i>
					</div>
				</div>

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


			<input type="checkbox" name="terms" class="wpstream-theme_user_terms_register"/>
			<label id="user_terms_register_label" for="wpstream-theme_user_terms_register">
				<?php esc_html_e( 'I agree with ', 'hello-wpstream' ); ?>
				<a href="<?php print esc_url( $this->terms_conditions_link ); ?>" target="_blank"
					class="wpstream-theme_user_terms_register_link">
					<?php esc_html_e( 'terms & conditions', 'hello-wpstream' ); ?>
				</a>
			</label>

			<?php
			if ( 'yes' === $this->use_captcha ) {
				print '<div id="top_register_menu" style="float:left;transform:scale(0.75);-webkit-transform:scale(0.75);transform-origin:0 0;-webkit-transform-origin:0 0;"></div>';
			}
			?>

			<?php if ( 'yes' !== $this->enable_user_pass_status ) { ?>
				<p id="reg_passmail"><?php esc_html_e( 'A password will be e-mailed to you', 'hello-wpstream' ); ?></p>
			<?php } ?>

			<button class="wpstream_submit_button wpstream-theme_wp_register_button">
				<?php esc_html_e( 'Register', 'hello-wpstream' ); ?>
			</button>

		</div>

		<?php
		$display_string = ob_get_contents();
		ob_end_clean();
		return $display_string;
	}

	/**
	 * Forgot form
	 */
	public function generate_forgot_form() {
		$display_string = '';
		ob_start();
		?>


		<div class="wpstream-theme_forgot_form">
			<span class="h5 offcanvas-title wpstream-offcanvas-title"><?php esc_html_e( 'Reset Password', 'hello-wpstream' ); ?></span>

			<div class="wpstream-theme_forgot-div-title">
				<?php esc_html_e( 'Back to', 'hello-wpstream' ); ?>
				<div class="wpstream-theme_login_link"><?php esc_html_e( 'Log in', 'hello-wpstream' ); ?></div>
			</div>

			<div class="wpstream-theme_forgot_alert"></div>

			<input type="email" class="form-control wpstream-theme_forgot_email"
					name="forgot_email"
					autofocus placeholder="<?php esc_attr_e( 'Enter Your Email Address', 'hello-wpstream' ); ?>"
					size="20"/>

			<input type="hidden" class="wpstream-theme_forgot_email_postid" value="
						<?php
						if ( isset( $post->ID ) ) {
							echo intval( $post->ID );
						}
						?>
			">

			<button class="wpstream_submit_button wpstream-theme_wp_forgot_button">
				<?php esc_html_e( 'Reset Password', 'hello-wpstream' ); ?>
			</button>
		</div>

		<?php
		$display_string = ob_get_contents();
		ob_end_clean();
		return $display_string;
	}
}

global $login_register_object;
$login_register_object = WpStream_Login_Register::get_instance();
