<?php
/**
 * Channel Settings Module.
 *
 * Owns resolution of a Channel's saved streaming behavior. WordPress storage is
 * an Adapter at the storage seam so Module tests can use another Adapter.
 *
 * @package Wpstream
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Storage port used by the Channel Settings Module. */
interface Wpstream_Channel_Settings_Storage_Port {
	public function read_defaults();
	public function read_customized( $channel_id );
	public function read_default_snapshot( $channel_id );
	public function read_use_defaults( $channel_id );
	public function write_default_snapshot( $channel_id, $settings );
	public function write_customized( $channel_id, $settings );
	public function write_use_defaults( $channel_id, $use_defaults );
	public function write_defaults( $settings );
}

/** Baker synchronization port used by the Channel Settings Module. */
interface Wpstream_Channel_Settings_Baker_Port {
	public function synchronize( $channel_id, $configuration );
}

/** WordPress option/post-meta Adapter for Channel Settings storage. */
class Wpstream_WordPress_Channel_Settings_Storage implements Wpstream_Channel_Settings_Storage_Port {
	const DEFAULTS_OPTION = 'wpstream_user_streaming_global_channel_options';
	const CUSTOM_META     = 'local_event_options';
	const MODE_META       = 'use_global_event_options';
	const SNAPSHOT_META   = 'wpstream_channel_settings_snapshot';

	public function read_defaults() {
		$settings = get_option( self::DEFAULTS_OPTION, array() );
		return is_array( $settings ) ? $settings : array();
	}

	public function read_customized( $channel_id ) {
		return get_post_meta( (int) $channel_id, self::CUSTOM_META, true );
	}

	public function read_default_snapshot( $channel_id ) {
		return get_post_meta( (int) $channel_id, self::SNAPSHOT_META, true );
	}

	public function read_use_defaults( $channel_id ) {
		return get_post_meta( (int) $channel_id, self::MODE_META, true );
	}

	public function write_default_snapshot( $channel_id, $settings ) {
		return update_post_meta( (int) $channel_id, self::SNAPSHOT_META, $settings );
	}

	public function write_customized( $channel_id, $settings ) {
		return update_post_meta( (int) $channel_id, self::CUSTOM_META, $settings );
	}

	public function write_use_defaults( $channel_id, $use_defaults ) {
		return update_post_meta( (int) $channel_id, self::MODE_META, $use_defaults ? 1 : 0 );
	}

	public function write_defaults( $settings ) {
		return update_option( self::DEFAULTS_OPTION, $settings );
	}
}

/** Production Baker Adapter backed by the authenticated live connection. */
class Wpstream_Baker_Channel_Settings_Adapter implements Wpstream_Channel_Settings_Baker_Port {
	/** @var Wpstream_Live_Api_Connection */
	private $connection;

	public function __construct( $connection ) {
		$this->connection = $connection;
	}

	public function synchronize( $channel_id, $configuration ) {
		return $this->connection->synchronize_channel_settings( $channel_id, $configuration );
	}
}

/**
 * Deep Module for Channel Settings.
 *
 * Its small Interface reads state, applies intent, and prepares Baker config.
 */
class Wpstream_Channel_Settings {
	const CHANNEL_KEYS = array(
		'record',
		'view_count',
		'domain_lock',
		'autoplay',
		'mute',
		'low_latency',
		'adaptive_bitrate',
		'encrypt',
	);

	/** @var Wpstream_Channel_Settings_Storage_Port */
	private $storage;
	/** @var Wpstream_Channel_Settings_Baker_Port|null */
	private $baker;

	public function __construct( Wpstream_Channel_Settings_Storage_Port $storage, Wpstream_Channel_Settings_Baker_Port $baker = null ) {
		$this->storage = $storage;
		$this->baker   = $baker;
	}

