jQuery(document).ready(function (jQuery) {
    "use strict";

    jQuery('#wpstream-user-channel-selection,#wpstream_edit_category,#wpstream_edit_post_tag,#wpstream_edit_wpstream_actors,#wpstream_edit_wpstream_category,#wpstream_edit_wpstream_movie_rating,#wpstream_edit_product_cat,#wpstream_edit_product_tag').select2({
        theme: "bootstrap-5",
        width: jQuery(this).data('width') ? jQuery(this).data('width') : jQuery(this).hasClass('w-100') ? '100%' : 'style',
        placeholder: jQuery(this).data('placeholder'),
        closeOnSelect: false,
    });



    wpstream_dashboard_user_menu();
    wpstream_edit_account_function();
    wpstream_edit_address_function('wpstream_edit_address_save');
    wpstream_edit_address_function('wpstream_edit_address_save_shipping');
    wpstream_delete_profile_picture();
    wpstream_edit_channel_function();
});

/**
 * wpstream_edit_channel_function
 */

function wpstream_edit_channel_function() {
    jQuery('#wpstream_edit_channel_save').on('click', function () {
   
        var nonce                   = jQuery('input[name="wpstream_nonce"]').val();
        var wpstream_admin_ajax_url = wpstream_dashboard_script_vars.ajaxurl;
        var thumb_id                = jQuery('.wpstream_uploaded_images').attr('data-imageid');
        var title                   = jQuery('#channel_name').val();
        var description             = jQuery('#wstream_description').val();
        var channel_paid            = 0;
        var isChecked = jQuery('input[name="channel_paid"]').is(':checked');
        if (isChecked) {
            channel_paid = 1;
        }

        var channel_price   = jQuery('#channel_price').val();
        var images          = jQuery('#attachid').val();
        var postID          = jQuery(this).attr('data-postID');

        var categories = {};
        // wpstream_edit_channel_taxonomy_wrapper

        jQuery('.wpstream_taxonomies').each(function(){
            var selected_tax = jQuery(this).attr('data-tax');
            var selected_val = jQuery(this).val();
            categories[selected_tax]=selected_val;
        });



        jQuery.ajax({
            type: 'POST',
            url: wpstream_admin_ajax_url, // WordPress AJAX URL,
            dataType: 'json',
            data: {
                action: 'wpstream_dashboard_save_channel_data',
                thumb_id: thumb_id,
                title: title,
                description: description,
                channel_paid: channel_paid,
                channel_price: channel_price,
                images: images,
                postID: postID,
                selected_categories:categories,
                nonce: nonce


            },
            success: function (response) {

                if (response.success) {

                    jQuery('.event_thumb_wrapper').css('background-image', 'url('+response.data.thumburl+')');
                    jQuery('#wpstream_channel_title').empty().text(title);
                    jQuery('#wpstream_channel_description').empty().html(description);
                    
                    if(channel_paid == 0){
                        jQuery('.wpstream-dashboard-details_price').hide();
                    }else{
                        jQuery('.wpstream-dashboard-details_price').show();
                    }
                    
                    jQuery('#wpstream_channel_price').empty().text(channel_price);
                    jQuery('.wpstream_uploaded_images_wrapper').empty().html(response.data.images);
                    jQuery('.wpstream-theme-dashboard-chanel-gallery__list').empty().html(response.data.images);
          
                    jQuery('.wpstream_taxonomies_wrapper').empty().html(response.data.taxonomies);
                    jQuery('#wpstream_edit_channel_modal').modal('hide');

                    // Check if there is a trailer video
                    var trailerVideoWrapper = jQuery('.wpstream-theme-dashboard-channel-video-trailer__video');
                    if ( trailerVideoWrapper.find('#wpstream-video-trailer').length > 0 ) {
                        jQuery('#wpstream-video-trailer video source').attr('src', response.data.video_trailer);
                        jQuery('#wpstream-video-trailer video')[0].load();
                    } else {
                        // If there is no trailer video, create it
                        trailerVideoWrapper.empty();
                        trailerVideoWrapper.append(`<div class="wpstream-video-trailer" id="wpstream-video-trailer">` +
                            `<video height="240" controls><source src="${response.data.video_trailer}" type="video/mp4"></video></div>`);
                    }
                    // Check if there is a preview video
                    var previewVideoWrapper = jQuery('.wpstream-theme-dashboard-channel-video-preview__video');
                    if ( previewVideoWrapper.find('#wpstream-video-preview').length > 0 ) {
                        jQuery('#wpstream-video-preview video source').attr('src', response.data.video_preview);
                        jQuery('#wpstream-video-preview video')[0].load();
                    } else {
                        // If there is no preview video, create it
                        previewVideoWrapper.empty();
                        previewVideoWrapper.append(`<div class="wpstream-video-preview" id="wpstream-video-preview">` +
                            `<video height="240" controls><source src="${response.data.video_preview}" type="video/mp4"></video></div>`);
                    }
                } else {
                    console.log('Error');
                }


            },
            error: function (err) {
                console.log(err);
                // Handle AJAX error
                alert('AJAX request failed.');
            }
        });
    });
}

/**
 * wpstream_dashboard_user_menu
 */
var popover;
function wpstream_dashboard_user_menu() {
    var profileImage = document.getElementById('dashboard-header_profile-image');
    if(typeof popover !== 'undefined' ){
        popover.dispose();
    }
    popover = new bootstrap.Popover(profileImage, {
        placement: 'bottom',
    });
}

