<?php
/** Plugin data/authorization adapter for the transportable broadcaster. */
defined( 'ABSPATH' ) || exit;

function wpstream_broadcaster_domain() {
    return (string) wp_parse_url( get_site_url(), PHP_URL_HOST );
}

/** Service identities are WordPress post IDs scoped to this site's host. */
function wpstream_broadcaster_is_channel( $id ) {
    $post = get_post( $id );
    // A channel saved in any status can broadcast; an auto-draft has never been saved.
    if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'draft', 'private', 'pending', 'future' ), true ) ) { return false; }
    if ( 'wpstream_product' === $post->post_type ) { return true; }
    return 'product' === $post->post_type && (
        has_term( 'live_stream', 'product_type', $id ) ||
        ( has_term( array( 'subscription', 'variable-subscription' ), 'product_type', $id ) && 'yes' === get_post_meta( $id, '_subscript_live_event', true ) )
    );
}

function wpstream_broadcaster_authorized( $id ) {
    global $wpstream_plugin;
    return wpstream_broadcaster_is_channel( $id ) && wpstream_can_manage_channel( get_current_user_id(), $id )
        && $wpstream_plugin->main->wpstream_check_user_can_stream();
}

/** Return only module contract fields; never cached publishing credentials. */
function wpstream_broadcaster_status( $id ) {
    global $wpstream_plugin;
    if ( ! wpstream_broadcaster_authorized( $id ) ) {
        return array( 'success' => false, 'error' => __( 'You are not allowed to broadcast this channel.', 'wpstream' ) );
    }
    $row = $wpstream_plugin->main->wpstream_live_connection->get_channel_broadcast_status( $id );
    if ( ! is_array( $row ) || empty( $row['success'] ) || empty( $row['status'] ) ) {
        return array( 'success' => false, 'error' => __( 'Unable to retrieve channel status. Please retry.', 'wpstream' ) );
    }
    $result = array_intersect_key( $row, array_flip( array( 'status', 'whipUrl', 'qos_url_data', 'broadcast_url', 'embedUrl', 'contentId', 'record' ) ) );
    $result['success'] = true;
    $result['contentId'] = (string) ( $result['contentId'] ?? '' );
    if ( 'stopped' === $row['status'] ) {
        $settings = $wpstream_plugin->main->channel_settings->read( $id );
        $result['record'] = (bool) $settings['options']['record'];
        // The WHIP and meter URLs belong to a live event and are stale once it
        // ends. The RTMP ingest (broadcast_url) belongs to the channel itself:
        // an external encoder points at it to bring the channel up, so the
        // Streaming software panel must be able to show it while stopped.
        $result['whipUrl'] = $result['qos_url_data'] = '';
    }
    return $result;
}

