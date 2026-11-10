<?php
/**
 * Plugin-owned copy of the portal broadcaster, migrated 2026-10-28.
 * Rendering and assets stay in this folder; plugin data access is confined to
 * prepare() and broadcaster-ajax.php. PHP symbols/handles are plugin-specific,
 * while DOM hooks and window.wpstreamPortal.broadcaster remain module-owned.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* -----------------------------------------------------------------------------
   Module constants (AGENTS.md rule 3).
   The host-specific strings the broadcaster module depends on live here and
   nowhere else, so a host (the WPStream plugin on migration day) can swap
   them without editing module code: it predefines any of these in its own
   config before this file loads, and the defined() guards keep its values.
   This file is required before broadcaster-ajax.php, so both files see them.

   Note: WP's i18n string extraction (makepot) expects a literal text domain
   in each __() / esc_html__() call. The module gives that up on purpose in
   exchange for a single swappable domain.
   ----------------------------------------------------------------------------- */

// Text domain for every translatable string in the module. 'wpstream' is
// today's domain, so no translation lookup changes.
if ( ! defined( 'WPSTREAM_PLUGIN_BC_TEXTDOMAIN' ) ) {
    define( 'WPSTREAM_PLUGIN_BC_TEXTDOMAIN', 'wpstream' );
}

// Nonce action created for the hidden #wpstream_start_event_nonce input and
// verified by every broadcaster AJAX handler.
if ( ! defined( 'WPSTREAM_PLUGIN_BC_NONCE' ) ) {
    define( 'WPSTREAM_PLUGIN_BC_NONCE', 'wpstream_start_event_nonce' );
}

/** Render the same channel recording control in each source/lifecycle panel. */
function wpstream_plugin_render_bc_recording( $record ) {
    ?>
    <label class="wpstream_portal_bc_recording" data-wpstream-portal-bc-recording>
        <strong data-wpstream-portal-bc-recording-title><?php echo esc_html( $record ? __( 'Recording on', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ) : __( 'Recording off', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ) ); ?></strong>
        <span data-wpstream-portal-bc-recording-help><?php echo esc_html( $record ? __( 'Saved to All Videos when you end.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ) : __( 'This broadcast won’t be saved.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ) ); ?></span>
        <input type="checkbox" role="switch" data-wpstream-portal-bc-recording-toggle aria-label="<?php echo esc_attr( $record ? __( 'Recording on', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ) : __( 'Recording off', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ) ); ?>" <?php checked( $record ); ?> disabled>
        <span data-wpstream-portal-bc-recording-error role="status"></span>
    </label>
    <?php
}

/**
 * Echo the 4-row live-stats grid (Viewers / Duration / Bitrate / Health).
 *
 * The exact same grid markup appears in BOTH the LIVE side panel and the
 * EXTERNAL side panel of the broadcaster card. This helper is the single
 * source of that markup so the two panels can never drift apart. The output
 * is byte-for-byte identical to the previously-inlined grid: same
 * .wpstream_portal_bc_stats wrapper, the four .wpstream_portal_bc_stat rows,
 * the icon SVG path data, the .wpstream_portal_mono classes on the Duration
 * and Bitrate value cells, the health-dot span, and the
 * data-wpstream-portal-bc-stat hooks ("viewers" / "duration" / "bitrate" /
 * "health") that broadcaster.js writes live values into. When the channel
 * has no QoS data, all four cells render the translated NA label.
 * broadcaster.js overwrites these once the QoS socket reports,
 * moves them to the loading label while a meter connects, and restores NA on disconnect.
 *
 * @return void Echoes the stats-grid HTML directly.
 */
