/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 * player,'.$live_event_uri_final.','.$live_conect_views.'
 */

/*
 * wpstream-player.js — front-end player runtime for the WpStream plugin.
 *
 * This ES6 module drives the Video.js-based player used on the public site. It
 * covers the full live playback lifecycle (stopped / not-started / started /
 * on-air / paused / ended), dynamic status polling over AJAX, a WebSocket
 * "LiveConnect" channel for real-time viewer count / on-air / status pushes,
 * trailer-vs-content switching, adaptive-bitrate (ABR) quality selection,
 * QoE (Quality of Experience) telemetry, and a watchdog that recovers a stalled
 * live stream. A second, standalone entry point (`wpstream_player_initialize_vod`)
 * handles Video-on-Demand playback including YouTube sources.
 *
 * Key classes:
 *   WpstreamPlayer      — top-level controller: state machine + wiring.
 *   WpstreamPlayback    — owns the Video.js instance and playback commands.
 *   Qoe                 — collects/report startup time and rebuffering metrics.
 *   WpstreamChat        — thin bridge to the (optional) global chat functions.
 *   WpstreamLiveMessage — the "not live" / status overlay message.
 *   LiveCounter         — the live viewer-count badge.
 *   LiveConnect         — the WebSocket real-time control channel.
 *
 * Relies on the `wpstream_player_vars` object localized from PHP for URLs,
 * nonces, theme, ABR flags, logo settings, and localized status messages.
 */

// Normalise the WebSocket constructor across browsers (older Firefox exposed MozWebSocket).
window.WebSocket = window.WebSocket || window.MozWebSocket;
// Warn (but do not hard-fail) when the browser has no WebSocket support at all.
if (!window.WebSocket) {
  console.log("Sorry, but your browser does not support WebSockets");
}
/**
 * Public entry point: build a live player controller from its settings object.
 *
 * @param {Object} settings Localized configuration (element ids, URLs, flags).
 */
function wpstream_player_initialize(settings) {
  // Instantiating the controller wires up everything; the reference is intentionally local.
  const player = new WpstreamPlayer(settings);
}

/**
 * Top-level live player controller.
 *
 * Owns the state machine, the polling "ruler", and all the sub-components
 * (playback, chat, live message, counter, live-connect). Reacts to state
 * changes and real-time messages by driving the underlying Video.js player.
 */
class WpstreamPlayer {
  // Below: original author's field notes (kept as documentation of shape/values).
  // id;
  // trailerUrl;
  // contentUrl;
  // statsUri;
  // autoplay;
  // ruler = 0; //0 - basic; 1 - ajax; 2 - ws  (which polling mechanism is active)
  // state = -1;
  //-1 - unknown
  // 0 - stopped
  // 1 - notstarted
  // 2 - started
  // 4 - init
  // 5 - paused
  // 6 - startup
  // 7 - onair
  // 9 - ended
  // 10 - finished

  // liveConnect;
  // wrapper;
  // counter;
  // chat;

  /**
   * Build the controller: cache settings, resolve DOM handles, construct
   * sub-components, and start AJAX status polling (ruler 1).
   *
   * @param {Object} settings Localized player configuration.
   */
  constructor(settings) {
    console.log("[]WpstreamPlayer: ", settings);
    // Keep the raw settings around for later reference (e.g. theater-mode buttons).
    this.settings = settings;
    // DOM id of the <video> element this controller drives.
    this.id = settings.videoElementId;
    // Optional trailer URL played before the live content is available.
    this.trailerUrl = settings.trailerUrl;
    // The live content (HLS) URL.
    this.contentUrl = settings.contentUrl;
    // Endpoint used for stats reporting.
    this.statsUri = settings.statsUri;
    // Whether playback should auto-start.
    this.autoplay = settings.autoplay;
    // jQuery handles for the trailer play / mute / unmute buttons.
    this.playTrailerButton = jQuery(`#${settings.playTrailerButtonElementId}`);
    this.muteTrailerButton = jQuery(`#${settings.muteTrailerButtonElementId}`);
    this.unmuteTrailerButton = jQuery(
      `#${settings.unmuteTrailerButtonElementId}`
    );
    // Optional overlay element that shows the title on hover / user activity.
    this.titleOverlay = document.getElementById(settings.titleOverlayElementId);
    // WebSocket real-time control channel (viewer count, on-air, status).
    this.liveConnect = new LiveConnect(this);
    // Outer wrapper element for this player instance.
    this.wrapper = jQuery("#wpstream_live_player_wrapper" + this.id);
    console.log("wrapper: ", this.wrapper);
    // Product/channel id read off the wrapper's data attribute; used for status AJAX.
    this.channelId = this.wrapper.attr("data-product-id");
    console.log("channelId: ", this.channelId);
    // Playback engine (owns the Video.js instance).
    this.playback = new WpstreamPlayback(this, this.id, this.autoplay);
    // Live viewer-count badge.
    this.counter = new LiveCounter(this.wrapper, this.id);
    // Overlay message shown when the stream is not live.
    this.liveMessage = new WpstreamLiveMessage(this.wrapper, this.id);
    // Chat bridge (delegates to global chat functions if present).
    this.chat = new WpstreamChat();
    // Start in ruler 1 = AJAX status polling.
    this.setRuler(1);

    
    // Grab the underlying Video.js player instance.
    let player = this.playback.player;
    console.log("player: ", player);
    // Root DOM element of the player.
    let playerElement = player.el();
    // Move the title overlay inside the player element so it overlays the video.
    if (this.titleOverlay){
      playerElement.appendChild(this.titleOverlay);
    }
    // console.log("playerElement: ", playerElement);
    // Parent of the player element; used to detect the "simple player" shortcode.
    let playerParentElement = playerElement.parentNode;

    // The simple-player shortcode never plays a trailer, so drop any trailer URL.
    if (
      playerParentElement.classList.contains(
        "wpstream_simple_player_shortcode_wrapper"
      )
    ) {
      settings.trailerUrl = null;
    }
    

    // Wire up trailer controls only when a trailer URL survived the check above.
    if (settings.trailerUrl) {
      // Capture `this` for use inside the jQuery event callbacks.
      const owner = this;
      // Play-trailer button: hide itself and start the trailer (user-initiated).
      this.playTrailerButton.on("click", function () {
        console.log("playTrailer()");
        owner.playTrailerButton.hide();
        owner.playback.playTrailer(owner.trailerUrl, true);
      });
      // Mute button mutes the underlying player.
      this.muteTrailerButton.on("click", function () {
        owner.playback.player.muted(true);
      });
      // Unmute button unmutes the underlying player.
      this.unmuteTrailerButton.on("click", function () {
        owner.playback.player.muted(false);
      });
    }
    // The play-trailer button starts hidden; it is shown again by state logic.
    this.playTrailerButton.hide();
  }

  /**
   * Switch the polling mechanism ("ruler").
   *
   * Ruler 1 = periodic AJAX status polling (fallback); Ruler 2 = live WebSocket
   * is active so the AJAX poll is suspended.
   *
   * @param {number} ruler 1 for AJAX polling, 2 for WebSocket-driven.
   */
  setRuler(ruler) {
    console.log("setRuler: " + ruler);
    // Remember the previous ruler so we only kick off a fresh fetch on transition.
    let oldRuler = this.ruler;
    console.log("oldRuler: ", oldRuler);
    // Record the new ruler.
    this.ruler = ruler;
    switch (ruler) {
      case 1:
        // On entering AJAX mode from a different ruler, fetch status immediately.
        if (oldRuler != 1) {
          this.getDynamicSettings();
        }
        // Cancel any pending poll timer, then schedule the next poll in 30s.
        clearTimeout(this.retrieveDynamicSettingsTimeout);
        let self = this;
        this.retrieveDynamicSettingsTimeout = setTimeout(
          () => self.getDynamicSettings(),
          30 * 1000
        );
        break;
      case 2:
        // WebSocket has taken over: stop the AJAX poll timer.
        clearTimeout(this.retrieveDynamicSettingsTimeout);
        break;
    }
  }

  /**
   * Poll the server (admin-ajax) for the channel's current live status and
   * react: set state, open the LiveConnect WebSocket, load the content source,
   * and connect/disconnect chat as appropriate.
   */
  getDynamicSettings() {
    console.log("getDynamicSettings()");
    // Build the admin-ajax endpoint URL from the localized admin URL.
    let ajaxurl = wpstream_player_vars.admin_url + "admin-ajax.php";
	// const nonce = jQuery('.wpstream_live_player_wrapper').attr('data-nonce');
	// Nonce that authorizes the check-status AJAX action.
	const nonce = wpstream_player_vars.player_check_status_nonce;
    // Capture `this` for use inside the AJAX callbacks.
    let owner = this;
    jQuery.ajax({
      type: "POST",
      url: ajaxurl,
      dataType: "json",
      data: {
        // Server action + channel id + nonce.
        action: "wpstream_player_check_status",
        channel_id: this.channelId,
        nonce: nonce,
      },
      success: function (data) {
        console.log("dynamicSettings: ", data);
        // data === 0 → channel is stopped; clear any loading spinner.
        if (data == 0) {
          owner.setState("stopped");
		  removeSpinner(3);
        } else if (data.started == "no") {
          // Channel exists but broadcast has not started; drop chat.
          owner.setState("notstarted");
          owner.chat.disconnect();
        } else if (data.started == "yes") {
          // Broadcast is live: open the WebSocket, load content, connect chat.
          let liveConnectUri = data.live_conect_views;
          owner.liveConnect.setup(liveConnectUri);
          let contentUrl = data.event_uri;
          owner.setState("started");
          owner.setContentSrc(contentUrl);
          owner.chat.connect(data.chat_url);
        }
      },
      error: function (error) {
        // Network/parse failure → surface an error state.
        console.log("dynamicSettingsError: ", error);
		owner.setState("error");
      },
    });
    // If still in (or below) AJAX mode, re-arm the polling timer.
    if (this.ruler <= 1) {
      this.setRuler(1);
    }
  }

