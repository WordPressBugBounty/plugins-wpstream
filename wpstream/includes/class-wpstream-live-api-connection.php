<?php
/**
 * HTTP client for the WPStream cloud backend (baker.wpstream.net / rest-baker.wpstream.net).
 *
 * This class is the single funnel through which the plugin talks to the remote
 * streaming API. It:
 *  - authenticates against the API (`access_token` grant) and caches the bearer
 *    token in a transient so every call does not re-login (see wpstream_get_token()).
 *  - funnels every backend call through a single WP-HTTP transport (request())
 *    that centralises timeout, error handling, HTTP-status handling, JSON decoding
 *    and failure logging, returning a decoded array or WP_Error (never output).
 *  - owns authentication end to end: authorized_request() fetches the token and
 *    injects it into the body itself, and is_connected() answers "is this site
 *    linked to a WpStream account?" — production code outside this class never
 *    handles the token.
 *  - drives the channel lifecycle: start (channel/start), stop (channel/stop),
 *    update settings (channel/update), poll status (channel/info) and list
 *    active channels (channel/list).
 *  - manages recorded video assets stored in the cloud (video/list, video/upload,
 *    video/download, video/delete).
 *  - exposes a large surface of `wp_ajax_*` handlers that the admin JS calls to
 *    turn channels on/off, save per-event and global settings, check DNS/quota/whip,
 *    and enumerate pending recordings.
 *  - composes Wpstream_User_Quota_Service (consumed directly by the quota
 *    manager) and Wpstream_Channel_Service in the constructor.
 *
 * NOTE: several AJAX handlers below intentionally left as-is have weak or missing
 * nonce/ownership checks; this is a comments-only pass, so nothing here is changed.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remote streaming-API client plus the admin AJAX surface that drives it.
 */
class Wpstream_Live_Api_Connection  {

    /** @var Wpstream_User_Quota_Service Handles pack/quota lookups against the API. */
    private $user_quota_service;

    /** @var Wpstream_Channel_Service Handles remote channel creation. */
    private $channel_service;

	/** @var Wpstream_Channel_Settings Deep owner used by compatibility handlers. */
	private $channel_settings;

	/** @var Wpstream_Streaming_Content_Creation|null Local-first creation lifecycle owner. */
	private $streaming_content_creation;


    /**
     * Compose the collaborator services and register every admin AJAX endpoint
     * (plus the admin-notice hook) this class serves.
     *
     * All handlers are `wp_ajax_*` only (logged-in admin surface); none are
     * registered for `wp_ajax_nopriv_*`.
     */
    public function __construct() {
        // Collaborators receive $this so they can reuse the token + cURL helpers.
		$this->user_quota_service = new Wpstream_User_Quota_Service( $this );
		$this->channel_service    = new Wpstream_Channel_Service( $this );
		if ( class_exists( 'Wpstream_Channel_Settings' ) ) {
			$this->channel_settings = new Wpstream_Channel_Settings(
				new Wpstream_WordPress_Channel_Settings_Storage()
			);
		}

        // Channel on/off: request a live URI (start) and stop a running channel.
        add_action( 'wp_ajax_wpstream_give_me_live_uri', array($this,'wpstream_give_me_live_uri') );
        add_action( 'wp_ajax_wpstream_turn_of_channel',  array($this,'wpstream_turn_of_channel') );
        // Per-event and global streaming settings persistence.
        add_action( 'wp_ajax_wpstream_update_local_event_settings',array($this,'wpstream_update_local_event_settings'));
        add_action( 'wp_ajax_wpstream_update_use_global_event_options',array($this,'wpstream_update_use_global_event_options'));
		add_action( 'wp_ajax_wpstream_update_default_channel_settings', array( $this, 'wpstream_update_default_channel_settings' ) );
		add_action( 'wp_ajax_wpstream_update_settings', array( $this, 'wpstream_update_settings' ) );

        // Status/connectivity polling endpoints used while a broadcast is starting.
        add_action( 'wp_ajax_wpstream_check_event_status', array($this,'wpstream_check_event_status') );
		add_action( 'wp_ajax_wpstream_check_whipurl', array($this, 'wpstream_check_whipurl') );
		add_action( 'wp_ajax_wpstream_check_user_quota', array($this, 'wpstream_check_user_quota') );

        // Recorded-file management (download link, delete).
        add_action( 'wp_ajax_wpstream_get_download_link', array($this,'wpstream_get_download_link') );
        add_action( 'wp_ajax_wpstream_get_delete_file', array($this,'wpstream_get_delete_file') );

        // Connection/credential warnings printed at the top of WPStream admin pages.
        add_action( 'admin_notices',array($this, 'wpstream_admin_notices') );

        // Poll for recordings that are still being processed in the cloud.
		add_action( 'wp_ajax_wpstream_check_pending_videos', array($this,'wpstream_check_pending_videos') );


    }

	/** Inject the Channel Settings Module after the connection Adapter is composed. */
	public function set_channel_settings( Wpstream_Channel_Settings $channel_settings ) {
		$this->channel_settings = $channel_settings;
	}

	/** Inject the Streaming Content Creation Module after all Adapters are composed. */
	public function set_streaming_content_creation( Wpstream_Streaming_Content_Creation $streaming_content_creation ) {
		$this->streaming_content_creation = $streaming_content_creation;
	}


    /*
     * Admin Notices
     *
     *
     *
     * */
    /**
     * Print connectivity warnings at the top of WPStream admin pages.
     *
     * Warns when the PHP cURL extension is missing, and when no auth token can
     * be obtained (i.e. the site is not connected to WpStream.net). Hooked on
     * `admin_notices`.
     *
     * @return void Echoes notice markup; returns early on non-WPStream screens.
     */
    function wpstream_admin_notices(){
        // Current admin page slug, used to limit where these notices appear.
        global $pagenow;




        // Only run on admin.php-hosted pages (the plugin's menu screens).
        if($pagenow!='admin.php'){
            return;
        }

        // Whitelist of WPStream admin screens allowed to show the notice.
        $permited_pages=array('wpstream_plugin_options','wpstream_live_channels','wpstream_recordings','wpstream_settings');
        // '' when no ?page= is present, so the variable is always defined below.
        $page = !empty($_GET['page']) ? esc_html($_GET['page']) : '';
        if ($page !== '') {
            // Bail if the requested page is not one of ours.
            if( !in_array($page, $permited_pages)){
               return;
            }
        }

        // Verify the cURL extension is loaded; without it no API call can succeed.
        if(in_array('curl', get_loaded_extensions())){
            //cURL module has been loaded
        } else{
            print '<div class="api_not_conected wpstream_notice_top">We could not connect to WpStream.net. Make sure you have the php Curl library enabled and your hosting allows  outgoing HTTP Connection. </div>';
        }

        // Connectivity probe: no obtainable token means not connected.
        if( !$this->is_connected() and $page!='wpstream_plugin_options'){
            // echo 'wpstream_curl_failed: ' . get_option('wpstream_curl_failed');
            // wpstream_curl_failed === "0" => credentials wrong (no cURL error);
            // otherwise a transport/HTTP error occurred and was surfaced above.
            // Translate the literals at definition — gettext cannot extract variable strings.
            $text = get_option('wpstream_curl_failed') === "0" ?
                __( 'Not connected to WpStream. Please check your credentials <a href="/wp-admin/admin.php?page=wpstream_credentials">here</a>.', 'wpstream' ) :
                __( 'Not connected to WpStream. Please note the errors above and contact support.', 'wpstream' );

            // Render the appropriate "not connected" message (contains a link, so kses instead of esc_html).
            echo '<div class="api_not_conected wpstream_notice_top">'.wp_kses_post($text).'</div>';
        }

	}
    
    
    /*
     * Curl request
     *
     *
     *
     * */

    /**
     * POST to the WPStream backend API over the WP HTTP layer.
     *
     * The single transport every backend call funnels through. Never produces
     * output; user-facing error rendering belongs to callers/admin notices.
     *
     * @param string $endpoint API path appended to WPSTREAM_API (e.g. 'channel/start').
     * @param array  $body     POST body fields.
     * @param int    $timeout  Request timeout in seconds.
     * @return array|WP_Error Decoded response array, or WP_Error on transport/HTTP/JSON failure.
     */
    public function request( $endpoint, $body, $timeout = 10 ) {
        // Test host wins over production when defined (same rule as before).
        $base_api_url = defined( 'WPSTREAM_TEST_API' ) ? WPSTREAM_TEST_API : WPSTREAM_API;

        $args = array(
            'timeout' => $timeout,
            'body'    => $body,
        );

        /**
         * Filters the WP HTTP args of an outgoing WpStream API request.
         *
         * Lets trusted code adjust `timeout`, `headers` or `body` before the
         * call. The destination URL/host is not modifiable here; `$endpoint`
         * is read-only context. `$args['body']` may carry credentials — the
         * account access token on most endpoints, and the WpStream username
         * and plaintext password on the `access_token` endpoint — so this
         * filter is for trusted site code only: never log, forward or expose
         * `$args` wholesale.
         *
         * @since 4.14.0
         *
         * @param array  $args     Args passed to wp_remote_post()/wp_remote_get().
         * @param string $endpoint API path (request()) or full URL (get()).
         */
        $args = apply_filters( 'wpstream_api_request_args', $args, $endpoint );

        $response = wp_remote_post( $base_api_url . '/' . $endpoint, $args );

        return $this->handle_response( $response, $endpoint );
    }

