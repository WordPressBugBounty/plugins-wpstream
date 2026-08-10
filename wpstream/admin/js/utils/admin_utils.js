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
        var parent              =   jQuery(this).parent();

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
            },
            success: function (data) {
                // Server returned a usable URL: turn the element into a real download link.
                if( data.success === true ){
                    parent.find('.wpstream_download_link').show().text(wpstream_admin_control_vars.download_mess);
                    parent.find('.wpstream_download_link').show().attr('href',data.url);
                }else{
                    // Otherwise surface the returned error message to the user.
                    var error_message = data.error;
                    // Translate the known "not enough traffic" code into a friendly message.
                    if( data.error === 'NOT_ENOUGH_TRAFFIC' ) {
                        error_message = 'Not Enough data to download!';
                    }

                    // Display whichever error message we ended up with.
                    parent.find('.wpstream_download_link').show().text(error_message);
                }
            },
            error: function (errorThrown) {
                // error state
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
        var parent              =   jQuery(this).parent();

        // Ask the server to delete this recording file.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'wpstream_get_delete_file',
                'video_name': video_name

            },
            success: function (data) {
                // On confirmed deletion, remove the recording's row from the page.
                if( data.success === true ){
                    parent.remove();
                }
            },
            error: function (errorThrown) {
                // error state
            }
        });
    });
}