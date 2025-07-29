<?php
/**
 * Ajax functions
 *
 * @package wpstream-theme
 */

add_action( 'wp_ajax_wpstream_handle_watch_later_item_ajax', 'wpstream_handle_watch_later_item_ajax' );
/**
 * Watch later item ajax
 */
function wpstream_handle_watch_later_item_ajax() {
	// Check if the user is logged in.
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( 'You must be logged in to perform this action.' );
		die();
	}

	if ( isset( $_POST['security'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['postID'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_id           = intval( $_POST['postID'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$user_id           = get_current_user_id();
			$watch_later_items = get_user_meta( $user_id, 'wpstream_user_watch_later_items', true );
			if ( is_array( $watch_later_items ) ) {
				if ( in_array( $post_id, $watch_later_items, true ) ) {
					// Item is already in the watch later list, remove it.
					$watch_later_items = array_diff( $watch_later_items, array( $post_id ) );
					$message           = esc_html__( 'Removed from Watch Later', 'hello-wpstream' );
				} else {
					// Add the item to the watch later list.
					$watch_later_items[] = $post_id;
					$message             = esc_html__( 'Added to Watch Later', 'hello-wpstream' );
				}
			} else {
				// Create a new watch later list if it doesn't exist.
				$watch_later_items = array( $post_id );
				$message           = esc_html__( 'Added to Watch Later', 'hello-wpstream' );
			}

			update_user_meta( $user_id, 'wpstream_user_watch_later_items', $watch_later_items );
			// Change the water later text and icon using toggle method.
			$content  = wpstream_theme_show_watch_later( $post_id );
			$response = array(
				'success' => true,
				'message' => $message,
				'content' => $content,
			);
		} else {
			$response = array(
				'success' => false,
				'message' => 'Invalid postID format.',
			);
		}
	} else {
		// Nonce verification failed, handle the error or exit.
		$response = array(
			'success' => false,
			'message' => 'Nonce verification failed.',
		);
	}
	wp_send_json( $response );
	wp_die(); // Always include this at the end of an AJAX callback.
}

if ( ! function_exists( 'wpstream_theme_create_channel' ) ) {
	/**
	 * Create channel for the theme.
	 */
	function wpstream_theme_create_channel() {
		$maxim_channels_per_user  = wpstream_return_max_channels_per_user();
		$allow_user_paid_channels = wpstream_return_user_can_create_paid();
		$how_many_posts           = wpstream_theme_return_user_channel_list( '', 'found_posts' );


		?>

		<?php if ( ( $how_many_posts < $maxim_channels_per_user ) || current_user_can( 'manage_options' ) ) : ?>
			<div class="wpstream-dashboard-start-streaming__cta-wrapper_buttons">
				<div class="wpstream_theme_button_dashboard wpstream_user_create_new_channel">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path fill-rule="evenodd" clip-rule="evenodd" d="M12 6.25C12.4142 6.25 12.75 6.58579 12.75 7V11.25H17C17.4142 11.25 17.75 11.5858 17.75 12C17.75 12.4142 17.4142 12.75 17 12.75H12.75V17C12.75 17.4142 12.4142 17.75 12 17.75C11.5858 17.75 11.25 17.4142 11.25 17V12.75H7C6.58579 12.75 6.25 12.4142 6.25 12C6.25 11.5858 6.58579 11.25 7 11.25H11.25V7C11.25 6.58579 11.5858 6.25 12 6.25Z" fill="#0F0F0F"/>
					</svg>

					<?php echo esc_html__( 'New Free Channel', 'hello-wpstream' ); ?>
				</div>

				<?php if ( $allow_user_paid_channels || current_user_can( 'manage_options' ) ) : ?>
					<div class="wpstream_theme_button_dashboard wpstream_user_create_new_paid_channel">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path fill-rule="evenodd" clip-rule="evenodd" d="M12 6.25C12.4142 6.25 12.75 6.58579 12.75 7V11.25H17C17.4142 11.25 17.75 11.5858 17.75 12C17.75 12.4142 17.4142 12.75 17 12.75H12.75V17C12.75 17.4142 12.4142 17.75 12 17.75C11.5858 17.75 11.25 17.4142 11.25 17V12.75H7C6.58579 12.75 6.25 12.4142 6.25 12C6.25 11.5858 6.58579 11.25 7 11.25H11.25V7C11.25 6.58579 11.5858 6.25 12 6.25Z" fill="#0F0F0F"/>
						</svg>

						<?php echo esc_html__( 'New Paid Channel', 'hello-wpstream' ); ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
			if ( ! current_user_can( 'manage_options' ) ) {
				/* translators: %1$s - number of channels, %2$s - maximum number of channels */
				$translated_string = sprintf( esc_html__( 'You have, %1$s channels from %2$s possible', 'hello-wpstream' ), $how_many_posts, $maxim_channels_per_user );
				echo esc_html( $translated_string );
			}
			?>



		<?php else : ?>
			<?php $translated_string = sprintf( esc_html__( 'You reach the maximum no of channels allowed.', 'hello-wpstream' ), $how_many_posts, $maxim_channels_per_user ); ?>

			<?php echo esc_html( $translated_string ); ?>
		<?php endif; ?>

		<?php
	}
}