    /**
     * GET a full URL over the WP HTTP layer with the same response contract
     * as request(). Used for the legacy REST host and other WpStream GET
     * endpoints that do not live under the WPSTREAM_API base path.
     *
     * @param string $url     Full URL to fetch.
     * @param int    $timeout Request timeout in seconds.
     * @return array|WP_Error Decoded response array, or WP_Error on transport/HTTP/JSON failure.
     */
    public function get( $url, $timeout = 10 ) {
        /** This filter is documented in request() above. */
        $args = apply_filters( 'wpstream_api_request_args', array( 'timeout' => $timeout ), $url );

        $response = wp_remote_get( $url, $args );

        return $this->handle_response( $response, $url );
    }

    /**
     * Shared response handling for request()/get(): validate, decode, record.
     *
     * @param array|WP_Error $response Raw wp_remote_* return value.
     * @param string         $endpoint Endpoint/URL used for logging context.
     * @return array|WP_Error Decoded response array, or WP_Error on failure.
     */
    private function handle_response( $response, $endpoint ) {
        // Transport-level failure (DNS, refused connection...): hand the
        // WP_Error straight to the caller.
        if ( is_wp_error( $response ) ) {
            return $this->record_failure( $response->get_error_message(), $endpoint, $response );
        }

        // Non-200 status: error out with the HTTP code as error data so
        // callers can react to specific statuses without string parsing.
        $http_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $http_code ) {
            return $this->record_failure( $http_code, $endpoint, new WP_Error(
                'wpstream_http_error',
                'API returned HTTP ' . $http_code . ' on endpoint ' . $endpoint,
                $http_code
            ) );
        }