  /**
   * Forward a new content source URL to the playback engine.
   *
   * @param {string} uri HLS content URL.
   */
  setContentSrc(uri) {
    this.playback.setContentSrc(uri);
  }

  /**
   * Central state machine: translate a status string into overlay messages,
   * trailer playback, and content play/pause behaviour.
   *
   * @param {string} state One of stopped/notstarted/starting/started/init/
   *                       paused/ended/startup/onair/error.
   */
  setState(state) {
    console.log("setState: ", state);
    // Remember the previous state (used by the "startup" transition below).
    const oldState = this.state;
    console.log("oldState: ", oldState);
    // Store the new state.
    this.state = state;

    // Whenever a trailer exists and we're not actively showing live content,
    // fall back to playing the trailer.
    if (
      this.trailerUrl &&
      state != "onair" &&
      state != "started" &&
      state != "startup"
    ) {
      this.playback.playTrailer(this.trailerUrl);
    }

	// Ensure the live-message overlay container is visible before deciding content.
	this.liveMessage.show();
    switch (state) {
      case "stopped":
      case "notstarted":
      case "starting":
        // Not broadcasting yet: show the "stopped" message, pause, clear spinner.
        this.liveMessage.showMessage('stopped');
        this.playback.pauseContent();
		removeSpinner(1);
        break;
      case "started":
        // Content is loading/ready: hide the overlay message.
        this.liveMessage.hide();
        break;
      case "init":
      case "paused":
        // Initialising or paused: show the matching message and pause playback.
        this.liveMessage.showMessage(state);
        this.playback.pauseContent();
        break;
      case "ended":
        // Broadcast ended: show message and hard-stop playback (stop=true).
        this.liveMessage.showMessage(state);
        this.playback.pauseContent(true);
        break;
      case "startup":
        // Startup: show the message; only pause if we were not already on-air.
        this.liveMessage.showMessage(state);
        if (oldState != "onair") {
          this.playback.pauseContent();
        }
        break;
      case "onair":
        // Live: hide the message and start playing content.
        this.liveMessage.hide();
        this.playback.playContent();
        break;
	  case 'error':
		  // Error: clear spinner and show the error overlay.
		  removeSpinner(4);
	    this.liveMessage.showMessage('error');
    }
  }

  /**
   * Show or hide the trailer mute/unmute buttons based on whether a trailer is
   * currently playing and its muted state.
   */
  showHideMuteTrailerButtons() {
    console.log("showHideMuteTrailerButtons()");
    console.log("playingTrailer: ", this.playback.playingTrailer);
    console.log("muted: ", this.playback.player.muted());
    // Only relevant while a trailer is playing.
    if (this.playback.playingTrailer) {
      if (this.playback.player.muted()) {
        // Muted → offer the unmute button.
        this.muteTrailerButton.hide();
        this.unmuteTrailerButton.show();
      } else {
        // Unmuted → offer the mute button.
        this.muteTrailerButton.show();
        this.unmuteTrailerButton.hide();
      }
    } else {
      // No trailer playing → hide both.
      this.muteTrailerButton.hide();
      this.unmuteTrailerButton.hide();
    }
  }

  /**
   * Callback from LiveConnect when the WebSocket opens/closes. Switches the
   * ruler (2 when active, 1 when not) and hides the counter on disconnect.
   *
   * @param {boolean} isActive Whether the WebSocket is currently connected.
   */
  onLiveConnectActive(isActive) {
    console.log("onLiveConnectActive: ", isActive);
    // Active WebSocket → ruler 2 (suspend AJAX); otherwise fall back to ruler 1.
    this.setRuler(isActive ? 2 : 1);
    // When the real-time channel drops, the viewer count is no longer reliable.
    if (!isActive) {
      this.counter.hide();
    }
  }

  /**
   * Update the live viewer-count badge.
   *
   * @param {number} count Current viewer count.
   */
  updateViewerCount(count) {
    console.log("updateViewerCount: ", count);
    this.counter.setCount(count);
  }

  /**
   * Show a "waiting in queue" message when max viewers is reached.
   *
   * @param {*} place Position/label describing who must leave first.
   */
  updatePending(place){
    console.log("updatePending: ", place);
    this.counter.showPending(place);
  }

  /**
   * Fade the title overlay in or out.
   *
   * @param {boolean} [show=true] True to show (opacity 1), false to hide.
   */
  showTitleOverlay(show = true) {
    if (this.titleOverlay){
      this.titleOverlay.style.opacity = show ? '1' : '0';
    }
  }
}

/**
 * Playback engine: constructs and owns the Video.js instance and exposes the
 * high-level playback commands (play trailer, play/pause content, set source).
 * Also runs the stall-recovery watchdog and feeds the QoE collector.
 */
class WpstreamPlayback {
  // Original author's field notes on instance shape:
  // player;
  // timeQueue = [];
  // master;
  // paused = false;
  // played = false;

  /**
   * @param {WpstreamPlayer} master   Owning controller.
   * @param {string}         id       Video element id suffix.
   * @param {boolean}        autoplay Whether to autoplay.
   */
  constructor(master, id, autoplay) {
    // Rolling window of recent currentTime samples used by the watchdog.
    this.timeQueue = [];
    // Whether playback is intentionally paused.
    this.paused = false;
    // Whether the player has ever started playing (gates autoplay retries).
    this.played = false;
    // Current live content source URL.
    this.contentSrc = null;
    // Trailer lifecycle: notstarted → attempted → playing → ended.
    this.trailerState = "notstarted";
    // Whether the trailer (vs. content) is currently the active source.
    this.playingTrailer = false;
    // Back-reference to the owning controller.
    this.master = master;
    // Build the Video.js player and register all event handlers.
    this.setupBasePlayer(id, autoplay);
    // Start the periodic stall-recovery watchdog.
    this.runWatchdog();
    // QoE collector; reports via the LiveConnect WebSocket.
    this.qoe = new Qoe(master.liveConnect.sendQoeData, master.liveConnect);
  }

