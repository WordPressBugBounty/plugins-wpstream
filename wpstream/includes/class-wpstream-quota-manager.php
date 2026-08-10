<?php

/**
 * Handles automatic quota updates using WordPress cron.
 *
 * Keeps a short-lived transient cache of the current user's streaming quota
 * ("request pack" data) fresh so the rest of the plugin can read plan limits
 * without hitting the WpStream cloud API on every page load. The cache is
 * refreshed opportunistically on admin page loads, can be force-refreshed when
 * a channel is about to start, and exposes helper predicates that interpret the
 * quota payload (data-vs-hours plans, storage availability, VOD eligibility).
 *
 * @since 4.9
 * @package WpStream
 */

 /**
  * Quota cache manager and plan-limit interpreter.
  */
 class WpStream_Quota_Manager {
	 /** @var Wpstream Main plugin instance, used to reach shared services. */
	 private $main;

	 /** @var Wpstream_Live_Api_Connection Cloud API client used to fetch quota data. */
	 private $api_connection;

	 /*
	  * Transient key under which the per-user quota payload is cached.
	  */
	 const TRANSIENT_KEY = 'wpstream_request_pack_data_per_user_transient';

	 /**
	  * Wire up dependencies and register hooks.
	  *
	  * @param Wpstream $main Main plugin instance (service locator).
	  */
	 public function __construct( $main ) {
		 // Keep a reference to the main plugin object.
		 $this->main = $main;
		 // Grab the live API connection off the main object for later calls.
		 $this->api_connection = $main->wpstream_live_connection;

		 // Register the WordPress hooks this manager needs.
		 $this->init_hooks();
	 }

	 /**
	  * Register the admin-side hook that refreshes the quota cache.
	  */
	 private function init_hooks() {
		 // Add admin-only hook
		add_action( 'admin_init', array( $this, 'maybe_update_quota' ) );
	 }

	 /**
	  * On admin page loads, refresh the quota cache if it has expired.
	  */
	 public function maybe_update_quota() {
		 // Only site managers trigger the (rate-limited) refresh.
		 if ( !current_user_can( 'manage_options' ) ) {
			 return;
		 }

		 // Look for an existing cached payload.
		 $cached_data = get_transient( self::TRANSIENT_KEY );
		 // Transient absent/expired: fetch and cache fresh quota data.
		 if ( $cached_data === false ) {
			 $this->update_quota_transient();
		 }
	 }

	 /*
	  * Fetch quota data from the API and store it in the transient cache.
	  */
	 public function update_quota_transient() {
		 // Ask the cloud API for this user's current pack/quota figures.
		 $quota_data = $this->api_connection->wpstream_request_pack_data_per_user('user_quota_cron_update');

		 // Only cache a well-formed, successful response.
		 if ( $this->is_valid_quota_data( $quota_data ) ) {
			 // Cache the payload for 60 seconds.
			 set_transient( self::TRANSIENT_KEY, $quota_data, 60 );

			 // Record the successful refresh in the plugin log.
			 $this->log_quota_update( 'success', 'User quota updated via cron' );
		 } else {
			 // Log the failure but leave any prior cache untouched.
			 $this->log_quota_update( 'error', 'Failed to update user quota via cron' );
		 }
	 }

	 /**
	  * Validate a quota API payload.
	  *
	  * @param mixed $data Response returned by the API.
	  * @return bool True only for a non-empty array reporting success === true.
	  */
	 public function is_valid_quota_data( $data ) {
		 return $data &&                          // not null/false/empty
				is_array( $data ) &&              // is an array
				isset( $data['success'] ) &&      // has a success flag
				$data['success'] === true;        // and it is explicitly true
	 }

	 /**
	  * Append a quota-update outcome to the plugin log, if the logger exists.
	  *
	  * @param string $status  Log entry type, e.g. 'success' or 'error'.
	  * @param string $message Human-readable description of the event.
	  */
	 public function log_quota_update( $status, $message ) {
		 // The logger is optional; only log when the class is available.
		 if ( class_exists( 'WpStream_Logger' ) )  {
			 // Build a logger and a single log entry describing this event.
			 $logger    = new Wpstream_Logger();
			 $log_entry = new WpStream_Log_Entry([
				 'type' => $status,
				 'description' => $message,
				 'timestamp' => current_time( 'timestamp' )
			 ]);
			 // Persist the entry.
			 $logger->add( $log_entry );
		 }
	 }

	 /*
	  * Force immediate quota update and return fresh data
	  * needed for example when starting a channel
	  */
	 public function force_quota_update() {
		 // Drop the cache so the next read cannot serve stale data.
		 delete_transient( self::TRANSIENT_KEY );
		 // Repopulate the cache from the API.
		 $this->update_quota_transient();
		 // Return whatever was just cached (false if the refresh failed).
		 return get_transient( self::TRANSIENT_KEY );
	 }

	 /**
	  * Return quota data, preferring the cache and falling back to a live call.
	  *
	  * @param string $context Label passed to the API to identify the caller.
	  * @return array|mixed Cached payload, or a fresh API response when uncached.
	  */
	 public function get_live_quota_data( $context = 'user_quota_on_demand' ) {
		 // Serve the cached payload when present.
		 $cached_data = get_transient( self::TRANSIENT_KEY );

		 if ( $cached_data !== false ) {
			 return $cached_data;
		 }

		 // If transient is empty, fetch fresh data
		 return $this->api_connection->wpstream_request_pack_data_per_user( $context );
	 }

	 /**
	  * Read cached quota only — never calls the API.
	  *
	  * @return array|false
	  */
	 public function get_cached_quota_data() {
		 // Read the cached payload without ever contacting the API.
		 $cached_data = get_transient( self::TRANSIENT_KEY );

		 // Return it only if it is a valid, successful payload.
		 if ( $this->is_valid_quota_data( $cached_data ) ) {
			 return $cached_data;
		 }

		 // Missing or malformed cache -> report "no data".
		 return false;
	 }

	 /**
	  * Start-streaming UI flags from cache only (for script localization).
	  * Avoids user/quota API calls on every page load; channel start still
	  * validates via get_live_quota_data() when the user turns a channel on.
	  *
	  * @return array{is_basic_streaming: bool, use_streaming_hours: bool}
	  */
	 public function get_streaming_ui_flags_from_cache() {
		 // Pull the cached quota payload (no API call).
		 $pack_details = $this->get_cached_quota_data();

		 // With no usable cache, default both flags to false.
		 if ( ! is_array( $pack_details ) ) {
			 return array(
				 'is_basic_streaming'  => false,
				 'use_streaming_hours' => false,
			 );
		 }

		 // Derive the two UI flags from the cached plan details.
		 return array(
			 'is_basic_streaming'  => $this->is_basic_streaming_mode( $pack_details ),
			 'use_streaming_hours' => $this->uses_streaming_hours( $pack_details ),
		 );
	 }

	 /**
	  * Whether this plan is metered in streaming hours (vs. data megabytes).
	  *
	  * @param mixed $pack_details Quota payload.
	  * @return bool True when the payload carries a truthy use_streaming_hours flag.
	  */
	 public function uses_streaming_hours( $pack_details ) {
		 return is_array( $pack_details )                       // must be an array
			 && isset( $pack_details['use_streaming_hours'] )   // with the flag present
			 && $pack_details['use_streaming_hours'];           // and truthy
	 }

	 /**
	  * Whether the account has run out of streaming allowance ("basic" mode).
	  *
	  * @param array|null $pack_details Quota payload; fetched live when null.
	  * @param string     $context      Label passed to the API on a live fetch.
	  * @return bool True when the relevant allowance is depleted.
	  */
	 public function is_basic_streaming_mode( $pack_details = null, $context = 'is_basic_streaming_mode' ) {
		 // Fetch quota data on demand when the caller did not supply it.
		 if ( null === $pack_details ) {
			 $pack_details = $this->get_live_quota_data( $context );
		 }

		 // No usable data -> treat as not-basic (fail open).
		 if ( ! is_array( $pack_details ) ) {
			 return false;
		 }

		 // Hours-based plans are "basic" when broadcast OR viewer hours hit zero.
		 if ( $this->uses_streaming_hours( $pack_details ) ) {
			 return ( isset( $pack_details['available_broadcast_hours'] ) &&
				  $pack_details['available_broadcast_hours'] <= 0 ) ||      // out of broadcast hours
				( isset( $pack_details['available_viewer_hours'] ) &&
				  $pack_details['available_viewer_hours'] <= 0 );           // or out of viewer hours
		 }

		 // Data-based plans are "basic" once available data megabytes hit zero.
		 return isset( $pack_details['available_data_mb'] )
			 && $pack_details['available_data_mb'] <= 0;
	 }

	 /**
	  * Whether the account still has storage allowance for recordings/VOD.
	  *
	  * @param array|null $pack_details Quota payload; fetched live when null.
	  * @param string     $context      Label passed to the API on a live fetch.
	  * @return bool True when storage remains (defaults to true when unknown).
	  */
	 public function has_storage_quota( $pack_details = null, $context = 'has_storage_quota' ) {
		 // Fetch quota data on demand when not provided.
		 if ( null === $pack_details ) {
			 $pack_details = $this->get_live_quota_data( $context );
		 }

		 // No usable data -> no storage.
		 if ( ! is_array( $pack_details ) ) {
			 return false;
		 }

		 // Hours-based plans track storage in hours.
		 if ( $this->uses_streaming_hours( $pack_details ) ) {
			 // Missing field -> assume storage is available.
			 if ( ! isset( $pack_details['available_storage_hours'] ) ) {
				 return true;
			 }

			 // Otherwise storage exists only when the figure is positive.
			 return $pack_details['available_storage_hours'] > 0;
		 }

		 // Data-based plans expose storage in megabytes.
		 if ( isset( $pack_details['available_storage_mb'] ) ) {
			 return $pack_details['available_storage_mb'] > 0;
		 }

		 // Fallback legacy field for storage.
		 if ( isset( $pack_details['available_storage'] ) ) {
			 return $pack_details['available_storage'] > 0;
		 }

		 // No storage field at all -> assume storage is available.
		 return true;
	 }

	 /**
	  * Whether the account may stream/serve VOD given remaining allowance.
	  *
	  * @param array|null $pack_details Quota payload; fetched live when null.
	  * @param string     $context      Label passed to the API on a live fetch.
	  * @return bool True when viewer hours or data remain.
	  */
	 public function can_stream_vod( $pack_details = null, $context = 'can_stream_vod' ) {
		 // Fetch quota data on demand when not provided.
		 if ( null === $pack_details ) {
			 $pack_details = $this->get_live_quota_data( $context );
		 }

		 // No usable data -> cannot stream VOD.
		 if ( ! is_array( $pack_details ) ) {
			 return false;
		 }

		 // Hours-based plans require positive viewer hours.
		 if ( $this->uses_streaming_hours( $pack_details ) ) {
			 return ( isset( $pack_details['available_viewer_hours'] ) &&
				  $pack_details['available_viewer_hours'] > 0 );
		 }

		 // Data-based plans require positive available data.
		 if ( isset( $pack_details['available_data_mb'] ) ) {
			 return $pack_details['available_data_mb'] > 0;
		 }

		 // Neither metric present -> not allowed.
		 return false;
	 }
}