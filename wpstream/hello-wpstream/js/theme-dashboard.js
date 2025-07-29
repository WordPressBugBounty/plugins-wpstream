"use strict";

jQuery(document).ready(function ($) {
    wpstream_channel_selection();
    wpstream_save_channel_details();
    wpstream_user_creates_channel();

});

/*
*   user creates channel
*/
function wpstream_user_creates_channel() {
    jQuery('.wpstream_user_create_new_paid_channel,.wpstream_user_create_new_channel').on('click', function () {
        const ajaxurl = wpstreamDashboardVars.admin_url + 'admin-ajax.php';
        const nonce = jQuery('#wpstream_user_channel_list_nonce').val();
        jQuery('.wpstream-theme-dashboard-select-channel-notification').empty().show().text(wpstreamDashboardVars.createchannel);
        let channel_type = 'free';

        if (jQuery(this).hasClass('wpstream_user_create_new_paid_channel')) {
            channel_type = 'paid';
        }

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'wpstream_handle_channel_creation',
                'channel_type': channel_type,
                'security': nonce
            },
            success: function (data) {
              
                if (data.success) {
                    location.reload();
                } else {
                    jQuery('.wpstream-theme-dashboard-select-channel-notification').empty().show().text(data.message);
                }
            },
            error: function (errorThrown) {}
        });//end ajax
    });
}

/*
*   save Channel details in start streaming page
*/
function wpstream_save_channel_details() {
    jQuery('#wpstream_save_details').on('click', function () {
        const postID = jQuery(this).attr('data-postID');
        const ajaxurl = wpstreamDashboardVars.admin_url + 'admin-ajax.php';
        const nonce = jQuery('#wpstream_user_channel_list_nonce').val();
        const title = jQuery('#wpstream_channel_name').val();
        const description = jQuery('#wstream_description').val();
        const price = jQuery('#wpstream_channel_price').val();
        const images = jQuery('#attachid').val();
        const featured = jQuery('#attachthumb').val();
        const taxonomies = {};

        jQuery('.wpstream_taxonomies').each(function () {
            const dataTax = jQuery(this).data('tax');
            let selectedOptionValues = jQuery(this).val();

            if (selectedOptionValues.length === 0) {
                selectedOptionValues = ['-1'];
            }

            taxonomies[dataTax] = selectedOptionValues;
        });

        //scroll to top
        const targetId = 'wpstream-theme-dashboard-select-channel-notification';
        const targetOffset = jQuery('#' + targetId).offset().top - 100;

        jQuery('html, body').animate({
            scrollTop: targetOffset
        }, 400); // Adjust the animation duration as needed

        // update message area
        jQuery('.wpstream-theme-dashboard-select-channel-notification').empty().show().text(wpstreamDashboardVars.saving);

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'wpstream_handle_channel_details_saving',
                'postID': postID,
                'title': title,
                'description': description,
                'price': price,
                'images': images,
                'featured': featured,
                'taxonomies': taxonomies,
                'security': nonce
            },
            success: function (data) {
                if (data.success) {
                    jQuery('.wpstream-theme-dashboard-select-channel-notification').empty().show().text(wpstreamDashboardVars.saved);
                } else {
                    jQuery('.wpstream-theme-dashboard-select-channel-notification').empty().show().text(wpstreamDashboardVars.notsaved);
                }
            },
            error: function (errorThrown) {
            }
        }); //ajax end
    });
}

/*
* Change Channel in start streaming page
*/
function wpstream_channel_selection() {
    jQuery('#wpstream-user-channel-selection').on('change', function () {
        const selected_value = jQuery(this).val();
        const ajaxurl = wpstreamDashboardVars.admin_url + 'admin-ajax.php';
        const nonce = jQuery('#wpstream_user_channel_list_nonce').val();
        jQuery('.wpstream-theme-dashboard-select-channel-notification').empty().show().text(wpstreamDashboardVars.saving);

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'wpstream_handle_channel_selection',
                'selected_value': selected_value,
                'security': nonce
            },
            success: function (data) {
               

                if (data.success) {
                    location.reload();
                }
            },
            error: function (errorThrown) {}
        });//end ajax
    });
}

function toggle_dashboard_menu() {
    const toggleBtn = document.querySelector('#wpstream_toggle_dashboard_menu');
    const dashboardMenuContainer = document.querySelector('.wpstream_theme_dashboard_menu_wrapper');
    const dashboardLogoWrapper = document.querySelector('.wpstream-dashboard-header-logo-wrapper');

    toggleBtn.addEventListener('click', function() {
        dashboardMenuContainer.classList.toggle('menu-collapse');
        dashboardLogoWrapper.classList.toggle('menu-collapse');
    })
}

if (document.querySelector('#wpstream_toggle_dashboard_menu')) {
    toggle_dashboard_menu();
}