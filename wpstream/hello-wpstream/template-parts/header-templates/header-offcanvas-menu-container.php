<?php
/**
 * Header offcanvas menu container
 *
 * @package wpstream-theme
 */

$login_register_settings = get_theme_mod( 'wpstream_theme_login_register_settings', 'position-left' );
$login_register_position = '';
if ( 'position-left' === $login_register_settings ) {
	$login_register_position = 'offcanvas-start';
} else {
	$login_register_position = 'offcanvas-end';
}
?>

<div class="offcanvas <?php echo esc_attr( $login_register_position ); ?> wpstream-offcanvas" tabindex="-1" id="offcanvas-user">
	<div class="wpstream-offcanvas-header">
		<button type="button" class="btn-close text-reset wpstream-offcanvas-btn-close" data-bs-dismiss="offcanvas" aria-label="Close">

			<?php echo wpstream_theme_get_svg_icon( 'close.svg' ) ;?>
		
		</button>
	</div>
	<div class="offcanvas-body wpstream-offcanvas-body-menu">
		<div class="my-offcanvas-account">
			<?php require WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/template-menu-offcanvas.php'; ?>
		</div>
	</div>
</div>