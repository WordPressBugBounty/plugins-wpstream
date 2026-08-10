/**
 * WpStream quality selector for Video.js (VHS/HLS).
 *
 * Avoids external selector plugins that can break on Video.js 8.
 * Requires: videojs-contrib-quality-levels
 */
// IIFE keeps every helper and component class out of the global scope; only the
// installer (window.wpstreamInstallQualitySelector) is exported at the end.
(function (window) {
  'use strict';

  // Nothing to do if Video.js was not loaded on this page.
  if (!window.videojs) {
    return;
  }

  // Local handle to the global Video.js factory.
  const videojs = window.videojs;

  /**
   * Detect whether a player is backed by VHS (HTTP streaming / HLS-MPD tech).
   *
   * @param {Object} player Video.js player instance.
   * @return {boolean} True when the player exposes qualityLevels().
   */
  function isHlsPlayer(player) {
    try {
      // VHS exposes qualityLevels() on the player when HLS/MPD tech is used
      return typeof player.qualityLevels === 'function';
    } catch (e) {
      // Any access error means we cannot treat this as an HLS player.
      return false;
    }
  }

  /**
   * Read the current quality levels off a player as a plain array.
   *
   * @param {Object} player Video.js player instance.
   * @return {Array} Snapshot of quality-level objects (empty on failure).
   */
  function getLevels(player) {
    try {
      // The QualityLevelList is array-like but not a real Array.
      const ql = player.qualityLevels();
      if (!ql) return [];
      // Copy each indexed level into a real array we can slice/sort/iterate.
      const arr = [];
      for (let i = 0; i < ql.length; i++) arr.push(ql[i]);
      return arr;
    } catch (e) {
      // Player disposed or plugin missing: behave as if there are no levels.
      return [];
    }
  }

  /**
   * Build a human-readable label for a single quality level.
   *
   * @param {Object} level A quality-level object (may have height/width/bitrate).
   * @return {string} Label such as "720p", "1280x720", "800 kbps", or fallback.
   */
  function prettyLabel(level) {
    if (!level) return '';
    // Prefer resolution height, then explicit dimensions, then bitrate.
    const h = level.height;
    const w = level.width;
    const br = level.bitrate;
    if (h) return `${h}p`;                                 // e.g. "720p"
    if (w && level.height) return `${w}x${level.height}`;  // e.g. "1280x720"
    if (br) return `${Math.round(br / 1000)} kbps`;        // e.g. "800 kbps"
    // Nothing useful to show: generic fallback label.
    return 'Quality';
  }

  /**
   * Enable every quality level (used by the "Auto" choice so VHS is free to pick).
   *
   * @param {Array} levels Quality-level objects to enable.
   */
  function enableAll(levels) {
    levels.forEach((l) => {
      try {
        // Turning all levels on hands adaptive selection back to VHS.
        l.enabled = true;
      } catch (e) {}  // ignore levels that reject the assignment
    });
  }

  /**
   * Restrict playback to a single chosen level, disabling all others.
   *
   * @param {Array} levels Quality-level objects to toggle.
   * @param {Object} selected The one level that should stay enabled.
   */
  function enableOnly(levels, selected) {
    levels.forEach((l) => {
      try {
        // Only the selected level stays enabled; everything else is off.
        l.enabled = l === selected;
      } catch (e) {}  // ignore levels that reject the assignment
    });
  }

  // Base Video.js components we extend to build the quality control.
  const MenuButton = videojs.getComponent('MenuButton');
  const MenuItem = videojs.getComponent('MenuItem');

  /**
   * A single clickable row in the quality menu (either "Auto" or one level).
   */
  class WpstreamQualityMenuItem extends MenuItem {
    /**
     * @param {Object} player  Owning Video.js player.
     * @param {Object} options Item config: label, wpstreamLevel, wpstreamIsAuto, selected.
     */
    constructor(player, options) {
      super(player, options);
      // The concrete quality level this row selects (null for the Auto row).
      this.wpstreamLevel = options.wpstreamLevel || null;
      // Flag marking this row as the adaptive "Auto" choice.
      this.wpstreamIsAuto = !!options.wpstreamIsAuto;
      // Screen-reader / control text for the row.
      this.controlText(options.label || '');
      // Styling hook for the menu item.
      this.addClass('vjs-wpstream-quality-item');
      // Reflect whether this row is the currently active choice.
      this.selected(!!options.selected);
    }

    /**
     * Apply this row's quality choice when the user clicks it.
     */
    handleClick() {
      const player = this.player();
      // Re-read levels fresh; the list can change as segments load.
      const levels = getLevels(player);

      // Auto row: re-enable every level and let VHS adapt.
      if (this.wpstreamIsAuto) {
        enableAll(levels);
        try {
          // Notify the menu button so it rebuilds/repaints the selection.
          player.trigger('wpstreamqualitychange');
        } catch (e) {}
        return;
      }

      // Specific level row: lock playback to just that level.
      if (this.wpstreamLevel) {
        enableOnly(levels, this.wpstreamLevel);
        try {
          // Notify the menu button so it rebuilds/repaints the selection.
          player.trigger('wpstreamqualitychange');
        } catch (e) {}
      }
    }
  }

  /**
   * The control-bar button that opens the quality menu and keeps it in sync.
   */
  class WpstreamQualityMenuButton extends MenuButton {
    /**
     * @param {Object} player  Owning Video.js player.
     * @param {Object} options Standard Video.js component options.
     */
    constructor(player, options) {
      super(player, options);
      // Styling hook + accessible label for the button.
      this.addClass('vjs-wpstream-quality');
      this.controlText('Quality');

      // Rebuild menu when quality levels change.
      // Metadata/data load and our custom event all warrant a rebuild.
      player.on('loadedmetadata', () => this.update());
      player.on('loadeddata', () => this.update());
      player.on('wpstreamqualitychange', () => this.update());

      try {
        // Also react to VHS's own quality-level list mutations, if available.
        const ql = player.qualityLevels && player.qualityLevels();
        if (ql && typeof ql.on === 'function') {
          ql.on('addqualitylevel', () => this.update());     // new rendition appeared
          ql.on('removequalitylevel', () => this.update());  // rendition dropped
          ql.on('change', () => this.update());              // active rendition switched
        }
      } catch (e) {}  // quality-levels plugin absent: static menu is fine
    }

    /**
     * Build the list of menu rows (Auto + one row per distinct quality level).
     *
     * @return {Array} Array of WpstreamQualityMenuItem instances.
     */
    createItems() {
      const player = this.player();
      const levels = getLevels(player);

      // Always show the menu button, even if there's only one level.
      // This is useful for debugging / confirming the stream is being interpreted as HLS.
      try {
        this.show();
      } catch (e) {}

      // Sort a copy highest-quality first: by height, then bitrate as tiebreak.
      const sorted = levels
        .slice()
        .sort((a, b) => (b.height || 0) - (a.height || 0) || (b.bitrate || 0) - (a.bitrate || 0));

      // Accumulates the menu rows we will return.
      const items = [];

      // Auto
      // First row is always "Auto"; selected when every level is currently enabled.
      items.push(
        new WpstreamQualityMenuItem(player, {
          label: 'Auto',
          wpstreamIsAuto: true,
          selectable: true,
          selected: sorted.length > 0 ? sorted.every((l) => l.enabled === true) : true,
        })
      );

      // If VHS hasn't populated levels yet, keep Auto only (menu still visible).
      if (sorted.length === 0) {
        return items;
      }

      // Dedupe by label (common case: duplicate heights)
      // Set tracks labels already added so we don't list the same resolution twice.
      const seen = new Set();
      sorted.forEach((level) => {
        const label = prettyLabel(level);
        if (!label) return;            // skip levels with no usable label
        if (seen.has(label)) return;   // skip duplicates of an already-listed label
        seen.add(label);

        // One row per distinct level; selected only when it is the sole enabled level.
        items.push(
          new WpstreamQualityMenuItem(player, {
            label,
            wpstreamLevel: level,
            selectable: true,
            selected: level.enabled === true && sorted.filter((l) => l.enabled === true).length === 1,
          })
        );
      });

      return items;
    }
  }

  // Register the button so it can be added to a player's control bar by name.
  videojs.registerComponent('WpstreamQualityMenuButton', WpstreamQualityMenuButton);

  /**
   * Install the quality menu on a player.
   *
   * @param {Object} player Video.js player to attach the quality control to.
   */
  function install(player) {
    // Skip missing or already-disposed players.
    if (!player || player.isDisposed && player.isDisposed()) return;

    // Ensure we only install once.
    // Guard flag prevents adding a second button on repeat calls.
    if (player.wpstreamQualitySelectorInitialized) return;
    player.wpstreamQualitySelectorInitialized = true;

    // Only for VHS/HLS players.
    if (!isHlsPlayer(player)) return;

    // Defer until the player is ready so its control bar exists.
    player.ready(function () {
      try {
        // Locate the control bar we will inject the button into.
        const cb = player.getChild('controlBar');
        if (!cb) return;

        // Avoid duplicates.
        if (cb.getChild('WpstreamQualityMenuButton')) return;

        // Place near the right side (before fullscreen if available)
        // Insert two slots from the end so it sits just before the fullscreen toggle.
        const children = cb.children();
        let insertIndex = children ? children.length - 2 : undefined;
        if (insertIndex < 0) insertIndex = undefined;  // fall back to default position

        cb.addChild('WpstreamQualityMenuButton', {}, insertIndex);
      } catch (e) {
        // ignore
      }
    });
  }

  // Expose the installer so player bootstrap code can call it per player.
  window.wpstreamInstallQualitySelector = install;
})(window);

