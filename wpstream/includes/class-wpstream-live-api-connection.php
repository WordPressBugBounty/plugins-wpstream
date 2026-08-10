<?php
/**
 * HTTP client for the WPStream cloud backend (baker.wpstream.net / rest-baker.wpstream.net).
 *
 * This class is the single funnel through which the plugin talks to the remote
 * streaming API. It:
 *  - authenticates against the API (`access_token` grant) and caches the bearer
 *    token in a transient so every call does not re-login (see wpstream_get_token()).
 *  - wraps raw cURL POSTs to the API in a shared helper (wpstream_baker_do_curl_base())
 *    that centralises timeout, error handling, HTTP-status handling, JSON decoding
 *    and failure logging.
 *  - drives the channel lifecycle: start (channel/start), stop (channel/stop),
 *    update settings (channel/update), poll status (channel/info) and list
 *    active channels (channel/list).
 *  - manages recorded video assets stored in the cloud (video/list, video/upload,
 *    video/download, video/delete).
 *  - exposes a large surface of `wp_ajax_*` handlers that the admin JS calls to
 *    turn channels on/off, save per-event and global settings, check DNS/quota/whip,
 *    and enumerate pending recordings.
 *  - delegates quota and channel-create concerns to Wpstream_User_Quota_Service
 *    and Wpstream_Channel_Service (composed in the constructor).
 *
 * NOTE: several AJAX handlers below intentionally left as-is have weak or missing
 * nonce/ownership checks; this is a comments-only pass, so nothing here is changed.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */

// Pull in the two collaborator services this connection composes.
require_once dirname( __FILE__ ) . '/api/class-wpstream-user-quota-service.php';
require_once dirname( __FILE__ ) . '/api/class-wpstream-channel-service.php';

/**
 * Remote streaming-API client plus the admin AJAX surface that drives it.
 */
class Wpstream_Live_Api_Connection  {

    /** @var Wpstream_User_Quota_Service Handles pack/quota lookups against the API. */
    private $user_quota_service;

    /** @var Wpstream_Channel_Service Handles remote channel creation. */
    private $channel_service;


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

        // Channel on/off: request a live URI (start) and stop a running channel.
        add_action( 'wp_ajax_wpstream_give_me_live_uri', array($this,'wpstream_give_me_live_uri') );
        add_action( 'wp_ajax_wpstream_turn_of_channel',  array($this,'wpstream_turn_of_channel') );
        // Per-event and global streaming settings persistence.
        add_action( 'wp_ajax_wpstream_update_local_event_settings',array($this,'wpstream_update_local_event_settings'));
        add_action( 'wp_ajax_wpstream_update_use_global_event_options',array($this,'wpstream_update_use_global_event_options'));
		add_action( 'wp_ajax_wpstream_update_default_channel_settings', array( $this, 'wpstream_update_default_channel_settings' ) );
		add_action( 'wp_ajax_wpstream_update_settings', array( $this, 'wpstream_update_settings' ) );

        // Status/connectivity polling endpoints used while a broadcast is starting.
        add_action( 'wp_ajax_wpstream_check_dns_sync', array($this,'wpstream_check_dns_sync') );
        add_action( 'wp_ajax_wpstream_check_event_status', array($this,'wpstream_check_event_status') );
		add_action( 'wp_ajax_wpstream_check_whipurl', array($this, 'wpstream_check_whipurl') );
		add_action( 'wp_ajax_wpstream_check_user_quota', array($this, 'wpstream_check_user_quota') );

        // Event teardown and recorded-file management (download link, delete).
        add_action( 'wp_ajax_wpstream_close_event', array($this,'wpstream_close_event') );
        add_action( 'wp_ajax_wpstream_get_download_link', array($this,'wpstream_get_download_link') );
        add_action( 'wp_ajax_wpstream_get_delete_file', array($this,'wpstream_get_delete_file') );

        // Connection/credential warnings printed at the top of WPStream admin pages.
        add_action( 'admin_notices',array($this, 'wpstream_admin_notices') );

        // Poll for recordings that are still being processed in the cloud.
		add_action( 'wp_ajax_wpstream_check_pending_videos', array($this,'wpstream_check_pending_videos') );


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
        if (!empty($_GET['page'])) {
            // Sanitize the requested page and bail if it is not one of ours.
            $page =  esc_html($_GET['page']) ;
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

        // Attempt to fetch (cached) auth token; empty means not connected.
        $token          =   $this->wpstream_get_token();
        if($token=='' and $page!='wpstream_plugin_options'){
            // echo 'wpstream_curl_failed: ' . get_option('wpstream_curl_failed');
            // wpstream_curl_failed === "0" => credentials wrong (no cURL error);
            // otherwise a transport/HTTP error occurred and was surfaced above.
            $text = get_option('wpstream_curl_failed') === "0" ?
                'Not connected to WpStream. Please check your credentials <a href="/wp-admin/admin.php?page=wpstream_credentials">here</a>.' :
                'Not connected to WpStream. Please note the errors above and contact support.';

            // Render the appropriate "not connected" message.
            echo '<div class="api_not_conected wpstream_notice_top">'.__($text,'wpstream').'</div>';
        }

	}
    
    
    /*
     * Curl request
     *
     *
     *
     * */

