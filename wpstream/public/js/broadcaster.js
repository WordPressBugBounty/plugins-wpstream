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
document.addEventListener("DOMContentLoaded", function () {
	// Global variables
	let allDevices = null;
	let input = null;
	let streamingStarted = false;
	let frameCalculatorTimer = null;
	let totalVideoFrames = 0;
	let whipUrl = null;
	let videoEnabled = true;
	let audioEnabled = true;
	let localStream = null;
	let considerReconnect = false; // set true after a successful start to allow auto-reconnects
	let pendingReconnect = false; // true while waiting to reconnect
	let pendingReconnectTimeout = null; // timeout handle for scheduled reconnect
	const reconnectDelayMs = 15000; // 10s delay before attempting to reconnect

	// Get WHIP URL from config
	if (wpstream_broadcaster_vars && wpstream_broadcaster_vars.whip_url) {
		whipUrl = wpstream_broadcaster_vars.whip_url;
	}

	// DOM elements
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

	// Resolution mappings from demo
	const userResolutions = {
		vga: {
			width: { ideal: 640 },
			height: { ideal: 480 },
		},
		hd: {
			width: { ideal: 1280 },
			height: { ideal: 720 },
		},
		fhd: {
			width: { ideal: 1920 },
			height: { ideal: 1080 },
		},
		square: {
			width: { ideal: 800 },
			height: { ideal: 600 },
		},
		default: {
			width: { ideal: 1280 },
			height: { ideal: 720 },
		},
	};

	const displayResolutions = {
		vga: { width: 640, height: 480 },
		hd: { width: 1280, height: 720 },
		fhd: { width: 1920, height: 1080 },
		square: { width: 800, height: 600 },
		default: { width: 1280, height: 720 },
	};

	function getResolutionAndCalculateFrame(videoElement) {
		if (frameCalculatorTimer) {
			clearInterval(frameCalculatorTimer);
			frameCalculatorTimer = null;
			totalVideoFrames = 0;
		}

		frameCalculatorTimer = setInterval(function () {
			console.log(
				"Resolution: " +
					videoElement.videoWidth +
					"x" +
					videoElement.videoHeight
			);

			if (totalVideoFrames === 0) {
				totalVideoFrames =
					videoElement.getVideoPlaybackQuality().totalVideoFrames;
			} else {
				let currentTotalFrame =
					videoElement.getVideoPlaybackQuality().totalVideoFrames;
				let frameRate = currentTotalFrame - totalVideoFrames;
				// console.log('Frame rate: ' + frameRate + 'fps');
				totalVideoFrames = currentTotalFrame;
			}
		}, 1000);
	}

	function getUserConstraints() {
		let videoDeviceId = videoSourceSelect.value;
		let videoResolution = videoResolutionSelect.value;
		let audioDeviceId = audioSourceSelect.value;

		let newConstraint = {};

		if (videoDeviceId) {
			newConstraint.video = {
				deviceId: {
					exact: videoDeviceId,
				},
			};
		}

		if (audioDeviceId) {
			newConstraint.audio = {
				deviceId: {
					exact: audioDeviceId,
				},
			};
		}

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

	function getDisplayConstraints() {
		let videoResolution = videoResolutionSelect.value;

		let newConstraint = {};
		newConstraint.video = {};

		if (videoResolution && displayResolutions[videoResolution]) {
			const resolution = displayResolutions[videoResolution];
			newConstraint.video.width = resolution.width;
			newConstraint.video.height = resolution.height;
		} else {
			newConstraint.video = true;
		}

		newConstraint.audio = true;
		return newConstraint;
	}

	function setDevice(type, select, devices) {
		select.innerHTML = "";

		if (type === "audio" && devices.length === 0) {
			const option = document.createElement("option");
			option.value = "";
			option.textContent = "No Source Available";
			select.appendChild(option);
		} else {
			devices.forEach(function (device) {
				const option = document.createElement("option");
				option.textContent =
					device.label || `${type} ${select.options.length + 1}`;
				option.value = device.deviceId;
				select.appendChild(option);
			});
		}

		if (select.options.length > 0) {
			select.selectedIndex = 0;
		}
	}

	function resetMessages() {
		if (messageContainer) {
			messageContainer.innerHTML = "";
		}

		clearInterval(frameCalculatorTimer);
		frameCalculatorTimer = null;
	}

	function showMessage(message, type = "info") {
		if (!messageContainer) return;

		const messageElement = document.createElement("div");
		messageElement.className = type + "-message";
		messageElement.textContent = message;

		messageContainer.innerHTML = "";
		messageContainer.appendChild(messageElement);

		if (type === "success" || type === "info") {
			setTimeout(() => {
				messageElement.remove();
			}, 5000);
		}
	}

	function updateStatus(status) {
		if (!statusIndicator || !statusText) return;

		statusIndicator.classList.remove(
			"connected",
			"disconnected",
			"connecting"
		);

		switch (status) {
			case "connected":
				statusIndicator.classList.add("connected");
				statusText.textContent = "Connected - Broadcasting Live";
				break;
			case "connecting":
				statusIndicator.classList.add("connecting");
				statusText.textContent = "Connecting...";
				break;
			case "disconnected":
			default:
				statusIndicator.classList.add("disconnected");
				statusText.textContent = "Not Broadcasting";
				break;
		}
	}

	function createInput( shouldAutoStart = false ) {
		if (streamingButton) {
			streamingButton.disabled = true;
		}

		if (input) {
			input.remove();
			input = null;
		}

		resetMessages();

		input = OvenLiveKit.create({
			callbacks: {
				error: function (error) {
					let errorMessage = "";

					if (error.message) {
						errorMessage = error.message;
					} else if (error.name) {
						errorMessage = error.name;
					} else {
						errorMessage = error.toString();
					}

					if (errorMessage === "OverconstrainedError") {
						errorMessage =
							"The input device does not support the specified resolution or frame rate.";
					}

					resetMessages();
					showMessage(errorMessage, "error");

					if ( shouldAutoStart) {
						considerReconnect = false;
					}
				},
				connectionClosed: function (type, event) {
					console.log("Connection closed:", type, event);
					streamingStarted = false;
					updateStatus("disconnected");

					if (streamingButton) {
						streamingButton.classList.remove("hidden");
						streamingButton.disabled = false;
					}
					if (stopButton) {
						stopButton.classList.add("hidden");
					}

					if (considerReconnect && !pendingReconnect) {
						console.log('connection closed, attempting to reconnect');
						attemptReconnect();
					} else {
						console.log('connection closed, not reconnecting');
						updateInputState(false);
					}
				},
				iceStateChange: function (state) {
					console.log("ICE state changed:", state);
					if ( state === 'connected' ) {
						showMessage("Broadcast started successfully");
					}

					if (state === "disconnected" && considerReconnect) {
						streamingStarted = false;
						updateStatus("disconnected");

						if (considerReconnect && !pendingReconnect) {
							console.log('connection closed, attempting to reconnect from ice state change');
							showMessage("Connection lost, attempting to reconnect...", "info");
							attemptReconnect();
						} else {
							showMessage(
								"Connection failed. Please check your network settings.",
								"error"
							);
						}
					}
				},
			},
		});

		input.attachMedia(videoElement);

		if (videoSourceSelect.value) {
			if (videoSourceSelect.value === "displayCapture") {
				input
					.getDisplayMedia(getDisplayConstraints())
					.then(function (stream) {
						localStream = stream;
						if (streamingButton) {
							streamingButton.disabled = false;
						}

						if ( shouldAutoStart && considerReconnect ) {
							startStreaming(true);
						}
					})
					.catch(function (error) {
						console.error('Failed to get display media:', error);
						if ( shouldAutoStart ) {
							showMessage('Failed to access screen sharing.', 'error');
							considerReconnect = false;
							updateInputState(false);
						}
					});
			} else {
				input
					.getUserMedia(getUserConstraints())
					.then(function (stream) {
						localStream = stream;
						if (streamingButton) {
							streamingButton.disabled = false;
						}

						if ( shouldAutoStart && considerReconnect ) {
							startStreaming(true);
						}
					})
					.catch(function (error) {
						console.error('Failed to get user media:', error);
						if ( shouldAutoStart ) {
							showMessage('Failed to access camera/microphone.', 'error');
							considerReconnect = false;
							updateInputState(false);
						}
					});
			}
		}
	}

	function startStreaming( isReconnect = false) {
		// make an ajax call to check if the channel is active and if the user has quota
		const channelCheckPromise = checkChannelStatus(wpstream_broadcaster_vars.channel_id);
		const channelUserQuotaPromise = checkUserQuota();

		Promise.all([channelCheckPromise, channelUserQuotaPromise])
			.then(function (results) {
				const channelActive = results[0];
				const userQuotaValid = results[1];

				if (!channelActive) {
					resetStreamingUI();
					considerReconnect = false;
					return; // Channel is not active, do not proceed with streaming
				}

				if (!userQuotaValid) {
					resetStreamingUI();
					considerReconnect = false;
					return; // User quota is not valid, do not proceed with streaming
				}

				proceedWithStreaming(isReconnect);
			});
	}

	function updateInputState(state) {
		videoSourceSelect.disabled = state;
		audioSourceSelect.disabled = state;
		videoResolutionSelect.disabled = state;
		streamingButton.disabled = state;
	}

	function stopStreaming() {
		streamingStarted = false;
		considerReconnect = false; // user-initiated stop should cancel auto-reconnect
		if (pendingReconnect && pendingReconnectTimeout) {
			clearTimeout(pendingReconnectTimeout);
			pendingReconnect = false;
			pendingReconnectTimeout = null;
		}
		updateStatus("disconnected");

		if (streamingButton) {
			streamingButton.classList.remove("hidden");
		}
		if (stopButton) {
			stopButton.classList.add("hidden");
		}

		if (input) {
			input.stopStreaming();
			createInput();
		}

		showMessage("Broadcasting stopped", "info");
		updateInputState(false);
	}

	function attemptReconnect() {
		console.log("attemptReconnect()");

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

		// Show a reconnecting state and allow user to cancel via Stop button
		updateStatus("connecting");
		showMessage("Disconnected. Reconnecting in 5 seconds...", "info");
		updateInputState(true);

		pendingReconnect = true;
		pendingReconnectTimeout = setTimeout(function () {
			pendingReconnect = false;
			pendingReconnectTimeout = null;
			if (considerReconnect) {
				checkChannelStatus(wpstream_broadcaster_vars.channel_id)
					.then(function(channelActive) {
						if (channelActive && considerReconnect) {
							console.log("Channel is active, proceeding with reconnect...");
							input.stopStreaming();
							setTimeout(function () {
								createInput(true);
							}, 15000);
						} else {
							console.log("Channel is not active, cannot reconnect.");
							considerReconnect = false;
							showMessage('Channel is no longer active. Broadcasting stopped');
							resetStreamingUI();
						}
					})
					.catch(function (error) {
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

	function toggleVideo(enabled) {
		let stream = localStream;

		if ( enabled ) {
			document.getElementById('video-off').style.display = 'inline';
			document.getElementById('video-on').style.display = 'none';
		} else {
			document.getElementById('video-on').style.display = 'inline';
			document.getElementById('video-off').style.display = 'none';
		}

		if (!stream && videoElement && videoElement.srcObject) {
			stream = videoElement.srcObject;
		}

		if (stream) {
			const videoTracks = stream.getVideoTracks();

			videoTracks.forEach((track) => {
				track.enabled = enabled;
			});

			showMessage(enabled ? "Video enabled" : "Video disabled", "info");
		}
	}

	function toggleAudio(enabled) {
		let stream = localStream;

		if ( enabled ) {
			document.getElementById('audio-off').style.display = 'inline';
			document.getElementById('audio-on').style.display = 'none';
		} else {
			document.getElementById('audio-on').style.display = 'inline';
			document.getElementById('audio-off').style.display = 'none';
		}

		if (!stream && videoElement && videoElement.srcObject) {
			stream = videoElement.srcObject;
		}

		if (stream) {
			const audioTracks = stream.getAudioTracks();

			audioTracks.forEach((track) => {
				track.enabled = enabled;
			});

			showMessage(enabled ? "Audio enabled" : "Audio disabled", "info");
		}
	}

	// Event listeners
	if (streamingButton) {
		streamingButton.addEventListener("click", function () {
			if (!streamingStarted) {
				startStreaming();
			}
		});
	}

	if (stopButton) {
		stopButton.addEventListener("click", function () {
			// If a reconnect is pending, treat this as "Cancel Broadcast"
			if (pendingReconnect) {
				if (pendingReconnectTimeout) {
					clearTimeout(pendingReconnectTimeout);
					pendingReconnectTimeout = null;
				}
				pendingReconnect = false;
				considerReconnect = false;
				updateStatus("disconnected");
				showMessage("Broadcast stopped", "info");
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

			if (streamingStarted) {
				stopStreaming();
			}
		});
	}

	// Device change listeners
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

	if (videoToggle) {
		videoToggle.addEventListener("click", function () {
			videoEnabled = !videoEnabled;
			toggleVideo(videoEnabled);
		});
	}

	if (audioToggle) {
		audioToggle.addEventListener("click", function () {
			audioEnabled = !audioEnabled;
			toggleAudio(audioEnabled);
		});
	}

	function init() {
		if (allDevices) {
			setDevice("video", videoSourceSelect, allDevices.videoinput);
			setDevice("audio", audioSourceSelect, allDevices.audioinput);
		}

		createInput();
	}

	// Initialize - get all devices first
	OvenLiveKit.getDevices()
		.then(function (devices) {
			allDevices = devices;
			init();
		})
		.catch(function (error) {
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

	function changeErrorMessage(message) {
		console.log(wpstream_broadcaster_vars);
		switch (message) {
			case "No input devices were found.":
				showMessage(
					wpstream_broadcaster_vars.no_video_audio_access,
					"error"
				);
				break;
			case "Can not find Audio devices":
				showMessage(wpstream_broadcaster_vars.no_audio_access, "error");
				break;
			case "Can not find Video devices":
				showMessage(wpstream_broadcaster_vars.no_video_access, "error");
		}
	}

	function checkChannelStatus(channelId) {
		return new Promise((resolve, reject) => {
			if (!wpstream_broadcaster_vars.ajax_url) {
				resolve(true);
				return;
			}

			jQuery.ajax({
				url: wpstream_broadcaster_vars.ajax_url,
				type: 'POST',
				data: {
					action: 'wpstream_check_event_status',
					channel_id: channelId,
				},
				success: function(response) {
					try {
						const parsedResponse = JSON.parse(response);
						if (parsedResponse.status === 'active') {
							resolve(true);
						} else {
							showMessage(wpstream_broadcaster_vars.channel_off, 'error');
							resolve(false);
						}
					} catch (e) {
						console.error('Error parsing response:', e);
						showMessage('Error checking channel status', 'error');
						reject(false);
					}
				},
				error: function(xhr, status, error) {
					console.error('Error checking channel status:', error);
					showMessage('Error checking channel status: ' + error, 'error');
					reject(false);
				}
			})
		})
	}

	function checkUserQuota() {
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
						if (parsedResponse.available_data_mb > 0) {
							resolve(true);
						} else {
							showMessage('Error checking quota', 'error');
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
		})
	}

	function proceedWithStreaming(isReconnect) {
		streamingStarted = true;
		// If a reconnect was pending, cancel it as we're actively starting now
		if (pendingReconnect && pendingReconnectTimeout) {
			clearTimeout(pendingReconnectTimeout);
			pendingReconnect = false;
			pendingReconnectTimeout = null;
		}

		updateStatus("connecting");

		if (streamingButton) {
			streamingButton.classList.add("hidden");
		}
		if (stopButton) {
			stopButton.classList.remove("hidden");

		}

		if (input && whipUrl) {
			let connectionConfig = {};

			// Begin streaming; mark that we should auto-reconnect on unexpected disconnects
			considerReconnect = true;
			input.startStreaming(whipUrl, connectionConfig);
			updateStatus("connected");
			if ( isReconnect ) {
				console.log('Reconnected successfully!');
				showMessage("Reconnected successfully!", "success");
			}
			updateInputState(true);
		} else {
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
