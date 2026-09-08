<?php

/**
 * Front-end video player for WpStream live channels and video-on-demand.
 *
 * This class is the WordPress and backwards-compatibility facade: it hooks
 * the_content, decides whether the current user is entitled to watch, preserves
 * the established public renderer methods, and exposes the status-poll AJAX
 * endpoint. Wpstream_Playback_Presentation owns source resolution, engine
 * selection, assets, and viewer-facing player markup.
 *
 * Live status is fetched from the WpStream cloud API and cached in a 45-second
 * transient so repeated polls do not hammer the remote service.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 * @author     cretu
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wpstream_Player{
    /** @var object Main plugin instance; exposes shared services (live connection, quota manager). */
    public $main;

	/**
	 * @var Wpstream_Playback_Session Helper that issues/validates playback sessions for encrypted streams.
	 */
	public $playback_session;

    /**
     * Wire up the player: keep a handle on the plugin, load the playback-session
     * helper, and register the content filter, purchase check, and status-poll
     * AJAX endpoints (both logged-in and nopriv).
     *
     * @param object $plugin_main Main plugin instance passed in by the loader.
     */
    public function __construct($plugin_main) {
        // Keep a reference to the main plugin so we can reach its services later.
        $this->main = $plugin_main;

		// Instantiate the playback-session helper (used for encrypted streams).
		$this->playback_session = new Wpstream_Playback_Session();

        // Inject the player markup into single wpstream product/VOD pages.
        add_filter( 'the_content',array($this, 'wpstream_filter_the_title') );
        // On a WooCommerce single product, render the player only if the visitor already bought it.
        add_action( 'woocommerce_before_single_product', array($this,'wpstream_user_logged_in_product_already_bought') );

        // Front-end status poll endpoint for logged-in users.
        add_action( 'wp_ajax_wpstream_player_check_status', array($this,'wpstream_player_check_status') );
        // Same endpoint for logged-out visitors (nopriv) so public streams can poll too.
        add_action('wp_ajax_nopriv_wpstream_player_check_status', array($this,'wpstream_player_check_status'));

    }
    
    
        
  
    
        
    /**
     * AJAX endpoint: report whether a channel is currently live and, if so, its
     * playback/stats/chat URLs. Called repeatedly by the front-end player JS.
     *
     * The posted id must be a published streaming channel or it is rejected
     * before any work. Reads the channel status from a 45-second transient; on
     * a cache miss it calls the WpStream cloud API and refreshes the transient.
     * Liveness is public, but the playback/stats/chat URLs are returned only to
     * callers who pass the player-render entitlement check; the response always
     * carries exactly the five keys the player JS reads. Echoes a JSON payload
     * and dies. No nonce check on purpose (see inline note) because the hosting
     * page may be cached, which would invalidate the nonce.
     *
     * edited 4.0
     *
     * @return void Outputs JSON and terminates the request.
     * @author cretu
     */
    public function wpstream_player_check_status(){
		// did not add a nonce check here because this is a call done from the frontend
	    // and the page might be cached, so the nonce would not be valid
        // Which channel/post the caller is asking about.
        $channel_id = intval($_POST['channel_id']);

        // Reject anything that is not a published streaming channel BEFORE any
        // cache read, remote API call or meta write: the id must be a wpstream
        // live/VOD post, or a WooCommerce product carrying one of the wpstream
        // product types. This stops arbitrary-id probing from reaching the API
        // or touching unrelated posts' meta.
        $channel_post = get_post( $channel_id );
        $is_valid_channel = false;
        if ( $channel_post instanceof WP_Post && 'publish' === $channel_post->post_status ) {
            if ( in_array( $channel_post->post_type, array( 'wpstream_product', 'wpstream_product_vod' ), true ) ) {
                $is_valid_channel = true;
            } elseif ( 'product' === $channel_post->post_type
                && has_term( array( 'live_stream', 'video_on_demand', 'subscription' ), 'product_type', $channel_post ) ) {
                $is_valid_channel = true;
            }
        }
        if ( ! $is_valid_channel ) {
            ob_end_clean();
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid channel.', 'wpstream' ) ), 400 );
        }

        // Channel state (cache, Baker fetch on miss, fresh-data meta sync) is
        // owned by Wpstream_Channel_State — the poll only asks questions of it.
        $state = Wpstream_Channel_State::for_channel( $channel_id, 'wpstream_player_check_status_note_from_js' );

        // Live means playable: upstream status active AND a playback URL present.
        if ( $state->is_live() ) {

            // Entitlement gate: playback/stats/chat URLs go only to
            // callers who pass the same check that decides whether the player
            // renders at all (free wpstream post types for anyone; WooCommerce
            // products only for buyers / active subscribers / bundle owners).
            // Liveness itself stays visible so a paywall page can reflect it.
            $viewer_is_entitled = wpstream_user_entitled_for_product( $channel_id );

            // cleanup any previous echo before sending json
            ob_end_clean();
            // Both branches return exactly the keys the player JS reads —
            // never the raw upstream payload or any debug field.
            if ( $viewer_is_entitled ) {
                echo json_encode(   array(
                        'started'               =>  'yes',
                        'channel_id'            =>  $channel_id,
                        'event_uri'             =>  $state->playback_url(),
                        'live_conect_views'     =>  $state->stats_url(),
                        'chat_url'              =>  $state->chat_url(),
                ));
            } else {
                // Unentitled caller: same JSON shape, real liveness, but every
                // URL field empty.
                echo json_encode(   array(
                        'started'               =>  'yes',
                        'channel_id'            =>  $channel_id,
                        'event_uri'             =>  '',
                        'live_conect_views'     =>  '',
                        'chat_url'              =>  '',
                ));
            }

        }else{
            // Channel is not live: return a "started: no" payload with empty
            // URLs, in the same five-key shape as the live branch.
            // cleanup any previous echo before sending json
            ob_end_clean();
            echo json_encode(   array(
                    'started'               =>  'no',
                    'channel_id'            =>  $channel_id,
                    'event_uri'             =>  '',
                    'live_conect_views'     =>  '',
                    'chat_url'              =>  '',
            ));

        }

        // AJAX response is complete; stop execution so nothing else is appended.
        die();
    }



    /**
    * the_content filter: prepend the player markup to single live/VOD product pages.
    *
    * @param string $content The post content WordPress is about to render.
    * @return string Content with the player div prepended, or unchanged.
    * @author cretu
    */
    public function wpstream_filter_the_title( $content   ) {
            // Only inject on single WpStream live-product or VOD-product pages.
            if( is_singular('wpstream_product') || is_singular('wpstream_product_vod') ){
                global $post;
                // The theme may own the player placement and switch the injection off.
                if ( ! $this->auto_insert_enabled( intval( $post->ID ), 'the_content' ) ) {
                    return $content;
                }
                // Build the player HTML for the current post and prepend it to the content.
                $args=array('id'=>$post->ID);
                $custom_content = $this->wpstream_insert_player_inpage($args);
                $content = '<div class="wpestream_inserted_player">'.$custom_content.'</div>'.$content;
                return $content;
            }else{
                // Not a player page: return content untouched.
                return $content;
            }
    }
    
    /**
     * Decide whether the plugin injects its player automatically into a page.
     *
     * Themes that render the player in their own templates switch this off.
     * Replaces the `wpstream_remove_wpstream_filter()` function-name sniff,
     * which is honoured as the default for one more release.
     *
     * @param int    $post_id Post the page is about.
     * @param string $context `the_content` or `woocommerce_before_single_product`.
     * @return bool
     */
    private function auto_insert_enabled( $post_id, $context ) {
        // One-release shim: a theme defining the legacy opt-out function still opts out.
        $enabled = ! function_exists( 'wpstream_remove_wpstream_filter' );

        /**
         * Filter whether the plugin inserts its player into the page automatically.
         *
         * Only the automatic placements (post content, WooCommerce single product)
         * are affected; shortcodes and builder widgets always render.
         *
         * @since 4.14.0
         *
         * @param bool   $enabled Whether to insert the player.
         * @param int    $post_id Post the page is about.
         * @param string $context `the_content` or `woocommerce_before_single_product`.
         */
        return (bool) apply_filters( 'wpstream_player_auto_insert', $enabled, $post_id, $context );
    }

    /**
     * Build the access notice shown in place of the player.
     *
     * @param string $reason        `nobuy`, `nolog` or `sub_active`.
     * @param int    $product_id    Product the notice is about.
     * @param string $message       Configured wording for the reason.
     * @param string $wrapper_class Extra class on the outer wrapper (shortcode placement).
     * @param string $notice_style  Inline style kept by the shortcode placement.
     * @return string Notice markup.
     */
    private function not_entitled_notice( $reason, $product_id, $message, $wrapper_class = '', $notice_style = '' ) {
        /**
         * Filter the wording of the access notice shown in place of the player.
         *
         * @since 4.14.0
         *
         * @param string $message    Plain-text message (not yet escaped).
         * @param int    $product_id Product the notice is about.
         * @param string $reason     `nobuy`, `nolog` or `sub_active` — the entitlement path,
         *                           not the wording (global-subscription sites show the
         *                           "not subscribed" text under `nobuy`).
         */
        $message = (string) apply_filters( 'wpstream_player_not_entitled_message', $message, $product_id, $reason );

        // Assemble the block with the message escaped; the style is a plugin constant.
        $style = '' !== $notice_style ? ' style="' . esc_attr( $notice_style ) . '"' : '';
        $html  = '<div class="wpstream_player_wrapper ' . esc_attr( trim( $wrapper_class . ' no_buy' ) ) . '"><div class="wpstream_player_container">'
            . '<div class="wpstream_notice"' . $style . '>' . esc_html( $message ) . '</div>'
            . '</div></div>';

        /**
         * Filter the whole access notice block shown in place of the player.
         *
         * The default markup is already escaped; callbacks own the escaping of
         * anything they add. This decides presentation only — entitlement was
         * refused before this point and is not revisited.
         *
         * @since 4.14.0
         *
         * @param string $html       Notice markup.
         * @param int    $product_id Product the notice is about.
         * @param string $reason     `nobuy`, `nolog` or `sub_active` (the entitlement path).
         */
        return (string) apply_filters( 'wpstream_player_not_entitled_html', $html, $product_id, $reason );
    }

    /**
    * Build the player HTML for a shortcode/embed and return it as a string.
    *
    * Buffers the echoed player markup so it can be returned instead of printed.
    *
    * @param array       $attributes Shortcode attributes; 'id' selects the product.
    * @param string|null $content    Unused enclosed shortcode content.
    * @return string The captured player markup.
    * @author cretu
    */
    public function wpstream_insert_player_inpage($attributes, $content = null){
        $product_id     =   '';
        $return_string  =   '';
        // Normalise the incoming attributes against the supported defaults.
        $attributes =   shortcode_atts(
            array(
                'id'                       => 0,
            ), $attributes) ;


        // Take the product id from the attributes when present.
        if ( isset($attributes['id']) ){
            $product_id=$attributes['id'];
        }

        // No explicit id: fall back to the first available product id. The
        // lookup lives on the main plugin class, not on this player service.
        if(intval($product_id)==0){
            $product_id= $this->main->wpstream_player_retrieve_first_id();
        }

        // Capture everything the shortcode renderer echoes into a string.
        ob_start();

        $this->wpstream_video_player_shortcode($product_id);
        $return_string= ob_get_contents();
        ob_end_clean();

        return $return_string;
    }

    
    
    
    /**
    * Render the player for a product, gated by login and purchase entitlement.
    *
    * Picks the live or VOD renderer based on product type / taxonomy term, and
    * shows a "not purchased" / "must log in" notice when the visitor is not
    * entitled to watch.
    *
    * @param string|int $from_sh_id Product/post id to render.
    * @return void Echoes the player markup or an access notice.
    * @author cretu
    */
    public function wpstream_video_player_shortcode($from_sh_id='') {
		// disable cache in order to be able to check the nonce
		if ( !defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		$product_id   = intval( $from_sh_id );
		$product_post = get_post( $product_id );
		if ( ! $product_post instanceof WP_Post
			|| ( 'publish' !== $product_post->post_status && ! current_user_can( 'read_post', $product_id ) ) ) {
			return;
		}

        // Player depends on Video.js plus the WpStream player glue script (+styles).
        wpstream_enqueue_player_assets();

        if ( is_user_logged_in() ) {
            // Logged-in branch: gather the identifier needed to check entitlement.
            // Entitlement gate: the single shared rule (free type / purchase /
            // active subscription / bundle / Netflix mode).
            if ( wpstream_user_entitled_for_product( $product_id ) ){
                // Open the player wrapper markup.
                echo '<div class="wpstream_player_wrapper wpstream_player_shortcode"><div class="wpstream_player_container">';
				echo $this->main->playback_presentation->render( $product_id, 'auto' );
                // Close the player wrapper markup.
                echo '</div></div>';
            }else{
                // Logged in but not entitled: show a "not purchased" notice for paid products.
                if( get_post_type($product_id) == 'product' ){
                    $message = (string) get_option( 'wpstream_product_not_bought', 'You did not yet purchase this item.' );
                    echo $this->not_entitled_notice( 'nobuy', $product_id, $message, 'wpstream_player_shortcode', 'background:#e16767;' );
                }
            }


        }else{
            // Logged-out branch. Stable type slug is '' when the product has no term.
            $product_type_slug = wpstream_get_product_type_slug($product_id);

            if( get_post_type($product_id) == 'product' && ($product_type_slug=='live_stream' || $product_type_slug=='video_on_demand') ){
                // Paid live/VOD product requires login: show a "must log in" notice.
                $message = (string) get_option( 'wpstream_product_not_login', 'You must be logged in to watch this video.' );
                echo $this->not_entitled_notice( 'nolog', $product_id, $message, 'wpstream_player_shortcode', 'background:#e16767;' );
            }elseif( get_post_type($product_id) == 'wpstream_product' ){
                // Free live product is watchable without login.
                echo '<div class="wpstream_player_wrapper wpstream_player_shortcode"><div class="wpstream_player_container">';
				echo $this->main->playback_presentation->render( $product_id, 'auto' );
                echo '</div></div>';
            } else if( get_post_type($product_id) == 'wpstream_product_vod' ){
                // Free VOD product is watchable without login.
                echo '<div class="wpstream_player_wrapper wpstream_player_shortcode"><div class="wpstream_player_container">';
				echo $this->main->playback_presentation->render( $product_id, 'auto' );
                echo '</div></div>';
            }
        }
    }

    
    
    /**
    * Strip a leading http:// or https:// scheme from a URL.
    *
    * @param string $url URL to normalise.
    * @return string URL without its leading scheme, or unchanged if none matched.
    * @author cretu
    */
    function remove_http($url) { return $this->main->playback_presentation->remove_http( $url ); }

    /**
    * Derive the "live connect" stats URI from an event-status payload.
    *
    * @deprecated Kept because separately-installed themes may call it; the
    *             derivation now lives in Wpstream_Channel_State.
    * @param array $event_status Status data from the cloud API.
    * @return string The stats URL, a host derived from the playback URL, or ''.
    */
    function wpstream_get_live_connect_uri($event_status) {
        return Wpstream_Channel_State::derive_stats_url( $event_status );
    }
      
    /**
    * Resolve the effective event settings for a product: per-event options when
    * enabled, otherwise the site-wide global channel options.
    *
    * @param int $product_id Product/channel id.
    * @return mixed The per-event options array, or the global options.
    * @author cretu
    */
    function wpstream_return_event_settings($product_id){ return $this->main->playback_presentation->wpstream_return_event_settings( $product_id ); }

    /**
    * Deprecated misspelled alias of wpstream_return_event_settings().
    *
    * Kept because the method is public and may be called by external code.
    *
    * @deprecated 4.13.4 Use wpstream_return_event_settings() instead.
    */
    function wpestream_return_event_settings($product_id){ return $this->wpstream_return_event_settings( $product_id ); }

    /**
    * Render the live event player for a channel.
    *
    * Fetches channel status from the cloud API (cached 45s in a transient),
    * then renders either the legacy Video.js markup or, when the channel has an
    * embedUrl, the newer iframe-based player with a minted embed key.
    *
    * edited in 4.0
    *
    * @param int    $channel_id  Channel/post id.
    * @param string $poster_show Pass 'no' to suppress the poster image.
    * @return void Echoes the player markup.
    * @author cretu
    */
    function wpstream_live_event_player($channel_id,$poster_show=''){ $this->main->playback_presentation->wpstream_live_event_player( $channel_id, $poster_show ); }

        
        /*
        * Ask the cloud API for a VOD's HLS manifest URL (and DRM keys/embed data).
        *
        * Persists a 5-minute transient of the HLS URL and stores decryption keys /
        * embed metadata as post meta. Note: the transient read is immediately
        * overwritten with false below, so the API is currently queried every call.
        *
        * @param string $video_name Remote video name/identifier.
        * @param int    $product_id Product/post to store the resulting meta on.
        * @return string HLS URL on success, '' on failure.
        */
        public function  wpstream_request_video_on_demand_hls_player($video_name,$product_id){ return $this->main->playback_presentation->wpstream_request_video_on_demand_hls_player( $video_name, $product_id ); }

        /**
        * Resolve the CSS class for the configured Video.js theme/skin.
        *
        * Enqueues the theme stylesheet as a side effect. Streamify users get no theme.
        *
        * @param int|null $channel_id Channel/product id (used for the streamify check).
        * @return string A 'vjs-theme-*' class, or '' when no theme applies.
        */
        function wpstream_get_player_theme( $channel_id = null ) { return $this->main->playback_presentation->wpstream_get_player_theme( $channel_id ); }

		/**
		 * Enqueue the bundled Video.js theme stylesheet for the selected skin.
		 *
		 * @param string $player_theme Theme slug (skips the built-in 'default').
		 * @return void
		 */
		function wpstream_enqueue_player_theme_style( $player_theme ) { $this->main->playback_presentation->wpstream_enqueue_player_theme_style( $player_theme ); }

        /**
        * Resolve the VOD source URL and type for a product.
        *
        * Encrypted VODs (and WooCommerce products) get an HLS manifest fetched
        * from the cloud API; unencrypted VODs use the stored external/local URL.
        *
        * @param int $product_id Product/post id.
        * @return array Keys: video_path_final, wpstream_data_setup, video_type,
        *               free_video_type, post_type.
        * @author cretu
        */
        public function wpstream_video_on_demand_player_uri_request($product_id){ return $this->main->playback_presentation->resolve_vod_source( $product_id ); }
     
 
 
         /**
        * VODPlayer url
        *
        * @author cretu
        */

        public function wpstream_video_on_demand_player($product_id){ $this->main->playback_presentation->wpstream_video_on_demand_player( $product_id ); }


        
        /**
        * Render a VOD player that shows only the trailer (used by the theme).
        *
        * No main video source is set; playback is limited to the trailer plus
        * its play/mute/unmute controls.
        *
        * @param int $product_id Product/post id.
        * @return void Echoes the trailer-only player markup.
        * @author cretu
        */
        public function wpstream_video_on_demand_player_only_trailer($product_id){ $this->main->playback_presentation->wpstream_video_on_demand_player_only_trailer( $product_id ); }

    /**
     * Decide whether the current user may watch the given product.
     *
     * @deprecated Kept as a compatibility shim for separately-installed themes
     * that call it; the rule itself lives in wpstream_user_entitled_for_product()
     * (includes/Helpers/wpstream-entitlement.php). New code calls the helper.
     *
     * @param int $product_id Product id.
     * @return bool True when the user is entitled to watch.
     * @since     3.12
    */
    public function wpstream_check_if_player_can_dsplay($product_id){
        // Delegate to the single entitlement rule.
        return wpstream_user_entitled_for_product( $product_id );
    }



    /**
    * Theme variant of the entitlement check.
    *
    * @deprecated Kept as a compatibility shim for separately-installed themes
    * that call it; the rule itself lives in wpstream_user_entitled_for_product()
    * (includes/Helpers/wpstream-entitlement.php). New code calls the helper.
    *
    * @param int $product_id Product/post id.
    * @return bool True when the player may be displayed.
    * @since     3.12
    */
        public function wpstream_check_if_player_can_dsplay_theme($product_id){
            // Delegate to the single entitlement rule.
            return wpstream_user_entitled_for_product( $product_id );
        }


        /**
     * Check the global-subscription entitlement for a product (Netflix mode).
     *
     * @deprecated Logic moved to wpstream_user_has_qualifying_global_subscription()
     * (includes/Helpers/wpstream-entitlement.php); kept as a compatibility shim
     * for external callers.
     *
     * @param int $product_id Product id.
     * @return bool True when the user has a qualifying active subscription.
     * @since     3.12
    */
        public   function wpstream_in_plugin_check_global_subscription_model($product_id) {
            // Delegate to the moved Netflix-mode leg of the entitlement rule.
            return wpstream_user_has_qualifying_global_subscription( $product_id );
        }
        
        
        
        
        
        
        
        
        
        /**
         * 
         * 
         * 
         * 
     * check if the user bought the product and display the player - TO REDo
     *
     * @since     3.0.1
         * returns html of the player
         * 
         * 
         * 
         * 
         * 
    */
          public function wpstream_user_logged_in_product_already_bought($from_sh_id='') {
            // Work against the current WooCommerce product on the page.
            global $product;
            if ( ! $product instanceof WC_Product ) {
                return;
            }
            $product_id   = $product->get_id();
            $current_user = wp_get_current_user();
            // The theme may own the player placement and switch the injection off.
            if ( ! $this->auto_insert_enabled( intval( $product_id ), 'woocommerce_before_single_product' ) ) {
                return;
            }
            // Stable type slug is '' when the product has no term (never a crash).
            $product_type_slug = wpstream_get_product_type_slug($product_id);

			// A "simple subscription" (neither live nor VOD) has no player here.
			$is_simple_subscription = get_post_meta($product_id, '_subscript_live_event');
			if ( $product_type_slug == 'subscription' && ! empty( $is_simple_subscription ) && $is_simple_subscription[0] == 'none' ) {
				return;
			}

            if ( is_user_logged_in() ) {
                if( wpstream_user_entitled_for_product($product_id) ){
                    // Entitled: the presentation module classifies live vs VOD
                    // (product term or subscription flag) and renders.
                    echo '<div class="wpstream_player_wrapper "><div class="wpstream_player_container">';
                    echo $this->main->playback_presentation->render( $product_id, 'auto' );
                    echo '</div></div>';
                } else {
                    // Not entitled: show the appropriate "no buy" message.
                    if( $product_type_slug=='subscription'  ){
                        // For subscriptions, only prompt when there is no active subscription.
                        if( !wcs_user_has_subscription( $current_user->ID, $product_id ,'active') ) {
                            $this->wpstream_display_no_buy_message('nobuy',$product_id);
                        }

                    }else{
                        $this->wpstream_display_no_buy_message('nobuy',$product_id);
                    }


                }


            }else{
                // Logged out: prompt the visitor to log in.
                $this->wpstream_display_no_buy_message('nolog',$product_id);
            }
        }
        
        
        
        
        
        /**
     * display no buy Message
     * Render the appropriate access notice ("must log in" / "not purchased" /
     * "not subscribed" / "subscription active") for a product.
     *
     * @param string $log        Which case to show: 'sub_active', 'nolog', or other (nobuy).
     * @param int    $product_id Product/post id.
     * @return void Echoes the notice markup.
     * @since     3.12.2
    */
        public function wpstream_display_no_buy_message($log,$product_id) {
            // Special case: subscription already active -> confirmation notice and return early.
            if($log=='sub_active'){
                $message = (string) get_option( 'wpstream_subscription_active', 'Your Subscription is Active.' );
                echo $this->not_entitled_notice( 'sub_active', $product_id, $message );
                return;

            }else if($log=='nolog'){
               // Visitor is logged out.
               $reason  = 'nolog';
               $message = (string) get_option( 'wpstream_product_not_login', 'You must be logged in to watch this video.' );
            }else{
                // Default: logged in but has not purchased.
                $reason  = 'nobuy';
                $message = (string) get_option( 'wpstream_product_not_bought', 'You did not yet purchase this item.' );
            }
            // In subscription mode, swap in the "not subscribed" wording.
            $subscription_model     =       intval( get_option('wpstream_global_sub','')) ;
            if($subscription_model==1){
                $message = (string) get_option( 'wpstream_product_not_subscribe', 'You did not yet subscribe to this item.' );
            }


            if( get_post_type($product_id) == 'product' && $subscription_model==0 ){
                // PPV mode: only show the notice for live/VOD/subscription product types.
                $product                    =   wc_get_product($product_id);
                $product_type_slug          =   wpstream_get_product_type_slug($product_id);
                $product_type               =   $product->get_type();
                $is_subscription_live_event =   esc_html(get_post_meta($product_id,'_subscript_live_event',true));



                if( $product_type_slug=='video_on_demand' ||  $product_type_slug=='live_stream' || $product_type=='subscription'){
                    echo $this->not_entitled_notice( $reason, $product_id, $message );
                }

            }else  if( get_post_type($product_id) == 'product' && $subscription_model==1 ){
                // Subscription mode: show the notice for any non-simple product.
                // '' (no term) counts as non-simple, matching the pre-guard intent.
                if( wpstream_get_product_type_slug($product_id)!=='simple'){
                    echo $this->not_entitled_notice( $reason, $product_id, $message );
                }
            }
        }

	/**
	 * Get the video player logo URL.
	 *
	 * @param int $product_id Product ID.
	 * @return mixed|string Logo URL, the WpStream symbol for streamify users, or ''.
	 */
	public function wpstream_get_video_player_logo( $product_id ) { return $this->main->playback_presentation->wpstream_get_video_player_logo( $product_id ); }

	/*
	 * Check whether a channel/product is on a basic (streamify) subscription.
	 *
	 * @param int $channel_id Channel ID
	 * @return bool True when the 'basicStreaming' meta equals '1'.
	 */
	public function wpstream_is_streamify_user( $channel_id ) { return $this->main->playback_presentation->wpstream_is_streamify_user( $channel_id ); }
}
