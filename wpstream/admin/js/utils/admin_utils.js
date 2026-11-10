/*
 * WpStream admin utility helpers.
 *
 * Exposes a small shared namespace (window.WpStreamUtils) of reusable admin-side
 * actions for managing stored cloud recordings/media: requesting a temporary
 * download link for a recording and deleting a recording file. Both talk to
 * admin-ajax.php and update the surrounding markup in place.
 */

// Reuse an existing namespace object if present, otherwise create a fresh one.
window.WpStreamUtils = window.WpStreamUtils || {};

/**
 * Wire up "get download link" buttons: on click, ask the server for a
 * downloadable URL for the associated recording and swap the button for a link.
 *
 * @return {void}
 */
WpStreamUtils.generate_download_link = function(){
    // Bind the click handler to every download-link trigger on the page.
    jQuery('.wpstream_get_download_link').on('click',function(){
        // Build the admin-ajax endpoint URL from the localized admin base URL.
        var ajaxurl      =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        // The recording's file name is stored on the clicked element's data attribute.
        var video_name          =   jQuery(this).attr('data-filename');
        // Cache the parent element so we can update its contents after the request.
        var parent              =   jQuery(this).closest('.wpstream_video_wrapper');
        if (!parent.length) parent = jQuery(this).parent();
        parent.find('.wpstream_download_link').removeAttr('hidden');

        // Remove the button so it cannot be clicked twice while we wait.
        jQuery(this).remove();
        // Show a temporary "please wait" message in place of the link.
        parent.find('.wpstream_download_link').show().text('please wait...');

        // Request the download URL for this recording.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'wpstream_get_download_link',
                'video_name': video_name,
                'security': wpstream_admin_control_vars.recordings_nonce
            },
            success: function (data) {
                // Server returned a usable URL: turn the element into a real download link.
                if( data.success === true ){
                    var link = parent.find('.wpstream_download_link');
                    link.show().text(wpstream_admin_control_vars.download_mess).attr({ href: data.url, target: '_blank', rel: 'noopener', download: '' });
                    // Start the download now; the visible link remains if the browser blocks this.
                    link.get(0).click();
                }else{
                    // Otherwise show the sentence the server wrote for the customer.
                    parent.find('.wpstream_download_link').show().text(data.error);
                }
            },
            error: function () {
                // The request itself failed: say so rather than staying on "please wait...".
                parent.find('.wpstream_download_link').show().text(wpstream_admin_control_vars.download_error);
            }
        });
    });
}

/**
 * Wire up "delete media" buttons: on click, ask the server to delete the
 * associated recording file and remove its row from the DOM on success.
 *
 * @return {void}
 */
WpStreamUtils.generate_delete_link = function() {
    // Bind the click handler to every delete-media trigger on the page.
    jQuery('.wpstream_delete_media').on('click',function(){
        // Build the admin-ajax endpoint URL from the localized admin base URL.
        var ajaxurl             =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        // The recording's file name (trimmed) is stored on the clicked element's data attribute.
        var video_name          =   jQuery(this).attr('data-filename').trim();
        // Cache the parent element so we can remove the whole row on success.
        var parent              =   jQuery(this).closest('.wpstream_video_wrapper');
        if (!parent.length) parent = jQuery(this).parent();

        // The row itself asks and reports. Names stay inert text, so O'Reilly.mp4 cannot become markup.
        var notice = parent.find('.wpstream_video_notice').first();
        if (!notice.length) notice = jQuery('<div class="wpstream_video_notice" role="status"></div>').appendTo(parent);
        var trigger = jQuery(this);
        var labels = wpstream_admin_control_vars;
        var affected = parent.attr('data-recording-users');
        var confirm = jQuery('<button type="button" class="button button-link-delete"></button>').text(labels.delete_file);
        var cancel = jQuery('<button type="button" class="button"></button>').text(labels.delete_cancel);
        notice.text(labels.delete_question.replace('%s', video_name) + (affected ? ' ' + labels.delete_affected.replace('%s', affected) : '') + ' ').append(confirm, ' ', cancel);
        confirm.trigger('focus');

        cancel.on('click', function () {
            notice.empty();
            // A button inside a menu is closed away by now, so focus returns to the menu's own button.
            var menu = trigger.closest('.library-menu');
            (menu.length ? menu.find('summary') : trigger).trigger('focus');
        });

        confirm.on('click', function () {
            notice.find('button').prop('disabled', true);
            // Ask the server to delete this recording file.
            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: {
                    'action': 'wpstream_get_delete_file',
                    'video_name': video_name,
                    'security': wpstream_admin_control_vars.recordings_nonce

                },
                success: function (data) {
                    if( data && data.success === true ){
                        WpStreamUtils.remove_deleted_recording(parent, video_name);
                    }else{
                        notice.text((data && data.error) || labels.delete_failed);
                    }
                },
                error: function () {
                    notice.text(labels.delete_failed);
                }
            });
        });
    });
}

/**
 * Take a deleted recording off the page: its own row, and the same file
 * shown under every video that was using it.
 *
 * @param {jQuery} row        The row or panel the delete started from.
 * @param {string} video_name The deleted file's name.
 * @return {void}
 */
WpStreamUtils.remove_deleted_recording = function (row, video_name) {
    var labels = wpstream_admin_control_vars;
    var section = row.closest('section');
    var player = row.find('video').get(0);
    if (player) player.pause();
    row.remove();

    // Videos keep their title but no longer have this file.
    jQuery('.library-file').filter(function () {
        return jQuery(this).find('.storage_file_name_real').text() === video_name;
    }).remove();
    jQuery('[data-recording-file]').filter(function () {
        return this.dataset.recordingFile === video_name;
    }).text(labels.delete_unavailable);

    // The list of waiting recordings is only printed while it has rows.
    if (section.is('#wpstream_file_management') && !section.find('tbody tr').length) section.remove();
}
