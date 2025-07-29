<?php

/**
 * Template loader for the WpStream plugin
 *
 * @package wpstream-plugin
 */

class WpStream_Template_Loader {
	public function __construct() {
		add_filter( 'theme_page_templates', array( $this, 'wpstream_add_page_templates' ), 100 ); // Higher priority
		add_filter( 'template_include', array( $this, 'include_templates' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_dashboard_scripts' ) );
	}

	public function wpstream_add_page_templates( $templates ) {
		if ( get_template() === 'hello-wpstream' ) {
			// Add the Dashboard template
			$templates['wpstream-theme-dashboard.php'] = __('WpStream Dashboard Page', 'wpstream');
		}

		return $templates;
	}

	public function include_templates( $template ) {
		if ( is_page() && get_template() === 'hello-wpstream' ) {
			$page_template = get_page_template_slug();

			if ( 'wpstream-theme-dashboard.php' === $page_template ) {
				$file = WPSTREAM_PLUGIN_PATH . 'hello-wpstream/page-templates/wpstream-theme-dashboard.php';

				if ( file_exists( $file ) ) {
					return $file;
				}
			}
		}

		return $template;
	}

	public function maybe_enqueue_dashboard_scripts() {
		if ( 'wpstream-theme-dashboard.php' === get_page_template_slug() && get_template() === 'hello-wpstream' ) {
			$modified_theme_js = gmdate( 'YmdHi', filemtime( WPSTREAM_PLUGIN_PATH . 'hello-wpstream/js/theme-dashboard.js' ) );
			wp_enqueue_script( 'jquery-ui-sortable' );

			wp_enqueue_script( 'wpstream_theme-dashboard-js', WPSTREAM_PLUGIN_DIR_URL . 'hello-wpstream/js/theme-dashboard.js', array( 'jquery' ), $modified_theme_js, true );
			wp_localize_script(
				'wpstream_theme-dashboard-js',
				'wpstreamDashboardVars',
				array(
					'admin_url'     => get_admin_url(),
					'saving'        => esc_html__( 'Updating your details....', 'hello-wpstream' ),
					'saved'         => esc_html__( 'The changes were saved', 'hello-wpstream' ),
					'notsaved'      => esc_html__( 'Something did not not work. Please try again.', 'hello-wpstream' ),
					'createchannel' => esc_html__( 'We are creating the channel. The page will refresh after this is done.', 'hello-wpstream' ),
				)
			);

			$modificated_ajax_upload_js = gmdate( 'YmdHi', filemtime( WPSTREAM_PLUGIN_PATH . 'hello-wpstream/js/ajax-upload.js' ) );
			wp_enqueue_script(
				'ajax-upload',
				WPSTREAM_PLUGIN_DIR_URL . 'hello-wpstream/js/ajax-upload.js',
				array(
					'jquery',
					'plupload-handlers',
				),
				$modificated_ajax_upload_js,
				true
			);
		}
	}
}