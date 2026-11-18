<?php
/** Shared admin lists for Live Channels, VODs and the cloud media files. */
defined( 'ABSPATH' ) || exit;

class Wpstream_Content_Library {
    private $main;
    private $recordings = array();
    private $recording_users = array();
    private $save_succeeded = false;

    public function __construct( $main ) {
        $this->main = $main;
    }

    /**
     * The channels or videos listed on one library page, newest first.
     *
     * Free items are their own post type. Paid items are WooCommerce products,
     * so only products of the matching WpStream type (or a subscription flagged
     * for that kind) are read, never the rest of the shop.
     *
     * @param string $kind 'channel' or 'video'.
     * @return WP_Post[] Items the current user may edit.
     */
    private function items( $kind ) {
        global $wpdb;
        $free_type = 'channel' === $kind ? 'wpstream_product' : 'wpstream_product_vod';
        $paid_type = 'channel' === $kind ? 'live_stream' : 'video_on_demand';
        $live_flag = 'channel' === $kind ? 'yes' : 'no';

        // Paid items: products whose product_type term is this kind, or a
        // subscription whose live-event flag matches this kind.
        $paid_sql = post_type_exists( 'product' ) ? $wpdb->prepare(
            " OR ( p.post_type = 'product' AND EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
                INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                WHERE tr.object_id = p.ID AND ( t.slug = %s OR ( t.slug = 'subscription' AND EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_subscript_live_event' AND pm.meta_value = %s
                ) ) )
            ) )",
            $paid_type,
            $live_flag
        ) : '';

        // One query for the IDs, every status the library shows, newest first.
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             WHERE p.post_status IN ( 'publish', 'draft', 'private', 'pending', 'future', 'trash' )
               AND ( p.post_type = %s{$paid_sql} )
             ORDER BY p.post_date DESC, p.ID DESC",
            $free_type
        ) );

        // Load the posts in one go, then keep those the user may edit.
        _prime_post_caches( array_map( 'intval', $ids ) );
        $posts = array_filter( array_map( 'get_post', $ids ) );
        return array_values( array_filter( $posts, function ( $post ) {
            return current_user_can( 'edit_post', $post->ID );
        } ) );
    }

    private function query( $key, $default = '' ) {
        return isset( $_GET[$key] ) && is_scalar( $_GET[$key] ) ? sanitize_text_field( wp_unslash( $_GET[$key] ) ) : $default;
    }

    private function url( $kind, $changes = array() ) {
        $pages = array( 'channel' => 'wpstream_live_channels', 'video' => 'wpstream_recordings', 'recording' => 'wpstream_all_recordings' );
        $args = array( 'page' => $pages[$kind] );
        foreach ( array( 'library_status', 'library_search', 'library_category', 'library_access_type', 'library_sort', 'library_page' ) as $key ) {
            if ( '' !== $this->query( $key ) ) { $args[$key] = $this->query( $key ); }
        }
        return add_query_arg( array_merge( $args, $changes ), admin_url( 'admin.php' ) );
    }

    private function categories( $post ) {
        $taxonomies = array_intersect( array( 'category', 'product_cat', 'wpstream_category' ), get_object_taxonomies( $post->post_type ) );
        $terms = wp_get_object_terms( $post->ID, array_values( $taxonomies ) );
        return is_wp_error( $terms ) ? array() : $terms;
    }

    private function access( $post ) {
        if ( 'product' !== $post->post_type ) { return __( 'Free to watch', 'wpstream' ); }
        $parents = array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_wpstream_parent_sub', true ) ) );
        if ( $parents ) { return __( 'Subscription', 'wpstream' ) . ' · ' . implode( ', ', array_map( 'get_the_title', $parents ) ); }
        $price = function_exists( 'wc_price' ) ? html_entity_decode( wp_strip_all_tags( wc_price( get_post_meta( $post->ID, '_regular_price', true ) ) ), ENT_QUOTES, 'UTF-8' ) : '';
        if ( 'subscription' === wpstream_get_product_type_slug( $post->ID ) ) { return __( 'Subscription', 'wpstream' ) . ' · ' . $price . ' / ' . get_post_meta( $post->ID, '_subscription_period', true ); }
        if ( 1 === (int) get_option( 'wpstream_global_sub' ) ) { return __( 'Subscription', 'wpstream' ); }
        return __( 'Pay per view', 'wpstream' ) . ' · ' . $price;
    }

    private function access_key( $post ) {
        // Global Subscription Mode stops new pay-per-view items; an existing one keeps its own access.
        return 'product' !== $post->post_type ? 'free' : ( 'subscription' === wpstream_get_product_type_slug( $post->ID ) || get_post_meta( $post->ID, '_wpstream_parent_sub', true ) ? 'subscription' : 'paid' );
    }

    private function editor( $post, $kind ) {
        echo '<tr id="library-editor-' . intval( $post->ID ) . '" class="library-editor" hidden><td colspan="3">';
        // The panel opens under its row, so a heading says what opened. A video with a stored file gets a second column for it.
        $source = 'video' === $kind ? $this->source( $post ) : '';
        $has_file = $source && isset( $this->recordings[$source] );
        echo '<h3 class="library-editor-title">' . esc_html( 'video' === $kind ? __( 'Edit video', 'wpstream' ) : __( 'Edit channel', 'wpstream' ) ) . '</h3><div class="library-editor-layout' . ( $has_file ? ' has-source' : '' ) . '"><form method="post" class="library-edit-form">';
        wp_nonce_field( 'wpstream_library_action', 'library_nonce' );
        echo '<input type="hidden" name="library_ids[]" value="' . intval( $post->ID ) . '"><input type="hidden" name="library_action" value="save"><input type="hidden" name="library_kind" value="' . esc_attr( $kind ) . '">';
        echo '<label>' . esc_html__( 'Title', 'wpstream' ) . '<input name="library_title" required value="' . esc_attr( $post->post_title ) . '"></label>';
        echo '<label>' . esc_html__( 'Description', 'wpstream' ) . '<textarea name="library_content" rows="4">' . esc_textarea( $post->post_content ) . '</textarea></label>';
        $access = $this->access_key( $post );
        echo Wpstream_Commerce_Requirements::panels();
        echo '<label>' . esc_html__( 'Who can watch?', 'wpstream' ) . '<select name="library_access">';
        $choices = array( 'free' => __( 'Free to watch', 'wpstream' ) );
        if ( ! get_option( 'wpstream_global_sub' ) || 'paid' === $access ) { $choices['paid'] = __( 'Pay per view', 'wpstream' ); }
        $choices['subscription'] = __( 'Subscription', 'wpstream' );
        // A choice this site cannot sell yet stays selectable and explains what it needs.
        $unavailable = array( 'paid' => ! class_exists( 'WooCommerce' ), 'subscription' => ! class_exists( 'WC_Product_Subscription' ) );
        foreach ( $choices as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '"' . ( empty( $unavailable[$key] ) ? '' : ' data-unavailable' ) . selected( $access, $key, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></label><div data-library-price' . ( 'free' === $access ? ' hidden' : '' ) . '><label>' . esc_html__( 'Price', 'wpstream' ) . ( function_exists( 'get_woocommerce_currency' ) ? ' (' . esc_html( get_woocommerce_currency() ) . ')' : '' ) . '<input name="library_price" type="number" min="0" step="any" value="' . esc_attr( get_post_meta( $post->ID, '_regular_price', true ) ) . '"></label></div>';
        $parents = (array) get_post_meta( $post->ID, '_wpstream_parent_sub', true );
        $own_plan = 'subscription' === wpstream_get_product_type_slug( $post->ID );
        // An item with its own plan keeps it, also after Global Subscription Mode is turned on.
        $plan_mode = $own_plan ? 'own' : 'existing';
        echo '<div data-library-subscription' . ( 'subscription' !== $access ? ' hidden' : '' ) . '><label>' . esc_html__( 'Subscription', 'wpstream' ) . '<select name="library_subscription_mode">';
        if ( $own_plan ) { echo '<option value="own" selected>' . esc_html__( 'Its own subscription', 'wpstream' ) . '</option>'; }
        echo '<option value="existing"' . selected( $plan_mode, 'existing', false ) . '>' . esc_html__( 'Use an existing subscription', 'wpstream' ) . '</option>';
        if ( ! get_option( 'wpstream_global_sub' ) ) { echo '<option value="create">' . esc_html__( 'Create a new subscription', 'wpstream' ) . '</option>'; }
        echo '</select></label><div data-library-plan-fields' . ( 'existing' !== $plan_mode ? ' hidden' : '' ) . '><label>' . esc_html__( 'Choose subscription', 'wpstream' ) . '<select name="library_plan"><option value="">' . esc_html__( 'Choose a subscription', 'wpstream' ) . '</option>';
        if ( class_exists( 'WC_Product_Subscription' ) ) {
            foreach ( wc_get_products( array( 'type' => 'subscription', 'status' => 'publish', 'limit' => -1 ) ) as $plan ) {
                if ( $plan->get_id() === $post->ID || ! current_user_can( 'edit_post', $plan->get_id() ) ) { continue; }
                echo '<option value="' . intval( $plan->get_id() ) . '"' . selected( (int) ( $parents[0] ?? 0 ), $plan->get_id(), false ) . '>' . esc_html( $plan->get_name() ) . '</option>';
            }
        }
        echo '</select></label></div><label data-library-plan-name hidden>' . esc_html__( 'Subscription name', 'wpstream' ) . '<input name="library_subscription_name"></label><div data-library-billing' . ( 'existing' === $plan_mode ? ' hidden' : '' ) . '><label>' . esc_html__( 'Bill every', 'wpstream' ) . '<select name="library_interval">';
        foreach ( array( 1, 2, 3, 4, 5, 6 ) as $interval ) { echo '<option value="' . intval( $interval ) . '"' . selected( get_post_meta( $post->ID, '_subscription_period_interval', true ) ?: 1, $interval, false ) . '>' . intval( $interval ) . '</option>'; }
        echo '</select></label><label>' . esc_html__( 'Period', 'wpstream' ) . '<select name="library_period">';
        foreach ( array( 'day' => __( 'Day', 'wpstream' ), 'week' => __( 'Week', 'wpstream' ), 'month' => __( 'Month', 'wpstream' ), 'year' => __( 'Year', 'wpstream' ) ) as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '"' . selected( get_post_meta( $post->ID, '_subscription_period', true ) ?: 'month', $key, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></label></div></div><p><a target="_blank" rel="noopener" href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Open full editor ↗', 'wpstream' ) . '</a></p>';
        echo '<p class="library-row-notice" data-library-row-notice role="alert"></p><div class="library-actions"><button type="button" class="button" data-library-close>' . esc_html__( 'Cancel', 'wpstream' ) . '</button><button class="button button-primary" type="submit">' . esc_html__( 'Save changes', 'wpstream' ) . '</button></div></form>';
        if ( $has_file ) { $this->source_card( $source ); }
        echo '</div></td></tr>';
    }

    /**
     * Print the card of the stored file behind a video, kept apart from the video's own title and access.
     * Deleting the file is not ordinary editing, so it sits in the card's menu.
     *
     * @param string $name File name from the cloud.
     */
    private function source_card( $name ) {
        echo '<div class="library-file wpstream_video_wrapper" data-recording-users="' . esc_attr( implode( ', ', $this->recording_users[$name] ?? array() ) ) . '"><div class="library-file-head"><h4>' . esc_html__( 'Source media file', 'wpstream' ) . '</h4>';
        echo '<details class="library-menu"><summary aria-label="' . esc_attr( sprintf( __( 'More actions for %s', 'wpstream' ), $name ) ) . '"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="3" cy="8" r="1.5"/><circle cx="8" cy="8" r="1.5"/><circle cx="13" cy="8" r="1.5"/></svg></summary><div class="library-menu-list"><button type="button" class="button-link button-link-delete wpstream_delete_media" data-filename="' . esc_attr( $name ) . '">' . esc_html__( 'Delete file', 'wpstream' ) . '</button></div></details></div>';
        echo '<p class="library-file-name"><span class="storage_file_name_real">' . esc_html( $name ) . '</span> <small class="library-muted">' . esc_html( size_format( (int) $this->recordings[$name]['size'], 1 ) ) . '</small></p><div class="library-actions">';
        // The player opens with the panel, so the button names what a click will do; the page script swaps the two labels.
        echo '<button type="button" class="button-link" data-recording-preview data-filename="' . esc_attr( $name ) . '" data-label-show="' . esc_attr__( 'Show preview', 'wpstream' ) . '" data-label-hide="' . esc_attr__( 'Hide preview', 'wpstream' ) . '" aria-expanded="false">' . esc_html__( 'Show preview', 'wpstream' ) . '</button> ';
        echo '<button type="button" class="button-link wpstream_get_download_link" data-filename="' . esc_attr( $name ) . '">' . esc_html__( 'Download', 'wpstream' ) . '</button>';
        echo '<a class="wpstream_download_link" hidden></a></div><div class="wpstream_video_notice" role="status"></div></div>';
    }

    private function source( $post ) {
        if ( 3 === (int) get_post_meta( $post->ID, 'wpstream_product_type', true ) ) { return ''; }
        return (string) get_post_meta( $post->ID, 'product' === $post->post_type ? '_movie_url' : 'wpstream_free_video', true );
    }

    /**
     * Print the actions every stored file has: Preview, Download and Delete file.
     * The anchor the download script writes its link into is printed by the caller.
     *
     * @param string $name File name from the cloud.
     */
    private function recording_actions( $name ) {
        echo '<button type="button" class="button-link" data-recording-preview data-filename="' . esc_attr( $name ) . '" aria-expanded="false">' . esc_html__( 'Preview', 'wpstream' ) . '</button> ';
        echo '<button type="button" class="button-link wpstream_get_download_link" data-filename="' . esc_attr( $name ) . '">' . esc_html__( 'Download', 'wpstream' ) . '</button> ';
        echo '<button type="button" class="button-link button-link-delete wpstream_delete_media" data-filename="' . esc_attr( $name ) . '">' . esc_html__( 'Delete file', 'wpstream' ) . '</button>';
    }

    private function save( $items, $kind ) {
        if ( empty( $_POST['library_action'] ) ) { return ''; }
        if ( ! isset( $_POST['library_nonce'] ) || ! is_scalar( $_POST['library_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['library_nonce'] ) ), 'wpstream_library_action' ) ) {
            return __( 'Your session expired. Reload the page and try again.', 'wpstream' );
        }
        $action = is_scalar( $_POST['library_action'] ) ? sanitize_key( $_POST['library_action'] ) : '';
        if ( ! in_array( $action, array( 'save', 'trash', 'restore' ), true ) ) { return __( 'Invalid action.', 'wpstream' ); }
        $ids = isset( $_POST['library_ids'] ) && is_array( $_POST['library_ids'] ) ? array_unique( array_map( 'absint', $_POST['library_ids'] ) ) : array();
        $allowed = wp_list_pluck( $items, 'ID' );
        if ( ! $ids || array_diff( $ids, $allowed ) ) { return __( 'Select content you can manage on this page.', 'wpstream' ); }
        foreach ( $ids as $id ) {
            if ( ! current_user_can( 'save' === $action ? 'edit_post' : 'delete_post', $id ) ) { return __( 'You cannot change this content.', 'wpstream' ); }
        }
        if ( 'save' === $action ) {
            $title = isset( $_POST['library_title'] ) && is_scalar( $_POST['library_title'] ) ? sanitize_text_field( wp_unslash( $_POST['library_title'] ) ) : '';
            if ( '' === $title || 1 !== count( $ids ) ) { return __( 'Enter a title.', 'wpstream' ); }
            $content = isset( $_POST['library_content'] ) && is_scalar( $_POST['library_content'] ) ? wp_kses_post( wp_unslash( $_POST['library_content'] ) ) : '';
            $post = get_post( $ids[0] );
            if ( 'trash' === $post->post_status ) { return __( 'Restore this item before editing it.', 'wpstream' ); }
            $previous = $this->access_key( $post );
            $access = isset( $_POST['library_access'] ) && is_scalar( $_POST['library_access'] ) ? sanitize_key( $_POST['library_access'] ) : $previous;
            if ( ! in_array( $access, array( 'free', 'paid', 'subscription' ), true ) || ( 'free' !== $access && ! class_exists( 'WooCommerce' ) ) || ( 'subscription' === $access && ! class_exists( 'WC_Product_Subscription' ) ) ) { return __( 'This viewing access is unavailable.', 'wpstream' ); }
            if ( get_option( 'wpstream_global_sub' ) && 'paid' === $access && 'paid' !== $previous ) { return __( 'Individual pay-per-view purchases are disabled in Global Subscription Mode.', 'wpstream' ); }
            // Existing callers can save only text. Access fields must be explicit before altering commerce data.
            $change_access = isset( $_POST['library_access'] );
            $plan_id = 0;
            $plan_mode = isset( $_POST['library_subscription_mode'] ) && is_scalar( $_POST['library_subscription_mode'] ) ? sanitize_key( $_POST['library_subscription_mode'] ) : '';
            $own_plan = 'subscription' === wpstream_get_product_type_slug( $post->ID );
            // Global Subscription Mode allows only existing plans, besides an item's own plan it already has.
            if ( $change_access && 'subscription' === $access && get_option( 'wpstream_global_sub' ) && 'existing' !== $plan_mode && ! ( 'own' === $plan_mode && $own_plan ) ) { return __( 'Choose an existing subscription in Global Subscription Mode.', 'wpstream' ); }
            if ( $change_access && 'subscription' === $access && 'existing' === $plan_mode ) {
                $plan_id = isset( $_POST['library_plan'] ) && is_scalar( $_POST['library_plan'] ) ? absint( $_POST['library_plan'] ) : 0;
                // No plan picked for an item that already sells through one (even a
                // plan since trashed, which the editor no longer lists): keep how it
                // is sold and save only the text.
                $current_plans = (array) get_post_meta( $post->ID, '_wpstream_parent_sub', true );
                if ( 0 === $plan_id && 'subscription' === $previous && ! $own_plan && ! empty( $current_plans[0] ) ) {
                    $change_access = false;
                } else {
                    $plan = wc_get_product( $plan_id );
                    if ( ! $plan || $plan_id === $post->ID || ! $plan->is_type( 'subscription' ) || 'publish' !== $plan->get_status() || ! current_user_can( 'edit_post', $plan_id ) ) { return __( 'Choose an existing subscription.', 'wpstream' ); }
                }
            }
            $create_plan = $change_access && 'subscription' === $access && 'create' === $plan_mode;
            $plan_name = isset( $_POST['library_subscription_name'] ) && is_scalar( $_POST['library_subscription_name'] ) ? sanitize_text_field( wp_unslash( $_POST['library_subscription_name'] ) ) : '';
            if ( $create_plan && ( get_option( 'wpstream_global_sub' ) || '' === trim( $plan_name ) ) ) { return __( 'Enter a subscription name, or choose an existing subscription in Global Subscription Mode.', 'wpstream' ); }
            $external_source = 3 === (int) get_post_meta( $post->ID, 'wpstream_product_type', true ) ? (string) get_post_meta( $post->ID, 'product' === $post->post_type ? '_movie_url_external' : 'wpstream_free_video_external', true ) : '';
            if ( $change_access && 'video' === $kind && 'free' !== $access && 'free' === $previous && '' === $this->source( $post ) && '' === $external_source ) { return __( 'Choose a video source in the video editor before enabling paid viewing.', 'wpstream' ); }
            if ( $change_access && 'free' !== $access ) {
                $price = $plan_id ? '0' : ( isset( $_POST['library_price'] ) && is_scalar( $_POST['library_price'] ) ? wp_unslash( $_POST['library_price'] ) : '' );
                if ( ! is_numeric( $price ) || ! is_finite( (float) $price ) || (float) $price < 0 ) { return __( 'Enter a valid price.', 'wpstream' ); }
                $price = wc_format_decimal( $price, wc_get_price_decimals() );
                if ( 'subscription' === $access ) {
                    $interval = isset( $_POST['library_interval'] ) && is_scalar( $_POST['library_interval'] ) ? absint( $_POST['library_interval'] ) : 1;
                    $period = isset( $_POST['library_period'] ) && is_scalar( $_POST['library_period'] ) ? sanitize_key( $_POST['library_period'] ) : 'month';
                    if ( $interval < 1 || $interval > 6 || ! in_array( $period, array( 'day', 'week', 'month', 'year' ), true ) ) { return __( 'Choose a valid billing period.', 'wpstream' ); }
                }
            }
            // Validate everything before creating a plan or changing the existing item.
            $new_plan = null;
            $saved = false;
            // What a failed save puts back, so an item is never left half switched.
            $before_terms = taxonomy_exists( 'product_type' ) ? wp_get_object_terms( $ids[0], 'product_type', array( 'fields' => 'slugs' ) ) : array();
            try {
                if ( $create_plan ) {
                    $new_plan = $plan = new WC_Product_Subscription();
                    $plan->set_name( $plan_name ); $plan->set_status( 'draft' ); $plan->set_virtual( true );
                    $plan->set_regular_price( $price ); $plan->set_price( $price );
                    $plan->update_meta_data( '_subscription_price', $price );
                    $plan->update_meta_data( '_subscription_period_interval', $interval );
                    $plan->update_meta_data( '_subscription_period', $period );
                    $plan->update_meta_data( '_subscription_length', 0 );
                    $plan->update_meta_data( '_subscript_live_event', 'none' );
                    $plan_id = $plan->save();
                    if ( ! $plan_id ) { return __( 'The subscription could not be saved.', 'wpstream' ); }
                }
                $result = wp_update_post( wp_slash( array( 'ID' => $ids[0], 'post_title' => $title, 'post_content' => $content ) ), true );
                if ( ! is_wp_error( $result ) && $change_access ) {
                    $id = $ids[0];
                    $source = $this->source( $post );
                    $post_type = 'free' === $access ? ( 'channel' === $kind ? 'wpstream_product' : 'wpstream_product_vod' ) : 'product';
                    if ( $post_type !== $post->post_type ) { $result = wp_update_post( array( 'ID' => $id, 'post_type' => $post_type ), true ); }
                    if ( is_wp_error( $result ) ) { return $result->get_error_message(); }
                    if ( 'free' !== $access ) {
                        $type = 'subscription' === $access && ! $plan_id ? 'subscription' : ( 'channel' === $kind ? 'live_stream' : 'video_on_demand' );
                        $terms = wp_set_object_terms( $id, $type, 'product_type' );
                        if ( is_wp_error( $terms ) ) { return $terms->get_error_message(); }
                        clean_post_cache( $id );
                        // Build the product with its own class: wc_get_product() reads a cached type,
                        // and a hook that loaded this post earlier caches 'simple', which save() would
                        // then write back over the term set above.
                        $classes = array( 'subscription' => 'WC_Product_Subscription', 'live_stream' => 'WC_Product_Live_Stream', 'video_on_demand' => 'WC_Product_Video_On_Demand' );
                        $product = new $classes[ $type ]( $id );
                        if ( ! $product->get_id() ) { return __( 'The product could not be saved.', 'wpstream' ); }
                        $product->set_regular_price( $plan_id ? '' : $price );
                        // Preserve a valid existing sale price; changing the regular price should not cancel a sale.
                        $product->set_price( $plan_id ? '' : ( $product->is_on_sale() ? $product->get_sale_price() : $price ) );
                        if ( $plan_id ) { $product->set_sale_price( '' ); $product->update_meta_data( '_wpstream_parent_sub', array( $plan_id ) ); }
                        else { $product->delete_meta_data( '_wpstream_parent_sub' ); }
                        $product->set_virtual( true );
                        $product->save();
                        if ( 'subscription' === $access && ! $plan_id ) {
                            update_post_meta( $id, '_subscription_price', $price );
                            update_post_meta( $id, '_subscription_period_interval', $interval );
                            update_post_meta( $id, '_subscription_period', $period );
                            update_post_meta( $id, '_subscript_live_event', 'channel' === $kind ? 'yes' : 'no' );
                        }
                        wc_delete_product_transients( $id );
                    }
                    if ( 'free' === $access ) { delete_post_meta( $id, '_wpstream_parent_sub' ); }
                    if ( 'video' === $kind && '' !== $source ) {
                        update_post_meta( $id, 'free' === $access ? 'wpstream_free_video' : '_movie_url', $source );
                        update_post_meta( $id, 'wpstream_product_type', 2 );
                    }
                    if ( 'video' === $kind && '' !== $external_source ) {
                        update_post_meta( $id, 'free' === $access ? 'wpstream_free_video_external' : '_movie_url_external', $external_source );
                        update_post_meta( $id, 'wpstream_product_type', 3 );
                        delete_post_meta( $id, 'wpstream_free_video' );
                        delete_post_meta( $id, '_movie_url' );
                    }
                    if ( 'channel' === $kind && $previous !== $access ) { do_action( 'wpstream_channel_access_changed', $id, 'free' === $access ? 'free' : 'paid', 'free' === $previous ? 'free' : 'paid' ); }
                }
                if ( ! is_wp_error( $result ) && $new_plan ) {
                    $new_plan->set_status( 'publish' );
                    if ( ! $new_plan->save() ) { throw new RuntimeException(); }
                }
                $saved = ! is_wp_error( $result );
                $this->save_succeeded = $saved;
                return $saved ? __( 'Changes saved.', 'wpstream' ) : $result->get_error_message();
            } catch ( Throwable $error ) {
                return __( 'The changes could not be saved. Please try again.', 'wpstream' );
            } finally {
                // A failed edit must not leave a purchasable orphan subscription.
                if ( ! $saved && $new_plan && $new_plan->get_id() ) {
                    if ( array( $new_plan->get_id() ) === get_post_meta( $ids[0], '_wpstream_parent_sub', true ) ) { delete_post_meta( $ids[0], '_wpstream_parent_sub' ); }
                    $new_plan->delete( true );
                }
                if ( ! $saved ) {
                    wp_update_post( wp_slash( array( 'ID' => $ids[0], 'post_title' => $post->post_title, 'post_content' => $post->post_content, 'post_type' => $post->post_type ) ) );
                    if ( is_array( $before_terms ) && taxonomy_exists( 'product_type' ) ) { wp_set_object_terms( $ids[0], $before_terms, 'product_type' ); }
                    clean_post_cache( $ids[0] );
                }
            }
        }
        foreach ( $ids as $id ) {
            if ( 'trash' === $action && 'trash' !== get_post_status( $id ) ) {
                $result = wp_trash_post( $id );
            } elseif ( 'restore' === $action && 'trash' === get_post_status( $id ) ) {
                $previous = get_post_meta( $id, '_wp_trash_meta_status', true );
                $result = wp_untrash_post( $id );
                // A paid channel keeps the draft status its untrash rule gives it, so it is not back on sale.
                $paid_channel = 'product' === get_post_type( $id ) && Wpstream_Streaming_Content_Creation::is_live_channel_post( $id );
                if ( $result && ! $paid_channel && in_array( $previous, array( 'publish', 'draft', 'private', 'pending', 'future' ), true ) ) {
                    $result = wp_update_post( array( 'ID' => $id, 'post_status' => $previous ), true );
                }
            } else { continue; }
            if ( ! $result || is_wp_error( $result ) ) { return __( 'The change could not be saved. Please try again.', 'wpstream' ); }
        }
        return 'trash' === $action ? __( 'Moved to Trash.', 'wpstream' ) : __( 'Content restored.', 'wpstream' );
    }

    /** Fetch the cloud recordings and note which items use each file. */
    private function load_recordings( $items ) {
        $raw = $this->main->wpstream_live_connection->wpstream_get_videos_from_api();
        // Newest first. Files sharing a time are ordered by name, so paging never shows one twice or skips one.
        if ( isset( $raw['items'] ) && is_array( $raw['items'] ) ) { usort( $raw['items'], function ( $a, $b ) { return ( ( $b['time'] ?? 0 ) <=> ( $a['time'] ?? 0 ) ) ?: strcasecmp( $a['name'] ?? '', $b['name'] ?? '' ); } ); }
        foreach ( isset( $raw['items'] ) && is_array( $raw['items'] ) ? $raw['items'] : array() as $file ) {
            if ( isset( $file['name'] ) ) { $this->recordings[$file['name']] = $file; }
        }
        foreach ( $items as $post ) { if ( 'trash' !== $post->post_status && $this->source( $post ) ) { $this->recording_users[$this->source( $post )][] = $post->post_title; } }
        return $raw;
    }

    /**
     * Print the 16:9 image at the start of a row.
     * A featured image comes first. A video without one shows a frame of its stored file, captured once
     * and kept on this site the same way the Unused media files list does it. Anything else shows a
     * pale icon for its kind: a broadcast signal for a channel, a play mark for a video.
     */
    private function tile( $post, $kind ) {
        $image = get_the_post_thumbnail( $post->ID, 'thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) );
        if ( $image ) { echo '<span class="library-tile">' . $image . '</span>'; return; }
        $source = 'channel' === $kind ? '' : $this->source( $post );
        if ( $source && isset( $this->recordings[$source] ) ) { $this->file_tile( $source ); return; }
        $this->blank_tile( $kind );
    }

    /**
     * Print the tile of a stored file: its saved frame, or a tile the page script fills by reading
     * a short preview once and saving the frame here.
     *
     * @param string $name File name from the cloud.
     */
    private function file_tile( $name ) {
        $thumb = Wpstream_Media_Thumbs::url( $name );
        if ( $thumb ) { echo '<span class="library-tile"><img src="' . esc_url( $thumb ) . '" alt="" loading="lazy"></span>'; }
        else { $this->blank_tile( 'video', ' data-recording-thumb="' . esc_attr( $name ) . '"' ); }
    }

    /**
     * Print the pale icon that stands in for a missing row image.
     *
     * @param string $kind       'channel' for a broadcast signal, anything else for a play mark.
     * @param string $attributes Escaped extra attributes for the tile, such as the file a thumbnail is loaded for.
     */
    private function blank_tile( $kind, $attributes = '' ) {
        $icon = 'channel' === $kind
            ? '<circle cx="12" cy="12" r="2"/><path d="M16.2 7.8a6 6 0 0 1 0 8.4M7.8 16.2a6 6 0 0 1 0-8.4M19 5a10 10 0 0 1 0 14M5 19a10 10 0 0 1 0-14"/>'
            : '<path d="M8 5.5v13l11-6.5z"/>';
        echo '<span class="library-tile"' . $attributes . ' aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $icon . '</svg></span>';
    }

    /** Print the recording a video uses: its file name and size, or why there is no file. */
    private function recording_summary( $post ) {
        $source = $this->source( $post );
        if ( $source ) {
            // The name and size live on the source card, so the row keeps only the running time. The delete-file script
            // rewrites this element, so it is printed even when the cloud reports no time; an empty one takes no space.
            if ( isset( $this->recordings[$source] ) ) {
                $seconds = $this->recordings[$source]['durationSeconds'] ?? 0;
                echo '<span data-recording-file="' . esc_attr( $source ) . '">' . ( $seconds ? esc_html( $this->duration( $seconds ) ) : '' ) . '</span>';
            } else {
                // A missing file is a problem worth a word on the row; the tooltip still names it.
                echo '<span title="' . esc_attr( $source ) . '">' . esc_html__( 'File unavailable', 'wpstream' ) . '</span>';
            }
        } elseif ( get_post_meta( $post->ID, 'wpstream_free_video_external', true ) || get_post_meta( $post->ID, '_movie_url_external', true ) ) {
            echo '<span>' . esc_html__( 'External URL', 'wpstream' ) . '</span>';
        }
    }

    /** Category names worth showing on a row. The site's default category says nothing about an item, so it is left out. */
    private function listed_categories( $post ) {
        $default = array( (int) get_option( 'default_category' ), (int) get_option( 'default_product_cat' ) );
        $terms = array_filter( $this->categories( $post ), function ( $term ) use ( $default ) { return ! in_array( (int) $term->term_id, $default, true ); } );
        return implode( ', ', wp_list_pluck( $terms, 'name' ) );
    }

    /** Print how much of a list is on screen, for example "Showing 1–10 of 17 channels". */
    private function showing( $type, $page, $shown, $total ) {
        $first = ( $page - 1 ) * 10 + 1;
        $last = $first + $shown - 1;
        $texts = array(
            'channel' => _n( 'Showing %1$d–%2$d of %3$d channel', 'Showing %1$d–%2$d of %3$d channels', $total, 'wpstream' ),
            'video' => _n( 'Showing %1$d–%2$d of %3$d video', 'Showing %1$d–%2$d of %3$d videos', $total, 'wpstream' ),
            'recording' => _n( 'Showing %1$d–%2$d of %3$d media file', 'Showing %1$d–%2$d of %3$d media files', $total, 'wpstream' ),
        );
        echo '<span class="library-count">' . esc_html( sprintf( $texts[$type], $first, $last, $total ) ) . '</span>';
    }

    /**
     * Print a list's pagination: the count, then arrows around the page numbers.
     * A long list shows the first page, the last page and the current page's neighbours.
     * A list of one page shows only the count.
     */
    private function pagination( $type, $page, $pages, $shown, $total ) {
        echo '<nav class="library-pagination" aria-label="' . esc_attr__( 'Pagination', 'wpstream' ) . '">';
        $this->showing( $type, $page, $shown, $total );
        echo '<span class="library-pagination-links">';
        if ( $pages > 1 ) {
            $this->page_arrow( $type, 'prev', $page > 1 ? $page - 1 : 0 );
            $in_gap = false;
            for ( $number = 1; $number <= $pages; $number++ ) {
                // Pages away from both ends and the current page collapse into a single gap.
                if ( 1 !== $number && $pages !== $number && abs( $number - $page ) > 1 ) {
                    if ( ! $in_gap ) { echo '<span class="library-page-gap" aria-hidden="true">…</span>'; }
                    $in_gap = true;
                    continue;
                }
                $in_gap = false;
                if ( $number === $page ) {
                    echo '<span class="button library-page-link is-current" aria-current="page">' . intval( $number ) . '</span>';
                } else {
                    echo '<a class="button library-page-link" href="' . esc_url( $this->url( $type, array( 'library_page' => $number ) ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Page %d', 'wpstream' ), $number ) ) . '">' . intval( $number ) . '</a>';
                }
            }
            $this->page_arrow( $type, 'next', $page < $pages ? $page + 1 : 0 );
        }
        echo '</span></nav>';
    }

    /** Print the previous or next arrow. With no target page it stays in place, disabled, so the numbers do not shift. */
    private function page_arrow( $type, $direction, $target ) {
        $classes = 'button library-page-link library-page-' . $direction;
        $icon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ( 'prev' === $direction ? 'm15 18-6-6 6-6' : 'm9 18 6-6-6-6' ) . '"/></svg>';
        if ( ! $target ) {
            echo '<span class="' . esc_attr( $classes ) . ' is-disabled" aria-hidden="true">' . $icon . '</span>';
            return;
        }
        $label = 'prev' === $direction ? __( 'Previous page', 'wpstream' ) : __( 'Next page', 'wpstream' );
        echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $this->url( $type, array( 'library_page' => $target ) ) ) . '" aria-label="' . esc_attr( $label ) . '">' . $icon . '</a>';
    }

    /** Print one list row with its hidden editor. */
    private function row( $post, $kind ) {
        $is_channel = 'channel' === $kind;
        $is_trash = 'trash' === $post->post_status;
        $id = $post->ID;
        $title = $post->post_title ?: __( '(no title)', 'wpstream' );
        // A trashed channel cannot broadcast, so its status is never looked up.
        $state = $is_channel && ! $is_trash ? Wpstream_Channel_State::for_channel( $id, 'admin_library' ) : null;
        $is_live = $state && $state->is_live();
        echo '<tr data-content-id="' . intval( $id ) . '"' . ( $is_live ? ' class="library-row-live"' : '' ) . '>';
        echo '<td class="library-title" data-label="' . esc_attr( $is_channel ? __( 'Channel', 'wpstream' ) : __( 'Video', 'wpstream' ) ) . '"><div class="library-item">';
        $this->tile( $post, $kind );
        echo '<div class="library-item-text"><div class="library-title-line">';
        // A trashed item can only be restored, so its title opens nothing. Long titles are cut to one line; the tooltip and keyboard focus reveal the rest.
        if ( $is_trash ) { echo '<strong class="library-title-link" title="' . esc_attr( $title ) . '">' . esc_html( $title ) . '</strong>'; }
        else { echo '<a class="library-title-link" title="' . esc_attr( $title ) . '" href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( $title ) . '</a>'; }
        // Published is the normal state, and Trash has its own filter, so only the other states are called out.
        if ( ! in_array( $post->post_status, array( 'publish', 'trash' ), true ) ) { echo '<span class="library-state-tag">' . esc_html( get_post_status_object( $post->post_status )->label ) . '</span>'; }
        // Access comes first under the title, followed by the quieter details.
        echo '</div><div class="library-meta"><span' . ( 'free' === $this->access_key( $post ) ? '' : ' class="library-access-badge"' ) . '>' . esc_html( $this->access( $post ) ) . '</span>';
        if ( ! $is_channel ) { $this->recording_summary( $post ); }
        $categories = $this->listed_categories( $post );
        if ( $categories ) { echo '<span>' . esc_html( $categories ) . '</span>'; }
        echo '<span>' . esc_html( mysql2date( get_option( 'date_format' ), $post->post_date ) ) . '</span>';
        echo '</div></div></div></td><td data-label="' . esc_attr__( 'Actions', 'wpstream' ) . '"><div class="library-actions">';
        if ( $is_trash ) {
            // Restore is the only thing a trashed item can do, so it needs no menu.
            echo '<form method="post">'; wp_nonce_field( 'wpstream_library_action', 'library_nonce' );
            echo '<input type="hidden" name="library_ids[]" value="' . intval( $id ) . '"><button class="button-link" name="library_action" value="restore">' . esc_html__( 'Restore', 'wpstream' ) . '</button></form>';
        } else {
            $view_url = 'publish' === $post->post_status ? get_permalink( $id ) : get_preview_post_link( $id );
            $view_label = 'publish' === $post->post_status ? __( 'View', 'wpstream' ) : __( 'Preview', 'wpstream' );
            // Status sits beside Broadcast, so a channel's state reads together with its next action.
            if ( $is_channel ) { echo '<span class="library-status' . ( $is_live ? ' is-live' : '' ) . '">' . esc_html( $is_live ? __( 'Live', 'wpstream' ) : ( 'starting' === $state->status() ? __( 'Connecting', 'wpstream' ) : __( 'Offline', 'wpstream' ) ) ) . '</span>'; }
            // One action stays on the row: Broadcast for a channel, View for a video. It is neutral, and solid only while the channel is live.
            if ( $is_channel ) { echo '<a class="button library-main-action' . ( $is_live ? ' button-primary' : '' ) . '" href="' . esc_url( Wpstream_Broadcaster_Host::url( $id ) ) . '">' . esc_html__( 'Broadcast', 'wpstream' ) . '</a>'; }
            else { echo '<a class="button library-main-action" target="_blank" rel="noopener" href="' . esc_url( $view_url ) . '">' . esc_html( 'publish' === $post->post_status ? __( 'View video page', 'wpstream' ) : $view_label ) . '</a>'; }
            // Everything else lives in the row menu.
            echo '<details class="library-menu"><summary aria-label="' . esc_attr( sprintf( __( 'More actions for %s', 'wpstream' ), $title ) ) . '"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="3" cy="8" r="1.5"/><circle cx="8" cy="8" r="1.5"/><circle cx="13" cy="8" r="1.5"/></svg></summary><div class="library-menu-list">';
            echo '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html__( 'Edit', 'wpstream' ) . '</a>';
            echo '<button type="button" class="button-link library-row-toggle" aria-expanded="false" aria-controls="library-editor-' . intval( $id ) . '" aria-label="' . esc_attr( sprintf( __( 'Quick edit %s', 'wpstream' ), $post->post_title ) ) . '">' . esc_html__( 'Quick edit', 'wpstream' ) . '</button>';
            if ( $is_channel ) { echo '<a target="_blank" rel="noopener" href="' . esc_url( $view_url ) . '">' . esc_html( $view_label ) . '</a>'; }
            echo '<form method="post">'; wp_nonce_field( 'wpstream_library_action', 'library_nonce' );
            echo '<input type="hidden" name="library_ids[]" value="' . intval( $id ) . '"><button class="button-link button-link-delete" name="library_action" value="trash">' . esc_html__( 'Trash', 'wpstream' ) . '</button></form>';
            echo '</div></details>';
        }
        echo '</div></td></tr>';
        if ( 'trash' !== $post->post_status ) { $this->editor( $post, $kind ); }
    }

    /** Save a row editor without a page reload and answer with the row as it now is. */
    public function ajax_save() {
        if ( ! current_user_can( 'administrator' ) ) { wp_send_json_error( array( 'message' => __( 'You cannot change this content.', 'wpstream' ) ) ); }
        if ( ! isset( $_POST['library_action'] ) || 'save' !== $_POST['library_action'] ) { wp_send_json_error( array( 'message' => __( 'Invalid action.', 'wpstream' ) ) ); }
        $kind = isset( $_POST['library_kind'] ) && 'video' === $_POST['library_kind'] ? 'video' : 'channel';
        // The shared save path checks the nonce, ownership and every submitted value.
        $message = $this->save( $this->items( $kind ), $kind );
        if ( ! $this->save_succeeded ) { wp_send_json_error( array( 'message' => $message ) ); }
        $items = $this->items( $kind );
        if ( 'video' === $kind ) { $this->load_recordings( $items ); }
        ob_start();
        $this->row( get_post( absint( $_POST['library_ids'][0] ) ), $kind );
        wp_send_json_success( array( 'message' => $message, 'html' => ob_get_clean() ) );
    }

    /**
     * Print the filter dropdowns of a list.
     *
     * @param array $filters Field name => array( label, chosen value, value => option text ).
     */
    private function filter_selects( $filters ) {
        foreach ( $filters as $name => $field ) {
            echo '<select name="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $field[0] ) . '">';
            foreach ( $field[2] as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $field[1], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
            echo '</select>';
        }
    }

    /**
     * Say where a cloud file came from. The cloud does not report this, but it names every
     * recording itself as "Recording - Mon Nov 2, 2026 - Channel 64 (1).mp4", where the channel
     * and the copy number are optional. Any other name is treated as an upload.
     *
     * @param string $name File name from the cloud.
     * @return string The label for the Type column.
     */
    private function file_type( $name ) {
        $is_recording = preg_match( '/^Recording - [A-Z][a-z]{2} [A-Z][a-z]{2} \d{1,2}, \d{4}( - Channel \d+)?( \(\d+\))?\.mp4$/', $name );
        return $is_recording ? __( 'Recording', 'wpstream' ) : __( 'Uploaded', 'wpstream' );
    }

    /**
     * Format a length in seconds the way a player shows it: 4:05, or 1:04:05 past an hour.
     *
     * @param int|float $seconds Length reported by the cloud.
     * @return string
     */
    private function duration( $seconds ) {
        $seconds = (int) round( $seconds );
        $hours = intdiv( $seconds, 3600 );
        $minutes = intdiv( $seconds % 3600, 60 );
        return $hours ? sprintf( '%d:%02d:%02d', $hours, $minutes, $seconds % 60 ) : sprintf( '%d:%02d', $minutes, $seconds % 60 );
    }

    /**
     * Link to the channel a recording came from. The cloud names a recording after the channel's
     * post ID ("... - Channel 64"), so the ID is read from the file name and the channel's title shown.
     * A number that no longer names a channel on this site adds nothing.
     *
     * @param string $name File name from the cloud.
     * @return string Link HTML, or '' when the name carries no channel this site knows.
     */
    private function channel_link( $name ) {
        if ( ! preg_match( '/ - Channel (\d+)/', $name, $found ) ) { return ''; }
        $channel = get_post( (int) $found[1] );
        if ( ! $channel || ! in_array( $channel->post_type, array( 'wpstream_product', 'product' ), true ) ) { return ''; }
        return '<a href="' . esc_url( get_edit_post_link( $channel->ID ) ) . '">' . esc_html( $channel->post_title ?: __( '(no title)', 'wpstream' ) ) . '</a>';
    }

    /**
     * Print one row of the Unused media files list: the file's own frame, its name, length, size,
     * time and channel, then Create video with the other actions in a menu.
     *
     * @param array $entry File data from the cloud plus whether it is still processing.
     */
    private function recording_row( $entry ) {
        $file = $entry['file'];
        $name = $file['name'];
        echo '<tr class="wpstream_video_wrapper"><td class="library-title" data-preview-host data-label="' . esc_attr__( 'Media file', 'wpstream' ) . '"><div class="library-item">';
        // A finished file shows its own frame; a file still processing cannot be read yet.
        if ( $entry['pending'] ) { $this->blank_tile( 'video' ); } else { $this->file_tile( $name ); }
        echo '<div class="library-item-text"><div class="library-title-line"><span class="library-title-link storage_file_name_real" title="' . esc_attr( $name ) . '">' . esc_html( $name ) . '</span></div>';
        echo '<div class="library-meta">';
        if ( ! empty( $file['durationSeconds'] ) ) { echo '<span class="library-duration">' . esc_html( $this->duration( $file['durationSeconds'] ) ) . '</span>'; }
        echo '<span>' . esc_html( size_format( (int) ( $file['size'] ?? 0 ), 1 ) ) . '</span>';
        // The cloud reports the time in milliseconds. Recordings are told apart by when they were made, so the time of day is shown too.
        $time = (int) ( $file['time'] ?? 0 );
        if ( $time > 0 ) { echo '<span>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $time / 1000 ) ) ) . '</span>'; }
        $channel = $this->channel_link( $name );
        if ( $channel ) { echo '<span class="library-meta-file">' . $channel . '</span>'; }
        // Every file here is unused, so the only state worth a word is one still being processed.
        if ( $entry['pending'] ) { echo '<span class="library-recording-status">' . esc_html__( 'Processing', 'wpstream' ) . '</span>'; }
        echo '</div><div class="wpstream_video_notice" role="status"></div><a class="wpstream_download_link" hidden></a></div></div></td>';
        echo '<td data-label="' . esc_attr__( 'Type', 'wpstream' ) . '">' . esc_html( $this->file_type( $name ) ) . '</td><td data-label="' . esc_attr__( 'Actions', 'wpstream' ) . '"><div class="library-actions library-recording-actions">';
        // A file still processing cannot be used yet: its actions are printed hidden, and the page's polling script shows them once it is ready.
        $hidden = $entry['pending'] ? ' hidden' : '';
        if ( $entry['pending'] ) { echo '<span class="wpstream_video_pending">' . esc_html__( 'The video is still processing', 'wpstream' ) . '</span>'; }
        echo '<a class="button library-main-action create_new_free_video"' . $hidden . ' href="' . esc_url( add_query_arg( array( 'page' => 'wpstream_recordings', 'library_upload' => 1, 'new_video_name' => $name ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Create video', 'wpstream' ) . '</a>';
        echo '<details class="library-menu"' . $hidden . '><summary aria-label="' . esc_attr( sprintf( __( 'More actions for %s', 'wpstream' ), $name ) ) . '"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><circle cx="3" cy="8" r="1.5"/><circle cx="8" cy="8" r="1.5"/><circle cx="13" cy="8" r="1.5"/></svg></summary><div class="library-menu-list">';
        $this->recording_actions( $name );
        echo '</div></details></div></td></tr>';
    }

    /**
     * Print the Unused media files page: every file in the connected cloud account that no video
     * uses yet, with search, a processing filter, sort and paging. A file a video uses is managed
     * from that video on All Videos.
     */
    public function render_recordings() {
        if ( ! current_user_can( 'administrator' ) ) { esc_html_e( 'Only for administrators', 'wpstream' ); return; }
        require_once __DIR__ . '/class-wpstream-admin-header.php';
        // WordPress notices stay outside the branded library to retain their own typography.
        echo '<hr class="wp-header-end"><div class="wrap wpstream-library">';
        Wpstream_Admin_Header::render( $this->main );
        // Without an account there is nothing to list, so the page only offers the way to connect.
        if ( ! $this->main->wpstream_live_connection->is_connected() ) {
            echo '<div class="library-heading"><h1>' . esc_html__( 'Unused media files', 'wpstream' ) . '</h1></div>';
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Connect your WpStream account to broadcast or manage media files.', 'wpstream' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wpstream_onboard' ) ) . '">' . esc_html__( 'Connect account', 'wpstream' ) . '</a></p></div></div>';
            return;
        }
        $items = $this->items( 'video' );
        $raw = $this->load_recordings( $items );
        // Note which videos use each file; a trashed video no longer counts.
        $users = array();
        foreach ( $items as $post ) {
            if ( 'trash' !== $post->post_status && $this->source( $post ) ) { $users[$this->source( $post )][] = $post; }
        }
        // Files still processing come first, followed by the finished ones, newest first.
        // A file a video uses belongs to that video and is managed from All Videos, so it is left out.
        $files = array();
        foreach ( array( 'pending', 'items' ) as $group ) {
            foreach ( isset( $raw[$group] ) && is_array( $raw[$group] ) ? $raw[$group] : array() as $file ) {
                if ( empty( $file['name'] ) || isset( $users[$file['name']] ) ) { continue; }
                $files[] = array( 'file' => $file, 'pending' => 'pending' === $group, 'state' => 'pending' === $group ? 'processing' : 'ready' );
            }
        }
        $statuses = array( 'all' => __( 'All statuses', 'wpstream' ), 'processing' => __( 'Processing', 'wpstream' ) );
        $status = $this->query( 'library_status', 'all' );
        if ( ! isset( $statuses[$status] ) ) { $status = 'all'; }
        $search = $this->query( 'library_search' );
        $sort = $this->query( 'library_sort', 'date' );
        $matches = array_values( array_filter( $files, function ( $entry ) use ( $status, $search ) {
            return ( 'all' === $status || $status === $entry['state'] ) && ( '' === $search || false !== stripos( $entry['file']['name'], $search ) );
        } ) );
        if ( 'title' === $sort ) { usort( $matches, function ( $a, $b ) { return strcasecmp( $a['file']['name'], $b['file']['name'] ); } ); }
        $pages = max( 1, (int) ceil( count( $matches ) / 10 ) );
        $page = min( $pages, max( 1, (int) $this->query( 'library_page', '1' ) ) );
        $visible = array_slice( $matches, ( $page - 1 ) * 10, 10 );
        echo '<div class="library-heading"><div class="library-heading-text"><h1>' . esc_html__( 'Unused media files', 'wpstream' ) . '</h1><span class="library-heading-count">' . intval( count( $files ) ) . '</span>' . '<p class="library-heading-note">' . esc_html__( 'Recordings and uploads that haven’t been turned into videos. Create a video to offer free or paid viewing.', 'wpstream' ) . '</p>' . '</div><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=wpstream_recordings&library_upload=1#library-new-video' ) ) . '">' . esc_html__( 'Create video', 'wpstream' ) . '</a></div>';
        // The cloud answers false or nothing at all when it cannot be reached, which is not the same as having no files.
        if ( false === $raw || array() === $raw ) {
            echo '<div class="notice notice-warning inline" role="status"><p>' . esc_html__( 'Media files could not be loaded. Check your connection and reload this page.', 'wpstream' ) . '</p></div></div>';
            return;
        }
        // A status that would show nothing is left out. The chosen one always stays.
        $status_choices = array();
        foreach ( $statuses as $key => $label ) {
            $count = 'all' === $key ? count( $files ) : count( wp_list_filter( $files, array( 'state' => $key ) ) );
            if ( $status !== $key && 'all' !== $key && 0 === $count ) { continue; }
            $status_choices[$key] = $label . ' (' . $count . ')';
        }
        echo '<form method="get" class="library-filters"><input type="hidden" name="page" value="wpstream_all_recordings"><input type="search" name="library_search" aria-label="' . esc_attr__( 'Search media files', 'wpstream' ) . '" placeholder="' . esc_attr__( 'Search media files', 'wpstream' ) . '" value="' . esc_attr( $search ) . '">';
        $filters = array();
        // With only "All" to pick from, a status filter would be a control that does nothing.
        if ( count( $status_choices ) > 1 ) { $filters['library_status'] = array( __( 'Status', 'wpstream' ), $status, $status_choices ); }
        $filters['library_sort'] = array( __( 'Sort', 'wpstream' ), $sort, array( 'date' => __( 'Newest first', 'wpstream' ), 'title' => __( 'Name A–Z', 'wpstream' ) ) );
        $this->filter_selects( $filters );
        echo '</form><div class="library-table-wrap"><table class="widefat library-table library-files"><thead><tr><th>' . esc_html__( 'Media file', 'wpstream' ) . '</th><th>' . esc_html__( 'Type', 'wpstream' ) . '</th><th>' . esc_html__( 'Actions', 'wpstream' ) . '</th></tr></thead><tbody>';
        foreach ( $visible as $entry ) { $this->recording_row( $entry ); }
        if ( ! $visible ) { echo '<tr><td colspan="3" class="library-empty">' . esc_html( $files ? __( 'No media files match. Change your filters.', 'wpstream' ) : __( 'No media files yet. Recorded broadcasts and uploaded files appear here until a video uses them.', 'wpstream' ) ) . '</td></tr>'; }
        echo '</tbody></table></div>';
        if ( $visible ) {
            $this->pagination( 'recording', $page, $pages, count( $visible ), count( $matches ) );
        }
        echo '</div>';
    }

    public function render( $kind, $create_video = false, $default_access = 'free' ) {
        if ( ! current_user_can( 'administrator' ) ) { esc_html_e( 'Only for administrators', 'wpstream' ); return; }
        $is_channel = 'channel' === $kind;
        if ( $is_channel && 'channel' === $this->query( 'library_new' ) ) {
            require_once __DIR__ . '/class-wpstream-admin-header.php';
            require_once __DIR__ . '/class-wpstream-admin-video-creator.php';
            // WordPress notices stay outside the branded library to retain their own typography.
            echo '<hr class="wp-header-end"><div class="wrap wpstream-library">';
            Wpstream_Admin_Header::render( $this->main );
            echo '<div class="library-heading"><h1>' . esc_html__( 'New channel', 'wpstream' ) . '</h1><a href="' . esc_url( admin_url( 'admin.php?page=wpstream_live_channels' ) ) . '">' . esc_html__( 'Back to All Channels', 'wpstream' ) . '</a></div>';
            ( new Wpstream_Admin_Video_Creator( $this->main, 'live_channel' ) )->render( array() );
            echo '</div>';
            return;
        }
        $new_video = ! $is_channel && ( $create_video || '1' === $this->query( 'library_upload' ) );
        $connected = $this->main->wpstream_live_connection->is_connected();
        // Without an account there is nothing to manage, so the page only offers the way to connect.
        if ( ! $connected && ! $new_video ) {
            require_once __DIR__ . '/class-wpstream-admin-header.php';
            echo '<hr class="wp-header-end"><div class="wrap wpstream-library">';
            Wpstream_Admin_Header::render( $this->main );
            echo '<div class="library-heading"><h1>' . esc_html( $is_channel ? __( 'All Channels', 'wpstream' ) : __( 'All Videos', 'wpstream' ) ) . '</h1></div>';
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Connect your WpStream account to broadcast or manage recordings.', 'wpstream' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wpstream_onboard' ) ) . '">' . esc_html__( 'Connect account', 'wpstream' ) . '</a></p></div></div>';
            return;
        }
        $items = $this->items( $kind );
        $message = $this->save( $items, $kind );
        if ( $message ) { $items = $this->items( $kind ); }
        // Each video row names the file it uses.
        if ( ! $is_channel ) {
            $this->load_recordings( $items );
        }
        $status = $this->query( 'library_status', 'all' );
        $search = $this->query( 'library_search' );
        $category = $this->query( 'library_category' );
        $access_type = $this->query( 'library_access_type' );
        $sort = $this->query( 'library_sort', 'date' );
        $statuses = array( 'all' => __( 'All statuses', 'wpstream' ), 'publish' => __( 'Published', 'wpstream' ), 'draft' => __( 'Draft', 'wpstream' ), 'private' => __( 'Private', 'wpstream' ), 'trash' => __( 'Trash', 'wpstream' ) );
        $categories = array();
        foreach ( $items as $post ) {
            foreach ( $this->categories( $post ) as $term ) { $categories[$term->slug] = $term->name; }
        }
        asort( $categories );
        $matches = array_values( array_filter( $items, function ( $post ) use ( $status, $search, $category, $access_type ) {
            $in_status = 'all' === $status ? 'trash' !== $post->post_status : $status === $post->post_status;
            // Match what the row shows: Global Subscription Mode lists paid items as Subscription.
            $row_access = $this->access_key( $post );
            if ( 'paid' === $row_access && 1 === (int) get_option( 'wpstream_global_sub' ) ) { $row_access = 'subscription'; }
            return $in_status && ( '' === $search || false !== stripos( $post->post_title, $search ) ) &&
                ( '' === $access_type || $access_type === $row_access ) &&
                ( '' === $category || in_array( $category, wp_list_pluck( $this->categories( $post ), 'slug' ), true ) );
        } ) );
        if ( 'title' === $sort ) { usort( $matches, function ( $a, $b ) { return strcasecmp( $a->post_title, $b->post_title ); } ); }
        if ( $is_channel && 'trash' !== $status ) {
            $live_ids = array_map( 'intval', $this->main->wpstream_live_connection->api20_wpstream_request_live_stream_for_user_for_shortcode() );
            $is_active = function ( $post ) use ( $live_ids ) { return in_array( (int) $post->ID, $live_ids, true ); };
            // Each group keeps the chosen sort order.
            $matches = array_merge( array_filter( $matches, $is_active ), array_filter( $matches, function ( $post ) use ( $is_active ) { return ! $is_active( $post ); } ) );
        }
        $pages = max( 1, (int) ceil( count( $matches ) / 10 ) );
        $page = min( $pages, max( 1, (int) $this->query( 'library_page', '1' ) ) );
        $visible = array_slice( $matches, ( $page - 1 ) * 10, 10 );
        $status_count = function ( $key ) use ( $items ) {
            return count( array_filter( $items, function ( $post ) use ( $key ) { return 'all' === $key ? 'trash' !== $post->post_status : $key === $post->post_status; } ) );
        };
        // WordPress notices stay outside the branded library to retain their own typography.
        echo '<hr class="wp-header-end"><div class="wrap wpstream-library">';
        require_once __DIR__ . '/class-wpstream-admin-header.php';
        Wpstream_Admin_Header::render( $this->main );
        // Leaving the creation screen is a quiet link, so filled buttons only move creation forward.
        if ( $new_video ) { echo '<a class="library-back-link" href="' . esc_url( admin_url( 'admin.php?page=wpstream_recordings' ) ) . '">' . esc_html__( 'All videos', 'wpstream' ) . '</a>'; }
        echo '<div class="library-heading"><h1>' . esc_html( $new_video ? __( 'Add new video', 'wpstream' ) : ( $is_channel ? __( 'All Channels', 'wpstream' ) : __( 'All Videos', 'wpstream' ) ) ) . '</h1>' . ( $new_video ? '' : '<span class="library-heading-count">' . intval( $status_count( 'all' ) ) . '</span>' ) . ( $new_video ? '' : '<a class="button button-primary" href="' . esc_url( $is_channel ? admin_url( 'admin.php?page=wpstream_live_channels&library_new=channel' ) : $this->url( $kind, array( 'library_upload' => 1 ) ) . '#library-new-video' ) . '">' . esc_html( $is_channel ? __( 'New channel', 'wpstream' ) : __( 'Create video', 'wpstream' ) ) . '</a>' ) . '</div>';
        if ( $new_video ) {
            require_once __DIR__ . '/class-wpstream-admin-video-creator.php';
            ( new Wpstream_Admin_Video_Creator( $this->main ) )->render( $this->recordings, $default_access );
            echo '</div>';
            return;
        }
        if ( $message ) { echo '<div class="notice notice-info inline" role="status"><p>' . esc_html( $message ) . '</p></div>'; }
        // A status that would show nothing new is left out: an empty one, or Published when everything is published. The chosen one always stays.
        $status_choices = array();
        foreach ( $statuses as $key => $label ) {
            $count = $status_count( $key );
            if ( $status !== $key && 'all' !== $key && ( 0 === $count || ( 'publish' === $key && $count === $status_count( 'all' ) ) ) ) { continue; }
            $status_choices[$key] = $label . ' (' . $count . ')';
        }
        echo '<form method="get" class="library-filters"><input type="hidden" name="page" value="' . esc_attr( $is_channel ? 'wpstream_live_channels' : 'wpstream_recordings' ) . '"><input type="search" name="library_search" aria-label="' . esc_attr__( 'Search content', 'wpstream' ) . '" placeholder="' . esc_attr( $is_channel ? __( 'Search channels', 'wpstream' ) : __( 'Search videos', 'wpstream' ) ) . '" value="' . esc_attr( $search ) . '">';
        $filters = array();
        // With only "All" to pick from, a status filter would be a control that does nothing.
        if ( count( $status_choices ) > 1 ) { $filters['library_status'] = array( __( 'Status', 'wpstream' ), $status, $status_choices ); }
        $filters['library_category'] = array( __( 'Category', 'wpstream' ), $category, array( '' => __( 'All categories', 'wpstream' ) ) + $categories );
        $filters['library_access_type'] = array( __( 'Access', 'wpstream' ), $access_type, array( '' => __( 'All access types', 'wpstream' ), 'free' => __( 'Free to watch', 'wpstream' ), 'paid' => __( 'Pay per view', 'wpstream' ), 'subscription' => __( 'Subscription', 'wpstream' ) ) );
        $filters['library_sort'] = array( __( 'Sort', 'wpstream' ), $sort, array( 'date' => __( 'Newest first', 'wpstream' ), 'title' => __( 'Title A–Z', 'wpstream' ) ) );
        $this->filter_selects( $filters );
        echo '</form>';
        // Every row carries its own actions, so the table has no header.
        echo '<div class="library-row-status" data-library-status role="status"></div><div class="library-table-wrap"><table class="widefat library-table"><tbody>';
        foreach ( $visible as $post ) {
            $this->row( $post, $kind );
        }
        if ( ! $visible ) { echo '<tr><td colspan="2" class="library-empty">' . esc_html( $is_channel ? __( 'No channels match. Create a channel or change your filters.', 'wpstream' ) : __( 'No videos match. Create a video or change your filters.', 'wpstream' ) ) . '</td></tr>'; }
        echo '</tbody></table></div>';
        if ( $visible ) {
            $this->pagination( $kind, $page, $pages, count( $visible ), count( $matches ) );
        }
        echo '</div>';
    }
}
