<?php
/**
 * Ajax functions
 *
 * Front-end AJAX endpoints for the hello-wpstream theme dashboard:
 * - Toggling an item in the logged-in user's "Watch Later" list.
 * - Rendering the "create channel" call-to-action (respecting per-user
 *   channel limits and paid-channel permissions).
 *
 * @package wpstream-theme
 */

// Register the authenticated AJAX handler that toggles Watch Later membership.
add_action( 'wp_ajax_wpstream_handle_watch_later_item_ajax', 'wpstream_handle_watch_later_item_ajax' );
/**
 * Watch later item ajax
 *
 * AJAX handler (action `wpstream_handle_watch_later_item_ajax`). Adds or
 * removes a post from the current user's Watch Later list and returns the
 * refreshed toggle markup.
 *
 * Reads from $_POST: `security` (presence checked only), `postID` (the item
 * to toggle). Requires the user to be logged in. Note: only the presence of
 * `security` is checked — the nonce value itself is never verified (see report).
 *
 * @return void Emits a JSON response and terminates.
 */
function wpstream_handle_watch_later_item_ajax() {
	// Check if the user is logged in.
	if ( ! is_user_logged_in() ) {
		// Reject anonymous requests outright.
		wp_send_json_error( 'You must be logged in to perform this action.' );
		die();
	}

	// Gate on the presence of the security token (value is not validated here).
	if ( isset( $_POST['security'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
		// Require a target post ID before doing any work.
		if ( isset( $_POST['postID'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			// Sanitize the incoming post ID to an integer.
			$post_id           = intval( $_POST['postID'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
			// Identify the current user whose meta we will update.
			$user_id           = get_current_user_id();
			// Load the user's existing Watch Later list (array of post IDs).
			$watch_later_items = get_user_meta( $user_id, 'wpstream_user_watch_later_items', true );
			if ( is_array( $watch_later_items ) ) {
				// A list already exists: decide whether to add or remove.
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

			// Persist the updated list back to user meta.
			update_user_meta( $user_id, 'wpstream_user_watch_later_items', $watch_later_items );
			// Change the water later text and icon using toggle method.
			// Regenerate the toggle button markup reflecting the new state.
			$content  = wpstream_theme_show_watch_later( $post_id );
			// Success payload: new state message plus refreshed markup.
			$response = array(
				'success' => true,
				'message' => $message,
				'content' => $content,
			);
		} else {
			// postID was missing from the request.
			$response = array(
				'success' => false,
				'message' => 'Invalid postID format.',
			);
		}
	} else {
		// Nonce verification failed, handle the error or exit.
		// (Reached when the `security` field is absent from the POST.)
		$response = array(
			'success' => false,
			'message' => 'Nonce verification failed.',
		);
	}
	// Send the assembled response as JSON.
	wp_send_json( $response );
	wp_die(); // Always include this at the end of an AJAX callback.
}

if ( ! function_exists( 'wpstream_theme_create_channel' ) ) {
	/**
	 * Create channel for the theme.
	 *
	 * Echoes the dashboard "create a new channel" call-to-action. Shows the
	 * free-channel button (and a paid-channel button when permitted) only while
	 * the user is under their channel quota; administrators bypass the quota.
	 * When the quota is reached, prints a "maximum reached" notice instead.
	 *
	 * @return void Outputs HTML directly.
	 */
	function wpstream_theme_create_channel() {
		// Maximum number of channels a single user may own.
		$maxim_channels_per_user  = wpstream_return_max_channels_per_user();
		// Whether users are allowed to create paid channels.
		$allow_user_paid_channels = wpstream_return_user_can_create_paid();
		// Count of channels this user already owns.
		$how_many_posts           = wpstream_theme_return_user_channel_list( '', 'found_posts' );


		?>

		<!-- Show the create buttons while under quota, or always for admins. -->
		<?php if ( ( $how_many_posts < $maxim_channels_per_user ) || current_user_can( 'manage_options' ) ) : ?>
			<!-- Wrapper holding the create-channel action buttons -->
			<div class="wpstream-dashboard-start-streaming__cta-wrapper_buttons">
				<!-- Free channel creation button (triggers JS via its class) -->
				<div class="wpstream_theme_button_dashboard wpstream_user_create_new_channel">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path fill-rule="evenodd" clip-rule="evenodd" d="M12 6.25C12.4142 6.25 12.75 6.58579 12.75 7V11.25H17C17.4142 11.25 17.75 11.5858 17.75 12C17.75 12.4142 17.4142 12.75 17 12.75H12.75V17C12.75 17.4142 12.4142 17.75 12 17.75C11.5858 17.75 11.25 17.4142 11.25 17V12.75H7C6.58579 12.75 6.25 12.4142 6.25 12C6.25 11.5858 6.58579 11.25 7 11.25H11.25V7C11.25 6.58579 11.5858 6.25 12 6.25Z" fill="#0F0F0F"/>
					</svg>

					<!-- Free channel button label -->
					<?php echo esc_html__( 'New Free Channel', 'hello-wpstream' ); ?>
				</div>

				<!-- Offer a paid-channel button only when permitted (or admin). -->
				<?php if ( $allow_user_paid_channels || current_user_can( 'manage_options' ) ) : ?>
					<!-- Paid channel creation button -->
					<div class="wpstream_theme_button_dashboard wpstream_user_create_new_paid_channel">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path fill-rule="evenodd" clip-rule="evenodd" d="M12 6.25C12.4142 6.25 12.75 6.58579 12.75 7V11.25H17C17.4142 11.25 17.75 11.5858 17.75 12C17.75 12.4142 17.4142 12.75 17 12.75H12.75V17C12.75 17.4142 12.4142 17.75 12 17.75C11.5858 17.75 11.25 17.4142 11.25 17V12.75H7C6.58579 12.75 6.25 12.4142 6.25 12C6.25 11.5858 6.58579 11.25 7 11.25H11.25V7C11.25 6.58579 11.5858 6.25 12 6.25Z" fill="#0F0F0F"/>
						</svg>

						<!-- Paid channel button label -->
						<?php echo esc_html__( 'New Paid Channel', 'hello-wpstream' ); ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
			// For non-admins, print how many of their allowed channels are used.
			if ( ! current_user_can( 'manage_options' ) ) {
				/* translators: %1$s - number of channels, %2$s - maximum number of channels */
				$translated_string = sprintf( esc_html__( 'You have, %1$s channels from %2$s possible', 'hello-wpstream' ), $how_many_posts, $maxim_channels_per_user );
				echo esc_html( $translated_string );
			}
			?>



		<?php else : ?>
			<!-- Quota reached: build the "maximum reached" notice string. -->
			<?php $translated_string = sprintf( esc_html__( 'You reach the maximum no of channels allowed.', 'hello-wpstream' ), $how_many_posts, $maxim_channels_per_user ); ?>

			<!-- Output the quota-reached message. -->
			<?php echo esc_html( $translated_string ); ?>
		<?php endif; ?>

		<?php
	}
}

