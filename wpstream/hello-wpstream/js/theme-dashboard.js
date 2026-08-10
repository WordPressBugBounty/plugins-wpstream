"use strict";

/*
 * theme-dashboard.js
 *
 * Front-end streaming-dashboard behaviour. Handles the "start streaming" page:
 * creating free/paid channels, saving channel details (title, description,
 * price, images, taxonomies), switching the active channel, and toggling the
 * collapsible dashboard side menu. All server calls go through admin-ajax with
 * the localized `wpstreamDashboardVars` and a per-user nonce.
 */

// Wire up the dashboard handlers once the DOM is ready.
jQuery(document).ready(function ($) {
    wpstream_channel_selection();
    wpstream_save_channel_details();
    wpstream_user_creates_channel();

});

/*
*   user creates channel
*/
/**
 * Handle clicks on the "create channel" buttons (free or paid), then reload
 * the page on success so the new channel appears.
 *
 * @return {void}
 */
function wpstream_user_creates_channel() {
    jQuery('.wpstream_user_create_new_paid_channel,.wpstream_user_create_new_channel').on('click', function () {
        // admin-ajax endpoint URL and the request nonce.
        const ajaxurl = wpstreamDashboardVars.admin_url + 'admin-ajax.php';
        const nonce = jQuery('#wpstream_user_channel_list_nonce').val();
        // Show the "creating channel" status message.
        jQuery('.wpstream-theme-dashboard-select-channel-notification').empty().show().text(wpstreamDashboardVars.createchannel);
        // Default to a free channel unless the paid button was clicked.
        let channel_type = 'free';

        // Paid button carries a distinct class.
        if (jQuery(this).hasClass('wpstream_user_create_new_paid_channel')) {
            channel_type = 'paid';
        }

        // Ask the server to create the channel.
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
              
                // On success reload; otherwise surface the server message.
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
/**
 * Collect the channel form fields (including selected taxonomies), scroll the
 * notification area into view, and POST the details to the server.
 *
 * @return {void}
 */
function wpstream_save_channel_details() {
    jQuery('#wpstream_save_details').on('click', function () {
        // Gather the post id, endpoint, nonce, and all editable fields.
        const postID = jQuery(this).attr('data-postID');
        const ajaxurl = wpstreamDashboardVars.admin_url + 'admin-ajax.php';
        const nonce = jQuery('#wpstream_user_channel_list_nonce').val();
        const title = jQuery('#wpstream_channel_name').val();
        const description = jQuery('#wstream_description').val();
        const price = jQuery('#wpstream_channel_price').val();
        const images = jQuery('#attachid').val();
        const featured = jQuery('#attachthumb').val();
        const taxonomies = {};

        // Collect selected terms per taxonomy control.
        jQuery('.wpstream_taxonomies').each(function () {
            const dataTax = jQuery(this).data('tax');
            let selectedOptionValues = jQuery(this).val();

            // Use "-1" as a sentinel when nothing is selected.
            if (selectedOptionValues.length === 0) {
                selectedOptionValues = ['-1'];
            }

            taxonomies[dataTax] = selectedOptionValues;
        });

        //scroll to top
        // Compute a scroll offset just above the notification area.
        const targetId = 'wpstream-theme-dashboard-select-channel-notification';
        const targetOffset = jQuery('#' + targetId).offset().top - 100;

        // Smoothly scroll the notification into view.
        jQuery('html, body').animate({
            scrollTop: targetOffset
        }, 400); // Adjust the animation duration as needed

        // update message area
        jQuery('.wpstream-theme-dashboard-select-channel-notification').empty().show().text(wpstreamDashboardVars.saving);

        // Persist the channel details.
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
                // Show a saved / not-saved message based on the response.
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
/**
 * When the channel dropdown changes, tell the server which channel is now
 * active and reload the page on success.
 *
 * @return {void}
 */
function wpstream_channel_selection() {
    jQuery('#wpstream-user-channel-selection').on('change', function () {
        // Newly selected channel id, endpoint, and nonce.
        const selected_value = jQuery(this).val();
        const ajaxurl = wpstreamDashboardVars.admin_url + 'admin-ajax.php';
        const nonce = jQuery('#wpstream_user_channel_list_nonce').val();
        // Show a saving status while the request is in flight.
        jQuery('.wpstream-theme-dashboard-select-channel-notification').empty().show().text(wpstreamDashboardVars.saving);

        // Send the new channel selection to the server.
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
               

                // Reload so the newly selected channel is reflected.
                if (data.success) {
                    location.reload();
                }
            },
            error: function (errorThrown) {}
        });//end ajax
    });
}

/**
 * Toggle the collapsed state of the dashboard side menu and its logo wrapper.
 *
 * @return {void}
 */
function toggle_dashboard_menu() {
    // Cache the toggle button and the two elements it collapses.
    const toggleBtn = document.querySelector('#wpstream_toggle_dashboard_menu');
    const dashboardMenuContainer = document.querySelector('.wpstream_theme_dashboard_menu_wrapper');
    const dashboardLogoWrapper = document.querySelector('.wpstream-dashboard-header-logo-wrapper');

    // Flip the collapse classes on each click.
    toggleBtn.addEventListener('click', function() {
        dashboardMenuContainer.classList.toggle('menu-collapse');
        dashboardLogoWrapper.classList.toggle('menu-collapse');
    })
}

// Only wire the menu toggle when its button exists on the page.
if (document.querySelector('#wpstream_toggle_dashboard_menu')) {
    toggle_dashboard_menu();
}