<?php

/**
 * One-time migration of legacy WpStream storage names.
 *
 * @package Wpstream
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Moves plugin-owned state behind the wpstream_ namespace.
 */
final class Wpstream_Storage_Migration {

	/** Migration marker option. */
	const MIGRATION_OPTION = 'wpstream_storage_names_migration_31';

	/**
	 * Migrate legacy state once per site.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		if ( get_option( self::MIGRATION_OPTION, false ) ) {
			return;
		}

		$current_token_migrated = self::migrate_option( 'wp_estate_curent_token', 'wpstream_current_token' );
		$token_expiry_migrated = self::migrate_option( 'wp_estate_token_expire', 'wpstream_token_expire' );
		$transients_migrated = self::migrate_transients();
		$user_meta_migrated = self::migrate_buddyboss_user_meta();

		if ( $current_token_migrated && $token_expiry_migrated && $transients_migrated && $user_meta_migrated ) {
			update_option( self::MIGRATION_OPTION, 1, false );
		}
	}

	/**
	 * Move the misspelled BuddyBoss selection key for every affected user.
	 *
	 * @return bool Whether every affected user was migrated safely.
	 */
	private static function migrate_buddyboss_user_meta() {
		global $wpdb;

		$legacy_name = 'budyboss_selected_channel';
		$new_name = 'wpstream_buddyboss_selected_channel';
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
				$legacy_name
			)
		);
		if ( null === $user_ids ) {
			return false;
		}

		$success = true;
		foreach ( $user_ids as $user_id ) {
			$user_id = (int) $user_id;
			if ( ! metadata_exists( 'user', $user_id, $new_name ) ) {
				if ( false === update_user_meta( $user_id, $new_name, get_user_meta( $user_id, $legacy_name, true ) ) ) {
					$success = false;
					continue;
				}
			}
			delete_user_meta( $user_id, $legacy_name );
			if ( metadata_exists( 'user', $user_id, $legacy_name ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Move dynamic transient families while retaining their remaining lifetime.
	 *
	 * @return bool Whether every transient was copied or safely discarded.
	 */
	private static function migrate_transients() {
		global $wpdb;
		$success = true;

		$prefixes = array(
			'free_event_streamName_'       => 'wpstream_free_event_streamName_',
			'paid_event_streamName_'       => 'wpstream_paid_event_streamName_',
			'vod_decryption_key_index_'    => 'wpstream_vod_decryption_key_index_',
			'paid_vod_key_index_'          => 'wpstream_paid_vod_key_index_',
		);

		foreach ( $prefixes as $legacy_prefix => $new_prefix ) {
			$legacy_option_prefix = '_transient_' . $legacy_prefix;
			$legacy_option_names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( $legacy_option_prefix ) . '%'
				)
			);

			if ( null === $legacy_option_names ) {
				$success = false;
				continue;
			}

			foreach ( $legacy_option_names as $legacy_option_name ) {
				$suffix = substr( $legacy_option_name, strlen( $legacy_option_prefix ) );
				$legacy_transient = $legacy_prefix . $suffix;
				$new_transient = $new_prefix . $suffix;
				$missing = new stdClass();
				$legacy_value = get_option( $legacy_option_name, $missing );

				if ( $missing === $legacy_value ) {
					continue;
				}

				$new_option_name = '_transient_' . $new_transient;
				if ( $missing === get_option( $new_option_name, $missing ) ) {
					$timeout = (int) get_option( '_transient_timeout_' . $legacy_transient, 0 );
					$expiration = $timeout > 0 ? $timeout - time() : 0;

					if ( 0 === $timeout || $expiration > 0 ) {
						if ( ! set_transient( $new_transient, $legacy_value, max( 0, $expiration ) ) ) {
							$success = false;
							continue;
						}
					}
				}

				delete_transient( $legacy_transient );
				$legacy_still_present = new stdClass();
				if ( $legacy_still_present !== get_option( $legacy_option_name, $legacy_still_present ) ) {
					$success = false;
				}
			}
		}

		return $success;
	}

	/**
	 * Copy a legacy option only when the destination is absent, then clean up.
	 *
	 * @param string $legacy_name Legacy option name.
	 * @param string $new_name    Namespaced option name.
	 * @return bool Whether the legacy value is absent or was migrated safely.
	 */
	private static function migrate_option( $legacy_name, $new_name ) {
		$missing = new stdClass();
		$legacy_value = get_option( $legacy_name, $missing );

		if ( $missing === $legacy_value ) {
			return true;
		}

		if ( $missing === get_option( $new_name, $missing ) ) {
			if ( ! update_option( $new_name, $legacy_value, false ) ) {
				return false;
			}
		}

		delete_option( $legacy_name );
		return $missing === get_option( $legacy_name, $missing );
	}
}

add_action( 'plugins_loaded', array( 'Wpstream_Storage_Migration', 'maybe_migrate' ), 5 );
