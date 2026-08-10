/*
 * WpStream onboarding-page telemetry.
 *
 * Sends onboarding progress events to the remote WpStream tracking endpoint so
 * the funnel (register -> pick channel/vod -> create) can be measured. Runs on
 * the admin onboarding/quick-start screens and on the relevant post-edit pages.
 * Correlates events with a per-browser transaction id cookie and an optional
 * session id, and fires a beacon on page unload. Also maps internal step/branch
 * codes to human-readable labels.
 */

// Bootstrap a stable transaction id (from cookie, or freshly created) as soon
// as the script loads so every event in this browser shares one identifier.
const transactionId = getOrCreateTransactionId();

/**
 * Resolve which session id to attach to a tracking event.
 *
 * @param {string} explicitSessionId - Caller-provided session id (optional).
 * @return {string} The explicit id, else the global `sessionId`, else empty string.
 */
function resolveTrackingSessionId(explicitSessionId = '') {
	// Prefer an explicitly passed session id when present.
	if (explicitSessionId) {
		return explicitSessionId;
	}

	// Otherwise fall back to a page-global `sessionId` if one is defined.
	if (typeof sessionId !== 'undefined' && sessionId) {
		return sessionId;
	}

	// No session id available.
	return '';
}

/**
 * Track onboarding steps
 *
 * @param {string} action - The action to track
 * @param {string} step - The button that was pressed
 * @param {string} element_type - The type of the element (optional)
 * @param {string} element_name - The name of the element (optional)
 * @param {string} tracking_session_id - Session id override (optional)
 */
function wpstream_track_onboarding_step(action, step, element_type= '', element_name = '', tracking_session_id = '') {
	// POST the event as JSON to the remote onboarding tracker endpoint.
	fetch( wpstream_onboarding_page_vars.request_url + '/onboarding/index.php', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({
			// Origin of the site sending the event.
			website: window.location.origin,
			// The tracked action name.
			action: action,
			// WpStream account/user identifier from the localized vars.
			wps_user: wpstream_onboarding_page_vars.wps_user,
			// Contextual detail about the step and (optional) UI element.
			parameters: {
				step: step,
				element_type: element_type,
				element_name: element_name
			},
			// Plugin version so events can be segmented by release.
			plugin_version: wpstream_onboarding_page_vars.plugin_version,
			// Session id (explicit override, global, or empty).
			session_id: resolveTrackingSessionId(tracking_session_id),
			// Stable per-browser transaction id for correlation.
			transaction_id: transactionId
		})
	}).then(res => {
		// Response is intentionally ignored; fire-and-forget tracking.
		// do nothing for now
	});
}

// On initial page load, emit an "onboarding_started" event and then a
// page-specific "page_loaded" event depending on which screen/branch we are on.
window.addEventListener('DOMContentLoaded', async function() {
	// Always record that onboarding started for the current page.
	wpstream_track_onboarding_step('onboarding_started', 'onboarding_' + wpstream_onboarding_page_vars.current_page);

	// if it's the create channel page
	if ( wpstream_onboarding_page_vars.current_page === 'post_edit' ) {
		// Branch determines which create-* step the user landed on.
		switch ( wpstream_onboarding_page_vars.branch ) {
			case '1':
				// Free live channel creation.
				wpstream_track_onboarding_step('page_loaded', 'create_free_channel_step' );
				break;
			case '2':
				// Paid live channel creation.
				wpstream_track_onboarding_step('page_loaded', 'create_paid_channel_step' );
				break;
			case '3':
				// Free VOD creation.
				wpstream_track_onboarding_step('page_loaded', 'create_free_vod_step' );
				break;
			case '4':
				// Paid VOD creation.
				wpstream_track_onboarding_step('page_loaded', 'create_paid_vod_step' );
				break;
			default:
				// do nothing
				break;
		}
	}

	// if it's the WpStream -> Quick start page
	if ( wpstream_onboarding_page_vars.current_page === 'onboarding' ) {
		// Presence of the token field means the account is already connected,
		// so the user is at the channel/vod selection step; otherwise register.
		if (jQuery('#wpstream_have_token').length > 0) {
			wpstream_track_onboarding_step('page_loaded', 'select_channel_or_vod_step' );
		} else {
			wpstream_track_onboarding_step('page_loaded', 'register_step');
		}
	}
});

