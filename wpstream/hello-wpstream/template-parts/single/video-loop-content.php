<?php
/**
 * Video loop content
 *
 * @package wpstream-theme
 *
 * @var WP_Query $query
 */

$is_wpstream_theme_dashboard = false;

if ( is_page_template( 'wpstream-theme-dashboard.php' ) ) {
	$is_wpstream_theme_dashboard = true;
}

if ( $query->have_posts() ) :
	while ( $query->have_posts() ) :
		$query->the_post(); ?>
		<?php
		if ( is_page_template( 'wpstream-theme-dashboard.php' ) ) {
			$wpstream_cols_name = wpstream_video_cards_column_class( 3 );
		} else {
			$wpstream_cols_name = wpstream_video_cards_column_class( 4 );
		}

		$post_type = get_post_type( get_the_ID() );//phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		if ( isset( $show_bundled_content ) && $show_bundled_content ) {
			if ( 'product' === get_post_type( get_the_ID() ) &&   function_exists('wc_get_product') ) {
				$product = wc_get_product( get_the_ID() );
				if ( $product ) {
					$excerpt = $product->get_short_description();
				}
			} else {
				$excerpt = get_the_excerpt( get_the_ID() );
			}

			include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/single/video-cards/bundled-child-card-v1.php';
		} else {
			$overwrite_wpstream_cols_name = $wpstream_cols_name;
			$unit_card_type               = wpstream_video_item_card_selector();
			include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;
		}

	endwhile;
	wp_reset_postdata();
else :
	?>
	<p><?php esc_html_e( 'Sorry, no video matched your criteria.', 'hello-wpstream' ); ?></p>
<?php endif; ?>