/**
 * WpStream quality selector for Video.js (VHS/HLS).
 *
 * Avoids external selector plugins that can break on Video.js 8.
 * Requires: videojs-contrib-quality-levels
 */
(function (window) {
  'use strict';

  if (!window.videojs) {
    return;
  }

  const videojs = window.videojs;

  function isHlsPlayer(player) {
    try {
      // VHS exposes qualityLevels() on the player when HLS/MPD tech is used
      return typeof player.qualityLevels === 'function';
    } catch (e) {
      return false;
    }
  }

  function getLevels(player) {
    try {
      const ql = player.qualityLevels();
      if (!ql) return [];
      const arr = [];
      for (let i = 0; i < ql.length; i++) arr.push(ql[i]);
      return arr;
    } catch (e) {
      return [];
    }
  }

  function prettyLabel(level) {
    if (!level) return '';
    const h = level.height;
    const w = level.width;
    const br = level.bitrate;
    if (h) return `${h}p`;
    if (w && level.height) return `${w}x${level.height}`;
    if (br) return `${Math.round(br / 1000)} kbps`;
    return 'Quality';
  }

  function enableAll(levels) {
    levels.forEach((l) => {
      try {
        l.enabled = true;
      } catch (e) {}
    });
  }

  function enableOnly(levels, selected) {
    levels.forEach((l) => {
      try {
        l.enabled = l === selected;
      } catch (e) {}
    });
  }

  const MenuButton = videojs.getComponent('MenuButton');
  const MenuItem = videojs.getComponent('MenuItem');

  class WpstreamQualityMenuItem extends MenuItem {
    constructor(player, options) {
      super(player, options);
      this.wpstreamLevel = options.wpstreamLevel || null;
      this.wpstreamIsAuto = !!options.wpstreamIsAuto;
      this.controlText(options.label || '');
      this.addClass('vjs-wpstream-quality-item');
      this.selected(!!options.selected);
    }

    handleClick() {
      const player = this.player();
      const levels = getLevels(player);

      if (this.wpstreamIsAuto) {
        enableAll(levels);
        try {
          player.trigger('wpstreamqualitychange');
        } catch (e) {}
        return;
      }

      if (this.wpstreamLevel) {
        enableOnly(levels, this.wpstreamLevel);
        try {
          player.trigger('wpstreamqualitychange');
        } catch (e) {}
      }
    }
  }

  class WpstreamQualityMenuButton extends MenuButton {
    constructor(player, options) {
      super(player, options);
      this.addClass('vjs-wpstream-quality');
      this.controlText('Quality');

      // Rebuild menu when quality levels change.
      player.on('loadedmetadata', () => this.update());
      player.on('loadeddata', () => this.update());
      player.on('wpstreamqualitychange', () => this.update());

      try {
        const ql = player.qualityLevels && player.qualityLevels();
        if (ql && typeof ql.on === 'function') {
          ql.on('addqualitylevel', () => this.update());
          ql.on('removequalitylevel', () => this.update());
          ql.on('change', () => this.update());
        }
      } catch (e) {}
    }

    createItems() {
      const player = this.player();
      const levels = getLevels(player);

      // Always show the menu button, even if there's only one level.
      // This is useful for debugging / confirming the stream is being interpreted as HLS.
      try {
        this.show();
      } catch (e) {}

      const sorted = levels
        .slice()
        .sort((a, b) => (b.height || 0) - (a.height || 0) || (b.bitrate || 0) - (a.bitrate || 0));

      const items = [];

      // Auto
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
      const seen = new Set();
      sorted.forEach((level) => {
        const label = prettyLabel(level);
        if (!label) return;
        if (seen.has(label)) return;
        seen.add(label);

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

  videojs.registerComponent('WpstreamQualityMenuButton', WpstreamQualityMenuButton);

  /**
   * Install the quality menu on a player.
   */
  function install(player) {
    if (!player || player.isDisposed && player.isDisposed()) return;

    // Ensure we only install once.
    if (player.wpstreamQualitySelectorInitialized) return;
    player.wpstreamQualitySelectorInitialized = true;

    // Only for VHS/HLS players.
    if (!isHlsPlayer(player)) return;

    player.ready(function () {
      try {
        const cb = player.getChild('controlBar');
        if (!cb) return;

        // Avoid duplicates.
        if (cb.getChild('WpstreamQualityMenuButton')) return;

        // Place near the right side (before fullscreen if available)
        const children = cb.children();
        let insertIndex = children ? children.length - 2 : undefined;
        if (insertIndex < 0) insertIndex = undefined;

        cb.addChild('WpstreamQualityMenuButton', {}, insertIndex);
      } catch (e) {
        // ignore
      }
    });
  }

  window.wpstreamInstallQualitySelector = install;
})(window);

