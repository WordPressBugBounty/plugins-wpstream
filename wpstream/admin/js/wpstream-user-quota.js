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

function wpstream_format_hours(hours, decimals) {
	var formatted = parseFloat(hours);
	decimals = ( typeof decimals === 'undefined' ) ? 2 : Math.max( 0, parseInt( decimals, 10 ) || 0 );

	if ( isNaN( formatted ) || formatted < 0 ) {
		formatted = 0;
	}

	var factor = Math.pow( 10, decimals );

	return Math.floor( Math.abs( formatted ) * factor ) / factor;
}

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
				if ( data.data.use_streaming_hours === true ) {
					if ( data.data.available_viewer_hours !== undefined ) {
						jQuery('#wpstream_available_viewer_hours').text( wpstream_format_hours( data.data.available_viewer_hours ) + ' hours');
					}
					if ( data.data.available_broadcast_hours !== undefined ) {
						jQuery('#wpstream_available_broadcast_hours').text( wpstream_format_hours( data.data.available_broadcast_hours ) + ' hours');
					}
					if ( data.data.available_storage_hours !== undefined ) {
						jQuery('#wpstream_available_storage_hours').text( wpstream_format_hours( data.data.available_storage_hours ) + ' hours');
					}
				} else {
					jQuery('#wpstream_available_data').text( wpstream_convert_mb_to_gb( data.data.available_data_mb ) + ' GB');
					jQuery('#wpstream_available_storage').text( wpstream_convert_mb_to_gb( data.data.available_storage_mb ) + ' GB');
				}
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
