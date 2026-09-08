/*global $, jQuery, wpstream_integrations_vars*/
/*
 * WpStream integrations front-end script (BuddyPress/BuddyBoss).
 *
 * Wires up the profile "Live Video" tab: the channel-selection dropdown that
 * saves the chosen channel over AJAX. Timeline activities are posted
 * server-side from the channel lifecycle hooks, not from the browser.
 * Localized data (admin URL, buddyboss flag, nonce) arrives via the
 * wpstream_integrations_vars object.
 */
jQuery(document).ready(function ($) {
    "use strict";
    // Only bind the channel picker when the BuddyBoss integration is active.
    if(wpstream_integrations_vars.is_buddyboss==='yes'){
        wpstream_buddy_boss_select_channel();
    }
});


/*
*
*  Select streaming channet on BuddyBoss
*
*/


/**
 * Bind the profile tab's channel <select> so a change persists the selection.
 *
 * On change, POSTs the chosen channel id to the select-channel AJAX action and
 * reloads the page when the server confirms it was saved.
 */
function wpstream_buddy_boss_select_channel(){
    // Listen for the user picking a different channel in the dropdown.
    jQuery('#wpstream_buddyboss_select_channel').on('change',function(){

        // Build the admin-ajax endpoint and read the newly selected channel id.
        var ajaxurl = wpstream_integrations_vars.admin_url+ 'admin-ajax.php';;
        var show_id = jQuery('#wpstream_buddyboss_select_channel').val();
        var nonce = wpstream_integrations_vars.buddy_nonce;
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            timeout: 300000,

            // Server action, the selected channel id and the integration nonce.
            data: {
                'action'            :   'wpstream_buddy_boss_select_channel_function',
                'show_id'           :   show_id,
                'security'          :   nonce
            },
            success: function (data) {
                // Reload so the page reflects the newly selected channel.
                if(data.saved===true){
                    location.reload();
                }

            },
            // Swallow AJAX errors silently.
            error: function (jqXHR,textStatus,errorThrown) {
            }
        });
    });
}
