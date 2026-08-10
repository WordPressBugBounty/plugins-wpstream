<?php
/**
 * Blog list filters.
 *
 * Renders the filter bar above a blog post listing shortcode: one taxonomy
 * dropdown per enabled taxonomy (except tags) plus a "sort by" dropdown. The
 * currently selected sort can be overridden via the `sort_by` query string so
 * the selection survives AJAX-driven re-filtering.
 *
 * @package wpstream-theme
 *
 * @var array $attributes Shortcode attributes controlling which bars/values show.
 */

?>
<div class="wpstream_item_list_filter">
	<?php
	// Map each supported taxonomy to the shortcode attribute holding its preselected term IDs.
	$taxonomy_to_attributes = array(
		'category'              => 'category_ids',
    );

	// Grab every registered taxonomy, then drop tags (never shown as a filter here).
	$taxonomy_array = wpstream_return_taxonomy_array();
	unset( $taxonomy_array['post_tag'] );


	// Emit a Bootstrap dropdown for each taxonomy the shortcode opted into showing.
	foreach ( $taxonomy_array as $taxonomy => $post_types ) {// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		// Only render this taxonomy's bar when its show_bar_* attribute is set and not 'none'.
		if ( isset( $attributes[ 'show_bar_' . $taxonomy ] ) && 'none' !== $attributes[ 'show_bar_' . $taxonomy ] ) {

			// Print the taxonomy term dropdown, seeded with any preselected term IDs.
			print wpstream_taxonomy_terms_dropdown_blog_post_list_with_bootstrap( $taxonomy, $attributes[ $taxonomy_to_attributes[ $taxonomy ] ], $attributes );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	// Default the sort selection to the shortcode attribute value.
	$selected_sort = $attributes['sort_by'];

	// A sort_by query arg (from a prior selection) overrides the default.
	if ( isset( $_GET['sort_by'] ) ) {                                         //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_sort = sanitize_text_field( wp_unslash( $_GET['sort_by'] ) );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

    // Build the sort options list, removing entries 7 and 8 which don't apply to blog posts.
    $options = [];
    if ( function_exists( 'wstream_sort_options_array' ) ) {
	    $options = wstream_sort_options_array();
        unset( $options [7] );
        unset( $options [8] );
    }

	// Render the custom sort dropdown wired to the blog-list AJAX trigger classes.
	print wpstream_create_custom_dropdown(
		$options,
		'sort_options',
		'wpstream_dropdown_select_trigger_ajax_blog_list wpstream_blog_sort_options',
		'blog_sort_options_dropdown',
		esc_html__('Default','hello-wpstream'),
		$selected_sort
	);
	?>
</div>
