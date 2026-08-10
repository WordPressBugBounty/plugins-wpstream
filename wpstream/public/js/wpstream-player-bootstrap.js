/*
 * wpstream-player-bootstrap.js
 *
 * Data-attribute driven player initializer for the WpStream plugin.
 *
 * Instead of printing an inline <script> next to every embedded player, the PHP
 * templates render a wrapper element carrying `data-wpstream-bootstrap="live"`,
 * `"live-low-latency"` or `"vod"` plus a set of `data-*` configuration attributes.
 * This IIFE scans the DOM for those markers, reads the attributes into a settings
 * object, and hands them to the matching global player-initialization function
 * (`wpstream_player_initialize`, `wpstream_player_initialize_vod`, or `initPlayer`).
 *
 * Because the player library scripts may load after this file, initialization is
 * retried on a short timer, and a MutationObserver picks up players injected into
 * the page later (e.g. via AJAX or block rendering). Each element is marked with
 * `data-wpstream-bootstrapped="1"` so it is only initialized once.
 */
(function () {
  "use strict";

  // Selector for standard (non low-latency) live player wrappers awaiting bootstrap.
  const LIVE_SELECTOR =
    '.wpstream_live_player_wrapper[data-wpstream-bootstrap="live"]:not(.wpstream_low_latency)';
  // Selector for low-latency (WebRTC/WHEP) live player wrappers.
  const LIVE_LOW_LATENCY_SELECTOR =
    '.wpstream_live_player_wrapper[data-wpstream-bootstrap="live-low-latency"]';
  // Selector for on-demand (VOD) <video> elements awaiting bootstrap.
  const VOD_SELECTOR = 'video[data-wpstream-bootstrap="vod"]';
  // Maximum number of retry passes while waiting for player libraries to load.
  const MAX_RETRIES = 40;
  // Delay (ms) between retry passes.
  const RETRY_DELAY_MS = 50;

  // Running count of retry passes performed so far (guards against infinite retries).
  let retryCount = 0;
  // Ensures the MutationObserver is only wired up once.
  let observerStarted = false;
  /**
   * Read an attribute from an element, returning "" when it is absent.
   *
   * @param {Element} element DOM element to read from.
   * @param {string}  name    Attribute name.
   * @return {string} Attribute value, or empty string if not set.
   */
  function getAttr(element, name) {
    // getAttribute returns null for missing attributes; coerce that to "".
    return element.getAttribute(name) || "";
  }

  /**
   * Interpret a data-attribute string as a boolean flag.
   *
   * @param {string} value Raw attribute value (e.g. "1", "true", "autoplay").
   * @return {boolean} True when the value is one of the accepted truthy tokens.
   */
  function parseBool(value) {
    // Normalize: coerce to string, trim surrounding space, lowercase.
    const normalized = String(value || "")
      .trim()
      .toLowerCase();

    // Treat any of these tokens as true; everything else is false.
    return (
      normalized === "1" ||
      normalized === "true" ||
      normalized === "yes" ||
      normalized === "autoplay" ||
      normalized === "muted"
    );
  }

  /**
   * Collect every element matching `selector` at or beneath `root`.
   *
   * Unlike querySelectorAll alone, this also includes `root` itself when it
   * matches, which matters when the observer hands us a freshly-added node.
   *
   * @param {Element|Document} root     Subtree root to search (may be falsy).
   * @param {string}           selector CSS selector to match.
   * @return {Element[]} Array of matching elements (empty when root is falsy).
   */
  function collectNodes(root, selector) {
    // Accumulator for matches.
    const nodes = [];
    // Nothing to search if root is missing.
    if (!root) {
      return nodes;
    }

    // Include root itself when it matches the selector.
    if (typeof root.matches === "function" && root.matches(selector)) {
      nodes.push(root);
    }

    // Add every descendant match (guarded because Document/Element both expose it).
    if (typeof root.querySelectorAll === "function") {
      const found = root.querySelectorAll(selector);
      // Copy NodeList entries into the plain array.
      for (const node of found) {
        nodes.push(node);
      }
    }

    return nodes;
  }

  /**
   * Build the optional watermark/logo overlay settings for a VOD player from
   * its data-player-logo-* attributes.
   *
   * @param {Element} videoElement The <video> element carrying the attributes.
   * @return {Object|null} Logo settings object, or null when no logo image is set.
   */
  function createVodLogoSettings(videoElement) {
    // No logo overlay unless an image URL is supplied.
    const image = getAttr(videoElement, "data-player-logo-image");
    if (!image) {
      return null;
    }

    // Parse the numeric attributes; NaN when absent/invalid (handled below).
    const opacityRaw = parseFloat(getAttr(videoElement, "data-player-logo-opacity"));
    const widthRaw = parseInt(getAttr(videoElement, "data-player-logo-width"), 10);
    const paddingRaw = parseInt(getAttr(videoElement, "data-player-logo-padding"), 10);

    // Assemble the settings, falling back to sensible defaults for each field.
    return {
      image,
      // Overlay corner; default top-right.
      position: getAttr(videoElement, "data-player-logo-position") || "top-right",
      // Opacity 0-1; default fully opaque when not a finite number.
      opacity: Number.isFinite(opacityRaw) ? opacityRaw : 1,
      // Width in px; default 100.
      width: Number.isFinite(widthRaw) ? widthRaw : 100,
      // Height may be "auto" or a CSS value; default "auto".
      height: getAttr(videoElement, "data-player-logo-height") || "auto",
      // Padding from the edge in px; default 10.
      padding: Number.isFinite(paddingRaw) ? paddingRaw : 10,
    };
  }

  /**
   * Initialize a standard live player wrapper by reading its data-* attributes
   * and delegating to the global wpstream_player_initialize().
   *
   * @param {Element} wrapper The .wpstream_live_player_wrapper element.
   * @return {boolean} True when handled (already done, done now, or nothing to
   *                   do); false when the player library is not yet available
   *                   and a retry is warranted.
   */
  function initializeLivePlayer(wrapper) {
    // Skip elements already bootstrapped.
    if (wrapper.dataset.wpstreamBootstrapped === "1") {
      return true;
    }

    // The player library may not have loaded yet; signal caller to retry.
    if (typeof window.wpstream_player_initialize !== "function") {
      return false;
    }

    // Translate the wrapper's data-* attributes into the player settings object.
    const settings = {
      // DOM id of the element used for the title overlay.
      titleOverlayElementId: getAttr(wrapper, "data-title-overlay-element-id"),
      // DOM id of the target <video>; falls back to the legacy data-now attribute.
      videoElementId:
        getAttr(wrapper, "data-video-element-id") || getAttr(wrapper, "data-now"),
      // Optional trailer stream URL.
      trailerUrl: getAttr(wrapper, "data-trailer-url"),
      // Main live content (HLS) URL.
      contentUrl: getAttr(wrapper, "data-content-url"),
      // Endpoint for reporting viewer/playback stats.
      statsUri: getAttr(wrapper, "data-stats-uri"),
      // Live chat endpoint URL.
      chatUrl: getAttr(wrapper, "data-chat-url"),
      // Whether playback should start automatically.
      autoplay: parseBool(getAttr(wrapper, "data-autoplay")),
      // Whether playback should start muted.
      muted: parseBool(getAttr(wrapper, "data-muted")),
      // DOM id of the "play trailer" control.
      playTrailerButtonElementId: getAttr(
        wrapper,
        "data-play-trailer-button-element-id"
      ),
      // DOM id of the "mute trailer" control.
      muteTrailerButtonElementId: getAttr(
        wrapper,
        "data-mute-trailer-button-element-id"
      ),
      // DOM id of the "unmute trailer" control.
      unmuteTrailerButtonElementId: getAttr(
        wrapper,
        "data-unmute-trailer-button-element-id"
      ),
    };

    // Without a target video id there is nothing to attach a player to; mark
    // as handled so it is not retried.
    if (!settings.videoElementId) {
      wrapper.dataset.wpstreamBootstrapped = "1";
      return true;
    }

    // Hand the settings to the live player library and mark the wrapper done.
    window.wpstream_player_initialize(settings);
    wrapper.dataset.wpstreamBootstrapped = "1";
    return true;
  }

  /**
   * Initialize an on-demand (VOD) <video> element by reading its data-*
   * attributes and delegating to the global wpstream_player_initialize_vod().
   *
   * @param {Element} videoElement The <video> element to bootstrap.
   * @return {boolean} True when handled; false when the VOD player library is
   *                   not yet loaded and a retry is warranted.
   */
  function initializeVodPlayer(videoElement) {
    // Skip elements already bootstrapped.
    if (videoElement.dataset.wpstreamBootstrapped === "1") {
      return true;
    }

    // VOD player library not loaded yet; signal caller to retry.
    if (typeof window.wpstream_player_initialize_vod !== "function") {
      return false;
    }

    // Translate the element's data-* attributes into the VOD settings object.
    const settings = {
      // DOM id of the element used for the title overlay.
      titleOverlayElementId: getAttr(videoElement, "data-title-overlay-element-id"),
      // Target video id; falls back to the element's own id.
      videoElementId:
        getAttr(videoElement, "data-video-element-id") || videoElement.id,
      // Optional trailer stream URL.
      trailerUrl: getAttr(videoElement, "data-trailer-url"),
      // Main on-demand video URL.
      videoUrl: getAttr(videoElement, "data-video-url"),
      // Whether playback should start automatically.
      autoplay: parseBool(getAttr(videoElement, "data-autoplay")),
      // Whether playback should start muted.
      muted: parseBool(getAttr(videoElement, "data-muted")),
      // Optional captions/subtitles track URL.
      captionsUrl: getAttr(videoElement, "data-captions-url"),
      // DOM id of the "play trailer" control.
      playTrailerButtonElementId: getAttr(
        videoElement,
        "data-play-trailer-button-element-id"
      ),
      // DOM id of the "mute trailer" control.
      muteTrailerButtonElementId: getAttr(
        videoElement,
        "data-mute-trailer-button-element-id"
      ),
      // DOM id of the "unmute trailer" control.
      unmuteTrailerButtonElementId: getAttr(
        videoElement,
        "data-unmute-trailer-button-element-id"
      ),
      // DOM id of the "play video" control.
      playVideoButtonElementId: getAttr(
        videoElement,
        "data-play-video-button-element-id"
      ),
    };

    // Attach optional watermark/logo overlay settings when a logo image is set.
    const logoSettings = createVodLogoSettings(videoElement);
    if (logoSettings) {
      settings.playerLogoSettings = logoSettings;
    }

    // No target video id: mark handled so it is not retried.
    if (!settings.videoElementId) {
      videoElement.dataset.wpstreamBootstrapped = "1";
      return true;
    }

    // Hand the settings to the VOD player library and mark the element done.
    window.wpstream_player_initialize_vod(settings);
    videoElement.dataset.wpstreamBootstrapped = "1";
    return true;
  }

  /**
   * Initialize a low-latency (WebRTC/WHEP) live player wrapper by delegating to
   * the global initPlayer(), then optionally wiring up chat/stats websockets.
   *
   * @param {Element} wrapper The .wpstream_live_player_wrapper element.
   * @return {boolean} True when handled; false when initPlayer is not yet
   *                   available and a retry is warranted.
   */
  function initializeLowLatencyPlayer(wrapper) {
    // Skip elements already bootstrapped.
    if (wrapper.dataset.wpstreamBootstrapped === "1") {
      return true;
    }

    // Low-latency player library not loaded yet; signal caller to retry.
    if (typeof window.initPlayer !== "function") {
      return false;
    }

    // Required inputs: target video id and the low-latency content URL.
    const videoElementId = getAttr(wrapper, "data-video-element-id");
    const lowLatencyUri = getAttr(wrapper, "data-content-url");
    // Missing either input: mark handled so it is not retried.
    if (!videoElementId || !lowLatencyUri) {
      wrapper.dataset.wpstreamBootstrapped = "1";
      return true;
    }

    // initPlayer expects the literal strings "muted"/"autoplay" (or empty).
    const muted = parseBool(getAttr(wrapper, "data-muted")) ? "muted" : "";
    const autoplay = parseBool(getAttr(wrapper, "data-autoplay"))
      ? "autoplay"
      : "";

    // Start the low-latency player.
    window.initPlayer(videoElementId, lowLatencyUri, muted, autoplay);

    // When available, subscribe to the websocket feed for chat and live stats.
    if (typeof window.wpstream_read_websocket_info === "function") {
      // Event id sourced from data-event-id, falling back to the product id.
      const eventId = getAttr(wrapper, "data-event-id") || getAttr(wrapper, "data-product-id");
      // Chat and stats endpoints.
      const chatUri = getAttr(wrapper, "data-chat-url");
      const statsUri = getAttr(wrapper, "data-stats-uri");
      // Open the websocket info channel bound to this wrapper's DOM id.
      window.wpstream_read_websocket_info(eventId, null, wrapper.id, chatUri, statsUri);
    }

    // Mark the wrapper as done.
    wrapper.dataset.wpstreamBootstrapped = "1";
    return true;
  }

  /**
   * Find every live, low-latency and VOD player under `root` and initialize each.
   * If any initialization failed because its library was not yet loaded, schedule
   * another whole-document pass (up to MAX_RETRIES).
   *
   * @param {Element|Document} root Subtree to scan for players.
   */
  function bootstrapPlayers(root) {
    // Set when any player could not be initialized yet.
    let needsRetry = false;

    // Initialize standard live players.
    const livePlayers = collectNodes(root, LIVE_SELECTOR);
    for (const player of livePlayers) {
      // A false return means the library is not ready; retry later.
      if (!initializeLivePlayer(player)) {
        needsRetry = true;
      }
    }

    // Initialize low-latency live players.
    const lowLatencyPlayers = collectNodes(root, LIVE_LOW_LATENCY_SELECTOR);
    for (const player of lowLatencyPlayers) {
      if (!initializeLowLatencyPlayer(player)) {
        needsRetry = true;
      }
    }

    // Initialize VOD players.
    const vodPlayers = collectNodes(root, VOD_SELECTOR);
    for (const player of vodPlayers) {
      if (!initializeVodPlayer(player)) {
        needsRetry = true;
      }
    }

    // Retry the whole document shortly, bounded by MAX_RETRIES, until libraries load.
    if (needsRetry && retryCount < MAX_RETRIES) {
      retryCount += 1;
      window.setTimeout(function () {
        bootstrapPlayers(document);
      }, RETRY_DELAY_MS);
    }
  }

  /**
   * Watch the document for player elements added after initial load (AJAX,
   * blocks, etc.) and bootstrap them as they appear. Wires up at most one
   * MutationObserver for the page lifetime.
   */
  function startObserver() {
    // Do nothing if already observing, the body is missing, or the API is absent.
    if (observerStarted || !document.body || !window.MutationObserver) {
      return;
    }

    // Latch so the observer is only created once.
    observerStarted = true;

    // React to DOM mutations by scanning newly-added subtrees for players.
    const observer = new MutationObserver(function (mutations) {
      for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
          // Only element nodes (nodeType 1) can contain players.
          if (node.nodeType !== 1) {
            continue;
          }

          // Check the added subtree for any of the three player kinds.
          const liveNodes = collectNodes(node, LIVE_SELECTOR);
          const liveLowLatencyNodes = collectNodes(node, LIVE_LOW_LATENCY_SELECTOR);
          const vodNodes = collectNodes(node, VOD_SELECTOR);
          // If any players are present, bootstrap this subtree and stop this pass.
          if (
            liveNodes.length > 0 ||
            liveLowLatencyNodes.length > 0 ||
            vodNodes.length > 0
          ) {
            bootstrapPlayers(node);
            return;
          }
        }
      }
    });

    // Observe the whole body subtree for added/removed children.
    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }

  /**
   * Entry point once the DOM is ready: bootstrap existing players and start
   * watching for future ones.
   */
  function onReady() {
    // Initialize everything already in the document.
    bootstrapPlayers(document);
    // Begin watching for dynamically-added players.
    startObserver();
  }

  // Defer to DOMContentLoaded while the document is still parsing; otherwise run now.
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", onReady, { once: true });
  } else {
    onReady();
  }
})();
