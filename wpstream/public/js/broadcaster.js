/*
 * WpStream in-browser broadcaster.
 *
 * Drives the "Go Live from your browser" page. Enumerates the visitor's
 * camera/microphone/screen-capture devices, previews the selected source in a
 * <video> element, and publishes it to the WpStream ingest endpoint over WebRTC
 * using the OvenLiveKit WHIP client (`OvenLiveKit`).
 *
 * Responsibilities:
 *  - device discovery and <select> population (video/audio, plus "displayCapture");
 *  - building getUserMedia / getDisplayMedia constraints from the resolution picker;
 *  - the publish lifecycle: pre-flight channel-status + quota AJAX checks, then
 *    startStreaming(), ICE/connection state handling, and status UI updates;
 *  - automatic reconnect with a fixed delay after an unexpected disconnect;
 *  - video/audio mute toggles and onboarding-step telemetry;
 *  - resolving the onboarding popup session id from window.name / a cookie.
 *
 * Config is injected from PHP via the global `wpstream_broadcaster_vars`.
 *
 * Comments-only annotation pass: no executable code was changed.
 */
/**
 * WpStream Broadcaster
 * @typedef {Object} WpStreamBroadcasterVars
 * @property {string} whip_url - The WHIP URL for streaming
 * @property {string} channel_id - The channel ID
 * @property {string} ajax_url - The AJAX URL for requests
 * @property {string} no_video_audio_access - Error message for no video/audio access
 * @property {string} no_audio_access - Error message for no audio access
 * @property {string} no_video_access - Error message for no video access
 * @property {string} channel_off - Message when channel is off
 */
