/**
 * (1) VOD iframe host: title "Play Video" overlay → postMessage(play), decorative state aligned with wpstream-player `playing_content`.
 * (2) Optional dev harness when `#playerFrame` exists with a src (controls panel / session tooling).
 */

(function () {
	function sendPlayerCommand(iframe, command) {
		if (!iframe || !iframe.contentWindow) {
			return;
		}
		try {
			const origin = new URL(iframe.src).origin;
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

	function receivePlayerEvent(iframe, callback) {
		if (!iframe || !iframe.contentWindow || typeof callback !== "function") {
			return;
		}
		let origin = "";
		try {
			origin = new URL(iframe.src).origin;
		} catch (_err) {
			return;
		}
		window.addEventListener("message", function (event) {
			if (event.origin !== origin) {
				return;
			}
			if (event.source !== iframe.contentWindow) {
				return;
			}
			const data =
				event.data && typeof event.data === "object" ? event.data : null;
			if (!data || data.source !== "player.iframe") {
				return;
			}
			if (data.type !== "player.event" || typeof data.event !== "string") {
				return;
			}
			callback(data.event, data.details || {}, data);
		});
	}

	function findVodIframe(relatedNode) {
		const playerFrame = document.getElementById("playerFrame");
		if (playerFrame) {
			return playerFrame;
		}
		if (relatedNode && relatedNode.closest) {
			const section = relatedNode.closest(
				".wpstream_featured_banner_vod, .wpstream-featured-player-wrapper, .woocommerce, .single-product, #page"
			);
			if (section) {
				const inside = section.querySelector(
					"iframe.wpstream_video_on_demand_iframe"
				);
				if (inside) {
					return inside;
				}
			}
		}
		return document.querySelector("iframe.wpstream_video_on_demand_iframe");
	}

	function findTrailerMuteUiRoot(el) {
		if (!el || !el.closest) {
			return null;
		}
		return (
			el.closest(".wpstream_video_on_demand_actions_wrapper")
		);
	}

	function applyTrailerMuteButtonsVisibility(root, trailerIsMuted) {
		if (!root) {
			return;
		}
		const muteEl = root.querySelector(
			".wpstream_video_on_demand_mute_trailer"
		);
		const unmuteEl = root.querySelector(
			".wpstream_video_on_demand_unmute_trailer"
		);
		if (!muteEl || !unmuteEl) {
			return;
		}
		if (trailerIsMuted) {
			muteEl.style.display = "none";
			unmuteEl.style.display = "block";
		} else {
			muteEl.style.display = "block";
			unmuteEl.style.display = "none";
		}
	}

	function getTrailerCommandIframe(frames) {
		return frames.trailer || frames.content;
	}

	function applyInitialTrailerMuteFromHost(triggerEl, trailerIframe) {
		const wrap = findTrailerMuteUiRoot(triggerEl);
		const defaultMuted =
			wrap &&
			wrap.getAttribute("data-trailer-muted-default") === "1";
		sendPlayerCommand(
			trailerIframe,
			defaultMuted ? "mute" : "unmute"
		);
		applyTrailerMuteButtonsVisibility(wrap, defaultMuted);
	}

	function findVodFrameSet(relatedNode) {
		const contentById = document.getElementById("playerFrame");
		const trailerById = document.getElementById("playerFrameTrailer");
		if (contentById) {
			return {
				content: contentById,
				trailer: trailerById,
			};
		}
		const contentFrame = findVodIframe(relatedNode);
		if (!contentFrame) {
			return {
				content: null,
				trailer: null,
			};
		}
		const wrap = contentFrame.closest(".wpstream_player_iframe_wrap") || contentFrame.parentElement;
		const trailerFrame = wrap
			? wrap.querySelector('iframe[data-wpstream-frame-role="trailer"]')
			: null;
		return {
			content: contentFrame,
			trailer: trailerFrame,
		};
	}

	function setFrameVisibility(iframe, visible) {
		if (!iframe) {
			return;
		}
		iframe.style.display = visible ? "" : "none";
		iframe.setAttribute("aria-hidden", visible ? "false" : "true");
		iframe.tabIndex = visible ? 0 : -1;
	}

	function findTitleOverlayForFrame(iframe) {
		if (!iframe) {
			return null;
		}
		const wrap =
			iframe.closest(".wpstream_player_iframe_wrap") || iframe.parentElement;
		if (!wrap) {
			return document.querySelector(".wpstream-video-title-overlay");
		}
		const insideWrap = wrap.querySelector(".wpstream-video-title-overlay");
		if (insideWrap) {
			return insideWrap;
		}
		const previous = wrap.previousElementSibling;
		if (
			previous &&
			previous.classList &&
			previous.classList.contains("wpstream-video-title-overlay")
		) {
			return previous;
		}
		const scope =
			wrap.closest(
				".wpstream_player_wrapper, .wpstream-featured-player-wrapper, .wpstream_featured_banner_vod"
			) || wrap.parentElement;
		return scope
			? scope.querySelector(".wpstream-video-title-overlay")
			: null;
	}

	function setTitleOverlayOpacityForFrame(iframe, opacity) {
		const overlay = findTitleOverlayForFrame(iframe);
		if (!overlay) {
			return;
		}
		overlay.style.opacity = String(opacity);
	}

	function getFeaturedVodSection(relatedNode) {
		if (!relatedNode || !relatedNode.closest) {
			return null;
		}
		return relatedNode.closest(
			".wpstream_featured_banner_vod, .wpstream-featured-player-wrapper"
		);
	}

	function getFeaturedPlayerWrapper(section) {
		if (!section) {
			return null;
		}
		return section.querySelector(
			".wpstream_player_wrapper.wpstream_player_shortcode"
		);
	}

	function revealFeaturedVodPlayer(relatedNode) {
		const section = getFeaturedVodSection(relatedNode);
		const wrapper = getFeaturedPlayerWrapper(section);
		if (!wrapper) {
			return;
		}
		wrapper.style.visibility = "visible";
	}

	function dispatchPlaybackState(root, state) {
		try {
			document.dispatchEvent(
				new CustomEvent("wpstream:playback-state-change", {
					bubbles: true,
					detail: { state, root },
				})
			);
		} catch (_e) {}
	}

	function applyPlayingContentDecorNearby(targetEl) {
		if (typeof jQuery === "undefined" || !targetEl) {
			return;
		}
		const $t = jQuery(targetEl);
		const $scope = $t.parents().slice(0, 12);
		$scope.find(".wpstream_hide_on_trailer").hide();
		$scope.find(".wpstream_hide_on_play").hide();
		$scope.find(".vjs-wpstream").removeClass(
			"wpstream_theme_player_has_trailer"
		);
	}

	function applyPlayingTrailerDecorNearby(targetEl) {
		if (typeof jQuery === "undefined" || !targetEl) {
			return;
		}
		const $t = jQuery(targetEl);
		const $scope = $t.parents().slice(0, 12);
		$scope.find(".wpstream_hide_on_trailer").hide();
		$scope.find(".wpstream_hide_on_play").show();
		$scope.find(".vjs-wpstream").addClass("wpstream_theme_player_has_trailer");
	}

	function hideActionsWrapperNearby(targetEl) {
		if (typeof jQuery === "undefined" || !targetEl) {
			return;
		}
		const $t = jQuery(targetEl);
		const $scope = $t.parents().slice(0, 12);
		$scope.find(".wpstream_video_on_demand_actions_wrapper").hide();
	}

	function onTitleOverlayActivate(button) {
		const frames = findVodFrameSet(button);
		if (!frames.content) {
			return;
		}
		revealFeaturedVodPlayer(button);
		sendPlayerCommand(frames.trailer, "pause");
		setFrameVisibility(frames.trailer, false);
		setFrameVisibility(frames.content, true);
		sendPlayerCommand(frames.content, "play");
		dispatchPlaybackState(button, "playing_content");
		applyPlayingContentDecorNearby(button);
		hideActionsWrapperNearby(button);
	}

	function runOnce(fn) {
		let done = false;
		return function () {
			if (done) {
				return;
			}
			done = true;
			fn();
		};
	}

	function whenTrailerIframeMessagingReady(trailerIframe, callback) {
		if (!trailerIframe || typeof callback !== "function") {
			return;
		}
		const fire = runOnce(callback);
		trailerIframe.addEventListener(
			"load",
			function () {
				window.setTimeout(fire, 280);
			},
			{ once: true }
		);
		window.setTimeout(fire, 900);
	}

	function reinforceTrailerPlay(trailerIframe, times) {
		const n = typeof times === "number" ? times : 4;
		for (let i = 0; i < n; i++) {
			window.setTimeout(function () {
				sendPlayerCommand(trailerIframe, "play");
			}, i * 280);
		}
	}

	function onTrailerOverlayActivate(triggerEl, options) {
		const opts = options || {};
		const frames = findVodFrameSet(triggerEl);
		if (!frames.trailer) {
			return;
		}
		revealFeaturedVodPlayer(triggerEl);
		sendPlayerCommand(frames.content, "pause");
		setFrameVisibility(frames.content, false);
		setFrameVisibility(frames.trailer, true);

		function deliverTrailerCommands() {
			if (opts.programmaticAutostart) {
				sendPlayerCommand(frames.trailer, "mute");
				sendPlayerCommand(frames.trailer, "play");
				applyTrailerMuteButtonsVisibility(
					findTrailerMuteUiRoot(triggerEl),
					true
				);
				reinforceTrailerPlay(frames.trailer, 4);
			} else {
				sendPlayerCommand(frames.trailer, "play");
				applyInitialTrailerMuteFromHost(triggerEl, frames.trailer);
				reinforceTrailerPlay(frames.trailer, 3);
			}
			dispatchPlaybackState(triggerEl, "playing_trailer");
			applyPlayingTrailerDecorNearby(triggerEl);
		}

		if (opts.programmaticAutostart) {
			whenTrailerIframeMessagingReady(frames.trailer, deliverTrailerCommands);
		} else {
			deliverTrailerCommands();
		}
	}

	function initAutoplayTrailerFromHost() {
		document
			.querySelectorAll(
				'.wpstream_video_on_demand_actions_wrapper[data-autoplay-trailer="1"]'
			)
			.forEach(function (wrap) {
				const trailerBtn = wrap.querySelector(
					".wpstream_video_on_demand_play_trailer"
				);
				if (!trailerBtn) {
					return;
				}
				onTrailerOverlayActivate(trailerBtn, {
					programmaticAutostart: true,
				});
			});
	}

	function initTitleOverlayPlay() {
		document.addEventListener(
			"click",
			function (e) {
				const btn = e.target.closest(
					"button.wpstream_player_controls.wpstream_video_on_demand_play_video_wrapper"
				);
				if (!btn) {
					return;
				}
				e.preventDefault();
				onTitleOverlayActivate(btn);
			},
			false
		);

		document.addEventListener(
			"click",
			function (e) {
				const trailerBtn = e.target.closest(
					".wpstream_video_on_demand_play_trailer"
				);
				if (!trailerBtn) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				onTrailerOverlayActivate(trailerBtn);
			},
			false
		);

		document.addEventListener(
			"click",
			function (e) {
				const muteEl = e.target.closest(
					".wpstream_video_on_demand_mute_trailer"
				);
				if (muteEl) {
					e.preventDefault();
					e.stopPropagation();
					const frames = findVodFrameSet(muteEl);
					const iframe = getTrailerCommandIframe(frames);
					sendPlayerCommand(iframe, "mute");
					applyTrailerMuteButtonsVisibility(
						findTrailerMuteUiRoot(muteEl),
						true
					);
					return;
				}
				const unmuteEl = e.target.closest(
					".wpstream_video_on_demand_unmute_trailer"
				);
				if (!unmuteEl) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				const frames = findVodFrameSet(unmuteEl);
				const iframe = getTrailerCommandIframe(frames);
				sendPlayerCommand(iframe, "unmute");
				applyTrailerMuteButtonsVisibility(
					findTrailerMuteUiRoot(unmuteEl),
					false
				);
			},
			false
		);
	}

	/** Undo trailer-playing chrome when the trailer iframe reports `ended`. */
	function restoreUiAfterTrailerEnded(trailerIframe) {
		if (!trailerIframe) {
			return;
		}
		const scopeRoot =
			trailerIframe.closest(
				".row"
			) ||
			trailerIframe.parentElement;
		if (!scopeRoot) {
			return;
		}

		function hideTrailerMuteControls(root) {
			root
				.querySelectorAll(".wpstream_video_on_demand_mute_trailer")
				.forEach(function (el) {
					el.style.display = "none";
				});
			root
				.querySelectorAll(".wpstream_video_on_demand_unmute_trailer")
				.forEach(function (el) {
					el.style.display = "none";
				});
		}

		hideTrailerMuteControls(scopeRoot);

		if (typeof jQuery !== "undefined") {
			const $root = jQuery(scopeRoot);
			$root.find(".wpstream_hide_on_trailer").show();
			$root.find(".wpstream_video_on_demand_play_trailer").show();
			$root.siblings(".wpstream_hide_on_trailer").show();
		} else {
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
	 * Same message filtering as iframe_test.html, wired to findVodFrameSet(…).trailer.
	 */
	function initTrailerIframeEndedFromHost() {
		const trailerFrame = findVodFrameSet(null).trailer;
		if (!trailerFrame || !trailerFrame.src) {
			return;
		}
		receivePlayerEvent(trailerFrame, function (eventName) {
			if (eventName !== "ended") {
				return;
			}
			restoreUiAfterTrailerEnded(trailerFrame);
		});
	}

	function initIframeHoverOverlayFromHost() {
		const frames = findVodFrameSet(null);
		[frames.content, frames.trailer].forEach(function (iframe) {
			if (!iframe || !iframe.src) {
				return;
			}
			receivePlayerEvent(iframe, function (eventName) {
				if (eventName === "hover_start") {
					setTitleOverlayOpacityForFrame(iframe, 1);
					return;
				}
				if (eventName === "hover_end") {
					setTitleOverlayOpacityForFrame(iframe, 0);
				}
			});
		});
	}

	function initLiveUpdateEventsFromHost() {
		const frames = findVodFrameSet(null);
		if (!frames.content || !frames.content.src) {
			return;
		}
		receivePlayerEvent(frames.content, function (eventName, details) {
			switch( eventName ) {
				case "video_playing":
					revealFeaturedVodPlayer(frames.content);
					setFrameVisibility(frames.content, true);
					setFrameVisibility(frames.trailer, false);
					applyPlayingContentDecorNearby(frames.content);
					hideActionsWrapperNearby(frames.content);
					dispatchPlaybackState(frames.content, "playing_content");
					// this is added so that when the streaming is currently live, the status message get hidden
					changeStatusMessage("onair");
					break;
				case "live_update":
					// here we check for status update
					const status =
						details && details.update
							? details.update.status
							: null;
					if (typeof status === "string" && status) {
						changeStatusMessage(status);
					}
					break;
				case "live_update_ended":
					break;
				case "live_update_open":
					break;
				case "live_update_error":
					break;
				case "live_update_closed":
					break;
				default:
					break;
			}
		});
	}

	function changeStatusMessage(status) {
		const liveStrings =
			window.wpstreamLiveUiConfig &&
			typeof window.wpstreamLiveUiConfig === "object"
				? window.wpstreamLiveUiConfig
				: {};
		if ( !liveStrings.isThemeActive ) {
			return;
		}
		const statusEl = document.querySelector('.wpstream_live_channel_actions_wrapper .wpstream_live_channel_status');
		const messageEl = document.querySelector('.wpstream_live_channel_actions_wrapper .wpstream_live_channel_status .wpstream_live_channel_status_message');
		if ( !statusEl || !messageEl ) {
			return;
		}
		statusEl.style.display = "block";
		switch ( status ) {
			case "stopped":
				messageEl.textContent =
					liveStrings.wpstream_player_state_stopped_msg ||
					'We are not live at this moment';
				messageEl.classList.remove('wpstream_player_state_init_class');
				break;
			case "init":
				messageEl.textContent =
					liveStrings.wpstream_player_state_init_msg ||
					'The live stream has not yet started';
				messageEl.classList.add('wpstream_player_state_init_class');
				break;
			case "startup":
				messageEl.textContent =
					liveStrings.wpstream_player_state_startup_msg ||
					'The live stream is starting...';
				break;
			case "onair":
				document.querySelectorAll('.wpstream-featured-player-wrapper .wpstream_hide_on_play, .wpstream-featured-player-wrapper .wpstream_video_poster_holder.wpstream_hide_on_trailer').forEach(function (el) {
					el.classList.add('hide_on_play');
				});
				messageEl.classList.add('hide_on_play');
				break;
			case "paused":
				document.querySelectorAll('.wpstream-featured-player-wrapper .wpstream_hide_on_play, .wpstream-featured-player-wrapper .wpstream_video_poster_holder.wpstream_hide_on_trailer').forEach(function (el) {
					el.classList.remove('hide_on_play');
				});
				messageEl.classList.remove('hide_on_play');
				messageEl.textContent =
					liveStrings.wpstream_player_state_paused_msg ||
					'The live stream is paused';
				break;
		}
	}

	function sendI18nToFrame(iframe) {
		if (!iframe || !iframe.contentWindow || !window.wpstreamLiveUiConfig) {
			return;
		}
		try {
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

	function wireI18nAfterLoad(iframe) {
		if (!iframe) {
			return;
		}
		const send = function () {
			sendI18nToFrame(iframe);
		};
		iframe.addEventListener("load", send, { once: true });
		window.setTimeout(send, 400);
		window.setTimeout(send, 1200);
	}

	function initPlayerI18nFromHost() {
		document
			.querySelectorAll(
				"iframe.wpstream_live_channel_iframe, iframe.wpstream_video_on_demand_iframe"
			)
			.forEach(wireI18nAfterLoad);
	}

	function sendPlaybackSessionToFrame(iframe, playbackSession) {
		if (!iframe || !iframe.contentWindow) {
			return;
		}
		const token = String(playbackSession || "").trim();
		if (!token) {
			return;
		}
		try {
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

	function wirePlaybackSessionAfterLoad(iframe, playbackSession) {
		if (!iframe) {
			return;
		}
		const send = function () {
			sendPlaybackSessionToFrame(iframe, playbackSession);
		};
		iframe.addEventListener("load", send, { once: true });
		window.setTimeout(send, 400);
		window.setTimeout(send, 1200);
	}

	async function hydrateVodPlaybackSessionsIfNeeded() {
		const cfgCandidates = [
			window.wpstreamVodIframeSessionApi,
			window.wpstreamLiveIframeSessionApi,
		];
		const cfg =
			cfgCandidates.find(function (candidate) {
				return (
					candidate &&
					typeof candidate === "object" &&
					candidate.requirePlaybackSession
				);
			}) || null;
		if (!cfg || !cfg.requirePlaybackSession) {
			return;
		}
		const body = new URLSearchParams();
		body.set("action", "wpstream_issue_playback_session");
		body.set("nonce", cfg.nonce);
		body.set("productId", String(cfg.productId));

		let playbackSession = "";
		try {
			const res = await fetch(cfg.ajaxUrl, {
				method: "POST",
				credentials: "same-origin",
				headers: {
					"Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
				},
				body: body.toString(),
			});
			const payload = await res.json();
			if (payload && payload.success) {
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
			playbackSession = String(playbackSession || "").trim();
		} catch (_err) {
			return;
		}
		if (!playbackSession) {
			return;
		}

		wirePlaybackSessionAfterLoad(
			document.getElementById("playerFrame"),
			playbackSession
		);
		wirePlaybackSessionAfterLoad(
			document.getElementById("playerFrameTrailer"),
			playbackSession
		);
	}

	function initVodHost() {
		initTitleOverlayPlay();
		initAutoplayTrailerFromHost();
		initTrailerIframeEndedFromHost();
		initIframeHoverOverlayFromHost();
		initLiveUpdateEventsFromHost();
		initPlayerI18nFromHost();
		hydrateVodPlaybackSessionsIfNeeded();
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initVodHost);
	} else {
		initVodHost();
	}
})();

(() => {
	const frame = document.getElementById("playerFrame");
	if (!frame || !frame.src) {
		return;
	}
	const btnPlay = document.getElementById("btnPlay");
	if (!btnPlay) {
		return;
	}
	const btnPause = document.getElementById("btnPause");
	const btnMute = document.getElementById("btnMute");
	const btnUnmute = document.getElementById("btnUnmute");
	const btnShowControls = document.getElementById("btnShowControls");
	const btnHideControls = document.getElementById("btnHideControls");
	const btnInvalidateSession = document.getElementById(
		"btnInvalidateSession"
	);
	const playerState = document.getElementById("playerState");
	const sessionStatus = document.getElementById("sessionStatus");
	const frameVars = document.getElementById("frameVars");
	const hostLabel = document.getElementById("hostLabel");
	const frameOrigin = new URL(frame.src).origin;
	const malformSession = "{{MALFORM_PLAYBACK_SESSION}}" === "true";
	const frameUrl = new URL(frame.src);
	const validatePlaybackSessionUrl = String(
		frameUrl.searchParams.get("validatePlaybackSessionUrl") || ""
	).trim();
	const requiresHostPlaybackSession = validatePlaybackSessionUrl.length > 0;

	if (hostLabel) {
		hostLabel.textContent = window.location.host || "(unknown)";
	}

	const vars = Array.from(frameUrl.searchParams.entries());
	if (frameVars) {
		if (vars.length === 0) {
			frameVars.innerHTML =
				'<span><span class="k">vars:</span> <span class="v">(none)</span></span>';
		} else {
			frameVars.innerHTML = vars
				.map(
					([k, v]) =>
						`<span><span class="k">${k}:</span> <span class="v">${v}</span></span>`
				)
				.join("");
		}
	}

	function setState(label) {
		if (playerState) {
			playerState.textContent = label;
		}
	}

	let frameLoaded = false;
	let issuedPlaybackSession = "";
	let fetchedPlaybackSession = "";

	function malformSessionToken(value) {
		const token = String(value || "").trim();
		if (!token) return token;
		const first = token[0] === "a" ? "b" : "a";
		return `${first}${token.slice(1)}`;
	}

	function sendCommand(command, extra = {}) {
		if (!frame || !frame.contentWindow) return;
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

	function receivePlayerEvent(callback) {
		if (typeof callback !== "function") return;
		window.addEventListener("message", (event) => {
			if (event.origin !== frameOrigin) return;
			if (event.source !== frame.contentWindow) return;
			const data =
				event.data && typeof event.data === "object" ? event.data : null;
			if (!data || data.source !== "player.iframe") return;
			if (data.type !== "player.event") return;
			if (typeof data.event !== "string") return;
			callback(data.event, data.details || {}, data);
		});
	}

	function maybeSendPlaybackSession() {
		if (!requiresHostPlaybackSession) return;
		if (!frameLoaded) return;
		if (!issuedPlaybackSession) return;
		sendCommand("set_playback_session", {
			playbackSession: issuedPlaybackSession,
		});
	}

	async function requestPlaybackSession() {
		try {
			const response = await fetch("/getPlaybackSession", {
				cache: "no-store",
			});
			const payload = await response.json();
			const playbackSession =
				payload &&
				payload.success &&
				typeof payload.playbackSession === "string"
					? payload.playbackSession.trim()
					: "";
			if (!playbackSession) {
				if (sessionStatus) {
					sessionStatus.className = "session error";
					sessionStatus.textContent = "session: unavailable";
				}
				return;
			}
			fetchedPlaybackSession = playbackSession;
			issuedPlaybackSession = malformSession
				? malformSessionToken(playbackSession)
				: playbackSession;
			if (sessionStatus) {
				sessionStatus.className = "session";
				sessionStatus.textContent = malformSession
					? `session: ${issuedPlaybackSession} (malformed)`
					: `session: ${playbackSession}`;
			}
			maybeSendPlaybackSession();
		} catch (_err) {
			if (sessionStatus) {
				sessionStatus.className = "session error";
				sessionStatus.textContent = "session: request failed";
			}
		}
	}

	async function invalidatePlaybackSession() {
		const token = (fetchedPlaybackSession || issuedPlaybackSession || "").trim();
		if (!token) {
			if (sessionStatus) {
				sessionStatus.className = "session error";
				sessionStatus.textContent = "session: nothing to invalidate";
			}
			return;
		}

		try {
			const response = await fetch("/invalidatePlaybackSession", {
				method: "POST",
				headers: { "Content-Type": "application/json" },
				cache: "no-store",
				body: JSON.stringify({ playbackSession: token }),
			});
			const payload = await response.json();
			const ok = Boolean(payload && payload.success);
			const invalidated = Boolean(payload && payload.invalidated);
			if (!ok) {
				if (sessionStatus) {
					sessionStatus.className = "session error";
					sessionStatus.textContent = "session: invalidate failed";
				}
				return;
			}
			if (sessionStatus) {
				sessionStatus.className = invalidated ? "session error" : "session pending";
				sessionStatus.textContent = invalidated
					? `session: invalidated ${token}`
					: `session: not found ${token}`;
			}
		} catch (_err) {
			if (sessionStatus) {
				sessionStatus.className = "session error";
				sessionStatus.textContent = "session: invalidate request failed";
			}
		}
	}

	frame.addEventListener("load", () => {
		frameLoaded = true;
		maybeSendPlaybackSession();
	});

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
	if (btnInvalidateSession) {
		btnInvalidateSession.addEventListener("click", () => {
			invalidatePlaybackSession().catch(() => {});
		});
	}

	if (requiresHostPlaybackSession) {
		requestPlaybackSession();
	} else {
		if (btnInvalidateSession) btnInvalidateSession.hidden = true;
		if (sessionStatus) sessionStatus.hidden = true;
	}

	receivePlayerEvent((eventName) => {
		setState(eventName);
	});
})();
