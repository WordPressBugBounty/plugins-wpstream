<?php

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
		if ( $item ) {
			$this->timestamp   = isset( $item['timestamp'] ) ? (int) $item['timestamp'] : time();
			$this->type        = isset( $item['type'] ) ? $item['type'] : '';
			$this->description = $item['description'];
		} else {
			$this->timestamp   = time();
			$this->type        = '';
			$this->description = '';
		}
	}
}