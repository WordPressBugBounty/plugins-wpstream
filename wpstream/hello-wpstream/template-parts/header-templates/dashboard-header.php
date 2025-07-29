<?php
/**
 * Dashboard header template
 *
 * @package wpstream-theme
 */

?>
<header id="masthead" class="site-header header-1">
	<div class="wpstream-dashboard-header">
		<nav id="nav-main" class="navbar navbar-expand-lg">
			<div class="<?php echo esc_attr( wpstream_theme_header_container_class() ); ?>">

				<button id="wpstream_toggle_dashboard_menu" class="wpstream_toggle_dashboard_menu d-none d-lg-block">
					<svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path fill-rule="evenodd" clip-rule="evenodd" d="M26.3333 16C26.3333 15.4478 25.8856 15 25.3333 15H6.66667C6.11439 15 5.66667 15.4478 5.66667 16C5.66667 16.5523 6.11439 17 6.66667 17H25.3333C25.8856 17 26.3333 16.5523 26.3333 16Z" fill="#2F2F35"/>
						<path fill-rule="evenodd" clip-rule="evenodd" d="M26.3333 9.33337C26.3333 8.78109 25.8856 8.33337 25.3333 8.33337H6.66667C6.11439 8.33337 5.66667 8.78109 5.66667 9.33337C5.66667 9.88566 6.11439 10.3334 6.66667 10.3334H25.3333C25.8856 10.3334 26.3333 9.88566 26.3333 9.33337Z" fill="#2F2F35"/>
						<path fill-rule="evenodd" clip-rule="evenodd" d="M26.3333 22.6667C26.3333 22.1144 25.8856 21.6667 25.3333 21.6667H6.66667C6.11439 21.6667 5.66667 22.1144 5.66667 22.6667C5.66667 23.219 6.11439 23.6667 6.66667 23.6667H25.3333C25.8856 23.6667 26.3333 23.219 26.3333 22.6667Z" fill="#2F2F35"/>
					</svg>
				</button>

				<div class="wpstream-dashboard-header-cta-wrap ms-auto ms-lg-0 align-items-center">
					<?php
					
				
					if (  wpstream_check_if_user_can_stream() ) {
					
						if ( function_exists( 'wc_get_account_menu_items' ) ) {
							$start_streaming_link = wc_get_endpoint_url( 'start-streaming' );
						} else {
							$start_streaming_link = wpstream_non_woo_get_account_endpoint_url( 'start-streaming' );
						}
						?>

						<a href="<?php echo esc_url( $start_streaming_link ); ?>" id="wpstream_dashboard_header_start_streaming" class="wpstream-gradient-button type-2-button-style">
							<?php esc_html_e( 'Start Streaming', 'hello-wpstream' ); ?>
						</a>
					



					<?php
					}
					require WPSTREAM_PLUGIN_PATH . 'hello-wpstream/template-parts/header-templates/dashboard-header-user-section.php';
					?>

					<!-- Offcanvas Navbar -->
					<div class="header-actions d-flex align-items-center d-lg-none">
						<!-- Navbar Toggle -->
						<button class="btn btn-outline-secondary ms-1 ms-md-2 wp-stream-toggle-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvas-user" aria-controls="offcanvas-navbar">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M26.3333 16C26.3333 15.4478 25.8856 15 25.3333 15H6.66667C6.11439 15 5.66667 15.4478 5.66667 16C5.66667 16.5523 6.11439 17 6.66667 17H25.3333C25.8856 17 26.3333 16.5523 26.3333 16Z" fill="#2F2F35"/>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M26.3333 9.33337C26.3333 8.78109 25.8856 8.33337 25.3333 8.33337H6.66667C6.11439 8.33337 5.66667 8.78109 5.66667 9.33337C5.66667 9.88566 6.11439 10.3334 6.66667 10.3334H25.3333C25.8856 10.3334 26.3333 9.88566 26.3333 9.33337Z" fill="#2F2F35"/>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M26.3333 22.6667C26.3333 22.1144 25.8856 21.6667 25.3333 21.6667H6.66667C6.11439 21.6667 5.66667 22.1144 5.66667 22.6667C5.66667 23.219 6.11439 23.6667 6.66667 23.6667H25.3333C25.8856 23.6667 26.3333 23.219 26.3333 22.6667Z" fill="#2F2F35"/>
                            </svg>
                            <span class="visually-hidden-focusable"><?php esc_html_e( 'Menu', 'hello-wpstream' ); ?></span>
						</button>

					</div><!-- .header-actions -->
				</div>

			</div><!-- wpstream_theme_container_class(); -->

		</nav><!-- .navbar -->

	</div><!-- .fixed-top .bg-light -->
			<!-- offcanvas user -->
	<?php
	require WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/header-templates/header-offcanvas-menu-container.php';
	?>

</header><!-- #masthead -->
