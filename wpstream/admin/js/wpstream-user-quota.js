/*
 * WpStream admin live-quota widget.
 *
 * Periodically polls admin-ajax.php for the account's remaining streaming quota
 * and writes the figures into the admin notice/widget. Depending on the plan the
 * quota is expressed either in hours (viewer / broadcast / storage hours) or in
 * data volume (GB). Polling runs on a one-minute interval that is paused while
 * the tab is hidden and resumed (with an immediate refresh if stale) when it
 * becomes visible again, and torn down before the page unloads.
 */

// Handle for the active setInterval timer (null when no timer is running).
var quotaUpdateInterval = null;
// Timestamp of the most recent successful quota fetch, used for the staleness check.
var lastQuotaDate = null;

// On page load, fetch the quota once and then start the recurring poll.
jQuery(document).ready(function() {
	"use strict";

	// Immediate first fetch so the widget is populated right away.
	wpstream_fetch_and_update_quota();
	// Kick off the one-minute polling loop.
	wpstream_set_interval_update_quota_data();
});

// Stop the timer when the page is being unloaded to avoid a dangling interval.
jQuery(window).on('beforeunload', wpstream_cleanup_quota_interval );
// Pause polling while the tab is hidden; resume it when the tab is shown again.
jQuery(window).on('visibilitychange', function() {
	if ( document.hidden ) {
		// Tab is not visible: stop polling to save requests.
		wpstream_cleanup_quota_interval();
	} else {
		// if the last quota update was more than one minute ago, update now
		if ( lastQuotaDate < new Date( Date.now() - 60000) ) {
			// fetch data
			wpstream_fetch_and_update_quota();
		}
		// set the interval for one minute
		// Clear any existing timer first, then restart the one-minute poll.
		wpstream_cleanup_quota_interval();
		quotaUpdateInterval = setInterval( wpstream_fetch_and_update_quota, 60000 );
	}
});

/**
 * Normalize a raw hours value into a safe, truncated number for display.
 *
 * @param {number|string} hours    The raw hours figure to format.
 * @param {number}        [decimals=2] How many decimal places to keep.
 * @return {number} Non-negative value truncated (floored) to `decimals` places.
 */
function wpstream_format_hours(hours, decimals) {
	// Coerce the incoming value to a float.
	var formatted = parseFloat(hours);
	// Default to 2 decimals; otherwise clamp the requested precision to a non-negative integer.
	decimals = ( typeof decimals === 'undefined' ) ? 2 : Math.max( 0, parseInt( decimals, 10 ) || 0 );

	// Guard against NaN or negative input by falling back to zero.
	if ( isNaN( formatted ) || formatted < 0 ) {
		formatted = 0;
	}

	// Scaling factor used to floor to the requested number of decimals.
	var factor = Math.pow( 10, decimals );

	// Truncate (not round) to `decimals` places and return.
	return Math.floor( Math.abs( formatted ) * factor ) / factor;
}

/**
 * Fetch the current quota figures from the server and render them into the widget.
 *
 * @return {void}
 */
