<?php
/**
 * Menu dashboard template
 *
 * @package wpstream-theme
 */

?>

<div class="wpstream_theme_dashboard_menu_wrapper d-none d-lg-block">
	<div class="wpstream_theme_dashboard_menu_container">
		<div class="wpstream-dashboard-header-logo-wrapper">
			<?php
			$logo = '';

			$dashboard_logo_id = get_theme_mod( 'dashboard_logo' );
			$dashboard_logo_collapsed_id = get_theme_mod( 'dashboard_logo_collapsed' );
			$dashboard_retina_logo_id = get_theme_mod( 'wpstream_theme_dashboard_retina_logo' );

			if ($dashboard_logo_id){
				$logo = wp_get_attachment_image_src( $dashboard_logo_id, 'full' )[0];
			}

			if ( $dashboard_retina_logo_id ) {
				$dashboard_retina_logo_src = wp_get_attachment_image_src( $dashboard_retina_logo_id, 'full' )[0];
			}

			?>
			<a class="navbar-brand wpstream-dashboard-logo h-100 w-100" href="<?php echo esc_url( home_url() ); ?>">
			

				<?php if (!empty($logo)): ?>
					<img src="<?php echo esc_url( $logo ); ?>" alt="logo" class="logo xs mh-100">
      	  		<?php endif; ?>

                <?php if (!empty($dashboard_retina_logo_src)): ?>

                    <img src="<?php echo esc_url( $dashboard_retina_logo_src ); ?>" alt="logo" class="logo xs logo-retina mh-100">

                <?php endif; ?>
			</a>
			<a class="navbar-brand wpstream-dashboard-logo-collapsed" href="<?php echo esc_url( home_url() ); ?>">
				<?php  
				if($dashboard_logo_collapsed_id){
					print '<img src="'.  esc_url( wp_get_attachment_image_src( $dashboard_logo_collapsed_id, 'full' )[0] ).'" alt="logo" class="logo xs">';
				}
				?>
			</a>
		</div>
		<?php
//        if ( class_exists( 'WooCommerce' ) && class_exists( 'Wpstream_Player' ) ) {
	        echo wpstream_generate_user_menu();//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
//        }
        ?>
	</div>
</div>