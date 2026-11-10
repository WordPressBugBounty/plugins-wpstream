<?php
/** Plugin page chrome and editor mounts. No theme dependencies. */
defined( 'ABSPATH' ) || exit;
class Wpstream_Broadcaster_Host {
    public static function channels() {
        $posts = get_posts( array( 'post_type' => array( 'wpstream_product', 'product' ), 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
        return array_values( array_filter( $posts, function ( $post ) { return wpstream_broadcaster_authorized( $post->ID ); } ) );
    }
    /** Enter an existing channel; create the first free channel only on an explicit POST. */
    public static function open_live() {
        if ( ! current_user_can( 'administrator' ) ) { wp_die( esc_html__( 'Only for administrators', 'wpstream' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( 'wpstream_open_live' );
        if ( ! self::connected() ) { wp_safe_redirect( admin_url( 'admin.php?page=wpstream_onboard' ) ); exit; }
        $channels = self::channels();
        if ( ! $channels ) {
            // Atomic lock prevents double clicks from creating two first channels.
            $lock = 'wpstream_first_channel_lock';
            if ( ! add_option( $lock, time(), '', false ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=wpstream_live_channels' ) ); exit;
            }
            try {
                $channels = self::channels();
                if ( ! $channels ) {
                    global $wpstream_plugin;
                    $result = $wpstream_plugin->main->streaming_content_creation->create( array( 'actor_id' => get_current_user_id(), 'owner_id' => get_current_user_id(), 'kind' => 'live_channel', 'access' => 'free', 'title' => __( 'My first channel', 'wpstream' ) ) );
                    if ( ! empty( $result['success'] ) ) { $channels = array( get_post( $result['content_id'] ) ); }
                }
            } finally { delete_option( $lock ); }
        }
        wp_safe_redirect( $channels ? self::url( $channels[0]->ID ) : admin_url( 'admin.php?page=wpstream_live_channels&library_new=channel' ) );
        exit;
    }
    public static function connected() {
        global $wpstream_plugin;
        return $wpstream_plugin->main->wpstream_live_connection->is_connected();
    }
    public static function connection_notice() {
        echo '<section class="wpstream-broadcast-connection" role="status"><h1>' . esc_html__( 'Connect to WpStream to broadcast', 'wpstream' ) . '</h1><p>' . esc_html__( 'You are not connected to WpStream. Connect your account before opening the broadcasting tool.', 'wpstream' ) . '</p>';
        if ( current_user_can( 'administrator' ) ) {
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=wpstream_onboard' ) ) . '" target="_top">' . esc_html__( 'Connect account', 'wpstream' ) . '</a>';
        } else {
            echo '<p>' . esc_html__( 'Please ask the site administrator to connect the WpStream account.', 'wpstream' ) . '</p>';
        }
        echo '</section>';
    }
    public static function url( $id, $embedded = false ) {
        if ( ! $embedded && current_user_can( 'administrator' ) ) {
            return add_query_arg( array( 'page' => 'wpstream_broadcast', 'channel_id' => (int) $id ), admin_url( 'admin.php' ) );
        }
        return add_query_arg( array( 'channel_id' => (int) $id, 'wpstream_broadcaster' => 1, 'in-editor' => $embedded ? 1 : 0 ), home_url( '/' ) );
    }
    public static function admin_page() {
        global $wpstream_plugin;
        $id = filter_var( $_GET['channel_id'] ?? '', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
        if ( ! current_user_can( 'administrator' ) || ! $id || ! wpstream_broadcaster_authorized( $id ) ) {
            wp_die( esc_html__( 'You do not have permission to broadcast this channel.', 'wpstream' ), '', array( 'response' => 403 ) );
        }
        require_once WPSTREAM_PLUGIN_PATH . 'admin/class-wpstream-admin-header.php';
        echo '<div class="wrap wpstream-library">';
        Wpstream_Admin_Header::render( $wpstream_plugin->main );
        if ( self::connected() ) {
            echo '<div class="library-heading"><h1>' . esc_html__( 'Live channels', 'wpstream' ) . '</h1></div><hr class="wp-header-end">';
            echo '<div class="wpstream-channel-picker"><label for="wpstream-workspace-channel">' . esc_html__( 'Channel', 'wpstream' ) . '</label><select id="wpstream-workspace-channel">';
            foreach ( self::channels() as $channel ) { echo '<option value="' . esc_url( self::url( $channel->ID ) ) . '"' . selected( $id, $channel->ID, false ) . '>' . esc_html( $channel->post_title ) . '</option>'; }
            echo '</select><a class="button" href="' . esc_url( admin_url( 'admin.php?page=wpstream_live_channels&library_new=channel' ) ) . '">' . esc_html__( 'New channel', 'wpstream' ) . '</a></div>';
            self::frame( $id );
        } else {
            self::connection_notice();
        }
        echo '</div>';
    }
    public static function frame( $id ) {
        static $mounted = false;
        if ( 'auto-draft' === get_post_status( $id ) ) {
            echo '<p>' . esc_html__( 'Save this channel to use its broadcasting tools.', 'wpstream' ) . '</p>';
            return;
        }
        if ( ! wpstream_broadcaster_authorized( $id ) ) {
            echo '<p>' . esc_html__( 'Publish a live channel and sign in with streaming permission to broadcast.', 'wpstream' ) . '</p>';
            return;
        }
        if ( ! self::connected() ) {
            self::connection_notice();
            return;
        }
        if ( $mounted ) {
            echo '<p><a href="' . esc_url( self::url( $id ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open broadcaster', 'wpstream' ) . '</a></p>';
            return;
        }
        $mounted = true;
        // The editor iframe uses the same class-based sizing as the standalone host.
        wp_enqueue_style( 'wpstream-broadcaster-host', WPSTREAM_PLUGIN_DIR_URL . 'public/css/wpstream-broadcaster-host.css', array( 'wpstream-brand' ), filemtime( WPSTREAM_PLUGIN_PATH . 'public/css/wpstream-broadcaster-host.css' ) );
        wp_enqueue_script( 'wpstream-broadcaster-frames', WPSTREAM_PLUGIN_DIR_URL . 'admin/js/wpstream-broadcaster-frames.js', array(), filemtime( WPSTREAM_PLUGIN_PATH . 'admin/js/wpstream-broadcaster-frames.js' ), true );
        // The message covers the frame until the tool has loaded. The handler sits on the iframe because this markup also runs inside the block editor canvas, where no other plugin script is available.
        echo '<section class="wpstream-broadcast-host"><div class="wpstream-broadcast-loading" role="status">' . esc_html__( 'Loading the broadcasting tool…', 'wpstream' ) . '</div>';
        echo '<iframe class="wpstream-broadcast-frame" title="' . esc_attr__( 'Channel broadcasting workspace', 'wpstream' ) . '" src="' . esc_url( self::url( $id, true ) ) . '" allow="camera; microphone; display-capture; fullscreen" loading="eager" onload="this.previousElementSibling.hidden=true"></iframe></section>';
    }
    public static function args( $id ) {
        $row = array( 'id' => (int) $id, 'title' => get_the_title( $id ), 'domain' => wpstream_broadcaster_domain() );
        return array( 'streams' => array( $row ), 'default_channel' => $row, 'lock_channel' => true,
            'asset_url' => WPSTREAM_PLUGIN_DIR_URL . 'broadcaster/', 'asset_dir' => WPSTREAM_PLUGIN_PATH . 'broadcaster/',
            'actions' => array( 'check_event_status' => 'wpstream_broadcaster_status', 'give_me_live_uri' => 'wpstream_broadcaster_start', 'save_recording' => 'wpstream_broadcaster_recording' ) );
    }
    public static function settings( $id ) {
        global $wpstream_plugin;
        $state = $wpstream_plugin->main->channel_settings->read( $id );
        $labels = array( 'record' => __( 'Record broadcast', 'wpstream' ), 'view_count' => __( 'Show viewer count', 'wpstream' ), 'domain_lock' => __( 'Restrict playback to this website', 'wpstream' ), 'autoplay' => __( 'Autoplay', 'wpstream' ), 'mute' => __( 'Mute by default', 'wpstream' ), 'low_latency' => __( 'Low latency', 'wpstream' ), 'adaptive_bitrate' => __( 'Adaptive bitrate', 'wpstream' ), 'encrypt' => __( 'Encrypt stream', 'wpstream' ) );
        echo '<section data-workspace-panel="settings" hidden><form id="wpstream-broadcast-settings"><fieldset disabled><legend>' . esc_html__( 'Channel settings', 'wpstream' ) . '</legend>';
        foreach ( $labels as $key => $label ) {
            echo '<label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1" ' . checked( ! empty( $state['options'][$key] ), true, false ) . '> ' . esc_html( $label ) . '</label>';
        }
        echo '<button type="submit">' . esc_html__( 'Save channel settings', 'wpstream' ) . '</button></fieldset><p data-settings-status role="status">' . esc_html__( 'Checking channel status. Settings can only be changed while the channel is stopped.', 'wpstream' ) . '</p></form></section>';
    }
    public static function workspace( $id ) {
        if ( ! self::connected() ) {
            self::connection_notice();
            return;
        }
        echo '<main class="wpstream-broadcast-workspace" data-channel="' . esc_attr( $id ) . '">';
        echo '<h1>' . esc_html( get_the_title( $id ) ) . '</h1>';
        do_action( 'wpstream_broadcaster_settings_panel_before', (int) $id );
        echo '<nav class="wpstream-broadcast-tabs" aria-label="' . esc_attr__( 'Channel workspace', 'wpstream' ) . '"><button type="button" data-workspace-tab="broadcast" aria-pressed="true">' . esc_html__( 'Broadcast', 'wpstream' ) . '</button><button type="button" data-workspace-tab="statistics" aria-pressed="false">' . esc_html__( 'Statistics', 'wpstream' ) . '</button><button type="button" data-workspace-tab="settings" aria-pressed="false">' . esc_html__( 'Channel settings', 'wpstream' ) . '</button></nav>';
        echo '<section data-workspace-panel="broadcast">';
        wpstream_plugin_render_broadcaster( self::args( $id ) );
        echo '</section>';
        echo '<section data-workspace-panel="statistics" hidden><div class="wpstream-host-stats">';
        foreach ( array( 'viewers' => __( 'Viewers now', 'wpstream' ), 'peak' => __( 'Peak viewers', 'wpstream' ), 'duration' => __( 'Duration', 'wpstream' ), 'health' => __( 'Health', 'wpstream' ) ) as $key => $label ) {
            echo '<div><span>' . esc_html( $label ) . '</span><strong data-host-stat="' . esc_attr( $key ) . '">' . esc_html__( 'NA', 'wpstream' ) . '</strong></div>';
        }
        echo '</div><div class="wpstream-host-charts">';
        foreach ( array( 'viewers' => __( 'Audience', 'wpstream' ), 'bitrate' => __( 'Broadcast Performance', 'wpstream' ) ) as $metric => $label ) {
            echo '<section class="wpstream-host-chart-card"><h2>' . esc_html( $label ) . '</h2><div class="wpstream-chart-ranges" role="group" aria-label="' . esc_attr( $label ) . '"><button type="button" data-chart-range="300" data-chart="' . esc_attr( $metric ) . '" aria-pressed="true">' . esc_html__( 'Last 5 min', 'wpstream' ) . '</button><button type="button" data-chart-range="18000" data-chart="' . esc_attr( $metric ) . '" aria-pressed="false">' . esc_html__( 'Last 5 hours', 'wpstream' ) . '</button></div><svg viewBox="0 0 600 180" role="img" aria-label="' . esc_attr( $label ) . '" data-host-chart="' . esc_attr( $metric ) . '"></svg><p data-chart-empty="' . esc_attr( $metric ) . '">' . esc_html__( 'No readings available yet.', 'wpstream' ) . '</p></section>';
        }
        echo '</div><details class="wpstream-host-technical"><summary>' . esc_html__( 'Technical details', 'wpstream' ) . '</summary><dl><dt>' . esc_html__( 'Average bitrate', 'wpstream' ) . '</dt><dd data-host-stat="bitrate">' . esc_html__( 'NA', 'wpstream' ) . '</dd><dt>' . esc_html__( 'Stream source', 'wpstream' ) . '</dt><dd data-host-source>' . esc_html__( 'NA', 'wpstream' ) . '</dd></dl></details></section>';
        self::settings( $id );
        echo '<section class="wpstream-host-share"><h2>' . esc_html__( 'Your channel page', 'wpstream' ) . '</h2><input type="url" readonly aria-label="' . esc_attr__( 'Channel page URL', 'wpstream' ) . '" value="' . esc_url( get_permalink( $id ) ) . '"><a href="' . esc_url( get_permalink( $id ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open page', 'wpstream' ) . '</a><button type="button" data-copy-channel>' . esc_html__( 'Copy link', 'wpstream' ) . '</button><span data-copy-status role="status"></span></section>';
        // Hidden until the status check reports a running channel; the result message stays outside so it outlives the button.
        echo '<div data-backend-stop hidden><p><button type="button" id="wpstream-backend-stop">' . esc_html__( 'Stop channel', 'wpstream' ) . '</button></p>';
        echo '<p>' . esc_html__( 'End Broadcast stops your browser transmission. Stop channel also disconnects external encoders and lets you change channel settings.', 'wpstream' ) . '</p></div><p id="wpstream-backend-status" role="status"></p>';
        do_action( 'wpstream_broadcaster_settings_panel_after', (int) $id );
        echo '</main>';
    }
}
add_action( 'admin_menu', function () {
    add_submenu_page( null, __( 'Broadcast', 'wpstream' ), __( 'Broadcast', 'wpstream' ), 'administrator', 'wpstream_broadcast', array( 'Wpstream_Broadcaster_Host', 'admin_page' ) );
} );
add_action( 'admin_enqueue_scripts', function () {
    if ( ( $_GET['page'] ?? '' ) !== 'wpstream_broadcast' ) { return; }
    wp_enqueue_style( 'wpstream-content-library', WPSTREAM_PLUGIN_DIR_URL . 'admin/css/wpstream-content-library.css', array(), filemtime( WPSTREAM_PLUGIN_PATH . 'admin/css/wpstream-content-library.css' ) );
} );
// Preserve existing bookmarked library Broadcast URLs without rendering the list.
add_action( 'admin_init', function () {
    if ( ! isset( $_GET['page'], $_GET['library_broadcast'] ) || 'wpstream_live_channels' !== $_GET['page'] ) { return; }
    $id = filter_var( $_GET['library_broadcast'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
    if ( ! current_user_can( 'administrator' ) || ! $id || ! wpstream_broadcaster_authorized( $id ) ) {
        wp_die( esc_html__( 'You do not have permission to broadcast this channel.', 'wpstream' ), '', array( 'response' => 403 ) );
    }
    wp_safe_redirect( Wpstream_Broadcaster_Host::url( $id ) );
    exit;
} );
add_filter( 'template_include', function ( $template ) {
    if ( isset( $_GET['wpstream_broadcaster'] ) && '1' === $_GET['wpstream_broadcaster'] ) {
        return WPSTREAM_PLUGIN_PATH . 'templates/broadcaster-template.php';
    }
    return $template;
}, 99 );

add_action( 'admin_post_wpstream_open_live', array( 'Wpstream_Broadcaster_Host', 'open_live' ) );