	/**
	 * Read the Channel Settings state callers render or consume.
	 *
	 * @param int $channel_id Channel post ID.
	 * @return array<string,mixed>
	 */
	public function read( $channel_id ) {
		$channel_id = (int) $channel_id;
		$defaults   = $this->storage->read_defaults();
		$customized = $this->storage->read_customized( $channel_id );
		$use_defaults = $this->storage->read_use_defaults( $channel_id );

		$is_customized = is_array( $customized ) && empty( $use_defaults );
		if ( $is_customized ) {
			$options = $this->channel_values( $customized );
			$mode    = 'customized';
		} else {
			$snapshot = $this->storage->read_default_snapshot( $channel_id );
			if ( ! is_array( $snapshot ) ) {
				// Legacy Channels have no trustworthy creation-time snapshot; migrate
				// them by copying the current Default Channel Settings exactly once.
				$snapshot = $defaults;
				$snapshot = $this->channel_values( $snapshot );
				$this->storage->write_default_snapshot( $channel_id, $snapshot );
			}
			$options = $this->channel_values( $snapshot );
			$mode    = 'defaults';
		}

		// Session Encryption remains site-wide and is never snapshotted per Channel.
		$options['ses_encrypt'] = isset( $defaults['ses_encrypt'] ) ? (int) $defaults['ses_encrypt'] : 0;

		return array(
			'mode'               => $mode,
			'options'            => $options,
			'defaults'           => $defaults,
			'customized'         => is_array( $customized ) ? $customized : array(),
			'session_encryption' => (bool) $options['ses_encrypt'],
		);
	}

	/**
	 * Whether the site-wide Session Encryption gate is enabled.
	 *
	 * @return bool
	 */
	public function session_encryption_enabled() {
		$defaults = $this->storage->read_defaults();
		return is_array( $defaults )
			&& isset( $defaults['ses_encrypt'] )
			&& 1 === intval( $defaults['ses_encrypt'] );
	}

	/**
	 * Apply one settings change from a compatibility Adapter.
	 *
	 * @param array<string,mixed> $change Change command.
	 * @return array<string,mixed> Result with a stable success/error shape.
	 */
	public function apply( $change ) {
		if ( ! is_array( $change ) ) {
			return array( 'success' => false, 'error' => 'invalid_change' );
		}

		$type       = $change['type'] ?? '';
		if ( 'save_defaults' === $type ) {
			$validation = $this->validate_default_values( $change['options'] ?? array() );
			if ( ! $validation['success'] ) {
				return $validation;
			}
			$previous_defaults = $this->storage->read_defaults();
			$previous_defaults = is_array( $previous_defaults ) ? $previous_defaults : array();
			$stored = $this->storage->write_defaults( $validation['values'] );
			if ( false === $stored && $validation['values'] !== $this->storage->read_defaults() ) {
				return array( 'success' => false, 'error' => 'storage_failed' );
			}

			/**
			 * Fires after the site's Default Channel Settings were saved.
			 *
			 * @since 4.14.0
			 *
			 * @param array $new      Validated settings just stored.
			 * @param array $previous Settings they replaced, normalised to the same
			 *                        keys as $new so the two maps diff key by key.
			 * @param int   $user_id  User who saved them.
			 */
			do_action( 'wpstream_default_channel_settings_saved', $validation['values'], array_merge( $this->channel_values( $previous_defaults ), array( 'ses_encrypt' => isset( $previous_defaults['ses_encrypt'] ) ? (int) $previous_defaults['ses_encrypt'] : 0 ) ), get_current_user_id() );

			return array( 'success' => true, 'defaults' => $validation['values'] );
		}

		$channel_id = isset( $change['channel_id'] ) ? (int) $change['channel_id'] : 0;
		if ( 'initialize' === $type ) {
			/**
			 * Filter the settings a newly created channel starts with.
			 *
			 * The result is normalised to the per-channel catalog (unknown keys
			 * are dropped, values cast to 0/1), so a callback can only set
			 * known settings.
			 *
			 * @since 4.14.0
			 *
			 * @param array $defaults   The site's Default Channel Settings.
			 * @param int   $channel_id Live Channel post ID.
			 */
			$defaults = $this->channel_values( apply_filters( 'wpstream_channel_default_settings', $this->channel_values( $this->storage->read_defaults() ), $channel_id ) );
			$stored   = true;
			if ( ! is_array( $this->storage->read_customized( $channel_id ) ) ) {
				$stored = false !== $this->storage->write_customized( $channel_id, $defaults ) && $stored;
			}
			if ( ! is_array( $this->storage->read_default_snapshot( $channel_id ) ) ) {
				$stored = false !== $this->storage->write_default_snapshot( $channel_id, $defaults ) && $stored;
			}
			$stored = false !== $this->storage->write_use_defaults( $channel_id, true ) && $stored;
			if ( ! $stored ) {
				return array( 'success' => false, 'error' => 'storage_failed' );
			}
			return array( 'success' => true, 'state' => $this->read( $channel_id ) );
		}

		if ( 'select_mode' === $type ) {
			$mode = $change['mode'] ?? '';
			if ( ! in_array( $mode, array( 'defaults', 'customized' ), true ) ) {
				return array( 'success' => false, 'error' => 'invalid_mode' );
			}
			if ( 'customized' === $mode && ! is_array( $this->storage->read_customized( $channel_id ) ) ) {
				$baseline = $this->read( $channel_id );
				$this->storage->write_customized( $channel_id, $this->channel_values( $baseline['options'] ) );
			}
			$use_defaults = 'defaults' === $mode;
			$stored       = $this->storage->write_use_defaults( $channel_id, $use_defaults );
			if ( false === $stored && intval( $use_defaults ) !== intval( $this->storage->read_use_defaults( $channel_id ) ) ) {
				return array( 'success' => false, 'error' => 'storage_failed' );
			}
			return $this->synchronize_after_save( $channel_id );
		}

		if ( 'save_customized' !== $type ) {
			return array( 'success' => false, 'error' => 'invalid_change' );
		}

		$validation = $this->validate_channel_values( $change['options'] ?? array() );
		if ( ! $validation['success'] ) {
			return $validation;
		}
		$submitted  = $validation['values'];
		$previous   = $this->channel_values( $this->storage->read_customized( $channel_id ) );

		$speed_newly_enabled =
			( 1 !== $previous['low_latency'] && 1 === $submitted['low_latency'] )
			|| ( 1 !== $previous['adaptive_bitrate'] && 1 === $submitted['adaptive_bitrate'] );
		$encryption_newly_enabled = 1 !== $previous['encrypt'] && 1 === $submitted['encrypt'];

		if ( $speed_newly_enabled && $encryption_newly_enabled ) {
			return array( 'success' => false, 'error' => 'ambiguous_settings_intent' );
		}

		if ( $speed_newly_enabled ) {
			$submitted['encrypt'] = 0;
		} elseif ( $encryption_newly_enabled ) {
			$submitted['low_latency']      = 0;
			$submitted['adaptive_bitrate'] = 0;
		}

		$stored = $this->storage->write_customized( $channel_id, $submitted );
		if ( false === $stored && $submitted !== $this->storage->read_customized( $channel_id ) ) {
			return array( 'success' => false, 'error' => 'storage_failed' );
		}

		/**
		 * Fires after a channel's own (customized) settings were saved, before
		 * they are pushed to Baker.
		 *
		 * @since 4.14.0
		 *
		 * @param int   $channel_id Live Channel post ID.
		 * @param array $new        Settings just stored (full map).
		 * @param array $previous   The channel's settings before this save.
		 */
		do_action( 'wpstream_channel_settings_saved', $channel_id, $submitted, $previous );

		return $this->synchronize_after_save( $channel_id );
	}

