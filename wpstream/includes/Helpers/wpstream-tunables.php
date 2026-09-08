<?php
/**
 * Tunables helper.
 *
 * Single place where cache lifetimes, poll intervals, lock ages and size
 * limits pass through their `wpstream_{noun}_ttl` / `_interval` / `_max_*`
 * filter and are clamped to a sane range (see docs/adr/0014). Every tunable
 * keeps today's hardcoded value as its default; only the number is
 * filterable — transient names, cached payload shapes and the token value
 * itself never are.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes/Helpers
 */

// Block direct file access outside of WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wpstream_tunable' ) ) {
	/**
	 * Run a numeric tunable through its filter and clamp the result.
	 *
	 * The lower bound is always 1: a zero or negative value would turn a cache
	 * into a hot loop or a limit into "nothing allowed". The upper bound is
	 * the point past which a runaway value breaks liveness for that tunable.
	 *
	 * @param string $hook    Filter name, e.g. `wpstream_channel_state_ttl`.
	 * @param int    $default Today's hardcoded value; passed to the filter.
	 * @param int    $max     Inclusive upper bound.
	 * @param array  $args    Extra filter arguments (after the value).
	 * @return int Clamped value in [1, $max].
	 */
	function wpstream_tunable( $hook, $default, $max, $args = array() ) {
		// Run the filter with the default first, then any context arguments.
		$value = apply_filters( $hook, (int) $default, ...array_values( (array) $args ) );

		// Clamp: never below 1, never above the liveness bound.
		return (int) min( (int) $max, max( 1, (int) $value ) );
	}
}
