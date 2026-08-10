<?php
/**
 * Menu dashboard template.
 *
 * Renders the left-hand (large-screen) dashboard sidebar: the site/dashboard
 * logo variants (standard, retina, collapsed) pulled from the Customizer theme
 * mods, followed by the generated user navigation menu.
 *
 * @package wpstream-theme
 */

?>

<!-- Sidebar wrapper: visible only on large screens (Bootstrap d-none d-lg-block). -->
<div class="wpstream_theme_dashboard_menu_wrapper d-none d-lg-block">
	<div class="wpstream_theme_dashboard_menu_container">
		<!-- Logo area: expanded, retina and collapsed logo variants. -->
		<div class="wpstream-dashboard-header-logo-wrapper">
			<?php
			// Default to no logo URL until a Customizer mod supplies one.
			$logo = '';

			// Attachment IDs for the three configurable dashboard logo variants.
			$dashboard_logo_id = get_theme_mod( 'dashboard_logo' );
			$dashboard_logo_collapsed_id = get_theme_mod( 'dashboard_logo_collapsed' );
			$dashboard_retina_logo_id = get_theme_mod( 'wpstream_theme_dashboard_retina_logo' );

			// Resolve the standard logo attachment ID to a full-size image URL.
			if ($dashboard_logo_id){
				$logo = wp_get_attachment_image_src( $dashboard_logo_id, 'full' )[0];
			}

			// Resolve the retina logo attachment ID to a full-size image URL.
			if ( $dashboard_retina_logo_id ) {
				$dashboard_retina_logo_src = wp_get_attachment_image_src( $dashboard_retina_logo_id, 'full' )[0];
			}

			?>
			<!-- Expanded-state brand logo, links back to the site home. -->
			<a class="navbar-brand wpstream-dashboard-logo h-100 w-100" href="<?php echo esc_url( home_url() ); ?>">
			

				<!-- Standard logo, shown only when one is configured. -->
				<?php if (!empty($logo)): ?>
					<img src="<?php echo esc_url( $logo ); ?>" alt="logo" class="logo xs mh-100">
      	  		<?php endif; ?>

                <!-- Retina (high-DPI) logo, shown only when one is configured. -->
                <?php if (!empty($dashboard_retina_logo_src)): ?>

                    <img src="<?php echo esc_url( $dashboard_retina_logo_src ); ?>" alt="logo" class="logo xs logo-retina mh-100">

                <?php endif; ?>
			</a>
			<!-- Collapsed-state brand logo (shown when the sidebar is minimized). -->
			<a class="navbar-brand wpstream-dashboard-logo-collapsed" href="<?php echo esc_url( home_url() ); ?>">
				<?php  
				// Print the collapsed logo image when its attachment ID is set.
				if($dashboard_logo_collapsed_id){
					print '<img src="'.  esc_url( wp_get_attachment_image_src( $dashboard_logo_collapsed_id, 'full' )[0] ).'" alt="logo" class="logo xs">';
				}
				?>
			</a>
		</div>
		<?php
		// Output the user's dashboard navigation menu (commented-out guard left in place).
//        if ( class_exists( 'WooCommerce' ) && class_exists( 'Wpstream_Player' ) ) {
	        echo wpstream_generate_user_menu();//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
//        }
        ?>
	</div>
</div>