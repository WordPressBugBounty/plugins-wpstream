/*global $, jQuery, document, window, plupload, ajax_vars ,ajaxurl*/


var current_no_up;
var uploader;
var upload_by;
var upload_type;

function thumb_setter() {
    "use strict";
    return;

}

function delete_binder() {
    "use strict";
    jQuery('#wpstream_imagelist i').unbind('click');
    jQuery('#wpstream_imagelist i.fa-trash-alt').on('click',function () {
        var curent = '';
        var remove='';


        var img_remove= jQuery(this).parent().attr('data-imageid');
        var nonce = jQuery('#wpstream_theme_image_upload').val();
        current_no_up=current_no_up-1;

        jQuery(this).parent().remove();

        jQuery('#wpstream_imagelist .wpstream_uploaded_images').each(function () {
            curent = curent + ',' + jQuery(this).attr('data-imageid');
        });
        jQuery('#attachid').val(curent);

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

function wpstream_bind_click_events(){
    jQuery('#aaiu-uploader').on('click',function (e) {
        uploader.start();
        upload_by = 'general';
        upload_type = 'image';
        e.preventDefault();
    });


    jQuery('#aaiu-uploader-single').on('click',function (e) {
        uploader.start();
        upload_by = 'single';
        upload_type = 'image';
        e.preventDefault();
    });

    jQuery('#aaiu-uploader-trailer').on('click',function (e) {
        uploader.start();
        upload_by='single';
        upload_type='video-trailer';
        e.preventDefault();
    });

    jQuery('#aaiu-uploader-preview').on('click',function (e) {
        uploader.start();
        upload_by='single';
        upload_type='video-preview';
        e.preventDefault();
    })
}

function wpstream_plp_uploader(){
    var should_warn=0;
    if (typeof (plupload) !== 'undefined' && ajax_vars.plupload!=='undefined') {
        if( jQuery('#profile-image') .length>0){
            console.log(  ajax_vars.plupload.multi_selection=false);
        }

        var wpstream_imagelist="wpstream_imagelist";
        var aaiu_upload_imagelist="aaiu-upload-imagelist"

        if( upload_by === 'single' && upload_type === 'image'){
            ajax_vars.plupload.multi_selection=false
            wpstream_imagelist      =   "wpstream_imagelist_single"
            aaiu_upload_imagelist   =   "aaiu-upload-imagelist_single";
        }else if( upload_by === 'general' && upload_type === 'image'){
            ajax_vars.plupload.multi_selection=true
        }

        uploader = new plupload.Uploader(ajax_vars.plupload);


        uploader.init();
        uploader.bind('FilesAdded', function (up, files) {

            if ( upload_type === 'image' ) {
                if (ajax_vars.max_images > 0) { // if is not unlimited
                    if (current_no_up === 0) {
                        array_cut = ajax_vars.max_images;
                        if (files.length > ajax_vars.max_images) {
                            current_no_up = array_cut;
                        } else {
                            current_no_up = files.length;
                        }
                    } else {
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

                    if (array_cut > 0) {
                        up.files.slice(0, array_cut);
                        files.slice(0, array_cut);
                        var i = array_cut;
                        while (files.length > array_cut) {
                            up.files.pop();
                            files.pop();
                            should_warn = 1;
                        }
                    }

                    if (should_warn === 1) {
                        jQuery('.image_max_warn').remove();
                        jQuery('#' + wpstream_imagelist).before('<div class="image_max_warn" style="width:100%;float:left;">' + ajax_vars.warning_max + '</div>');
                    }

                    if (array_cut == -1) {
                        jQuery('.image_max_warn').remove();
                        jQuery('#' + wpstream_imagelist).before('<div class="image_max_warn" style="width:100%;float:left;">' + ajax_vars.warning_max + '</div>');
                        files = [];
                        up = [];
                        return;
                    }

                }

                jQuery.each(files, function (i, file) {
                    jQuery('#' + aaiu_upload_imagelist).append('<div id="' + file.id + '">' + file.name + ' (' + plupload.formatSize(file.size) + ') <b></b>' + '</div>');
                });
            }

            up.refresh(); // Reposition Flash/Silverlight
            uploader.start();
        });

        uploader.bind('UploadProgress', function (up, file) {
            console.log ('UploadProgress .....');
            jQuery('#' + file.id + " b").html(file.percent + "%");
        });

        // On erro occur
        uploader.bind('Error', function (up, err) {
            console.log ('Error .....');
            jQuery('#'+aaiu_upload_imagelist).append("<div>Error: " + err.code +
                ", Message: " + err.message +
                (err.file ? ", File: " + err.file.name : "") +
                "</div>"
            );
            up.refresh(); // Reposition Flash/Silverlight
        });

        uploader.bind('FileUploaded', function (up, file, response) {
            console.log ('FileUploaded .....');
            var current_no_up2=  parseInt( jQuery('.wpstream_uploaded_images ').length,10);


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

            if(ajax_vars.max_images>0 && current_no_up2> ajax_vars.max_images){
                return;
            }

            result = jQuery.parseJSON(response.response);
            jQuery('#image_warn').remove();
            jQuery('#' + file.id).remove();
            if (result.success) {

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

                jQuery('#profile-image,#dashboard-header_profile-image').attr('src', result.profile_image);
                jQuery('#dashboard-header_profile-image-menu').attr('src', result.profile_image);
                wpstream_dashboard_user_menu();

                jQuery('#profile-image').attr('data-profileurl', result.html);
                jQuery('#profile-image').attr('data-smallprofileurl', result.attach);

                jQuery('#wpstream_remove_profile').attr('data-image-id',result.attach);

                jQuery('#profile-image-input').val(result.html);
                jQuery('#profile-image-input-small').val(result.attach);

                jQuery('#wpstream_uploaded_profile_image').attr('src',result.html);
                jQuery('#wpstream_uploaded_profile_image').attr('data-imageid', result.attach)

                if( upload_by == 'general'){
                    all_id = jQuery('#attachid').val();
                    all_id = all_id + "," + result.attach;
                    jQuery('#attachid').val(all_id);
                }

                if ( upload_type == 'image' ) {
                    if (result.html !== '') {
                        jQuery('#' + wpstream_imagelist).append('<div class="wpstream_uploaded_images" data-imageid="' + result.attach + '"><img src="' + result.html + '"  /><i class="far wpstream_delete_image fa-trash-alt"></i> </div>');
                    }

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

                    delete_binder();
                    // thumb_setter();
                }
            }else{

                if (result.image){
                    jQuery('#'+wpstream_imagelist).before('<div id="image_warn" style="width:100%;float:left;">'+ajax_vars.warning+'</div>');
                }
            }
        });

    }
}



jQuery(document).ready(function ($) {
    "use strict";
    var all_id, uploader, result;

    // ajax_vars is not available when the page does not contain a media uploader
    // ajax_vars is needed for these two functions
    if ( document.getElementById('aaiu-upload-container') !== null ) {
        wpstream_plp_uploader();
        delete_binder();
    }
    wpstream_bind_click_events()

    var array_cut;

    current_no_up =  parseInt( jQuery('.wpstream_uploaded_images ').length,10);
    array_cut=0;
});


