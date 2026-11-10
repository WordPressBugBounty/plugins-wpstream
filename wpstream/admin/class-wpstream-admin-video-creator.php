<?php
/** Admin video publishing, using the shared content creation and upload modules. */
defined( 'ABSPATH' ) || exit;

class Wpstream_Admin_Video_Creator {
    private $main;
    private $is_channel;

    public function __construct( $main, $kind = 'vod' ) { $this->main = $main; $this->is_channel = 'live_channel' === $kind; }

    private function value( $key, $default = '' ) {
        return isset( $_POST[$key] ) && is_scalar( $_POST[$key] ) ? sanitize_text_field( wp_unslash( $_POST[$key] ) ) : $default;
    }

    private function preset( $key, $default = '' ) {
        return isset( $_GET[$key] ) && is_scalar( $_GET[$key] ) ? sanitize_text_field( wp_unslash( $_GET[$key] ) ) : $default;
    }

    /** Every access type the form shows, whether or not this site can sell it yet. */
    private function access_labels() {
        return array(
            'free' => array( __( 'Free to watch', 'wpstream' ), __( 'No purchase required', 'wpstream' ) ),
            'paid' => array( __( 'Pay per view', 'wpstream' ), __( 'Viewers purchase access', 'wpstream' ) ),
            'subscription' => array( __( 'Subscription', 'wpstream' ), __( 'Active subscribers can watch', 'wpstream' ) ),
        );
    }

    /** The access types this site can actually sell. */
    public function access_choices() {
        $choices = $this->access_labels();
        if ( ! class_exists( 'WooCommerce' ) ) { unset( $choices['paid'], $choices['subscription'] ); }
        elseif ( ! class_exists( 'WC_Subscriptions' ) || ! class_exists( 'WC_Product_Subscription' ) || ! function_exists( 'wcs_user_has_subscription' ) ) { unset( $choices['subscription'] ); }
        return $choices;
    }

    /** A completed form token belongs to the administrator who submitted it. */
    private function published_id( $token ) {
        if ( '' === $token || ! current_user_can( 'administrator' ) ) { return 0; }
        $id = (int) get_transient( 'wpstream_published_' . md5( get_current_user_id() . '|' . $token ) );
        return $id > 0 && get_post( $id ) ? $id : 0;
    }

