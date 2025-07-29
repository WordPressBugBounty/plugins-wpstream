<?php
/**
 * Upload picture template
 *
 * @package wpstream-theme
 */

$current_user        = wp_get_current_user();// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$user_id             = $current_user->ID;// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$user_login          = $current_user->user_login;// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$user_custom_picture = wpstream_get_author_profile_image_url_by_author_id( $user_id );
$image_id            = get_the_author_meta( 'custom_picture_small', $user_id );

?>
	<div id="upload-container" class="wpstream-dashboard-account__upload-wrapper">
		<div class="wpstream-dashboard-account__image-wrapper">
			<p class="wpstream-dashboard-account__upload-title">
				<?php echo esc_html__( 'Profile Picture', 'hello-wpstream' ); ?>
			</p>

			<img id="profile-image" class="wpstream-dashboard-account__profile-image" src="<?php echo esc_url( $user_custom_picture ); ?>" alt="<?php esc_attr_e( 'user image', 'hello-wpstream' ); ?>"
				data-profileurl="<?php echo esc_attr( $user_custom_picture ); ?>"
				data-smallprofileurl="<?php echo esc_attr( $image_id ); ?>">
		</div>


		<div id="aaiu-upload-container" class="wpstream-dashboard-account__image-actions">
			<div id="aaiu-upload-imagelist">
				<ul id="aaiu-ul-list" class="aaiu-upload-list"></ul>
			</div>

			<div id="drag-and-drop" class="rh_drag_and_drop_wrapper ">
				<div class="drag-drop-msg wpstream-dashboard-account__image-description"><?php esc_html_e( 'Must be JPEG, PNG, or GIF and cannot exceed 4MB.', 'hello-wpstream' ); ?></div>

				<div class="wpstream-dashboard-account__actions-wrap">
					<div id="aaiu-uploader-profile" class="wpstream_theme_button_dashboard wpstream-dashboard-account__action-btn">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path fill-rule="evenodd" clip-rule="evenodd" d="M10.7379 16.6273C9.96427 16.6273 9.31895 16.0361 9.2514 15.2654C9.11015 13.6541 9.07441 12.0357 9.14427 10.4204C9.05994 10.4147 8.97563 10.4088 8.89133 10.4027L7.40178 10.2941C6.44973 10.2247 5.91752 9.16312 6.43151 8.35874C7.5277 6.64323 9.53693 4.72317 11.1904 3.53544C11.6742 3.18789 12.3258 3.18789 12.8097 3.53544C14.4631 4.72317 16.4723 6.64322 17.5685 8.35874C18.0825 9.16312 17.5503 10.2247 16.5983 10.2941L15.1087 10.4027C15.0244 10.4088 14.9401 10.4147 14.8558 10.4204C14.9256 12.0357 14.8899 13.6541 14.7486 15.2654C14.6811 16.0361 14.0358 16.6273 13.2622 16.6273H10.7379ZM10.6815 9.76256C10.5678 11.5498 10.589 13.3431 10.745 15.1273H13.255C13.411 13.3431 13.4323 11.5498 13.3186 9.76256C13.3058 9.56219 13.3739 9.36508 13.5077 9.21534C13.6414 9.06559 13.8296 8.97573 14.0301 8.96585C14.3535 8.94992 14.6767 8.93018 14.9997 8.90664L16.0815 8.82778C15.1219 7.41448 13.9204 6.18023 12.5313 5.18238L12 4.80074L11.4687 5.18238C10.0796 6.18023 8.87813 7.41449 7.91858 8.82778L9.00038 8.90664C9.32337 8.93018 9.64656 8.94992 9.9699 8.96585C10.1704 8.97573 10.3586 9.06559 10.4924 9.21534C10.6261 9.36508 10.6942 9.56219 10.6815 9.76256Z" fill="#0F0F0F"/>
							<path d="M5.75 17C5.75 16.5858 5.41421 16.25 5 16.25C4.58579 16.25 4.25 16.5858 4.25 17V19C4.25 19.9665 5.0335 20.75 6 20.75H18C18.9665 20.75 19.75 19.9665 19.75 19V17C19.75 16.5858 19.4142 16.25 19 16.25C18.5858 16.25 18.25 16.5858 18.25 17V19C18.25 19.1381 18.1381 19.25 18 19.25H6C5.86193 19.25 5.75 19.1381 5.75 19V17Z" fill="#0F0F0F"/>
						</svg>

						<?php esc_html_e( 'Change Profile Picture', 'hello-wpstream' ); ?>
					</div>
					<div id="wpstream_remove_profile" data-image-id="<?php echo esc_attr( $image_id ); ?>" class="wpstream_theme_button_dashboard wpstream-dashboard-account__action-btn">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M10 2.25C9.58579 2.25 9.25 2.58579 9.25 3V3.75H5C4.58579 3.75 4.25 4.08579 4.25 4.5C4.25 4.91421 4.58579 5.25 5 5.25H19C19.4142 5.25 19.75 4.91421 19.75 4.5C19.75 4.08579 19.4142 3.75 19 3.75H14.75V3C14.75 2.58579 14.4142 2.25 14 2.25H10Z" fill="#0F0F0F"/>
							<path d="M10 10.65C10.4142 10.65 10.75 10.9858 10.75 11.4L10.75 18.4C10.75 18.8142 10.4142 19.15 10 19.15C9.58579 19.15 9.25 18.8142 9.25 18.4L9.25 11.4C9.25 10.9858 9.58579 10.65 10 10.65Z" fill="#0F0F0F"/>
							<path d="M14.75 11.4C14.75 10.9858 14.4142 10.65 14 10.65C13.5858 10.65 13.25 10.9858 13.25 11.4V18.4C13.25 18.8142 13.5858 19.15 14 19.15C14.4142 19.15 14.75 18.8142 14.75 18.4V11.4Z" fill="#0F0F0F"/>
							<path fill-rule="evenodd" clip-rule="evenodd" d="M5.99142 7.91718C6.03363 7.53735 6.35468 7.25 6.73684 7.25H17.2632C17.6453 7.25 17.9664 7.53735 18.0086 7.91718L18.2087 9.71852C18.5715 12.9838 18.5715 16.2793 18.2087 19.5446L18.189 19.722C18.045 21.0181 17.0404 22.0517 15.7489 22.2325C13.2618 22.5807 10.7382 22.5807 8.25108 22.2325C6.95954 22.0517 5.955 21.0181 5.81098 19.722L5.79128 19.5446C5.42846 16.2793 5.42846 12.9838 5.79128 9.71852L5.99142 7.91718ZM7.40812 8.75L7.2821 9.88417C6.93152 13.0394 6.93152 16.2238 7.2821 19.379L7.3018 19.5563C7.37011 20.171 7.84652 20.6612 8.45905 20.747C10.8082 21.0758 13.1918 21.0758 15.5409 20.747C16.1535 20.6612 16.6299 20.171 16.6982 19.5563L16.7179 19.379C17.0685 16.2238 17.0685 13.0394 16.7179 9.88417L16.5919 8.75H7.40812Z" fill="#0F0F0F"/>
						</svg>

						<?php esc_html_e( 'Remove', 'hello-wpstream' ); ?>
					</div>
				</div>
			</div>

		</div>
	</div>

<?php
$ajax_nonce = wp_create_nonce( 'wpstream_profile_image_upload' );
print '<input type="hidden" id="wpstream_profile_image_upload" value="' . esc_html( $ajax_nonce ) . '"/>';
?>
<?php wpstream_theme_localize_upload_script_images( 'aaiu-uploader-profile' ); ?>