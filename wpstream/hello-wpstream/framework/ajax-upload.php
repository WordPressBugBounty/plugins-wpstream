<?php
/**
 * Ajax upload
 *
 * AJAX-driven media upload/delete handlers for the theme dashboard. Users can
 * upload images (gallery, profile picture) and videos (trailer/preview) via
 * plupload, and delete their own attachments. Uploaded files are stored as
 * WordPress attachments; profile pictures are made private and mirrored into
 * user meta.
 *
 * @package wpstream-theme
 */

// Register the authenticated AJAX endpoint that receives file uploads.
add_action( 'wp_ajax_wpstream_me_upload', 'wpstream_me_upload' );
/**
 * Handles AJAX request for file upload.
 *
 * AJAX handler (action `wpstream_me_upload`). Verifies the `aaiu_allow` nonce
 * and the `upload_files` capability, assembles the uploaded file from $_FILES,
 * then delegates to wpstream_fileupload_process().
 *
 * Reads from $_POST: `nonce` (verified), `button_id` (which uploader fired).
 * Reads from $_FILES: `aaiu_upload_file` (the uploaded file).
 *
 * @return void Delegates to the processor, which emits JSON and exits.
 */
function wpstream_me_upload() {
	// Verify the upload nonce; dies automatically on failure.
	check_ajax_referer( 'aaiu_allow', 'nonce' );

	// Only logged-in users with the upload capability may proceed.
	if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( array( 'message' => 'You are not allowed to upload files.' ), 403 );
	}

	// Identify which dashboard uploader triggered this request.
	$button_id = isset( $_POST['button_id'] ) ? sanitize_text_field( $_POST['button_id'] ) : '';

	// Normalize the uploaded file array for wp_handle_upload().
	$file = array( //phpcs:ignore WordPress.Security.NonceVerification.Missing
		'name'     => isset( $_FILES['aaiu_upload_file']['name'] ) ? sanitize_file_name( $_FILES['aaiu_upload_file']['name'] ) : '', // sanitized original filename
		'type'     => $_FILES['aaiu_upload_file']['type'],     // client-reported MIME type
		'tmp_name' => $_FILES['aaiu_upload_file']['tmp_name'], // PHP temp path of the upload
		'error'    => $_FILES['aaiu_upload_file']['error'],    // PHP upload error code
		'size'     => $_FILES['aaiu_upload_file']['size'],     // uploaded size in bytes
	);

	// Hand off to the processor which stores the attachment and responds.
	wpstream_fileupload_process( $file, $button_id );
}

/**
 * Process uploaded file.
 *
 * This function handles the processing of an uploaded file. It calls another function,
 * wpstream_handle_file(), to handle the file upload and then generates HTML markup
 * for displaying the uploaded file. If the button ID is 'aaiu-uploader-profile',
 * it updates the user's profile picture.
 *
 * @param array  $file       The uploaded file data.
 * @param string $button_id  Optional. The ID of the button triggering the upload process.
 * @return void
 */
function wpstream_fileupload_process( $file, $button_id = '' ) {
	// Store the file as an attachment; returns array on success, false on fail.
	$attachment = wpstream_handle_file( $file, $button_id );

	if ( is_array( $attachment ) ) {
		// Build the preview markup/URL for the newly-created attachment.
		$html = wpstream_get_html( $attachment );

		// Special handling when the upload is the user's profile picture.
		if ( 'aaiu-uploader-profile' === $button_id ) {
			// Resolve the current user to attach the picture to.
			$current_user = wp_get_current_user();
			$user_id      = $current_user->ID;
			// Get the URL for the profile-sized image variant.
			$image_src    = wp_get_attachment_image_src( $attachment['id'], 'wpstream_user_image' );
			if ( isset( $image_src[0] ) ) {
				// Persist the profile picture URL in user meta.
				update_user_meta( $user_id, 'custom_picture', $image_src[0] );
			}
			// Also store the attachment ID for later reference.
			update_user_meta( $user_id, 'custom_picture_small', $attachment['id'] );

		}

		// Success payload returned to the uploader JS.
		$response = array(
			'success'    => true,
			'html'       => $html,
			'attach'     => $attachment['id'],
			'$button_id' => $button_id,
		);

		// Include the profile image URL in the response when available.
		if ( isset( $image_src[0] ) ) {
			$response['profile_image'] = $image_src[0];
		}

		// Emit the JSON success response and stop.
		echo wp_json_encode( $response );
		exit;
	}

	// wpstream_handle_file() failed: report failure.
	$response = array( 'success' => false );
	echo wp_json_encode( $response );
	exit;
}

/**
 * Handle file upload.
 *
 * This function handles the upload of a file by using the WordPress function wp_handle_upload().
 * It processes the uploaded file data and inserts it as an attachment in the media library.
 * If the upload is intended for a user profile picture (button ID 'aaiu-uploader-profile'),
 * the attachment is set to be private.
 *
 * @param array  $upload_data  The uploaded file data.
 * @param string $button_id    Optional. The ID of the button triggering the upload process.
 * @return array|bool          Returns the attachment data on success, or false on failure.
 */
