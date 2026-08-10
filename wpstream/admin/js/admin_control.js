/*global $, jQuery, */
/*
 * WpStream admin control — product edit + storage upload behaviours.
 *
 * Loaded on the WooCommerce product edit and WpStream storage admin screens.
 * Responsibilities:
 *  - Dismissing WpStream admin notices via AJAX.
 *  - The direct-to-cloud file upload flow, including chunked S3-style multipart
 *    upload with retry for files over the 5GB standard-upload limit.
 *  - Toggling product-type-specific metabox fields (live stream / VOD / bundle).
 *  - WordPress media-library pickers for category images, external/recorded VOD
 *    sources, VTT captions, and the player logo.
 * Server strings and endpoints come from the localized `wpstream_admin_control_vars`.
 */
// Per-page counter store (used by helper code).
var counters={};

// Multipart chunk size: 128MB per part.
const CHUNK_SIZE = 128 * 1024 * 1024; // 128MB in bytes
// Files larger than this (5GB) must use the multipart upload path.
const MAX_STANDARD_UPLOAD_SIZE = 5 * 1000000000; // 5GB in bytes
// Maximum number of retry attempts for a failed chunk.
const MAX_RETRIES = 3;
// Delay (ms) between chunk retry attempts.
const RETRY_DELAY = 5000;

