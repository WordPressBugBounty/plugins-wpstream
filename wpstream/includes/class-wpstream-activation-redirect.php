<?php

/**
 * One-shot onboarding redirect after a first activation.
 *
 * @package Wpstream
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Transient used to carry activation intent into the next admin request. */
const WPSTREAM_ACTIVATION_REDIRECT_TRANSIENT = 'wpstream_activation_redirect_pending';

/**
 * Whether the current request context must never participate in onboarding.
 *
 * @return bool
 */
function wpstream_activation_redirect_is_guarded() {
	return isset( $_GET['activate-multi'] )
		|| is_network_admin()
		|| wp_doing_ajax()
		|| ( defined( 'WP_CLI' ) && WP_CLI );
}

/**
 * Queue onboarding for this plugin after an ordinary activation.
 *
 * @param string $plugin Activated plugin basename.
 * @return void
 */
function wpstream_queue_activation_redirect( $plugin ) {
	if ( WPSTREAM_PLUGIN_BASE !== $plugin ) {
		return;
	}

	if ( wpstream_activation_redirect_is_guarded() ) {
		return;
	}

	if ( get_option( 'wpstream_current_token', '' ) ) {
		return;
	}

	set_transient( WPSTREAM_ACTIVATION_REDIRECT_TRANSIENT, 1, 60 );
}

/**
 * Consume a queued onboarding redirect on the next admin request.
 *
 * @return void
 */
function wpstream_maybe_activation_redirect() {
	if ( wpstream_activation_redirect_is_guarded() ) {
		return;
	}

	if ( ! get_transient( WPSTREAM_ACTIVATION_REDIRECT_TRANSIENT ) ) {
		return;
	}

	delete_transient( WPSTREAM_ACTIVATION_REDIRECT_TRANSIENT );

	if ( get_option( 'wpstream_current_token', '' ) ) {
		return;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=wpstream_onboard' ) );
	exit;
}

add_action( 'activated_plugin', 'wpstream_queue_activation_redirect' );
add_action( 'admin_init', 'wpstream_maybe_activation_redirect' );