	/**
	 * Translate the resolved Channel Settings into Baker's flag conventions.
	 *
	 * @param int $channel_id Channel post ID.
	 * @return array<string,mixed>
	 */
	public function prepare_baker( $channel_id ) {
		$state   = $this->read( $channel_id );
		$options = $state['options'];

		$low_latency = 1 === $options['low_latency'];
		$abr_enabled = 1 === $options['adaptive_bitrate'];
		$encrypted   = 1 === $options['encrypt'];
		if ( $low_latency || $abr_enabled ) {
			$encrypted = false;
		} elseif ( $encrypted ) {
			$low_latency = false;
			$abr_enabled = false;
		}

		// Scheme and host both come from the site URL: is_ssl() only describes
		// the current request, so a sync from wp-cli, cron, or a request behind
		// an SSL-terminating proxy would otherwise pin the lock to http:// on
		// an https site — an origin no real viewer has.
		$site   = wp_parse_url( get_site_url() );
		$host   = isset( $site['host'] ) ? $site['host'] : '';
		$origin = ( isset( $site['scheme'] ) ? $site['scheme'] : 'http' ) . '://' . $host;

		// An enabled domain lock pins playback to this site and is not filterable;
		// only an unlocked channel's origin ('*') may be narrowed by an integration.
		$domain_locked = 1 === $options['domain_lock'];
		$allowed       = $origin;
		if ( ! $domain_locked ) {
			/**
			 * Filter the origin an unlocked channel may be played from.
			 *
			 * Not applied when the channel's domain lock is enabled — that
			 * setting always sends this site's origin.
			 *
			 * @since 4.14.0
			 *
			 * @param string $origin     `*` (any origin) or `scheme://host`.
			 * @param int    $channel_id Live Channel post ID.
			 */
			$allowed = (string) apply_filters( 'wpstream_channel_allowed_origin', '*', (int) $channel_id );
		}

		/**
		 * Filter the channel configuration sent to Baker.
		 *
		 * `allow_access_from` is restored to this site's origin after the
		 * filter when the channel's domain lock is enabled.
		 *
		 * @since 4.14.0
		 *
		 * @param array $configuration allow_access_from, record, encrypt, low_latency, abr, autostart, options.
		 * @param int   $channel_id    Live Channel post ID.
		 */
		$configuration = (array) apply_filters(
			'wpstream_channel_baker_configuration',
			array(
				'allow_access_from' => $allowed,
				'record'            => 1 === $options['record'] ? 'true' : 'false',
				'encrypt'           => $encrypted,
				'low_latency'       => $low_latency,
				'abr'               => $abr_enabled ? 'common' : 'none',
				'autostart'         => 'true',
				'options'           => $options,
			),
			(int) $channel_id
		);
		if ( $domain_locked ) {
			$configuration['allow_access_from'] = $origin;
		}

		return $configuration;
	}