  /**
   * Create the Video.js player with VHS/HLS options, optional quality selector,
   * theme, logo, and theater-mode buttons, then bind all playback + QoE events.
   *
   * @param {string}  id       Video element id suffix.
   * @param {boolean} autoplay Whether to autoplay.
   */
  setupBasePlayer(id, autoplay) {
    console.log("setupBasePlayer: ", id, autoplay);
    // Resolve the live content URL and detect low-latency HLS.
    let contentUrl = this.master.contentUrl;
    console.log("contentUrl: ", contentUrl);
    let llhls = isLlHls(contentUrl);
    console.log("llhls: ", llhls);
    // Instantiate Video.js with HTTP Streaming (VHS) tuning options.
    this.player = videojs("wpstream-video" + id, {
      html5: {
        vhs: {
          // Reuse the last measured bandwidth to pick an initial rendition.
          useBandwidthFromLocalStorage: true,
          // Do not cap quality by the rendered player size.
          limitRenditionByPlayerDimensions: false,
          // Factor device pixel ratio into rendition selection.
          useDevicePixelRatio: true,
          // Use the JS HLS engine everywhere except Safari (which plays HLS natively).
          overrideNative: !videojs.browser.IS_SAFARI,
          // Cache DRM/encryption keys to avoid re-fetching per segment.
          cacheEncryptionKeys: true,
          // Enable low-latency HLS when the source is an LL-HLS playlist.
          llhls,
        },
      },
      // Suppress Video.js's built-in error dialog (we handle errors ourselves).
      errorDisplay: false,
      autoplay: autoplay,
      preload: "auto",
      // muted    : true
    });

    // Enable HLS quality selector (Video.js 8 compatible)
    try {
      // Only install the ABR quality menu when the helper exists and ABR is enabled.
      if (typeof window.wpstreamInstallQualitySelector === 'function' && wpstream_player_vars.is_abr_enabled == 1) {
        window.wpstreamInstallQualitySelector(this.player);
      }
    } catch (e) {
      // optional
    }

	  // Apply the configured skin theme for non-Streamify users.
	  if ( !wpstream_player_vars.wpstream_is_streamify_user ) {
		  this.applyTheme(wpstream_player_vars.wpstream_player_theme);
	  }

      // Overlay the configured watermark/logo when the logo plugin is present.
      if ( typeof this.player.logo === 'function' && wpstream_player_vars.playerLogoSettings ) {
          this.player.logo({
              image: wpstream_player_vars.playerLogoSettings.imageUrl,
              position: wpstream_player_vars.playerLogoSettings.position,
              width: 100,
              height: 'auto',
              // Opacity is stored 0-100 in settings; convert to a 0-1 fraction.
              opacity: parseFloat(wpstream_player_vars.playerLogoSettings.opacity)/100,
              padding: 10,
          });
      }

    // Optionally add custom theater-mode enter/leave control-bar buttons.
    if (this.master.settings.theaterModeButtons) {
      // Video.js Button base class + captured `this` for the button callbacks.
      const Button = videojs.getComponent("Button");
      const owner = this;

      // Control-bar button that switches INTO theater mode.
      class TheaterModeEnterButton extends Button {
        constructor(player, options) {
          super(player, options);
          // Tooltip/aria label and configured skin class.
          this.controlText("Switch to Theater Mode");
          this.addClass(
            owner.master.settings.theaterModeButtons.enterTheaterModeButton.skin
          );
        }
        handleClick() {
          // Swap the two buttons, then run the site-supplied enter callback.
          owner.theaterModeEnterButton.hide();
          owner.theaterModeLeaveButton.show();
          console.log("entering Theater Mode...");
          // NOTE: eval() of a settings-provided callback string (see report).
          eval(
            owner.master.settings.theaterModeButtons.enterTheaterModeButton
              .callback
          );
        }
      }
      // Control-bar button that switches OUT of theater mode.
      class TheaterModeLeaveButton extends Button {
        constructor(player, options) {
          super(player, options);
          // Tooltip/aria label and configured skin class.
          this.controlText("Switch to Normal Mode");
          this.addClass(
            owner.master.settings.theaterModeButtons.leaveTheaterModeButton.skin
          );
        }
        handleClick() {
          // Swap the two buttons, then run the site-supplied leave callback.
          owner.theaterModeEnterButton.show();
          owner.theaterModeLeaveButton.hide();
          console.log("leaving Theater Mode...");
          // NOTE: eval() of a settings-provided callback string (see report).
          eval(
            owner.master.settings.theaterModeButtons.leaveTheaterModeButton
              .callback
          );
        }
      }

      // Register both custom components with Video.js.
      videojs.registerComponent(
        "TheaterModeEnterButton",
        TheaterModeEnterButton
      );
      videojs.registerComponent(
        "TheaterModeLeaveButton",
        TheaterModeLeaveButton
      );
      // Insert the buttons into the control bar at positions 17 and 18.
      this.theaterModeEnterButton = owner.player
        .getChild("controlBar")
        .addChild("TheaterModeEnterButton", {}, 17);
      this.theaterModeLeaveButton = owner.player
        .getChild("controlBar")
        .addChild("TheaterModeLeaveButton", {}, 18);
      // Start with the "leave" button hidden (we begin in normal mode).
      this.theaterModeLeaveButton.hide();
    }

    // this.player.controls(false);
    // Hide the default big center play button (custom UI drives playback).
    this.player.bigPlayButton.hide();
    // player.controlBar.progressControl.hide();
    // Capture `this` for the event handlers registered below.
    const owner = this;

    // Show the title overlay while the user is active (mouse/touch).
    this.player.on('useractive', function() {
      owner.master.showTitleOverlay();
    });

    // Hide the title overlay once the user goes idle.
    this.player.on('userinactive', function() {
      owner.master.showTitleOverlay(false);
    });

    // "play": toggle title overlay, mark played, and update trailer/content UI.
    this.player.on("play", function (event) {
      console.log("Play");

      // If the user was active when play fired, reveal the title overlay.
      if (this.userActive()) {
        owner.master.showTitleOverlay();
      }

      // Record that playback has started at least once.
      owner.played = true;
      console.log("src: ", owner.player.currentSrc());
      console.log("playingTrailer: ", owner.playingTrailer);
      if (owner.playingTrailer) {
        // A trailer just began: advance its state and move the message to the bottom.
        console.log("trailerState: ", owner.trailerState);
        if (owner.trailerState == "attempted") {
          owner.trailerState = "playing";
          owner.master.liveMessage.showAtBottom(true);
        }
        // Toggle DOM elements for the "trailer playing" layout.
        showDomElements('playing_trailer', owner.player.el());
      } else {
        // Content playing: toggle DOM elements for the "content playing" layout.
        showDomElements('playing_content', owner.player.el());
      }
      // Hide the trailer play button and reconcile mute/unmute buttons.
      owner.master.playTrailerButton.hide();
      owner.master.showHideMuteTrailerButtons();
    });
    // "pause": logging only.
    this.player.on("pause", function (event) {
      console.log("Pause");
    });
    // "ended": handle trailer end (return to idle) vs. content end (reset controls).
    this.player.on("ended", () => {
      console.log("Ended");
      console.log("playingTrailer: ", this.playingTrailer);
      if (this.playingTrailer) {
        // Trailer finished: restore idle layout and stop the trailer.
        showDomElements('idle', owner.player.el());
        owner.stopTrailer();
      } else {
        // Content finished: hide controls and reset the started flag.
        console.log("content ended");
        this.player.controls(false);
        this.player.hasStarted(false);
      }
      // Reconcile the trailer mute/unmute buttons.
      this.master.showHideMuteTrailerButtons();
    });
    // "durationchange": for finite (VOD-like) durations, hide the live controls.
    this.player.on("durationchange", () => {
      const duration = this.player.duration();
      console.log("durationchange: ", duration);
      if (duration > 0 && duration < Infinity) {
        this.player.controls(false);
      }
    });
    // "error": logging only (recovery is handled elsewhere / by the watchdog).
    this.player.on("error", (error) => {
      console.log("error: ", error);
    });
    // "volumechange": reconcile the trailer mute/unmute buttons.
    this.player.on("volumechange", () => {
      console.log("muted: ", this.player.muted());
      this.master.showHideMuteTrailerButtons();
    });

    console.log("src: ", this.player.currentSrc());
    // setTimeout(() => {
    //     this.player.bigPlayButton.show();
    // }, 2000);

    // The handlers below forward player events to the QoE collector, but only
    // for real content (trailers are excluded from telemetry).
    this.player.on("play", () => {
      if (!this.playingTrailer) this.qoe.play();
    });
    this.player.on("pause", () => {
      if (!this.playingTrailer) this.qoe.pause();
    });
    this.player.on("waiting", () => {
      // "waiting" marks a rebuffering event for QoE.
      if (!this.playingTrailer) this.qoe.waiting();
    });
    this.player.on("playing", () => {
      // "playing" ends a startup/rebuffer interval for QoE.
      if (!this.playingTrailer) {
        this.qoe.playing();
      }
    });
    this.player.on("loadeddata", () => {
      if (!this.playingTrailer) {
        this.qoe.loadeddata();
      }
    });
    this.player.on("resolutionchange", () => {
      if (!this.playingTrailer)this.qoe.resolutionchange();
    });
    this.player.on("ended", () => {
      if (!this.playingTrailer)this.qoe.ended();
    });
    this.player.on("error", () => {
      if (!this.playingTrailer) this.qoe.error();
    });
  }

  /**
   * Apply a Video.js skin theme, removing any of the known themes first.
   *
   * @param {string} themeName Theme suffix (e.g. "city", "forest", "sea").
   */
  applyTheme(themeName) {
    // Known theme classes; strip all of them so only one is active.
    const themes = ['vjs-theme-city', 'vjs-theme-forest', 'vjs-theme-fantasy', 'vjs-theme-sea'];
    themes.forEach(theme => {
      this.player.removeClass(theme);
    })

    // Add the requested theme class.
    this.player.addClass(`vjs-theme-${themeName}`);
  }

  /**
   * Placeholder for a theme selector control (currently unimplemented).
   */
  initThemeSelector() {
    const Button = videojs.getComponent('Button');
    const owner = this;

  }

  /**
   * Start (or arm) trailer playback.
   *
   * @param {string}  src   Trailer source URL.
   * @param {boolean} click True when initiated by an explicit user click.
   */
  playTrailer(src, click) {
    console.log("### playTrailer: ", src, click);
    console.log("trailerState: ", this.trailerState);
    // if (this.trailerState == 'notstarted' || this.trailerState == 'attempted'){

    // Not yet started and not a click → just reveal the play-trailer button.
    if (this.trailerState == "notstarted" && !click) {
      this.master.playTrailerButton.show();
    }

    // Load the trailer source when starting fresh or on an explicit click.
    if (this.trailerState == "notstarted" || click) {
      this.playingTrailer = true;
      const player = this.player;
      // Hide controls and point the player at the trailer.
      player.controls(false);
      player.src({ src });
      if (click) {
        // Explicit click → actually begin playback.
        player.play();
        // showDomElements('playing_trailer');  //probably not needed
      }
      // Mark that a trailer play has been attempted.
      this.trailerState = "attempted";
    }
  }

  /**
   * Stop the trailer and (if it was playing) reset overlay/started state.
   */
  stopTrailer() {
    console.log("stopTrailer()");
    console.log("trailerState: ", this.trailerState);
    // Only transition if the trailer was actually playing.
    if (this.trailerState == "playing") {
      console.log("trailer has ended");
      this.trailerState = "ended";
      // Move the message overlay back up and reset the started flag.
      this.master.liveMessage.showAtBottom(false);
      this.player.hasStarted(false);
    }
    // In all cases, we are no longer playing a trailer.
    this.playingTrailer = false;
  }

  /**
   * Record the live content source (and enable controls). The actual src load
   * is deferred via a timeout; the load body is currently commented out.
   *
   * @param {string}  src   HLS content URL.
   * @param {boolean} force When true, use a near-immediate timeout.
   */
  setContentSrc(src, force) {
    console.log("### setContentSrc: ", src, force);
    console.log("currentSrc: ", this.player.currentSrc());
    console.log("paused: ", this.player.paused());
    console.log("currentTime: ", this.player.currentTime());
    // Content (not trailer) is now the active source; remember the URL.
    this.playingTrailer = false;
    this.contentSrc = src;

    // Debounce: cancel any pending deferred src set, then re-arm it.
    const owner = this;
    clearTimeout(this.setSrcTimeout);
    this.setSrcTimeout = setTimeout(
      () => {
        console.log("setting src...");
        // src = src != null ? src : owner.player.currentSrc()
        // owner.player.src({
        //     src,
        //     type: "application/x-mpegURL",
        //     llhls: isLlHls(src)
        // });
        // owner.playContent(force);
      },
      // force → ~1ms; otherwise wait 2s to coalesce rapid updates.
      force ? 1 : 2000
    );

    // this.player.controlBar.show();
    // this.player.loadingSpinner.show();
    // Enable the control bar for content.
    this.player.controls(true);
    // this.player.muted(true);
    // this.player.play();
  }

