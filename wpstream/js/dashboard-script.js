/*
 * WpStream front-end dashboard script.
 *
 * Powers the member dashboard modals: initializes Select2 on the taxonomy/
 * channel pickers and wires the AJAX save handlers for editing a channel, the
 * user account, billing/shipping addresses, and removing the profile picture.
 * Localized data (ajaxurl, messages) is provided via wpstream_dashboard_script_vars.
 */
jQuery(document).ready(function (jQuery) {
    "use strict";

    // Enhance the taxonomy/channel multi-selects with Select2 (Bootstrap 5 theme).
    jQuery('#wpstream-user-channel-selection,#wpstream_edit_category,#wpstream_edit_post_tag,#wpstream_edit_wpstream_actors,#wpstream_edit_wpstream_category,#wpstream_edit_wpstream_movie_rating,#wpstream_edit_product_cat,#wpstream_edit_product_tag').select2({
        theme: "bootstrap-5",
        width: jQuery(this).data('width') ? jQuery(this).data('width') : jQuery(this).hasClass('w-100') ? '100%' : 'style',
        placeholder: jQuery(this).data('placeholder'),
        closeOnSelect: false,
    });



    // Bind all dashboard interactions once the DOM is ready.
    wpstream_dashboard_user_menu();
    wpstream_edit_account_function();
    wpstream_edit_address_function('wpstream_edit_address_save');
    wpstream_edit_address_function('wpstream_edit_address_save_shipping');
    wpstream_delete_profile_picture();
    wpstream_edit_channel_function();
});

/**
 * Wire the "save channel" modal button.
 *
 * Collects the channel fields (title, description, paid flag/price, thumbnail,
 * gallery images, taxonomies) and POSTs them to the save-channel AJAX action,
 * then updates the dashboard UI (thumbnail, texts, price visibility, gallery,
 * trailer/preview videos) from the response.
 */
function wpstream_edit_channel_function() {
    jQuery('#wpstream_edit_channel_save').on('click', function () {

        // Gather the channel form values and the security nonce.
        var nonce                   = jQuery('input[name="wpstream_nonce"]').val();
        var wpstream_admin_ajax_url = wpstream_dashboard_script_vars.ajaxurl;
        var thumb_id                = jQuery('.wpstream_uploaded_images').attr('data-imageid');
        var title                   = jQuery('#channel_name').val();
        var description             = jQuery('#wstream_description').val();
        // Paid flag: 1 when the "paid" checkbox is ticked, else 0.
        var channel_paid            = 0;
        var isChecked = jQuery('input[name="channel_paid"]').is(':checked');
        if (isChecked) {
            channel_paid = 1;
        }

        var channel_price   = jQuery('#channel_price').val();
        var images          = jQuery('#attachid').val();
        var postID          = jQuery(this).attr('data-postID');

        // Collect selected terms per taxonomy into a { taxonomy: values } map.
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

                // On success, reflect the saved values back into the dashboard view.
                if (response.success) {

                    // Update the thumbnail, title and description displays.
                    jQuery('.event_thumb_wrapper').css('background-image', 'url('+response.data.thumburl+')');
                    jQuery('#wpstream_channel_title').empty().text(title);
                    jQuery('#wpstream_channel_description').empty().html(description);

                    // Show or hide the price row depending on the paid flag.
                    if(channel_paid == 0){
                        jQuery('.wpstream-dashboard-details_price').hide();
                    }else{
                        jQuery('.wpstream-dashboard-details_price').show();
                    }

                    // Refresh price, gallery images and taxonomy chips, then close the modal.
                    jQuery('#wpstream_channel_price').empty().text(channel_price);
                    jQuery('.wpstream_uploaded_images_wrapper').empty().html(response.data.images);
                    jQuery('.wpstream-theme-dashboard-chanel-gallery__list').empty().html(response.data.images);

                    jQuery('.wpstream_taxonomies_wrapper').empty().html(response.data.taxonomies);
                    jQuery('#wpstream_edit_channel_modal').modal('hide');

                    // Check if there is a trailer video
                    var trailerVideoWrapper = jQuery('.wpstream-theme-dashboard-channel-video-trailer__video');
                    if ( trailerVideoWrapper.find('#wpstream-video-trailer').length > 0 ) {
                        // Existing trailer: swap the source and reload the element.
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
                        // Existing preview: swap the source and reload the element.
                        jQuery('#wpstream-video-preview video source').attr('src', response.data.video_preview);
                        jQuery('#wpstream-video-preview video')[0].load();
                    } else {
                        // If there is no preview video, create it
                        previewVideoWrapper.empty();
                        previewVideoWrapper.append(`<div class="wpstream-video-preview" id="wpstream-video-preview">` +
                            `<video height="240" controls><source src="${response.data.video_preview}" type="video/mp4"></video></div>`);
                    }
                } else {
                    // Server reported failure.
                    console.log('Error');
                }


            },
            error: function (err) {
                // Network/transport failure: log and notify the user.
                console.log(err);
                // Handle AJAX error
                alert('AJAX request failed.');
            }
        });
    });
}