/**
 * edit address profile
 */
function wpstream_edit_address_function(button_id) {
    jQuery('#' + button_id).on('click', function () {
            var nonce = jQuery('input[name="wpstream_nonce"]').val();
            var wpstream_admin_ajax_url = wpstream_dashboard_script_vars.ajaxurl;
           
            // Get the values from the form fields
            var modalBody = jQuery(this).closest('.modal-content');
            var inputData = [];

            // Find all input elements in the modal body.
            //var inputs = jQuery('.modal-body input,.modal-body select');
            var inputs = modalBody.find('input, select');

            // Loop through each input element and add its ID and value to the array.
            inputs.each(function () {
                var element = jQuery(this);
                var inputId = element.attr('id');
                var inputValue = element.val();
                inputData.push({id: inputId, value: inputValue});
            });

            jQuery.ajax({
                type: 'POST',
                url: wpstream_admin_ajax_url, // WordPress AJAX URL,
                dataType: 'json',
                data: {
                    action: 'wpstream_dashboard_save_user_address',
                    nonce: nonce,
                    inputData: inputData,
                    type: 'billing'
                },
                success: function (response) {
           
                    if (response.success) {
                        jQuery('#wpstream_edit_addr_modal').modal('hide');
                        jQuery('#wpstream_edit_addr_modal_shipping').modal('hide');
                        for (var i = 0; i < inputData.length; i++) {
                            var inputId = inputData[i].id;
                            var inputValue = inputData[i].value;
                            // Set the value of the input element based on its ID.
                            jQuery('#wpstream_display_' + inputId).empty().text(inputValue);
                        }
                    } else {

                    }
                },
                error: function () {
                    // Handle AJAX error
                    alert('AJAX request failed.');
                }
            });
        }
    )
}

/**
 * delete profile
 */
function wpstream_delete_profile_picture() {
    jQuery('#wpstream_remove_profile').on('click', function () {
        var imageId = jQuery('#wpstream_remove_profile').attr('data-image-id');
        var ajaxurl = wpstream_dashboard_script_vars.ajaxurl;
        var nonce = jQuery('#wpstream_profile_image_upload').val();

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                action: 'wpstream_delete_profile_attachment',
                image_id: imageId,
                security: nonce,
            },
            success: function (response) {
                if (response.success) {                  
                    jQuery('#wpstream_remove_profile').attr('data-image-id', '');
                    jQuery('#profile-image,#dashboard-header_profile-image').attr('src', response.data.default);
                }
            }
        });
    });
}

/**
 * edit account
 */
function wpstream_edit_account_function() {
    jQuery('#wpstream_edit_account_save').on('click', function () {
        // Get the values from the form fields
        var firstName           = jQuery('#account_first_name').val();
        var lastName            = jQuery('#account_last_name').val();
        var displayName         = jQuery('#account_display_name').val();
        var email               = jQuery('#account_email').val();
        var aboutMe             = jQuery('#account_about_me').val();
        var currentPassword     = jQuery('#password_current').val();
        var newPassword1        = jQuery('#password_1').val();
        var newPassword2        = jQuery('#password_2').val();
        var message_password    = jQuery('.wpstream_passoword_change_notification');
        var message_account     = jQuery('.wpstream_account_change_notification');
        var nonce               = jQuery('input[name="wpstream_nonce"]').val();
        var wpstream_admin_ajax_url = wpstream_dashboard_script_vars.ajaxurl;


        // Check if the passwords meet the conditions
        if (newPassword1 !== '' && newPassword2 !== '') {
            if (currentPassword === '') {
                message_password.empty().text(wpstream_dashboard_script_vars.currentPassEmpty)
                return
            } else if (newPassword1 != newPassword2) {
                message_password.empty().text(wpstream_dashboard_script_vars.passNoMatch)
                return
            }
        }

        // Make an AJAX request to save the user's data
        jQuery.ajax({
            type: 'POST',
            url: wpstream_admin_ajax_url, // WordPress AJAX URL,
            dataType: 'json',
            data: {
                action: 'wpstream_dashboard_save_user_data',
                nonce: nonce,
                firstName: firstName,
                lastName: lastName,
                displayName: displayName,
                email: email,
                newPassword1: newPassword1,
                newPassword2: newPassword2,
                currentPassword: currentPassword,
                aboutMe
            },
            success: function (response) {
      
                if (response.success) {
                    message_password.empty().text(response.message);
                    jQuery('#wpstream_first_name_value').empty().text(firstName);
                    jQuery('#wpstream_last_name_value').empty().text(lastName);
                    jQuery('#wpstream_display_name_value').empty().text(displayName);
                    jQuery('#wpstream_email_value').empty().text(email);
                    jQuery('#wpstream_about_me_value').empty().text(aboutMe);
                    jQuery('#wpstream_edit_account_modal').modal('hide');

                    jQuery('.dashboard-header_profile-username').text(firstName+' '+lastName);
   
                    if (response.data.passwordchanged) {
                        location.reload();
                    }
                } else {
                    if (response.data.failpass) {
                        message_password.empty().text(response.data.failpass)
                    } else if (response.data.failaccount) {
                        message_account.empty().text(response.data.failaccount)
                    }
                }
            },
            error: function () {
                // Handle AJAX error
                alert('AJAX request failed.');
            }
        });
    });
}