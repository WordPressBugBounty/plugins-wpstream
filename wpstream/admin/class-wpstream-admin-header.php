<?php
/** Shared logo and connected-account resource header for WpStream admin screens. */
defined( 'ABSPATH' ) || exit;

class Wpstream_Admin_Header {
    /** Portal icon for each resource pill, keyed by metric. */
    private const ICONS = array(
        'broadcast' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M21 12c0 1.7-4 3-9 3s-9-1.3-9-3"/>',
        'viewer'    => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'recording' => '<path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>',
    );

    /**
     * Work out the used, left and percent figures of one resource.
     *
     * Baker reports only a cap and a balance, so nothing is returned when no cap is known. A top-up
     * can raise the balance above the cap; usage then stays at zero instead of turning negative.
     *
     * @return array|null Keys used, left and percent, or null when the cap is unknown.
     */
    private static function usage( $field, $total_field, $quota, $divisor ) {
        if ( ! isset( $quota[$field], $quota[$total_field] ) || ! is_numeric( $quota[$field] ) || ! is_numeric( $quota[$total_field] ) || $quota[$total_field] <= 0 ) { return null; }
        $left = max( 0, (float) $quota[$field] ) / $divisor;
        $total = (float) $quota[$total_field] / $divisor;
        $used = max( 0, $total - $left );
        return array( 'used' => $used, 'left' => $left, 'percent' => min( 100, (int) round( $used / $total * 100 ) ) );
    }

    /** Colour modifier of the pill meter: warning from 80% used, danger from 95%. */
    private static function fill_class( $percent ) {
        if ( $percent >= 95 ) { return ' is-danger'; }
        return $percent >= 80 ? ' is-warn' : '';
    }

