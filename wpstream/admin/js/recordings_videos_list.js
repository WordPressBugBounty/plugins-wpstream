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
WpStreamUtils.checkPendingVideos = function (scope = document) {
    // Collect all currently-pending video placeholders.
    const pendingElements = scope.querySelectorAll('.wpstream_video_pending');

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
                updateVideoStatuses( response.data.items, scope );
            }
        },
        complete: function() {
            // Reschedule the poll in 10s if any pending videos still remain.
            if (scope.querySelector('.wpstream_video_pending')) {
                pendingVideosTimeout = setTimeout(() => WpStreamUtils.checkPendingVideos(scope), 10000);
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
function updateVideoStatuses(videos, scope = document) {
    // Process each ready video from the server response.
    videos.forEach(video => {
        // Grab all video wrapper rows in the list.
        const videoWrapper = scope.querySelectorAll('.wpstream_video_wrapper');

        // Find the wrapper whose displayed file name matches this video.
        const singleVideo = Array.from(videoWrapper).find(wrapper => {
            const element = wrapper.querySelector('.storage_file_name_real');
            return element && element.textContent === video.name;
        })
        // Skip videos with no matching row (the list may have been re-rendered
        // since the poll started) so one stale item can't abort the whole loop.
        if ( !singleVideo ) {
            return;
        }

        // Only rows still showing the pending placeholder need updating.
        const pendingDiv = singleVideo.querySelector('.wpstream_video_pending');
        if (pendingDiv) {
            // Drop the "processing" placeholder.
            pendingDiv.remove();
            document.dispatchEvent(new CustomEvent('wpstream:recording-ready', { detail: { name: video.name } }));
            if (scope.querySelector('#library-video-form,.editor-video-panel')) {
                singleVideo.remove();
                return;
            }

            // The media files list prints a finished row's actions in advance, hidden. Showing them is all a
            // ready file needs; their click handlers were bound with the page. The Processing word goes too.
            const library = singleVideo.querySelector('.library-recording-actions');
            if (library) {
                library.querySelectorAll('[hidden]').forEach(element => element.removeAttribute('hidden'));
                const status = singleVideo.querySelector('.library-recording-status');
                if (status) status.remove();
                return;
            }
            // Build inert DOM nodes: a recording name is data, never HTML.
            const labels = wpstream_recordings_videos_list_vars;
            const target = singleVideo;
            const addButton = (className, label) => {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'button-link ' + className;
                button.dataset.filename = video.name; button.textContent = label;
                target.append(button); return button;
            };
            addButton('wpstream_get_download_link', labels.download);
            const download = document.createElement('a');
            download.className = 'wpstream_download_link'; download.style.display = 'none'; target.append(download);
            const addLink = (className, base, label) => {
                const link = document.createElement('a'); link.className = className;
                link.href = base + encodeURIComponent(video.name); link.textContent = label; target.append(link);
            };
            addLink('create_new_free_video', labels.add_free_video_url, labels.create_ftv_vod);
            if (labels.woocommerce_exists) addLink('create_new_ppv_video', labels.add_paid_video_url, labels.create_ptv_vod);
            addButton('wpstream_delete_media', labels.delete_file);
            // Clear any stale click handlers before re-binding.
            jQuery('.wpstream_get_download_link').unbind('click');
            jQuery('.wpstream_delete_media').unbind('click');
            // Re-attach the download and delete link handlers to the new markup.
            WpStreamUtils.generate_download_link();
            WpStreamUtils.generate_delete_link();
        }
    });
}