	/** Keep only the per-Channel catalog and normalize its values to integers. */
	private function channel_values( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$values   = array();
		foreach ( self::CHANNEL_KEYS as $key ) {
			$values[ $key ] = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;
		}
		return $values;
	}

	/** Validate a submitted full map without mutating storage. */
	private function validate_channel_values( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array( 'success' => false, 'error' => 'invalid_value' );
		}

		$values = array();
		foreach ( self::CHANNEL_KEYS as $key ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$values[ $key ] = 0;
				continue;
			}

			$value = $settings[ $key ];
			if ( true === $value || 1 === $value || '1' === $value ) {
				$values[ $key ] = 1;
			} elseif ( false === $value || 0 === $value || '0' === $value ) {
				$values[ $key ] = 0;
			} else {
				return array( 'success' => false, 'error' => 'invalid_value', 'field' => $key );
			}
		}

		return array( 'success' => true, 'values' => $values );
	}

	/** Validate and merge a partial Default Channel Settings map. */
	private function validate_default_values( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array( 'success' => false, 'error' => 'invalid_value' );
		}

		$existing = $this->storage->read_defaults();
		$values   = $this->channel_values( $existing );
		$values['ses_encrypt'] = isset( $existing['ses_encrypt'] ) ? (int) $existing['ses_encrypt'] : 0;
		$allowed = array_merge( self::CHANNEL_KEYS, array( 'ses_encrypt' ) );

		foreach ( $settings as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}
			if ( true === $value || 1 === $value || '1' === $value ) {
				$values[ $key ] = 1;
			} elseif ( false === $value || 0 === $value || '0' === $value ) {
				$values[ $key ] = 0;
			} else {
				return array( 'success' => false, 'error' => 'invalid_value', 'field' => $key );
			}
		}

		$previous = $this->channel_values( $existing );
		$speed_newly_enabled =
			( 1 !== $previous['low_latency'] && 1 === $values['low_latency'] )
			|| ( 1 !== $previous['adaptive_bitrate'] && 1 === $values['adaptive_bitrate'] );
		$encryption_newly_enabled = 1 !== $previous['encrypt'] && 1 === $values['encrypt'];
		if ( $speed_newly_enabled && $encryption_newly_enabled ) {
			return array( 'success' => false, 'error' => 'ambiguous_settings_intent' );
		}
		if ( $speed_newly_enabled ) {
			$values['encrypt'] = 0;
		} elseif ( $encryption_newly_enabled ) {
			$values['low_latency']      = 0;
			$values['adaptive_bitrate'] = 0;
		}

		return array( 'success' => true, 'values' => $values );
	}

	/** Synchronize a locally saved change without exposing Baker conventions to callers. */
	private function synchronize_after_save( $channel_id ) {
		$state = $this->read( $channel_id );
		if ( null === $this->baker ) {
			return array( 'success' => true, 'saved' => true, 'baker_success' => null, 'baker_result' => null, 'state' => $state );
		}

		$result = $this->baker->synchronize( $channel_id, $this->prepare_baker( $channel_id ) );
		if ( is_array( $result ) && ! empty( $result['success'] ) ) {
			return array( 'success' => true, 'saved' => true, 'baker_success' => true, 'baker_result' => $result, 'state' => $state );
		}

		return array( 'success' => true, 'saved' => true, 'baker_success' => false, 'baker_result' => $result, 'error' => 'baker_rejected', 'state' => $state );
	}
}
