"use strict";

jQuery(document).ready(function () {
    wpstream_watch_later();
    wpstream_watch_later_video_remove();
});

/**
 *
 */
function wpstream_elementor_submit_form() {
    jQuery('.wpstream_elementor_form').on('submit', function (event) {
        event.preventDefault();
        var form_submit = jQuery('.wpstream_elementor_form').find('.agent_submit_class_elementor');
        wpstream_elementor_contact_process_form(form_submit);
    });
}

function wpstream_elementor_contact_process_form(form_submit) {
    var parent, button,message_area, ajaxurl, contact_u_email, contact_u_name, subject, booking_from_date, booking_to_date, booking_guest_no, message, nonce, agent_property_id, is_elementor;


    parent = form_submit.parent();
    button = jQuery('.wpstream_elementor_form').find('.agent_submit_class_elementor');
    message_area = jQuery('.wpstream_elementor_form').find('.wpstream-contact-form-message');


    button.val(wpstreamPluginScriptsVars.processing);
    button.text(wpstreamPluginScriptsVars.processing);
    button.prop('disabled', true);
    ajaxurl = wpstreamPluginScriptsVars.ajaxurl;
    message = jQuery("#form-field-message").val();
    contact_u_email = jQuery("#rentals_contact_builder_email").val();
    contact_u_name = jQuery("#rentals_contact_builder_name").val();
    nonce = jQuery('#agent_property_ajax_nonce').val();



    is_elementor = parent.find('#contact_form_elementor').val();
    var elementor_email_subject = jQuery('#elementor_email_subject').val();

    var temp_details;
    temp_details = '';
    var elementor_form = form_submit.parents('.wpstream_elementor_form');

    var form_items = elementor_form.find('.elementor-field');

    form_items.each(function () {
        temp_details = temp_details + jQuery(this).attr('name') + ": " + jQuery(this).val() + "/n";
    });

    message = temp_details;


    if (jQuery('#wpstream_agree_gdpr').length > 0 && !jQuery('#wpstream_agree_gdpr').is(':checked')) {
        button.val(wpstreamPluginScriptsVars.send_mess);
        button.text(wpstreamPluginScriptsVars.send_mess);
        var aTag = jQuery(".wpstream_elementor_form");
        jQuery('html,body').animate({scrollTop: aTag.offset().top - 120}, 'slow');
        button.prop('disabled', false);
        message_area.empty().text(  wpstreamPluginScriptsVars.gdpr_agree);

        return;
    }



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
            var aTag = jQuery(".wpstream_elementor_form");
            jQuery('html,body').animate({scrollTop: aTag.offset().top - 120}, 'slow');

            // reset contact form
            button.val(wpstreamPluginScriptsVars.send_mess);
            button.text(wpstreamPluginScriptsVars.send_mess);
            button.prop('disabled', false);
            jQuery(".wpstream_elementor_form .elementor-field").val('');

            var aTag = jQuery(".wpstream_elementor_form");
            jQuery('html,body').animate({scrollTop: aTag.offset().top - 120}, 'slow');
            message_area.empty().text(data.response );


        },
        error: function (errorThrown) {

        }

    });

}


/**
 * Add to watch later
 */
function wpstream_watch_later() {
    jQuery(document).on('click', '.wpstream-watch-later-action', function () {
        const item = jQuery(this);
        var parent = jQuery(this).closest('.wpstream-watch-later-btn');

        if (item.hasClass('wpstream_no_action')) {
            return;
        }

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
 */
function wpstream_watch_later_video_remove() {
    jQuery('.wpstream_watch-later-remove-btn').on('click', function () {
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
                if (response.success) {
                    item_to_remove.remove();
                }
            }
        });
    });
}