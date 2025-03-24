window.WpStreamUtils = window.WpStreamUtils || {};
let pendingVideosTimeout;

jQuery(document).ready(function () {
    if (document.querySelector('.wpstream_video_pending')) {
        WpStreamUtils.checkPendingVideos();
    }
})

WpStreamUtils.checkPendingVideos = function () {
    const pendingElements = document.querySelectorAll('.wpstream_video_pending');

    if ( !pendingElements.length ) {
        if ( pendingVideosTimeout ) {
            clearTimeout( pendingVideosTimeout );
            pendingVideosTimeout = null;
        }
        return;
    }

    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'wpstream_check_pending_videos',
        },
        success: function(response) {
            if ( response.success && response.data.items ) {
                updateVideoStatuses( response.data.items );
            }
        },
        complete: function() {
            if (document.querySelector('.wpstream_video_pending')) {
                pendingVideosTimeout = setTimeout(WpStreamUtils.checkPendingVideos, 10000);
            }
        }
    });
}

function updateVideoStatuses(videos) {
    videos.forEach(video => {
        const videoWrapper = document.querySelectorAll('.wpstream_video_wrapper');
        if ( !videoWrapper ) {
            return;
        }

        const singleVideo = Array.from(videoWrapper).find(wrapper => {
            const element = wrapper.querySelector('.storage_file_name_real');
            return element && element.textContent === video.name;
        })

        const isPendingItem = singleVideo.querySelector('.wpstream_video_pending');
        if ( isPendingItem ) {
            const pendingDiv = singleVideo.querySelector('.wpstream_video_pending');

            if (pendingDiv) {
                pendingDiv.remove();

                const buttonHtml = `
                    <div class="wpstream_delete_media" onclick="return confirm('Are you sure you wish to delete ${video.name}?')" data-filename="${video.name}">${wpstream_recordings_videos_list_vars.delete_file}</div>
                    <div class="wpstream_get_download_link" data-filename="${video.name}">${wpstream_recordings_videos_list_vars.download}</div>
                    <a href class="wpstream_download_link"></a>
                    <a class="create_new_free_video" href="${wpstream_recordings_videos_list_vars.add_free_video_url}${video.name}">${wpstream_recordings_videos_list_vars.create_ftv_vod}</a>
                    ${wpstream_recordings_videos_list_vars.woocommerce_exists ? `<a class="create_new_ppv_video" href="${wpstream_recordings_videos_list_vars.add_paid_video_url}${video.name}">${wpstream_recordings_videos_list_vars.create_ptv_vod}</a>` : ''}
                `

                singleVideo.insertAdjacentHTML('beforeend', buttonHtml);
                jQuery('.wpstream_get_download_link').unbind('click');
                jQuery('.wpstream_delete_media').unbind('click');
                WpStreamUtils.generate_download_link();
                WpStreamUtils.generate_delete_link();
            }
        }
    });
}