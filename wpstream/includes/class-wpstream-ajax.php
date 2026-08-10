<?php

/**
 * Dashboard / channel AJAX endpoint surface.
 *
 * Registers and implements the admin-ajax.php handlers behind the front-end
 * user dashboard: editing channel data, saving the user's account/address,
 * managing profile attachments, selecting/creating channels, the "watch later"
 * list, and live-quota lookups. Also enqueues and localizes the dashboard JS.
 *
 * Handlers echo/`wp_send_json_*` a JSON payload and terminate the request.
 * Several handlers are noted in code comments where their nonce/ownership
 * checks are weaker than others in this file.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */

/**
 * Wires up the dashboard AJAX actions and provides their callbacks.
 */
class WpStream_Ajax {

	/**
	 * Store plugin main class to allow public access.
	 *
	 * @since    20180622
	 * @var object      The main plugin class, exposing shared services such as
	 *                  the live-API connection and the quota manager.
	 */
	public $main;

	/**
	 * Constructor.
	 *
	 * Stashes the main plugin instance and registers every AJAX action this
	 * class answers, plus the dashboard script enqueue hook. All actions here
	 * are `wp_ajax_` only (logged-in users); none are exposed to `nopriv`.
	 *
	 * @param object $plugin_main The main class.
	 */
	public function __construct( $plugin_main ) {
		// Keep a reference to the main plugin so handlers can reach its services.
		$this->main = $plugin_main;

		// Onboarding: video list lookup and broadcaster RTMP info lookup.
		add_action( 'wp_ajax_wpstream_get_videos_list',  [$this,'wpstream_get_videos_list'] );
		add_action('wp_ajax_wpstream_get_broadcaster_info', array($this, 'wpstream_get_broadcaster_info'));

		// Add the dashboard AJAX actions
		add_action( 'wp_ajax_wpstream_dashboard_save_channel_data', [$this, 'wpstream_dashboard_save_channel_data'] );
		add_action( 'wp_ajax_wpstream_dashboard_save_user_address', [$this, 'wpstream_dashboard_save_user_address'] );
		add_action( 'wp_ajax_wpstream_delete_profile_attachment', [$this, 'wpstream_delete_profile_attachment'] );
		add_action( 'wp_ajax_wpstream_dashboard_save_user_data', [$this, 'wpstream_dashboard_save_user_data'] );
		add_action( 'wp_ajax_wpstream_handle_channel_selection', [$this, 'wpstream_handle_channel_selection'] );
		add_action( 'wp_ajax_wpstream_handle_channel_creation', [$this, 'wpstream_handle_channel_creation'] );
		add_action( 'wp_ajax_wpstream_handle_channel_details_saving', [$this, 'wpstream_handle_channel_details_saving'] );
		add_action( 'wp_ajax_wpstream_remove_post_id', [$this, 'wpstream_remove_post_id_callback'] );
		add_action( 'wp_ajax_wpstream_get_live_quota_data', [$this, 'wpstream_get_live_quota_data'] );

		// Enqueue dashboard scripts
		add_action( 'wp_enqueue_scripts', [$this, 'wpstream_enqueue_dashboard_scripts'] );
	}

