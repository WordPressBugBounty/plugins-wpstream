<?php
/**
 * Front-end SEO metadata for WpStream content.
 *
 * Emits the share and discovery markup for the three WpStream singular views —
 * WooCommerce products, free live channels and free VOD:
 *
 *   - Open Graph + Twitter card tags, so shared links render a rich preview.
 *   - JSON-LD structured data, so search engines understand the page as video
 *     rather than as a generic article.
 *
 * The guiding rule is to enrich, never duplicate. Whatever another component
 * already states correctly is left alone:
 *
 *   - rel=canonical belongs to WordPress core, which emits one on every
 *     singular view; a second would be an SEO defect.
 *   - Open Graph and Twitter tags belong to a dedicated SEO plugin (Yoast,
 *     Rank Math) whenever one is active.
 *   - Product and Offer structured data belongs to WooCommerce, which already
 *     describes every product's price and availability.
 *
 * What no other component can state is that a WpStream page is *video* — a
 * live broadcast or a recording — so that is this class's contribution, for
 * free channels and paid stream products alike.
 *
 * @package Wpstream
 */

// Block direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and prints WpStream's front-end SEO metadata.
 */
class Wpstream_Seo {

	/**
	 * Number of words kept in the generated share description.
	 *
	 * Open Graph consumers truncate long descriptions anyway; trimming here
	 * keeps the head small and avoids dumping an entire post body into a tag.
	 */
	const DESCRIPTION_WORDS = 30;

	/**
	 * Register the front-end hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Build the service and print its markup inside the document head.
		$seo = new self();
		add_action( 'wp_head', array( $seo, 'render' ) );
	}

	/**
	 * Print all SEO metadata for the current request.
	 *
	 * Does nothing unless the request is one of the WpStream singular views.
	 *
	 * @return void
	 */
	public function render() {
		// Resolve the post being viewed, or bail on any other kind of request.
		$post = $this->current_post();
		if ( null === $post ) {
			return;
		}

		// Only claim the social surface when no SEO plugin already owns it.
		if ( ! $this->seo_plugin_active() ) {
			$this->render_open_graph( $post );
		}

		// Structured data is always ours: no general-purpose SEO plugin knows
		// how to describe a WpStream channel or VOD as a video object.
		$this->render_schema( $post );
	}

