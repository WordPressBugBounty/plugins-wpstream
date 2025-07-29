<?php
/**
 * Offcanvas Login / Registration and User Dashboard
 *
 * @package wpstream-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<?php if ( is_user_logged_in() ) { ?>
	<?php
	global $current_user;
	wp_get_current_user();
	$user_image = get_avatar( $current_user->ID, 96 );
	$user_image = wpstream_get_author_profile_image_url_by_author_id(  $current_user->ID );
	$logout_url = wp_logout_url( home_url() );
	?>
	<div class="account-salution">
		<div class="account-salution-user-image-wrapper">
			<?php if ( $user_image ) : ?>
				<img src="<?php echo esc_url($user_image); ?>" alt="profile image">
			<?php endif; ?>
		</div>

		<p class="h2 account-salution-user-name">
			<?php esc_html_e( 'Hello', 'hello-wpstream' ); ?>
			<?php echo esc_html( $current_user->display_name ); ?>
		</p>

		<div class="account-salution-logout-link-wrapper">
			<a class="account-salution-logout-link" href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Log out', 'hello-wpstream' ); ?></a>
		</div>

		<p class="account-salution-description"><?php esc_html_e( 'Here you can view your recent orders, manage your shipping and billing addresses, and edit your password and account details.', 'hello-wpstream' ); ?></p>
	</div>

	<div class="navigation">
		<nav class="woocommerce-MyAccount-navigation" role="navigation">
			<div class="wpstream-account-navigation-links">
				<?php
				    echo wpstream_generate_user_menu(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
		</nav>
	</div>

	<?php
} else {
	global $login_register_object;
	echo wpstream_sanitize_html($login_register_object->generate_login_register_forgot_form());

 } ?>