/**
 * Initialize the dashboard header profile-image popover menu.
 *
 * Disposes any previous popover instance before creating a new one so repeated
 * calls do not stack Bootstrap popovers on the same element.
 */
var popover;
function wpstream_dashboard_user_menu() {
    var profileImage = document.getElementById('dashboard-header_profile-image');
    // Tear down a prior popover to avoid duplicates.
    if(typeof popover !== 'undefined' ){
        popover.dispose();
    }
    // Create the bottom-placed popover on the profile image.
    popover = new bootstrap.Popover(profileImage, {
        placement: 'bottom',
    });
}

/**
 * Wire an address-save modal button (billing or shipping).
 *
 * Reads every input/select in the modal, POSTs them to the save-address AJAX
 * action, and on success closes the modal and mirrors the values into the
 * matching display fields.
 *
 * @param {string} button_id Id of the save button to bind.
 */
function wpstream_edit_address_function(button_id) {
    jQuery('#' + button_id).on('click', function () {
            // Security nonce and AJAX endpoint.
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
                // Action, nonce, collected fields, and address type (sent as 'billing').
                data: {
                    action: 'wpstream_dashboard_save_user_address',
                    nonce: nonce,
                    inputData: inputData,
                    type: 'billing'
                },
                success: function (response) {

                    if (response.success) {
                        // Close both address modals and mirror each value into its display field.
                        jQuery('#wpstream_edit_addr_modal').modal('hide');
                        jQuery('#wpstream_edit_addr_modal_shipping').modal('hide');
                        for (var i = 0; i < inputData.length; i++) {
                            var inputId = inputData[i].id;
                            var inputValue = inputData[i].value;
                            // Set the value of the input element based on its ID.
                            jQuery('#wpstream_display_' + inputId).empty().text(inputValue);
                        }
                    } else {
                        // No-op on a non-success response.
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
 * Wire the "remove profile picture" button.
 *
 * POSTs the current image id to the delete-attachment AJAX action and, on
 * success, clears the stored image id and resets both avatar images to the
 * default returned by the server.
 */
function wpstream_delete_profile_picture() {
    jQuery('#wpstream_remove_profile').on('click', function () {
        // The attachment to delete, endpoint, and nonce.
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
                    // Clear the stored id and swap both avatars back to the default image.
                    jQuery('#wpstream_remove_profile').attr('data-image-id', '');
                    jQuery('#profile-image,#dashboard-header_profile-image').attr('src', response.data.default);
                }
            }
        });
    });
}

/**
 * Wire the "save account" modal button.
 *
 * Validates the optional password change (current required, new fields must
 * match), then POSTs the account fields to the save-user-data AJAX action and
 * updates the display fields or shows the relevant error on the response.
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
            // A new password requires the current one and both new fields to match.
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
                    // Mirror the saved values into their display fields and close the modal.
                    message_password.empty().text(response.message);
                    jQuery('#wpstream_first_name_value').empty().text(firstName);
                    jQuery('#wpstream_last_name_value').empty().text(lastName);
                    jQuery('#wpstream_display_name_value').empty().text(displayName);
                    jQuery('#wpstream_email_value').empty().text(email);
                    jQuery('#wpstream_about_me_value').empty().text(aboutMe);
                    jQuery('#wpstream_edit_account_modal').modal('hide');

                    // Refresh the header username label.
                    jQuery('.dashboard-header_profile-username').text(firstName+' '+lastName);

                    // A password change invalidates the session, so reload the page.
                    if (response.data.passwordchanged) {
                        location.reload();
                    }
                } else {
                    // Surface whichever error the server reported (password or account).
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