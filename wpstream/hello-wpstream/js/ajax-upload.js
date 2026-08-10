/*global $, jQuery, document, window, plupload, ajax_vars ,ajaxurl*/


/*
 * ajax-upload.js
 *
 * Front-end (dashboard) media uploader built on Plupload. Wires the upload
 * buttons, configures the Plupload instance per upload context (profile image,
 * gallery images, trailer/preview video), enforces the max-image limit, renders
 * progress + result thumbnails, and lets the user reorder/delete uploaded
 * images. Depends on the localized `ajax_vars` object and jQuery UI sortable.
 */
// Running count of images currently uploaded (used to enforce max_images).
var current_no_up;
// The active Plupload uploader instance.
var uploader;
// Which button triggered the upload: 'general' (gallery) or 'single'.
var upload_by;
// What kind of asset is being uploaded: 'image', 'video-trailer', 'video-preview'.
var upload_type;

/**
 * Placeholder hook for setting a thumbnail. Currently a no-op.
 *
 * @return {void}
 */
function thumb_setter() {
    "use strict";
    return;

}

/**
 * (Re)bind the trash-icon click handler for uploaded gallery images.
 * Removes the clicked image from the DOM and rebuilds the hidden attach-id list.
 *
 * @return {void}
 */
function delete_binder() {
    "use strict";
    // Clear any previous click handlers to avoid double-binding.
    jQuery('#wpstream_imagelist i').unbind('click');
    jQuery('#wpstream_imagelist i.fa-trash-alt').on('click',function () {
        var curent = '';
        var remove='';


        // Attachment id of the image being removed, plus the upload nonce.
        var img_remove= jQuery(this).parent().attr('data-imageid');
        var nonce = jQuery('#wpstream_theme_image_upload').val();
        // Decrement the running upload counter.
        current_no_up=current_no_up-1;

        // Drop the image element from the DOM.
        jQuery(this).parent().remove();

        // Rebuild the comma-separated list of remaining attachment ids.
        jQuery('#wpstream_imagelist .wpstream_uploaded_images').each(function () {
            curent = curent + ',' + jQuery(this).attr('data-imageid');
        });
        jQuery('#attachid').val(curent);

        // Early return: the server-side delete below is intentionally disabled.
        return;
        var ajaxurl     =   ajax_vars.ajaxurl;
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                'action'            :   'wpstream_theme_delete_file',
                'attach_id'         :   img_remove,
                'security'          :   nonce

            },
            success: function (data) {


            },
            error: function (errorThrown) {}
        });//end ajax

    });
}

/**
 * Bind click handlers to the various upload buttons. Each handler records the
 * upload context (upload_by / upload_type) and kicks off the uploader.
 *
 * @return {void}
 */
function wpstream_bind_click_events(){
    // Gallery (multi) image uploader.
    jQuery('#aaiu-uploader').on('click',function (e) {
        uploader.start();
        upload_by = 'general';
        upload_type = 'image';
        e.preventDefault();
    });


    // Single image uploader (e.g. profile / featured image).
    jQuery('#aaiu-uploader-single').on('click',function (e) {
        uploader.start();
        upload_by = 'single';
        upload_type = 'image';
        e.preventDefault();
    });

    // Trailer video uploader.
    jQuery('#aaiu-uploader-trailer').on('click',function (e) {
        uploader.start();
        upload_by='single';
        upload_type='video-trailer';
        e.preventDefault();
    });

    // Preview video uploader.
    jQuery('#aaiu-uploader-preview').on('click',function (e) {
        uploader.start();
        upload_by='single';
        upload_type='video-preview';
        e.preventDefault();
    })
}

/**
 * Configure and initialize the Plupload uploader and its lifecycle callbacks
 * (FilesAdded, UploadProgress, Error, FileUploaded). Sets target list ids and
 * multi-selection based on the current upload context.
 *
 * @return {void}
 */
