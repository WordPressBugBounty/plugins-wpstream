/*
 * WpStream admin settings page behaviour.
 *
 * Handles the "WpStream Settings" admin screen: it auto-saves the Default
 * Channel Settings tab and the individual setting controls (checkboxes, text
 * inputs, selects, range sliders) to admin-ajax.php as the user changes them,
 * shows a transient error message when a save fails, and drives the "update
 * plugin" button on the support tab. wpstream_broadcaster_bind() is a helper
 * (currently not called) that opens the browser broadcaster window.
 */

// Shared handle for debouncing rapid text-input saves.
var debounceTimer;

// Wire up all settings-page handlers once the DOM is ready.
jQuery(document).ready(function($) {
    // Save default channel options
    wpstream_save_default_channel_options();

    // Save settings
    wpstream_save_settings();

    // Bind the "update plugin" button on the support tab.
    wpstream_update_plugin_support_tab();

    // wpstream_broadcaster_bind();
});

/**
 * Save the changes to the Default Channel Settings tab
 *
 * Binds each option toggle so that, shortly after a click, all toggles in the
 * same group are collected and persisted together via AJAX.
 *
 * @return {void}
 */
function wpstream_save_default_channel_options() {
    // Respond to clicks on any default-channel option toggle.
    jQuery('.theme_options_tab_wpstream .wpstream_event_option_item').on('click',function() {

        // Apply any dependent UI adjustments for the clicked option.
        wpstream_adjust_settings_general(jQuery(this));

        // Accumulator for the option name/value pairs to send.
        var optionarray ={};
        // The wrapper that groups the related option toggles.
        var holder = jQuery(this).parents('.wpstream_option_wrapper');
        // Security nonce read from a hidden field on the page.
        var nonce               =   jQuery('#wpstream-settings-nonce').val();

        // Show the saving spinner.
        jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','visible');
        // Brief delay so the toggle's checked state settles before we read it.
        var timer = setTimeout(function() {
            // Collect every option in the group into name => 1/0 pairs.
            holder.find('.wpstream_event_option_item').each(function(){
                optionarray[jQuery(this).attr('data-attr-ajaxname')]=jQuery(this).prop("checked") ? 1 : 0 ;
            });

            // JSON copy of the option map (kept for potential debugging/use).
            var jsonOptions = JSON.stringify(optionarray);
            // Persist the whole option group in one request.
            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                timeout: 300000,
                data: {
                    'action'            :   'wpstream_update_default_channel_settings',
                    'option'            :   optionarray,
                    'security'          :   nonce
                },
                success: function (data) {
                    // Hide the spinner once the save completes.
                    jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','hidden');
                },
                error: function (jqXHR,textStatus,errorThrown) {
                    // Show a transient error message on failure.
                    wpstream_show_error_message(jQuery('.theme_options_tab_wpstream .wpstream-save-settings'));
                }
            })
        }, 300);
    });
}

/**
 * Adjust the settings for WpStream Settings
 *
 * Binds change/input handlers on the individual setting controls (checkboxes,
 * text inputs, selects and range sliders) so each one is saved via AJAX as it
 * changes. Text inputs are debounced to avoid a save on every keystroke.
 *
 * @return {void}
 */
