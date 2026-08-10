<?php
/**
 * Property list filters.
 *
 * Renders the filter bar above a video/property listing shortcode: a post-type
 * selector, one taxonomy dropdown per enabled taxonomy (except tags), and a
 * "sort by" dropdown. The selected type and sort can be overridden via the
 * `type` / `sort_by` query string so selections survive AJAX re-filtering.
 *
 * @package wpstream-theme
 *
 * @var array $attributes Shortcode attributes controlling which bars/values show.
 */

?>
<div class="wpstream_item_list_filter">
	<?php

	// Default the post-type and card style from the shortcode attributes.
	$selected_type = $attributes['type'];
	$video_card = $attributes['video_card'];

	// A `type` query arg (from a prior selection) overrides the default post type.
	if ( isset( $_GET['type'] ) ) {                                         //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_type = sanitize_text_field( wp_unslash( $_GET['type'] ) );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	// Print the post-type selector dropdown, seeded with the resolved selection.
	print wpstream_post_type_options_select( $attributes, $selected_type );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	// Map each supported taxonomy to the shortcode attribute holding its preselected term IDs.
	$taxonomy_to_attributes = array(
		'category'              => 'category_ids',
		'wpstream_actors'       => 'actors_ids',
		'wpstream_category'     => 'wpstream_category_ids',
		'wpstream_movie_rating' => 'movie_ratings_ids',
	);

	// Grab every registered taxonomy, then drop tags (never shown as a filter here).
	$taxonomy_array = wpstream_return_taxonomy_array();
	unset( $taxonomy_array['post_tag'] );

	// Emit a Bootstrap dropdown for each taxonomy the shortcode opted into showing.
	foreach ( $taxonomy_array as $taxonomy => $post_types ) {// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		// Only render this taxonomy's bar when its show_bar_* attribute is set and not 'none'.
		if ( isset( $attributes[ 'show_bar_' . $taxonomy ] ) && 'none' !== $attributes[ 'show_bar_' . $taxonomy ] ) {
			// Legacy non-Bootstrap dropdown kept commented out; the Bootstrap variant is used below.
			//print wpstream_taxonomy_terms_dropdown( $taxonomy, $attributes[ $taxonomy_to_attributes[ $taxonomy ] ], $attributes );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			// Print the taxonomy term dropdown, seeded with any preselected term IDs.
			print wpstream_taxonomy_terms_dropdown_bootstrap( $taxonomy, $attributes[ $taxonomy_to_attributes[ $taxonomy ] ], $attributes );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		
		}
	}

	// Default the sort selection to the shortcode attribute value.
	$selected_sort = $attributes['sort_by'];

	// A sort_by query arg (from a prior selection) overrides the default.
	if ( isset( $_GET['sort_by'] ) ) {                                         //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_sort = sanitize_text_field( wp_unslash( $_GET['sort_by'] ) );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

    // Build the full sort options list (empty string when the helper is unavailable).
    $options = '';
    if ( function_exists( 'wstream_sort_options_array' ) ) {
	    $options = wstream_sort_options_array();
    }
	// Render the custom sort dropdown wired to the listing AJAX trigger classes.
	print  wpstream_create_custom_dropdown( $options,'sort_options', 'wpstream_dropdown_select_trigger_ajax wpstream_dropdown_sort_by', 'sort_options_dropdown', esc_html__('Default','hello-wpstream'),$selected_sort);//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	
	?>
</div>