  /**
   * Play the live content: cancel deferred src, load the content source when
   * not already playing (or when forced), and show controls/spinner.
   *
   * @param {boolean} forced Force a fresh src load + play attempt.
   */
  playContent(forced) {
    console.log("playContent() ", forced);
    // Clear paused/trailer flags and hide the trailer button.
    this.paused = false;
    this.stopTrailer();
    this.playingTrailer = false;
    this.master.playTrailerButton.hide();
    // Show the big play button and cancel any pending deferred src set.
    this.player.bigPlayButton.show();
    clearTimeout(this.setSrcTimeout);
    console.log("player.paused: ", this.player.paused());
    console.log("currentTime: ", this.player.currentTime());
    console.log("objectivelyPlayingContent: ", this.objectivelyPlayingContent);

    // if (this.player.paused() || forced || this.player.currentTime() === 0){
    // Only (re)load the source if content isn't already visibly advancing, or if forced.
    if (!this.objectivelyPlayingContent || forced) {
      // Point the player at the live HLS content (LL-HLS auto-detected).
      this.player.src({
        src: this.contentSrc,
        type: "application/x-mpegURL",
        llhls: isLlHls(this.contentSrc),
      });
      console.log("autoplay: ", this.player.autoplay());
      // this.player.currentTime(0);
      console.log("played: ", this.played);
      // Only auto-resume if the user has already started playback once.
      if (this.played) {
        var promise = this.player.play();
        // console.log("promise: ", promise);
        let player = this.player;
        // Handle the play() promise to log autoplay success/failure.
        if (promise !== undefined) {
          promise
            .then(function () {
              console.log("Autoplay started ;)");
            })
            .catch(function (error) {
              console.log("Autoplay did not work ", error);
            });
        }
        console.log("no promise");
      }
    }
    // Reveal the control bar, spinner, and enable controls.
    this.player.controlBar.show();
    this.player.loadingSpinner.show();
    this.player.controls(true);
  }

  /**
   * Pause content playback and hide the control UI. When `stop` is true, also
   * reset the "has started" flag (used for the ended/stopped case).
   *
   * @param {boolean} [stop] True to also reset hasStarted().
   */
  pauseContent(stop) {
    console.log("pauseContent()");
    // Mark as intentionally paused and cancel any pending deferred src set.
    this.paused = true;
    clearTimeout(this.setSrcTimeout);
    console.log("playingTrailer: ", this.playingTrailer);
    // Never pause when a trailer is playing.
    if (!this.playingTrailer) {
      console.log("paused: ", this.player.paused());
      // Pause and hide the control bar / spinner / controls.
      this.player.pause();
      console.log("currentTime: ", this.player.currentTime());
      this.player.controlBar.hide();
      this.player.loadingSpinner.hide();
      this.player.controls(false);
      // Full stop → reset the started state so the poster/UI resets.
      if (stop) {
        this.player.hasStarted(false);
      }
    }
  }

  /**
   * Stall-recovery watchdog. Sampled once per second, it tracks currentTime in
   * a rolling window; if playback appears frozen (time not advancing over ~25
   * samples) while it should be live, it forces a fresh content load.
   */
  runWatchdog() {
    //console.log("runWatchdog()");
    // Sample the current playback position and push it onto the rolling queue.
    const currentTime = this.player.currentTime();
    this.timeQueue.push(currentTime);

    // Determine if content is genuinely advancing (last sample > previous sample).
    var objectivelyPlayingContent = false;
    if (currentTime > 0 && !this.playingTrailer) {
      const queueLength = this.timeQueue.length;
      if (queueLength > 1) {
        if (this.timeQueue[queueLength - 1] > this.timeQueue[queueLength - 2]) {
          objectivelyPlayingContent = true;
        }
      }
    }
    // Expose the "really playing" flag for playContent()'s reload decision.
    this.objectivelyPlayingContent = objectivelyPlayingContent;

    // Once the window exceeds 25 samples, evaluate for a stall.
    if (this.timeQueue.length > 25) {
      // Keep the window bounded.
      this.timeQueue.shift();
      // If the oldest and newest samples match, currentTime has not advanced → stalled.
      if (this.timeQueue[0] === this.timeQueue[this.timeQueue.length - 1]) {
        console.log(
          "queue: ",
          this.timeQueue[0],
          this.timeQueue[this.timeQueue.length - 1]
        );
        console.log("paused: ", this.paused);
        console.log("ruler: ", this.master.ruler);
        console.log("state: ", this.master.state);
        console.log("player paused: ", this.player.paused());
        console.log("currentTime: ", this.player.currentTime());

        // Ruler 2 (WebSocket live): force a reload if the player isn't paused.
        if (this.master.ruler == 2) {
          if (!this.player.paused()) {
            this.playContent(true);
          }
        } else if (this.master.state > 1) {
          // Otherwise, when past "notstarted", reload if playing or stuck at 0.
          if (!this.player.paused() || this.player.currentTime() === 0) {
            this.playContent(true);
          }
        }
        // Reset the window after acting on a stall.
        this.timeQueue = [];
      }
    }
    // Re-arm the watchdog to run again in ~1 second.
    let self = this;
    setTimeout(() => self.runWatchdog(), 1 * 1000);
  }
}

/**
 * Toggle the surrounding page chrome (titles, descriptions, buttons, CSS
 * classes) to match a playback layout: idle, trailer playing, or content
 * playing. Operates on elements found near the given player element.
 *
 * @param {string}      state  'idle' | 'playing_trailer' | 'playing_content'.
 * @param {HTMLElement} target The player element used as the DOM anchor.
 */
function showDomElements(state, target){
  console.log("showDomElements: ", state, target);
  switch (state){
    case 'idle':
      // Idle layout: reveal titles/descriptions/trailer button, drop the trailer class.
      // jQuery(".wpstream_hide_on_trailer").show();
      showHideNearby(target, ".wpstream_hide_on_trailer");
      // jQuery(".wpstream_hide_on_play").show();
      showHideNearby(target, ".wpstream_hide_on_play");
      // jQuery(".wpstream_bundble_title_details h1 ").show();
      showHideNearby(target, ".wpstream_bundble_title_details h1 ");
      // jQuery(".wpstream_bundble_title_details .wpstream-product-description").show();
      showHideNearby(target, ".wpstream_bundble_title_details .wpstream-product-description");
      // jQuery(".wpstream_bundble_title_details .wpstream-product-categories-wrapper").show();
      showHideNearby(target, ".wpstream_bundble_title_details .wpstream-product-categories-wrapper");
      // jQuery(".wpstream_video_on_demand_play_trailer").show();
      showHideNearby(target, ".wpstream_video_on_demand_play_trailer");
      // jQuery(".wpstream_video_on_demand_unmute_trailer").hide();
      showHideNearby(target, ".wpstream_video_on_demand_unmute_trailer", false);
      // jQuery(".vjs-wpstream").removeClass("wpstream_theme_player_has_trailer");
      addRemoveClassNearby(target, '.vjs-wpstream', 'wpstream_theme_player_has_trailer', false)
      break;
    case 'playing_trailer':
      // Trailer layout: hide title details, keep hide-on-play, add the trailer class.
      // jQuery(".wpstream_hide_on_trailer").hide();
      showHideNearby(target, ".wpstream_hide_on_trailer", false);
      // jQuery(".wpstream_hide_on_play").show();
      showHideNearby(target, ".wpstream_hide_on_play");
      // jQuery(".wpstream_bundble_title_details h1 ").hide();
      showHideNearby(target, ".wpstream_bundble_title_details", false);
      // jQuery(".wpstream_bundble_title_details .wpstream-product-description").hide();
      showHideNearby(target, ".wpstream_bundble_title_details .wpstream-product-description", false);
      // jQuery(".wpstream_bundble_title_details .wpstream-product-categories-wrapper").hide();
      showHideNearby(target, ".wpstream_bundble_title_details .wpstream-product-categories-wrapper", false);
      // jQuery(".vjs-wpstream").addClass("wpstream_theme_player_has_trailer");
      addRemoveClassNearby(target, '.vjs-wpstream', 'wpstream_theme_player_has_trailer')
      break;
    case 'playing_content':
      // Content layout: hide both trailer and play hint elements, drop the trailer class.
      // jQuery(".wpstream_hide_on_trailer").hide();
      showHideNearby(target, ".wpstream_hide_on_trailer", false);
      // jQuery(".wpstream_hide_on_play").hide();
      showHideNearby(target, ".wpstream_hide_on_play", false);
      // jQuery(".vjs-wpstream").removeClass("wpstream_theme_player_has_trailer");
      addRemoveClassNearby(target, '.vjs-wpstream', 'wpstream_theme_player_has_trailer', false)
      break;
  } 
}

/**
 * Show or hide elements matching a selector found near the player.
 *
 * @param {HTMLElement} target      DOM anchor (the player element).
 * @param {string}      targetClass jQuery selector to match nearby.
 * @param {boolean}     [show=true] True to show, false to hide.
 */
function showHideNearby(target, targetClass, show = true) {
    // Choose the jQuery method based on the show flag.
    var method = show ? 'show' : 'hide';
    // Find matching elements near the anchor and apply the method to each.
    var nearbyElements = findNearbyElements(target, targetClass);
    nearbyElements.each(function() {
        jQuery(this)[method]();
    });
}

/**
 * Add or remove a CSS class on elements found near the player.
 *
 * @param {HTMLElement} target      DOM anchor (the player element).
 * @param {string}      targetClass jQuery selector to match nearby.
 * @param {string}      className   Class to add/remove.
 * @param {boolean}     [add=true]  True to add, false to remove.
 */
function addRemoveClassNearby(target, targetClass, className, add = true) {
    // Choose addClass/removeClass based on the add flag.
    var method = add ? 'addClass' : 'removeClass';
    // Apply the class mutation to each nearby match.
    var nearbyElements = findNearbyElements(target, targetClass);
    nearbyElements.each(function() {
        jQuery(this)[method](className);
    });
}

/**
 * Find elements matching a selector within the anchor's nearby ancestors.
 *
 * @param {HTMLElement} target      DOM anchor (the player element).
 * @param {string}      targetClass jQuery selector to search for.
 * @return {jQuery} Matched elements.
 */
function findNearbyElements(target, targetClass) {
    // Walk up to 4 ancestor levels and search within them for the selector.
    var $target = jQuery(target);
    var nearbyElements = $target.parents().slice(0, 4).find(targetClass);
    // .add($target.closest('.container').find('.' + targetClass));
    return nearbyElements;
}


