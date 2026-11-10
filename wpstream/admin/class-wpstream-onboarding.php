<?php
/**
 * Onboarding workflow module.
 *
 * @package    Wpstream
 * @subpackage Wpstream/admin
 */

/**
 * Owns the Quick Start entry screen and its account/content actions.
 */
class Wpstream_Onboarding {

	/** @var Wpstream Main plugin module. */
	private $main;

	/**
	 * @param Wpstream $main Main plugin module.
	 */
	public function __construct( $main ) {
		$this->main = $main;
	}

	/**
	 * Render the Quick Start entry point.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! current_user_can( 'administrator' ) ) {
			return '';
		}

		ob_start();
		$this->render_quick_start();
		return (string) ob_get_clean();
	}

	/**
	 * Render the "connect your account" entry screen.
	 *
	 * A disconnected site sees the sign-in / create-account card and two intent
	 * cards that look active but do nothing, with a tooltip asking to log in; a
	 * connected site sees the confirmation notice and working cards. The connected state is decided server-side, so the page reloads
	 * after a successful login or registration.
	 */
	private function render_quick_start() {
		// Existing installs saved the password as an autoloaded option; flip it
		// to non-autoloaded so the secret is not loaded on every request.
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( 'wpstream_api_password', false );
		}

		require_once __DIR__ . '/class-wpstream-admin-header.php';
		// Notices stay above the WpStream top bar, so the bar sits right above the page title.
		echo '<hr class="wp-header-end">';
		Wpstream_Admin_Header::render( $this->main, true, true );
		echo '<input type="hidden" id="wpstream_onboarding_nonce" value="' . esc_attr( wp_create_nonce( 'wpstream_onboarding_nonce' ) ) . '">';
		$connected = $this->main->wpstream_live_connection->is_connected();
		?>
		<div class="wpstream-screen wpstream-entry" id="entry">
			<div class="intro">
				<div class="eyebrow"><?php esc_html_e( 'VIDEO ON YOUR WORDPRESS SITE', 'wpstream' ); ?></div>
				<?php if ( $connected ) : ?>
					<h1 id="entry-heading"><?php esc_html_e( 'What would you like to create?', 'wpstream' ); ?></h1>
				<?php else : ?>
					<h1 id="entry-heading"><?php esc_html_e( 'Welcome to WpStream', 'wpstream' ); ?></h1>
					<p id="entry-description"><?php esc_html_e( 'Connect your account to start live streaming or publish videos, free or paid.', 'wpstream' ); ?></p>
				<?php endif; ?>
			</div>

