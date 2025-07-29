<?php
/**
 * Template Name: WpStream Dashboard Page
 * Template Post Type: page
 *
 * @package wpstream-plugin
 */

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( esc_url( home_url( '/' ) ) );
	exit();
}

get_header();

?>

	<div id="content" class="site-content wpstream-dashboard-page <?php echo esc_attr( wpstream_theme_container_class() ); ?>">

		<div id="primary" class="content-area">
			<!-- Hook to add something nice -->
			<?php do_action( 'bs_after_primary' ); ?>

			<main id="main" class="site-main">
				<div class="entry-content wpstream-dashboard-page-wrapper">
					<section class="wpstream_section wpstream_dashboard_section">
						<div class="<?php echo esc_attr( wpstream_theme_container_class() ); ?>">

							<div class="wpstream_dashboard_section_content_wrapper_flex">

								<?php
								require WPSTREAM_FRAMEWORK_BASE . '/template-parts/dashboard-templates/template-menu-dashboard.php';
								?>

								<div class=" wpstream_dashboard_section_content_wrapper">

									<?php
									require WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/header-templates/dashboard-header.php';
									?>

									<div class="wpstream-dashboard-page-content">
										<?php
										wpstream_my_account_content_shortcode();

										if ( ! function_exists( 'woocommerce_my_account' ) ) {
											$argument = isset( $_GET['endpoint'] ) ? sanitize_text_field( wp_unslash( $_GET['endpoint'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended

											switch ( $argument ) {
												case '':
													break;

												case 'dashboard':
													include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/template-dashboard.php';

													break;

												case 'start-streaming':
													include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/template-start-streaming.php';

													break;

												case 'watch-later':
													include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/template-watch-later.php';

													break;

												case 'edit-account':
													include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/template-edit-account.php';

													break;

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

get_footer();