/**
 * Generate a random 32-character alphanumeric string (used as a QoE session id).
 *
 * @return {string} 32-char random string.
 */
function randomString32() {
  // Build 32 random base-36 characters and join them.
  return Array.from({ length: 32 }, () =>
    Math.random().toString(36)[2]
  ).join('');
}

/**
 * Quality-of-Experience collector.
 *
 * Tracks per-session startup time, rebuffering count/duration, and total
 * playback time based on player events, and periodically reports a summary via
 * the supplied callback (wired to the LiveConnect WebSocket).
 */
class Qoe {
  /**
   * @param {Function} callback      Report sink, invoked with the QoE payload.
   * @param {Object}   callbackScope `this` binding for the callback.
   */
  constructor(callback, callbackScope) {
    // console.log("Qoe: ", callback, callbackScope);
    // Store the report callback and the scope it should be called with.
    this.callback = callback;
    this.callbackScope = callbackScope;
  }

  /**
   * Begin a new measurement session on "play": report the prior session, reset
   * all counters, mint a session id, and start the 60s periodic reporter.
   */
  play() {
    // console.log("----qoe play");
    // Flush any data from the previous session first.
    this.reportCurrentSession();
    // Restart the periodic reporter (clear any existing interval).
    if (this.reportInterval){
      clearInterval(this.reportInterval);
      this.reportInterval = null;
    }
    this.reportInterval = setInterval(() => {
      this.reportCurrentSession()
    }, 60 * 1000);
    // Fresh session id and zeroed counters.
    this.currentSession = randomString32();
    this.rebufferCount = 0;
    this.startupTime = 0;
    this.totalPlaybackTime = 0;
    this.totalRebufferTime = 0;
    this.lastRebufferStartTimestamp = null;
    // Mark the moment play began; used to compute startup time on first "playing".
    this.playTimestamp = performance.now();
  }

  /**
   * On "pause": treat as an interruption (delegates to waiting()).
   */
  pause(){
    // console.log("----qoe pause");
    this.waiting();
  }

  /**
   * On "ended": treat as an interruption (delegates to waiting()).
   */
  ended(){
    // console.log("----qoe ended");
    this.waiting();
  }

  /**
   * On "playing": close out the startup interval (first time) and/or the
   * current rebuffer interval, updating counters, then mark playback resumed.
   */
  playing(){
    // console.log("----qoe playing ", document.hidden);
    // console.log("playTimestamp: ", this.playTimestamp);
    // console.log("lastRebufferStartTimestamp: ", this.lastRebufferStartTimestamp);
    // First "playing" after play() → measure startup latency.
    if (this.playTimestamp){
      this.startupTime = performance.now() - this.playTimestamp;
      // console.log("startupTime: ", this.startupTime / 1000);
      this.playTimestamp = null;
    }
    // If we were rebuffering, close that interval and fold it into the totals.
    if (this.lastRebufferStartTimestamp) {
      let rebufferTime = performance.now() - this.lastRebufferStartTimestamp;
      // console.log("    rebufferTime: ", (rebufferTime / 1000).toFixed(2));
      if (rebufferTime < 60 * 1000){  //discard unrealistically long rebuffers
        if (rebufferTime > 500){  //do not count short rebuffers
          this.rebufferCount ++;
        }
        this.totalRebufferTime += rebufferTime;
      }
      this.lastRebufferStartTimestamp = null;
    }
    // Mark the start of the current continuous playing interval.
    this.lastPlayingTimestamp = performance.now();
  }

  /**
   * On "waiting"/pause/ended: accumulate the just-ended playing interval and, if
   * appropriate, open a new rebuffer interval (unless it's the initial buffering
   * or the tab is hidden).
   */
  waiting(){
    // console.log("----qoe waiting", document.hidden);
    // console.log("playTimestamp: ", this.playTimestamp);
    // console.log("lastPlayingTimestamp: ", this.lastPlayingTimestamp);
    // console.log("lastRebufferStartTimestamp: ", this.lastRebufferStartTimestamp);

    // Close the current playing interval and add it to total playback time.
    if (this.lastPlayingTimestamp){
      let playingTime = performance.now() - this.lastPlayingTimestamp;
      this.lastPlayingTimestamp = null;
      // console.log("    playingTime: ", (playingTime / 1000).toFixed(2));
      this.totalPlaybackTime += playingTime;
      // console.log("totalPlaybackTime: ", this.totalPlaybackTime);
    }

    if (this.playTimestamp){  //it's the first time it buffers
      // do nothing
    }
    // Otherwise start a rebuffer timer, but only if not already rebuffering and the tab is visible.
    else if (!this.lastRebufferStartTimestamp && !document.hidden) {
      // console.log("rebufferCount: ", this.rebufferCount);
      this.lastRebufferStartTimestamp = performance.now();
    }
  }

  /**
   * On "loadeddata": no-op hook (reserved for future metrics).
   */
  loadeddata(){
    // console.log("qoe loadeddata")
  }

  /**
   * On "resolutionchange": no-op hook (reserved for future metrics).
   */
  resolutionchange(){
    // console.log("qoe resolutionchange")
  }

  /**
   * On "error": no-op hook (reserved for future metrics).
   */
  error(){
    // console.log("qoe error")
  }

  /**
   * Build and emit the current session's QoE report via the callback, but only
   * when there is playback to report and the totals changed since last report.
   */
  reportCurrentSession(){
    // console.log("----reportCurrentSession: ");
    // console.log("totalPlaybackTime: ", this.totalPlaybackTime);
    // console.log("lastPlayingTimestamp: ", this.lastPlayingTimestamp);
    // Only report if some playback has accrued (finished or in-progress).
    if (this.totalPlaybackTime > 0 || this.lastPlayingTimestamp){
      let totalPlaybackTime = this.totalPlaybackTime;
      // console.log("totalPlaybackTime: ", totalPlaybackTime);
      // Include the still-running playing interval, if any.
      if (this.lastPlayingTimestamp){
        totalPlaybackTime += performance.now() - this.lastPlayingTimestamp;
      }
      // console.log("totalPlaybackTime: ", totalPlaybackTime);
      
      // Skip duplicate reports where the playback time hasn't advanced.
      if (!this.lastReportedPlaybackTime || this.lastReportedPlaybackTime != totalPlaybackTime){
        // Assemble the QoE payload for this session.
        let report = {
          startupTime: this.startupTime,
          totalPlaybackTime: totalPlaybackTime,
          rebufferCount: this.rebufferCount, 
          totalRebufferTime: this.totalRebufferTime,
          session: this.currentSession,
        }
        console.log("report: ", report);
        // console.log("callback: ", this.callback)
        // Send via the configured callback bound to its scope.
        this.callback.call(this.callbackScope, report);
        // Remember what we last reported to dedupe the next call.
        this.lastReportedPlaybackTime = totalPlaybackTime;
      }
    }
  }
}

/**
 * Thin bridge to the (optional) global chat implementation. Delegates to the
 * globally-defined `connect`/`showChat` functions when they exist.
 */
class WpstreamChat {
  // connected = '';

  /**
   * Initialise the connection-state flag.
   */
  constructor() {
    this.connected = "";
  }

  /**
   * Connect chat to the given URL (if a global `connect` function exists).
   *
   * @param {string} url Chat server URL.
   */
  connect(url) {
    // Mark connected and delegate to the global chat connector when available.
    this.connected = "yes";
    if (typeof connect === "function") {
      connect(url);
    }
  }

  /**
   * Disconnect chat, showing a "not connected" info message if we were connected.
   */
  disconnect() {
    // Only act if chat UI exists and we were previously connected.
    if (typeof showChat === "function" && this.connected === "yes") {
      showChat("info", null, wpstream_player_vars.chat_not_connected);
      this.connected = "no";
    }
  }
}

/**
 * The overlay message shown over the player when it is not live (stopped, init,
 * paused, startup, ended, error), plus an optional broadcaster-supplied custom
 * message. Also supports re-positioning to the bottom while a trailer plays.
 */
class WpstreamLiveMessage {
  // element;
  // msg;
  // originalMessage;
  // customMessage;
  // state = -1; // -1 - unknown; 0 - hidden; 1 - showing original msg; 3 - showing paused msg; 5 - showing custom msg

  /** @var {string[]} States for which a custom broadcaster message may override the default text. */
  static customMessageStates = [
    "stopped",
    "init",
    "paused",
    "startup",
    "ended",
  ];
  // Whether the message is currently pinned to the bottom (trailer mode).
  bottom = false;

  /**
   * Cache the overlay elements, remember the original markup, and move the
   * overlay inside the player element.
   *
   * @param {jQuery} wrapper Player wrapper element.
   * @param {string} id      Video element id suffix.
   */
  constructor(wrapper, id) {
    // Current display state (string label).
    this.state = "none";
    // The overlay container and its inner text element.
    this.element = wrapper.find(".wpstream_not_live_mess");
    this.msg = wrapper.find(".wpstream_not_live_mess_mess");
    // Preserve the original message markup to restore later.
    this.originalMessage = this.msg.html();
    console.log("originalMessage: ", this.originalMessage);
    // Move the overlay inside the video element so it renders on top.
    var playerElement = jQuery("#wpstream-video" + id);
    this.element.appendTo(playerElement);
  }

  /**
   * Store a broadcaster-supplied custom message; if currently paused/ended,
   * refresh the visible message immediately.
   *
   * @param {string} message Custom message text.
   */
  setCustomMessage(message){
    this.customMessage = message;
	// Refresh the on-screen message when paused/ended so the new text shows now.
	if (this.state === "paused" || this.state === "ended") {
		this.showMessage("paused");
	}
  }

