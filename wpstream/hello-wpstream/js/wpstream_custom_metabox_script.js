"use strict";

/*
 * Admin metabox script for the "bundle" product editor.
 *
 * Powers the two-list bundle builder on the product edit screen: a left list
 * (#sortable1) of items available to add and a right list (#sortable2) of items
 * currently in the bundle. Items move between the lists on click or drag, an
 * autocomplete search box lets the editor pull in additional items via AJAX,
 * and every change is serialised into the hidden #wpstream_bundle_selection
 * field as a comma-separated list of post IDs that WordPress saves with the post.
 *
 * Localised data (ajaxurl + status strings) arrives in the global
 * `wpstream_custom_metabox_script_vars`, printed by PHP via wp_localize_script.
 */

// Wire up the sortable lists and the autocomplete search once the DOM is ready.
jQuery(document).ready(function () {
    wpstream_admin_sortable_for_bundle();
    wpstream_admin_autocomplete_items_bundle();
});

/**
 * Initialise the two connected sortable lists and their click handlers.
 *
 * Makes #sortable1 (available) and #sortable2 (selected) drag-sortable and
 * connected, so items can be dragged between them; on drag-stop it re-serialises
 * the current selection. Also wires single-click to move an item to the other list.
 */
function wpstream_admin_sortable_for_bundle() {
    // Turn both lists into jQuery UI sortables that share the same connect group.
    jQuery("#sortable1, #sortable2").sortable({
        connectWith: ".connectedSortable",
        // Fired after a drag completes: figure out which list the item landed in.
        stop: function (event, ui) {
            // Id of the list the dragged item now belongs to.
            const sourceList = ui.item.parent().attr("id");
            // Post ID carried on the dragged item (unused here, kept for context).
            const postID = ui.item.attr("data-postID");

            // Either way the selection changed, so refresh the hidden field.
            if (sourceList === "sortable1") {
                updateBundleSelectionValue()
            } else if (sourceList === "sortable2") {
                updateBundleSelectionValue()
            }
        }
    }).disableSelection(); // Stop text selection interfering with dragging.

    // Event handler for adding items
    // Clicking an available item (list 1) moves it into the selected list.
    jQuery('#sortable1 li').click(function () {
        wpstream_sortable_1_action(jQuery(this))
    });

    // Event handler for removing items
    // Clicking a selected item (list 2) moves it back to the available list.
    jQuery('#sortable2 li').click(function () {
        wpstream_sortable_2_action(jQuery(this))
    });
}

/**
 * Move an item from the available list (1) into the selected list (2).
 *
 * @param {jQuery} item The clicked <li> in #sortable1 to add to the bundle.
 */
function wpstream_sortable_1_action(item) {
    // Clone the clicked item and append it to sortable2
    const listItem = item.clone();
    listItem.appendTo('#sortable2');
    // Give the clone the reverse click handler so it can be removed later.
    listItem.click(function () {
        wpstream_sortable_2_action(jQuery(this))
    });

    // Drop the original from list 1 and re-serialise the selection.
    item.remove();
    updateBundleSelectionValue()
}

/**
 * Move an item from the selected list (2) back into the available list (1).
 *
 * @param {jQuery} item The clicked <li> in #sortable2 to remove from the bundle.
 */
function wpstream_sortable_2_action(item){
    // Remove the clicked item from sortable2

    // Clone the item and put it back at the top of the available list.
    var  listItem=item.clone();
    jQuery('#sortable1').prepend(listItem);
    // Re-attach the add handler to the returned clone.
    listItem.click(function () {
        wpstream_sortable_1_action(  jQuery(this) )
    });
    // Drop the original from list 2 and re-serialise the selection.
    item.remove();
    updateBundleSelectionValue()
}

/**
 * Attach a jQuery UI autocomplete to the item search box.
 *
 * As the editor types, it queries the `wpstream_product_autocomplete` AJAX
 * action for matching items, shows live status text, and on selection builds a
 * new selected-list <li> (with free/type badges), removes any matching entry
 * from the available list, and updates the hidden selection field.
 */
function wpstream_admin_autocomplete_items_bundle() {
	// The search input; bail out if it is not present on this screen.
	const $searchInput = jQuery('.wpstream_item_autocomplete_search');

	if ( !$searchInput.length ) {
		return;
	}

    $searchInput.autocomplete({
        // Custom source: fetch matching items from the server for the typed term.
        source: function (request, response) {
            // Show the "searching" status while the request is in flight.
            jQuery('#wpstream_autocomplete_status').text(wpstream_custom_metabox_script_vars.searching_text);

            jQuery.ajax({
                url: wpstream_custom_metabox_script_vars.ajaxurl, // WordPress AJAX endpoint
                dataType: 'json',
                type: 'POST',
                data: {
                    action: 'wpstream_product_autocomplete', // AJAX action hook
                    term: request.term // What the user has typed so far.
                },
                beforeSend: function () {
                    // Change the text of the status element to "searching" before sending the AJAX request
                    jQuery('#wpstream_autocomplete_status').text(wpstream_custom_metabox_script_vars.searching_text);
                },
                // Handle a successful response: results are an array of item objects.
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
                // On failure, surface the localised error status text.
                error: function (errorThrown) {
                    jQuery('#wpstream_autocomplete_status').text(wpstream_custom_metabox_script_vars.error_text);
                }
            });
        },
        minLength: 1, // Start searching after a single typed character.
        // Fired when the editor picks a suggestion: build and insert the item.
        select: function (event, ui) {
            // Chosen item's post ID (value) and display title (label).
            const postID = ui.item.value;
            const title = ui.item.label;

            // Create the <li> element with custom content
            const listItem = jQuery('<li>').addClass('ui-state-default').attr('data-postID', postID);

            // Extra metadata used to render the free/type badges on the item.
            const type = ui.item.type;
            const meta_free = ui.item.meta_free;
            const meta_type = ui.item.meta_type;

            // Put the title text into the new list item.
            listItem.append(title);
            // Append the free/type badge markup after the title.
            listItem.append('<div class="wpstream_product_list_type_wrapper"><span class="wpstream_product_list_free">' + meta_free + '</span><span class="wpstream_product_list_type">' + meta_type + '</span>');

            // Append the <li> element to sortable2
            jQuery('#sortable2').append(listItem);
            // Attach the remove handler so this item can be clicked back out.
            listItem.click(function () {
                wpstream_sortable_2_action(jQuery(this))
            });

            // Drop any matching entry from the available list to avoid duplicates.
            jQuery('#sortable1 li[data-postID="' + postID + '"]').remove();
            // Re-serialise the selection now that a new item was added.
            updateBundleSelectionValue(postID);

            return false; // Prevent the default select behavior
        }// Minimum characters to trigger autocomplete
    });
}

/**
 * Serialise the selected list into the hidden bundle field.
 *
 * Reads the data-postID of every <li> currently in #sortable2 and writes them
 * as a comma-separated string into #wpstream_bundle_selection, which WordPress
 * persists when the product is saved.
 */
function updateBundleSelectionValue() {
    // The hidden input that stores the bundle's ordered item IDs.
    const bundleSelectionInput = jQuery('#wpstream_bundle_selection');
    const postIDs = [];

    // Collect the post ID from each item in the selected list, in order.
    jQuery('#sortable2 li').each(function () {
        const postID = jQuery(this).attr('data-postID');
        postIDs.push(postID);
    });

    // Join into a comma-separated list and store it in the hidden field.
    const postIDsString = postIDs.join(',');
    bundleSelectionInput.val(postIDsString);
}