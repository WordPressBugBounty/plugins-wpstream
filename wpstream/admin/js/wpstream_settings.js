var debounceTimer;

jQuery(document).ready(function($) {
    // Save default channel options
    wpstream_save_default_channel_options();

    // Save settings
    wpstream_save_settings();
});

/**
 * Save the changes to the Default Channel Settings tab
 */
function wpstream_save_default_channel_options() {
    jQuery('.theme_options_tab_wpstream .wpstream_event_option_item').on('click',function() {

        wpstream_adjust_settings_general(jQuery(this));

        var optionarray ={};
        var holder = jQuery(this).parents('.wpstream_option_wrapper');
        var nonce               =   jQuery('#wpstream-settings-nonce').val();

        jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','visible');
        var timer = setTimeout(function() {
            holder.find('.wpstream_event_option_item').each(function(){
                optionarray[jQuery(this).attr('data-attr-ajaxname')]=jQuery(this).prop("checked") ? 1 : 0 ;
            });

            var jsonOptions = JSON.stringify(optionarray);
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
                    jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','hidden');
                    console.log(data);
                },
                error: function (jqXHR,textStatus,errorThrown) {
                    wpstream_show_error_message(jQuery('.theme_options_tab_wpstream .wpstream-save-settings'));
                }
            })
        }, 300);
    });
}

/**
 * Adjust the settings for WpStream Settings
 */
function wpstream_save_settings() {
    var nonce = jQuery('#wpstream-settings-nonce').val();
    jQuery('.wpstream_option_wrapper .wpstream_event_option_itemc').on('change',function(e){
        // Get the new value (1 if checked, 0 if unchecked)
        var checkedState = e.target.checked ? 1 : 0;

        jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','visible');
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
                jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','hidden');
            },
            error: function (jqXHR,textStatus,errorThrown) {
                wpstream_show_error_message(jQuery('.theme_options_tab_wpstream .wpstream-save-settings'));
            }
        })
    })

    jQuery('.wpstream_option_wrapper .wpstream-text-input-setting').on('input',function(e){
        var option_name = jQuery(this).attr('id');
        var option_value = jQuery(this).val();
        var option_type = jQuery(this).attr('type');

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout( function() {
            jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','visible');
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
                    jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','hidden');
                },
                error: function (jqXHR,textStatus,errorThrown) {
                    wpstream_show_error_message(jQuery('.theme_options_tab_wpstream .wpstream-save-settings'));
                }
            })
        }, 1000); // Wait for 3 seconds after the user stops typing
    });

    jQuery('.wpstream_option_wrapper select').on('change',function(e){
        var option_name = jQuery(this).siblings('label').attr('for');
        var option_value = jQuery(this).val();
        var option_type = jQuery(this).prop('multiple') ? 'multiple-select' : 'select';

        jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','visible');
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
                jQuery('.theme_options_tab_wpstream .wpstream-save-settings').find('.spinner').css('visibility','hidden');
            },
            error: function (jqXHR,textStatus,errorThrown) {
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
function wpstream_show_error_message(container) {
    container.find('.spinner').css('visibility', 'hidden');
    container.append('<div class="wpstream-error-message">Failed to save settings. Please try again.</div>');
    container.find('.wpstream-error-message').hide().fadeIn(400).delay(3000).fadeOut(400, function() {
        jQuery(this).remove();
    });
}