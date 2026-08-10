/*
 * WpStream user quota widget.
 *
 * Periodically polls the `wpstream_get_live_quota_data` admin-ajax endpoint and
 * writes the account's remaining streaming allowance (viewer/broadcast/storage
 * hours, or data/storage in GB) into the matching DOM elements. Polling runs on
 * a one-minute interval that is paused while the tab is hidden and on unload to
 * avoid needless background requests.
 */

// Handle for the active setInterval timer (null when no timer is running).
var quotaUpdateInterval = null;
// Timestamp of the most recent fetch, used to decide whether a refresh is due.
var lastQuotaDate = null;

// On page load: do one immediate fetch, then start the recurring poll.
jQuery(document).ready(function() {
	"use strict";

	wpstream_fetch_and_update_quota();
	wpstream_set_interval_update_quota_data();
});

// Stop polling when the page is being unloaded.
jQuery(window).on('beforeunload', wpstream_cleanup_quota_interval );
// Pause polling while the tab is hidden; resume (and catch up) when visible again.
jQuery(window).on('visibilitychange', function() {
	if ( document.hidden ) {
		// Tab moved to background: cancel the timer to save requests.
		wpstream_cleanup_quota_interval();
	} else {
		// if the last quota update was more than one minute ago, update now
		if ( lastQuotaDate < new Date( Date.now() - 60000) ) {
			// fetch data
			wpstream_fetch_and_update_quota();
		}
		// set the interval for one minute
		// Clear any stale timer first, then start a fresh one-minute poll.
		wpstream_cleanup_quota_interval();
		quotaUpdateInterval = setInterval( wpstream_fetch_and_update_quota, 60000 );
	}
});

/**
 * Format an hours value by truncating (not rounding) to a fixed number of decimals.
 *
 * @param {number|string} hours    Raw hours value to format.
 * @param {number} [decimals=2]    Decimal places to keep (clamped to >= 0).
 * @return {number} Non-negative, floored hours value.
 */
function wpstream_format_hours(hours, decimals) {
	// Coerce the incoming value to a number.
	var formatted = parseFloat(hours);
	// Default to 2 decimals; otherwise sanitize to a non-negative integer count.
	decimals = ( typeof decimals === 'undefined' ) ? 2 : Math.max( 0, parseInt( decimals, 10 ) || 0 );

	// Treat non-numeric or negative input as zero.
	if ( isNaN( formatted ) || formatted < 0 ) {
		formatted = 0;
	}

	// Scale factor for the requested decimal precision (10^decimals).
	var factor = Math.pow( 10, decimals );

	// Floor at the chosen precision so we never over-report remaining quota.
	return Math.floor( Math.abs( formatted ) * factor ) / factor;
}

/**
 * Fetch the current quota data over admin-ajax and paint it into the widget.
 */
function wpstream_fetch_and_update_quota() {
	// Record the time of this fetch for the visibilitychange catch-up check.
	lastQuotaDate = new Date();
	// Build the admin-ajax endpoint URL from the localized admin_url.
	var ajaxurl = wpstream_start_streaming_vars.admin_url + 'admin-ajax.php';
	// Nonce that authorizes the quota request, read from a hidden field.
	var nonce          = jQuery('#wpstream_notice_nonce').val();

	jQuery.ajax({
		type: 'POST',
		dataType: 'json',
		url: ajaxurl,
		timeout: 3000000,

		// Endpoint action plus the security nonce.
		data: {
			'action': 'wpstream_get_live_quota_data',
			'security': nonce,
		},
		success: function (data) {
			// Only update the UI when the endpoint reports success.
			if (data.success === true) {
				// Account measures usage in hours: show viewer/broadcast/storage hours.
				if ( data.data.use_streaming_hours === true ) {
					// Remaining viewer (playback) hours.
					if ( data.data.available_viewer_hours !== undefined ) {
						jQuery('#wpstream_available_viewer_hours').text( wpstream_format_hours( data.data.available_viewer_hours ) + ' hours');
					}
					// Remaining broadcast (streaming) hours.
					if ( data.data.available_broadcast_hours !== undefined ) {
						jQuery('#wpstream_available_broadcast_hours').text( wpstream_format_hours( data.data.available_broadcast_hours ) + ' hours');
					}
					// Remaining recording/storage hours.
					if ( data.data.available_storage_hours !== undefined ) {
						jQuery('#wpstream_available_storage_hours').text( wpstream_format_hours( data.data.available_storage_hours ) + ' hours');
					}
				} else {
					// Account measures usage in data volume: convert MB to GB for display.
					jQuery('#wpstream_available_data').text( wpstream_convert_mb_to_gb( data.data.available_data_mb ) + ' GB');
					jQuery('#wpstream_available_storage').text( wpstream_convert_mb_to_gb( data.data.available_storage_mb ) + ' GB');
				}
			}
		},
		error: function (jqXHR, textStatus, errorThrown) {
			// Network/parse failure: log details for debugging, leave UI unchanged.
			console.log(jqXHR, textStatus, errorThrown);
		}
	});
}

/**
 * (Re)start the recurring one-minute quota poll, clearing any existing timer first.
 */
function wpstream_set_interval_update_quota_data(){
	wpstream_cleanup_quota_interval();
	quotaUpdateInterval = setInterval( wpstream_fetch_and_update_quota, 60000 );
}

/**
 * Cancel the active poll timer, if any, and reset the handle.
 */
function wpstream_cleanup_quota_interval() {
	if (quotaUpdateInterval) {
		clearInterval(quotaUpdateInterval);
		quotaUpdateInterval = null;
	}
}
