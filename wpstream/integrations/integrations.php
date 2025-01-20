<?php
/**
 * Check and load integrations for plugins like BuddyPress or BuddyBoss.
 *
 * This function checks if the BuddyPress or BuddyBoss plugin is active
 * and, if so, includes the necessary integration file for compatibility.
 *
 * @return void
 */
function wpstream_check_integrations() {
    // Check if the BuddyPress or BuddyBoss class exists, indicating the plugin is active.
    if (class_exists('BuddyPress')) {
        // Include the integration file for BuddyBoss compatibility.
        require plugin_dir_path( __FILE__ ) . 'buddyboss/buddyboss.php';
    }
}




/**
 * Get live events for the logged-in user.
 *
 * This function retrieves the live event associated with the current logged-in user.
 * It first checks if the event data is cached in a transient to avoid redundant processing.
 * If no cached data is found, it retrieves the live event data using the wpstream plugin
 * and stores it in a transient for 10 minutes. Optionally, it can exit after fetching the data.
 *
 * @param string $with_exit Optional. Whether to exit after retrieving live events. Defaults to 'yes'.
 * @return mixed The live event data for the logged-in user.
 */
function wpestream_integrations_get_current_user_live_events($with_exit = 'yes') {
    // Check if the live event data is cached in a transient.
    $live_event_for_user = get_transient('wpstream_bb_get_live_event_for_user');
    global $wpstream_plugin;

    // If no cached data is found, fetch the live event data.
    if ($live_event_for_user === false) {
        $live_event_for_user = $wpstream_plugin->main->wpstream_live_connection->wpstream_get_live_event_for_user($with_exit);

        // Cache the live event data in a transient for 10 minutes.
        set_transient('wpstream_bb_get_live_event_for_user', $live_event_for_user, 600);
    }

    // Return the live event data for the logged-in user.
    return $live_event_for_user;
}


?>