// Main admin bootstrap: runs once the DOM is ready.
jQuery(document).ready(function ($) {
    "use strict";


    // Wire up the shared download/delete link handlers for stored recordings.
    WpStreamUtils.generate_download_link();
    WpStreamUtils.generate_delete_link();
    // Bind the recorded/external video and caption media pickers.
    wpstream_handle_video_selection();
	wpstream_handle_caption_selection();
    // Bind category featured-image uploader and the player-logo uploader.
    wpstream_upload_images_in_wpadmin();

    wpstream_upload_player_logo();


    /**
     * Toggle a social-media settings sub-panel based on its checkbox.
     *
     * @param {string} social_class - Base CSS class for the checkbox/container pair.
     * @return {void}
     */
    function social_media_toggle(social_class){

        // When the checkbox for this social class changes, slide its container.
        jQuery('.'+social_class).on('change',function(){

            // Checked: reveal the matching container; unchecked: hide it.
            if( $(this).prop('checked') ){
                jQuery($(this).parent().parent().find( '.'+social_class+'_container' )).slideDown('100');
            }else{
                jQuery($(this).parent().parent().find( '.'+social_class+'_container' )).slideUp('100');
            }

        });
    }
    
    
    
    // Dismiss a WpStream admin notice: persist the dismissal server-side.
    jQuery('.wpstream_notices .notice-dismiss').on('click',function(){

        // Build the admin-ajax endpoint URL.
        var ajaxurl     = wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        // Which notice was dismissed (from the parent's data attribute).
        var notice_type = $(this).parent().attr('data-notice-type');
        // Security nonce for the dismissal request.
        var nonce       = $('#wpstream_notice_nonce').val();


        // Tell the server to remember this notice was dismissed.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                'action'                    :   'wpstream_update_cache_notice',
                'notice_type'               :   notice_type,
                'security'                  :   nonce
            },
            success: function (data) {
                // No UI action needed on success.

            },
            error: function (errorThrown) {
                // Errors are silently ignored.

            }
        });
    });

    // Custom styled file inputs: reflect the chosen file name in the label.
    $( '.inputfile' ).each( function(){
		// Cache the input, its label, and the label's original markup.
		var $input	 = $( this ),
			$label	 = $input.next( 'label' ),
			labelVal = $label.html();

		// On file selection, compute the display name.
		$input.on( 'change', function( e )
		{
			var fileName = '';

			// Multiple files: use the data-multiple-caption template with the count.
			if( this.files && this.files.length > 1 )
				fileName = ( this.getAttribute( 'data-multiple-caption' ) || '' ).replace( '{count}', this.files.length );
			// Single file: take the base name from the input's value path.
			else if( e.target.value )
				fileName = e.target.value.split( '\\' ).pop();

			// Show the file name, or restore the original label if none.
			if( fileName )
				$label.find( 'span' ).html( fileName );
			else
				$label.html( labelVal );
		});

		// Firefox bug fix
		// Track focus state on the input so it can be styled while focused.
		$input
		.on( 'focus', function(){ $input.addClass( 'has-focus' ); })
		.on( 'blur', function(){ $input.removeClass( 'has-focus' ); });
    });
    


    /*
    *
    * File Upload
    *
    */


    // The direct-upload form and multipart upload state shared across handlers.
    var form = $('.direct-upload');
    // Holds multipart part URLs once a multipart upload is initiated (null = standard upload).
    var multipartUploadData = null;
    // Server-side handle identifying the in-progress multipart upload.
    var handle = null;
    // Accumulates the parts that have been uploaded successfully.
    var currentUploadedParts = [];

    // Configure the jQuery File Upload plugin on the direct-upload form.
    form.fileupload({
        url: form.attr('action'),
        type: form.attr('method'),

        datatype: 'xml',
            // Called when a file is selected/added; validates and starts the upload.
            add: function (event, data) {


               // Reject anything that is not MP4 or QuickTime video.
               if( data.files[0].type!=='video/mp4' && data.files[0].type!=='video/quicktime'){
                   jQuery('#wpstream_uploaded_mes').empty().html(wpstream_admin_control_vars.not_accepted);
                   jQuery('#wpstream_label_action').text(wpstream_admin_control_vars.choose_a_file);
                   return;
               }

                // Get file info
                var file = data.files[0];
                var fileSizeInBytes = file.size;
                // File size in megabytes for quota comparison.
                var file_size = (parseInt(fileSizeInBytes, 10))/1000000;
				// Quota check: either against streaming-hours or storage/bandwidth allowance.
				if ( wpstream_admin_control_vars.use_streaming_hours ) {
					// Streaming-hours model: block upload when the user has no hours left.
					var user_storage_hours = jQuery('#wpstream_storage_hours').val();
					if ( parseFloat(user_storage_hours) <= 0 ) {
						jQuery('#wpstream_uploaded_mes').empty().html(wpstream_admin_control_vars.no_streaming_hours);
						return;
					}
				} else {
					// Storage/bandwidth model: read the remaining allowances.
					var user_storage = jQuery('#wpstream_storage').val();
					var user_band = jQuery('#wpstream_band').val();

					// Block the upload if the file exceeds storage or bandwidth.
					if(file_size > user_storage || file_size > user_band){
						jQuery('#wpstream_uploaded_mes').empty().html(wpstream_admin_control_vars.no_band_no_store);
						return;
					}
				}

                // Update UI
                // Switch the label to the "uploading" state and disable the button.
                $('#wpstream_label_action').text(wpstream_admin_control_vars.uploading);
                $('#wpstream_upload').prop('disabled', true);
                $('label[for="wpstream_upload"]')
                    .css('cursor','not-allowed')
                    .css('background-color','#8c8f94');

                // Clear any previous status message.
                jQuery('#wpstream_uploaded_mes').empty();

                // Show warning message if leaving page during upload
                window.onbeforeunload = function () {
                    return 'You have unsaved changes.';
                };

                // Set content headers
                // Populate the form's content-type/length fields for the upload.
                form.find('input[name="Content-Type"]').val(file.type);
                form.find('input[name="Content-Length"]').val(file.size);

                // Show the progress bar
                // Build a progress bar keyed by file size so progress handlers can find it.
                var bar = $('<div class="progress" data-mod="'+file.size+'"><div class="bar"></div></div>');
                $('.progress-bar-area').append(bar);
                bar.slideDown('fast');

                // Check if file size exceeds 5GB and requires multipart upload
                if (fileSizeInBytes > MAX_STANDARD_UPLOAD_SIZE) {
                    // Show multipart upload message
                    jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.exceeding_limit);
                    // Initiate multipart upload
                    initiateMultipartUpload(file, data);
                } else {
                    // Standard upload for files under 5GB
                    data.submit();
                }
            },
            // Progress callback: updates the bar during a standard (non-multipart) upload.
            progress: function (e, data) {
                // Standard upload progress
                if (!multipartUploadData) {
                    // Compute and render the percentage complete.
                    var percent = Math.round((data.loaded / data.total) * 100);
                    $('.progress[data-mod="'+data.files[0].size+'"] .bar').css('width', percent + '%').html(percent+'%');
                }
            },

            // Upload failure callback.
            fail: function () {
                handleUploadFailure();
            },

            // Upload error callback.
            error: function () {
                handleUploadFailure();
            },
            // Upload done callback: finalize a completed standard upload.
            done: function (event, data) {
                if (!multipartUploadData) {
                    // Handle standard upload completion
                    handleUploadSuccess(data.files[0]);
                }
            }
    });

    /**
     * Initiate a multipart (chunked) upload for a large file.
     *
     * Requests presigned part URLs and an upload handle from the server, then
     * begins uploading chunks. Falls back to failure handling on any error.
     *
     * @param {File} file - The selected file to upload.
     * @param {Object} data - jQuery File Upload data object for this file.
     * @return {void}
     */
    // Function to initiate multipart upload
    function initiateMultipartUpload(file, data) {
        // AJAX endpoint plus file metadata for the request.
        var ajaxurl = wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var fileName = file.name;
        var fileSize = file.size;

        // Calculate number of parts needed
        var numParts = Math.ceil(fileSize / CHUNK_SIZE);

        // Inform the user we are preparing the multipart upload.
        jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.preparing_multipart);

        // Ask the server to initiate the multipart upload and return part URLs.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'wpstream_initiate_multipart_upload',
                'security': wpstream_admin_control_vars.multipart_upload_nonce,
                'file_name': fileName,
                'file_size': fileSize,
                'content_type': file.type,
                'parts': numParts
            },
            success: function(response) {
                if (response.success) {
                    // Validate required data exists in response
                    if (!response.data ||
                        !response.data.multipart ||
                        !response.data.parts ||
                        !response.data.handle
                    ) {
                        // Missing fields: abort with an invalid-response message.
                        jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.invalid_response);
                        handleUploadFailure();
                        return;
                    }

                    // Store the part URLs and reset per-upload state.
                    multipartUploadData = {
                        parts: response.data.parts
                    };
                    currentUploadedParts = [];
                    handle = response.data.handle;

                    // Start uploading chunks
                    uploadNextChunk(file, 0, numParts);
                } else {
                    // Server reported failure: show error and clean up.
                    jQuery('#wpstream_uploaded_mes').html(response.error || wpstream_admin_control_vars.upload_failed);
                    handleUploadFailure();
                }
            },
            error: function() {
                // Transport error initiating the multipart upload.
                jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.upload_failed);
                handleUploadFailure();
            }
        });
    }

    /**
     * Upload a single chunk of the file, then recurse to the next chunk.
     *
     * PUTs the chunk to its presigned URL, updates the progress bar, and on
     * success advances to the next part; on network error it defers to the
     * retry handler. When all parts are done it completes the upload.
     *
     * @param {File} file - The file being uploaded.
     * @param {number} partIndex - Zero-based index of the current chunk.
     * @param {number} totalParts - Total number of chunks.
     * @param {number} [retryCount=0] - Current retry attempt for this chunk.
     * @return {void}
     */
    // Function to upload a chunk of the file
    function uploadNextChunk(
        file,
        partIndex,
        totalParts,
        retryCount = 0
    ) {
        // Base case: every part uploaded, so finalize the multipart upload.
        if (partIndex >= totalParts) {
            // All parts uploaded, complete the multipart upload
            completeMultipartUpload(file, totalParts);
            return;
        }

        // Compute this chunk's byte range and slice it out of the file.
        var start = partIndex * CHUNK_SIZE;
        var end = Math.min((partIndex + 1) * CHUNK_SIZE, file.size);
        var chunk = file.slice(start, end);
        // Part numbers are 1-based for the storage API.
        var partNumber = partIndex + 1;

        // Show which part is currently uploading.
        jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.uploading_part.replace('{part}', partNumber).replace('{total}', totalParts));

        // Update progress bar to show overall progress
        var overallProgress = Math.round((partIndex / totalParts) * 100);
        jQuery('.progress[data-mod="'+file.size+'"] .bar').css('width', overallProgress + '%').html(overallProgress+'%');

        // Upload the chunk
        // PUT the chunk directly to its presigned part URL.
        var xhr = new XMLHttpRequest();
        // xhr.open('POST', 'https://s3.amazonaws.com/' + partData.bucket, true);
        xhr.open('PUT', multipartUploadData.parts[partIndex], true);

        // On completion, check the HTTP status to decide success vs failure.
        xhr.onload = function() {
            // 200/204 indicate the part stored successfully.
            if (xhr.status === 204 || xhr.status === 200) {
                // Record the completed part.
                currentUploadedParts.push({
                    PartNumber: partNumber,
                });

                // Upload next chunk
                uploadNextChunk(file, partIndex + 1, totalParts);
            } else {
                // Non-success status: log details and abort the upload.
                var errorInfo = {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    response: xhr.responseText,
                    headers: xhr.getAllResponseHeaders()
                };
                console.error('Part Upload Failed:', errorInfo);

                jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.upload_failed_part.replace('{part}', partNumber));
                handleUploadFailure();
            }
        };

        // On network error, hand off to the retry logic.
        xhr.onerror = function() {
            handleChunkError(file, partIndex, totalParts, retryCount, xhr);
            // jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.upload_failed_part.replace('{part}', partNumber));
            // handleUploadFailure();
        };

        // Per-chunk progress: blend chunk progress into the overall bar.
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                // Calculate chunk progress and overall progress
                var chunkProgress = (e.loaded / e.total) * 100;
                var overallProgress = Math.round((partIndex / totalParts * 100) + (chunkProgress / totalParts));
                jQuery('.progress[data-mod="'+file.size+'"] .bar').css('width', overallProgress + '%').html(overallProgress+'%');
            }
        };

        // Send the chunk body.
        xhr.send(chunk);
    }

    /**
     * Retry a failed chunk after a delay, up to MAX_RETRIES times.
     *
     * @param {File} file - The file being uploaded.
     * @param {number} partIndex - Zero-based index of the failed chunk.
     * @param {number} totalParts - Total number of chunks.
     * @param {number} retryCount - Number of retries already attempted.
     * @param {XMLHttpRequest} xhr - The failed request (unused; kept for context).
     * @return {void}
     */
    // Function to retry uploading when failing
    // Adding a delay of RETRY_DELAY seconds before retrying
    function handleChunkError(file, partIndex, totalParts, retryCount, xhr) {
        // 1-based part number for user-facing messages.
        var partNumber = partIndex + 1;

        // Retry while under the cap; otherwise give up.
        if ( retryCount < MAX_RETRIES ) {
            // Notify the user this part is being retried.
            jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.upload_failed_part_retry.replace('{part}', partNumber).replace('{times}', retryCount + 1));
            // Re-attempt the same chunk after RETRY_DELAY with an incremented count.
            setTimeout(function() {
                uploadNextChunk(file, partIndex, totalParts, retryCount + 1);
            }, RETRY_DELAY);
        } else {
            // Retries exhausted: abort the whole upload.
            handleUploadFailure();
        }

    }

    /**
     * Finalize a multipart upload once all parts are uploaded.
     *
     * Tells the server to assemble the parts into the final object, then routes
     * to success or failure handling based on the response.
     *
     * @param {File} file - The uploaded file.
     * @param {number} totalParts - Total number of parts uploaded.
     * @return {void}
     */
    // Function to complete multipart upload
    function completeMultipartUpload(file, totalParts) {
        // AJAX endpoint for the completion request.
        var ajaxurl = wpstream_admin_control_vars.admin_url + 'admin-ajax.php';

        // Inform the user the upload is being finalized.
        jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.completing_upload);
        // Ask the server to complete/assemble the multipart upload.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'wpstream_complete_multipart_upload',
                'security': wpstream_admin_control_vars.multipart_upload_nonce,
                'parts': totalParts,
                'file_name': file.name,
                'handle': handle,
            },
            success: function(response) {
                if (response.success) {
                    // Reset multipart data
                    multipartUploadData = null;
                    currentUploadedParts = [];

                    // Handle success
                    handleUploadSuccess(file);
                } else {
                    // Server could not complete the upload.
                    jQuery('#wpstream_uploaded_mes').html(response.error || wpstream_admin_control_vars.upload_failed);
                    handleUploadFailure();
                }
            },
            error: function(e) {
                // Transport error during completion.
                jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.upload_failed);
                handleUploadFailure();
            }
        });
    }

    /**
     * Reset the UI and upload state after any upload failure.
     *
     * @return {void}
     */
    // Handle upload failure
    function handleUploadFailure() {
        // Remove the unsaved-changes guard and the progress bar.
        window.onbeforeunload = null;
        jQuery('.bar').remove();
        // Show the failure messages.
        jQuery('#wpstream_uploaded_mes').empty().html(wpstream_admin_control_vars.upload_failed);
        jQuery('#wpstream_label_action').empty().html(wpstream_admin_control_vars.upload_failed2);
        // Re-enable the upload button and restore its default styling.
        jQuery('#wpstream_upload').prop('disabled', false);
        jQuery('label[for="wpstream_upload"]')
            .css('cursor','')
            .css('background-color','');

        // Reset multipart upload data
        multipartUploadData = null;
        currentUploadedParts = [];
    }

    /**
     * Handle a successful upload: reset UI and insert a pending video row.
     *
     * Sanitizes the file name to mirror the server's stored name, injects a new
     * "processing" video entry into the list, and starts pending-status polling.
     *
     * @param {File} file - The successfully uploaded file.
     * @return {void}
     */
    // Handle upload success
    function handleUploadSuccess(file) {
        // Remove the unsaved-changes guard and the progress bar.
        window.onbeforeunload = null;
        jQuery('.bar').remove();
        // Show the completion messages.
        jQuery('#wpstream_uploaded_mes').empty().html(wpstream_admin_control_vars.upload_complete);
        jQuery('#wpstream_label_action').text(wpstream_admin_control_vars.upload_complete2);
        // Re-enable the upload button and restore its default styling.
        jQuery('#wpstream_upload').prop('disabled', false);
        jQuery('label[for="wpstream_upload"]')
            .css('cursor','')
            .css('background-color','');

        // Capture the original name and size (in MB) of the file.
        var new_file_name = file.name;
        var new_file_size = Math.floor(file.size / 1048576);

        // Sanitize the base name (spaces to underscores, strip non-word chars)
        // and re-attach the extension, matching the server's stored filename.
        var new_file_name_array = new_file_name.split(".");
        var temp_file_name = new_file_name_array[0].split(' ').join('_');
        temp_file_name = temp_file_name.replace(/\W/g, '');
        new_file_name = temp_file_name+'.'+new_file_name_array[new_file_name_array.length-1];

        // Build the new video wrapper markup, ending with a "pending" placeholder.
        var to_insert='<div class="wpstream_video_wrapper"><div class="wpstream_video_title"><div class="wpstream_video_notice"></div></div>';
        to_insert += `<div class="wpstream_video_title"><strong class="storage_file_name">${wpstream_admin_control_vars.file_name_text}</strong><span class="storage_file_name_real">`+new_file_name+`</span><span class="storage_file_size">` + new_file_size + ` MB</span></div>`;
        to_insert += `<div class="wpstream_video_pending">${wpstream_admin_control_vars.video_processing}</div>`;

        // Insert the new entry after the list heading.
        jQuery('#video_management_title').after(to_insert);

        // Begin polling so the pending entry updates once processing completes.
        WpStreamUtils.checkPendingVideos();
    }

    // When the WooCommerce product type changes, reveal the "sold individually"
    // field for WpStream product types.
    jQuery('#product-type').on('change',function(){

        // Read the newly selected product type.
        var product_type= jQuery('#product-type').val();
        // Show the field only for WpStream product types.
        if(product_type==='live_stream' || product_type==='video_on_demand' || product_type==='wpstream_bundle' ){
            jQuery('._sold_individually_field').show();
        }

    });

    // Deep-link support: if arriving with a new VOD name, preselect VOD.
    if(wpstream_findGetParameter('new_video_name')!=='' && wpstream_findGetParameter('new_video_name')!=null ){
        jQuery('#product-type').val('video_on_demand').trigger('change');
    }

    // Deep-link support: if arriving with a new stream flag, preselect live stream.
    if(wpstream_findGetParameter('new_stream')!=='' && wpstream_findGetParameter('new_stream')!=null ){
        jQuery('#product-type').val('live_stream').trigger('change');
    }

    // Reveal the product-type-specific metabox sections for the current type.
    var product_type=  jQuery('#product-type').val();
    if ( product_type === 'video_on_demand' ) {
        // VOD-only fields.
        jQuery('.show_if_video_on_demand' ).show();
    }else  if ( product_type === 'live_stream' ) {
        // Live-stream-only fields.
        jQuery( '.show_if_live_stream' ).show();
    } else  if ( product_type === 'wpstream_bundle' ) {
        // Bundle-only fields, plus force the General tab open.
        jQuery( '.show_if_wpstream_bundle' ).show();
        console.log ('we do click');
        var element= jQuery('.general_tab');
        console.log(element);
        // Trigger clicks to activate the General product data tab.
        jQuery('.general_tab').trigger('click');
          $('a[href="#general_product_data"]').click();
          $('.product_data_tabs .tab.general_tab').click();
    }
            
  

  
    
    /**
     * Read a query-string parameter from the current URL.
     *
     * @param {string} parameterName - The parameter name to look up.
     * @return {string|null} The decoded value, or null if not present.
     */
    function wpstream_findGetParameter(parameterName) {
        // Accumulator for the result and a scratch array for each pair.
        var result = null,
            tmp = [];
        // Split the query string into key=value pairs and scan for a match.
        location.search
            .substr(1)
            .split("&")
            .forEach(function (item) {
              tmp = item.split("=");
              // Store the decoded value when the key matches.
              if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
            });
        return result;
    }


    // Toggle the movie-URL field based on the subscription-event selector.
    jQuery('#_subscript_live_event').change(function(){
        //alert('move it'+product_type);
        // For WpStream product types this control is not applicable; do nothing.
        if ( product_type === 'video_on_demand' || product_type === 'live_stream' || product_type === 'wpstream_bundle' ) {

        }else{
            // Otherwise show the movie-URL field only when subscription is "no".
            var value= jQuery(this).val();
            if(value==="no"){
                jQuery("._movie_url_field").parent().removeClass("hide_if_subscription").show();
            }else{
                jQuery("._movie_url_field").parent().addClass("hide_if_subscription").hide();
            }
        }
    });

    // Apply the subscription-event visibility on initial load.
    jQuery('#_subscript_live_event').trigger('change');


    // Toggle VOD source fields based on the WpStream product-type selector.
    $('#wpstream_product_type').change(function(){
        // Hide all source-specific fields first.
        jQuery('.video_free').hide();
        jQuery('.video_free_external').hide();

        jQuery('.wpstream_option_vod_source').hide();

        // Value "2": recorded/free video source.
        if( jQuery('#wpstream_product_type').val()=== "2"){
            jQuery('.video_free').show();
            jQuery('.wpstream_show_recording').show();
        }
        // Value "3": external video source.
        if( jQuery('#wpstream_product_type').val()=== "3"){
            jQuery('.video_free_external').show();
            jQuery('.wpstream_show_external').show();
        }
    });
    // Apply the product-type visibility on initial load.
    $('#wpstream_product_type').trigger('change');
    





    // Close (end) a live event via AJAX and remove its list row on success.
    $('.close_event').click(function(event){
        // Prevent the default link/button action.
        event.preventDefault();
        // Endpoint, clicked element, parent row, and notification target.
        var ajaxurl             =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var acesta              =   $(this);
        var parent              =   $(this).parent().parent();
        var notification_area   =   $(this).parent().find('.event_list_unit_notificationx');
        // Event/show id and the security nonce.
        var show_id             =   parseFloat( $(this).attr('data-show-id') );
        var nonce               =   $('#wpstream_start_event_nonce').val();
        //$(this).unbind();
        // Show progress text while the request runs.
        notification_area.text('Closing Event');


        // Request the server to close the event.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action'            :   'wpstream_close_event',
                'security'          :   nonce,
                'show_id'           :   show_id
            },
            success: function (data) {
                // Remove the event's row from the list.
                parent.remove();
            },
            error: function (errorThrown) {
                // Errors are silently ignored.

            }
        });

    });
});



