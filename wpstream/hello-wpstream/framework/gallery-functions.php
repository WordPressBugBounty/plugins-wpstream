<?php
/**
 * Gallery Image Function
 *
 * @package wpstream-theme
 */

if ( ! function_exists( 'wpstream_theme_image_gallery' ) ) {
	/**
	 * Generate an image gallery HTML.
	 *
	 * @param int    $post_id    The ID of the post.
	 * @param string $full_size  The size of the full-size images.
	 * @return string Returns the HTML for the image gallery.
	 */
	function wpstream_theme_image_gallery( $post_id, $full_size = 'full', $images_count = null ) {
		// Get the post type.
		$post_type = get_post_type( $post_id );

		if ( 'product' === $post_type ) {
			// If the post type is 'product', retrieve gallery images using WooCommerce custom field.
			$product_image = get_post_meta( $post_id, '_thumbnail_id', true );
			$gallery_images_source = get_post_meta( $post_id, '_product_image_gallery', true );
			// get only a specific number of images, based on $images_count
			if ( $images_count && $gallery_images_source ) {
				$gallery_images = explode( ',', $gallery_images_source );
				$gallery_images = array_slice( $gallery_images, 0, $images_count );
				$gallery_images = array_merge( array( $product_image ), $gallery_images );
			} else {
				$gallery_images = array( $product_image );
			}
			return wpstream_theme_single_product_generate_fancybox( $gallery_images, $full_size );
		} else {
			// For other post types, retrieve gallery images using the 'wpstream_theme_gallery' custom field.
			$gallery_images = null;
			if(function_exists('rwmb_meta')){
				$gallery_images = rwmb_meta( 'wpstream_theme_gallery', array(), $post_id );
				$gallery_images = array_keys( $gallery_images );
			}
		}

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
		if ( empty( $image_ids ) ) {
			return;
		}

		$output  = '<div class="wpstream_theme_image_generate_fancybox">';
		$output .= '<div class="row">';
		// Generate links to open images in FancyBox.
		foreach ( $image_ids as $image_id ) {
			$wpstream_featured_unit_cards = wp_get_attachment_image_src( $image_id, 'wpstream_featured_unit_cards' );
			$image_src                    = wp_get_attachment_image_src( $image_id, $full_size );
			$image_alt                    = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			$output                      .= '<div class="col-6 col-sm-6 col-md-4 col-lg-3 wpstream-gallery-items">';
			if ( isset( $image_src[0] ) ) {
		
				$output .='<div class="wpstream_video_unit_video_play">'.wpstream_theme_get_svg_icon('zoom-in.svg').'</div>';
				
				
				$output .= '<a href="' . $image_src[0] . '" rel="data-fancybox-thumb" data-fancybox="gallery">';
				$output .='<div class="wpstream_video_unit_overlay"></div>';
				$output .= '<img class="w-100 h-100 rounded-3" src="' . $wpstream_featured_unit_cards[0] . '" alt="' . $image_alt . '">';
				$output .= '</a>';
			}
			$output .= '</div>';
		}
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
		$output  = '<div class="wpstream_theme_image_generate_fancybox">';

		// Generate the first item
		$output .= wpstream_generate_fancybox_image_html( $image_ids[0], 'wpstream_featured_blog_image', count( $image_ids ) === 1 ? 'col-12' : 'col-12 col-sm-12 col-md-12 col-lg-9' );

		// Generate the rest of the gallery images
		array_shift( $image_ids );
		if ( ! empty( $image_ids ) ) {
			$output .= '<div class="row col-6 col-lg-3 col-md-12 col-sm-12">';
			foreach ( $image_ids as $image_id ) {
				$output .= wpstream_generate_fancybox_image_html( $image_id, $full_size, 'col-6 col-sm-6 col-md-4 col-lg-3' );
			}
			$output .= '</div>';
		}

		$output .= '</div>';

		return $output;
	}
}

if ( ! function_exists( 'wpstream_generate_fancybox_image_html') ) {
	function wpstream_generate_fancybox_image_html( $image_id, $full_size, $class ) {
		$wpstream_featured_unit_cards = wp_get_attachment_image_src( $image_id, $full_size );
		$image_src                    = wp_get_attachment_image_src( $image_id, $full_size );
		$image_alt                    = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		if ( ! isset( $image_src[0] ) ) {
			return '';
		}

		$output  = '<div class="wpstream-gallery-items ' . esc_attr( $class ) . '">';
		$output .= '<div class="wpstream_video_unit_video_play">' . wpstream_theme_get_svg_icon('zoom-in.svg') . '</div>';
		$output .= '<a href="' . esc_url( $image_src[0] ) . '" rel="data-fancybox-thumb" data-fancybox="gallery">';
		$output .= '<div class="wpstream_video_unit_overlay"></div>';
		$output .= '<img class="w-100 h-100 rounded-3" src="' . esc_url( $wpstream_featured_unit_cards[0] ) . '" alt="' . esc_attr( $image_alt ) . '">';
		$output .= '</a>';
		$output .= '</div>';

		return $output;
	}
}