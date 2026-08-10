<?php
/**
 * Header offcanvas menu container.
 *
 * Outputs the Bootstrap offcanvas panel that slides in from the side on the
 * dashboard/mobile header. The customizer setting decides which edge it opens
 * from, and the panel body pulls in the offcanvas account menu partial.
 *
 * @package wpstream-theme
 */

// Read the customizer preference for which side the login/register panel sits on.
$login_register_settings = get_theme_mod( 'wpstream_theme_login_register_settings', 'position-left' );
// Bootstrap offcanvas placement class, resolved from the setting below.
$login_register_position = '';
if ( 'position-left' === $login_register_settings ) {
	// Left-aligned preference -> slide in from the start (left) edge.
	$login_register_position = 'offcanvas-start';
} else {
	// Any other value -> slide in from the end (right) edge.
	$login_register_position = 'offcanvas-end';
}
?>

<!-- Offcanvas panel; placement class chosen above, opened by the header toggle targeting #offcanvas-user. -->
<div class="offcanvas <?php echo esc_attr( $login_register_position ); ?> wpstream-offcanvas" tabindex="-1" id="offcanvas-user">
	<!-- Panel header holding the close (X) control. -->
	<div class="wpstream-offcanvas-header">
		<button type="button" class="btn-close text-reset wpstream-offcanvas-btn-close" data-bs-dismiss="offcanvas" aria-label="Close">

			<!-- Inline close icon SVG rendered from the theme's icon set. -->
			<?php echo wpstream_theme_get_svg_icon( 'close.svg' ) ;?>
		
		</button>
	</div>
	<!-- Panel body: the account/navigation menu partial. -->
	<div class="offcanvas-body wpstream-offcanvas-body-menu">
		<div class="my-offcanvas-account">
			<!-- Render the offcanvas account menu (login/register or logged-in links). -->
			<?php require WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/template-menu-offcanvas.php'; ?>
		</div>
	</div>
</div>