    /**
     * Shared low-level POST to the WPStream backend API.
     *
     * Every remote call in this class funnels through here. Handles transport
     * errors, non-200 HTTP codes and (optionally) malformed JSON, records the
     * failure code in the `wpstream_curl_failed` option, logs errors, and always
     * returns a JSON string (either the raw API body or a synthesized error).
     *
     * @param string $url              API path appended to WPSTREAM_API (e.g. 'channel/start').
     * @param array  $curl_post_fields POST body fields (http_build_query'd).
     * @param bool   $expect_json      When true, validate the response is well-formed JSON.
     * @param bool   $quiet            When true, suppress echoed HTML error notices (for AJAX/JSON callers).
     * @param int    $timeout          cURL timeout in seconds.
     * @return string JSON string: raw API response, or a synthesized {success:false,error:...} payload.
     */
    function wpstream_baker_do_curl_base($url,$curl_post_fields, $expect_json = false, $quiet = false, $timeout = 10){
		// Initialise the handle and resolve the base API host (test vs production).
		$curl         = curl_init();
		$base_api_url = defined('WPSTREAM_TEST_API' ) ? WPSTREAM_TEST_API : WPSTREAM_API;
		$api_url      = $base_api_url . '/' . $url;

        // Configure the request as a POST with form-encoded body and no-cache headers.
        curl_setopt_array($curl, array(
          CURLOPT_URL =>$api_url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => $timeout,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => http_build_query($curl_post_fields),
          CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded"
          ),
        ));

        // Execute the request and capture body, transport error and HTTP status.
        $response   = curl_exec($curl);
        $err        = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // 0 = success; overwritten below with the error/HTTP code on failure.
        $curl_failed = 0;

		// Logger used to persist any failure to the plugin's log store.
		$logger = new WpStream_Logger();

        // Branch 1: transport-level cURL failure (DNS, connection refused, etc.).
        if ($err) {
            // do not echo every time, some operations must return JSON
            if (!$quiet){
                echo '<div class="api_not_conected wpstream_error_curl">Critical: Could Not Connect to WpStream - '.$err.'</div>';
            }

            // Record the cURL error string as the failure marker.
            $curl_failed = $err;

			// Log the transport error against the endpoint that was called.
			$log_entry = new WpStream_Log_Entry([
				'type'        => 'error',
				'description' => 'CURL error: ' . $err . ' on endpoint ' . $url,
			]);
			$logger->add( $log_entry );

            // Replace the (empty) body with a synthesized error payload.
            $response = json_encode(   array(
                'success'      =>  false,
                'error'        =>  $err,
            ));
        }
        // Branch 2: request reached the server but returned a non-200 status.
        else if ($http_code != 200) {
			// Log the HTTP error together with the failing endpoint.
			$log_entry = new WpStream_Log_Entry([
				'type'        => 'error',
				'description' => 'HTTP error: ' . $http_code . ' on endpoint ' . $url,
			]);
			$logger->add( $log_entry );

            if (!$quiet){
                // Map common HTTP codes to a human-readable admin message.
                switch ($http_code) {
                    case 0:
                        $message = "CURL failed with code 0. Please address CURL connectivity with your hosting provider.";
                        break;
                    case 429:
                        $message = "API: Too many Requests";
                        break;
					case 403:
						$message = "API: Access Forbidden with response: " . $response . ' and HTTP code: ' . $http_code;
						break;
                    default:
                        $message = "API - Unexpected response: " . $http_code;
                        break;
                }
                echo '<div class="api_not_conected wpstream_error_curl">'.$message.'</div>';
            }
            // Record the HTTP status as the failure marker.
            $curl_failed = $http_code;

            // Replace the body with a synthesized error payload.
            $response = json_encode(   array(
                'success'      =>  false,
                'error'        =>  $http_code,
            ));
        }
        // Branch 3: 200 OK and the caller expects JSON — validate it parses.
        else if ($expect_json){
            $curl_response_decoded  =   json_decode($response,JSON_OBJECT_AS_ARRAY);
            if (JSON_ERROR_NONE !== json_last_error()) {
	            // Malformed JSON from the API; log the parse error and endpoint.
	            $log_entry = new WpStream_Log_Entry([
		            'type'        => 'error',
		            'description' => 'Malformed JSON response: ' . json_last_error_msg() . ' on endpoint ' . $url,
	            ]);
	            $logger->add( $log_entry );

	            if (!$quiet) {
		            echo '<div class="api_not_conected wpstream_error_curl">Critical: Malformed API response #: ' . json_last_error() . '</div>';
	            }

	            // Record the JSON error code as the failure marker.
	            $curl_failed = json_last_error();

	            // Replace the unparseable body with a synthesized error payload.
	            $response = json_encode(array(
		            'success' => false,
		            'error' => json_last_error(),
	            ));
            }
        }