function wpstream_plp_uploader(){
    // Tracks whether the max-images warning needs to be shown.
    var should_warn=0;
    // Only proceed when Plupload and its config are available.
    if (typeof (plupload) !== 'undefined' && ajax_vars.plupload!=='undefined') {
        // Profile-image context forces single selection.
        if( jQuery('#profile-image') .length>0){
            console.log(  ajax_vars.plupload.multi_selection=false);
        }

        // Default target container ids for the gallery uploader.
        var wpstream_imagelist="wpstream_imagelist";
        var aaiu_upload_imagelist="aaiu-upload-imagelist"

        // Adjust selection mode + target ids based on the upload context.
        if( upload_by === 'single' && upload_type === 'image'){
            ajax_vars.plupload.multi_selection=false
            wpstream_imagelist      =   "wpstream_imagelist_single"
            aaiu_upload_imagelist   =   "aaiu-upload-imagelist_single";
        }else if( upload_by === 'general' && upload_type === 'image'){
            ajax_vars.plupload.multi_selection=true
        }

        // Build the uploader from the localized Plupload settings.
        uploader = new plupload.Uploader(ajax_vars.plupload);


        uploader.init();
        // FilesAdded: enforce the image cap and list queued files.
        uploader.bind('FilesAdded', function (up, files) {

            // Image cap logic only applies to image uploads.
            if ( upload_type === 'image' ) {
                if (ajax_vars.max_images > 0) { // if is not unlimited
                    // First batch: how many of these files fit under the cap.
                    if (current_no_up === 0) {
                        array_cut = ajax_vars.max_images;
                        if (files.length > ajax_vars.max_images) {
                            current_no_up = array_cut;
                        } else {
                            current_no_up = files.length;
                        }
                    } else {
                        // Subsequent batches: compute remaining allowance.
                        if (current_no_up >= ajax_vars.max_images) {
                            array_cut = -1;
                        } else {
                            array_cut = ajax_vars.max_images - current_no_up;
                            if (files.length > array_cut) {
                                current_no_up = current_no_up + array_cut;
                            } else {
                                current_no_up = current_no_up + files.length;
                            }
                        }
                    }

                    // Trim the queue down to the allowed count.
                    if (array_cut > 0) {
                        up.files.slice(0, array_cut);
                        files.slice(0, array_cut);
                        var i = array_cut;
                        // Pop the overflow files and flag the warning.
                        while (files.length > array_cut) {
                            up.files.pop();
                            files.pop();
                            should_warn = 1;
                        }
                    }

                    // Show the "too many images" warning when we trimmed files.
                    if (should_warn === 1) {
                        jQuery('.image_max_warn').remove();
                        jQuery('#' + wpstream_imagelist).before('<div class="image_max_warn" style="width:100%;float:left;">' + ajax_vars.warning_max + '</div>');
                    }

                    // Cap already reached: warn and abort this batch entirely.
                    if (array_cut == -1) {
                        jQuery('.image_max_warn').remove();
                        jQuery('#' + wpstream_imagelist).before('<div class="image_max_warn" style="width:100%;float:left;">' + ajax_vars.warning_max + '</div>');
                        files = [];
                        up = [];
                        return;
                    }

                }

                // List each queued file with a spot for its progress percent.
                jQuery.each(files, function (i, file) {
                    jQuery('#' + aaiu_upload_imagelist).append('<div id="' + file.id + '">' + file.name + ' (' + plupload.formatSize(file.size) + ') <b></b>' + '</div>');
                });
            }

            up.refresh(); // Reposition Flash/Silverlight
            // Begin uploading the accepted files.
            uploader.start();
        });

        // UploadProgress: update the per-file percentage label.
        uploader.bind('UploadProgress', function (up, file) {
            console.log ('UploadProgress .....');
            jQuery('#' + file.id + " b").html(file.percent + "%");
        });

        // On erro occur
        // Error: surface a server message when available, else a generic one.
        uploader.bind('Error', function (up, err) {
            console.log ('Error .....');
	        // Default message shown if we cannot extract a specific one.
	        let errorMessage = "An error occurred during upload.";
			// Try to parse a JSON error body and pull out its message.
			if ( typeof err.response === 'string' && err.response.trim() !== '' ) {
				try {
					const parsedResponse = JSON.parse(err.response);
					if ( parsedResponse && parsedResponse.data && parsedResponse.data.message ) {
						errorMessage = parsedResponse.data.message;
					}
				} catch ( e ) {
					// If response is not a valid JSON, keep the default error message
				}
			}
			jQuery('#'+aaiu_upload_imagelist).append(
				`<div>Error: ${err.code}, Message: ${errorMessage} ${err.file ? `, File: ${err.file.name}` : ''}</div>`
			);
            up.refresh(); // Reposition Flash/Silverlight
        });

        // FileUploaded: process the server response for one finished file.
        uploader.bind('FileUploaded', function (up, file, response) {
            console.log ('FileUploaded .....');
            // Current count of rendered image thumbnails.
            var current_no_up2=  parseInt( jQuery('.wpstream_uploaded_images ').length,10);


            // Re-resolve target ids/selection for the single vs gallery case.
            if( upload_by === 'single' && upload_type === 'image' ){
                ajax_vars.plupload.multi_selection=false
                wpstream_imagelist      =   "wpstream_imagelist_single"
                aaiu_upload_imagelist   =   "aaiu-upload-imagelist_single";

                jQuery('#wpstream_imagelist_single .wpstream_uploaded_images').remove();
            }else if( upload_by === 'general' && upload_type === 'image' ){
                ajax_vars.plupload.multi_selection=true
                wpstream_imagelist="wpstream_imagelist";
                aaiu_upload_imagelist="aaiu-upload-imagelist"
            }

            // Stop if adding this file would exceed the image cap.
            if(ajax_vars.max_images>0 && current_no_up2> ajax_vars.max_images){
                return;
            }

            // Parse the JSON payload and clear the placeholder row.
            result = jQuery.parseJSON(response.response);
            jQuery('#image_warn').remove();
            jQuery('#' + file.id).remove();
            if (result.success) {

                // Video uploads: swap in the new trailer/preview player.
                if ( file.type === 'video/mp4' ) {
                    // Get the video type (trailer or preview), remove the existing preview
                    // and append the new uploaded video
                    const [ , videoType ] = upload_type.split('video-');
                    const containerId = `wpstream_${videoType}list`;
                    const videoClass = `wpstream_uploaded_${videoType}_video`;

                    jQuery(`#wpstream_uploaded_${videoType}`).remove();
                    jQuery(`#${containerId} .${videoClass}`).remove();
                    jQuery(`#${containerId}`).append(
                        `<div class="${videoClass}" data-videoid="${result.attach}">` +
                        `<video height="240" controls><source src="${result.html}" type="video/mp4"></video></div>`
                    )
                }

                // Update profile-image previews across the dashboard chrome.
                jQuery('#profile-image,#dashboard-header_profile-image').attr('src', result.profile_image);
                jQuery('#dashboard-header_profile-image-menu').attr('src', result.profile_image);
                wpstream_dashboard_user_menu();

                // Store the new image URLs/ids on the profile-image element.
                jQuery('#profile-image').attr('data-profileurl', result.html);
                jQuery('#profile-image').attr('data-smallprofileurl', result.attach);

                jQuery('#wpstream_remove_profile').attr('data-image-id',result.attach);

                jQuery('#profile-image-input').val(result.html);
                jQuery('#profile-image-input-small').val(result.attach);

                jQuery('#wpstream_uploaded_profile_image').attr('src',result.html);
                jQuery('#wpstream_uploaded_profile_image').attr('data-imageid', result.attach)

                // Gallery uploads: append the new id to the hidden attach list.
                if( upload_by == 'general'){
                    all_id = jQuery('#attachid').val();
                    all_id = all_id + "," + result.attach;
                    jQuery('#attachid').val(all_id);
                }

                // Image uploads: render the new thumbnail and (re)enable sorting.
                if ( upload_type == 'image' ) {
                    if (result.html !== '') {
                        jQuery('#' + wpstream_imagelist).append('<div class="wpstream_uploaded_images" data-imageid="' + result.attach + '"><img src="' + result.html + '"  /><i class="far wpstream_delete_image fa-trash-alt"></i> </div>');
                    }

                    // Make the gallery sortable; rebuild attach-id order on change.
                    jQuery("#wpstream_imagelist").sortable({
                        revert: true,
                        update: function (event, ui) {
                            var all_id, new_id;
                            all_id = "";
                            jQuery("#wpstream_imagelist .wpstream_uploaded_images").each(function () {

                                new_id = jQuery(this).attr('data-imageid');
                                if (typeof new_id != 'undefined') {
                                    all_id = all_id + "," + new_id;

                                }

                            });

                            jQuery('#attachid').val(all_id);
                        },
                    });

                    // Re-bind delete handlers for the updated image set.
                    delete_binder();
                    // thumb_setter();
                }
            }else{

                // Server rejected the image: show the validation warning.
                if (result.image){
                    jQuery('#'+wpstream_imagelist).before('<div id="image_warn" style="width:100%;float:left;">'+ajax_vars.warning+'</div>');
                }
            }
        });

    }
}



// On DOM ready: wire up the uploader when its container is on the page.
jQuery(document).ready(function ($) {
    "use strict";
    var all_id, uploader, result;

    // ajax_vars is not available when the page does not contain a media uploader
    // ajax_vars is needed for these two functions
    if ( document.getElementById('aaiu-upload-container') !== null ) {
        // Initialize Plupload and bind delete handlers on existing images.
        wpstream_plp_uploader();
        delete_binder();
    }
    // Always bind the upload buttons.
    wpstream_bind_click_events()

    var array_cut;

    // Seed the running count from any pre-rendered thumbnails.
    current_no_up =  parseInt( jQuery('.wpstream_uploaded_images ').length,10);
    array_cut=0;
});


