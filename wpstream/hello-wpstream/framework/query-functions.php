<?php
/**
 * Query functions
 *
 * @package wpstream-theme
 */

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

			if ( false !== $query ) {
				return $query; // Return cached query result if available.
			}
		}

		// If not using transient caching or cache is not available, proceed with the query.
		$query_args['ignore_sticky_posts'] = 1;
		if ( ! isset( $query_args['s'] ) ) {
			$query_args['post__not_in'] = array( get_the_ID() );
		}
		$query                             = new WP_Query( $query_args );

		// Check if the query has posts.
		if ( $query->have_posts() ) {
			if ( $use_transient ) {
				set_transient( $transient_key, $query, 6 * 60 * 60 );
			}
		} else {
			delete_transient( $transient_key );
		}
		wp_reset_postdata();
		return $query;
	}
}
