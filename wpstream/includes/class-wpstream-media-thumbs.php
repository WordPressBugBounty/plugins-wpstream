<?php
/**
 * Stored thumbnails for the Unused media files list.
 *
 * The cloud keeps no picture of a stored file, so the list page captures one frame in the
 * browser the first time a file is shown and sends it here. It is kept as a small JPEG under
 * the uploads folder, named by a hash of the file name, and shown on every later load without
 * asking the cloud again. It goes with the file when the file is deleted.
 */
defined( 'ABSPATH' ) || exit;

class Wpstream_Media_Thumbs {
    /** Folder under uploads that holds the thumbnails. */
    const FOLDER = 'wpstream-thumbs';
    /** Largest JPEG accepted, in bytes. A 160×90 frame is a few kilobytes. */
    const MAX_BYTES = 102400;

    /**
     * Where a file's thumbnail lives on disk.
     *
     * @param string $name File name from the cloud.
     * @return string
     */
    public static function path( $name ) {
        return wp_upload_dir()['basedir'] . '/' . self::FOLDER . '/' . md5( $name ) . '.jpg';
    }

    /**
     * URL of a file's stored thumbnail, or '' when none was saved yet.
     *
     * @param string $name File name from the cloud.
     * @return string
     */
    public static function url( $name ) {
        $path = self::path( $name );
        if ( ! file_exists( $path ) ) { return ''; }
        // The time stamp lets a recaptured frame replace a cached one.
        return wp_upload_dir()['baseurl'] . '/' . self::FOLDER . '/' . md5( $name ) . '.jpg?v=' . filemtime( $path );
    }

    /**
     * A deleted file takes its thumbnail with it.
     *
     * @param string $name File name from the cloud.
     */
    public static function delete( $name ) {
        if ( file_exists( self::path( $name ) ) ) { wp_delete_file( self::path( $name ) ); }
    }

    /**
     * AJAX: keep the frame the browser captured (wp_ajax_wpstream_save_media_thumb). Admin only.
     * The image must arrive as a JPEG data URL and decode to a real JPEG no larger than a
     * thumbnail, whatever the request claims; anything else is refused and nothing is written.
     *
     * @return void Emits JSON success with the stored URL, or error.
     */
    public static function ajax_save() {
        // CSRF gate: the library script sends this nonce as 'security'.
        check_ajax_referer( 'wpstream_recordings_nonce', 'security' );
        if ( ! current_user_can( 'administrator' ) ) { wp_send_json_error( null, 403 ); }
        $name = isset( $_POST['video_name'] ) && is_scalar( $_POST['video_name'] ) ? sanitize_text_field( wp_unslash( $_POST['video_name'] ) ) : '';
        $image = isset( $_POST['image'] ) && is_scalar( $_POST['image'] ) ? (string) wp_unslash( $_POST['image'] ) : '';
        $prefix = 'data:image/jpeg;base64,';
        if ( '' === $name || 0 !== strpos( $image, $prefix ) || strlen( $image ) > self::MAX_BYTES * 2 ) { wp_send_json_error(); }
        $bytes = base64_decode( substr( $image, strlen( $prefix ) ), true );
        $info = $bytes ? @getimagesizefromstring( $bytes ) : false;
        if ( ! $info || 'image/jpeg' !== $info['mime'] || $info[0] > 320 || $info[1] > 180 || strlen( $bytes ) > self::MAX_BYTES ) { wp_send_json_error(); }
        $path = self::path( $name );
        if ( ! wp_mkdir_p( dirname( $path ) ) || false === file_put_contents( $path, $bytes ) ) { wp_send_json_error(); }
        wp_send_json_success( array( 'url' => self::url( $name ) ) );
    }
}