  /**
   * Show the message for a given state, preferring the custom message when
   * allowed. Hides the overlay if the resolved label is blank.
   *
   * @param {string} state State whose localized message to display.
   */
  showMessage(state) {
    // console.log("showMessage: ", state);
    var label;
    // Use the custom message for eligible states; otherwise the localized default.
    if (
      this.customMessage &&
      WpstreamLiveMessage.customMessageStates.includes(state)
    ) {
      label = this.customMessage;
    } else {
      label = wpstream_player_vars[`wpstream_player_state_${state}_msg`];
    }
    // Set the text and a per-state CSS class.
    this.msg.text(label);
    this.msg.addClass(`wpstream_player_state_${state}_class`);
    // don't show if label is empty or spaces
    if (!/^\s*$/.test(label)) this.show();
    else this.hide();
    // Remember the current state.
    this.state = state;
  }

  /**
   * Restore and show the original (markup) message.
   */
  showOriginalMessage() {
    this.msg.html(this.originalMessage);
    this.show();
    this.state = "original";
  }

  /**
   * Reposition the overlay to the bottom (trailer mode) or its default spot,
   * and refresh the stopped/original message if one is currently showing.
   *
   * @param {boolean} show True to pin to the bottom.
   */
  showAtBottom(show) {
    console.log("showAtBottom: ", show);
    // Track position and move the overlay via inline top offset.
    this.bottom = show;
    this.element[0].style.top = this.bottom ? "80%" : "31%";

    // Re-render the appropriate stopped/original message for the new position.
    if (this.state == "stopped" || this.state == "original") {
      this.showStoppedMessage();
    }
  }

  /**
   * Show either the localized "stopped" message (bottom mode) or the original
   * markup message (default position).
   */
  showStoppedMessage() {
    console.log("element: ", this.element);
    if (this.bottom) {
      this.showMessage("stopped");
    } else {
      this.showOriginalMessage();
    }
  }

  //public
  /**
   * Hide the overlay and mark state as hidden (0).
   */
  hide() {
    this.element.hide();
    this.state = 0;
  }

  //private
  /**
   * Show the overlay element.
   */
  show() {
    this.element.show();
  }
}

/**
 * The live viewer-count badge overlaid on the player. Whether it renders at all
 * is controlled by the wrapper's `showviewercount` data attribute.
 */
class LiveCounter {
  // element;
  /**
   * Resolve the badge element, read the show/hide flag, move it into the player,
   * and start hidden.
   *
   * @param {jQuery} wrapper Player wrapper element.
   * @param {string} id      Video element id suffix.
   */
  constructor(wrapper, id) {
    console.log("[]LiveCounter: ", wrapper, id);
    // Badge element + its translucent red background.
    this.element = wrapper.find(".wpestream_live_counting");
    this.element.css("background-color", "rgb(174 69 69 / 90%)");

    // Read the data-* attributes; decide whether the counter should ever show.
    const data = this.element?.data?.() || {};
    console.log("showviewercount:", data.showviewercount);
    this.showCounter = (data.showviewercount !== undefined && data.showviewercount !== null)
        ? data.showviewercount.toString() === "1"
        : false;
    console.log("showCounter:", this.showCounter);

    //var playerElement = wrapper.find('.wpstream-video' + id);
    // Move the badge inside the video element and hide it initially.
    var playerElement = jQuery("#wpstream-video" + id);
    console.log("playerElement: ", playerElement);
    this.element.appendTo(playerElement);
    this.hide();
  }

  /**
   * Show the badge.
   */
  show() {
    this.element.show();
  }

  /**
   * Hide the badge.
   */
  hide() {
    this.element.hide();
  }

  /**
   * Update the viewer count; render only when the counter is enabled.
   *
   * @param {number} count Current viewer count.
   */
  setCount(count) {
    if (this.showCounter){
      // Enabled → render "N Viewers" and show.
      this.element.html(count + " Viewers");
      this.show();
    }
    else {
      // Disabled → keep hidden.
      this.hide();
    }
  }
  /**
   * Show a "max viewers reached" waiting message.
   *
   * @param {*} place Who must leave before a slot frees up.
   */
  showPending(place){
    this.element.html(`Max viewers reached. Please wait for ${place} to leave.`);
    this.show();
  }
}

/**
 * Real-time control channel over WebSocket. Receives viewer-count, pending,
 * on-air, and status messages and pushes QoE reports back to the server. When
 * the socket opens/closes it toggles the controller's ruler.
 */
class LiveConnect {
  // master;
  // wsUri;
  // ws;
  // connectCount = 0;
  // connected = false;
  // pendingConnect = false;

  /**
   * @param {WpstreamPlayer} master Owning controller.
   */
  constructor(master) {
    // Monotonic connection attempt counter (used to tag log lines).
    this.connectCount = 0;
    this.connected = false;
    this.pendingConnect = false;
    // Back-reference to the owning controller.
    this.master = master;
  }

  /**
   * (Re)initialise the connection to a new WebSocket URI.
   *
   * @param {string} wsUri WebSocket endpoint.
   */
  setup(wsUri) {
    console.log("setup: ", wsUri);
    // Tear down any existing socket, store the new URI, then connect.
    this.close();
    this.wsUri = wsUri;
    this.connect();
  }

  /**
   * Close and clear the current WebSocket, if any.
   */
  close() {
    if (this.ws != null) {
      this.ws.close();
    }
    this.ws = null;
  }

  /**
   * Send a QoE report over the socket (only when it is open).
   *
   * NOTE: bound as the Qoe callback, so `this` here is the LiveConnect instance.
   *
   * @param {Object} data QoE payload.
   */
  sendQoeData(data){
    // console.log("sendQoeData: ", data);
    // Only send when the socket exists and is open.
    if (this.ws && this.ws.readyState === WebSocket.OPEN){
      const message = {type:'qoe', data}
      this.ws.send(JSON.stringify(message));
    }
  }

  /**
   * Open the WebSocket and wire its open/close/error/message handlers.
   */
  connect() {
    // Tag this attempt for correlating log output.
    let connectAttempt = ++this.connectCount;
    console.log("connect() ", connectAttempt);
    this.pendingConnect = true;
    try {
      // Open the socket and capture `this` for the handlers.
      this.ws = new WebSocket(this.wsUri);
      let owner = this;
      // Open → mark connected and switch the controller to ruler 2.
      this.ws.onopen = function () {
        console.log("connected. ", connectAttempt);
        owner.pendingConnect = false;
        owner.master.onLiveConnectActive(true);
        //socket_connection.send(`{"type":"register","data":"${now}"}`);
      };
      // Close → notify the controller (falls back to AJAX polling).
      this.ws.onclose = function () {
        console.log("onclose.. ", connectAttempt);
        owner.master.onLiveConnectActive(false);
      };
      // Error → same as close: deactivate LiveConnect.
      this.ws.onerror = function (error) {
        console.log("onerror: ", connectAttempt, error);
        owner.master.onLiveConnectActive(false);
      };
      // Message → clear the spinner and dispatch to processMessage.
      this.ws.onmessage = function (message) {
        removeSpinner( 2);
        console.log("onmessage: ", connectAttempt, message.data);
        owner.processMessage(message.data);
      };
    } catch (error) {
      // Construction failed → deactivate LiveConnect.
      console.log(error);
      this.master.onLiveConnectActive(false);
    }
  }

  /**
   * Parse and dispatch an incoming WebSocket message by its `type`.
   *
   * @param {string} msg Raw JSON message text.
   */
  processMessage(msg) {
    console.log("processMessage: ", msg);
    var json;
    // Guard against malformed messages.
    try {
      json = JSON.parse(msg);
    } catch (e) {
      console.log("Invalid JSON: ", msg);
      return;
    }
    if (json.type) {
      switch (json.type) {
        case "viewerCount":
			// Update the viewer counter (suppressed for Streamify users).
			if ( !wpstream_player_vars.wpstream_is_streamify_user ) {
				this.master.updateViewerCount(json.data);
			}
          break;
        case "pending":
          // Viewer is queued because max viewers is reached.
          this.master.updatePending(json.data);
          break;
        case "onair":
          // On-air push: prefer the explicit broadcasting state if present.
          if (json.info) {
            this.master.setState(json.info.broadcasting);
          } else {
            this.master.setState(json.data ? "onair" : "paused");
          }
          break;
        case "status":
          // Broadcaster-supplied custom status message.
          this.master.liveMessage.setCustomMessage(json.data);
          break;
        default:
          // Unknown message type.
          console.log("invalid type: ", json.type);
      }
    }
  }
}

/**
 * Legacy helper: when an SLDP player exists, open a chat connection using the
 * live-connect views URI.
 *
 * @param {*}      event_id                          Event identifier.
 * @param {*}      player                            Player reference.
 * @param {*}      player_wrapper                    Wrapper reference.
 * @param {string} socket_wss_live_conect_views_uri  WebSocket views/chat URI.
 * @param {*}      event_uri                         Event content URI.
 */
function wpstream_read_websocket_info(
  event_id,
  player,
  player_wrapper,
  socket_wss_live_conect_views_uri,
  event_uri
) {
  console.log(
    "wpstream_read_websocket_info: ",
    event_id,
    player,
    player_wrapper,
    socket_wss_live_conect_views_uri,
    event_uri
  );
  console.log("sldpPlayer: ", sldpPlayer);
  // Only connect chat when an SLDP player instance is present.
  if (sldpPlayer != null) {
    var chat = new WpstreamChat();
    chat.connect(socket_wss_live_conect_views_uri);
  }
}

// Module-level reference to the (legacy) SLDP/OvenPlayer instance.
var sldpPlayer;

/**
 * Initialise a low-latency WebRTC player via OvenPlayer, lazy-loading the
 * OvenPlayer script from CDN first.
 *
 * @param {string} playerID        DOM id of the player container.
 * @param {string} low_latency_uri WebRTC source URI.
 * @param {string} muted           "muted" to start muted.
 * @param {string} autoplay        "autoplay" to auto-start.
 */
