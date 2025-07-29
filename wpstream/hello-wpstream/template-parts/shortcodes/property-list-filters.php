<?php
/**
 * Property list filters
 *
 * @package wpstream-theme
 *
 * @var array $attributes
 */

?>
<div class="wpstream_item_list_filter">
	<?php

	$selected_type = $attributes['type'];
	$video_card = $attributes['video_card'];

	if ( isset( $_GET['type'] ) ) {                                         //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_type = sanitize_text_field( wp_unslash( $_GET['type'] ) );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	print wpstream_post_type_options_select( $attributes, $selected_type );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	$taxonomy_to_attributes = array(
		'category'              => 'category_ids',
		'wpstream_actors'       => 'actors_ids',
		'wpstream_category'     => 'wpstream_category_ids',
		'wpstream_movie_rating' => 'movie_ratings_ids',
	);

	$taxonomy_array = wpstream_return_taxonomy_array();
	unset( $taxonomy_array['post_tag'] );

	foreach ( $taxonomy_array as $taxonomy => $post_types ) {// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( isset( $attributes[ 'show_bar_' . $taxonomy ] ) && 'none' !== $attributes[ 'show_bar_' . $taxonomy ] ) {
			//print wpstream_taxonomy_terms_dropdown( $taxonomy, $attributes[ $taxonomy_to_attributes[ $taxonomy ] ], $attributes );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			print wpstream_taxonomy_terms_dropdown_bootstrap( $taxonomy, $attributes[ $taxonomy_to_attributes[ $taxonomy ] ], $attributes );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		
		}
	}

	$selected_sort = $attributes['sort_by'];

	if ( isset( $_GET['sort_by'] ) ) {                                         //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_sort = sanitize_text_field( wp_unslash( $_GET['sort_by'] ) );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

    $options = '';
    if ( function_exists( 'wstream_sort_options_array' ) ) {
	    $options = wstream_sort_options_array();
    }
	print  wpstream_create_custom_dropdown( $options,'sort_options', 'wpstream_dropdown_select_trigger_ajax wpstream_dropdown_sort_by', 'sort_options_dropdown', esc_html__('Default','hello-wpstream'),$selected_sort);//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	
	?>
</div>