/** Strict validation shared by every authenticated module action. */
function wpstream_broadcaster_request_channel() {
    if ( ! is_user_logged_in() ) { wp_send_json( array( 'success' => false, 'connected' => false, 'error' => 'NOT_LOGGED_IN' ), 401 ); }
    if ( ! check_ajax_referer( WPSTREAM_PLUGIN_BC_NONCE, 'nonce', false ) ) {
        wp_send_json( array( 'success' => false, 'connected' => false, 'error' => __( 'Your session has expired. Reload this page and try again.', 'wpstream' ) ), 403 );
    }
    $id = filter_var( $_POST['channel_id'] ?? '', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
    $domain = $_POST['domain'] ?? null;
    if ( ! $id || ! is_string( $domain ) || wp_unslash( $domain ) !== wpstream_broadcaster_domain() ) {
        wp_send_json( array( 'success' => false, 'connected' => false, 'error' => 'INVALID_CHANNEL' ), 400 );
    }
    if ( ! wpstream_broadcaster_authorized( $id ) ) {
        wp_send_json( array( 'success' => false, 'connected' => false, 'error' => 'CHANNEL_NOT_ALLOWED' ), 403 );
    }
    return (int) $id;
}

function wpstream_broadcaster_ajax_status() {
    wp_send_json( wpstream_broadcaster_status( wpstream_broadcaster_request_channel() ) );
}
function wpstream_broadcaster_ajax_start() {
    global $wpstream_plugin;
    $id = wpstream_broadcaster_request_channel();
    wp_send_json( $wpstream_plugin->main->wpstream_live_connection->start_broadcast_channel( $id ) );
}
function wpstream_broadcaster_ajax_stop() {
    global $wpstream_plugin;
    $id = wpstream_broadcaster_request_channel();
    wp_send_json( $wpstream_plugin->main->wpstream_live_connection->stop_broadcast_channel( $id ) );
}
function wpstream_broadcaster_ajax_recording() {
    global $wpstream_plugin;
    $id = wpstream_broadcaster_request_channel();
    $record = $_POST['record'] ?? null;
    if ( ! in_array( $record, array( '0', '1' ), true ) ) { wp_send_json( array( 'success' => false, 'error' => 'INVALID_RECORDING' ), 400 ); }
    $status = wpstream_broadcaster_status( $id );
    if ( empty( $status['success'] ) || 'stopped' !== $status['status'] ) {
        wp_send_json( array( 'success' => false, 'error' => __( 'Stop the channel before changing recording.', 'wpstream' ) ), 409 );
    }
    $quota = $wpstream_plugin->main->quota_manager;
    if ( $quota->is_basic_streaming_mode( $quota->get_live_quota_data( 'broadcaster_recording' ) ) ) {
        wp_send_json( array( 'success' => false, 'error' => __( 'Recording is unavailable in Basic Streaming mode.', 'wpstream' ) ), 403 );
    }
    $settings = $wpstream_plugin->main->channel_settings;
    $result = $settings->apply( array( 'type' => 'save_recording', 'channel_id' => $id, 'record' => (int) $record ) );
    if ( empty( $result['success'] ) || empty( $result['baker_success'] ) ) {
        wp_send_json( array( 'success' => false, 'error' => __( 'Unable to save recording. Please retry.', 'wpstream' ) ), 502 );
    }
    wp_send_json( array( 'success' => true, 'record' => '1' === $record ) );
}
function wpstream_broadcaster_ajax_settings() {
    global $wpstream_plugin;
    $id = wpstream_broadcaster_request_channel();
    $status = wpstream_broadcaster_status( $id );
    if ( empty( $status['success'] ) || 'stopped' !== $status['status'] ) {
        wp_send_json( array( 'success' => false, 'error' => __( 'Stop the channel before changing settings.', 'wpstream' ) ), 409 );
    }
    $quota = $wpstream_plugin->main->quota_manager;
    if ( $quota->is_basic_streaming_mode( $quota->get_live_quota_data( 'broadcaster_settings' ) ) ) {
        wp_send_json( array( 'success' => false, 'error' => __( 'Channel settings are unavailable in Basic Streaming mode.', 'wpstream' ) ), 403 );
    }
    $options = $_POST['options'] ?? null;
    if ( ! is_array( $options ) || array_diff( array_keys( $options ), Wpstream_Channel_Settings::CHANNEL_KEYS ) ) {
        wp_send_json( array( 'success' => false, 'error' => 'INVALID_SETTINGS' ), 400 );
    }
    $settings = $wpstream_plugin->main->channel_settings;
    $result = $settings->apply( array( 'type' => 'save_customized', 'channel_id' => $id, 'options' => $options ) );
    if ( ! empty( $result['success'] ) ) {
        $result = $settings->apply( array( 'type' => 'select_mode', 'channel_id' => $id, 'mode' => 'customized' ) );
    }
    wp_send_json( array( 'success' => ! empty( $result['success'] ) && ! empty( $result['baker_success'] ), 'error' => $result['error'] ?? '', 'options' => $settings->read( $id )['options'] ) );
}
foreach ( array( 'status', 'start', 'stop', 'recording', 'settings' ) as $wpstream_bc_action ) {
    add_action( 'wp_ajax_wpstream_broadcaster_' . $wpstream_bc_action, 'wpstream_broadcaster_ajax_' . $wpstream_bc_action );
}
