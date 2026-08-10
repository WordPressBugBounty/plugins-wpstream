<?php
/**
 * Persistent store for WpStream log entries.
 *
 * Wraps a single wp_options row: prepends new WpStream_Log_Entry records,
 * caps the list at a fixed size, reads the full history back, and prunes
 * entries older than 30 days.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes/Logger
 */

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
	 *
	 * @var int
	 */
	private $max_logs = 100;


	/**
	 * Constructor; no initialization is required.
	 */
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
		// Only accept genuine log-entry objects; reject anything else.
		if ( ! ($entry instanceof WpStream_Log_Entry) ) {
			return false;
		}

		// Load the current history so the new entry can be prepended to it.
		$logs = $this->getAll();

		// Flatten the entry object into the plain array shape stored in wp_options.
		$log_data = [
			'timestamp'   => $entry->timestamp,
			'type'        => $entry->type,
			'description' => $entry->description,
		];

		// Newest entry goes to the front of the list.
		array_unshift( $logs, $log_data );

		// Enforce the size cap by trimming the oldest overflow off the tail.
		if ( count( $logs ) > $this->max_logs ) {
			$logs = array_slice( $logs, 0, $this->max_logs );
		}

		// Persist the updated history back to the options table.
		return update_option( $this->option_name, $logs );
	}

	/**
	 * Get all logs
	 *
	 * @return array Array of log entries
	 */
	public function getAll(): array	{
		// Read the stored history, defaulting to an empty array when unset.
		$logs = get_option( $this->option_name, array() );

		// Guard against a corrupted/non-array option value.
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Clear logs older than 30 days
	 *
	 * @return bool True on success, false otherwise
	 */
	public function clear_old_logs(): bool {
		// Start from the full stored history.
		$logs = $this->getAll();

		// Compute the cutoff instant: 30 days before now.
		$one_month_ago = time() - (30 * DAY_IN_SECONDS);
		// Keep only entries whose timestamp is at or after the cutoff.
		$filtered_logs = array_filter( $logs, function( $log ) use ( $one_month_ago ) {
			return $log['timestamp'] >= $one_month_ago;
		});

		// Save the pruned history back to the options table.
		return update_option( $this->option_name, $filtered_logs );
	}
}