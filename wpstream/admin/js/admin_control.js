/*global $, jQuery, */
var counters={};

const CHUNK_SIZE = 128 * 1024 * 1024; // 128MB in bytes
const MAX_STANDARD_UPLOAD_SIZE = 5 * 1000000000; // 5GB in bytes
const MAX_RETRIES = 3;
const RETRY_DELAY = 5000;

jQuery(document).ready(function ($) {
    "use strict";
    

    WpStreamUtils.generate_download_link();
    WpStreamUtils.generate_delete_link();
    wpstream_handle_video_selection();    
    wpstream_upload_images_in_wpadmin();
 
    
    function social_media_toggle(social_class){
        
        jQuery('.'+social_class).on('change',function(){

            if( $(this).prop('checked') ){
                jQuery($(this).parent().parent().find( '.'+social_class+'_container' )).slideDown('100');
            }else{
                jQuery($(this).parent().parent().find( '.'+social_class+'_container' )).slideUp('100');
            }  

        });
    }
    
    
    
    jQuery('.wpstream_notices .notice-dismiss').on('click',function(){
       
        var ajaxurl     = wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var notice_type = $(this).parent().attr('data-notice-type');
        var nonce       = $('#wpstream_notice_nonce').val();
        

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                'action'                    :   'wpstream_update_cache_notice',
                'notice_type'               :   notice_type,
                'security'                  :   nonce
            },
            success: function (data) {     

            
            },
            error: function (errorThrown) { 
              
            }
        });
    });

    $( '.inputfile' ).each( function(){
		var $input	 = $( this ),
			$label	 = $input.next( 'label' ),
			labelVal = $label.html();

		$input.on( 'change', function( e )
		{
			var fileName = '';

			if( this.files && this.files.length > 1 )
				fileName = ( this.getAttribute( 'data-multiple-caption' ) || '' ).replace( '{count}', this.files.length );
			else if( e.target.value )
				fileName = e.target.value.split( '\\' ).pop();

			if( fileName )
				$label.find( 'span' ).html( fileName );
			else
				$label.html( labelVal );
		});

		// Firefox bug fix
		$input
		.on( 'focus', function(){ $input.addClass( 'has-focus' ); })
		.on( 'blur', function(){ $input.removeClass( 'has-focus' ); });
    });
    


    /*
    *
    * File Upload
    * 
    */


    var form = $('.direct-upload');
    var multipartUploadData = null;
    var handle = null;
    var currentUploadedParts = [];

    form.fileupload({
        url: form.attr('action'),
        type: form.attr('method'),
       
        datatype: 'xml',
            add: function (event, data) {


               if( data.files[0].type!=='video/mp4' && data.files[0].type!=='video/quicktime'){
                   jQuery('#wpstream_uploaded_mes').empty().html(wpstream_admin_control_vars.not_accepted);
                   jQuery('#wpstream_label_action').text(wpstream_admin_control_vars.choose_a_file);
                   return;
               }

                // Get file info
                var file = data.files[0];
                var fileSizeInBytes = file.size;
                var file_size = (parseInt(fileSizeInBytes, 10))/1000000;
                var user_storage = jQuery('#wpstream_storage').val();
                var user_band = jQuery('#wpstream_band').val();

                if(file_size > user_storage || file_size > user_band){
                    jQuery('#wpstream_uploaded_mes').empty().html(wpstream_admin_control_vars.no_band_no_store);
                    return;
                }
                
                // Update UI
                $('#wpstream_label_action').text(wpstream_admin_control_vars.uploading);
                $('#wpstream_upload').prop('disabled', true);
                $('label[for="wpstream_upload"]')
                    .css('cursor','not-allowed')
                    .css('background-color','#8c8f94');

                jQuery('#wpstream_uploaded_mes').empty();
                
                // Show warning message if leaving page during upload
                window.onbeforeunload = function () {
                    return 'You have unsaved changes.';
                };
                
                // Set content headers
                form.find('input[name="Content-Type"]').val(file.type);
                form.find('input[name="Content-Length"]').val(file.size);

                // Show the progress bar
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
            progress: function (e, data) {
                // Standard upload progress
                if (!multipartUploadData) {
                    var percent = Math.round((data.loaded / data.total) * 100);
                    $('.progress[data-mod="'+data.files[0].size+'"] .bar').css('width', percent + '%').html(percent+'%');
                }
            },

            fail: function () {
                handleUploadFailure();
            },

            error: function () {
                handleUploadFailure();
            },
            done: function (event, data) {
                if (!multipartUploadData) {
                    // Handle standard upload completion
                    handleUploadSuccess(data.files[0]);
                }
            }
    });

    // Function to initiate multipart upload
    function initiateMultipartUpload(file, data) {
        var ajaxurl = wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var fileName = file.name;
        var fileSize = file.size;

        // Calculate number of parts needed
        var numParts = Math.ceil(fileSize / CHUNK_SIZE);

        jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.preparing_multipart);

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'wpstream_initiate_multipart_upload',
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
                        jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.invalid_response);
                        handleUploadFailure();
                        return;
                    }

                    multipartUploadData = {
                        parts: response.data.parts
                    };
                    currentUploadedParts = [];
                    handle = response.data.handle;

                    // Start uploading chunks
                    uploadNextChunk(file, 0, numParts);
                } else {
                    jQuery('#wpstream_uploaded_mes').html(response.error || wpstream_admin_control_vars.upload_failed);
                    handleUploadFailure();
                }
            },
            error: function() {
                jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.upload_failed);
                handleUploadFailure();
            }
        });
    }

    // Function to upload a chunk of the file
    function uploadNextChunk(
        file,
        partIndex,
        totalParts,
        retryCount = 0
    ) {
        if (partIndex >= totalParts) {
            // All parts uploaded, complete the multipart upload
            completeMultipartUpload(file, totalParts);
            return;
        }

        var start = partIndex * CHUNK_SIZE;
        var end = Math.min((partIndex + 1) * CHUNK_SIZE, file.size);
        var chunk = file.slice(start, end);
        var partNumber = partIndex + 1;

        jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.uploading_part.replace('{part}', partNumber).replace('{total}', totalParts));

        // Update progress bar to show overall progress
        var overallProgress = Math.round((partIndex / totalParts) * 100);
        jQuery('.progress[data-mod="'+file.size+'"] .bar').css('width', overallProgress + '%').html(overallProgress+'%');

        // Upload the chunk
        var xhr = new XMLHttpRequest();
        // xhr.open('POST', 'https://s3.amazonaws.com/' + partData.bucket, true);
        xhr.open('PUT', multipartUploadData.parts[partIndex], true);

        xhr.onload = function() {
            if (xhr.status === 204 || xhr.status === 200) {
                currentUploadedParts.push({
                    PartNumber: partNumber,
                });

                // Upload next chunk
                uploadNextChunk(file, partIndex + 1, totalParts);
            } else {
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

        xhr.onerror = function() {
            handleChunkError(file, partIndex, totalParts, retryCount, xhr);
            // jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.upload_failed_part.replace('{part}', partNumber));
            // handleUploadFailure();
        };

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                // Calculate chunk progress and overall progress
                var chunkProgress = (e.loaded / e.total) * 100;
                var overallProgress = Math.round((partIndex / totalParts * 100) + (chunkProgress / totalParts));
                jQuery('.progress[data-mod="'+file.size+'"] .bar').css('width', overallProgress + '%').html(overallProgress+'%');
            }
        };

        xhr.send(chunk);
    }

    // Function to retry uploading when failing
    // Adding a delay of RETRY_DELAY seconds before retrying
    function handleChunkError(file, partIndex, totalParts, retryCount, xhr) {
        var partNumber = partIndex + 1;

        if ( retryCount < MAX_RETRIES ) {
            jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.upload_failed_part_retry.replace('{part}', partNumber).replace('{times}', retryCount + 1));
            setTimeout(function() {
                uploadNextChunk(file, partIndex, totalParts, retryCount + 1);
            }, RETRY_DELAY);
        } else {
            handleUploadFailure();
        }

    }

    // Function to complete multipart upload
    function completeMultipartUpload(file, totalParts) {
        var ajaxurl = wpstream_admin_control_vars.admin_url + 'admin-ajax.php';

        jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.completing_upload);
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'wpstream_complete_multipart_upload',
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
                    jQuery('#wpstream_uploaded_mes').html(response.error || wpstream_admin_control_vars.upload_failed);
                    handleUploadFailure();
                }
            },
            error: function(e) {
                jQuery('#wpstream_uploaded_mes').html(wpstream_admin_control_vars.upload_failed);
                handleUploadFailure();
            }
        });
    }

    // Handle upload failure
    function handleUploadFailure() {
        window.onbeforeunload = null;
        jQuery('.bar').remove();
        jQuery('#wpstream_uploaded_mes').empty().html(wpstream_admin_control_vars.upload_failed);
        jQuery('#wpstream_label_action').empty().html(wpstream_admin_control_vars.upload_failed2);
        jQuery('#wpstream_upload').prop('disabled', false);
        jQuery('label[for="wpstream_upload"]')
            .css('cursor','')
            .css('background-color','');

        // Reset multipart upload data
        multipartUploadData = null;
        currentUploadedParts = [];
    }

    // Handle upload success
    function handleUploadSuccess(file) {
        window.onbeforeunload = null;
        jQuery('.bar').remove();
        jQuery('#wpstream_uploaded_mes').empty().html(wpstream_admin_control_vars.upload_complete);
        jQuery('#wpstream_label_action').text(wpstream_admin_control_vars.upload_complete2);
        jQuery('#wpstream_upload').prop('disabled', false);
        jQuery('label[for="wpstream_upload"]')
            .css('cursor','')
            .css('background-color','');

        var new_file_name = file.name;
        var new_file_size = Math.floor(file.size / 1048576);

        var new_file_name_array = new_file_name.split(".");
        var temp_file_name = new_file_name_array[0].split(' ').join('_');
        temp_file_name = temp_file_name.replace(/\W/g, '');
        new_file_name = temp_file_name+'.'+new_file_name_array[new_file_name_array.length-1];

        var to_insert='<div class="wpstream_video_wrapper"><div class="wpstream_video_title"><div class="wpstream_video_notice"></div></div>';
        to_insert += `<div class="wpstream_video_title"><strong class="storage_file_name">${wpstream_admin_control_vars.file_name_text}</strong><span class="storage_file_name_real">`+new_file_name+`</span><span class="storage_file_size">` + new_file_size + ` MB</span></div>`;
        to_insert += `<div class="wpstream_video_pending">${wpstream_admin_control_vars.video_processing}</div>`;

        jQuery('#video_management_title').after(to_insert);

        WpStreamUtils.checkPendingVideos();
    }

    jQuery('#product-type').on('change',function(){
        
        var product_type= jQuery('#product-type').val();
        if(product_type==='live_stream' || product_type==='video_on_demand' || product_type==='wpstream_bundle' ){
            jQuery('._sold_individually_field').show();
        }
        
    });
    
    if(wpstream_findGetParameter('new_video_name')!=='' && wpstream_findGetParameter('new_video_name')!=null ){
        jQuery('#product-type').val('video_on_demand').trigger('change');
    }
    
    if(wpstream_findGetParameter('new_stream')!=='' && wpstream_findGetParameter('new_stream')!=null ){
        jQuery('#product-type').val('live_stream').trigger('change');
    }
    
    var product_type=  jQuery('#product-type').val();
    if ( product_type === 'video_on_demand' ) {
        jQuery('.show_if_video_on_demand' ).show();      
    }else  if ( product_type === 'live_stream' ) {
        jQuery( '.show_if_live_stream' ).show();
    } else  if ( product_type === 'wpstream_bundle' ) {
        jQuery( '.show_if_wpstream_bundle' ).show();   
        console.log ('we do click');
        var element= jQuery('.general_tab');
        console.log(element);
        jQuery('.general_tab').trigger('click');   
          $('a[href="#general_product_data"]').click();
          $('.product_data_tabs .tab.general_tab').click();
    }
            
  

  
    
    function wpstream_findGetParameter(parameterName) {
        var result = null,
            tmp = [];
        location.search
            .substr(1)
            .split("&")
            .forEach(function (item) {
              tmp = item.split("=");
              if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
            });
        return result;
    }


    jQuery('#_subscript_live_event').change(function(){
        //alert('move it'+product_type);
        if ( product_type === 'video_on_demand' || product_type === 'live_stream' || product_type === 'wpstream_bundle' ) {
        
        }else{
            var value= jQuery(this).val();     
            if(value==="no"){
                jQuery("._movie_url_field").parent().removeClass("hide_if_subscription").show();
            }else{
                jQuery("._movie_url_field").parent().addClass("hide_if_subscription").hide();
            }
        }
    });

    jQuery('#_subscript_live_event').trigger('change');
   

    $('#wpstream_product_type').change(function(){
        jQuery('.video_free').hide();
        jQuery('.video_free_external').hide();

        jQuery('.wpstream_option_vod_source').hide();
  
        if( jQuery('#wpstream_product_type').val()=== "2"){
            jQuery('.video_free').show();
            jQuery('.wpstream_show_recording').show();
        }
        if( jQuery('#wpstream_product_type').val()=== "3"){
            jQuery('.video_free_external').show();
            jQuery('.wpstream_show_external').show();
        }
    });
    $('#wpstream_product_type').trigger('change');
    





    $('.close_event').click(function(event){
        event.preventDefault();
        var ajaxurl             =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var acesta              =   $(this);
        var parent              =   $(this).parent().parent();
        var notification_area   =   $(this).parent().find('.event_list_unit_notificationx');
        var show_id             =   parseFloat( $(this).attr('data-show-id') );
        var nonce               =   $('#wpstream_start_event_nonce').val();
        //$(this).unbind();
        notification_area.text('Closing Event');
    
        
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
                parent.remove();
            },
            error: function (errorThrown) {
              
            }
        });
        
    });
});