function initPlayer(playerID, low_latency_uri, muted, autoplay) {
  console.log("initPlayer: ", low_latency_uri);
  // Translate the string flags into booleans (default: unmuted, autoplay on).
  var is_muted = false;
  var is_autoplay = true;
  if (muted === "muted") {
    is_muted = true;
  }

  if (autoplay !== "autoplay") {
    is_autoplay = false;
  }

  console.log("is_muted " + is_muted + "/ " + is_autoplay);

  // Load OvenPlayer on demand, then create the WebRTC player once available.
  loadScriptIfNeeded('https://cdn.jsdelivr.net/npm/ovenplayer/dist/ovenplayer.js')
    .then(() => {
        let player = OvenPlayer.create(playerID, {
        autoStart: is_autoplay,
        autoFallback: false,
        mute: is_muted,
        // Primary source is WebRTC (low latency).
        sources: [
          {
            type: "webrtc",
            file: low_latency_uri,
          },
        ],
        // HLS fallback tuning for low-latency live sync.
        hlsConfig: {
          liveSyncDuration: 1.5,
          liveMaxLatencyDuration: 3,
          maxLiveSyncPlaybackRate: 1.5,
        },
        // WebRTC retry/timeout tuning.
        webrtcConfig: {
          timeoutMaxRetry: 100,
          connectionTimeout: 10000,
        },
      });
    })
    .catch((error) => {
        // Script failed to load → log and abort init.
        console.error('Error loading the script:', error);
    });

  
}

/**
 * Destroy the current SLDP/OvenPlayer instance.
 */
function removePlayer() {
  sldpPlayer.destroy();
}

/**
 * Load an external script once, resolving immediately if already present.
 *
 * @param {string} scriptUrl Script URL to load.
 * @return {Promise<void>} Resolves when the script is available.
 */
function loadScriptIfNeeded(scriptUrl) {
    return new Promise((resolve, reject) => {
        // Check if the script is already loaded
        if (document.querySelector(`script[src="${scriptUrl}"]`)) {
            console.log(`Script already loaded: ${scriptUrl}`);
            resolve(); // Resolve immediately if the script is already loaded
            return;
        }

        // Create and append the script if not loaded
        const script = document.createElement('script');
        script.src = scriptUrl;
        script.type = 'text/javascript';
        script.async = true;

        // Resolve the promise once the script finishes loading.
        script.onload = () => {
            console.log(`Script loaded: ${scriptUrl}`);
            resolve();
        };

        // Reject the promise if the script fails to load.
        script.onerror = () => {
            console.error(`Failed to load script: ${scriptUrl}`);
            reject(new Error(`Failed to load script: ${scriptUrl}`));
        };

        // Kick off the load by appending to <head>.
        document.head.appendChild(script);
    });
}

// Expected shape of the VOD settings object:
// {
//  videoElementId
//  trailerUrl
//  videoUrl
//  autoplay
//  muted
//  playTrailerButtonElementId
//  playVideoButtonElementId
// }
/**
 * Standalone Video-on-Demand entry point. Sets up a Video.js player that can
 * play an optional trailer followed by the main video, handles trailer/content
 * switching, mute controls, title overlay, captions, and YouTube sources
 * (including a start-on-load + retry loop for YouTube autoplay).
 *
 * @param {Object} settings VOD configuration (element ids, URLs, flags).
 */
