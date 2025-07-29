"use strict";

jQuery(document).ready(function () {
    wpstream_admin_sortable_for_bundle();
    wpstream_admin_autocomplete_items_bundle();
});

/**
 *  Sortable function
 */
function wpstream_admin_sortable_for_bundle() {
    jQuery("#sortable1, #sortable2").sortable({
        connectWith: ".connectedSortable",
        stop: function (event, ui) {
            const sourceList = ui.item.parent().attr("id");
            const postID = ui.item.attr("data-postID");

            if (sourceList === "sortable1") {
                updateBundleSelectionValue()
            } else if (sourceList === "sortable2") {
                updateBundleSelectionValue()
            }
        }
    }).disableSelection();

    // Event handler for adding items
    jQuery('#sortable1 li').click(function () {
        wpstream_sortable_1_action(jQuery(this))
    });

    // Event handler for removing items
    jQuery('#sortable2 li').click(function () {
        wpstream_sortable_2_action(jQuery(this))
    });
}

/**
 * Sortable function for list 1
 * @param item
 */
function wpstream_sortable_1_action(item) {
    // Clone the clicked item and append it to sortable2
    const listItem = item.clone();
    listItem.appendTo('#sortable2');
    listItem.click(function () {
        wpstream_sortable_2_action(jQuery(this))
    });

    item.remove();
    updateBundleSelectionValue()
}

/**
 * Sortable function for list 2
 * @param item
 */
function wpstream_sortable_2_action(item){
    // Remove the clicked item from sortable2

    var  listItem=item.clone();
    jQuery('#sortable1').prepend(listItem);
    listItem.click(function () {
        wpstream_sortable_1_action(  jQuery(this) )
    });
    item.remove();
    updateBundleSelectionValue()
}

/**
 * Autocomplete search
 */
function wpstream_admin_autocomplete_items_bundle() {
    jQuery('.wpstream_item_autocomplete_search').autocomplete({
        source: function (request, response) {
            jQuery('#wpstream_autocomplete_status').text(wpstream_custom_metabox_script_vars.searching_text);

            jQuery.ajax({
                url: wpstream_custom_metabox_script_vars.ajaxurl, // WordPress AJAX endpoint
                dataType: 'json',
                type: 'POST',
                data: {
                    action: 'wpstream_product_autocomplete', // AJAX action hook
                    term: request.term
                },
                beforeSend: function () {
                    // Change the text of the status element to "searching" before sending the AJAX request
                    jQuery('#wpstream_autocomplete_status').text(wpstream_custom_metabox_script_vars.searching_text);
                },
                success: function (data) {
                    if (data.length === 0) {
                        // Change the text of the status element to "no items found" if no results are returned
                        jQuery('#wpstream_autocomplete_status').text(wpstream_custom_metabox_script_vars.no_items);
                    } else {
                        // Pass the retrieved data to the autocomplete response
                        response(data);
                        // Change the text of the status element to "please select" if results are returned
                        jQuery('#wpstream_autocomplete_status').text(wpstream_custom_metabox_script_vars.please_select);
                    }

                    response(data); // Pass the retrieved data to the autocomplete response
                },
                error: function (errorThrown) {
                    jQuery('#wpstream_autocomplete_status').text(wpstream_custom_metabox_script_vars.error_text);
                }
            });
        },
        minLength: 1,
        select: function (event, ui) {
            const postID = ui.item.value;
            const title = ui.item.label;

            // Create the <li> element with custom content
            const listItem = jQuery('<li>').addClass('ui-state-default').attr('data-postID', postID);

            const type = ui.item.type;
            const meta_free = ui.item.meta_free;
            const meta_type = ui.item.meta_type;

            listItem.append(title);
            listItem.append('<div class="wpstream_product_list_type_wrapper"><span class="wpstream_product_list_free">' + meta_free + '</span><span class="wpstream_product_list_type">' + meta_type + '</span>');

            // Append the <li> element to sortable2
            jQuery('#sortable2').append(listItem);
            listItem.click(function () {
                wpstream_sortable_2_action(jQuery(this))
            });

            jQuery('#sortable1 li[data-postID="' + postID + '"]').remove();
            updateBundleSelectionValue(postID);

            return false; // Prevent the default select behavior
        }// Minimum characters to trigger autocomplete
    });
}

/**
 * Update selection values
 */
function updateBundleSelectionValue() {
    const bundleSelectionInput = jQuery('#wpstream_bundle_selection');
    const postIDs = [];

    jQuery('#sortable2 li').each(function () {
        const postID = jQuery(this).attr('data-postID');
        postIDs.push(postID);
    });

    const postIDsString = postIDs.join(',');
    bundleSelectionInput.val(postIDsString);
}