function wpstream_save_settings() {
    // Security nonce shared by all the save requests below.
    var nonce = jQuery('#wpstream-settings-nonce').val();
    // Checkbox settings: save immediately on change.
    jQuery('.wpstream_option_wrapper .wpstream_event_option_itemc').on('change',function(e){
        // Get the new value (1 if checked, 0 if unchecked)
        var checkedState = e.target.checked ? 1 : 0;

        // Show the saving spinner.
        jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','visible');
        // Persist this single checkbox setting.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            timeout: 300000,
            data: {
                'action'        : 'wpstream_update_settings',
                'option_name'   : jQuery(this).attr('name'),
                'option_type'   : jQuery(this).attr('type'),
                'option_value'  : checkedState,
                'security'      : nonce
            },
            success: function (data) {
                // Hide the spinner once the save completes.
                jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','hidden');
            },
            error: function (jqXHR,textStatus,errorThrown) {
                // Show a transient error message on failure.
                wpstream_show_error_message(jQuery('.theme_options_tab_wpstream .wpstream-save-settings'));
            }
        })
    })

    // Text inputs: save after the user pauses typing (debounced).
    jQuery('.wpstream_option_wrapper .wpstream-text-input-setting').on('input',function(e){
        // Read the option identity, value and type from the input.
        var option_name = jQuery(this).attr('id');
        var option_value = jQuery(this).val();
        var option_type = jQuery(this).attr('type');

        // Reset the pending debounce timer on every keystroke.
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout( function() {
            // Show the saving spinner.
            jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','visible');
            // Persist this text setting.
            jQuery.ajax({
                type: 'POST',
                url: ajaxurl,
                timeout: 300000,
                data: {
                    'action'        : 'wpstream_update_settings',
                    'option_name'   : option_name,
                    'option_type'   : option_type,
                    'option_value'  : option_value,
                    'security'      : nonce
                },
                success: function (data) {
                    // Hide the spinner once the save completes.
                    jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','hidden');
                },
                error: function (jqXHR,textStatus,errorThrown) {
                    // Show a transient error message on failure.
                    wpstream_show_error_message(jQuery('.theme_options_tab_wpstream .wpstream-save-settings'));
                }
            })
        }, 1000); // Wait for 3 seconds after the user stops typing
    });

    // Select / multi-select settings: save immediately on change.
    jQuery('.wpstream_option_wrapper select').on('change',function(e){
        // Derive the option name from the associated label's "for" attribute.
        var option_name = jQuery(this).siblings('label').attr('for');
        var option_value = jQuery(this).val();
        // Distinguish single vs multiple selects for the server.
        var option_type = jQuery(this).prop('multiple') ? 'multiple-select' : 'select';

        // Show the saving spinner.
        jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','visible');
        // Persist this select setting.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            timeout: 300000,
            data: {
                'action'        : 'wpstream_update_settings',
                'option_name'   : option_name,
                'option_type'   : option_type,
                'option_value'  : option_value,
                'security'      : nonce
            },
            success: function (data) {
                // Hide the spinner once the save completes.
                jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','hidden');
            },
            error: function (jqXHR,textStatus,errorThrown) {
                // Show a transient error message on failure.
                wpstream_show_error_message(jQuery('.theme_options_tab_wpstream .wpstream-save-settings'));
            }
        })
    })

    // Range slider settings: save immediately on change.
    jQuery('.wpstream_option_wrapper .wpstream-range-input').on('change',function(e){
        // Read the option identity, value and type from the slider.
        var option_name = jQuery(this).attr('id');
        var option_value = jQuery(this).val();
        var option_type = jQuery(this).attr('type');

        // Show the saving spinner.
        jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','visible');
        // Persist this range setting.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            timeout: 300000,
            data: {
                'action'        : 'wpstream_update_settings',
                'option_name'   : option_name,
                'option_type'   : option_type,
                'option_value'  : option_value,
                'security'      : nonce
            },
            success: function (data) {
                // Hide the spinner once the save completes.
                jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','hidden');
            },
            error: function (jqXHR,textStatus,errorThrown) {
                // Show a transient error message on failure.
                wpstream_show_error_message(jQuery('.theme_options_tab_wpstream .wpstream-save-settings'));
            }
        })
    })
}

/*
*
* Show error message when the settings are not saved
*
*/
/**
 * Show a transient "could not save" error message inside a container.
 *
 * @param {jQuery} container The settings-save wrapper to display the error in.
 * @return {void}
 */
function wpstream_show_error_message(container) {
    // Hide the spinner (the save has failed, not still running).
    container.find('.spinner').css('visibility', 'hidden');
    // Append the localized error message.
    container.append('<div class="wpstream-error-message">' + wpstream_settings_vars.error_message + '</div>');
    // Fade the message in, hold it briefly, then fade out and remove it.
    container.find('.wpstream-error-message').hide().fadeIn(400).delay(3000).fadeOut(400, function() {
        jQuery(this).remove();
    });
}

/**
 * Bind the "update plugin" button on the support tab to an AJAX self-update.
 *
 * @return {void}
 */
function wpstream_update_plugin_support_tab() {
    // Security nonce read from a hidden field on the page.
    var nonce = jQuery('#wpstream-settings-nonce').val();
    // Handle clicks on the update-plugin button.
    jQuery('.wpstream-update-plugin-button').on('click', function(e) {
        e.preventDefault();
        // Replace the button with an "Updating..." indicator.
        jQuery(this).parents('.update-button-wrapper').html('Updating... <span class="spinner" style="visibility: visible;"></span>');
        // Ask the server to run the plugin update.
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            timeout: 300000,
            data: {
                'action'    : 'wpstream_settings_tab_update_plugin',
                'security'  : nonce
            },
            success: function (data) {
                // Report success or failure in place of the button.
                if (data.success) {
                    jQuery('.update-button-wrapper').html(wpstream_settings_vars.update_successful);
                } else {
                    jQuery('.update-button-wrapper').html(wpstream_settings_vars.update_failed);
                }
            },
            error: function (jqXHR,textStatus,errorThrown) {
                // Show the failure message on a transport error.
                jQuery('.update-button-wrapper').html(wpstream_settings_vars.update_failed);
            }
        })
    });
}

/**
 * Open the browser broadcaster for a channel in a new window.
 *
 * Bound to the "start webcaster" control; ignored when the control is inactive
 * or has no channel id. (Helper is defined but not currently invoked.)
 *
 * @return {void}
 */
function wpstream_broadcaster_bind() {
    // Handle clicks on the start-webcaster control.
    jQuery('.start_webcaster').on('click', function(e) {
        // Do nothing if the control is marked inactive.
        if (jQuery(this).hasClass('wpstream_inactive_icon')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();

        // Get the channel ID from the data attribute
        var channelId = jQuery(this).closest('.event_list_unit').data('show-id');

        // Bail out if no channel id is available.
        if (!channelId) {
            return;
        }

        // Open the broadcaster in a new window
        var broadcasterUrl = wpstream_settings_vars.broadcaster_url + channelId;
        window.open(broadcasterUrl, 'wpstream_broadcaster_' + channelId, 'fullscreen=yes');
    });

}