		// Persist the last call's outcome (0 = ok) for the admin-notice logic.
		update_option( "wpstream_curl_failed", $curl_failed );

        // Always a JSON string: raw success body or a synthesized error payload.
        return $response;
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

    function retrive_server_id_based_on_show_id($show_id){

            // Per-show cache key so repeat lookups avoid a network round-trip.
            $transient_name = 'server_id_to_return_'.$show_id;
            $server_id_to_return = get_transient( $transient_name );

            // Cache miss: query the remote API for the server id.
            if ( false ===  $server_id_to_return  ) {
                $token  = $this->wpstream_get_token();
                // NOTE: this first $values_array is immediately overwritten below.
                $values_array=array(
                    "show_id"           =>  intval($show_id),
                );
                $values_array=array();
                $show_id=intval($show_id);

                // Build the GET URL (show id + access token passed as query args).
                $url="https://rest-baker.wpstream.net/?&apiFunctionName=server_id_by_show_id&show_id=".$show_id."&access_token=".$token;
                $arguments = array(
                    'method'        => 'GET',
                    'timeout'       => 45,
                    'redirection'   => 5,
                    'httpversion'   => '1.0',
                    'blocking'      => true,
                    'headers'       => array(),
                    'body'          => $values_array,
                    'cookies'       => array()
                );

                // Perform the request via the WP HTTP API.
                $response       = wp_remote_post($url,$arguments);

                // Extract and JSON-decode the response body.
                $received_data  =  wp_remote_retrieve_body($response);

                $received_data_decoded=json_decode($received_data);


                // Accept only a 200 with a truthy success flag and a non-empty result.
                if( isset($response['response']['code']) && $response['response']['code']=='200' && $received_data_decoded->success===true && $received_data_decoded->result!=''){
                    $server_id_to_return = $received_data_decoded->result;
                    // Cache the result briefly (60s) and return it.
                    set_transient( $transient_name, json_decode($server_id_to_return), 60 );
                    return $server_id_to_return;

                }else{
                    // Any failure yields an empty string (not cached).
                    return '';

                }
            }else{
                // Cache hit: return the stored server id.
                return $server_id_to_return;
            }

            // Unreachable (both branches return above).
            die();
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
		             * Fires when a channel becomes active and is ready to stream.
		             *
		             * @param int   $channel_id Channel post ID.
		             * @param array $response   API response data.
		             * @param string $notes     Caller context from JS (e.g. wpstream_check_live_connections_on_start).
		             */
		            do_action( 'wpstream_channel_became_active', $channel_id, $response, $notes );
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

        // Resolve auth token + this site's host for the request.
        $access_token   =   $this->wpstream_get_token();
        $domain         =   parse_url ( get_site_url() );
        $url            =   'channel/info';

        // do not make the call if no token is available
        if (!$access_token) return false;

        // Build the POST body; 'embed' asks the API to include embed data.
        $curl_post_fields=array(
            'access_token'  =>  $access_token,
            'channel_id'    =>  $channel_id,
            'domain'        =>  $domain['host'],
			'embed'         => true,
            'notes'         =>  $notes
        );




        // POST to channel/info (expect JSON, not quiet, plugin timeout constant).
        $curl_response          =   $this->wpstream_baker_do_curl_base($url,$curl_post_fields,true, false,WPSTREAM_TIMEOUT_CONST);


        // Decode and return the response as an associative array.
        $curl_response_decoded  =   json_decode($curl_response,JSON_OBJECT_AS_ARRAY);

        return $curl_response_decoded;


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

        // Only arrays can be iterated into post meta.
        if( is_array($response) )  {
            $event_data_for_transient               =   array();
            $transient_name                         =   'event_data_to_return_'.$channel_id;

            // Mirror each response field into post meta and the transient payload.
            foreach($response as $key=>$value){
                update_post_meta($channel_id,$key,$value);
                $event_data_for_transient[$key]=$value;
            }
            // Cache the assembled event data for 45 seconds.
            set_transient($transient_name,$event_data_for_transient,45);
            return $event_data_for_transient;
        }else{
            // Non-array response: nothing to store.
            return false;
        }


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

        // Read the target channel id and the raw posted option array.
        $show_id        =   intval($_POST['show_id']);
        $option_array   =   $_POST['option'];

        // Sanitize each key/value pair before persisting.
        $to_save_option=array();
        foreach($option_array as $key=>$value){
            $to_save_option[sanitize_key($key)]=sanitize_text_field($value);
        }

        // Low-latency or adaptive bitrate is incompatible with encryption.
        if(   $to_save_option['low_latency'] == 1 ||   $to_save_option['adaptive_bitrate'] ==1 ){
            $to_save_option['encrypt']=0;
        }

        // Conversely, encryption disables low-latency and adaptive bitrate.
        if(  $to_save_option['encrypt']==1){
            $to_save_option['low_latency'] = 0;
            $to_save_option['adaptive_bitrate'] =0;
        }



        // Save locally, then sync the resolved options up to the API.
        update_post_meta ($show_id,'local_event_options',$to_save_option);
        $this->wpstream_update_chanel_on_baker($show_id,$to_save_option);

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

		// Load the global defaults and apply them to the given channel.
		$show_id = intval($_POST['show_id']);
		$default_channel_settings = get_option('wpstream_user_streaming_global_channel_options') ;

		$this->wpstream_update_chanel_on_baker($show_id, $default_channel_settings);
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

		// Read the channel id and the desired global/local flag.
		$show_id                  = intval($_POST['show_id']);
		$use_global_event_options = intval($_POST['use_global']);

		// Persist the flag onto the channel post meta.
		$result = update_post_meta(
			$show_id,
			'use_global_event_options',
			$use_global_event_options
		);

		// update_post_meta returns false when the value is unchanged or fails.
		if ( !$result ) {
			wp_send_json_error(['success' => false, 'message' => __('Failed to update event', 'wpstream')]);
		}

		// Current local overrides for this channel (may be empty).
		$local_event_options = get_post_meta($show_id,'local_event_options',true);

		if( $use_global_event_options ) {
			// Using global: sync the global option set to the API.
			$global_event_options = get_option('wpstream_user_streaming_global_channel_options');

			$this->wpstream_update_chanel_on_baker( $show_id, $global_event_options );
		} else {
			// Using local: still need a baseline if none exists yet.
			$global_event_options = get_option('wpstream_user_streaming_global_channel_options');

			// if the local options are not set, we need to set them to the global ones
			if ( !is_array($local_event_options) ) {
				update_post_meta( $show_id, 'local_event_options', $global_event_options );
			}

			// Sync the global option set to the API.
			$this->wpstream_update_chanel_on_baker( $show_id, $global_event_options );
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
		// Settings-nonce gate (no explicit capability check here).
		check_ajax_referer( 'wpstream-settings-nonce', 'security' );

		// Sanitize each posted option key/value.
		$options_array= $_POST['option'];
		$sanitized_options = array();
		foreach( $options_array as $key => $value ) {
			$sanitized_options[ sanitize_key( $key ) ] = sanitize_text_field( $value );
		}

		// Low-latency/ABR disable encryption...
		if ( $sanitized_options['low_latency'] == 1 || $sanitized_options['adaptive_bitrate'] == 1 ) {
			$sanitized_options['encrypt'] = 0;
		}

		// ...and encryption disables low-latency/ABR (mutually exclusive).
		if ( $sanitized_options['encrypt'] == 1 ) {
			$sanitized_options['low_latency'] = 0;
			$sanitized_options['adaptive_bitrate'] = 0;
		}

		// Persist the global defaults option.
		$successful_update = update_option( 'wpstream_user_streaming_global_channel_options', $sanitized_options );

		if ( $successful_update ) {
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
	 * AJAX: generic single-option saver, typed by the option's field kind.
	 *
	 * Sanitizes the value according to `option_type` (checkbox/text/select/
	 * multiple-select) and stores it under `wpstream_{option_name}`.
	 * Endpoint: wp_ajax_wpstream_update_settings.
	 *
	 * @return void Emits JSON success/error then dies.
	 */
	public function wpstream_update_settings() {
		// Settings-nonce gate (no explicit capability check here).
		check_ajax_referer( 'wpstream-settings-nonce', 'security' );

		// Option identity and its field type drive the sanitization below.
		$option_name    = sanitize_key( $_POST['option_name'] );
		$option_type    = sanitize_key( $_POST['option_type'] );

		// Sanitize the incoming value per field type.
		switch( $option_type ) {
			case 'checkbox':
				// Coerce to an int (0/1).
				$option_value = filter_var( $_POST['option_value'], FILTER_VALIDATE_INT );
				break;
			case 'text':
			case 'select':
				// Single scalar text value.
				$option_value = sanitize_text_field( $_POST['option_value'] );
				break;
			case 'multiple-select':
				// Expect an array; empty when not an array.
				if ( !is_array( $_POST['option_value'] ) ) {
					$option_value = array();
					break;
				}
				$option_value = array_map( 'sanitize_text_field', $_POST['option_value'] );
				break;
			default:
				// Fallback: treat as plain text.
				$option_value = sanitize_text_field( $_POST['option_value'] );
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
     * from the saved options and POSTs them. Prints the raw API response.
     *
     * @param int   $channel_id     Channel post ID.
     * @param array $to_save_option Sanitized option map (record/encrypt/low_latency/adaptive_bitrate...).
     * @return void Prints the API response then exits.
     * @since 4.2
    */


    public function wpstream_update_chanel_on_baker($channel_id,$to_save_option){

        // Require a valid token; otherwise emit a "not connected" JSON and stop.
        $access_token   =   $this->wpstream_get_token();
        if($access_token==''){
            // cleanup any previous echo before sending json
            ob_end_clean();
            echo json_encode(   array(
                    'is_record'     =>  '',
                    'conected'      =>  false,
                    'event_data'    =>  '',
                    'error'         =>  esc_html('You are not connected to wpstream.net! Please check your WpStream credentials!','wpstream'),
                ));
            exit();
        }
        // Current user id (captured but not sent in the request below).
        $current_user       =   wp_get_current_user();
        $userID             =   $current_user->ID;



        // (First $url assignment; overwritten to 'channel/update' below.)
        $url            =   '/channel/update';
        // Any per-channel local overrides, used only for the domain_lock check.
        $local_event_options =   get_post_meta($channel_id,'local_event_options',true);

        // Determine site host + scheme for CORS origin.
        $domain         = parse_url ( get_site_url() );
        $domain_scheme  =   'http';
        if(is_ssl()){
            $domain_scheme='https';
        }

        // Server IP allowed to fetch HLS keys; fall back to open range.
        $domain_ip= esc_html( $_SERVER['SERVER_ADDR'] );
        if($domain_ip==''){
            $domain_ip="0.0.0.0/0";
        }

        // domain_lock off => allow any origin; on => restrict to this site.
        $corsorigin='*';
        if( isset($local_event_options['domain_lock']) && intval( $local_event_options['domain_lock']) ==0 ){
            $corsorigin='*';
        } else{
            $corsorigin=$domain_scheme.'://'.$domain['host'];
        }


        // Actual endpoint path used for the request.
        $url            =   'channel/update';


        // Map the boolean record option to the API's string flag.
        $to_record="false";
        if($to_save_option['record']){
            $to_record="true";
        }

        // Re-apply the encrypt vs low-latency/ABR mutual-exclusion rules.
        if(   $to_save_option['low_latency'] == 1 ||   $to_save_option['adaptive_bitrate'] ==1 ){
            $to_save_option['encrypt']=0;
        }

        if(  $to_save_option['encrypt']==1){
            $to_save_option['low_latency'] = 0;
            $to_save_option['adaptive_bitrate'] =0;
        }


        // Adaptive bitrate maps to the 'common' ABR profile, else none.
        $abr='none';
        if($to_save_option['adaptive_bitrate'] ==1 ){
            $abr='common';
        }



        // Assemble the channel/update POST body.
        $curl_post_fields=array(
            'access_token'          =>  $access_token,
            'channel_id'            =>  $channel_id,
            'domain'                =>  $domain['host'],
            'allow_access_from'     =>  $corsorigin,
            'record'                =>  $to_record,
            'encrypt'               =>  boolval($to_save_option['encrypt']),
            'autostart'             =>  'true',
            'low_latency'           =>  boolval($to_save_option['low_latency']),
            'abr'                   =>  $abr,
            'hls_keys_url_prefix'   =>  get_site_url().'?wpstream_livedrm=',
            'allow_key_access_from' =>  $domain_ip,
            'to_save_option'        =>  $to_save_option,
        );



        // POST to channel/update and echo the raw response back to the caller.
        $curl_response          =   $this->wpstream_baker_do_curl_base($url,$curl_post_fields,true);
        print_r($curl_response);
        exit();
    }





    /**
    * AJAX: stop a running channel (channel/stop).
    *
    * Requires a token and stream permission, then POSTs the stop request and
    * echoes a connected/answer JSON payload.
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
        $access_token   =   $this->wpstream_get_token();
        if($access_token==''){
            // cleanup any previous echo before sending json
            ob_end_clean();
            echo json_encode(   array(
                    'is_record'     =>  '',
                    'conected'      =>  false,
                    'event_data'    =>  '',
                    'error'         =>  esc_html('You are not connected to wpstream.net! Please check your WpStream credentials!','wpstream'),
                ));
            exit();
        }
        // Current user id (captured; not used further here).
        $current_user       =   wp_get_current_user();
        $userID             =   $current_user->ID;


        // Enforce the site's streaming-permission rule.
        global $wpstream_plugin;
        if( !$wpstream_plugin->main->wpstream_check_user_can_stream() ){
            exit('You are not allowed to stream.Code 407');
        }

        // Build and send the channel/stop request.
        $url            =   'channel/stop';
        $domain         =   parse_url ( get_site_url() );
        $curl_post_fields=array(
            'access_token'          =>  $access_token,
            'channel_id'            =>  $channel_id,
            'domain'                =>  $domain['host'],

        );



        // POST to channel/stop and decode the response.
        $curl_response          =   $this->wpstream_baker_do_curl_base($url,$curl_post_fields,true);
        $curl_response_decoded  =   json_decode($curl_response,JSON_OBJECT_AS_ARRAY);



        // Success => report connected; otherwise report already off / not found.
        if( isset($curl_response_decoded['success']) && $curl_response_decoded['success']===true   ){
            // cleanup any previous echo before sending json
            ob_end_clean();
            echo json_encode(   array(
                'conected'      =>  true,
                'answer'        =>  $curl_response_decoded,
            ));
        }else{
            // cleanup any previous echo before sending json
            ob_end_clean();
            echo json_encode(   array(
                'conected'      =>  false,
                'error'         =>  esc_html__('Channel is already turned off or does not exist!','wpstream'),
                'answer'        =>  $curl_response_decoded,
            ));
        }
        die();




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
        $access_token   =   $this->wpstream_get_token();
        if($access_token==''){
            // cleanup any previous echo before sending json
            ob_end_clean();
            echo json_encode(   array(
                    'is_record'     =>  '',
                    'conected'      =>  false,
                    'event_data'    =>  '',
                    'error'         =>  esc_html('You are not connected to wpstream.net! Please check your WpStream credentials!','wpstream'),
                ));
            exit();
        }

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
        $local_event_options = get_post_meta($channel_id,'local_event_options',true);
		$use_global_event_options = get_post_meta($channel_id,'use_global_event_options',true);
	    // Local options apply when they exist and global is not requested/enabled.
	    $is_local_event_options_enabled = ( is_array( $local_event_options ) && empty( $use_global_event_options ) ) ||
            ( !empty($use_global_event_options) && intval( $use_global_event_options ) === 0 );
	    if( !$is_local_event_options_enabled ) {
		    // Fall back to the site-wide global defaults.
		    $local_event_options = get_option('wpstream_user_streaming_global_channel_options') ;
	    }

	    // API flags default to string "true"/"false" (the API expects strings).
	    $is_autostart="true";
	    $is_encrypt="false";
	    $low_latency="false";
	    $adaptive_bitrate="false";
	    $is_record="false";
	    $corsorigin='*';

	    // Translate the resolved option map into the API flag strings.
	    if ( is_array($local_event_options) ) {

		    // Encryption enabled?
		    if ( isset($local_event_options['encrypt']) &&
		         intval( $local_event_options['encrypt']) == 1 ) {
			    $is_encrypt = "true";
		    }

		    // Low-latency enabled?
		    if ( isset($local_event_options['low_latency']) &&
		         intval( $local_event_options['low_latency']) == 1 ) {
			    $low_latency = "true";
		    }

		    // Adaptive bitrate enabled?
		    if ( isset($local_event_options['adaptive_bitrate']) &&
		         intval( $local_event_options['adaptive_bitrate']) == 1 ) {
			    $adaptive_bitrate = "true";
		    }

		    // Recording enabled?
		    if ( isset($local_event_options['record']) &&
		         intval( $local_event_options['record']) == 1 ) {
			    $is_record = "true";
		    }

		    // domain_lock unset or 0 => allow any origin.
		    if ( !isset($local_event_options['domain_lock']) || intval( $local_event_options['domain_lock']) == 0 ) {
			    $corsorigin = '*';
		    }
	    }

	    // Encryption is incompatible with ABR/low-latency: turn it off if either is on.
	    if( $adaptive_bitrate=="true" || $low_latency=="true" ) {
		    $is_encrypt="false";
	    }

	    // Conversely, encryption forces ABR/low-latency off.
	    if( $is_encrypt=="true" ) {
		    $adaptive_bitrate="false";
		    $low_latency="false";
	    }

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
            // cleanup any previous echo before sending json
            ob_end_clean();
            echo json_encode(   array(
                'is_record'     =>  $is_record,
                'conected'      =>  true,
                'event_data'    =>  $event_data,

                ));
        }else{
            // Failure => map known error codes to a friendly message.
            $default_error= 'Failed to turn channel ON. Please try again in a few minutes.';
            if( isset($event_data['error'])){
                $plumer_error = $event_data['error'];
                switch ($plumer_error) {
                    case 'NOT_ENOUGH_TRAFFIC':
                        // Quota exhausted: prompt an upgrade.
                        $default_error= 'You do not have enough Streaming Data to turn ON a live channel. Please upgrade your subscription for more resources.' ;
                        break;

                }

            }
            // cleanup any previous echo before sending json
            ob_end_clean();
            echo json_encode(   array(
                'is_record'     =>  $is_record,
                'conected'      =>  false,
                'event_data'    =>  $event_data,
                'error'         =>  $default_error,


                ));
        }
        die();
    }

	/**
	 * Whether the current user is on the "streamify" HLS-proxy path.
	 *
	 * Currently hard-disabled (returns false); the quota-based logic is retained
	 * commented-out for reference.
	 *
	 * @return bool Always false.
	 */
	public function wpstream_is_streamify_user() {
		return false;
//		global $wpstream_plugin;
//		$pack_details = $wpstream_plugin->main->quota_manager->get_live_quota_data( 'wpstream_is_streamify_user' );
//
//		if ( isset( $pack_details['available_data'] ) && $pack_details['available_data'] <= 0 ) {
//			return true;
//		}
//		return false;
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

            // Resolve site host + scheme for CORS.
            $domain         = parse_url ( get_site_url() );
            $domain_scheme  =   'http';
            if(is_ssl()){
                $domain_scheme='https';
            }

            // Server IP allowed to fetch HLS keys; fall back to open range.
            $domain_ip= esc_html( $_SERVER['SERVER_ADDR'] );
            if($domain_ip==''){
                $domain_ip="0.0.0.0/0";
            }

            // If not fully open, lock CORS to this site's scheme+host.
            if($corsorigin!='*'){
                $corsorigin=$domain_scheme.'://'.$domain['host'];
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

            // Endpoint + auth token.
            $url            =   'channel/start';
            $access_token   =   $this->wpstream_get_token();

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

            // Build the channel/start body; advanced flags gated by $basic_streaming.
            $curl_post_fields=array(
                'access_token'          =>  $access_token,
                'channel_id'            =>  $schannel_id,
                'domain'                =>  $domain['host'],
                'allow_access_from'     =>  $corsorigin,
                'record'                =>  $basic_streaming ? $is_record : 'false',
                'encrypt'               =>  $basic_streaming ? $is_encrypt : 'false',
                'low_latency'           =>  $basic_streaming ? $low_latency : 'false',
                'abr'                   =>  $basic_streaming ? $abr : 'none',
                'hls_keys_url_prefix'   =>  get_site_url().'?wpstream_livedrm=',
                'allow_key_access_from' =>  $domain_ip,
                'metadata'              =>  json_encode($metadata_array),
                'autostart'             =>  'true',
              //  'fakeError'             =>  'init'
            );


            // POST to channel/start, decode and return the response.
            $curl_response          =   $this->wpstream_baker_do_curl_base($url,$curl_post_fields,true);
            $curl_response_decoded  =   json_decode($curl_response,JSON_OBJECT_AS_ARRAY);
            // $curl_response_decoded['curl_post_fields']=$curl_post_fields;
            return $curl_response_decoded;



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
                // Cache a good token for ~58 minutes.
                set_transient( 'wpstream_token_api', $token ,3500);
            }
            else {
                // cache the failed response for a second, otherwise it'll make a shitload of requests
                set_transient( 'wpstream_token_api', 'failed' , 1);
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
        // Stored WpStream credentials.
        $username       = get_option('wpstream_api_username','');
        $password       = get_option('wpstream_api_password','');

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
        // POST to access_token and decode the response object.
        $curl_response    = $this->wpstream_baker_do_curl_base($url, $curl_post_fields, true);
		$response         = json_decode($curl_response);

        // Return the token when present, otherwise signal failure.
        if( isset( $response->access_token ) && $response->access_token != '' ) {
            return $response->access_token;
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
    * Return the current user's package/quota data (delegated to the quota service).
    *
    * @param string $context Caller label for logging/telemetry.
    * @return mixed Pack data as returned by the quota service.
    * @since 3.0.1
    */

    public function wpstream_request_pack_data_per_user($context = ''){
		return $this->user_quota_service->request_pack_data_per_user( $context );
    }

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
    * Report whether the API is reachable.
    *
    * Currently a stub that always reports available.
    *
    * @return bool Always true.
    * @since 3.0.1
    */

    function wpstream_client_check_api_status(){
        return true;
    }





    /**
    * Return the current user's active live events, keyed by channel id.
    *
    * Gated by the site's stream permission; delegates the API call to
    * wpstream_request_live_stream_for_user() (channel/list).
    *
    * @param string $with_exit Legacy flag; both branches return early (no output).
    * @return array Map of channel_id => event data (empty when none/not permitted).
    * @since 3.0.1
    */
    public function wpstream_get_live_event_for_user($with_exit='yes'){
        // Identify the current user.
        $current_user       =   wp_get_current_user();
        $userID             =   $current_user->ID;

        // Bail (empty) when the user is not allowed to stream.
        global $wpstream_plugin;
        if( !$wpstream_plugin->main->wpstream_check_user_can_stream() ){
            if($with_exit=='yes'){
               // esc_html_e ('You are not allowed to start a live stream !','wpstream');
                return;
            }else{
                return;
            }

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
        $access_token   =   $this->wpstream_get_token();

        // do not make the call if no token is available
        if (!$access_token) return false;

        // Request only currently-active channels.
        $curl_post_fields=array(
                'access_token'  =>  $access_token,
                'domain'        =>  $domain['host'],
                'status'        =>  'active'
            );
        // POST to channel/list and decode.
        $curl_response          =   $this->wpstream_baker_do_curl_base($url,$curl_post_fields,true);
        $curl_response_decoded  =   json_decode($curl_response,JSON_OBJECT_AS_ARRAY);



        // Return the channels array on success, false otherwise.
        if( isset($curl_response_decoded['success']) && $curl_response_decoded['success']==true   ){
            return $curl_response_decoded['channels'];
        }else{
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
            set_transient('wpstream_live_stream_for_user_for_shortcode',$result,30);
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
    * AJAX: delete/close an event.
    *
    * Endpoint: wp_ajax_wpstream_close_event. Placeholder — not implemented.
    *
    * @return void
    * @since 3.0.1
    */

    public function wpstream_close_event(){
          //not implemented yet
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
        $access_token   =   $this->wpstream_get_token();

        // do not make the call if no token is available
        if (!$access_token) return array(
            'success'      =>  false,
            'error'        =>  'not_connected',
        );

        // POST to video/upload with just the token; decode the signed form.
        $curl_post_fields=array(
               'access_token'  =>  $access_token,
        );
        $curl_response          =   $this->wpstream_baker_do_curl_base($url,$curl_post_fields,true);
        $curl_response_decoded  =   json_decode($curl_response,JSON_OBJECT_AS_ARRAY);


       // Return the decoded signed form (the exit() below is unreachable).
       return $curl_response_decoded;
       exit();

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
        $access_token   =   $this->wpstream_get_token();

        // do not make the call if no token is available
        if (!$access_token) return false;

        // POST to video/list with just the token.
        $curl_post_fields=array(
               'access_token'  =>  $access_token,
        );

        // Current admin screen (fetched but not used in the request).
        $current_page= get_current_screen();
        $curl_response          =   $this->wpstream_baker_do_curl_base($url,$curl_post_fields,true);
        $curl_response_decoded  =   json_decode($curl_response,JSON_OBJECT_AS_ARRAY);




        // Return full payload on success, empty array on failure.
        if( isset($curl_response_decoded['success']) && $curl_response_decoded['success']==true   ){
            return $curl_response_decoded;
        }else{
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

        // Admin-only guard.
        if( !current_user_can('administrator') ){
            exit('not admin on get_download_link');
        }

        // Target video name from the request.
        $video_name                 =   sanitize_text_field($_POST['video_name']);

        $url            =   'video/download';
        $access_token   =   $this->wpstream_get_token();

        // do not make the call if no token is available
        if (!$access_token) return false;

        // POST to video/download and echo the raw response (a link payload).
        $curl_post_fields=array(
            'access_token'  =>  $access_token,
            'name'          =>  $video_name,
        );
        $curl_response          =   $this->wpstream_baker_do_curl_base($url,$curl_post_fields);
        print $curl_response;
        exit();



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
        // Admin-only guard.
        if( !current_user_can('administrator') ){
            exit('not admin on get_delete_file');
        }

        // Target video name from the request.
        $video_name                 =   esc_html($_POST['video_name']);


        $url            =   'video/delete';
        $access_token   =   $this->wpstream_get_token();

        // do not make the call if no token is available
        if (!$access_token) return false;

        // POST to video/delete; response decoded (into unused var) then echoed raw.
        $curl_post_fields=array(
            'access_token'  =>  $access_token,
            'name'=>$video_name,
        );
        $curl_response          =   $this->wpstream_baker_do_curl_base($url,$curl_post_fields,true);
        $curl_response_decoded  =   json_decode($curl_response,JSON_OBJECT_AS_ARRAY);


        print $curl_response;

        exit();


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