    /** Validate the submitted access/source, then publish fully initialized content. */
    public function publish() {
        if ( 'publish' !== $this->value( 'video_action' ) ) { return null; }
        if ( ! current_user_can( 'administrator' ) || ! wp_verify_nonce( $this->value( 'video_nonce' ), 'wpstream_publish_video' ) ) {
            return new WP_Error( 'forbidden', __( 'Your session expired. Reload this page and try again.', 'wpstream' ) );
        }
        // Repeated submissions answer with the item this form already created.
        $token = $this->value( 'video_token' );
        $token_key = 'wpstream_published_' . md5( get_current_user_id() . '|' . $token );
        $published = $this->published_id( $token );
        if ( $published ) { return $published; }
        $access = $this->value( 'video_access', 'free' );
        if ( ! isset( $this->access_choices()[$access] ) ) { return new WP_Error( 'unavailable', __( 'This viewing access is unavailable.', 'wpstream' ) ); }
        if ( ! $this->main->wpstream_live_connection->is_connected() ) {
            return new WP_Error( 'not_connected', __( 'Sign in to your WpStream account to create content.', 'wpstream' ) );
        }
        if ( 'paid' === $access && 1 === (int) get_option( 'wpstream_global_sub' ) ) {
            return new WP_Error( 'global_subscription', __( 'Individual pay-per-view purchases are disabled in Global Subscription Mode.', 'wpstream' ) );
        }
        $request = array( 'actor_id' => get_current_user_id(), 'owner_id' => get_current_user_id(), 'kind' => $this->is_channel ? 'live_channel' : 'vod', 'access' => $access, 'title' => $this->value( 'video_title' ), 'source_recording' => $this->value( 'video_source' ) );
        if ( $this->is_channel ) { unset( $request['source_recording'] ); }
        if ( ! $this->is_channel && 'external' === $this->value( 'video_source_mode' ) ) {
            unset( $request['source_recording'] );
            $request['external_source'] = $this->value( 'video_external_url' );
        }
        if ( 'paid' === $access ) { $request['price'] = $this->value( 'video_price' ); }
        $subscription = 'subscription' === $access;
        $global = $subscription && 1 === (int) get_option( 'wpstream_global_sub' );
        $existing_plan = $global || 'existing' === $this->value( 'video_subscription_mode' );
        $create_plan = $subscription && ! $global && 'create' === $this->value( 'video_subscription_mode' );
        if ( $create_plan && '' === trim( $this->value( 'video_subscription_name' ) ) ) {
            return new WP_Error( 'plan_name', __( 'Enter a subscription name.', 'wpstream' ) );
        }
        if ( $subscription ) {
            $interval = $this->value( 'video_interval', '1' );
            $period = $this->value( 'video_period', 'month' );
            if ( ! in_array( $interval, array( '1', '2', '3', '6' ), true ) || ! in_array( $period, array( 'week', 'month', 'year' ), true ) ) {
                return new WP_Error( 'billing', __( 'Choose a valid billing period.', 'wpstream' ) );
            }
            $request['access'] = 'paid';
            $request['price'] = $this->value( 'video_subscription_price' );
            if ( $existing_plan ) {
                $plan_id = absint( $this->value( 'video_plan' ) );
                $plan = wc_get_product( $plan_id );
                if ( ! $plan || ! $plan->is_type( 'subscription' ) || 'publish' !== $plan->get_status() || ! current_user_can( 'edit_post', $plan_id ) ) {
                    return new WP_Error( 'plan', __( 'Choose an existing subscription.', 'wpstream' ) );
                }
                $request['price'] = '0';
            }
        }
        // Subscription metadata must be complete before the video is published.
        $stage = function ( $args, $command ) use ( $request ) {
            if ( $command === $request ) { $args['post_status'] = 'draft'; }
            return $args;
        };
        if ( $subscription ) { add_filter( 'wpstream_streaming_content_post_args', $stage, 100, 2 ); }
        try { $result = $this->main->streaming_content_creation->create( $request ); }
        finally { remove_filter( 'wpstream_streaming_content_post_args', $stage, 100 ); }
        if ( empty( $result['success'] ) ) { return new WP_Error( 'creation_failed', __( 'Could not create the content. Check its name and access settings, then try again.', 'wpstream' ) ); }
        $id = (int) $result['content_id'];
        // Captions are the same field the editors use, whichever access the video has.
        $captions = esc_url_raw( $this->value( 'video_captions' ), array( 'http', 'https' ) );
        if ( ! $this->is_channel && '' !== $captions ) { update_post_meta( $id, 'wpstream_closed_captions_file', $captions ); }
        $new_plan_id = 0;
        if ( $subscription ) {
            try {
                // A new plan is a separate product; the VOD keeps its video product type.
                if ( $create_plan ) {
                    $plan = new WC_Product_Subscription();
                    $plan->set_name( $this->value( 'video_subscription_name' ) );
                    $plan->set_status( 'publish' );
                    $plan->set_virtual( true );
                    $plan->set_regular_price( $request['price'] );
                    $plan->set_price( $request['price'] );
                    $plan->update_meta_data( '_subscription_price', wc_format_decimal( $request['price'], wc_get_price_decimals() ) );
                    $plan->update_meta_data( '_subscription_period_interval', $interval );
                    $plan->update_meta_data( '_subscription_period', $period );
                    $plan->update_meta_data( '_subscription_length', 0 );
                    $plan->update_meta_data( '_subscript_live_event', 'none' );
                    $new_plan_id = $plan_id = $plan->save();
                    if ( ! $plan_id ) { throw new RuntimeException(); }
                }
                if ( $existing_plan || $create_plan ) {
                    $product = wc_get_product( $id );
                    $product->set_price( '' );
                    $product->set_regular_price( '' );
                    $product->update_meta_data( '_wpstream_parent_sub', array( $plan_id ) );
                } else {
                    $terms = wp_set_object_terms( $id, 'subscription', 'product_type' );
                    if ( is_wp_error( $terms ) ) { throw new RuntimeException(); }
                    $product = new WC_Product_Subscription( $id );
                    $product->update_meta_data( '_subscription_price', $request['price'] );
                    $product->update_meta_data( '_subscription_period_interval', $interval );
                    $product->update_meta_data( '_subscription_period', $period );
                    $product->update_meta_data( '_subscription_length', 0 );
                    $product->update_meta_data( '_subscript_live_event', $this->is_channel ? 'yes' : 'no' );
                }
                $product->set_status( 'publish' );
                $product->save();
                wc_delete_product_transients( $id );
            } catch ( Throwable $error ) {
                wp_delete_post( $id, true );
                if ( $new_plan_id ) { wp_delete_post( $new_plan_id, true ); }
                return new WP_Error( 'subscription_failed', __( 'The subscription could not be saved. Please try again.', 'wpstream' ) );
            }
        }
        if ( '' !== $token ) { set_transient( $token_key, $id, DAY_IN_SECONDS ); }
        return $id;
    }