/*
* Upload images in admin
*
*/
/**
 * Bind WordPress media-library pickers to admin image upload buttons.
 *
 * For each configured button id, opens the media picker on click and writes the
 * chosen image's URL and id into the sibling hidden fields.
 *
 * @return {void}
 */
function wpstream_upload_images_in_wpadmin(){
    console.log('wpstream_upload_images_in_wpadmin');
    // List of upload button ids to wire up.
    var idList          = ["category_featured_image_button"];

    // Attach a click handler to each button in the list.
    for (var i = 0; i < idList.length; i++) {
        var currentId = idList[i];
        jQuery('#'+currentId).on( 'click', function(event) {
            // Cache the button's parent so we can find its fields.
            var parent=jQuery(this).parent();
            // Open the media picker, then store the selected image's url and id.
            wpstream_admin_return_uploaded_image().then(function(image) {
                parent.find('.wpestate_landing_upload').val(image.url);
                parent.find('.wpestate_landing_upload_id').val(image.id);

            });

        });

    }
}


/*
* return uploaded image
*
*/
/**
 * Open the WordPress media library and resolve with the inserted image.
 *
 * Note: this function is defined twice in this file (duplicate declaration).
 *
 * @return {Promise<Object>} Resolves with the selected attachment's JSON.
 */
