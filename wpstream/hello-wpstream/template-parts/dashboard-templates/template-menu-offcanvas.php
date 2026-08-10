<?php
/**
 * Offcanvas Login / Registration and User Dashboard.
 *
 * Slide-out (offcanvas) panel content. For a logged-in user it shows a greeting,
 * avatar, logout link and the generated account navigation menu; for a guest it
 * renders the combined login / register / forgot-password form.
 *
 * @package wpstream-theme
 */

// Block direct file access outside of WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<!-- Logged-in branch: show the account salutation and navigation. -->
<?php if ( is_user_logged_in() ) { ?>
	<?php
	// Current user and its avatar / custom profile image plus a logout URL.
	global $current_user;
	wp_get_current_user();
	$user_image = get_avatar( $current_user->ID, 96 );
	// Prefer the WpStream custom profile image over the default avatar.
	$user_image = wpstream_get_author_profile_image_url_by_author_id(  $current_user->ID );
	$logout_url = wp_logout_url( home_url() );
	?>
	<!-- Salutation block: avatar, greeting, logout link and description. -->
	<div class="account-salution">
		<!-- Avatar / profile image, printed only when one is available. -->
		<div class="account-salution-user-image-wrapper">
			<?php if ( $user_image ) : ?>
				<img src="<?php echo esc_url($user_image); ?>" alt="profile image">
			<?php endif; ?>
		</div>

		<!-- "Hello <display name>" greeting. -->
		<p class="h2 account-salution-user-name">
			<?php esc_html_e( 'Hello', 'hello-wpstream' ); ?>
			<?php echo esc_html( $current_user->display_name ); ?>
		</p>

		<!-- Logout link. -->
		<div class="account-salution-logout-link-wrapper">
			<a class="account-salution-logout-link" href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Log out', 'hello-wpstream' ); ?></a>
		</div>

		<!-- Short account description shown under the greeting. -->
		<p class="account-salution-description"><?php esc_html_e( 'Here you can view your recent orders, manage your shipping and billing addresses, and edit your password and account details.', 'hello-wpstream' ); ?></p>
	</div>

	<!-- Account navigation menu (My Account links). -->
	<div class="navigation">
		<nav class="woocommerce-MyAccount-navigation" role="navigation">
			<div class="wpstream-account-navigation-links">
				<?php
				    // Echo the generated user menu markup (already escaped internally).
				    echo wpstream_generate_user_menu(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
		</nav>
	</div>

	<?php
	// Guest branch: render the combined login / register / forgot-password form.
} else {
	global $login_register_object;
	echo wpstream_sanitize_html($login_register_object->generate_login_register_forgot_form());

 } ?>