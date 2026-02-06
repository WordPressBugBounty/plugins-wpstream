<?php

/**
 * Handles automatic quota updates using WordPress cron
 *
 * @since 4.9
 * @package WpStream
 */

 class WpStream_Quota_Manager {
	 private $main;

	 private $api_connection;

	 /*
	  * Transient key for quota data
	  */
	 const TRANSIENT_KEY = 'wpstream_request_pack_data_per_user_transient';

	 public function __construct( $main ) {
		 $this->main = $main;
		 $this->api_connection = $main->wpstream_live_connection;

		 $this->init_hooks();
	 }

	 private function init_hooks() {
		 // Add admin-only hook
		add_action( 'admin_init', array( $this, 'maybe_update_quota' ) );
	 }

	 public function maybe_update_quota() {
		 if ( !current_user_can( 'manage_options' ) ) {
			 return;
		 }

		 $cached_data = get_transient( self::TRANSIENT_KEY );
		 if ( $cached_data === false ) {
			 $this->update_quota_transient();
		 }
	 }

	 /*
	  * Update quota data transient
	  */
	 public function update_quota_transient() {
		 $quota_data = $this->api_connection->wpstream_request_pack_data_per_user('user_quota_cron_update');

		 if ( $this->is_valid_quota_data( $quota_data ) ) {
			 set_transient( self::TRANSIENT_KEY, $quota_data, 60 );

			 $this->log_quota_update( 'success', 'User quota updated via cron' );
		 } else {
			 $this->log_quota_update( 'error', 'Failed to update user quota via cron' );
		 }
	 }

	 public function is_valid_quota_data( $data ) {
		 return $data &&
				is_array( $data ) &&
				isset( $data['success'] ) &&
				$data['success'] === true;
	 }

	 public function log_quota_update( $status, $message ) {
		 if ( class_exists( 'WpStream_Logger' ) )  {
			 $logger    = new Wpstream_Logger();
			 $log_entry = new WpStream_Log_Entry([
				 'type' => $status,
				 'description' => $message,
				 'timestamp' => current_time( 'timestamp' )
			 ]);
			 $logger->add( $log_entry );
		 }
	 }

	 /*
	  * Force immediate quota update and return fresh data
	  * needed for example when starting a channel
	  */
	 public function force_quota_update() {
		 delete_transient( self::TRANSIENT_KEY );
		 $this->update_quota_transient();
		 return get_transient( self::TRANSIENT_KEY );
	 }

	 public function get_live_quota_data( $context = 'user_quota_on_demand' ) {
		 $cached_data = get_transient( self::TRANSIENT_KEY );

		 if ( $cached_data !== false ) {
			 return $cached_data;
		 }

		 // If transient is empty, fetch fresh data
		 return $this->api_connection->wpstream_request_pack_data_per_user( $context );
	 }
}