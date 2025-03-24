window.WpStreamUtils = window.WpStreamUtils || {};

WpStreamUtils.generate_download_link = function(){
    jQuery('.wpstream_get_download_link').on('click',function(){
        var ajaxurl      =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var video_name          =   jQuery(this).attr('data-filename');
        var parent              =   jQuery(this).parent();

        jQuery(this).remove();
        parent.find('.wpstream_download_link').show().text('please wait...');

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'wpstream_get_download_link',
                'video_name': video_name,
            },
            success: function (data) {
                if( data.success === true ){
                    parent.find('.wpstream_download_link').show().text(wpstream_admin_control_vars.download_mess);
                    parent.find('.wpstream_download_link').show().attr('href',data.url);
                }else{
                    var error_message = data.error;
                    if( data.error === 'NOT_ENOUGH_TRAFFIC' ) {
                        error_message = 'Not Enough data to download!';
                    }

                    parent.find('.wpstream_download_link').show().text(error_message);
                }
            },
            error: function (errorThrown) {
                // error state
            }
        });
    });
}

WpStreamUtils.generate_delete_link = function() {
    jQuery('.wpstream_delete_media').on('click',function(){
        var ajaxurl             =   wpstream_admin_control_vars.admin_url + 'admin-ajax.php';
        var video_name          =   jQuery(this).attr('data-filename').trim();
        var parent              =   jQuery(this).parent();

        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                'action': 'wpstream_get_delete_file',
                'video_name': video_name

            },
            success: function (data) {
                if( data.success === true ){
                    parent.remove();
                }
            },
            error: function (errorThrown) {
                // error state
            }
        });
    });
}