// generic unload event (fires on close, refresh, and navigation)
window.addEventListener('beforeunload', function() {
	// Resolve the session id for the unload event.
	const trackingSessionId = resolveTrackingSessionId();
	// Build the unload payload mirroring the tracked-step shape.
	const data = JSON.stringify({
		website: window.location.origin,
		action: 'onboarding_unload',
		wps_user: wpstream_onboarding_page_vars.wps_user,
		parameters: {
			step: wpstream_onboarding_page_vars.current_page,
		},
		plugin_version: wpstream_onboarding_page_vars.plugin_version,
		session_id: trackingSessionId,
		transaction_id: transactionId
	});

	// Use sendBeacon so the request survives the page unload.
	const blob = new Blob([data], { type: 'application/json' });
	navigator.sendBeacon(wpstream_onboarding_page_vars.request_url + '/onboarding/index.php', blob);
});

// Wire up click tracking for the post-create success buttons.
jQuery(document).ready(function($) {
	// Track clicks on the "view channel" button.
	jQuery('.wpstream_view_channel').on('click', function() {
		wpstream_track_onboarding_step('view_channel_clicked', 'wpstream_' + post_type, 'button', 'view_channel_button');
	});

	// Track clicks on the "view statistics" button.
	jQuery('.wpstream_live_data.wpstream_statistics').on('click', function() {
		wpstream_track_onboarding_step('view_statistics_clicked', 'wpstream_' + post_type, 'button', 'view_statistics_button');
	})
});

/**
 * Get a cookie value by name
 *
 * @param {string} name - Cookie name
 * @return {string|null} - Cookie value or null if not found
 */
function getCookie(name) {
	// Prefix with "; " so every cookie (including the first) is delimited uniformly.
	const value = `; ${document.cookie}`;
	// Split on "; name=" to isolate the target cookie's value portion.
	const parts = value.split(`; ${name}=`);
	// A clean split yields exactly two segments when the cookie exists.
	if (parts.length === 2) {
		// Take the trailing segment, cut at the next ";", and decode it.
		return decodeURIComponent(parts.pop().split(';').shift());
	}
	// Cookie not present.
	return null;
}

/**
 * Set a session cookie (expires when browser closes)
 *
 * @param {string} name - Cookie name
 * @param {string} value - Cookie value
 */
function setSessionCookie(name, value) {
	// Write a path-wide cookie with no expiry, so it lasts for the browser session.
	document.cookie = name + '=' + encodeURIComponent(value) + '; path=/';
}

/**
 * Get or create a transaction ID cookie
 *
 * @return {string} - Transaction ID
 */
function getOrCreateTransactionId() {
	// Reuse an existing transaction id if one is already stored.
	let transactionId = getCookie('transactionId');
	if (!transactionId) {
		// Otherwise mint a new id from a timestamp plus random suffix and persist it.
		transactionId = 'txn_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
		setSessionCookie('transactionId', transactionId);
	}
	return transactionId;
}

/**
 * Map an internal onboarding step code to a human-readable label.
 *
 * @param {string} step - Internal step key (e.g. 'wpstream_step_3a').
 * @return {string} Readable label, or empty string if unrecognized.
 */
function onboarding_step_to_string(step) {
	// Translate each known step key to its label.
	switch (step) {
		case 'wpstream_step_1':
			return 'register_or_login';
		case 'wpstream_step_2':
			return 'select_channel_or_vod';
		case 'wpstream_step_3':
			return 'create_channel';
		case 'wpstream_step_3a':
			return 'create_free_channel';
		case 'wpstream_step_3b':
			return 'create_paid_channel';
		case 'wpstream_step_4':
			return 'create_vod';
		case 'wpstream_step_4a':
			return 'create_free_vod';
		case 'wpstream_step_4b':
			return 'create_paid_vod';
		default:
			// Unknown step.
			return '';
	}
}

/**
 * Map an onboarding branch number to a human-readable label.
 *
 * @param {string} branch - Branch code ('1'..'4').
 * @return {string} Readable label, or empty string if unrecognized.
 */
function branch_to_string(branch) {
	// Translate each branch code to its label.
	switch (branch) {
		case '1':
			return 'free_channel';
		case '2':
			return 'paid_channel';
		case '3':
			return 'free_vod';
		case '4':
			return 'paid_vod';
		default:
			// Unknown branch.
			return '';
	}
}