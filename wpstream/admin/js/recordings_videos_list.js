/*
 * WpStream recordings/videos list — pending-video polling.
 *
 * Powers the admin storage/recordings list. While any uploaded video is still
 * being processed (marked with `.wpstream_video_pending`), this script polls the
 * `wpstream_check_pending_videos` AJAX action on an interval, and when the server
 * reports a video is ready it swaps the "pending" placeholder for the real action
 * buttons (delete, download link, create free/paid VOD). Exposed helpers live on
 * the shared `window.WpStreamUtils` namespace.
 */

// Ensure the shared utilities namespace exists without clobbering it.
window.WpStreamUtils = window.WpStreamUtils || {};
// Holds the setTimeout handle for the polling loop so it can be cleared.
let pendingVideosTimeout;

// On DOM ready, kick off polling only if the page has at least one pending video.
jQuery(document).ready(function () {
    // Start the pending-video check when a pending placeholder is present.
    if (document.querySelector('.wpstream_video_pending')) {
        WpStreamUtils.checkPendingVideos();
    }
})

/**
 * Poll the server for the status of videos still being processed.
 *
 * Sends the `wpstream_check_pending_videos` AJAX request; on a successful
 * response with items it updates each matching video's UI, and it reschedules
 * itself every 10s while pending videos remain (stopping otherwise).
 *
 * @return {void}
 */
WpStreamUtils.checkPendingVideos = function () {
    // Collect all currently-pending video placeholders.
    const pendingElements = document.querySelectorAll('.wpstream_video_pending');

    // Nothing pending: clear any scheduled poll and stop.
    if ( !pendingElements.length ) {
        if ( pendingVideosTimeout ) {
            clearTimeout( pendingVideosTimeout );
            pendingVideosTimeout = null;
        }
        return;
    }

    // Ask the server which pending videos have finished processing.
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'wpstream_check_pending_videos',
        },
        success: function(response) {
            // Apply status updates when the response carries items.
            if ( response.success && response.data.items ) {
                updateVideoStatuses( response.data.items );
            }
        },
        complete: function() {
            // Reschedule the poll in 10s if any pending videos still remain.
            if (document.querySelector('.wpstream_video_pending')) {
                pendingVideosTimeout = setTimeout(WpStreamUtils.checkPendingVideos, 10000);
            }
        }
    });
}

/**
 * Replace the "pending" placeholder with action buttons for ready videos.
 *
 * @param {Array<{name: string}>} videos - Videos the server reports as ready.
 * @return {void}
 */
function updateVideoStatuses(videos) {
    // Process each ready video from the server response.
    videos.forEach(video => {
        // Grab all video wrapper rows in the list.
        const videoWrapper = document.querySelectorAll('.wpstream_video_wrapper');
        // Bail on this video if there are no wrappers to search.
        if ( !videoWrapper ) {
            return;
        }

        // Find the wrapper whose displayed file name matches this video.
        const singleVideo = Array.from(videoWrapper).find(wrapper => {
            const element = wrapper.querySelector('.storage_file_name_real');
            return element && element.textContent === video.name;
        })

        // Check whether that wrapper still shows the pending placeholder.
        const isPendingItem = singleVideo.querySelector('.wpstream_video_pending');
        if ( isPendingItem ) {
            // Re-select the pending div to remove/replace it.
            const pendingDiv = singleVideo.querySelector('.wpstream_video_pending');

            if (pendingDiv) {
                // Drop the "processing" placeholder.
                pendingDiv.remove();

                // Build the action-buttons markup for the now-ready video.
                const buttonHtml = `
                    <div class="wpstream_delete_media" onclick="return confirm('Are you sure you wish to delete ${video.name}?')" data-filename="${video.name}">${wpstream_recordings_videos_list_vars.delete_file}</div>
                    <div class="wpstream_get_download_link" data-filename="${video.name}">${wpstream_recordings_videos_list_vars.download}</div>
                    <a href class="wpstream_download_link"></a>
                    <a class="create_new_free_video" href="${wpstream_recordings_videos_list_vars.add_free_video_url}${video.name}">${wpstream_recordings_videos_list_vars.create_ftv_vod}</a>
                    ${wpstream_recordings_videos_list_vars.woocommerce_exists ? `<a class="create_new_ppv_video" href="${wpstream_recordings_videos_list_vars.add_paid_video_url}${video.name}">${wpstream_recordings_videos_list_vars.create_ptv_vod}</a>` : ''}
                `

                // Inject the new buttons at the end of the video wrapper.
                singleVideo.insertAdjacentHTML('beforeend', buttonHtml);
                // Clear any stale click handlers before re-binding.
                jQuery('.wpstream_get_download_link').unbind('click');
                jQuery('.wpstream_delete_media').unbind('click');
                // Re-attach the download and delete link handlers to the new markup.
                WpStreamUtils.generate_download_link();
                WpStreamUtils.generate_delete_link();
            }
        }
    });
}