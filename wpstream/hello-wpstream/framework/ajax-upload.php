<?php
/**
 * Ajax upload
 *
 * @package wpstream-theme
 */

add_action( 'wp_ajax_wpstream_me_upload', 'wpstream_me_upload' );
/**
 * Handles AJAX request for file upload.
 */
function wpstream_me_upload() {
	if ( ! is_user_logged_in() ) {
		exit( 'ko' );
	}

	$button_id = isset( $_POST['button_id'] ) ? sanitize_text_field( $_POST['button_id'] ) : '';

	$file = array( //phpcs:ignore WordPress.Security.NonceVerification.Missing
		'name'     => isset( $_FILES['aaiu_upload_file']['name'] ) ? sanitize_file_name( $_FILES['aaiu_upload_file']['name'] ) : '',
		'type'     => $_FILES['aaiu_upload_file']['type'],
		'tmp_name' => $_FILES['aaiu_upload_file']['tmp_name'],
		'error'    => $_FILES['aaiu_upload_file']['error'],
		'size'     => $_FILES['aaiu_upload_file']['size'],
	);

	wpstream_fileupload_process( $file, $button_id);
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
	$attachment = wpstream_handle_file( $file, $button_id );

	if ( is_array( $attachment ) ) {
		$html = wpstream_get_html( $attachment );

		if ( 'aaiu-uploader-profile' === $button_id ) {
			$current_user = wp_get_current_user();
			$user_id      = $current_user->ID;
			$image_src    = wp_get_attachment_image_src( $attachment['id'], 'wpstream_user_image' );
			if ( isset( $image_src[0] ) ) {
				update_user_meta( $user_id, 'custom_picture', $image_src[0] );
			}
			update_user_meta( $user_id, 'custom_picture_small', $attachment['id'] );

		}

		$response = array(
			'success'    => true,
			'html'       => $html,
			'attach'     => $attachment['id'],
			'$button_id' => $button_id,
		);

		if ( isset( $image_src[0] ) ) {
			$response['profile_image'] = $image_src[0];
		}

		echo wp_json_encode( $response );
		exit;
	}

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
	$return        = false;
	$uploaded_file = wp_handle_upload( $upload_data, array( 'test_form' => false ) );

	if ( isset( $uploaded_file['file'] ) ) {
		$file_loc  = $uploaded_file['file'];
		$file_name = basename( $upload_data['name'] );
		$file_type = wp_check_filetype( $file_name );

		$attachment = array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_name ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		if ( isset( $_GET['propid'] ) && is_numeric( $_GET['propid'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$attachment['post_parent'] = intval( $_GET['propid'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( 'aaiu-uploader-profile' === $button_id ) {
			$attachment['post_status'] = 'private';
		}

		$attach_id   = wp_insert_attachment( $attachment, $file_loc );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_loc );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		$return = array(
			'data' => $attach_data,
			'id'   => $attach_id,
		);

		return $return;
	}

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
	$attach_id = $attachment['id'];
	$file      = '';
	$html      = '';

	if ( isset( $attachment['data']['file'] ) ) {
		$file = explode( '/', $attachment['data']['file'] );
		$file = array_slice( $file, 0, count( $file ) - 1 );
		$path = implode( '/', $file );

		$image = $attachment['data']['sizes']['wpstream_featured_unit_cards']['file'];

		$post = get_post( $attach_id );
		$dir  = wp_upload_dir();
		$path = $dir['baseurl'] . '/' . $path;
		$html = '';

		$current_user = wp_get_current_user();

		$user_id = $current_user->ID;
		$html   .= $path . '/' . $image;

	}

	return $html;
}

add_action( 'wp_ajax_wpstream_delete_file', 'wpstream_delete_file' );

if ( ! function_exists( 'wpstream_delete_file' ) ) {
	/**
	 * Delete an uploaded file.
	 *
	 * This function handles the deletion of an uploaded file. It checks the user's
	 * permissions and verifies the nonce before deleting the file. If the user has
	 * the necessary permissions and the file exists, it is deleted from the media library.
	 *
	 * @return void
	 */
	function wpstream_delete_file() {
		check_ajax_referer( 'wpstream_theme_image_upload', 'security' );
		$current_user = wp_get_current_user();
		$user_id      = $current_user->ID;

		if ( ! is_user_logged_in() ) {
			exit( 'ko' );
		}
		if ( 0 === $user_id ) {
			exit( 'out pls' );
		}

		if ( isset( $_POST['attach_id'] ) ) {
			$attach_id = intval( sanitize_text_field( wp_unslash( $_POST['attach_id'] ) ) );
		}

		$the_post = get_post( $attach_id );

		if ( $user_id !== $the_post->post_author ) {
			exit( 'you don\'t have the right to delete this' );
		}

		wp_delete_attachment( $attach_id, true );
		exit;
	}
}

add_action( 'wp_ajax_aaiu_delete', 'wpstream_me_delete_file' );
/**
 * Delete file
 */
function wpstream_me_delete_file() {
	$current_user = wp_get_current_user();
	$user_id      = $current_user->ID;

	if ( ! is_user_logged_in() ) {
		exit( 'ko' );
	}
	if ( 0 === $user_id ) {
		exit( 'out pls' );
	}

	if ( isset( $_POST['attach_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$attach_id = intval( $_POST['attach_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
	$the_post = get_post( $attach_id );

	if ( $current_user->ID !== $the_post->post_author ) {
		exit( 'you don\'t have the right to delete this' );

	}

	wp_delete_attachment( $attach_id, true );
	exit;
}
