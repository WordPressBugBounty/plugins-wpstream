/**
 * Live Channel editor — reveal the Live Streaming controls after the first save.
 *
 * The Live Streaming sidebar panel is a classic metabox rendered by PHP. The
 * block editor draws such metaboxes once, on page load, and never redraws them
 * after a save, so a channel that was new when the editor opened keeps
 * showing "save this channel" after it was saved. This script watches the
 * editor's store and reloads the page once a first save has completed, so the
 * panel comes back rendered for a saved channel.
 *
 * Only loaded on the block-editor screen of a never-saved channel; the
 * classic editor reloads on its own after a save. The enqueue declares the
 * editor package as a dependency, so the core/editor store exists here.
 *
 * @package Wpstream
 */
(function (wp) {
	'use strict';

	var editor = wp.data.select('core/editor');

	// True once a real save (not an autosave) has been seen in progress.
	var sawSave = false;

	var unsubscribe = wp.data.subscribe(function () {
		if (editor.isSavingPost() && !editor.isAutosavingPost()) {
			sawSave = true;
			return;
		}
		if (!sawSave) {
			return;
		}
		sawSave = false;

		// A save just finished. Once the channel exists the panel can load the broadcaster.
		if (editor.getCurrentPostAttribute('status') !== 'auto-draft') {
			unsubscribe();
			window.location.reload();
		}
	});
})(window.wp);
