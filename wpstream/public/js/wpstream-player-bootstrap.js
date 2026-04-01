(function () {
  "use strict";

  const LIVE_SELECTOR =
    '.wpstream_live_player_wrapper[data-wpstream-bootstrap="live"]:not(.wpstream_low_latency)';
  const LIVE_LOW_LATENCY_SELECTOR =
    '.wpstream_live_player_wrapper[data-wpstream-bootstrap="live-low-latency"]';
  const VOD_SELECTOR = 'video[data-wpstream-bootstrap="vod"]';
  const MAX_RETRIES = 40;
  const RETRY_DELAY_MS = 50;

  let retryCount = 0;
  let observerStarted = false;
  function getAttr(element, name) {
    return element.getAttribute(name) || "";
  }

  function parseBool(value) {
    const normalized = String(value || "")
      .trim()
      .toLowerCase();

    return (
      normalized === "1" ||
      normalized === "true" ||
      normalized === "yes" ||
      normalized === "autoplay" ||
      normalized === "muted"
    );
  }

  function collectNodes(root, selector) {
    const nodes = [];
    if (!root) {
      return nodes;
    }

    if (typeof root.matches === "function" && root.matches(selector)) {
      nodes.push(root);
    }

    if (typeof root.querySelectorAll === "function") {
      const found = root.querySelectorAll(selector);
      for (const node of found) {
        nodes.push(node);
      }
    }

    return nodes;
  }

  function createVodLogoSettings(videoElement) {
    const image = getAttr(videoElement, "data-player-logo-image");
    if (!image) {
      return null;
    }

    const opacityRaw = parseFloat(getAttr(videoElement, "data-player-logo-opacity"));
    const widthRaw = parseInt(getAttr(videoElement, "data-player-logo-width"), 10);
    const paddingRaw = parseInt(getAttr(videoElement, "data-player-logo-padding"), 10);

    return {
      image,
      position: getAttr(videoElement, "data-player-logo-position") || "top-right",
      opacity: Number.isFinite(opacityRaw) ? opacityRaw : 1,
      width: Number.isFinite(widthRaw) ? widthRaw : 100,
      height: getAttr(videoElement, "data-player-logo-height") || "auto",
      padding: Number.isFinite(paddingRaw) ? paddingRaw : 10,
    };
  }

  function initializeLivePlayer(wrapper) {
    if (wrapper.dataset.wpstreamBootstrapped === "1") {
      return true;
    }

    if (typeof window.wpstream_player_initialize !== "function") {
      return false;
    }

    const settings = {
      titleOverlayElementId: getAttr(wrapper, "data-title-overlay-element-id"),
      videoElementId:
        getAttr(wrapper, "data-video-element-id") || getAttr(wrapper, "data-now"),
      trailerUrl: getAttr(wrapper, "data-trailer-url"),
      contentUrl: getAttr(wrapper, "data-content-url"),
      statsUri: getAttr(wrapper, "data-stats-uri"),
      chatUrl: getAttr(wrapper, "data-chat-url"),
      autoplay: parseBool(getAttr(wrapper, "data-autoplay")),
      muted: parseBool(getAttr(wrapper, "data-muted")),
      playTrailerButtonElementId: getAttr(
        wrapper,
        "data-play-trailer-button-element-id"
      ),
      muteTrailerButtonElementId: getAttr(
        wrapper,
        "data-mute-trailer-button-element-id"
      ),
      unmuteTrailerButtonElementId: getAttr(
        wrapper,
        "data-unmute-trailer-button-element-id"
      ),
    };

    if (!settings.videoElementId) {
      wrapper.dataset.wpstreamBootstrapped = "1";
      return true;
    }

    window.wpstream_player_initialize(settings);
    wrapper.dataset.wpstreamBootstrapped = "1";
    return true;
  }

  function initializeVodPlayer(videoElement) {
    if (videoElement.dataset.wpstreamBootstrapped === "1") {
      return true;
    }

    if (typeof window.wpstream_player_initialize_vod !== "function") {
      return false;
    }

    const settings = {
      titleOverlayElementId: getAttr(videoElement, "data-title-overlay-element-id"),
      videoElementId:
        getAttr(videoElement, "data-video-element-id") || videoElement.id,
      trailerUrl: getAttr(videoElement, "data-trailer-url"),
      videoUrl: getAttr(videoElement, "data-video-url"),
      autoplay: parseBool(getAttr(videoElement, "data-autoplay")),
      muted: parseBool(getAttr(videoElement, "data-muted")),
      captionsUrl: getAttr(videoElement, "data-captions-url"),
      playTrailerButtonElementId: getAttr(
        videoElement,
        "data-play-trailer-button-element-id"
      ),
      muteTrailerButtonElementId: getAttr(
        videoElement,
        "data-mute-trailer-button-element-id"
      ),
      unmuteTrailerButtonElementId: getAttr(
        videoElement,
        "data-unmute-trailer-button-element-id"
      ),
      playVideoButtonElementId: getAttr(
        videoElement,
        "data-play-video-button-element-id"
      ),
    };

    const logoSettings = createVodLogoSettings(videoElement);
    if (logoSettings) {
      settings.playerLogoSettings = logoSettings;
    }

    if (!settings.videoElementId) {
      videoElement.dataset.wpstreamBootstrapped = "1";
      return true;
    }

    window.wpstream_player_initialize_vod(settings);
    videoElement.dataset.wpstreamBootstrapped = "1";
    return true;
  }

  function initializeLowLatencyPlayer(wrapper) {
    if (wrapper.dataset.wpstreamBootstrapped === "1") {
      return true;
    }

    if (typeof window.initPlayer !== "function") {
      return false;
    }

    const videoElementId = getAttr(wrapper, "data-video-element-id");
    const lowLatencyUri = getAttr(wrapper, "data-content-url");
    if (!videoElementId || !lowLatencyUri) {
      wrapper.dataset.wpstreamBootstrapped = "1";
      return true;
    }

    const muted = parseBool(getAttr(wrapper, "data-muted")) ? "muted" : "";
    const autoplay = parseBool(getAttr(wrapper, "data-autoplay"))
      ? "autoplay"
      : "";

    window.initPlayer(videoElementId, lowLatencyUri, muted, autoplay);

    if (typeof window.wpstream_read_websocket_info === "function") {
      const eventId = getAttr(wrapper, "data-event-id") || getAttr(wrapper, "data-product-id");
      const chatUri = getAttr(wrapper, "data-chat-url");
      const statsUri = getAttr(wrapper, "data-stats-uri");
      window.wpstream_read_websocket_info(eventId, null, wrapper.id, chatUri, statsUri);
    }

    wrapper.dataset.wpstreamBootstrapped = "1";
    return true;
  }

  function bootstrapPlayers(root) {
    let needsRetry = false;

    const livePlayers = collectNodes(root, LIVE_SELECTOR);
    for (const player of livePlayers) {
      if (!initializeLivePlayer(player)) {
        needsRetry = true;
      }
    }

    const lowLatencyPlayers = collectNodes(root, LIVE_LOW_LATENCY_SELECTOR);
    for (const player of lowLatencyPlayers) {
      if (!initializeLowLatencyPlayer(player)) {
        needsRetry = true;
      }
    }

    const vodPlayers = collectNodes(root, VOD_SELECTOR);
    for (const player of vodPlayers) {
      if (!initializeVodPlayer(player)) {
        needsRetry = true;
      }
    }

    if (needsRetry && retryCount < MAX_RETRIES) {
      retryCount += 1;
      window.setTimeout(function () {
        bootstrapPlayers(document);
      }, RETRY_DELAY_MS);
    }
  }

  function startObserver() {
    if (observerStarted || !document.body || !window.MutationObserver) {
      return;
    }

    observerStarted = true;

    const observer = new MutationObserver(function (mutations) {
      for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
          if (node.nodeType !== 1) {
            continue;
          }

          const liveNodes = collectNodes(node, LIVE_SELECTOR);
          const liveLowLatencyNodes = collectNodes(node, LIVE_LOW_LATENCY_SELECTOR);
          const vodNodes = collectNodes(node, VOD_SELECTOR);
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

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }

  function onReady() {
    bootstrapPlayers(document);
    startObserver();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", onReady, { once: true });
  } else {
    onReady();
  }
})();
