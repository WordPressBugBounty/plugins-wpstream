<?php
/**
 * Public captions endpoint for the hosted player.
 *
 * The hosted player runs on another domain and fetches a video's .vtt file
 * with a cross-origin request. The file sits in this site's uploads folder,
 * which sends no CORS header, so the browser would refuse it. This endpoint
 * serves the captions file the editor saved for a video, with the header the
 * player needs, and the player is given this endpoint's URL instead of the
 * raw file URL.
 *
 * @package wpstream
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves `admin-ajax.php?action=wpstream_captions&product_id=<id>`.
 */
class Wpstream_Captions_Endpoint {

	/**
	 * Post types that can carry a captions file: the free VOD post type and
	 * WooCommerce products (paid videos).
	 *
	 * @var string[]
	 */
	const POST_TYPES = array( 'wpstream_product_vod', 'product' );

	/**
	 * Register the endpoint for logged-in and logged-out callers alike: the
	 * player fetches the file without this site's cookies, whoever is watching.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wpstream_captions', array( $this, 'serve' ) );
		add_action( 'wp_ajax_nopriv_wpstream_captions', array( $this, 'serve' ) );
	}

	/**
	 * The URL the hosted player fetches a video's captions from.
	 *
	 * @param int $product_id Video post ID.
	 * @return string Absolute URL of this endpoint for that video.
	 */
	public static function url( $product_id ) {
		return add_query_arg(
			array(
				'action'     => 'wpstream_captions',
				'product_id' => (int) $product_id,
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Stream the video's captions file with the headers a cross-origin text
	 * track needs, or answer 404 when the video has none.
	 *
	 * No entitlement check on purpose: the player's request carries no cookies,
	 * so none could pass, and the file is already public at its upload URL.
	 * The endpoint only ever serves the file an editor saved for that video.
	 *
	 * @return void Outputs the file and terminates the request.
	 */
	public function serve() {
		$product_id = isset( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;
		$file       = $this->captions_file( $product_id );

		// Drop anything another plugin printed before us; the body must be the file alone.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		if ( '' === $file ) {
			status_header( 404 );
			exit;
		}

		// admin-ajax already sent text/html and no-cache headers; replace the ones that matter.
		status_header( 200 );
		header( 'Content-Type: text/vtt; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file );
		exit;
	}

	/**
	 * Resolve the captions file saved for a video to a readable local path.
	 *
	 * The saved value is the Media Library URL the editor picked, so it is
	 * mapped back to its attachment and that attachment's file. Anything that
	 * is not a published video, has no captions, or does not resolve to a
	 * readable .vtt file yields '' and the endpoint answers 404.
	 *
	 * @param int $product_id Video post ID.
	 * @return string Absolute file path, or '' when there is nothing to serve.
	 */
	private function captions_file( $product_id ) {
		$post = get_post( $product_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! in_array( $post->post_type, self::POST_TYPES, true ) ) {
			return '';
		}

		$url = (string) get_post_meta( $product_id, 'wpstream_closed_captions_file', true );
		if ( '' === $url ) {
			return '';
		}

		$attachment_id = attachment_url_to_postid( $url );
		$file          = $attachment_id ? (string) get_attached_file( $attachment_id ) : '';
		if ( '' === $file || ! is_readable( $file ) || 'vtt' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
			return '';
		}

		return $file;
	}
}
