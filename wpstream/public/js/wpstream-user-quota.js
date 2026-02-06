var quotaUpdateInterval = null;
var lastQuotaDate = null;

jQuery(document).ready(function() {
	"use strict";

	wpstream_fetch_and_update_quota();
	wpstream_set_interval_update_quota_data();
});

jQuery(window).on('beforeunload', wpstream_cleanup_quota_interval );
jQuery(window).on('visibilitychange', function() {
	if ( document.hidden ) {
		wpstream_cleanup_quota_interval();
	} else {
		// if the last quota update was more than one minute ago, update now
		if ( lastQuotaDate < new Date( Date.now() - 60000) ) {
			// fetch data
			wpstream_fetch_and_update_quota();
		}
		// set the interval for one minute
		wpstream_cleanup_quota_interval();
		quotaUpdateInterval = setInterval( wpstream_fetch_and_update_quota, 60000 );
	}
});

function wpstream_fetch_and_update_quota() {
	lastQuotaDate = new Date();
	var ajaxurl = wpstream_start_streaming_vars.admin_url + 'admin-ajax.php';
	var nonce          = jQuery('#wpstream_notice_nonce').val();

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
			if (data.success === true) {
				jQuery('#wpstream_available_data').text(wpstream_convert_mb_to_gb( data.data.available_data_mb ) + ' GB');
				jQuery('#wpstream_available_storage').text( wpstream_convert_mb_to_gb( data.data.available_storage_mb ) + ' GB');
			}
		},
		error: function (jqXHR, textStatus, errorThrown) {
			console.log(jqXHR, textStatus, errorThrown);
		}
	});
}

function wpstream_set_interval_update_quota_data(){
	wpstream_cleanup_quota_interval();
	quotaUpdateInterval = setInterval( wpstream_fetch_and_update_quota, 60000 );
}

function wpstream_cleanup_quota_interval() {
	if (quotaUpdateInterval) {
		clearInterval(quotaUpdateInterval);
		quotaUpdateInterval = null;
	}
}