/* global wpstream_broadcaster_vars */
// Defer all setup until the DOM is ready; everything runs inside this closure.
document.addEventListener("DOMContentLoaded", function () {
	// Global (closure-scoped) mutable state shared across the handlers below.
	let allDevices = null; // { videoinput:[], audioinput:[] } from OvenLiveKit.getDevices()
	let input = null; // current OvenLiveKit instance (the capture + WHIP publisher)
	let streamingStarted = false; // true while a publish session is active
	let frameCalculatorTimer = null; // setInterval handle for the debug FPS/resolution logger
	let totalVideoFrames = 0; // running frame count used to derive an approximate FPS
	let whipUrl = null; // resolved WHIP ingest URL (from wpstream_broadcaster_vars)
	let videoEnabled = true; // mirrors the video mute toggle state
	let audioEnabled = true; // mirrors the audio mute toggle state
	let localStream = null; // the active MediaStream currently attached to the preview
	let considerReconnect = false; // set true after a successful start to allow auto-reconnects
	let pendingReconnect = false; // true while waiting to reconnect
	let pendingReconnectTimeout = null; // timeout handle for scheduled reconnect
	const reconnectDelayMs = 10000; // 10s delay before attempting to reconnect
	const BROADCASTER_SESSION_COOKIE = 'broadcasterSessionId'; // cookie name persisting the onboarding popup session id

	/**
	 * Write a session-scoped cookie (path=/, no explicit expiry) with a
	 * URL-encoded value.
	 *
	 * @param {string} name  Cookie name.
	 * @param {string} value Raw value; encoded before storage.
	 */
	function setSessionCookie(name, value) {
		// Persist for the whole site path; value is percent-encoded to stay cookie-safe.
		document.cookie = name + '=' + encodeURIComponent(value) + '; path=/';
	}

	/**
	 * Read a cookie value by name from document.cookie.
	 *
	 * @param {string} name Cookie name to look up.
	 * @return {string} Decoded value, or '' when the cookie is absent.
	 */
	function getCookie(name) {
		// Prefix with "; " so every cookie (including the first) is delimited uniformly.
		const value = `; ${document.cookie}`;
		// Split on "; name=" — a match yields exactly two parts around the target value.
		const parts = value.split(`; ${name}=`);
		if (parts.length === 2) {
			// Take the tail, cut at the next ";", and decode the stored value.
			return decodeURIComponent(parts.pop().split(';').shift());
		}
		// Cookie not present.
		return '';
	}

	/**
	 * Recover the onboarding popup payload handed to this window.
	 *
	 * When the go-live page is opened as an onboarding popup, the opener stashes
	 * a JSON blob in `window.name`; this parses it, persists the session id to a
	 * cookie, and falls back to that cookie on subsequent (reloaded) visits.
	 * `window.name` is cleared afterwards so the payload is consumed only once.
	 *
	 * @return {{session_id?: string}} The payload object (possibly empty).
	 */
	function getPopupPayloadFromWindowName() {
		let payload = {};
		try {
			// window.name may carry a JSON payload from the onboarding opener.
			if (window.name) {
				const parsed = JSON.parse(window.name);
				// Only accept our namespaced key to avoid consuming unrelated window.name data.
				if (parsed && parsed.wpstream_onboarding_popup_payload) {
					payload = parsed.wpstream_onboarding_popup_payload;
					// Persist the session id so a page reload can recover it from the cookie.
					if (payload.session_id) {
						setSessionCookie(BROADCASTER_SESSION_COOKIE, payload.session_id);
					}
				}
			}
		} catch (e) {
			// Malformed / non-JSON window.name: ignore and treat as no payload.
			payload = {};
		}

		// No payload this load: try to restore the session id from the cookie.
		if (!payload.session_id) {
			const cookieSessionId = getCookie(BROADCASTER_SESSION_COOKIE);
			if (cookieSessionId) {
				payload = {
					session_id: cookieSessionId,
				};
			}
		}

		// Consume window.name so the payload is not re-read on later navigations.
		window.name = '';
		return payload;
	}

	// Resolve the popup payload/session id once at startup for telemetry correlation.
	const popupPayload = getPopupPayloadFromWindowName();
	const popupSessionId = popupPayload && popupPayload.session_id ? popupPayload.session_id : '';
	console.log('Popup session ID:', popupSessionId);

	/**
	 * Fire an onboarding-step telemetry event, but only if the global tracking
	 * helper is present (it is a no-op outside the onboarding flow).
	 *
	 * @param {string} action       Event/action name.
	 * @param {string} step         Step identifier.
	 * @param {string} [elementType] Optional UI element type.
	 * @param {string} [elementName] Optional UI element name.
	 * @param {string} [trackingSessionId] Optional session id for correlation.
	 */
	function trackOnboardingStepSafe(action, step, elementType = '', elementName = '', trackingSessionId = '') {
		// Guard: the tracker is defined only when the onboarding script is loaded.
		if (typeof wpstream_track_onboarding_step === 'function') {
			wpstream_track_onboarding_step(action, step, elementType, elementName, trackingSessionId);
		}
	}

	// Get WHIP URL from config (injected by PHP); leaves whipUrl null if unset.
	if (wpstream_broadcaster_vars && wpstream_broadcaster_vars.whip_url) {
		whipUrl = wpstream_broadcaster_vars.whip_url;
	}

	// DOM element references for the broadcaster UI (may be null if markup differs).
	const videoElement = document.getElementById("localVideo");
	const streamingButton = document.getElementById("startBroadcast");
	const stopButton = document.getElementById("stopBroadcast");
	const videoSourceSelect = document.getElementById("videoDevice");
	const videoToggle = document.getElementById("videoToggle");
	const videoResolutionSelect = document.getElementById("videoQuality");
	const audioSourceSelect = document.getElementById("audioDevice");
	const audioToggle = document.getElementById("audioToggle");
	const messageContainer = document.getElementById("messageContainer");
	const statusIndicator = document.getElementById("statusIndicator");
	const statusText = document.getElementById("statusText");
	const liveIndicatorLive = document.getElementById("videoLiveIndicatorLive");
	const liveIndicatorError = document.getElementById("videoLiveIndicatorError");
	const loadSpinner = document.getElementById("wpstream-pre-load-spinner");

	// getUserMedia constraint presets per quality option (exact/ideal width & height).
	const userResolutions = {
		vga: {
			width: { ideal: 640 },
			height: { ideal: 360 },
		},
		hd: {
			width: { exact: 1280 },
			height: { exact: 720 },
		},
		fhd: {
			width: { exact: 1920 },
			height: { exact: 1080 },
		},
		square: {
			width: { exact: 800 },
			height: { exact: 600 },
		},
		default: {
			width: { min: 640, ideal: 1280, max: 1920 },
			height: { min: 360, ideal: 720, max: 1080 },
		},
	};

	// Plain width/height presets used for getDisplayMedia (screen capture) constraints.
	const displayResolutions = {
		vga: { width: 640, height: 360 },
		hd: { width: 1280, height: 720 },
		fhd: { width: 1920, height: 1080 },
		square: { width: 800, height: 600 },
		default: { width: 1280, height: 720 },
	};

	/**
	 * Start a 1s debug timer that logs the live video resolution and derives an
	 * approximate frame rate from getVideoPlaybackQuality(). Diagnostic only.
	 *
	 * @param {HTMLVideoElement} videoElement The preview element to sample.
	 */
	function getResolutionAndCalculateFrame(videoElement) {
		// Restart cleanly: clear any prior timer and reset the frame accumulator.
		if (frameCalculatorTimer) {
			clearInterval(frameCalculatorTimer);
			frameCalculatorTimer = null;
			totalVideoFrames = 0;
		}

		// Sample once per second.
		frameCalculatorTimer = setInterval(function () {
			// Log the current decoded video dimensions.
			console.log(
				"Resolution: " +
				videoElement.videoWidth +
				"x" +
				videoElement.videoHeight
			);

			if (totalVideoFrames === 0) {
				// First tick: seed the baseline total frame count.
				totalVideoFrames =
					videoElement.getVideoPlaybackQuality().totalVideoFrames;
			} else {
				// Subsequent ticks: delta since last second ~= frames per second.
				let currentTotalFrame =
					videoElement.getVideoPlaybackQuality().totalVideoFrames;
				let frameRate = currentTotalFrame - totalVideoFrames;
				// console.log('Frame rate: ' + frameRate + 'fps');
				totalVideoFrames = currentTotalFrame;
			}
		}, 1000);
	}

	/**
	 * Build a getUserMedia() constraints object from the current device and
	 * resolution <select> values (camera/mic path).
	 *
	 * @return {MediaStreamConstraints} Constraints for camera/microphone capture.
	 */
	function getUserConstraints() {
		// Read the currently selected device ids and quality preset.
		let videoDeviceId = videoSourceSelect.value;
		let videoResolution = videoResolutionSelect.value;
		let audioDeviceId = audioSourceSelect.value;

		let newConstraint = {};

		// Pin the exact chosen camera when one is selected.
		if (videoDeviceId) {
			newConstraint.video = {
				deviceId: {
					exact: videoDeviceId,
				},
			};
		}

		// Pin the exact chosen microphone when one is selected.
		if (audioDeviceId) {
			newConstraint.audio = {
				deviceId: {
					exact: audioDeviceId,
				},
			};
		}

		// Layer the resolution preset onto the video constraint (creating it if needed).
		if (videoResolution && userResolutions[videoResolution]) {
			const resolution = userResolutions[videoResolution];

			if (!newConstraint.video) {
				newConstraint.video = {};
			}

			newConstraint.video.width = resolution.width;
			newConstraint.video.height = resolution.height;
		}

		return newConstraint;
	}

	/**
	 * Build a getDisplayMedia() constraints object for screen-capture publishing.
	 *
	 * @return {MediaStreamConstraints} Constraints for screen + system/tab audio.
	 */
	function getDisplayConstraints() {
		let videoResolution = videoResolutionSelect.value;

		let newConstraint = {};
		newConstraint.video = {};

		// Apply a fixed capture size when a known preset is chosen, else let the browser decide.
		if (videoResolution && displayResolutions[videoResolution]) {
			const resolution = displayResolutions[videoResolution];
			newConstraint.video.width = resolution.width;
			newConstraint.video.height = resolution.height;
		} else {
			newConstraint.video = true;
		}

		// Always request audio alongside the shared screen.
		newConstraint.audio = true;
		return newConstraint;
	}

	/**
	 * Populate a device <select> with one <option> per enumerated device.
	 *
	 * @param {string} type            Device kind label ("video" | "audio").
	 * @param {HTMLSelectElement} select The <select> to fill.
	 * @param {MediaDeviceInfo[]} devices Devices of that kind.
	 */
	function setDevice(type, select, devices) {
		// Clear any previously rendered options.
		select.innerHTML = "";

		if (type === "audio" && devices.length === 0) {
			// No microphones: show a single placeholder "No Source Available" option.
			const option = document.createElement("option");
			option.value = "";
			option.textContent = "No Source Available";
			select.appendChild(option);
		} else {
			// One option per device; fall back to a generic "type N" label when unnamed
			// (device labels are empty until the user grants media permissions).
			devices.forEach(function (device) {
				const option = document.createElement("option");
				option.textContent =
					device.label || `${type} ${select.options.length + 1}`;
				option.value = device.deviceId;
				select.appendChild(option);
			});
		}

		// Default to the first option when the list is non-empty.
		if (select.options.length > 0) {
			select.selectedIndex = 0;
		}
	}

	/**
	 * Clear any on-screen status messages and stop the debug frame timer.
	 */
	function resetMessages() {
		// Empty the message area if present.
		if (messageContainer) {
			messageContainer.innerHTML = "";
		}

		// Stop the resolution/FPS debug interval.
		clearInterval(frameCalculatorTimer);
		frameCalculatorTimer = null;
	}

	/**
	 * Render a single dismissible status message (plain text) into the message area.
	 *
	 * @param {string} message The text to display (inserted as textContent, so safe).
	 * @param {string} [type]  Severity class prefix: "info" | "success" | "error".
	 */
	function showMessage(message, type = "info") {
		// Nothing to render into.
		if (!messageContainer) return;

		// Build the message element; textContent keeps user/error strings safe.
		const messageElement = document.createElement("div");
		messageElement.className = type + "-message";
		messageElement.textContent = message;

		// Add an "×" dismiss button that removes the message on click.
		const dismissButton = document.createElement("button");
		dismissButton.className = 'dismiss-message';
		dismissButton.innerHTML = '&times;';
		dismissButton.addEventListener('click', function() {
			messageElement.remove();
		});

		messageElement.appendChild(dismissButton);

		// Replace any existing message with this one (single-message area).
		messageContainer.innerHTML = "";
		messageContainer.appendChild(messageElement);

		// Auto-dismiss transient info/success messages after 5s; errors persist.
		if (type === "success" || type === "info") {
			setTimeout(() => {
				messageElement.remove();
			}, 5000);
		}
	}

	/**
	 * Render a dismissible status message whose body is trusted HTML.
	 *
	 * Used for server-provided rich messages (e.g. the "not enough traffic"
	 * notice). Caller is responsible for the safety of `html`.
	 *
	 * @param {string} html  HTML string assigned via innerHTML.
	 * @param {string} [type] Severity class prefix; defaults to "error".
	 */
	function showHtmlMessage(html, type = "error") {
		// Nothing to render into.
		if (!messageContainer) return;

		// Build the message element; innerHTML allows the caller's markup.
		const messageElement = document.createElement("div");
		messageElement.className = type + "-message";
		messageElement.innerHTML = html;

		// Add an "×" dismiss button that removes the message on click.
		const dismissButton = document.createElement("button");
		dismissButton.className = 'dismiss-message';
		dismissButton.innerHTML = '&times;';
		dismissButton.addEventListener('click', function() {
			messageElement.remove();
		});

		messageElement.appendChild(dismissButton);

		messageContainer.innerHTML = "";
		messageContainer.appendChild(messageElement);
	}

	/**
	 * Update the status indicator dot/text and the live/error overlay badges to
	 * reflect the current broadcast connection state.
	 *
	 * @param {string} status One of "connected" | "connecting" | "reconnecting"
	 *                        | "disconnected".
	 */
	function updateStatus(status) {
		// Requires both the indicator dot and its text label.
		if (!statusIndicator || !statusText) return;

		// Reset to a clean slate before applying the state-specific class.
		statusIndicator.classList.remove(
			"connected",
			"disconnected",
			"connecting",
			"reconnecting"
		);

		switch (status) {
			// Live: green dot, hide the error badge, show the LIVE badge.
			case "connected":
				statusIndicator.classList.add("connected");
				statusText.textContent = "Connected - Broadcasting Live";
				liveIndicatorLive.style.display = 'inline';
				liveIndicatorError.style.display = 'none';
				break;
			// Connecting: amber dot, show a "Connecting..." error-slot badge.
			case "connecting":
				statusIndicator.classList.add("connecting");
				statusText.textContent = "Connecting...";
				liveIndicatorError.style.display = 'inline';
				liveIndicatorError.innerText = 'Connecting...';
				break;
			// Reconnecting: amber dot, hide LIVE, show the countdown message.
			case "reconnecting":
				statusIndicator.classList.add("connecting");
				statusText.textContent = "Reconnecting";
				liveIndicatorError.style.display = 'inline';
				liveIndicatorLive.style.display = 'none';
				liveIndicatorError.innerText = 'Connection lost. Reconnecting in 10 seconds...';
				break;
			// Disconnected / default: grey dot, hide both LIVE and error badges.
			case "disconnected":
			default:
				statusIndicator.classList.add("disconnected");
				statusText.textContent = "Not Broadcasting";
				liveIndicatorLive.style.display = 'none';
				liveIndicatorError.style.display = 'none';
				break;
		}
	}

	/**
	 * (Re)create the OvenLiveKit capture instance, wire its lifecycle callbacks,
	 * acquire the selected media (camera/mic or screen), and attach it to the
	 * preview. Optionally auto-starts publishing (used by the reconnect path).
	 *
	 * @param {boolean} [shouldAutoStart] When true, begin streaming once media is ready
	 *                                    (only if considerReconnect is still set).
	 * @param {boolean} [keepMessages]    When true, preserve currently shown messages
	 *                                    (used when re-creating after an OverconstrainedError).
	 */
	function createInput( shouldAutoStart = false, keepMessages = false ) {
		// Disable the start button while we (re)initialise capture.
		if (streamingButton) {
			streamingButton.disabled = true;
		}

		// Show the loading spinner
		if (loadSpinner) {
			loadSpinner.style.display = "block";
		}

		// Tear down any previous OvenLiveKit instance first.
		if (input) {
			input.remove();
			input = null;
		}

		// Clear stale messages unless the caller asked to keep them.
		if ( !keepMessages ) {
			resetMessages();
		}

		// Stop and release tracks from the previous local stream to free the devices.
		if (localStream) {
			localStream.getTracks().forEach((track) => track.stop());
			localStream = null;
		}

		// Create a fresh OvenLiveKit instance with lifecycle callbacks.
		input = OvenLiveKit.create({
			callbacks: {
				// Capture/publish error handler.
				error: function (error) {
					// Normalise the various error shapes into a display string.
					let errorMessage = '';

						if (error.message) {
							errorMessage = error.message;
						} else if (error.name) {
							errorMessage = error.name;
						} else {
							errorMessage = error.toString();
						}

					// Unsupported resolution: warn, fall back to "default", and rebuild.
					if (error.name === "OverconstrainedError") {
						showMessage(
							"Your browser or camera does not support this frame size: " + videoResolutionSelect.value,
							'error'
						);
						videoResolutionSelect.value = 'default';
						createInput(shouldAutoStart, true);
						return;
					}

						// Any other error: surface it and, if this was an auto-start, stop reconnecting.
						resetMessages();
						showMessage(errorMessage, "error");

						if (shouldAutoStart) {
							considerReconnect = false;
						}
					},
					// Fired when the underlying WebRTC/WHIP connection closes.
					connectionClosed: function (type, event) {
						console.log("Connection closed:", type, event);
						streamingStarted = false;
						// updateStatus("disconnected");

						// if (streamingButton) {
						// 	streamingButton.classList.remove("hidden");
						// 	streamingButton.disabled = false;
						// }
						// if (stopButton) {
						// 	stopButton.classList.add("hidden");
						// }

					// Auto-reconnect if it was an unexpected drop; otherwise treat as a clean stop.
					if (considerReconnect && !pendingReconnect) {
						console.log('connection closed, attempting to reconnect');
						attemptReconnect();
						liveIndicatorLive.style.display = 'none';
						liveIndicatorError.style.display = 'inline';
						liveIndicatorError.innerContent = 'Reconnecting';
					} else {
						console.log('connection closed, not reconnecting');
						// Report the stop to onboarding telemetry.
						trackOnboardingStepSafe('broadcaster_streaming_stopped', 'wpstream_broadcaster', 'button', 'streaming_stopped', popupSessionId);
						// updateInputState(false);
					}
					// Hide the spinner regardless of outcome.
					if (loadSpinner) {
						loadSpinner.style.display = "none";
					}
				},
				// Fired on ICE connection state transitions.
				iceStateChange: function (state) {
					console.log("ICE state changed:", state);
					// Reached "connected": mark live and record the streaming-started event.
					if ( state === 'connected' ) {
						// showMessage("Broadcast started successfully");
						updateStatus('connected');
						trackOnboardingStepSafe('broadcaster_streaming_started', 'wpstream_broadcaster', 'button', 'streaming_started', popupSessionId);
					}

						// Dropped while we still intend to stream: kick off a reconnect.
						if (state === "disconnected" && considerReconnect) {
							streamingStarted = false;
							if (considerReconnect && !pendingReconnect) {
								console.log(
									"connection closed, attempting to reconnect from ice state change"
								);
								updateStatus("reconnecting");
								attemptReconnect();
							} else {
								// A reconnect is already pending: just inform the user.
								showMessage(
									"Connection failed. Please check your network settings.",
									"error"
								);
							}
						}
					},
				},
			});

			// Bind the capture output to the preview <video> element.
			input.attachMedia(videoElement);

		// Acquire media only if at least one video source option exists.
		if (videoSourceSelect.options.length > 0) {
			if (videoSourceSelect.value === "displayCapture") {
				// Screen-share path: request the display surface.
				input
					.getDisplayMedia(getDisplayConstraints())
					.then(function (stream) {
						// Keep a handle to the captured stream, re-enable the button, hide spinner.
						localStream = stream;
						if (streamingButton) {
							streamingButton.disabled = false;
						}
						if (loadSpinner) {
							loadSpinner.style.display = "none";
						}

						// Reconnect flow: media ready, resume publishing automatically.
						if ( shouldAutoStart && considerReconnect ) {
							startStreaming(true);
						}
					})
					.catch(function (error) {
						// Screen capture denied/failed.
						console.error('Failed to get display media:', error);
						if ( shouldAutoStart ) {
							// During auto-reconnect, report and abort further reconnects.
							showMessage('Failed to access screen sharing.', 'error');
							considerReconnect = false;
							updateInputState(false);
						}
						if (loadSpinner) {
							loadSpinner.style.display = "none";
						}
					});
			} else {
				// Camera/microphone path: request the selected devices.
				input
					.getUserMedia(getUserConstraints())
					.then(function (stream) {
						// Keep a handle to the captured stream and re-enable the start button.
						localStream = stream;
						if (streamingButton) {
							streamingButton.disabled = false;
						}

						// Reconnect flow: media ready, resume publishing automatically.
						if ( shouldAutoStart && considerReconnect ) {
							startStreaming(true);
						}
						if (loadSpinner) {
							loadSpinner.style.display = "none";
						}
					})
					.catch(function (error) {
						// Camera/mic permission denied or device error.
						console.error('Failed to get user media:', error);
						if ( shouldAutoStart ) {
							// During auto-reconnect, report and abort further reconnects.
							showMessage('Failed to access camera/microphone.', 'error');
							considerReconnect = false;
							updateInputState(false);
						}
						if (loadSpinner) {
							loadSpinner.style.display = "none";
						}
					});
			}
		}
	}

	/**
	 * Pre-flight the publish: verify the channel is active and the account has
	 * quota (both via AJAX), then proceed to actually start streaming.
	 *
	 * @param {boolean} [isReconnect] True when invoked as part of a reconnect.
	 */
	function startStreaming( isReconnect = false) {
		// make an ajax call to check if the channel is active and if the user has quota
		const channelCheckPromise = checkChannelStatus(wpstream_broadcaster_vars.channel_id);
		const channelUserQuotaPromise = checkUserQuota();

		// Wait for both checks before deciding whether to publish.
		Promise.all([channelCheckPromise, channelUserQuotaPromise])
			.then(function (results) {
				const channelActive = results[0];
				const userQuotaValid = results[1];

				// Channel not started server-side: show the "channel off" message and bail.
				if (!channelActive) {
					showMessage(wpstream_broadcaster_vars.channel_off, 'error');
					resetStreamingUI();
					considerReconnect = false;
					return;
				}

				// Insufficient quota/traffic: show the rich notice and bail.
				if (!userQuotaValid) {
					showHtmlMessage(wpstream_broadcaster_vars.not_enough_traffic, 'error');
					resetStreamingUI();
					considerReconnect = false;
					return;
				}

				// Checks passed: clear messages and begin the actual publish.
				resetMessages();
				proceedWithStreaming(isReconnect);
			});
	}

	/**
	 * Enable/disable the device and resolution controls plus the start button.
	 * Called with true while streaming (lock controls) and false when idle.
	 *
	 * @param {boolean} state true = disabled (locked), false = enabled.
	 */
	function updateInputState(state) {
		videoSourceSelect.disabled = state;
		audioSourceSelect.disabled = state;
		videoResolutionSelect.disabled = state;
		streamingButton.disabled = state;
	}

	/**
	 * User-initiated stop: cancel any pending reconnect, reset the UI to idle,
	 * stop the current publish and rebuild a fresh (idle) capture instance.
	 */
	function stopStreaming() {
		streamingStarted = false;
		considerReconnect = false; // user-initiated stop should cancel auto-reconnect
		// Cancel a scheduled reconnect if one is pending.
		if (pendingReconnect && pendingReconnectTimeout) {
			clearTimeout(pendingReconnectTimeout);
			pendingReconnect = false;
			pendingReconnectTimeout = null;
		}
		updateStatus("disconnected");

		// Swap the Stop button back to the Start button.
		if (streamingButton) {
			streamingButton.classList.remove("hidden");
		}
		if (stopButton) {
			stopButton.classList.add("hidden");
		}

		// Stop publishing, then re-create a fresh idle capture/preview.
		if (input) {
			input.stopStreaming();
			createInput();
		}

		// showMessage("Broadcasting stopped", "info");
		// Re-enable the device/resolution controls.
		updateInputState(false);
	}

	/**
	 * Schedule an automatic reconnect after an unexpected disconnect: tear down
	 * the current peer/websocket, wait reconnectDelayMs, re-check the channel is
	 * still live, and rebuild the capture with auto-start. Guards against
	 * overlapping attempts via pendingReconnect.
	 */
	function attemptReconnect() {
		console.log("attemptReconnect()");
		updateStatus('reconnecting');

		// Do not stack reconnect attempts.
		if ( pendingReconnect ) {
			console.log('reconnect already in progress');
			return;
		}

		// Clean up existing connection before reconnecting
		if (input) {
			if (input.peerConnection || input.webSocket) {
				// Force cleanup without triggering callbacks
				if (input.peerConnection) {
					input.peerConnection.close();
					input.peerConnection = null;
				}
				if (input.webSocket) {
					input.webSocket.close();
					input.webSocket = null;
				}
				// Reset streaming mode
				input.streamingMode = null;
			}
		}

		pendingReconnect = true;

		// Show a reconnecting state and allow user to cancel via Stop button
		// showMessage("Disconnected. Reconnecting in 5 seconds...", "info");
		// updateInputState(false);

		pendingReconnect = true;
		// After the delay, verify the channel then rebuild capture (auto-start).
		pendingReconnectTimeout = setTimeout(function () {
			pendingReconnect = false;
			pendingReconnectTimeout = null;
			// Only proceed if the user hasn't cancelled in the meantime.
			if (considerReconnect) {
				checkChannelStatus(wpstream_broadcaster_vars.channel_id)
					.then(function(channelActive) {
						// Channel still live and reconnect still wanted: re-create with auto-start.
						if (channelActive && considerReconnect) {
							console.log("Channel is active, proceeding with reconnect...");
							updateStatus("connecting");
							// input.stopStreaming();
							// Small extra delay before rebuilding the capture.
							setTimeout(function () {
								createInput(true);
							}, 5000);
						} else {
							// Channel ended server-side: give up and reset the UI.
							console.log("Channel is not active, cannot reconnect.");
							considerReconnect = false;
							showMessage('Channel is no longer active. Broadcasting stopped');
							resetStreamingUI();
						}
					})
					.catch(function (error) {
						// Status check failed: retry the whole reconnect while still wanted.
						console.error('Error checking channel status');
						if (considerReconnect) {
							console.error('Error during reconnect attempt:', error);
							attemptReconnect();
						}
					});

				// console.log('Reconnecting...');
				// createInput( true );
				// startStreaming();
			}
		}, reconnectDelayMs);
	}

	/**
	 * Enable/disable the outgoing video track and swap the on/off toggle icons.
	 * Note the icon logic is inverted: when video is enabled the "video-off"
	 * (mute) icon is shown as the actionable control, and vice versa.
	 *
	 * @param {boolean} enabled true to enable the video track, false to mute it.
	 */
	function toggleVideo(enabled) {
		let stream = localStream;

		// Toggle which icon is visible (the icon offers the opposite action).
		if ( enabled ) {
			document.getElementById('video-off').style.display = 'inline';
			document.getElementById('video-on').style.display = 'none';
		} else {
			document.getElementById('video-on').style.display = 'inline';
			document.getElementById('video-off').style.display = 'none';
		}

		// Fall back to the preview element's stream if localStream isn't set.
		if (!stream && videoElement && videoElement.srcObject) {
			stream = videoElement.srcObject;
		}

		// Apply the enabled flag to every video track.
		if (stream) {
			const videoTracks = stream.getVideoTracks();

			videoTracks.forEach((track) => {
				track.enabled = enabled;
			});

			// showMessage(enabled ? "Video enabled" : "Video disabled", "info");
		}
	}

	/**
	 * Enable/disable the outgoing audio track and swap the on/off toggle icons
	 * (same inverted-icon convention as toggleVideo).
	 *
	 * @param {boolean} enabled true to enable the audio track, false to mute it.
	 */
	function toggleAudio(enabled) {
		let stream = localStream;

		// Toggle which icon is visible (the icon offers the opposite action).
		if ( enabled ) {
			document.getElementById('audio-off').style.display = 'inline';
			document.getElementById('audio-on').style.display = 'none';
		} else {
			document.getElementById('audio-on').style.display = 'inline';
			document.getElementById('audio-off').style.display = 'none';
		}

		// Fall back to the preview element's stream if localStream isn't set.
		if (!stream && videoElement && videoElement.srcObject) {
			stream = videoElement.srcObject;
		}

		// Apply the enabled flag to every audio track.
		if (stream) {
			const audioTracks = stream.getAudioTracks();

			audioTracks.forEach((track) => {
				track.enabled = enabled;
			});

			// showMessage(enabled ? "Audio enabled" : "Audio disabled", "info");
		}
	}

	// Event listeners
	// Start button: begin the publish flow (only if not already streaming).
	if (streamingButton) {
		streamingButton.addEventListener("click", function () {
			if (!streamingStarted) {
				updateStatus('connecting');
				streamingButton.classList.add("hidden");
				stopButton.classList.remove("hidden");
				startStreaming();
			}
		});
	}

	// Stop button: doubles as "Cancel" while a reconnect is pending, else stops.
	if (stopButton) {
		stopButton.addEventListener("click", function () {
			// If a reconnect is pending, treat this as "Cancel Broadcast"
			if (pendingReconnect) {
				// Cancel the scheduled reconnect and return the UI to idle.
				if (pendingReconnectTimeout) {
					clearTimeout(pendingReconnectTimeout);
					pendingReconnectTimeout = null;
				}
				pendingReconnect = false;
				considerReconnect = false;
				updateStatus("disconnected");
				// showMessage("Broadcast stopped", "info");
				updateInputState(false);
				if (stopButton) {
					stopButton.classList.add("hidden");
				}
				if (streamingButton) {
					streamingButton.classList.remove("hidden");
					streamingButton.disabled = false;
				}
				return;
			}

			// Normal case: stop an active broadcast.
			if (streamingStarted) {
				stopStreaming();
			}
		});
	}

	// Device change listeners: re-create the capture when a selection changes
	// (only if capture is already initialised).
	if (videoSourceSelect) {
		videoSourceSelect.addEventListener("change", function () {
			if (input) {
				createInput();
			}
		});
	}

	if (videoResolutionSelect) {
		videoResolutionSelect.addEventListener("change", function () {
			if (input) {
				createInput();
			}
		});
	}

	if (audioSourceSelect) {
		audioSourceSelect.addEventListener("change", function () {
			if (input) {
				createInput();
			}
		});
	}

	// Video mute toggle: flip the flag and apply it to the track.
	if (videoToggle) {
		videoToggle.addEventListener("click", function () {
			videoEnabled = !videoEnabled;
			toggleVideo(videoEnabled);
		});
	}

	// Audio mute toggle: flip the flag and apply it to the track.
	if (audioToggle) {
		audioToggle.addEventListener("click", function () {
			audioEnabled = !audioEnabled;
			toggleAudio(audioEnabled);
		});
	}

	// Mobile video expand toggle: grow/shrink the preview container and keep
	// the button's ARIA state (expanded + controlled element id) in sync.
	const expandToggle = document.getElementById("videoExpandToggle");
	if (expandToggle) {
		expandToggle.addEventListener("click", function () {
			const container = document.querySelector(".video-container");
			if (container) {
				container.classList.toggle("expanded");
				// Sync accessibility attributes with visual state
				const isExpanded = container.classList.contains("expanded");
				expandToggle.setAttribute("aria-expanded", isExpanded ? "true" : "false");
				// Ensure the toggle references the controlled element
				if (!container.id) {
					container.id = "videoContainer";
				}
				expandToggle.setAttribute("aria-controls", container.id);
			}
		});
	}

	/**
	 * Populate the device <select>s from the enumerated devices and create the
	 * initial (idle) capture/preview instance.
	 */
	function init() {
		// Fill the camera/mic dropdowns once devices have been enumerated.
		if (allDevices) {
			setDevice("video", videoSourceSelect, allDevices.videoinput);
			setDevice("audio", audioSourceSelect, allDevices.audioinput);
		}

		// Build the initial preview.
		createInput();
	}

	// Initialize - get all devices first
	OvenLiveKit.getDevices()
		.then(function (devices) {
			// Cache the device list, then initialise the UI.
			allDevices = devices;
			init();
		})
		.catch(function (error) {
			// Device enumeration failed: map known messages, else show raw text.
			let errorMessage = "";

			if (error.message) {
				console.log(error.message);
				changeErrorMessage(error.message);
			} else if (error.name) {
				errorMessage = error.name;
				showMessage(errorMessage, "error");
			} else {
				errorMessage = error.toString();
				showMessage(errorMessage, "error");
			}
		});

	/**
	 * Translate an OvenLiveKit device-enumeration error message into the
	 * localized, config-provided message and display it.
	 *
	 * @param {string} message The raw error message from OvenLiveKit.
	 */
	function changeErrorMessage(message) {
		console.log(wpstream_broadcaster_vars);
		switch (message) {
			// No camera and no microphone available.
			case "No input devices were found.":
				showMessage(
					wpstream_broadcaster_vars.no_video_audio_access,
					"error"
				);
				break;
			// Microphone missing.
			case "Can not find Audio devices":
				showMessage(wpstream_broadcaster_vars.no_audio_access, "error");
				break;
			// Camera missing.
			case "Can not find Video devices":
				showMessage(wpstream_broadcaster_vars.no_video_access, "error");
		}
	}

	/**
	 * Ask the server whether the given channel is currently active (started).
	 *
	 * @param {string|number} channelId The WpStream channel id to check.
	 * @return {Promise<boolean>} Resolves true when status === 'active'; resolves
	 *                            true immediately if no ajax_url is configured;
	 *                            rejects on parse/transport error.
	 */
	function checkChannelStatus(channelId) {
		return new Promise((resolve, reject) => {
			// No AJAX endpoint configured: assume active so streaming isn't blocked.
			if (!wpstream_broadcaster_vars.ajax_url) {
				resolve(true);
				return;
			}

			// CSRF nonce pulled from the hidden field rendered in the page.
			var nonce = jQuery('#wpstream_start_event_nonce').val();

			jQuery.ajax({
				url: wpstream_broadcaster_vars.ajax_url,
				type: 'POST',
				data: {
					action: 'wpstream_check_event_status',
					channel_id: channelId,
					nonce: nonce
				},
				success: function(response) {
					// Response is a JSON string; parse and inspect the status field.
					try {
						const parsedResponse = JSON.parse(response);
						if (parsedResponse.status === 'active') {
							resolve(true);
						} else {
							resolve(false);
						}
					} catch (e) {
						// Unparseable payload: report and reject.
						console.error('Error parsing response:', e);
						showMessage('Error checking channel status', 'error');
						reject(false);
					}
				},
				error: function(xhr, status, error) {
					// Transport-level failure: reset the UI and reject.
					console.error('Error checking channel status:', error);
					resetStreamingUI();
					showMessage('Error checking channel status: ' + error, 'error');
					reject(false);
				}
			})
		})
	}

	/**
	 * Decide whether the account has quota left to broadcast, from a quota payload.
	 *
	 * @param {Object} quotaData Parsed quota response.
	 * @return {boolean} true if quota remains under whichever metering model applies.
	 */
	function hasBroadcastQuota(quotaData) {
		// Missing or explicitly-failed payload: no quota.
		if (!quotaData || quotaData.success === false) {
			return false;
		}

		// Streaming-hours metering: require positive remaining broadcast hours.
		if (quotaData.use_streaming_hours === true && quotaData.available_broadcast_hours !== undefined) {
			return parseFloat(quotaData.available_broadcast_hours) > 0;
		}

		// Data-volume metering: require positive remaining MB.
		if (quotaData.available_data_mb !== undefined) {
			return parseFloat(quotaData.available_data_mb) > 0;
		}

		// No recognisable quota field: treat as no quota.
		return false;
	}

	/**
	 * Check the account's broadcast quota via AJAX.
	 *
	 * NOTE: quota enforcement is currently disabled — this always resolves true
	 * so users can always stream. The AJAX implementation below is intentionally
	 * retained (but unreachable) in case quota gating is reinstated. Not changed
	 * in this comments-only pass.
	 *
	 * @return {Promise<boolean>} Always resolves true (quota check bypassed).
	 */
	function checkUserQuota() {
		// We add this here so that the user can always stream, no matter the quota
		return Promise.resolve(true);

		// Leaving this here if we want to ever return to
		// checking the user quota and restrict streaming
		return new Promise((resolve, reject) => {
			if (!wpstream_broadcaster_vars.ajax_url) {
				resolve(true);
				return;
			}

			jQuery.ajax({
				url: wpstream_broadcaster_vars.ajax_url,
				type: 'POST',
				data: {
					action: 'wpstream_check_user_quota',
				},
				success: function(response) {
					try {
						const parsedResponse = JSON.parse(response);
						if (hasBroadcastQuota(parsedResponse)) {
							resolve(true);
						} else {
							resolve(false);
						}
					} catch (e) {
						console.error('Error parsing response:', e);
						showMessage('Error checking quota', 'error');
						reject(false);
					}
				},
				error: function(xhr, status, error) {
					console.error('Error checking user quota:', error);
					showMessage('Error checking user quota: ' + error, 'error');
					reject(false);
				}
			})
		});
	}

	/**
	 * Perform the actual WHIP publish once pre-flight checks have passed: lock the
	 * UI, enable auto-reconnect, and hand the stream to OvenLiveKit. Falls back to
	 * an error state when no WHIP URL is configured.
	 *
	 * @param {boolean} isReconnect True when this publish is part of a reconnect.
	 */
	function proceedWithStreaming(isReconnect) {
		streamingStarted = true;
		// If a reconnect was pending, cancel it as we're actively starting now
		if (pendingReconnect && pendingReconnectTimeout) {
			clearTimeout(pendingReconnectTimeout);
			pendingReconnect = false;
			pendingReconnectTimeout = null;
		}

		updateStatus("connecting");

		// Swap Start -> Stop while connecting.
		if (streamingButton) {
			streamingButton.classList.add("hidden");
		}
		if (stopButton) {
			stopButton.classList.remove("hidden");

		}

		// Publish only when we have both a capture instance and a WHIP URL.
		if (input && whipUrl) {
			let connectionConfig = {};

			// Begin streaming; mark that we should auto-reconnect on unexpected disconnects
			considerReconnect = true;
			input.startStreaming(whipUrl, connectionConfig);
			// NOTE: this guard checks the truthy `input` (not a null check) and always
			// logs here after startStreaming; kept as-is (comments-only pass).
			if ( input ) {
				// TODO: check why input is sometimes null here
				console.log('something was wrong' );
			}
			// updateStatus("connected");
			// On a successful reconnect just log (user-facing message is commented out).
			if ( isReconnect ) {
				console.log('Reconnected successfully!');
				// showMessage("Reconnected successfully!", "success");
			}
			// Lock the device/resolution controls while live.
			updateInputState(true);
		} else {
			// No WHIP URL (or no capture): revert UI and surface the error.
			streamingButton.classList.remove("hidden");
			stopButton.classList.add("hidden");
			console.log(whipUrl);
			showMessage("Error: No WHIP URL configured", "error");
			updateStatus("disconnected");

			// Stop reconnecting if there's an error
			if ( isReconnect ) {
				considerReconnect = false;
			}
		}
	}

	/**
	 * Return the controls to the idle "not broadcasting" state: grey status, show
	 * an enabled Start button and hide the Stop button.
	 */
	function resetStreamingUI() {
		updateStatus("disconnected");
		if (streamingButton) {
			streamingButton.classList.remove("hidden");
			streamingButton.disabled = false;
		}
		if (stopButton) {
			stopButton.classList.add("hidden");
		}
	}
});
