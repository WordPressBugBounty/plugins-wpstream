<?php
/**
 * Gallery Image Function.
 *
 * Builds the front-end image gallery markup for a post or WooCommerce product.
 * WooCommerce products read their images from the standard product gallery meta,
 * while other post types use the theme's own "wpstream_theme_gallery" Meta Box
 * field. Output is rendered as FancyBox-enabled thumbnail grids.
 *
 * @package wpstream-theme
 */

// Guard against redeclaration when function names overlap.
if ( ! function_exists( 'wpstream_theme_image_gallery' ) ) {
	/**
	 * Generate an image gallery HTML.
	 *
	 * @param int      $post_id      The ID of the post.
	 * @param string   $full_size    The size of the full-size images.
	 * @param int|null $images_count Optional cap on how many product gallery images to include.
	 * @return string Returns the HTML for the image gallery.
	 */
	function wpstream_theme_image_gallery( $post_id, $full_size = 'full', $images_count = null ) {
		// Get the post type.
		$post_type = get_post_type( $post_id );

		// Products source their gallery from WooCommerce meta; everything else uses the theme field.
		if ( 'product' === $post_type ) {
			// If the post type is 'product', retrieve gallery images using WooCommerce custom field.
			// Featured image (thumbnail) becomes the first/hero image of the gallery.
			$product_image = get_post_meta( $post_id, '_thumbnail_id', true );
			// Comma-separated list of additional product gallery attachment IDs.
			$gallery_images_source = get_post_meta( $post_id, '_product_image_gallery', true );
			// get only a specific number of images, based on $images_count
			if ( $images_count && $gallery_images_source ) {
				// Split the CSV into an array of IDs.
				$gallery_images = explode( ',', $gallery_images_source );
				// Limit to the requested number of images.
				$gallery_images = array_slice( $gallery_images, 0, $images_count );
				// Put the featured image at the front of the list.
				$gallery_images = array_merge( array( $product_image ), $gallery_images );
			} else {
				// No cap (or no gallery meta): use just the featured image.
				$gallery_images = array( $product_image );
			}
			// Products use the dedicated single-product FancyBox layout (hero + row).
			return wpstream_theme_single_product_generate_fancybox( $gallery_images, $full_size );
		} else {
			// For other post types, retrieve gallery images using the 'wpstream_theme_gallery' custom field.
			$gallery_images = null;
			// The gallery field is provided by the Meta Box plugin; only read it if available.
			if(function_exists('rwmb_meta')){
				// rwmb_meta returns an id => attachment map for image_advanced fields.
				$gallery_images = rwmb_meta( 'wpstream_theme_gallery', array(), $post_id );
				// Reduce the map down to a flat list of attachment IDs.
				$gallery_images = array_keys( $gallery_images );
			}
		}

		// Print a section heading only when there are images to show.
		if ( ! empty( $gallery_images ) ) {
			print '<h2 class="mb-30">' . esc_html__( 'Image Gallery', 'hello-wpstream' ) . '</h2>';
		}

		// Generate the gallery HTML and return it as a string.
		return wpstream_theme_image_generate_fancybox( $gallery_images, $full_size );
	}
}

if ( ! function_exists( 'wpstream_theme_image_generate_fancybox' ) ) {
	/**
	 * Generate HTML for a gallery using FancyBox.
	 *
	 * @param array  $image_ids  Array of attachment IDs for the images in the gallery.
	 * @param string $full_size  The size of the full-size images.
	 * @return string Returns the HTML for the gallery.
	 */
	function wpstream_theme_image_generate_fancybox( $image_ids, $full_size = 'full' ) {
		// Nothing to render when there are no attachment IDs.
		if ( empty( $image_ids ) ) {
			return;
		}

		// Open the gallery wrapper and a Bootstrap row.
		$output  = '<div class="wpstream_theme_image_generate_fancybox">';
		$output .= '<div class="row">';
		// Generate links to open images in FancyBox.
		foreach ( $image_ids as $image_id ) {
			// Card-sized thumbnail source used for the visible <img>.
			$wpstream_featured_unit_cards = wp_get_attachment_image_src( $image_id, 'wpstream_featured_unit_cards' );
			// Full-size source used for the FancyBox lightbox link.
			$image_src                    = wp_get_attachment_image_src( $image_id, $full_size );
			// Alt text pulled from the attachment meta.
			$image_alt                    = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			// Open the responsive grid column for this item.
			$output                      .= '<div class="col-6 col-sm-6 col-md-4 col-lg-3 wpstream-gallery-items">';
			// Only render the link/image when a valid source URL exists.
			if ( isset( $image_src[0] ) ) {
		
				// Zoom-in overlay icon shown on hover.
				$output .='<div class="wpstream_video_unit_video_play">'.wpstream_theme_get_svg_icon('zoom-in.svg').'</div>';
				
				
				// FancyBox link pointing at the full-size image.
				$output .= '<a href="' . $image_src[0] . '" rel="data-fancybox-thumb" data-fancybox="gallery">';
				// Hover overlay element.
				$output .='<div class="wpstream_video_unit_overlay"></div>';
				// Visible thumbnail image.
				$output .= '<img class="w-100 h-100 rounded-3" src="' . $wpstream_featured_unit_cards[0] . '" alt="' . $image_alt . '">';
				// Close the FancyBox link.
				$output .= '</a>';
			}
			// Close this grid column.
			$output .= '</div>';
		}
		// Close the row and gallery wrapper.
		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}
}

