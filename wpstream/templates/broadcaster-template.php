<?php
/** Plugin-owned broadcast workspace, also embedded in native editors. */
defined( 'ABSPATH' ) || exit;
$raw_channel = get_query_var( 'channel_id' ) ?: ( $_GET['channel_id'] ?? '' );
$channel_id = filter_var( $raw_channel, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
if ( ! $channel_id || ! wpstream_broadcaster_authorized( $channel_id ) ) {
    wp_die( esc_html__( 'You do not have permission to broadcast this channel.', 'wpstream' ), '', array( 'response' => 403 ) );
}
nocache_headers();
show_admin_bar( false );
$connected = Wpstream_Broadcaster_Host::connected();
// Existing standalone admin links should show the connection notice in wp-admin.
if ( ! $connected && current_user_can( 'administrator' ) && ( ! isset( $_GET['in-editor'] ) || '1' !== $_GET['in-editor'] ) ) {
    wp_safe_redirect( Wpstream_Broadcaster_Host::url( $channel_id ) );
    exit;
}
// A standalone workspace must not inherit the theme's button and layout rules.
add_action( 'wp_enqueue_scripts', function () {
    foreach ( wp_styles()->queue as $handle ) {
        if ( ! in_array( $handle, array( 'wpstream-brand', 'wpstream-plugin-broadcaster', 'wpstream-broadcaster-host', 'admin-bar', 'dashicons' ), true ) ) { wp_dequeue_style( $handle ); }
    }
    foreach ( wp_scripts()->queue as $handle ) {
        $src = wp_scripts()->registered[$handle]->src ?? '';
        if ( is_string( $src ) && false !== strpos( $src, '/themes/' ) ) { wp_dequeue_script( $handle ); }
    }
}, PHP_INT_MAX );
$is_onboarding = isset( $_GET['onboarding'] ) && '1' === $_GET['onboarding'];
$embedded = isset( $_GET['in-editor'] ) && '1' === $_GET['in-editor'];
// Render before the head so shortcode/widget-style asset discovery still prints CSS.
ob_start();
do_action( 'wpstream_before_broadcaster_page', (int) $channel_id, $is_onboarding );
Wpstream_Broadcaster_Host::workspace( $channel_id );
$workspace = ob_get_clean();
wp_enqueue_style( 'wpstream-broadcaster-host', WPSTREAM_PLUGIN_DIR_URL . 'public/css/wpstream-broadcaster-host.css', $connected ? array( 'wpstream-brand', 'wpstream-plugin-broadcaster' ) : array( 'wpstream-brand' ), filemtime( WPSTREAM_PLUGIN_PATH . 'public/css/wpstream-broadcaster-host.css' ) );
if ( $connected ) {
wp_enqueue_script( 'wpstream-broadcaster-host', WPSTREAM_PLUGIN_DIR_URL . 'public/js/wpstream-broadcaster-host.js', $connected ? array( 'wpstream-plugin-broadcaster' ) : array(), filemtime( WPSTREAM_PLUGIN_PATH . 'public/js/wpstream-broadcaster-host.js' ), true );
wp_localize_script( 'wpstream-broadcaster-host', 'wpstreamBroadcastHost', array(
    'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( WPSTREAM_PLUGIN_BC_NONCE ), 'channelId' => $channel_id,
    'copied' => __( 'Link copied.', 'wpstream' ), 'copyManually' => __( 'Copy the selected link.', 'wpstream' ),
    'domain' => wpstream_broadcaster_domain(), 'confirmStop' => __( 'Stop this channel and disconnect all broadcasters?', 'wpstream' ),
    'stopping' => __( 'Stopping channel?', 'wpstream' ), 'stopped' => __( 'Channel stopped.', 'wpstream' ),
    'settingsLocked' => __( 'Stop the channel before changing settings.', 'wpstream' ), 'statusUnavailable' => __( "Couldn't read this channel's status, so its settings can't be changed right now.", 'wpstream' ), 'saved' => __( 'Channel settings saved.', 'wpstream' ),
    'error' => __( 'Unable to confirm channel status. Please retry.', 'wpstream' ),
    'statsLoading' => esc_html__( 'Loading…', 'wpstream' ), 'statsNa' => esc_html__( 'NA', 'wpstream' ),
    'createUrl' => current_user_can( 'edit_posts' ) ? admin_url( 'admin.php?page=wpstream_live_channels&library_new=channel' ) : '',
) );
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php esc_html_e( 'WpStream Broadcaster', 'wpstream' ); ?></title><?php wp_head(); ?></head>
<body class="wpstream-plugin-broadcast-page<?php echo $embedded ? ' wpstream-broadcast-embedded' : ''; ?>">
<?php wp_body_open(); ?>

<?php echo $workspace; // Escaped by the shared renderer.
do_action( 'wpstream_after_broadcaster_page', (int) $channel_id, $is_onboarding ); wp_footer(); ?>
</body></html>