        // Decode the JSON body into an associative array for callers; a body
        // that does not parse (proxy/maintenance HTML, truncation) is an error.
        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return $this->record_failure( json_last_error(), $endpoint, new WP_Error(
                'wpstream_bad_json',
                'Malformed API response: ' . json_last_error_msg() . ' on endpoint ' . $endpoint,
                json_last_error()
            ) );
        }

        // Healthy call: mark the connection as OK for the admin notice.
        $this->record_call_outcome( 0 );

        return $decoded;
    }

    /**
     * Log an API failure, record its marker, and pass the WP_Error through.
     *
     * @param string|int $marker   Value stored in wpstream_curl_failed (non-zero = failing).
     * @param string     $endpoint Endpoint the failing call targeted (for the log).
     * @param WP_Error   $error    The error to return to the caller.
     * @return WP_Error The $error given, unchanged.
     */
    private function record_failure( $marker, $endpoint, $error ) {
        $logger = new WpStream_Logger();
        $logger->add( new WpStream_Log_Entry( array(
            'type'        => 'error',
            'description' => $error->get_error_message() . ' on endpoint ' . $endpoint,
        ) ) );

        $this->record_call_outcome( $marker );

        /**
         * Fires when a call to the WpStream API failed (transport, HTTP status
         * or malformed body).
         *
         * The error carries no credentials: the request body is not attached.
         *
         * @since 4.14.0
         *
         * @param WP_Error $error    The failure (`wpstream_http_error`, `wpstream_bad_json`, transport codes).
         * @param string   $endpoint API path the call targeted.
         */
        do_action( 'wpstream_api_request_failed', $error, (string) $endpoint );

        return $error;
    }

    /**
     * Persist the last API-call outcome (0 = ok) for the admin-notice logic,
     * writing the option only when the value actually changed.
     *
     * @param string|int $marker 0 on success, error/HTTP/JSON code otherwise.
     * @return void
     */
    private function record_call_outcome( $marker ) {
        // String-compare so a missing option (false) still differs from 0,
        // while "0" (DB round-trip) and 0 count as unchanged.
        $previous = get_option( 'wpstream_curl_failed' );
        if ( (string) $previous !== (string) $marker ) {
            update_option( 'wpstream_curl_failed', $marker, false );

            /**
             * Fires when the API connection outcome changes (healthy ↔ failing).
             *
             * Fires once per transition, not per call, so it suits alerting.
             *
             * @since 4.14.0
             *
             * @param string|int $marker   New outcome: 0 healthy, otherwise the HTTP/JSON/transport code.
             * @param string|int $previous The outcome it replaced (false when never recorded).
             */
            do_action( 'wpstream_api_connection_state_changed', $marker, $previous );
        }
    }

    /**
     * POST an authenticated call to the backend API.
     *
     * The connection fetches the account token itself and injects it as the
     * `access_token` body field, so callers never handle credentials. When no
     * token is available no request is made and a `wpstream_not_connected`
     * WP_Error is returned (its message is caller-safe UI text). An HTTP 401
     * invalidates the cached token and is retried once with fresh credentials;
     * a second 401 is returned and its rejected replacement is not cached.
     *
     * @param string $endpoint API path appended to WPSTREAM_API (e.g. 'channel/list').
     * @param array  $fields   POST body fields, without access_token.
     * @param int    $timeout  Request timeout in seconds.
     * @return array|WP_Error Decoded response array, or WP_Error on failure —
     *                        code `wpstream_not_connected` when the site holds no token.
     */
    public function authorized_request( $endpoint, $fields = array(), $timeout = 10 ) {
        $access_token = $this->wpstream_get_token();

        // No token: the site is not linked to a WpStream account. No call is
        // made, and the connection-outcome option is left untouched (nothing
        // about the transport failed).
        if ( empty( $access_token ) ) {
            return new WP_Error( 'wpstream_not_connected', 'Not connected to WPStream service' );
        }

        $fields['access_token'] = $access_token;
        $response               = $this->request( $endpoint, $fields, $timeout );

        // Baker can invalidate a cached token before its local transient expires.
        // Refresh and replay once so the authenticated operation self-heals in
        // the same public call; the direct second request prevents retry loops.
        if ( is_wp_error( $response ) && 401 === (int) $response->get_error_data() ) {
            delete_transient( 'wpstream_token_api' );
            $access_token = $this->wpstream_get_token();
            if ( empty( $access_token ) ) {
                return new WP_Error( 'wpstream_not_connected', 'Not connected to WPStream service' );
            }

            $fields['access_token'] = $access_token;
            $response               = $this->request( $endpoint, $fields, $timeout );
            if ( is_wp_error( $response ) && 401 === (int) $response->get_error_data() ) {
                delete_transient( 'wpstream_token_api' );
            }

            return $response;
        }

        return $response;
    }

    /**
     * Whether this site is Connected: a WpStream API token exists (cached or
     * freshly obtained). Says nothing about any channel being live — use it
     * only for "is the account linked?" checks.
     *
     * @return bool
     */
    public function is_connected() {
        $token = $this->wpstream_get_token();
        return ! empty( $token );
    }

    /**
     * Return the API token, or send the standard "not connected" JSON and die.
     *
     * Shared gate for the AJAX handlers that cannot proceed without a linked
     * WpStream account; keeps the legacy response keys the dashboard JS reads.
     *
     * @return string The access token (never returns on failure).
     */
    private function require_token_or_die() {
        $access_token = $this->wpstream_get_token();
        if ( empty( $access_token ) ) {
            wp_send_json( array(
                'is_record'  => '',
                'connected'  => false,
                'event_data' => '',
                'error'      => esc_html__( 'You are not connected to wpstream.net! Please check your WpStream credentials!', 'wpstream' ),
            ) );
        }
        return $access_token;
    }

    /**
     * Resolve which streaming server hosts a given show, cached in a transient.
     *
     * Calls the legacy REST endpoint `rest-baker.wpstream.net?apiFunctionName=server_id_by_show_id`.
     *
     * @param int|string $show_id Local show/channel post ID.
     * @return string Server id on success, or '' when unavailable/failed.
     * @since 3.0.1
    */

    function wpstream_retrieve_server_id_based_on_show_id($show_id){

            // Per-show cache key so repeat lookups avoid a network round-trip.
            $transient_name      = 'server_id_to_return_' . $show_id;
            $server_id_to_return = get_transient( $transient_name );

            // Cache hit: return the stored server id.
            if ( false !== $server_id_to_return ) {
                return $server_id_to_return;
            }

            // Cache miss: query the legacy REST endpoint (it only accepts its
            // parameters, token included, as GET query args).
            $token = $this->wpstream_get_token();
            $url   = WPSTREAM_REST_API . '/?apiFunctionName=server_id_by_show_id&show_id=' . intval( $show_id ) . '&access_token=' . $token;

            $response = $this->get( $url, 45 );

            // Accept only a success flag with a non-empty result.
            if ( ! is_wp_error( $response ) && isset( $response['success'], $response['result'] ) && $response['success'] === true && $response['result'] != '' ) {
                $server_id_to_return = $response['result'];
                /**
                 * Filter how long a show's server id is cached, in seconds.
                 *
                 * Clamped to [1, 3600]. The transient name and value are frozen.
                 *
                 * @since 4.14.0
                 *
                 * @param int $seconds Lifetime; default 60.
                 */
                $ttl = wpstream_tunable( 'wpstream_server_id_cache_ttl', 60, HOUR_IN_SECONDS );
                // Cache the result briefly and return it.
                set_transient( $transient_name, $server_id_to_return, $ttl );
                return $server_id_to_return;
            }

            // Any failure yields an empty string (not cached).
            return '';
    }

    /**
    * Deprecated misspelled alias of wpstream_retrieve_server_id_based_on_show_id().
    *
    * Kept because the method is public and may be called by external code.
    *
    * @deprecated 4.13.4 Use wpstream_retrieve_server_id_based_on_show_id() instead.
    */
    function retrive_server_id_based_on_show_id($show_id){
            return $this->wpstream_retrieve_server_id_based_on_show_id( $show_id );
    }
    
    
    
    /**
     * AJAX: poll a channel's status while a broadcast is starting up.
     *
     * Calls channel/info (via wpstream_check_event_status_api_call), persists the
     * returned event data, fires `wpstream_channel_became_active` on the active
     * transition, derives the OBS ingest URI/stream key from broadcast_url, and
     * echoes the response as JSON. Endpoint: wp_ajax_wpstream_check_event_status.
     *
     * @return void Prints JSON then dies.
     * @since 3.0.1
    */

    public  function wpstream_check_event_status(){
            // Verify the start-event nonce before doing anything.
	        check_ajax_referer( 'wpstream_start_event_nonce', 'nonce' );
            // Sanitize inputs: the channel id and an optional caller-context note.
            $channel_id         =   intval($_POST['channel_id']);

            // Ownership gate: only the channel's author (or an admin) may query
            // status and have ingest credentials written back to its meta.
            if ( ! wpstream_can_manage_channel( get_current_user_id(), $channel_id ) ) {
                print json_encode( array(
                    'success' => false,
                    'error'   => esc_html__( 'You are not allowed to control this channel.', 'wpstream' ),
                ) );
                die();
            }

            $notes              =   'wpstream_check_event_status_note';
            if(isset($_POST['notes'])){
                $notes = sanitize_text_field($_POST['notes']);
            }


            // Ask the API for the channel's current status/details.
            $response           =   $this->wpstream_check_event_status_api_call($channel_id,$notes);

		    // Remember the prior status so we can detect a transition to active.
		    $previous_status = get_post_meta( $channel_id, 'status', true );

            // Only process a successful API response.
            if( isset($response['success']) && $response['success']){
                // Persist all returned fields to post meta + a short transient.
                $this->api20_wpstream_update_event($response,$channel_id);

	            if (
		            isset( $response['status'] )
		            && $response['status'] === 'active'
		            && $previous_status !== 'active'
	            ) {
		            /**
		             * Fires when the Channel status transitions to `active`
		             * (the broadcaster has connected). This is NOT "Live": a
		             * channel is Live only when it is active AND a playback URL
		             * exists, and the playback URL may not exist yet at this
		             * moment — do not embed a player from this hook. Fires once
		             * per live session (guarded by the previously stored status).
		             *
		             * @param int   $channel_id Channel post ID.
		             * @param array $response   API response data.
		             * @param string $notes     Caller context from JS (e.g. wpstream_check_live_connections_on_start).
		             */
		            do_action( 'wpstream_channel_became_active', $channel_id, $response, $notes );
	            }

	            if (
		            isset( $response['status'] )
		            && in_array( $response['status'], array( 'stopped', 'stopping', 'ended' ), true )
		            && $previous_status === 'active'
	            ) {
		            /**
		             * Fires when a live channel stops streaming (counterpart of
		             * wpstream_channel_became_active). Guarded by the previously
		             * stored status, so it fires once per live session — repeat
		             * polls after the stop see a non-active previous status and
		             * do not re-fire it.
		             *
		             * @param int   $channel_id Channel post ID.
		             * @param array $response   API response data.
		             * @param string $notes     Caller context from JS.
		             */
		            do_action( 'wpstream_channel_became_inactive', $channel_id, $response, $notes );
	            }

                // When active with a broadcast URL, expose ingest details to the client.
                if( isset($response['broadcast_url']) && isset($response['status']) && $response['status']==='active' ){


                    // Surface the QoS/live-data URL under the expected key.
                    $response['live_data_url'] =    $response['qos_url'];

                    /* obsolote due to new url format
                    $local_event_options = get_post_meta ($channel_id,'local_event_options',true);
                    if( is_array( $local_event_options ) && intval( $local_event_options['autostart']) ==1 ){
                        $to_split=explode('/',$response['broadcast_url']);
                        $obs_stream = array_pop($to_split);;
                        $obs_uri    = str_replace($obs_stream,'',$response['broadcast_url']);    
                    }else{
                        $to_split=explode('wpstream/',$response['broadcast_url']);
                        $obs_uri = $to_split[0].'wpstream/';
                        $obs_stream = $to_split[1];
                    }
                    */


                    // Split broadcast_url into the RTMP server URI and stream key:
                    // the last path segment is the stream key, the rest is the URI.
                    $to_split   =   explode('/',$response['broadcast_url']);
                    $obs_stream =   array_pop($to_split);;
                    $obs_uri    =   str_replace($obs_stream,'',$response['broadcast_url']);

                    // Add the derived ingest fields to the response payload.
                    $response['obs_uri']       =    $obs_uri;
                    $response['obs_stream']    =    $obs_stream;

                    // Persist ingest URI, stream key and full broadcast URL to meta.
                    update_post_meta($channel_id,'obs_uri',$obs_uri);
                    update_post_meta($channel_id,'obs_stream',$obs_stream);
                    update_post_meta($channel_id,'broadcast_url',$response['broadcast_url']);
					// Store the embed key when the API supplied one.
					if ( isset( $response['embedKey'] ) && $response['embedKey'] != '' ) {
						update_post_meta( $channel_id,'embedKey',$response['embedKey'] );
					}

                }


            }

            // Return the (possibly augmented) status payload to the JS caller.
            print json_encode($response);
            die();

    }

	/**
	 * AJAX: return the stored WHIP publish URL for a channel (WebRTC ingest).
	 *
	 * Reads the `whipUrl` post meta (no remote call). Endpoint:
	 * wp_ajax_wpstream_check_whipurl.
	 *
	 * @return void Prints JSON then dies.
	 */
	public function wpstream_check_whipurl() {
		// Verify the start-event nonce and read the target channel id.
		check_ajax_referer( 'wpstream_start_event_nonce', 'nonce' );
		$channel_id = intval($_POST['channel_id']);

		// Ownership gate: never return another broadcaster's WHIP credential.
		if ( ! wpstream_can_manage_channel( get_current_user_id(), $channel_id ) ) {
			print json_encode( array(
				'success' => false,
				'error'   => esc_html__( 'You are not allowed to control this channel.', 'wpstream' ),
			) );
			die();
		}

		// The WHIP URL is cached on the channel post meta once known.
		$whip_url = get_post_meta($channel_id, 'whipUrl', true);

		if ( $whip_url ) {
			// Found: return it to the browser broadcaster.
			print json_encode(
				array(
					'success' => true,
					'whip_url' => $whip_url,
				)
			);
		} else {
			// Not yet available for this channel.
			print json_encode(
				array(
					'success' => false,
					'error' => esc_html__('WHIP URL not found for this channel.', 'wpstream'),
				)
			);
		}
		die();
	}
    
    /**
     * Query the remote API for a channel's current status/details.
     *
     * Endpoint: channel/info. Returns the decoded response (embed data included).
     *
     * @param int    $channel_id Channel post ID.
     * @param string $notes      Caller context passed through to the API.
     * @return array|false Decoded response array, or false when no token is available.
     * @since 3.0.1
    */


    public  function wpstream_check_event_status_api_call($channel_id,$notes){

        // This site's host scopes the request; the token is injected by the transport.
        $domain         =   parse_url ( get_site_url() );
        $url            =   'channel/info';

        // Build the POST body; 'embed' asks the API to include embed data.
        $curl_post_fields=array(
            'channel_id'    =>  $channel_id,
            'domain'        =>  $domain['host'],
			'embed'         => true,
            'notes'         =>  $notes
        );




        // POST to channel/info; callers (and the JS poller) expect an array
        // with success/error keys, so map a WP_Error onto that shape — except
        // not-connected, which keeps the legacy `false` so Channel_State never
        // caches a payload for an unlinked site.
        $response = $this->authorized_request( $url, $curl_post_fields, WPSTREAM_TIMEOUT_CONST );
        if ( is_wp_error( $response ) ) {
            if ( 'wpstream_not_connected' === $response->get_error_code() ) {
                return false;
            }
            return array( 'success' => false, 'error' => $response->get_error_message() );
        }

        return $response;


    }
    
    
    
    
    
    
    
    
    
    
    
   
    /**
     * Clear cached per-event meta (stats/HLS/server) for a channel.
     *
     * @param int $event_id Channel/event post ID.
     * @return void
     */
    public function wpstream_reset_event_data($event_id){
        // Blank out the three volatile fields tied to a live session.
        update_post_meta($event_id,'stats_url','');
        update_post_meta($event_id,'hls_playback_url','');
        update_post_meta($event_id,'server_id', '' );
    }

    /**
     * Persist every field of an API event response to post meta and cache it.
     *
     * @param array $response   Decoded API response (key/value event fields).
     * @param int   $channel_id Channel post ID to store the meta against.
     * @return array|false The stored data on success, false if $response is not an array.
     * @since 3.0.1
    */



    function api20_wpstream_update_event($response,$channel_id){

        // Allowlist filtering and the status-cache write are owned by
        // Wpstream_Channel_State (the cache's single writer); it returns the
        // filtered payload, or false for a non-array response.
        $event_data_for_transient = Wpstream_Channel_State::store( $channel_id, $response );
        if ( false === $event_data_for_transient ) {
            // Non-array response: nothing to store.
            return false;
        }

        // Mirror each stored field into post meta — same allowlisted set as
        // the cache, so a response can never plant arbitrary meta keys.
        foreach ( $event_data_for_transient as $key => $value ) {
            update_post_meta( $channel_id, $key, $value );
        }
        return $event_data_for_transient;


    }
            
    /**
     * AJAX: save a single channel's per-event streaming options.
     *
     * Sanitizes the posted option map, enforces the encrypt/low-latency/ABR
     * mutual-exclusion rules, stores it as `local_event_options` post meta, then
     * pushes the config to the API via channel/update.
     * Endpoint: wp_ajax_wpstream_update_local_event_settings.
     *
     * @return void
     * @since 3.0.1
    */


    public function wpstream_update_local_event_settings(){

        // Nonce + auth gate: must be a logged-in administrator.
        check_ajax_referer( 'wpstream_start_event_nonce', 'security' );
        if(!is_user_logged_in()){
            exit('not logged in');
        }
        if( !current_user_can('administrator') ){
            exit('not admin');
        }

		$channel_id = intval( $_POST['show_id'] ?? 0 );
		if ( ! $this->is_published_live_channel( $channel_id ) ) {
			wp_send_json_error( array( 'success' => false, 'error' => 'invalid_channel' ), 400 );
		}

		$result = $this->channel_settings->apply(
			array(
				'type'       => 'save_customized',
				'channel_id' => $channel_id,
				'options'    => ( isset( $_POST['option'] ) && is_array( $_POST['option'] ) ) ? $_POST['option'] : array(),
			)
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'success' => false, 'error' => $result['error'] ?? 'invalid_value' ), 400 );
		}

		$this->send_channel_settings_baker_result( $result );
		wp_send_json( array( 'success' => true === ( $result['baker_success'] ?? false ) ) );

    }

	/**
	 * Push the site's global streaming options onto one channel via the API.
	 *
	 * Not wired to an AJAX action in this class; callable helper. Reads the
	 * `wpstream_user_streaming_global_channel_options` option and syncs it up.
	 *
	 * @return void Emits JSON error on auth/param failure.
	 */
	public function wpstream_update_local_event_settings_with_global() {
		// Nonce check (note: check_ajax_referer is called twice here).
		check_ajax_referer( 'wpstream_start_event_nonce', 'security' );

		check_ajax_referer( 'wpstream_start_event_nonce', 'security' );
		// Auth gate: logged-in administrator required.
		if(!is_user_logged_in()){
			wp_send_json_error(['success' => false, 'message' => __('Not logged in', 'wpstream')]);
		}
		if( !current_user_can('administrator') ){
			wp_send_json_error(['success' => false, 'message' => __('Not admin', 'wpstream')]);
		}

		// Require both expected POST params.
		if ( !isset($_POST['show_id']) || !isset($_POST['use_global']) ) {
			wp_send_json_error(['success' => false, 'message' => __('Missing parameters', 'wpstream')]);
		}

		// Apply the Channel's resolved saved snapshot.
		$show_id = intval($_POST['show_id']);
		$this->wpstream_update_channel_on_baker( $show_id, array() );
	}

	/**
	 * AJAX: toggle whether a channel uses global settings or its own local ones.
	 *
	 * Stores the `use_global_event_options` flag, ensures local options exist
	 * when switching to local, then syncs global options to the API either way.
	 * Endpoint: wp_ajax_wpstream_update_use_global_event_options.
	 *
	 * @return void Emits JSON error on failure.
	 */
	public function wpstream_update_use_global_event_options() {
		// Nonce + administrator auth gate.
		check_ajax_referer( 'wpstream_start_event_nonce', 'security' );
		if(!is_user_logged_in()){
			wp_send_json_error(['success' => false, 'message' => __('Not logged in', 'wpstream')]);
		}
		if( !current_user_can('administrator') ){
			wp_send_json_error(['success' => false, 'message' => __('Not admin', 'wpstream')]);
		}

		// Both parameters are mandatory.
		if ( !isset($_POST['show_id']) || !isset($_POST['use_global']) ) {
			wp_send_json_error(['success' => false, 'message' => __('Missing parameters', 'wpstream')]);
		}

		$channel_id = intval( $_POST['show_id'] );
		if ( ! $this->is_published_live_channel( $channel_id ) ) {
			wp_send_json_error( array( 'success' => false, 'error' => 'invalid_channel' ), 400 );
		}

		$result = $this->channel_settings->apply(
			array(
				'type'       => 'select_mode',
				'channel_id' => $channel_id,
				'mode'       => intval( $_POST['use_global'] ) ? 'defaults' : 'customized',
			)
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'success' => false, 'message' => __( 'Failed to update event', 'wpstream' ) ) );
		}

		$this->send_channel_settings_baker_result( $result );
		// The channel now resolves to a different settings map (its own, or its
		// creation snapshot) than the one the modal on screen was rendered with.
		// Return that map so the browser can repaint the switches: a bare
		// {success} reply leaves stale switches, and the next option click would
		// save those stale values over the channel's real settings.
		wp_send_json(
			array(
				'success' => true === ( $result['baker_success'] ?? false ),
				'mode'    => $result['state']['mode'] ?? '',
				'options' => $result['state']['options'] ?? array(),
			)
		);
	}

	/**
	 * Whether Channel Settings may mutate the requested post.
	 *
	 * @param int $channel_id Candidate post ID.
	 * @return bool
	 */
	private function is_published_live_channel( $channel_id ) {
		$post = get_post( $channel_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return false;
		}

		return 'wpstream_product' === $post->post_type
			|| ( 'product' === $post->post_type && has_term( 'live_stream', 'product_type', $post ) );
	}

	/** Preserve the legacy disconnected response emitted by settings AJAX Adapters. */
	private function send_channel_settings_baker_result( $result ) {
		$baker_result = $result['baker_result'] ?? null;
		if ( is_wp_error( $baker_result ) && 'wpstream_not_connected' === $baker_result->get_error_code() ) {
			wp_send_json(
				array(
					'is_record'  => '',
					'connected'  => false,
					'event_data' => '',
					'error'      => esc_html__( 'You are not connected to wpstream.net! Please check your WpStream credentials!', 'wpstream' ),
				)
			);
		}
	}

	/**
	 * AJAX: save the site-wide default streaming options.
	 *
	 * Sanitizes the posted option map, applies the encrypt/low-latency/ABR
	 * mutual-exclusion rules, and stores it in the
	 * `wpstream_user_streaming_global_channel_options` option.
	 * Endpoint: wp_ajax_wpstream_update_default_channel_settings.
	 *
	 * @return void Emits JSON success/error then dies.
	 */
	public function wpstream_update_default_channel_settings() {
		// CSRF gate, then authorization: writing site-wide defaults requires manage_options.
		// The nonce alone is not authorization — anyone holding a valid settings nonce must
		// still be an administrator to change these options.
		check_ajax_referer( 'wpstream-settings-nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'success' => false ), 403 );
		}

		// Sanitize each posted option key/value, keeping only keys the plugin defines.
		// This is a fixed catalog (the global event options), so an arbitrary key set
		// cannot inject unknown entries into the stored option.
		$result = $this->channel_settings->apply(
			array(
				'type'    => 'save_defaults',
				'options' => ( isset( $_POST['option'] ) && is_array( $_POST['option'] ) ) ? $_POST['option'] : array(),
			)
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'success' => false, 'error' => $result['error'] ?? 'invalid_value' ), 400 );
		}

		wp_send_json( array( 'success' => true ) );

		// Low-latency/ABR disable encryption... (keys may be absent after allowlist filtering).

		// ...and encryption disables low-latency/ABR (mutually exclusive).

		// Merge the posted keys onto the stored option rather than replacing it. The
		// settings form only posts the channel toggles, so a plain update_option() would
		// drop sibling keys the form never sends — notably the site-wide ses_encrypt flag.

		// Persist the merged global defaults option.
	}

	/**
	 * AJAX: generic single-option saver, typed by the option's field kind.
	 *
	 * Sanitizes the value according to `option_type` (checkbox/text/select/
	 * multiple-select) and stores it under `wpstream_{option_name}`.
	 * Endpoint: wp_ajax_wpstream_update_settings.
	 *
	 * @return void Emits JSON success/error then dies.
	 */
	public function wpstream_update_settings() {
		// CSRF gate, then authorization: this generic option writer requires manage_options.
		// A valid settings nonce is not authorization on its own.
		check_ajax_referer( 'wpstream-settings-nonce', 'security' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'success' => false ), 403 );
		}

		// Option identity and its field type drive the sanitization below.
		$option_name    = sanitize_key( $_POST['option_name'] ?? '' );
		$option_type    = sanitize_key( $_POST['option_type'] ?? '' );

		// Only the settings-screen fields that auto-save through this endpoint may be written.
		// This is an explicit allowlist rather than "any wpstream_ key": it deliberately excludes
		// the credential options (api_username / api_password), which are never editable here.
		// Deliberately not filterable: a filter would let any plugin re-open those
		// credential options through admin-ajax.
		$allowed_options = array(
			'free_media_slug',
			'free_media_slug_vod',
			'stream_role',
			'user_streaming_channel_type',
			'user_streaming_default_price',
			'global_sub',
			'global_sub_id',
			'product_not_login',
			'product_not_bought',
			'product_not_subscribe',
			'product_thankyou',
			'subscription_active',
			'you_are_not_live',
			'video_player_theme',
			'player_logo_position',
			'vod_autoplay',
			'vod_start_muted',
			'vod_domain_lock',
			'vod_encrypt',
		);
		if ( ! in_array( $option_name, $allowed_options, true ) ) {
			wp_send_json_error( array( 'success' => false ), 400 );
		}

		// Read the raw value once (may be a string or, for multi-selects, an array).
		$raw_option_value = $_POST['option_value'] ?? '';

		// Sanitize the incoming value per field type.
		switch( $option_type ) {
			case 'checkbox':
				// Coerce to an int (0/1).
				$option_value = filter_var( $raw_option_value, FILTER_VALIDATE_INT );
				break;
			case 'text':
			case 'select':
				// Single scalar text value.
				$option_value = sanitize_text_field( $raw_option_value );
				break;
			case 'multiple-select':
				// Expect an array; empty when not an array.
				if ( !is_array( $raw_option_value ) ) {
					$option_value = array();
					break;
				}
				$option_value = array_map( 'sanitize_text_field', $raw_option_value );
				break;
			default:
				// Fallback: treat as plain text.
				$option_value = sanitize_text_field( $raw_option_value );
		}

		// Persist under a namespaced option name.
		$successful_update = update_option( 'wpstream_' . $option_name, $option_value );

		if( $successful_update ) {
			// Saved (value changed).
			wp_send_json(
				array(
					'success' => true,
				)
			);
		} else {
			// update_option returned false (unchanged value or failure).
			wp_send_json_error(
				array(
					'success' => false,
				)
			);
		}

		wp_die();
	}

 /**
     * Push a channel's resolved streaming config to the API (channel/update).
     *
     * Builds CORS origin, key-access IP, record/encrypt/low-latency/ABR flags
     * from the saved options and POSTs them. Prints a minimal {success} JSON.
     *
     * @param int   $channel_id     Channel post ID.
     * @param array $to_save_option Retained for compatibility; the Module resolves the saved snapshot.
     * @return void Prints a {success} JSON then exits.
     * @since 4.2
    */


    public function wpstream_update_channel_on_baker( $channel_id, $to_save_option = array() ) {

		$configuration = $this->channel_settings->prepare_baker( (int) $channel_id );
		$response      = $this->synchronize_channel_settings( (int) $channel_id, $configuration );
		wp_send_json(
			array(
				'success' => ! is_wp_error( $response ) && ! empty( $response['success'] ),
			)
		);
    }

    /**
    * Deprecated misspelled alias of wpstream_update_channel_on_baker().
    *
    * Kept because the method is public and may be called by external code.
    *
    * @deprecated 4.13.4 Use wpstream_update_channel_on_baker() instead.
    */
    public function wpstream_update_chanel_on_baker( $channel_id, $to_save_option = array() ) {
        return $this->wpstream_update_channel_on_baker( $channel_id, $to_save_option );
    }

	/**
	 * Address this site fetches HLS keys from, as sent to Baker in `allow_key_access_from`.
	 *
	 * Prefers the web server's own SERVER_ADDR. When that is absent (CLI, cron,
	 * proxies, containers) the site's host name is resolved once and cached for
	 * an hour. Only when resolution fails too does the value fall open to
	 * 0.0.0.0/0, and a warning is written to the plugin log so the operator
	 * can see that key delivery is not pinned to this server.
	 *
	 * @return string IPv4 address or '0.0.0.0/0'.
	 */
	public function wpstream_key_access_address() {
		// The request's own server address is the best answer when present.
		$server_addr = isset( $_SERVER['SERVER_ADDR'] ) ? (string) $_SERVER['SERVER_ADDR'] : '';
		if ( false !== filter_var( $server_addr, FILTER_VALIDATE_IP ) ) {
			return $server_addr;
		}

		// Otherwise use the resolved site address cached from an earlier request.
		$cached = get_transient( 'wpstream_key_access_address' );
		if ( false !== $cached ) {
			return $cached;
		}

		// Resolve the site's host name; gethostbyname() echoes the name back on failure.
		$host     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$resolved = '' !== $host ? gethostbyname( $host ) : '';
		if ( false !== filter_var( $resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			set_transient( 'wpstream_key_access_address', $resolved, HOUR_IN_SECONDS );
			return $resolved;
		}

		// Last resort: leave key access open and say so in the log.
		$logger = new WpStream_Logger();
		$logger->add( new WpStream_Log_Entry( array(
			'type'        => 'error',
			'description' => 'HLS key access left open (0.0.0.0/0): SERVER_ADDR is missing and "' . $host . '" could not be resolved.',
		) ) );

		return '0.0.0.0/0';
	}

	/**
	 * The address or CIDR range allowed to fetch a channel's HLS keys.
	 *
	 * A filtered value is accepted only when it is an IP address or an
	 * IP/prefix CIDR range; anything else keeps the detected address so a
	 * malformed callback cannot open or break key delivery.
	 *
	 * @param int $channel_id Live Channel post ID.
	 * @return string IP address or CIDR range.
	 */
	private function allow_key_access_from( $channel_id ) {
		$detected = $this->wpstream_key_access_address();

		/**
		 * Filter the address allowed to fetch the channel's HLS keys.
		 *
		 * Use when keys are served from a different host than this site
		 * (CDN, reverse proxy). Must be an IP or CIDR range.
		 *
		 * @since 4.14.0
		 *
		 * @param string $address    Detected server address (or `0.0.0.0/0` when unknown).
		 * @param int    $channel_id Live Channel post ID.
		 */
		$filtered = trim( (string) apply_filters( 'wpstream_allow_key_access_from', $detected, (int) $channel_id ) );

		// Accept "ip" or "ip/prefix" only; the prefix is bounded by the address family.
		$parts      = explode( '/', $filtered, 2 );
		$is_ip      = false !== filter_var( $parts[0], FILTER_VALIDATE_IP );
		$max_prefix = false !== filter_var( $parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ? 32 : 128;
		$is_ok      = $is_ip && ( ! isset( $parts[1] ) || ( ctype_digit( $parts[1] ) && (int) $parts[1] <= $max_prefix ) );

		return $is_ok ? $filtered : $detected;
	}

	/**
	 * The URL prefix Baker's players fetch HLS keys from for a channel.
	 *
	 * @param int $channel_id Live Channel post ID.
	 * @return string URL prefix the key name is appended to.
	 */
	private function hls_keys_url_prefix( $channel_id ) {
		/**
		 * Filter the HLS key delivery URL prefix.
		 *
		 * The key name is appended verbatim; the endpoint must answer with the
		 * plugin's DRM key delivery (see Wpstream_Drm_Key_Delivery).
		 *
		 * @since 4.14.0
		 *
		 * @param string $prefix     Default `{site_url}?wpstream_livedrm=`.
		 * @param int    $channel_id Live Channel post ID.
		 */
		return esc_url_raw( (string) apply_filters( 'wpstream_hls_keys_url_prefix', get_site_url() . '?wpstream_livedrm=', (int) $channel_id ) );
	}

	/** Production Baker Adapter operation; authentication and HTTP remain connection-owned. */
	public function synchronize_channel_settings( $channel_id, $configuration ) {
		$domain    = wp_parse_url( get_site_url() );

		return $this->authorized_request(
			'channel/update',
			array(
				'channel_id'            => (int) $channel_id,
				'domain'                => $domain['host'] ?? '',
				'allow_access_from'     => $configuration['allow_access_from'],
				'record'                => $configuration['record'],
				'encrypt'               => $configuration['encrypt'],
				'autostart'             => $configuration['autostart'],
				'low_latency'           => $configuration['low_latency'],
				'abr'                   => $configuration['abr'],
				'hls_keys_url_prefix'   => $this->hls_keys_url_prefix( $channel_id ),
				'allow_key_access_from' => $this->allow_key_access_from( $channel_id ),
				'to_save_option'        => $configuration['options'],
			)
		);
	}





    /**
    * AJAX: stop a running channel (channel/stop).
    *
    * Requires a token and stream permission, then POSTs the stop request and
    * echoes a minimal connected/error JSON payload.
    * Endpoint: wp_ajax_wpstream_turn_of_channel.
    *
    * @return void Prints JSON then dies.
    * @since 5.0
    */
    public function wpstream_turn_of_channel(){

        // Target channel id from the request.
        $channel_id  =   intval($_POST['show_id']);

        // Ownership gate: only the channel's author (or an admin) may stop it.
        // Runs BEFORE the token check so an unauthorized caller is denied
        // regardless of whether this site is connected to wpstream.net.
        // NOTE: intentionally NOT nonce-gated — see wpstream_give_me_live_uri()
        // for the caching rationale (stale cached start-event nonce breaks real
        // users). No wp_ajax_nopriv_ registration, so guests cannot reach it;
        // the ownership check is the real CSRF/IDOR guard for logged-in callers.
        if( !wpstream_can_manage_channel( get_current_user_id(), $channel_id ) ){
            exit('You are not allowed to control this channel. Code 408');
        }

        // Require a valid token; otherwise emit a "not connected" JSON and stop.
        $this->require_token_or_die();
        // Current user id (captured; not used further here).
        $current_user       =   wp_get_current_user();
        $userID             =   $current_user->ID;


        // Enforce the site's streaming-permission rule.
        global $wpstream_plugin;
        if( !$wpstream_plugin->main->wpstream_check_user_can_stream() ){
            exit('You are not allowed to stream.Code 407');
        }

        /**
         * Fires when an authorised stop request is about to be sent to Baker.
         *
         * @since 4.14.0
         *
         * @param int $channel_id Live Channel post ID.
         * @param int $user_id    User who asked to stop the channel.
         */
        do_action( 'wpstream_channel_stop_requested', intval( $channel_id ), intval( $userID ) );

        // Build and send the channel/stop request.
        $url            =   'channel/stop';
        $domain         =   parse_url ( get_site_url() );
        $curl_post_fields=array(
            'channel_id'            =>  $channel_id,
            'domain'                =>  $domain['host'],

        );



        // POST to channel/stop.
        $response = $this->authorized_request( $url, $curl_post_fields );

        // Success => report connected; otherwise report already off / not found.
        // Only the outcome is returned — the decoded upstream response stays
        // server-side (no JS consumer reads it, and it can carry API internals).
        if ( ! is_wp_error( $response ) && isset( $response['success'] ) && $response['success'] === true ) {

            // End-of-stream hook: fire once per live session. The stored
            // status is the guard — only a channel still marked active
            // transitions here, and persisting 'stopped' immediately keeps
            // both this path and the status poll from firing again for the
            // same session.
            if ( get_post_meta( $channel_id, 'status', true ) === 'active' ) {
                update_post_meta( $channel_id, 'status', 'stopped' );

                /** This action is documented in wpstream_check_event_status() above. */
                do_action( 'wpstream_channel_became_inactive', $channel_id, $response, 'wpstream_turn_of_channel' );
            }

            wp_send_json( array(
                'connected' => true,
            ) );
        } else {
            /**
             * Fires when Baker refused (or failed) a stop request.
             *
             * @since 4.14.0
             *
             * @param int   $channel_id Live Channel post ID.
             * @param array $response   Decoded upstream response; a transport
             *                          failure is reported as array( 'error' => message ).
             */
            do_action( 'wpstream_channel_stop_failed', intval( $channel_id ), is_wp_error( $response ) ? array( 'error' => $response->get_error_message() ) : (array) $response );

            wp_send_json( array(
                'connected' => false,
                'error'     => esc_html__( 'Channel is already turned off or does not exist!', 'wpstream' ),
            ) );
        }




    }
  
    
    /**
     * AJAX: turn a channel ON and return its live ingest data.
     *
     * Resolves the effective options (local overrides vs global defaults),
     * normalises the encrypt/low-latency/ABR/record flags, then delegates to
     * wpstream_request_live_stream_uri() (channel/start) and echoes the result.
     * Endpoint: wp_ajax_wpstream_give_me_live_uri.
     *
     * @return void Prints JSON then dies.
     * @since 3.0.1
     */
    public function wpstream_give_me_live_uri(){

        // Target channel id from the request.
        $channel_id  =   intval($_POST['show_id']);

        // Ownership gate: only the channel's author (or an admin) may start it.
        // Runs BEFORE the token check so an unauthorized caller is denied
        // regardless of whether this site is connected to wpstream.net.
        // NOTE: intentionally NOT nonce-gated — the start-event nonce is printed
        // into cacheable start-streaming markup, so a page cache serves a stale
        // token and check_ajax_referer would reject legitimate users (this was
        // tried on the abandoned `nonce-fixes` branch and reverted). This handler
        // also has no wp_ajax_nopriv_ registration, so guests cannot reach it;
        // for logged-in callers the ownership check is the real CSRF/IDOR guard.
        if( !wpstream_can_manage_channel( get_current_user_id(), $channel_id ) ){
            exit('You are not allowed to control this channel. Code 408');
        }

        // Require a valid token; otherwise emit a "not connected" JSON and stop.
        $access_token = $this->require_token_or_die();

        // Current user id (used as the request-by id for the API call).
        $current_user       =   wp_get_current_user();
        $userID             =   $current_user->ID;


        // Enforce the site's streaming-permission rule.
        global $wpstream_plugin;
        if( !$wpstream_plugin->main->wpstream_check_user_can_stream() ){
            exit('You are not allowed to stream.Code 407');
        }

		// Quota lookup determines whether advanced (non-basic) features are allowed.
		$pack_details    = $wpstream_plugin->main->quota_manager->get_live_quota_data( 'wpstream_give_me_live_uri' );
		$basic_streaming = $wpstream_plugin->main->quota_manager->is_basic_streaming_mode( $pack_details );

        // Optional onboarding flag threaded into the start request metadata.
        $on_boarding =   '';
        if(isset($_POST['start_onboarding'])){
            $on_boarding =   sanitize_text_field($_POST['start_onboarding']);
        }


        // Decide whether to use this channel's local options or the global set.
		$channel_configuration = $this->channel_settings->prepare_baker( $channel_id );
		$is_autostart          = $channel_configuration['autostart'];
		$is_record             = $channel_configuration['record'];
		$is_encrypt            = $channel_configuration['encrypt'] ? 'true' : 'false';
		$low_latency           = $channel_configuration['low_latency'] ? 'true' : 'false';
		$adaptive_bitrate      = 'common' === $channel_configuration['abr'] ? 'true' : 'false';
		$corsorigin            = $channel_configuration['allow_access_from'];

		/**
		 * Fires when a go-live request passed ownership and streaming permission,
		 * just before Baker is asked to start the channel. Quota has only been
		 * read to pick the feature tier; it is enforced upstream and an exhausted
		 * account surfaces through wpstream_channel_start_failed.
		 *
		 * @since 4.14.0
		 *
		 * @param int   $channel_id    Live Channel post ID.
		 * @param int   $user_id       User who asked to go live.
		 * @param array $configuration Resolved Channel Settings in Baker's
		 *                             flag conventions (record, encrypt, ...).
		 */
		do_action( 'wpstream_channel_start_requested', intval( $channel_id ), intval( $userID ), $channel_configuration );

	    // Translate the resolved option map into the API flag strings.
	    // Fire the channel/start request with the resolved flags.
	    $event_data = $this->wpstream_request_live_stream_uri(
		    $channel_id,
		    $is_autostart,
		    $is_record,
		    $is_encrypt,
		    $low_latency,
		    $adaptive_bitrate,
		    $userID,
		    $corsorigin,
		    $on_boarding,
		    $basic_streaming
	    );

        // Success => return the event data with connected:true.
        if( isset($event_data['success']) && $event_data['success']===true   ){
            /**
             * Fires when Baker issued the ingest for a channel — earlier than
             * wpstream_channel_became_active, which waits for the status poll.
             * The ingest credentials are deliberately not passed.
             *
             * @since 4.14.0
             *
             * @param int $channel_id Live Channel post ID.
             * @param int $user_id    User who went live.
             */
            do_action( 'wpstream_channel_started', intval( $channel_id ), intval( $userID ) );

            wp_send_json( array(
                'is_record'   => $is_record,
                'connected'   => true,
                'event_data'  => $event_data,
            ) );
        }else{
            // Failure => map known error codes to a friendly message.
            $default_error= 'Failed to turn channel ON. Please try again in a few minutes.';
            $plumer_error = '';
            if( isset($event_data['error'])){
                $plumer_error = is_scalar( $event_data['error'] ) ? (string) $event_data['error'] : 'unknown';
                switch ($plumer_error) {
                    case 'NOT_ENOUGH_TRAFFIC':
                        // Quota exhausted: prompt an upgrade.
                        $default_error= 'You do not have enough Streaming Data to turn ON a live channel. Please upgrade your subscription for more resources.' ;
                        break;

                }

            }

            /**
             * Fires when Baker refused to start a channel.
             *
             * @since 4.14.0
             *
             * @param int    $channel_id Live Channel post ID.
             * @param string $error_code Upstream error code (e.g. NOT_ENOUGH_TRAFFIC,
             *                           provisioning_failed), '' when none was given,
             *                           'unknown' when it was not a scalar.
             * @param int    $user_id    User who asked to go live.
             */
            do_action( 'wpstream_channel_start_failed', intval( $channel_id ), $plumer_error, intval( $userID ) );
            // The raw upstream response stays server-side: the JS failure path
            // only reads `error`, and upstream objects can carry API internals.
            wp_send_json( array(
                'is_record'   => $is_record,
                'connected'   => false,
                'event_data'  => '',
                'error'       => $default_error,
            ) );
        }
    }

    /**
     * Send the channel/start request that turns a channel live.
     *
     * Endpoint: channel/start. Assembles domain/CORS/key-access/metadata and the
     * feature flags, gated by $basic_streaming (advanced flags are forced off
     * when the user is not in basic-streaming mode).
     *
     * @param int    $schannel_id       Channel post ID.
     * @param string $is_autostart      Autostart flag (unused; 'true' is sent literally).
     * @param string $is_record         "true"/"false" record flag.
     * @param string $is_encrypt        "true"/"false" encryption flag.
     * @param string $low_latency       "true"/"false" low-latency flag (coerced to bool below).
     * @param string $adaptive_bitrate  "true"/"false" ABR flag.
     * @param int    $request_by_userid Requesting user id (unused in the body).
     * @param string $corsorigin        Allowed origin ('*' or scheme://host).
     * @param string $on_boarding       Non-empty adds on_boarding metadata.
     * @param bool   $basic_streaming   Whether advanced flags are permitted.
     * @return array|null Decoded channel/start response.
     * @since 3.0.1
     */


    public function wpstream_request_live_stream_uri(
		$schannel_id,
		$is_autostart,
		$is_record,
		$is_encrypt,
		$low_latency,
		$adaptive_bitrate,
		$request_by_userid,
		$corsorigin,
		$on_boarding,
		$basic_streaming
    ) {
			if ( $this->streaming_content_creation ) {
				$provisioning = $this->streaming_content_creation->ensure_provisioned( intval( $schannel_id ) );
				if ( empty( $provisioning['success'] ) ) {
					return array(
						'success' => false,
						'error'   => 'provisioning_failed',
					);
				}
			}

            // Resolve site host + scheme for CORS. The scheme comes from the
            // site URL, not is_ssl(): the latter describes the current request
            // and is false under wp-cli/cron and behind SSL-terminating
            // proxies, which would pin the origin to http:// on an https site.
            $domain         = parse_url ( get_site_url() );
            $domain_scheme  = isset( $domain['scheme'] ) ? $domain['scheme'] : 'http';

            // The origin comes resolved from the Channel Settings Module (site
            // origin when domain-locked, `*` or a filtered origin otherwise);
            // only an empty value falls back to this site.
            if ( '' === trim( (string) $corsorigin ) ) {
                $corsorigin = $domain_scheme . '://' . $domain['host'];
            }

            // Adaptive bitrate maps to the 'common' ABR profile.
            $abr='none';
            if($adaptive_bitrate=="true"){
                $abr='common';
            }

            // Coerce the low-latency string flag into a real boolean.
            if($low_latency=="true"){
                $low_latency=true;
            }else{
                $low_latency = false;
            }

            // Normalise basic-streaming to a string flag used as the gate below.
            $basic_streaming = $basic_streaming ? 'true' : 'false';

            // Endpoint; the auth token is injected by the transport.
            $url            =   'channel/start';

            // Metadata sent with the start request (plugin version, onboarding, permalink).
            $metadata_array=array(
                'pluginVersion'=>WPSTREAM_PLUGIN_VERSION
            );

            if($on_boarding!=''){
                $metadata_array['on_boarding']='yes';
            }
            $permalink = get_permalink($schannel_id);
            if ($permalink !== false) {
                $metadata_array['permalink'] = $permalink;
            }

            /**
             * Filter the metadata sent with channel/start.
             *
             * Stored by the WpStream cloud alongside the session (plugin
             * version, onboarding flag, permalink); add tenant or campaign
             * identifiers here. Never put credentials in it.
             *
             * @since 4.14.0
             *
             * @param array $metadata   Key/value metadata (JSON-encoded for transport).
             * @param int   $channel_id Live Channel post ID.
             */
            $metadata_array = (array) apply_filters( 'wpstream_channel_start_metadata', $metadata_array, (int) $schannel_id );

            // Build the channel/start body; advanced flags gated by $basic_streaming.
            $curl_post_fields=array(
                'channel_id'            =>  $schannel_id,
                'domain'                =>  $domain['host'],
                'allow_access_from'     =>  $corsorigin,
                'record'                =>  $basic_streaming ? $is_record : 'false',
                'encrypt'               =>  $basic_streaming ? $is_encrypt : 'false',
                'low_latency'           =>  $basic_streaming ? $low_latency : 'false',
                'abr'                   =>  $basic_streaming ? $abr : 'none',
                'hls_keys_url_prefix'   =>  $this->hls_keys_url_prefix( $schannel_id ),
                'allow_key_access_from' =>  $this->allow_key_access_from( $schannel_id ),
                'metadata'              =>  json_encode($metadata_array),
                'autostart'             =>  'true',
              //  'fakeError'             =>  'init'
            );


            // POST to channel/start; callers read success/error array keys,
            // so map a WP_Error (not-connected included) onto that shape.
            $response = $this->authorized_request( $url, $curl_post_fields );
            if ( is_wp_error( $response ) ) {
                return array( 'success' => false, 'error' => $response->get_error_message() );
            }
            return $response;



    }











    /**
     * Return a cached API auth token, fetching a fresh one on cache miss.
     *
     * The token is stored in the `wpstream_token_api` transient for ~58 min;
     * failed logins are cached for 1s to avoid hammering the API.
     *
     * @return string|false Bearer token, or false when authentication failed.
     * @since 3.0.1
     */
    public function wpstream_get_token(){
        // Try the cached token first.
        $token =  get_transient('wpstream_token_api');
        // Cache miss / empty => attempt a fresh login.
        if ( false === $token || $token === '' || $token=== NULL ) {
            $token = $this->wpstream_club_get_token();
            if ($token !== false){
                /**
                 * Filter how long a good cloud access token is cached, in seconds.
                 *
                 * Clamped to [1, 3500]: the cloud expires the token itself after
                 * an hour, so the cache can only be shortened. The token value
                 * and transient name are never filterable.
                 *
                 * @since 4.14.0
                 *
                 * @param int $seconds Lifetime; default 3500.
                 */
                $ttl = wpstream_tunable( 'wpstream_api_token_ttl', 3500, 3500 );
                set_transient( 'wpstream_token_api', $token, $ttl );
            }
            else {
                /**
                 * Filter how long a failed login is remembered before the next
                 * attempt, in seconds. Clamped to [1, 300]: without it every
                 * request would retry the login.
                 *
                 * @since 4.14.0
                 *
                 * @param int $seconds Cool-down; default 1.
                 */
                $failure_ttl = wpstream_tunable( 'wpstream_api_token_failure_ttl', 1, 5 * MINUTE_IN_SECONDS );
                set_transient( 'wpstream_token_api', 'failed', $failure_ttl );

                /**
                 * Fires when the WpStream account could not be authenticated
                 * (wrong or revoked credentials, API down).
                 *
                 * Deliberately has no arguments: neither credentials nor the
                 * token are ever exposed.
                 *
                 * @since 4.14.0
                 */
                do_action( 'wpstream_api_authentication_failed' );
            }
        }
        // The 'failed' sentinel maps to false for callers.
        $ret = $token === 'failed' ? false : $token;
        return $ret;
    }

    /**
     * Authenticate against the API and return a fresh access token.
     *
     * Endpoint: access_token (password grant using the stored WpStream
     * username/password options).
     *
     * @return string|false|null Access token, false on rejected login, null when credentials are unset.
     * @since 3.0.1
     */
    protected function wpstream_club_get_token(){
        // WpStream credentials: wp-config constants win over the stored options.
        $username       = wpstream_get_api_username();
        $password       = wpstream_get_api_password();

        // No credentials configured: nothing to do.
        if ( $username=='' || $password==''){
            return;
        }

        // Password-grant login body.
        $url              = 'access_token';
        $curl_post_fields = array(
            'grant_type' => 'password',
            'username'   => $username,
            'password'   => $password
        );
        // POST to access_token.
        $response = $this->request( $url, $curl_post_fields );

        // Return the token when present, otherwise signal failure.
        if ( ! is_wp_error( $response ) && isset( $response['access_token'] ) && $response['access_token'] != '' ) {
            return $response['access_token'];
        } else {
             return false;
        }
    }

    /*
     * 
     * Return token for api version 3.0
     * 
     */
    
 
    
	/**
	 * Accessor for the composed user-quota service.
	 *
	 * @return Wpstream_User_Quota_Service
	 */
	public function get_user_quota_service() {
		return $this->user_quota_service;
	}

	/**
	 * Accessor for the composed channel service.
	 *
	 * @return Wpstream_Channel_Service
	 */
	public function get_channel_service() {
		return $this->channel_service;
	}

	/**
	 * Create a remote channel (delegated to the channel service).
	 *
	 * @param int         $channel_id Channel post ID.
	 * @param string|null $domain     Optional domain override.
	 * @return mixed Result from the channel service.
	 */
	public function wpstream_create_channel( $channel_id, $domain = null ) {
		return $this->channel_service->create_channel( $channel_id, $domain );
	}

	/**
	 * Delete a remote channel (delegated to the channel service).
	 *
	 * @param int         $channel_id Channel post ID.
	 * @param string|null $domain     Optional domain override.
	 * @return mixed Result from the channel service.
	 */
	public function wpstream_delete_channel( $channel_id, $domain = null ) {
		return $this->channel_service->delete_channel( $channel_id, $domain );
	}


	/**
	 * AJAX: return the current user's live quota data as JSON.
	 *
	 * Endpoint: wp_ajax_wpstream_check_user_quota. Delegates to the quota manager.
	 *
	 * @return void Prints JSON then dies.
	 */
	public function wpstream_check_user_quota() {
		// Fetch quota via the shared quota manager.
		global $wpstream_plugin;
		$pack_data = $wpstream_plugin->main->quota_manager->get_live_quota_data( 'wpstream_check_user_quota' );
		// On missing/failed data, return a generic error payload.
		if ( ! $pack_data || ! isset( $pack_data['success'] ) || ! $pack_data['success'] ) {
			print json_encode(
				array(
					'success' => false,
					'error' => esc_html__('Couldn\'t get user quota.', 'wpstream'),
				)
			);
		} else {
			// Otherwise echo the full quota payload.
			print json_encode($pack_data);
		}
		die();
	}



    /**
    * Return the current user's active live events, keyed by channel id.
    *
    * Gated by the site's stream permission; delegates the API call to
    * wpstream_request_live_stream_for_user() (channel/list).
    *
    * @return array Map of channel_id => event data (empty when none/not permitted).
    * @since 3.0.1
    */
    public function wpstream_get_live_event_for_user(){
        // Identify the current user.
        $current_user       =   wp_get_current_user();
        $userID             =   $current_user->ID;

        // Bail (empty) when the user is not allowed to stream.
        global $wpstream_plugin;
        if( !$wpstream_plugin->main->wpstream_check_user_can_stream() ){
            return;
        }


        // Fetch active events and re-key them by their channel_id.
        $event_data         =   $this->wpstream_request_live_stream_for_user($userID);
        $return_event       =   array();
        if(is_array($event_data)):
            foreach ($event_data as $key=>$event){
                $return_event[$event['channel_id']]=$event;
            }
        endif;
        return $return_event;
    }
    
    
    
    
    
 
    
    
    
    
    
    /**
    * Fetch the list of active channels for this site from the API.
    *
    * Endpoint: channel/list (status=active).
    *
    * @param int|string $user_id Requesting user id (not sent in the body).
    * @return array|false Array of channel entries, or false on no token/failure.
    * @since 3.0.1
    */
    public function wpstream_request_live_stream_for_user($user_id){

        global $wpstream_plugin;


        // This site's host is sent so the API scopes results correctly.
        $domain = parse_url ( get_site_url() );


        $url            =   'channel/list';

        // Request only currently-active channels; the token is injected by the transport.
        $curl_post_fields=array(
                'domain'        =>  $domain['host'],
                'status'        =>  'active'
            );
        // POST to channel/list; not-connected and transport failures both map to false below.
        $response = $this->authorized_request( $url, $curl_post_fields );

        // Return the channels array on success, false otherwise.
        if ( ! is_wp_error( $response ) && isset( $response['success'], $response['channels'] ) && $response['success'] == true ) {
            return $response['channels'];
        } else {
            return false;
        }


    }
    
    
    /**
    * Return active live channel ids for shortcode use, cached in a transient.
    *
    * Wraps wpstream_request_live_stream_for_user() behind a 30s transient to
    * avoid repeated API calls when the shortcode renders.
    *
    * @param string $outside Unused caller marker.
    * @return array List of channel_id values for currently-active events.
    * @since 3.0.1
    */
     public function api20_wpstream_request_live_stream_for_user_for_shortcode($outside=''){
        global $wpstream_plugin;
        $return_array=array();

        // Serve from the 30s cache when present.
        $result = get_transient('wpstream_live_stream_for_user_for_shortcode');

        // Cache miss: fetch fresh channel list and cache it.
        if($result===false){
            $result = $this->wpstream_request_live_stream_for_user('');
            /**
             * Filter how long the account's active-channel list is cached for
             * shortcodes, in seconds. Clamped to [1, 300] so a channel that
             * just went live shows up within minutes.
             *
             * @since 4.14.0
             *
             * @param int $seconds Lifetime; default 30.
             */
            $ttl = wpstream_tunable( 'wpstream_active_channels_cache_ttl', 30, 5 * MINUTE_IN_SECONDS );
            set_transient( 'wpstream_live_stream_for_user_for_shortcode', $result, $ttl );
        }

        // Reduce the channel entries to just their ids.
        if(is_array($result)):
            foreach($result as $key=>$event){
                $return_array[]=$event['channel_id'];
            }
        endif;
        return $return_array;
     }



    /**
    * Get a signed upload form (S3/AWS) for pushing a recording to storage.
    *
    * Endpoint: video/upload. Admin only.
    *
    * @return array|false Decoded signed-form data, false when no token, or a
    *                     {success:false,error:'not_connected'} array.
    * @since 3.0.1
    */
    public function wpstream_get_signed_form_upload_data(){
        // Admin-only guard.
        if( !current_user_can('administrator') ){
            exit('not admin on wpstream_get_signed_form_upload_data');
        }


        $url            =   'video/upload';

        // POST to video/upload with an empty body (token injected by the
        // transport); return the signed form. Not-connected keeps the literal
        // 'not_connected' error string the admin JS checks.
        $response = $this->authorized_request( $url, array() );
        if ( is_wp_error( $response ) ) {
            $error = 'wpstream_not_connected' === $response->get_error_code() ? 'not_connected' : $response->get_error_message();
            return array( 'success' => false, 'error' => $error );
        }

        return $response;
    }






    
    /**
    * Return a name=>name map of stored videos for admin dropdowns.
    *
    * Fetches the raw list from the API, sorts newest-first by 'time' and
    * reduces it to video names. Admin only.
    *
    * @return array Map of video name => video name.
    * @since 3.0.1
    */
    public function wpstream_get_videos(){
        // Admin-only guard (returns empty for non-admins).
        if( !current_user_can('administrator') ){
          return;
        }


        $video_options          =   array();
        // Pull the raw video listing from the API.
        $video_array            =   $this->wpstream_get_videos_from_api();
	    $video_list_raw_array = false;

	    // Extract the 'items' array if the response shape is as expected.
	    if ( is_array($video_array) && isset($video_array['items']) && is_array($video_array['items']) ) {
		    $video_list_raw_array = $video_array['items'];
	    }

	    if(is_array($video_list_raw_array)){
            // Sort the list by timestamp descending (newest first).
            $keys = array_column($video_list_raw_array, 'time');
            array_multisort($keys, SORT_DESC , $video_list_raw_array);

            // Build the name=>name options map, skipping unnamed entries.
            foreach ($video_list_raw_array as $key => $videos){
                if($videos['name']!=''):
                    $video_options[$videos['name']]=$videos['name'];
                endif;
            }

        }
        return $video_options;
    }
    
    
    
    /**
    * Fetch the raw stored-video listing from the API.
    *
    * Endpoint: video/list. Admin only.
    *
    * @return array|false Decoded listing on success, false when no token, empty array on API failure.
    * @since 3.0.1
    */
    public function wpstream_get_videos_from_api( ){

        // Admin-only guard.
        if( !current_user_can('administrator') ){
            exit('not admin on wpstream_get_videos_from_api');
        }



        $url            =   'video/list';

        // POST to video/list with an empty body (token injected by the transport).
        $response = $this->authorized_request( $url, array() );

        // Not-connected keeps the legacy false return so callers can tell it
        // from an API failure (empty array).
        if ( is_wp_error( $response ) && 'wpstream_not_connected' === $response->get_error_code() ) {
            return false;
        }

        // Return full payload on success, empty array on failure.
        if ( ! is_wp_error( $response ) && isset( $response['success'] ) && $response['success'] == true ) {
            /**
             * Filter the cloud recordings listing shown on the Recordings screen.
             *
             * @since 4.14.0
             *
             * @param array $listing Decoded video/list payload (`videos` holds the entries).
             */
            return (array) apply_filters( 'wpstream_recordings_list', $response );
        } else {
           return array();
        }

    }

    
    
    /**
    * AJAX: get a signed download link for a stored recording.
    *
    * Endpoint: video/download (wp_ajax_wpstream_get_download_link). Admin only.
    * Echoes the raw API response.
    *
    * @return void Prints the API response then exits.
    * @since 3.0.1
    */

    function wpstream_get_download_link(){

        // CSRF gate: the recordings-list JS sends this nonce as 'security'.
        check_ajax_referer( 'wpstream_recordings_nonce', 'security' );
        // Admin-only guard.
        if( !current_user_can('administrator') ){
            exit('not admin on get_download_link');
        }

        // Target video name from the request.
        $video_name                 =   sanitize_text_field($_POST['video_name']);

        // POST to video/download and return the link payload as JSON; the
        // token is injected by the transport. Not-connected keeps the legacy
        // silent return (no JSON emitted).
        $response = $this->authorized_request( 'video/download', array( 'name' => $video_name ) );
        if ( is_wp_error( $response ) ) {
            if ( 'wpstream_not_connected' === $response->get_error_code() ) {
                return false;
            }
            wp_send_json( array( 'success' => false, 'error' => $response->get_error_message() ) );
        }
        wp_send_json( $response );
    }


     /**
    * AJAX: delete a stored recording from cloud storage.
    *
    * Endpoint: video/delete (wp_ajax_wpstream_get_delete_file). Admin only.
    * Echoes the raw API response.
    *
    * @return void Prints the API response then exits.
    * @since 3.0.1
    */
    public function wpstream_get_delete_file(){
        // CSRF gate: the recordings-list JS sends this nonce as 'security'.
        check_ajax_referer( 'wpstream_recordings_nonce', 'security' );
        // Admin-only guard.
        if( !current_user_can('administrator') ){
            exit('not admin on get_delete_file');
        }

        // Target video name from the request.
        $video_name                 =   esc_html($_POST['video_name']);


        // POST to video/delete and return the outcome payload as JSON; the
        // token is injected by the transport. Not-connected keeps the legacy
        // silent return (no JSON emitted).
        $response = $this->authorized_request( 'video/delete', array( 'name' => $video_name ) );
        if ( is_wp_error( $response ) ) {
            if ( 'wpstream_not_connected' === $response->get_error_code() ) {
                return false;
            }
            wp_send_json( array( 'success' => false, 'error' => $response->get_error_message() ) );
        }
        if ( ! empty( $response['success'] ) ) {
            /**
             * Fires after the cloud confirmed a recording was deleted.
             *
             * @since 4.14.0
             *
             * @param string $video_name Recording file name.
             */
            do_action( 'wpstream_recording_deleted', $video_name );
        }
        wp_send_json( $response );
    }


	/**
	 * AJAX: return the raw stored-video listing (used to poll for processing recordings).
	 *
	 * Endpoint: wp_ajax_wpstream_check_pending_videos. Admin only.
	 *
	 * @return void Emits JSON success/error.
	 */
	public function wpstream_check_pending_videos() {
		// Admin-only guard.
		if (!current_user_can('administrator')) {
			wp_send_json_error(__('Unauthorized', 'wpstream'));
		}

		// Return the raw video/list payload to the poller.
		$videos_list_raw = $this->wpstream_get_videos_from_api();
		wp_send_json_success($videos_list_raw);
	}


}// end class
