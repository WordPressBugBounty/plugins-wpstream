<?php
/**
 * Value object for a single WpStream log record.
 *
 * A lightweight data holder describing one entry (timestamp, type, description)
 * that the logger stores in and reads back from the wp_options table.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes/Helpers
 */

/**
 * Class WpStream_Log_Entry
 *
 * Represents a single log entry in the WpStream logging system.
 */
class WpStream_Log_Entry {
	/**
	 * The timestamp of the log entry
	 *
	 * @var int
	 */
	public $timestamp;

	/**
	 * The type of the log entry
	 *
	 * @var string
	 */
	public $type;

	/*
	 * The description of the log entry
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Constructor of the log entry
	 *
	 * @param array|null $item Log data
	 *
	 * @return Wpstream_Log_Entry
	 */

	public function __construct( $item = null ) {
		// Hydrate from an existing stored record when one is supplied.
		if ( $item ) {
			// Use the stored timestamp when present, otherwise stamp with the current time.
			$this->timestamp   = isset( $item['timestamp'] ) ? (int) $item['timestamp'] : time();
			// Type is optional; fall back to an empty string when missing.
			$this->type        = isset( $item['type'] ) ? $item['type'] : '';
			// Description is taken as-is from the source array.
			$this->description = $item['description'];
		} else {
			// No source data: build a fresh, empty entry stamped with the current time.
			$this->timestamp   = time();
			$this->type        = '';
			$this->description = '';
		}
	}
}