    /** Build the popover's meter and used/left rows; the live quota poll keeps them current. */
    private static function popover_usage( $label, $usage, $unit ) {
        if ( ! $usage ) { return ''; }
        return '<div class="library-usage-meter" role="progressbar" aria-label="' . esc_attr( sprintf( __( '%s used', 'wpstream' ), $label ) ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . intval( $usage['percent'] ) . '"><span style="width:' . intval( $usage['percent'] ) . '%"></span></div>'
            . '<div class="library-usage-labels"><span data-usage-used>' . esc_html( sprintf( __( '%1$s %2$s used', 'wpstream' ), number_format( $usage['used'], 2, '.', '' ), $unit ) ) . '</span><span data-usage-left>' . esc_html( sprintf( __( '%1$s %2$s left', 'wpstream' ), number_format( $usage['left'], 2, '.', '' ), $unit ) ) . '</span></div>';
    }

    /** Connected-account balances; portal-user plan allocations are a different account scope. */
    public static function render( $main, $standalone = false, $entry = false ) {
        $connected = $main->wpstream_live_connection->is_connected();
        $quota = $connected ? $main->quota_manager->get_live_quota_data( 'admin_library' ) : array();
        $hours = ! empty( $quota['use_streaming_hours'] );
        $metrics = $hours ? array(
            array( 'broadcast', __( 'Broadcast Hours', 'wpstream' ), 'available_broadcast_hours', 'wpstream_broadcast_hours', 'total_broadcast_hours' ),
            array( 'viewer', __( 'Viewer Hours', 'wpstream' ), 'available_viewer_hours', 'wpstream_viewer_hours', 'total_viewer_hours' ),
            array( 'recording', __( 'Recording Hours', 'wpstream' ), 'available_storage_hours', 'wpstream_storage_hours', 'total_storage_hours' ),
        ) : array(
            array( 'viewer', __( 'Cloud Data', 'wpstream' ), 'available_data_mb', 'wpstream_band', 'total_data_mb' ),
            array( 'recording', __( 'Cloud Storage', 'wpstream' ), 'available_storage_mb', 'wpstream_storage', 'total_storage_mb' ),
        );
        if ( $standalone ) { echo '<div class="wrap wpstream-library">'; }
        echo '<header class="library-header"><a class="library-brand" href="' . esc_url( admin_url( 'admin.php?page=wpstream_onboard' ) ) . '"><img src="' . esc_url( plugin_dir_url( dirname( __FILE__ ) ) . 'public/images/wpstream-brand-logo.svg' ) . '" alt="WpStream" width="108" height="38"></a>';
        echo '<div class="library-account-status" aria-label="' . esc_attr__( 'Available streaming resources', 'wpstream' ) . '">';
        if ( ! $connected ) {
            // The entry screen already holds the sign-in form, so only other screens link to it.
            $connect_link = $entry ? '' : ' <a href="' . esc_url( admin_url( 'admin.php?page=wpstream_onboard' ) ) . '">' . esc_html__( 'Connect account', 'wpstream' ) . '</a>';
            echo '<span class="library-account-disconnected">' . esc_html__( 'WpStream account not connected', 'wpstream' ) . $connect_link . '</span>';
        }
        if ( ! extension_loaded( 'curl' ) ) {
            echo '<span class="library-account-disconnected" role="status">' . esc_html__( 'The PHP cURL extension is unavailable. Ask your hosting provider to enable it and allow outgoing HTTP connections.', 'wpstream' ) . '</span>';
        } elseif ( ! $connected && get_option( 'wpstream_curl_failed' ) && '0' !== get_option( 'wpstream_curl_failed' ) ) {
            echo '<span class="library-account-disconnected" role="status">' . esc_html__( 'We could not connect to WpStream. Please contact support if the problem persists.', 'wpstream' ) . '</span>';
        }
        foreach ( $connected ? $metrics : array() as list( $key, $label, $field, $input, $total_field ) ) {
            $known = isset( $quota[$field] ) && is_numeric( $quota[$field] );
            $raw = $known ? max( 0, (float) $quota[$field] ) : 0;
            // Data plans are reported in megabytes but displayed in gigabytes.
            $balance = $hours ? $raw : $raw / 1024;
            $value = $known ? number_format( floor( $balance * 100 ) / 100, 2, '.', '' ) : '—';
            // The pill follows the portal's rule: two decimals below 10, one from 10 upwards.
            $pill_value = $known ? number_format( $balance, $balance < 10 ? 2 : 1, '.', '' ) : '—';
            $unit = $hours ? 'hrs' : 'GB';
            $usage = self::usage( $field, $total_field, $quota, $hours ? 1 : 1024 );
            $percent = $usage ? $usage['percent'] : 0;
            // Only a known cap can be recalculated by the live quota poll.
            $poll_attributes = $usage ? ' data-library-usage data-available="' . esc_attr( $field ) . '" data-total="' . esc_attr( $total_field ) . '" data-unit="' . esc_attr( $unit ) . '"' : '';
            $icon = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . self::ICONS[$key] . '</svg>';
            $chevron = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
            $details_icon = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18M7 14l3-3 3 3 5-5"/></svg>';
            $upgrade_icon = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>';
            // Unknown caps have no percentage, including in the compact pill.
            $mini_meter = $usage ? '<span class="library-usage-mini" role="progressbar" aria-label="' . esc_attr( sprintf( __( '%s used', 'wpstream' ), $label ) ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . intval( $percent ) . '"><span class="library-usage-mini-fill' . esc_attr( self::fill_class( $percent ) ) . '" style="width:' . intval( $percent ) . '%"></span></span>' : '';
            // The pill: icon, label, used meter, remaining balance and chevron.
            echo '<details class="library-usage-item library-usage-' . esc_attr( $key ) . '"' . $poll_attributes . '><summary><span class="library-usage-icon" aria-hidden="true">' . $icon . '</span><span class="library-usage-label">' . esc_html( $label ) . '</span>' . $mini_meter . '<span class="library-usage-value"><span data-library-pill-quota="' . esc_attr( $field ) . '">' . esc_html( $pill_value ) . '</span><small> ' . esc_html( $unit ) . '</small></span><span class="library-usage-chev" aria-hidden="true">' . $chevron . '</span></summary>';
            // The popover: heading with percent badge, meter, used/left, total and account links.
            echo '<div class="library-usage-popover" role="dialog" aria-label="' . esc_attr( sprintf( __( '%s details', 'wpstream' ), $label ) ) . '"><div class="library-usage-head"><strong>' . esc_html( $label ) . '</strong>' . ( $usage ? '<span class="library-usage-percent"><span class="library-usage-dot"></span><span data-usage-percent>' . esc_html( sprintf( __( '%d%% used', 'wpstream' ), $percent ) ) . '</span></span>' : '' ) . '</div>' . self::popover_usage( $label, $usage, $unit ) . '<dl><div><dt>' . esc_html__( 'Total available', 'wpstream' ) . '</dt><dd><span data-library-quota="' . esc_attr( $field ) . '">' . esc_html( $value ) . '</span> ' . esc_html( $unit ) . '</dd></div></dl><div class="library-usage-actions"><a href="https://wpstream.net/my-account/billing/" target="_blank" rel="noopener noreferrer">' . $details_icon . esc_html__( 'See details', 'wpstream' ) . '</a><a class="library-usage-upgrade" href="https://wpstream.net/my-account/upgrade-plan/" target="_blank" rel="noopener noreferrer">' . $upgrade_icon . esc_html__( 'Upgrade / Top-up', 'wpstream' ) . '</a></div></div></details>';
            if ( $known ) { echo '<input type="hidden" id="' . esc_attr( $input ) . '" value="' . esc_attr( $raw ) . '">'; }
        }
        // Connecting comes first, so Upgrade stays a quiet link until an account is connected.
        echo '<a class="' . ( $connected ? 'button library-account-upgrade' : 'library-account-upgrade is-quiet' ) . '" href="https://wpstream.net/pricing/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Upgrade', 'wpstream' ) . '</a>';
        echo '</div></header>';
        if ( $standalone ) { echo '</div>'; }
    }

}