function wpstream_admin_return_uploaded_image(){

    // Wrap the media picker in a promise resolved on image insert.
    return new Promise(function(resolve, reject) {
        // Create a media frame for inserting a single image.
        var mediaUploader = wp.media({
          frame: "post",
          state: "insert",
          multiple: false
        });

        // On insert, resolve with the chosen attachment's data.
        mediaUploader.on("insert", function(){
          var image = mediaUploader.state().get("selection").first().toJSON();
          resolve(image);
        });

        // Open the media frame.
        mediaUploader.open();
      });
}





/*
* handle video selection for recording
*
*/

/**
 * Bind the media picker to the external/recorded free-video button.
 *
 * On click, opens the media library and writes the chosen URL into the
 * external free-video field.
 *
 * @return {void}
 */
function wpstream_handle_video_selection(){

    // When the external free-video button is clicked, pick a media URL.
    jQuery('#wpstream_free_video_external_button').on( 'click', function(event) {
        // Cache the button's parent for locating the target field.
        var parent=jQuery(this).parent();
            // Open the media picker and store the selected image's URL.
            wpstream_admin_return_uploaded_image().then(function(image) {
                parent.find('#wpstream_free_video_external').val(image.url);
            });
    });
}

/*
* handle caption selection for recording
 */
/**
 * Bind VTT caption selection and removal for the VOD captions field.
 *
 * Opens a media picker restricted to text/vtt files, stores the chosen file's
 * URL/name, and wires an add/remove toggle for the caption.
 *
 * @return {void}
 */
