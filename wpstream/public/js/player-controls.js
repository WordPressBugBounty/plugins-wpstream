/*
 * WpStream player-controls host script.
 *
 * This file is the "host" side of the WpStream player. The actual video player
 * runs inside a cross-origin <iframe>; this script talks to it through the
 * postMessage protocol (host.harness -> player.command out, player.iframe ->
 * player.event in). It has two independent parts, each wrapped in its own IIFE:
 *
 *   (1) VOD iframe host: wires the title "Play Video" overlay and the trailer
 *       overlay/mute controls to postMessage commands, keeps the surrounding
 *       theme chrome (posters, action buttons, status message) in sync with the
 *       decorative "playing_content" / "playing_trailer" states, forwards i18n
 *       strings, and hydrates playback-session tokens when required.
 *   (2) Optional dev harness that only activates when a `#playerFrame` element
 *       with a src exists (a standalone controls panel / session tooling page):
 *       play/pause/mute buttons, playback-session request & invalidation, and a
 *       live state readout.
 */

// IIFE (1): the VOD iframe host wiring.
(function () {
	/**
	 * Post a player.command message to a player iframe.
	 *
	 * @param {HTMLIFrameElement} iframe  Target player iframe.
	 * @param {string} command            Command name (e.g. "play", "pause", "mute").
	 * @return {void}
	 */
	function sendPlayerCommand(iframe, command) {
		// Nothing to talk to if the iframe or its window is missing.
		if (!iframe || !iframe.contentWindow) {
			return;
		}
		try {
			// Derive the iframe's origin so postMessage is targeted, not wildcard.
			const origin = new URL(iframe.src).origin;
			// Send the structured command envelope the player listens for.
			iframe.contentWindow.postMessage(
				{
					source: "host.harness",
					type: "player.command",
					command,
				},
				origin
			);
		} catch (_err) {
			// Invalid src or cross-origin mismatch.
		}
	}

	/**
	 * Subscribe to player.event messages coming back from a player iframe.
	 *
	 * @param {HTMLIFrameElement} iframe  Iframe whose events to listen for.
	 * @param {Function} callback         Called as (eventName, details, rawData).
	 * @return {void}
	 */
	function receivePlayerEvent(iframe, callback) {
		// Need a live iframe and a real callback to do anything.
		if (!iframe || !iframe.contentWindow || typeof callback !== "function") {
			return;
		}
		// Resolve the expected origin up front; bail if the src is unparseable.
		let origin = "";
		try {
			origin = new URL(iframe.src).origin;
		} catch (_err) {
			return;
		}
		// Listen on the window for messages and filter down to this iframe's events.
		window.addEventListener("message", function (event) {
			// Reject messages from any other origin.
			if (event.origin !== origin) {
				return;
			}
			// Reject messages that did not come from this specific iframe window.
			if (event.source !== iframe.contentWindow) {
				return;
			}
			// Only accept object payloads; ignore primitives/null.
			const data =
				event.data && typeof event.data === "object" ? event.data : null;
			// The payload must be tagged as originating from the player iframe.
			if (!data || data.source !== "player.iframe") {
				return;
			}
			// The payload must be a player.event carrying a string event name.
			if (data.type !== "player.event" || typeof data.event !== "string") {
				return;
			}
			// Hand the event name, its details object, and the raw data to the caller.
			callback(data.event, data.details || {}, data);
		});
	}

	/**
	 * Locate the VOD content iframe, preferring one near a related DOM node.
	 *
	 * @param {Element|null} relatedNode Node used to scope the search (e.g. a clicked button).
	 * @return {HTMLIFrameElement|null} The best-matching VOD iframe, or null.
	 */
	function findVodIframe(relatedNode) {
		// Fast path: a harness/dev page exposes the iframe by id.
		const playerFrame = document.getElementById("playerFrame");
		if (playerFrame) {
			return playerFrame;
		}
		// Otherwise, try to find an iframe within the related node's enclosing section.
		if (relatedNode && relatedNode.closest) {
			const section = relatedNode.closest(
				".wpstream_featured_banner_vod, .wpstream-featured-player-wrapper, .woocommerce, .single-product, #page"
			);
			if (section) {
				// Look for a VOD iframe inside that section.
				const inside = section.querySelector(
					"iframe.wpstream_video_on_demand_iframe"
				);
				if (inside) {
					return inside;
				}
			}
		}
		// Last resort: first VOD iframe anywhere on the page.
		return document.querySelector("iframe.wpstream_video_on_demand_iframe");
	}

	/**
	 * Find the actions wrapper that holds the trailer mute/unmute buttons.
	 *
	 * @param {Element|null} el Node from which to search upward.
	 * @return {Element|null} The enclosing actions wrapper, or null.
	 */
	function findTrailerMuteUiRoot(el) {
		// Cannot search without a node that supports closest().
		if (!el || !el.closest) {
			return null;
		}
		// The mute/unmute buttons live inside this actions wrapper.
		return (
			el.closest(".wpstream_video_on_demand_actions_wrapper")
		);
	}

	/**
	 * Toggle which of the trailer mute / unmute buttons is visible.
	 *
	 * @param {Element|null} root       Actions wrapper containing both buttons.
	 * @param {boolean} trailerIsMuted  True to show "unmute", false to show "mute".
	 * @return {void}
	 */
	function applyTrailerMuteButtonsVisibility(root, trailerIsMuted) {
		// No wrapper means nothing to toggle.
		if (!root) {
			return;
		}
		// Grab the mute button (shown while audio is on).
		const muteEl = root.querySelector(
			".wpstream_video_on_demand_mute_trailer"
		);
		// Grab the unmute button (shown while audio is muted).
		const unmuteEl = root.querySelector(
			".wpstream_video_on_demand_unmute_trailer"
		);
		// Both buttons must exist for the toggle to make sense.
		if (!muteEl || !unmuteEl) {
			return;
		}
		if (trailerIsMuted) {
			// Muted: hide the mute button, show the unmute button.
			muteEl.style.display = "none";
			unmuteEl.style.display = "block";
		} else {
			// Unmuted: show the mute button, hide the unmute button.
			muteEl.style.display = "block";
			unmuteEl.style.display = "none";
		}
	}

	/**
	 * Pick the iframe that trailer commands should target.
	 *
	 * @param {{trailer: ?HTMLIFrameElement, content: ?HTMLIFrameElement}} frames Frame set.
	 * @return {HTMLIFrameElement|null} The trailer iframe, or the content iframe as fallback.
	 */
	function getTrailerCommandIframe(frames) {
		// Prefer the dedicated trailer frame; fall back to the content frame.
		return frames.trailer || frames.content;
	}

	/**
	 * Apply the host-configured default mute state to a trailer when it starts.
	 *
	 * @param {Element} triggerEl        Element whose actions wrapper holds the default.
	 * @param {HTMLIFrameElement} trailerIframe Trailer iframe to command.
	 * @return {void}
	 */
	function applyInitialTrailerMuteFromHost(triggerEl, trailerIframe) {
		// Find the actions wrapper that carries the default-muted flag.
		const wrap = findTrailerMuteUiRoot(triggerEl);
		// Read the data attribute; "1" means the trailer should start muted.
		const defaultMuted =
			wrap &&
			wrap.getAttribute("data-trailer-muted-default") === "1";
		// Command the trailer to match the configured default.
		sendPlayerCommand(
			trailerIframe,
			defaultMuted ? "mute" : "unmute"
		);
		// Sync the mute/unmute button visibility to that same state.
		applyTrailerMuteButtonsVisibility(wrap, defaultMuted);
	}

	/**
	 * Resolve the content + trailer iframe pair for a given context.
	 *
	 * @param {Element|null} relatedNode Node used to scope the DOM search.
	 * @return {{content: ?HTMLIFrameElement, trailer: ?HTMLIFrameElement}} Frame set.
	 */
	function findVodFrameSet(relatedNode) {
		// Fast path: harness/dev page exposes both frames by id.
		const contentById = document.getElementById("playerFrame");
		const trailerById = document.getElementById("playerFrameTrailer");
		if (contentById) {
			return {
				content: contentById,
				trailer: trailerById,
			};
		}
		// Otherwise locate the content iframe near the related node.
		const contentFrame = findVodIframe(relatedNode);
		// Without a content frame there is no usable set.
		if (!contentFrame) {
			return {
				content: null,
				trailer: null,
			};
		}
		// The trailer frame, if any, is a sibling inside the same iframe wrap.
		const wrap = contentFrame.closest(".wpstream_player_iframe_wrap") || contentFrame.parentElement;
		const trailerFrame = wrap
			? wrap.querySelector('iframe[data-wpstream-frame-role="trailer"]')
			: null;
		return {
			content: contentFrame,
			trailer: trailerFrame,
		};
	}

	/**
	 * Show or hide an iframe, keeping accessibility attributes in sync.
	 *
	 * @param {HTMLIFrameElement|null} iframe The iframe to toggle.
	 * @param {boolean} visible               True to show, false to hide.
	 * @return {void}
	 */
	function setFrameVisibility(iframe, visible) {
		// Nothing to toggle without an iframe.
		if (!iframe) {
			return;
		}
		// Toggle CSS display (empty string restores the default display).
		iframe.style.display = visible ? "" : "none";
		// Mirror the visibility to assistive technology.
		iframe.setAttribute("aria-hidden", visible ? "false" : "true");
		// Keep hidden frames out of the tab order.
		iframe.tabIndex = visible ? 0 : -1;
	}

	/**
	 * Find the title overlay element associated with a given iframe.
	 *
	 * @param {HTMLIFrameElement|null} iframe The player iframe.
	 * @return {Element|null} The matching title overlay, or null.
	 */
	function findTitleOverlayForFrame(iframe) {
		// No iframe means no scoped overlay to find.
		if (!iframe) {
			return null;
		}
		// Determine the iframe's wrapping container.
		const wrap =
			iframe.closest(".wpstream_player_iframe_wrap") || iframe.parentElement;
		// With no wrap, fall back to the first overlay on the page.
		if (!wrap) {
			return document.querySelector(".wpstream-video-title-overlay");
		}
		// Prefer an overlay nested inside the wrap.
		const insideWrap = wrap.querySelector(".wpstream-video-title-overlay");
		if (insideWrap) {
			return insideWrap;
		}
		// Otherwise check the element immediately before the wrap.
		const previous = wrap.previousElementSibling;
		if (
			previous &&
			previous.classList &&
			previous.classList.contains("wpstream-video-title-overlay")
		) {
			return previous;
		}
		// As a last resort, search within the broader player wrapper scope.
		const scope =
			wrap.closest(
				".wpstream_player_wrapper, .wpstream-featured-player-wrapper, .wpstream_featured_banner_vod"
			) || wrap.parentElement;
		return scope
			? scope.querySelector(".wpstream-video-title-overlay")
			: null;
	}

	/**
	 * Set the opacity of the title overlay tied to an iframe.
	 *
	 * @param {HTMLIFrameElement} iframe The player iframe.
	 * @param {number} opacity           Opacity value (0..1).
	 * @return {void}
	 */
	function setTitleOverlayOpacityForFrame(iframe, opacity) {
		// Resolve the overlay for this frame; bail if none.
		const overlay = findTitleOverlayForFrame(iframe);
		if (!overlay) {
			return;
		}
		// Apply the opacity as a string CSS value.
		overlay.style.opacity = String(opacity);
	}

	/**
	 * Find the featured-VOD banner/section enclosing a node.
	 *
	 * @param {Element|null} relatedNode Node to search upward from.
	 * @return {Element|null} The featured section, or null.
	 */
	function getFeaturedVodSection(relatedNode) {
		// Need a node that supports closest() to search.
		if (!relatedNode || !relatedNode.closest) {
			return null;
		}
		// Match either the featured banner or the featured player wrapper.
		return relatedNode.closest(
			".wpstream_featured_banner_vod, .wpstream-featured-player-wrapper"
		);
	}

	/**
	 * Get the shortcode player wrapper inside a featured section.
	 *
	 * @param {Element|null} section The featured section.
	 * @return {Element|null} The player wrapper, or null.
	 */
	function getFeaturedPlayerWrapper(section) {
		// No section means no wrapper.
		if (!section) {
			return null;
		}
		// The wrapper carries both the generic and shortcode-specific classes.
		return section.querySelector(
			".wpstream_player_wrapper.wpstream_player_shortcode"
		);
	}

	/**
	 * Make a hidden featured VOD player wrapper visible.
	 *
	 * @param {Element} relatedNode Node used to locate the featured player.
	 * @return {void}
	 */
	function revealFeaturedVodPlayer(relatedNode) {
		// Resolve the section, then the player wrapper within it.
		const section = getFeaturedVodSection(relatedNode);
		const wrapper = getFeaturedPlayerWrapper(section);
		// Nothing to reveal if the wrapper is absent.
		if (!wrapper) {
			return;
		}
		// Flip the wrapper to visible (it starts hidden until playback begins).
		wrapper.style.visibility = "visible";
	}

	/**
	 * Broadcast a playback-state change as a bubbling DOM CustomEvent.
	 *
	 * @param {Element} root  The element associated with the state change.
	 * @param {string} state  State name (e.g. "playing_content", "playing_trailer").
	 * @return {void}
	 */
	function dispatchPlaybackState(root, state) {
		try {
			// Dispatch a document-level event other scripts/themes can hook.
			document.dispatchEvent(
				new CustomEvent("wpstream:playback-state-change", {
					bubbles: true,
					detail: { state, root },
				})
			);
		} catch (_e) {}
	}

	/**
	 * Apply "content is playing" chrome to elements near the target.
	 *
	 * @param {Element} targetEl Node whose ancestors define the search scope.
	 * @return {void}
	 */
	function applyPlayingContentDecorNearby(targetEl) {
		// Requires jQuery and a target element.
		if (typeof jQuery === "undefined" || !targetEl) {
			return;
		}
		// Scope the search to the target's nearest 12 ancestors.
		const $t = jQuery(targetEl);
		const $scope = $t.parents().slice(0, 12);
		// Hide trailer-only chrome now that main content is playing.
		$scope.find(".wpstream_hide_on_trailer").hide();
		// Hide "hide on play" chrome (posters, play buttons).
		$scope.find(".wpstream_hide_on_play").hide();
		// Clear the has-trailer marker class from the player element.
		$scope.find(".vjs-wpstream").removeClass(
			"wpstream_theme_player_has_trailer"
		);
	}

	/**
	 * Apply "trailer is playing" chrome to elements near the target.
	 *
	 * @param {Element} targetEl Node whose ancestors define the search scope.
	 * @return {void}
	 */
	function applyPlayingTrailerDecorNearby(targetEl) {
		// Requires jQuery and a target element.
		if (typeof jQuery === "undefined" || !targetEl) {
			return;
		}
		// Scope the search to the target's nearest 12 ancestors.
		const $t = jQuery(targetEl);
		const $scope = $t.parents().slice(0, 12);
		// Hide trailer-only chrome while the trailer itself plays.
		$scope.find(".wpstream_hide_on_trailer").hide();
		// Show "hide on play" chrome (trailer still counts as pre-content).
		$scope.find(".wpstream_hide_on_play").show();
		// Mark the player element as currently showing a trailer.
		$scope.find(".vjs-wpstream").addClass("wpstream_theme_player_has_trailer");
	}

	/**
	 * Hide the VOD actions wrapper near the target element.
	 *
	 * @param {Element} targetEl Node whose ancestors define the search scope.
	 * @return {void}
	 */
	function hideActionsWrapperNearby(targetEl) {
		// Requires jQuery and a target element.
		if (typeof jQuery === "undefined" || !targetEl) {
			return;
		}
		// Scope the search to the target's nearest 12 ancestors.
		const $t = jQuery(targetEl);
		const $scope = $t.parents().slice(0, 12);
		// Hide the action buttons wrapper (play/trailer/mute controls).
		$scope.find(".wpstream_video_on_demand_actions_wrapper").hide();
	}

	/**
	 * Handle activation of the title "Play Video" overlay: start main content.
	 *
	 * @param {Element} button The clicked play-video overlay button.
	 * @return {void}
	 */
	function onTitleOverlayActivate(button) {
		// Resolve the content/trailer frames for this button's context.
		const frames = findVodFrameSet(button);
		// Without a content frame there is nothing to play.
		if (!frames.content) {
			return;
		}
		// Reveal the (initially hidden) featured player wrapper.
		revealFeaturedVodPlayer(button);
		// Stop and hide the trailer frame.
		sendPlayerCommand(frames.trailer, "pause");
		setFrameVisibility(frames.trailer, false);
		// Show the content frame and start playback.
		setFrameVisibility(frames.content, true);
		sendPlayerCommand(frames.content, "play");
		// Announce the state and apply matching chrome, then hide the actions.
		dispatchPlaybackState(button, "playing_content");
		applyPlayingContentDecorNearby(button);
		hideActionsWrapperNearby(button);
	}

	/**
	 * Wrap a function so it can only ever run once.
	 *
	 * @param {Function} fn The function to guard.
	 * @return {Function} A wrapper that invokes fn at most one time.
	 */
	function runOnce(fn) {
		// Latch that flips true after the first call.
		let done = false;
		return function () {
			// Ignore all calls after the first.
			if (done) {
				return;
			}
			done = true;
			fn();
		};
	}

	/**
	 * Invoke a callback once the trailer iframe is ready to receive messages.
	 *
	 * @param {HTMLIFrameElement} trailerIframe The trailer iframe.
	 * @param {Function} callback               Called once messaging is ready.
	 * @return {void}
	 */
	function whenTrailerIframeMessagingReady(trailerIframe, callback) {
		// Need both an iframe and a real callback.
		if (!trailerIframe || typeof callback !== "function") {
			return;
		}
		// Ensure the callback fires exactly once regardless of which timer wins.
		const fire = runOnce(callback);
		// On load, wait a short grace period for the player to wire its listeners.
		trailerIframe.addEventListener(
			"load",
			function () {
				window.setTimeout(fire, 280);
			},
			{ once: true }
		);
		// Safety net in case the load event was missed: fire after 900ms.
		window.setTimeout(fire, 900);
	}

	/**
	 * Repeatedly send "play" to the trailer to defeat autoplay races.
	 *
	 * @param {HTMLIFrameElement} trailerIframe The trailer iframe.
	 * @param {number} times                    Number of play attempts (default 4).
	 * @return {void}
	 */
	function reinforceTrailerPlay(trailerIframe, times) {
		// Default to 4 attempts when a count is not supplied.
		const n = typeof times === "number" ? times : 4;
		// Stagger the play commands 280ms apart to survive slow player init.
		for (let i = 0; i < n; i++) {
			window.setTimeout(function () {
				sendPlayerCommand(trailerIframe, "play");
			}, i * 280);
		}
	}

	/**
	 * Handle activation of the trailer overlay: start the trailer playback.
	 *
	 * @param {Element} triggerEl The element that triggered the trailer.
	 * @param {{programmaticAutostart?: boolean}} [options] Behaviour options.
	 * @return {void}
	 */
	function onTrailerOverlayActivate(triggerEl, options) {
		// Normalise options and resolve the frame set.
		const opts = options || {};
		const frames = findVodFrameSet(triggerEl);
		// No trailer frame means nothing to do.
		if (!frames.trailer) {
			return;
		}
		// Reveal the featured player wrapper.
		revealFeaturedVodPlayer(triggerEl);
		// Pause and hide the main content frame while the trailer plays.
		sendPlayerCommand(frames.content, "pause");
		setFrameVisibility(frames.content, false);
		// Show the trailer frame.
		setFrameVisibility(frames.trailer, true);

		// Local helper that actually issues the play/mute commands.
		function deliverTrailerCommands() {
			if (opts.programmaticAutostart) {
				// Autostart path: must start muted (browser autoplay policy).
				sendPlayerCommand(frames.trailer, "mute");
				sendPlayerCommand(frames.trailer, "play");
				// Reflect the muted state in the button visibility.
				applyTrailerMuteButtonsVisibility(
					findTrailerMuteUiRoot(triggerEl),
					true
				);
				// Reinforce play a few times to beat autoplay races.
				reinforceTrailerPlay(frames.trailer, 4);
			} else {
				// User-initiated path: play, then apply host default mute state.
				sendPlayerCommand(frames.trailer, "play");
				applyInitialTrailerMuteFromHost(triggerEl, frames.trailer);
				reinforceTrailerPlay(frames.trailer, 3);
			}
			// Announce the trailer state and apply matching chrome.
			dispatchPlaybackState(triggerEl, "playing_trailer");
			applyPlayingTrailerDecorNearby(triggerEl);
		}

		if (opts.programmaticAutostart) {
			// Autostart needs to wait until the trailer iframe can receive messages.
			whenTrailerIframeMessagingReady(frames.trailer, deliverTrailerCommands);
		} else {
			// User click implies the iframe is already interactive; deliver now.
			deliverTrailerCommands();
		}
	}

	/**
	 * Auto-start trailers for any action wrappers flagged for autoplay.
	 *
	 * @return {void}
	 */
	function initAutoplayTrailerFromHost() {
		// Find every actions wrapper opted into autoplay via data attribute.
		document
			.querySelectorAll(
				'.wpstream_video_on_demand_actions_wrapper[data-autoplay-trailer="1"]'
			)
			.forEach(function (wrap) {
				// Locate the trailer button within the wrapper.
				const trailerBtn = wrap.querySelector(
					".wpstream_video_on_demand_play_trailer"
				);
				// Skip wrappers that have no trailer button.
				if (!trailerBtn) {
					return;
				}
				// Kick off the trailer as a programmatic (muted) autostart.
				onTrailerOverlayActivate(trailerBtn, {
					programmaticAutostart: true,
				});
			});
	}

	/**
	 * Delegate click handlers for the play-video, trailer, and mute controls.
	 *
	 * @return {void}
	 */
	function initTitleOverlayPlay() {
		// Delegated handler: click on the "Play Video" overlay button.
		document.addEventListener(
			"click",
			function (e) {
				// Match only clicks landing on the play-video overlay button.
				const btn = e.target.closest(
					"button.wpstream_player_controls.wpstream_video_on_demand_play_video_wrapper"
				);
				if (!btn) {
					return;
				}
				// Suppress default behaviour and start the main content.
				e.preventDefault();
				onTitleOverlayActivate(btn);
			},
			false
		);

		// Delegated handler: click on the "Play Trailer" control.
		document.addEventListener(
			"click",
			function (e) {
				// Match only clicks landing on a play-trailer element.
				const trailerBtn = e.target.closest(
					".wpstream_video_on_demand_play_trailer"
				);
				if (!trailerBtn) {
					return;
				}
				// Fully stop propagation so no other overlay handler also fires.
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				// Start the trailer (user-initiated, not autostart).
				onTrailerOverlayActivate(trailerBtn);
			},
			false
		);

		// Delegated handler: click on the trailer mute / unmute controls.
		document.addEventListener(
			"click",
			function (e) {
				// First, test for a click on the mute button.
				const muteEl = e.target.closest(
					".wpstream_video_on_demand_mute_trailer"
				);
				if (muteEl) {
					// Prevent default/bubbling for this control click.
					e.preventDefault();
					e.stopPropagation();
					// Resolve the frame set and pick the trailer command target.
					const frames = findVodFrameSet(muteEl);
					const iframe = getTrailerCommandIframe(frames);
					// Mute the trailer and flip the button visibility to "muted".
					sendPlayerCommand(iframe, "mute");
					applyTrailerMuteButtonsVisibility(
						findTrailerMuteUiRoot(muteEl),
						true
					);
					return;
				}
				// Otherwise, test for a click on the unmute button.
				const unmuteEl = e.target.closest(
					".wpstream_video_on_demand_unmute_trailer"
				);
				if (!unmuteEl) {
					return;
				}
				// Prevent default/bubbling for this control click.
				e.preventDefault();
				e.stopPropagation();
				// Resolve the frame set and pick the trailer command target.
				const frames = findVodFrameSet(unmuteEl);
				const iframe = getTrailerCommandIframe(frames);
				// Unmute the trailer and flip the button visibility to "unmuted".
				sendPlayerCommand(iframe, "unmute");
				applyTrailerMuteButtonsVisibility(
					findTrailerMuteUiRoot(unmuteEl),
					false
				);
			},
			false
		);
	}

	/**
	 * Undo trailer-playing chrome when the trailer iframe reports `ended`.
	 *
	 * @param {HTMLIFrameElement} trailerIframe The trailer iframe that ended.
	 * @return {void}
	 */
	function restoreUiAfterTrailerEnded(trailerIframe) {
		// Nothing to restore without the trailer iframe.
		if (!trailerIframe) {
			return;
		}
		// Scope the restore to the trailer's enclosing row (or its parent).
		const scopeRoot =
			trailerIframe.closest(
				".row"
			) ||
			trailerIframe.parentElement;
		if (!scopeRoot) {
			return;
		}

		// Local helper: hide both mute and unmute trailer controls in a root.
		function hideTrailerMuteControls(root) {
			// Hide every mute button in scope.
			root
				.querySelectorAll(".wpstream_video_on_demand_mute_trailer")
				.forEach(function (el) {
					el.style.display = "none";
				});
			// Hide every unmute button in scope.
			root
				.querySelectorAll(".wpstream_video_on_demand_unmute_trailer")
				.forEach(function (el) {
					el.style.display = "none";
				});
		}

		// Trailer is over, so the mute controls are no longer relevant.
		hideTrailerMuteControls(scopeRoot);

		if (typeof jQuery !== "undefined") {
			// jQuery path: re-show the poster/trailer chrome and play-trailer button.
			const $root = jQuery(scopeRoot);
			$root.find(".wpstream_hide_on_trailer").show();
			$root.find(".wpstream_video_on_demand_play_trailer").show();
			// Also re-show trailer chrome that lives as siblings of the scope root.
			$root.siblings(".wpstream_hide_on_trailer").show();
		} else {
			// Vanilla fallback: clear inline display on the same chrome elements.
			scopeRoot
				.querySelectorAll(".wpstream_hide_on_trailer")
				.forEach(function (el) {
					el.style.removeProperty("display");
				});
			scopeRoot
				.querySelectorAll(".wpstream_video_on_demand_play_trailer")
				.forEach(function (el) {
					el.style.removeProperty("display");
				});
		}
	}

	/**
	 * Listen for the trailer iframe's `ended` event and restore the UI.
	 *
	 * Same message filtering as iframe_test.html, wired to findVodFrameSet(…).trailer.
	 *
	 * @return {void}
	 */
	function initTrailerIframeEndedFromHost() {
		// Resolve the trailer frame; require a real src to listen to.
		const trailerFrame = findVodFrameSet(null).trailer;
		if (!trailerFrame || !trailerFrame.src) {
			return;
		}
		// Subscribe and act only on the "ended" event.
		receivePlayerEvent(trailerFrame, function (eventName) {
			if (eventName !== "ended") {
				return;
			}
			// Trailer finished: put the surrounding chrome back.
			restoreUiAfterTrailerEnded(trailerFrame);
		});
	}

	/**
	 * Fade the title overlay in/out based on hover events from either frame.
	 *
	 * @return {void}
	 */
	function initIframeHoverOverlayFromHost() {
		// Resolve both frames and wire hover handling on each present one.
		const frames = findVodFrameSet(null);
		[frames.content, frames.trailer].forEach(function (iframe) {
			// Skip absent frames or ones without a src.
			if (!iframe || !iframe.src) {
				return;
			}
			receivePlayerEvent(iframe, function (eventName) {
				// Hover start: fully show the title overlay.
				if (eventName === "hover_start") {
					setTitleOverlayOpacityForFrame(iframe, 1);
					return;
				}
				// Hover end: fade the title overlay back out.
				if (eventName === "hover_end") {
					setTitleOverlayOpacityForFrame(iframe, 0);
				}
			});
		});
	}

	/**
	 * Wire live-stream events from the content iframe to host-side UI updates.
	 *
	 * @return {void}
	 */
	function initLiveUpdateEventsFromHost() {
		// Resolve frames; require a content frame with a src to subscribe.
		const frames = findVodFrameSet(null);
		if (!frames.content || !frames.content.src) {
			return;
		}
		// Route each incoming player event by name.
		receivePlayerEvent(frames.content, function (eventName, details) {
			switch( eventName ) {
				case "video_playing":
					// Live content actually started playing: reveal + show content frame.
					revealFeaturedVodPlayer(frames.content);
					setFrameVisibility(frames.content, true);
					setFrameVisibility(frames.trailer, false);
					// Apply the playing-content chrome and hide the action buttons.
					applyPlayingContentDecorNearby(frames.content);
					hideActionsWrapperNearby(frames.content);
					dispatchPlaybackState(frames.content, "playing_content");
					// this is added so that when the streaming is currently live, the status message get hidden
					changeStatusMessage("onair");
					break;
				case "live_update":
					// here we check for status update
					// Pull the status string out of the update payload, if present.
					const status =
						details && details.update
							? details.update.status
							: null;
					// Only forward non-empty string statuses to the UI.
					if (typeof status === "string" && status) {
						changeStatusMessage(status);
					}
					break;
				case "live_update_ended":
					// No host-side UI change on stream end (handled elsewhere).
					break;
				case "live_update_open":
					// Live update channel opened; nothing to do here.
					break;
				case "live_update_error":
					// Live update error; nothing to do here.
					break;
				case "live_update_closed":
					// Live update channel closed; nothing to do here.
					break;
				default:
					// Unknown event; ignore.
					break;
			}
		});
	}

	/**
	 * Update the live-channel status message/overlay for a given stream status.
	 *
	 * @param {string} status Live status ("stopped", "init", "startup", "onair", "paused").
	 * @return {void}
	 */
	function changeStatusMessage(status) {
		// Use the localized strings config if present, otherwise an empty object.
		const liveStrings =
			window.wpstreamLiveUiConfig &&
			typeof window.wpstreamLiveUiConfig === "object"
				? window.wpstreamLiveUiConfig
				: {};
		// Only the bundled theme renders this status UI; bail otherwise.
		if ( !liveStrings.isThemeActive ) {
			return;
		}
		// Locate the status container and its message element.
		const statusEl = document.querySelector('.wpstream_live_channel_actions_wrapper .wpstream_live_channel_status');
		const messageEl = document.querySelector('.wpstream_live_channel_actions_wrapper .wpstream_live_channel_status .wpstream_live_channel_status_message');
		// Both must exist to show a status.
		if ( !statusEl || !messageEl ) {
			return;
		}
		// Ensure the status container is visible before setting the message.
		statusEl.style.display = "block";
		switch ( status ) {
			case "stopped":
				// Not live: show the "not live" message and clear the init styling.
				messageEl.textContent =
					liveStrings.wpstream_player_state_stopped_msg ||
					'We are not live at this moment';
				messageEl.classList.remove('wpstream_player_state_init_class');
				break;
			case "init":
				// Stream created but not started: show the init message + styling.
				messageEl.textContent =
					liveStrings.wpstream_player_state_init_msg ||
					'The live stream has not yet started';
				messageEl.classList.add('wpstream_player_state_init_class');
				break;
			case "startup":
				// Stream is spinning up: show the "starting" message.
				messageEl.textContent =
					liveStrings.wpstream_player_state_startup_msg ||
					'The live stream is starting...';
				break;
			case "onair":
				// Live on air: hide posters/pre-play chrome across the featured wrapper.
				document.querySelectorAll('.wpstream-featured-player-wrapper .wpstream_hide_on_play, .wpstream-featured-player-wrapper .wpstream_video_poster_holder.wpstream_hide_on_trailer').forEach(function (el) {
					el.classList.add('hide_on_play');
				});
				// Hide the status message itself while live.
				messageEl.classList.add('hide_on_play');
				break;
			case "paused":
				// Paused: bring back the posters/pre-play chrome...
				document.querySelectorAll('.wpstream-featured-player-wrapper .wpstream_hide_on_play, .wpstream-featured-player-wrapper .wpstream_video_poster_holder.wpstream_hide_on_trailer').forEach(function (el) {
					el.classList.remove('hide_on_play');
				});
				// ...re-show the status message and set the "paused" text.
				messageEl.classList.remove('hide_on_play');
				messageEl.textContent =
					liveStrings.wpstream_player_state_paused_msg ||
					'The live stream is paused';
				break;
		}
	}

	/**
	 * Push the host's localized UI strings into a player iframe.
	 *
	 * @param {HTMLIFrameElement} iframe Target player iframe.
	 * @return {void}
	 */
	function sendI18nToFrame(iframe) {
		// Require a live iframe window and a strings config to send.
		if (!iframe || !iframe.contentWindow || !window.wpstreamLiveUiConfig) {
			return;
		}
		try {
			// Post a set_i18n command carrying the localized strings.
			const origin = new URL(iframe.src).origin;
			iframe.contentWindow.postMessage(
				{
					source: "host.harness",
					type: "player.command",
					command: "set_i18n",
					strings: window.wpstreamLiveUiConfig,
				},
				origin
			);
		} catch (_e) {}
	}

	/**
	 * Send i18n strings to an iframe on load, plus timed retries.
	 *
	 * @param {HTMLIFrameElement} iframe Target player iframe.
	 * @return {void}
	 */
	function wireI18nAfterLoad(iframe) {
		// Nothing to wire without an iframe.
		if (!iframe) {
			return;
		}
		// Closure that sends the strings to this iframe.
		const send = function () {
			sendI18nToFrame(iframe);
		};
		// Send once on load, then retry twice to cover late listener setup.
		iframe.addEventListener("load", send, { once: true });
		window.setTimeout(send, 400);
		window.setTimeout(send, 1200);
	}

	/**
	 * Forward i18n strings to every live-channel and VOD iframe on the page.
	 *
	 * @return {void}
	 */
	function initPlayerI18nFromHost() {
		// Wire i18n delivery for each relevant player iframe.
		document
			.querySelectorAll(
				"iframe.wpstream_live_channel_iframe, iframe.wpstream_video_on_demand_iframe"
			)
			.forEach(wireI18nAfterLoad);
	}

	/**
	 * Send a playback-session token into a player iframe.
	 *
	 * @param {HTMLIFrameElement} iframe   Target player iframe.
	 * @param {string} playbackSession     The playback-session token.
	 * @return {void}
	 */
	function sendPlaybackSessionToFrame(iframe, playbackSession) {
		// Require a live iframe window.
		if (!iframe || !iframe.contentWindow) {
			return;
		}
		// Normalise the token; do nothing if it is empty.
		const token = String(playbackSession || "").trim();
		if (!token) {
			return;
		}
		try {
			// Post a set_playback_session command carrying the token.
			const origin = new URL(iframe.src).origin;
			iframe.contentWindow.postMessage(
				{
					source: "host.harness",
					type: "player.command",
					command: "set_playback_session",
					playbackSession: token,
				},
				origin
			);
		} catch (_e) {}
	}

	/**
	 * Deliver a playback-session token to an iframe on load, plus timed retries.
	 *
	 * @param {HTMLIFrameElement} iframe   Target player iframe.
	 * @param {string} playbackSession     The playback-session token.
	 * @return {void}
	 */
	function wirePlaybackSessionAfterLoad(iframe, playbackSession) {
		// Nothing to wire without an iframe.
		if (!iframe) {
			return;
		}
		// Closure that sends the token to this iframe.
		const send = function () {
			sendPlaybackSessionToFrame(iframe, playbackSession);
		};
		// Send once on load, then retry twice to cover late listener setup.
		iframe.addEventListener("load", send, { once: true });
		window.setTimeout(send, 400);
		window.setTimeout(send, 1200);
	}

	/**
	 * When the server requires it, fetch a playback-session token and deliver
	 * it to the content and trailer iframes.
	 *
	 * @return {Promise<void>}
	 */
	async function hydrateVodPlaybackSessionsIfNeeded() {
		// Two possible config sources (VOD or live); prefer whichever requires a session.
		const cfgCandidates = [
			window.wpstreamVodIframeSessionApi,
			window.wpstreamLiveIframeSessionApi,
		];
		// Pick the first config object that flags requirePlaybackSession.
		const cfg =
			cfgCandidates.find(function (candidate) {
				return (
					candidate &&
					typeof candidate === "object" &&
					candidate.requirePlaybackSession
				);
			}) || null;
		// If no config requires a session, there is nothing to hydrate.
		if (!cfg || !cfg.requirePlaybackSession) {
			return;
		}
		// Build the AJAX request body for the issue-session action.
		const body = new URLSearchParams();
		body.set("action", "wpstream_issue_playback_session");
		body.set("nonce", cfg.nonce);
		body.set("productId", String(cfg.productId));

		// Will hold the resolved session token.
		let playbackSession = "";
		try {
			// POST to the WordPress AJAX endpoint with same-origin credentials.
			const res = await fetch(cfg.ajaxUrl, {
				method: "POST",
				credentials: "same-origin",
				headers: {
					"Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
				},
				body: body.toString(),
			});
			// Parse the JSON response.
			const payload = await res.json();
			if (payload && payload.success) {
				// Prefer the token nested under payload.data, else top-level.
				const fromData =
					payload.data &&
					typeof payload.data === "object" &&
					payload.data.playbackSession
						? payload.data.playbackSession
						: "";
				playbackSession =
					(typeof fromData === "string" && fromData) ||
					(typeof payload.playbackSession === "string"
						? payload.playbackSession
						: "");
			}
			// Normalise whatever token we ended up with.
			playbackSession = String(playbackSession || "").trim();
		} catch (_err) {
			// Network/parse failure: give up silently.
			return;
		}
		// No token means nothing to deliver.
		if (!playbackSession) {
			return;
		}

		// Deliver the token to the content frame...
		wirePlaybackSessionAfterLoad(
			document.getElementById("playerFrame"),
			playbackSession
		);
		// ...and to the trailer frame.
		wirePlaybackSessionAfterLoad(
			document.getElementById("playerFrameTrailer"),
			playbackSession
		);
	}

	/**
	 * Initialise all VOD host wiring in one place.
	 *
	 * @return {void}
	 */
	function initVodHost() {
		// Wire click handlers for play/trailer/mute controls.
		initTitleOverlayPlay();
		// Auto-start any trailers opted into autoplay.
		initAutoplayTrailerFromHost();
		// Restore chrome when a trailer ends.
		initTrailerIframeEndedFromHost();
		// Fade the title overlay on hover.
		initIframeHoverOverlayFromHost();
		// React to live-stream status events.
		initLiveUpdateEventsFromHost();
		// Push localized strings into the iframes.
		initPlayerI18nFromHost();
		// Fetch/deliver playback-session tokens when required.
		hydrateVodPlaybackSessionsIfNeeded();
	}

	// Initialise now, or defer until the DOM is ready if still loading.
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initVodHost);
	} else {
		initVodHost();
	}
})();

