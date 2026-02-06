// Initialize transaction ID at the start
const transactionId = getOrCreateTransactionId();
console.log(transactionId);

/**
 * Track onboarding steps
 *
 * @param {string} action - The action to track
 * @param {string} step - The button that was pressed
 * @param {string} element_type - The type of the element (optional)
 * @param {string} element_name - The name of the element (optional)
 */
function wpstream_track_onboarding_step(action, step, element_type= '', element_name = '') {
	fetch( wpstream_onboarding_page_vars.request_url + '/onboarding/index.php', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify({
			website: window.location.origin,
			action: action,
			wps_user: wpstream_onboarding_page_vars.wps_user,
			parameters: {
				step: step,
				element_type: element_type,
				element_name: element_name
			},
			plugin_version: wpstream_onboarding_page_vars.plugin_version,
			session_id: sessionId,
			transaction_id: transactionId
		})
	}).then(res => {
		// do nothing for now
	});
}

window.addEventListener('DOMContentLoaded', async function() {
	// if it's the create channel page
	if ( wpstream_onboarding_page_vars.current_page === 'post_edit' ) {
		switch ( wpstream_onboarding_page_vars.branch ) {
			case '1':
				wpstream_track_onboarding_step('page_loaded', 'create_free_channel_step' );
				break;
			case '2':
				wpstream_track_onboarding_step('page_loaded', 'create_paid_channel_step' );
				break;
			case '3':
				wpstream_track_onboarding_step('page_loaded', 'create_free_vod_step' );
				break;
			case '4':
				wpstream_track_onboarding_step('page_loaded', 'create_paid_vod_step' );
				break;
			default:
				// do nothing
				break;
		}
	}

	// if it's the WpStream -> Quick start page
	if ( wpstream_onboarding_page_vars.current_page === 'onboarding' ) {
		if (jQuery('#wpstream_have_token').length > 0) {
			wpstream_track_onboarding_step('page_loaded', 'select_channel_or_vod_step' );
		} else {
			wpstream_track_onboarding_step('page_loaded', 'register_step');
		}
	}
});

// make a call to the wpstream_track_onboarding_step function when the user closes the page in browser but not on reload
window.addEventListener('beforeunload', function() {
	const data = JSON.stringify({
		website: window.location.origin,
		action: 'onboarding_closed',
		wps_user: wpstream_onboarding_page_vars.wps_user,
		parameters: {
			step: wpstream_onboarding_page_vars.current_page,
		},
		plugin_version: wpstream_onboarding_page_vars.plugin_version,
		session_id: sessionId,
		transaction_id: transactionId
	});

	const blob = new Blob([data], { type: 'application/json' });
	navigator.sendBeacon(wpstream_onboarding_page_vars.request_url + '/onboarding/index.php', blob);
});

jQuery(document).ready(function($) {
	jQuery('.wpstream_view_channel').on('click', function() {
		wpstream_track_onboarding_step('view_channel_clicked', 'wpstream_' + post_type, 'button', 'view_channel_button');
	});

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
	const value = `; ${document.cookie}`;
	const parts = value.split(`; ${name}=`);
	if (parts.length === 2) {
		return decodeURIComponent(parts.pop().split(';').shift());
	}
	return null;
}

/**
 * Set a session cookie (expires when browser closes)
 *
 * @param {string} name - Cookie name
 * @param {string} value - Cookie value
 */
function setSessionCookie(name, value) {
	document.cookie = name + '=' + encodeURIComponent(value) + '; path=/';
}

/**
 * Get or create a transaction ID cookie
 *
 * @return {string} - Transaction ID
 */
function getOrCreateTransactionId() {
	let transactionId = getCookie('transactionId');
	if (!transactionId) {
		transactionId = 'txn_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
		setSessionCookie('transactionId', transactionId);
	}
	return transactionId;
}

function onboarding_step_to_string(step) {
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
			return '';
	}
}

function branch_to_string(branch) {
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
			return '';
	}
}