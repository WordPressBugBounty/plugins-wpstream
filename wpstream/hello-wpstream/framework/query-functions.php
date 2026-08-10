<?php
/**
 * Query functions.
 *
 * A thin wrapper around WP_Query that adds optional transient caching so
 * repeated listing queries can be served from cache. The current post is
 * excluded from non-search queries to avoid showing the page you are on.
 *
 * @package wpstream-theme
 */

// Guard against redeclaration.
if ( ! function_exists( 'wpstream_custom_query' ) ) {
	/**
	 * Perform a custom query with optional transient caching.
	 *
	 * @param array  $query_args    Query arguments.
	 * @param string $transient_key Transient key for caching.
	 * @param bool   $use_transient Whether to use transient caching. Default is false.
	 *
	 * @return WP_Query|array|null The query result.
	 */
	function wpstream_custom_query( $query_args, $transient_key, $use_transient = false ) {
		// Check if transient caching is enabled and post type is not "post".
		if ( $use_transient ) {
			// Try to get the data from the transient cache.
			$query = get_transient( $transient_key );

			// A cached WP_Query object was found: serve it directly.
			if ( false !== $query ) {
				return $query; // Return cached query result if available.
			}
		}

		// If not using transient caching or cache is not available, proceed with the query.
		// Never let sticky posts jump the ordering of these listings.
		$query_args['ignore_sticky_posts'] = 1;
		// For non-search queries, exclude the current post from the results.
		if ( ! isset( $query_args['s'] ) ) {
			$query_args['post__not_in'] = array( get_the_ID() );
		}
		// Run the query.
		$query                             = new WP_Query( $query_args );

		// Check if the query has posts.
		if ( $query->have_posts() ) {
			// Cache the populated result set for 6 hours when caching is enabled.
			if ( $use_transient ) {
				set_transient( $transient_key, $query, 6 * 60 * 60 );
			}
		} else {
			// No results: drop any stale cache entry for this key.
			delete_transient( $transient_key );
		}
		// Restore the global post after the secondary query.
		wp_reset_postdata();
		return $query;
	}
}
