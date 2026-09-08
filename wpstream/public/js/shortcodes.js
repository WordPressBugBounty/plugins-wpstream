/*
 * WpStream TinyMCE shortcode buttons.
 *
 * Registers the player toolbar button for the WordPress Classic Editor.
 */

// Register the "WpStream Player" shortcode button.
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
