<?php
/**
 * Metaboxes.
 *
 * Registers the theme's Meta Box (rwmb) fields: per-post display overrides
 * (sidebar / transparent header / page title), the VOD-to-channel attachment
 * selector, the image gallery field, and the video trailer / preview / logo
 * uploads. All boxes are added via the `rwmb_meta_boxes` filter.
 *
 * @package wpstream-theme
 */


// Register the per-post settings box last (PHP_INT_MAX priority) so it wins over earlier additions.
add_filter( 'rwmb_meta_boxes', 'wpstream_theme_register_metabox', PHP_INT_MAX );
/**
 * Register metabox.
 * Adds theme settings to the post page.
 *
 * @param $meta_boxes
 * @return mixed
 */
function wpstream_theme_register_metabox( $meta_boxes ) {
	// Shared prefix for all theme field IDs.
	$prefix = 'wpstream_theme_';

	// Post types that should show the "Hello WPStream settings" box.
	$post_types = array( 'post', 'page', 'product', 'wpstream_product', 'wpstream_product_vod', 'wpstream_bundles' );

	// "Hello WPStream settings" box: shown in the side context with low priority.
	$meta_boxes[] = array(
		'title'      => esc_html__( 'Hello WPStream settings', 'hello-wpstream' ),
		'id'         => 'wpstream-theme-settings',
		'post_types' => $post_types,
		'context'    => 'side',
		'priority'   => 'low',
		'fields'     => array(
			// Per-post sidebar override (Yes/No, empty = use global setting).
			array(
				'name'        => esc_html__( 'Show sidebar?', 'hello-wpstream' ),
				'id'          => $prefix . 'show_sidebar_on_post',
				'type'        => 'select',
				'placeholder' => esc_html__('Use global','hello-wpstream'),
				'options'     => array(
					'1' => esc_html__( 'Yes', 'hello-wpstream' ),
					'0' => esc_html__( 'No', 'hello-wpstream' ),
				),
				'desc'        => esc_html__( 'Use this option for override global settings.', 'hello-wpstream' ),
			),
			// Per-post transparent-header override (Yes/No, empty = use global setting).
			array(
				'name'        => esc_html__( 'Use transparent header?', 'hello-wpstream' ),
				'id'          => $prefix . 'use_transparent_on_post',
				'type'        => 'select',
				'placeholder' => esc_html__('Use global','hello-wpstream'),
				'options'     => array(
					'1' => esc_html__( 'Yes', 'hello-wpstream' ),
					'0' => esc_html__( 'No', 'hello-wpstream' ),
				),
				'desc'        => esc_html__( 'Use this option for override global settings.', 'hello-wpstream' ),
			),
		),
	);

	// "WPStream Page settings" box: pages only, adds a per-page title-visibility override.
	$meta_boxes[] = array(
		'title'      => esc_html__( 'WPStream Page settings', 'hello-wpstream' ),
		'id'         => 'wpstream-theme-page-settings',
		'post_types' => array ('page'),
		'context'    => 'side',
		'priority'   => 'low',
		'fields'     => array(
			// Per-page "show page title" override (Yes/No, empty = use global setting).
			array(
				'name'        => esc_html__( 'Show page title?', 'hello-wpstream' ),
				'id'          => $prefix . 'show_page_title',
				'type'        => 'select',
				'placeholder' => esc_html__('Use global','hello-wpstream'),
				'options'     => array(
					'1' => esc_html__( 'Yes', 'hello-wpstream' ),
					'0' => esc_html__( 'No', 'hello-wpstream' ),
				),
				'desc'        => esc_html__( 'Use this option for override global settings.', 'hello-wpstream' ),
			),
		),
	);

	return $meta_boxes;
}

// Register the VOD-to-channel attachment box.
add_filter( 'rwmb_meta_boxes', 'wpstream_metabox_vod_to_channel' );

/**
 * Define metabox for attaching VOD to Channel.
 *
 * @param array $meta_boxes The array of existing metaboxes.
 * @return array            The modified array of metaboxes.
 */
function wpstream_metabox_vod_to_channel( $meta_boxes ) {
	// Shared prefix for field IDs.
	$prefix       = 'wpstream_theme_';
	// Box shown only on VOD products; lets the editor link the VOD to a live channel.
	$meta_boxes[] = array(
		'title'      => esc_html__( 'Attach VOD to Channel', 'hello-wpstream' ),
		'post_types' => 'wpstream_product_vod', // Replace with your custom post type slug.
		'fields'     => array(
			// Channel selector; options are the live channels/products on the site.
			array(
				'name'        => esc_html__( 'Select the channel', 'hello-wpstream' ),
				'id'          => $prefix . 'attach_to_channel',
				'type'        => 'select',
				'options'     => wpstream_get_channels_for_metaboxes(),
				'placeholder' => esc_html__( 'Select the channel', 'hello-wpstream' ),
			),
		),
	);

	return $meta_boxes;
}

/**
 * Get channels for metaboxes.
 *
 * Builds an id => title option list of channel-like posts for the VOD
 * attachment selector. When WooCommerce is active it queries products of the
 * live_stream type (or with no product_type term); otherwise it queries the
 * standalone wpstream_product post type.
 *
 * @return array Map of post ID => escaped post title.
 */
