<?php
/**
 * Template Name: WpStream Dashboard Page
 * Template Post Type: page
 *
 * Logged-in-only "My Account" style dashboard page. Renders the sidebar menu,
 * the dashboard header, and the account content. When WooCommerce's My Account
 * is unavailable, it falls back to routing an `?endpoint=` query var to the
 * matching dashboard template part (dashboard, start-streaming, watch-later,
 * edit-account, logout).
 *
 * @package wpstream-plugin
 */

// Guests may not view the dashboard: bounce anyone not logged in to the home page.
if ( ! is_user_logged_in() ) {
	wp_safe_redirect( esc_url( home_url( '/' ) ) );
	exit();
}

// Output the theme header (opening markup, <head>, site chrome).
get_header();

?>

	<!-- Dashboard page wrapper; container class comes from the active theme layout setting. -->
	<div id="content" class="site-content wpstream-dashboard-page <?php echo esc_attr( wpstream_theme_container_class() ); ?>">

		<div id="primary" class="content-area">
			<!-- Hook to add something nice -->
			<?php do_action( 'bs_after_primary' ); ?>

			<main id="main" class="site-main">
				<div class="entry-content wpstream-dashboard-page-wrapper">
					<section class="wpstream_section wpstream_dashboard_section">
						<div class="<?php echo esc_attr( wpstream_theme_container_class() ); ?>">

							<!-- Flex row: dashboard menu on one side, content on the other. -->
							<div class="wpstream_dashboard_section_content_wrapper_flex">

								<!-- Left column: the dashboard navigation menu. -->
								<?php
								require WPSTREAM_FRAMEWORK_BASE . '/template-parts/dashboard-templates/template-menu-dashboard.php';
								?>

								<!-- Right column: dashboard header plus the routed content. -->
								<div class=" wpstream_dashboard_section_content_wrapper">

									<!-- Dashboard header (greeting / account summary). -->
									<?php
									require WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/header-templates/dashboard-header.php';
									?>

									<!-- Main dashboard content area. -->
									<div class="wpstream-dashboard-page-content">
										<?php
										// Print the account content shortcode (WooCommerce My Account when available).
										wpstream_my_account_content_shortcode();

										// Fallback routing when WooCommerce's My Account is not present:
										// pick the dashboard template part based on the ?endpoint= query var.
										if ( ! function_exists( 'woocommerce_my_account' ) ) {
											// Read and sanitise the requested endpoint (nonce not needed for a read-only view switch).
											$argument = isset( $_GET['endpoint'] ) ? sanitize_text_field( wp_unslash( $_GET['endpoint'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended

											// Route the endpoint to its matching template part.
											switch ( $argument ) {
												// No endpoint: render nothing extra here.
												case '':
													break;

												// Dashboard overview screen.
												case 'dashboard':
													include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/template-dashboard.php';

													break;

												// "Start streaming" go-live screen.
												case 'start-streaming':
													include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/template-start-streaming.php';

													break;

												// Saved "watch later" list.
												case 'watch-later':
													include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/template-watch-later.php';

													break;

												// Edit-account form.
												case 'edit-account':
													include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/template-edit-account.php';

													break;

												// Log the user out.
												case 'logout':
													wp_logout();

													break;
											}
										}

										?>

									</div>
								</div>
							</div>
						</div>
					</section>
				</div>
			</main>
		</div>
	</div>
<?php

// Output the theme footer (closing markup, scripts).
get_footer();