/*
* Upload images in admin
*
*/
function wpstream_upload_images_in_wpadmin(){
    console.log('wpstream_upload_images_in_wpadmin');
    var idList          = ["category_featured_image_button"];

    for (var i = 0; i < idList.length; i++) {
        var currentId = idList[i];
        jQuery('#'+currentId).on( 'click', function(event) {
            var parent=jQuery(this).parent();
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
function wpstream_admin_return_uploaded_image(){

    return new Promise(function(resolve, reject) {
        var mediaUploader = wp.media({
          frame: "post",
          state: "insert",
          multiple: false
        });
    
        mediaUploader.on("insert", function(){
          var image = mediaUploader.state().get("selection").first().toJSON();
          resolve(image);
        });
    
        mediaUploader.open();
      });
}





/*
* handle video selection for recording
*
*/

function wpstream_handle_video_selection(){

    jQuery('#wpstream_free_video_external_button').on( 'click', function(event) {
        var parent=jQuery(this).parent();
            wpstream_admin_return_uploaded_image().then(function(image) { 
                parent.find('#wpstream_free_video_external').val(image.url);
            });
    });
}



/*
* return uploaded image
*
*/
function wpstream_admin_return_uploaded_image(){


    return new Promise(function(resolve, reject) {
        var mediaUploader = wp.media({
        frame: "post",
        state: "insert",
        multiple: false
        });

        mediaUploader.on("insert", function(){
        var image = mediaUploader.state().get("selection").first().toJSON();
        resolve(image);
        });

        mediaUploader.open();
    });
}

