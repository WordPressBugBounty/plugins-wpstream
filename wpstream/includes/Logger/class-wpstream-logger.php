<?php

/**
 * Class WpStream_Log_Entry
 *
 * Represents a single log entry in the WpStream logging system.
 */
class WpStream_Logger {
	/**
	 * The option name where the logs are stored in wp_options
	 *
	 * @var string
	 */
	private $option_name = 'wpstream_logs';

	/**
	 * Maximum number of logs to store
	 */
	private $max_logs = 100;


	public function __construct() {
	}

	/**
	 * Add a log entry to the logs
	 *
	 * @param WpStream_Log_Entry $entry Log entry object
	 *
	 * @return bool True on success, false on failure
	 */
	public function add( $entry ): bool	{
		if ( ! ($entry instanceof WpStream_Log_Entry) ) {
			return false;
		}

		$logs = $this->getAll();

		$log_data = [
			'timestamp'   => $entry->timestamp,
			'type'        => $entry->type,
			'description' => $entry->description,
		];

		array_unshift( $logs, $log_data );

		if ( count( $logs ) > $this->max_logs ) {
			$logs = array_slice( $logs, 0, $this->max_logs );
		}

		return update_option( $this->option_name, $logs );
	}

	/**
	 * Get all logs
	 *
	 * @return array Array of log entries
	 */
	public function getAll(): array	{
		$logs = get_option( $this->option_name, array() );

		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Clear logs older than 30 days
	 *
	 * @return bool True on success, false otherwise
	 */
	public function clear_old_logs(): bool {
		$logs = $this->getAll();

		$one_month_ago = time() - (30 * DAY_IN_SECONDS);
		$filtered_logs = array_filter( $logs, function( $log ) use ( $one_month_ago ) {
			return $log['timestamp'] >= $one_month_ago;
		});

		return update_option( $this->option_name, $filtered_logs );
	}
}