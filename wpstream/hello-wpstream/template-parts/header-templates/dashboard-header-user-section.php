<?php
/**
 * Dashboard header user section
 *
 * @package wpstream-theme
 */

$current_user        = wp_get_current_user();// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$user_id             = $current_user->ID;
$user_custom_picture = wpstream_get_author_profile_image_url_by_author_id( $user_id );

if ( function_exists( 'wc_get_account_menu_items' ) ) {
	$edit_account_link = wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) );
} else {
	$edit_account_link = wpstream_non_woo_get_account_endpoint_url( 'edit-account' );
}
?>
<?php
$popover_content = '<div class="popover-content account-profile-popover">
    <div class="dashboard-header-user-profile-menu-item account-profile-popover__image">
        <img id="dashboard-header_profile-image-menu" alt="' . esc_attr( __( 'Profile image', 'hello-wpstream' ) ) . '" src="' . esc_url( $user_custom_picture ) . '"/>
        <div class="dashboard-header_profile-username">' . esc_html( $current_user->first_name ) . ' ' . esc_html( $current_user->last_name ) . '</div>
    </div>
  <div class="dashboard-header-user-profile-menu-item account-profile-popover__action account-profile-popover__action--website">
    <a href="' . esc_url( home_url() ) . '">' . esc_html__( 'Back to Website', 'hello-wpstream' ) . '</a>
  </div>
  <div class="dashboard-header-user-profile-menu-item account-profile-popover__action account-profile-popover__action--edit-account">
    <a href="' . esc_url( $edit_account_link ) . '">' . esc_html__( 'Edit Account', 'hello-wpstream' ) . '</a>
  </div>
  <div class="dashboard-header-user-profile-menu-item account-profile-popover__logout">
    <a href="' . wp_logout_url( home_url() ) . '">' . esc_html__( 'Logout', 'hello-wpstream' ) . '</a>
  </div>

</div>';
?>

<img id="dashboard-header_profile-image" src="<?php echo esc_url( $user_custom_picture ); ?>"
	alt="<?php esc_attr_e( 'user image', 'hello-wpstream' ); ?>"
	accesskey="" data-bs-toggle="popover" data-bs-placement="bottom"
	data-bs-html="true" data-bs-content='<?php echo trim( $popover_content );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>'>