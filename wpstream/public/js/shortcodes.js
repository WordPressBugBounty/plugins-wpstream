/*
 * WpStream TinyMCE shortcode buttons.
 *
 * Registers three toolbar buttons for the WordPress Classic Editor (TinyMCE).
 * Each button, when clicked, inserts a WpStream shortcode skeleton into the
 * editor content so authors can embed a player or media list without typing
 * the shortcode by hand:
 *   - wpstream_player                  -> [wpstream_player ...]
 *   - wpstream_list_products           -> [wpstream_list_products ...]
 *   - wpstream_list_products_channels  -> [wpstream_list_products_channels]
 *
 * Each button lives in its own IIFE and follows the standard TinyMCE plugin
 * shape: create the plugin definition, then register it with the PluginManager.
 */

// IIFE #1: register the "WpStream Player" shortcode button.
(function () {
    "use strict";
    // Define the TinyMCE plugin that provides the player-insertion button.
    tinymce.create('tinymce.plugins.wpstream_player', {
        /**
         * TinyMCE plugin init: add the toolbar button to the editor.
         *
         * @param {object} ed  The TinyMCE editor instance.
         * @param {string} url Base URL of this plugin's asset folder (for images).
         */
        init: function (ed, url) {
            // Register the toolbar button and its click behaviour.
            ed.addButton('wpstream_player', {
                title: 'WpStream Player',              // tooltip shown on hover
                image: url + '/button.png',            // button icon, resolved against the asset URL
                onclick: function () {
                    // Insert an empty player shortcode skeleton at the cursor.
                    ed.selection.setContent('[wpstream_player id="Add here the live stream id or the video id" ][/wpstream_player]');
                }
            });
        },
        /**
         * TinyMCE plugin createControl hook: no custom control is provided.
         *
         * @param {string} n  Control name requested by TinyMCE.
         * @param {object} cm Control manager.
         * @return {null} Always null (this plugin only adds a button).
         */
        createControl: function (n, cm) {
            return null;
        }
    });
    // Register the completed plugin definition with TinyMCE.
    tinymce.PluginManager.add('wpstream_player', tinymce.plugins.wpstream_player);
})();


// IIFE #2: register the "WpStream List Products" shortcode button.
(function () {
    // Define the TinyMCE plugin that inserts a media-list shortcode.
    tinymce.create('tinymce.plugins.wpstream_list_products', {

        /**
         * TinyMCE plugin init: add the list-products toolbar button.
         *
         * @param {object} ed  The TinyMCE editor instance.
         * @param {string} url Base URL of this plugin's asset folder (for images).
         */
        init: function (ed, url) {
            // Register the toolbar button and its click behaviour.
            ed.addButton('wpstream_list_products', {
                title: 'WpStream List Products',       // tooltip shown on hover
                image:  url + '/list_media.png',       // button icon, resolved against the asset URL
                onclick: function () {
                    // Insert a list-products shortcode skeleton with placeholder attributes.
                    ed.selection.setContent('[wpstream_list_products media_number="No of media" product_type="Free Live Channel or Free Video"][/wpstream_list_products]');
                }
            });
        },
        /**
         * TinyMCE plugin createControl hook: no custom control is provided.
         *
         * @param {string} n  Control name requested by TinyMCE.
         * @param {object} cm Control manager.
         * @return {null} Always null (this plugin only adds a button).
         */
        createControl: function (n, cm) {
            return null;
        }
    });
    // Register the completed plugin definition with TinyMCE.
    tinymce.PluginManager.add('wpstream_list_products', tinymce.plugins.wpstream_list_products);
})();

// IIFE #3: register the "WpStream List Channels" shortcode button.
(function () {
    // Define the TinyMCE plugin that inserts a channel-list shortcode.
    tinymce.create('tinymce.plugins.wpstream_list_products_channels', {

        /**
         * TinyMCE plugin init: add the list-channels toolbar button.
         *
         * @param {object} ed  The TinyMCE editor instance.
         * @param {string} url Base URL of this plugin's asset folder (for images).
         */
        init: function (ed, url) {
            // Register the toolbar button and its click behaviour.
            ed.addButton('wpstream_list_products_channels', {
                title: 'WpStream List Channels',       // tooltip shown on hover
                image:  url + '/list_media.png',       // button icon, resolved against the asset URL
                onclick: function () {
                    // Insert an empty list-channels shortcode skeleton (no attributes).
                    ed.selection.setContent('[wpstream_list_products_channels][/wpstream_list_products_channels]');
                }
            });
        },
        /**
         * TinyMCE plugin createControl hook: no custom control is provided.
         *
         * @param {string} n  Control name requested by TinyMCE.
         * @param {object} cm Control manager.
         * @return {null} Always null (this plugin only adds a button).
         */
        createControl: function (n, cm) {
            return null;
        }
    });
    // Register the completed plugin definition with TinyMCE.
    tinymce.PluginManager.add('wpstream_list_products_channels', tinymce.plugins.wpstream_list_products_channels);
})();