function wpstream_handle_caption_selection(){
	// Open a VTT-only media picker when the captions button is clicked.
	jQuery('#wpstream_vod_captions_url_button').on( 'click', function(event) {
		// Prevent the default button action.
		event.preventDefault();
		// Cache the parent container and the button itself.
		var parent = jQuery(this).parent();
		var button = jQuery(this);

		// Create a media frame limited to VTT caption files.
		var mediaUploader = wp.media({
			title: wpstream_admin_control_vars.select_caption_file,
			button: {
				text: 'Select'
			},
			multiple: false,
			library: {
				type: 'text/vtt'
			}
		});

		// On selection, store the caption file and update the display.
		mediaUploader.on("select", function(){
			// Grab the chosen attachment's data.
			var attachment = mediaUploader.state().get("selection").first().toJSON();
			parent.find('#wpstream_closed_captions_file').val(attachment.url);
			parent.find('.wpstream_caption_file_display').text(attachment.filename);

			// Hide the select button now that a caption is chosen.
			button.hide();

			// Add a "remove caption" button if one is not already present.
			if( parent.find('.wpstream_remove_caption').length === 0 ){
				parent.append('<input type="button" class="button wpstream_remove_caption" value="' + wpstream_admin_control_vars.remove_button + '" style="margin-left: 5px;" />');
			}
		});

		// Open the media frame.
		mediaUploader.open();
	});

	// Delegated handler: remove a selected caption and restore the picker button.
	jQuery(document).on('click', '.wpstream_remove_caption', function(e){
		// Prevent the default button action.
		e.preventDefault();
		// Clear the stored caption URL and display text.
		var parent = jQuery(this).parent();
		parent.find('#wpstream_closed_captions_file').val('');
		parent.find('.wpstream_caption_file_display').text('');

		// Show the caption picker button again.
		parent.find('#wpstream_vod_captions_url_button').show();

		// Remove this "remove" button.
		jQuery(this).remove();
	});
}