function wpstream_handle_file( $upload_data, $button_id = '' ) {
	// Default return is false, meaning "upload failed".
	$return        = false;
	// Move the uploaded temp file into the uploads directory (no form nonce).
	$uploaded_file = wp_handle_upload( $upload_data, array( 'test_form' => false ) );

	if ( isset( $uploaded_file['file'] ) ) {
		// Absolute path of the stored file.
		$file_loc  = $uploaded_file['file'];
		// Base filename used for the title and type detection.
		$file_name = basename( $upload_data['name'] );
		// Determine the MIME type from the filename extension.
		$file_type = wp_check_filetype( $file_name );

		// Assemble the attachment post data.
		$attachment = array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_name ) ), // filename without extension
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Attach to a parent post when a numeric propid is supplied via query.
		if ( isset( $_GET['propid'] ) && is_numeric( $_GET['propid'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$attachment['post_parent'] = intval( $_GET['propid'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		// Profile pictures are stored as private attachments.
		if ( 'aaiu-uploader-profile' === $button_id ) {
			$attachment['post_status'] = 'private';
		}

		// Insert the attachment record and generate its metadata/thumbnails.
		$attach_id   = wp_insert_attachment( $attachment, $file_loc );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_loc );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		// Return the generated metadata and the new attachment ID.
		$return = array(
			'data' => $attach_data,
			'id'   => $attach_id,
		);

		return $return;
	}

	// wp_handle_upload() reported no file: return the failure default.
	return $return;
}

/**
 * Generate HTML markup for displaying an attachment.
 *
 * This function generates the HTML markup necessary to display an attachment.
 * It retrieves the attachment data and constructs the URL to the attachment image.
 * If the attachment is intended for a user profile picture, it constructs the URL
 * based on the user's ID.
 *
 * @param array $attachment The attachment data.
 * @return string           The HTML markup for displaying the attachment.
 */
function wpstream_get_html( $attachment ) {
	// Attachment ID and working variables.
	$attach_id = $attachment['id'];
	$file      = '';
	$html      = '';

	if ( isset( $attachment['data']['file'] ) ) {
		// Split the stored file path and drop the filename to keep the folder.
		$file = explode( '/', $attachment['data']['file'] );
		$file = array_slice( $file, 0, count( $file ) - 1 );
		$path = implode( '/', $file );

		// The generated card-sized thumbnail filename.
		$image = $attachment['data']['sizes']['wpstream_featured_unit_cards']['file'];

		// Look up the attachment post and the uploads base URL.
		$post = get_post( $attach_id );
		$dir  = wp_upload_dir();
		// Build the public folder URL for the thumbnail.
		$path = $dir['baseurl'] . '/' . $path;
		$html = '';

		// Current user (retained for context; not used in the returned URL).
		$current_user = wp_get_current_user();

		$user_id = $current_user->ID;
		// Final URL: folder + thumbnail filename.
		$html   .= $path . '/' . $image;

	}

	// Return the thumbnail URL (empty string if no size data existed).
	return $html;
}

// Register the authenticated AJAX endpoint that deletes an uploaded file.
add_action( 'wp_ajax_wpstream_delete_file', 'wpstream_delete_file' );

if ( ! function_exists( 'wpstream_delete_file' ) ) {
	/**
	 * Delete an uploaded file.
	 *
	 * This function handles the deletion of an uploaded file. It checks the user's
	 * permissions and verifies the nonce before deleting the file. If the user has
	 * the necessary permissions and the file exists, it is deleted from the media library.
	 *
	 * AJAX handler (action `wpstream_delete_file`). Reads from $_POST:
	 * `security` (nonce, verified against `wpstream_theme_image_upload`),
	 * `attach_id` (the attachment to delete). Only the attachment's author may
	 * delete it.
	 *
	 * @return void Terminates the request.
	 */
	function wpstream_delete_file() {
		// Verify the image-upload nonce before doing anything else.
		check_ajax_referer( 'wpstream_theme_image_upload', 'security' );
		// Resolve the current user for the ownership check.
		$current_user = wp_get_current_user();
		$user_id      = $current_user->ID;

		// Require an authenticated user.
		if ( ! is_user_logged_in() ) {
			exit( 'ko' );
		}
		// Guard against a zero (logged-out) user ID.
		if ( 0 === $user_id ) {
			exit( 'out pls' );
		}

		// Read and sanitize the target attachment ID.
		if ( isset( $_POST['attach_id'] ) ) {
			$attach_id = intval( sanitize_text_field( wp_unslash( $_POST['attach_id'] ) ) );
		}

		// Load the attachment post to inspect its author.
		$the_post = get_post( $attach_id );

		// Only the author of the attachment is allowed to delete it.
		if ( $user_id !== $the_post->post_author ) {
			exit( 'you don\'t have the right to delete this' );
		}

		// Permanently delete the attachment (and its files).
		wp_delete_attachment( $attach_id, true );
		exit;
	}
}

// Register the legacy plupload delete endpoint (action `aaiu_delete`).
add_action( 'wp_ajax_aaiu_delete', 'wpstream_me_delete_file' );
/**
 * Delete file
 *
 * AJAX handler (action `aaiu_delete`). Deletes an attachment owned by the
 * current user. Reads from $_POST: `attach_id`. Note: this handler performs an
 * ownership check but does NOT verify any nonce (see report).
 *
 * @return void Terminates the request.
 */
function wpstream_me_delete_file() {
	// Resolve the current user for the ownership check.
	$current_user = wp_get_current_user();
	$user_id      = $current_user->ID;

	// Require an authenticated user.
	if ( ! is_user_logged_in() ) {
		exit( 'ko' );
	}
	// Guard against a zero (logged-out) user ID.
	if ( 0 === $user_id ) {
		exit( 'out pls' );
	}

	// Read and cast the target attachment ID.
	if ( isset( $_POST['attach_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$attach_id = intval( $_POST['attach_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
	// Load the attachment post to inspect its author.
	$the_post = get_post( $attach_id );

	// Only the author of the attachment is allowed to delete it.
	if ( $current_user->ID !== $the_post->post_author ) {
		exit( 'you don\'t have the right to delete this' );

	}

	// Permanently delete the attachment (and its files).
	wp_delete_attachment( $attach_id, true );
	exit;
}
