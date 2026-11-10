<?php
/** The editors' video panel: the shared video source component under a toolbar naming the chosen video. */
defined( 'ABSPATH' ) || exit;
class Wpstream_Classic_Video_Creation {
    public static function register() {
        add_filter( 'woocommerce_product_type_query', function ( $type, $id ) {
            if ( 'post-new.php' === ( $GLOBALS['pagenow'] ?? '' ) && 'auto-draft' === get_post_status( $id ) && current_user_can( 'edit_post', $id ) && ( 'video_on_demand' === ( $_GET['product_type'] ?? '' ) || ! empty( $_GET['new_video_name'] ) ) ) { return 'video_on_demand'; }
            return $type;
        }, 10, 2 );
        add_action( 'init', array( __CLASS__, 'register_meta' ), 99 );
        add_action( 'enqueue_block_assets', array( __CLASS__, 'block_styles' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 99 );
        add_action( 'edit_form_after_title', array( __CLASS__, 'classic_panel' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'remove_old_panel' ), 99 );
        add_action( 'rest_after_insert_wpstream_product_vod', array( __CLASS__, 'after_rest_save' ), 10, 2 );
        add_filter( 'rest_pre_insert_wpstream_product_vod', array( __CLASS__, 'before_rest_save' ), 10, 2 );
        add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_source' ), 30 );
    }
    /** Save a native product source only for the authorized video panel submission. */
    public static function save_product_source( $id ) {
        $nonce = $_POST['wpstream_native_video_nonce'] ?? '';
        if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wpstream_native_video' ) || ! current_user_can( 'edit_post', $id ) ) { return; }
        $product = wc_get_product( $id );
        if ( ! $product || ! ( $product->is_type( 'video_on_demand' ) || ( $product->is_type( 'subscription' ) && 'no' === $product->get_meta( '_subscript_live_event' ) ) ) ) { return; }
        $type = $_POST['wpstream_product_type'] ?? '';
        if ( ! in_array( $type, array( '2', '3' ), true ) ) { return; }
        // Captions follow the video and do not change its source, so they save before the source checks.
        if ( isset( $_POST['wpstream_closed_captions_file'] ) && is_string( $_POST['wpstream_closed_captions_file'] ) ) {
            update_post_meta( $id, 'wpstream_closed_captions_file', esc_url_raw( wp_unslash( $_POST['wpstream_closed_captions_file'] ), array( 'http', 'https' ) ) );
        }
        $external = $_POST['wpstream_free_video_external'] ?? '';
        if ( '3' === $type && ( ! is_string( $external ) || ! filter_var( $external, FILTER_VALIDATE_URL ) || ! in_array( strtolower( wp_parse_url( $external, PHP_URL_SCHEME ) ?? '' ), array( 'http', 'https' ), true ) ) ) { return; }
        // Retire the old source before deleting its cached key lookup identity.
        global $wpstream_plugin;
        $wpstream_plugin->drm_key_delivery->invalidate_vod_lookup( $id );
        foreach ( array( 'hlsDecryptionKey', 'hlsDecryptionKeyIndex', 'wpstream_vod_video_data', 'wpstream_vod_embed_key', 'wpstream_vod_embed_url' ) as $cached ) { delete_post_meta( $id, $cached ); }
        update_post_meta( $id, 'wpstream_product_type', (int) $type );
        if ( '3' === $type ) {
            update_post_meta( $id, '_movie_url_external', esc_url_raw( $external, array( 'http', 'https' ) ) );
            delete_post_meta( $id, '_movie_url' );
        } else {
            delete_post_meta( $id, '_movie_url_external' );
        }
    }
    public static function register_meta() {
        add_post_type_support( 'wpstream_product_vod', 'custom-fields' );
        foreach ( array( 'wpstream_product_type', 'wpstream_free_video', 'wpstream_free_video_external', 'wpstream_closed_captions_file' ) as $key ) {
            register_post_meta( 'wpstream_product_vod', $key, array(
                'single' => true, 'type' => 'string', 'show_in_rest' => true,
                'sanitize_callback' => in_array( $key, array( 'wpstream_free_video_external', 'wpstream_closed_captions_file' ), true ) ? 'esc_url_raw' : 'sanitize_text_field',
                'auth_callback' => function ( $allowed, $key, $id ) { return current_user_can( 'edit_post', $id ); },
            ) );
        }
        register_block_type( 'wpstream/video-source', array( 'render_callback' => '__return_empty_string' ) );
    }
    public static function before_rest_save( $post, $request ) {
        $meta = $request->get_param( 'meta' );
        $id = (int) $request['id'];
        if ( $id && is_array( $meta ) ) {
            foreach ( array( 'wpstream_product_type', 'wpstream_free_video', 'wpstream_free_video_external' ) as $key ) {
                if ( isset( $meta[$key] ) && (string) $meta[$key] !== (string) get_post_meta( $id, $key, true ) ) {
                    global $wpstream_plugin;
                    $wpstream_plugin->drm_key_delivery->invalidate_vod_lookup( $id );
                    foreach ( array( 'hlsDecryptionKey', 'hlsDecryptionKeyIndex', 'wpstream_vod_video_data', 'wpstream_vod_embed_key', 'wpstream_vod_embed_url' ) as $cached ) { delete_post_meta( $id, $cached ); }
                    break;
                }
            }
        }
        return $post;
    }
    public static function after_rest_save( $post, $request ) {
        $meta = $request->get_param( 'meta' );
        if ( ! is_array( $meta ) || ! isset( $meta['wpstream_product_type'] ) ) { return; }
        delete_post_meta( $post->ID, '3' === $meta['wpstream_product_type'] ? 'wpstream_free_video' : 'wpstream_free_video_external' );
    }
    public static function remove_old_panel() {
        remove_meta_box( 'add_wpstream_product_metaboxes-sectionid', 'wpstream_product_vod', 'normal' );
    }
    /** Load the shared brand and source-panel styles inside the block editor canvas. */
    public static function block_styles() {
        if ( is_admin() ) {
            // WordPress collects the canvas styles before the admin styles exist, and drops any style whose dependency is unknown.
            wp_register_style( 'wpstream-brand', WPSTREAM_PLUGIN_DIR_URL . 'public/css/wpstream-brand.css', array(), filemtime( WPSTREAM_PLUGIN_PATH . 'public/css/wpstream-brand.css' ) );
            wp_enqueue_style( 'wpstream-native-video', plugin_dir_url( __FILE__ ) . 'css/wpstream-native-video.css', array( 'dashicons', 'wpstream-brand' ), filemtime( __DIR__ . '/css/wpstream-native-video.css' ) );
        }
    }
    /** Load source editing assets only on supported WordPress product editors. */
    public static function assets() {
        global $post;
        $screen = get_current_screen();
        if ( ! $screen || 'post' !== $screen->base || ! $post || ! in_array( $post->post_type, array( 'wpstream_product_vod', 'product' ), true ) ) { return; }
        wp_enqueue_media();
        wp_enqueue_style( 'wpstream-native-video', plugin_dir_url( __FILE__ ) . 'css/wpstream-native-video.css', array( 'dashicons', 'wpstream-brand' ), filemtime( __DIR__ . '/css/wpstream-native-video.css' ) );
        wp_enqueue_script( 'wpstream-video-source', plugin_dir_url( __FILE__ ) . 'js/wpstream-video-source.js', array( 'jquery' ), filemtime( __DIR__ . '/js/wpstream-video-source.js' ), true );
        wp_enqueue_script( 'wpstream-native-video', plugin_dir_url( __FILE__ ) . 'js/wpstream-native-video.js', array( 'wpstream-video-source', 'jquery', 'wp-blocks', 'wp-element', 'wp-data', 'wp-block-editor', 'wp-dom-ready', 'wp-plugins', 'wpstream-admin-control' ), filemtime( __DIR__ . '/js/wpstream-native-video.js' ), true );
        wp_localize_script( 'wpstream-native-video', 'wpstreamNativeVideo', array( 'product' => 'product' === $post->post_type, 'block' => use_block_editor_for_post( $post ), 'html' => self::panel( $post ), 'preset' => isset( $_GET['new_video_name'] ) && is_scalar( $_GET['new_video_name'] ) ? sanitize_text_field( wp_unslash( $_GET['new_video_name'] ) ) : '' ) );
    }
    public static function classic_panel( $post ) {
        if ( in_array( $post->post_type, array( 'wpstream_product_vod', 'product' ), true ) && ! use_block_editor_for_post( $post ) ) { echo self::panel( $post ); }
    }
    public static function panel( $post ) {
        global $wpstream_plugin;
        // Both new and existing video editors require a connected account before
        // requesting recordings/quota or offering the source and upload controls.
        $product_type = 'product' === $post->post_type ? wpstream_get_product_type_slug( $post->ID ) : '';
        $video_route = 'wpstream_product_vod' === $post->post_type || 'video_on_demand' === $product_type ||
            ( 'subscription' === $product_type && 'no' === get_post_meta( $post->ID, '_subscript_live_event', true ) ) ||
            'video_on_demand' === ( $_GET['product_type'] ?? '' ) || ! empty( $_GET['new_video_name'] );
        if ( $video_route && ! $wpstream_plugin->wpstream_live_connection->is_connected() ) {
            return '<p class="notice notice-warning wpstream-video-signin-required">' . esc_html__( 'Sign in to your WpStream account to manage this video.', 'wpstream' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wpstream_onboard' ) ) . '">' . esc_html__( 'Sign in to WpStream', 'wpstream' ) . '</a></p>';
        }
        $product = 'product' === $post->post_type;
        $recordings = $wpstream_plugin->wpstream_live_connection->wpstream_get_videos();
        $recordings = is_array( $recordings ) ? $recordings : array();
        $recording = (string) get_post_meta( $post->ID, $product ? '_movie_url' : 'wpstream_free_video', true );
        if ( ! empty( $_GET['new_video_name'] ) && is_scalar( $_GET['new_video_name'] ) ) { $recording = sanitize_text_field( wp_unslash( $_GET['new_video_name'] ) ); }
        if ( $recording ) { $recordings[$recording] = $recording; }
        $type = (string) get_post_meta( $post->ID, 'wpstream_product_type', true );
        $quota = $wpstream_plugin->quota_manager->get_live_quota_data( 'admin_video_editor' );
        ob_start(); ?>
        <section class="editor-video-panel" aria-label="Video source and preview" <?php if ( $product ) { echo 'hidden'; } ?>>
            <?php echo Wpstream_Commerce_Requirements::render(); ?>
            <?php if ( $product ) { wp_nonce_field( 'wpstream_native_video', 'wpstream_native_video_nonce' ); } ?>
            <div class="editor-video-toolbar"><strong data-video-filename>Choose a video</strong></div>
            <div class="editor-video-picker">
                <?php
                // A saved source opens on its own controls; products keep their recording in the WooCommerce field.
                echo Wpstream_Video_Source_Panel::render( array(
                    'mode'           => '3' === $type ? 'external' : ( $recording ? 'recording' : 'upload' ),
                    'recording'      => $recording,
                    'recordings'     => $recordings,
                    'external'       => get_post_meta( $post->ID, $product ? '_movie_url_external' : 'wpstream_free_video_external', true ),
                    'recording_name' => $product ? '' : 'wpstream_free_video',
                    'external_name'  => 'wpstream_free_video_external',
                    'captions'       => get_post_meta( $post->ID, 'wpstream_closed_captions_file', true ),
                    'captions_name'  => 'wpstream_closed_captions_file',
                ) );
                ?>
                <input type="hidden" name="wpstream_product_type" value="<?php echo '3' === $type ? '3' : '2'; ?>">
            </div>
            <?php foreach ( array( 'wpstream_storage_hours' => 'available_storage_hours', 'wpstream_storage' => 'available_storage_mb', 'wpstream_band' => 'available_data_mb' ) as $input => $key ) { ?><input type="hidden" id="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( $quota[$key] ?? 0 ); ?>"><?php } ?>
        </section>
        <?php return ob_get_clean();
    }
}