			<?php
			if ( $connected ) {
				// Connected: confirmation notice replaces the connection card.
				?>
				<div class="connected-notice" id="connection-success" role="status"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><span><?php esc_html_e( 'WpStream account connected', 'wpstream' ); ?></span>
					<?php if ( ! wpstream_api_credentials_from_constants() ) : ?>
					<form method="post" class="connected-logout" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'wpstream_logout' ); ?>
						<input type="hidden" name="action" value="wpstream_logout">
						<button type="submit" class="button secondary" id="logout-button"><?php esc_html_e( 'Log out', 'wpstream' ); ?></button>
					</form>
					<?php endif; ?>
				</div>
				<?php
			} else {
				$this->render_connection_card();
			}
			?>


			<?php
			// aria-disabled, because a disabled button shows no tooltip. wpstream-onboarding2.js blocks the click.
			$login_tip = __( 'Log in to your WpStream account to use this.', 'wpstream' );
			$card_lock = $connected ? '' : 'aria-disabled="true" aria-describedby="intent-login-tip" data-login-tip="' . esc_attr( $login_tip ) . '"';
			if ( ! $connected ) {
				echo '<span id="intent-login-tip" hidden>' . esc_html( $login_tip ) . '</span>';
			}
			?>
			<div class="intent-grid" role="group" aria-label="<?php esc_attr_e( 'Choose what to create', 'wpstream' ); ?>">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpstream-live-entry-form"><?php wp_nonce_field( 'wpstream_open_live' ); ?><input type="hidden" name="action" value="wpstream_open_live"><button type="submit" class="intent" id="choose-live" aria-pressed="false" <?php echo $card_lock; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built. ?>>
					<span class="intent-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="2.5" fill="currentColor"/><path d="M7.8 16.2a6 6 0 0 1 0-8.4M16.2 7.8a6 6 0 0 1 0 8.4M4.9 19.1a10 10 0 0 1 0-14.2M19.1 4.9a10 10 0 0 1 0 14.2"/></svg></span>
					<span class="intent-title"><?php esc_html_e( 'Live streaming', 'wpstream' ); ?></span>
					<span class="intent-description"><?php esc_html_e( 'Broadcast from your camera or streaming software.', 'wpstream' ); ?></span>
					<span class="intent-action"><?php esc_html_e( 'Set up a live channel', 'wpstream' ); ?> <span aria-hidden="true">&rarr;</span></span>
				</button></form>
				<button type="button" class="intent" id="choose-video" aria-pressed="false" <?php echo $card_lock; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built. ?>>
					<span class="intent-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4.5v15l12.5-7.5z"/></svg></span>
					<span class="intent-title"><?php esc_html_e( 'Video on demand', 'wpstream' ); ?></span>
					<span class="intent-description"><?php esc_html_e( 'Upload a video or publish an existing recording.', 'wpstream' ); ?></span>
					<span class="intent-action"><?php esc_html_e( 'Create video', 'wpstream' ); ?> <span aria-hidden="true">&rarr;</span></span>
				</button>
			</div>

			<?php $this->render_tutorials(); ?>
		</div>
		<?php
	}

	/**
	 * Render the Video tutorials card on the entry screen.
	 *
	 * @return void
	 */
	private function render_tutorials() {
		echo '<section class="admin-card"><h2>' . esc_html__( 'Video tutorials', 'wpstream' ) . '</h2><ul class="tutorial-links">';
		foreach ( array( 'https://youtu.be/9DQrxsKcpmQ' => __( 'Stream with OBS', 'wpstream' ), 'https://youtu.be/qMSjJCskAfM' => __( 'Stream from your browser', 'wpstream' ), 'https://youtu.be/h6myD_vhKcg' => __( 'Stream from your iPhone', 'wpstream' ) ) as $url => $label ) {
			echo '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a></li>';
		}
		echo '</ul></section>';
	}

	/**
	 * Render the connection card: explanatory copy plus the sign-in / create-account
	 * form. The field ids are the ones wpstream-onboarding2.js submits from.
	 * When wp-config.php owns the credentials the form is shown disabled, with a note.
	 */
	private function render_connection_card() {
		$locked = wpstream_api_credentials_from_constants();
		?>
		<section class="connection" id="connection" aria-labelledby="connect-heading">
			<div class="connection-copy">
				<span class="eyebrow"><?php esc_html_e( 'CONNECT ONCE', 'wpstream' ); ?></span>
				<h2 id="connect-heading"><?php esc_html_e( 'Get started with WpStream', 'wpstream' ); ?></h2>
				<p id="connection-description"><?php esc_html_e( 'WpStream delivers your video to viewers on your site.', 'wpstream' ); ?></p>
			</div>

			<div class="connection-form">
				<div class="account-tabs" role="group" aria-label="<?php esc_attr_e( 'Account action', 'wpstream' ); ?>">
					<button type="button" id="signin" aria-pressed="true" <?php disabled( $locked ); ?>><?php esc_html_e( 'Sign in', 'wpstream' ); ?></button>
					<button type="button" id="register" aria-pressed="false" <?php disabled( $locked ); ?>><?php esc_html_e( 'Create an account', 'wpstream' ); ?></button>
				</div>

				<?php if ( $locked ) : ?>
				<p id="credentials-config-note" class="admin-notice"><?php esc_html_e( 'Your WpStream credentials are defined in wp-config.php. Update them there to change the connected account.', 'wpstream' ); ?></p>
				<?php endif; ?>
				<form id="account-form" class="wpstream-account-form" method="post" novalidate>
					<?php // Sign-in fields (default tab). ?>
					<div id="wpstream-signin-fields">
						<label for="api_username"><?php esc_html_e( 'Username or email', 'wpstream' ); ?>
							<input id="api_username" type="text" name="api_username" value="" autocomplete="username" <?php disabled( $locked ); ?> />
						</label>
						<label for="api_password"><?php esc_html_e( 'Password', 'wpstream' ); ?>
							<input id="api_password" type="password" name="api_password" value="" autocomplete="current-password" <?php disabled( $locked ); ?> />
						</label>
					</div>
					<?php if ( ! $locked ) { $this->render_register_fields(); } ?>

					<div class="wpstream_onboarding_notification" role="status"></div>
					<button type="submit" name="submit" class="button primary wide" id="connect-button" <?php disabled( $locked ); ?>><?php esc_html_e( 'Connect account', 'wpstream' ); ?></button>
				</form>
				<?php if ( ! $locked ) : ?>
				<?php // Sign up opens the Create an account tab, and disappears once that tab is showing. ?>
				<p class="signup-prompt" id="signup-prompt"><span><?php esc_html_e( 'New to WpStream?', 'wpstream' ); ?></span> <button type="button" class="button secondary" id="signup-button"><?php esc_html_e( 'Sign up', 'wpstream' ); ?></button></p>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Render the "Create an account" fields, hidden until that tab is chosen.
	 *
	 * The entry screen submits these ids to the wpstream_on_board_register AJAX
	 * handler via wpstream-onboarding2.js
	 * (e-mail, a generated password the API e-mails back, the privacy consent box
	 * and the Altcha captcha widget on HTTPS). The caller renders the
	 * #wpstream_onboarding_nonce input the handler checks.
	 *
	 * @return void
	 */
	public function render_register_fields() {
		?>
		<div id="wpstream-register-fields" hidden>
			<label for="wpstream_register_email"><?php esc_html_e( 'Your email', 'wpstream' ); ?>
				<input id="wpstream_register_email" type="text" name="wpstream_register_email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" autocomplete="off" />
			</label>
			<input id="wpstream_register_password" hidden type="text" name="wpstream_register_password" value="<?php echo esc_attr( $this->random_password() ); ?>" />
			<p class="field-help"><?php esc_html_e( 'This site connects as soon as your account is created. We\'ll email you the password, so you don\'t need to confirm anything first.', 'wpstream' ); ?></p>

			<?php if ( isset( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) : ?>
				<script async defer src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'js/vendor/altcha.min.js?ver=3.2.1' ); ?>" type="module"></script>
				<div class="wpstream-captcha-hidden">
					<altcha-widget
						challengeurl="<?php echo esc_url( WPSTREAM_API . '/v2/user/getcaptcha' ); ?>"
						name="altcha"
						auto="onload"
						hidefooter
						hidelogo
						strings='{"label": "<?php esc_html_e( 'I am not a robot', 'wpstream' ); ?>", "error": "<?php esc_html_e( 'Verification failed', 'wpstream' ); ?>", "wait": "<?php esc_html_e( 'Verifying...', 'wpstream' ); ?>"}'
					></altcha-widget>
				</div>
			<?php endif; ?>

			<label class="checkbox" for="wpstream_register_privacy">
				<input id="wpstream_register_privacy" type="checkbox" name="wpstream_register_privacy" />
				<span>
					<?php
					printf(
						/* translators: 1: opening link tag, 2: closing link tag. */
						esc_html__( 'I agree to the %1$sPrivacy Policy%2$s.', 'wpstream' ),
						'<a href="https://wpstream.net/privacy-policy/" target="_blank" rel="noopener">',
						'</a>'
					);
					?>
				</span>
			</label>
			<?php // Explains the pale button. wpstream-onboarding2.js hides it once the box is ticked. ?>
			<p class="field-help" id="register-consent-cue"><?php esc_html_e( 'Accept the Privacy Policy to continue', 'wpstream' ); ?></p>
		</div>
		<?php
	}

	/** Generate a registration password. */
	private function random_password() {
		$alphabet     = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
		$password     = array();
		$alpha_length = strlen( $alphabet ) - 1;
		for ( $index = 0; $index < 16; $index++ ) {
			$password[] = $alphabet[ random_int( 0, $alpha_length ) ];
		}

		return implode( $password );
	}

	/** Dispatch authenticated account and content actions. */
	public function handle_ajax( $action ) {
		$handlers = array(
			'wpstream_on_board_login'              => 'handle_login',
			'wpstream_on_board_register'           => 'handle_register',
			'wpstream_get_captcha_challenge'       => 'handle_captcha_challenge',
			'wpstream_on_board_create_channel'     => 'handle_create_channel',
			'wpstream_on_board_create_channel_ppv' => 'handle_create_channel_ppv',
			'wpstream_on_board_create_free_vod'    => 'handle_create_free_vod',
			'wpstream_on_board_create_ppv_vod'     => 'handle_create_ppv_vod',
		);

		if ( ! isset( $handlers[ $action ] ) ) {
			return new WP_Error( 'unknown_onboarding_action', __( 'Unknown onboarding action.', 'wpstream' ) );
		}

		return $this->{$handlers[ $action ]}();
	}

	/**
	 * Log out of the WpStream account and return to the entry screen, which then
	 * offers sign in and create account. Clears the saved account and every cached
	 * token and quota, so nothing keeps working under the old account. A site whose
	 * credentials come from wp-config.php has nothing here to clear.
	 *
	 * @return void
	 */
	public function handle_logout() {
		if ( ! current_user_can( 'administrator' ) ) {
			wp_die( esc_html__( 'Only for administrators', 'wpstream' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wpstream_logout' );

		if ( ! wpstream_api_credentials_from_constants() ) {
			$previous_username = (string) get_option( 'wpstream_api_username', '' );
			update_option( 'wpstream_api_username', '' );
			delete_option( 'wpstream_api_password' );

			// Drop the cached login and usage data of the old account.
			update_option( 'wpstream_token_expire', 0 );
			update_option( 'wpstream_current_token', ' ' );
			delete_transient( 'wpstream_token_api' );
			delete_transient( 'wpstream_token_request_30' );
			$this->main->quota_manager->invalidate();

			if ( '' !== $previous_username ) {
				/**
				 * Fires after the WpStream account was logged out.
				 *
				 * @since 4.14.0
				 *
				 * @param string $username The username that was removed.
				 */
				do_action( 'wpstream_account_disconnected', $previous_username );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpstream_onboard' ) );
		exit;
	}

	/** Log in to WpStream during onboarding. */
	private function handle_login() {
		check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );

		if ( current_user_can( 'administrator' ) ) {
			// WordPress slashes every $_POST value. Unslash first, or a username or password
			// with a quote is stored wrong and the correct password gets refused.
			$username = sanitize_text_field( wp_unslash( $_POST['api_username'] ) );
			// Never sanitized: the password is a secret sent verbatim to the API.
			$password = wp_unslash( $_POST['api_password'] );
			$previous_username = (string) get_option( 'wpstream_api_username', '' );
			$previous_password = (string) get_option( 'wpstream_api_password', '' );
			update_option( 'wpstream_api_username', $username );
			update_option( 'wpstream_api_password', $password, false );

			// A refused sign-in must not replace the account already saved.
			$connected = $this->main->wpstream_live_connection->keep_signin_if_accepted( $previous_username, $previous_password );
			$this->main->wpstream_live_connection->wpstream_get_videos();
			ob_end_clean();

			if ( $connected ) {
				// Logged-out page loads leave a failed quota fetch behind, which would blank the header.
				$this->main->quota_manager->invalidate();
				/**
				 * Fires after WpStream account credentials were accepted and saved.
				 *
				 * @since 4.14.0
				 *
				 * @param string $username WpStream.net username or e-mail.
				 */
				do_action( 'wpstream_account_connected', $username );
				echo json_encode( array( 'success' => true ) );
			} else {
				$text = get_option( 'wpstream_curl_failed' )
					? 'Login failed with critical error: ' . get_option( 'wpstream_curl_failed' )
					: 'Wrong username or password!';
				echo json_encode(
					array(
						'success' => false,
						'error'   => $text,
					)
				);
			}
		} else {
			echo json_encode(
				array(
					'success' => false,
					'token'   => esc_html__( 'You are not an administrator', 'wpstream' ),
				)
			);
		}

		die();
	}

	/** Fetch a captcha challenge from the WpStream API. */
	private function handle_captcha_challenge() {
		check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );
		if ( ! current_user_can( 'administrator' ) ) {
			wp_send_json( array( 'success' => false, 'error' => esc_html__( 'You are not an administrator', 'wpstream' ) ) );
		}

		$response = $this->main->wpstream_live_connection->get( WPSTREAM_API . '/v2/user/getcaptcha' );
		if ( is_wp_error( $response ) ) {
			wp_send_json( array( 'success' => false, 'error' => 'Could not reach captcha service.' ) );
		}

		wp_send_json( $response );
	}

	/** Register a new WpStream account during onboarding. */
	private function handle_register() {
		check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );
		if ( current_user_can( 'administrator' ) ) {
			// The browser checks this too, but only the server can make it a requirement.
			if ( empty( $_POST['wpstream_register_privacy'] ) ) {
				ob_end_clean();
				echo json_encode( array( 'success' => false, 'message' => esc_html__( 'You must agree to the Privacy Policy to continue.', 'wpstream' ) ) );
				die();
			}
			$email    = sanitize_text_field( $_POST['wpstream_register_email'] );
			$password = $_POST['wpstream_register_password'];
			$validate = $this->validate_registration( $email, $password );

			if ( ! $validate['success'] ) {
				ob_end_clean();
				echo json_encode( $validate );
				die();
			}

			$altcha   = isset( $_POST['wpstream_altcha'] ) ? trim( $_POST['wpstream_altcha'] ) : '';
			$is_https = isset( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'];
			if ( empty( $altcha ) ) {
				$message = $is_https
					? esc_html__( 'Captcha verification failed. Please try again.', 'wpstream' )
					: esc_html__( 'Security check not ready yet. Please wait a moment and try again.', 'wpstream' );
				echo json_encode( array( 'success' => false, 'message' => $message ) );
				die();
			}

			$response = $this->main->wpstream_live_connection->request(
				'v2/user/create',
				array(
					'email'      => $email,
					'password'   => $password,
					'solution'   => $altcha,
					'captcha_id' => '',
				)
			);
			if ( is_wp_error( $response ) ) {
				$response = array( 'success' => false );
			}

			if ( ! empty( $response['success'] ) ) {
				if ( $response['request']['registred'] ) {
					update_option( 'wpstream_api_username', $email );
					update_option( 'wpstream_api_password', $password, false );
					$connected = $this->main->wpstream_live_connection->is_connected();
					// Logged-out page loads leave a failed quota fetch behind, which would blank the header.
					$this->main->quota_manager->invalidate();
					ob_end_clean();
					echo json_encode(
						array(
							'success' => true,
							'token'   => $connected,
							'message' => esc_html__( 'Your Account was created. Please stand by...', 'wpstream' ),
						)
					);
					die();
				}

				ob_end_clean();
				echo json_encode(
					array(
						'success' => false,
						'message' => $response['request']['message'],
						'curl'    => $response,
					)
				);
				die();
			}

			ob_end_clean();
			echo json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Registration could not be completed. Please try again or register on wpstream.net', 'wpstream' ),
				)
			);
			die();
		}

		ob_end_clean();
		echo json_encode(
			array(
				'success' => false,
				'message' => esc_html__( 'You are not an administrator', 'wpstream' ),
			)
		);
		die();
	}

	/** @return mixed */
	private function handle_create_channel() {
		check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );
		$current_user = wp_get_current_user();

		if ( current_user_can( 'administrator' ) ) {
			$channel_name = sanitize_text_field( $_POST['channel_name'] );
			$result       = $this->main->streaming_content_creation->create(
				array(
					'actor_id' => intval( $current_user->ID ),
					'owner_id' => intval( $current_user->ID ),
					'kind'     => 'live_channel',
					'access'   => 'free',
					'title'    => $channel_name,
				)
			);

			if ( empty( $result['success'] ) ) {
				$this->log_creation_error( 'Couldn\'t create channel during onboarding because of error: ', $result );
				echo json_encode( array( 'success' => false ) );
			} else {
				$post_id = intval( $result['content_id'] );
				/**
				 * Fires when an onboarding step creates Streaming Content.
				 *
				 * @since 4.14.0
				 *
				 * @param string $step       live_free, live_ppv, vod_free or vod_ppv.
				 * @param int    $content_id Created Streaming Content post ID.
				 * @param string $kind       live_channel or vod.
				 */
				do_action( 'wpstream_onboarding_step_completed', 'live_free', $post_id, 'live_channel' );
				$this->send_creation_success( $post_id, '1' );
			}
		}

		die();
	}

	/** @return mixed */
	private function handle_create_channel_ppv() {
		check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );
		$current_user = wp_get_current_user();

		if ( current_user_can( 'administrator' ) ) {
			$channel_name  = sanitize_text_field( $_POST['channel_name'] );
			$channel_price = isset( $_POST['channel_price'] ) ? wp_unslash( $_POST['channel_price'] ) : '';
			$result        = $this->main->streaming_content_creation->create(
				array(
					'actor_id' => intval( $current_user->ID ),
					'owner_id' => intval( $current_user->ID ),
					'kind'     => 'live_channel',
					'access'   => 'paid',
					'title'    => $channel_name,
					'price'    => $channel_price,
				)
			);

			if ( empty( $result['success'] ) ) {
				$this->log_creation_error( 'Couldn\'t create channel during onboarding because of error: ', $result );
				echo json_encode( array( 'success' => false ) );
			} else {
				$post_id = intval( $result['content_id'] );
				do_action( 'wpstream_onboarding_step_completed', 'live_ppv', $post_id, 'live_channel' );
				$this->send_creation_success( $post_id, '2' );
			}
		}

		die();
	}

	/** @return mixed */
	private function handle_create_free_vod() {
		$current_user = wp_get_current_user();
		check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );

		if ( current_user_can( 'administrator' ) ) {
			$channel_name = sanitize_text_field( $_POST['channel_name'] );
			$file_name    = sanitize_text_field( $_POST['file_name'] );
			$result       = $this->main->streaming_content_creation->create(
				array(
					'actor_id'         => intval( $current_user->ID ),
					'owner_id'         => intval( $current_user->ID ),
					'kind'             => 'vod',
					'access'           => 'free',
					'title'            => $channel_name,
					'source_recording' => $file_name,
				)
			);

			if ( empty( $result['success'] ) ) {
				$this->log_creation_error( 'Couldn\'t create free VOD during onboarding because of error: ', $result );
				echo json_encode( array( 'success' => false ) );
			} else {
				$post_id = intval( $result['content_id'] );
				do_action( 'wpstream_onboarding_step_completed', 'vod_free', $post_id, 'vod' );
				$this->send_creation_success( $post_id, '3' );
			}
		}

		die();
	}

	/** @return mixed */
	private function handle_create_ppv_vod() {
		$current_user = wp_get_current_user();
		check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );

		if ( current_user_can( 'administrator' ) ) {
			$channel_name = sanitize_text_field( $_POST['channel_name'] );
			$vod_price    = isset( $_POST['vod_price'] ) ? wp_unslash( $_POST['vod_price'] ) : '';
			$file_name    = sanitize_text_field( $_POST['file_name'] );
			$result       = $this->main->streaming_content_creation->create(
				array(
					'actor_id'         => intval( $current_user->ID ),
					'owner_id'         => intval( $current_user->ID ),
					'kind'             => 'vod',
					'access'           => 'paid',
					'title'            => $channel_name,
					'source_recording' => $file_name,
					'price'            => $vod_price,
				)
			);

			if ( empty( $result['success'] ) ) {
				$this->log_creation_error( 'Couldn\'t create PPV VOD during onboarding because of error: ', $result );
				echo json_encode( array( 'success' => false ) );
			} else {
				$post_id = intval( $result['content_id'] );
				do_action( 'wpstream_onboarding_step_completed', 'vod_ppv', $post_id, 'vod' );
				$this->send_creation_success( $post_id, '4', $file_name );
			}
		}

		die();
	}

	/**
	 * Record a content-creation failure using the existing onboarding message.
	 *
	 * @param string $prefix Error description prefix.
	 * @param array  $result Creation result.
	 * @return void
	 */
	private function log_creation_error( $prefix, $result ) {
		$logger    = new WPStream_Logger();
		$log_entry = new WpStream_Log_Entry(
			array(
				'type'        => 'error',
				'description' => $prefix . ( $result['error'] ?? 'unknown' ),
			)
		);
		$logger->add( $log_entry );
	}

	/**
	 * Send the edit link returned by successful onboarding content creation.
	 *
	 * @param int         $post_id   Created content ID.
	 * @param string      $branch    Wizard branch.
	 * @param string|null $file_name Legacy PPV VOD response value.
	 * @return void
	 */
	private function send_creation_success( $post_id, $branch, $file_name = null ) {
		$permalink = add_query_arg( 'onboard', 'yes', get_edit_post_link( $post_id ) );
		$permalink = add_query_arg( 'branch', $branch, $permalink );
		$response  = array(
			'success' => true,
			'link'    => $permalink,
		);

		if ( null !== $file_name ) {
			$response[' $file_name'] = $file_name;
		}

		echo json_encode( $response );
	}

	/**
	 * Validate onboarding registration credentials.
	 *
	 * @param string $email    Submitted email address.
	 * @param string $password Submitted password.
	 * @return array{success: bool, message?: string}
	 */
	public function validate_registration( $email, $password ) {
		$result = array( 'success' => true );

		if ( '' === $email ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'The email Field is Empty', 'wpstream' ),
			);
		}

		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'The email doesn\'t look right !', 'wpstream' ),
			);
		}

		$domain = mb_substr( strrchr( $email, '@' ), 1 );
		if ( '' !== $domain && ! checkdnsrr( $domain ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'The email doesn\'t look right !', 'wpstream' ),
			);
		}

		if ( strlen( $password ) < 5 ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'The password is too short. Please use at least 5 characters.', 'wpstream' ),
			);
		}

		return $result;
	}

	/**
	 * Enqueue and configure the assets used by the onboarding workflow.
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		wp_enqueue_style(
			'wpstream-on-boarding-css',
			plugin_dir_url( __FILE__ ) . 'css/wpstream-admin-onboarding.css',
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . 'css/wpstream-admin-onboarding.css' )
		);

		wp_enqueue_script(
			'wpstream-on-boarding-js',
			plugin_dir_url( __FILE__ ) . 'js/wpstream-onboarding2.js',
			array(),
			filemtime( __DIR__ . '/js/wpstream-onboarding2.js' ),
			true
		);
		wp_localize_script(
			'wpstream-on-boarding-js',
			'wpstreamonboarding_js_vars',
			array(
				'admin_url'  => get_admin_url(),
				'plugin_url' => get_dashboard_url() . '/plugins.php',
				'upload_url' => admin_url( 'admin.php?page=wpstream_recordings&library_upload=1#library-new-video' ),
				'channel_url' => admin_url( 'admin.php?page=wpstream_live_channels&library_new=channel' ),
			)
		);
	}
}
