<?php
/**
 * Video loop content.
 *
 * Iterates a WP_Query of videos/products and renders one card per result. The
 * column width and card template vary by context: dashboard vs front-end,
 * bundled-child cards when $show_bundled_content is set, otherwise the theme's
 * configured unit card. Prints a "no results" message when the query is empty.
 *
 * @package wpstream-theme
 *
 * @var WP_Query $query The query whose posts are looped and rendered as cards.
 */

// Track whether we are rendering inside the WpStream dashboard page template.
$is_wpstream_theme_dashboard = false;

// Detect the dashboard page template up front (also re-checked per item below).
if ( is_page_template( 'wpstream-theme-dashboard.php' ) ) {
	$is_wpstream_theme_dashboard = true;
}

// Loop the query results when there are any, otherwise fall through to the else.
if ( $query->have_posts() ) :
	while ( $query->have_posts() ) :
		// Advance the loop and set up the global post for template tags.
		$query->the_post(); ?>
		<?php
		// Narrower 3-up grid on the dashboard, wider 4-up grid elsewhere.
		if ( is_page_template( 'wpstream-theme-dashboard.php' ) ) {
			$wpstream_cols_name = wpstream_video_cards_column_class( 3 );
		} else {
			$wpstream_cols_name = wpstream_video_cards_column_class( 4 );
		}

		// Post type of the current item, used to pick the excerpt source below.
		$post_type = get_post_type( get_the_ID() );//phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// Bundled context renders a child card and needs an excerpt prepared first.
		if ( isset( $show_bundled_content ) && $show_bundled_content ) {
			// Products pull their short description; other post types use the excerpt.
			if ( 'product' === get_post_type( get_the_ID() ) &&   function_exists('wc_get_product') ) {
				$product = wc_get_product( get_the_ID() );
				if ( $product ) {
					$excerpt = $product->get_short_description();
				}
			} else {
				$excerpt = get_the_excerpt( get_the_ID() );
			}

			// Render the bundled child card variant.
			include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/single/video-cards/bundled-child-card-v1.php';
		} else {
			// Non-bundled: expose the column class and render the configured unit card.
			$overwrite_wpstream_cols_name = $wpstream_cols_name;
			$unit_card_type               = wpstream_video_item_card_selector();
			include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;
		}

	endwhile;
	// Restore the global post after the custom loop.
	wp_reset_postdata();
else :
	?>
	<!-- Empty-state message shown when the query returned no posts. -->
	<p><?php esc_html_e( 'Sorry, no video matched your criteria.', 'hello-wpstream' ); ?></p>
<?php endif; ?>