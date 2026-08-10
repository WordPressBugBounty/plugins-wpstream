<?php
/**
 * Watch later template.
 *
 * Renders the dashboard "Watch Later" list. Queries the posts saved in the
 * user's watch-later meta (across VOD, live, product and bundle post types),
 * paginates them and includes the per-item card partial. Falls back to a
 * plugin-required notice when the WpStream player class is unavailable.
 *
 * @package wpstream-theme
 */

?>
<!-- Watch Later grid container. -->
<div class="wpstream-grid-main">
    <?php
    // Only render the list when the WpStream player class is available.
    if ( class_exists( 'Wpstream_Player' ) ) {
        ?>
        <!-- Section heading. -->
        <h3> <?php esc_html_e( 'Watch Later', 'hello-wpstream' ); ?></h3>
        <!-- Row holding the watch-later cards. -->
        <div class="row m-0">
            <?php

            // Current user whose saved watch-later items are listed.
            $current_user           = wp_get_current_user();// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            $user_id                = $current_user->ID;// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            // Transient cache key for this user's watch-later query.
            $transient_key          = 'wpstream_user_watch_later_items_query_' . $user_id;
            $wpstream_get_post_type = array( 'wpstream_product_vod', 'wpstream_product' );
            // Post IDs the user saved to watch later.
            $watch_later_item_ids   = get_user_meta( $user_id, 'wpstream_user_watch_later_items', true );

            // Expose the pagination page number for the query below.
            global $paged;
            // Site-wide posts-per-page setting.
            $posts_per_page = get_option( 'posts_per_page' );
            // For some woocomerse add into pagination details in watch-later instead of page
            // This should be changed when we found why.
            // Without WooCommerce, derive the page from the 'watch-later' query var; otherwise use 'paged'.
            if ( ! function_exists( 'woocommerce_my_account' ) ) {
                $paged = ( get_query_var( 'watch-later' ) );          // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                $paged = intval( str_replace( 'page/', '', $paged ) );// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                if ( 0 === $paged ) {
                    $paged = intval( get_query_var( 'paged' ) );// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                }
            } else {
                $paged = intval( get_query_var( 'paged' ) );// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
            }

            // Number of items to show per page.
            $per_page = get_option( 'posts_per_page' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

            // Build the query args restricted to the saved item IDs and current page.
            $watch_later_query_args = array(
                'ignore_sticky_posts' => true,
                'post_status'         => 'any',
                'post_type'           => array( 'wpstream_product_vod', 'wpstream_product', 'product', 'wpstream_bundles' ),
                'posts_per_page'      => $per_page,
                'post__in'            => $watch_later_item_ids, // Include posts with these IDs.
                'paged'               => $paged, // Current page number.
            );

            // Run the watch-later query.
            $query = new WP_Query( $watch_later_query_args );
            // With results: wrap the list and loop each item into the card partial.
            if ( $query->have_posts() ) {
                print '<div class="wpstream_dashboard_items_list_wrapper">';
                // Iterate results, setting up post data for each card.
                while ( $query->have_posts() ) {
                    $query->the_post();
                    // Render the single watch-later card for the current post.
                    include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/dashboard-templates/watch-later-list.php';
                }
                print '</div>';
            } else {
                // Empty-list message.
                esc_html_e( 'There are no items in the list', 'hello-wpstream' );
            }

            // Restore the main query's post data after the custom loop.
            wp_reset_postdata();

            ?>
        </div>

        <!-- Hidden nonce used by the watch-later remove action. -->
        <input type="hidden" name="wpstream_nonce" id="wpstream-watch-later-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpstream-watch-later-nonce' ) ); ?>"/>

        <!-- Pagination -->
        <!-- Pagination controls. -->
        <div class="navigation">

            <?php
            // Output pagination links for the watch-later results.
            wpstream_theme_pagination( $query->max_num_pages, $range = 2,$paged );
            ?>

        </div>
        <?php
        } else {
            // Player class missing: build a link and show an install-the-plugin notice.
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