	/**
	 * Print the JSON-LD structured data block for a post.
	 *
	 * @param WP_Post $post The post being viewed.
	 * @return void
	 */
	private function render_schema( $post ) {
		/**
		 * Filter the structured data emitted for a WpStream singular view.
		 *
		 * Returning an empty array suppresses the block entirely.
		 *
		 * @param array   $schema The JSON-LD structure about to be printed.
		 * @param WP_Post $post   The post being described.
		 */
		$schema = apply_filters( 'wpstream_seo_schema', $this->build_schema( $post ), $post );

		// Nothing to say about this post.
		if ( empty( $schema ) ) {
			return;
		}

		// JSON_HEX_TAG is what makes this block safe to inline: it encodes the
		// angle brackets as unicode escapes, so post content carrying a closing
		// script tag cannot terminate the element. JSON_HEX_AMP does the same
		// for ampersands; JSON_UNESCAPED_SLASHES just keeps the URLs readable.
		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP )
		);
	}

	/**
	 * Build the schema.org structure describing a post.
	 *
	 * Live channels and VOD are both VideoObjects; a live channel is set apart
	 * by a BroadcastEvent publication, which is the shape search engines look
	 * for when surfacing a livestream.
	 *
	 * @param WP_Post $post The post being described.
	 * @return array The JSON-LD structure, or an empty array when not applicable.
	 */
	private function build_schema( $post ) {
		// Live, recorded, or not WpStream media at all.
		$kind = $this->media_kind( $post );
		if ( '' === $kind ) {
			return array();
		}

		// Base video description shared by live channels and recordings.
		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'VideoObject',
			'name'        => get_the_title( $post ),
			'description' => $this->description( $post ),
			'url'         => get_permalink( $post ),
			// Search engines expect an ISO-8601 date here.
			'uploadDate'  => get_post_time( 'c', true, $post ),
		);

		// Only advertise a thumbnail when the post actually has one.
		$image = $this->image_url( $post );
		if ( '' !== $image ) {
			$schema['thumbnailUrl'] = $image;
		}

		// A live channel is a broadcast rather than a recording, which is what
		// search engines look for when surfacing a livestream.
		if ( 'live' === $kind ) {
			$schema['publication'] = array(
				'@type'           => 'BroadcastEvent',
				'isLiveBroadcast' => true,
				'startDate'       => get_post_time( 'c', true, $post ),
			);
		}

		return $schema;
	}

	/**
	 * Classify a post as live streaming, recorded video, or neither.
	 *
	 * Free content is identified by its post type; paid content is an ordinary
	 * WooCommerce product whose product type carries the distinction.
	 *
	 * @param WP_Post $post The post being described.
	 * @return string 'live', 'vod', or an empty string when the post is not
	 *                WpStream media.
	 */
	private function media_kind( $post ) {
		// Free live channels and free recordings each have their own post type.
		if ( 'wpstream_product' === $post->post_type ) {
			return 'live';
		}
		if ( 'wpstream_product_vod' === $post->post_type ) {
			return 'vod';
		}

		// Anything else can only qualify as a paid WooCommerce stream.
		if ( 'product' !== $post->post_type || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return '';
		}

		// The plugin's two custom product types mirror the free post types.
		if ( 'live_stream' === $product->get_type() ) {
			return 'live';
		}
		if ( 'video_on_demand' === $product->get_type() ) {
			return 'vod';
		}

		// An ordinary shop product is not video and gets nothing from us.
		return '';
	}

	/**
	 * Resolve the post for a WpStream singular view.
	 *
	 * @return WP_Post|null The current post, or null when this is not a
	 *                      WpStream singular view.
	 */
	private function current_post() {
		// The share markup applies to paid products and to free live/VOD posts.
		if ( ! is_singular( array( 'product', 'wpstream_product', 'wpstream_product_vod' ) ) ) {
			return null;
		}

		// Read the post from the main query rather than the $post global, so a
		// stray setup_postdata() elsewhere cannot redirect the tags.
		$post = get_queried_object();

		// Guard against the queried object being a term or user.
		return ( $post instanceof WP_Post ) ? $post : null;
	}

	/**
	 * Detect an active SEO plugin that already emits social metadata.
	 *
	 * @return bool True when Yoast SEO or Rank Math is active.
	 */
	private function seo_plugin_active() {
		// Yoast defines its version constant once loaded; Rank Math its class.
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
	}

	/**
	 * Print the Open Graph and Twitter card tags for a post.
	 *
	 * @param WP_Post $post The post being viewed.
	 * @return void
	 */
	private function render_open_graph( $post ) {
		// Full-size featured image URL, or an empty string when none is set.
		$image = $this->image_url( $post );

		// A Woo product is a purchasable product; live channels and VOD are video.
		$type = ( 'product' === $post->post_type ) ? 'product' : 'video.other';

		// Without a picture there is nothing for the large card layout to show.
		$card = ( '' !== $image ) ? 'summary_large_image' : 'summary';

		// Every value is attribute-escaped: stripping tags from post content
		// leaves quotes intact, which would otherwise close the content
		// attribute and let the body inject its own markup.
		printf(
			'<meta property="og:title" content="%s" />' . "\n",
			esc_attr( get_the_title( $post ) )
		);
		printf(
			'<meta property="og:url" content="%s" />' . "\n",
			esc_url( get_permalink( $post ) )
		);
		printf(
			'<meta property="og:type" content="%s" />' . "\n",
			esc_attr( $type )
		);
		printf(
			'<meta property="og:site_name" content="%s" />' . "\n",
			esc_attr( get_bloginfo( 'name' ) )
		);
		printf(
			'<meta property="og:description" content="%s" />' . "\n",
			esc_attr( $this->description( $post ) )
		);

		// Both image tags are only meaningful when a featured image exists.
		if ( '' !== $image ) {
			printf(
				'<meta property="og:image" content="%s" />' . "\n",
				esc_url( $image )
			);
			printf(
				'<meta property="og:image:secure_url" content="%s" />' . "\n",
				esc_url( $image )
			);
		}

		// Twitter reads title, description and picture from the og:* tags above;
		// the card type is the only value with no Open Graph equivalent.
		printf(
			'<meta name="twitter:card" content="%s" />' . "\n",
			esc_attr( $card )
		);
	}

	/**
	 * Build the plain-text share description for a post.
	 *
	 * @param WP_Post $post The post being viewed.
	 * @return string Tag-free, trimmed description text.
	 */
	private function description( $post ) {
		// Strip markup first, then shorten to a share-sized summary.
		return wp_trim_words( wp_strip_all_tags( $post->post_content ), self::DESCRIPTION_WORDS );
	}

	/**
	 * Resolve the full-size featured image URL for a post.
	 *
	 * @param WP_Post $post The post being viewed.
	 * @return string Image URL, or an empty string when no featured image is set.
	 */
	private function image_url( $post ) {
		// Featured image attachment id, 0 when the post has none.
		$image_id = get_post_thumbnail_id( $post );
		if ( ! $image_id ) {
			return '';
		}

		// wp_get_attachment_image_src() returns [url, width, height, is_resized].
		$src = wp_get_attachment_image_src( $image_id, 'full' );

		return ( is_array( $src ) && ! empty( $src[0] ) ) ? $src[0] : '';
	}
}

// Wire the front-end hooks as soon as this file is loaded.
Wpstream_Seo::init();
