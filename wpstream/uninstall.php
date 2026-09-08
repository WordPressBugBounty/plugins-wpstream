<?php

/**
 * Fired when the plugin is uninstalled (deleted from the Plugins screen).
 *
 * This is the plugin's single place for permanent data removal — the
 * deactivator intentionally preserves the account connection (issue #112),
 * so credentials/tokens are cleaned up ONLY here. WordPress runs this file
 * on its own, without loading the plugin, so only core WP functions may be
 * used below.
 *
 * Scope: plugin-owned options, cached data, user meta, scheduled events and
 * generated Streamify files are removed. WpStream content is preserved unless
 * the site explicitly opts in to deleting it.
 *
 * @link       http://wpstream.net
 * @since      3.0.1
 *
 * @package    Wpstream
 */

// If uninstall not called from WordPress, then exit.
// WP_UNINSTALL_PLUGIN is only defined when WordPress runs this file through the
// official uninstall routine; its absence means the file was reached directly
// (e.g. a direct HTTP request), so we abort to prevent arbitrary execution.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	// Not a legitimate uninstall context — stop immediately.
	exit;
}

global $wpdb;

/**
 * Remove all WpStream-owned data from the current site's tables.
 *
 * The current-site seam is intentional: switch_to_blog() changes $wpdb's site
 * tables, allowing the same cleanup implementation to serve single-site and
 * multisite installations without separate inventories.
 */
$wpstream_cleanup_current_site = static function () use ( $wpdb ) {
	// Read the destructive preference before the wpstream_* option sweep.
	$wpstream_delete_content_option = get_option( 'wpstream_delete_content_on_uninstall', 'no' );
	$wpstream_delete_content = in_array(
		$wpstream_delete_content_option,
		array( true, 1, '1', 'yes' ),
		true
	);

	// Credentials and tokens are explicitly removed even if a later bulk query
	// fails, because plaintext secrets must never outlive the plugin.
	delete_option( 'wpstream_api_key' );
	delete_option( 'wpstream_api_secret_key' );
	delete_option( 'wpstream_api_username' );
	delete_option( 'wpstream_api_password' );
	delete_option( 'wpstream_current_token' );
	delete_option( 'wpstream_token_expire' );
	// Clean residual pre-TASK-31 names if migration never had a chance to run.
	delete_option( 'wp_estate_curent_token' );
	delete_option( 'wp_estate_token_expire' );
	delete_transient( 'wpstream_token_api' );
	delete_transient( 'wpstream_token_request_30' );

	wp_clear_scheduled_hook( 'wpstream_log_cleanup' );
	wp_clear_scheduled_hook( 'wpstreamify_cleanup_event' );

	// Remove dynamic plugin options that cannot be enumerated safely. Escaping
	// underscores prevents SQL LIKE from broadening the ownership prefixes.
	$wpstream_option_patterns = array(
		$wpdb->esc_like( 'wpstream_' ) . '%',
		$wpdb->esc_like( 'wp_estate_' ) . '%',
		$wpdb->esc_like( 'wp_stream_' ) . '%',
		$wpdb->esc_like( '_transient_free_event_streamName_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_free_event_streamName_' ) . '%',
		$wpdb->esc_like( '_transient_paid_event_streamName_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_paid_event_streamName_' ) . '%',
		$wpdb->esc_like( '_transient_wpstream_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wpstream_' ) . '%',
		$wpdb->esc_like( '_transient_' ) . '%' . $wpdb->esc_like( '_api20_streamName' ),
		$wpdb->esc_like( '_transient_timeout_' ) . '%' . $wpdb->esc_like( '_api20_streamName' ),
		$wpdb->esc_like( '_transient_vod_decryption_key_index_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_vod_decryption_key_index_' ) . '%',
		$wpdb->esc_like( '_transient_paid_vod_key_index_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_paid_vod_key_index_' ) . '%',
		$wpdb->esc_like( '_transient_event_data_to_return_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_event_data_to_return_' ) . '%',
		$wpdb->esc_like( '_transient_server_id_to_return_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_server_id_to_return_' ) . '%',
	);
	$wpstream_option_conditions = implode(
		' OR ',
		array_fill( 0, count( $wpstream_option_patterns ), 'option_name LIKE %s' )
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE {$wpstream_option_conditions}",
			$wpstream_option_patterns
		)
	);

	// User-specific state is normally prefixed; the BuddyBoss integration also
	// shipped one legacy misspelled key which remains plugin-owned.
	$wpstream_usermeta_patterns = array(
		$wpdb->esc_like( 'wpstream_' ) . '%',
		$wpdb->esc_like( 'budyboss_selected_channel' ),
	);
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			$wpstream_usermeta_patterns
		)
	);

	// Authored content is valuable, so delete it only for this site's opt-in.
	if ( $wpstream_delete_content ) {
		$wpstream_content_ids = get_posts(
			array(
				'post_type'        => array( 'wpstream_product', 'wpstream_product_vod', 'wpstream_bundles' ),
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		foreach ( $wpstream_content_ids as $wpstream_content_id ) {
			wp_delete_post( (int) $wpstream_content_id, true );
		}
	}
};

if ( is_multisite() ) {
	$wpstream_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wpstream_site_ids as $wpstream_site_id ) {
		switch_to_blog( (int) $wpstream_site_id );
		try {
			$wpstream_cleanup_current_site();
		} finally {
			restore_current_blog();
		}
	}
} else {
	$wpstream_cleanup_current_site();
}

// Streamify creates only generated cache data here, so the complete directory
// may be removed. Files are traversed child-first to support nested cache paths.
$wpstream_cache_dir = WP_CONTENT_DIR . '/uploads/wpstreamify_cache';
if ( is_dir( $wpstream_cache_dir ) ) {
	$wpstream_cache_items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $wpstream_cache_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $wpstream_cache_items as $wpstream_cache_item ) {
		if ( $wpstream_cache_item->isDir() && ! $wpstream_cache_item->isLink() ) {
			rmdir( $wpstream_cache_item->getPathname() );
		} else {
			unlink( $wpstream_cache_item->getPathname() );
		}
	}

	rmdir( $wpstream_cache_dir );
}