	/**
	 * Enqueue and localize the dashboard JavaScript.
	 *
	 * Only loads on the front-end dashboard page. Passes the admin-ajax URL and
	 * a couple of translated validation strings to the script via
	 * `wpstream_dashboard_script_vars`.
	 */
	public function wpstream_enqueue_dashboard_scripts() {
		// Only enqueue when we are on the plugin's dashboard page.
		if ( function_exists('wpstream_is_dashboard_page') && wpstream_is_dashboard_page() ) {

			// Register the dashboard script (jQuery-dependent, loaded in footer).
			wp_enqueue_script(
				'wpstream-dashboard-script',
				plugin_dir_url( dirname( __FILE__ ) ) . 'js/dashboard-script.js',
				array( 'jquery' ),
				$this->main->get_version(),
				true
			);

			// Expose the AJAX endpoint and translated password-validation messages to JS.
			wp_localize_script( 'wpstream-dashboard-script', 'wpstream_dashboard_script_vars', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'currentPassEmpty' => esc_html__( 'Please enter your current password.', 'wpstream' ),
				'passNoMatch' => esc_html__( 'Passwords do not match!', 'wpstream' ),
			));
		}
	}

	/**
	 * Fetch the account's VOD list from the WpStream cloud API.
	 *
	 * Reads: nonce field `security`. Returns JSON `{success, videos}` when an
	 * API token is available, otherwise `{success:false, error:'Token not found'}`.
	 * Used by the onboarding video picker.
	 */
	public function wpstream_get_videos_list() {
		// Validate the onboarding nonce before hitting the API.
		check_ajax_referer( 'wpstream_onboarding_video_list_nonce', 'security' );

		// Resolve the API auth token and pull the remote video list.
		$token = $this->main->wpstream_live_connection->wpstream_get_token();
		$videos_list = $this->main->wpstream_live_connection->wpstream_get_videos();

		// cleanup any previous echo before sending json
		ob_end_clean();

		// A valid token means the list is trustworthy; return it.
		if ( $token ) {
			echo json_encode( array(
				'success' => true,
				'videos' => $videos_list,
			));
		} else {
			// No token: signal the failure so the UI can prompt to reconnect.
			echo json_encode( array(
				'success' => false,
				'error' => 'Token not found',
			));
		}
		// Terminate the AJAX request.
		die();
	}

	/**
	 * Return a channel's RTMP publish URL and stream key.
	 *
	 * Reads: nonce field `nonce`, POST `channel_id`. Requires the `publish_posts`
	 * capability. Returns JSON success `{rtmp_url, stream_key}` pulled from the
	 * channel post's `obs_uri` / `obs_stream` meta, or an error otherwise.
	 */
	public function wpstream_get_broadcaster_info() {
		// Verify nonce
		check_ajax_referer('wpstream_broadcaster_nonce', 'nonce');

		// Read the target channel post ID (0 when missing).
		$channel_id = isset($_POST['channel_id']) ? intval($_POST['channel_id']) : 0;

		if (empty($channel_id)) {
			// No channel supplied: nothing to look up.
			wp_send_json_error('Invalid channel ID');
			return;
		}

		// Ownership gate: RTMP credentials belong to the channel's owner only.
		// Replaces the old publish_posts-only check, which let any author-level
		// user read another broadcaster's stream key (IDOR).
		if ( ! wpstream_can_manage_channel( get_current_user_id(), $channel_id ) ) {
			wp_send_json_error('You are not allowed to control this channel.');
			return;
		}

		// Get RTMP URL from post meta
		$obs_uri = get_post_meta($channel_id, 'obs_uri', true);
		$obs_stream = get_post_meta($channel_id, 'obs_stream', true);

		if (empty($obs_uri) || empty($obs_stream)) {
			// Channel has no stored RTMP endpoint/key yet.
			wp_send_json_error('RTMP information not available');
			return;
		}

		// Hand back the RTMP endpoint and stream key for the broadcaster UI.
		wp_send_json_success([
			'rtmp_url' => $obs_uri,
			'stream_key' => $obs_stream
		]);
	}

	/**
	 * Saves channel data from the dashboard.
	 *
	 * Handles the saving of channel data from the dashboard, including title, description,
	 * thumbnail ID, images, category terms, and whether the channel is paid.
	 *
	 * Reads: nonce field `nonce`, POST `postID`, `thumb_id`, `title`,
	 * `description`, `channel_paid`, `channel_price`, `images`, and
	 * `selected_categories`. Toggling `channel_paid` switches the post between
	 * the `wpstream_product` and WooCommerce `product` post types. Returns JSON
	 * success with the rebuilt thumbnail/gallery/taxonomy HTML and trailer/preview URLs.
	 */
	public function wpstream_dashboard_save_channel_data() {
		// Verify the nonce for security
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpstream_edit_channel_nonce' ) ) {
			die( 'Permission denied.' );
		}
		// Must be an authenticated user to edit a channel.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'error' => 'User is not logged in.' ) );
			die();
		}

		// Ownership check: the caller must be the real author of the target
		// channel (admins allowed). This uses post_author via
		// wpstream_can_manage_channel() rather than the old
		// wpstream_start_streaming_channel user meta — that meta is client-writable
		// (see wpstream_dashboard_save_user_address), so trusting it let a user
		// point it at a foreign post id and edit/hijack that post (SEC-02).
		$postID = isset( $_POST['postID'] ) ? intval( $_POST['postID'] ) : 0;
		if ( $postID === 0 ) {
			// A zero/missing postID is not a valid channel to edit.
			wp_send_json_error( array( 'error' => 'Invalid channel ID.' ) );
			die();
		}
		if ( ! wpstream_can_manage_channel( get_current_user_id(), $postID ) ) {
			wp_send_json_error( array( 'error' => 'Can\'t edit this channel.' ) );
			die();
		}

		// Collect and sanitize the submitted channel fields.
		$thumb_id     = isset( $_POST['thumb_id'] ) ? intval( $_POST['thumb_id'] ) : 0;
		$title        = sanitize_text_field( $_POST['title'] );
		$description  = sanitize_text_field( $_POST['description'] );
		$channel_paid = intval( $_POST['channel_paid'] );
		$images       = sanitize_text_field( $_POST['images'] );
	        // Strip stray leading/trailing commas from the CSV image-id list.
	        $images       = trim($images,',');
	        $channel_price=0;
		// Paid channels carry a price; read it as a float when supplied.
		if ( isset( $_POST['channel_price'] ) ) {
			$channel_price = floatval( $_POST['channel_price'] );
		}
		// $postID was already read and ownership-checked above.

		// Free channels use the custom post type; paid ones become WooCommerce products.
		$new_post_type = 'wpstream_product';
		if ( $channel_paid == 1 ) {
			// Paid: switch to the product post type and persist the price meta.
			$new_post_type = 'product';
			update_post_meta( $postID, '_price', $channel_price );
			update_post_meta( $postID, '_regular_price', $channel_price );

		}

		// Only proceed when we have a real post to update.
		if ( $postID != '0' ) {
			// Write the core post fields, applying any post-type switch from above.
			$post_data = array(
				'ID'           => $postID,
				'post_title'   => $title,
				'post_content' => $description,
				'post_type'    => $new_post_type,
			);
			wp_update_post( $post_data );
			// Set the channel's featured image / thumbnail.
			set_post_thumbnail( $postID, $thumb_id );


			/*
			* Manage images
			*/
			if ( $channel_paid == 1 ) {
				// Paid channels store their gallery in WooCommerce product meta.
				update_post_meta( $postID, '_product_type', 'live_stream' );
				wp_set_post_terms( $postID, 'live_stream', 'product_type' );
				update_post_meta( $postID, '_product_image_gallery', $images );
			} else {

				// Free channels store each image id as a separate gallery meta row.
				$images_array = explode( ',', $images );
	                        // Clear the old gallery before re-adding the current image ids.
	                        delete_post_meta( $postID, 'wpstream_theme_gallery' );
				foreach ( $images_array as $key => $value ) :
	                            add_post_meta( $postID, 'wpstream_theme_gallery', $value, false );
				endforeach;
			}
			// Read the gallery back in the normalized shape used for rendering.
			$gallery_images = $this->wpstream_return_image_gallery( $postID );


			/*
			* Manage categories
			*/
			if(isset( $_POST['selected_categories'])):
				// Assign the submitted terms per taxonomy.
				$categories = $_POST['selected_categories'];
				foreach ( $categories as $taxonomy => $term_ids ) {

					// Normalize a single term id into an array.
					if ( ! is_array( $term_ids ) ) {
						$term_ids = array( $term_ids );
					}
					// Force term ids to integers.
					$term_ids = array_map( 'intval', $term_ids );

					// Replace the post's terms in this taxonomy with the selection.
					wp_set_object_terms( $postID, $term_ids, $taxonomy );
				}
			endif;

			// Build the taxonomy summary HTML for the dashboard response.
			$taxonomy_information = $this->wpstream_return_taxoomy_information( $postID );

			// Resolve the trailer and preview video URLs (empty when unset).
			$video_trailer = $this->wpstream_theme_return_trailer_video( $postID );

			$video_preview = $this->wpstream_theme_return_preview_video( $postID );

			// Return the refreshed thumbnail, gallery, taxonomy HTML and video URLs.
			wp_send_json_success(
				array(
					'succes'     => true,
					's'          => $images,
					'thumburl'   => get_the_post_thumbnail_url( $postID, 'wpstream_featured_unit_cards' ),
					'images'     => $this->wpstream_build_html_gallery_dashboard( $gallery_images ),
					'taxonomies' => $taxonomy_information['html'],
	                                'channel_paid'=>$channel_paid,
	                                'channel_price'=>$channel_price,
					'video_trailer' => $video_trailer,
					'video_preview' => $video_preview,
					'message'    => esc_html__( 'Changes saved successfully.', 'wpstream' ),
				)
			);
			die();
		}
	}

	/**
	 * Return the image gallery for a post.
	 *
	 * This function returns the image gallery for a post based on the post type.
	 *
	 * @param int $post_id The ID of the post.
	 * @return array The array of image gallery for the post.
	 */
	public function wpstream_return_image_gallery( $post_id ) {
		// The gallery is stored differently for WooCommerce products vs custom channels.
		$post_type      = get_post_type( $post_id );
		$gallery_images = array();

		if ( 'product' === $post_type ) {
			// Products keep a comma-separated id list in a single meta value.
			$gallery_images_source = get_post_meta( $post_id, '_product_image_gallery', true );
			$gallery_images        = explode( ',', $gallery_images_source );
		} else {
			// Non-products use Meta Box's repeated gallery field (when available).
			if(function_exists('rwmb_meta')){
				$gallery_images = rwmb_meta( 'wpstream_theme_gallery', array(), $post_id );

				// Meta Box may return an id-keyed array, a single value, or empty.
				if ( is_array( $gallery_images ) ) {
					$gallery_images = array_keys( $gallery_images );
				} elseif ( ! empty( $gallery_images ) ) {
					$gallery_images = array( $gallery_images );
				} else {
					$gallery_images = array();
				}
			}
		}

		// Drop empty entries before returning the id list.
		return array_filter( $gallery_images );
	}

	/**
	 * Returns information about taxonomies for the specified post.
	 *
	 * @param int $post_id The post ID.
	 * @return array An array containing information about taxonomies and HTML markup.
	 */
	public function wpstream_return_taxoomy_information( $post_id ) {
		// Enumerate every taxonomy registered for this post's type.
		$post_type    = get_post_type( $post_id );
		$taxonomies   = get_object_taxonomies( $post_type );
		$all_terms    = array();
		$return_array = array();

		// Loop through each taxonomy and get terms attached to the post.
		foreach ( $taxonomies as $taxonomy_slug ) {
			// Skip WooCommerce's internal product_type/product_visibility taxonomies.
			if ( 'product_type' !== $taxonomy_slug && 'product_visibility' !== $taxonomy_slug ) {
				$taxonomy_obj  = get_taxonomy( $taxonomy_slug );
				$taxonomy_name = $taxonomy_obj->labels->name; // This fetches the name of the taxonomy.
				$terms         = wp_get_post_terms( $post_id, $taxonomy_slug, array( 'fields' => 'all' ) ); // fetch all fields of the term.

				// Record the terms keyed by the taxonomy's display name.
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$all_terms[ $taxonomy_name ] = $terms;
				}
			}
		}

		// Build the read-only HTML block shown in the dashboard details panel.
		$return_html = '';

		foreach ( $all_terms as $taxonomy => $terms ) {
			// One details section per taxonomy, headed by its name.
			$return_html .= ' <div  class="wpstream-dashboard-details" >';
			$return_html .= '<div class="wpstream-dashboard-details-header">' . $taxonomy . '</div>';
			$return_html .= '<div class="wpstream_account_details_value" id="wpstream_' . sanitize_key( $taxonomy ) . '">';

			// Render each selected term as a chip.
			foreach ( $terms as $term ) {
				$return_html .= '<span class="wpstream_term_selected">' . $term->name . '</span>';
			}

			$return_html .= ' </div>
 
	        </div>  ';
		}

		// Return both the raw term data and the rendered HTML.
		$return_array['tax_information'] = $all_terms;
		$return_array['html']            = $return_html;

		return $return_array;
	}

	/**
	 * Return the trailer video for a post.
	 *
	 * This function returns the trailer video for a post based on the post type.
	 *
	 * @param int $post_id The ID of the post.
	 * @return string The URL of the trailer video.
	 */
	function wpstream_theme_return_trailer_video( $post_id ) {
		// Resolve the attachment stored in the channel's `video_trailer` meta.
		$trailer_video_id = get_post_meta( $post_id, 'video_trailer', true );
		$attachment_url   = wp_get_attachment_url( $trailer_video_id );

		// Return the URL when the attachment exists, otherwise an empty string.
		if ( ! empty( $attachment_url ) ) {;
			return $attachment_url;
		}

		return '';
	}

	/**
	 * Build HTML for the video trailer in the dashboard.
	 *
	 * This function builds HTML for the video trailer in the dashboard based on the provided video URL.
	 *
	 * @param string $video_url The URL of the video.
	 * @return string The HTML string for the video trailer.
	 */
	function wpstream_theme_build_html_video_trailer_dashboard( $video_url ) {
		// Wrap a non-empty URL in an HTML5 video player; empty URL yields no markup.
		if ( ! empty( $video_url ) ) {
			return '<div class="wpstream-video-trailer" id="wpstream-video-trailer"><video height="240" controls><source src="' . esc_url( $video_url ) . '" type="video/mp4"></video></div>';
		}

		return '';
	}

	/**
	 * Return the trailer video for a post.
	 *
	 * This function returns the trailer video for a post based on the post type.
	 *
	 * @param int $post_id The ID of the post.
	 * @return string The URL of the trailer video.
	 */
	function wpstream_theme_return_preview_video( $post_id ) {
		// Resolve the attachment stored in the channel's `video_preview` meta.
		$preview_video_id = get_post_meta( $post_id, 'video_preview', true );
		$attachment_url   = wp_get_attachment_url( $preview_video_id );

		// Return the URL when the attachment exists, otherwise an empty string.
		if ( ! empty( $attachment_url ) ) {;
			return $attachment_url;
		}

		return '';
	}

	/**
	 * Build HTML for the video preview in the dashboard.
	 *
	 * This function builds HTML for the video preview in the dashboard based on the provided video URL.
	 *
	 * @param string $video_url The URL of the video.
	 * @return string The HTML string for the video preview.
	 */
	function wpstream_theme_build_html_video_preview_dashboard( $video_url ) {
		// Wrap a non-empty URL in an HTML5 video player; empty URL yields no markup.
		if ( ! empty( $video_url ) ) {
			return '<div class="wpstream-video-preview" id="wpstream-video-preview"><video height="240" controls><source src="' . esc_url( $video_url ) . '" type="video/mp4"></video></div>';
		}

		return '';
	}

	/**
	 * Build HTML for the gallery in the dashboard.
	 *
	 * This function builds HTML for the gallery in the dashboard based on the provided array of image IDs.
	 *
	 * @param array $gallery_images An array of image IDs.
	 * @return string The HTML string for the gallery.
	 */
	public function wpstream_build_html_gallery_dashboard( $gallery_images ) {
		$return_string = '';

		// Render one thumbnail tile per attachment id that resolves to an image.
		if ( is_array( $gallery_images ) ) {
			foreach ( $gallery_images as $attachment_id ) {
				// Look up the sized image source for this attachment.
				$preview = wp_get_attachment_image_src( $attachment_id, 'wpstream_featured_unit_cards' );

				// Only emit markup when a real image URL came back.
				if ( $preview && '' !== $preview[0] ) {
					$return_string .= '<div class="wpstream_uploaded_images" data-imageid="' . esc_attr( $attachment_id ) . '">';
					$return_string .= '<img src="' . esc_url( $preview[0] ) . '" alt="' . esc_html__( 'thumb', 'wpstream' ) . '" /></div>';
				}
			}
		}

		return $return_string;
	}

	/**
	 * Save user address data from dashboard.
	 *
	 * This function handles saving user address data from the dashboard.
	 *
	 * Reads: nonce field `nonce`, POST `inputData` (an array of `{id, value}`
	 * pairs). Each pair is stored as user meta on the current user. Returns a
	 * JSON success message.
	 */
	public function wpstream_dashboard_save_user_address() {
		// Verify the nonce for security.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpstream_edit_addr_nonce' ) ) {
			die( 'Permission denied.' );
		}

		// Require an authenticated user.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'error' => 'User is not logged in.' ) );
		}

	    // Allowlist of user-meta keys this address form is permitted to write.
	    // The key comes from a form input id and is fully client-controlled, so
	    // without this allowlist any logged-in user could set arbitrary meta —
	    // e.g. wpstream_start_streaming_channel, which channel-edit ownership used
	    // to trust (SEC-02). Only the standard WooCommerce billing/shipping
	    // address fields are accepted; anything else is ignored.
	    $allowed_address_meta = array(
	        'billing_first_name', 'billing_last_name', 'billing_company',
	        'billing_address_1', 'billing_address_2', 'billing_city',
	        'billing_state', 'billing_postcode', 'billing_country',
	        'billing_phone', 'billing_email',
	        'shipping_first_name', 'shipping_last_name', 'shipping_company',
	        'shipping_address_1', 'shipping_address_2', 'shipping_city',
	        'shipping_state', 'shipping_postcode', 'shipping_country',
	        'shipping_phone',
	    );

	    // Persist each submitted field as user meta on the current user.
	    $userID = get_current_user_id();
	    foreach ($_POST['inputData'] as $item){
	        // Only store entries whose key is an allowlisted address field.
	        if( isset( $item['id'] ) && in_array( $item['id'], $allowed_address_meta, true ) ){
	            update_user_meta($userID, sanitize_text_field( $item['id']) , sanitize_text_field( $item['value']) );
	        }
	    }


		// Report success back to the dashboard.
		wp_send_json_success(
			array(
				'succes'  => true,
				'message' => esc_html__( 'Changes saved successfully.', 'wpstream' ),
			)
		);

		die();
	}

	/**
	 * Delete the current user's profile picture attachment.
	 *
	 * Reads: nonce field `security`, POST `image_id`. Deletes the attachment only
	 * when the current user is its author, then clears the `custom_picture` /
	 * `custom_picture_small` user meta. Returns JSON with the default avatar URL.
	 */
	public function wpstream_delete_profile_attachment() {
		// Verify the nonce for security.
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'wpstream_profile_image_upload' ) ) {
			wp_send_json_error(
				array(
					'succes'  => false,
					'message' => esc_html__( 'Permission denied nonce', 'wpstream' ),
				)
			);

			die();
		}

		// Read the attachment id to delete.
		if ( isset( $_POST['image_id'] ) ) {
			$image_id = intval( $_POST['image_id'] );
		}
		$user_id = get_current_user_id();

		// Get the attachment author.
		$attachment = get_post( $image_id );

		// Ownership check: only the attachment's author may delete it.
		if ( empty( $attachment ) || intval( $attachment->post_author ) !== intval( $user_id ) ) {
			wp_send_json_error(
				array(

					'succes'  => false,

					'author'  => $attachment->post_author,

					'userid'  => $user_id,

					'message' => esc_html__( 'Permission denied!!!', 'wpstream' ),

				)
			);

			die();

		}

		// Delete the attachment (you can customize this part).
		wp_delete_attachment( $image_id, true );
		// Clear the cached profile-picture meta so the default avatar is used.
		delete_user_meta( $user_id, 'custom_picture' );
		delete_user_meta( $user_id, 'custom_picture_small' );

		// Return success plus the fallback avatar URL for the UI to swap in.
		wp_send_json_success(
			array(
				'succes'  => true,
				'default' => function_exists('wpstream_get_author_profile_image_url_by_author_id') ? wpstream_get_author_profile_image_url_by_author_id($user_id) : '',
				'message' => esc_html__( 'Changes saved successfully.', 'wpstream' ),
			)
		);

		die();
	}

	/**
	 * Save the current user's account profile fields from the dashboard.
	 *
	 * Reads: nonce field `nonce`, POST `firstName`, `lastName`, `displayName`,
	 * `email`, `aboutMe`, `newPassword1`, `newPassword2`, `currentPassword`.
	 * Updates only the non-empty fields, validates the email for uniqueness and
	 * format, and changes the password when both new-password fields match and
	 * the supplied current password checks out. Returns JSON success/failure.
	 */
	public function wpstream_dashboard_save_user_data() {
		// Verify the nonce for security.

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpstream_edit_account_nonce' ) ) {
			die( 'Permission denied.' );
		}

		// Require an authenticated user.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'error' => 'User is not logged in.' ) );
		}

		// Get the user's ID.
		$user_id      = get_current_user_id();
		$current_user = wp_get_current_user();

		// Get the data from the AJAX request.
		// Each field is read and sanitized only when present in the request.
		if ( isset( $_POST['firstName'] ) ) {
			$first_name = sanitize_text_field( wp_unslash( $_POST['firstName'] ) );
		}
		if ( isset( $_POST['lastName'] ) ) {
			$last_name = sanitize_text_field( wp_unslash( $_POST['lastName'] ) );
		}
		if ( isset( $_POST['displayName'] ) ) {
			$display_name = sanitize_text_field( wp_unslash( $_POST['displayName'] ) );
		}
		if ( isset( $_POST['email'] ) ) {
			$email = sanitize_email( wp_unslash( $_POST['email'] ) );
		}
	    // Bio/about text is stored in the user's description.
	    if (isset( $_POST['aboutMe'])){
	        $description = sanitize_textarea_field( wp_unslash( $_POST['aboutMe'] ) );
	    }
		// Password change inputs: the two new-password fields plus the current one.
		if ( isset( $_POST['newPassword1'] ) ) {
			$new_password1 = sanitize_text_field( wp_unslash( $_POST['newPassword1'] ) );
		}
		if ( isset( $_POST['newPassword2'] ) ) {
			$new_password2 = sanitize_text_field( wp_unslash( $_POST['newPassword2'] ) );
		}
		if ( isset( $_POST['currentPassword'] ) ) {
			$current_password = sanitize_text_field( wp_unslash( $_POST['currentPassword'] ) );
		}

		// Track whether a password change actually happened for the response.
		$passwordchanged = false;

		// Only update fields that are not empty.
		$user_data = array();

		// Stage each provided profile field for the wp_update_user() call.
		if ( ! empty( $first_name ) ) {
			$user_data['first_name'] = $first_name;
		}

		if ( ! empty( $last_name ) ) {
			$user_data['last_name'] = $last_name;
		}

	    if ( !empty( $description ) ){
		    $user_data['description'] = $description;
	    }

		if ( ! empty( $display_name ) ) {
			$user_data['display_name'] = $display_name;
		}

		// Reject the email if it already belongs to a different account.
		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user && $existing_user->ID !== $user_id ) {
			wp_send_json_error(
				array(
					'succes'      => false,
					'failaccount' => esc_html__( 'Email already exists.', 'wpstream' ),
				)
			);
		}

		// Reject an empty or malformed email address.
		if ( empty( $email ) || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			wp_send_json_error(
				array(
					'succes'      => false,
					'failaccount' => esc_html__( 'Invalid Email Format', 'wpstream' ),
				)
			);
		}

		// Stage the validated email for update.
		if ( ! empty( $email ) ) {
			$user_data['user_email'] = $email;
		}

		// Update the user's data.
		// Persist the staged profile fields in one call when any exist.
		if ( ! empty( $user_data ) ) {
			$user_data['ID'] = $user_id;
			wp_update_user( $user_data );
		}

		// Handle a password change only when both new-password fields are filled.
		if ( ! empty( $new_password1 ) && ! empty( $new_password2 ) ) {
			// The two new passwords must be identical.
			if ( $new_password1 !== $new_password2 ) {
				wp_send_json_error(
					array(
						'succes'   => false,
						'failpass' => esc_html__( 'Passwords do not match!', 'wpstream' ),
					)
				);

				die();

			} elseif ( ! wp_check_password( $current_password, $current_user->data->user_pass, $current_user->ID ) ) {
				// The supplied current password must verify against the stored hash.
				wp_send_json_error(
					array(
						'succes'   => false,
						'failpass' => esc_html__( 'Current Password is not right!', 'wpstream' ),
					)
				);

				die();

			} else {
				// Both checks passed: set the new password.
				wp_set_password( $new_password1, $user_id );
				$passwordchanged = true;
			}
		}

		// Send a response to the client.
		wp_send_json_success(
			array(
				'succes'          => true,
				'passwordchanged' => $passwordchanged,
				'message'         => esc_html__( 'Changes saved successfully.', 'wpstream' ),
			)
		);
	}

	/**
	 * Handle the selection of a channel.
	 *
	 * This function handles the AJAX request for selecting a channel.
	 * It checks if the user is logged in, validates the security nonce,
	 * and updates the user meta with the selected channel if the user is the owner of the channel.
	 * It returns a JSON response indicating success or failure.
	 *
	 * @return void
	 */
	public function wpstream_handle_channel_selection() {
		// Require an authenticated user.
		if ( ! is_user_logged_in() ) {
			wp_die();
		}

		// Validate the channel-list nonce.
		check_ajax_referer( 'wpstream_user_channel_list', 'security' );
		// The channel post id the user wants to make active.
		if ( isset( $_POST['selected_value'] ) ) {
			$selected_value = intval( $_POST['selected_value'] );
		}
		$current_user   = wp_get_current_user();
		// Look up the author of the chosen channel for the ownership check.
		$post_author_id = intval( get_post_field( 'post_author', $selected_value ) );

		// Only the channel's owner may select it as their active channel.
		if ( $current_user->ID !== $post_author_id ) {
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'You are not the owner of this channel', 'wpstream' ),
				)
			);

		} else {
			// Record the selected channel as the user's active streaming channel.
			update_user_meta( $current_user->ID, 'wpstream_start_streaming_channel', $selected_value );

			echo wp_json_encode(
				array(
					'success' => true,
					'message' => esc_html__( 'Channel updated', 'wpstream' ),
				)
			);
		}

		wp_die();
	}

	/**
	 * Create a new channel post for the current user.
	 *
	 * Reads: nonce field `security`, POST `channel_type` ('paid' or free).
	 * Enforces the per-user channel limit (admins bypass it) and the paid-channel
	 * permission, inserts a `wpstream_product` or WooCommerce `product`, and sets
	 * it as the user's active streaming channel. Echoes a JSON success/error.
	 */
	public function wpstream_handle_channel_creation() {
		// Require an authenticated user.
		if ( ! is_user_logged_in() ) {
			wp_die();
		}

		// Validate the channel-list nonce.
		check_ajax_referer( 'wpstream_user_channel_list', 'security' );
		// Requested channel type ('paid' triggers a WooCommerce product).
		if ( isset( $_POST['channel_type'] ) ) {
			$channel_type = sanitize_text_field( wp_unslash( $_POST['channel_type'] ) );
		}
		$current_user = wp_get_current_user();

		// These functions should be implemented in the plugin or be accessible
		// Resolve the per-user limit, paid-channel permission, and current count
		// (falling back to safe defaults when the theme helpers are unavailable).
		$maxim_channels_per_user = function_exists('wpstream_return_max_channels_per_user') ? wpstream_return_max_channels_per_user() : 100;
		$allow_user_paid_channels = function_exists('wpstream_return_user_can_create_paid') ? wpstream_return_user_can_create_paid() : false;
		$how_many_posts = function_exists('wpstream_theme_return_user_channel_list') ? wpstream_theme_return_user_channel_list( '', 'found_posts' ) : 0;

		// Allow creation while under the limit, or unconditionally for admins.
		if ( ( $how_many_posts < $maxim_channels_per_user ) || current_user_can( 'manage_options' ) ) {
			// Default to a free channel.
			$post_type = 'wpstream_product';
			$title     = 'My New Free Channel';

			// Upgrade to a paid product when allowed and requested.
			if ( ( $allow_user_paid_channels || current_user_can( 'manage_options' ) ) && 'paid' === $channel_type ) {
				$post_type = 'product';
				$title     = 'My New Paid Channel';
			}

			// Insert the new published channel owned by the current user.
			$post_data = array(
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_author' => $current_user->ID,
				'post_type'   => $post_type,
			);

			$post_id = wp_insert_post( $post_data );

			if ( ! is_wp_error( $post_id ) ) {
				// On success, make the new channel the user's active one.
				update_user_meta( $current_user->ID, 'wpstream_start_streaming_channel', $post_id );

				echo wp_json_encode(
					array(
						'success' => true,
						'message' => 'Post created with ID: ' . $post_id,
					)
				);
			} else {
				// Report the insertion error back to the client.
				echo wp_json_encode(
					array(
						'success' => false,
						'message' => 'Error creating post: ' . $post_id->get_error_message(),
					)
				);
			}
		} else {
			// Limit reached and not an admin: refuse creation.
			echo wp_json_encode(
				array(
					'success' => false,
					'message' => esc_html__( 'Your reached the maximum number of channels', 'wpstream' ),
				)
			);
		}

		wp_die();
	}

	/**
	 * Handle AJAX request to save channel details.
	 *
	 * This function handles the AJAX request to save the details of a channel, including its title, description,
	 * price, images, featured status, and taxonomies.
	 *
	 * @return void Outputs JSON-encoded response indicating success or failure of the operation.
	 */
	public function wpstream_handle_channel_details_saving() {
		// Require an authenticated user.
		if ( ! is_user_logged_in() ) {
			wp_die();
		}

		// Validate the channel-list nonce.
		check_ajax_referer( 'wpstream_user_channel_list', 'security' );

		// Target channel post id.
		if ( isset( $_POST['postID'] ) ) {
			$post_id = intval( $_POST['postID'] );
		}
		$current_user   = wp_get_current_user();
		// Ownership check: compare the channel's author to the current user.
		$post_author_id = intval( get_post_field( 'post_author', $post_id ) );

		if ( $current_user->ID !== $post_author_id ) {
			// Not the owner: refuse and echo back the ids for debugging.
			echo wp_json_encode(
				array(
					'success'         => false,
					'$postID'         => $post_id,
					'$post_author_id' => $post_author_id,
					'message'         => esc_html__( 'You are not the owner of this channel', 'wpstream' ),
				)
			);
		} else {
			// Owner confirmed: read and sanitize the submitted channel fields.
			if ( isset( $_POST['title'] ) ) {
				$title = sanitize_text_field( wp_unslash( $_POST['title'] ) );
			}
			if ( isset( $_POST['description'] ) ) {
				// Description allows post-safe HTML.
				$sanitized_content = wp_kses_post( wp_unslash( $_POST['description'] ) );
			}
			$price = 0;

			if ( isset( $_POST['price'] ) ) {
				$price = sanitize_text_field( wp_unslash( $_POST['price'] ) );
			}

			if ( isset( $_POST['images'] ) ) {
				$images = sanitize_text_field( wp_unslash( $_POST['images'] ) );
			}

			if ( isset( $_POST['featured'] ) ) {
				$featured = intval( $_POST['featured'] );
			}

			if ( isset( $_POST['taxonomies'] ) ) {
				// Sanitize each taxonomy value when an array was submitted.
				$taxonomies_raw = sanitize_text_field( wp_unslash( $_POST['taxonomies'] ) );
				$taxonomies     = is_array( $taxonomies_raw ) ? array_map( 'sanitize_text_field', $taxonomies_raw ) : array();
			}
			// Strip surrounding whitespace and stray commas from the image id list.
			$images = rtrim( ltrim( trim( $images ), ',' ), ',' );

			// Store the gallery/price differently for products vs custom channels.
			if ( get_post_type( $post_id ) === 'product' ) {
				update_post_meta( $post_id, '_product_image_gallery', $images );
				update_post_meta( $post_id, '_regular_price', $price );
			} else {
				// Free channels append each image id as its own gallery meta row.
				$images_array = explode( ',', $images );
				foreach ( $images_array as $key => $value ) :
					add_post_meta( $post_id, 'wpstream_theme_gallery', $value );
				endforeach;
			}

			// Apply the featured image / thumbnail.
			set_post_thumbnail( $post_id, $featured );

			// Update the core post title/content.
			$post_data = array(
				'ID'           => $post_id,
				'post_title'   => $title,
				'post_content' => $sanitized_content,
			);

			// Update the post.
			wp_update_post( $post_data );

			// Reassign terms for each submitted taxonomy.
			foreach ( $taxonomies as $taxonomy => $term_ids ) {
				// Clear existing terms in this taxonomy first.
				wp_remove_object_terms( $post_id, '', $taxonomy );

				if ( is_array( $term_ids ) ) {
					foreach ( $term_ids as $key => $term_id ) {
						// Tags are set by name (appended), other taxonomies by term id.
						if ( 'product_tag' === $taxonomy || 'post_tag' === $taxonomy ) {
							$tagterm = get_term( $term_id );

							if ( $tagterm && ! is_wp_error( $tagterm ) ) {
								$tag_term_name = $tagterm->name;
								wp_set_post_terms( $post_id, $tag_term_name, $taxonomy, true );
							}
						} elseif ( -1 !== $term_id ) {
							// Skip the -1 "none" sentinel; otherwise assign the term id.
							wp_set_post_terms( $post_id, intval( $term_id ), $taxonomy, true );
						}
					}
				}
			}

			// Echo the saved values back for the dashboard to confirm/update state.
			echo wp_json_encode(
				array(
					'success'            => true,
					'$price'             => $price,
					'message'            => esc_html__( 'Channel updated', 'wpstream' ),
					'title'              => $title,
					'$sanitized_content' => $sanitized_content,
					'$images'            => $images,
					'$featured'          => $featured,
					'$taxonomies'        => $taxonomies,
				)
			);
		}

		wp_die();
	}

	/**
	 * Callback handler to remove a post ID from the "watch later" list.
	 *
	 * Reads: nonce field `wpstream_nonce`, POST `postID`. Filters the given post
	 * id out of the user's `wpstream_user_watch_later_items` meta array and saves
	 * it. Returns JSON success/failure.
	 */
	public function wpstream_remove_post_id_callback() {
		// Require an authenticated user.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'You must be logged in to perform this action.' );
			die();
		}

		// Verify the nonce.
		if ( isset( $_POST['wpstream_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpstream_nonce'] ) ), 'wpstream-watch-later-nonce' ) ) {
			// Proceed only when a post id to remove was supplied.
			if ( isset( $_POST['postID'] ) ) {
				$post_id_to_remove = intval( $_POST['postID'] );
				$meta_key          = 'wpstream_user_watch_later_items';
				$current_user      = wp_get_current_user();
				$user_id           = $current_user->ID;

				// Get the current array of post IDs.
				$watch_later_item_ids = get_user_meta( $user_id, $meta_key, true );

				// Remove the specific ID from the array.
				$watch_later_item_ids = array_filter(
					$watch_later_item_ids,
					function ( $id ) use ( $post_id_to_remove ) {
						// Keep every id except the one being removed.
						return $id !== $post_id_to_remove;
					}
				);

				// Update the user's metadata with the modified array.
				update_user_meta( $user_id, $meta_key, $watch_later_item_ids );

				// Success payload.
				$response = array(
					'success' => true,
					'message' => 'Item removed',
				);

			} else {
				// No post id was provided.
				$response = array(
					'success' => false,
					'message' => 'Invalid postID format.',
				);
			}
		} else {
			// Missing or invalid nonce.
			$response = array(
				'success' => false,
				'message' => 'Nonce verification failed.',
			);
		}

		// Emit whichever response was built above and end the request.
		wp_send_json( $response );

		wp_die();
	}

	/**
	 * Return the account's live-streaming quota data for the dashboard.
	 *
	 * Reads: nonce field `security`. Delegates to the quota manager, augments the
	 * result with an `is_basic_streaming` flag, and returns it as JSON success,
	 * or an error when no data is available.
	 */
	public function wpstream_get_live_quota_data() {
		// Validate the notice nonce (null-coalesced when the field is absent).
		if ( ! wp_verify_nonce( $_POST['security'] ?? '', 'wpstream_notice_nonce' ) ) {
			wp_send_json_error( 'Nonce verification failed.' );
			die();
		}

		// Require an authenticated user.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'You must be logged in to perform this action.' );
			die();
		}

		// Ask the quota manager for the current live quota figures.
		$quota_data = $this->main->quota_manager->get_live_quota_data( 'wpstream_get_live_quota_data' );

		if ( $quota_data ) {
			// Add the basic-streaming flag derived from the quota data.
			$quota_data['is_basic_streaming'] = $this->main->quota_manager->is_basic_streaming_mode( $quota_data );
			wp_send_json_success( $quota_data );
		} else {
			// No quota data returned by the manager.
			wp_send_json_error( 'Could not retrieve quota data.' );
		}
	}
}