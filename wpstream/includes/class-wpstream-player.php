<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


/**
 * Front-end video player for WpStream live channels and video-on-demand.
 *
 * This class owns everything the visitor sees when a stream or VOD is embedded
 * in a page: it hooks the_content to inject the player, decides whether the
 * current user is entitled to watch, renders either the legacy Video.js markup
 * or the newer iframe-based player, mints embed-key hashes for the iframe URLs,
 * wraps the live chat, and exposes a (nopriv) AJAX endpoint the front-end JS
 * polls to learn when a channel goes live.
 *
 * Live status is fetched from the WpStream cloud API and cached in a 45-second
 * transient so repeated polls do not hammer the remote service.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 * @author     cretu
 */
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

		// Load and instantiate the playback-session helper (used for encrypted streams).
		require_once dirname( __FILE__ ) . '/player/class-wpstream-playback-session.php';
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
     * Reads the channel status from a 45-second transient; on a cache miss it
     * calls the WpStream cloud API and refreshes the transient. Echoes a JSON
     * payload and dies. No nonce check on purpose (see inline note) because the
     * hosting page may be cached, which would invalidate the nonce.
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

        // Look for a cached status payload for this channel (45s TTL).
        $transient_name           = 'event_data_to_return_'.   $channel_id;
        $event_data_for_transient = get_transient( $transient_name );

        // Default provenance note; overwritten below if we have to hit the API.
        $usefull_info =' get cache from transiet';
        if ( false ===  $event_data_for_transient || $event_data_for_transient=='' ) { //ws || $hls_to_return==''
            // Cache miss: ask the cloud API for the live status of this channel.
            $notes                      =   'wpstream_player_check_status_note_from_js';
            $event_status               =   $this->main->wpstream_live_connection->wpstream_check_event_status_api_call($channel_id,$notes);
            $event_data_for_transient   =   $event_status;
            $usefull_info =' no cache found';
            // Store the fresh status so the next poll (within 45s) is served from cache.
            set_transient($transient_name,$event_data_for_transient,45);
        }

        // A non-empty HLS playback URL means the channel is live and streamable.
        if( isset($event_data_for_transient['hls_playback_url']) && $event_data_for_transient['hls_playback_url']!=''){
            // cleanup any previous echo before sending json
            ob_end_clean();
            echo json_encode(   array(
               
                    'started'               =>  'yes',
                    'usefull_info'          =>  $usefull_info,
                    'channel_id'            =>  $channel_id,
                    'event_uri'             =>  $event_data_for_transient['hls_playback_url'],
                    'live_conect_views'     =>  $event_data_for_transient['stats_url'],
                    'chat_url'              =>  $event_data_for_transient['chat_url'],
                    '$event_data_for_transient'=>$event_data_for_transient
                 
                                   
            ));
            $usedfull_info =' ';
            // Persist the current stream name and HLS key URL for later playback/DRM use.
            update_post_meta($channel_id,'stream_name',$event_data_for_transient['stream_name']);
            update_post_meta($channel_id,'hls_key_retrieval_url',$event_data_for_transient['hls_key_retrieval_url']);
            // Drop the "free event" name cache now that the stream is confirmed live.
            delete_transient(  'free_event_streamName_'.$event_data_for_transient['stream_name']);

        }else{
            // Channel is not live: return a "started: no" payload with empty URLs.
            // cleanup any previous echo before sending json
            ob_end_clean();
            echo json_encode(   array(
                    'started'               =>  'no',
                    'usefull_info'          =>  $usefull_info,
                    'server_id'             =>  '',
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
            // Allow the theme (or other code) to opt out of the plugin's auto-insertion.
            if(function_exists('wpstream_remove_wpstream_filter')){
                return $content;
            }

            // Only inject on single WpStream live-product or VOD-product pages.
            if( is_singular('wpstream_product') || is_singular('wpstream_product_vod') ){
                global $post;
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

        // No explicit id: fall back to the first available product id.
        if(intval($product_id)==0){
            $product_id= $this->wpstream_player_retrive_first_id();
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

        // Player depends on Video.js plus the WpStream player glue script.
        wp_enqueue_script('video.min');
        wp_enqueue_script('wpstream-player');

        if ( is_user_logged_in() ) {
            // Logged-in branch: gather the identifiers needed to check entitlement.
            global $product;
            $current_user   =   wp_get_current_user();
            $product_id     =   intval($from_sh_id);
            $term_list      =   wp_get_post_terms($product_id, 'product_type');
            $possible_bundle = get_post_meta($product_id, 'wpstream_part_of_bundle', true);




            // Entitlement gate: allow rendering when any clause holds.
            if (
                // WooCommerce active and the user bought this exact product, OR
                ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product_id) ) ||
                // WooCommerce active and the user bought the bundle this product belongs to, OR
                ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && (intval($possible_bundle)!=0) && wc_customer_bought_product( $current_user->user_email, $current_user->ID, $possible_bundle) ) ||
                // it is a free WpStream live product, OR
                get_post_type($product_id)=='wpstream_product' ||
                // it is a free WpStream VOD product.
                get_post_type($product_id)=='wpstream_product_vod' ){
                global $product;
                // Open the player wrapper markup.
                echo '<div class="wpstream_player_wrapper wpstream_player_shortcode"><div class="wpstream_player_container">';


                if( get_post_type($product_id) == 'wpstream_product' ){
                    // Free live product -> live player.
                    $this->wpstream_live_event_player($product_id);
                }else if( get_post_type($product_id) == 'wpstream_product_vod' ){
                    // Free VOD product -> VOD player.
                    $this->wpstream_video_on_demand_player($product_id);
                }else{
                    // WooCommerce product: decide live vs VOD from its taxonomy term / subscription flag.
                    $is_subscription_live_event =   esc_html(get_post_meta($product_id,'_subscript_live_event',true));

                    if( $term_list[0]->name=='live_stream' || ( $term_list[0]->name=='subscription' && $is_subscription_live_event=='yes' ) ){
                        // Live stream, or a subscription flagged as a live event.
                        $this->wpstream_live_event_player($product_id);
                    }else if( $term_list[0]->name=='video_on_demand'  || ($term_list[0]->name=='subscription' && $is_subscription_live_event=='no' ) ){
                        // On-demand video, or a subscription flagged as VOD.
                        $this->wpstream_video_on_demand_player($product_id);
                    }
                }


                // Close the player wrapper markup.
                echo '</div></div>';
            }else{
                // Logged in but not entitled: show a "not purchased" notice for paid products.
                if( get_post_type($product_id) == 'product' ){
                    echo '<div class="wpstream_player_wrapper wpstream_player_shortcode no_buy"><div class="wpstream_player_container">';
                    $message =esc_html( get_option('wpstream_product_not_bought','You did not yet purchase this item.')) ;
                    echo '<div class="wpstream_notice" style="background:#e16767;">'.esc_html($message).'</div>';
                    echo '</div></div>';
                }
            }


        }else{
            // Logged-out branch.
            $product_id     =   intval($from_sh_id);
            $term_list      =   wp_get_post_terms($product_id, 'product_type');

            if( get_post_type($product_id) == 'product' && ($term_list[0]->name=='live_stream' || $term_list[0]->name=='video_on_demand') ){
                // Paid live/VOD product requires login: show a "must log in" notice.
                echo '<div class="wpstream_player_wrapper wpstream_player_shortcode no_buy"><div class="wpstream_player_container">';
                $message= esc_html( get_option('wpstream_product_not_login','You must be logged in to watch this video.')) ;


                echo '<div class="wpstream_notice" style="background:#e16767;">'.esc_html($message).'</div>';
                echo '</div></div>';
            }elseif( get_post_type($product_id) == 'wpstream_product' ){
                // Free live product is watchable without login.
                echo '<div class="wpstream_player_wrapper wpstream_player_shortcode"><div class="wpstream_player_container">';
                    $this->wpstream_live_event_player($product_id);
                echo '</div></div>';
            } else if( get_post_type($product_id) == 'wpstream_product_vod' ){
                // Free VOD product is watchable without login.
                echo '<div class="wpstream_player_wrapper wpstream_player_shortcode"><div class="wpstream_player_container">';
                    $this->wpstream_video_on_demand_player($product_id);
                echo '</div></div>';
            }
        }
    }

    
    
    /**
    * Low-latency (SLDP) variant of the player shortcode, with the same
    * login/purchase gating as wpstream_video_player_shortcode().
    *
    * @param string|int $from_sh_id Product/post id to render.
    * @return void Echoes the player markup or an access notice.
    * @author cretu
    */
    public function wpstream_video_player_shortcode_low_latency($from_sh_id='') {
	    // Disable page cache so the player is always rendered fresh.
	    if ( !defined( 'DONOTCACHEPAGE' ) ) {
		    define( 'DONOTCACHEPAGE', true );
	    }

        // Player dependencies.
        wp_enqueue_script('video.min');
        wp_enqueue_script('wpstream-player');


        if ( is_user_logged_in() ) {
            // Logged-in branch: gather identifiers for the entitlement check.
            global $product;
            $current_user   =   wp_get_current_user();
            $product_id     =   intval($from_sh_id);


            // Allow if the user bought the product (WooCommerce) or it is a free live product.
            if ( ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product_id) ) || get_post_type($product_id)=='wpstream_product' ){
                global $product;
                // Open the player wrapper.
                echo '<div class="wpstream_player_wrapper wpstream_player_shortcode"><div class="wpstream_player_container">';


                if( get_post_type($product_id) == 'wpstream_product' ){
                    // Free live product -> low-latency live player.
                    $this->wpstream_live_event_player_low_latency($product_id);
                }else{
                    // WooCommerce product: only live streams (or live-flagged subs) get the low-latency player.
                    $term_list                  =   wp_get_post_terms($product_id, 'product_type');
                    $is_subscription_live_event =   esc_html(get_post_meta($product_id,'_subscript_live_event',true));

                    if( $term_list[0]->name=='live_stream' || ( $term_list[0]->name=='subscription' && $is_subscription_live_event=='yes' ) ){
                        $this->wpstream_live_event_player_low_latency($product_id);
                    }
                }


                // Close the player wrapper.
                echo '</div></div>';
            }else{
                // Logged in but not entitled: show a "not purchased" notice for paid products.
                if( get_post_type($product_id) == 'product' ){
                    echo '<div class="wpstream_player_wrapper wpstream_player_shortcode no_buy"><div class="wpstream_player_container">';

                    $message =esc_html( get_option('wpstream_product_not_bought','You did not yet purchase this item.')) ;
                    echo '<div class="wpstream_notice" style="background:#e16767;">'.$message.'</div>';
                    echo '</div></div>';
                }
            }


        }else{
            // Logged-out branch: only free live products play without login.
            $product_id     =   intval($from_sh_id);
            if( get_post_type($product_id) == 'wpstream_product' ){
                $this->wpstream_live_event_player_low_latency($product_id);
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
    function remove_http($url) {
        // Schemes we want to remove from the front of the string.
        $disallowed = array('http://', 'https://');
        foreach($disallowed as $d) {
           // Only strip when the scheme is at the very start of the URL.
           if(strpos($url, $d) === 0) {
              return str_replace($d, '', $url);
           }
        }
        // No leading scheme matched; return the URL as-is.
        return $url;
    }

	/**
	 * Site origin (scheme://host:port) for the new player `embedAncestor` query param.
	 * The player includes this value in the embed-key hash; mint keys with the same string.
	 *
	 * @return string
	 */
	private function wpstream_get_site_origin_for_embed() {
		// Break the site home URL into scheme/host/port parts.
		$parts = wp_parse_url( home_url() );
		// Without a host there is no origin to build.
		if ( empty( $parts['host'] ) ) {
			return '';
		}
		// Default to https when the scheme is missing.
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : 'https://';
		$host   = $parts['host'];
		// Include the port only when one is explicitly set.
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		// Reassemble scheme://host[:port].
		return $scheme . $host . $port;
	}

	/**
	 * Keep this in sync with player/src/config/env.js fallback.
	 *
	 * @return string
	 */
	private function wpstream_get_player_embed_key_salt() {
		// Hardcoded fallback salt; must match the player's env.js fallback.
		$default_salt = 'EYjb84vNTXE85TfR';
		// A site can override the salt via option.
		$configured   = trim( (string) get_option( 'wpstream_player_embed_key_salt', '' ) );
		// Prefer the configured salt when present, else the default.
		$base_salt    = '' !== $configured ? $configured : $default_salt;

		// Let integrators filter the final salt.
		return (string) apply_filters( 'wpstream_player_embed_key_salt', $base_salt );
	}

	/**
	 * Masked salt metadata for debugging embed-key mismatches.
	 *
	 * @return array<string,mixed>
	 */
	private function wpstream_get_player_embed_key_salt_debug_meta() {
		// Recompute the effective salt (same logic as wpstream_get_player_embed_key_salt()).
		$default_salt = 'EYjb84vNTXE85TfR';
		$configured   = trim( (string) get_option( 'wpstream_player_embed_key_salt', '' ) );
		$base_salt    = '' !== $configured ? $configured : $default_salt;
		$final_salt   = (string) apply_filters( 'wpstream_player_embed_key_salt', $base_salt );
		// Length and first/last couple of characters, used to build a masked preview.
		$len          = strlen( $final_salt );
		$prefix       = $len > 0 ? substr( $final_salt, 0, min( 2, $len ) ) : '';
		$suffix       = $len > 2 ? substr( $final_salt, -2 ) : '';

		// Non-secret metadata: where the salt came from, its length, a short hash,
		// and a masked preview that never exposes the full salt.
		return array(
			'source'        => '' !== $configured ? 'option_or_filter' : 'default_or_filter',
			'length'        => $len,
			'sha256_12'     => substr( hash( 'sha256', $final_salt ), 0, 12 ),
			'maskedPreview' => $prefix . ( $len > 0 ? '***' : '' ) . $suffix,
		);
	}

	/**
	 * Mirror player/src/security/embed-key.js hash generation.
	 *
	 * @param string $video Media path used by /player/vod?video=...
	 * @param string $validate_playback_session_url Optional session verify URL.
	 * @param string $embed_ancestor Optional frame ancestor origin.
	 * @param string $encrypt_raw Optional encrypt toggle (yes/true/1).
	 * @return string
	 */
	private function wpstream_generate_player_embed_key( $video, $validate_playback_session_url = '', $embed_ancestor = '', $encrypt_raw = '' ) {
		// An empty media path produces no key.
		$video = trim( (string) $video );
		if ( '' === $video ) {
			return '';
		}

		// Base input: "<video> <salt>". Optional parts are prepended in a fixed order.
		$hash_input = $video . ' ' . $this->wpstream_get_player_embed_key_salt();
		// Prepend the session-validation URL when supplied.
		$validate_playback_session_url = trim( (string) $validate_playback_session_url );
		if ( '' !== $validate_playback_session_url ) {
			$hash_input = $validate_playback_session_url . ' ' . $hash_input;
		}

		// Prepend the allowed frame-ancestor origin when supplied.
		$embed_ancestor = trim( (string) $embed_ancestor );
		if ( '' !== $embed_ancestor ) {
			$hash_input = $embed_ancestor . ' ' . $hash_input;
		}

		// Prepend the "crypt" marker when encryption is requested.
		$encrypt_raw = strtolower( trim( (string) $encrypt_raw ) );
		if ( in_array( $encrypt_raw, array( 'yes', 'true', '1' ), true ) ) {
			$hash_input = 'crypt ' . $hash_input;
		}

		// MD5 the assembled input, then base64url-encode it (matching embed-key.js).
		$binary_hash = md5( $hash_input, true );
		$base64      = base64_encode( $binary_hash );

		// URL-safe base64 without padding.
		return rtrim( strtr( $base64, '+/', '-_' ), '=' );
	}

    /**
    * Derive the "live connect" stats URI from an event-status payload.
    *
    * @param array  $event_status     Status data from the cloud API.
    * @param string $playback_url_key Which playback URL key to inspect.
    * @return string The stats URL, a host derived from the playback URL, or ''.
    */
    function wpstream_get_live_connect_uri($event_status, $playback_url_key = 'hls_playback_url') {
        // Prefer an explicit stats_url when the API provides one.
        if (isset($event_status['stats_url']) && trim($event_status['stats_url']) !== '') {
            return trim($event_status['stats_url']);
        }

        // Otherwise, if the playback URL points at the known streamer host, derive it from there.
        if (
            isset($event_status[$playback_url_key]) &&
            trim($event_status[$playback_url_key]) !== '' &&
            strpos($event_status[$playback_url_key], 'live.streamer.wpstream.net') !== false
        ) {
            // Keep everything up to and including the streamer host, then strip the scheme.
            $live_conect_array = explode('live.streamer.wpstream.net', $event_status[$playback_url_key]);
            return $this->remove_http($live_conect_array[0] . 'live.streamer.wpstream.net');
        }

        // No stats URL available.
        return '';
    }
      
    /**
    * Resolve the effective event settings for a product: per-event options when
    * enabled, otherwise the site-wide global channel options.
    *
    * @param int $product_id Product/channel id.
    * @return mixed The per-event options array, or the global options.
    * @author cretu
    */
    function wpestream_return_event_settings($product_id){
        // Per-event options and the flag that says whether to use the global set instead.
        $local_event_options            = get_post_meta( $product_id, 'local_event_options', true);
	    $use_global_event_options       = get_post_meta($product_id, 'use_global_event_options',true);
	    // Local options apply only when they exist and "use global" is not turned on.
	    $is_local_event_options_enabled = isset( $local_event_options ) &&
	                                      ( ! isset( $use_global_event_options ) || (int) $use_global_event_options === 0 );

	    // Fall back to the global channel options when local ones are disabled.
	    if( !$is_local_event_options_enabled ) {
            $local_event_options =   get_option('wpstream_user_streaming_global_channel_options') ;
        }

        return $local_event_options;
    }

	/**
	 * Localized strings for live player UI (parent overlay + iframe postMessage).
	 *
	 * @return array<string,string|bool>
	 */
	private function wpstream_get_player_i18n_config() {
		// Translated player-state strings plus a flag telling the player if the WpStream theme is active.
		return array(
			'wpstream_player_state_stopped_msg'      => esc_html(
				get_option(
					'wpstream_you_are_not_live',
					__( 'We are not live at this moment', 'wpstream' )
				)
			),
			'wpstream_player_state_init_msg'         => esc_html__( 'The live stream has not yet started', 'wpstream' ),
			'wpstream_player_state_startup_msg'      => esc_html__( 'The live stream is starting...', 'wpstream' ),
			'wpstream_player_state_paused_msg'       => esc_html__( 'The live stream is paused', 'wpstream' ),
			'wpstream_player_state_ended_msg'        => esc_html__( 'The live stream has ended', 'wpstream' ),
			'wpstream_player_state_error_msg'        => esc_html__( 'Something went wrong', 'wpstream' ),
			'wpstream_player_offair_default_msg'     => esc_html__( 'Live stream is not on air.', 'wpstream' ),
			'wpstream_player_max_viewers_msg'        => esc_html__( 'Max viewers reached. Please wait', 'wpstream' ),
			'wpstream_player_max_viewers_wait_msg'   => esc_html__( 'Max viewers reached. Please wait for %d to leave', 'wpstream' ),
			'wpstream_player_invalid_session_msg'    => esc_html__( 'Invalid session. Please refresh and start playback again.', 'wpstream' ),
			'isThemeActive'                          => get_template() === 'hello-wpstream',
		);
	}
    
    
    
    /**
    * Render the live event player for a channel.
    *
    * Fetches channel status from the cloud API (cached 45s in a transient),
    * then renders either the legacy Video.js markup or, when the channel has an
    * embedUrl, the newer iframe-based player with a minted embed key. Optionally
    * attaches chat.
    *
    * edited in 4.0
    *
    * @param int    $channel_id  Channel/post id.
    * @param string $poster_show Pass 'no' to suppress the poster image.
    * @param string $use_chat    Pass 'yes' to also render the chat widget.
    * @return void Echoes the player markup.
    * @author cretu
    */
    function wpstream_live_event_player($channel_id,$poster_show='',$use_chat=''){
        // Player dependencies (Video.js core, player glue, and player controls).
        wp_enqueue_script('video.min');
        wp_enqueue_script('wpstream-player');
		wp_enqueue_script( 'wpstream-player-controls' );

	    // Resolve the configured player skin/theme for this channel.
	    $player_theme = $this->wpstream_get_player_theme( $channel_id );
        // Unique-ish id used to disambiguate multiple players on one page.
        $now                =   time().rand(0,1000000);
        $overlay_video_div_id = "random_id_".$now;
        // print '<div id="'.esc_attr($overlay_video_div_id).'" class="vjs-title-overlay wpstream-video-title-overlay">'.esc_html__('Playing:','wpstream').' '.get_the_title($channel_id).'</div>';


        // Poster thumbnail, streaming username, and autoplay default.
        $thumb_id           =   get_post_thumbnail_id($channel_id);
        $thumb              =   wp_get_attachment_image_src($thumb_id,'small');
        $usernamestream     =   esc_html ( get_option('wpstream_api_username','') );
        $autoplay           =   true;

        // Effective per-event / global settings (autoplay, mute, view count, encryption...).
        $event_settings     =   $this->wpestream_return_event_settings($channel_id);

        // Live status, cached for 45s to limit remote API calls.
        $transient_name = 'event_data_to_return_'.   $channel_id;
        $event_status = get_transient( $transient_name );

        if ( false ===  $event_status || $event_status=='' ) {
            // Cache miss: query the cloud API for the channel status and cache it.
            $notes              =   'wpstream_live_event_player_note';
            $event_status       =   $this->main->wpstream_live_connection-> wpstream_check_event_status_api_call($channel_id,$notes);
            set_transient($transient_name, $event_status, 45);
        }

        // Playback URL / stats URI, populated only when the channel is live.
        $hls_playback_url     =   '';
        $live_conect_views  =   '';

        if(isset($event_status['status']) && $event_status['status']=='active'){
            //live event
            if(isset($event_status['hls_playback_url'])){
                // Channel is live: capture the HLS URL.
                $hls_playback_url        =   $event_status['hls_playback_url'];

                // Persist stream name / HLS key URL and clear the free-event name cache.
                update_post_meta($channel_id,'stream_name',$event_status['stream_name']);
                update_post_meta($channel_id,'hls_key_retrieval_url',$event_status['hls_key_retrieval_url']);
                delete_transient(  'free_event_streamName_'.$event_status['stream_name']);

            }
             // Derive the live-viewer/stats URI from the status payload.
             $live_conect_views = $this->wpstream_get_live_connect_uri($event_status, 'hls_playback_url');
             if(isset($event_status['chat_url'])){
                // Capture the chat endpoint when available.
                $chat_url = $event_status['chat_url'];
            }

        }else{
            // event not live
        }

	    // Optional pre-roll trailer attachment; when present it replaces the poster.
	    $trailer_attachment_id = intval( get_post_meta( $channel_id, 'video_trailer', true ) );
	    $video_trailer         = '';
	    $has_trailer_class     = '';
	    if ( $trailer_attachment_id != 0 ) {
		    // Resolve the trailer URL and mime type from the attachment metadata.
		    $video_trailer       = wp_get_attachment_url( $trailer_attachment_id );
		    $attachment_metadata = wp_get_attachment_metadata( $trailer_attachment_id );
		    if ( isset ( $attachment_metadata['mime_type'] ) ) {
			    $video_trailer_type = $attachment_metadata['mime_type'];
		    }
		    $poster_data       = ''; // cancel poster for theme
		    $has_trailer_class = 'wpstream_theme_player_has_trailer';
	    }

        // Honour the "autoplay off" event setting.
        if(isset($event_settings['autoplay']) && intval($event_settings['autoplay'])==0){
            $autoplay=false;
        }

        // Bootstrap data-* values consumed by wpstream-player-bootstrap.js.
        $bootstrap_is_muted = ( isset( $event_settings['mute'] ) && intval( $event_settings['mute'] ) === 1 );
        $bootstrap_trailer_attachment_id = intval( get_post_meta( $channel_id, 'video_trailer', true ) );
        $bootstrap_trailer_url = '';
        if ( $bootstrap_trailer_attachment_id !== 0 ) {
            $bootstrap_trailer_url = wp_get_attachment_url( $bootstrap_trailer_attachment_id );
        }
        // Content / stats / chat URIs passed to the player as data attributes.
        $live_content_uri = isset( $hls_playback_url ) ? trim( $hls_playback_url ) : '';
        $live_stats_uri   = isset( $live_conect_views ) ? trim( $live_conect_views ) : '';
        $live_chat_uri    = isset( $chat_url ) ? trim( $chat_url ) : '';
        // Element ids for the trailer play/mute/unmute buttons.
        $play_trailer_button_element_id   = 'wpstream_live_video_play_trailer_btn_' . $now;
        $mute_trailer_button_element_id   = 'wpstream_live_video_mute_trailer_btn_' . $now;
        $unmute_trailer_button_element_id = 'wpstream_live_video_unmute_trailer_btn_' . $now;



	    // Nonce for the status-poll endpoint (used by the legacy player path).
	    $player_nonce = wp_create_nonce( 'wpstream_player_check_status_nonce' );

		// New iframe player is keyed off the presence of an embedUrl meta value.
		$live_channel_embed_url = get_post_meta( $channel_id, 'embedUrl', true );

		// if embedUrl is set, we are using the new player
		if ( !$live_channel_embed_url ) {
			// Legacy Video.js path: emit the wrapper carrying all bootstrap data-* attributes.
			echo '<div class="wpstream_live_player_wrapper function_wpstream_live_event_player" data-now="' . $now . '" data-me="' . esc_attr( $usernamestream ) . '" data-product-id="' . $channel_id . '" id="wpstream_live_player_wrapper' . $now . '" data-nonce="' . $player_nonce . '" data-wpstream-bootstrap="live" data-instance-id="wpstream-live-' . esc_attr( $now ) . '" data-video-element-id="' . esc_attr( $now ) . '" data-title-overlay-element-id="' . esc_attr( $overlay_video_div_id ) . '" data-content-url="' . esc_attr( $live_content_uri ) . '" data-stats-uri="' . esc_attr( $live_stats_uri ) . '" data-chat-url="' . esc_attr( $live_chat_uri ) . '" data-trailer-url="' . esc_attr( $bootstrap_trailer_url ) . '" data-autoplay="' . ( $autoplay ? '1' : '0' ) . '" data-muted="' . ( $bootstrap_is_muted ? '1' : '0' ) . '" data-play-trailer-button-element-id="' . esc_attr( $play_trailer_button_element_id ) . '" data-mute-trailer-button-element-id="' . esc_attr( $mute_trailer_button_element_id ) . '" data-unmute-trailer-button-element-id="' . esc_attr( $unmute_trailer_button_element_id ) . '" > ';

			// Show the live viewer count unless the setting explicitly disables it.
			$show_viewer_count = (
				( isset( $event_settings['view_count'] ) && intval( $event_settings['view_count'] ) == 1 )
				|| ! isset( $event_settings['view_count'] )
			);

			// Container the JS fills with the live viewer count.
			echo '<div id="wpestream_live_counting" class="wpestream_live_counting" data-showviewercount="' . ( $show_viewer_count ? '1' : '0' ) . '"></div>';

			// Show the "not live" overlay only while there is no playback URL yet.
			$show_wpstream_not_live_mess = ' style="display:none;" ';
			if ( trim( $hls_playback_url ) == '' ) {

				$show_wpstream_not_live_mess = '';
			}

			// Configurable "we are not live" message.
			$message_show = esc_html( get_option( 'wpstream_you_are_not_live', 'We are not live at this moment' ) );

			// Prefer the theme's own not-live section if it exists, else render a default overlay.
			if ( function_exists( 'wpstream_theme_not_live_section' ) ) {
				print wpstream_theme_not_live_section( $channel_id );
			} else {
				print '<div class="wpstream_not_live_mess" ' . $show_wpstream_not_live_mess . ' style="display: none">
					<div class="wpstream_not_live_mess_back"></div>
					<div class="wpstream_not_live_mess_mess">' . esc_html( $message_show ) . '</div>
				</div>';
			}


			// Build the poster attribute; suppressed when caller passes 'no'.
			$poster_data = '';
			if ( isset( $thumb[0] ) ) {
				$poster_data = ' poster="' . $thumb[0] . '" ';
			}
			if ( $poster_show == 'no' ) {
				$poster_data = '';
			}

			// Start muted when the event setting requests it.
			$is_muted = false;
			if ( isset( $event_settings['mute'] ) && intval( $event_settings['mute'] ) == 1 ) {
				$is_muted = true;
			}
			// override $is_muted and $autoplay here - for testing
			// $autoplay = true;
			// $is_muted = false;

			// Translate the autoplay/muted booleans into HTML <video> attributes.
			$autoplay_str = $autoplay ? 'autoplay' : '';
			$is_muted_str = $is_muted ? 'muted' : '';

			// override trailer url here - for testing
			// $video_trailer = '';
			// $video_trailer = '/wp-content/uploads/2023/10/production-ID_4608975.mp4';
			// $video_trailer = '/wp-content/uploads/2023/10/ultrawide.mp4';


			// Watermark/logo placement classes applied to the <video> element.
			$player_logo_position_data       = $this->wpstream_get_player_logo_data( $channel_id );
			$player_logo_position            = $player_logo_position_data['player_logo_position'];
			$player_logo_position_class      = $player_logo_position_data['player_logo_position_class'];
			$player_logo_horizontal_position = $player_logo_position_data['player_logo_horizontal_position'];
//			echo'
//				<div class="wpstream-video-container">
//					<div id="wpstream-pre-load-spinner" class="wpstream-pre-load-spinner"></div>
//					<video id="wpstream-video'.$now.'"     '.$poster_data.'  class="video-js vjs-default-skin  vjs-fluid vjs-wpstream ' . esc_attr($has_trailer_class) . ' ' . $player_theme . ' ' . $player_logo_position_class . ' ' . $player_logo_horizontal_position . '" playsinline="true" '.$is_muted_str." ".$autoplay_str.'>
//					</video>
//				</div>';
			// Pre-load spinner shown until the player is ready.
			echo '<div class="wpstream-pre-load-spinner"></div>';
			// The Video.js element itself, with skin/logo classes and autoplay/muted attributes.
			echo '
					<video id="wpstream-video' . $now . '"     ' . $poster_data . '  class="video-js vjs-default-skin  vjs-fluid vjs-wpstream ' . esc_attr( $has_trailer_class ) . ' ' . $player_theme . ' ' . $player_logo_position_class . ' ' . $player_logo_horizontal_position . '" playsinline="true" ' . $is_muted_str . " " . $autoplay_str . '>

                </video>';
			// When a trailer exists, render its play/mute/unmute button controls (SVG icons).
			if ( $video_trailer ) {
				print '<div class="wpstream_theme_trailer_wrapper">';
				print '<div id="' . esc_attr( $play_trailer_button_element_id ) . '" style="display: none;" class="wpstream_video_on_demand_play_trailer">
                    <svg width="30" height="24" viewBox="0 0 30 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M26.6667 1.5H3.33337C2.50495 1.5 1.83337 2.17157 1.83337 3V21C1.83337 21.8284 2.50495 22.5 3.33338 22.5H26.6667C27.4951 22.5 28.1667 21.8284 28.1667 21V3C28.1667 2.17157 27.4951 1.5 26.6667 1.5ZM3.33337 0C1.67652 0 0.333374 1.34315 0.333374 3V21C0.333374 22.6569 1.67652 24 3.33338 24H26.6667C28.3236 24 29.6667 22.6569 29.6667 21V3C29.6667 1.34315 28.3236 0 26.6667 0H3.33337ZM4.83337 4C4.55723 4 4.33337 4.22386 4.33337 4.5V6.16667C4.33337 6.44281 4.55723 6.66667 4.83337 6.66667H6.50004C6.77618 6.66667 7.00004 6.44281 7.00004 6.16667V4.5C7.00004 4.22386 6.77618 4 6.50004 4H4.83337ZM23.5 4C23.2239 4 23 4.22386 23 4.5V6.16667C23 6.44281 23.2239 6.66667 23.5 6.66667H25.1667C25.4428 6.66667 25.6667 6.44281 25.6667 6.16667V4.5C25.6667 4.22386 25.4428 4 25.1667 4H23.5ZM4.33337 11.167C4.33337 10.8909 4.55723 10.667 4.83337 10.667H6.50004C6.77618 10.667 7.00004 10.8909 7.00004 11.167V12.8337C7.00004 13.1098 6.77618 13.3337 6.50004 13.3337H4.83337C4.55723 13.3337 4.33337 13.1098 4.33337 12.8337V11.167ZM23.5001 10.667C23.224 10.667 23.0001 10.8909 23.0001 11.167V12.8337C23.0001 13.1098 23.224 13.3337 23.5001 13.3337H25.1668C25.4429 13.3337 25.6668 13.1098 25.6668 12.8337V11.167C25.6668 10.8909 25.4429 10.667 25.1668 10.667H23.5001ZM4.33337 17.833C4.33337 17.5569 4.55723 17.333 4.83337 17.333H6.50004C6.77618 17.333 7.00004 17.5569 7.00004 17.833V19.4997C7.00004 19.7758 6.77618 19.9997 6.50004 19.9997H4.83337C4.55723 19.9997 4.33337 19.7758 4.33337 19.4997V17.833ZM23.5001 17.333C23.224 17.333 23.0001 17.5569 23.0001 17.833V19.4997C23.0001 19.7758 23.224 19.9997 23.5001 19.9997H25.1668C25.4429 19.9997 25.6668 19.7758 25.6668 19.4997V17.833C25.6668 17.5569 25.4429 17.333 25.1668 17.333H23.5001ZM19.0677 13.0997L13.4077 16.5087C13.0434 16.7281 12.6092 16.7094 12.2661 16.5091C11.9218 16.3081 11.6666 15.9224 11.6666 15.4086V8.59072C11.6666 8.07698 11.9218 7.69125 12.2661 7.49026C12.6092 7.28999 13.0434 7.27126 13.4077 7.49064L19.0677 10.8996C19.8663 11.3805 19.8663 12.6188 19.0677 13.0997Z"/>
                    </svg>
                    ' . esc_html__( 'Play Trailer', 'wpstream' ) . '</div>';
				print '<div id="' . esc_attr( $mute_trailer_button_element_id ) . '" style="display: none;" class="wpstream_video_on_demand_mute_trailer">
                    <svg width="37" height="36" viewBox="0 0 37 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.32143 10.0789H8.69499L18.8964 0L21.1428 0.921053V35.1316L18.8964 36L8.69499 25.8684H1.32143L0 24.5526V11.3947L1.32143 10.0789ZM10.175 23.6842L18.5 31.9474V4.10526L10.175 12.3158L9.24999 12.7105H2.64286V23.2368H9.24999L10.175 23.6842ZM37 17.9737C37.0069 22.2216 35.5329 26.3401 32.8295 29.6263L30.9478 27.7579C33.1613 24.9734 34.3629 21.5249 34.3571 17.9737C34.3571 14.2895 33.0885 10.8974 30.9637 8.21053L32.8454 6.34211C35.5382 9.62494 37.0062 13.735 37 17.9737ZM31.7143 17.9737C31.7193 20.8255 30.7895 23.6011 29.0661 25.8789L27.1738 23.9947C28.4127 22.2295 29.0752 20.1272 29.0714 17.9737C29.0751 15.8287 28.4174 13.7344 27.1871 11.9737L29.0793 10.0895C30.7338 12.2868 31.7143 15.0158 31.7143 17.9737ZM26.4286 17.9737C26.4286 19.4842 26.0057 20.8947 25.2657 22.0947L23.3126 20.1526C23.6249 19.4729 23.7876 18.7345 23.7899 17.9869C23.7922 17.2394 23.634 16.5001 23.3258 15.8184L25.2789 13.8737C26.0083 15.0684 26.4286 16.4737 26.4286 17.9737Z" fill="white"/>
                    </svg>

                    </div>';
				print '<div id="' . esc_attr( $unmute_trailer_button_element_id ) . '" style="display: none;" class="wpstream_video_on_demand_unmute_trailer">
                    <svg width="33" height="32" viewBox="0 0 33 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.15625 8.85688H7.60813L16.5344 0L18.5 0.809375V30.8719L16.5344 31.635L7.60813 22.7319H1.15625L0 21.5756V10.0131L1.15625 8.85688ZM8.90313 20.8125L16.1875 28.0738V3.6075L8.90313 10.8225L8.09375 11.1694H2.3125V20.4194H8.09375L8.90313 20.8125ZM30.5967 11.3127L32.2316 12.9477L28.2287 16.9506L32.2316 20.9559L30.5967 22.5908L26.5938 18.5856L22.5885 22.5908L20.9536 20.9559L24.9588 16.9506L20.9513 12.95L22.5862 11.3151L26.5938 15.3157L30.5967 11.3127Z" fill="white"/>
                    </svg>
                    </div>';
				print '</div>';
			}
			// Close the legacy player wrapper.
			print '</div>';


			// Live player bootstrap is handled by wpstream-player-bootstrap.js.


			// Optionally attach the live chat widget.
			if ( $use_chat == "yes" ) {
				$this->wpstream_connect_to_chat( $channel_id );
			}

			// Small pause so the unique $now id differs across rapid successive renders.
			usleep( 10000 );
		} else {
			// New iframe player path: assemble the /player/live iframe URL and query args.
			$live_channel_frame_base               = WPSTREAM_PLAYER . "/player/live?";
			$live_channel_id                       = get_post_meta( $channel_id, 'channelId', true );

			// Global channel options drive session encryption, domain lock, and autoplay.
			$local_event_options                   = get_option( 'wpstream_user_streaming_global_channel_options' );
			$wpstream_session_encryption           = false;
			$wpstream_live_channel_lock_to_website = false;
			$wpstream_live_channel_autoplay        = false;

			// Session encryption on?
			if ( isset( $local_event_options['ses_encrypt'] ) && intval( $local_event_options['ses_encrypt'] ) == 1 ) {
				$wpstream_session_encryption = true;
			}
			// Lock playback to this website's origin?
			if ( isset( $local_event_options['domain_lock'] ) && intval( $local_event_options['domain_lock'] ) == 1 ) {
				$wpstream_live_channel_lock_to_website = true;
			}
			// Autoplay on?
			if ( isset( $local_event_options['autoplay'] ) && intval( $local_event_options['autoplay'] ) == 1 ) {
				$wpstream_live_channel_autoplay = true;
			}

			// When session encryption is on, build the validate-session URL and expose it to the controls JS.
			$wpstream_live_channel_validate_url = '';
			if ( $wpstream_session_encryption ) {
				$wpstream_live_channel_validate_url = esc_url_raw(
					apply_filters(
						'wpstream_live_channel_validate_playback_session_url',
						$this->playback_session->wpstream_get_default_validate_playback_session_url(),
						$live_channel_id
					)
				);
				wp_localize_script(
					'wpstream-player-controls',
					'wpstreamLiveIframeSessionApi',
					array(
						'requirePlaybackSession'            => true,
						'nonce'                             => wp_create_nonce( 'wpstream_playback_session_issue' ),
						'productId'                         => (int) $channel_id,
						'ajaxUrl'                           => admin_url( 'admin-ajax.php' ),
					)
				);
			}

			// Re-read the options and derive integer flags used in the iframe query args.
			$local_event_options                   = get_option( 'wpstream_user_streaming_global_channel_options' );
			$wpstream_live_channel_lock_to_website = 0;
			$wpstream_live_channel_encrypt         = 0;
			$wpstream_live_channel_abr             = 0;
			$wpstream_live_channel_muted           = 0;

			// Domain lock flag.
			if ( isset( $local_event_options['domain_lock'] ) && intval( $local_event_options['domain_lock'] ) == 1 ) {
				$wpstream_live_channel_lock_to_website = 1;
			}
			// HLS encryption flag.
			if ( isset( $local_event_options['encrypt'] ) && intval( $local_event_options['encrypt'] ) == 1 ) {
				$wpstream_live_channel_encrypt = 1;
			}
			// Adaptive bitrate flag.
			if ( isset( $local_event_options['adaptive_bitrate'] ) && intval( $local_event_options['adaptive_bitrate'] ) == 1 ) {
				$wpstream_live_channel_abr = 1;
			}
			// Start-muted flag.
			if ( isset( $local_event_options['mute'] ) && intval( $local_event_options['mute'] ) == 1 ) {
				$wpstream_live_channel_muted = 1;
			}

			// Frame-ancestor origin, only set when playback is locked to this website.
			$live_channel_embed_ancestor = '';
			if ( $wpstream_live_channel_lock_to_website ) {
				$live_channel_embed_ancestor = esc_url_raw(
					apply_filters(
						'wpstream_live_channel_embed_ancestor',
						$this->wpstream_get_site_origin_for_embed(),
						$live_channel_id
					)
				);
			}

			// Mint the embed key for the live channel from the same inputs as the iframe URL.
			$wpstream_live_channel_embed_key = $this->wpstream_generate_player_embed_key(
				$live_channel_id,
				$wpstream_live_channel_validate_url,
				$live_channel_embed_ancestor,
				$wpstream_live_channel_encrypt ? 'yes' : ''
			);
			// Fall back to a stored embed_key if the generated one is empty.
			if ( '' === $wpstream_live_channel_embed_key ) {
				$wpstream_live_channel_embed_key = (string) get_post_meta( $channel_id, 'embed_key', true );
			}
			// Separate embed key for the trailer iframe.
			$wpstream_live_channel_trailer_embed_key = $this->wpstream_generate_player_embed_key(
				$video_trailer,
				$wpstream_live_channel_validate_url,
				$live_channel_embed_ancestor,
				$wpstream_live_channel_encrypt ? 'yes' : ''
			);

			// Map the Video.js theme name to a bare skin slug the iframe player understands.
			$wpstream_player_iframe_skin_slug = '';
			if ( preg_match( '/^vjs-theme-(city|fantasy|forest|sea)$/', $player_theme, $vod_iframe_skin_m ) ) {
				$wpstream_player_iframe_skin_slug = $vod_iframe_skin_m[1];
			}
			// Viewer-count badge on unless the setting disables it.
			$wpstream_show_viewer_count = (
				( isset( $event_settings['view_count'] ) && intval( $event_settings['view_count'] ) == 1 )
				|| ! isset( $event_settings['view_count'] )
			);

			// Poster image and logo/watermark for the iframe query.
			$wpstream_poster_image = get_the_post_thumbnail_url( $channel_id, 'full' );

			$wpstream_player_logo_data       = $this->wpstream_get_player_logo_data( $channel_id );
			$wpstream_player_logo_image      = $wpstream_player_logo_data['player_logo_src'];

			// Assemble the live iframe query args, dropping null/empty values.
			$live_channel_query_args = array_filter(
				array(
					'channel'                    => $live_channel_id,
					'posterImage'                => $wpstream_poster_image,
					'embedKey'                   => $wpstream_live_channel_embed_key,
					'autoplay'                   => $wpstream_live_channel_autoplay ? true : '',
					'startMuted'                 => (bool) $wpstream_live_channel_muted,
					'viewerCountBadge'           => $wpstream_show_viewer_count ? true : '',
					'encrypt'                    => $wpstream_live_channel_encrypt ? true : '',
					'abr'                        => $wpstream_live_channel_abr ? true : '',
					'validatePlaybackSessionUrl' => $wpstream_live_channel_validate_url,
					'embedAncestor'              => $live_channel_embed_ancestor,
					'skin'                       => $wpstream_player_iframe_skin_slug,
					'logoImage'                  => $wpstream_player_logo_image,
					'logoPosition'               => $wpstream_player_logo_image ? $wpstream_player_logo_data['player_logo_position'] : '',
					'logoOpacity'                => $wpstream_player_logo_image ? $wpstream_player_logo_data['player_logo_opacity'] : '',
					'isThemeActive'              => get_template() == 'hello-wpstream',
				),
				static function ( $v ) {
					return $v !== null && $v !== '';
				}
			);

			// Assemble the trailer iframe query args, dropping null/empty values.
			$live_channel_trailer_iframe_query_args = array_filter(
				array(
					'video'                      => $video_trailer,
					'embedKey'                   => $wpstream_live_channel_trailer_embed_key,
					'skin'                       => $wpstream_player_iframe_skin_slug,
					'startMuted'                 => (bool) $wpstream_live_channel_muted,
					'encrypt'                    => $wpstream_live_channel_encrypt ? true : '',
					'validatePlaybackSessionUrl' => $wpstream_live_channel_validate_url,
					'embedAncestor'              => $live_channel_embed_ancestor,
				),
				static function ( $v ) {
					return $v !== null && $v !== '';
				}
			);

			// Emit the content iframe pointing at /player/live with the assembled query.
			echo '<div class="wpstream_player_iframe_wrap">';
			echo '<iframe id="playerFrame" 
							class="wpstream_live_channel_iframe" 
							title="' . esc_attr__( 'Embedded content', 'wpstream' ) . '" 
							src="' . esc_url( add_query_arg( $live_channel_query_args, $live_channel_frame_base ) ) . '"
							data-wpstream-frame-role="content"
							allowfullscreen 
							allow="autoplay; fullscreen">
					</iframe>';
			// Add the hidden trailer iframe only when a trailer exists and the WpStream theme is active.
			if ( $trailer_attachment_id && get_template() === 'hello-wpstream' ) {
				echo '<iframe id="playerFrameTrailer" 
							class="wpstream_live_channel_iframe wpstream_live_channel_iframe_trailer" 
							title="' . esc_attr__( 'Embedded trailer content', 'wpstream' ) . '" 
							src="' . esc_url( add_query_arg( $live_channel_trailer_iframe_query_args, WPSTREAM_PLAYER . "/player/vod?" ) ) . '"
							data-wpstream-frame-role="trailer"
							allowfullscreen
							allow="autoplay; fullscreen"
							style="display:none;"
							aria-hidden="true"
							tabindex="-1">
						</iframe>';
			}
			echo '</div>';

		}

    }





    /**
    * Render the low-latency (SLDP) live player for a channel.
    *
    * Like wpstream_live_event_player() but uses the sldp_playback_url and calls
    * the cloud API directly on every render (no status transient here).
    *
    * Edited in 4.0
    *
    * @param int    $channel_id  Channel/post id.
    * @param string $poster_show Pass 'no' to suppress the poster image.
    * @param string $use_chat    Pass 'yes' to also render the chat widget.
    * @return void Echoes the player markup.
    * @author cretu
    */
    function wpstream_live_event_player_low_latency($channel_id,$poster_show='',$use_chat=''){
            // Streaming username, poster thumbnail, and effective event settings.
            $usernamestream         =   esc_html ( get_option('wpstream_api_username','') );
            $thumb_id               =   get_post_thumbnail_id($channel_id);
            $thumb                  =   wp_get_attachment_image_src($thumb_id,'small');

            $event_settings     =   $this->wpestream_return_event_settings($channel_id);
            // Fetch channel status straight from the cloud API (no transient caching here).
            $notes              =   'wpstream_live_event_player_low_latency_note';
            $event_status       =   $this->main->wpstream_live_connection-> wpstream_check_event_status_api_call($channel_id,$notes);
            $hls_playback_url     =   '';
            $live_conect_views  =   '';
            // Unique-ish id to disambiguate multiple players on one page.
            $now                =   time().rand(0,10);



            if(isset($event_status['status']) && $event_status['status']=='active'){
                //live event
                if(isset($event_status['sldp_playback_url'])){
                    // Low-latency stream uses the SLDP playback URL.
                    $hls_playback_url         =   $event_status['sldp_playback_url'];

                }
                 // Derive the stats URI from the SLDP URL.
                 $live_conect_views = $this->wpstream_get_live_connect_uri($event_status, 'sldp_playback_url');
                 if(isset($event_status['chat_url'])){
                    // Capture the chat endpoint when present.
                    $chat_url =$event_status['chat_url'];
                }

            }else{
                // event not live
            }



            // Resolve muted/autoplay from settings and normalise the content/chat/stats URIs.
            $low_latency_is_muted = ( isset($event_settings['mute']) && intval($event_settings['mute']) == 1 );
            $low_latency_autoplay = true;
            if ( isset($event_settings['autoplay']) && intval($event_settings['autoplay']) == 0 ) {
                $low_latency_autoplay = false;
            }
            $low_latency_content_uri = isset( $hls_playback_url ) ? trim( $hls_playback_url ) : '';
            $low_latency_chat_uri    = isset( $chat_url ) ? trim( $chat_url ) : '';
            $low_latency_stats_uri   = isset( $live_conect_views ) ? trim( $live_conect_views ) : '';

            // Player wrapper carrying the low-latency bootstrap data-* attributes.
            echo '<div class="wpstream_live_player_wrapper function_wpstream_live_event_player_low_latency wpstream_low_latency" data-now="'.$now.'" data-me="'.esc_attr($usernamestream).'" data-product-id="'.$channel_id.'" id="wpstream_live_player_wrapper'.$now.'" data-instance-id="wpstream-live-low-latency-'.esc_attr( $now ).'" data-wpstream-bootstrap="live-low-latency" data-event-id="'.esc_attr( $channel_id ).'" data-video-element-id="wpstream-video'.esc_attr( $now ).'" data-content-url="'.esc_attr( $low_latency_content_uri ).'" data-chat-url="'.esc_attr( $low_latency_chat_uri ).'" data-stats-uri="'.esc_attr( $low_latency_stats_uri ).'" data-autoplay="'. ( $low_latency_autoplay ? '1' : '0' ) .'" data-muted="'. ( $low_latency_is_muted ? '1' : '0' ) .'" > ';
                    
                   
                    // Live viewer-count container, shown unless the setting disables it.
                    if( ( isset($event_settings['view_count'] ) && intval($event_settings['view_count'])==1 ) || !isset($event_settings['view_count']) ){
                        echo '<div id="wpestream_live_counting" class="wpestream_live_counting"></div>';
                    }

                    // Show the "not live" overlay only while there is no playback URL yet.
                    $show_wpstream_not_live_mess=' style="display:none;" ';
                    if(trim($hls_playback_url) ==''){
                        $show_wpstream_not_live_mess='';
                    }

                    // Configurable "not live" message overlay.
                    $message_show= esc_html( get_option('wpstream_you_are_not_live','We are not live at this moment')) ;
                    print '<div class="wpstream_not_live_mess " '.$show_wpstream_not_live_mess.' ><div class="wpstream_not_live_mess_back"></div><div class="wpstream_not_live_mess_mess">'. $message_show.'</div></div>';

                    // Poster attribute, suppressed when caller passes 'no'.
                    $poster_data=' poster="'.$thumb[0].'" ';
                    if($poster_show=='no'){
                        $poster_data='';
                    }

                    // Muted attribute derived from the low-latency mute flag.
                    $is_muted='';
                    if( $low_latency_is_muted ){
                        $is_muted=' muted ';
                    }


                    // Autoplay attribute derived from the low-latency autoplay flag.
                    $autoplay='autoplay';
                    if( !$low_latency_autoplay ){
                        $autoplay='';
                    }

                    // Empty player container; the SLDP player JS attaches to this element.
                    echo'
                    <div  iccd="player" id="wpstream-video'.$now.'"   '.$poster_data.' '.$is_muted.' class="" >
                    </div>';

                    // Low-latency player bootstrap is handled by wpstream-player-bootstrap.js.


               // Optionally attach the live chat widget.
               if($use_chat=="yes"){
                    $this->wpstream_connect_to_chat($channel_id);
               }

               // Brief pause so the unique $now id differs across rapid successive renders.
               usleep (10000);

        }


        
    
        
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
        public function  wpstream_request_video_on_demand_hls_player($video_name,$product_id){
            // No video name means nothing to request.
            if($video_name==''){
                return '';
            }

            // Cached HLS URL for this video (read then forced to false, see note above).
            $transient_name =   'wpstream_video_on_demand_'.$video_name;
            $hls_to_return  =   get_transient( $transient_name );
            $hls_to_return  =   false;


            if($hls_to_return==false){
                // Get an API access token and the endpoint for video info.
                $access_token   =   $this->main->wpstream_live_connection->wpstream_get_token();
                $url            =   'video/info';

                //corsorigin de check
                // Determine the request scheme (http/https) for the CORS origin.
                $local_event_options    =   get_option('wpstream_user_streaming_global_channel_options') ;
                $domain                 =   parse_url ( get_site_url() );
                $domain_scheme          =   'http';
                if(is_ssl()){
                    $domain_scheme='https';
                }

                // With VOD domain-lock on, restrict CORS to this site's origin; otherwise allow any.
                $wpstream_vod_domain_lock = intval( get_option('wpstream_vod_domain_lock','') ) ;
                $corsorigin='*';
                if($wpstream_vod_domain_lock !== 0 ){
                    $corsorigin=$domain_scheme.'://'.$domain['host'];
                }

                // Request HLS encryption when the VOD encrypt option is enabled.
                $is_encrypt="false";
                $wpstream_vod_encrypt = intval( get_option('wpstream_vod_encrypt','') ) ;
                if( intval( $wpstream_vod_encrypt ) ==1 ){
                    $is_encrypt="true";
                }

                // Prefix the encryption-key requests route back through this site (?wpstream_voddrm=).
                $hlsKeysUrlPrefix    =  get_site_url().'?wpstream_voddrm=';
                $encrypt             =  $is_encrypt;
                $debugDrm            =  false;

                // Body of the API request.
                $curl_post_fields=array(
                    'access_token'      =>  $access_token,
                    'name'              =>  $video_name,
                    'corsOrigin'        =>  $corsorigin,
                    'encryptHls'        =>  $encrypt,
                    'hlsKeysUrlPrefix'  =>  $hlsKeysUrlPrefix,
                    'debugDrm'          =>  $debugDrm,
	                'embed'             => true,
                );

       
                // Perform the API call and decode the JSON response as an array.
                $curl_response          =   $this->main->wpstream_live_connection->wpstream_baker_do_curl_base($url,$curl_post_fields);
                $curl_response_decoded  =   json_decode($curl_response,JSON_OBJECT_AS_ARRAY);

                if($curl_response_decoded['success']){
                    // Cache the HLS URL for 5 minutes and use it as the return value.
                    set_transient(  $transient_name, $curl_response_decoded['hlsUrl'] ,300);
                    $hls_to_return =  $curl_response_decoded['hlsUrl'];
                    // Store or clear the HLS decryption key/index depending on the response.
                    if( isset($curl_response_decoded['hlsDecryptionKey']) && isset($curl_response_decoded['hlsDecryptionKeyIndex']) ){
                        update_post_meta($product_id,'hlsDecryptionKey',$curl_response_decoded['hlsDecryptionKey']);
                        update_post_meta($product_id,'hlsDecryptionKeyIndex',$curl_response_decoded['hlsDecryptionKeyIndex']);
                    }else{
                        delete_post_meta($product_id,'hlsDecryptionKey');
                        delete_post_meta($product_id,'hlsDecryptionKeyIndex');
                    }

					// When the API returns new-player embed data, persist it as post meta.
					if ( isset( $curl_response_decoded['video'] ) &&
						isset( $curl_response_decoded['embedKey'] ) &&
						isset( $curl_response_decoded['embedUrl'] )
					) {
						update_post_meta( $product_id, 'wpstream_vod_video_data', trim( (string) $curl_response_decoded['video'] ) );
						update_post_meta( $product_id, 'wpstream_vod_embed_key', trim( (string) $curl_response_decoded['embedKey'] ) );
						update_post_meta( $product_id, 'wpstream_vod_embed_url', trim( (string) $curl_response_decoded['embedUrl'] ) );
					}
                }else{
                    // API reported failure: no URL to return.
                    return '';
                }

            }

            return $hls_to_return;

        }

        /**
        * Resolve the CSS class for the configured Video.js theme/skin.
        *
        * Enqueues the theme stylesheet as a side effect. Streamify users get no theme.
        *
        * @param int|null $channel_id Channel/product id (used for the streamify check).
        * @return string A 'vjs-theme-*' class, or '' when no theme applies.
        */
        function wpstream_get_player_theme( $channel_id = null ) {
			// Configured theme name and whether this is a streamify (basic) user.
			$player_theme = get_option('wpstream_video_player_theme');
			$is_streamify_user = $this->wpstream_is_streamify_user( $channel_id );
			// Apply the theme only for non-streamify users with a theme set.
			if ( !empty($player_theme) && !$is_streamify_user ) {
				$this->wpstream_enqueue_player_theme_style( $player_theme );
				return 'vjs-theme-' . $player_theme;
			}

			// No theme (default skin or streamify user).
			return '';
		}

		/**
		 * Enqueue the Video.js theme stylesheet from the jsDelivr CDN.
		 *
		 * @param string $player_theme Theme slug (skips the built-in 'default').
		 * @return void
		 */
		function wpstream_enqueue_player_theme_style( $player_theme ) {
			// The 'default' skin ships with Video.js, so only load external theme CSS.
			if ( $player_theme != 'default' ) {
				wp_enqueue_style('videojs-theme-' . $player_theme, 'https://cdn.jsdelivr.net/npm/@videojs/themes@1/dist/' . $player_theme . '/index.min.css', array(), '1.0.0');

			}
		}

		/*
		 * Return the player logo image URL plus its CSS position classes/opacity.
		 *
		 * @param int $product_id Product/channel id.
		 * @return array Logo src, position, position classes, and opacity.
		 */
		private function wpstream_get_player_logo_data( $product_id ): array {
			// Configured logo corner, e.g. "top-left"; split into vertical/horizontal classes.
			$player_logo_position            = get_option( 'wpstream_player_logo_position', 'top-left' );
			$player_logo_position_class      = '';
			$player_logo_horizontal_position = '';
			if ( $player_logo_position && $player_logo_position != '' ) {
				$player_logo_position_parts = explode( '-', $player_logo_position );
				if ( isset( $player_logo_position_parts[0], $player_logo_position_parts[1] ) ) {
					$player_logo_position_class      = 'logo-' . $player_logo_position_parts[0];
					$player_logo_horizontal_position = 'logo-' . $player_logo_position_parts[1];
				}
			}

			// Logo image URL and its opacity (stored as a percentage, returned as 0-1).
			$player_logo_src = $this->wpstream_get_video_player_logo( $product_id );
			$player_logo_opacity = intval( esc_html( get_option('wpstream_player_logo_opacity','100') ) ) / 100;

			// Bundle the logo image, corner, derived classes, and opacity.
			return array(
				'player_logo_src'                 => $player_logo_src,
				'player_logo_position'            => $player_logo_position,
				'player_logo_position_class'      => $player_logo_position_class,
				'player_logo_horizontal_position' => $player_logo_horizontal_position,
				'player_logo_opacity'             => $player_logo_opacity,
			);
		}

        
        
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
        public function wpstream_video_on_demand_player_uri_request($product_id){
                // Base Video.js data-setup attribute.
                $wpstream_data_setup    =   '  data-setup="{}" ';

                /* free_video_type
                 * 1 - free live channel
                 * 2 - free video encrypted
                 * 3 - free video -not encrypted
                 */

                // Post type and the stored "free video type" classification.
                $post_type              =   get_post_type($product_id);
                $free_video_type        =   intval( get_post_meta($product_id, 'wpstream_product_type', true));
                $video_path_final       =   '';
                $video_type             =   '';
                if( (  $post_type  =='wpstream_product_vod' && $free_video_type==2 ) || get_post_type($product_id)=='product' ){

                    /*
                    * IF vide is encrypted-  readed from vod,streaner
                    *
                    */

                    // Encrypted / paid VOD: fetch an HLS manifest from the cloud API.
                    $video_type         =   'application/x-mpegURL';
                    $video_path         =   get_post_meta($product_id,'_movie_url',true);
                    if(get_post_type($product_id)=='wpstream_product_vod'){
                        // Free-VOD post type stores the source under a different meta key.
                        $video_path =    esc_html(get_post_meta($product_id, 'wpstream_free_video', true));
                    }
                    $video_path_final = $this->wpstream_request_video_on_demand_hls_player($video_path,$product_id);


                }else if(   $post_type =='wpstream_product_vod'  && $free_video_type==3 ){

                    /* Video is unecrypted - read from local or youtube / vimeo
                    */

                    // Unencrypted VOD: use the stored external URL and load the YouTube tech.
                    $video_type         =   'video/mp4';
                    $video_path_final=esc_html(get_post_meta($product_id, 'wpstream_free_video_external', true));
                    wp_enqueue_script('youtube.min');
                }

            // Package the resolved source details for the caller.
            $return_array                       =   array();
            $return_array['video_path_final']   =   $video_path_final;
            $return_array['wpstream_data_setup']=   $wpstream_data_setup;
            $return_array['video_type']         =   $video_type;
            $return_array['free_video_type']    =   $free_video_type;
            $return_array['post_type']          =   $post_type ;
            return $return_array;
 }
     
 
 
         /**
        * VODPlayer url
        *
        * @author cretu
        */

        public function wpstream_video_on_demand_player($product_id){
			// Player dependencies (Video.js core, glue script, controls).
			wp_enqueue_script('video.min');
            wp_enqueue_script('wpstream-player');
	        wp_enqueue_script( 'wpstream-player-controls' );

			// Resolve the configured skin/theme and the VOD source details.
			$player_theme = $this->wpstream_get_player_theme( $product_id );

            $uri_details        =   $this->wpstream_video_on_demand_player_uri_request($product_id);
            $video_path_final   =   $uri_details['video_path_final'];
            $wpstream_data_setup =  $uri_details['wpstream_data_setup'];
            $video_type          =  $uri_details['video_type'];
            // Unique-ish id for this player instance.
            $now                =   time().rand(0,1000000);

            // Render the optional title overlay via the theme action hook.
            $overlay_video_div_id = "random_id_".$now;
            $this->wpstream_render_vod_title_overlay(
                $overlay_video_div_id,
                get_the_title($product_id)
            );

            // Poster thumbnail and streaming username.
            $thumb_id               =   get_post_thumbnail_id($product_id);
            $thumb                  =   wp_get_attachment_image_src($thumb_id,'small');
            $usernamestream         =   esc_html ( get_option('wpstream_api_username','') );

            $poster_thumb           =   '';
            if(isset($thumb[0])){
                $poster_thumb=$thumb[0];
            }

            // DRM decryption key/index stored earlier by the HLS request.
            $hlsDecryptionKey       =   get_post_meta($product_id,'hlsDecryptionKey',true);
            $hlsDecryptionKeyIndex  =   get_post_meta($product_id,'hlsDecryptionKeyIndex',true);


			// Quota/pack data used below to decide whether streaming is allowed.
			$pack = $this->main->quota_manager->get_live_quota_data( 'wpstream_video_on_demand_player' );



            // Optional pre-roll trailer attachment and its mime type.
            $trailer_attachment_id    =  intval (get_post_meta( $product_id, 'video_trailer', true ));
            $video_trailer            = '';
            $video_trailer_type       = '';
            if($trailer_attachment_id!=0) {
                $video_trailer                 =   wp_get_attachment_url( $trailer_attachment_id );
                $attachment_metadata           =   wp_get_attachment_metadata($trailer_attachment_id);
	            if( isset ($attachment_metadata['mime_type']) ) {
		            $video_trailer_type            =   $attachment_metadata['mime_type'];
	            }
            }

            // override trailer setup here (for testing)
            // $trailer_attachment_id = 1;
            // $video_trailer = '/wp-content/uploads/2023/10/production-ID_4608975.mp4';
            // $video_trailer = '/wp-content/uploads/2023/10/ultrawide.mp4';

            // If the video is self hosted or external, we should let the user see it
            // (type 3 = unencrypted; bypasses the quota check below).
            $video_type = intval( get_post_meta($product_id, 'wpstream_product_type', true));


            // Render the player only when the quota allows VOD streaming, or the video is unencrypted (type 3).
            if ( $this->main->quota_manager->can_stream_vod( $pack ) || $video_type === 3 ) {

                if($video_path_final==''){
                    // Empty source: show a "missing video" notice, except for unencrypted VODs.
                    if( $uri_details['post_type']=='wpstream_product_vod'  && $uri_details['free_video_type']==3 ){
                    }else{
                        print '<div class="wpstream_vod_notice">This video does not exist or it has been deleted!</div>';
                    }

                }

                // Autoplay/mute defaults, overridden by the VOD options below.
                // TODO (crerem) populate these from VOD settings
                $autoplay = false;
                $muted = false;

                // Start muted when the VOD "start muted" option is on.
                $wpstream_vod_start_muted   =   intval ( get_option('wpstream_vod_start_muted','') );
                if($wpstream_vod_start_muted===1){
                    $muted=true;
                }
                // Autoplay when the VOD autoplay option is on.
                $wpstream_vod_autoplay      =   intval  ( get_option('wpstream_vod_autoplay','') );
                if($wpstream_vod_autoplay===1){
                    $autoplay=true;
                }

                // Poster attribute; dropped in favour of a trailer when one exists.
                $poster_data = 'poster="'.esc_url($poster_thumb).'"';
                $has_trailer_class='';
                if($trailer_attachment_id !=0){
                    $poster_data=''; // cancel poster for theme
                    $has_trailer_class='wpstream_theme_player_has_trailer';
                }

	            // Logo/watermark image, position, and opacity for the player.
	            $player_logo_data                = $this->wpstream_get_player_logo_data( $product_id );
	            $player_logo_image               = $player_logo_data['player_logo_src'];
	            $player_logo_position            = $player_logo_data['player_logo_position'];
	            $player_logo_position_class      = $player_logo_data['player_logo_position_class'];
	            $player_logo_horizontal_position = $player_logo_data['player_logo_horizontal_position'];
	            $player_logo_opacity			 = $player_logo_data['player_logo_opacity'];

				// Captions file and the trailer/video control button element ids.
				$captionsUrl = get_post_meta( $product_id, 'wpstream_closed_captions_file', true );
	            $play_trailer_button_element_id = $trailer_attachment_id != 0 ? 'wpstream_video_on_demand_play_trailer_btn_' . $now : '';
                $mute_trailer_button_element_id = $trailer_attachment_id != 0 ? 'wpstream_video_on_demand_mute_trailer_btn_' . $now : '';
                $unmute_trailer_button_element_id = $trailer_attachment_id != 0 ? 'wpstream_video_on_demand_unmute_trailer_btn_' . $now : '';
	            $play_video_button_element_id = $trailer_attachment_id != 0 ? 'wpstream_video_on_demand_play_video_btn_' . $now : '';

				// VOD encryption and domain-lock options.
				$wpstream_vod_encrypt = intval( get_option( 'wpstream_vod_encrypt', '' ) );
				$wpstream_vod_lock_to_website = intval( get_option( 'wpstream_vod_domain_lock', '' ) );

				// Session encryption flag from the global channel options.
				$wpstream_session_encryption = 0;
	            $local_event_options         =   get_option('wpstream_user_streaming_global_channel_options') ;
	            if(isset($local_event_options['ses_encrypt']) && intval($local_event_options['ses_encrypt'])==1 ) {
		            $wpstream_session_encryption = 1;
	            }

		            /**
				 * Presence bootstrap expects validatePlaybackSessionUrl as an absolute HTTP(S) URL that returns JSON { success: true } when the playback session is valid — not the literals "true"/"false".
				 * Embed keys must be minted with the same inputs as the iframe query (video, validatePlaybackSessionUrl, embedAncestor, encrypt) per player embed-key.js.
				 *
				 * @see WPStream player embed-key.js and session-validation.js (verifyPlaybackSessionUrl).
				 */
				// With session encryption on, build the validate-session URL and expose the session API to the controls JS.
				$vod_validate_url = '';
				if ( $wpstream_session_encryption ) {
					$vod_validate_url = esc_url_raw(
						apply_filters(
							'wpstream_vod_validate_playback_session_url',
							$this->playback_session->wpstream_get_default_validate_playback_session_url(),
							$product_id
						)
					);
					wp_localize_script(
						'wpstream-player-controls',
						'wpstreamVodIframeSessionApi',
						array(
							'requirePlaybackSession' => true,
							'nonce'                    => wp_create_nonce( 'wpstream_playback_session_issue' ),
							'productId'                => (int) $product_id,
							'ajaxUrl'                  => admin_url( 'admin-ajax.php' ),
						)
					);
				}

				// Frame-ancestor origin, only when VOD playback is locked to this website.
				$vod_embed_ancestor = '';
				if ( $wpstream_vod_lock_to_website ) {
					$vod_embed_ancestor = esc_url_raw(
						apply_filters(
							'wpstream_vod_embed_ancestor',
							$this->wpstream_get_site_origin_for_embed(),
							$product_id
						)
					);
				}

				// if there's "wpstream_vod_embed_url" set, it means that we use the new player
				$wpstream_vod_embed_url       = get_post_meta( $product_id, 'wpstream_vod_embed_url', true );

				// New-player iframe base URL and the stored video identifier.
				$vod_iframe_base              = WPSTREAM_PLAYER . "/player/vod?";
				$vod_iframe_video_raw         = (string) get_post_meta( $product_id, 'wpstream_vod_video_data', true );
				$vod_iframe_video             = trim( $vod_iframe_video_raw );
				// Strip a surrounding pair of single/double quotes from the stored value.
				if ( strlen( $vod_iframe_video ) >= 2 ) {
					$first_char = $vod_iframe_video[0];
					$last_char  = substr( $vod_iframe_video, -1 );
					$is_wrapped_in_double_quotes = '"' === $first_char && '"' === $last_char;
					$is_wrapped_in_single_quotes = "'" === $first_char && "'" === $last_char;
					if ( $is_wrapped_in_double_quotes || $is_wrapped_in_single_quotes ) {
						$vod_iframe_video = substr( $vod_iframe_video, 1, -1 );
					}
				}
				// Mint the VOD embed key from the same inputs used in the iframe URL.
	            $vod_iframe_embed_key = $this->wpstream_generate_player_embed_key(
					$vod_iframe_video,
					$vod_validate_url,
					$vod_embed_ancestor,
					$wpstream_vod_encrypt ? 'yes' : ''
				);
				// Fall back to a stored embed key when the generated one is empty.
				if ( '' === $vod_iframe_embed_key ) {
					$vod_iframe_embed_key = (string) get_post_meta( $product_id, 'wpstream_vod_embed_key', true );
				}
				// Separate embed key for the trailer iframe.
				$vod_iframe_trailer_embed_key = $this->wpstream_generate_player_embed_key(
					$video_trailer,
					$vod_validate_url,
					$vod_embed_ancestor,
					$wpstream_vod_encrypt ? 'yes' : ''
				);

				// Poster image and active theme (used for the isThemeActive flag).
				$vod_poster_image = get_the_post_thumbnail_url( $product_id, 'full' );
				$current_active_theme = wp_get_theme();

				// Map the Video.js theme name to a bare skin slug for the iframe player.
				$vod_iframe_skin_slug = '';
				if ( preg_match( '/^vjs-theme-(city|fantasy|forest|sea)$/', $player_theme, $vod_iframe_skin_m ) ) {
					$vod_iframe_skin_slug = $vod_iframe_skin_m[1];
				}

				// Assemble the VOD iframe query args, dropping null/empty values.
				$vod_iframe_query_args = array_filter(
					array(
						'video'                      => $vod_iframe_video,
						'posterImage'                => $vod_poster_image,
						'embedKey'                   => $vod_iframe_embed_key,
						'startMuted'                 => $muted ? '1' : '',
						'skin'                       => $vod_iframe_skin_slug,
						'encrypt'                    => $wpstream_vod_encrypt ? 'yes' : '',
						'validatePlaybackSessionUrl' => $vod_validate_url,
						'embedAncestor'              => $vod_embed_ancestor,
						'logoImage'                  => $player_logo_image,
						'logoPosition'               => $player_logo_image ? $player_logo_position : '',
						'logoOpacity'                => $player_logo_image ? $player_logo_opacity : '',
						'isThemeActive'              => $current_active_theme->get('Name') === 'Hello WPStream',
					),
					static function ( $v ) {
						return $v !== null && $v !== '';
					}
				);

				// Assemble the trailer iframe query args, dropping null/empty values.
				$vod_trailer_iframe_query_args = array_filter(
					array(
						'video'                      => $video_trailer,
						'embedKey'                   => $vod_iframe_trailer_embed_key,
						'skin'                       => $vod_iframe_skin_slug,
						'startMuted'                 => $muted ? '1' : '',
						'encrypt'                    => $wpstream_vod_encrypt ? 'yes' : '',
						'validatePlaybackSessionUrl' => $vod_validate_url,
						'embedAncestor'              => $vod_embed_ancestor,
					),
					static function ( $v ) {
						return $v !== null && $v !== '';
					}
				);

				// New iframe player when an embed URL exists; otherwise the legacy Video.js element.
				if ( $wpstream_vod_embed_url ) {
					// Emit the content iframe pointing at /player/vod with the assembled query.
					echo '<div class="wpstream_player_iframe_wrap">';
					echo '<iframe id="playerFrame" 
							class="wpstream_video_on_demand_iframe" 
							title="' . esc_attr__( 'Embedded content', 'wpstream' ) . '" 
							src="' . esc_url( add_query_arg( $vod_iframe_query_args, $vod_iframe_base ) ) . '"
							data-wpstream-frame-role="content"
							allowfullscreen 
							allow="autoplay; fullscreen">
					</iframe>';
					// Add the hidden trailer iframe only when a trailer exists and the WpStream theme is active.
					if ( $trailer_attachment_id != 0 && get_template() == 'hello-wpstream' ) {
						echo '<iframe id="playerFrameTrailer" 
							class="wpstream_video_on_demand_iframe wpstream_video_on_demand_iframe_trailer" 
							title="' . esc_attr__( 'Embedded trailer content', 'wpstream' ) . '" 
							src="' . esc_url( add_query_arg( $vod_trailer_iframe_query_args, $vod_iframe_base ) ) . '"
							data-wpstream-frame-role="trailer"
							allowfullscreen
							allow="autoplay; fullscreen"
							style="display:none;"
							aria-hidden="true"
							tabindex="-1">
						</iframe>';
					}
					echo '</div>';
				} else {
					// Legacy Video.js <video> element carrying every bootstrap data-* attribute.
					echo '<video id="wpstream-video-vod-'.$now.'" class="'.esc_attr($has_trailer_class).' video-js vjs-default-skin  vjs-fluid kuk wpstream_video_on_demand vjs-wpstream ' . $player_theme .' ' . $player_logo_position_class . ' ' . $player_logo_horizontal_position . '"  data-me="'.esc_attr($usernamestream).'" data-product-id="'.$product_id.'" data-wpstream-bootstrap="vod" data-instance-id="wpstream-vod-'.esc_attr( $now ).'" data-video-element-id="wpstream-video-vod-'.esc_attr( $now ).'" data-title-overlay-element-id="'.esc_attr( $overlay_video_div_id ).'" data-video-url="'.esc_attr( $video_path_final ).'" data-trailer-url="'.esc_attr( $video_trailer ).'" data-autoplay="'. ( $autoplay ? '1' : '0' ) .'" data-muted="'. ( $muted ? '1' : '0' ) .'" data-captions-url="'.esc_attr( $captionsUrl ).'" data-play-trailer-button-element-id="'.esc_attr( $play_trailer_button_element_id ).'" data-mute-trailer-button-element-id="'.esc_attr( $mute_trailer_button_element_id ).'" data-unmute-trailer-button-element-id="'.esc_attr( $unmute_trailer_button_element_id ).'" data-play-video-button-element-id="'.esc_attr( $play_video_button_element_id ).'" data-player-logo-image="'.esc_attr( $player_logo_image ).'" data-player-logo-position="'.esc_attr( $player_logo_position ).'" data-player-logo-opacity="'.esc_attr( $player_logo_opacity ).'" data-player-logo-width="100" data-player-logo-height="auto" data-player-logo-padding="10" playsinline preload="auto"
                  '. $poster_data.' '.$wpstream_data_setup.'>
                        <p class="vjs-no-js">
                          To view this video please enable JavaScript, and consider upgrading to a web browser that
                          <a href="http://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
                        </p>
                    </video>';

					// When a trailer exists, render its play-trailer / play-video / mute / unmute controls (SVG icons).
					if($trailer_attachment_id !=0){
						print '<div class="wpstream_theme_trailer_wrapper">';
						print '<div id="'.esc_attr( $play_trailer_button_element_id ).'" class="wpstream_video_on_demand_play_trailer">
                    <svg width="30" height="24" viewBox="0 0 30 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M26.6667 1.5H3.33337C2.50495 1.5 1.83337 2.17157 1.83337 3V21C1.83337 21.8284 2.50495 22.5 3.33338 22.5H26.6667C27.4951 22.5 28.1667 21.8284 28.1667 21V3C28.1667 2.17157 27.4951 1.5 26.6667 1.5ZM3.33337 0C1.67652 0 0.333374 1.34315 0.333374 3V21C0.333374 22.6569 1.67652 24 3.33338 24H26.6667C28.3236 24 29.6667 22.6569 29.6667 21V3C29.6667 1.34315 28.3236 0 26.6667 0H3.33337ZM4.83337 4C4.55723 4 4.33337 4.22386 4.33337 4.5V6.16667C4.33337 6.44281 4.55723 6.66667 4.83337 6.66667H6.50004C6.77618 6.66667 7.00004 6.44281 7.00004 6.16667V4.5C7.00004 4.22386 6.77618 4 6.50004 4H4.83337ZM23.5 4C23.2239 4 23 4.22386 23 4.5V6.16667C23 6.44281 23.2239 6.66667 23.5 6.66667H25.1667C25.4428 6.66667 25.6667 6.44281 25.6667 6.16667V4.5C25.6667 4.22386 25.4428 4 25.1667 4H23.5ZM4.33337 11.167C4.33337 10.8909 4.55723 10.667 4.83337 10.667H6.50004C6.77618 10.667 7.00004 10.8909 7.00004 11.167V12.8337C7.00004 13.1098 6.77618 13.3337 6.50004 13.3337H4.83337C4.55723 13.3337 4.33337 13.1098 4.33337 12.8337V11.167ZM23.5001 10.667C23.224 10.667 23.0001 10.8909 23.0001 11.167V12.8337C23.0001 13.1098 23.224 13.3337 23.5001 13.3337H25.1668C25.4429 13.3337 25.6668 13.1098 25.6668 12.8337V11.167C25.6668 10.8909 25.4429 10.667 25.1668 10.667H23.5001ZM4.33337 17.833C4.33337 17.5569 4.55723 17.333 4.83337 17.333H6.50004C6.77618 17.333 7.00004 17.5569 7.00004 17.833V19.4997C7.00004 19.7758 6.77618 19.9997 6.50004 19.9997H4.83337C4.55723 19.9997 4.33337 19.7758 4.33337 19.4997V17.833ZM23.5001 17.333C23.224 17.333 23.0001 17.5569 23.0001 17.833V19.4997C23.0001 19.7758 23.224 19.9997 23.5001 19.9997H25.1668C25.4429 19.9997 25.6668 19.7758 25.6668 19.4997V17.833C25.6668 17.5569 25.4429 17.333 25.1668 17.333H23.5001ZM19.0677 13.0997L13.4077 16.5087C13.0434 16.7281 12.6092 16.7094 12.2661 16.5091C11.9218 16.3081 11.6666 15.9224 11.6666 15.4086V8.59072C11.6666 8.07698 11.9218 7.69125 12.2661 7.49026C12.6092 7.28999 13.0434 7.27126 13.4077 7.49064L19.0677 10.8996C19.8663 11.3805 19.8663 12.6188 19.0677 13.0997Z"/>
                    </svg>
                    '.esc_html__('Play Trailer','wpstream').'</div>';

						print '<div class="wpstream_video_on_demand_play_video_wrapper" id="'.esc_attr( $play_video_button_element_id ).'" >
                    <div class="wpstream_video_on_demand_play_video">
                        <svg width="29" height="30" viewBox="0 0 29 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M6.1808 28.9035L26.274 18.1652C29.1087 16.6503 29.1087 12.7497 26.274 11.2348L6.1808 0.496557C4.88769 -0.194506 3.34623 -0.1355 2.1283 0.495357C0.906043 1.12846 1.0095e-06 2.34351 9.38766e-07 3.96179L0 25.4382C-7.07369e-08 27.0565 0.906042 28.2715 2.1283 28.9046C3.34622 29.5355 4.88769 29.5945 6.1808 28.9035ZM24.8221 13.8026C25.5742 14.2045 25.5742 15.1955 24.8221 15.5974L4.72891 26.3356C3.94628 26.7539 3.01386 26.2165 3.01386 25.4382L3.01386 3.96179C3.01386 3.18347 3.94628 2.6461 4.72891 3.06436L24.8221 13.8026Z" fill="#F1F1F1"/>
                        </svg>
                    </div>
                    '.esc_html__('Play Video','wpstream').'
                    </div>';



						print '<div id="'.esc_attr( $mute_trailer_button_element_id ).'" class="wpstream_video_on_demand_mute_trailer">
                  
                    <svg width="37" height="36" viewBox="0 0 37 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.32143 10.0789H8.69499L18.8964 0L21.1428 0.921053V35.1316L18.8964 36L8.69499 25.8684H1.32143L0 24.5526V11.3947L1.32143 10.0789ZM10.175 23.6842L18.5 31.9474V4.10526L10.175 12.3158L9.24999 12.7105H2.64286V23.2368H9.24999L10.175 23.6842ZM37 17.9737C37.0069 22.2216 35.5329 26.3401 32.8295 29.6263L30.9478 27.7579C33.1613 24.9734 34.3629 21.5249 34.3571 17.9737C34.3571 14.2895 33.0885 10.8974 30.9637 8.21053L32.8454 6.34211C35.5382 9.62494 37.0062 13.735 37 17.9737ZM31.7143 17.9737C31.7193 20.8255 30.7895 23.6011 29.0661 25.8789L27.1738 23.9947C28.4127 22.2295 29.0752 20.1272 29.0714 17.9737C29.0751 15.8287 28.4174 13.7344 27.1871 11.9737L29.0793 10.0895C30.7338 12.2868 31.7143 15.0158 31.7143 17.9737ZM26.4286 17.9737C26.4286 19.4842 26.0057 20.8947 25.2657 22.0947L23.3126 20.1526C23.6249 19.4729 23.7876 18.7345 23.7899 17.9869C23.7922 17.2394 23.634 16.5001 23.3258 15.8184L25.2789 13.8737C26.0083 15.0684 26.4286 16.4737 26.4286 17.9737Z" fill="white"/>
                    </svg>
                    </div>';
						print '<div id="'.esc_attr( $unmute_trailer_button_element_id ).'" class="wpstream_video_on_demand_unmute_trailer">
                    <svg width="33" height="32" viewBox="0 0 33 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.15625 8.85688H7.60813L16.5344 0L18.5 0.809375V30.8719L16.5344 31.635L7.60813 22.7319H1.15625L0 21.5756V10.0131L1.15625 8.85688ZM8.90313 20.8125L16.1875 28.0738V3.6075L8.90313 10.8225L8.09375 11.1694H2.3125V20.4194H8.09375L8.90313 20.8125ZM30.5967 11.3127L32.2316 12.9477L28.2287 16.9506L32.2316 20.9559L30.5967 22.5908L26.5938 18.5856L22.5885 22.5908L20.9536 20.9559L24.9588 16.9506L20.9513 12.95L22.5862 11.3151L26.5938 15.3157L30.5967 11.3127Z" fill="white"/>
                    </svg>

                    </div>';
						print '</div>';
					}
				}
            }else{
                // Quota exhausted for an encrypted VOD: show an "insufficient resources" notice.
                print '<div class="wpstream_insuficent_res">'.esc_html__('Insufficient resources to stream this title','wpstream').'</div>';
            }

        }


        
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
        public function wpstream_video_on_demand_player_only_trailer($product_id){
            // Player dependencies.
            wp_enqueue_script('video.min');
            wp_enqueue_script('wpstream-player');

	        // Skin/theme, unique id, and the optional title overlay.
	        $player_theme = $this->wpstream_get_player_theme();
            $now                =   time().rand(0,1000000);
            $overlay_video_div_id = "random_id_".$now;
            $this->wpstream_render_vod_title_overlay(
                $overlay_video_div_id,
                get_the_title($product_id)
            );



            // Poster thumbnail and streaming username.
            $thumb_id               =   get_post_thumbnail_id($product_id);
            $thumb                  =   wp_get_attachment_image_src($thumb_id,'small');
            $usernamestream         =   esc_html ( get_option('wpstream_api_username','') );

            $poster_thumb           =   '';
            if(isset($thumb[0])){
                $poster_thumb=$thumb[0];
            }


            // Trailer attachment URL and mime type.
            $trailer_attachment_id    =  intval (get_post_meta( $product_id, 'video_trailer', true ));
            $video_trailer            = '';
            $video_trailer_type       = '';
            if($trailer_attachment_id!=0) {
                $video_trailer                 =   wp_get_attachment_url( $trailer_attachment_id );
                $attachment_metadata           =   wp_get_attachment_metadata($trailer_attachment_id);
                if(isset($attachment_metadata['mime_type'])){
                    $video_trailer_type            =   $attachment_metadata['mime_type'];
                }

            }


            // Autoplay/mute from the VOD options.
            $autoplay = false;
            $muted = false;

			if( intval ( get_option('wpstream_vod_start_muted','') ) === 1){
				$muted = true;
			}
			if( intval  ( get_option('wpstream_vod_autoplay','') ) === 1 ){
				$autoplay = true;
			}
                 // No main video source (trailer only); captions and control ids.
                 $video_path_final='';
                 $has_trailer_class='wpstream_theme_player_has_trailer';
                 $captions_url = get_post_meta( $product_id, 'wpstream_closed_captions_file', true );
                 $play_trailer_button_element_id = $trailer_attachment_id != 0 ? 'wpstream_video_on_demand_play_trailer_btn_' . $now : '';
                 $mute_trailer_button_element_id = $trailer_attachment_id != 0 ? 'wpstream_video_on_demand_mute_trailer_btn_' . $now : '';
                 $unmute_trailer_button_element_id = $trailer_attachment_id != 0 ? 'wpstream_video_on_demand_unmute_trailer_btn_' . $now : '';

                 // Video.js element with an empty data-video-url (trailer supplied via data-trailer-url).
                 echo '<video id="wpstream-video-vod-'.$now.'" class="video-js vjs-default-skin  vjs-fluid kuk wpstream_video_on_demand vjs-wpstream '.esc_attr(  $has_trailer_class ).' ' . $player_theme . '"  data-me="'.esc_attr($usernamestream).'" data-product-id="'.$product_id.'" data-wpstream-bootstrap="vod" data-instance-id="wpstream-vod-trailer-'.esc_attr( $now ).'" data-video-element-id="wpstream-video-vod-'.esc_attr( $now ).'" data-title-overlay-element-id="'.esc_attr( $overlay_video_div_id ).'" data-video-url="" data-trailer-url="'.esc_attr( $video_trailer ).'" data-autoplay="'. ( $autoplay ? '1' : '0' ) .'" data-muted="'. ( $muted ? '1' : '0' ) .'" data-captions-url="'.esc_attr( $captions_url ).'" data-play-trailer-button-element-id="'.esc_attr( $play_trailer_button_element_id ).'" data-mute-trailer-button-element-id="'.esc_attr( $mute_trailer_button_element_id ).'" data-unmute-trailer-button-element-id="'.esc_attr( $unmute_trailer_button_element_id ).'"  playsinline preload="auto"
                   >
                    <p class="vjs-no-js">
                      To view this video please enable JavaScript, and consider upgrading to a web browser that
                      <a href="http://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
                    </p>
                </video>';
                            
            // Render the trailer play/mute/unmute controls (SVG icons) when a trailer exists.
            if($trailer_attachment_id !=0){
                print '<div class="wpstream_theme_trailer_wrapper">';
                print '<div id="'.esc_attr( $play_trailer_button_element_id ).'" class="wpstream_video_on_demand_play_trailer">
                     <svg width="30" height="24" viewBox="0 0 30 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M26.6667 1.5H3.33337C2.50495 1.5 1.83337 2.17157 1.83337 3V21C1.83337 21.8284 2.50495 22.5 3.33338 22.5H26.6667C27.4951 22.5 28.1667 21.8284 28.1667 21V3C28.1667 2.17157 27.4951 1.5 26.6667 1.5ZM3.33337 0C1.67652 0 0.333374 1.34315 0.333374 3V21C0.333374 22.6569 1.67652 24 3.33338 24H26.6667C28.3236 24 29.6667 22.6569 29.6667 21V3C29.6667 1.34315 28.3236 0 26.6667 0H3.33337ZM4.83337 4C4.55723 4 4.33337 4.22386 4.33337 4.5V6.16667C4.33337 6.44281 4.55723 6.66667 4.83337 6.66667H6.50004C6.77618 6.66667 7.00004 6.44281 7.00004 6.16667V4.5C7.00004 4.22386 6.77618 4 6.50004 4H4.83337ZM23.5 4C23.2239 4 23 4.22386 23 4.5V6.16667C23 6.44281 23.2239 6.66667 23.5 6.66667H25.1667C25.4428 6.66667 25.6667 6.44281 25.6667 6.16667V4.5C25.6667 4.22386 25.4428 4 25.1667 4H23.5ZM4.33337 11.167C4.33337 10.8909 4.55723 10.667 4.83337 10.667H6.50004C6.77618 10.667 7.00004 10.8909 7.00004 11.167V12.8337C7.00004 13.1098 6.77618 13.3337 6.50004 13.3337H4.83337C4.55723 13.3337 4.33337 13.1098 4.33337 12.8337V11.167ZM23.5001 10.667C23.224 10.667 23.0001 10.8909 23.0001 11.167V12.8337C23.0001 13.1098 23.224 13.3337 23.5001 13.3337H25.1668C25.4429 13.3337 25.6668 13.1098 25.6668 12.8337V11.167C25.6668 10.8909 25.4429 10.667 25.1668 10.667H23.5001ZM4.33337 17.833C4.33337 17.5569 4.55723 17.333 4.83337 17.333H6.50004C6.77618 17.333 7.00004 17.5569 7.00004 17.833V19.4997C7.00004 19.7758 6.77618 19.9997 6.50004 19.9997H4.83337C4.55723 19.9997 4.33337 19.7758 4.33337 19.4997V17.833ZM23.5001 17.333C23.224 17.333 23.0001 17.5569 23.0001 17.833V19.4997C23.0001 19.7758 23.224 19.9997 23.5001 19.9997H25.1668C25.4429 19.9997 25.6668 19.7758 25.6668 19.4997V17.833C25.6668 17.5569 25.4429 17.333 25.1668 17.333H23.5001ZM19.0677 13.0997L13.4077 16.5087C13.0434 16.7281 12.6092 16.7094 12.2661 16.5091C11.9218 16.3081 11.6666 15.9224 11.6666 15.4086V8.59072C11.6666 8.07698 11.9218 7.69125 12.2661 7.49026C12.6092 7.28999 13.0434 7.27126 13.4077 7.49064L19.0677 10.8996C19.8663 11.3805 19.8663 12.6188 19.0677 13.0997Z"/>
                            </svg>
                            '.esc_html__('Play Trailer','wpstream').'
                </div>';
                print '<div id="'.esc_attr( $mute_trailer_button_element_id ).'" class="wpstream_video_on_demand_mute_trailer">
                <svg width="37" height="36" viewBox="0 0 37 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M1.32143 10.0789H8.69499L18.8964 0L21.1428 0.921053V35.1316L18.8964 36L8.69499 25.8684H1.32143L0 24.5526V11.3947L1.32143 10.0789ZM10.175 23.6842L18.5 31.9474V4.10526L10.175 12.3158L9.24999 12.7105H2.64286V23.2368H9.24999L10.175 23.6842ZM37 17.9737C37.0069 22.2216 35.5329 26.3401 32.8295 29.6263L30.9478 27.7579C33.1613 24.9734 34.3629 21.5249 34.3571 17.9737C34.3571 14.2895 33.0885 10.8974 30.9637 8.21053L32.8454 6.34211C35.5382 9.62494 37.0062 13.735 37 17.9737ZM31.7143 17.9737C31.7193 20.8255 30.7895 23.6011 29.0661 25.8789L27.1738 23.9947C28.4127 22.2295 29.0752 20.1272 29.0714 17.9737C29.0751 15.8287 28.4174 13.7344 27.1871 11.9737L29.0793 10.0895C30.7338 12.2868 31.7143 15.0158 31.7143 17.9737ZM26.4286 17.9737C26.4286 19.4842 26.0057 20.8947 25.2657 22.0947L23.3126 20.1526C23.6249 19.4729 23.7876 18.7345 23.7899 17.9869C23.7922 17.2394 23.634 16.5001 23.3258 15.8184L25.2789 13.8737C26.0083 15.0684 26.4286 16.4737 26.4286 17.9737Z" fill="white"/>
                </svg>

          
                </div>';
                print '<div id="'.esc_attr( $unmute_trailer_button_element_id ).'" class="wpstream_video_on_demand_unmute_trailer">
                <svg width="33" height="32" viewBox="0 0 33 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M1.15625 8.85688H7.60813L16.5344 0L18.5 0.809375V30.8719L16.5344 31.635L7.60813 22.7319H1.15625L0 21.5756V10.0131L1.15625 8.85688ZM8.90313 20.8125L16.1875 28.0738V3.6075L8.90313 10.8225L8.09375 11.1694H2.3125V20.4194H8.09375L8.90313 20.8125ZM30.5967 11.3127L32.2316 12.9477L28.2287 16.9506L32.2316 20.9559L30.5967 22.5908L26.5938 18.5856L22.5885 22.5908L20.9536 20.9559L24.9588 16.9506L20.9513 12.95L22.5862 11.3151L26.5938 15.3157L30.5967 11.3127Z" fill="white"/>
                </svg>

                </div>';
                print '</div>';
            }
            else {
                //just show the poster or don't show anything; no player needed
            }

        }

        
        
        /**
        * Return the API username (from the saved token) used in VOD paths.
        *
        * edited 4.0
        *
        * @return mixed Stored username option value.
        * @author cretu
        */
        private function wpstream_retrive_username(){

            return  get_option('wpstream_api_username_from_token');
        }

            /**
             * Render title overlay via action hook.
             *
             * @param string $overlay_id Overlay element id.
             * @param string $title_text Title text.
             * @return void
             */
            private function wpstream_render_vod_title_overlay( $overlay_id, $title_text ) {
                // Only fire when a theme/plugin has registered the overlay renderer.
                if ( has_action( 'wpstream_vod_title_overlay' ) ) {
                    do_action(
                        'wpstream_vod_title_overlay',
                        $overlay_id,
                        $title_text,
                        esc_html__( 'Playing:', 'wpstream' )
                    );
                }
            }
        
    /**
     * Decide whether the current user may watch the given product.
     *
     * Branches on subscription (Netflix) mode vs pay-per-view: in sub mode it
     * checks the global subscription entitlement; in PPV it checks an active
     * subscription or a completed purchase.
     *
     * @param int $product_id Product id.
     * @return bool True when the user is entitled to watch.
     * @since     3.12
    * returns html of the player
    */
    public function wpstream_check_if_player_can_dsplay($product_id){
        if ( is_user_logged_in() ) {
            // Gather the product term, current user, sub-mode flag, and product type.
            $term_list              =       wp_get_post_terms($product_id, 'product_type');
            $current_user           =       wp_get_current_user();
            $subscription_model     =       intval( get_option('wpstream_global_sub','')) ;

            $product = wc_get_product($product_id);
            $product_type = $product->get_type();

        if($subscription_model==1){ // if we have Neflix mode
                if( $product_type=='subscription' ){ // if the product loaded is a subscription and we are on netflix mode
                    // In Netflix mode a bare subscription product has no player of its own.
                    return false;
                }
                // Entitled if the global subscription model grants access.
                if($this->wpstream_in_plugin_check_global_subscription_model($product_id)){
                    return true;
                }
            }else{
                // ppv mode

                if( $product_type=='subscription' ){

//                        $user_subscriptions = wcs_get_users_subscriptions($current_user->ID );
//                        foreach ($user_subscriptions as $subscription) {
//                                $subscription_status = $subscription->get_status();
//                                echo "</br>*** Subscription ID $subscription->ID for User ID $current_user->ID has status: $subscription_status";
//                        }

        
                    if( wcs_user_has_subscription( $current_user->ID, $product_id ,'active') ) {
                        // user has active subcription
                        return true;
                    }

                }else{
                    // Non-subscription product: entitled if the user bought it.
                    if( wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product_id)){
                        return true;
                    }
                }


            }
            // Logged in but no entitlement matched.
            return false;

        }
        // Not logged in: never entitled here.
        return false;

    }
    
        

    /**
    * Theme variant of the entitlement check.
    *
    * Free WpStream live/VOD post types are always allowed; WooCommerce products
    * are gated by subscription (Netflix) mode or a purchase / active subscription /
    * bought bundle in PPV mode.
    *
    * @param int $product_id Product/post id.
    * @return bool True when the player may be displayed.
    * @since     3.12
    * returns html of the player
    */
        public function wpstream_check_if_player_can_dsplay_theme($product_id){
            // Post type drives the free vs paid decision below.
            $post_type= get_post_type($product_id);

            // Free WpStream live/VOD post types are always watchable.
            if($post_type=='wpstream_product_vod' || $post_type=='wpstream_product'){
                return true; // if we have free vod or live
            }



            if ( is_user_logged_in() && $post_type==='product' ) {
                // WooCommerce product: gather type, possible bundle, user, and sub-mode flag.
                $product            = wc_get_product( $product_id );
                $product_type       = $product->get_type();
                $possible_bundle    = get_post_meta($product_id, 'wpstream_part_of_bundle',true);




                $current_user           =       wp_get_current_user();
                $subscription_model     =       intval( get_option('wpstream_global_sub','')) ;
              //  print 'subscription model '.$subscription_model.'</br>';

                if($subscription_model==1){ // if we have Neflix mode
                    if( $product_type=='subscription' ){ // if the product loaded is a subscription and we are on netflix mode
                        // A bare subscription product has no player in Netflix mode.
                        return false;
                    }
                    // Entitled if the global subscription model grants access.
                    if($this->wpstream_in_plugin_check_global_subscription_model($product_id)){
                        return true;
                    }
                }else{
                    // ppv mode
                    if ( wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product_id) ){
                        return true; //simple product bought
                    }else if (function_exists('wcs_user_has_subscription')  && wcs_user_has_subscription( $current_user->ID, $product_id ,'active') ){
                        return true; // subscription boght
                    }else if( get_post_type($product_id) =='product' &&
                            intval($possible_bundle )!=0 &&
                            wc_customer_bought_product( $current_user->user_email, $current_user->ID, $possible_bundle)
                            ){
                        return true; // part of a boght bundle
                    }
                }
               // print ' fac return false </br>';
                // Logged-in WooCommerce product with no matching entitlement.
                return false;

            }
            // Not logged in, or a post type with no theme player.
            return false;

        }
        
        
        /**
     * Check the global-subscription entitlement for a product (Netflix mode).
     *
     * A product may point at one or more parent subscriptions (per-product), or
     * the site may define a single global subscription; the user is entitled if
     * they hold an active subscription to any of them.
     *
     * @param int $product_id Product id.
     * @return bool True when the user has a qualifying active subscription.
     * @since     3.12
    */
        public   function wpstream_in_plugin_check_global_subscription_model($product_id) {
            //  $selected_sub= get_post_meta($post->ID,'_wpstream_parent_sub',true);
            // Requires a logged-in user and WooCommerce Subscriptions.
            if( is_user_logged_in()  && function_exists('wcs_user_has_subscription') ){

                global $woocommerce;
                $current_user   =       wp_get_current_user();

                // Sub-mode flag and the site-wide "main" subscription id.
                $subscription_model =   intval( get_option('wpstream_global_sub','')) ;
                $main_subscription  =   intval(  get_option('wpstream_global_sub_id',''));

                if($subscription_model==1){
                    // Subscription id(s) this product is tied to.
                    $selected_sub=  get_post_meta($product_id,'_wpstream_parent_sub',true)  ;

                    if( is_array($selected_sub) ){
                        // we have per product sub
                        // Entitled if the user actively subscribes to any linked subscription.
                        foreach($selected_sub as $key=>$subscrition_id ):
                            if( wcs_user_has_subscription( $current_user->ID, $subscrition_id ,'active') ) {
                                return true;
                            }
                        endforeach;

                    } else if($main_subscription!=0){

                        // if we have one main subscription
                        // Entitled if the user actively subscribes to the single global subscription.
                        if( wcs_user_has_subscription( $current_user->ID, $main_subscription ,'active') ) {
                            return true;
                        }
                    }


                }
                // Logged in but no qualifying subscription.
                return false;

            }
            // there is no woo subscription or user not logged in
            return false;
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
            // Allow the theme to opt out of the plugin's automatic player insertion.
            if(function_exists('wpstream_remove_wpstream_filter')){
                return;
            }
            // Work against the current WooCommerce product on the page.
            global $product;
            $product_id   = $product->get_id();
            $current_user = wp_get_current_user();
            $term_list    = wp_get_post_terms($product_id, 'product_type');

			// A "simple subscription" (neither live nor VOD) has no player here.
			$is_simple_subscription = get_post_meta($product_id, '_subscript_live_event');
			if ( $term_list[0]->name == 'subscription' && ! empty( $is_simple_subscription ) && $is_simple_subscription[0] == 'none' ) {
				return;
			}

            if ( is_user_logged_in() ) {
                if($this->wpstream_check_if_player_can_dsplay($product_id) ){
                    // Entitled: open the player wrapper and render live or VOD by term/flag.
                    echo '<div class="wpstream_player_wrapper "><div class="wpstream_player_container">';

                    $is_subscription_live_event =   esc_html(get_post_meta($product_id,'_subscript_live_event',true));



                    if( $term_list[0]->name=='live_stream' || ($term_list[0]->name=='subscription' && $is_subscription_live_event=='yes' )  ){
                        // Live stream (or live-flagged subscription).
                        $this->wpstream_live_event_player($product_id);
                    }else if( $term_list[0]->name=='video_on_demand'  || ($term_list[0]->name=='subscription' && $is_subscription_live_event=='no' ) ){
                        // On-demand video (or VOD-flagged subscription).
                        $this->wpstream_video_on_demand_player($product_id);
                    }
                    echo '</div></div>';
                } else {
                    // Not entitled: show the appropriate "no buy" message.
                    if( $term_list[0]->name=='subscription'  ){
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
        * Include the chat template markup for a product.
        *
        * @param int $product_id Product/post id (in scope for the template).
        * @return void
        * @since     3.0.1
        * returns html of the player
    */
        public function wpstream_chat_wrapper($product_id){
           // Load the chat widget template; $product_id is available to it.
           require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/templates/wpstream_chat_template.php';
        }

        
        
        
        
        
        
        
          /**
     * Enqueue chat assets and bootstrap the chat client for a product.
     *
     * Loads the SockJS/emoji/chat scripts and styles, then prints an inline
     * script that seeds the current username and (empty) key for chat.js.
     *
     * @param int $product_id Product/post id holding the chat_url meta.
     * @return void Echoes the chat bootstrap script.
     * @since     1.12.2
    */
        public  function wpstream_connect_to_chat($product_id){
        // Current user identity used to label chat messages.
        $current_user           =   wp_get_current_user();
        $userID                 =   $current_user->ID;
        $user_login             =   $current_user->user_login;

        $key='';

        // Chat server endpoint stored on the product.
        $chat_url                =   get_post_meta($product_id,'chat_url',true);

        // Chat client dependencies (SockJS transport, emoji, linkify, MDL, chat.js).
        wp_enqueue_script( 'sockjs-0.3.min' );
        wp_enqueue_script( 'emojione.min.js' );
        wp_enqueue_script( "jquery-effects-core");
        wp_enqueue_script( 'jquery.linkify.min.js');
        wp_enqueue_script( 'ripples.min.js');
        wp_enqueue_script( 'material.min.js');
        wp_enqueue_script( 'chat.js');



        // Chat styles.
        wp_enqueue_style( 'chat.css');
        wp_enqueue_style( 'ripples.css');
        wp_enqueue_style( 'emojione.min.css');


        // Logged-out visitors chat anonymously with no chat URL.
       if(!is_user_logged_in()){
           $user_login='';
           $chat_url='';
       }

       // Seed the chat client globals (username/key) on document ready.
       print '<script type="text/javascript">
            //<![CDATA[
                jQuery(document).ready(function(){
                    username = "'.$user_login.'";
                    key="'.$key.'";
                   
                });
            //]]>
        </script>';

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
                $message= esc_html( get_option('wpstream_subscription_active','Your Subscription is Active.')) ;
                echo '<div class="wpstream_player_wrapper no_buy"><div class="wpstream_player_container">';
                echo '<div class="wpstream_notice"> '.$message.'</div>';
                echo '</div></div>';
                return;

            }else if($log=='nolog'){
               // Visitor is logged out.
               $message= esc_html( get_option('wpstream_product_not_login','You must be logged in to watch this video.')) ;
            }else{
                // Default: logged in but has not purchased.
                $message =esc_html( get_option('wpstream_product_not_bought','You did not yet purchase this item.')) ;
            }
            // In subscription mode, swap in the "not subscribed" wording.
            $subscription_model     =       intval( get_option('wpstream_global_sub','')) ;
            if($subscription_model==1){
                $message =esc_html( get_option('wpstream_product_not_subscribe','You did not yet subscribe to this item.'));
            }


            if( get_post_type($product_id) == 'product' && $subscription_model==0 ){
                // PPV mode: only show the notice for live/VOD/subscription product types.
                $product                    =   wc_get_product($product_id);
                $term_list                  =   wp_get_post_terms($product_id, 'product_type');
                $product_type               =   $product->get_type();
                $is_subscription_live_event =   esc_html(get_post_meta($product_id,'_subscript_live_event',true));



                if( $term_list[0]->name=='video_on_demand' ||  $term_list[0]->name=='live_stream' || $product_type=='subscription'){

                    echo '<div class="wpstream_player_wrapper no_buy"><div class="wpstream_player_container">';
                    echo '<div class="wpstream_notice">'.$message.'</div>';
                    echo '</div></div>';

                }

            }else  if( get_post_type($product_id) == 'product' && $subscription_model==1 ){
                // Subscription mode: show the notice for any non-simple product.
                $term_list                  =   wp_get_post_terms($product_id, 'product_type');
                if( $term_list[0]->name!=='simple'){
                    echo '<div class="wpstream_player_wrapper no_buy"><div class="wpstream_player_container">';
                    echo '<div class="wpstream_notice">  '.$message.'</div>';
                    echo '</div></div>';
                }
            }
        }

	/**
	 * Get the video player logo URL.
	 *
	 * @param int $product_id Product ID.
	 * @return mixed|string Logo URL, the WpStream symbol for streamify users, or ''.
	 */
	public function wpstream_get_video_player_logo( $product_id ) {
		// Streamify (basic) users always get the bundled WpStream watermark.
		$is_streamify_user = $this->wpstream_is_streamify_user( $product_id );
		if ( $is_streamify_user ) {
			return WPSTREAM_PLUGIN_DIR_URL . 'img/wpstream-symbol-large.png';
		}

		// Otherwise use the site-configured logo if one is set.
		$logo = get_option( 'wpstream_player_logo', '' );
		if ( ! empty( $logo ) ) {
			return $logo;
		}

		// No logo configured.
		return '';
	}

	/*
	 * Check whether a channel/product is on a basic (streamify) subscription.
	 *
	 * @param int $channel_id Channel ID
	 * @return bool True when the 'basicStreaming' meta equals '1'.
	 */
	public function wpstream_is_streamify_user( $channel_id ) {
		// The 'basicStreaming' meta flag marks streamify (basic) channels.
		$is_basic_streaming = get_post_meta( $channel_id, 'basicStreaming', true );
		if ( $is_basic_streaming === '1' ) {
			return true;
		}
		return false;
	}
}
