<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
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
// NOTE: No cleanup logic is implemented below; uninstalling the plugin performs
// no data removal at this time (this remains the boilerplate skeleton).
