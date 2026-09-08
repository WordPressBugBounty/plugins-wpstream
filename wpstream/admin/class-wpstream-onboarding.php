<?php
/**
 * Onboarding workflow module.
 *
 * @package    Wpstream
 * @subpackage Wpstream/admin
 */

/**
 * Owns the Quick Start wizard and its account/content actions.
 */
class Wpstream_Onboarding {

	/** @var Wpstream Main plugin module. */
	private $main;

	/** @var bool Whether the wizard footer callback has been registered. */
	private $footer_registered = false;

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

		if ( ! $this->footer_registered ) {
			add_action( 'admin_footer', function () {
				if ( current_user_can( 'administrator' ) ) {
					$this->render_wizard();
				}
			} );
			$this->footer_registered = true;
		}

		ob_start();
		$this->render_quick_start();
		return (string) ob_get_clean();
	}

	/**
	 * Render a named legacy fragment for compatibility forwarders.
	 *
	 * @internal Only Wpstream_Admin compatibility methods should call this.
	 *
	 * @param string $part Fragment name.
	 * @return mixed
	 */
	public function render_part( $part ) {
		$parts = array(
			'quick_start'   => 'render_quick_start',
			'wizard'        => 'render_wizard',
			'wizard_header' => 'render_wizard_header',
			'wizard_footer' => 'render_wizard_footer',
			'account'       => 'render_account_step',
			'password'      => 'random_password',
			'path'          => 'render_path_step',
			'live'          => 'render_live_step',
			'live_free'     => 'render_live_free_step',
			'live_ppv'      => 'render_live_ppv_step',
			'woo_warning'   => 'render_woo_warning',
			'vod'           => 'render_vod_step',
			'vod_free'      => 'render_vod_free_step',
			'file_warning'  => 'render_file_warning',
			'vod_ppv'       => 'render_vod_ppv_step',
			'dashboard_page'=> 'register_dashboard_page',
			'standalone'    => 'load_standalone_wizard',
			'page_footer'   => 'render_page_footer',
		);

		if ( ! isset( $parts[ $part ] ) ) {
			return new WP_Error( 'unknown_onboarding_fragment', __( 'Unknown onboarding fragment.', 'wpstream' ) );
		}

		return $this->{$parts[ $part ]}();
	}

	/** Register the legacy hidden dashboard endpoint. */
	private function register_dashboard_page() {
		add_dashboard_page( '', '', 'administrator', 'wpstream-onboarding', '' );
	}

	/** Bootstrap the legacy standalone wizard context. */
	private function load_standalone_wizard() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		set_current_screen();
		remove_action( 'admin_print_styles', 'gutenberg_block_editor_admin_print_styles' );
		echo $this->render();
	}

	/** Render the legacy page-footer entry point when Quick Start is active. */
	private function render_page_footer() {
		if ( current_user_can( 'administrator' )
			&& isset( $_GET['page'] )
			&& 'wpstream_onboard' === $_GET['page'] ) {
			$this->render_wizard();
		}
	}

	/** Render the Quick Start landing screen. */
	private function render_quick_start() {
		$pack_details = $this->main->quota_manager->get_live_quota_data( 'wpstream_pre_onboard_display' );
		$this->main->show_user_data( $pack_details );
		$thumb = plugin_dir_url( dirname( __FILE__ ) ) . 'img/logo_onboarding.svg';
		?>
		<div id="wpstream-onboarding-root"></div>
			<div class="wpstream_quick_start_wrapper">
				<img class="wpstream_onboarding_logo" src="<?php echo esc_url( $thumb ); ?>" />

				<h1><?php print esc_html__( 'WpStream', 'wpstream' ) . ' <span class="header_special">' . esc_html__( 'Quick Start', 'wpstream' ) . '</span>'; ?></h1>

				<p>
					<?php esc_html_e( 'The quick start guide will help you set up Live Streaming, Video On Demand, and Monetization in a fun and interactive way. Give it a shot! ', 'wpstream' ); ?>
				</p>

				<div id="wpstream_trigger_quick_start" class="wpstream_button wpstream_button_action"><?php esc_html_e( 'Start the Guide', 'wpstream' ); ?></div>
			</div>

			<script type="text/javascript">
				//<![CDATA[
					jQuery(document).ready(function(){
						jQuery(".wpstream_on_boarding_wrapper").show();
						jQuery(".wpstream_modal_background_onboard").show();
					});
				//]]>
			</script>
		<?php
	}

	/** Render the complete onboarding wizard. */
	private function render_wizard() {
		/**
		 * Filter the onboarding wizard steps.
		 *
		 * @since 4.14.0
		 *
		 * @param array $steps Step slug => callable, in display order.
		 */
		$steps = (array) apply_filters(
			'wpstream_onboarding_steps',
			array(
				'account'   => array( $this->main->admin, 'wpstream_onboarding_step1' ),
				'path'      => array( $this->main->admin, 'wpstream_onboarding_step2' ),
				'live'      => array( $this->main->admin, 'wpstream_onboarding_step3_live_streaming' ),
				'live_free' => array( $this->main->admin, 'wpstream_onboarding_step_3_A_live_streaming_free_view' ),
				'live_ppv'  => array( $this->main->admin, 'wpstream_onboarding_step_3_B_live_streaming_pay_per_view' ),
				'vod'       => array( $this->main->admin, 'wpstream_onboarding_step4_vod' ),
				'vod_free'  => array( $this->main->admin, 'wpstream_onboarding_step_4_free_vod' ),
				'vod_ppv'   => array( $this->main->admin, 'wpstream_onboarding_step_4_ppv_vod' ),
			)
		);

		$this->render_wizard_header();
		foreach ( $steps as $step ) {
			if ( is_callable( $step ) ) {
				call_user_func( $step );
			}
		}
		$this->render_wizard_footer();
	}

	/** Open the wizard wrapper. */
	private function render_wizard_header() {
		$thumb = plugin_dir_url( dirname( __FILE__ ) ) . 'img/logo_onboarding.svg';
		?>
		<div class="wpstream_on_boarding_wrapper">
			<div class="wpstream_close_onboarding wpstream_close_initial_onboarding"></div>
			<img class="wpstream_onboarding_logo" src="<?php echo esc_url( $thumb ); ?>" />
			<div class="wpstream_close_onboarding_warning"></div>
		<?php
	}

	/** Close the wizard wrapper and render its backdrop. */
	private function render_wizard_footer() {
		?>
			</div>
			<div class="wpstream_modal_background_onboard"></div>
		<?php
	}

	/** Render the account login and registration step. */
	private function render_account_step() {
		$options = array(
			2 => array(
				'label' => 'WpStream.net Username or Email',
				'name'  => 'api_username',
				'type'  => 'text',
			),
			3 => array(
				'label' => 'WpStream.net Password',
				'name'  => 'api_password',
				'type'  => 'password',
			),
		);
		$connected = $this->main->wpstream_live_connection->is_connected();
		?>
		<div class="wpstream_step_wrapper wpstream_step_1" id="wpstream_step_1">
			<div class="wpstream_has_credential">
				<h1><?php esc_html_e( 'WpStream Account', 'wpstream' ); ?></h1>

				<div class="wpstream_on_board_login_wrapper_explanations">
					<?php esc_html_e( 'A WpStream account is required to make use of the plugin.', 'wpstream' ); ?>
				</div>

				<div class="wpstream_check_account_status">
					<?php esc_html_e( 'Checking if you are already logged.....', 'wpstream' ); ?>
				</div>

				<div class="wpstream_onboarding_notification"></div>

				<div class="wpstream_option_wrapper wpstream_on_board_login_wrapper">
					<h2><?php esc_html_e( 'Login with your WpStream Account', 'wpstream' ); ?></h2>
					<?php
					foreach ( $options as $option ) {
						print '<div class="wpstream_option">';
						print '<label for="' . $option['name'] . '">' . $option['label'] . '</label>';
						if ( 'password' === $option['type'] ) {
							print '<input id="' . $option['name'] . '" type="password" size="36" name="' . $option['name'] . '" value="" autocomplete="current-password" />';
						} else {
							$value = esc_html( get_option( 'wpstream_' . $option['name'], '' ) );
							print '<input id="' . $option['name'] . '" type="' . $option['type'] . '" size="36" name="' . $option['name'] . '" value="' . esc_html( $value ) . '" />';
						}
						print '</div>';
					}
					?>
					<input type="submit" name="submit" class="wpstream_button wpstream_button_action wpstream_onboard_login" value="<?php esc_html_e( 'Login', 'wpstream' ); ?>" />
				</div>

				<div class="wpstream_on_board_register_wrapper">
					<h2><?php esc_html_e( 'Register for  a WpStream Account', 'wpstream' ); ?></h2>
					<div class="wpstream_option">
						<label for="wpstream_register_email"><?php esc_html_e( 'Your Email', 'wpstream' ); ?></label>
						<input id="wpstream_register_email" type="text" size="36" name="wpstream_register_email" value="<?php echo get_option( 'admin_email' ); ?>" />
					</div>

					<div class="wpstream_option">
						<input id="wpstream_register_password" hidden type="text" size="36" name="wpstream_register_password" value="<?php echo $this->random_password(); ?>" />
						<span><?php esc_html_e( 'We\'ll send the password to the email you attached. ', 'wpstream' ); ?></span>
					</div>

					<?php if ( isset( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) : ?>
						<script async defer src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'js/vendor/altcha.min.js?ver=3.2.1' ); ?>" type="module"></script>
						<div class="wpstream_option" style="display:none;">
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

					<div class="wpstream_option wpstream_terms_agreement">
						<input id="wpstream_register_privacy" type="checkbox" name="wpstream_register_privacy" />
						<label for="wpstream_register_privacy">
							<?php
							printf(
								esc_html__( 'By registering you agree to the %sPrivacy Policy%s', 'wpstream' ),
								'<a href="https://wpstream.net/privacy-policy/" target="_blank">',
								'</a>'
							);
							?>
						</label>
					</div>
					<input type="submit" name="submit" class="wpstream_button wpstream_button_action wpstream_onboard_register" value="<?php esc_html_e( 'register', 'wpstream' ); ?>" />
				</div>

				<div id="wpstream_onboarding_action_login"><?php esc_html_e( 'I already have a WpStream Account', 'wpstream' ); ?></div>
				<div id="wpstream_onboarding_action_register"><?php esc_html_e( 'Back to Registration', 'wpstream' ); ?></div>
			</div>

			<?php $ajax_nonce = wp_create_nonce( 'wpstream_onboarding_nonce' ); ?>
			<input type="hidden" id="wpstream_onboarding_nonce" value="<?php echo esc_html( $ajax_nonce ); ?>" />
			<div id="wpstream_on_board" class="wpstream_action_next_step" data-nextthing="wpstream_step_2" style="display:none;">Move to step 2</div>
		</div>
		<?php

		if ( $connected ) {
			print '<div id="wpstream_have_token"></div>';
		}
	}

	/** Generate the registration password used by the account step. */
	private function random_password() {
		$alphabet     = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
		$password     = array();
		$alpha_length = strlen( $alphabet ) - 1;
		for ( $index = 0; $index < 16; $index++ ) {
			$password[] = $alphabet[ random_int( 0, $alpha_length ) ];
		}

		return implode( $password );
	}

	/** Render the live-or-VOD path choice. */
	private function render_path_step() {
		?>
		<div class="wpstream_step_wrapper wpstream_step_2" id="wpstream_step_2">
			<h1>Welcome to <span class="header_special">WpStream</span>! How would you like to start?</h1>
			<div class="wpstream_accordion_header wpstream_action_next_step" data-nextthing="wpstream_step_3"><?php esc_html_e( 'Go LIVE!', 'wpstream' ); ?></div>
			<div class="wpstram_or">or</div>
			<div class="wpstream_accordion_header wpstream_action_next_step wpstream_step_2_create_vod" data-nextthing="wpstream_step_4"><?php esc_html_e( 'Create a Video-On-Demand (VOD)', 'wpstream' ); ?></div>
		</div>
		<?php
	}

	/** Render the free-or-paid live choice. */
	private function render_live_step() {
		?>
		<div class="wpstream_step_wrapper wpstream_step_3 wpstream_onboarding_live" id="wpstream_step_3">
			<h1><?php esc_html_e( 'Do you want to charge a fee for watching?', 'wpstream' ); ?></h1>
			<div class="wpstream_accordion_header wpstream_action_next_step wpstream_step_3a" data-nextthing="wpstream_step_3a"><?php esc_html_e( 'No - Free-To-View (FTV)', 'wpstream' ); ?></div>
			<div class="wpstram_or">or</div>
			<div class="wpstream_accordion_header wpstream_action_next_step" data-nextthing="wpstream_step_3b"><?php esc_html_e( 'Yes - Pay-Per-View (PPV)', 'wpstream' ); ?></div>
			<div class="wpstream_initial_onboarding_controls_wrapper">
				<span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_2"><?php esc_html_e( 'Prev', 'wpstream' ); ?></span>
			</div>
		</div>
		<?php
	}

	/** Render the free live-channel creation step. */
	private function render_live_free_step() {
		?>
		<div class="wpstream_step_wrapper wpstream_step_3a wpstream_onboarding_live" id="wpstream_step_3a">
			<h1><?php esc_html_e( 'Let’s create your first FTV live channel', 'wpstream' ); ?></h1>
			<div id="wpstream_onboard_live_notice" class="wpstream_onboarding_notification"></div>
			<div id="wpstream_onboard_live" class="wpstream_accordion_container">
				<label>Name your first Free-To-View channel</label>
				<input type="text" name="channel_name" id="wpstream_onboarding_channel_name" class="wpstream_onboarding_channel_name" value="<?php esc_html_e( 'My first FTV channel', 'wpstream' ); ?>">
				<input type="submit" name="submit" id="wpstream_on_board_create_channel" class="wpstream_button wpstream_button_action wpstream_onboard_live_action" value="<?php esc_html_e( 'Create Channel', 'wpstream' ); ?>" />
			</div>
			<div class="wpstream_initial_onboarding_controls_wrapper">
				<span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_2"><?php esc_html_e( 'Prev', 'wpstream' ); ?></span>
			</div>
		</div>
		<?php
	}

	/** Render the paid live-channel creation step. */
	private function render_live_ppv_step() {
		?>
		<div class="wpstream_step_wrapper wpstream_step_3b wpstream_onboarding_live" id="wpstream_step_3b">
			<h1><?php esc_html_e( 'Make your live stream Pay Per View', 'wpstream' ); ?></h1>
			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<div id="wpstream_onboard_live_ppv_notice" class="wpstream_onboarding_notification"></div>
				<div id="wpstream_onboard_live_ppv" class="wpstream_accordion_container">
					<label><?php esc_html_e( 'Choose a name for your channel', 'wpstream' ); ?></label>
					<input type="text" name="channel_name" id="wpstream_onboarding_channel_name_ppv" class="wpstream_onboarding_channel_name" value="<?php esc_html_e( 'My First PPV Channel', 'wpstream' ); ?>">
					<label><?php esc_html_e( 'Pay-Per-View Price ($)', 'wpstream' ); ?></label>
					<input type="text" name="channel_name" id="wpstream_onboarding_event_price_ppv" class="wpstream_onboarding_event_price" value="10">
					<input type="submit" name="submit" id="wpstream_onboard_live_ppv_action" class="wpstream_button wpstream_button_action wpstream_onboard_live_ppv_action" value="<?php esc_html_e( 'Create Channel', 'wpstream' ); ?>" />
				</div>
			<?php else : ?>
				<?php $this->render_woo_warning(); ?>
			<?php endif; ?>
			<div class="wpstream_initial_onboarding_controls_wrapper">
				<span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_2"><?php esc_html_e( 'Prev', 'wpstream' ); ?></span>
			</div>
		</div>
		<?php
	}

	/** Render the missing-WooCommerce warning. */
	private function render_woo_warning() {
		print '<div class="wpstream_warning_onboarding">
			' . esc_html__( 'Pay-Per-View streaming requires WooCommerce. Please install the WooCommerce plugin and try again.', 'wpstream' ) . '
			</br>
			<div class="wpstream_install_plugin">' . esc_html__( 'Install WooCommerce', 'wpstream' ) . '</div>
			<div class="wpstream_onboarding_tryagain">' . esc_html__( 'Try Again', 'wpstream' ) . '</div>
		</div>';
	}

	/** Render the free-or-paid VOD choice. */
	private function render_vod_step() {
		?>
		<div class="wpstream_step_wrapper wpstream_step_4 wpstream_onboarding_vod" id="wpstream_step_4">
			<h1><?php esc_html_e( 'Do you want to charge a fee for watching?', 'wpstream' ); ?></h1>
			<div class="wpstream_accordion_header wpstream_action_next_step wpstream_step_4a" data-control="wpstream_onboard_vod_free" data-nextthing="wpstream_step_4a"><?php esc_html_e( 'No - Free-To-View (FTV)', 'wpstream' ); ?></div>
			<div class="wpstram_or">or</div>
			<input type="hidden" id="wpstream_onboarding_video_list_nonce" value="<?php echo wp_create_nonce( 'wpstream_onboarding_video_list_nonce' ); ?>">
			<div class="wpstream_accordion_header wpstream_action_next_step wpstream_step_4b" data-control="wpstream_onboard_vod_ppv" data-nextthing="wpstream_step_4b"><?php esc_html_e( 'Yes - Pay-Per-View (PPV)', 'wpstream' ); ?></div>
			<div class="wpstream_initial_onboarding_controls_wrapper">
				<span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_2"><?php esc_html_e( 'Prev', 'wpstream' ); ?></span>
			</div>
		</div>
		<?php
	}

	/** Render the free VOD creation step. */
	private function render_vod_free_step() {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'wpstream_page_wpstream_onboard' !== $current_screen->base ) {
			return;
		}
		?>
		<div class="wpstream_step_wrapper wpstream_step_4a wpstream_onboarding_live" id="wpstream_step_4a">
			<h1><?php esc_html_e( 'Let’s create your first Free-To-View VOD', 'wpstream' ); ?></h1>
			<div id="wpstream_onboard_vod_free_notice" class="wpstream_onboarding_notification"></div>
			<div id="wpstream_onboard_vod_free" class="wpstream_accordion_container">
				<div class="spinner"></div>
				<div class="wpstream-step-container" style="display: none">
					<label><?php esc_html_e( 'Name your FTV Video-On-Demand', 'wpstream' ); ?></label>
					<input type="text" name="channel_name" class="wpstream_onboarding_vod_name" id="wpstream_onboarding_vod_name" value="<?php esc_html_e( 'My First FTV VOD', 'wpstream' ); ?>">
					<div id="wpstream_free_vod_dropdown_videos_list"></div>
					<input type="submit" name="submit" class="wpstream_button wpstream_button_action wpstream_onboard_vod_free_action" id="wpstream_onboard_vod_free_action" value="Create FTV VOD">
				</div>
			</div>
			<?php $this->render_file_warning(); ?>
			<div class="wpstream_initial_onboarding_controls_wrapper">
				<span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_4"><?php esc_html_e( 'Prev', 'wpstream' ); ?></span>
			</div>
		</div>
		<?php
	}

	/** Render the missing-recording warning. */
	private function render_file_warning() {
		print '<div class="wpstream_warning_onboarding" style="display: none">
			' . esc_html__( 'A recording is needed to create a VOD from. There are no recordings under your account. You can create new recordings by recording a live channel or uploading video files directly.', 'wpstream' ) . '
			</br>
			<div class="wpstream_upload_video">' . esc_html__( 'Upload Video', 'wpstream' ) . '</div>
			<div class="wpstream_onboarding_tryagain">' . esc_html__( 'Try Again', 'wpstream' ) . '</div>
		</div>';
	}

	/** Render the paid VOD creation step. */
	private function render_vod_ppv_step() {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'wpstream_page_wpstream_onboard' !== $current_screen->base ) {
			return;
		}
		?>
		<div class="wpstream_step_wrapper wpstream_step_4b wpstream_onboarding_live" id="wpstream_step_4b">
			<h1><?php esc_html_e( 'Let\'s create your first Pay-Per-View VOD', 'wpstream' ); ?></h1>
			<div id="wpstream_onboard_vod_ppv_notice" class="wpstream_onboarding_notification"></div>
			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<div id="wpstream_onboard_vod_ppv" class="wpstream_accordion_container">
					<div class="spinner"></div>
					<div class="wpstream-step-container" style="display: none">
						<label><?php esc_html_e( 'Name your PPV Video-On-Demand', 'wpstream' ); ?></label>
						<input type="text" name="channel_name" class="wpstream_onboarding_ppv_vod_name" id="wpstream_onboarding_ppv_vod_name" value="<?php esc_html_e( 'My First PPV VOD', 'wpstream' ); ?>">
						<div id="wpstream_ppv_vod_dropdown_videos_list"></div>
						<label><?php esc_html_e( 'Pay-Per-View Price', 'wpstream' ); ?></label>
						<input type="text" name="channel_name" class="wpstream_onboarding_vod_price" id="wpstream_onboarding_vod_price" value="10">
						<input type="submit" name="submit" class="wpstream_button wpstream_button_action wpstream_onboard_vod_ppv_action" id="wpstream_onboard_vod_ppv_action" value="<?php esc_html_e( 'Create PPV VOD', 'wpstream' ); ?>" />
					</div>
				</div>
				<?php $this->render_file_warning(); ?>
			<?php else : ?>
				<?php $this->render_woo_warning(); ?>
			<?php endif; ?>
			<div class="wpstream_initial_onboarding_controls_wrapper">
				<span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_4"><?php esc_html_e( 'Prev', 'wpstream' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Dispatch an onboarding AJAX action through the workflow interface.
	 *
	 * @param string $action WordPress AJAX action name.
	 * @return mixed
	 */
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

	/** Log in to WpStream during onboarding. */
	private function handle_login() {
		check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );

		if ( current_user_can( 'administrator' ) ) {
			$username = sanitize_text_field( $_POST['api_username'] );
			$password = $_POST['api_password'];
			update_option( 'wpstream_api_username', $username );
			update_option( 'wpstream_api_password', $password, false );
			delete_transient( 'wpstream_token_api' );

			$connected = $this->main->wpstream_live_connection->is_connected();
			$this->main->wpstream_live_connection->wpstream_get_videos();
			ob_end_clean();

			if ( $connected ) {
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
		$branch = isset( $_GET['branch'] ) ? sanitize_text_field( wp_unslash( $_GET['branch'] ) ) : '';

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
			WPSTREAM_PLUGIN_VERSION,
			true
		);
		wp_localize_script(
			'wpstream-on-boarding-js',
			'wpstreamonboarding_js_vars',
			array(
				'admin_url'  => get_admin_url(),
				'plugin_url' => get_dashboard_url() . '/plugins.php',
				'upload_url' => get_dashboard_url() . 'admin.php?page=wpstream_recordings',
				'branch'     => $branch,
			)
		);

		wp_enqueue_script(
			'wpstream-on-boarding-page-js',
			plugin_dir_url( __FILE__ ) . 'js/wpstream-onboarding-page.js',
			array(),
			WPSTREAM_PLUGIN_VERSION,
			true
		);
		wp_localize_script(
			'wpstream-on-boarding-page-js',
			'wpstream_onboarding_page_vars',
			array(
				'admin_url'      => get_admin_url(),
				'request_url'    => WPSTREAM_CLICK,
				'wps_user'       => get_option( 'wpstream_api_username_from_token' ),
				'current_page'   => isset( $_GET['onboard'] ) && 'yes' === $_GET['onboard'] ? 'post_edit' : 'onboarding',
				'plugin_version' => WPSTREAM_PLUGIN_VERSION,
				'branch'         => $branch,
			)
		);
	}
}