// IIFE (2): optional standalone dev/QA harness controls panel.
// Only activates when a #playerFrame with a src plus a #btnPlay button exist,
// i.e. the dedicated controls/session tooling page — never on normal pages.
(() => {
	// The harness targets a single player iframe referenced by id.
	const frame = document.getElementById("playerFrame");
	if (!frame || !frame.src) {
		return;
	}
	// The Play button is the minimum required control; without it, bail.
	const btnPlay = document.getElementById("btnPlay");
	if (!btnPlay) {
		return;
	}
	// Grab the remaining optional control buttons.
	const btnPause = document.getElementById("btnPause");
	const btnMute = document.getElementById("btnMute");
	const btnUnmute = document.getElementById("btnUnmute");
	const btnShowControls = document.getElementById("btnShowControls");
	const btnHideControls = document.getElementById("btnHideControls");
	const btnInvalidateSession = document.getElementById(
		"btnInvalidateSession"
	);
	// Read-out / diagnostic elements.
	const playerState = document.getElementById("playerState");
	const sessionStatus = document.getElementById("sessionStatus");
	const frameVars = document.getElementById("frameVars");
	const hostLabel = document.getElementById("hostLabel");
	// Origin of the target iframe, used for postMessage targeting.
	const frameOrigin = new URL(frame.src).origin;
	// Test flag (server-templated): when "true", deliberately corrupt the session token.
	const malformSession = "{{MALFORM_PLAYBACK_SESSION}}" === "true";
	// Parse the iframe URL to inspect its query parameters.
	const frameUrl = new URL(frame.src);
	// A non-empty validate-URL param means this frame expects a host-issued session.
	const validatePlaybackSessionUrl = String(
		frameUrl.searchParams.get("validatePlaybackSessionUrl") || ""
	).trim();
	const requiresHostPlaybackSession = validatePlaybackSessionUrl.length > 0;

	// Show the current host in the diagnostic label, if present.
	if (hostLabel) {
		hostLabel.textContent = window.location.host || "(unknown)";
	}

	// Render the frame's query params into the diagnostic vars panel.
	const vars = Array.from(frameUrl.searchParams.entries());
	if (frameVars) {
		if (vars.length === 0) {
			// No params: show a "(none)" placeholder.
			frameVars.innerHTML =
				'<span><span class="k">vars:</span> <span class="v">(none)</span></span>';
		} else {
			// Otherwise render each key/value pair as a labeled span.
			frameVars.innerHTML = vars
				.map(
					([k, v]) =>
						`<span><span class="k">${k}:</span> <span class="v">${v}</span></span>`
				)
				.join("");
		}
	}

	/**
	 * Update the diagnostic player-state read-out.
	 *
	 * @param {string} label State label to display.
	 * @return {void}
	 */
	function setState(label) {
		if (playerState) {
			playerState.textContent = label;
		}
	}

	// True once the iframe has fired its load event.
	let frameLoaded = false;
	// The session token actually sent to the frame (possibly malformed for tests).
	let issuedPlaybackSession = "";
	// The pristine token as fetched from the server.
	let fetchedPlaybackSession = "";

	/**
	 * Corrupt a session token (for testing rejection of bad tokens).
	 *
	 * @param {string} value Original token.
	 * @return {string} Token with its first character flipped (a<->b).
	 */
	function malformSessionToken(value) {
		// Normalise; an empty token cannot be malformed.
		const token = String(value || "").trim();
		if (!token) return token;
		// Flip the first character between "a" and "b" to invalidate it.
		const first = token[0] === "a" ? "b" : "a";
		return `${first}${token.slice(1)}`;
	}

	/**
	 * Post a command to the harness's player iframe.
	 *
	 * @param {string} command Command name.
	 * @param {object} [extra] Additional fields merged into the envelope.
	 * @return {void}
	 */
	function sendCommand(command, extra = {}) {
		// Require a live iframe window.
		if (!frame || !frame.contentWindow) return;
		// Send the command envelope plus any extra fields.
		frame.contentWindow.postMessage(
			{
				source: "host.harness",
				type: "player.command",
				command,
				...extra,
			},
			frameOrigin
		);
	}

	/**
	 * Subscribe to player.event messages from the harness iframe.
	 *
	 * @param {Function} callback Called as (eventName, details, rawData).
	 * @return {void}
	 */
	function receivePlayerEvent(callback) {
		// Require a real callback.
		if (typeof callback !== "function") return;
		window.addEventListener("message", (event) => {
			// Filter to the expected origin and this iframe's window.
			if (event.origin !== frameOrigin) return;
			if (event.source !== frame.contentWindow) return;
			// Only accept object payloads tagged from the player iframe.
			const data =
				event.data && typeof event.data === "object" ? event.data : null;
			if (!data || data.source !== "player.iframe") return;
			// Require a player.event with a string event name.
			if (data.type !== "player.event") return;
			if (typeof data.event !== "string") return;
			// Deliver the event to the caller.
			callback(data.event, data.details || {}, data);
		});
	}

	/**
	 * Send the issued playback session to the frame, if all preconditions hold.
	 *
	 * @return {void}
	 */
	function maybeSendPlaybackSession() {
		// Only relevant when the frame requires a host session.
		if (!requiresHostPlaybackSession) return;
		// The frame must have finished loading.
		if (!frameLoaded) return;
		// We must actually have a token to send.
		if (!issuedPlaybackSession) return;
		// Deliver the token as a set_playback_session command.
		sendCommand("set_playback_session", {
			playbackSession: issuedPlaybackSession,
		});
	}

	/**
	 * Request a playback-session token from the local test endpoint and,
	 * if configured, malform it before issuing.
	 *
	 * @return {Promise<void>}
	 */
	async function requestPlaybackSession() {
		try {
			// Ask the harness endpoint for a fresh session token.
			const response = await fetch("/getPlaybackSession", {
				cache: "no-store",
			});
			const payload = await response.json();
			// Extract a trimmed token only on a successful string response.
			const playbackSession =
				payload &&
				payload.success &&
				typeof payload.playbackSession === "string"
					? payload.playbackSession.trim()
					: "";
			// No token: mark the status read-out as unavailable and stop.
			if (!playbackSession) {
				if (sessionStatus) {
					sessionStatus.className = "session error";
					sessionStatus.textContent = "session: unavailable";
				}
				return;
			}
			// Keep the pristine token; derive the issued token (malformed for tests).
			fetchedPlaybackSession = playbackSession;
			issuedPlaybackSession = malformSession
				? malformSessionToken(playbackSession)
				: playbackSession;
			// Reflect the issued token (and malformed flag) in the status read-out.
			if (sessionStatus) {
				sessionStatus.className = "session";
				sessionStatus.textContent = malformSession
					? `session: ${issuedPlaybackSession} (malformed)`
					: `session: ${playbackSession}`;
			}
			// Try to deliver the token to the frame now that we have one.
			maybeSendPlaybackSession();
		} catch (_err) {
			// Request failed: surface the error in the status read-out.
			if (sessionStatus) {
				sessionStatus.className = "session error";
				sessionStatus.textContent = "session: request failed";
			}
		}
	}

	/**
	 * Invalidate the current playback-session token via the test endpoint.
	 *
	 * @return {Promise<void>}
	 */
	async function invalidatePlaybackSession() {
		// Prefer the pristine token, fall back to the issued one; require something.
		const token = (fetchedPlaybackSession || issuedPlaybackSession || "").trim();
		if (!token) {
			// Nothing to invalidate: report and stop.
			if (sessionStatus) {
				sessionStatus.className = "session error";
				sessionStatus.textContent = "session: nothing to invalidate";
			}
			return;
		}

		try {
			// POST the token to the invalidate endpoint.
			const response = await fetch("/invalidatePlaybackSession", {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				cache: "no-store",
				body: JSON.stringify({ playbackSession: token }),
			});
			const payload = await response.json();
			// Read the success flag and whether an existing session was invalidated.
			const ok = Boolean(payload && payload.success);
			const invalidated = Boolean(payload && payload.invalidated);
			// A non-success response is treated as a failure.
			if (!ok) {
				if (sessionStatus) {
					sessionStatus.className = "session error";
					sessionStatus.textContent = "session: invalidate failed";
				}
				return;
			}
			// Report whether the token was actually invalidated or simply not found.
			if (sessionStatus) {
				sessionStatus.className = invalidated ? "session error" : "session pending";
				sessionStatus.textContent = invalidated
					? `session: invalidated ${token}`
					: `session: not found ${token}`;
			}
		} catch (_err) {
			// Request failed: surface the error in the status read-out.
			if (sessionStatus) {
				sessionStatus.className = "session error";
				sessionStatus.textContent = "session: invalidate request failed";
			}
		}
	}

	// When the iframe loads, flag it and (re)try delivering the session.
	frame.addEventListener("load", () => {
		frameLoaded = true;
		maybeSendPlaybackSession();
	});

	// Wire each present control button to its player command.
	if (btnPlay)
		btnPlay.addEventListener("click", () => sendCommand("play"));
	if (btnPause)
		btnPause.addEventListener("click", () => sendCommand("pause"));
	if (btnMute)
		btnMute.addEventListener("click", () => sendCommand("mute"));
	if (btnUnmute)
		btnUnmute.addEventListener("click", () => sendCommand("unmute"));
	if (btnShowControls)
		btnShowControls.addEventListener("click", () =>
			sendCommand("show_controls")
		);
	if (btnHideControls)
		btnHideControls.addEventListener("click", () =>
			sendCommand("hide_controls")
		);
	// Invalidate button runs the async invalidation, swallowing rejections.
	if (btnInvalidateSession) {
		btnInvalidateSession.addEventListener("click", () => {
			invalidatePlaybackSession().catch(() => {});
		});
	}

	if (requiresHostPlaybackSession) {
		// Frame needs a host session: request one now.
		requestPlaybackSession();
	} else {
		// No session required: hide the session-related UI.
		if (btnInvalidateSession) btnInvalidateSession.hidden = true;
		if (sessionStatus) sessionStatus.hidden = true;
	}

	// Mirror every incoming player event into the state read-out.
	receivePlayerEvent((eventName) => {
		setState(eventName);
	});
})();
