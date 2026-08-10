<?php

/**
 * Template loader for the WpStream plugin.
 *
 * When the bundled "hello-wpstream" theme is active, this class:
 *  - registers a selectable "WpStream Dashboard" page template,
 *  - swaps in the plugin's own page/single templates via `template_include`, and
 *  - enqueues the dashboard's JavaScript (and localized strings) on that page.
 *
 * @package wpstream-plugin
 */

/**
 * Registers and resolves the plugin's front-end templates for the companion theme.
 */
class WpStream_Template_Loader {
	/**
	 * Hook the template registration, resolution and script enqueue callbacks.
	 */
	public function __construct() {
		// Offer the Dashboard page template in the editor (late priority to win over others).
		add_filter( 'theme_page_templates', array( $this, 'wpstream_add_page_templates' ), 100 ); // Higher priority
		// Override which template file WordPress loads for our pages/CPTs.
		add_filter( 'template_include', array( $this, 'include_templates' ) );
		// Enqueue dashboard scripts when the Dashboard template is in use.
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_dashboard_scripts' ) );
	}

	/**
	 * Add the Dashboard page template to the theme's list of selectable templates.
	 *
	 * @param array $templates Existing page templates (slug => label).
	 * @return array Templates, with the Dashboard entry added under hello-wpstream.
	 */
	public function wpstream_add_page_templates( $templates ) {
		// Only expose the template when the companion theme is active.
		if ( get_template() === 'hello-wpstream' ) {
			// Add the Dashboard template
			$templates['wpstream-theme-dashboard.php'] = __('WpStream Dashboard Page', 'wpstream');
		}

		// Return the (possibly extended) template list.
		return $templates;
	}

	/**
	 * Resolve the actual template file to load for our pages and custom post types.
	 *
	 * @param string $template Template path WordPress would otherwise use.
	 * @return string Plugin-provided template when one applies, else the original.
	 */
	public function include_templates( $template ) {
		// Page template override: only for real pages under the companion theme.
		if ( is_page() && get_template() === 'hello-wpstream' ) {
			// Which page template was assigned to this page?
			$page_template = get_page_template_slug();

			// Route the Dashboard template to the plugin's own file.
			if ( 'wpstream-theme-dashboard.php' === $page_template ) {
				$file = WPSTREAM_PLUGIN_PATH . 'hello-wpstream/page-templates/wpstream-theme-dashboard.php';

				// Use it only if the file actually exists.
				if ( file_exists( $file ) ) {
					return $file;
				}
			}
		}

		// Single templates for custom post types
		if ( get_template() === 'hello-wpstream' ) {
			// Base directory holding the plugin's single-CPT templates.
			$single_template_path = WPSTREAM_PLUGIN_PATH . 'hello-wpstream/single-templates/';

			// Free live-stream product single view.
			if ( is_singular( 'wpstream_product' ) ) {
				$template_file = $single_template_path . 'single-wpstream_product.php';
				if ( file_exists( $template_file ) ) {
					$template = $template_file;
				}
			}

			// VOD product single view.
			if ( is_singular( 'wpstream_product_vod' ) ) {
				$template_file = $single_template_path . 'single-wpstream_product_vod.php';
				if ( file_exists( $template_file ) ) {
					$template = $template_file;
				}
			}

			// Bundle single view.
			if ( is_singular( 'wpstream_bundles' ) ) {
				$template_file = $single_template_path . 'single-wpstream_bundles.php';
				if ( file_exists( $template_file ) ) {
					$template = $template_file;
				}
			}
		}

		// No override matched (or file missing): keep the original template.
		return $template;
	}

	/**
	 * Enqueue the dashboard JS (and its localized strings) on the Dashboard page.
	 */
	public function maybe_enqueue_dashboard_scripts() {
		// Only run on the Dashboard template under the companion theme.
		if ( 'wpstream-theme-dashboard.php' === get_page_template_slug() && get_template() === 'hello-wpstream' ) {
			// Cache-busting version derived from the JS file's modified time.
			$modified_theme_js = gmdate( 'YmdHi', filemtime( WPSTREAM_PLUGIN_PATH . 'hello-wpstream/js/theme-dashboard.js' ) );
			// The dashboard reorders items via jQuery UI sortable.
			wp_enqueue_script( 'jquery-ui-sortable' );

			// Register/enqueue the dashboard behaviour script.
			wp_enqueue_script( 'wpstream_theme-dashboard-js', WPSTREAM_PLUGIN_DIR_URL . 'hello-wpstream/js/theme-dashboard.js', array( 'jquery' ), $modified_theme_js, true );
			// Expose admin URL and translated status strings to that script.
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

			// Cache-busting version for the chunked upload helper script.
			$modificated_ajax_upload_js = gmdate( 'YmdHi', filemtime( WPSTREAM_PLUGIN_PATH . 'hello-wpstream/js/ajax-upload.js' ) );
			// Enqueue the AJAX/plupload uploader used by the dashboard.
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