if ( ! function_exists( 'wpstream_theme_single_product_generate_fancybox' ) ) {
	/**
	 * Generate HTML for a gallery using FancyBox.
	 *
	 * @param array  $image_ids  Array of attachment IDs for the images in the gallery.
	 * @param string $full_size  The size of the full-size images.
	 * @return string Returns the HTML for the gallery.
	 */
	function wpstream_theme_single_product_generate_fancybox( $image_ids, $full_size = 'full' ) {
		// Open the gallery wrapper.
		$output  = '<div class="wpstream_theme_image_generate_fancybox">';

		// Generate the first item
		// Hero image: full width when it is the only image, otherwise the wide 9-column slot.
		$output .= wpstream_generate_fancybox_image_html( $image_ids[0], 'wpstream_featured_blog_image', count( $image_ids ) === 1 ? 'col-12' : 'col-12 col-sm-12 col-md-12 col-lg-9' );

		// Generate the rest of the gallery images
		// Drop the hero image already rendered above.
		array_shift( $image_ids );
		// Render any remaining images in a side/secondary row.
		if ( ! empty( $image_ids ) ) {
			$output .= '<div class="row col-6 col-lg-3 col-md-12 col-sm-12">';
			// One FancyBox thumbnail per remaining image.
			foreach ( $image_ids as $image_id ) {
				$output .= wpstream_generate_fancybox_image_html( $image_id, $full_size, 'col-6 col-sm-6 col-md-4 col-lg-3' );
			}
			$output .= '</div>';
		}

		// Close the gallery wrapper.
		$output .= '</div>';

		return $output;
	}
}

// Guard against redeclaration.
if ( ! function_exists( 'wpstream_generate_fancybox_image_html') ) {
	/**
	 * Build the FancyBox markup for a single gallery image.
	 *
	 * @param int    $image_id  Attachment ID of the image.
	 * @param string $full_size Registered image size to use for the source.
	 * @param string $class     Extra CSS classes for the item wrapper (column sizing).
	 * @return string           The item HTML, or '' when the image source is missing.
	 */
	function wpstream_generate_fancybox_image_html( $image_id, $full_size, $class ) {
		// Thumbnail source for the visible <img> (same size used for both here).
		$wpstream_featured_unit_cards = wp_get_attachment_image_src( $image_id, $full_size );
		// Source used for the FancyBox link target.
		$image_src                    = wp_get_attachment_image_src( $image_id, $full_size );
		// Attachment alt text.
		$image_alt                    = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		// Skip images without a resolvable source.
		if ( ! isset( $image_src[0] ) ) {
			return '';
		}

		// Item wrapper with caller-supplied column classes.
		$output  = '<div class="wpstream-gallery-items ' . esc_attr( $class ) . '">';
		// Zoom-in hover icon.
		$output .= '<div class="wpstream_video_unit_video_play">' . wpstream_theme_get_svg_icon('zoom-in.svg') . '</div>';
		// FancyBox link to the full image (URL escaped).
		$output .= '<a href="' . esc_url( $image_src[0] ) . '" rel="data-fancybox-thumb" data-fancybox="gallery">';
		// Hover overlay element.
		$output .= '<div class="wpstream_video_unit_overlay"></div>';
		// Visible thumbnail image (source and alt escaped).
		$output .= '<img class="w-100 h-100 rounded-3" src="' . esc_url( $wpstream_featured_unit_cards[0] ) . '" alt="' . esc_attr( $image_alt ) . '">';
		// Close the link and wrapper.
		$output .= '</a>';
		$output .= '</div>';

		return $output;
	}
}