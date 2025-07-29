<?php

/**
 * Elementor taxonomy helpers functions
 *
 * @package wpstream-theme
 */

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Generate category data for autocomplete.
 *
 * @package wpstream-theme
 */

/**
 * Generate category
 *
 * @param string $taxonomy_name The name of the taxonomy.
 * @return array The category data for autocomplete.
 */

if ( ! function_exists( 'wpstream_theme_generate_category_values' ) ) {
	/**
	 * Generate category values.
	 *
	 * @param string $taxonomy_name The name of the taxonomy.
	 * @return array The category values.
	 */
	function wpstream_theme_generate_category_values( $taxonomy_name ) {
		$use_transient = function_exists( 'wpstream_return_use_transient' ) ? wpstream_return_use_transient() : false;

		$use_transient = ! $use_transient ? false : $use_transient;

		$transient_name = 'wpstream_taxonomy_value_terms_' . $taxonomy_name;

		// If transients are not used or the transient doesn't exist, fetch and cache the terms.
		if ( ! $use_transient || false === ( $item_taxonomy_values = wpstream_request_transient_cache( $transient_name ) ) ) {//phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
			$terms_category = get_terms(
				array(
					'taxonomy'   => $taxonomy_name,
					'hide_empty' => false,
				)
			);

			if ( is_wp_error( $terms_category ) ) {
				return array();
			}

			$item_taxonomy_values = array_map(
				function ( $term ) {
					return array(
						'label' => $term->name,
						'value' => $term->term_id,
					);
				},
				$terms_category
			);

			// Cache the results only if transients are being used.
			if ( $use_transient ) {
				wstream_set_transient_cache( $transient_name, $item_taxonomy_values, 4 * HOUR_IN_SECONDS );
			}
		}

		return $item_taxonomy_values;
	}
}

if ( ! function_exists( 'wpstream_return_taxonomy_array' ) ) {
	/**
	 * Return taxonomy array.
	 *
	 * @return array Taxonomy array.
	 */
	function wpstream_return_taxonomy_array() {
		$taxonomy_array = array(
			'category'              => array(
				'post',
				'product',
				'wpstream_bundles',
				'wpstream_product_vod',
				'wpstream_product',
			),

			'post_tag'              => array(
				'post',
				'product',
				'wpstream_bundles',
				'wpstream_product_vod',
				'wpstream_product',
			),
			'date'              => array(
				'post',
			),

			'wpstream_actors'       => array(
				'product',
				'wpstream_bundles',
				'wpstream_product_vod',
				'wpstream_product',
			),

			'wpstream_category'     => array(
				'product',
				'wpstream_bundles',
				'wpstream_product_vod',
				'wpstream_product',
			),

			'wpstream_movie_rating' => array(
				'product',
				'wpstream_bundles',
				'wpstream_product_vod',
				'wpstream_product',
			),

		);

		return $taxonomy_array;
	}
}

if ( ! function_exists( 'wpstream_return_taxonomy_labels' ) ) {
	/**
	 * Return taxonomy labels.
	 *
	 * @return array Taxonomy labels.
	 */
	function wpstream_return_taxonomy_labels() {
		$taxonomy_array = array(
			'category'              => esc_html__( 'Category', 'hello-wpstream' ),
			'post_tag'              => esc_html__( 'Tags', 'hello-wpstream' ),
			'wpstream_actors'       => esc_html__( 'Actors', 'hello-wpstream' ),
			'wpstream_category'     => esc_html__( 'Media Category', 'hello-wpstream' ),
			'wpstream_movie_rating' => esc_html__( 'Media Rating', 'hello-wpstream' ),
		);

		return $taxonomy_array;
	}
}