function wpstream_plugin_render_bc_stats_grid() {
    ?>
                <div class="wpstream_portal_bc_stats">

                    <?php /* Stat — Viewers now */ ?>
                    <div class="wpstream_portal_bc_stat">
                        <div class="wpstream_portal_bc_stat_l">
                            <span class="wpstream_portal_bc_stat_ico wpstream_portal_bc_stat_ico_live">
                                <?php /* I.Eye — d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" */ ?>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>
                            </span>
                            <span><?php esc_html_e( 'Viewers now', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span>
                        </div>
                        <?php /* NA until a meter is in use; a reported zero remains a real value. */ ?>
                        <div class="wpstream_portal_bc_stat_v" data-wpstream-portal-bc-stat="viewers"><?php esc_html_e( 'NA', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></div>
                    </div>

                    <?php /* Stat — Duration */ ?>
                    <div class="wpstream_portal_bc_stat">
                        <div class="wpstream_portal_bc_stat_l">
                            <span class="wpstream_portal_bc_stat_ico wpstream_portal_bc_stat_ico_cyan">
                                <?php /* I.Activity — d="M22 12h-4l-3 9L9 3l-3 9H2" */ ?>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            </span>
                            <span><?php esc_html_e( 'Duration', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span>
                        </div>
                        <?php /* NA until a meter is in use; the QoS start time then drives the duration ticker. */ ?>
                        <div class="wpstream_portal_bc_stat_v wpstream_portal_mono" data-wpstream-portal-bc-stat="duration"><?php esc_html_e( 'NA', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></div>
                    </div>

                    <?php /* Stat — Bitrate */ ?>
                    <div class="wpstream_portal_bc_stat">
                        <div class="wpstream_portal_bc_stat_l">
                            <span class="wpstream_portal_bc_stat_ico wpstream_portal_bc_stat_ico_success">
                                <?php /* I.Wifi — d="M5 13a10 10 0 0 1 14 0M8.5 16.4a5 5 0 0 1 7 0M2 8.8a15 15 0 0 1 20 0M12 20h.01" */ ?>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13a10 10 0 0 1 14 0M8.5 16.4a5 5 0 0 1 7 0M2 8.8a15 15 0 0 1 20 0M12 20h.01"/></svg>
                            </span>
                            <span><?php esc_html_e( 'Bitrate', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span>
                        </div>
                        <?php /* NA until a meter is in use; a reported zero bitrate is still a real sample. */ ?>
                        <div class="wpstream_portal_bc_stat_v wpstream_portal_mono" data-wpstream-portal-bc-stat="bitrate"><?php esc_html_e( 'NA', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></div>
                    </div>

                    <?php /* Stat — Health */ ?>
                    <div class="wpstream_portal_bc_stat">
                        <div class="wpstream_portal_bc_stat_l">
                            <?php /* data-wpstream-portal-bc-health-ico — broadcaster.js swaps the _neutral/_success/_warn/_danger modifier on this span to recolor the dot per the live health rating. */ ?>
                            <span class="wpstream_portal_bc_stat_ico wpstream_portal_bc_stat_ico_neutral" data-wpstream-portal-bc-health-ico>
                                <span class="wpstream_portal_bc_stat_health_dot">&#x25CF;</span>
                            </span>
                            <span><?php esc_html_e( 'Health', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span>
                        </div>
                        <?php /* NA and a neutral dot until a meter is in use; the runtime restores this label on reset. */ ?>
                        <div class="wpstream_portal_bc_stat_v" data-wpstream-portal-bc-stat="health"><?php esc_html_e( 'NA', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></div>
                    </div>

                </div>
    <?php
}

/**
 * Echo one read-only "share row" (value span + copy button).
 *
 * The same .wpstream_portal_share_row + .wpstream_portal_share_row_val + copy
 * button + 12×12 copy-icon SVG markup is repeated five times in the
 * broadcaster card (External-panel RTMP URL + Stream key, plus the encoder
 * modal's Larix RTMP, encoder URI and encoder key rows). This helper emits
 * exactly that shared shape so all five stay in lock-step.
 *
 * The inline `style` values (padding:8px 10px; on the row, font-size:11.5px;
 * on the value span) are preserved verbatim — CSS extraction is a separate
 * refactor. The third parameter optionally adds a bare data-attribute on the
 * value span; the modal rows need it (data-wpstream-portal-larix-rtmp /
 * -encoder-uri / -encoder-key) so dashboard-2026.js can refresh each value
 * live on channel change, while the External-panel rows pass '' (no span
 * attribute, matching their original markup).
 *
 * @param string $display_value Already-escaped (or plain) text shown in the
 *                              value span. Passed through esc_html() here.
 * @param string $copy_value    Raw value written to data-wpstream-portal-copy-value
 *                              (escaped here with esc_attr()).
 * @param string $val_data_attr Optional bare data-attribute name to add on
 *                              the value span (e.g. 'data-wpstream-portal-encoder-uri').
 *                              Empty string adds nothing. Default ''.
 * @return void Echoes the share-row HTML directly.
 */
function wpstream_plugin_render_bc_share_row( $display_value, $copy_value, $val_data_attr = '' ) {
    ?>
                    <div class="wpstream-bc-share-compact wpstream_portal_share_row">
                        <span class="wpstream_portal_share_row_val wpstream-bc-share-value"<?php echo ( '' !== $val_data_attr ) ? ' ' . $val_data_attr : ''; ?>><?php echo esc_html( $display_value ); ?></span>
                        <button class="wpstream_portal_copy_btn" data-wpstream-portal-copy="" data-wpstream-portal-copy-value="<?php echo esc_attr( $copy_value ); ?>">
                            <?php /* I.Copy size=12 */ ?>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M16 1H4a2 2 0 0 0-2 2v12h2V3h12V1Zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm0 16H8V7h11v14Z"/></svg>
                            <?php esc_html_e( 'Copy', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                        </button>
                    </div>
    <?php
}

/**
 * Echo the interactive channel dropdown (button + popover of channels).
 *
 * Single source of the picker markup, used both inside the card head and by
 * the detached seam below. broadcaster.js (channelSelect) binds to it by id,
 * so it works wherever the host places it. Ids are unique per page: render
 * the dropdown once.
 *
 * @param array $streams         Channel rows ('id', 'title', 'domain').
 * @param array $default_channel The pre-selected row ('id', 'title').
 * @return void Echoes the dropdown HTML directly.
 */
function wpstream_plugin_render_bc_channel_dropdown( array $streams, array $default_channel ) {
    ?>
                <div class="wpstream_portal_channel_select" id="wpstream_portal_channel_select_wrap">
                    <button
                        class="wpstream_portal_channel_select_btn"
                        id="wpstream_portal_channel_select_btn"
                        aria-haspopup="listbox"
                        aria-expanded="false"
                    >
                        <span class="wpstream_portal_channel_select_name" id="wpstream_portal_channel_select_name">
                            <?php echo esc_html( $default_channel['title'] ); ?>
                        </span>
                        <?php /* I.ChevDown — d="m6 9 6 6 6-6" */ ?>
                        <svg class="wpstream_portal_channel_select_chev" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>

                    <div class="wpstream-bc-hidden wpstream_portal_popover wpstream_portal_channel_popover" id="wpstream_portal_channel_popover" data-wpstream-portal-popover="wpstream_portal_channel_popover" role="listbox">
                        <?php /* Repeat the existing create action before the channel list for long menus. */ ?>
                        <div class="wpstream_portal_channel_popover_head">
                            <button type="button" class="wpstream_portal_channel_create">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                <?php esc_html_e( 'New live stream', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                            </button>
                        </div>
                        <?php foreach ( $streams as $stream ) : ?>
                            <button
                                class="wpstream_portal_channel_option<?php echo ( $stream['id'] === $default_channel['id'] ) ? ' wpstream_portal_channel_option_selected' : ''; ?>"
                                data-wpstream-portal-channel-id="<?php echo esc_attr( $stream['id'] ); ?>"
                                data-wpstream-portal-channel-title="<?php echo esc_attr( $stream['title'] ); ?>"
                                <?php /* Channel's domain — part of baker's channel identity. broadcaster.js reads this off the selected option to POST the correct domain for cross-domain channels; dashboard-2026.js reads it on pick. Guard for rows that predate the domain field → ''. */ ?>
                                data-wpstream-portal-channel-domain="<?php echo esc_attr( isset( $stream['domain'] ) ? $stream['domain'] : '' ); ?>"
                                role="option"
                                aria-selected="<?php echo ( $stream['id'] === $default_channel['id'] ) ? 'true' : 'false'; ?>"
                            >
                                <?php /* Option label is a two-line column: the channel title on the
                                        primary line and the channel's domain on a smaller, muted
                                        secondary line beneath it. Many channels share the same title
                                        (e.g. "Channel #6") across different domains, so the domain
                                        disambiguates them in the picker. */ ?>
                                <span class="wpstream_portal_channel_option_text">
                                    <span class="wpstream_portal_channel_option_name"><?php echo esc_html( $stream['title'] ); ?></span>
                                    <?php /* Guard the domain line for older baker rows that predate the
                                            domain field (mirrors the isset() guard on the data-attribute
                                            above) — render nothing when the domain is empty/missing. */ ?>
                                    <?php if ( ! empty( $stream['domain'] ) ) : ?>
                                        <span class="wpstream_portal_channel_option_domain"><?php echo esc_html( $stream['domain'] ); ?></span>
                                    <?php endif; ?>
                                </span>
                            </button>
                        <?php endforeach; ?>
                        <div class="wpstream_portal_channel_popover_foot">
                            <button type="button" class="wpstream_portal_channel_create">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                <?php esc_html_e( 'New live stream', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                            </button>
                        </div>
                    </div>
                </div>
    <?php
}

/**
 * Public seam: echo the channel picker DETACHED from the broadcaster card.
 *
 * Lets a host place the picker in its own slot (the dashboard puts it above
 * the Broadcast / Statistics / Channel settings tabs) while the markup, CSS
 * and JS stay module-owned. Pair it with 'channel_select' => 'detached' on
 * wpstream_plugin_render_broadcaster() so the card head does not print a
 * second copy. Pure view: no data access, reads only the two args below
 * (pass the same values given to the card).
 *
 * @param array $args {
 *     @type array $streams         Channel rows ('id', 'title', 'domain').
 *     @type array $default_channel The pre-selected row ('id', 'title').
 * }
 * @return void Echoes the picker HTML directly.
 */
function wpstream_plugin_render_broadcaster_channel_select( array $args ) {

    // Same safe defaults prepare() applies, so an incomplete payload renders.
    $streams         = isset( $args['streams'] ) && is_array( $args['streams'] ) ? $args['streams'] : array();
    $default_channel = isset( $args['default_channel'] ) && is_array( $args['default_channel'] ) ? $args['default_channel'] : array( 'id' => '', 'title' => '' );

    // Wrapper class scopes the module's base resets (broadcaster.css) to the
    // picker now that it sits outside the .wpstream_portal_broadcaster root.
    ?>
    <div class="wpstream_portal_bc_channel_picker">
        <span class="wpstream_portal_bc_head_label"><?php esc_html_e( 'Channel:', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span>
        <?php wpstream_plugin_render_bc_channel_dropdown( $streams, $default_channel ); ?>
    </div>
    <?php
}

/**
 * Build the broadcaster view-model from the caller's $args.
 *
 * This is the ONLY broadcaster function that performs data access — it is
 * the single place that calls baker (wpstream_portal_channel_info),
 * reads get_user_meta() and resolves get_current_user_id(). Splitting the
 * fetch out of the render function
 * means wpstream_plugin_render_broadcaster() can stay a pure view template
 * (it consumes only the returned array) and the data layer can be reused or
 * relocated independently.
 *
 * Steps performed here (all moved verbatim from the former monolithic render
 * function — same baker calls, same args, same empty-string-on-failure
 * fallbacks, same function_exists guards):
 *   - normalise the caller's $args (streams / default_channel / embed_code /
 *     lock_channel) into safe defaults;
 *   - resolve the default channel id;
 *   - fetch the channel record via wpstream_portal_channel_info() and unwrap
 *     the standard { success, result.all_results[0] } envelope;
 *   - hoist status / whipUrl / qos_url_data / embedUrl to flat values;
 *   - parse broadcast_url into obs_uri (base RTMP URL) + obs_stream (key);
 *   - read the per-user camera-preview preference;
 *   - read the user's available data quota for the Phase 0 gate.
 *
 * Expected $args shape:
 *   - 'streams'         (array)  List of stream rows, each with keys
 *                                'id', 'title', 'status', 'type'. Used to
 *                                populate the channel selector popover.
 *   - 'default_channel' (array)  The pre-selected stream row (same shape as
 *                                a single 'streams' entry). Drives the
 *                                button label + the "selected" check icon.
 *   - 'embed_code'      (string) Raw iFrame snippet to display + copy. Will
 *                                be escaped before output; the caller is
 *                                responsible for shape/contents.
 *   - 'content_id'      (string) Optional. The contentId string from baker's
 *                                /channel/info answer. Shown + copied by the
 *                                Astro Code + Shopify code + Wix code tabs of
 *                                the embed card. Defaults to '' (panels render
 *                                empty).
 *   - 'lock_channel'    (bool)   Optional. When true the channel selector
 *                                renders as static text. Defaults to false.
 *   - 'channel_select'  (string) Optional. 'detached' when the host renders
 *                                the picker outside the card via
 *                                wpstream_plugin_render_broadcaster_channel_select().
 *   - 'asset_url'      (string) Optional. Public base URL of the module's
 *                                assets folder (holds broadcaster.css and
 *                                broadcaster.js, flat). Defaults to
 *                                <stylesheet uri>/dashboard-files/broadcaster/.
 *                                The plugin host passes a plugins_url()
 *                                based value. Always trailing-slashed.
 *   - 'asset_dir'       (string) Optional. Filesystem path of the same
 *                                assets folder, used for filemtime()
 *                                cache-busting. Defaults to
 *                                <stylesheet dir>/dashboard-files/broadcaster/.
 *                                Always trailing-slashed.
 *
 * @param  array $args  See expected shape above.
 * @return array        Flat view-model consumed by
 *                      wpstream_plugin_broadcaster_enqueue() and
 *                      wpstream_plugin_render_broadcaster(). Keys:
 *                      streams, default_channel, embed_code, lock_channel,
 *                      channel_select_detached, channel_id, channel_domain, is_channel_live, whip_url,
 *                      qos_url_data, obs_uri, obs_stream, embed_url,
 *                      asset_url, asset_dir.
 */
function wpstream_plugin_broadcaster_prepare( array $args ) {

    /* ------------------------------------------------------------------
       Step 1 — Normalise inputs. Fall back to safe empty values so the
       widget can render even if the caller passes an incomplete payload
       (defensive; keeps the demo states from fatal-ing during dev).
       ------------------------------------------------------------------ */
    $streams         = isset( $args['streams'] )         && is_array( $args['streams'] )         ? $args['streams']         : array();
    $default_channel = isset( $args['default_channel'] ) && is_array( $args['default_channel'] ) ? $args['default_channel'] : array( 'id' => '', 'title' => '' );
    $embed_code      = isset( $args['embed_code'] )      ? (string) $args['embed_code']                                     : '';

    // contentId string from baker's /channel/info answer. Powers the
    // "Astro Code" + "Shopify code" + "Wix code" tabs of the embed-code card
    // (all display the same raw contentId). Normalised exactly like
    // $embed_code above; empty string when baker didn't return it (or
    // local-DB path), in which case those tab panels render empty and copy
    // nothing.
    $content_id      = isset( $args['content_id'] )      ? (string) $args['content_id']                                     : '';

    // When true, the channel selector is rendered as static text instead of
    // the interactive dropdown. Set by the Channel Detail screen, which is
    // already scoped to a single channel.
    $lock_channel    = ! empty( $args['lock_channel'] );

    // Asset base seam (AGENTS.md rule 2). The module's CSS/JS location is
    // injectable so the same module can be lifted into the WPStream plugin,
    // whose host will pass plugins_url() / plugin_dir_path() based values.
    // Without injection the assets load from the module's own folder
    // (dashboard-files/broadcaster/, flat: the module ships as one folder).
    // These two defaults
    // are the ONLY get_stylesheet_directory*() calls left in the module.
    $asset_url = isset( $args['asset_url'] ) && '' !== $args['asset_url']
        ? trailingslashit( (string) $args['asset_url'] )
        : WPSTREAM_PLUGIN_DIR_URL . 'broadcaster/';
    $asset_dir = isset( $args['asset_dir'] ) && '' !== $args['asset_dir']
        ? trailingslashit( (string) $args['asset_dir'] )
        : WPSTREAM_PLUGIN_PATH . 'broadcaster/';

    // Resolve the default channel ID. $channel_id is baker's per-user
    // channel_id (the local sequence "1", "2", …). The whole turn-on
    // flow uses this value end-to-end: broadcaster JS POSTs it, the
    // AJAX handlers pass it straight to baker /channel/info /
    // /channel/start / /channel/stop.
    $channel_id = isset( $default_channel['id'] ) ? $default_channel['id'] : '';

    // Resolve the default channel's DOMAIN. The domain is part of baker's
    // channel identity (ch_wps_<user_id>_<domain_hash>_<channel_id>), so a
    // channel created under a customer's own WordPress site — e.g. by the
    // WPStream plugin — cannot be resolved by passing the literal
    // 'wpstream.net'. Source it from the selected channel row; fall back to
    // 'wpstream.net' for portal-native channels (and any row that predates
    // the domain field) so existing behaviour is preserved.
    $channel_domain = isset( $default_channel['domain'] ) && '' !== $default_channel['domain']
        ? (string) $default_channel['domain']
        : wpstream_broadcaster_domain();

    // Phase 1 moved to page-load. Fetch the default channel's record
    // server-side and localize the fields the JS would otherwise pull
    // via AJAX on every Start click. Saves one round-trip on the cold
    // path; the dropdown change handler re-fires
    // wpstream_dashboard_check_event_status AJAX-side to refresh these
    // when the user picks a different channel.
    //
    // wpstream_portal_channel_info returns the standard envelope
    // { success, result: { all_results: [ row ] }, num_rows } or
    // { success: false, error: '...' }. We hoist the fields the JS
    // reads (status, whipUrl, obs_uri, obs_stream) to top level —
    // empty strings on failure so the broadcaster card still renders.
    $channel_info_row = wpstream_broadcaster_status( (int) $channel_id );
    if ( empty( $channel_info_row['success'] ) ) { $channel_info_row = array(); }

    $is_channel_live = isset( $channel_info_row['status'] )   ? (string) $channel_info_row['status']   : '';
    $whip_url        = isset( $channel_info_row['whipUrl'] )  ? (string) $channel_info_row['whipUrl']  : '';

    // qos_url_data — baker's chandler meter socket URL
    // (wss://chandler.wpstream.live/meter/<eventId>/<key>). Drives the live
    // Viewers + Health stats in the LIVE side panel. broadcaster.js opens
    // this socket on go-live, sends {"req":"getQos"} and reads bwHistory from
    // the reply. Empty on the local-DB stub path (no real chandler locally) —
    // the JS guards on an empty URL and simply skips the QoS stats.
    $qos_url_data    = isset( $channel_info_row['qos_url_data'] ) ? (string) $channel_info_row['qos_url_data'] : '';

    // RTMP URL + stream key — derived from baker's broadcast_url field.
    // Format: rtmp://<host>/<app>/<key> — RTMP URL = everything up to the
    // last '/', stream key = everything after. Mirrors the plugin's parser
    // at class-wpstream-live-api-connection.php:298-300. broadcast_url is
    // the single source of truth; obs_uri / obs_stream postmeta keys are
    // no longer read here. If baker doesn't return broadcast_url, both
    // stay empty and the cells show the "No channel selected" placeholder.
    $broadcast_url = isset( $channel_info_row['broadcast_url'] ) ? (string) $channel_info_row['broadcast_url'] : '';
    $obs_uri       = '';
    $obs_stream    = '';
    if ( $broadcast_url !== '' ) {
        $last_slash = strrpos( $broadcast_url, '/' );
        if ( $last_slash !== false ) {
            $obs_uri    = substr( $broadcast_url, 0, $last_slash );
            $obs_stream = substr( $broadcast_url, $last_slash + 1 );
        }
    }
    // embedUrl — populated by wpstream_portal_channel_info() which now
    // passes embed=true to baker (see api/channel_event_db_functions.php:328).
    // On prod this gives the JS a ready-to-use iframe URL for the
    // External-encoder preview surface. On dev/local where baker may stub
    // it out, $embed_url stays empty → the iframe stays hidden, no harm.
    $embed_url       = isset( $channel_info_row['embedUrl'] ) ? (string) $channel_info_row['embedUrl'] : '';
    $content_id = (string) ( $channel_info_row['contentId'] ?? $content_id );
    if ( '' === $embed_code && '' !== $embed_url ) {
        $embed_code = '<iframe src="' . esc_url( $embed_url ) . '" width="100%" height="480" allowfullscreen></iframe>';
    }

    /* ------------------------------------------------------------------
       Return the flat view-model. Every value the enqueue + markup need
       is resolved here; downstream functions perform NO data access.
       ------------------------------------------------------------------ */
    return array(
        'streams'                => $streams,
        'default_channel'        => $default_channel,
        'embed_code'             => $embed_code,
        'content_id'             => $content_id,
        'lock_channel'           => $lock_channel,
        // True when the host prints the channel picker itself (detached
        // above its own chrome); render() then leaves it out of the head.
        'channel_select_detached' => isset( $args['channel_select'] ) && 'detached' === $args['channel_select'],
        'channel_id'             => $channel_id,
        // Default channel's domain — carried through so enqueue() can localise
        // it (channel_domain) and broadcaster.js has the domain for the
        // pre-selected channel on cold load (before any dropdown pick).
        'channel_domain'         => $channel_domain,
        'is_channel_live'        => $is_channel_live,
        'record'                 => in_array( $channel_info_row['record'] ?? false, array( true, 1, '1', 'true' ), true ),
        'actions'                 => $args['actions'] ?? array(),
        'whip_url'               => $whip_url,
        'qos_url_data'           => $qos_url_data,
        'obs_uri'                => $obs_uri,
        'obs_stream'             => $obs_stream,
        'embed_url'              => $embed_url,
        // Asset base (URL + filesystem dir), trailing-slashed. enqueue()
        // builds every module asset src/path from these two values.
        'asset_url'              => $asset_url,
        'asset_dir'              => $asset_dir,
    );
}

/**
 * Register + enqueue the broadcaster runtime scripts and localise the
 * wpstream_broadcaster_vars payload from a prepared view-model.
 *
 * Split out of the render function so the script wiring is isolated from the
 * markup, but deliberately STILL invoked from the render flow (see
 * wpstream_plugin_render_broadcaster) rather than from a global
 * wp_enqueue_scripts hook — the assets must only ship on pages that actually
 * render the widget, and the localised payload is channel-scoped to whatever
 * the render call resolved. The timing is unchanged from before the split.
 *
 * The render function may be called more than once on a single page (e.g.
 * Dashboard widget + Channel Detail mount). Both wp_register_script and the
 * localised vars use the same handle, so calling this twice is a no-op (WP
 * de-dupes by handle).
 *
 * @param  array $vm  View-model from wpstream_plugin_broadcaster_prepare().
 *                    Reads: obs_uri, obs_stream, channel_id, channel_domain,
 *                    is_channel_live, whip_url, qos_url_data, embed_url,
 *                    asset_url, asset_dir.
 * @return void
 */
function wpstream_plugin_broadcaster_enqueue( array $vm ) {

    // Step 2.0 — Broadcaster stylesheet. Self-contained sheet holding every
    // rule that styles the broadcaster card (extracted from dashboard-2026.css
    // in refactor #5). Registered with 'wpstream_dashboard_2026' as a
    // dependency so it always loads AFTER the dashboard sheet — the broadcaster
    // rules + the duplicate :root token block then resolve to the same computed
    // values as before the extraction (identical token values, broadcaster
    // rules unopposed). filemtime() cache-bust so dev edits invalidate
    // immediately. wp_register_style is gated by wp_style_is(..., 'registered')
    // so a second render call on the same page is a no-op (WP also de-dupes by
    // handle on enqueue).
    // Path + URL come from the injected asset base (prepare() asset_dir /
    // asset_url) so a plugin host can serve the sheet from its own folder.
    $broadcaster_css_path = $vm['asset_dir'] . 'broadcaster.css';
    // The host dashboard sheet is an OPTIONAL dependency. WP silently drops a
    // handle whose dependency is not registered, and the plugin host has no
    // 'wpstream_dashboard_2026'. broadcaster.css ships its own :root tokens,
    // so it renders correctly without it. On the portal the handle is
    // registered and the load order stays exactly as before.
    // Register the shared brand even when this standalone module renders first.
    wp_register_style( 'wpstream-brand', WPSTREAM_PLUGIN_DIR_URL . 'public/css/wpstream-brand.css', array(), filemtime( WPSTREAM_PLUGIN_PATH . 'public/css/wpstream-brand.css' ) );
    $css_deps = array( 'wpstream-brand' );
    if ( ! wp_style_is( 'wpstream-plugin-broadcaster', 'registered' ) ) {
        wp_register_style(
            'wpstream-plugin-broadcaster',
            $vm['asset_url'] . 'broadcaster.css',
            $css_deps,
            file_exists( $broadcaster_css_path ) ? filemtime( $broadcaster_css_path ) : false
        );
    }
    wp_enqueue_style( 'wpstream-plugin-broadcaster' );

    // Step 2a — Underscore (WP core has it registered already, just
    // enqueue). Underscore is referenced by the OvenLiveKit init path
    // in some templating helpers, so keep the dependency explicit.
    wp_enqueue_script( 'underscore' );

    // Step 2b — OvenLiveKit from the same CDN URL the official plugin
    // uses. `null` version because the CDN URL itself is versionless
    // ("@latest"); footer load so the broadcaster markup is in the DOM
    // before the script runs.
    if ( ! wp_script_is( 'wpstream-plugin-ovenlivekit', 'registered' ) ) {
        wp_register_script(
            'wpstream-plugin-ovenlivekit',
            'https://cdn.jsdelivr.net/npm/ovenlivekit@latest/dist/OvenLiveKit.min.js',
            array(),
            null,
            true
        );
    }
    wp_enqueue_script( 'wpstream-plugin-ovenlivekit' );

    // Step 2c — The dashboard broadcaster JS clone. Depends on
    // underscore + OvenLiveKit so it boots after both globals exist.
    // filemtime() cache-bust so dev edits invalidate immediately.
    // Path + URL come from the injected asset base, same seam as the CSS.
    $broadcaster_js_path = $vm['asset_dir'] . 'broadcaster.js';
    // wpstream-portal-telemetry provides window.wpstream_track_onboarding_step
    // (the shim broadcaster.js calls via trackOnboardingStepSafe); declaring it
    // a dependency guarantees it loads alongside the broadcaster on every surface.
    // It is added ONLY when the host registered it: WP silently drops a handle
    // whose dependency is not registered, and the plugin host has no telemetry
    // handle. broadcaster.js already guards every telemetry call
    // (trackOnboardingStepSafe / wpstreamTrackQos seams), so it runs without it.
    wp_register_script( 'wpstream-qrcode-generator', WPSTREAM_PLUGIN_DIR_URL . 'public/js/vendor/qrcode-generator.js', array(), '1.4.4', true );
    $js_deps = array( 'underscore', 'wpstream-plugin-ovenlivekit', 'wpstream-qrcode-generator' );

    if ( ! wp_script_is( 'wpstream-plugin-broadcaster', 'registered' ) ) {
        wp_register_script(
            'wpstream-plugin-broadcaster',
            $vm['asset_url'] . 'broadcaster.js',
            $js_deps,
            file_exists( $broadcaster_js_path ) ? filemtime( $broadcaster_js_path ) : false,
            true
        );
    }
    wp_enqueue_script( 'wpstream-plugin-broadcaster' );

    // Step 2d — Localise the vars the JS expects on window. Shape
    // mirrors the plugin's broadcaster-template.php exactly so the
    // cloned JS finds every key it reads. `nonce` here uses the
    // `wpstream_broadcaster_nonce` action to match the plugin payload;
    // the JS additionally reads a separate `wpstream_start_event_nonce`
    // value from a hidden <input> below (same scheme as the plugin),
    // which is the one actually verified by the AJAX handlers in
    // broadcaster-ajax.php.
    wp_localize_script(
        'wpstream-plugin-broadcaster',
        'wpstream_broadcaster_vars',
        array(
            'ajax_url'              => admin_url( 'admin-ajax.php' ),
            'nonce'                 => wp_create_nonce( 'wpstream_broadcaster_nonce' ),
            // Unused by broadcaster.js; kept for payload parity with the
            // plugin's broadcaster-template.php. Carries the injected asset
            // base so no theme URL leaks when a plugin host injects its own.
            'plugin_url'            => $vm['asset_url'],
            'obs_uri'               => (string) $vm['obs_uri'],
            'obs_stream'            => (string) $vm['obs_stream'],
            'channel_id'            => $vm['channel_id'],
            // Default channel's domain — part of baker's channel identity.
            // broadcaster.js seeds its active-domain from this on cold load and
            // includes it in the check_event_status / give_me_live_uri POSTs so
            // baker can resolve channels created under ANY domain the user owns
            // (e.g. a customer's own WordPress site via the WPStream plugin),
            // not just wpstream.net. The dropdown change handler in
            // dashboard-2026.js overwrites this when the user picks a different
            // channel. Falls back to 'wpstream.net' via prepare().
            'channel_domain'        => (string) $vm['channel_domain'],
            'is_channel_live'       => (string) $vm['is_channel_live'],
            'record'                => $vm['record'],
            'actions'                 => $vm['actions'],
            'broadcast_connection_failed' => __( 'Could not connect to the streaming server. The broadcast did not start. Please try again.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'recording_strings'     => array(
                'on'      => __( 'Recording on', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
                'off'     => __( 'Recording off', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
                'saved'   => __( 'Saved to All Videos when you end.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
                'unsaved' => __( 'This broadcast won’t be saved.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
                'failed'  => __( 'Could not save recording. Try again while the channel is stopped.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            ),
            // Phase 1 page-load values — set by the server-side
            // wpstream_portal_channel_info() call in prepare(). broadcaster.js
            // reads these synchronously at the top of startStreaming()
            // instead of firing the Phase 1 AJAX round-trip. The
            // dashboard dropdown change handler in dashboard-2026.js
            // refreshes these via AJAX (wpstream_dashboard_check_event_status)
            // when the user picks a different channel.
            'channel_status'        => (string) $vm['is_channel_live'],
            'whip_url'              => (string) $vm['whip_url'],
            // QoS meter socket URL for the live Viewers + Health stats.
            // broadcaster.js opens this on go-live; the dropdown change
            // handler refreshes it via wpstream_dashboard_check_event_status
            // (the poll flattens qos_url_data to the top level). Empty when
            // baker doesn't return it (local stub) → JS skips QoS stats.
            'qos_url_data'          => (string) $vm['qos_url_data'],
            // Embed URL for the External-encoder preview iframe. JS reads
            // this on encoder-tab click (mode === 'external') and sets it
            // as the iframe src on demand — kept empty in markup so the
            // iframe doesn't pre-load on every dashboard render. When the
            // dropdown picks a different channel, dashboard-2026.js
            // refreshes this value via wpstream_dashboard_check_event_status.
            'embed_url'             => (string) $vm['embed_url'],
            'no_video_audio_access' => esc_html__( 'We couldn’t access your camera or microphone. Please allow permissions and reload the page.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'no_audio_access'       => esc_html__( 'We couldn’t access your microphone. Please allow permissions and reload the page.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'no_video_access'       => esc_html__( 'We couldn’t access your camera. Please allow permissions and reload the page.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            // Shown when the page has no camera API at all: browsers expose it
            // only on https (or localhost), so no permission can fix a plain
            // http site. Streaming software still works there over RTMP.
            'insecure_origin'       => esc_html__( 'Browser broadcasting needs a secure (https) connection. Open this page over https, or use Streaming software.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'channel_off'           => esc_html__( 'Invalid event. Your live event may have expired or its credentials are incorrect.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'is_onboarding'           => false,

            // ----------------------------------------------------------
            // Cycle #6d — turning-on UI state copy.
            // Three short strings consumed by broadcaster.js's
            // setTurningOnUI() helper to render the indicator label, the
            // Start button label, and the message-overlay text while the
            // turn-on flow is in Phase 1 / 2 / 3. See
            // dashboard-files/screens/components/broadcaster-turn-on-flow.html
            // "UI state machine during turn-on" notes block for the
            // contract these three keys satisfy.
            // ----------------------------------------------------------
            'starting_channel'        => esc_html__( 'Starting channel…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'starting_label'          => esc_html__( 'Starting…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'connecting_label'        => esc_html__( 'Connecting…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'waiting_label'           => esc_html__( 'Please wait…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'checking_channel_label'  => esc_html__( 'Checking channel…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'creating_channel_label'  => esc_html__( 'Preparing stream…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'waiting_channel_label'   => esc_html__( 'Waiting for channel…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'startup_timeout_message' => esc_html__( 'Broadcast startup timed out. Please try again.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'stats_loading_label'     => esc_html__( 'Loading…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'stats_na_label'          => esc_html__( 'NA', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
            'preparing_channel'       => esc_html__( 'Preparing your channel…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
        )
    );
}

/**
 * Render the Browser Broadcaster widget.
 *
 * Thin view template. It performs NO data fetching of its own — all data
 * access lives in wpstream_plugin_broadcaster_prepare(), and the script
 * register/enqueue/localise lives in wpstream_plugin_broadcaster_enqueue().
 * This function is now a three-step orchestrator:
 *
 *   1. $vm = wpstream_plugin_broadcaster_prepare( $args )  — fetch + normalise;
 *   2. wpstream_plugin_broadcaster_enqueue( $vm )          — scripts + localise
 *                                                            (kept in the render
 *                                                            flow so assets only
 *                                                            ship when the widget
 *                                                            renders);
 *   3. echo the card markup using ONLY $vm values.
 *
 * The public signature is unchanged so the two existing callers
 * (dashboard.php, channel-detail.php) need no edits. The `static`
 * single-emission guards for the hidden start-event nonce input and the
 * encoder-setup modal live with the markup, below.
 *
 * Expected $args shape:
 *   - 'streams'         (array)  List of stream rows ('id','title','status','type').
 *   - 'default_channel' (array)  Pre-selected stream row (same shape).
 *   - 'embed_code'      (string) Raw iFrame snippet to display + copy.
 *   - 'content_id'      (string) Optional. contentId for the Astro/Shopify/Wix tabs.
 *   - 'lock_channel'    (bool)   Optional. Static channel name when true.
 *   - 'channel_select'  (string) Optional. 'detached' = the host prints the
 *                                picker itself via
 *                                wpstream_plugin_render_broadcaster_channel_select(),
 *                                so the head omits it. Ignored when lock_channel.
 *
 * @param  array $args  See expected shape above.
 * @return void         Echoes the broadcaster HTML directly.
 */
function wpstream_plugin_render_broadcaster( array $args ) {

    /* ------------------------------------------------------------------
       Step 1 — Build the view-model (the only data-access pass). Step 2 —
       wire + localise the runtime scripts (kept in the render flow so the
       assets only ship when the widget renders). Both delegate to the two
       sibling functions above; this function consumes only $vm.
       ------------------------------------------------------------------ */
    $vm = wpstream_plugin_broadcaster_prepare( $args );
    wpstream_plugin_broadcaster_enqueue( $vm );

    // Local aliases for the markup below — keeps the echo blocks identical
    // to the pre-split version (which referenced bare $streams / $obs_uri /
    // … locals). No data access happens here; these are pure reads off $vm.
    $streams                = $vm['streams'];
    $default_channel        = $vm['default_channel'];
    $embed_code             = $vm['embed_code'];
    $content_id             = $vm['content_id'];
    $lock_channel           = $vm['lock_channel'];
    $channel_select_detached = $vm['channel_select_detached'];
    $obs_uri                = $vm['obs_uri'];
    $obs_stream             = $vm['obs_stream'];

    /* ------------------------------------------------------------------
       Step 3 — Output the broadcaster card. Markup is byte-for-byte from
       the prototype (project/screen-dashboard.jsx BrowserBroadcaster +
       BcEmbed). Do NOT reinterpret class names, SVG paths or copy text;
       those are the design lock that CSS + JS rely on.
       ------------------------------------------------------------------ */

    // Single-emission gate for the hidden start-event nonce input. The
    // render function may be called more than once per page (Dashboard
    // + /go-live in future), and we want exactly one #wpstream_start_event_nonce
    // input in the DOM regardless — the JS reads by ID.
    static $start_event_nonce_printed = false;
    ?>
    <div class="wpstream_portal_broadcaster">

        <?php
        /* Hidden nonce input — broadcaster.js reads this by ID
           (see jQuery('#wpstream_start_event_nonce').val() inside
           checkChannelStatus) and forwards it as the `nonce` POST var to
           the three AJAX handlers in broadcaster-ajax.php, which verify
           via check_ajax_referer(WPSTREAM_PLUGIN_BC_NONCE, 'nonce').
           Static guard prevents a double-emission if the render
           function runs twice on the same page. */
        if ( ! $start_event_nonce_printed ) :
            $start_event_nonce_printed = true;
            ?>
            <input type="hidden" id="wpstream_start_event_nonce" value="<?php echo esc_attr( wp_create_nonce( WPSTREAM_PLUGIN_BC_NONCE ) ); ?>">
            <?php
        endif;
        ?>

        <?php /* -- Broadcaster head: "Channel:" label + channel select (or the
               static channel name on lock_channel pages). The head now holds
               only this one group, so it is printed only when that group has
               something to show: a locked channel always does; an unlocked
               widget does only when the host did NOT render the picker
               detached ('channel_select' => 'detached', e.g. the dashboard,
               which therefore gets no head at all). The Browser / External
               mode choice lives in the full-width source-choice block below. -- */ ?>
        <?php if ( $lock_channel || ! $channel_select_detached ) : ?>
        <div class="wpstream_portal_broadcaster_head">
            <div class="wpstream-bc-gap-small wpstream_portal_row">
                <span class="wpstream_portal_bc_head_label"><?php esc_html_e( 'Channel:', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span>
                <?php if ( $lock_channel ) : ?>
                    <?php /* Channel Detail page: the page is locked to one
                           channel, so the selector would be meaningless.
                           Render the channel name as static text instead of
                           the interactive button + popover. */ ?>
                    <span class="wpstream_portal_channel_select_name"><?php echo esc_html( $default_channel['title'] ); ?></span>
                <?php else : ?>
                    <?php wpstream_plugin_render_bc_channel_dropdown( $streams, $default_channel ); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php /* -- Source choice: "How do you want to go live?" --
               Ported from the blueprint (plugins/plugin/tests/work-on-blueprints-2/
               broadcast-tool.html, #source-choice). Two cards above the tool pick
               the mode; the tint of this block and of the body below follows the
               picked card (purple = this browser, teal = streaming app; CSS keys
               on aria-pressed, see broadcaster.css "SOURCE CHOICE").
               Contract kept from the old pill tabs: each card is a button with
               class wpstream_portal_encoder_tab + data-wpstream-portal-encoder-tab
               (browser / external) and .active on the selected one. broadcaster.js
               flips .active and aria-pressed on click. */ ?>
        <div class="wpstream_portal_bc_source_choice" role="group" aria-labelledby="wpstream_portal_bc_source_choice_title">
            <p class="wpstream_portal_bc_source_choice_title" id="wpstream_portal_bc_source_choice_title"><?php esc_html_e( 'How do you want to go live?', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></p>
            <div class="wpstream_portal_bc_source_choice_cards">
                <button type="button" class="wpstream_portal_encoder_tab wpstream_portal_bc_source_card active" data-wpstream-portal-encoder-tab="browser" aria-pressed="true">
                    <span class="wpstream_portal_bc_source_card_icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h-4L8 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-4z"/><circle cx="12" cy="13" r="3"/></svg></span>
                    <span class="wpstream_portal_bc_source_card_copy"><span class="wpstream_portal_bc_source_card_title"><?php esc_html_e( 'Webcam & microphone', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span><span class="wpstream_portal_bc_source_card_text"><?php esc_html_e( 'Broadcast directly from your browser. No software required.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span></span>
                </button>
                <button type="button" class="wpstream_portal_encoder_tab wpstream_portal_bc_source_card" data-wpstream-portal-encoder-tab="external" aria-pressed="false">
                    <span class="wpstream_portal_bc_source_card_icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19v3M8 22h8M12 3v2M5.64 5.64l1.42 1.42M3 12h2M18.36 5.64l-1.42 1.42M19 12h2M8.46 14.46a5 5 0 0 1 7.08 0M5.64 11.64a9 9 0 0 1 12.72 0"/></svg></span>
                    <span class="wpstream_portal_bc_source_card_copy"><span class="wpstream_portal_bc_source_card_title"><?php esc_html_e( 'Streaming software', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span><span class="wpstream_portal_bc_source_card_text"><?php esc_html_e( 'Connect OBS, vMix, Larix or another streaming app.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span></span>
                </button>
            </div>
        </div>

        <div class="wpstream_portal_bc_browser_stats">
            <?php wpstream_plugin_render_bc_stats_grid(); ?>
        </div>

        <?php /* -- Broadcaster body: preview pane + side controls -- */ ?>
        <div class="wpstream_portal_broadcaster_body">

            <?php /* Preview pane — off-air state (setup) */ ?>
            <div class="wpstream_portal_bc_preview">

                <?php /* Live status badges — broadcaster.js toggles display between these two during ICE connection state changes (see iceStateChange handler in broadcaster.js). Hidden during setup. */ ?>
                <span id="videoLiveIndicatorLive" class="wpstream-bc-hidden wpstream_portal_bc_live_badge"><?php esc_html_e( 'LIVE', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span>
                <span id="videoLiveIndicatorError" class="wpstream-bc-hidden wpstream_portal_bc_error_badge"><?php esc_html_e( 'Connecting...', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span>

                <div class="wpstream_portal_bc_offair">
                    <?php /* Uniform dim layer — sits BELOW the grid pattern and the centre content, on top of the camera preview. Black at opacity 0.75 darkens the video uniformly so the off-air UI on top stays readable. */ ?>
                    <div class="wpstream_portal_bc_offair_dim"></div>
                    <div class="wpstream_portal_bc_offair_grid"></div>
                    <div class="wpstream_portal_bc_offair_center">
                        <div class="wpstream_portal_bc_offair_ico">
                            <?php /* I.Cam size=28 — d="m23 7-7 5 7 5V7ZM3 5h14v14H3z" */ ?>
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m23 7-7 5 7 5V7ZM3 5h14v14H3z"/></svg>
                        </div>
                        <div class="wpstream_portal_bc_offair_label"><?php esc_html_e( 'Off Air', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></div>
                        <?php /* Static placeholder copy — must stay visible in off-air state. Do NOT add id="messageContainer" here; the JS wipes the container's innerHTML on init via resetMessages(), so this text would disappear on page load. The real #messageContainer is a separate overlay below. */ ?>
                        <button type="button" class="wpstream_portal_btn wpstream_portal_btn_lg wpstream_portal_btn_start_broadcast" data-wpstream-portal-bc-show-preview hidden><?php esc_html_e( 'Show preview', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></button>
                        <div class="wpstream_portal_bc_offair_sub"><?php esc_html_e( 'Camera ready · click Start Broadcast to go live', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></div>
                        <div class="wpstream_portal_bc_offair_meta">
                            <?php /* id="statusText" — broadcaster.js writes connection-state text here ("Connected - Broadcasting Live", "Connecting...", "Reconnecting", "Not Broadcasting") via textContent. Wrapped in its own span so the sibling .wpstream_portal_bc_offair_dot isn't wiped. */ ?>
                            <span><span id="statusIndicator" class="wpstream_portal_bc_offair_dot"></span> <span id="statusText"><?php esc_html_e( 'No signal', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span></span>
                            <span class="wpstream_portal_muted_w">&middot;</span>
                            <span class="wpstream_portal_mono">00:00:00</span>
                        </div>

                        <?php /* Permission retry control; JS requests undecided permissions on load and owns visibility. */ ?>
                        <div class="wpstream_portal_bc_camera_gate" data-wpstream-portal-bc-camera-gate>
                            <button type="button" class="wpstream_portal_btn wpstream_portal_btn_lg wpstream_portal_btn_start_broadcast" data-wpstream-portal-bc-enable-camera><?php esc_html_e( 'Enable camera & microphone', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></button>
                        </div>
                    </div>
                </div>

                <?php /* Live preview surface — hidden until Start Broadcast; broadcaster.js attaches the getUserMedia stream to #localVideo and shows it. Spinner shows during WHIP handshake. */ ?>
                <video id="localVideo" autoplay muted playsinline class="wpstream-bc-hidden"></video>

                <?php /* External-encoder preview surface — hidden until the user clicks the "External encoder" tab; broadcaster.js sets src from wpstream_broadcaster_vars.embed_url at that point. position:absolute;inset:0 mirrors the off-air overlay positioning; z-index:5 puts it above the off-air dim layer when visible. Empty src on render so the iframe doesn't pre-load on every dashboard view. */ ?>
                <iframe id="wpstreamPortalExternalEmbed"
                        title="<?php esc_attr_e( 'Live channel embed preview', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>"
                        allow="autoplay; encrypted-media"
                        allowfullscreen
                        class="wpstream-bc-hidden wpstream-bc-external-frame"></iframe>

                <button type="button" class="wpstream_portal_btn" data-wpstream-portal-bc-hide-preview><?php esc_html_e( 'Hide preview', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></button>
                <div id="wpstream-pre-load-spinner" class="wpstream-bc-hidden wpstream-pre-load-spinner"></div>

                <?php /* id="messageContainer" — broadcaster.js appends info/error message <div>s here via showMessage(). Separate from the off-air placeholder so the JS's innerHTML wipe doesn't kill the static "Camera ready…" copy. CSS hides the overlay when empty. */ ?>
                <div id="messageContainer" class="wpstream_portal_bc_message_overlay"></div>
            </div>



            <?php /* Side controls — SETUP STATE. Visible by default. broadcaster.js swaps display with .wpstream_portal_bc_side_live when Start Broadcast is pressed and the WHIP handshake reaches connected. */ ?>
            <div class="wpstream_portal_bc_side wpstream_portal_bc_side_setup" data-wpstream-portal-bc-state="setup">

                <?php /* Start Broadcast button — full-width accent with pulsing dot. id="startBroadcast" is the click target wired by broadcaster.js (boots getUserMedia + WHIP). */ ?>
                <button id="startBroadcast" disabled class="wpstream_portal_btn wpstream_portal_btn_lg wpstream_portal_btn_start_broadcast">
                    <span class="wpstream_portal_ldot_pulse"></span>
                    <?php esc_html_e( 'Start Broadcast', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                </button>
                <button id="cancelBroadcastStart" type="button" class="wpstream_portal_btn wpstream_portal_btn_ghost wpstream_portal_btn_sm wpstream_portal_bc_cancel_start hidden">
                    <?php esc_html_e( 'Cancel startup', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                </button>
                <?php /* Follow the camera gate so this next-step hint disappears when devices are ready. */ ?>
                <p class="wpstream_portal_bc_prestart_help" data-wpstream-portal-bc-prestart-help aria-live="polite"><?php esc_html_e( 'Enable your camera and microphone before broadcasting.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></p>

                <?php wpstream_plugin_render_bc_recording( $vm['record'] ); ?>

                <?php /* Video Source field. id="videoDevice" — broadcaster.js wipes static options and refills from MediaDevices.enumerateDevices() (videoinput kind) on boot. The static <option>s below are visible only before the JS runs (no design change). */ ?>
                <div class="wpstream_portal_bc_field">
                    <label><?php esc_html_e( 'Video Source', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></label>
                    <div class="wpstream-bc-gap-tight wpstream_portal_row">
                        <select id="videoDevice" class="wpstream_portal_select">
                            <option><?php esc_html_e( 'Loading cameras…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></option>
                        </select>
                        <button id="videoToggle" class="wpstream_portal_bc_toggle_ic on" data-wpstream-portal-bc-toggle="video" title="<?php esc_attr_e( 'Disable camera', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>">
                            <?php /* I.Cam size=15 */ ?>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m23 7-7 5 7 5V7ZM3 5h14v14H3z"/></svg>
                        </button>
                    </div>
                </div>

                <?php /* Audio Source field */ ?>
                <div class="wpstream_portal_bc_field">
                    <label><?php esc_html_e( 'Audio Source', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></label>
                    <div class="wpstream-bc-gap-tight wpstream_portal_row">
                        <select id="audioDevice" class="wpstream_portal_select">
                            <option><?php esc_html_e( 'Loading microphones…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></option>
                        </select>
                        <button id="audioToggle" class="wpstream_portal_bc_toggle_ic on" data-wpstream-portal-bc-toggle="audio" title="<?php esc_attr_e( 'Mute mic', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>">
                            <?php /* I.Mic size=15 — d="M12 1a3 3 0 0 0-3 3v8a3 3 0 1 0 6 0V4a3 3 0 0 0-3-3ZM19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8" */ ?>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 1 0 6 0V4a3 3 0 0 0-3-3ZM19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></svg>
                        </button>
                    </div>
                </div>

                <?php /* Video Resolution field */ ?>
                <div class="wpstream_portal_bc_field">
                    <label><?php esc_html_e( 'Video Resolution', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></label>
                    <div class="wpstream-bc-gap-tight wpstream_portal_row">
                        <?php /* id="videoQuality" — broadcaster.js reads select.value and looks it up in its userResolutions table (default / fhd / hd / square). Each <option> MUST carry a value attribute matching one of those keys, or the JS skips applying the constraint and the browser picks its own default. */ ?>
                        <select id="videoQuality" class="wpstream-bc-grow wpstream_portal_select">
                            <option value="default"><?php esc_html_e( 'Default · auto', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></option>
                            <option value="fhd"><?php esc_html_e( '1080p (1920×1080)', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></option>
                            <option value="hd"><?php esc_html_e( '720p (1280×720)', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></option>
                            <option value="square"><?php esc_html_e( 'Square (800×600)', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></option>
                        </select>
                    </div>
                </div>

                <?php /* Tip — verbatim from proto bc-tip */ ?>
                <div class="wpstream_portal_bc_tip">
                    <span><?php esc_html_e( 'Use Chrome or Edge for best webcam compatibility.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span>
                </div>

            </div>

            <?php /* Side controls — LIVE STATE. Hidden by default; broadcaster.js shows this and hides .wpstream_portal_bc_side_setup once the WHIP handshake reaches connected. Stat values (viewers / duration / bitrate / health) are static placeholders here — real values will be wired in a later pass when the JS exposes live-stat hooks. */ ?>
            <div class="wpstream-bc-hidden wpstream_portal_bc_side wpstream_portal_bc_side_live" data-wpstream-portal-bc-state="live">

                <?php /* End Broadcast button — id="stopBroadcast" is the click target wired by broadcaster.js (tears down the WHIP session + getUserMedia tracks). */ ?>
                <button id="stopBroadcast" class="wpstream_portal_btn wpstream_portal_btn_lg wpstream_portal_btn_stop_broadcast">
                    <?php /* I.Stop — d="M5 5h14v14H5z" */ ?>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="none" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5h14v14H5z"/></svg>
                    <?php esc_html_e( 'End Broadcast', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                </button>

                <div class="wpstream_portal_bc_live_av_controls" aria-label="<?php esc_attr_e( 'Live camera and microphone controls', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>">
                    <div class="wpstream_portal_bc_live_av_control">
                        <span><?php esc_html_e( 'Camera', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span>
                        <button type="button" class="wpstream_portal_bc_toggle_ic on" data-wpstream-portal-bc-toggle="video" aria-label="<?php esc_attr_e( 'Turn camera off', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>" aria-pressed="true" title="<?php esc_attr_e( 'Turn camera off', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m23 7-7 5 7 5V7ZM3 5h14v14H3z"/></svg>
                        </button>
                    </div>
                    <div class="wpstream_portal_bc_live_av_control">
                        <span><?php esc_html_e( 'Microphone', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></span>
                        <div class="wpstream_portal_bc_audio_level">
                            <div class="wpstream_portal_bc_audio_level_track" role="meter" aria-label="<?php esc_attr_e( 'Microphone input level', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-wpstream-portal-bc-audio-meter><span data-wpstream-portal-bc-audio-meter-fill></span></div>
                            <span class="wpstream_portal_bc_audio_level_value" data-wpstream-portal-bc-audio-meter-value>—</span>
                        </div>
                        <button type="button" class="wpstream_portal_bc_toggle_ic on" data-wpstream-portal-bc-toggle="audio" aria-label="<?php esc_attr_e( 'Mute microphone', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>" aria-pressed="true" title="<?php esc_attr_e( 'Mute microphone', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 1 0 6 0V4a3 3 0 0 0-3-3ZM19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/></svg>
                        </button>
                    </div>
                </div>

                <?php wpstream_plugin_render_bc_recording( $vm['record'] ); ?>

                <?php /* Live stats grid — 4 rows: Viewers, Duration, Bitrate, Health. All four are wired live by broadcaster.js: Duration/Bitrate from timers + WebRTC getStats, Viewers/Health from the chandler QoS meter socket. The text below is placeholder only. Markup factored into wpstream_plugin_render_bc_stats_grid() — shared verbatim with the External panel grid below. */ ?>
                <?php wpstream_plugin_render_bc_stats_grid(); ?>

            </div>

            <?php /* Side controls — EXTERNAL ENCODER STATE. Hidden by
                   default; the encoder-tab click handler in broadcaster.js
                   shows this when the user clicks "External encoder" and
                   hides the setup panel. The RTMP URL + stream key values
                   are read from the channel's `obs_uri` / `obs_stream`
                   post meta (same source the plugin uses); empty strings
                   surface as a "No channel selected" placeholder rather
                   than a fake stream key. The "Full encoder setup"
                   button carries data-wpstream-portal-bc-encoder-setup
                   for the future modal-opener; no behavior wired yet. */ ?>
            <div class="wpstream-bc-hidden wpstream_portal_bc_side wpstream_portal_bc_side_external" data-wpstream-portal-bc-state="external">

                <?php /* Waiting-for-encoder card — verbatim from proto
                       (BrowserBroadcaster External panel, .bc-encoder-state
                       block). Yellow/amber tint signals "configuration
                       awaiting external action". */ ?>
                <?php /* data-wpstream-portal-bc-encoder-state on the wrapper +
                       data-…-title / -subtitle on the two text rows give
                       dashboard-2026.js stable hooks for the postMessage
                       listener that swaps the copy when player.wpstream.net
                       emits a live_update event ('stopped' / 'init' /
                       'starting' / 'onair' / 'paused' / 'ended'). */ ?>
                <div class="wpstream_portal_bc_encoder_state" data-wpstream-portal-bc-encoder-state="waiting">
                    <div class="wpstream-bc-gap-medium wpstream_portal_row">
                        <div class="wpstream_portal_bc_encoder_pulse"><span></span></div>
                        <div>
                            <div class="wpstream-bc-caption wpstream_portal_bold" data-wpstream-portal-bc-encoder-title><?php esc_html_e( 'Waiting for encoder…', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></div>
                            <div class="wpstream_portal_tiny wpstream_portal_muted" data-wpstream-portal-bc-encoder-subtitle><?php esc_html_e( 'Point OBS / Wirecast / vMix at the URL below.', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></div>
                        </div>
                    </div>
                </div>

                <?php wpstream_plugin_render_bc_recording( $vm['record'] ); ?>

                <?php
                /* Resolve display values. If the channel has no
                   obs_uri/obs_stream set (no channel selected, or the
                   channel post was deleted server-side), show a single
                   placeholder rather than printing an empty box that
                   looks like a real-but-blank stream key. */
                $rtmp_display = $obs_uri    !== '' ? $obs_uri    : esc_html__( 'No channel selected', WPSTREAM_PLUGIN_BC_TEXTDOMAIN );
                $key_display  = $obs_stream !== '' ? $obs_stream : esc_html__( 'No channel selected', WPSTREAM_PLUGIN_BC_TEXTDOMAIN );
                ?>

                <?php /* RTMP URL — read-only + copy. Same .share_row
                       pattern as the Live panel's Share link. The wrapper
                       carries data-wpstream-portal-bc-rtmp so the dropdown
                       channel-change handler in dashboard-2026.js can
                       refresh the value + copy payload live. */ ?>
                <div class="wpstream_portal_bc_field" data-wpstream-portal-bc-rtmp>
                    <label><?php esc_html_e( 'RTMP URL', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></label>
                    <?php wpstream_plugin_render_bc_share_row( $rtmp_display, $obs_uri ); ?>
                </div>

                <?php /* Stream key — read-only + copy. Treated as
                       sensitive (do not log, do not expose to third
                       parties — see Larix QR note in expertise.yml). The
                       wrapper carries data-wpstream-portal-bc-streamkey so
                       the dropdown channel-change handler in
                       dashboard-2026.js can refresh the value live. */ ?>
                <div class="wpstream_portal_bc_field" data-wpstream-portal-bc-streamkey>
                    <label><?php esc_html_e( 'Stream key', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></label>
                    <?php wpstream_plugin_render_bc_share_row( $key_display, $obs_stream ); ?>
                </div>

                <?php /* Full encoder setup — opens the per-encoder
                       Quick start modal (OBS, StreamYard, Restream, vMix,
                       Wirecast, XSplit, Larix QR). The modal markup is
                       emitted once per page below (static guard); the
                       data-wpstream-portal-modal-open attribute is the
                       generic open trigger handled by the dashboard-2026.js
                       modal helper. */ ?>
                <button class="wpstream-bc-full-width wpstream_portal_btn wpstream_portal_btn_ghost wpstream_portal_btn_sm" data-wpstream-portal-bc-encoder-setup="" data-wpstream-portal-modal-open="wpstream_portal_encoder_setup_modal">
                    <?php /* I.Settings size=11 — gear icon from the proto */ ?>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    <?php esc_html_e( 'Full encoder setup', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                </button>

                <?php /* Browser-live lockout notice (my-notes/brodcaster_notes.md
                       lines 46-60). Hidden by default. broadcaster.js's
                       encoder-tab handler shows this — and hides the RTMP URL,
                       Stream key, and "Full encoder setup" button above —
                       whenever the External tab is opened while an in-page
                       browser WHIP broadcast is live (streamingStarted). It
                       reverts to the normal credentials view when the browser
                       broadcast is not live. Copy is verbatim from the notes. */ ?>
                <div class="wpstream-bc-hidden wpstream_portal_bc_field" data-wpstream-portal-bc-browser-live-notice>
                    <p class="wpstream-bc-no-margin wpstream_portal_tiny wpstream_portal_muted">
                        <?php esc_html_e( 'you are streaming already via browser. stop the stream if you want to switch to external encoder', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                    </p>
                </div>

                <?php /* Live stats grid — IDENTICAL markup to the LIVE
                       panel's grid above, but in external-encoder mode there
                       is NO browser WebRTC peer, so all four cells are fed
                       from the QoS meter socket (getQos) by broadcaster.js:
                       Viewers + Health from bwHistory (same as the LIVE
                       panel), Bitrate from the ingest rate (bwHistory.bwIn /
                       avgBwIn1), and Duration from a local timer started when
                       QoS first reports on-air. Same data attributes so the
                       JS dual-cell writers update both panels at once.
                       Markup factored into wpstream_plugin_render_bc_stats_grid()
                       — shared verbatim with the LIVE panel grid above. */ ?>
                <?php wpstream_plugin_render_bc_stats_grid(); ?>

            </div>
        </div>

        <?php
        /* ------------------------------------------------------------------
           "Go Live with External Streaming App" modal.

           Presents the SAME information as the official WPStream plugin's
           external-streaming modal (class-wpstream-admin.php
           wpstream_display_modal_broadcast + the seven per-encoder methods)
           but re-styled to the 2026 dashboard design system. It reuses:
             - the dashboard modal shell (.wpstream_portal_modal_backdrop /
               .wpstream_portal_modal / _head / _title / _body) so the
               overlay, animation and close button match every other
               dashboard modal;
             - the generic copy mechanism (data-wpstream-portal-copy /
               data-wpstream-portal-copy-value) the rest of broadcaster.php
               already uses — NOT the plugin's copy_live_uri/copy_live_key
               jQuery;
             - the generic modal open/close delegation in dashboard-2026.js.

           Encoder switching, the Larix combined-RTMP string and the QR
           image src are computed in dashboard-2026.js (the
           wpstream_portal_encoder_setup namespace), reading from the SAME
           $obs_uri / $obs_stream source the External-encoder side panel
           reads, so there is exactly one source of truth. When the channel
           dropdown changes, the same channel-change handler in
           dashboard-2026.js that refreshes the side-panel RTMP/stream-key
           cells also refreshes the modal values + QR.

           Emitted once per page via a static guard — the render function may
           run more than once (Dashboard + Channel Detail) but the modal is a
           singleton keyed by its data-wpstream-portal-modal id.
           ------------------------------------------------------------------ */
        static $encoder_modal_printed = false;
        if ( ! $encoder_modal_printed ) :
            $encoder_modal_printed = true;

            /* Per-encoder definitions. Each row mirrors the corresponding
               plugin method verbatim:
                 - 'value'      the <select> option value (matches the
                                plugin's option values exactly, including the
                                vMix/XSplit "wimx"/"xplit" spellings);
                 - 'label'      the option label;
                 - 'uri_label'  the "Server / URL / Address / RTMP …" field
                                label for that encoder;
                 - 'key_label'  the stream-key field label (Larix has none);
                 - 'larix'      true only for the Larix block (combined RTMP +
                                QR layout instead of two fields);
                 - 'steps'      the instruction list, reproduced word-for-word
                                from the plugin. */
            $wpstream_portal_encoders = array(
                array(
                    'value'     => 'wpstream_obs_settings',
                    'label'     => 'OBS',
                    'uri_label' => 'Server:',
                    'key_label' => 'Stream Key:',
                    'larix'     => false,
                    'steps'     => array(
                        '1. Click Settings in the OBS Window and then Select Stream.',
                        '2. Choose Custom Streaming Server in the Stream Type dropdown menu.',
                        '3. In the URL box, type/paste your Server.',
                        '4. In the Stream key, type/paste your Stream key.',
                        '5. Save changes.Close the Settings window and click on the "Start Streaming" button in the main window of OBS.',
                    ),
                ),
                array(
                    'value'     => 'wpstream_streamyard_settings',
                    'label'     => 'StreamYard',
                    'uri_label' => 'RTMP server URL:',
                    'key_label' => 'Stream key:',
                    'larix'     => false,
                    'steps'     => array(
                        '1. Set up your destination by going to your StreamYard account.',
                        '2. Choose "Custom RTMP" and add the RTMP server URL and Stream key from WpStream.',
                        '3. Go to "Broadcasts" then "create a broadcast".',
                        '4. Enter Live Studio.',
                        '5. Adjust all your preferred settings and Click on "Go Live".',
                    ),
                ),
                array(
                    'value'     => 'wpstream_restream_settings',
                    'label'     => 'Restream',
                    'uri_label' => 'RTMP URL:',
                    'key_label' => 'Stream key:',
                    'larix'     => false,
                    'steps'     => array(
                        '1. Go to your Restream account and set up a destination or channel.',
                        '2. Choose "Custom RTMP" and add the RTMP URL and Stream Key from WpStream.',
                        '3. Click on "Add Channel".',
                        '4. Enter Live Studio.',
                        '5. Adjust your preferred settings and Go Live.',
                    ),
                ),
                array(
                    'value'     => 'wpstream_wimx_settings',
                    'label'     => 'vMix',
                    'uri_label' => 'URL:',
                    'key_label' => 'Stream Key:',
                    'larix'     => false,
                    'steps'     => array(
                        '1. Click on the gear icon near the stream button on the bottom.',
                        '2. Choose a custom RTMP Server in destination.',
                        '3. In the URL box, type/paste your URL.',
                        '4. In the Stream key, type/paste your Stream key.',
                        '5. Save changes. You can start streaming by clicking on the stream button at the bottom of the dashboard.',
                    ),
                ),
                array(
                    'value'     => 'wpstream_wirecast_settings',
                    'label'     => 'Wirecast',
                    'uri_label' => 'Address:',
                    'key_label' => 'Stream:',
                    'larix'     => false,
                    'steps'     => array(
                        '1. Click Output on the top of the screen and then Select Output Settings.',
                        '2. In the destination, choose RTMP Server and click OK.',
                        '3. In the Address box, type/paste your Address.',
                        '4. In the Stream box, type/paste your Stream key.',
                        '5. Click on OK to save changes just click the Output on the top of the screen and then Start/Stop Broadcasting.',
                    ),
                ),
                array(
                    'value'     => 'wpstream_xplit_settings',
                    'label'     => 'XSplit',
                    'uri_label' => 'RTMP Url:',
                    'key_label' => 'Stream Key:',
                    'larix'     => false,
                    'steps'     => array(
                        '1. Click Broadcast in the XSplit Window and then click "Set up a new output".',
                        '2. Choose Custom RTMP in the Set up a new output dropdown menu.',
                        '3. In the URL box, type/paste your RTMP Url.',
                        '4. In the Stream key, type/paste your Stream key.',
                        '5. Save changes and click on the "Stream" button in the main window.',
                    ),
                ),
                array(
                    'value'     => 'wpstream_larix_settings',
                    'label'     => 'Larix Broadcaster',
                    'uri_label' => 'RTMP:',
                    'key_label' => '',
                    'larix'     => true,
                    'steps'     => array(
                        'A. Scan the QR code.',
                        'or',
                        'B. Manually configure Larix with the above RTMP',
                    ),
                ),
            );

            /* Larix combined RTMP string — obs_uri concatenated with
               obs_stream (no slash inserted; obs_uri already ends in '/').
               Matches plugin/public/js/start_streaming.js:388. Recomputed
               client-side on channel change, but seeded here so the modal
               shows a real value on first open without a round-trip. */
            $larix_rtmp_initial = ( $obs_uri !== '' || $obs_stream !== '' ) ? ( $obs_uri . $obs_stream ) : '';
            ?>
            <div data-wpstream-portal-modal="wpstream_portal_encoder_setup_modal"
                 class="wpstream-bc-hidden wpstream_portal_modal_backdrop"
                 id="wpstream_portal_encoder_setup_modal"
                >
                <div class="wpstream_portal_modal wpstream_portal_encoder_modal" role="dialog" aria-modal="true" aria-labelledby="wpstream_portal_encoder_modal_title">

                    <?php /* Modal header — title mirrors the plugin's
                           "Go Live with External Streaming App". */ ?>
                    <div class="wpstream_portal_modal_head">
                        <div>
                            <div class="wpstream_portal_modal_title" id="wpstream_portal_encoder_modal_title"><?php esc_html_e( 'Go Live with External Streaming App', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></div>
                            <div class="wpstream_portal_modal_sub"><?php esc_html_e( 'Please choose your RTMP encoder/broadcaster', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></div>
                        </div>
                        <button class="wpstream_portal_icon_btn"
                                data-wpstream-portal-modal-close="wpstream_portal_encoder_setup_modal"
                                aria-label="<?php esc_attr_e( 'Close', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>">
                            <?php /* I.X size=14 */ ?>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <div class="wpstream_portal_modal_body">

                        <?php /* Encoder selector. The change handler in
                               dashboard-2026.js shows the matching
                               .wpstream_portal_encoder_block and hides the
                               others (same UX as the plugin's
                               .wpstream_external_broadcast_options handler),
                               using the data-wpstream-portal-encoder-pick hook
                               instead of the plugin's CSS classes. */ ?>
                        <div class="wpstream-bc-field-spaced wpstream_portal_bc_field">
                            <label><?php esc_html_e( 'Encoder / broadcaster', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></label>
                            <select class="wpstream_portal_select" data-wpstream-portal-encoder-pick="">
                                <?php foreach ( $wpstream_portal_encoders as $encoder ) : ?>
                                    <option value="<?php echo esc_attr( $encoder['value'] ); ?>"><?php echo esc_html( $encoder['label'] ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php /* Per-encoder blocks. OBS (first row) is shown
                               by default; the rest are display:none until
                               picked. Each block is keyed by
                               data-wpstream-portal-encoder-block matching its
                               <option> value. */ ?>
                        <?php foreach ( $wpstream_portal_encoders as $index => $encoder ) : ?>
                            <div class="wpstream_portal_encoder_block<?php echo 0 === $index ? '' : ' wpstream-bc-hidden'; ?>"
                                 data-wpstream-portal-encoder-block="<?php echo esc_attr( $encoder['value'] ); ?>"
>

                                <?php if ( ! empty( $encoder['larix'] ) ) : ?>

                                    <?php /* Larix — single combined RTMP field
                                           (obs_uri + obs_stream) + QR image.
                                           The value span carries
                                           data-wpstream-portal-larix-rtmp so
                                           the channel-change handler can refresh
                                           it; the copy button reads the same
                                           combined value. */ ?>
                                    <div class="wpstream_portal_bc_field" data-wpstream-portal-encoder-larix>
                                        <label><?php echo esc_html( $encoder['uri_label'] ); ?></label>
                                        <?php wpstream_plugin_render_bc_share_row(
                                            $larix_rtmp_initial !== '' ? $larix_rtmp_initial : esc_html__( 'No channel selected', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
                                            $larix_rtmp_initial,
                                            'data-wpstream-portal-larix-rtmp'
                                        ); ?>
                                    </div>

                                    <ul class="wpstream_portal_encoder_steps">
                                        <?php foreach ( $encoder['steps'] as $step ) : ?>
                                            <li><?php echo esc_html( $step ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>

                                    <?php /* QR image — src is filled by
                                           dashboard-2026.js (tec-it API URL
                                           built from the larix:// deep link,
                                           matching the plugin). Empty src on
                                           render so no request fires until the
                                           modal/encoder is shown. */ ?>
                                    <div class="wpstream_portal_encoder_qr">
                                        <img data-wpstream-portal-larix-qr src="" alt="<?php esc_attr_e( 'Larix QR code', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>" width="150" height="150" />
                                    </div>

                                <?php else : ?>

                                    <?php /* Standard two-field block — URI +
                                           stream key, each read-only with a
                                           copy button. The value spans carry
                                           data-wpstream-portal-encoder-uri /
                                           -key so the channel-change handler
                                           refreshes every block at once. */ ?>
                                    <div class="wpstream_portal_bc_field">
                                        <label><?php echo esc_html( $encoder['uri_label'] ); ?></label>
                                        <?php wpstream_plugin_render_bc_share_row(
                                            $obs_uri !== '' ? $obs_uri : esc_html__( 'No channel selected', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
                                            $obs_uri,
                                            'data-wpstream-portal-encoder-uri'
                                        ); ?>
                                    </div>

                                    <div class="wpstream_portal_bc_field">
                                        <label><?php echo esc_html( $encoder['key_label'] ); ?></label>
                                        <?php wpstream_plugin_render_bc_share_row(
                                            $obs_stream !== '' ? $obs_stream : esc_html__( 'No channel selected', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ),
                                            $obs_stream,
                                            'data-wpstream-portal-encoder-key'
                                        ); ?>
                                    </div>

                                    <ul class="wpstream_portal_encoder_steps">
                                        <?php foreach ( $encoder['steps'] as $step ) : ?>
                                            <li><?php echo esc_html( $step ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>

                                <?php endif; ?>

                            </div>
                        <?php endforeach; ?>

                    </div><!-- /.wpstream_portal_modal_body -->
                </div><!-- /.wpstream_portal_modal -->
            </div><!-- /encoder setup modal -->
            <?php
        endif;
        ?>

        <?php
        /* -- Embed code card — 4-tab interface ----------------------------------
           Extends the original single BcEmbed strip into four tabs:
             • Web Embed  — the responsive iFrame snippet ($embed_code), default.
             • Astro Code — baker's raw contentId string ($content_id).
             • Shopify code — the same contentId string ($content_id).
             • Wix code   — the same contentId string ($content_id).
           Tab switching reuses the generic dashboard tab mechanism in
           dashboard-2026.js (wpstreamPortal.tabs): the click delegation reads
           data-wpstream-portal-tab-group + data-wpstream-portal-tab off the
           clicked button, while tabs.activate() finds the group container
           (which precedes its buttons in document order) and the panel group.
           Each panel carries its own Copy button using the existing per-element
           data-wpstream-portal-copy / -copy-value contract, so Copy always
           copies whatever the active tab shows. The Web Embed panel keeps the
           .wpstream_portal_bc_embed_code <pre> + its copy button first in the
           DOM so the dropdown-change refresh in dashboard-2026.js (which
           rewrites the page-load snippet for the newly-picked channel) keeps
           targeting the right elements unchanged.

           Layout: the header row holds only the four tabs (left-aligned).
           There is no longer an "Embed code" title. Each panel's Copy button
           lives in flow below the tabs but is lifted onto the tabs row's right
           edge purely in CSS (.wpstream_portal_bc_embed_panel_head is absolutely
           positioned to the card's top-right). Because only the active panel is
           displayed, exactly one Copy button is visible — the active tab's —
           sitting on the same line as the tabs. No JS swap is involved, so the
           per-panel copy-value contract and the dropdown-refresh selectors are
           untouched.

           $copy_svg holds the I.Copy glyph once so the four identical Copy
           buttons can't drift apart. Output is escaped exactly as before:
           esc_html for the <pre><code>, esc_attr for the copy-value attr. */
        $copy_svg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M16 1H4a2 2 0 0 0-2 2v12h2V3h12V1Zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm0 16H8V7h11v14Z"/></svg>';
        ?>
        <div class="wpstream_portal_bc_embed">
            <div class="wpstream_portal_bc_embed_head">
                <?php /* Tab triggers — the only content of the header row now
                       (the "Embed code" title was removed); they sit on the left
                       and the active panel's Copy button is lifted onto this same
                       line's right edge via CSS. Container carries the group id
                       (tabs.activate queries the buttons inside it); each button
                       repeats the group id so the click delegation — which reads
                       both attrs off one element — fires. "web" is active by
                       default. */ ?>
                <div class="wpstream_portal_bc_embed_tabs" data-wpstream-portal-tab-group="bc-embed" role="tablist">
                    <button type="button"
                        class="wpstream_portal_bc_embed_tab wpstream_portal_tab_active"
                        data-wpstream-portal-tab-group="bc-embed"
                        data-wpstream-portal-tab="web"
                        aria-selected="true"
                    ><?php esc_html_e( 'Web Embed', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></button>
                    <button type="button"
                        class="wpstream_portal_bc_embed_tab"
                        data-wpstream-portal-tab-group="bc-embed"
                        data-wpstream-portal-tab="astro"
                        aria-selected="false"
                    ><?php esc_html_e( 'Content ID for Astro', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></button>
                    <button type="button"
                        class="wpstream_portal_bc_embed_tab"
                        data-wpstream-portal-tab-group="bc-embed"
                        data-wpstream-portal-tab="shopify"
                        aria-selected="false"
                    ><?php esc_html_e( 'Content ID for Shopify', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></button>
                    <button type="button"
                        class="wpstream_portal_bc_embed_tab"
                        data-wpstream-portal-tab-group="bc-embed"
                        data-wpstream-portal-tab="wix"
                        aria-selected="false"
                    ><?php esc_html_e( 'Content ID for Wix', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?></button>
                </div>
            </div>

            <div class="wpstream_portal_bc_embed_panels" data-wpstream-portal-tab-panel-group="bc-embed">

                <?php /* Web Embed panel — default active. Keeps the original
                       .wpstream_portal_bc_embed_code <pre> + copy button first
                       in the DOM (dropdown-change refresh in dashboard-2026.js
                       targets these). */ ?>
                <div class="wpstream_portal_bc_embed_panel" data-wpstream-portal-tab-panel="web">
                    <div class="wpstream_portal_bc_embed_panel_head">
                        <button
                            class="wpstream_portal_copy_btn"
                            data-wpstream-portal-copy=""
                            data-wpstream-portal-copy-value="<?php echo esc_attr( $embed_code ); ?>"
                        >
                            <?php echo $copy_svg; // phpcs:ignore WordPress.Security.EscapeOutput — static literal SVG ?>
                            <?php esc_html_e( 'Copy code', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                        </button>
                    </div>
                    <pre class="wpstream_portal_bc_embed_code"><code><?php echo esc_html( $embed_code ); ?></code></pre>
                </div>

                <?php /* Astro Code panel — displays + copies the raw contentId. */ ?>
                <div class="wpstream-bc-hidden wpstream_portal_bc_embed_panel" data-wpstream-portal-tab-panel="astro">
                    <div class="wpstream_portal_bc_embed_panel_head">
                        <button
                            class="wpstream_portal_copy_btn"
                            data-wpstream-portal-copy=""
                            data-wpstream-portal-copy-value="<?php echo esc_attr( $content_id ); ?>"
                        >
                            <?php echo $copy_svg; // phpcs:ignore WordPress.Security.EscapeOutput — static literal SVG ?>
                            <?php esc_html_e( 'Copy code', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                        </button>
                    </div>
                    <pre class="wpstream_portal_bc_embed_code"><code><?php echo esc_html( $content_id ); ?></code></pre>
                </div>

                <?php /* Shopify code panel — same contentId string as Astro. */ ?>
                <div class="wpstream-bc-hidden wpstream_portal_bc_embed_panel" data-wpstream-portal-tab-panel="shopify">
                    <div class="wpstream_portal_bc_embed_panel_head">
                        <button
                            class="wpstream_portal_copy_btn"
                            data-wpstream-portal-copy=""
                            data-wpstream-portal-copy-value="<?php echo esc_attr( $content_id ); ?>"
                        >
                            <?php echo $copy_svg; // phpcs:ignore WordPress.Security.EscapeOutput — static literal SVG ?>
                            <?php esc_html_e( 'Copy code', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                        </button>
                    </div>
                    <pre class="wpstream_portal_bc_embed_code"><code><?php echo esc_html( $content_id ); ?></code></pre>
                </div>

                <?php /* Wix code panel — same contentId string as Astro/Shopify. */ ?>
                <div class="wpstream-bc-hidden wpstream_portal_bc_embed_panel" data-wpstream-portal-tab-panel="wix">
                    <div class="wpstream_portal_bc_embed_panel_head">
                        <button
                            class="wpstream_portal_copy_btn"
                            data-wpstream-portal-copy=""
                            data-wpstream-portal-copy-value="<?php echo esc_attr( $content_id ); ?>"
                        >
                            <?php echo $copy_svg; // phpcs:ignore WordPress.Security.EscapeOutput — static literal SVG ?>
                            <?php esc_html_e( 'Copy code', WPSTREAM_PLUGIN_BC_TEXTDOMAIN ); ?>
                        </button>
                    </div>
                    <pre class="wpstream_portal_bc_embed_code"><code><?php echo esc_html( $content_id ); ?></code></pre>
                </div>

            </div><!-- /.wpstream_portal_bc_embed_panels -->
        </div>

    </div><!-- /.wpstream_portal_broadcaster -->
    <?php
}