function wpstream_get_channels_for_metaboxes() {
	// With WooCommerce active, channels are products; narrow to live streams.
	if ( class_exists( 'WooCommerce' ) ) {
		$post_type      = array( 'product', 'wpstream_product' );
		// Match live_stream products OR products with no product_type term at all.
		$taxonomy_array = array(
			'relation' => 'OR',
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => array( 'live_stream' ),
			),
			array(
				'taxonomy' => 'product_type',
				'operator' => 'NOT EXISTS',
			),
		);

	} else {
		// Without WooCommerce, only the standalone channel post type exists.
		$post_type      = 'wpstream_product';
		$taxonomy_array = array();
	}

	// Query up to 50 candidate channels.
	$args = array(
		'post_type'      => $post_type,
		'posts_per_page' => 50,
		'tax_query'      => $taxonomy_array, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query

	);
	$query_list = new WP_Query( $args );
	// Accumulator for the id => title option list.
	$options    = array();

	// Walk the results and build the option map.
	if ( $query_list->have_posts() ) {
		while ( $query_list->have_posts() ) :
			$query_list->the_post();
			// Current post ID and its title/type.
			$the_id       = get_the_ID();
			$the_title    = get_the_title( $the_id );
			$post_type    = get_post_type( $the_id );
			$product_type = '';
			// For WooCommerce products, read the product type meta (currently informational only).
			if ( get_post_type( $the_id ) === 'product' ) {
				$product_type = get_post_meta( $the_id, '_product_type', true );
			}

			// Add this channel to the option list, keyed by integer ID.
			$options[ intval( $the_id ) ] = esc_html( $the_title );
		endwhile;
	}

	return $options;
}

// Register the image gallery box (the field read by gallery-functions.php).
add_filter( 'rwmb_meta_boxes', 'wpstream_theme_register_gallery_metabox' );
/**
 * Register gallery metabox for specified post types.
 *
 * @param array $meta_boxes The array of existing metaboxes.
 * @return array            The modified array of metaboxes.
 */
function wpstream_theme_register_gallery_metabox( $meta_boxes ) {
	// Shared prefix for field IDs.
	$prefix = 'wpstream_theme_';

	// Multi-image gallery field, capped at 20 uploads.
	$meta_boxes[] = array(
		'title'      => esc_html__( 'Gallery Metabox', 'hello-wpstream' ),
		'post_types' => array( 'post', 'wpstream_product', 'wpstream_product_vod', 'wpstream_bundles' ), // Replace with your custom post type slug.
		'fields'     => array(
			array(
				'name'             => 'Gallery Images',
				'id'               => $prefix . 'gallery',
				'type'             => 'image_advanced',
				'max_file_uploads' => 20, // Set the maximum number of allowed images.
			),
		),
	);

	return $meta_boxes;
}

// Register the trailer / preview / logo upload boxes.
add_filter( 'rwmb_meta_boxes', 'wpstream_theme_register_video_trailer_metabox' );
/**
 * Register video trailer metabox for specified post types.
 *
 * @param array $meta_boxes The array of existing metaboxes.
 * @return array            The modified array of metaboxes.
 */
function wpstream_theme_register_video_trailer_metabox( $meta_boxes ) {
	// Shared prefix (unused by the fields below, which use plain IDs).
	$prefix = 'wpstream_theme_';

	// Trailer upload: a single video file that can play as a preview/trailer.
	$meta_boxes[] = array(
		'title'      => 'Video Trailer',
		'post_types' => array( 'product', 'post', 'wpstream_product', 'wpstream_product_vod', 'wpstream_bundles' ), // Replace 'product' with the appropriate post type where you want the metabox to appear.
		'fields'     => array(
			array(
				'name'             => 'Video Trailer',
				'id'               => 'video_trailer',
				'type'             => 'file_advanced',
				'max_file_uploads' => 1,
				'mime_type'        => 'video', // Set the allowed MIME types for videos, e.g., 'video/mp4', 'video/webm', 'video/quicktime', etc.
				'desc'             => esc_html__( 'Upload a video trailer for your content. Supported formats: MP4, MOV', 'hello-wpstream' ),
			),
		),
	);

	// Preview upload: a short clip played on hover over the item card in listings.
	$meta_boxes[] = array(
		'title'      => 'Video Preview',
		'post_types' => array( 'product', 'post', 'wpstream_product', 'wpstream_product_vod', 'wpstream_bundles' ), // Replace 'product' with the appropriate post type where you want the metabox to appear.
		'fields'     => array(
			array(
				'name'             => 'Video Preview',
				'id'               => 'video_preview',
				'type'             => 'file_advanced',
				'max_file_uploads' => 1,
				'mime_type'        => 'video', // Set the allowed MIME types for videos, e.g., 'video/mp4', 'video/webm', 'video/quicktime', etc.
				'desc'             => esc_html__( 'Upload a video preview; this plays when a user hovers over the product image in an item list. Maximum resolution: 480x270. Supported formats: MP4, MOV.', 'hello-wpstream' ),
			),
		),
	);



	// Logo upload: a single image shown in the media header section.
	$meta_boxes[] = array(
		'title'      => 'Logo',
		'post_types' => array( 'product', 'wpstream_product', 'wpstream_product_vod', 'wpstream_bundles' ), // Replace 'product' with the appropriate post type where you want the metabox to appear.
		'fields'     => array(
			array(
				'name'             => 'Media Logo',
				'id'               => 'media_logo',
				'type'             => 'single_image',
				'desc'             => esc_html__( 'Upload a logo image that will appear on the media header section', 'hello-wpstream' ),
			),
		),
	);

	return $meta_boxes;
}
