<?php
/**
 * Watch later template
 *
 * @package wpstream-theme
 */

?>
<div class="wpstream-grid-main">
    <?php
    if ( class_exists( 'Wpstream_Player' ) ) {
        ?>
        <h3> <?php esc_html_e( 'Watch Later', 'hello-wpstream' ); ?></h3>
        <div class="row m-0">
            <?php

            $current_user           = wp_get_current_user();// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            $user_id                = $current_user->ID;// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            $transient_key          = 'wpstream_user_watch_later_items_query_' . $user_id;
            $wpstream_get_post_type = array( 'wpstream_product_vod', 'wpstream_product' );
            $watch_later_item_ids   = get_user_meta( $user_id, 'wpstream_user_watch_later_items', true );

            global $paged;
            $posts_per_page = get_option( 'posts_per_page' );
            // For some woocomerse add into pagination details in watch-later instead of page
            // This should be changed when we found why.
            if ( ! function_exists( 'woocommerce_my_account' ) ) {
                $paged = ( get_query_var( 'watch-later' ) );          // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                $paged = intval( str_replace( 'page/', '', $paged ) );// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                if ( 0 === $paged ) {
                    $paged = intval( get_query_var( 'paged' ) );// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                }
            } else {
                $paged = intval( get_query_var( 'paged' ) );// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            }

            $per_page = get_option( 'posts_per_page' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

            $watch_later_query_args = array(
                'ignore_sticky_posts' => true,
                'post_status'         => 'any',
                'post_type'           => array( 'wpstream_product_vod', 'wpstream_product', 'product', 'wpstream_bundles' ),
                'posts_per_page'      => $per_page,
                'post__in'            => $watch_later_item_ids, // Include posts with these IDs.
                'paged'               => $paged, // Current page number.
            );

            $query = new WP_Query( $watch_later_query_args );
            if ( $query->have_posts() ) {
                print '<div class="wpstream_dashboard_items_list_wrapper">';
                while ( $query->have_posts() ) {
                    $query->the_post();
                    include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/watch-later-list.php';
                }
                print '</div>';
            } else {
                esc_html_e( 'There are no items in the list', 'hello-wpstream' );
            }

            wp_reset_postdata();

            ?>
        </div>

        <input type="hidden" name="wpstream_nonce" id="wpstream-watch-later-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpstream-watch-later-nonce' ) ); ?>"/>

        <!-- Pagination -->
        <div class="navigation">

            <?php
            wpstream_theme_pagination( $query->max_num_pages, $range = 2,$paged );
            ?>

        </div>
        <?php
        } else {
            $plugin_name = esc_html__('WpStream plugin', 'hello-wpstream');
            $plugin_link = '<a href="https://wordpress.org/plugins/wpstream/" target="_blank">' . $plugin_name . '</a>';
            printf(
                '<h4>%s</h4>',
                sprintf(
                /* translators: %s: Link to WPStream plugin */
                    __('You need to install and activate the %s to use this feature.', 'hello-wpstream'),
                    $plugin_link
                )
            );
        }
    ?>
</div>

