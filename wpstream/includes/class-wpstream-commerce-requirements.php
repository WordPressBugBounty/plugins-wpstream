<?php
/** Dependency guidance shared by video and live-channel access controls. */
defined( 'ABSPATH' ) || exit;

final class Wpstream_Commerce_Requirements {
    /**
     * Return notices only for unavailable access types; never install plugins.
     *
     * @param string $access all, paid, or subscription.
     * @return string Escaped HTML suitable beside access controls.
     */
    public static function render( $access = 'all' ) {
        $woo = class_exists( 'WooCommerce' );
        $subscriptions = $woo && class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Product_Subscription' ) && function_exists( 'wcs_user_has_subscription' );
        $html = '';
        if ( in_array( $access, array( 'all', 'paid' ), true ) && ! $woo ) {
            $html .= self::notice( __( 'You need WooCommerce to sell pay-per-view videos or live streams.', 'wpstream' ), 'woocommerce/woocommerce.php', __( 'Install WooCommerce', 'wpstream' ), admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) );
        }
        if ( in_array( $access, array( 'all', 'subscription' ), true ) && ! $subscriptions ) {
            $html .= self::notice( __( 'You need WooCommerce and WooCommerce Subscriptions to offer subscription access.', 'wpstream' ), 'woocommerce-subscriptions/woocommerce-subscriptions.php', __( 'Get WooCommerce Subscriptions', 'wpstream' ), 'https://woocommerce.com/products/woocommerce-subscriptions/' );
        }
        return $html;
    }

    /**
     * Wrap each explanation so an access control can reveal it when its unavailable choice is picked.
     *
     * @return string Escaped HTML; empty when every access type is available.
     */
    public static function panels() {
        $html = '';
        foreach ( array( 'paid', 'subscription' ) as $access ) {
            $notice = self::render( $access );
            if ( '' !== $notice ) { $html .= '<div data-access-requirement="' . esc_attr( $access ) . '" hidden>' . $notice . '</div>'; }
        }
        return $html;
    }

    /** Render actionable guidance for administrators and plain guidance for authors. */
    private static function notice( $message, $plugin, $label, $url ) {
        $html = '<p class="wpstream-commerce-requirement" role="note">' . esc_html( $message );
        if ( current_user_can( 'activate_plugins' ) ) {
            // An installed but inactive plugin needs activation, not another installation.
            if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
                $label = __( 'Manage plugins', 'wpstream' );
                $url = admin_url( 'plugins.php' );
            } elseif ( ! current_user_can( 'install_plugins' ) && 'woocommerce/woocommerce.php' === $plugin ) {
                return $html . '</p>';
            }
            $html .= ' <a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }
        return $html . '</p>';
    }
}
