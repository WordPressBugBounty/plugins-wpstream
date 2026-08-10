<?php
/**
 * Not live past broadcast
 *
 * Loops a supplied WP_Query of past (non-live) broadcasts and renders each as a
 * "not live" video card. Falls back to a "no results" message when the query is
 * empty. Flags whether it is running inside the theme dashboard page template.
 *
 * @package wpstream-theme
 *
 * @var WP_Query $query Query of broadcast posts to display as cards.
 */

// Track whether this list is being shown on the theme dashboard page template.
$is_wpstream_theme_dashboard = false;

// The dashboard template uses this flag to tweak card behaviour (e.g. remove buttons).
if ( is_page_template( 'wpstream-theme-dashboard.php' ) ) {
	$is_wpstream_theme_dashboard = true;
}

// Render cards only when the query returned at least one post.
if ( $query->have_posts() ) :

	// Iterate every matched post and emit a "not live" card for it.
	while ( $query->have_posts() ) {
		// Advance the loop and set up global post data for template helpers.
		$query->the_post();
		// Force full-width columns for each card in this list.
		$wpstream_cols_name = 'col-md-12';
		// Pull in the shared "not live" card markup for the current post.
		include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/single/video-cards/video-card-not-live-v1.php';
	}

	// Restore the main query's global post after the custom loop.
	wp_reset_postdata();
else :
	// No posts matched: show a friendly empty-state message.
	?>
	<!-- Empty state: shown when the query returns no broadcasts. -->
	<p><?php esc_html_e( 'Sorry, no video matched your criteria.', 'hello-wpstream' ); ?></p>
<?php endif; ?>