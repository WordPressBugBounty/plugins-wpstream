<?php
/**
 * Blog list filters
 *
 * @package wpstream-theme
 *
 * @var array $attributes
 */

?>
<div class="wpstream_item_list_filter">
	<?php
	$taxonomy_to_attributes = array(
		'category'              => 'category_ids',
    );

	$taxonomy_array = wpstream_return_taxonomy_array();
	unset( $taxonomy_array['post_tag'] );


	foreach ( $taxonomy_array as $taxonomy => $post_types ) {// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( isset( $attributes[ 'show_bar_' . $taxonomy ] ) && 'none' !== $attributes[ 'show_bar_' . $taxonomy ] ) {

			print wpstream_taxonomy_terms_dropdown_blog_post_list_with_bootstrap( $taxonomy, $attributes[ $taxonomy_to_attributes[ $taxonomy ] ], $attributes );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	$selected_sort = $attributes['sort_by'];

	if ( isset( $_GET['sort_by'] ) ) {                                         //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_sort = sanitize_text_field( wp_unslash( $_GET['sort_by'] ) );//phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

    $options = [];
    if ( function_exists( 'wstream_sort_options_array' ) ) {
	    $options = wstream_sort_options_array();
        unset( $options [7] );
        unset( $options [8] );
    }

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
