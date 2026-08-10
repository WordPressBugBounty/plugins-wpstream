"use strict";

/*
 * wpstream-plugin-script.js
 *
 * Public-facing plugin behaviour for the theme: submitting the Elementor
 * contact form via AJAX, and the "watch later" add/remove actions. All requests
 * go through admin-ajax using the localized `wpstreamPluginScriptsVars`.
 */

// Bind the watch-later handlers once the DOM is ready.
jQuery(document).ready(function () {
    wpstream_watch_later();
    wpstream_watch_later_video_remove();
});

/**
 * Bind submit handling to the Elementor contact form and delegate processing.
 *
 * @return {void}
 */
function wpstream_elementor_submit_form() {
    jQuery('.wpstream_elementor_form').on('submit', function (event) {
        // Prevent the default page reload and process via AJAX.
        event.preventDefault();
        var form_submit = jQuery('.wpstream_elementor_form').find('.agent_submit_class_elementor');
        wpstream_elementor_contact_process_form(form_submit);
    });
}

/**
 * Validate and submit the Elementor contact form over AJAX: disable the button,
 * enforce the GDPR checkbox, serialize all fields, and post the message.
 *
 * @param {jQuery} form_submit The submit button element inside the form.
 * @return {void}
 */
function wpstream_elementor_contact_process_form(form_submit) {
    var parent, button,message_area, ajaxurl, contact_u_email, contact_u_name, subject, booking_from_date, booking_to_date, booking_guest_no, message, nonce, agent_property_id, is_elementor;


    // Cache the form, submit button, and message output area.
    parent = form_submit.parent();
    button = jQuery('.wpstream_elementor_form').find('.agent_submit_class_elementor');
    message_area = jQuery('.wpstream_elementor_form').find('.wpstream-contact-form-message');


    // Put the button into its "processing" (disabled) state.
    button.val(wpstreamPluginScriptsVars.processing);
    button.text(wpstreamPluginScriptsVars.processing);
    button.prop('disabled', true);
    // Read the endpoint, standard contact fields, and nonce.
    ajaxurl = wpstreamPluginScriptsVars.ajaxurl;
    message = jQuery("#form-field-message").val();
    contact_u_email = jQuery("#rentals_contact_builder_email").val();
    contact_u_name = jQuery("#rentals_contact_builder_name").val();
    nonce = jQuery('#agent_property_ajax_nonce').val();



    // Flag that this is an Elementor form and read its subject line.
    is_elementor = parent.find('#contact_form_elementor').val();
    var elementor_email_subject = jQuery('#elementor_email_subject').val();

    // Build the message body by concatenating every Elementor field.
    var temp_details;
    temp_details = '';
    var elementor_form = form_submit.parents('.wpstream_elementor_form');

    var form_items = elementor_form.find('.elementor-field');

    form_items.each(function () {
        temp_details = temp_details + jQuery(this).attr('name') + ": " + jQuery(this).val() + "/n";
    });

    message = temp_details;


    // GDPR gate: if the consent box exists but is unchecked, re-enable the
    // button, scroll to the form, show the consent notice, and bail.
    if (jQuery('#wpstream_agree_gdpr').length > 0 && !jQuery('#wpstream_agree_gdpr').is(':checked')) {
        button.val(wpstreamPluginScriptsVars.send_mess);
        button.text(wpstreamPluginScriptsVars.send_mess);
        var aTag = jQuery(".wpstream_elementor_form");
        jQuery('html,body').animate({scrollTop: aTag.offset().top - 120}, 'slow');
        button.prop('disabled', false);
        message_area.empty().text(  wpstreamPluginScriptsVars.gdpr_agree);

        return;
    }



    // Submit the contact message to the server.
    jQuery.ajax({
        type: 'POST',
        dataType: 'json',
        url: ajaxurl,
        data: {
            'action': 'wpstream_ajax_contact_function',
            'name': contact_u_name,
            'email': contact_u_email,
            'comment': message,
            'elementor_email_subject': elementor_email_subject,
            'is_elementor': 1,
            'nonce': nonce
        },
        success: function (data) {
            // Scroll the form back into view.
            var aTag = jQuery(".wpstream_elementor_form");
            jQuery('html,body').animate({scrollTop: aTag.offset().top - 120}, 'slow');

            // reset contact form
            button.val(wpstreamPluginScriptsVars.send_mess);
            button.text(wpstreamPluginScriptsVars.send_mess);
            button.prop('disabled', false);
            jQuery(".wpstream_elementor_form .elementor-field").val('');

            var aTag = jQuery(".wpstream_elementor_form");
            jQuery('html,body').animate({scrollTop: aTag.offset().top - 120}, 'slow');
            // Show the server's response message.
            message_area.empty().text(data.response );


        },
        error: function (errorThrown) {

        }

    });

}


/**
 * Add to watch later
 *
 * Toggle a video's "watch later" state via AJAX and swap in the returned
 * button markup.
 *
 * @return {void}
 */
function wpstream_watch_later() {
    jQuery(document).on('click', '.wpstream-watch-later-action', function () {
        // The clicked control and its button wrapper.
        const item = jQuery(this);
        var parent = jQuery(this).closest('.wpstream-watch-later-btn');

        // Ignore clicks on disabled ("no action") controls.
        if (item.hasClass('wpstream_no_action')) {
            return;
        }

        // Target post id, nonce placeholder, and endpoint.
        const postID = item.attr('data-postID');
        const nonce = 'nonce';
        const wpstream_admin_ajax_url = wpstreamPluginScriptsVars.ajaxurl;
        jQuery.ajax({
            type: 'POST',
            url: wpstream_admin_ajax_url, // WordPress AJAX URL,
            dataType: 'json',
            data: {
                action: 'wpstream_handle_watch_later_item_ajax',
                postID: postID,
                security: nonce // Include the nonce in the data
            },
            success: function (data) {
                // On success, flip the "already watched later" state and
                // replace the button markup with the server's version.
                if (data.success === true) {
                    if (item.hasClass('wpstream_already_watched_later')) {
                        // Class exists, remove it
                        item.removeClass('wpstream_already_watched_later');
                        parent.empty();
                        parent.html(data.content);
                    } else {
                        // Class does not exist, add it
                        item.addClass('wpstream_already_watched_later');
                        parent.empty();
                        parent.html(data.content);
                    }
                }
            },
            error: function (errorThrown) {
                // Handle AJAX errors here
            }
        }); // end ajax
    });
}

/**
 * Remove Video in Watch later page
 *
 * Remove a video from the "watch later" list and drop its card from the DOM.
 *
 * @return {void}
 */
function wpstream_watch_later_video_remove() {
    jQuery('.wpstream_watch-later-remove-btn').on('click', function () {
        // Post id to remove, endpoint, nonce, and the card element to delete.
        const postIdToRemove = jQuery(this).data('post-id');
        const wpstream_admin_ajax_url = wpstreamPluginScriptsVars.ajaxurl;
        const nonce = jQuery('#wpstream-watch-later-nonce').val();
        const item_to_remove = jQuery(this).closest('.wpstream-dashboard-card');

        jQuery.ajax({
            url: wpstream_admin_ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wpstream_remove_post_id',
                postID: postIdToRemove,
                wpstream_nonce: nonce // Include the nonce in the data
            },
            success: function (response) {
                // Remove the card only when the server confirms deletion.
                if (response.success) {
                    item_to_remove.remove();
                }
            }
        });
    });
}