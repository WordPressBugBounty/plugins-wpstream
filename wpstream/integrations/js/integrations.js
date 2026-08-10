/*global $, jQuery, wpstream_integrations_vars*/
/*
 * WpStream integrations front-end script (BuddyPress/BuddyBoss).
 *
 * Wires up the profile "Live Video" tab: the channel-selection dropdown that
 * saves the chosen channel over AJAX, and a helper that posts the live player
 * into the activity timeline. Localized data (admin URL, buddyboss flag, nonce)
 * arrives via the wpstream_integrations_vars object.
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
*  On this function we receive note that the event is on
*
*/


/**
 * Entry point invoked when a channel goes live: post its player to the timeline.
 *
 * @param {number|string} show_id Channel post id that just went live.
 */
function wpstream_integration_notifications(show_id){
    // Route to the BuddyBoss timeline player generator when that integration is active.
    if(wpstream_integrations_vars.is_buddyboss==='yes'){
        wpstream_buddyb_generate_player_html(show_id);
    }
}


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
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            timeout: 300000,

            // Server action plus the selected channel id.
            data: {
                'action'            :   'wpstream_buddy_boss_select_channel_function',
                'show_id'           :   show_id,
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



/*
*
* BuddyB - generate html player to add in timeline
*
*/

/**
 * Ask the server to post a live-player activity to the BuddyBoss timeline.
 *
 * @param {number|string} show_id Channel post id to embed in the timeline.
 * @param {number|string} [user_id] Optional user id (accepted but not sent).
 */
function wpstream_buddyb_generate_player_html(show_id,user_id){

    // admin-ajax endpoint and the integration nonce (read but not forwarded here).
    var ajaxurl = wpstream_integrations_vars.admin_url+ 'admin-ajax.php';;
    var nonce = wpstream_integrations_vars.buddy_nonce;
    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        dataType: 'json',
        timeout: 300000,

        // Server action plus the channel id to embed.
        data: {
            'action'            :   'wpstream_buddyb_integrations_generate_player_html',
            'show_id'           :   show_id,


        },
        // Fire-and-forget: the activity is created server-side, nothing to do on success.
        success: function (data) {

        },
        error: function (jqXHR,textStatus,errorThrown) {

        }
    });
}