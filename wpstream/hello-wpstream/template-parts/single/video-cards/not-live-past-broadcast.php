<?php
/**
 * Not live past broadcast
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

	while ( $query->have_posts() ) {
		$query->the_post();
		$wpstream_cols_name = 'col-md-12';
		include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/single/video-cards/video-card-not-live-v1.php';
	}

	wp_reset_postdata();
else :
	?>
	<p><?php esc_html_e( 'Sorry, no video matched your criteria.', 'hello-wpstream' ); ?></p>
<?php endif; ?>