function wpstream_fetch_and_update_quota() {
	// Record when this fetch happened for the visibility-based staleness check.
	lastQuotaDate = new Date();
	// Use this script's own localized admin base URL: the start-streaming script (and
	// its variables) is not loaded on every screen this widget appears on.
	var ajaxurl = wpstream_user_quota_vars.admin_url + 'admin-ajax.php';
	// Security nonce read from a hidden field on the page.
	var nonce          = jQuery('#wpstream_notice_nonce').val();

	// Request the latest quota data.
	jQuery.ajax({
		type: 'POST',
		dataType: 'json',
		url: ajaxurl,
		timeout: 3000000,

		data: {
			'action': 'wpstream_get_live_quota_data',
			'security': nonce,
		},
		success: function (data) {
			// Only update the UI when the server reports success.
			if (data.success === true) {
				// Compact library headers share this poll with the legacy quota notice.
				jQuery('[data-library-quota]').each(function () {
					var field = this.getAttribute('data-library-quota');
					if (data.data[field] === undefined) return;
					var value = Number(data.data[field]);
					if (!Number.isFinite(value)) return;
					if (field.endsWith('_mb')) value /= 1024;
					jQuery(this).text(wpstream_format_hours(value).toFixed(2));
				});
				// Pill balances follow the portal's rule: two decimals below 10, one from 10 upwards.
				jQuery('[data-library-pill-quota]').each(function () {
					var field = this.getAttribute('data-library-pill-quota');
					if (data.data[field] === undefined) return;
					var value = Number(data.data[field]);
					if (!Number.isFinite(value)) return;
					value = Math.max(0, field.endsWith('_mb') ? value / 1024 : value);
					jQuery(this).text(value.toFixed(value < 10 ? 2 : 1));
				});
				// Usage pills and popups: recalculate percent, meters and used/left from the fresh balance and cap.
				jQuery('[data-library-usage]').each(function () {
					var divisor = this.dataset.unit === 'GB' ? 1024 : 1;
					var left = Math.max(0, Number(data.data[this.dataset.available])) / divisor;
					var total = Number(data.data[this.dataset.total]) / divisor;
					if (!Number.isFinite(left) || !(total > 0)) return;
					var used = Math.max(0, total - left);
					var percent = Math.min(100, Math.round(used / total * 100));
					var replaceNumber = function (element, value) { element.textContent = element.textContent.replace(/^[\d.]+/, value); };
					replaceNumber(this.querySelector('[data-usage-percent]'), percent);
					replaceNumber(this.querySelector('[data-usage-used]'), used.toFixed(2));
					replaceNumber(this.querySelector('[data-usage-left]'), left.toFixed(2));
					this.querySelectorAll('[role=progressbar]').forEach(function (meter) {
						meter.setAttribute('aria-valuenow', percent);
						meter.firstElementChild.style.width = percent + '%';
					});
					// The pill meter turns amber from 80% used and red from 95%.
					var pillFill = this.querySelector('.library-usage-mini-fill');
					pillFill.classList.toggle('is-warn', percent >= 80 && percent < 95);
					pillFill.classList.toggle('is-danger', percent >= 95);
				});
				// Hours-based plans: show remaining viewer / broadcast / storage hours.
				if ( data.data.use_streaming_hours === true ) {
					// Remaining viewer hours (only if provided).
					if ( data.data.available_viewer_hours !== undefined ) {
						jQuery('#wpstream_available_viewer_hours').text( wpstream_format_hours( data.data.available_viewer_hours ) + ' viewer');
					}
					// Remaining broadcast hours (only if provided).
					if ( data.data.available_broadcast_hours !== undefined ) {
						jQuery('#wpstream_available_broadcast_hours').text( wpstream_format_hours( data.data.available_broadcast_hours ) + ' broadcast');
					}
					// Remaining storage hours (only if provided).
					if ( data.data.available_storage_hours !== undefined ) {
						jQuery('#wpstream_available_storage_hours').text( wpstream_format_hours( data.data.available_storage_hours ) + ' storage');
					}
				} else {
					// Data-volume plans: show remaining data and storage converted to GB.
					jQuery('#wpstream_available_data').text( wpstream_convert_mb_to_gb( data.data.available_data_mb ) + ' GB');
					jQuery('#wpstream_available_storage').text( wpstream_convert_mb_to_gb( data.data.available_storage_mb ) + ' GB');
				}
			}
		},
		error: function (jqXHR, textStatus, errorThrown) {
			// Log the transport/parse error status only — never the response payload.
			console.error('WpStream: quota refresh failed:', textStatus);
		}
	});
}

/**
 * (Re)start the recurring one-minute quota poll, clearing any existing timer first.
 *
 * @return {void}
 */
function wpstream_set_interval_update_quota_data(){
	// Ensure we never stack multiple intervals.
	wpstream_cleanup_quota_interval();
	// Poll for fresh quota data once a minute.
	quotaUpdateInterval = setInterval( wpstream_fetch_and_update_quota, 60000 );
}

/**
 * Stop the recurring quota poll and clear the stored timer handle.
 *
 * @return {void}
 */
function wpstream_cleanup_quota_interval() {
	// Only clear if a timer is actually running.
	if (quotaUpdateInterval) {
		clearInterval(quotaUpdateInterval);
		quotaUpdateInterval = null;
	}
}