function wpstream_player_initialize_vod(settings) {
  console.log("wpstream_player_initialize_vod: ", settings);
  // Start in "trailer" mode when a trailer URL exists, otherwise "content".
  var playing = settings.trailerUrl ? "trailer" : "content";

  // jQuery handles for the trailer/video/mute/unmute buttons.
  const playTrailerButton = jQuery(`#${settings.playTrailerButtonElementId}`);
  const playVideoButton = jQuery(`#${settings.playVideoButtonElementId}`);
  const muteTrailerButton = jQuery(`#${settings.muteTrailerButtonElementId}`);
  const unmuteTrailerButton = jQuery(
    `#${settings.unmuteTrailerButtonElementId}`
  );
  // Optional title overlay element.
  const titleOverlay = document.getElementById(settings.titleOverlayElementId);

  // Mute controls start hidden until a trailer is actually playing.
  muteTrailerButton.hide();
  unmuteTrailerButton.hide();

  // Hide the trailer / video buttons when their respective URLs are absent.
  if (!settings.trailerUrl) {
    playTrailerButton.hide();
  }
  if (!settings.videoUrl) {
    playVideoButton.hide();
  }

  // Choose the initial source: trailer if present, else the main video.
  const initialSrc = settings.trailerUrl
    ? getSrc(settings.trailerUrl)
    : getSrc(settings.videoUrl);
  console.log("initialSrc: ", initialSrc);

  // Create the Video.js player and apply base configuration.
  const player = videojs(settings.videoElementId);
  player.preload("auto");
  player.playsinline(true);
  // Controls are off while a trailer plays; on for direct content.
  player.controls(!settings.trailerUrl);
  player.autoplay(settings.autoplay);
  player.muted(settings.muted);

  // Overlay the watermark/logo when the logo plugin is available.
  if ( typeof player.logo === 'function' && settings.playerLogoSettings ) {
    player.logo(settings.playerLogoSettings);
  }

  // Remember the original poster so it can be restored after the trailer.
  const originalPoster = player.poster();

  // Spinner used for YouTube buffering/tech init.
  // Lazily-created spinner element (only needed for YouTube).
  let wpstreamVodSpinnerEl = null;
  // Create the spinner element once, inside a positioned container.
  const wpstreamEnsureVodSpinner = () => {
    // Already created → nothing to do.
    if (wpstreamVodSpinnerEl) return;
    try {
      // Use the player element's parent as the spinner container.
      const container = player.el() && player.el().parentNode ? player.el().parentNode : null;
      if (!container) return;

      // Ensure absolute-positioned spinner has a positioning context.
      try {
        // If the container is static-positioned, make it relative so the spinner anchors to it.
        const currentPos = window.getComputedStyle(container).position;
        if (!currentPos || currentPos === 'static') {
          container.style.position = 'relative';
        }
      } catch (e) {
        // ignore
      }

      // Build the hidden spinner div and attach it.
      wpstreamVodSpinnerEl = document.createElement('div');
      wpstreamVodSpinnerEl.className = 'wpstream-pre-load-spinner';
      wpstreamVodSpinnerEl.style.display = 'none';
      container.appendChild(wpstreamVodSpinnerEl);
    } catch (e) {
      // On any failure, leave the spinner unset.
      wpstreamVodSpinnerEl = null;
    }
  };

  // Show the spinner (creating it first if needed).
  const wpstreamShowVodSpinner = () => {
    wpstreamEnsureVodSpinner();
    if (wpstreamVodSpinnerEl) {
      wpstreamVodSpinnerEl.style.display = 'block';
    }
  };

  // Hide the spinner if it exists.
  const wpstreamHideVodSpinner = () => {
    if (wpstreamVodSpinnerEl) {
      wpstreamVodSpinnerEl.style.display = 'none';
    }
  };

  // Load the initial source, forcing muted autoplay for the first paint.
  player.src({ ...initialSrc, autoplay: true, muted: true });

	// Add an English captions track when a captions URL is supplied, and show it.
	if ( settings.captionsUrl ) {
		const trackObject = player.addRemoteTextTrack({
			kind: 'captions',
			src: settings.captionsUrl,
			srclang: 'en',
			label: 'English',
			default: true
		}, false);
		trackObject.track.mode = "showing";
	}

	// Move the title overlay inside the player element so it renders on top.
	if (titleOverlay){
    player.el().appendChild(titleOverlay);
  }

  //  Enable HLS quality selector (Video.js 8 compatible)
  // Disable for now on VODs
  // try {
  //   if (typeof window.wpstreamInstallQualitySelector === 'function' ) {
  //     window.wpstreamInstallQualitySelector(player);
  //   }
  // } catch (e) {
  //   // optional
  // }

  // Optionally add theater-mode enter/leave control-bar buttons (VOD variant).
  if (settings.theaterModeButtons) {
    // Video.js Button base + captured `this` (note: `this` is the call context).
    const Button = videojs.getComponent("Button");
    const owner = this;

    // Button that switches INTO theater mode.
    class TheaterModeEnterButton extends Button {
      constructor(player, options) {
        super(player, options);
        this.controlText("Switch to Theater Mode");
        this.addClass(settings.theaterModeButtons.enterTheaterModeButton.skin);
      }
      handleClick() {
        // Swap buttons and run the site-supplied enter callback.
        owner.theaterModeEnterButton.hide();
        owner.theaterModeLeaveButton.show();
        console.log("entering Theater Mode...");
        // NOTE: eval() of a settings-provided callback string (see report).
        eval(settings.theaterModeButtons.enterTheaterModeButton.callback);
      }
    }
    // Button that switches OUT of theater mode.
    class TheaterModeLeaveButton extends Button {
      constructor(player, options) {
        super(player, options);
        this.controlText("Switch to Normal Mode");
        this.addClass(settings.theaterModeButtons.leaveTheaterModeButton.skin);
      }
      handleClick() {
        // Swap buttons and run the site-supplied leave callback.
        owner.theaterModeEnterButton.show();
        owner.theaterModeLeaveButton.hide();
        console.log("leaving Theater Mode...");
        // NOTE: eval() of a settings-provided callback string (see report).
        eval(settings.theaterModeButtons.leaveTheaterModeButton.callback);
      }
    }

    // Register both components and add them to the control bar (positions 17/18).
    videojs.registerComponent("TheaterModeEnterButton", TheaterModeEnterButton);
    videojs.registerComponent("TheaterModeLeaveButton", TheaterModeLeaveButton);
    this.theaterModeEnterButton = player
      .getChild("controlBar")
      .addChild("TheaterModeEnterButton", {}, 17);
    this.theaterModeLeaveButton = player
      .getChild("controlBar")
      .addChild("TheaterModeLeaveButton", {}, 18);
    // Start in normal mode → hide the leave button.
    this.theaterModeLeaveButton.hide();
  }

  // "play": update UI depending on whether the trailer or the content is playing.
  player.on("play", () => {
    console.log("play()");
    playTrailerButton.hide();
    // Reveal the title overlay if the user is currently active.
    if (player.userActive()) {
      showTitleOverlay();
    }
    console.log("playing: ", playing);
    if (playing == "trailer") {
      // Trailer: clear the poster, hide controls, apply trailer layout.
      player.poster(null);
      player.controls(false);
      showDomElements('playing_trailer', player.el());
    } else {
      // Content: hide the play button, show controls, apply content layout.
      playVideoButton.hide();
      player.controls(true);
      showDomElements('playing_content', player.el())
    }
    // Reconcile mute/unmute buttons.
    showHideMuteButtons();
  });

  // "ended": when the trailer ends, switch to the main video (or reset).
  player.on("ended", () => {
    console.log("ended");
    if (playing == "trailer") {
      console.log("trailer has ended");
      if (settings.videoUrl) {
        // Transition to the main content: enable controls/audio, restore poster, set src.
        playing = "content";
        player.controls(true);
        player.autoplay(false);
        player.muted(false);
        player.poster(originalPoster);
        player.src(getSrc(settings.videoUrl));
      } else {
        // No main video → just reset the started state.
        player.hasStarted(false);
      }
      // Restore idle chrome and the trailer button.
      player.bigPlayButton.hide();
      playTrailerButton.show();
      showHideMuteButtons();
      showDomElements('idle', player.el())
    } else {
      // Main content finished.
      console.log("video has ended");
    }
  });

  // "volumechange": reconcile mute buttons while playing.
  player.on("volumechange", () => {
    console.log("muted: ", player.muted());
    // console.log("paused: ", player.paused());
    if (!player.paused()) {
      showHideMuteButtons();
    }
  });

  /**
   * Show/hide the trailer mute+unmute buttons based on trailer state and mute.
   */
  function showHideMuteButtons() {
    console.log("showHideMuteButtons()");
    // Only relevant while the trailer is the active source.
    if (playing == "trailer") {
      if (player.muted()) {
        // Muted → show unmute.
        muteTrailerButton.hide();
        unmuteTrailerButton.show();
      } else {
        // Unmuted → show mute.
        muteTrailerButton.show();
        unmuteTrailerButton.hide();
      }
    } else {
      // Content → hide both.
      muteTrailerButton.hide();
      unmuteTrailerButton.hide();
    }
  }

  /**
   * Fade the title overlay in/out.
   *
   * @param {boolean} [show=true] True to show, false to hide.
   */
  function showTitleOverlay(show = true) {
    if (titleOverlay){
      titleOverlay.style.opacity = show ? '1' : '0';
    }
  }

  // "error": hide the spinner; if a trailer failed, fall back to the main video.
  player.on("error", () => {
    console.log("error()");
	wpstreamHideVodSpinner();
    if (playing == "trailer") {
      console.log("trailer failed");
      playTrailerButton.hide();
      // NOTE: references bare `videoUrl` (not settings.videoUrl) — see report.
      if (videoUrl) {
        playing = "content";
        player.controls(true);
        player.src(getSrc(settings.videoUrl));
      }
    }
  });

  // Show the title overlay while the user is active.
  player.on('useractive', function() {
    showTitleOverlay();
  });

  // Hide the title overlay when the user is idle.
  player.on('userinactive', function() {
    showTitleOverlay(false);
  });

  // Play-trailer button: switch to the trailer source (if needed) and play.
  playTrailerButton.on("click", function () {
    console.log("playTrailer()");

    if (playing != "trailer") {
      playing = "trailer";
      player.src(settings.trailerUrl);
    }
    player.play();
  });

  // Mute button: mute only while a trailer is playing.
  muteTrailerButton.on("click", function () {
    if (playing == "trailer") {
      console.log("muteTrailer()");
      player.muted(true);
    }
  });

  // Unmute button: unmute only while a trailer is playing.
  unmuteTrailerButton.on("click", function () {
    if (playing == "trailer") {
      console.log("unmuteTrailer()");
      player.muted(false);
    }
  });

  // Re-entrancy guard so overlapping gestures don't start playback twice.
  let wpstreamPlayVideoInFlight = false;
  /**
   * Start main-video playback on a user gesture. Switches from trailer to
   * content if needed, and for YouTube runs a short retry loop until the tech
   * is ready (falling back to the big play button if it never starts).
   */
  const wpstreamStartVideoPlayback = () => {
    // Bail if a start is already in progress; otherwise take the lock.
    if (wpstreamPlayVideoInFlight) return;
    wpstreamPlayVideoInFlight = true;

    console.log("playVideo");
    // No video configured → release the lock and stop.
    if (!settings.videoUrl) {
      wpstreamPlayVideoInFlight = false;
      return;
    }
    // Switch the source from trailer to main content if still on the trailer.
    if (playing == "trailer") {
      playing = "content";
      player.controls(true);
      player.muted(false);
      player.src(getSrc(settings.videoUrl));
    }

    // YouTube sources need special handling (async tech init + spinner).
    const isYouTube = getSrc(settings.videoUrl).type === "video/youtube";
  if (isYouTube) {
    wpstreamShowVodSpinner();
  }

		// For YouTube, the tech becomes ready asynchronously after src() changes.
		// Do a short retry loop so one user click is enough.
    // Retry budget and cadence.
    const maxWaitMs = 2000;
    const retryEveryMs = 50;
		const startedAt = Date.now();
		let retryTimer = null;

		// Stop the retry loop, release the lock, and hide the spinner.
		const clearRetry = () => {
			if (retryTimer) {
				clearInterval(retryTimer);
				retryTimer = null;
			}
      wpstreamPlayVideoInFlight = false;
      wpstreamHideVodSpinner();
		};

		// Hide big-play immediately; only show it if all retries fail.
		try {
			player.bigPlayButton && player.bigPlayButton.hide();
		} catch (e) {}

		// If YouTube and user expects audio, start muted for reliability, then restore.
		let shouldRestoreMute = false;
		if (isYouTube && !settings.muted) {
			try {
				// Force muted for a reliable autoplay start; restore audio on "playing".
				player.muted(true);
				shouldRestoreMute = true;
			} catch (e) {}
		}

		// Once real playback begins, stop retrying and restore audio if needed.
		player.one("playing", function () {
			clearRetry();
			if (shouldRestoreMute) {
				try {
					player.muted(false);
				} catch (e) {}
			}
		});

		// One play() attempt: skip if already unpaused; swallow the rejection.
		const attemptPlay = () => {
      // Note: with the YouTube tech, paused() may flip to false before
      // playback truly starts. Only stop retries on the 'playing' event.
			try {
				if (!player.paused()) {
					return;
				}
			} catch (e) {}

			const p = player.play();
			if (p && typeof p.catch === "function") {
				p.catch(function () {
					// swallow; we will retry for a short time
				});
			}
		};

		// First immediate attempt.
		attemptPlay();

		// Only needed for YouTube: keep trying briefly until tech + iframe are ready.
		if (isYouTube) {
			retryTimer = setInterval(function () {
				// Give up after the retry budget elapses.
				if (Date.now() - startedAt > maxWaitMs) {
					clearRetry();
					// Give the user a manual fallback if autoplay is blocked.
					try {
						player.bigPlayButton && player.bigPlayButton.show();
					} catch (e) {}
					return;
				}
				// Otherwise keep attempting on each tick.
				attemptPlay();
			}, retryEveryMs);
    } else {
      // Non-YouTube: release the lock immediately.
      wpstreamPlayVideoInFlight = false;
    }
  };

  // Start as early as possible on a real user gesture to reduce perceived delay.
  playVideoButton.on("pointerdown", function () {
    wpstreamStartVideoPlayback();
  });

  // Fallback for browsers without pointer events.
  playVideoButton.on("click", function () {
    wpstreamStartVideoPlayback();
  });

  // If the VOD is a YouTube URL, try to start it on page load.
  // Important: browsers generally only allow this if playback starts muted.
  if (settings.videoUrl && getSrc(settings.videoUrl).type === "video/youtube" && !settings.trailerUrl) {
    try {
      // Once the player is ready, trigger a click to kick off (muted) playback.
      player.ready(function () {
        setTimeout(function () {
          try { playVideoButton.trigger("click"); } catch (e) {}
        }, 0);
      });
    } catch (e) {}
  }
}

/**
 * Build a Video.js source descriptor, inferring the MIME type from the URL:
 * HLS (.m3u8), YouTube, or null (let Video.js auto-detect).
 *
 * @param {string} url Media URL.
 * @return {{src: string, type: (string|null)}} Source descriptor.
 */
function getSrc(url) {
  return {
    src: url,
    // .m3u8 → HLS; youtube/youtu.be → YouTube tech; otherwise unknown (null).
    type: url.endsWith(".m3u8") ? "application/x-mpegURL" : url.includes("www.youtube") || url.includes("youtu.be") ? "video/youtube" : null,
  };
}

/**
 * Detect a low-latency HLS playlist URL (e.g. "...llhls.m3u8").
 *
 * @param {string} url Playlist URL.
 * @return {boolean} True if the URL looks like an LL-HLS playlist.
 */
function isLlHls(url) {
  return /ll[a-z]+\.m3u8/.test(url);
}

/**
 * Hide every pre-load spinner on the page.
 *
 * @param {*} place Caller-supplied tag (for debugging only; unused here).
 */
function removeSpinner( place ) {
	// Find all spinner elements and hide each one.
	const playerSpinner = document.querySelectorAll('.wpstream-pre-load-spinner');
	playerSpinner.forEach(spinner => {
		spinner.style.display = 'none';
	})
}