    /** Render the connected-account creation workspace or its actionable sign-in gate. */
    public function render( $recordings, $default_access = 'free' ) {
        // Creation tools are available only while the site is connected to WpStream.
        if ( ! $this->main->wpstream_live_connection->is_connected() ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Sign in to your WpStream account to create content.', 'wpstream' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wpstream_onboard' ) ) . '">' . esc_html__( 'Sign in to WpStream', 'wpstream' ) . '</a></p></div>';
            return;
        }
        $result = $this->publish();
        // WordPress canonicalizes admin history entries, so refresh is a GET
        // even after a form POST. Resolve only this user's completed form token.
        if ( null === $result ) { $result = $this->published_id( $this->preset( 'library_published' ) ); }
        if ( is_wp_error( $result ) ) { echo '<div class="notice notice-error inline" role="alert"><p>' . esc_html( $result->get_error_message() ) . '</p></div>'; }
        elseif ( $result ) {
            if ( '' !== $this->value( 'video_token' ) ) {
                $success_url = add_query_arg( array(
                    'page' => $this->is_channel ? 'wpstream_live_channels' : 'wpstream_recordings',
                    $this->is_channel ? 'library_new' : 'library_upload' => $this->is_channel ? 'channel' : '1',
                    'library_published' => $this->value( 'video_token' ),
                ), admin_url( 'admin.php' ) );
                echo '<script>if(window.history.replaceState){window.history.replaceState(null,"",' . wp_json_encode( $success_url ) . ');}</script>';
            }
            if ( $this->is_channel ) {
                echo '<section class="library-video-success"><h2>' . esc_html__( 'Channel created', 'wpstream' ) . '</h2><p>' . esc_html( get_the_title( $result ) ) . '</p><a class="button button-primary" href="' . esc_url( Wpstream_Broadcaster_Host::url( $result ) ) . '">' . esc_html__( 'Broadcast', 'wpstream' ) . '</a> <a class="button" href="' . esc_url( get_edit_post_link( $result ) ) . '">' . esc_html__( 'Edit channel', 'wpstream' ) . '</a></section>';
                return;
            }
            echo '<section class="library-video-success"><h2>' . esc_html__( 'Video published', 'wpstream' ) . '</h2><p>' . esc_html( get_the_title( $result ) ) . '</p><a class="button button-primary" href="' . esc_url( get_permalink( $result ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open page ↗', 'wpstream' ) . '</a> <a class="button" href="' . esc_url( get_edit_post_link( $result ) ) . '">' . esc_html__( 'Edit video', 'wpstream' ) . '</a> <button type="button" class="button" data-copy-link="' . esc_attr( get_permalink( $result ) ) . '">' . esc_html__( 'Copy link', 'wpstream' ) . '</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=wpstream_recordings&library_upload=1' ) ) . '">' . esc_html__( 'Create video', 'wpstream' ) . '</a> <a href="' . esc_url( admin_url( $this->is_channel ? 'admin.php?page=wpstream_live_channels' : 'admin.php?page=wpstream_recordings' ) ) . '">' . esc_html__( 'Back to All Videos', 'wpstream' ) . '</a><p data-copy-status role="status"></p></section>';
            return;
        }
        $choices = $this->access_choices();
        $access = $this->value( 'video_access', $this->preset( 'video_access', $default_access ) );
        if ( ! isset( $choices[$access] ) ) { $access = 'free'; }
        $source = $this->value( 'video_source', $this->preset( 'new_video_name' ) );
        $global = 1 === (int) get_option( 'wpstream_global_sub' );
        if ( $global && 'paid' === $access ) { $access = 'free'; }
        $source_mode = $this->value( 'video_source_mode', $source ? 'recording' : 'upload' );
        echo '<section id="library-new-video" class="library-video-workspace' . ( $this->is_channel ? ' library-channel-workspace' : '' ) . '">';
        if ( ! $this->is_channel ) {
        echo '<div class="library-video-source">' . Wpstream_Video_Source_Panel::render( array( 'mode' => $source_mode, 'recording' => $source, 'recordings' => $recordings, 'external' => $this->value( 'video_external_url' ), 'captions' => $this->value( 'video_captions' ), 'captions_name' => 'video_captions', 'mode_name' => 'video_source_mode', 'recording_name' => 'video_source', 'external_name' => 'video_external_url', 'form' => 'library-video-form' ) ) . '</div>';
        }
        echo '<form id="library-video-form" method="post" class="library-video-details"><h2>' . esc_html( $this->is_channel ? __( 'New channel', 'wpstream' ) : __( 'New video', 'wpstream' ) ) . '</h2>';
        wp_nonce_field( 'wpstream_publish_video', 'video_nonce' );
        echo '<input type="hidden" name="video_action" value="publish"><input type="hidden" name="video_token" value="' . esc_attr( wp_generate_uuid4() ) . '"><label for="video-title">' . esc_html( $this->is_channel ? __( 'Channel name', 'wpstream' ) : __( 'Video name', 'wpstream' ) ) . '</label><input id="video-title" name="video_title" maxlength="120" required value="' . esc_attr( $this->value( 'video_title' ) ) . '"><fieldset><legend>' . esc_html__( 'Who can watch?', 'wpstream' ) . '</legend>';
        // A choice this site cannot sell yet stays selectable and explains what it needs.
        foreach ( $this->access_labels() as $key => $labels ) {
            echo '<label class="library-access-option"><input type="radio" name="video_access" value="' . esc_attr( $key ) . '"' . ( isset( $choices[$key] ) ? '' : ' data-unavailable' ) . checked( $access, $key, false ) . disabled( $global && 'paid' === $key, true, false ) . '><span><strong>' . esc_html( $labels[0] ) . '</strong><small>' . esc_html( $labels[1] ) . '</small></span></label>';
        }
        echo '</fieldset>';
        echo Wpstream_Commerce_Requirements::panels();
        if ( $global ) { echo '<p class="description">' . esc_html__( 'Your site uses subscription access. Individual pay-per-view purchases are disabled.', 'wpstream' ) . '</p>'; }
        if ( isset( $choices['paid'] ) ) {
            echo '<div data-video-price' . ( 'paid' !== $access ? ' hidden' : '' ) . '><label for="video-price">' . esc_html( sprintf( __( 'Price (%s)', 'wpstream' ), get_woocommerce_currency() ) ) . '</label><input id="video-price" type="number" name="video_price" min="0" step="any" value="' . esc_attr( $this->value( 'video_price', '10' ) ) . '"></div>';
        }
        if ( isset( $choices['subscription'] ) ) {
            echo '<div data-video-subscription' . ( 'subscription' !== $access ? ' hidden' : '' ) . '>';
            $plans = array_filter( wc_get_products( array( 'type' => 'subscription', 'status' => 'publish', 'limit' => -1 ) ), function ( $plan ) { return current_user_can( 'edit_post', $plan->get_id() ); } );
            $mode = $global ? 'existing' : $this->value( 'video_subscription_mode', $plans ? 'existing' : 'create' );
            echo '<label>' . esc_html__( 'Subscription', 'wpstream' ) . '<select name="video_subscription_mode"><option value="existing"' . selected( $mode, 'existing', false ) . '>' . esc_html__( 'Use an existing subscription', 'wpstream' ) . '</option>';
            if ( ! $global ) { echo '<option value="create"' . selected( $mode, 'create', false ) . '>' . esc_html__( 'Create a new subscription', 'wpstream' ) . '</option>'; }
            echo '</select></label>';
            {
                echo '<div data-video-plan-fields' . ( 'existing' !== $mode ? ' hidden' : '' ) . '>';
                echo '<label for="video-plan">' . esc_html__( 'Subscription', 'wpstream' ) . '</label><select id="video-plan" name="video_plan"><option value="">' . esc_html__( 'Choose a subscription', 'wpstream' ) . '</option>';
                foreach ( $plans as $plan ) { echo '<option value="' . esc_attr( $plan->get_id() ) . '"' . selected( $this->value( 'video_plan' ), $plan->get_id(), false ) . '>' . esc_html( $plan->get_name() ) . '</option>'; }
                echo '</select><p class="description">' . esc_html__( 'Active subscribers to this plan can watch this content.', 'wpstream' ) . '</p></div>';
            }
            if ( ! $global ) {
                echo '<div data-video-new-plan-fields' . ( 'create' !== $mode ? ' hidden' : '' ) . '><label>' . esc_html__( 'Subscription name', 'wpstream' ) . '<input name="video_subscription_name" value="' . esc_attr( $this->value( 'video_subscription_name' ) ) . '"></label>';
                echo '<label for="video-subscription-price">' . esc_html__( 'Subscription price', 'wpstream' ) . '</label><input id="video-subscription-price" name="video_subscription_price" type="number" min="0" step="any" value="' . esc_attr( $this->value( 'video_subscription_price', '10' ) ) . '"><div class="library-billing-row"><label>' . esc_html__( 'Bill every', 'wpstream' ) . '<select name="video_interval">';
                foreach ( array( 1, 2, 3, 6 ) as $interval ) { echo '<option' . selected( $this->value( 'video_interval', '1' ), $interval, false ) . '>' . intval( $interval ) . '</option>'; }
                echo '</select></label><label>' . esc_html__( 'Period', 'wpstream' ) . '<select name="video_period">';
                foreach ( array( 'month' => __( 'Month', 'wpstream' ), 'year' => __( 'Year', 'wpstream' ), 'week' => __( 'Week', 'wpstream' ) ) as $period => $label ) { echo '<option value="' . esc_attr( $period ) . '"' . selected( $this->value( 'video_period', 'month' ), $period, false ) . '>' . esc_html( $label ) . '</option>'; }
                echo '</select></label></div><p class="description">' . esc_html__( 'The new subscription will be created and this content will be added to it automatically.', 'wpstream' ) . '</p></div>';
            }
            echo '</div>';
        }
        // The script fills this in with what is still missing while Publish is unavailable.
        if ( ! $this->is_channel ) { echo '<p class="library-video-requirement" data-video-requirement role="status"></p>'; }
        echo '<button type="submit" class="button button-primary">' . esc_html( $this->is_channel ? __( 'Create channel', 'wpstream' ) : __( 'Publish video', 'wpstream' ) ) . '</button><a class="library-video-cancel" href="' . esc_url( admin_url( $this->is_channel ? 'admin.php?page=wpstream_live_channels' : 'admin.php?page=wpstream_recordings' ) ) . '">' . esc_html__( 'Cancel', 'wpstream' ) . '</a></form></section>';
    }
}
