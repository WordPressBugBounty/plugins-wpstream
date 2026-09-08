<?php

/**
 * Handles automatic quota updates using WordPress cron.
 *
 * Keeps a short-lived transient cache of the current user's streaming quota
 * ("request pack" data) fresh so the rest of the plugin can read plan limits
 * without hitting the WpStream cloud API on every page load. This class is the
 * single owner of that cache — key, TTL, validation, and invalidation all live
 * here; the quota service only fetches. The cache is refreshed opportunistically
 * on admin page loads, can be force-refreshed when a channel is about to start,
 * and helper predicates interpret the quota payload (data-vs-hours plans,
 * storage availability, VOD eligibility). A failed fetch starts a short
 * cool-down during which reads report "no data" instead of retrying the
 * blocking API call, so a slow or down service cannot stall admin pages.
 *
 * @since 4.9
 * @package WpStream
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

 /**
  * Quota cache manager and plan-limit interpreter.
  */
 class WpStream_Quota_Manager {
	 /** @var Wpstream Main plugin instance, used to reach shared services. */
	 private $main;

	 /** @var Wpstream_User_Quota_Service Fetch-only client for the user/quota endpoint. */
	 private $quota_service;

	 /*
	  * Transient key under which the per-user quota payload is cached.
	  * This class is the only writer/deleter of this transient.
	  */
	 const TRANSIENT_KEY = 'wpstream_request_pack_data_per_user_transient';

	 /** @var int Default cache lifetime in seconds; see `wpstream_quota_cache_ttl`. */
	 const CACHE_TTL = 60;

	 /** @var int Upper bound for either filtered lifetime: stale quota past an hour would hide an empty pack. */
	 const MAX_TTL = HOUR_IN_SECONDS;

	 /*
	  * Transient set when a fetch fails, so admin page loads stop retrying the
	  * blocking API call while the service is down. Cleared by invalidate()
	  * (and therefore bypassed by force_quota_update()).
	  */
	 const FAILURE_KEY = 'wpstream_request_pack_data_fetch_failed';

	 /** @var int Default cool-down after a failed fetch, in seconds; see `wpstream_quota_failure_cooldown`. */
	 const FAILURE_TTL = 5 * MINUTE_IN_SECONDS;

	 /**
	  * Wire up dependencies and register hooks.
	  *
	  * @param Wpstream $main Main plugin instance (service locator).
	  */
	 public function __construct( $main ) {
		 // Keep a reference to the main plugin object.
		 $this->main = $main;
		 // Grab the fetch-only quota service off the main object for later calls.
		 $this->quota_service = $main->user_quota_service;

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
		 // Transient absent/expired: fetch and cache fresh quota data — unless a
		 // recent fetch already failed; then skip the blocking call until the
		 // cool-down expires so a slow/down API cannot stall every admin page.
		 if ( $cached_data === false && false === get_transient( self::FAILURE_KEY ) ) {
			 $this->update_quota_transient();
		 }
	 }

	 /*
	  * Fetch quota data from the API and store it in the transient cache.
	  *
	  * @param string $context Label passed to the API to identify the caller.
	  * @return array|false The freshly cached payload, or false when the fetch failed.
	  */
	 public function update_quota_transient( $context = 'user_quota_cron_update' ) {
		 // Ask the fetch-only quota service for this user's current pack/quota figures.
		 $quota_data = $this->quota_service->request_pack_data_per_user( $context );

		 // Only cache a well-formed, successful response.
		 if ( $this->is_valid_quota_data( $quota_data ) ) {
			 /**
			  * Filter how long a fetched quota snapshot is cached, in seconds.
			  *
			  * Clamped to [1, 3600]. The transient name and payload are frozen.
			  *
			  * @since 4.14.0
			  *
			  * @param int $seconds Lifetime; default 60.
			  */
			 $ttl = wpstream_tunable( 'wpstream_quota_cache_ttl', self::CACHE_TTL, self::MAX_TTL );
			 set_transient( self::TRANSIENT_KEY, $quota_data, $ttl );
			 // A success ends any failure cool-down immediately.
			 delete_transient( self::FAILURE_KEY );

			 // Record the successful refresh in the plugin log.
			 $this->log_quota_update( 'success', 'User quota updated (' . $context . ')' );

			 /**
			  * Fires after fresh quota data was fetched and cached. Listeners
			  * compare consecutive snapshots to notice a pack running out.
			  *
			  * @since 4.14.0
			  *
			  * @param array  $quota_data Decoded quota payload as cached.
			  * @param string $context    Label of the caller that triggered the refresh.
			  */
			 do_action( 'wpstream_quota_refreshed', $quota_data, $context );

			 return $quota_data;
		 }

		 // Remember the failure so admin page loads stop retrying for a while.
		 /**
		  * Filter how long admin page loads stop retrying after a failed quota
		  * fetch, in seconds. Clamped to [1, 3600].
		  *
		  * @since 4.14.0
		  *
		  * @param int $seconds Cool-down; default 300.
		  */
		 $cooldown = wpstream_tunable( 'wpstream_quota_failure_cooldown', self::FAILURE_TTL, self::MAX_TTL );
		 set_transient( self::FAILURE_KEY, 1, $cooldown );

		 // Log the failure but leave any prior cache untouched.
		 $this->log_quota_update( 'error', 'Failed to update user quota (' . $context . ')' );

		 /**
		  * Fires when a quota refresh failed and the retry cool-down started;
		  * any previously cached payload is kept.
		  *
		  * @since 4.14.0
		  *
		  * @param string $context Label of the caller that triggered the refresh.
		  */
		 do_action( 'wpstream_quota_refresh_failed', $context );

		 return false;
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

	 /**
	  * Drop the cached quota payload so the next read fetches fresh data.
	  * The one sanctioned way to erase the cache from outside this class
	  * (e.g. when the account credentials change).
	  */
	 public function invalidate() {
		 delete_transient( self::TRANSIENT_KEY );
		 // Also forget any failure cool-down: an explicit reset means the next
		 // read (e.g. a channel about to start) must genuinely retry the API.
		 delete_transient( self::FAILURE_KEY );
	 }

	 /*
	  * Force immediate quota update and return fresh data
	  * needed for example when starting a channel
	  */
	 public function force_quota_update() {
		 // Drop the cache so the next read cannot serve stale data.
		 $this->invalidate();
		 // Repopulate the cache from the API and return the result
		 // (false if the refresh failed).
		 return $this->update_quota_transient();
	 }

	 /**
	  * Return quota data, preferring the cache and falling back to a live call.
	  *
	  * @param string $context Label passed to the API to identify the caller.
	  * @return array|false Cached payload, or a freshly fetched-and-cached one; false on failure.
	  */
	 public function get_live_quota_data( $context = 'user_quota_on_demand' ) {
		 // Serve the cached payload when present.
		 $cached_data = get_transient( self::TRANSIENT_KEY );

		 if ( $cached_data !== false ) {
			 return $cached_data;
		 }

		 // Cache miss during a failure cool-down: report "no data" without
		 // another blocking call. force_quota_update() clears the cool-down
		 // first, so an explicit refresh still reaches the API.
		 if ( false !== get_transient( self::FAILURE_KEY ) ) {
			 return false;
		 }

		 // Cache miss: fetch, cache, and return through the single refresh path.
		 return $this->update_quota_transient( $context );
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