/*
* return uploaded image
*
*/
/**
 * Open the WordPress media library and resolve with the inserted image.
 *
 * Note: duplicate definition of the same-named function earlier in this file;
 * this later declaration is the one that takes effect at runtime.
 *
 * @return {Promise<Object>} Resolves with the selected attachment's JSON.
 */
function wpstream_admin_return_uploaded_image(){


    // Wrap the media picker in a promise resolved on image insert.
    return new Promise(function(resolve, reject) {
        // Create a media frame for inserting a single image.
        var mediaUploader = wp.media({
        frame: "post",
        state: "insert",
        multiple: false
        });

        // On insert, resolve with the chosen attachment's data.
        mediaUploader.on("insert", function(){
        var image = mediaUploader.state().get("selection").first().toJSON();
        resolve(image);
        });

        // Open the media frame.
        mediaUploader.open();
    });
}

/**
 * Wire the player-logo image upload and remove buttons.
 *
 * The upload button opens a (lazily-created) media picker and shows a preview;
 * the remove button clears the stored value and hides the preview.
 *
 * @return {void}
 */
function wpstream_upload_player_logo(){
    // Shared media frame instance, created on first use.
    var mediaUploader;

    // Handle upload button click
    jQuery('.wpstream-upload-image').on('click', function(e) {
        // Prevent the default button action.
        e.preventDefault();

        // Resolve the wrapper and its related fields/preview/remove button.
        var button = jQuery(this);
        var wrapper = button.closest('.wpstream-image-upload-wrapper');
        var inputField = wrapper.find('input[type="hidden"]');
        var previewArea = wrapper.find('.wpstream-image-preview');
        var removeButton = wrapper.find('.wpstream-remove-image');

        // Create media uploader instance if not already created
        if (!mediaUploader) {
            mediaUploader = wp.media({
                title: wpstream_settings_vars.choose_image_text || 'Choose Image',
                button: {
                    text: wpstream_settings_vars.select_image_text || 'Select Image'
                },
                multiple: false
            });

            // When image is selected in the media uploader
            mediaUploader.on('select', function() {
                // Store the selected image URL and populate the preview.
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                inputField.val(attachment.url);

                previewArea.find('img').attr('src', attachment.url);
                previewArea.show();
                removeButton.show();
            });
        }

        // Open the media uploader
        mediaUploader.open();
    });

    // Handle remove button click
    jQuery('.wpstream-remove-image').on('click', function(e) {
        // Prevent the default button action.
        e.preventDefault();

        // Resolve the wrapper and its field/preview.
        var button = jQuery(this);
        var wrapper = button.closest('.wpstream-image-upload-wrapper');
        var inputField = wrapper.find('input[type="hidden"]');
        var previewArea = wrapper.find('.wpstream-image-preview');

        // Clear the stored logo and hide the preview/remove button.
        inputField.val('');
        previewArea.hide();
        button.hide();
    });
}