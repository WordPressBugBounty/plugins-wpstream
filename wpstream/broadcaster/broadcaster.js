/** Set broadcaster visibility through CSS classes, retaining layout display modes. */
function wpstreamBroadcasterDisplay(element, display) {
    element.classList.remove('wpstream-bc-hidden', 'wpstream-bc-display-flex', 'wpstream-bc-display-block', 'wpstream-bc-display-inline');
    if (display === 'none') element.classList.add('wpstream-bc-hidden');
    else if (['flex', 'block', 'inline'].includes(display)) element.classList.add('wpstream-bc-display-' + display);
}
/**
 * Plugin-owned browser/external-encoder broadcaster, copied from the portal
 * module on 2026-10-28. Hosts use window.wpstreamPortal.broadcaster and root
 * events. Plugin changes: explicit media opt-in, local QR generation, and
 * the statistics event. No portal navigation or API dependencies.
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
 * @property {string} insecure_origin - Message when the page has no camera API (plain http origin)
 * @property {string} channel_off - Message when channel is off
 */
/* global wpstream_broadcaster_vars */
// Debug logger â€” no-ops in production. window.WPSTREAM_DEBUG is set from
// PHP WP_DEBUG in functions.php (false on prod). Keeps broadcaster traces
// available locally without spamming the production console.
const log = function () { if ( window.WPSTREAM_DEBUG ) { console.log.apply( console, arguments ); } };
document.addEventListener("DOMContentLoaded", function () {
	// Global variables
	let allDevices = null;
	let input = null;
	let streamingStarted = false;
	// The baker channel id this browser is actively publishing via WHIP, or 0
	// when no browser broadcast is running. Captured at go-live (the channel we
	// resolve in startStreaming) and cleared everywhere streamingStarted is
	// cleared. It lets the state machine tell "the SELECTED channel is the one
	// we're publishing" (show LIVE + End Broadcast) apart from "a broadcast runs
	// on a DIFFERENT channel" (show the selected channel's own state, Start
	// disabled). config.channel_id follows the dropdown, so it alone cannot make
	// that distinction. Its only consumer (selectedIsPublishing) also requires
	// streamingStarted, so a stale value while idle is inert.
	let publishingChannelId = 0;
	let publishingChannelDomain = '';
	let broadcastTarget = null;
	// Status of the channel currently SELECTED in the dropdown (the one the user
	// is LOOKING AT) â€” drives the channel-state readout. Distinct from the global
	// config.channel_status, which tracks the PUBLISHING channel for the reconnect
	// gate and is kept fresh by the polls even when the user has switched away.
	// Without this split, a background poll for the publishing channel paints
	// "channel: active" onto a different, stopped channel the user is viewing.
	let viewedChannelStatus = '';
	// AUTHORITATIVE global "a browser start is in flight" lifecycle signal,
	// decoupled from the cosmetic .wpstream_portal_broadcaster--turning-on
	// wrapper class. The class is now cleared per-VIEW (clearTurningOnVisuals)
	// while the start is still running on another channel, so it can no longer
	// be read as the lifecycle truth â€” this boolean is. Set true at turn-on
	// start (setTurningOnUI(true)) and back to false on go-live / fail / cancel
	// (setTurningOnUI(false)). Combined with publishingChannelId it tells "the
	// SELECTED channel is the one turning on" (selectedIsTurningOn) apart from
	// "a start runs on a DIFFERENT channel" (browserBusyElsewhere).
	let bcTurningOn = false;
	let frameCalculatorTimer = null;
	let totalVideoFrames = 0;
	let whipUrl = null;
	let videoEnabled = true;
	let audioEnabled = true;
	let audioLevelContext = null;
	let audioLevelAnalyser = null;
	let audioLevelSource = null;
	let audioLevelFrame = null;
	let localStream = null;
	let considerReconnect = false; // set true after a successful start to allow auto-reconnects
	let pendingReconnect = false; // true while waiting to reconnect
	let pendingReconnectTimeout = null; // timeout handle for scheduled reconnect
	const reconnectDelayMs = 10000; // 10s delay before attempting to reconnect
	const BROADCASTER_SESSION_COOKIE = 'broadcasterSessionId';

	// ------------------------------------------------------------------
	// Turn-on flow state â€” owned by the turn-on helpers
	// (requestLiveUri / pollEventReady / cancelTurnOn)
	// per broadcaster-turn-on-flow.html "broadcaster.js â€” module-scope
	// additions" notes block. Declared at module scope (inside the IIFE)
	// because the cancel path needs to abort whatever Phase 2 / Phase 3
	// activity is in flight from a different click handler:
	//
	//   turnOnPollInterval   â€” handle returned by setInterval() for the
	//     10s pollEventReady() loop. Used by both the loop's own
	//     "status === active" branch (self-clear on success) and by
	//     cancelTurnOn() (clear-from-the-outside on user cancel).
	//
	//   turnOnAbortController â€” AbortController wired into the Phase 2
	//     fetch() call inside requestLiveUri(). Lets cancelTurnOn() abort
	//     an in-flight give_me_live_uri XHR. A fresh controller is created
	//     at the top of every requestLiveUri() invocation; cleared (set to
	//     null) once the call resolves or the cancel path tears it down.
	// ------------------------------------------------------------------
	let turnOnPollInterval = null;   // setInterval handle for Phase 3 poll loop.
	let turnOnAbortController = null; // AbortController for the in-flight Phase 2 fetch.
	let turnOnTimeoutId = null;
	let turnOnGeneration = 0;
	let mediaGeneration = 0;
	let maintenancePollInterval = null; // setInterval handle for the post-live maintenance poll (plugin parity).
	const TURN_ON_TIMEOUT_MS = 120000;

	// ------------------------------------------------------------------
	// Cycle #6d â€” turning-on UI state machine.
	// Preserved start-button label captured the first time setTurningOnUI(true)
	// fires so we can restore the original "Start Broadcast" text when the
	// turning-on state clears. Captured once (not on every entry) because
	// re-entering turning-on while we're already showing "Startingâ€¦" would
	// otherwise clobber the original label with the in-progress one.
	// ------------------------------------------------------------------
	let startButtonOriginalLabel = null;

	// Opportunistic pre-stream preview. Distinct from `localStream` (which is
	// owned by OvenLiveKit's getUserMedia call inside createInput()): this
	// stream is fired off on page load when the page already has camera/mic
	// permission, so the user sees themselves in the preview pane BEFORE
	// clicking Start Broadcast. Released the moment we hand off to the real
	// streaming path so we never compete with OvenLiveKit for the same
	// hardware device. Also released + re-attached when the device dropdowns
	// change while not yet broadcasting.
	let previewStream = null;

	// Browser-mode camera/mic availability gate (requirement #7).
	// True once a getUserMedia attempt for the browser-broadcast preview has
	// been DENIED (NotAllowedError / PermissionDeniedError), found NO device
	// (NotFoundError) or otherwise failed; cleared the moment a getUserMedia
	// acquisition succeeds. Read by enableStartButton() to keep the Start
	// button disabled while we have no usable camera/mic in Browser mode.
	// Irrelevant to External-encoder mode (that path needs no local camera),
	// so the gate in enableStartButton() is scoped to !externalEncoderMode.
	let cameraAccessDenied = false;

	// Preview acquisition this session: initial permission request or a preview/start action.
	let cameraOptedIn = false;
	let cameraPermissionsChecked = false;
	let cameraPermissionsGranted = false;
	let pendingWhipAttempt = null;

	function clearWhipAttempt() {
		if (!pendingWhipAttempt) return;
		clearTimeout(pendingWhipAttempt.timer);
		window.removeEventListener('unhandledrejection', pendingWhipAttempt.onRejection);
		pendingWhipAttempt = null;
	}

	function failWhipAttempt(attempt) {
		if (!attempt || pendingWhipAttempt !== attempt || input !== attempt.input) return;
		clearWhipAttempt();
		considerReconnect = false;
		streamingStarted = false;
		publishingChannelId = 0;
		cancelTurnOn();
		stopQosSampler();
		// Close only this failed local publisher; never stop the server channel.
		try {
			Promise.resolve(attempt.input.stopStreaming()).catch(function () {});
		} catch (error) { /* The failed handshake may have no resource to delete. */ }
		resetStreamingUI();
		showMessage(config.broadcast_connection_failed || 'Could not connect to the streaming server. The broadcast did not start. Please try again.', 'error');
	}

	function watchWhipAttempt(publisher) {
		clearWhipAttempt();
		var attempt = { input: publisher, timer: null, onRejection: null };
		// OvenLiveKit 1.5.8 does not return its startWhip promise. Its fetch
		// rejection bypasses callbacks.error; scope this fallback to that SDK
		// and only while our handshake is pending. Do not swallow other errors.
		attempt.onRejection = function (event) {
			var stack = event.reason && event.reason.stack;
			if (stack && /ovenlivekit/i.test(String(stack))) failWhipAttempt(attempt);
		};
		pendingWhipAttempt = attempt;
		window.addEventListener('unhandledrejection', attempt.onRejection);
		attempt.timer = setTimeout(function () { failWhipAttempt(attempt); }, 30000);
		return attempt;
	}

	function setSessionCookie(name, value) {
		document.cookie = name + '=' + encodeURIComponent(value) + '; path=/';
	}

	function getCookie(name) {
		const value = `; ${document.cookie}`;
		const parts = value.split(`; ${name}=`);
		if (parts.length === 2) {
			return decodeURIComponent(parts.pop().split(';').shift());
		}
		return '';
	}

	function getPopupPayloadFromWindowName() {
		let payload = {};
		try {
			if (window.name) {
				const parsed = JSON.parse(window.name);
				if (parsed && parsed.wpstream_onboarding_popup_payload) {
					payload = parsed.wpstream_onboarding_popup_payload;
					if (payload.session_id) {
						setSessionCookie(BROADCASTER_SESSION_COOKIE, payload.session_id);
					}
				}
			}
		} catch (e) {
			payload = {};
		}

		if (!payload.session_id) {
			const cookieSessionId = getCookie(BROADCASTER_SESSION_COOKIE);
			if (cookieSessionId) {
				payload = {
					session_id: cookieSessionId,
				};
			}
		}

		window.name = '';
		return payload;
	}

	const popupPayload = getPopupPayloadFromWindowName();
	const popupSessionId = popupPayload && popupPayload.session_id ? popupPayload.session_id : '';
	log('Popup session ID:', popupSessionId);

	function trackOnboardingStepSafe(action, step = 'wpstream_broadcaster', elementType = '', elementName = '', trackingSessionId = '', props = {}) {
		if (typeof wpstream_track_onboarding_step === 'function') {
			wpstream_track_onboarding_step(action, step, elementType, elementName, trackingSessionId, props);
		}
	}

	// ==================================================================
	// Go-live funnel telemetry (ADDITIVE â€” see expertise.yml landmines).
	// ------------------------------------------------------------------
	// This block instruments the browser go-live funnel WITHOUT touching
	// the bcState state machine, the teardown clusters, the
	// cameraAccessDenied gate or any control flow. It opens a single
	// attempt "span" at broadcast_start_clicked and carries one
	// attempt_id + per-phase timing marks through the
	// camera â†’ quota â†’ event-start â†’ whip â†’ ICE milestones so the
	// server can stitch one funnel together. EVERY emit routes through
	// trackOnboardingStepSafe (the one liftable telemetry seam) â€” never
	// window.wpstreamTrack directly â€” to preserve liftability.
	// ==================================================================
	// Current attempt id. One per Start click; SURVIVES an auto-reconnect
	// (reconnect is the same attempt); nulled after stream_stopped and
	// after a TERMINAL broadcast_error.
	let currentAttemptId = null;
	// Per-phase timing marks (Date.now() ms). value_ms on each milestone
	// is the delta since the PREVIOUS milestone, not an absolute duration.
	let tRequested = 0; // set at event_start_requested (Phase 2 /channel/start POST)
	let tAccepted  = 0; // set at event_start_accepted  (Phase 2 resolved OK)
	let tWhip      = 0; // set at whip_ready            (Phase 3 active + whipUrl)

	// mode dimension for every funnel event: which ingest path is active.
	// externalEncoderMode is declared later in this same scope; this
	// helper only reads it at call time (runtime), so no TDZ concern.
	function bcTelemetryMode() {
		return externalEncoderMode ? 'external' : 'browser';
	}

	// Fresh client attempt id. MUST match ^[A-Za-z0-9_-]{1,64}$ (the
	// server rejects the field otherwise): 'att_' + base36 timestamp +
	// 6 base36 random chars.
	function newBcAttemptId() {
		return 'att_' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 8 );
	}

	// Thin funnel emit â€” one call shape for every milestone. Always goes
	// through trackOnboardingStepSafe so the broadcaster stays liftable.
	function emitFunnel( action, props ) {
		trackOnboardingStepSafe( action, 'wpstream_broadcaster', '', '', popupSessionId, props || {} );
	}

	// ==================================================================
	// Stream B â€” live encoder-side QoS sampler (ADDITIVE, liftable seam).
	// ------------------------------------------------------------------
	// While a BROWSER broadcast is live, sample the encoder's OWN outbound
	// WebRTC video stats (bitrate + fps) every ~10s and beacon one sample
	// per window to the Stream B ingest endpoint. This yields a per-attempt
	// quality time-series that Stream C can join back to the funnel via
	// attempt_id. Viewers is NOT available encoder-side (the backend / SNS
	// supplies it later), so it is deliberately omitted from the payload.
	//
	// Liftability: the transport goes through the trackQosSafe() seam â€”
	// mirroring trackOnboardingStepSafe â€” so the streaming logic NEVER calls
	// window.wpstreamTrackQos directly. When telemetry.js is absent the seam
	// is a silent no-op and the broadcaster still lifts cleanly.
	// ==================================================================
	// setInterval handle for the ~10s sampler (null when not sampling).
	let qosSampleInterval  = null;
	// Baselines carried between samples so bitrate/fps are computed from the
	// DELTA since the previous window rather than the absolute RTP counters.
	let qosSampleLastBytes  = 0; // outbound video bytesSent at the previous sample.
	let qosSampleLastFrames = 0; // outbound video framesEncoded at the previous sample.
	let qosSampleLastTs     = 0; // Date.now() at the previous sample (0 = no baseline yet).

	/**
	 * trackQosSafe( props ) â€” the ONE liftable Stream B transport seam.
	 *
	 * Mirrors trackOnboardingStepSafe: guarded + never throws. Forwards the
	 * QoS sample to window.wpstreamTrackQos (telemetry.js, owned by
	 * telemetry-builder â€” do NOT touch), which POSTs to
	 * wp_ajax_wpstream_track_qos with the shared nonce. Going through this
	 * seam (instead of calling window.wpstreamTrackQos directly from the
	 * streaming logic) is what keeps the broadcaster liftable.
	 *
	 * @param {Object} props  { attempt_id, channel_id, bitrate_kbps, fps, rebuffering, session_id }
	 * @return {void}
	 */
	function trackQosSafe( props ) {
		try {
			if ( typeof window.wpstreamTrackQos === 'function' ) {
				window.wpstreamTrackQos( props || {} );
			}
		} catch ( e ) {
			// Telemetry must never break a live broadcast â€” swallow.
		}
	}

	/**
	 * Take ONE encoder-side QoS sample and beacon it via trackQosSafe().
	 *
	 * Producer: the live OvenLiveKit RTCPeerConnection (input.peerConnection).
	 * Reads getStats(), finds the outbound VIDEO RTP report, and derives:
	 *   - bitrate_kbps  from the bytesSent delta since the previous sample.
	 *   - fps           from report.framesPerSecond when present, else the
	 *                   framesEncoded delta across the window.
	 *   - rebuffering   1 when the window shows a stall / zero bitrate, else 0.
	 * The FIRST call after start only SEEDS the baseline (no prior window to
	 * diff) and beacons nothing, so the first real sample lands one tick later.
	 *
	 * @return {void}
	 */
	function sampleQosOnce() {
		// Producer handle â€” the live publish peer connection. Absent once the
		// stream is torn down / reconnecting, in which case there is nothing to
		// sample (the sampler is stopped on those paths anyway).
		var pc = ( input && input.peerConnection ) ? input.peerConnection : null;
		if ( ! pc || typeof pc.getStats !== 'function' ) {
			return;
		}
		pc.getStats( null ).then( function ( stats ) {
			// Pull the outbound VIDEO report's counters. Chrome/Edge label the
			// media kind as `kind`, older impls as `mediaType` â€” accept either.
			var bytesSent = null, framesEncoded = null, fpsReported = null;
			stats.forEach( function ( report ) {
				if ( report.type === 'outbound-rtp' && ( report.kind === 'video' || report.mediaType === 'video' ) ) {
					if ( typeof report.bytesSent === 'number' )       { bytesSent     = report.bytesSent; }
					if ( typeof report.framesEncoded === 'number' )   { framesEncoded = report.framesEncoded; }
					if ( typeof report.framesPerSecond === 'number' ) { fpsReported   = report.framesPerSecond; }
				}
			} );
			// No outbound video report yet (handshake still settling) â€” wait for
			// the next tick rather than beacon a meaningless sample.
			if ( bytesSent === null ) {
				return;
			}
			var now         = Date.now();
			var hadBaseline = ( qosSampleLastTs > 0 );
			var deltaMs     = hadBaseline ? ( now - qosSampleLastTs ) : 0;
			var bitrateKbps = 0;
			var fps         = 0;
			if ( hadBaseline && deltaMs > 0 ) {
				// bytes * 8 / ms == kbps (kilobits per second).
				var deltaBytes = bytesSent - qosSampleLastBytes;
				bitrateKbps = ( deltaBytes > 0 ) ? Math.round( ( deltaBytes * 8 ) / deltaMs ) : 0;
				// Prefer the browser-reported instantaneous fps; else derive it
				// from the framesEncoded delta across the window.
				if ( fpsReported !== null ) {
					fps = Math.round( fpsReported );
				} else if ( framesEncoded !== null ) {
					var deltaFrames = framesEncoded - qosSampleLastFrames;
					fps = ( deltaFrames > 0 ) ? Math.round( deltaFrames / ( deltaMs / 1000 ) ) : 0;
				}
			}
			// Advance the baseline for the next window.
			qosSampleLastBytes = bytesSent;
			if ( framesEncoded !== null ) { qosSampleLastFrames = framesEncoded; }
			qosSampleLastTs    = now;
			// Seeding sample â€” no prior window, so nothing meaningful to send.
			if ( ! hadBaseline ) {
				return;
			}
			// rebuffering: a stall / zero-bitrate window is treated as a rebuffer.
			var rebuffering = ( bitrateKbps <= 0 ) ? 1 : 0;
			// Beacon through the liftable seam. attempt_id MUST be the funnel's
			// currentAttemptId so Stream C can join QoS to the attempt; viewers
			// is intentionally omitted (not available encoder-side).
			trackQosSafe( {
				attempt_id:   currentAttemptId,
				channel_id:   publishingChannelId || resolveSelectedChannelId(),
				bitrate_kbps: bitrateKbps,
				fps:          fps,
				rebuffering:  rebuffering,
				session_id:   popupSessionId
			} );
		} ).catch( function () {
			// getStats() rejects on a closing/closed PC â€” ignore; the sampler is
			// stopped on teardown so this only races a just-ended stream.
		} );
	}

	/**
	 * Start the ~10s Stream B sampler. Idempotent (no-op if already running).
	 * Called when the browser stream reaches live (alongside ice_connected).
	 * Seeds the baseline immediately so the first interval tick yields a real
	 * delta; the interval then beacons one sample every ~10s.
	 *
	 * @return {void}
	 */
	function startQosSampler() {
		if ( qosSampleInterval ) {
			return;
		}
		// Fresh baselines for this live window.
		qosSampleLastBytes  = 0;
		qosSampleLastFrames = 0;
		qosSampleLastTs     = 0;
		// Seed immediately (this call beacons nothing â€” it only sets baselines).
		sampleQosOnce();
		qosSampleInterval = setInterval( sampleQosOnce, 10000 );
	}

	/**
	 * Stop the Stream B sampler and clear its interval + baselines. Idempotent
	 * and safe to call when nothing is running. Called on stream_stopped, on
	 * End Broadcast (stopStreaming) and on any disconnect / reconnect / teardown
	 * so no interval dangles and no sampling continues after the stream ends.
	 *
	 * @return {void}
	 */
	function stopQosSampler() {
		if ( qosSampleInterval ) {
			clearInterval( qosSampleInterval );
			qosSampleInterval = null;
		}
		qosSampleLastBytes  = 0;
		qosSampleLastFrames = 0;
		qosSampleLastTs     = 0;
	}

	// ==================================================================
	// Single config / AJAX seam (refactor #2).
	// ------------------------------------------------------------------
	// Before this block the localized config blob was read ad-hoc in ~15
	// places â€” each re-implementing the
	// `typeof wpstream_broadcaster_vars !== 'undefined' && â€¦` guard â€” and
	// the AJAX response-envelope parse (`(typeof x === 'string') ? JSON.parse(x) : x`)
	// was duplicated across 4 request sites split between two HTTP stacks
	// (jQuery.ajax + native fetch). This block centralises all three:
	//
	//   config   â€” ONE reference to the localized vars blob. When the page
	//              localised wpstream_broadcaster_vars, `config` IS that same
	//              object (a reference, NOT a clone) so the existing in-place
	//              writes â€” `wpstream_broadcaster_vars.channel_id = â€¦`,
	//              applyInfoFromResponse(), the channelSelect dropdown
	//              refresh â€” all still mutate the one shared object. When the
	//              blob is absent `config` falls back to `{}` so a host page
	//              without the payload stays a silent no-op (every read returns
	//              undefined, never throws).
	//
	//   ACTIONS  â€” the PORTABILITY seam: logical-name â†’ admin-ajax action
	//              string. Values are sourced from `config.actions` when the
	//              host localises an override map, else the current
	//              `wpstream_dashboard_*` literals (the plugin fork uses bare
	//              `wpstream_*`). Centralising does NOT rename â€” the values
	//              emitted today are byte-identical to the old inline strings.
	//
	//   ajaxPost â€” the ONE request primitive: form-encodes (URLSearchParams,
	//              application/x-www-form-urlencoded â€” identical to the old
	//              fetch calls), attaches `action` + the start-event nonce, does
	//              the res.text() â†’ defensive-parse step, and resolves the parsed
	//              object (or rejects on network / parse failure). All 4 parse
	//              sites + the 2 former jQuery.ajax sites route through it. The
	//              POST body shape the PHP handlers receive (action, nonce,
	//              channel_id, â€¦) is unchanged so broadcaster-ajax.php keeps
	//              accepting them.
	// ==================================================================
	// True when the host page actually localised the vars blob. A few call
	// sites need to distinguish "blob present but field unset" from "no blob
	// at all" (e.g. writing back into the blob), so capture presence once.
	const configPresent = ( typeof wpstream_broadcaster_vars !== 'undefined' && !! wpstream_broadcaster_vars );
	const config = configPresent ? wpstream_broadcaster_vars : {};
	var recordingSaving = false;
	var recordingLoading = false;
	var recordingRevision = 0;
	var recordingError = '';

	/** Paint all source panels from the selected channel, never from a hidden tab's input. */
	function renderRecording() {
		var on = [true, 1, '1', 'true'].indexOf(config.record) !== -1;
		var copy = config.recording_strings || {};
		var locked = recordingSaving || recordingLoading || !resolveSelectedChannelId() ||
			viewedChannelStatus !== 'stopped' || channelIsExternalLive() || selectedIsPublishing() || selectedIsTurningOn();
		document.querySelectorAll('.wpstream_portal_broadcaster [data-wpstream-portal-bc-recording]').forEach(function (card) {
			var title = on ? copy.on : copy.off;
			var input = card.querySelector('input');
			input.checked = on;
			input.disabled = locked;
			input.setAttribute('aria-label', title);
			card.querySelector('[data-wpstream-portal-bc-recording-title]').textContent = title;
			card.querySelector('[data-wpstream-portal-bc-recording-help]').textContent = on ? copy.saved : copy.unsaved;
			card.querySelector('[data-wpstream-portal-bc-recording-error]').textContent = recordingError;
		});
	}

	/** Save a channel option once; stale completions cannot repaint a different selected channel. */
	function saveRecording(event) {
		var input = event.target.closest('[data-wpstream-portal-bc-recording-toggle]');
		if (!input || input.disabled) return;
		var revision = ++recordingRevision;
		var desired = input.checked;
		var previous = config.record;
		config.record = desired;
		recordingSaving = true;
		recordingError = '';
		renderRecording();
		enableStartButton();
		ajaxPost(ACTIONS.save_recording, { channel_id: resolveSelectedChannelId(), record: desired ? '1' : '0' })
			.then(function (response) {
				if (!response.success) throw new Error(response.error || 'UPDATE_FAILED');
				if (revision === recordingRevision) config.record = response.record;
			})
			.catch(function () {
				if (revision === recordingRevision) {
					config.record = previous;
					recordingError = config.recording_strings.failed;
				}
			})
			.finally(function () {
				if (revision !== recordingRevision) return;
				recordingSaving = false;
				renderRecording();
				enableStartButton();
			});
	}

	// Portability fork-point. Logical names map to the action strings the
	// theme's broadcaster-ajax.php registers. Honour a host-supplied override
	// map (config.actions) if present; otherwise fall back to the current
	// literals â€” values UNCHANGED from the old inline strings.
	const ACTIONS = {
		save_recording: ( config.actions && config.actions.save_recording ) || 'wpstream_dashboard_save_recording',
		check_event_status:        ( config.actions && config.actions.check_event_status )        || 'wpstream_dashboard_check_event_status',
		give_me_live_uri:          ( config.actions && config.actions.give_me_live_uri )          || 'wpstream_dashboard_give_me_live_uri',
	};

	/**
	 * Read the page's start-event nonce. Single accessor replacing the
	 * scattered `jQuery('#wpstream_start_event_nonce').val()` /
	 * `document.getElementById(...)` reads. Returns '' when the hidden input
	 * is absent (host page without the broadcaster card) so the POST still
	 * carries a (failing-but-present) `nonce` field rather than `undefined`.
	 *
	 * @return {string} the nonce value, or '' when the input is missing.
	 */
	function getStartEventNonce() {
		const el = document.getElementById( 'wpstream_start_event_nonce' );
		return el ? el.value : '';
	}

	/**
	 * ajaxPost( action, params, opts ) â€” the ONE admin-ajax request primitive.
	 *
	 * Owns: form-encoding (URLSearchParams â†’ application/x-www-form-urlencoded,
	 * matching the old fetch calls exactly), attaching `action` + the
	 * start-event nonce, the res.text() â†’ defensive-parse step, and resolving a
	 * normalized object. The defensive parse â€” `(typeof text === 'string') ?
	 * JSON.parse(text) : text` â€” is the SAME structural guard the 4 former
	 * parse sites used (text is always a string out of res.text(); the guard is
	 * future-proofing against a refactor to res.json()).
	 *
	 * Rejection contract mirrors what callers already handle:
	 *   - missing config.ajax_url        â†’ reject('Broadcaster vars missing â€” ajax_url not localized.')
	 *   - unparseable body               â†’ reject('Could not parse server response.')
	 *   - network error / abort          â†’ reject(err.message | 'Network error.' | 'Aborted.')
	 *
	 * @param  {string} action  logical action string (use an ACTIONS.* value).
	 * @param  {Object} params  extra POST params (channel_id, enabled, â€¦).
	 * @param  {Object} [opts]  { signal } â€” optional AbortSignal for Phase 2.
	 * @return {Promise<object>} resolves with the parsed response object.
	 */
	function ajaxPost( action, params, opts ) {
		opts = opts || {};
		return new Promise( function ( resolve, reject ) {
			// Bail early when the page never localised the AJAX URL â€” a host
			// page without the wp_localize_script() payload cannot reach a
			// handler. Same recognisable string the old callers rejected with.
			if ( ! config.ajax_url ) {
				reject( 'Broadcaster vars missing â€” ajax_url not localized.' );
				return;
			}

			// application/x-www-form-urlencoded body â€” admin-ajax.php reads
			// $_POST, so a JSON body would land in php://input unread.
			var body = new URLSearchParams();
			body.set( 'action', action );
			body.set( 'nonce', getStartEventNonce() );
			// Channel domain â€” part of baker's channel identity
			// (ch_wps_<user>_<domain_hash>_<channel_id>). Attached centrally
			// (like action + nonce) so every channel-facing POST
			// (check_event_status / give_me_live_uri) carries the domain of the
			// currently-selected channel, letting baker resolve channels created
			// under ANY domain the user owns â€” not just wpstream.net. Resolved
			// from the visible dropdown selection at POST time (see
			// resolveSelectedChannelDomain). A caller that passes its own
			// `domain` in params overrides this below.
			body.set( 'domain', resolveSelectedChannelDomain() );
			if ( params ) {
				Object.keys( params ).forEach( function ( key ) {
					body.set( key, String( params[ key ] ) );
				} );
			}

			var fetchOpts = {
				method:      'POST',
				credentials: 'include', // keep WP session cookie attached
				headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:        body.toString(),
			};
			if ( opts.signal ) {
				fetchOpts.signal = opts.signal;
			}

			fetch( config.ajax_url, fetchOpts )
				.then( function ( res ) {
					// Read as text first so the defensive parse works
					// regardless of Content-Type.
					return res.text();
				} )
				.then( function ( text ) {
					var response;
					try {
						response = ( typeof text === 'string' ) ? JSON.parse( text ) : text;
					} catch ( e ) {
						reject( 'Could not parse server response.' );
						return;
					}
					resolve( response );
				} )
				.catch( function ( err ) {
					// Preserve AbortError discrimination for the Phase 2 cancel path.
					if ( err && err.name === 'AbortError' ) {
						reject( 'Aborted.' );
						return;
					}
					reject( ( err && err.message ) ? err.message : 'Network error.' );
				} );
		} );
	}

	// Get WHIP URL from config
	if ( config.whip_url ) {
		whipUrl = config.whip_url;
	}

	// DOM elements
	const videoElement = document.getElementById("localVideo");
	const streamingButton = document.getElementById("startBroadcast");
	const stopButton = document.getElementById("stopBroadcast");
	const cancelStartButton = document.getElementById("cancelBroadcastStart");
	const videoSourceSelect = document.getElementById("videoDevice");
	const videoToggle = document.getElementById("videoToggle");
	const videoResolutionSelect = document.getElementById("videoQuality");
	const audioSourceSelect = document.getElementById("audioDevice");
	const audioToggle = document.getElementById("audioToggle");
	const audioMeter = document.querySelector('[data-wpstream-portal-bc-audio-meter]');
	const audioMeterFill = document.querySelector('[data-wpstream-portal-bc-audio-meter-fill]');
	const audioMeterValue = document.querySelector('[data-wpstream-portal-bc-audio-meter-value]');
	const messageContainer = document.getElementById("messageContainer");
	const statusIndicator = document.getElementById("statusIndicator");
	const statusText = document.getElementById("statusText");
	const liveIndicatorLive = document.getElementById("videoLiveIndicatorLive");
	const liveIndicatorError = document.getElementById("videoLiveIndicatorError");
	const loadSpinner = document.getElementById("wpstream-pre-load-spinner");

	// ------------------------------------------------------------------
	// Dashboard-side hooks â€” Setup / Live side-panel swap + live stats.
	// These are the portal-only additions that sit on top of the cloned
	// plugin runtime. All four are class-/attribute-based (not IDs) so
	// the page can omit them without breaking the original plugin code
	// path. typeof-style guards everywhere â€” every one of these may be
	// null on a host page that doesn't render the dashboard markup.
	// ------------------------------------------------------------------
	const sidePanelSetup     = document.querySelector('.wpstream_portal_bc_side_setup');
	const sidePanelLive      = document.querySelector('.wpstream_portal_bc_side_live');
	// External encoder panel â€” same flex column as setup/live; shown by the
	// encoder-tab click handler when the user picks "External encoder".
	const sidePanelExternal  = document.querySelector('.wpstream_portal_bc_side_external');
	// Single source of truth for the three-way swap. Keys match the values
	// accepted by setBroadcasterSideState() below ('setup' | 'live' | 'external').
	const sidePanels         = {
		setup:    sidePanelSetup,
		live:     sidePanelLive,
		external: sidePanelExternal,
	};
	// Stat value cells are identified by a stable data-attribute (added
	// in broadcaster.php) so the JS does not depend on child order.
	const statDurationEl     = document.querySelector('[data-wpstream-portal-bc-stat="duration"]');
	const statBitrateEl      = document.querySelector('[data-wpstream-portal-bc-stat="bitrate"]');
	// Viewers + Health stat cells, plus the Health dot's icon span (whose
	// modifier class is swapped to recolor the dot). Sourced from the QoS
	// meter socket â€” see startQosStats() / computeHealth() below.
	const statViewersEl      = document.querySelector('[data-wpstream-portal-bc-stat="viewers"]');
	const statHealthEl       = document.querySelector('[data-wpstream-portal-bc-stat="health"]');
	const statHealthIcoEl    = document.querySelector('[data-wpstream-portal-bc-health-ico]');

	// Default placeholder strings shown while not broadcasting. These
	// also get restored when the user clicks End Broadcast. When there is
	// no QoS data, a cell is in one of three states:
	//   NA       - no stream is running (idle channel, no meter URL, after End
	//              Broadcast, after a cancelled or failed start), or the meter
	//              confirms the channel off air. The *_DEFAULT values below are
	//              what every reset restores.
	//   Loading - a stream is starting, or its meter is connecting / connected
	//              with no usable sample for this cell yet.
	//   value    - the received sample, real zeros included, unless the same
	//              frame confirms off air (see renderChannelStats()).
	// Neither placeholder is a real health rating, so applyHealth() keeps its
	// dot NEUTRAL (grey) for both.
	const STATS_NA_LABEL      = config.stats_na_label || 'NA';
	const STATS_LOADING_LABEL = config.stats_loading_label || 'Loading…';
	const DURATION_DEFAULT   = STATS_NA_LABEL;
	const BITRATE_DEFAULT    = STATS_NA_LABEL;
	const VIEWERS_DEFAULT    = STATS_NA_LABEL;
	const HEALTH_DEFAULT     = STATS_NA_LABEL;

	// Internal handle for the per-second duration tick. Cleared on
	// disconnect / teardown to avoid leaks.
	let durationTimerId      = null;
	// Stream start time (Unix ms) taken from the QoS `settings` frame
	// (settings.startTime). Duration is computed as now - qosStartTime so it
	// tracks the real stream start, not when this tab connected. Null until a
	// valid settings frame arrives; held without ticking until an on-air frame
	// confirms the stream; cleared back to null on socket teardown / stop, after
	// which the caller writes the cell's NA or Loading placeholder.
	let qosStartTime         = null;
	// QoS meter socket + its 5s re-request interval. Both cleared on
	// disconnect. The socket is the SINGLE source for ALL live channel stats
	// â€” Viewers, Duration, Bitrate, Health â€” rendered by renderChannelStats()
	// for both browser-broadcast and external-encoder modes (a channel has one
	// set of live stats regardless of ingest).
	let qosSocket            = null;
	let qosPollId            = null;

	// External-encoder mode flag. True while the External tab is the active
	// panel â€” in that mode there is no browser WebRTC peer, so the QoS
	// message handler also derives Bitrate + Duration from bwHistory.
	let externalEncoderMode  = false;

	// True ONLY when the QoS meter has positively confirmed an EXTERNAL RTMP
	// encoder is the live source (streamSource.kind === 'rtmp'). This â€” NOT
	// config.channel_status â€” is the authoritative signal that drives the
	// "Start disabled" gate (my-notes/brodcaster_notes.md line 61). Defaults
	// false so Start stays ENABLED until rtmp is confirmed; reset to false on
	// socket teardown (the source is gone when the socket closes) and on a
	// confirmed off-air frame that reports no rtmp source (OBS stopped).
	let externalRtmpLive     = false;

	// Headline copy of the External panel's encoder-state block. Declared up here
	// because the boot path can reset the block before the player listener below.
	var BC_ENCODER_STATE_COPY = {
		stopped:  { title: 'Waiting for encoder…',  sub: 'Point OBS / Wirecast / vMix at the URL below.' },
		init:     { title: 'Initializing…',         sub: 'Player is loading.' },
		starting: { title: 'Stream starting…',      sub: 'Your encoder is connecting.' },
		onair:    { title: 'Live on air',           sub: 'Stream is active and broadcasting.' },
		paused:   { title: 'Stream paused',         sub: 'Broadcast temporarily paused.' },
		ended:    { title: 'Stream ended',          sub: 'Broadcast has finished.' }
	};

	// Resolution mappings from demo
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

	const displayResolutions = {
		vga: { width: 640, height: 360 },
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
			log(
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
		} else if ( allDevices && allDevices.audioinput && allDevices.audioinput.length ) {
			// A microphone exists but its deviceId is still '' â€” the boot list
			// is enumerated WITHOUT permission (the camera opens only on a user
			// action), and browsers hide device ids until the first grant. Ask
			// for the default microphone so the first acquisition still
			// carries audio.
			newConstraint.audio = true;
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

		const dismissButton = document.createElement("button");
		dismissButton.className = 'dismiss-message';
		dismissButton.innerHTML = '&times;';
		dismissButton.addEventListener('click', function() {
			messageElement.remove();
		});

		messageElement.appendChild(dismissButton);

		messageContainer.innerHTML = "";
		messageContainer.appendChild(messageElement);

		if (type === "success" || type === "info") {
			setTimeout(() => {
				messageElement.remove();
			}, 5000);
		}
	}

	/**
	 * Format a positive number of seconds as HH:MM:SS with leading zeros.
	 * Centralised so both the live-tick and the reset path produce the
	 * same shape. Caller-side guarantee: `seconds >= 0`.
	 */
	function formatDurationHms( seconds ) {
		const total = Math.max( 0, Math.floor( seconds ) );
		const h = Math.floor( total / 3600 );
		const m = Math.floor( ( total % 3600 ) / 60 );
		const s = total % 60;
		const pad = function ( n ) { return n < 10 ? '0' + n : String( n ); };
		return pad( h ) + ':' + pad( m ) + ':' + pad( s );
	}

	/**
	 * Start the 1-second Duration ticker from the stored qosStartTime. Any existing
	 * ticker is cleared first so only one ever runs.
	 *
	 * @return {void}
	 */
	function startDurationTicker() {
		if ( durationTimerId ) {
			clearInterval( durationTimerId );
			durationTimerId = null;
		}
		// Write once immediately so the cell updates without waiting a full
		// second, then tick every second from the stored start time.
		setStatText( 'duration', formatDurationHms( ( Date.now() - qosStartTime ) / 1000 ) );
		durationTimerId = setInterval( function () {
			setStatText( 'duration', formatDurationHms( ( Date.now() - qosStartTime ) / 1000 ) );
		}, 1000 );
	}

	/**
	 * Store the time Duration should count from. Only renderChannelStats() starts
	 * the ticker, on an on-air frame, so a `settings` frame alone never shows a time.
	 *
	 * Sources, both Unix MILLISECONDS: the `settings` frame's startTime and the time
	 * of the latest `onair` sample. A running ticker keeps its start, so a late
	 * settings push cannot move the counter.
	 *
	 * Guard: only a sane positive epoch is stored. A missing, zero, NaN or negative
	 * time is a no-op, leaving any time already stored.
	 *
	 * NOTE: live QoS frames often deliver numeric fields as STRINGS, so the time may
	 * arrive as either a number or a numeric string (e.g. "1781004461852"). We coerce
	 * with Number() before validating so both work.
	 *
	 * @param {(number|string)} startTime  Unix ms, number or numeric string.
	 * @return {void}
	 */
	function setDurationStartFromQos( startTime ) {
		var t = Number( startTime );
		if ( durationTimerId || ! isFinite( t ) || t <= 0 ) {
			return;
		}
		qosStartTime = t;
	}

	/**
	 * Start the live-stats section for a browser WHIP broadcast. A channel has
	 * a SINGLE set of live stats (Viewers, Duration, Bitrate, Health), all
	 * sourced from the chandler QoS meter socket â€” the SAME source the
	 * External-encoder panel uses â€” so this just opens that socket.
	 * renderChannelStats() (the QoS message handler) writes all four cells.
	 * Called from updateStatus() the moment ICE reaches `connected`.
	 *
	 * The cells follow the VIEWED channel, so they only move to Loading when the
	 * viewed channel is the one that just connected. startQosStats() resolves the
	 * other case (the user switched to a different channel during turn-on).
	 */
	function startLiveStatsTimers() {
		log( '[broadcaster] startLiveStatsTimers() â€” QoS-driven channel stats (shared with external mode)' );
		if ( selectedIsPublishing() ) {
			setLiveStatsLoading();
		}
		startQosStats();
	}

	/**
	 * Tear down the live-stats for a browser session and reset the value cells
	 * to their clean-state placeholders. Called on disconnect + on the
	 * End-Broadcast click path.
	 *
	 * The Duration ticker + qosStartTime belong to the QoS SOCKET lifecycle, not
	 * the panel: qosStartTime is seeded by the `settings` frame, which the meter
	 * pushes only ONCE on socket open. So the duration/cell teardown lives INSIDE
	 * the same `! externalEncoderMode` guard that gates stopQosStats(). When the
	 * External panel still owns the socket (externalEncoderMode true â€” e.g. the
	 * user switched tabs mid-stream), we leave the running ticker and cells
	 * alone; it keeps writing both grids and survives the tab switch. Viewers/
	 * Health are reset inside stopQosStats() itself. With no socket on the External
	 * tab (a start cancelled there, for example) no frame can ever replace a
	 * Loading label, so the cells go straight to NA.
	 */
	function stopLiveStatsTimers() {
		log( '[broadcaster] stopLiveStatsTimers() â€” tearing down duration timer + resetting cells', { externalEncoderMode: externalEncoderMode } );

		// Tear down the QoS socket + poll and reset ALL cells â€” BUT only if the
		// External panel isn't still using the socket. When externalEncoderMode
		// is active, the external panel owns the QoS socket and must keep it (and
		// the duration ticker it drives) open after a browser session ends.
		if ( ! externalEncoderMode ) {
			if ( durationTimerId ) {
				clearInterval( durationTimerId );
				durationTimerId = null;
			}
			qosStartTime = null;
			if ( statDurationEl ) setStatText( 'duration', DURATION_DEFAULT );
			if ( statBitrateEl )  setStatText( 'bitrate', BITRATE_DEFAULT );
			stopQosStats();
		} else if ( ! qosSocket ) {
			setLiveStatsPlaceholder( STATS_NA_LABEL );
		}
	}

	/**
	 * Derive a coarse stream-health rating from a QoS `bwHistory` object.
	 *
	 * Mirrors the rules used by the ops liveqos dashboard. Two inputs feed
	 * the rating, each independently coloured red/orange/green:
	 *
	 *   - Bandwidth ratio: how close the actual outbound bitrate
	 *     (`avgBwIn1`) is to the ideal for the source (`idealBwIn`).
	 *   - Keyframe interval (`keyframeInterval`, seconds): too long or too
	 *     short both hurt playback startup / seeking.
	 *
	 * A missing keyframe sample is treated as orange (conservative) rather
	 * than green so a fresh start reads "Good" until the first keyframe
	 * arrives. Any precondition gap (not on air, missing bandwidth data,
	 * non-finite math) collapses to 'Neutral'.
	 *
	 * @param {Object} bwHistory  The bwHistory object from a getQos reply.
	 *                            Each series is an array of {time,val}.
	 * @return {('Excellent'|'Good'|'Fair'|'Poor'|'Neutral')} Health label.
	 */
	function computeHealth( bwHistory ) {
		// Last sample of a {time,val}[] series, or undefined when empty.
		function last( a ) { return ( a && a.length ) ? a[ a.length - 1 ].val : undefined; }

		const onair    = last( bwHistory.onair );
		const avgBwIn1 = last( bwHistory.avgBwIn1 );
		const idealBw  = last( bwHistory.idealBwIn );
		const kiRaw    = last( bwHistory.keyframeInterval );

		// Preconditions â€” need an on-air event with both bandwidth figures.
		if ( onair !== true || avgBwIn1 == null || idealBw == null ) {
			return 'Neutral';
		}

		// Round bits/s â†’ Mbps with one decimal (matches the ops display).
		const broadcastingAt = Math.floor( avgBwIn1 / 100000 ) / 10;
		const idealBand      = Math.floor( idealBw   / 100000 ) / 10;
		if ( broadcastingAt <= 0 ) {
			return 'Neutral';
		}

		const balance = broadcastingAt / idealBand;
		if ( ! isFinite( balance ) ) {
			return 'Neutral';
		}

		// Ratio colour â€” how full the pipe is relative to ideal.
		let ratio;
		if ( balance > 0.85 )      { ratio = 'green'; }
		else if ( balance > 0.70 ) { ratio = 'orange'; }
		else                       { ratio = 'red'; }

		// Keyframe colour â€” missing sample â†’ orange (conservative).
		const ki = Math.round( kiRaw * 10 ) / 10;
		let kiColor;
		if ( ! isFinite( ki ) ) { kiColor = 'orange'; }
		else if ( ki >= 10 )    { kiColor = 'red'; }
		else if ( ki > 5 )      { kiColor = 'orange'; }
		else if ( ki < 1 )      { kiColor = 'orange'; }
		else                    { kiColor = 'green'; }

		// Combine the two colours into the final rating.
		if ( ratio === 'red' || kiColor === 'red' )      { return 'Poor'; }
		if ( ratio === 'green' && kiColor === 'green' )  { return 'Excellent'; }
		if ( ratio === 'green' && kiColor === 'orange' ) { return 'Good'; }
		if ( ratio === 'orange' && kiColor !== 'red' )   { return 'Fair'; }
		return 'Poor';
	}

	/**
	 * Write a stat value into EVERY matching cell. There are now two cells
	 * per stat name on the page â€” one in the LIVE panel, one in the
	 * External-encoder panel â€” so a single querySelector would leave the
	 * other panel stale. The panels are mutually exclusive (only one is
	 * visible), so writing the hidden panel's cell is harmless.
	 *
	 * @param {string} name  Stat name: viewers|duration|bitrate|health.
	 * @param {string} text  Value to write into the cell(s).
	 * @return {void}
	 */
	function setStatText( name, text ) {
        var root = document.querySelector('.wpstream_portal_broadcaster');
        if (root) root.dispatchEvent(new CustomEvent('wpstream-portal-bc:statistics', {bubbles:true, detail:{name:name, value:text}}));
		document.querySelectorAll( '[data-wpstream-portal-bc-stat="' + name + '"]' ).forEach( function ( el ) {
			el.textContent = text;
		} );
	}

	/**
	 * Write one placeholder into all four stat cells on every panel.
	 *
	 * @param {string} label  STATS_NA_LABEL or STATS_LOADING_LABEL.
	 * @return {void}
	 */
	function setLiveStatsPlaceholder( label ) {
		setStatText( 'viewers', label );
		setStatText( 'duration', label );
		setStatText( 'bitrate', label );
		applyHealth( label );
	}

	/**
	 * Show a pending value until each live stat receives usable QoS data. A running
	 * Duration ticker would overwrite its cell within a second, so it stops here;
	 * the next on-air frame restarts it.
	 *
	 * @return {void}
	 */
	function setLiveStatsLoading() {
		if ( durationTimerId ) {
			clearInterval( durationTimerId );
			durationTimerId = null;
		}
		setLiveStatsPlaceholder( STATS_LOADING_LABEL );
	}

	/**
	 * Is this browser starting, publishing or reconnecting the VIEWED channel?
	 * Reconnect gets its own clause because publishingChannelId is 0 during the
	 * gap; broadcastTarget and considerReconnect outlive it until the session ends.
	 *
	 * @return {boolean}
	 */
	function selectedHasBrowserSession() {
		return selectedIsPublishing()
			|| selectedIsTurningOn()
			|| ( !! considerReconnect
				&& !! broadcastTarget
				&& resolveSelectedChannelId() === broadcastTarget.channelId
				&& resolveSelectedChannelDomain() === broadcastTarget.domain );
	}

	/**
	 * Choose the placeholder when the viewed channel has no meter socket: its
	 * own browser session is still pending data (Loading), anything else has no
	 * stream to report (NA). This also covers a meter that dropped, so a closed
	 * socket can never leave Loading on screen for a stream that is not running.
	 *
	 * @return {void}
	 */
	function settleStatsWithoutMeter() {
		setLiveStatsPlaceholder( selectedHasBrowserSession() ? STATS_LOADING_LABEL : STATS_NA_LABEL );
	}

	/**
	 * Apply a health label to the DOM: write the text into the Health stat
	 * cell and swap the dot's colour modifier on the icon span. Null-guards
	 * every element so a host page missing the markup is a safe no-op.
	 *
	 * Colour map: Excellent/Good â†’ _success (green), Fair â†’ _warn (orange),
	 * Poor â†’ _danger (red), anything else (incl. the NA and Loading placeholders)
	 * â†’ _neutral (grey) via the else fall-through.
	 *
	 * @param {string} label  A real rating (Excellent|Good|Fair|Poor) or a
	 *                         localized NA / Loading placeholder.
	 * @return {void}
	 */
	function applyHealth( label ) {
        setStatText( 'health', label );
		// Both panels (LIVE + External) carry a Health cell + icon span, so
		// loop over every matching element rather than the single ref.
		document.querySelectorAll( '[data-wpstream-portal-bc-stat="health"]' ).forEach( function ( el ) {
			el.textContent = label;
		} );
		// Resolve the colour modifier once, then apply it to every health
		// icon span across both panels.
		let mod;
		if ( label === 'Excellent' || label === 'Good' ) { mod = 'wpstream_portal_bc_stat_ico_success'; }
		else if ( label === 'Fair' )                     { mod = 'wpstream_portal_bc_stat_ico_warn'; }
		else if ( label === 'Poor' )                     { mod = 'wpstream_portal_bc_stat_ico_danger'; }
		else                                             { mod = 'wpstream_portal_bc_stat_ico_neutral'; }
		document.querySelectorAll( '[data-wpstream-portal-bc-health-ico]' ).forEach( function ( ico ) {
			ico.classList.remove(
				'wpstream_portal_bc_stat_ico_neutral',
				'wpstream_portal_bc_stat_ico_success',
				'wpstream_portal_bc_stat_ico_warn',
				'wpstream_portal_bc_stat_ico_danger'
			);
			ico.classList.add( mod );
		} );
	}

	function setStartupBadge( text ) {
		if ( ! liveIndicatorError || ! text ) return;
		liveIndicatorError.textContent = text;
		wpstreamBroadcasterDisplay( liveIndicatorError, 'inline' );
	}

	function setStartupControlsLocked( locked ) {
		[ videoSourceSelect, audioSourceSelect, videoResolutionSelect ].forEach( function ( control ) {
			if ( control ) control.disabled = !!( locked || streamingStarted || pendingReconnect );
		} );
		document.querySelectorAll( '.wpstream_portal_broadcaster [data-wpstream-portal-bc-toggle]' ).forEach( function ( control ) {
			control.disabled = false;
		} );
	}

	function setConnectingControls() {
		setStartupControlsLocked( true );
		if ( cancelStartButton ) cancelStartButton.classList.add( 'hidden' );
		if ( streamingButton ) {
			streamingButton.classList.remove( 'hidden' );
			streamingButton.disabled = true;
			streamingButton.textContent = config.waiting_label || 'Please wait…';
		}
	}

	/**
	 * renderChannelStats( bwHistory )
	 *
	 * SINGLE source of truth for the live-stats section â€” Viewers, Duration,
	 * Bitrate, Health. A channel has ONE set of live stats no matter how it is
	 * fed (browser WHIP broadcast OR external encoder); they all come from the
	 * same chandler QoS bwHistory. Both modes call this from the QoS message
	 * handler, so the four cells render identically. Writes into BOTH stat grids
	 * (LIVE + External panels) via setStatText()/applyHealth() â€” the panels are
	 * mutually exclusive so writing the hidden one is harmless.
	 *
	 * @param {Object} bwHistory  bwHistory object from a getQos reply; each
	 *                            series is a {time,val}[] array.
	 * @return {void}
	 */
	function renderChannelStats( bwHistory ) {
		if ( ! bwHistory ) return;
        document.dispatchEvent(new CustomEvent('wpstream-portal-bc:history', {detail:{viewers:bwHistory.viewers || [], bitrate:bwHistory.bwIn || bwHistory.avgBwIn1 || []}}));
		// Last sample of a {time,val}[] series, or undefined when empty.
		function last( a ) { return ( a && a.length ) ? a[ a.length - 1 ].val : undefined; }

		// Names of the cells this frame carries no sample for.
		var unsampled = [];
		var onair     = last( bwHistory.onair );

		if ( onair === true && ! durationTimerId ) {
			// The latest onair sample is when the stream went on air, so a valid
			// time replaces any start time held from `settings`.
			setDurationStartFromQos( bwHistory.onair[ bwHistory.onair.length - 1 ].time );
			if ( qosStartTime !== null ) {
				startDurationTicker();
			}
		}

		// The meter's onair sample drives the encoder-state block. On air needs the
		// external source: our own browser session is the lockout's business. Numeric
		// 0 is a connecting encoder and changes nothing.
		if ( onair === true && channelIsExternalLive() ) {
			setEncoderPanelOnAir( true );
		} else if ( onair === false ) {
			setEncoderPanelOnAir( false );
		}

		// A confirmed off-air channel has nothing to report or count, so any
		// samples in this frame are ignored and the Duration ticker stops. This
		// browser's own session is exempt: the meter reports off air until the
		// first media arrives, so its cells stay pending.
		if ( onair === false && ! selectedHasBrowserSession() ) {
			if ( durationTimerId ) {
				clearInterval( durationTimerId );
				durationTimerId = null;
			}
			qosStartTime = null;
			setLiveStatsPlaceholder( STATS_NA_LABEL );
			return;
		}

		if ( ! durationTimerId ) {
			unsampled.push( 'duration' );
		}

		// --- Viewers -----------------------------------------------------
		var v = last( bwHistory.viewers );
		if ( v != null ) {
			setStatText( 'viewers', String( v ) );
		} else {
			unsampled.push( 'viewers' );
		}

		// --- Health ------------------------------------------------------
		var health = computeHealth( bwHistory );
		if ( health !== 'Neutral' ) {
			applyHealth( health );
		} else {
			unsampled.push( 'health' );
		}

		// --- Bitrate â€” the channel's live ingest rate (bwIn), falling back
		//     to the 1s rolling average. bits/s â†’ kbps, thin-space thousands
		//     separator (e.g. "4 800 kbps") for the live value.
		var raw = last( bwHistory.bwIn );
		if ( raw == null ) raw = last( bwHistory.avgBwIn1 );
		if ( raw != null ) {
			var kbps = Math.round( raw / 1000 );
			var formatted = String( kbps ).replace( /\B(?=(\d{3})+(?!\d))/g, ' ' );
			setStatText( 'bitrate', formatted + ' kbps' );
		} else {
			unsampled.push( 'bitrate' );
		}

		// --- An on-air frame moves NA cells without a sample back to Loading.
		if ( onair === true ) {
			unsampled.forEach( function ( name ) {
				var cell = document.querySelector( '[data-wpstream-portal-bc-stat="' + name + '"]' );
				if ( ! cell || cell.textContent !== STATS_NA_LABEL ) {
					return;
				}
				if ( name === 'health' ) {
					applyHealth( STATS_LOADING_LABEL );
				} else {
					setStatText( name, STATS_LOADING_LABEL );
				}
			} );
		}
	}

	/**
	 * Open the chandler QoS meter socket and start requesting getQos every
	 * 5s. Each reply carries a bwHistory object; we read the live viewer
	 * count and compute a health rating from it. Idempotent â€” bails if the
	 * socket or poll is already running, leaving the cells' data untouched. Bails
	 * (no error) when wpstream_broadcaster_vars.qos_url_data is empty, so a
	 * reconnect or the next go-live retries once baker has surfaced the URL.
	 *
	 * Opening a socket moves all four cells to Loading until their samples
	 * arrive. Without a URL the cells settle on NA or Loading (see
	 * settleStatsWithoutMeter()). A retry of a meter address already in use keeps
	 * the cells as they are, unless a browser session is on this channel: they
	 * already say what is known, and a dead meter retried on every status poll must
	 * not flash Loading each time.
	 *
	 * Socket errors/closures are logged to the console only â€” never shown
	 * as a user toast, since transient meter hiccups must not interrupt a
	 * live broadcast. They also settle the cells the same way as a missing URL.
	 *
	 * @param  {boolean} [isRetry]  True when retrying the same meter address.
	 * @return {void}
	 */
	function startQosStats( isRetry ) {
		// Idempotency â€” only one socket + poll at a time.
		if ( qosSocket || qosPollId ) {
			log( '[broadcaster] startQosStats: already running â€” no-op' );
			return;
		}

		// Read the meter URL from the localized vars; bail if absent.
		const url = config.qos_url_data || '';
		// Log presence only â€” never the URL itself (it can carry session/token query params).
		log( '[broadcaster] startQosStats: QoS meter URL decision', { has_qos_url: !!url } );
		if ( ! url ) {
			settleStatsWithoutMeter();
			return;
		}

		// All four cells wait on Loading until their samples arrive.
		if ( ! isRetry || selectedHasBrowserSession() ) {
			setLiveStatsLoading();
		}

		try {
			qosSocket = new WebSocket( url );
		} catch ( e ) {
			console.warn( 'WPStream QoS: failed to open meter socket', e );
			qosSocket = null;
			settleStatsWithoutMeter();
			return;
		}

		// On open: request immediately, then re-request every 5s. The
		// reply is ~38 KB, so 5s is ample for viewers/health.
		qosSocket.addEventListener( 'open', function () {
			log( '[broadcaster] QoS meter socket opened' );
			if ( qosSocket && qosSocket.readyState === 1 ) {
				qosSocket.send( '{"req":"getQos"}' );
				// getSnapshot is sent ONCE on open (NOT on the 5s poll). The
				// meter answers with a `settings` frame carrying
				// settings.startTime, which drives the Duration ticker.
				qosSocket.send( '{"req":"getSnapshot"}' );
			}
			qosPollId = setInterval( function () {
				if ( qosSocket && qosSocket.readyState === 1 ) {
					qosSocket.send( '{"req":"getQos"}' );
				}
			}, 5000 );
		} );

		// On message: parse, then dispatch by frame type. The `settings` frame
		// (pushed in reply to getSnapshot) carries settings.startTime, stored for
		// the Duration ticker; the bwHistory frame (getQos reply) feeds the other
		// three stats through the shared renderer â€” same path for browser-broadcast
		// and external modes.
		qosSocket.addEventListener( 'message', function ( ev ) {
			let payload;
			try {
				payload = JSON.parse( ev.data );
			} catch ( e ) {
				return;
			}
			if ( ! payload ) {
				return;
			}
			// settings frame â€” store settings.startTime for Duration. Done BEFORE
			// the bwHistory bail because settings frames carry no bwHistory.
			// setDurationStartFromQos() validates the timestamp; only an on-air
			// frame can start the ticker.
			if ( payload.settings ) {
				setDurationStartFromQos( payload.settings.startTime );
			}

			// --- Source detection â€” the ONLY driver of the external-live gate.
			// streamSource.kind === 'rtmp' means an EXTERNAL encoder is the live
			// source â†’ Start DISABLED; 'browser' (or missing/unknown) means no
			// external encoder â†’ Start ENABLED. Prefer the live snapshot
			// (bwData.streamSource), else the last bwHistory.streamSource entry
			// (a {time,val}[] series whose .val is the source object).
			var kind = null;
			if ( payload.bwData && payload.bwData.streamSource && payload.bwData.streamSource.kind ) {
				kind = payload.bwData.streamSource.kind;
			} else if ( payload.bwHistory && payload.bwHistory.streamSource && payload.bwHistory.streamSource.length ) {
				var srcSeries = payload.bwHistory.streamSource;
				var lastSrc   = srcSeries[ srcSeries.length - 1 ];
				if ( lastSrc && lastSrc.val && lastSrc.val.kind ) {
					kind = lastSrc.val.kind;
				}
			}
			document.dispatchEvent(new CustomEvent('wpstream-portal-bc:source', {detail:{kind:kind}}));
            var isRtmp = ( kind === 'rtmp' ); // 'browser'/other â†’ false (null handled below)
			// Act ONLY on frames that actually report a source kind. The meter
			// interleaves frames that OMIT streamSource (kind === null) with ones
			// that include it; treating an omission as "not rtmp" made
			// externalRtmpLive thrash trueâ†’falseâ†’true and re-enabled Start every
			// other poll. When kind is null we leave externalRtmpLive unchanged, unless
			// the same frame confirms off air: the socket stays open after OBS stops,
			// so that frame is what ends the external source. onair must be exactly
			// false (numeric 0 is a connecting encoder), and a frame that itself
			// reports rtmp keeps the flag set. Re-run the gate only on a real CHANGE
			// (no per-frame thrash).
			// Guard is selectedIsPublishing() (NOT bare streamingStarted): the QoS
			// socket reflects the SELECTED channel. Suppress external detection only
			// on the channel WE publish (QoS reports our own kind === 'browser');
			// on any OTHER selected channel read its true source even while a browser
			// broadcast is still running elsewhere.
			var onairSeries = payload.bwHistory && payload.bwHistory.onair;
			var confirmedOffAir = !! onairSeries && onairSeries.length > 0 && onairSeries[ onairSeries.length - 1 ].val === false;
			if ( ! selectedIsPublishing() && ( kind !== null || confirmedOffAir ) ) {
				if ( externalRtmpLive !== isRtmp ) {
					externalRtmpLive = isRtmp;
					enableStartButton(); // button-only: never swap the panel (encoder tab owns it)
				}
			}

			if ( ! payload.bwHistory ) {
				return;
			}
			renderChannelStats( payload.bwHistory );
		} );

		// On error/close: log only, then clear the poll + null the handles
		// so a later startQosStats() can cleanly reopen.
		//
		// Capture THIS socket so a late close/error from a SUPERSEDED socket
		// can't clobber the current one. A rapid channel switch closes the old
		// socket (stopQosStats) and immediately opens a new one (startQosStats);
		// the old socket's async 'close' would otherwise null qosSocket and reset
		// externalRtmpLive out from under the new socket. If we are no longer the
		// current socket, this is a stale event â€” ignore it (stopQosStats already
		// did, or the replacement owns, the cleanup).
		var thisSocket = qosSocket;
		function teardown( label, ev ) {
			console.warn( 'WPStream QoS: meter socket ' + label, ev );
			if ( qosSocket !== thisSocket ) {
				return;
			}
			if ( qosPollId ) {
				clearInterval( qosPollId );
				qosPollId = null;
			}
			// Stop the Duration ticker and forget the stored start time so a
			// later reconnect re-syncs cleanly from the next settings frame.
			if ( durationTimerId ) {
				clearInterval( durationTimerId );
				durationTimerId = null;
			}
			qosStartTime = null;
			qosSocket = null;
			// The source is gone once the socket closes â€” clear the rtmp flag and
			// re-run the state machine (when this isn't our own browser session)
			// so Start re-enables the moment an external encoder stops.
			externalRtmpLive = false;
			if ( ! selectedIsPublishing() ) {
				enableStartButton(); // button-only: never swap the panel (encoder tab owns it)
			}
			setEncoderPanelOnAir( false );
			// No more frames can arrive: a running browser session waits on Loading
			// for a retry from the maintenance poll, otherwise the cells return to NA.
			settleStatsWithoutMeter();
		}
		qosSocket.addEventListener( 'error', function ( ev ) { teardown( 'error', ev ); } );
		qosSocket.addEventListener( 'close', function ( ev ) { teardown( 'closed', ev ); } );
	}

	/**
	 * Tear down the QoS socket + poll and reset the Viewers/Health cells to
	 * NA. Called on disconnect + on the
	 * End-Broadcast click path. Safe to call when nothing is running.
	 *
	 * @return {void}
	 */
	function stopQosStats() {
		log( '[broadcaster] stopQosStats() â€” closing QoS socket + resetting Viewers/Health', { had_socket: !!qosSocket } );
		if ( qosPollId ) {
			clearInterval( qosPollId );
			qosPollId = null;
		}
		// Stop the Duration ticker and clear the stored QoS start time â€” the
		// duration cell is reset to its placeholder by the callers
		// (stopLiveStatsTimers / stopExternalStats / onChannelChange).
		if ( durationTimerId ) {
			clearInterval( durationTimerId );
			durationTimerId = null;
		}
		qosStartTime = null;
		// The QoS socket is the source-of-truth for the live source; closing it
		// means we no longer have a confirmed external source.
		externalRtmpLive = false;
		setEncoderPanelOnAir( false );
		if ( qosSocket ) {
			try { qosSocket.close(); } catch ( e ) { /* already closing */ }
			qosSocket = null;
		}
		if ( statViewersEl ) setStatText( 'viewers', VIEWERS_DEFAULT );
		applyHealth( HEALTH_DEFAULT );
	}

	/**
	 * Enter external-encoder stats mode: flip the mode flag and open the
	 * QoS meter socket. All four stat cells are QoS-fed via renderChannelStats()
	 * â€” the SAME path browser-broadcast mode uses. The externalEncoderMode flag
	 * now only coordinates socket OWNERSHIP between the two panels (see
	 * stopLiveStatsTimers / stopExternalStats), not the stat rendering.
	 * startQosStats() is idempotent and bails when qos_url_data is empty
	 * (channel not active), so this is safe to call every time the External tab
	 * is selected.
	 *
	 * @return {void}
	 */
	function startExternalStats() {
		log( '[broadcaster] startExternalStats() â€” entering QoS-driven (no-peer) stats mode' );
		externalEncoderMode = true;
		startQosStats();
	}

	/**
	 * Leave external-encoder stats mode: flip the mode flag off and â€” ONLY when
	 * no browser-live session is still using the socket (streamingStarted) â€”
	 * clear the QoS-driven duration timer, reset Duration + Bitrate, and tear
	 * the QoS socket down. That single guard is the genuine "is the other owner
	 * still live" check; the LIVE panel owns the socket while a WebRTC broadcast
	 * is running.
	 *
	 * The Duration ticker + qosStartTime belong to the QoS SOCKET lifecycle, not
	 * the panel: qosStartTime is seeded by the `settings` frame, which the meter
	 * pushes only ONCE on socket open. So on a plain tab switch mid-stream
	 * (streamingStarted true) we must NOT touch the ticker/cells â€” the socket
	 * stays open and would never re-seed qosStartTime, so tearing the ticker down
	 * here would drop Duration to a placeholder for the rest of the broadcast. Leaving it
	 * running lets it keep writing both grids across the tab switch.
	 *
	 * @return {void}
	 */
	function stopExternalStats() {
		log( '[broadcaster] stopExternalStats() â€” leaving QoS-driven stats mode', { streamingStarted: streamingStarted, has_qos_url_data: !! config.qos_url_data } );
		externalEncoderMode = false;
		// Keep the QoS socket OPEN whenever a meter URL exists. The socket drives
		// external-encoder SOURCE DETECTION (externalRtmpLive â†’ Start disable),
		// which must keep working on the Browser tab AFTER an Externalâ†’Browser tab
		// round-trip â€” and the `settings` frame that seeds the Duration ticker is
		// pushed only on socket OPEN. Closing it here killed detection (Start
		// wrongly re-enabled) and dropped Duration. Only tear down when there is
		// genuinely no socket need: no browser-live session AND no meter URL.
		if ( ! streamingStarted && ! config.qos_url_data ) {
			if ( durationTimerId ) {
				clearInterval( durationTimerId );
				durationTimerId = null;
			}
			qosStartTime = null;
			stopQosStats();
			// NA, unless a start is still in flight on this channel (returning to
			// the Browser tab mid-start must keep Loading).
			settleStatsWithoutMeter();
		}
	}

	/**
	 * Swap the visible side panel between Setup / Live / External. The
	 * three panels share the same grid cell â€” exactly one is visible at
	 * a time. This is the SINGLE source of truth for that swap; both the
	 * ICE connection-state hook (updateStatus) and the encoder-tab click
	 * handler call through here so the panels can never end up in an
	 * impossible "two visible" state.
	 *
	 * `.wpstream_portal_bc_side` is a flex column in dashboard-2026.css â€”
	 * we set display:'flex' (NOT 'block') when showing or internal gaps /
	 * alignment break.
	 *
	 * Any unrecognised value (e.g. 'connecting', 'reconnecting') is a
	 * no-op â€” keep whichever panel is currently visible so we don't
	 * flicker mid-handshake.
	 *
	 * @param {string} name 'setup' | 'live' | 'external'
	 */
	function setBroadcasterSideState( name ) {
		if ( ! sidePanels[ name ] ) return;
		var root = sidePanels[name].closest('.wpstream_portal_broadcaster');
		if (root && name === 'live' && sidePanels.setup && sidePanels.setup.offsetHeight) {
			var preview = root.querySelector('.wpstream_portal_bc_preview');
			if (preview) root.style.setProperty('--wpstream-bc-live-preview-height', preview.getBoundingClientRect().height + 'px');
		} else if (root && name === 'setup') {
			root.style.removeProperty('--wpstream-bc-live-preview-height');
		}
		Object.keys( sidePanels ).forEach( function ( key ) {
			const el = sidePanels[ key ];
			if ( ! el ) return;
			wpstreamBroadcasterDisplay(el, ( key === name ) ? 'flex' : 'none');
		} );
	}

	/**
	 * selectedIsPublishing() â€” true when the channel currently SELECTED in the
	 * dropdown is the SAME channel this browser is actively publishing via WHIP.
	 *
	 * This is the pivot the channel-switch stale-data fix turns on. A browser
	 * broadcast keeps running when the user switches channels (switching is a
	 * VIEW action), so "are we streaming?" (streamingStarted) is no longer the
	 * same question as "is the channel I'm LOOKING AT the one I'm streaming?".
	 * The LIVE panel + End Broadcast button must show only for the latter.
	 *
	 * publishingChannelId is 0 when no broadcast runs, so this is false then;
	 * the streamingStarted clause keeps a stale publishingChannelId inert.
	 * @returns {boolean}
	 */
	function selectedIsPublishing() {
		return !! streamingStarted
			&& publishingChannelId > 0
			&& resolveSelectedChannelId() === publishingChannelId
			&& resolveSelectedChannelDomain() === publishingChannelDomain;
	}

	/**
	 * selectedIsTurningOn() â€” true when the channel currently SELECTED in the
	 * dropdown is the SAME channel whose browser start is in flight.
	 *
	 * Mirrors selectedIsPublishing() but for the turn-on phase: it keys the
	 * GLOBAL bcTurningOn lifecycle signal to publishingChannelId (set at
	 * turn-on START in startStreaming, cleared on fail/cancel/end). This makes
	 * the 'turning-on' state channel-aware so switching to a DIFFERENT channel
	 * mid-turn-on no longer re-asserts the starting channel's "Startingâ€¦" UI.
	 * @returns {boolean}
	 */
	function selectedIsTurningOn() {
		return bcTurningOn
			&& publishingChannelId > 0
			&& resolveSelectedChannelId() === publishingChannelId
			&& resolveSelectedChannelDomain() === publishingChannelDomain;
	}

	/**
	 * browserBusyElsewhere() â€” true when a browser broadcast is live OR turning
	 * on, on a DIFFERENT channel than the one currently selected. Used to
	 * disable Start: no second browser broadcast is allowed while one is
	 * running or starting up elsewhere. False on the publishing/starting
	 * channel itself (where Start is hidden behind the LIVE/Starting panel).
	 * @returns {boolean}
	 */
	function browserBusyElsewhere() {
		return ( streamingStarted || bcTurningOn )
			&& publishingChannelId > 0
			&& ( resolveSelectedChannelId() !== publishingChannelId || resolveSelectedChannelDomain() !== publishingChannelDomain );
	}

	/**
	 * browserBroadcastIsLive() â€” true only when the SELECTED channel is the one
	 * this browser is actively publishing. Drives the LIVE panel (End Broadcast
	 * + live stats). When a broadcast runs on channel A but the user is viewing
	 * channel B this returns false, so B never shows A's End button. Reload never
	 * makes this true (streamingStarted is a fresh-page-load false).
	 * @returns {boolean}
	 */
	function browserBroadcastIsLive() {
		return selectedIsPublishing();
	}

	/**
	 * channelIsExternalLive() â€” the channel is live via an EXTERNAL encoder
	 * (not this browser). Drives the "Start disabled" gate (line 61).
	 *
	 * Driven SOLELY by externalRtmpLive â€” a CONFIRMED rtmp source from the QoS
	 * meter (streamSource.kind === 'rtmp'), set by the QoS message handler. rtmp
	 * is itself the authoritative "not us" signal: a browser broadcast reports
	 * streamSource.kind === 'browser', never 'rtmp', so an rtmp source is
	 * unambiguously an external encoder. The old wpstream_bc_browser_live hint
	 * heuristic has been removed â€” it was both obsolete and wrong here, because a
	 * stale hint (set by an earlier browser session) would persist while an
	 * external encoder was live and falsely keep Start enabled. streamingStarted
	 * still excludes our own in-session WHIP so Start isn't disabled against us.
	 *
	 * The exclusion is keyed on selectedIsPublishing() (NOT bare streamingStarted)
	 * so it suppresses external detection ONLY on the channel we are browser-
	 * publishing â€” where QoS reports our own kind === 'browser'. When the user
	 * switches to a DIFFERENT channel that is externally live, this correctly
	 * reports external-live even though a browser broadcast is still running
	 * elsewhere.
	 * @returns {boolean}
	 */
	function channelIsExternalLive() {
		return ! selectedIsPublishing() && externalRtmpLive;
	}

	/**
	 * broadcasterIsTurningOn() â€” true ONLY while a browser start is in flight
	 * (anywhere), false when live or off. This is the GLOBAL in-flight signal,
	 * NOT channel-scoped (use selectedIsTurningOn() for the per-view question).
	 *
	 * Reads the dedicated `bcTurningOn` lifecycle boolean (set/cleared by
	 * setTurningOnUI), NOT the cosmetic '--turning-on' wrapper class. The class
	 * is no longer a reliable lifecycle signal because clearTurningOnVisuals()
	 * now strips it per-VIEW â€” when the user switches to a channel that is NOT
	 * the one turning on, the shared "Startingâ€¦" look is cleared for that view
	 * while the start stays in flight on its own channel, so the class can be
	 * absent mid-turn-on. The boolean tracks the true in-flight lifecycle.
	 *
	 * (We also avoid the old turnOnAbortController handle: it is created on
	 * every requestLiveUri() but only nulled in cancelTurnOn(), never after a
	 * SUCCESSFUL start, so it stays truthy for the whole live session and would
	 * mis-report "starting" during a live encoder-tab round-trip.)
	 *
	 * Its 3 consumers â€” computeBcState() (via selectedIsTurningOn), the
	 * encoder-tab bcStartInFlight guard, and the preview re-enable guard â€” all
	 * want this true GLOBAL in-flight state, which the boolean gives them.
	 * @returns {boolean}
	 */
	function broadcasterIsTurningOn() {
		return bcTurningOn;
	}

	// ==================================================================
	// SINGLE broadcaster lifecycle state machine (refactor #1).
	// ------------------------------------------------------------------
	// Before this block "what state are we in" was reconstructed at every
	// call site from ~10 scattered signals (streamingStarted,
	// pendingReconnect, the --turning-on wrapper class, channel_status,
	// the wpstream_bc_browser_live hint, â€¦). The live-source decision was
	// written out three times and the teardown/control-reset cluster four
	// times, which is how the turnOnAbortController-never-nulled bug and
	// the "trapped disabled Start" bug crept in.
	//
	// `bcState` is now the ONE readable lifecycle state. It is the BROADCAST
	// lifecycle only â€” which side panel is *displayed* (Setup vs External
	// tab) and QoS-socket ownership remain separate concerns (the encoder
	// tab can be open over any bcState; externalEncoderMode still owns the
	// QoS socket hand-off, see its docblock).
	//
	// States (the minimal set that maps to existing behavior):
	//   'off'           â€” preview/idle. Start enabled. (channel not active,
	//                     or the active channel is our own reloaded browser
	//                     broadcast â€” the hint excludes it from external-live.)
	//   'turning-on'    â€” Phase 1â€“3 in flight. Maps EXACTLY to the
	//                     .wpstream_portal_broadcaster--turning-on wrapper
	//                     class (broadcasterIsTurningOn()), the only signal
	//                     that is false once live (see its docblock â€” the
	//                     turnOnAbortController handle is never nulled after
	//                     a successful start, so it must NOT be read as state).
	//   'live'          â€” this browser is publishing the WHIP broadcast
	//                     (streamingStarted === true).
	//   'reconnecting'  â€” transient drop, waiting to re-publish
	//                     (pendingReconnect === true).
	//   'external-live' â€” channel is active via an EXTERNAL encoder, this
	//                     browser is not the source. Start DISABLED (notes
	//                     line 61). Maps to channelIsExternalLive().
	// ==================================================================
	let bcState = 'off';

	/**
	 * computeBcState() â€” derive the current lifecycle state from the
	 * existing low-level signals. This is the SINGLE place those signals are
	 * read to answer "what state are we in"; every former inline re-derivation
	 * now calls here. Precedence mirrors the original scattered checks:
	 *   live (streamingStarted)  >  turning-on (--turning-on class)  >
	 *   reconnecting (pendingReconnect)  >  external-live  >  off.
	 *
	 * Live outranks turning-on because the --turning-on class is removed the
	 * moment proceedWithStreaming() sets streamingStarted=true; the ordering
	 * here is belt-and-suspenders so a stale class can never mask a live stream.
	 *
	 * @return {('off'|'turning-on'|'live'|'reconnecting'|'external-live')}
	 */
	function computeBcState() {
		if ( browserBroadcastIsLive() )   { return 'live'; }
		// Channel-keyed: 'turning-on' applies ONLY when the SELECTED channel is
		// the one whose browser start is in flight. Switching to another channel
		// mid-turn-on resolves that view to its own state (off / external-live),
		// while the start continues on its own channel (browserBusyElsewhere
		// gates Start disabled there).
		if ( selectedIsTurningOn() )      { return 'turning-on'; }
		if ( pendingReconnect )           { return 'reconnecting'; }
		if ( channelIsExternalLive() )    { return 'external-live'; }
		return 'off';
	}

	/**
	 * setEncoderTabLabel( showPreview ) â€” swaps the External-encoder tab's
	 * visible TEXT without touching its icon, its
	 * data-wpstream-portal-encoder-tab="external" value, or its click behaviour.
	 * Two server-rendered label spans (so the i18n stays in PHP) are toggled via
	 * the `hidden` attribute.
	 *
	 * Called only from applyBcStateUi() so it follows the SAME state branches
	 * that show/hide the LIVE panel + End button â€” which means it tracks channel
	 * switches automatically: switch away from the publishing channel and the
	 * label reverts to "External encoder"; switch back and it shows
	 * "Player preview".
	 *
	 *   showPreview=true  â†’ "Player preview"  (a BROWSER broadcast is live on the
	 *                       SELECTED channel â€” the 'live' state).
	 *   showPreview=false â†’ "External encoder" (every other state: off,
	 *                       external-live, turning-on, or a different channel we
	 *                       are not publishing).
	 *
	 * @param {boolean} showPreview true to show "Player preview".
	 * @return {void}
	 */
	function setEncoderTabLabel( showPreview ) {
		// Source-choice labels are fixed in the module markup.
	}

	/**
	 * updateCameraGate() â€” the ONE visibility rule for the camera gate
	 * (the "Enable camera & microphone"
	 * button, [data-wpstream-portal-bc-camera-gate] in broadcaster.php).
	 *
	 * The preview pane starts
	 * covered by this gate. It is SHOWN iff all of these hold:
	 *   1. the browser source card is selected (not the streaming-app card),
	 *   2. no camera stream is held (localStream is null),
	 *   3. permission checking is complete and at least one permission is needed,
	 *   4. no browser broadcast is live or starting (streamingStarted /
	 *      broadcasterIsTurningOn()).
	 * Otherwise it is HIDDEN, which lets the off-air overlay ("Camera ready Â·
	 * click Start Broadcast") show over the camera feed.
	 *
	 * Called from every place that changes one of those inputs:
	 * applyBcStateUi() (all lifecycle transitions, incl. the browser-card
	 * return via syncBcState), setTurningOnUI(), createInput()'s success and
	 * failure paths, the streaming-app card branch and init(). Reads the
	 * selected card from the DOM (.active, flipped synchronously on click) so
	 * it is correct even before the deferred tab work runs. Null-safe â€” the
	 * gate may be absent on a host that does not render it.
	 *
	 * @return {void}
	 */
	function updateCameraGate() {
		var gate = document.querySelector( '[data-wpstream-portal-bc-camera-gate]' );
		if ( ! gate ) return;

		// Step 1 â€” which source card is selected. No streaming-app card on the
		// page means browser mode.
		var externalCard   = document.querySelector( '[data-wpstream-portal-encoder-tab="external"]' );
		var browserPicked  = ! ( externalCard && externalCard.classList.contains( 'active' ) );

		// Step 2 â€” apply the rule. hidden attribute only; broadcaster.css keeps
		// [hidden]{display:none} working against the gate's display:flex.
		var show = cameraPermissionsChecked && ! cameraPermissionsGranted && browserPicked && ! localStream && ! streamingStarted && ! broadcasterIsTurningOn();
		gate.hidden = ! show;
		// The sidebar instruction follows the same readiness rule as the preview gate.
		var root = gate.closest('.wpstream_portal_broadcaster');
		var guidance = root && root.querySelector('[data-wpstream-portal-bc-prestart-help]');
		if (guidance) guidance.hidden = !show;
		var previewButton = root && root.querySelector('[data-wpstream-portal-bc-show-preview]');
		if (previewButton) previewButton.hidden = !cameraPermissionsChecked || show || !browserPicked || streamingStarted || broadcasterIsTurningOn();
		if (!localStream && root) root.classList.remove('wpstream_portal_bc_preview_shown');
	}

	// Read permissions without opening devices or prompting. Keep the gate in
	// sync when permissions change in browser settings during this session.
	function observeCameraPermissions() {
		function unavailable() {
			cameraPermissionsChecked = true;
			updateCameraGate();
		}
		if (!navigator.permissions || !navigator.permissions.query) {
			unavailable();
			return;
		}
		Promise.all(['camera', 'microphone'].map(function (name) {
			return navigator.permissions.query({ name: name });
		})).then(function (permissions) {
			function refresh() {
				cameraPermissionsChecked = true;
				cameraPermissionsGranted = permissions.every(function (permission) {
					return permission.state === 'granted';
				});
				updateCameraGate();
			}
			permissions.forEach(function (permission) {
				permission.addEventListener('change', refresh);
			});
			refresh();

		}).catch(unavailable);
	}

	/**
	 * applyBcStateUi( state ) â€” the ONE place that performs the side effects
	 * each lifecycle state implies on the Browser tab: the side-panel swap
	 * (routed through setBroadcasterSideState), the Start/End button
	 * enable/disable, and re-asserting the turning-on button look. Stat reset
	 * + the --turning-on wrapper class are owned by the lower-level helpers
	 * (proceedWithStreaming / setTurningOnUI) that this folds in â€” applyBcStateUi
	 * never adds/removes the wrapper class itself, it only READS it via the
	 * state, so the class is still added/removed at exactly the same moments.
	 *
	 * This collapses the three formerly-duplicated live-source decision trees
	 * (boot guard, encoder-tab Browser branch, updateStatus disconnected lock)
	 * into a single switch driven by computeBcState().
	 *
	 * @param {string} state One of the bcState values.
	 * @return {void}
	 */
	function applyBcStateUi( state ) {
		switch ( state ) {
			case 'live':
				// Browser broadcast publishing â†’ LIVE controls (End Broadcast + stats).
				// The live panel IS the player preview, so the encoder tab reads
				// "Player preview" (same branch that shows End, so it follows
				// channel switches).
				setBroadcasterSideState( 'live' );
				setEncoderTabLabel( true );
				break;
			case 'turning-on':
				// Start in flight. The --turning-on CSS hides every live-panel
				// child except Stop, so the live panel would render blank;
				// show SETUP and re-assert the disabled "Startingâ€¦" button.
				setBroadcasterSideState( 'setup' );
				setTurningOnUI( true );
				// (Encoder-tab label â†’ "Player preview" lives inside
				// setTurningOnUI(true) â€” the single owner of the turning-on
				// look â€” so it is not repeated here.)
				break;
			case 'external-live':
				// If ANOTHER channel is still turning on, strip the shared
				// "Startingâ€¦"/amber look from THIS view first (the start keeps
				// running on its own channel; bcTurningOn stays true). Must run
				// before setBroadcasterSideState so the view starts clean.
				clearTurningOnVisuals();
				// Another source owns the ingest â†’ SETUP, Start DISABLED (line 61).
				// bcTeardownControls(true) reveals the Start button (it may carry
				// .hidden from a go-live on a DIFFERENT channel) and hides Stop;
				// its gated re-enable disables Start because channelIsExternalLive().
				setBroadcasterSideState( 'setup' );
				bcTeardownControls( true );
				setEncoderTabLabel( false );
				break;
			case 'reconnecting':
				// Mid-handshake â€” do NOT swap panels (avoid flicker), matching
				// updateStatus('reconnecting'). Panel + encoder-tab label left as-is.
				break;
			case 'off':
			default:
				// If ANOTHER channel is still turning on, strip the shared
				// "Startingâ€¦"/amber look from THIS idle view first (the start
				// keeps running on its own channel; bcTurningOn stays true, and
				// bcTeardownControls below re-gates Start disabled via
				// browserBusyElsewhere). Must run before setBroadcasterSideState.
				clearTurningOnVisuals();
				// Idle â†’ SETUP. bcTeardownControls(true) reveals the Start button
				// (it may carry .hidden from a go-live on a DIFFERENT channel that
				// is still broadcasting) and hides Stop; its gated re-enable keeps
				// Start DISABLED while a browser broadcast runs elsewhere or the
				// channel is external-live, otherwise ENABLES it.
				setBroadcasterSideState( 'setup' );
				bcTeardownControls( true );
				setEncoderTabLabel( false );
				break;
		}
		// Every lifecycle transition can change the camera-gate inputs
		// (streaming / turning-on), so re-apply its visibility rule here.
		updateCameraGate();
	}

	/**
	 * setBcState( next ) â€” transition entry point. Records the new lifecycle
	 * state into `bcState`, applies its Browser-tab side effects via
	 * applyBcStateUi(), keeps the channel-settings lock in sync (locked while
	 * live / external-live), and refreshes the channel-state readout. This is
	 * the ONE transition function the refactor asks for.
	 *
	 * Note: the low-level handles (turnOnPollInterval, turnOnAbortController,
	 * maintenancePollInterval) are NOT touched here â€” they remain
	 * implementation details owned by the turn-on/teardown helpers for
	 * aborting/clearing. State READS go through bcState/computeBcState();
	 * those handles are no longer read as state signals.
	 *
	 * @param {string} next One of the bcState values.
	 * @return {void}
	 */
	function setBcState( next ) {
		log( '[broadcaster] setBcState', { from: bcState, to: next } );
		bcState = next;
        document.dispatchEvent(new CustomEvent('wpstream-portal-bc:state', {detail:{state:next}}));
		applyBcStateUi( next );
		// NOTE: the channel-settings lock is deliberately NOT touched here. It is
		// owned solely by the host's settings-lock poll (dashboard-2026.js,
		// channelDetail.startSettingsLockPoll â€” a single always-on channel/info
		// status poll) so the lock decision is decoupled from the broadcast
		// lifecycle and mirrors the server save gate by construction. setBcState
		// only drives the broadcast UI (side panel + Start/End + state readout).
		updateChannelStateIndicator();
	}

	/**
	 * syncBcState() â€” recompute the lifecycle state from the current signals
	 * and apply it. Used by the boot guard, the encoder-tab Browser branch,
	 * and any path that needs to "re-render from state" without asserting a
	 * specific target. Collapses the repeated live-source decision tree.
	 *
	 * @return {string} the state that was applied.
	 */
	function syncBcState() {
		var next = computeBcState();
		setBcState( next );
		renderRecording();
		return next;
	}

	/**
	 * bcTeardownControls() â€” the SINGLE teardown/control-reset cluster that
	 * the four former duplicates now route through: reveal #startBroadcast
	 * (drop .hidden), re-enable it, and hide #stopBroadcast. Callers that
	 * need the external-live gate on the re-enable pass `gated = true` (uses
	 * enableStartButton); the End-Broadcast / cancel paths pass `gated = false`
	 * because a channel WE just ended/cancelled is never external-live, so
	 * Start must be unconditionally usable (the "trapped disabled Start" fix).
	 *
	 * @param {boolean} gated true to route the re-enable through the
	 *                        external-live gate, false to force-enable.
	 * @return {void}
	 */
	function bcTeardownControls( gated ) {
		if ( streamingButton ) {
			streamingButton.classList.remove( 'hidden' );
			if ( gated ) {
				enableStartButton();
			} else {
				streamingButton.disabled = false;
			}
		}
		if ( stopButton ) {
			stopButton.classList.add( 'hidden' );
		}
	}

	/**
	 * updateChannelStateIndicator() â€” DISCRETE channel-state readout (debug aid;
	 * may be removed later). Writes a one-line "state Â· channel" string into every
	 * [data-wpstream-portal-bc-channel-state] element (under Start, under End, and
	 * under the external encoder-state block). Shows the local broadcaster UI state
	 * AND the SELECTED/viewed channel's server-reported status so the live state is
	 * visible at a glance. Reads viewedChannelStatus (the channel the user is
	 * looking at), NOT the global config.channel_status (which tracks the
	 * PUBLISHING channel for the reconnect gate) â€” so a background poll for a
	 * channel streaming in the background never paints its status onto a different,
	 * stopped channel the user has switched to. Called from updateStatus(),
	 * setTurningOnUI(), applyInfoFromResponse() and once at boot so it tracks every
	 * transition.
	 * @return {void}
	 */
	function updateChannelStateIndicator() {
		var cells = document.querySelectorAll( '[data-wpstream-portal-bc-channel-state]' );
		if ( ! cells.length ) return;

		// SELECTED/viewed channel's status only (page-load var + per-switch +
		// guarded poll refresh). Empty â†’ "â€”" (no status reported), NOT a fake
		// word like "none".
		var ch = viewedChannelStatus
			? String( viewedChannelStatus )
			: 'â€”';

		var text = 'channel: ' + ch;
		cells.forEach( function ( el ) { el.textContent = text; } );
	}

	/**
	 * enableStartButton() â€” single gate for re-enabling #startBroadcast.
	 *
	 * createInput()'s async success path re-enables the Start button after
	 * device acquisition. That runs AFTER the synchronous boot guard, so a
	 * naked `streamingButton.disabled = false` would clobber the external-live
	 * disable (line 61). Route every "enable Start" through here so the disable
	 * is never lost while the channel is external-live. One guard, not a flag
	 * sprinkled across call sites.
	 */
	function enableStartButton() {
		renderRecording();
		if ( ! streamingButton ) return;
		if (recordingSaving || recordingLoading) { streamingButton.disabled = true; return; }
		// Disable Start in Browser mode when the camera/mic is denied or
		// unavailable (requirement #7). External-encoder mode pushes from an
		// outside encoder and needs no local camera, so this gate is scoped to
		// !externalEncoderMode â€” never disable Start for a missing camera while
		// the External tab is active. Kept as a sibling early-return alongside
		// the external-live / other-channel guards (one flag, one guard clause).
		if ( ! externalEncoderMode && cameraAccessDenied ) {
			streamingButton.disabled = true;
			return;
		}
		// Disable Start when (a) the selected channel is live via an EXTERNAL
		// encoder, or (b) a browser broadcast is already running OR starting up
		// on ANOTHER channel â€” no second browser broadcast is allowed while one
		// is live or in flight. (On the publishing/starting channel itself the
		// Start button is hidden behind the LIVE/Starting panel, so
		// browserBusyElsewhere excludes that case.)
		if ( channelIsExternalLive() || browserBusyElsewhere() ) {
			streamingButton.disabled = true;
			return;
		}
		streamingButton.disabled = false;
	}

	/**
	 * resolveSelectedChannelId() â€” the channel id Start must target.
	 *
	 * Source of truth is the channel-select dropdown (the channel list):
	 *   1. the currently-selected option (.wpstream_portal_channel_option_selected),
	 *   2. else the localized wpstream_broadcaster_vars.channel_id,
	 *   3. else the first real channel option in the list.
	 * Returns 0 only when there is genuinely no real channel to target.
	 *
	 * Fixes the "start sends channel_id=0" bug: the page-load localized default
	 * can be 0 when no channel is pre-selected, so reading it alone sent 0 to
	 * baker. Reading the visible selection guarantees Start targets the channel
	 * the user actually sees in the list.
	 */
	function resolveSelectedChannelId() {
		// 1. The option the user has selected in the dropdown.
		var sel = document.querySelector( '.wpstream_portal_channel_option_selected[data-wpstream-portal-channel-id]' );
		var fromSel = sel ? parseInt( sel.getAttribute( 'data-wpstream-portal-channel-id' ), 10 ) : 0;
		if ( fromSel > 0 ) return fromSel;

		// 2. The localized var (kept in sync by the dropdown change handler).
		var fromVar = parseInt( config.channel_id, 10 );
		if ( fromVar > 0 ) return fromVar;

		// 3. First real channel option in the list (nothing selected yet).
		// Scoped to the module's own popover: host markup may carry the same
		// data attribute (the dashboard's channel panels do), and the module
		// must never read host markup (AGENTS.md rule 1).
		var opts = document.querySelectorAll( '#wpstream_portal_channel_popover [data-wpstream-portal-channel-id]' );
		for ( var i = 0; i < opts.length; i++ ) {
			var id = parseInt( opts[ i ].getAttribute( 'data-wpstream-portal-channel-id' ), 10 );
			if ( id > 0 ) return id;
		}
		return 0;
	}

	/**
	 * resolveSelectedChannelDomain() â€” the domain the current POST must target.
	 *
	 * The domain is part of baker's channel identity
	 * (ch_wps_<user>_<domain_hash>_<channel_id>), so it must travel with every
	 * channel-facing AJAX call. Mirrors resolveSelectedChannelId(): read the
	 * visible dropdown selection first so the domain always tracks the channel
	 * the user actually sees.
	 *   1. the currently-selected option's data-wpstream-portal-channel-domain,
	 *   2. else the localized wpstream_broadcaster_vars.channel_domain (the
	 *      default channel's domain â€” the only source on lock_channel pages
	 *      like Channel Detail, where the dropdown is static text with no
	 *      options),
	 *   3. else 'wpstream.net' (portal-native default; keeps back-compat).
	 *
	 * @return {string} the channel domain to POST.
	 */
	function resolveSelectedChannelDomain() {
		// 1. The domain of the option the user has selected in the dropdown.
		var sel = document.querySelector( '.wpstream_portal_channel_option_selected[data-wpstream-portal-channel-domain]' );
		if ( sel ) {
			var fromSel = sel.getAttribute( 'data-wpstream-portal-channel-domain' );
			if ( fromSel ) return fromSel;
		}

		// 2. The localized default-channel domain (also the lock_channel source).
		if ( config.channel_domain ) return config.channel_domain;

		// 3. Portal-native default.
		return 'wpstream.net';
	}

	/**
	 * onChannelChange( channelId, response ) â€” rebind the broadcaster sidebar to
	 * the channel the user just picked in the dropdown. Called by the
	 * channelSelect handler (in this file) after it refreshes its own DOM
	 * (RTMP URL / stream key cells, encoder modal, embed strip). This method
	 * owns the state + QoS half, which lives in broadcaster.js.
	 *
	 * Switching channels is a VIEW action: it MUST NOT touch the live WHIP
	 * publish (input / peerConnection / streamingStarted / publishingChannelId),
	 * so a browser broadcast on another channel keeps running. It closes the
	 * three stale-sidebar gaps the dashboard handler could not:
	 *   1. config.qos_url_data is repointed at the selected channel's meter
	 *      (the dashboard handler mirrored status/whip/obs/embed but not this).
	 *   2. The QoS socket + all four live-stat cells are torn down, reset, and
	 *      reopened for the selected channel (Viewers/Duration/Bitrate/Health).
	 *   3. The lifecycle state machine is re-run so the visible panel reflects
	 *      the SELECTED channel â€” LIVE + End Broadcast only when it is the one
	 *      we are publishing; Start disabled on every other channel while a
	 *      browser broadcast runs.
	 *
	 * @param {number|string} channelId The newly-selected baker channel id (the
	 *                                  dropdown handler has already written it to
	 *                                  config.channel_id before calling us).
	 * @param {Object} response         The check_event_status AJAX response for
	 *                                  that channel (carries qos_url_data, etc.).
	 * @return {void}
	 */
	function onChannelChange( channelId, response ) {
		// Invalidate any pending save before adopting the newly selected channel.
		recordingRevision++;
		recordingSaving = false;
		recordingLoading = false;
		recordingError = '';
		config.record = response ? response.record : false;
		log( '[broadcaster] onChannelChange', {
			channelId: channelId,
			has_qos_url: !! ( response && response.qos_url_data ),
			streamingStarted: streamingStarted,
			publishingChannelId: publishingChannelId,
		} );

		// (1) Repoint the QoS meter URL at the selected channel. Blank when the
		//     response carries none so an idle channel does NOT reuse the previous
		//     channel's socket. config IS wpstream_broadcaster_vars by reference.
		if ( configPresent ) {
			config.qos_url_data = ( response && response.qos_url_data ) ? response.qos_url_data : '';
		}

		// The channel-state readout now follows the SELECTED channel, so retarget
		// it at the freshly-picked channel's status. Blank when the response
		// carries none (idle channel) â†’ the readout shows "â€”" instead of the
		// previous channel's stale status.
		viewedChannelStatus = ( response && response.status ) ? String( response.status ) : '';

		// (2) Tear down the previous channel's QoS socket and reset every stat
		//     cell. stopQosStats() closes the socket/poll, resets Viewers + Health,
		//     clears externalRtmpLive + the duration ticker. Duration + Bitrate are
		//     owned by the live/external stat helpers, so reset them here too.
		stopQosStats();
		setStatText( 'duration', DURATION_DEFAULT );
		setStatText( 'bitrate', BITRATE_DEFAULT );

		// (3) Reopen the meter for the selected channel. Idempotent; self-bails
		//     when qos_url_data is empty (idle channel with no live meter), in
		//     which case it settles the cells: NA, or Loading when this browser is
		//     starting or publishing the channel the user just switched back to.
		startQosStats();

		// (4) Re-render from lifecycle state. This is a USER-driven path (dropdown
		//     click), so swapping the side panel via syncBcState() is safe here â€”
		//     UNLIKE QoS-frame async paths, which must call enableStartButton()
		//     only (the encoder tab owns the panel). When the External encoder tab
		//     owns the panel we must NOT yank it back to Browser setup, so we only
		//     refresh the Start-button gate there. selectedIsPublishing() now
		//     resolves the freshly-selected id, so LIVE + End shows only on the
		//     channel we publish; every other channel shows its own state.
		if ( externalEncoderMode ) {
			enableStartButton();
		} else {
			syncBcState();
		}

		// (4b) Re-derive the encoder-tab label for the SELECTED channel. It reads
		//      "Player preview" ONLY on the channel we are browser-broadcasting;
		//      every other channel reads "External encoder". The Browser branch
		//      above already does this via syncBcState()â†’applyBcStateUi, but the
		//      External branch only calls enableStartButton() and never re-runs the
		//      state machine â€” so without this an external-tab switch away from the
		//      publishing channel would leave the stale "Player preview" showing.
		setEncoderTabLabel( selectedIsPublishing() );

		// (5) Reconcile the SHARED preview pane (status dot + LIVE badge + status text)
		//     to the viewed channel. While we browser-publish channel A its 'connected'
		//     dot hides the off-air overlay and the LIVE badge shows; switching to a
		//     channel we are NOT publishing must drop that live look so A's camera feed
		//     isn't shown as live on a stopped channel. Skip when the viewed channel is
		//     the one turning on â€” syncBcState() above already set the amber 'turning-on'
		//     dot and we must not clobber it. Does NOT touch #localVideo/the publish.
		if ( selectedIsPublishing() ) {
			setPreviewLiveLook( true );
		} else if ( ! selectedIsTurningOn() ) {
			setPreviewLiveLook( false );
		}
	}

	/**
	 * setTurningOnUI( on )
	 *
	 * Single helper that toggles ALL of the visual signals required by the
	 * "UI state machine during turn-on" contract in
	 * `dashboard-files/screens/components/broadcaster-turn-on-flow.html`:
	 *
	 *   - Status indicator (#statusIndicator) gets/loses the 'turning-on'
	 *     class which paints the amber pulse via dashboard-2026.css.
	 *   - Status text (#statusText) shows the localized starting_channel
	 *     copy ("Starting channelâ€¦") while turning-on; restored to the
	 *     default by the next updateStatus() call.
	 *   - Start button (#startBroadcast) is disabled and its visible text
	 *     swapped to the localized starting_label copy ("Startingâ€¦"). On
	 *     restore we re-enable AND put the original label back (captured
	 *     once into startButtonOriginalLabel on first entry).
	 *   - Stop button (#stopBroadcast) becomes reachable. The button lives
	 *     inside .wpstream_portal_bc_side_live which has inline
	 *     style="display:none;" until the WHIP handshake reaches ICE
	 *     'connected', so a real user click would normally not be possible
	 *     during turn-on. We flip the panel's inline display to 'flex' AND
	 *     add the wrapper class 'wpstream_portal_broadcaster--turning-on'
	 *     which (per the CSS rule next to .wpstream_portal_bc_offair_dot)
	 *     hides every child of the live panel except the Stop button.
	 *   - Message overlay (#messageContainer) shows the preparing_channel
	 *     copy ("Preparing your channelâ€¦") as an info-message.
	 *
	 * Idempotent on the SAME boolean â€” calling setTurningOnUI(true) twice in
	 * a row is safe (the second call just re-applies the same classes/text).
	 *
	 * Centralizes the toggle so the 4 call sites in the orchestrator
	 * (startStreaming entry, proceedWithStreaming, the two .catch blocks,
	 * and the resetStreamingUI path) can never end up in a half-state
	 * where the indicator is amber but the button is re-enabled or vice
	 * versa.
	 *
	 * @param {boolean} on  true to enter turning-on, false to exit.
	 */
	function setTurningOnUI( on ) {
		log( '[broadcaster] setTurningOnUI', { on: !!on } );
		// Compute the wrapper element once. May be null on a page that
		// loads broadcaster.js without rendering the broadcaster widget â€”
		// every step below null-guards so this is a safe no-op then.
		const wrapper = document.querySelector( '.wpstream_portal_broadcaster' );

		if ( on ) {
			// Raise the AUTHORITATIVE global in-flight lifecycle signal FIRST,
			// before any cosmetic work, so every reader (computeBcState via
			// selectedIsTurningOn, browserBusyElsewhere, the encoder-tab and
			// preview guards) sees the start as in flight for its whole duration
			// regardless of which channel the user later switches to.
			// Only the real false to true edge moves the stats to Loading: this
			// function re-runs on every state sync during turn-on, and those calls
			// must not wipe samples that have already arrived.
			if ( ! bcTurningOn ) {
				setLiveStatsLoading();
			}
			bcTurningOn = true;
			// -- Indicator + status text -------------------------------
			if ( statusIndicator ) {
				// Clear competing modifier classes so the amber pulse is
				// the only visual state owning the dot. The connected /
				// disconnected / connecting / reconnecting classes are
				// owned by updateStatus() which will run AFTER the
				// turning-on state clears (proceedWithStreaming â†’
				// 'connecting' â†’ ICE 'connected' â†’ 'connected').
				statusIndicator.classList.remove(
					'connected', 'disconnected', 'connecting', 'reconnecting'
				);
				statusIndicator.classList.add( 'turning-on' );
			}
			if ( statusText && config.starting_channel ) {
				statusText.textContent = config.starting_channel;
			}
			setStartupBadge( config.starting_label || 'Starting…' );
			setStartupControlsLocked( true );
			if ( cancelStartButton ) cancelStartButton.classList.remove( 'hidden' );
			if ( turnOnTimeoutId === null ) {
				turnOnTimeoutId = setTimeout( function () {
					turnOnTimeoutId = null;
					if ( ! bcTurningOn || streamingStarted ) return;
					cancelTurnOn();
					considerReconnect = false;
					currentAttemptId = null;
					resetStreamingUI();
					showMessage( config.startup_timeout_message || 'Broadcast startup timed out. Please try again.', 'error' );
				}, TURN_ON_TIMEOUT_MS );
			}

			// -- Start button: disable + swap label --------------------
			if ( streamingButton ) {
				if ( startButtonOriginalLabel === null ) {
					// Capture the original label ONCE. textContent picks up
					// any text node inside the button regardless of sibling
					// SVG / pulse-dot spans; trim() drops the surrounding
					// whitespace from the PHP template.
					startButtonOriginalLabel = streamingButton.textContent.trim();
				}
				// The Start-button click handler does
				// streamingButton.classList.add("hidden") BEFORE calling
				// startStreaming(), which would normally hide the button
				// entirely. The cycle-#6d contract requires the button to
				// stay VISIBLE in its disabled "Startingâ€¦" state during
				// turn-on so the user sees the progress, so we drop the
				// .hidden class here. (resetStreamingUI's own
				// classList.remove("hidden") covers the recovery path on
				// failure / cancel.)
				streamingButton.classList.remove( 'hidden' );
				streamingButton.disabled = true;
				if ( config.waiting_label ) {
					// textContent wipes any inner <span class="ldot_pulse">
					// markup, which is fine for the turning-on state â€” the
					// pulsing dot belongs to the off-air look, not to
					// "Startingâ€¦". When the original label is restored
					// below, the pulse dot stays gone; this is acceptable
					// because by then either Phase 4 has hidden the Start
					// button entirely OR resetStreamingUI() has run and
					// the user can interact again.
					streamingButton.textContent = config.waiting_label;
				}
			}

			// Keep one panel visible while starting. Showing the live panel
			// here creates an empty second grid row because its controls are hidden.
			setBroadcasterSideState('setup');
			if ( wrapper ) {
				wrapper.classList.add( 'wpstream_portal_broadcaster--turning-on' );
			}

			// -- Encoder tab label â†’ "Player preview" the instant we start ----
			// Requirement #8: clicking Start must relabel the "External encoder"
			// tab to "Player preview" immediately. The Start-click path calls
			// setTurningOnUI(true) DIRECTLY, bypassing applyBcStateUi, so swapping here (the single
			// owner of the turning-on look) covers both the click path and the
			// applyBcStateUi('turning-on') tab-roundtrip path. The exit branch
			// does NOT reset it: the state that follows ('live' keeps "Player
			// preview", 'off'/'external-live' restore "External encoder") owns it.
			// The preview badge is the only startup progress message.
		} else {
			// Lower the global in-flight lifecycle signal: the start has
			// finished (go-live) or aborted (fail / cancel). Do this BEFORE the
			// cosmetic restore so any subsequent state read resolves correctly.
			bcTurningOn = false;
			// Restore the shared "Startingâ€¦" cosmetics. (Side-live panel display
			// is NOT restored here â€” the caller decides what comes next:
			// proceedWithStreaming leaves it visible for Phase 4 â†’ live;
			// resetStreamingUI â†’ updateStatus('disconnected') swings back to the
			// setup panel.)
			clearTurningOnVisuals();
		}

		// The turning-on flag is a camera-gate input. The Start-click path calls
		// this function directly (bypassing applyBcStateUi), so re-apply the
		// gate rule here too.
		updateCameraGate();

		// Refresh the discrete channel-state readout on every turning-on toggle.
		updateChannelStateIndicator();
	}

	/**
	 * clearTurningOnVisuals() â€” COSMETIC-only restore of the shared "Startingâ€¦"
	 * look: drop the amber 'turning-on' indicator class, re-enable the Start
	 * button and restore its original label, remove the
	 * '--turning-on' wrapper modifier, and clear the message overlay.
	 *
	 * It MUST NOT touch the bcTurningOn lifecycle flag. It is called from TWO
	 * places: setTurningOnUI(false) â€” the real end of turn-on (go-live / fail /
	 * cancel) â€” AND applyBcStateUi() when the user is VIEWING a channel that is
	 * NOT the one turning on. In the second case the start is still in flight on
	 * its own channel, so only the per-VIEW look is cleared while the lifecycle
	 * boolean stays true (the start keeps running, Start stays disabled here via
	 * browserBusyElsewhere). Separating cosmetics from lifecycle is what lets
	 * the same "Startingâ€¦" appearance be shown on one channel and hidden on
	 * another without ending the actual start.
	 * @return {void}
	 */
	function clearTurningOnVisuals() {
		var wrapper = document.querySelector( '.wpstream_portal_broadcaster' );
		if ( ! bcTurningOn && turnOnTimeoutId !== null ) {
			clearTimeout( turnOnTimeoutId );
			turnOnTimeoutId = null;
		}
		if ( cancelStartButton && ! bcTurningOn ) cancelStartButton.classList.add( 'hidden' );
		setStartupControlsLocked( bcTurningOn );
		if ( statusIndicator ) {
			statusIndicator.classList.remove( 'turning-on' );
		}
		if ( liveIndicatorError ) wpstreamBroadcasterDisplay( liveIndicatorError, 'none' );
		if ( streamingButton ) {
			streamingButton.disabled = false;
			if ( startButtonOriginalLabel !== null ) {
				streamingButton.textContent = startButtonOriginalLabel;
				// Don't null the cached label â€” a future cancel-and-retry
				// must restore the same original on each cycle. The cache is
				// set once on the first ever turning-on entry and is stable
				// for the lifetime of the page.
			}
		}
		if ( wrapper ) {
			wrapper.classList.remove( 'wpstream_portal_broadcaster--turning-on' );
		}
		// Clear the message overlay so the "Preparing your channelâ€¦" info-toast
		// doesn't linger after the turning-on look is removed.
	}

	/**
	 * setPreviewLiveLook( live ) â€” render the SHARED preview pane (status dot +
	 * status text + LIVE/error badges) for the VIEWED channel. The pane is one
	 * surface across all channels; its "connected" look (which the CSS uses to hide
	 * the off-air overlay and reveal #localVideo) must show ONLY when the channel in
	 * view is the one this browser is publishing. On any other channel we force the
	 * off-air look so the publishing channel's camera is not shown as live on a
	 * stopped channel. Touches ONLY the dot/text/badges â€” never #localVideo.srcObject
	 * or the peerConnection, so the background publish is undisturbed.
	 * @param {boolean} live true â†’ connected look; false â†’ off-air look.
	 * @return {void}
	 */
	function setPreviewLiveLook( live ) {
		if ( statusIndicator ) {
			statusIndicator.classList.remove( 'connected', 'connecting', 'reconnecting', 'turning-on', 'disconnected' );
			statusIndicator.classList.add( live ? 'connected' : 'disconnected' );
		}
		if ( statusText )         { statusText.textContent = live ? 'Connected - Broadcasting Live' : 'Not Broadcasting'; }
		if ( liveIndicatorLive )  { wpstreamBroadcasterDisplay(liveIndicatorLive, live ? 'inline' : 'none'); }
		if ( liveIndicatorError ) { wpstreamBroadcasterDisplay(liveIndicatorError, 'none'); }
	}

	function updateStatus(status) {
		log( '[broadcaster] updateStatus transition', { status: status } );
		if (!statusIndicator || !statusText) return;

		statusIndicator.classList.remove(
			"connected",
			"disconnected",
			"connecting",
			"reconnecting"
		);

		switch (status) {
			case "connected":
				clearWhipAttempt();
				setStartupControlsLocked( false );
				setToggleButtonState( videoToggle, videoEnabled, BC_ICON_PATHS.videoOn, BC_ICON_PATHS.videoOff );
				setToggleButtonState( audioToggle, audioEnabled, BC_ICON_PATHS.audioOn, BC_ICON_PATHS.audioOff );
				// Channel-aware: show the live look ONLY if the viewed channel is the
				// one we just connected (publishing). If the user switched to a
				// different channel during turn-on, keep the preview OFF-AIR for the
				// viewed channel.
				setPreviewLiveLook( selectedIsPublishing() );
				// Dashboard-only: apply the state for the CURRENTLY VIEWED
				// channel, then start the duration + bitrate stat updaters.
				// Go-live must be channel-aware now that switching channels
				// mid-broadcast is allowed: streamingStarted is already true here
				// (set in proceedWithStreaming), so computeBcState resolves 'live'
				// when the publishing channel is the one in view, and 'off' (or
				// external-live) when the user has switched away â€” syncBcState
				// applies whichever is correct instead of forcing 'live' onto the
				// wrong channel.
				syncBcState();
				startLiveStatsTimers();
				startAudioLevelMeter();
				break;
			case "connecting":
				statusIndicator.classList.add("connecting");
				statusText.textContent = config.connecting_label || 'Connecting…';
				wpstreamBroadcasterDisplay(liveIndicatorError, 'inline');
				liveIndicatorError.textContent = config.connecting_label || 'Connecting…';
				// Do NOT swap panels mid-handshake â€” keep whichever
				// panel is currently visible until ICE resolves.
				break;
			case "reconnecting":
				statusIndicator.classList.add("connecting");
				statusText.textContent = "Reconnecting";
				wpstreamBroadcasterDisplay(liveIndicatorError, 'inline');
				wpstreamBroadcasterDisplay(liveIndicatorLive, 'none');
				liveIndicatorError.innerText = 'Connection lost. Reconnecting in 10 seconds...';
				// Same as 'connecting' â€” preserve current panel state.
				break;
			case "disconnected":
			default:
				stopAudioLevelMeter( false );
				statusIndicator.classList.add("disconnected");
				statusText.textContent = "Not Broadcasting";
				wpstreamBroadcasterDisplay(liveIndicatorLive, 'none');
				wpstreamBroadcasterDisplay(liveIndicatorError, 'none');
				// Dashboard-only: tear down live timers, then re-render from the
				// recomputed lifecycle state. syncBcState() folds the former
				// inline live-source decision (setup panel + lock recompute from
				// browserBroadcastIsLive() || channelIsExternalLive()) into the
				// ONE transition path: 'off' â†’ setup + Start enabled + unlocked;
				// 'external-live' â†’ setup + Start disabled + locked (an external
				// source still owns the ingest even though our WHIP peer dropped).
				stopLiveStatsTimers();
				syncBcState();
				// Re-fire the opportunistic preview pass so the user sees
				// themselves again after ending a broadcast. If permission
				// is still granted, the preview comes back; otherwise the
				// off-air mock takes over silently.
				attemptPreviewAttach();
				break;
		}

		// Keep the discrete channel-state readout in sync with every transition.
		updateChannelStateIndicator();
	}

	function createInput( shouldAutoStart = false, keepMessages = false, onReady = null ) {
		const captureGeneration = ++mediaGeneration;
		log( '[broadcaster] createInput()', { shouldAutoStart: shouldAutoStart, keepMessages: keepMessages, has_onReady: typeof onReady === 'function' } );
		if (streamingButton) {
			streamingButton.disabled = true;
		}

		// Show the loading spinner
		if (loadSpinner) {
			wpstreamBroadcasterDisplay( loadSpinner, 'block' );
		}

		if (input) {
			const previousInput = input;
			input = null;
			previousInput.remove();
		}

		if ( !keepMessages ) {
			resetMessages();
		}

		if (localStream) {
			localStream.getTracks().forEach((track) => track.stop());
			localStream = null;
		}

		input = OvenLiveKit.create({
			callbacks: {
				error: function (error) {
					if (input !== captureInput) return;
					if (pendingWhipAttempt) {
						failWhipAttempt(pendingWhipAttempt);
						return;
					}
					let errorMessage = '';

						if (error.message) {
							errorMessage = error.message;
						} else if (error.name) {
							errorMessage = error.name;
						} else {
							errorMessage = error.toString();
						}

					if (error.name === "OverconstrainedError") {
						showMessage(
							"Your browser or camera does not support this frame size: " + videoResolutionSelect.value,
							'error'
						);
						// Retry ONCE by downgrading to the default resolution. If we
						// are already on 'default' and STILL overconstrained (e.g. no
						// camera at all), do NOT recreate — that would loop createInput
						// forever. Just surface the message and stop.
						if ( videoResolutionSelect.value !== 'default' ) {
							log( '[broadcaster] createInput: OverconstrainedError → retry once at default resolution' );
							videoResolutionSelect.value = 'default';
							createInput(shouldAutoStart, true, onReady);
						} else {
							log( '[broadcaster] createInput: OverconstrainedError at default — not retrying (would loop)' );
						}
						return;
					}

						resetMessages();
						showMessage(errorMessage, "error");

						if (shouldAutoStart) {
							considerReconnect = false;
						}
					},
					connectionClosed: function (type, event) {
						if (input !== captureInput) return;
						log("Connection closed:", type, event);
						// Stream B — connection dropped: stop the encoder-side QoS
						// sampler so no interval dangles and we never sample a dead
						// PC. A successful reconnect restarts it at ice 'connected'.
						stopQosSampler();
						streamingStarted = false;
						stopAudioLevelMeter( false );
						// publishingChannelId mirrors streamingStarted: clear it here.
						// A successful reconnect re-captures it in startStreaming();
						// while it is 0 selectedIsPublishing() is false anyway.
						publishingChannelId = 0;
						// updateStatus("disconnected");

						// if (streamingButton) {
						// 	streamingButton.classList.remove("hidden");
						// 	streamingButton.disabled = false;
						// }
						// if (stopButton) {
						// 	stopButton.classList.add("hidden");
						// }

					if (considerReconnect && !pendingReconnect) {
						log('connection closed, attempting to reconnect');
						attemptReconnect();
						wpstreamBroadcasterDisplay( liveIndicatorLive, 'none' );
						wpstreamBroadcasterDisplay( liveIndicatorError, 'inline' );
						liveIndicatorError.innerContent = 'Reconnecting';
					} else {
						log('connection closed, not reconnecting');
						// Funnel telemetry — stream stopped (action maps to
						// stream_stopped via telemetry.js). Closes the attempt span.
						trackOnboardingStepSafe('broadcaster_streaming_stopped', 'wpstream_broadcaster', 'button', 'streaming_stopped', popupSessionId, { attempt_id: currentAttemptId, mode: bcTelemetryMode() });
						currentAttemptId = null;
						// updateInputState(false);
					}
					if (loadSpinner) {
						wpstreamBroadcasterDisplay( loadSpinner, 'none' );
					}
				},
				iceStateChange: function (state) {
					if (input !== captureInput) return;
					log("ICE state changed:", state);
					if ( state === 'connected' ) {
						// showMessage("Broadcast started successfully");
						updateStatus('connected');
						// Funnel telemetry — ICE connected (action maps to
						// ice_connected via telemetry.js). value_ms = WHIP→ICE delta
						// since whip_ready. Keep the action string for map parity.
						trackOnboardingStepSafe('broadcaster_streaming_started', 'wpstream_broadcaster', 'button', 'streaming_started', popupSessionId, { attempt_id: currentAttemptId, mode: bcTelemetryMode(), value_ms: Date.now() - tWhip });
						// Stream B — start the ~10s encoder-side QoS sampler now that
						// the browser stream is live (same milestone as ice_connected).
						// Idempotent; a successful reconnect restarts it here.
						startQosSampler();
					}

						if (state === "disconnected" && considerReconnect) {
							streamingStarted = false;
							// Stream B — ICE dropped: stop the encoder-side QoS sampler
							// so we never sample a stalled PC during the reconnect gap.
							// A successful reconnect restarts it at ice 'connected'.
							stopQosSampler();
							// Mirror streamingStarted (see connectionClosed above).
							publishingChannelId = 0;
							if (considerReconnect && !pendingReconnect) {
								log(
									"connection closed, attempting to reconnect from ice state change"
								);
								updateStatus("reconnecting");
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

			const captureInput = input;
			captureInput.attachMedia(videoElement);

		if (videoSourceSelect.options.length > 0) {
			if (videoSourceSelect.value === "displayCapture") {
				log( '[broadcaster] createInput: acquiring media via getDisplayMedia (screen capture)' );
				captureInput
					.getDisplayMedia(getDisplayConstraints())
					.then(function (stream) {
						if (captureGeneration !== mediaGeneration) {
							stream.getTracks().forEach(function (track) { track.stop(); });
							return;
						}
						log( '[broadcaster] createInput: getDisplayMedia acquired stream' );
						localStream = stream;
						stream.getVideoTracks().forEach(function (track) { track.enabled = videoEnabled; });
						stream.getAudioTracks().forEach(function (track) { track.enabled = audioEnabled; });
						// Single-acquisition rule: this is the ONLY camera /
						// display capture in the broadcaster's lifetime. Mirror
						// the acquired stream into #localVideo here so the
						// preview shows the same feed OvenLiveKit will push.
						// attemptPreviewAttach() no longer calls a second
						// getUserMedia (which would end this track on Windows /
						// Chrome's single-acquisition rule — the cause of the
						// "video plays fine on the remote player but local
						// preview is black" bug).
						if ( videoElement ) {
							videoElement.srcObject = stream;
							wpstreamBroadcasterDisplay( videoElement, 'block' );
						}
						// Stream is held → hide the camera gate so the off-air
						// overlay shows over the captured feed.
						updateCameraGate();
						// Gated re-enable — stays disabled while external-live (line 61).
						enableStartButton();
						if (loadSpinner) {
							wpstreamBroadcasterDisplay( loadSpinner, 'none' );
						}

						if ( shouldAutoStart && considerReconnect ) {
							startStreaming(true);
						} else if ( typeof onReady === 'function' ) {
							// Fresh-start path: the camera was just acquired because
							// input had been released (preview off / after External).
							// Now that media is ready, run the normal Start flow.
							log( '[broadcaster] createInput: media ready → invoking onReady (fresh start)' );
							onReady();
						}
					})
					.catch(function (error) {
						if (captureGeneration !== mediaGeneration) return;
						console.error('Failed to get display media:', error);
						if ( shouldAutoStart || typeof onReady === 'function' ) {
							showMessage('Failed to access screen sharing.', 'error');
							considerReconnect = false;
							updateInputState(false);
							resetStreamingUI();
						}
						if (loadSpinner) {
							wpstreamBroadcasterDisplay( loadSpinner, 'none' );
						}
					});
			} else {
				log( '[broadcaster] createInput: acquiring media via getUserMedia (camera/mic)' );
				// The camera library calls navigator.mediaDevices.getUserMedia()
				// before it returns a promise, so on a page without that API (plain
				// http origin) it throws instead of rejecting. Turn such a throw
				// into a rejection so the failure path below runs and the start
				// attempt ends with a message rather than at the startup timeout.
				var acquireMedia;
				try {
					acquireMedia = captureInput.getUserMedia(getUserConstraints());
				} catch (error) {
					acquireMedia = Promise.reject(error);
				}
				acquireMedia
					.then(function (stream) {
						if (captureGeneration !== mediaGeneration) {
							stream.getTracks().forEach(function (track) { track.stop(); });
							return;
						}
						log( '[broadcaster] createInput: getUserMedia acquired stream' );
						localStream = stream;
						stream.getVideoTracks().forEach(function (track) { track.enabled = videoEnabled; });
						stream.getAudioTracks().forEach(function (track) { track.enabled = audioEnabled; });
						// Funnel telemetry — camera/mic permission granted. This is
						// the ONLY legal getUserMedia site (single-acquisition rule),
						// so camera_permission is emitted here regardless of the
						// cameraAccessDenied gate below (purely additive, gate
						// untouched). mode is always 'browser' for this event.
						emitFunnel( 'camera_permission', { attempt_id: currentAttemptId, mode: 'browser', error_code: 'granted' } );
						// Camera/mic are usable again — clear the access-denied gate
						// (requirement #7) so enableStartButton() below can re-enable
						// Start. Covers the "user granted permission on retry / device
						// change" case, since every retry re-fires createInput().
						cameraAccessDenied = false;
						// Single-acquisition rule — mirror the acquired stream
						// into #localVideo here so the preview shows the same
						// feed OvenLiveKit pushes. See the displayCapture
						// branch above for the full rationale.
						if ( videoElement ) {
							videoElement.srcObject = stream;
							wpstreamBroadcasterDisplay( videoElement, 'block' );
						}
						// Camera is on → hide the camera gate so the off-air
						// overlay ("Camera ready · click Start Broadcast") shows
						// over the live camera feed.
						updateCameraGate();
						// First grant on this page: the boot enumeration ran
						// without permission, so the dropdowns hold generic
						// "video 1" entries. Re-read the device list now that
						// labels are exposed (no second getUserMedia).
						refreshDeviceListAfterGrant( stream );
						// Gated re-enable — stays disabled while external-live (line 61).
						enableStartButton();

						if ( shouldAutoStart && considerReconnect ) {
							startStreaming(true);
						} else if ( typeof onReady === 'function' ) {
							// Fresh-start path: camera just acquired (input had been
							// released / preview off). Media is ready → run Start.
							log( '[broadcaster] createInput: media ready → invoking onReady (fresh start)' );
							onReady();
						}
						if (loadSpinner) {
							wpstreamBroadcasterDisplay( loadSpinner, 'none' );
						}
					})
					.catch(function (error) {
						if (captureGeneration !== mediaGeneration) return;
						console.error('Failed to get user media:', error);
						// Funnel telemetry — classify the getUserMedia failure WITHOUT
						// changing the cameraAccessDenied gate below (the gate stays
						// uniform on purpose; this only reads err.name for reporting):
						//   NotAllowedError / PermissionDeniedError            → 'denied'
						//   NotFoundError / OverconstrainedError /
						//     DevicesNotFoundError                             → 'no_device'
						//   anything else                                      → 'error'
						var camErrName = ( error && error.name ) ? error.name : '';
						var camErrCode;
						if ( camErrName === 'NotAllowedError' || camErrName === 'PermissionDeniedError' ) {
							camErrCode = 'denied';
						} else if ( camErrName === 'NotFoundError' || camErrName === 'OverconstrainedError' || camErrName === 'DevicesNotFoundError' ) {
							camErrCode = 'no_device';
						} else {
							camErrCode = 'error';
						}
						emitFunnel( 'camera_permission', { attempt_id: currentAttemptId, mode: 'browser', error_code: camErrCode } );
						// Camera/mic denied (NotAllowedError / PermissionDeniedError),
						// missing (NotFoundError) or otherwise unavailable. Raise the
						// access-denied gate (requirement #7) and re-run the Start gate
						// so #startBroadcast is DISABLED in Browser mode — every
						// getUserMedia failure blocks a browser broadcast, so we treat
						// them uniformly rather than branching on error.name.
						cameraAccessDenied = true;
						enableStartButton();
						// No stream → the camera gate stays visible so the user
						// can retry via "Enable camera & microphone".
						updateCameraGate();
						// Always tell the user the camera could not be opened.
						// Before, the message showed only for Start / reconnect,
						// so a blocked or busy camera made the gate button look
						// dead. The error name is logged for diagnosis.
						log( '[broadcaster] createInput: getUserMedia failed', { name: camErrName } );
						showMessage('Failed to access camera/microphone.', 'error');
						if ( shouldAutoStart || typeof onReady === 'function' ) {
							considerReconnect = false;
							updateInputState(false);
							resetStreamingUI();
						}
						if (loadSpinner) {
							wpstreamBroadcasterDisplay( loadSpinner, 'none' );
						}
					});
			}
		} else {
			// No camera listed at all (the Video Source dropdown is empty), so
			// there is nothing to acquire. Stop the spinner and say so instead
			// of leaving the gate button / Start looking dead. Same
			// camera-denied gate as a getUserMedia failure.
			log( '[broadcaster] createInput: no video device listed — nothing to acquire' );
			cameraAccessDenied = true;
			enableStartButton();
			if (loadSpinner) {
				wpstreamBroadcasterDisplay( loadSpinner, 'none' );
			}
			showMessage( config.no_video_access || 'Failed to access camera/microphone.', 'error' );
			if (shouldAutoStart || typeof onReady === 'function') resetStreamingUI();
		}
	}

	function startStreaming( isReconnect = false ) {
		const attemptGeneration = turnOnGeneration;
		const target = broadcastTarget || {
			channelId: resolveSelectedChannelId(), domain: resolveSelectedChannelDomain(),
			status: config.channel_status || '', whipUrl: config.whip_url || ''
		};
		broadcastTarget = target;
		log( '[broadcaster] Phase 0 enter', {
			isReconnect: isReconnect,
			channel_id: config.channel_id,
		} );
		// Funnel telemetry — broadcast_start_clicked opens a NEW attempt span.
		// Only on a genuine (non-reconnect) Start: a reconnect re-enters
		// startStreaming(true) but is the SAME attempt, so it must keep the
		// existing attempt_id and not re-emit the click. Generate the id FIRST
		// so every downstream milestone in this attempt carries it.
		if ( ! isReconnect ) {
			currentAttemptId = newBcAttemptId();
			emitFunnel( 'broadcast_start_clicked', { attempt_id: currentAttemptId, mode: bcTelemetryMode() } );
		}
		// =================================================================
		// Phase 0 — client-side quota gate DEACTIVATED. The former
		// synchronous UX pre-check (bail + not_enough_traffic message when
		// available_data_mb <= 0) was removed so Start always proceeds to
		// Phase 2. Quota remains enforced server-side by baker on the
		// /channel/start request — the client gate was UX only. No
		// quota_gate funnel event is emitted here anymore.
		// =================================================================

		// =================================================================
		// Enter the turning-on UI state. The helper locks controls, shows a
		// progress badge and Cancel startup, and starts the bounded timeout.
		// Exits via setTurningOnUI(false) in
		// proceedWithStreaming() (Phase 4 success), or in resetStreamingUI()
		// (Phase 1/2/3 failure or user cancel). See
		// dashboard-files/screens/components/broadcaster-turn-on-flow.html
		// "UI state machine during turn-on" notes block.
		// Skip on reconnect: the reconnect path is its own UI flow owned
		// by updateStatus('reconnecting') and should not collide with the
		// initial-turn-on indicator state.
		// =================================================================
		if ( ! isReconnect ) {
			setTurningOnUI( true );
			setStartupBadge( config.checking_channel_label || 'Checking channel…' );
		}

		// The initial Start click owns the target identity. Capture may finish
		// after a view change; never resolve its channel again from that view.
		const channelId = target.channelId;
		// Capture the channel we are starting as the publishing channel. This is
		// the definitive go-live id (the value sent to baker /channel/start) and
		// survives a mid-turn-on dropdown switch, unlike config.channel_id which
		// follows the visible selection. selectedIsPublishing() only treats it as
		// active once streamingStarted is true, so setting it here (before the
		// stream is confirmed) is inert on a failed/cancelled start.
		publishingChannelId = channelId;
		publishingChannelDomain = target.domain;
		log( '[broadcaster] startStreaming: target channelId resolved', { channelId: channelId } );
		if ( channelId <= 0 ) {
			log( '[broadcaster] startStreaming: no channel selected — aborting Start' );
			showMessage( 'Please select a channel from the list before going live.', 'error' );
			resetStreamingUI();
			considerReconnect = false;
			return;
		}

		// =================================================================
		// Phase 1 — Skip-if-active check (decision 4 in the flow schema).
		//
		// Raw fetch (not the existing checkChannelStatus helper) because
		// we need access to the FULL response body — checkChannelStatus()
		// resolves with a boolean and the reconnect path depends on that
		// signature staying stable. By doing our own fetch here we can
		// pull `whipUrl` out of an "already active" response and skip
		// straight to Phase 4 without paying for Phase 2 + Phase 3.
		//
		// Reconnect path note: when isReconnect=true the caller is
		// attemptReconnect() → createInput(true) → here, and per the
		// schema's "Reconnect path — unchanged" note we MUST NOT re-fire
		// requestLiveUri. The reconnect path is allowed to take ONLY the
		// short-circuit branch: if Phase 1 says active, proceed with the
		// module-scope whipUrl (set on the original Phase 4); otherwise
		// bail. Phase 2 + Phase 3 are skipped entirely in that case.
		// =================================================================
		// =================================================================
		// Phase 1 — synchronous read of the page-load channel/info values
		// localized by broadcaster.php (channel_status + whip_url). Replaces
		// the previous click-time AJAX round-trip to
		// wpstream_dashboard_check_event_status. The dropdown change
		// handler (channelSelect, this file) refreshes these vars via AJAX when
		// the user picks a different channel, so by the time we read them
		// here they already reflect the currently selected channel.
		//
		// Reconnect path note: when isReconnect=true the caller is
		// attemptReconnect() → createInput(true) → here. Per the schema's
		// "Reconnect path — unchanged" rule the reconnect path must NOT
		// mint a new event; it is allowed to take ONLY the short-circuit
		// branch (Phase 1 says active → proceed with the existing whipUrl).
		// If Phase 1 doesn't say active, the reconnect bails.
		// =================================================================
		var phase1Status  = target.status || '';
		var phase1WhipUrl = target.whipUrl || '';
		log( '[broadcaster] Phase 1 result (page-load values)', {
			status:      phase1Status,
			has_whipUrl: !!phase1WhipUrl,
		} );

		// Phase 1 short-circuit — channel already active.
		if ( phase1Status === 'active' && phase1WhipUrl ) {
			if ( isReconnect ) {
				// RECONNECT: a transient disconnect during an in-session
				// broadcast. The publisher just dropped, so re-publishing to the
				// SAME event's WHIP URL is correct — resume without re-minting.
				log( '[broadcaster] Phase 1 short-circuit (reconnect — reuse active event)' );
				if ( messageContainer ) messageContainer.innerHTML = '';
				proceedWithStreaming( phase1WhipUrl, isReconnect );
				return;
			}

			// FRESH START on an already-active channel — e.g. the user ended a
			// broadcast (which stops the STREAMING but NOT the channel) and is
			// starting again, or a reload landed on a still-active channel. Do
			// NOT reuse the stale localized whip (that 409s on the driver) and do
			// NOT stop the channel. Clear the cached active status so we don't
			// loop back into this branch, then re-enter Start: it falls through
			// to Phase 2, baker replies CHANNEL_NOT_STOPPED, and we REUSE the
			// running event by polling for its CURRENT whip URL — no /channel/stop.
			log( '[broadcaster] Phase 1: active on a fresh Start → reusing the running event (no channel stop)' );
			target.status = '';
			target.whipUrl = '';
			if ( messageContainer ) messageContainer.innerHTML = '';
			// Continue the same guarded attempt with the existing server event.
		}

		// Reconnect path bail-out — must not enter Phase 2.
		if ( isReconnect ) {
			log( '[broadcaster] Phase 1 reconnect bail-out' );
			showMessage( config.channel_off, 'error' );
			resetStreamingUI();
			considerReconnect = false;
			return;
		}

		// =====================================================
		// Phase 3 entry — Poll loop (unconditional). The 10s
		// setInterval inside pollEventReady() hits
		// wpstream_dashboard_check_event_status until status ===
		// 'active' and the response carries a whipUrl, at which point
		// it resolves and we hand the whipUrl to proceedWithStreaming().
		// The poll clears its interval on resolve. We use the
		// poll-resolved whipUrl, not the Phase 2 whipUrl, because the
		// Phase 2 response's whipUrl is preliminary — plumber's WHIP
		// endpoint is not actually provisioned (returns 401 + no CORS)
		// until the SNS callback flips the channel record to 'active'.
		// applyInfoFromResponse() still runs on every tick so encoder
		// readouts (obs_uri / obs_stream / etc.) populate as soon as
		// baker has them, independent of the resolve gate.
		//
		// Extracted so BOTH the normal Phase 2 success path AND the
		// CHANNEL_NOT_STOPPED branch below reach it: when the channel is
		// already running server-side, we skip the mint and poll for the
		// existing event's CURRENT whipUrl (never the stale localized one,
		// which is what caused the 409).
		// =====================================================
		function enterPhase3Poll() {
			log( '[broadcaster] Phase 3 enter pollEventReady', { channelId: channelId } );
			setStartupBadge( config.waiting_channel_label || 'Waiting for channel…' );
			pollEventReady( channelId, attemptGeneration, target.domain )
				.then( function ( finalEventData ) {
					if ( attemptGeneration !== turnOnGeneration ) return;
					log( '[broadcaster] Phase 3 resolved', {
						has_whipUrl: !!( finalEventData && finalEventData.whipUrl ),
					} );
					proceedWithStreaming( finalEventData.whipUrl, isReconnect );
				} )
				.catch( function ( pollError ) {
					if ( attemptGeneration !== turnOnGeneration ) return;
					console.error( '[broadcaster] Phase 3 poll failed:', pollError );
					showMessage( String( pollError ), 'error' );
					resetStreamingUI();
					considerReconnect = false;
				} );
		}

		// =====================================================
		// Phase 2 — Create event via baker /channel/start.
		// =====================================================
		log( '[broadcaster] Phase 2 enter requestLiveUri', { channelId: channelId } );
		setStartupBadge( config.creating_channel_label || 'Preparing stream…' );
		requestLiveUri( channelId, target.domain )
			.then( function ( eventData ) {
				if ( attemptGeneration !== turnOnGeneration ) return;
				log( '[broadcaster] Phase 2 result', {
					has_whipUrl: !!( eventData && eventData.whipUrl ),
					keys: eventData ? Object.keys( eventData ) : null,
				} );
				enterPhase3Poll();
			} )
			.catch( function ( reqError ) {
				if ( attemptGeneration !== turnOnGeneration ) return;
				// An explicit Cancel / startup timeout already reset the UI.
				// Do not let the aborted request reset it again or clear the
				// cancellation/timeout feedback.
				if ( reqError === 'Aborted.' ) {
					return;
				}
				// CHANNEL_NOT_STOPPED — baker refused to mint a fresh
				// event because this channel is ALREADY running server-side
				// (a prior start that never stopped). Per product rule:
				// don't fail — the channel already has a live event, so poll
				// for its current whipUrl and broadcast on the existing
				// event instead of erroring out. The poll fetches the LIVE
				// whipUrl via check_event_status, so we never republish the
				// stale localized URL (the 409 cause).
				if ( reqError === 'CHANNEL_NOT_STOPPED' ) {
					log( '[broadcaster] Phase 2 CHANNEL_NOT_STOPPED — channel already running; reusing existing event (poll for current whipUrl)' );
					enterPhase3Poll();
					return;
				}

				// Phase 2 rejected — give_me_live_uri said
				// connected:false (quota, ownership, AWS fail)
				// OR an in-flight abort/network error.
				console.error( 'Phase 2 give_me_live_uri failed:', reqError );
				// Don't spam the message overlay on a deliberate
				// user-cancel abort — the cancel handler owns the UI.
				if ( reqError !== 'Aborted.' ) {
					showMessage( String( reqError ), 'error' );
					// Funnel telemetry — Phase 2 terminal failure (genuine, not a
					// user-cancel abort). Clear the attempt span (terminal error).
					emitFunnel( 'broadcast_error', { attempt_id: currentAttemptId, mode: bcTelemetryMode(), phase: '2', error_code: 'start_request_failed' } );
					currentAttemptId = null;
				}
				resetStreamingUI();
				considerReconnect = false;
			} );
	}

	function updateInputState(state) {
		videoSourceSelect.disabled = state;
		audioSourceSelect.disabled = state;
		videoResolutionSelect.disabled = state;
		streamingButton.disabled = state;
	}

	/**
	 * stopStreaming()
	 *
	 * Handles BOTH terminating an active live WHIP session AND cancelling
	 * a turn-on that is still in flight (Phase 2 XHR or Phase 3 poll loop).
	 * The user clicks Stop in both cases.
	 *
	 * IMPORTANT — End Broadcast stops the STREAMING only, never the channel.
	 * There is NO /channel/stop call here (or anywhere in the broadcaster):
	 *   - Live stop: end the WHIP session via input.stopStreaming(). The
	 *     backend reconciles the channel record when SNS sees the publisher
	 *     drop.
	 *   - Cancel (turning-on in flight): cancelTurnOn() tears down the
	 *     client-side Phase 2 fetch / Phase 3 poll. The half-created event is
	 *     left for the backend to reconcile; a later Start reuses it via the
	 *     CHANNEL_NOT_STOPPED path. No server-side stop is issued.
	 *
	 * The resetStreamingUI() / button-swap / input.stopStreaming() / etc.
	 * cover the visual reset for both cases.
	 */
	function stopStreaming() {
		if (bcTurningOn && !streamingStarted) {
			cancelBroadcastStartup();
			return;
		}
		clearWhipAttempt();
		log( '[broadcaster] stopStreaming() entry', { streamingStarted: streamingStarted, isTurningOn: ( turnOnPollInterval !== null || turnOnAbortController !== null ) } );
		// Idempotent maintenance-poll teardown — covers BOTH the cancel
		// branch and the live-stop branch below so neither can leave the
		// post-live maintenance poll running. Re-entry-safe (stopMaintenancePoll
		// null-guards), and the maintenance terminal branch itself calls
		// stopStreaming(), which lands here again harmlessly.
		stopMaintenancePoll();

		// Stream B — ensure the encoder-side QoS sampler is stopped on End
		// Broadcast (and on cancel of an in-flight start). Idempotent, so a
		// no-op when nothing was sampling.
		stopQosSampler();

		// ----------------------------------------------------------------
		// Tear down any in-flight turn-on (Phase 2 fetch / Phase 3 poll) on the
		// CLIENT side only — clears the poll interval and aborts the in-flight
		// give_me_live_uri fetch. We deliberately do NOT call /channel/stop:
		// End Broadcast (and cancelling a start) stops the STREAMING only, never
		// the channel. The backend reconciles the channel state when it sees the
		// publisher drop. cancelTurnOn() null-guards its handles, so this is a
		// harmless no-op on a normal live End where nothing is in flight.
		// ----------------------------------------------------------------
		cancelTurnOn();

		streamingStarted = false;
		// End Broadcast is the genuine stop — clear the publishing channel so the
		// LIVE panel + End button no longer treat this channel as "ours".
		publishingChannelId = 0;
		publishingChannelDomain = '';
		broadcastTarget = null;
		log( '[broadcaster] stopStreaming(): streamingStarted cleared (browser-live hint intentionally retained)' );
		// We intentionally do NOT clear the persisted browser-live hint here.
		// End Broadcast stops the STREAMING, but baker keeps channel_status
		// 'active' for a few seconds until it reconciles the dropped publisher
		// (see the NOTE just below). Clearing the hint on End created a dead
		// window: a quick post-End reload (channel still 'active', hint already
		// gone) was misread by channelIsExternalLive() as an EXTERNAL encoder
		// being live and wrongly trapped the Start button disabled. Leaving the
		// hint in place keeps Start enabled in that window. The hint is now
		// removed in exactly ONE place — the boot-time stale-hint cleanup, which
		// drops it once the channel actually reports inactive (see ~line 3102).

		// NOTE: we intentionally do NOT blank the cached channel_status here.
		// End Broadcast stops the STREAMING only, so the channel is genuinely
		// still 'active' on the server for a bit (until the backend reconciles)
		// — the channel-state readout must reflect that truth, not a fake blank.
		// The Start button is kept usable by the camera-ready force-enable in the
		// createInput() callback below (it re-enables Start regardless of the
		// external-live gate), so blanking the status is no longer needed.
		considerReconnect = false; // user-initiated stop should cancel auto-reconnect
		if (pendingReconnect && pendingReconnectTimeout) {
			clearTimeout(pendingReconnectTimeout);
			pendingReconnect = false;
			pendingReconnectTimeout = null;
		}
		updateStatus("disconnected");

		// Teardown #1 — single control-reset cluster. Ungated re-enable: a
		// channel WE just ended is never external-live, so Start must be
		// unconditionally usable (the "trapped disabled Start" fix). Reveals
		// Start, hides Stop, enables Start.
		bcTeardownControls( false );

		if (input) {
			input.stopStreaming();
			// Re-acquire the camera for the preview. createInput() disables Start
			// at its top while acquiring and re-enables it on success via the
			// external-live-gated enableStartButton() — which would leave Start
			// DISABLED if this just-ended channel still reads 'active' server-side
			// (the "trapped disabled Start" bug). The onReady callback fires once
			// the camera is ready, AFTER enableStartButton(), so force Start
			// enabled there: a channel WE just ended is never external-live.
			createInput( false, false, function () {
				if ( streamingButton ) { streamingButton.disabled = false; }
			} );
		}
		// (No camera to re-acquire when input is already released — bcTeardownControls
		// above has already enabled Start, so the former else-branch is redundant.)

		// showMessage("Broadcasting stopped", "info");
		updateInputState(false);
	}

	function attemptReconnect() {
		const target = broadcastTarget;
		const generation = turnOnGeneration;
		if (!target) return;
		log( '[broadcaster] attemptReconnect() entry', { pendingReconnect: pendingReconnect, considerReconnect: considerReconnect } );
		// Funnel telemetry — auto-reconnect entered. SAME attempt_id (a
		// reconnect is the same attempt) — do NOT clear or regenerate it.
		emitFunnel( 'stream_reconnect', { attempt_id: currentAttemptId, mode: bcTelemetryMode() } );
		updateStatus('reconnecting');

		if ( pendingReconnect ) {
			log( '[broadcaster] attemptReconnect: bail — reconnect already in progress' );
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
		pendingReconnectTimeout = setTimeout(function () {
			pendingReconnect = false;
			pendingReconnectTimeout = null;
			if (considerReconnect) {
				checkChannelStatus(target.channelId, target.domain)
					.then(function(channelActive) {
						if (generation !== turnOnGeneration || target !== broadcastTarget) return;
						if (channelActive && considerReconnect) {
							log( '[broadcaster] attemptReconnect: channel active → proceeding with reconnect (createInput in 5s)' );
							updateStatus("connecting");
							// input.stopStreaming();
							setTimeout(function () {
								if (considerReconnect && generation === turnOnGeneration && target === broadcastTarget) createInput(true);
							}, 5000);
						} else {
							log( '[broadcaster] attemptReconnect: channel NOT active → bail, broadcasting stopped' );
							considerReconnect = false;
							if ( Number( target.channelId ) === Number( resolveSelectedChannelId() ) && target.domain === resolveSelectedChannelDomain() ) {
								showMessage('Channel is no longer active. Broadcasting stopped');
							}
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

	// SVG path data for the camera / mic icons in their on and off states.
	// Lifted verbatim from the prototype (shared.jsx I.Cam / I.CamOff /
	// I.Mic / I.MicOff) so the toggle swaps to the exact same glyph the
	// design uses. The on-state paths match the static markup emitted by
	// broadcaster.php; the off-state paths are the "slashed" variants.
	var BC_ICON_PATHS = {
		videoOn:  'm23 7-7 5 7 5V7ZM3 5h14v14H3z',
		videoOff: 'M1 1l22 22M16 16V8a2 2 0 0 0-2-2H8.5M3 5h.5L7 5M3 9v8a2 2 0 0 0 2 2h11',
		audioOn:  'M12 1a3 3 0 0 0-3 3v8a3 3 0 1 0 6 0V4a3 3 0 0 0-3-3ZM19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8',
		audioOff: 'M1 1l22 22M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23M12 19v4M8 23h8'
	};

	/**
	 * Reflect a toggle button's enabled/disabled state in the UI.
	 *
	 * Mirrors the official plugin's visual toggle (broadcaster.js
	 * toggleVideo/toggleAudio) but adapted to THIS widget's markup: the
	 * prototype uses one button with a `.on` / `.off` class modifier and a
	 * single inline <svg> whose path is swapped, instead of the plugin's two
	 * stacked <img> icons toggled via style.display. The mechanism is the
	 * same — show the icon that represents the current state — only the DOM
	 * shape differs.
	 *
	 * @param {HTMLElement} btn      The toggle <button> (videoToggle/audioToggle).
	 * @param {boolean}     enabled  true = source on, false = source muted/off.
	 * @param {string}      onPath   SVG `d` for the on (active) glyph.
	 * @param {string}      offPath  SVG `d` for the off (muted) glyph.
	 */
	function setToggleButtonState( btn, enabled, onPath, offPath ) {
		if ( ! btn ) {
			return;
		}
		var kind = btn.getAttribute( 'data-wpstream-portal-bc-toggle' );
		var controls = kind
			? document.querySelectorAll( '.wpstream_portal_broadcaster [data-wpstream-portal-bc-toggle="' + kind + '"]' )
			: [ btn ];
		var label = kind === 'video'
			? ( enabled ? 'Turn camera off' : 'Turn camera on' )
			: ( enabled ? 'Mute microphone' : 'Unmute microphone' );
		controls.forEach( function ( control ) {
			control.classList.toggle( 'on', enabled );
			control.classList.toggle( 'off', ! enabled );
			control.setAttribute( 'aria-pressed', enabled ? 'true' : 'false' );
			control.setAttribute( 'aria-label', label );
			control.title = label;
			var path = control.querySelector( 'svg path' );
			if ( path ) path.setAttribute( 'd', enabled ? onPath : offPath );
		} );
	}

	/**
	 * Toggle the browser camera on/off.
	 *
	 * Same mechanism as the official plugin: flip `track.enabled` on every
	 * video track of the captured getUserMedia stream. Disabling the track
	 * (rather than stopping it) keeps the WebRTC sender in place so the
	 * camera can be re-enabled mid-broadcast without renegotiation — the
	 * peer receives black frames while disabled. Also updates the button's
	 * visual state.
	 *
	 * @param {boolean} enabled  true = camera on, false = camera off.
	 */
	function toggleVideo(enabled) {
		// STEP 1 — update the button glyph + colour to match the new state.
		setToggleButtonState( videoToggle, enabled, BC_ICON_PATHS.videoOn, BC_ICON_PATHS.videoOff );

		// STEP 2 — resolve the live stream. Prefer the module-scope
		// localStream (used by the WHIP broadcast path); fall back to the
		// preview stream attached to the <video> element when the user is
		// still in pre-broadcast preview mode.
		var stream = localStream;
		if ( ! stream && videoElement && videoElement.srcObject ) {
			stream = videoElement.srcObject;
		}

		// STEP 3 — flip enabled on each video track (the actual mute).
		if ( stream ) {
			stream.getVideoTracks().forEach( function ( track ) {
				track.enabled = enabled;
			} );
		}
	}

	function paintAudioLevel( level, message ) {
		var value = Math.max( 0, Math.min( 100, Math.round( level || 0 ) ) );
		if ( audioMeterFill ) audioMeterFill.style.width = value + '%';
		if ( audioMeter ) audioMeter.setAttribute( 'aria-valuenow', String( value ) );
		if ( audioMeterValue ) audioMeterValue.textContent = message || ( value + '%' );
	}

	function stopAudioLevelMeter( muted ) {
		if ( audioLevelFrame !== null ) {
			cancelAnimationFrame( audioLevelFrame );
			audioLevelFrame = null;
		}
		if ( audioLevelSource ) {
			try { audioLevelSource.disconnect(); } catch ( e ) {}
			audioLevelSource = null;
		}
		if ( audioLevelContext ) {
			var context = audioLevelContext;
			audioLevelContext = null;
			audioLevelAnalyser = null;
			if ( context.state !== 'closed' ) context.close().catch( function () {} );
		}
		paintAudioLevel( 0, muted ? 'Muted' : '—' );
	}

	function startAudioLevelMeter() {
		stopAudioLevelMeter( false );
		if ( ! audioEnabled ) {
			paintAudioLevel( 0, 'Muted' );
			return;
		}
		var track = localStream && localStream.getAudioTracks ? localStream.getAudioTracks()[0] : null;
		var AudioContextCtor = window.AudioContext || window.webkitAudioContext;
		if ( ! track || ! AudioContextCtor || ! window.MediaStream ) {
			paintAudioLevel( 0, '—' );
			return;
		}
		try {
			audioLevelContext = new AudioContextCtor();
			audioLevelAnalyser = audioLevelContext.createAnalyser();
			audioLevelAnalyser.fftSize = 256;
			audioLevelAnalyser.smoothingTimeConstant = 0.72;
			audioLevelSource = audioLevelContext.createMediaStreamSource( new MediaStream( [ track ] ) );
			audioLevelSource.connect( audioLevelAnalyser );
			var samples = new Uint8Array( audioLevelAnalyser.fftSize );
			var sampleFrame = function () {
				if ( ! audioLevelAnalyser || ! audioEnabled || ! streamingStarted ) return;
				audioLevelAnalyser.getByteTimeDomainData( samples );
				var sum = 0;
				for ( var i = 0; i < samples.length; i++ ) {
					var sample = ( samples[i] - 128 ) / 128;
					sum += sample * sample;
				}
				var rms = Math.sqrt( sum / samples.length );
				paintAudioLevel( Math.max( 0, Math.min( 100, Math.round( rms * 500 ) ) ) );
				audioLevelFrame = requestAnimationFrame( sampleFrame );
			};
			if ( audioLevelContext.state === 'suspended' ) {
				audioLevelContext.resume().catch( function () {} );
			}
			sampleFrame();
		} catch ( e ) {
			console.warn( '[broadcaster] microphone level meter unavailable', e );
			stopAudioLevelMeter( false );
		}
	}

	/**
	 * Toggle the browser microphone on/off (mute).
	 *
	 * Same mechanism as the official plugin: flip `track.enabled` on every
	 * audio track of the captured stream. Also updates the button's visual
	 * state.
	 *
	 * @param {boolean} enabled  true = mic live, false = muted.
	 */
	function toggleAudio(enabled) {
		// STEP 1 — update the button glyph + colour to match the new state.
		setToggleButtonState( audioToggle, enabled, BC_ICON_PATHS.audioOn, BC_ICON_PATHS.audioOff );

		// STEP 2 — resolve the live stream (localStream, else preview stream).
		var stream = localStream;
		if ( ! stream && videoElement && videoElement.srcObject ) {
			stream = videoElement.srcObject;
		}

		// STEP 3 — flip enabled on each audio track (the actual mute).
		if ( stream ) {
			stream.getAudioTracks().forEach( function ( track ) {
				track.enabled = enabled;
			} );
		}
		if ( streamingStarted ) {
			if ( enabled ) startAudioLevelMeter();
			else stopAudioLevelMeter( true );
		} else if ( ! enabled ) {
			paintAudioLevel( 0, 'Muted' );
		}
	}

	function cancelBroadcastStartup() {
		currentAttemptId = null;
		resetStreamingUI();
	}

	function beginBroadcastStartup() {
		if (streamingStarted || bcTurningOn || pendingReconnect) return;
		const generation = ++turnOnGeneration;
		broadcastTarget = {
			channelId: resolveSelectedChannelId(),
			domain: resolveSelectedChannelDomain(),
			status: config.channel_status || '',
			whipUrl: config.whip_url || ''
		};
		publishingChannelId = broadcastTarget.channelId;
		publishingChannelDomain = broadcastTarget.domain;
		resetMessages();
		setTurningOnUI(true);
		releasePreviewStream();
		cameraOptedIn = true;
		if (!input || !localStream) {
			createInput(false, true, function () {
				if (generation === turnOnGeneration && bcTurningOn) startStreaming();
			});
		} else {
			startStreaming();
		}
	}

	// Event listeners
	if (streamingButton) {
		streamingButton.addEventListener('click', beginBroadcastStartup);
	}

	if (stopButton) {
		stopButton.addEventListener("click", function () {
			log( '[broadcaster] Stop/End Broadcast clicked', { pendingReconnect: pendingReconnect, streamingStarted: streamingStarted } );
			// If a reconnect is pending, treat this as "Cancel Broadcast"
			if (pendingReconnect) {
				log( '[broadcaster] Stop: cancelling a pending reconnect' );
				if (pendingReconnectTimeout) {
					clearTimeout(pendingReconnectTimeout);
					pendingReconnectTimeout = null;
				}
				pendingReconnect = false;
				considerReconnect = false;
				updateStatus("disconnected");
				// showMessage("Broadcast stopped", "info");
				updateInputState(false);
				// Teardown #2 â€” single control-reset cluster. Ungated: cancelling
				// our own pending reconnect leaves the channel ours, never
				// external-live, so Start is unconditionally re-enabled.
				bcTeardownControls( false );
				return;
			}

			// Two reasons to invoke stopStreaming() on a Stop click:
			//   (a) streamingStarted === true â€” a live WHIP session is up;
			//       stopStreaming() runs its full teardown path.
			//   (b) A turn-on is in flight (Phase 2 XHR OR Phase 3 poll
			//       loop) â€” the user is cancelling before the stream went
			//       live. stopStreaming() detects this via the
			//       turnOnPollInterval / turnOnAbortController module
			//       handles and dispatches to the cancel branch first.
			// Cycle #6c â€” added the (b) gate so the cancel branch is
			// reachable. Without it, stopStreaming() was unreachable
			// during turning-on because streamingStarted only flips true
			// inside proceedWithStreaming() (Phase 4).
			var isTurningOn = bcTurningOn || turnOnPollInterval !== null || turnOnAbortController !== null;
			log( '[broadcaster] Stop: dispatch decision', { streamingStarted: streamingStarted, isTurningOn: isTurningOn, will_stop: streamingStarted || isTurningOn } );
			// considerReconnect stays true through the reconnect gap after the
			// pending-reconnect timer has fired (status check + delayed
			// createInput), when streamingStarted is already false.
			if ( streamingStarted || isTurningOn || considerReconnect ) {
				stopStreaming();
			}
		});
	}

	if ( cancelStartButton ) {
		cancelStartButton.addEventListener( 'click', function () {
			if ( ! bcTurningOn || streamingStarted ) return;
			cancelBroadcastStartup();
		} );
	}

	// Device change listeners
	if (videoSourceSelect) {
		videoSourceSelect.addEventListener("change", function () {
			if ( streamingStarted || bcTurningOn || pendingReconnect ) return;
			log( '[broadcaster] video device changed', { device_id: videoSourceSelect.value, recreate_input: !!input, refresh_preview: !!( previewStream && ! streamingStarted ) } );
			if (input) {
				createInput();
			}
			// If the opportunistic preview is active (user hasn't clicked
			// Start Broadcast yet), re-request getUserMedia with the new
			// device so the preview reflects the dropdown selection.
			if ( previewStream && ! streamingStarted ) {
				attemptPreviewAttach();
			}
		});
	}

	if (videoResolutionSelect) {
		videoResolutionSelect.addEventListener("change", function () {
			if ( streamingStarted || bcTurningOn || pendingReconnect ) return;
			log( '[broadcaster] resolution changed', { resolution_key: videoResolutionSelect.value, recreate_input: !!input } );
			if (input) {
				createInput();
			}
		});
	}

	if (audioSourceSelect) {
		audioSourceSelect.addEventListener("change", function () {
			if ( streamingStarted || bcTurningOn || pendingReconnect ) return;
			log( '[broadcaster] audio device changed', { device_id: audioSourceSelect.value, recreate_input: !!input, refresh_preview: !!( previewStream && ! streamingStarted ) } );
			if (input) {
				createInput();
			}
			// Same pattern as videoSourceSelect â€” refresh the preview
			// stream when the audio dropdown changes while we're still
			// in pre-stream preview mode.
			if ( previewStream && ! streamingStarted ) {
				attemptPreviewAttach();
			}
		});
	}

	document.querySelectorAll( '.wpstream_portal_broadcaster [data-wpstream-portal-bc-toggle]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			if ( button.getAttribute( 'data-wpstream-portal-bc-toggle' ) === 'video' ) {
				videoEnabled = ! videoEnabled;
				toggleVideo( videoEnabled );
			} else {
				audioEnabled = ! audioEnabled;
				toggleAudio( audioEnabled );
			}
		} );
	} );

	// ------------------------------------------------------------------
	// Encoder-mode swap (source cards "From this browser" / "From a
	// streaming app" in the "How do you want to go live?" block).
	// ------------------------------------------------------------------
	// Wires the two source cards (`data-wpstream-portal-encoder-tab="browser"`
	// and `="external"`) to the three-way side-panel swap below.
	//   - "browser"  â†’ show Setup panel (NOT Live; the live panel is owned
	//                  by the WHIP connection-state path in updateStatus,
	//                  which only flips it after a real successful
	//                  handshake â€” flipping to Browser from External
	//                  should restore the setup controls).
	//   - "external" â†’ show External encoder panel + release the in-browser
	//                  camera (external encoders push from OBS / Wirecast /
	//                  vMix / Larix etc., so the browser-side device serves
	//                  no purpose and just lights up the indicator + drains
	//                  battery). The camera gate is hidden in external mode
	//                  since there's nothing to preview.
	// Safe no-op if the tabs aren't on the page (e.g. broadcaster widget
	// hasn't been rendered on this screen).
	const encoderTabs = document.querySelectorAll('[data-wpstream-portal-encoder-tab]');
	if ( encoderTabs && encoderTabs.length ) {
		encoderTabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				const mode = tab.getAttribute( 'data-wpstream-portal-encoder-tab' );
				if ( mode !== 'browser' && mode !== 'external' ) return;
				log( '[broadcaster] encoder-tab clicked', { mode: mode } );

				// Flip the visual .active class and aria-pressed so exactly
				// one source card reads as selected (the CSS selected style
				// and the mode tint key on aria-pressed). textContent/
				// setAttribute everywhere â€” no innerHTML on tab markup.
				encoderTabs.forEach( function ( other ) {
					if ( other === tab ) {
						other.classList.add( 'active' );
						other.setAttribute( 'aria-pressed', 'true' );
					} else {
						other.classList.remove( 'active' );
						other.setAttribute( 'aria-pressed', 'false' );
					}
				} );

				// Swap the side panel via the single helper. Browser mode is
				// live-aware (my-notes/brodcaster_notes.md line 38): when a
				// live session is active it restores the LIVE (broadcasting)
				// controls; otherwise it shows SETUP. Liveness is the same
				// signal cycle 1 uses on reload â€” channel_status === 'active'
				// reported by the server â€” OR'd with the in-page
				// streamingStarted flag so a real in-flight WHIP session also
				// restores correctly when returning from the External tab.
				// ----------------------------------------------------------
				// Paint-before-teardown. The .active class flip above is
				// synchronous, but everything below (setBroadcasterSideState /
				// syncBcState, releasePreviewStream, input.remove(), stopping
				// localStream tracks, iframe src swaps, start/stopExternalStats)
				// is heavy SYNCHRONOUS work that runs in the same task. The
				// browser cannot paint the .active highlight until that task
				// yields, so the tab looked unchanged until the next input
				// event ("click again to apply"). Defer the heavy block with
				// two nested rAFs: the outer fires before this frame's paint,
				// the inner fires before the NEXT frame's paint â€” i.e. after the
				// .active highlight has already been painted on screen. A single
				// rAF is insufficient because its callback still runs before the
				// current frame's paint. Both branches (external + browser) are
				// inside the deferral so behavior stays symmetric.
				requestAnimationFrame( function () {
				requestAnimationFrame( function () {
				if ( mode === 'external' ) {
					setBroadcasterSideState( 'external' );

					// Funnel telemetry â€” external-encoder path selected. The UI
					// has just flipped to external mode (setBroadcasterSideState
					// above). EVENT ONLY; surface + mode are fixed for the
					// external spine.
					emitFunnel( 'external_selected', { surface: 'broadcaster', mode: 'external' } );

					// ----------------------------------------------------
					// Release the in-browser camera. External encoders
					// push from a separate application â€” there is no
					// reason for the browser tab to keep the device
					// open. Skip the teardown if a live WHIP broadcast
					// is in flight (streamingStarted === true) â€” pulling
					// the camera out from under an active stream would
					// kill the broadcast. The user would have to End
					// Broadcast before switching tabs in that case.
					// Three things hold the camera: previewStream (this
					// file's permission-aware preview), OvenLiveKit's
					// `input` instance (via attachMedia) and the
					// `localStream` OvenLiveKit acquired via its own
					// getUserMedia inside createInput(). Tear all three
					// down so the device-in-use indicator goes off.
					// ----------------------------------------------------
					// A browser start IN FLIGHT (turning-on) holds the camera for
					// the imminent WHIP publish, exactly like a live broadcast
					// does â€” tearing it down mid-startup would orphan the
					// half-created event. Treat turning-on like streaming: keep
					// the device. broadcasterIsTurningOn() reads the wrapper class
					// (the only signal that is false once live â€” see its docblock).
					var bcStartInFlight = broadcasterIsTurningOn();
					if ( ! streamingStarted && ! bcStartInFlight ) {
						log( '[broadcaster] encoder-tab external: releasing in-browser camera (not streaming)' );
						releasePreviewStream();
						if ( input ) {
							try { input.remove(); } catch ( e ) {}
							input = null;
						}
						if ( localStream ) {
							try { localStream.getTracks().forEach( function ( t ) { t.stop(); } ); } catch ( e ) {}
							localStream = null;
						}
						if ( videoElement ) {
							try { videoElement.srcObject = null; } catch ( e ) {}
							wpstreamBroadcasterDisplay(videoElement, 'none');
						}
					} else {
						log( '[broadcaster] encoder-tab external: keeping camera (broadcast live or starting in flight)', { streamingStarted: streamingStarted, starting: bcStartInFlight } );
					}

					// Swap visible preview: hide the (now empty) video element + show the
					// live-channel iframe so the user sees what their viewers will see
					// while broadcasting via the external encoder. Read the embed URL live
					// from wpstream_broadcaster_vars so a prior dropdown channel-change
					// (channelSelect, this file) is reflected without a reload.
					var externalEmbedIframe = document.getElementById( 'wpstreamPortalExternalEmbed' );
					if ( externalEmbedIframe ) {
						var bcEmbedUrl = config.embed_url ? String( config.embed_url ) : '';
						if ( bcEmbedUrl !== '' ) {
							// Mute this preview player only (the copyable Web Embed snippet is
							// untouched): the broadcaster watches it next to their encoder, so
							// unmuted audio would echo/feedback. The embed_url always carries a
							// query string, so append with '&'. Skip if already present.
							if ( bcEmbedUrl.indexOf( 'startMuted' ) === -1 ) {
								bcEmbedUrl += '&startMuted=true';
							}
							log( '[broadcaster] encoder-tab external: showing embed iframe', { has_embed_url: true } );
							externalEmbedIframe.src = bcEmbedUrl;
							wpstreamBroadcasterDisplay(externalEmbedIframe, 'block');
						} else {
							// No embed URL available (baker didn't return one). Leave the
							// iframe hidden â€” falling back to a constructed URL is worse
							// than nothing per the no-fallback rule on the embed surface.
							log( '[broadcaster] encoder-tab external: hiding embed iframe (no embed_url)', { has_embed_url: false } );
							wpstreamBroadcasterDisplay(externalEmbedIframe, 'none');
							externalEmbedIframe.src = 'about:blank';
						}
					}

					// Hide the camera gate â€” there's nothing to preview in
					// external mode. The browser-card branch below re-applies
					// the rule (gate back, or camera back if opted in).
					updateCameraGate();

					// Drive the external panel's 4-stat grid from the QoS
					// meter socket (no WebRTC peer in external mode).
					startExternalStats();

					// ----------------------------------------------------
					// Browser-live lockout (my-notes/brodcaster_notes.md
					// lines 46-60). When an in-page browser WHIP broadcast
					// is live (streamingStarted), the external encoder
					// credentials are meaningless â€” the channel ingest is
					// already taken by the browser. Hide the RTMP URL,
					// Stream key, and "Full encoder setup" button, show the
					// "already streaming via browser" notice, and flip the
					// encoder-state block to on-air. When NOT live, restore
					// the normal credentials view (idempotent across repeated
					// tab switches). The live stats grid is untouched here â€”
					// it stays visible in both cases.
					// ----------------------------------------------------
					var bcRtmpEl       = document.querySelector( '[data-wpstream-portal-bc-rtmp]' );
					var bcStreamKeyEl   = document.querySelector( '[data-wpstream-portal-bc-streamkey]' );
					var bcEncoderSetup  = document.querySelector( '[data-wpstream-portal-bc-encoder-setup]' );
					var bcLiveNotice    = document.querySelector( '[data-wpstream-portal-bc-browser-live-notice]' );
					if ( browserBroadcastIsLive() ) {
						log( '[broadcaster] encoder-tab external: browser-live LOCKOUT â€” hiding RTMP/key/setup, showing notice, encoder-stateâ†’onair' );
						if ( bcRtmpEl )      wpstreamBroadcasterDisplay(bcRtmpEl, 'none');
						if ( bcStreamKeyEl ) wpstreamBroadcasterDisplay(bcStreamKeyEl, 'none');
						if ( bcEncoderSetup ) wpstreamBroadcasterDisplay(bcEncoderSetup, 'none');
						if ( bcLiveNotice )  wpstreamBroadcasterDisplay(bcLiveNotice, '');
						setEncoderPanelOnAir( true );
					} else {
						log( '[broadcaster] encoder-tab external: normal mode â€” showing RTMP/key/setup, hiding notice, encoder-stateâ†’waiting' );
						if ( bcRtmpEl )      wpstreamBroadcasterDisplay(bcRtmpEl, '');
						if ( bcStreamKeyEl ) wpstreamBroadcasterDisplay(bcStreamKeyEl, '');
						if ( bcEncoderSetup ) wpstreamBroadcasterDisplay(bcEncoderSetup, '');
						if ( bcLiveNotice )  wpstreamBroadcasterDisplay(bcLiveNotice, 'none');
						// Reset the encoder-state back to the waiting default. A prior
						// browser-live lockout flips it to on air; without this reset,
						// ending the browser broadcast and re-opening the External tab
						// would leave the stale on-air copy. With a confirmed external
						// source the meter frames own the block, so a tab click must
						// not knock a live OBS back to waiting.
						if ( ! externalRtmpLive ) {
							setEncoderPanelOnAir( false );
						}
					}
				} else {
					// Browser tab â€” restore the panel from the recomputed lifecycle
					// state (notes lines 38 + 61), the same decision the boot guard
					// uses. syncBcState() folds the former four-way inline tree:
					//   turning-on   â†’ SETUP + re-assert the disabled "Startingâ€¦"
					//                  button (a browser start IN FLIGHT outranks the
					//                  live-source decision â€” the poll keeps running
					//                  across the tab swap, so we must not drop to
					//                  SETUP with Start re-enabled; the --turning-on
					//                  CSS hides every live-panel child so restoring
					//                  'live' would render a blank side). turning-on
					//                  is keyed off the wrapper class, NOT
					//                  turnOnAbortController (which stays truthy while
					//                  live and would wrongly re-trigger over a live
					//                  stream â€” see computeBcState / broadcasterIsTurningOn).
					//   live         â†’ LIVE controls.
					//   external-liveâ†’ SETUP + Start disabled (line 61).
					//   off          â†’ SETUP + Start enabled.
					syncBcState();

					// Hide the external-encoder iframe and clear its src so the embedded
					// player stops loading network/CPU. about:blank (not '') avoids the
					// quirk where empty src can cause the iframe to reload the parent page
					// in some browsers.
					var externalEmbedIframe = document.getElementById( 'wpstreamPortalExternalEmbed' );
					if ( externalEmbedIframe ) {
						wpstreamBroadcasterDisplay(externalEmbedIframe, 'none');
						externalEmbedIframe.src = 'about:blank';
					}

					// Leave external-encoder stats mode: clears the QoS-driven
					// duration timer, resets Duration/Bitrate, and tears the
					// QoS socket down unless a browser-live session needs it.
					stopExternalStats();

					// Re-evaluate the Start gate now that externalEncoderMode is
					// false (requirement #7). The syncBcState() above ran while we
					// were still in external mode, so its enableStartButton() could
					// not apply the Browser-mode camera-denied gate. Re-run it here
					// so returning to Browser mode with a denied/unavailable camera
					// keeps Start disabled.
					enableStartButton();

					// Re-acquire the camera preview on return to the Browser tab.
					// The external branch RELEASES the in-browser camera when idle
					// (input + localStream torn down), so without this the preview
					// would stay black â€” switching back and forth "lost" the camera.
					// The camera opens only on a user action, so reopen the device
					// only when the user already opted in this session
					// (cameraOptedIn: gate button or Start). Otherwise the camera
					// gate shows again (applied by syncBcState() above via
					// applyBcStateUi â†’ updateCameraGate). Guarded on
					// !streamingStarted && !turning-on â€” SYMMETRIC with the external
					// branch's release condition â€” so a live / in-flight broadcast
					// (whose camera was deliberately KEPT) is never disturbed and we
					// never double-acquire. createInput() acquires the device and
					// wires the preview; its own denial handling raises the
					// camera-denied gate (requirement #7) if permission is refused.
					if ( cameraOptedIn && ! streamingStarted && ! broadcasterIsTurningOn() ) {
						log( '[broadcaster] encoder-tab browser: re-acquiring camera preview (opted in, idle)' );
						createInput();
						attemptPreviewAttach();
					}
				}
				} ); // inner requestAnimationFrame â€” heavy work runs after .active paints
				} ); // outer requestAnimationFrame â€” paint-before-teardown wrapper
			} );
		} );
	}

	// ------------------------------------------------------------------
	// External-encoder funnel telemetry (ADDITIVE â€” Stream A external spine).
	// Three more milestones for the OBS / StreamYard / vMix path, each emitted
	// through the SAME emitFunnel() seam with surface='broadcaster' and a fixed
	// mode='external'. These are additive click OBSERVERS â€” they do NOT change
	// the copy behavior (the module UI handler, moduleUi, performs the
	// clipboard write) nor the modal open (data-wpstream-portal-modal-open
	// still opens it, via moduleUi). Copy events are EVENT
	// ONLY: never include the URL or the stream-key value.
	// ------------------------------------------------------------------
	// encoder_setup_opened â€” the "Full encoder setup" button opens the modal,
	// making the per-encoder setup instructions visible.
	var bcEncoderSetupBtn = document.querySelector( '[data-wpstream-portal-bc-encoder-setup]' );
	if ( bcEncoderSetupBtn ) {
		bcEncoderSetupBtn.addEventListener( 'click', function () {
			emitFunnel( 'encoder_setup_opened', { surface: 'broadcaster', mode: 'external' } );
		} );
	}
	// rtmp_url_copied â€” the RTMP/server URL copy button inside the External
	// panel. Scoped to the [data-wpstream-portal-bc-rtmp] wrapper so we hook
	// only the URL row's copy button (never the stream-key row's).
	var bcRtmpCopyBtn = document.querySelector( '[data-wpstream-portal-bc-rtmp] .wpstream_portal_copy_btn' );
	if ( bcRtmpCopyBtn ) {
		bcRtmpCopyBtn.addEventListener( 'click', function () {
			// EVENT ONLY â€” never include the RTMP URL value.
			emitFunnel( 'rtmp_url_copied', { surface: 'broadcaster', mode: 'external' } );
		} );
	}
	// stream_key_copied â€” the stream-key copy button inside the External panel.
	// Scoped to the [data-wpstream-portal-bc-streamkey] wrapper.
	var bcKeyCopyBtn = document.querySelector( '[data-wpstream-portal-bc-streamkey] .wpstream_portal_copy_btn' );
	if ( bcKeyCopyBtn ) {
		bcKeyCopyBtn.addEventListener( 'click', function () {
			// EVENT ONLY â€” never include the stream-key value.
			emitFunnel( 'stream_key_copied', { surface: 'broadcaster', mode: 'external' } );
		} );
	}

	// Camera gate button ("Enable camera & microphone", inside the
	// [data-wpstream-portal-bc-camera-gate] overlay rendered by broadcaster.php).
	// RULE: the camera opens ONLY on a user action. This click is one of the
	// two user actions that first open it (the other is Start Broadcast;
	// later re-acquisitions such as device changes or the browser-card
	// return only happen after one of these two). It records
	// the session opt-in, then runs the single legal acquisition site
	// createInput() (the browser permission prompt appears here) and the
	// idempotent preview wire. createInput()'s success path hides the gate so
	// the off-air overlay shows over the camera feed; on failure its existing
	// camera-denied handling runs, an error message shows, and the gate stays
	// visible for a retry.
	// Bound by document-level delegation (closest()) so it works no matter
	// when the gate markup appears and whatever child of the button is the
	// click target. Nothing above the gate takes the click: it sits inside
	// the off-air centre (no pointer-events:none in the off-air state; that
	// rule only applies while connecting / connected, when the gate is
	// hidden). preventDefault() marks the click as handled so the host's
	// delegated _onDocumentClick (dashboard-2026.js) returns early.
	// Guarded on OvenLiveKit + #localVideo, like the boot block: without the
	// engine there is nothing to open.
	if ( typeof OvenLiveKit !== 'undefined' && videoElement ) {
		document.addEventListener( 'click', function ( e ) {
			var previewButton = e.target && e.target.closest ? e.target.closest('[data-wpstream-portal-bc-show-preview]') : null;
			var hidePreviewButton = e.target && e.target.closest ? e.target.closest('[data-wpstream-portal-bc-hide-preview]') : null;
			if (hidePreviewButton) {
				e.preventDefault();
				var root = hidePreviewButton.closest('.wpstream_portal_broadcaster');
				if (root) {
					root.classList.remove('wpstream_portal_bc_preview_shown');
					var showPreviewButton = root.querySelector('[data-wpstream-portal-bc-show-preview]');
					if (showPreviewButton && !showPreviewButton.hidden) showPreviewButton.focus();
				}
				return;
			}
			if (previewButton) {
				e.preventDefault();
				var previewRoot = previewButton.closest('.wpstream_portal_broadcaster');
				function revealPreview() {
					attemptPreviewAttach();
					if (previewRoot && localStream) previewRoot.classList.add('wpstream_portal_bc_preview_shown');
				}
				cameraOptedIn = true;
				if (localStream) revealPreview();
				else createInput(false, false, revealPreview);
				return;
			}
			var gateBtn = e.target && e.target.closest ? e.target.closest( '[data-wpstream-portal-bc-enable-camera]' ) : null;
			if ( ! gateBtn ) return;
			log( '[broadcaster] camera gate: enable clicked', { has_input: !! input, streamingStarted: streamingStarted } );
			e.preventDefault();
			cameraOptedIn = true;
			createInput();
			attemptPreviewAttach();
		} );
	}

	// Mobile video expand toggle
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

	// ------------------------------------------------------------------
	// Permission-aware pre-stream preview helpers.
	// ------------------------------------------------------------------
	// These run BEFORE the user clicks Start Broadcast, once the user has
	// opened the camera through the initial permission request or preview controls. The feed sits behind the off-air overlay â€” the overlay is
	// semi-transparent + z-indexed above the <video> in CSS, so the user
	// sees themselves dimmed with the "Off Air" / "Camera readyâ€¦" copy on top.
	// ------------------------------------------------------------------

	/**
	 * Detect "page already has camera permission". `enumerateDevices()` only
	 * returns non-empty `.label` strings once the origin has been authorized
	 * for media in a prior gesture; on a fresh origin the labels are empty
	 * even though the device count is correct. Used by
	 * refreshDeviceListAfterGrant() to tell whether the dropdowns still hold
	 * the label-less boot entries (and by init()'s diagnostic log).
	 *
	 * Returns true ONLY when at least one videoinput device with a
	 * non-empty label is present in the already-enumerated device list.
	 */
	function hasGrantedMediaPermission() {
		if ( ! allDevices || ! allDevices.videoinput || ! allDevices.videoinput.length ) {
			return false;
		}
		const firstLabel = allDevices.videoinput[0].label;
		return typeof firstLabel === 'string' && firstLabel.length > 0;
	}

	/**
	 * Release whatever preview tracks we currently hold, if any. Safe to
	 * call at any time â€” null `previewStream` is a no-op. Called before
	 * we re-request media on a device change and before the real
	 * streaming path takes over so we don't end up with two competing
	 * MediaStream subscriptions to the same hardware device.
	 */
	function releasePreviewStream() {
		if ( ! previewStream ) return;
		try {
			previewStream.getTracks().forEach( function ( t ) { t.stop(); } );
		} catch ( e ) {
			// stop() can throw on a track that's already ended â€” silent swallow.
		}
		previewStream = null;
		// Detach from the <video> element too. Calling track.stop() alone is
		// not enough on some browsers â€” the device-in-use indicator stays
		// lit until the <video>'s srcObject reference is released as well.
		// Skipping if a real broadcast stream is on the element (localStream
		// owns it then; releasing srcObject here would kill the live feed).
		if ( videoElement && videoElement.srcObject && ! ( typeof localStream !== 'undefined' && localStream ) ) {
			try { videoElement.srcObject = null; } catch ( e ) {}
		}
	}

	/**
	 * Wire the local preview to OvenLiveKit's already-acquired stream.
	 *
	 * Single-acquisition rule: the broadcaster makes EXACTLY ONE
	 * getUserMedia / getDisplayMedia call per session, owned by
	 * createInput() via OvenLiveKit's input.getUserMedia(). A second
	 * navigator.mediaDevices.getUserMedia() for the same camera device
	 * would end the first track (Windows / Chrome single-acquisition
	 * rule), which previously caused the "video plays on the remote
	 * player but the local preview is black" bug. This helper therefore
	 * does NOT call getUserMedia any more â€” it just assigns the
	 * already-acquired localStream to #localVideo.srcObject.
	 *
	 * Timing: createInput() is async. When this helper is called right
	 * after createInput() (camera gate button, browser-card return,
	 * device-change handlers), localStream is usually still undefined
	 * because input.getUserMedia()'s promise hasn't resolved yet. That's
	 * fine â€” createInput()'s own .then() sets videoElement.srcObject
	 * itself the moment the stream arrives, so this helper is then a
	 * no-op. So this helper is a defensive idempotent wire: if for any
	 * reason the .srcObject got cleared (e.g. after End Broadcast via
	 * updateStatus('disconnected')), restore it from localStream.
	 */
	function attemptPreviewAttach() {
		// Don't touch the video element when a real broadcast is live â€”
		// the streaming path owns it.
		if ( streamingStarted ) {
			return;
		}
		if ( ! videoElement ) return;

		// Reuse the single acquired stream. If localStream isn't ready
		// yet (createInput's input.getUserMedia still pending), do
		// nothing â€” createInput()'s own .then() will wire srcObject
		// when the stream arrives.
		if ( ! localStream ) {
			return;
		}

		// Idempotent re-attach: only assign if the current srcObject is
		// missing or pointing at a different stream. Avoids needlessly
		// re-triggering the video element's loadedmetadata cycle.
		if ( videoElement.srcObject !== localStream ) {
			videoElement.srcObject = localStream;
		}
		wpstreamBroadcasterDisplay(videoElement, 'block');
	}

	/**
	 * Populate device choices, then check permissions and request undecided
	 * camera/microphone access once, without starting a broadcast.
	 *
	 * @return {void}
	 */
	function init() {
		log( '[broadcaster] init() â€” device enumeration result', {
			videoinput_count: ( allDevices && allDevices.videoinput ) ? allDevices.videoinput.length : 0,
			audioinput_count: ( allDevices && allDevices.audioinput ) ? allDevices.audioinput.length : 0,
			has_granted_permission: hasGrantedMediaPermission(),
		} );
		// Step 1 â€” populate the dropdowns (programmatic, fires no change events).
		if (allDevices) {
			setDevice("video", videoSourceSelect, allDevices.videoinput);
			setDevice("audio", audioSourceSelect, allDevices.audioinput);
		}

		// Step 2 â€” show the camera gate (no stream is held yet).
		updateCameraGate();
		observeCameraPermissions();
	}

	// Surface a boot-time device-enumeration error to the user. This is the
	// original error-message logic, used when enumerateDevices() fails or is
	// unavailable (see the boot block below).
	function showBootDeviceError(error) {
		let errorMessage = "";

		if (error.message) {
			log(error.message);
			changeErrorMessage(error.message);
		} else if (error.name) {
			errorMessage = error.name;
			showMessage(errorMessage, "error");
		} else {
			errorMessage = error.toString();
			showMessage(errorMessage, "error");
		}
	}

	/**
	 * enumerateDevicesWithoutPrompt() â€” list cameras and microphones WITHOUT
	 * opening any device. Plain navigator.mediaDevices.enumerateDevices()
	 * never triggers a permission prompt (unlike OvenLiveKit.getDevices(),
	 * which primes permission with a getUserMedia first, and so must never
	 * run at boot). Reshapes the flat MediaDeviceInfo[] into the
	 * { videoinput, audioinput } object init()/setDevice() consume.
	 * Before the user grants permission, labels are empty (and browsers may
	 * expose one entry per kind with an empty deviceId); setDevice() then
	 * falls back to a generated "video N" / "audio N" label.
	 *
	 * @return {Promise<{videoinput: MediaDeviceInfo[], audioinput: MediaDeviceInfo[]}>}
	 */
	function enumerateDevicesWithoutPrompt() {
		return navigator.mediaDevices.enumerateDevices().then( function ( deviceList ) {
			return {
				videoinput: deviceList.filter( function ( device ) { return device.kind === 'videoinput'; } ),
				audioinput: deviceList.filter( function ( device ) { return device.kind === 'audioinput'; } ),
			};
		} );
	}

	/**
	 * refreshDeviceListAfterGrant( stream ) â€” after the FIRST camera grant on
	 * this page, re-read the device list so the dropdowns show real labels
	 * (and every device) instead of the generic boot entries. Uses
	 * enumerateDevicesWithoutPrompt(), never a second getUserMedia
	 * (single-acquisition rule). After refilling, each dropdown is pointed at
	 * the device the stream actually uses (track.getSettings().deviceId).
	 * Setting select.value programmatically fires no change event, so the
	 * device-change handlers (which re-create the input) do not run.
	 * No-op once labels are present (later acquisitions on the same page).
	 *
	 * @param {MediaStream} stream The stream createInput() just acquired.
	 * @return {void}
	 */
	function refreshDeviceListAfterGrant( stream ) {
		// Step 1 â€” only needed while the dropdowns still hold label-less entries.
		if ( hasGrantedMediaPermission() ) return;
		if ( ! navigator.mediaDevices || ! navigator.mediaDevices.enumerateDevices ) return;

		enumerateDevicesWithoutPrompt()
			.then( function ( devices ) {
				// Step 2 â€” refill both dropdowns with the now-labelled list.
				allDevices = devices;
				setDevice( 'video', videoSourceSelect, devices.videoinput );
				setDevice( 'audio', audioSourceSelect, devices.audioinput );

				// Step 3 â€” re-select the devices the live stream is using.
				[
					[ videoSourceSelect, stream.getVideoTracks()[0] ],
					[ audioSourceSelect, stream.getAudioTracks()[0] ],
				].forEach( function ( pair ) {
					var select = pair[0];
					var track  = pair[1];
					var id     = ( track && track.getSettings ) ? track.getSettings().deviceId : '';
					if ( select && id && Array.prototype.some.call( select.options, function ( o ) { return o.value === id; } ) ) {
						select.value = id;
					}
				} );
			} )
			.catch( function ( error ) {
				// Labels stay generic; the dropdowns remain usable.
				log( '[broadcaster] refreshDeviceListAfterGrant: enumerateDevices failed', { name: error && error.name } );
			} );
	}

	// Populate device choices and request undecided permissions for a preview.
	// Guarded on OvenLiveKit + #localVideo so this file is a safe no-op on
	// pages that don't load the OvenLiveKit script or render the widget.
	if (typeof OvenLiveKit !== 'undefined' && videoElement) {
		if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
			log( '[broadcaster] boot â€” enumerating devices (no permission prompt)' );
			enumerateDevicesWithoutPrompt()
				.then(function (devices) {
					log( '[broadcaster] boot â€” enumerateDevices resolved' );
					allDevices = devices;
					init();
				})
				.catch(function (error) {
					log( '[broadcaster] boot â€” enumerateDevices failed', { name: error && error.name } );
					showBootDeviceError(error);
				});
		} else {
			// No mediaDevices API: browsers expose it only on secure origins
			// (https or localhost), so a site served over plain http has no
			// camera at all and no permission prompt can change that. Tell the
			// user what to do and keep Start disabled in browser mode via the
			// same gate a denied camera uses, instead of letting a click run into
			// the camera library and sit there until the startup timeout.
			log( '[broadcaster] boot â€” navigator.mediaDevices unavailable' );
			cameraAccessDenied = true;
			enableStartButton();
			showMessage( config.insecure_origin || 'Browser broadcasting needs a secure (https) connection. Open this page over https, or use Streaming software.', 'error' );
		}
	} else {
		log( '[broadcaster] boot â€” OvenLiveKit or #localVideo absent; broadcaster engine is a no-op on this page', {
			has_OvenLiveKit: typeof OvenLiveKit !== 'undefined',
			has_videoElement: !!videoElement,
		} );
	}

	// ------------------------------------------------------------------
	// State persistence on reload (my-notes/brodcaster_notes.md lines 38 + 61).
	// Restore the Browser Broadcast area to its live SOURCE on boot rather than
	// defaulting to SETUP. Three-way decision via the shared live-source model:
	//   - browser-live  â†’ restore the LIVE panel (End Broadcast).
	//   - external-live â†’ SETUP panel, Start DISABLED (line 61): another source
	//                     owns the ingest, so starting a browser broadcast here
	//                     would collide with it.
	//   - off           â†’ SETUP, Start enabled (the default markup state).
	// Runs at module scope (not inside init()) so the restore does not depend on
	// the async device enumeration resolving. UI-only.
	//
	// NOTE: browserBroadcastIsLive() is selectedIsPublishing(), which requires
	// streamingStarted â€” always false on a fresh page load â€” so a browser
	// broadcast NEVER restores its LIVE panel on reload (it can't: the WHIP peer
	// died with the page, and publishingChannelId reset to 0). A reloaded
	// browser broadcast falls through to the `else` (SETUP, Start enabled) so the
	// user can start again. The LIVE branch below only ever runs from in-session
	// transitions that reuse this decision, not at boot.
	// ------------------------------------------------------------------
	// Boot-guard live-source decision â€” folded into the ONE transition path.
	// syncBcState() computes the lifecycle state from the page-load signals
	// (browser-live / turning-on / reconnecting / external-live / off), swaps
	// the side panel + Start button accordingly, and applies the channel-state
	// readout. This replaces the former inline three-way tree. The channel-
	// settings lock is NOT applied here â€” it is owned solely by the always-on
	// host's settings-lock poll (dashboard-2026.js).
	// At page load the localized config.channel_status IS the selected channel's
	// status, so seed the viewed-channel readout from it before the first render.
	viewedChannelStatus = config.channel_status || '';
	var bootState = syncBcState();
	if ( bootState === 'live' ) {
		log( '[broadcaster] boot-guard: browser-live â†’ restore LIVE panel + QoS stats' );
		// Live stats wiring (my-notes/brodcaster_notes.md lines 32-35). After a
		// reload there is NO WebRTC peer â€” we restored the UI only, not the
		// broadcast â€” so we cannot read bitrate/duration off a peer. Use the
		// same no-peer, QoS-socket-driven stats path the External-encoder mode
		// uses. Reset all four cells, then open the QoS socket, which moves them
		// to Loading until fresh samples arrive; the socket â€” startExternalStats() is idempotent and bails when
		// qos_url_data is empty (e.g. the local stub), which settles the cells instead.
		stopLiveStatsTimers();
		startExternalStats();
	} else if ( config.qos_url_data ) {
		// Open the QoS meter to DETECT the real source (rtmp vs browser) whenever a
		// meter URL exists. We gate ONLY on qos_url_data presence â€” the same choice
		// channel-analytics.js makes â€” because the channel status string is NOT a
		// reliable "is live" signal; gating on channel_status === 'active' left the
		// broadcaster's socket closed (so externalRtmpLive never set, Start never
		// disabled) even while an external encoder was live. qos_url_data is empty
		// unless baker reports an active event, so this stays a no-op on idle/local-
		// stub channels. Only a streamSource.kind === 'rtmp' frame disables Start;
		// 'browser'/none keeps it enabled.
		log( '[broadcaster] boot-guard: qos_url_data present + not browser-live â†’ open QoS to detect source (Start stays enabled until rtmp confirmed)' );
		startQosStats();
	}

	// The channel-detail settings-lock poll is HOST code now (dashboard-2026.js,
	// channelDetail.startSettingsLockPoll): it locks host markup (the settings
	// region) and reads the channel status through this module's public
	// getChannelStatus() API. Nothing to start here.

	function changeErrorMessage(message) {
		log(config);
		switch (message) {
			case "No input devices were found.":
				showMessage(
					config.no_video_audio_access,
					"error"
				);
				break;
			case "Can not find Audio devices":
				showMessage(config.no_audio_access, "error");
				break;
			case "Can not find Video devices":
				showMessage(config.no_video_access, "error");
		}
	}

	function checkChannelStatus(channelId, domain) {
		return new Promise((resolve, reject) => {
			if (!config.ajax_url) {
				resolve(true);
				return;
			}

			// RENAMED: plugin uses 'wpstream_check_event_status'. Theme clone
			// uses the dashboard-prefixed action (ACTIONS.check_event_status) so
			// it routes to the handler in
			// dashboard-files/broadcaster/broadcaster-ajax.php instead of
			// the plugin's class-wpstream-live-api-connection.php. ajaxPost()
			// owns form-encoding, the nonce, and the defensive parse â€” so the
			// envelope it resolves is already the decoded object.
			ajaxPost( ACTIONS.check_event_status, { channel_id: channelId, domain: domain } )
				.then(function (parsedResponse) {
					// A reply with no status is a failed request (expired nonce, lost
					// login, backend error), not a channel that went inactive. Reject
					// with the server's own message so the catch below shows it.
					if ( ! parsedResponse || ! parsedResponse.status ) {
						throw ( parsedResponse && parsedResponse.error ) || 'No status returned.';
					}
					var isViewedTarget = Number( channelId ) === Number( resolveSelectedChannelId() ) && ( ! domain || domain === resolveSelectedChannelDomain() );
					if ( broadcastTarget && broadcastTarget.channelId === channelId && broadcastTarget.domain === domain ) {
						broadcastTarget.status = parsedResponse && parsedResponse.status || '';
						if ( parsedResponse && parsedResponse.whipUrl ) broadcastTarget.whipUrl = parsedResponse.whipUrl;
					}
					log( '[wpstream_dashboard_check_event_status response â€” reconnect path]', parsedResponse );
					if (parsedResponse && parsedResponse.status === 'active') {
						if ( isViewedTarget && messageContainer ) messageContainer.innerHTML = "";
						resolve(true);
					} else {
						if ( isViewedTarget ) showMessage(config.channel_off, 'error');
						resolve(false);
					}
				})
				.catch(function (error) {
					// Mirrors the old jQuery error/parse branches: surface a
					// toast, reset the UI, reject(false). The old code split
					// parse failure (showMessage('Error checking channel status'))
					// from transport failure (showMessage('Error checking channel
					// status: ' + error)); ajaxPost rejects parse failures with
					// the literal 'Could not parse server response.' string, so
					// the combined message below preserves a visible, actionable
					// toast for both.
					console.error('Error checking channel status:', error);
					var isViewedTarget = Number( channelId ) === Number( resolveSelectedChannelId() ) && ( ! domain || domain === resolveSelectedChannelDomain() );
					if ( isViewedTarget ) {
						resetStreamingUI();
						showMessage('Error checking channel status: ' + error, 'error');
					}
					reject(false);
				});
		})
	}

	// NOTE: function checkUserQuota() was deleted in cycle #2 of the
	// turn-on-flow rewrite; the later page-load client-side quota gate at
	// the top of startStreaming() has since been deactivated as well. There
	// is no client-side quota check anymore â€” quota is enforced server-side
	// by baker on the /channel/start request.

	/**
	 * requestLiveUri()
	 *
	 * Phase 2 of the turn-on flow â€” wraps the
	 * wpstream_dashboard_give_me_live_uri AJAX endpoint registered in
	 * broadcaster-ajax.php. That handler delegates to
	 * wpstream_portal_channel_start() which POSTs baker /channel/start
	 * (Bearer token); baker provisions the plumber event and writes the
	 * prod bookkeeping. The returned event_data carries the connection
	 * envelope (whipUrl, broadcastUrl, â€¦) but the caller MUST treat the
	 * whipUrl as preliminary â€” see the unconditional Phase 3 poll in
	 * startStreaming() for why. The authoritative whipUrl is the one
	 * pollEventReady() returns once status === 'active'.
	 *
	 * Uses native fetch() (not jQuery.ajax) so the request can be aborted
	 * via the module-scope turnOnAbortController when the user clicks
	 * Stop mid-Phase-2. A fresh AbortController is created on every
	 * invocation â€” abort() on a stale controller has no effect on a new
	 * fetch, so we MUST replace, not reuse.
	 *
	 * Defensive JSON parse: cycle #1 rewrote the PHP handler to use
	 * wp_send_json() which sets Content-Type: application/json. fetch()
	 * does NOT auto-parse JSON the way jQuery.ajax does, so we call
	 * res.text() then parse only if the result is still a string. Mirrors
	 * the pattern at broadcaster.js checkChannelStatus():1466 so the two
	 * helpers stay tolerant of a future PHP failure-mode that prepends
	 * WP_DEBUG warnings.
	 *
	 * NOT YET CALLED from any user-facing flow â€” cycle #6a is helpers-only.
	 * Wired into startStreaming() in cycle #6b.
	 *
	 * @param  {number} channelId  wpstream_stream post ID â€” sanitised
	 *                             server-side via intval().
	 * @return {Promise<object>}   Resolves with the event_data object on
	 *                             {connected: true} success; rejects with
	 *                             the error string on {connected: false}
	 *                             failure, on network error, or on abort.
	 */
	function requestLiveUri(channelId, domain) {
		// Replace any prior AbortController. cancelTurnOn() may have already
		// nulled the module-scope handle, but if a previous requestLiveUri()
		// call completed normally without clearing it, we'd otherwise call
		// abort() on a stale instance from a future cancel. Fresh controller
		// per call. Wired into ajaxPost via the { signal } option so
		// cancelTurnOn() can abort the in-flight Phase 2 request.
		turnOnAbortController = new AbortController();

		// Funnel telemetry â€” Phase 2 begins. Mark tRequested so
		// event_start_accepted below can report the Phase 2 round-trip delta.
		tRequested = Date.now();
		emitFunnel( 'event_start_requested', { attempt_id: currentAttemptId, mode: bcTelemetryMode(), phase: '2' } );

		// ajaxPost owns form-encoding, the start-event nonce
		// (check_ajax_referer('wpstream_start_event_nonce','nonce')), the
		// res.text() â†’ defensive-parse step, the missing-ajax_url early reject,
		// and the AbortError discrimination. Only the {connected, event_data,
		// error} envelope mapping stays here.
		return ajaxPost(
			ACTIONS.give_me_live_uri,
			{ channel_id: channelId, domain: domain },
			{ signal: turnOnAbortController.signal }
		).then(function (response) {
			log( '[wpstream_dashboard_give_me_live_uri response]', response );

			// Map the {connected, event_data, error, code} envelope onto the
			// Promise's resolve/reject shape. Success path: hand event_data
			// straight back. Rejection path: hand back the human-readable
			// `error` the handler already mapped from Baker's codes, except for
			// CHANNEL_NOT_STOPPED: the channel is still running upstream, which
			// is not a failure to show, so the caller's reuse branch gets the
			// code itself. Throwing inside .then() rejects the returned promise.
			if (response && response.connected === true) {
				// Funnel telemetry â€” Phase 2 accepted (whipUrl issued / event
				// minted). value_ms = Phase 2 round-trip since event_start_requested.
				tAccepted = Date.now();
				emitFunnel( 'event_start_accepted', { attempt_id: currentAttemptId, mode: bcTelemetryMode(), phase: '2', value_ms: tAccepted - tRequested } );
				return response.event_data;
			}
			if ( response && response.code === 'CHANNEL_NOT_STOPPED' ) {
				throw 'CHANNEL_NOT_STOPPED';
			}
			throw ((response && response.error) ? response.error : 'Could not start the event.');
		});
	}

	/**
	 * applyInfoFromResponse( response )
	 *
	 * Mirror the fields returned by wpstream_dashboard_check_event_status
	 * (a flattened baker /channel/info row) into two surfaces:
	 *
	 *   1. window.wpstream_broadcaster_vars â€” keeps Phase 1's page-load
	 *      cache fresh so a subsequent click-to-start sees up-to-date
	 *      status / whipUrl / encoder fields without another AJAX round
	 *      trip. Mirrors the same key set the channelSelect dropdown
	 *      refresh writes.
	 *   2. Visible DOM elements that display per-channel encoder readouts
	 *      â€” the External-encoder panel's RTMP URL + Stream key spans and
	 *      their Copy-button payloads. These currently render baker's
	 *      `obs_uri` / `obs_stream` server-side at page load; once Phase 3
	 *      starts ticking, the same values may arrive (or refresh) live as
	 *      plumber finishes provisioning. Updating them in-place avoids a
	 *      reload to see the encoder credentials.
	 *
	 * Called from every Phase 3 tick BEFORE the status decision branch
	 * so encoder readouts populate as soon as baker has them, independent
	 * of when the poll resolves on 'active' + whipUrl. This is the core
	 * per-tick housekeeping that makes the External-encoder Copy buttons
	 * useful the moment plumber surfaces obs_uri / obs_stream.
	 *
	 * @param {Object|null|undefined} response  Decoded baker /channel/info row.
	 *                                          Safe on missing keys; missing
	 *                                          fields are left untouched (we
	 *                                          NEVER blank a previously-set
	 *                                          value because the field was
	 *                                          absent in a later tick).
	 * @return {void}
	 */
	function applyInfoFromResponse( response ) {
		if ( ! response || typeof response !== 'object' ) {
			return;
		}

		// --- (1) Mirror into wpstream_broadcaster_vars ------------------
		// Only assign when the field is actually present in the response
		// (using `in`/typeof checks rather than truthy checks) so an
		// auxiliary field that arrives late doesn't clobber an earlier
		// successful read with an empty string. Status is the one
		// exception â€” it is the authoritative state machine; we always
		// mirror it when present even if empty, so the JS gate reflects
		// reality.
		// `config` IS the localized wpstream_broadcaster_vars object (a
		// reference, not a clone) when the blob was present, so writing through
		// config.* mutates the same shared object the dropdown refresh + Phase 1
		// page-load reads use. Gated on configPresent so a host page without the
		// blob writes to nothing rather than to the throwaway {} fallback.
		if ( configPresent ) {
			if ( 'status' in response ) {
				config.channel_status  = response.status || '';
				config.is_channel_live = response.status || '';
			}
			if ( response.whipUrl )      { config.whip_url     = response.whipUrl; }
			// QoS meter socket URL â€” surfaces here once the poll returns it
			// so startQosStats() (called from the live-stats lifecycle) can
			// open the socket without a page reload. Same guarded pattern as
			// whip_url: only assign when present, never blank a prior value.
			if ( response.qos_url_data ) {
				var qosUrlChanged = config.qos_url_data !== response.qos_url_data;
				if ( qosUrlChanged ) stopQosStats();
				config.qos_url_data = response.qos_url_data;
				// The meter can arrive after ICE connects; start (or retry) it
				// from maintenance updates too, not only the connected event.
				startQosStats( ! qosUrlChanged );
			}
			if ( response.obs_uri )      { config.obs_uri      = response.obs_uri; }
			if ( response.obs_stream )   { config.obs_stream   = response.obs_stream; }
			// Auxiliary fields baker may surface once the event is
			// fully active. Mirrored verbatim so a future helper can
			// read them off the vars without another round trip.
			if ( response.broadcastUrl ) { config.broadcast_url  = response.broadcastUrl; }
			if ( response.live_data_url ){ config.live_data_url = response.live_data_url; }
		}

		// NOTE: applyInfoFromResponse() no longer touches the channel-settings
		// lock. The lock is owned solely by the host's settings-lock poll
		// (dashboard-2026.js â€” a single always-on channel/info status poll, decoupled from the broadcast
		// lifecycle). This helper still mirrors config.channel_status and the
		// encoder readouts below, but the lock follows that dedicated poll.

		// --- (2) Update visible DOM elements ----------------------------
		// RTMP URL + Stream key spans live inside the External-encoder
		// panel (broadcaster.php "wpstream_portal_bc_side_external"). The
		// two .wpstream_portal_share_row_val spans are addressed
		// positionally inside that panel because the markup does not give
		// them per-field IDs. Defensive null-checks throughout â€” the
		// panel may be absent on host pages that mount a stripped variant
		// of the widget.
		try {
			var external = document.querySelector( '.wpstream_portal_bc_side_external' );
			if ( external ) {
				var rows = external.querySelectorAll( '.wpstream_portal_share_row' );

				// Row 0 = RTMP URL (obs_uri); Row 1 = Stream key (obs_stream).
				// Order is set by broadcaster.php and is part of the markup
				// contract; verified by the encoder-panel tests.
				if ( response.obs_uri && rows[0] ) {
					var rtmpSpan = rows[0].querySelector( '.wpstream_portal_share_row_val' );
					var rtmpBtn  = rows[0].querySelector( '.wpstream_portal_copy_btn' );
					if ( rtmpSpan ) { rtmpSpan.textContent = response.obs_uri; }
					if ( rtmpBtn )  { rtmpBtn.setAttribute( 'data-wpstream-portal-copy-value', response.obs_uri ); }
				}
				if ( response.obs_stream && rows[1] ) {
					var keySpan = rows[1].querySelector( '.wpstream_portal_share_row_val' );
					var keyBtn  = rows[1].querySelector( '.wpstream_portal_copy_btn' );
					if ( keySpan ) { keySpan.textContent = response.obs_stream; }
					if ( keyBtn )  { keyBtn.setAttribute( 'data-wpstream-portal-copy-value', response.obs_stream ); }
				}
			}
		} catch ( e ) {
			// DOM-update failures are non-fatal â€” log and keep going so a
			// markup variant on a future host page can't break the poll.
			console.warn( '[broadcaster] applyInfoFromResponse: DOM update failed', e );
		}

		// The server channel_status may have just changed â€” refresh the readout.
		updateChannelStateIndicator();

		// Reconcile the External-tab "Live on air" headline against the authoritative
		// status. If baker now reports the channel is NOT active, a stale player
		// 'onair' (the player can miss its 'ended' event, leaving "Live on air"
		// frozen on a channel that has actually stopped) must be downgraded â€” a
		// stopped channel cannot be streaming. DOWNGRADE-ONLY: we never force
		// 'onair' here, so interim player states ('starting'/'paused') on a genuinely
		// active channel are left untouched; we only correct the false live claim.
		// Excluded while OUR browser broadcast is publishing this channel â€” the
		// browser-live lockout legitimately shows on-air even if baker's status
		// briefly lags behind the WHIP publish.
		if ( config.channel_status && String( config.channel_status ) !== 'active' &&
		     ! selectedIsPublishing() ) {
			// applyEncoderState is module-internal (defined in this file).
			var encStateWrap = document.querySelector( '[data-wpstream-portal-bc-encoder-state]' );
			if ( encStateWrap && encStateWrap.getAttribute( 'data-wpstream-portal-bc-encoder-state' ) === 'onair' ) {
				applyEncoderState( 'stopped' );
			}
		}
	}

	/**
	 * pollEventReady()
	 *
	 * Phase 3 of the turn-on flow â€” wraps the 10s setInterval that polls
	 * wpstream_dashboard_check_event_status until the plumber event
	 * reports status === 'active'. Resolves with the FULL decoded event
	 * data (including whipUrl + obs_uri + obs_stream + chat_url +
	 * hls_playback_url + qos_url + stats_url) so the caller can hand the
	 * whipUrl to proceedWithStreaming() and stash the rest for the live
	 * stats panel.
	 *
	 * Why a separate fetch and not a reuse of checkChannelStatus():
	 * checkChannelStatus() resolves with a boolean (true=active,
	 * false=anything else) and the rest of the codebase depends on that
	 * stable signature. Per cycle #6a's "Hard constraints â€” do NOT do
	 * any of these" rule, we don't change checkChannelStatus's return
	 * shape. Instead, this helper makes its own fetch against the same
	 * endpoint and keeps the full response in scope.
	 *
	 * Early-start contract: resolution requires `response.whipUrl` present
	 * AND status either `'starting'` OR `'active'`. The WHIP endpoint at
	 * driver.wpstream.live is writable as soon as the channel reaches
	 * 'starting' â€” the backend 401-before-active issue (a WHIP POST fired
	 * before status flipped to 'active' returned 401 Unauthorized + no CORS)
	 * has been fixed, so we no longer wait for 'active'. (An earlier version
	 * resolved only at 'active' precisely because of that 401; that
	 * constraint no longer applies.) The whipUrl appears at 'starting' and
	 * the endpoint behind it is now writable there, so we go live earlier.
	 *
	 * On resolve the startup setInterval is cleared AND turnOnPollInterval
	 * is set back to null â€” this hands the cancel-vs-live discrimination in
	 * stopStreaming()/the Stop handler the correct answer (a later Stop is a
	 * LIVE stop, not a turn-on cancel) â€” then startMaintenancePoll() begins a
	 * separate ~10s "maintenance" poll (on maintenancePollInterval) that runs
	 * for the rest of the live session and tears the local session down on a
	 * backend-initiated 'stopped'/'error'. The 10s leading delay before the
	 * first startup tick is plugin-parity for the cold-start race window â€”
	 * see expertise.yml known_patterns. applyInfoFromResponse() runs on
	 * every tick BEFORE the status branch so encoder readouts populate
	 * the moment baker has them, regardless of resolve state.
	 *
	 * Status transitions:
	 *   - ('starting' | 'active') + whipUrl â†’ clearInterval + null
	 *                                     turnOnPollInterval, start the
	 *                                     maintenance poll, resolve(response).
	 *   - 'error' | 'stopped'            â†’ clearInterval, reject.
	 *   - '' | 'creating'                â†’ no UI change, keep ticking.
	 *   - 'starting' WITHOUT whipUrl     â†’ keep ticking until whipUrl arrives
	 *                                     (applyInfoFromResponse mirrors any
	 *                                     fields that did arrive).
	 *
	 * @param  {number} channelId  Baker per-user channel_id.
	 * @return {Promise<object>}   Resolves with the response object on the
	 *                             first tick where status is 'starting' or
	 *                             'active' AND whipUrl is present. Rejects on
	 *                             terminal 'error' / 'stopped'.
	 */
	function pollEventReady(channelId, generation, domain) {
		return new Promise(function (resolve, reject) {
			// Bail early if the AJAX URL is missing â€” same gate as
			// requestLiveUri above. No point starting a setInterval
			// against a misconfigured page.
			if (!config.ajax_url) {
				reject('Broadcaster vars missing â€” ajax_url not localized.');
				return;
			}

			// Internal tick â€” a self-contained ajaxPost + status decode.
			// Defined inside the Promise so the resolve/reject closures
			// are in scope. Returns nothing; resolve/reject ends the
			// caller's wait.
			var tickCount = 0;
			var stoppedReads = 0; // consecutive 'stopped' readings
			function tick() {
				tickCount += 1;
				log( '[broadcaster] Phase 3 tick', { n: tickCount, channelId: channelId } );

				ajaxPost( ACTIONS.check_event_status, { channel_id: channelId, domain: domain } )
					.then(function (response) {
						if ( generation !== turnOnGeneration ) return;
						log( '[wpstream_dashboard_check_event_status response â€” Phase 3 tick #' + tickCount + ']', response );

						// FIRST â€” mirror everything baker just told us
						// into vars + visible UI. Runs on every tick
						// (including post-resolve) so the encoder
						// readouts populate as soon as fields appear.
						if ( broadcastTarget && broadcastTarget.channelId === channelId && broadcastTarget.domain === domain ) {
							broadcastTarget.status = response && response.status || '';
							if ( response && response.whipUrl ) broadcastTarget.whipUrl = response.whipUrl;
						}
						var isViewedTarget = Number( channelId ) === Number( resolveSelectedChannelId() ) && domain === resolveSelectedChannelDomain();
						if ( isViewedTarget ) applyInfoFromResponse( response );

						// Keep the viewed-channel readout in sync ONLY when the user is looking at
						// this (the turning-on) channel â€” don't paint its status onto a channel
						// they switched away to. config.channel_status above stays mirrored for the
						// reconnect gate regardless.
						if ( isViewedTarget ) {
							viewedChannelStatus = response && response.status ? String( response.status ) : '';
						}

						// Phase 3 decision tree â€” branch on status.
						var status = response ? response.status : '';
						log( '[broadcaster] Phase 3 tick result', {
							n: tickCount,
							status: status,
							has_whipUrl: !!( response && response.whipUrl ),
						} );

						if ( ( status === 'starting' || status === 'active' ) && response.whipUrl ) {
							// Early-start: the WHIP endpoint is writable as soon as the channel reaches
							// 'starting' (backend 401-before-active issue fixed). Stop the fast startup
							// poll and null turnOnPollInterval so stopStreaming()/the Stop handler classify
							// a later Stop as a LIVE stop, then start the maintenance poll for the rest of
							// the session.
							if ( turnOnPollInterval !== null ) {
								clearInterval( turnOnPollInterval );
								turnOnPollInterval = null;
							}
							// Funnel telemetry â€” Phase 3 WHIP ready (SINGLE resolve
							// site). value_ms = Phase 3 provisioning wait since
							// event_start_accepted.
							tWhip = Date.now();
							emitFunnel( 'whip_ready', { attempt_id: currentAttemptId, mode: bcTelemetryMode(), phase: '3', value_ms: tWhip - tAccepted } );
							startMaintenancePoll( channelId, domain );
							resolve( response );
							return;
						}

						// A single 'stopped' right after Start can be a stale read from
						// before the backend flipped the channel, so it only counts as
						// terminal when two ticks in a row report it. 'error' stays
						// immediate. Any other status resets the run.
						stoppedReads = ( status === 'stopped' ) ? stoppedReads + 1 : 0;
						if ( status === 'stopped' && stoppedReads < 2 ) {
							log( '[broadcaster] Phase 3: first "stopped" reading ignored, confirming on next tick' );
							return;
						}

						if (status === 'error' || status === 'stopped') {
							// Terminal failure â€” clear the loop and
							// reject so the Phase 3 .catch in
							// startStreaming shows the error message
							// + resets the UI.
							if (turnOnPollInterval !== null) {
								clearInterval(turnOnPollInterval);
								turnOnPollInterval = null;
							}
							// Funnel telemetry â€” Phase 3 terminal failure. 'stopped'
							// and 'error' are distinct terminal reasons. Clear the
							// attempt span (terminal error).
							emitFunnel( 'broadcast_error', { attempt_id: currentAttemptId, mode: bcTelemetryMode(), phase: '3', error_code: ( status === 'stopped' ? 'poll_stopped' : 'poll_error' ) } );
							currentAttemptId = null;
							reject('Event reported status: ' + status);
							return;
						}

						// Any other status ('', 'creating', a 'starting'
						// tick that does NOT yet carry a whipUrl, or an
						// unknown future value): no UI change, keep ticking.
						// The 10s cadence is set by the setInterval below.
						// applyInfoFromResponse() above has already mirrored
						// whatever fields were present, so the External-encoder
						// readouts will fill in as plumber writes them.
					})
					.catch(function (err) {
						// Network errors on a single tick are non-fatal â€”
						// log and let the next tick try again. cancelTurnOn()
						// is the only path that ends a stuck poll.
						console.error('pollEventReady: tick failed', err);
					});
			}

			// Kick off the poll â€” store the handle in module scope so
			// cancelTurnOn() has something to clear. First tick fires
			// after the 10s interval (NOT immediately). Plugin parity:
			// plugins/plugin/public/js/start_streaming.js:237-239 does
			// the same â€” no immediate first tick. The 10s delay gives
			// plumber time to provision the event so baker's channel
			// record has transitioned to 'active' (or terminal 'error')
			// by the time the first poll lands. An immediate first
			// tick races the provisioning and sees the pre-start
			// 'stopped' status, which the poll's terminal-failure
			// branch then treats as a hard failure.
			turnOnPollInterval = setInterval(tick, 10000);
		});
	}

	/**
	 * startMaintenancePoll()
	 *
	 * Post-live "maintenance" poll â€” plugin parity with
	 * plugins/plugin/public/js/start_streaming.js. Started once by
	 * pollEventReady() at the moment the channel goes live (status
	 * 'starting'|'active' + whipUrl), AFTER the fast Phase 3 startup poll has
	 * been cleared and turnOnPollInterval nulled. Runs on its own separate
	 * handle (maintenancePollInterval) so the cancel-vs-live discrimination in
	 * stopStreaming()/the Stop handler â€” which both key off turnOnPollInterval
	 * â€” stays correct: once live, turnOnPollInterval is null and only this
	 * maintenance handle is non-null.
	 *
	 * Each 10s tick re-queries the same wpstream_dashboard_check_event_status
	 * endpoint as the startup poll, runs applyInfoFromResponse() to keep
	 * encoder/stat readouts fresh, and on a terminal backend status
	 * ('stopped'|'error') stops itself, disables auto-reconnect, and (if the
	 * stream is live) tears the local WHIP session down via stopStreaming().
	 *
	 * Idempotent â€” the single non-null guard means a double-start is a no-op,
	 * which is the entire correctness mechanism (no extra flags needed).
	 * Single-tick network/parse failures only console.error and continue â€”
	 * same non-fatal policy as the startup tick, which prevents error-toast
	 * spam during a live session.
	 *
	 * @param  {number} channelId  Baker per-user channel_id.
	 * @return {void}
	 */
	function startMaintenancePoll( channelId, domain ) {
		const generation = turnOnGeneration;
		if ( maintenancePollInterval !== null ) return; // idempotent — never double-start
		function maintenanceTick() {
			ajaxPost( ACTIONS.check_event_status, { channel_id: channelId, domain: domain } )
				.then(function(response){
					if (generation !== turnOnGeneration) return;
					if (broadcastTarget) {
						broadcastTarget.status = response && response.status || '';
						if (response && response.whipUrl) broadcastTarget.whipUrl = response.whipUrl;
					}
					// Repaint the visible encoder / stat / config cells ONLY when
					// the channel this poll tracks (the STREAMING channel) is still
					// the one selected in the dropdown. Once the user switches the
					// dropdown to view a DIFFERENT channel, that channel's display is
					// owned by onChannelChange — repainting here would clobber it back
					// to the streaming channel's RTMP/key/embed/qos every 10s (the
					// "channel switch doesn't stick" bug). The terminal-status check
					// below still runs unconditionally so we always detect a stop.
					if ( Number( channelId ) === Number( resolveSelectedChannelId() ) && domain === resolveSelectedChannelDomain() ) {
						applyInfoFromResponse( response );
						// The viewed-channel readout is ours to update only while the
						// streaming channel IS the selected one — same guard as above.
						viewedChannelStatus = response && response.status ? String( response.status ) : '';
					}
					var status = response ? response.status : '';
					if ( status === 'stopped' || status === 'error' ) {
						stopMaintenancePoll();
						considerReconnect = false;
						if ( streamingStarted ) { stopStreaming(); }
					}
				})
				// Single-tick network/parse failures are non-fatal — log and
				// let the next tick retry, same policy as the startup poll.
				// ajaxPost rejects parse failures ('Could not parse server
				// response.') and transport failures through here.
				.catch(function(err){ console.error('maintenancePoll: tick failed', err); });
		}
		maintenancePollInterval = setInterval( maintenanceTick, 10000 );
	}

	/**
	 * stopMaintenancePoll()
	 *
	 * Clears the maintenance poll setInterval if it is running and nulls the
	 * handle. Idempotent — safe to call when no poll is active. Invoked from
	 * the maintenance terminal branch, stopStreaming(), cancelTurnOn(), and
	 * resetStreamingUI() so no teardown path can leak the poll.
	 *
	 * @return {void}
	 */
	function stopMaintenancePoll() {
		if ( maintenancePollInterval !== null ) {
			clearInterval( maintenancePollInterval );
			maintenancePollInterval = null;
		}
	}

	/**
	 * cancelTurnOn()
	 *
	 * Cancel-branch cleanup helper â€” invoked from stopStreaming() to tear
	 * down whatever turn-on activity is alive (Phase 2 fetch / Phase 3 poll)
	 * WITHOUT calling the server. By design there is NO server-side
	 * /channel/stop: End Broadcast (and cancelling a start) stop the STREAMING
	 * only, never the channel â€” the backend reconciles when the publisher drops.
	 *
	 * Idempotent â€” null checks on both handles mean a double-click on
	 * Stop is safe.
	 *
	 * @return {void}
	 */
	function cancelTurnOn() {
		turnOnGeneration += 1;
		mediaGeneration += 1;
		if ( turnOnTimeoutId !== null ) {
			clearTimeout( turnOnTimeoutId );
			turnOnTimeoutId = null;
		}
		// Clear the Phase 3 poll loop if running.
		if (turnOnPollInterval !== null) {
			clearInterval(turnOnPollInterval);
			turnOnPollInterval = null;
		}

		// Clear the post-live maintenance poll for symmetry/safety. No-op if
		// it was never started (cancel happens before we go live).
		stopMaintenancePoll();

		// Abort the in-flight Phase 2 XHR if any. The fetch() inside
		// requestLiveUri() catches the resulting AbortError and rejects
		// its Promise with 'Aborted.' â€” the caller can ignore that
		// rejection since the user explicitly cancelled.
		if (turnOnAbortController !== null) {
			turnOnAbortController.abort();
			turnOnAbortController = null;
		}
	}

	/**
	 * proceedWithStreaming( whipUrl, isReconnect )
	 *
	 * Phase 4 of the turn-on flow per broadcaster-turn-on-flow.html. Takes
	 * the WHIP ingest URL and hands it to OvenLiveKit's startStreaming(),
	 * which performs the WHIP handshake (SDP offer â†’ answer â†’ ICE) and
	 * begins pushing the camera + mic tracks over the negotiated
	 * RTCPeerConnection. The whipUrl can reach this function from two
	 * paths:
	 *   - Phase 3 poll completion â€” the normal cold-start path; whipUrl
	 *     comes from the active-tick response.
	 *   - Phase 1 short-circuit (reconnect path) â€” when a fresh page
	 *     already has an active event, startStreaming() skips Phase 2/3
	 *     entirely and passes response.whipUrl from Phase 1 here.
	 *
	 * No backend calls happen in this function â€” Phase 4 is pure
	 * client-side WebRTC. Server-side awareness of the live state comes
	 * later from SNS callbacks updating the channel record on the prod DB
	 * (which baker reads back via /channel/info on the next dashboard
	 * load or poll tick).
	 *
	 * @param {string}  whipUrlParam   WHIP ingest URL â€” the per-call source
	 *                                 of truth. The parameter is assigned
	 *                                 back to the module-scope `whipUrl`
	 *                                 variable so the reconnect path
	 *                                 (createInput / attemptReconnect) can
	 *                                 continue to reuse the same value
	 *                                 without re-firing Phase 2.
	 * @param {boolean} isReconnect    True when called from the reconnect
	 *                                 path so logging / reconnect-disable
	 *                                 semantics behave correctly.
	 */
	function proceedWithStreaming( whipUrlParam, isReconnect ) {
		// Cycle #6b spec signal â€” the orchestration test spies on this
		// console line to verify which phase's whipUrl reached Phase 4.
		// Format is load-bearing for the regex in
		// broadcaster-startstreaming-orchestration.spec.ts.
		log( '[broadcaster] proceedWithStreaming whipUrl=', whipUrlParam );

		// Cycle #6d â€” exit the turning-on UI state. The next call to
		// updateStatus('connecting') below repaints the indicator with
		// its 'connecting' modifier; ICE 'connected' will swing it to
		// 'connected'. Either way, the amber pulse / "Startingâ€¦" copy /
		// "Preparing your channelâ€¦" overlay are all gone by the time
		// the WHIP handshake begins. See "UI state machine during
		// turn-on" notes block in broadcaster-turn-on-flow.html.
		setTurningOnUI( false );
		setConnectingControls();

		// The parameter is now the source of truth â€” push it into
		// module-scope so the reconnect path that reads `whipUrl`
		// directly continues to work.
		if ( whipUrlParam ) {
			whipUrl = whipUrlParam;
		}

		streamingStarted = true;
		// If a reconnect was pending, cancel it as we're actively starting now
		if (pendingReconnect && pendingReconnectTimeout) {
			clearTimeout(pendingReconnectTimeout);
			pendingReconnect = false;
			pendingReconnectTimeout = null;
		}

		updateStatus("connecting");

		// The handshake is starting, so the publishing channel's cells are pending.
		// A different viewed channel keeps its own stats (the cells follow the view).
		if ( selectedIsPublishing() ) {
			setLiveStatsLoading();
		}

		// Swap to the LIVE panel ONLY when the channel we just went live on is the one
		// the user is viewing. streamingStarted was set true just above, so
		// selectedIsPublishing() is the authoritative "is the viewed channel ours and
		// live" check. If the user switched to a DIFFERENT channel during turn-on,
		// forcing the LIVE panel here would flash "End Broadcast" onto that idle channel
		// until updateStatus('connected')â†’syncBcState() corrected it â€” render the viewed
		// channel's own lifecycle state instead.
		if ( ! selectedIsPublishing() ) syncBcState();

		log( '[broadcaster] proceedWithStreaming: WHIP handshake decision', { has_input: !!input, has_whipUrl: !!whipUrl, isReconnect: !!isReconnect } );
		if (input && whipUrl) {
			let connectionConfig = {};

			// Begin streaming; mark that we should auto-reconnect on unexpected disconnects
			considerReconnect = true;
			var attempt = watchWhipAttempt(input);
			try {
				var result = input.startStreaming(whipUrl, connectionConfig);
				// Also support SDK versions that return a rejected start promise.
				if (result && typeof result.catch === 'function') {
					result.catch(function () { failWhipAttempt(attempt); });
				}
			} catch (error) {
				failWhipAttempt(attempt);
				return;
			}
			if (pendingWhipAttempt !== attempt) return;
			// updateStatus("connected");
			if ( isReconnect ) {
				log('Reconnected successfully!');
				// showMessage("Reconnected successfully!", "success");
			}
			updateInputState(true);
		} else {
			// Teardown #4 â€” single control-reset cluster. Our start attempt
			// failed, so the channel is ours (not external-live) â†’ ungated
			// re-enable so the user can retry.
			bcTeardownControls( false );
			// streamingStarted and the publishing channel were set above, before
			// the input/WHIP check; nothing is publishing, so clear them or the
			// LIVE panel keeps treating this channel as live.
			streamingStarted = false;
			publishingChannelId = 0;
			publishingChannelDomain = '';
			// Funnel telemetry â€” Phase 4 terminal failure (missing input or WHIP
			// URL â†’ OvenLiveKit.startStreaming never ran). Clear the attempt span.
			emitFunnel( 'broadcast_error', { attempt_id: currentAttemptId, mode: bcTelemetryMode(), phase: '4', error_code: 'whip_failed' } );
			currentAttemptId = null;
			// Log presence only â€” never the WHIP URL (it carries ingest credentials).
			log( '[broadcaster] proceedWithStreaming: cannot start â€” missing input or WHIP URL', { has_input: !!input, has_whipUrl: !!whipUrl } );
			showMessage("Error: No WHIP URL configured", "error");
			updateStatus("disconnected");

			// Stop reconnecting if there's an error
			if ( isReconnect ) {
				considerReconnect = false;
			}
		}
	}

	function resetStreamingUI() {
		cancelTurnOn();
		considerReconnect = false;
		if ( pendingReconnectTimeout !== null ) {
			clearTimeout( pendingReconnectTimeout );
			pendingReconnectTimeout = null;
		}
		pendingReconnect = false;
		publishingChannelId = 0;
		publishingChannelDomain = '';
		broadcastTarget = null;
		if ( ! localStream && input ) {
			const pendingInput = input;
			input = null;
			pendingInput.remove();
		}
		if ( loadSpinner ) wpstreamBroadcasterDisplay( loadSpinner, 'none' );
		log( '[broadcaster] resetStreamingUI() â€” failure/cancel reset path' );
		// Cycle #6d â€” exit the turning-on UI state FIRST so the indicator
		// dot's 'turning-on' modifier is dropped before updateStatus()
		// repaints the dot with 'disconnected'. Also restores the Start
		// button's original label + re-enables the click, which the manual
		// streamingButton.disabled = false below would otherwise overwrite
		// without restoring the text. Safe to call when not in turning-on
		// (no-op on the wrapper class removal + textContent restore from
		// a null cache).
		setTurningOnUI( false );

		// Central failure/reset path â€” also clear the maintenance poll so the
		// reconnect-failure leak is closed (went live â†’ maintenance poll
		// running â†’ RTC drops â†’ attemptReconnect finds channel inactive â†’
		// resetStreamingUI). Idempotent; no-op on the success path.
		stopMaintenancePoll();

		updateStatus("disconnected");
		// Teardown #3 â€” single control-reset cluster. Ungated re-enable: this is
		// the failure/cancel reset path for our own start attempt, never an
		// external-live channel, so Start is unconditionally re-enabled (matches
		// the prior explicit streamingButton.disabled = false here).
		bcTeardownControls( false );
	}

	/* ============================================================
	   MOVED FROM dashboard-2026.js (module self-containment)
	   ------------------------------------------------------------
	   The encoder tabs, channel-select dropdown, cam/mic toggle stub,
	   external-encoder setup modal, the encoder-state headline
	   (BC_ENCODER_STATE_COPY / applyEncoderState) and the player-iframe
	   postMessage listener used to live in the host script. They drive
	   ONLY broadcaster markup, and the module ships as one folder with no
	   shared files, so they now live here. Logic is verbatim; the only
	   changes are the module's ACTIONS map for the AJAX action and the
	   encoder-tab lookup scoped to the broadcaster root.
	   ============================================================ */

	/* ============================================================
	   ENCODER TABS
	   Clicking a .wpstream_portal_encoder_tab button activates it
	   by adding the .active class and removing it from siblings.
	   ============================================================ */
	// Local name bcEncoderTabs: this file already has an unrelated `encoderTabs`
	// NodeList (the mode-tab click wiring). Exported as broadcaster.encoderTabs.
	var bcEncoderTabs = {

		/**
		 * Initialise encoder tab click handlers.
		 * Uses event delegation on the tabs container.
		 */
		init: function () {
			/* Find every encoder-tabs container inside a broadcaster root. Scoped
			   to the module root: the channel-detail Analytics perf-range tabs
			   reuse the tab class but are host markup the module must not touch. */
			var containers = document.querySelectorAll('.wpstream_portal_broadcaster .wpstream_portal_encoder_tabs');
			containers.forEach(function (container) {
				container.addEventListener('click', function (e) {
					var btn = e.target.closest('.wpstream_portal_encoder_tab');
					if (!btn || btn.disabled) return;

					/* Remove active from all siblings, add to clicked */
					container.querySelectorAll('.wpstream_portal_encoder_tab').forEach(function (b) {
						b.classList.remove('active');
					});
					btn.classList.add('active');
				});
			});
		}
	};

	/* ============================================================
	   CHANNEL SELECT DROPDOWN
	   Toggle .wpstream_portal_popover_open on the channel popover.
	   Selecting an option updates the visible label text and closes.
	   Clicking outside closes the popover.
	   ============================================================ */
	var channelSelect = {

		/** @private ID of currently-open channel popover (string or null) */
		_open: null,

		/**
		 * Initialise the channel-select dropdown.
		 * Attaches listeners to the toggle button and all option buttons.
		 */
		init: function () {
			var self = channelSelect;

			/* Toggle button â€” opens/closes the popover */
			var btn = document.getElementById('wpstream_portal_channel_select_btn');
			if (btn) {
				btn.addEventListener('click', function (e) {
					e.stopPropagation();
					self._toggle();
				});
			}

			/* Option buttons â€” update label and close */
			var popover = document.getElementById('wpstream_portal_channel_popover');
			if (popover) {
				popover.addEventListener('click', function (e) {
					// Both create actions notify the host; creation UI and navigation
					// stay outside this transportable module, including detached mounts.
					if (e.target.closest('.wpstream_portal_channel_create')) {
						e.preventDefault();
						self._close();
						var createRoot = popover.closest('.wpstream_portal_broadcaster') ||
							document.querySelector('.wpstream_portal_broadcaster');
						if (createRoot && typeof window.CustomEvent === 'function') {
							createRoot.dispatchEvent(new CustomEvent('wpstream-portal-bc:channel-create', { bubbles: true }));
						}
						return;
					}
					var opt = e.target.closest('[data-wpstream-portal-channel-id]');
					if (!opt) return;

					/* Update displayed channel name */
					var title = opt.getAttribute('data-wpstream-portal-channel-title');
					var nameEl = document.getElementById('wpstream_portal_channel_select_name');
					if (nameEl && title) {
						nameEl.textContent = title;
					}

					/* Mark this option selected, unmark others */
					popover.querySelectorAll('[data-wpstream-portal-channel-id]').forEach(function (o) {
						o.classList.remove('wpstream_portal_channel_option_selected');
						o.setAttribute('aria-selected', 'false');
					});
					opt.classList.add('wpstream_portal_channel_option_selected');
					opt.setAttribute('aria-selected', 'true');

					/* Propagate the picked channel id back to the broadcaster's
					 * localized vars â€” otherwise broadcaster.js keeps sending the
					 * page-load default and we get a silent wrong-channel bug. */
					var pickedIdRaw = opt.getAttribute('data-wpstream-portal-channel-id');
					var pickedId    = pickedIdRaw ? parseInt(pickedIdRaw, 10) : 0;
					/* The picked channel's domain â€” part of baker's channel
					 * identity (ch_wps_<user>_<domain_hash>_<channel_id>). Read
					 * off the same option so the refresh POST below (and every
					 * subsequent broadcaster POST) targets the right domain for
					 * channels created under any domain the user owns. Empty
					 * string when the attribute is absent (older markup). */
					var pickedDomainRaw = opt.getAttribute('data-wpstream-portal-channel-domain');
					var pickedDomain    = pickedDomainRaw ? pickedDomainRaw : '';
					if (pickedId > 0 && typeof window.wpstream_broadcaster_vars === 'object' && window.wpstream_broadcaster_vars) {
						window.wpstream_broadcaster_vars.channel_id = pickedId;
						/* Keep the localized default-domain in sync so
						 * broadcaster.js resolveSelectedChannelDomain() falls
						 * back to the right domain even before the DOM selection
						 * settles. */
						window.wpstream_broadcaster_vars.channel_domain = pickedDomain;
						// Lock before the async read: the old channel's setting must never be saved onto this one.
						recordingRevision++;
						recordingSaving = false;
						recordingLoading = true;
						recordingError = '';
						viewedChannelStatus = '';
						config.record = false;
						enableStartButton();

						/* Refresh the page-load channel/info values
						 * (channel_status + whip_url) for the newly
						 * picked channel. broadcaster.js reads these
						 * synchronously at the top of startStreaming()
						 * as its Phase 1 gate (replaces the previous
						 * AJAX round-trip). Without this refresh the
						 * dashboard would still hold the OLD channel's
						 * status when the user clicks Start. */
						var vars  = window.wpstream_broadcaster_vars;
						var nonceEl = document.getElementById('wpstream_start_event_nonce');
						var nonce   = nonceEl ? nonceEl.value : '';

						if (vars.ajax_url && nonce) {
							var body = new URLSearchParams();
							body.set('action',     ACTIONS.check_event_status);
							body.set('nonce',      nonce);
							body.set('channel_id', String(pickedId));
							/* Domain travels with the channel id so baker can
							 * resolve cross-domain channels. Falls back to
							 * wpstream.net (matches the PHP handler fallback). */
							body.set('domain',     pickedDomain || 'wpstream.net');

							/* Grey out the side panel while the picked channel's data is in flight;
							   removed in the fetch's .finally() once check_event_status resolves. */
							var bcSidePanel = document.querySelector('.wpstream_portal_bc_side');
							if (bcSidePanel) { bcSidePanel.classList.add('wpstream_portal_bc_side--loading'); }

							fetch(vars.ajax_url, {
								method:      'POST',
								credentials: 'include',
								headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
								body:        body.toString(),
							})
								.then(function (res) { return res.json(); })
								.then(function (response) {
									log('[wpstream_dashboard_check_event_status response â€” dropdown refresh]', response);
									// A slower response from an earlier selection cannot repaint the current channel.
									if (Number(resolveSelectedChannelId()) !== pickedId || resolveSelectedChannelDomain() !== (pickedDomain || 'wpstream.net')) return;
									if (response && response.success) {
										vars.channel_status  = response.status  || '';
										vars.whip_url        = response.whipUrl || '';
										vars.is_channel_live = response.status  || '';

										/* Derive RTMP URL + stream key from
										 * baker's broadcast_url. Same parser as
										 * the server-side render in broadcaster.php
										 * and the plugin's
										 * class-wpstream-live-api-connection.php:298-300.
										 * broadcast_url is the single source of
										 * truth â€” obs_uri / obs_stream postmeta
										 * are no longer read. */
										var bcastUrl         = response.broadcast_url || '';
										var derivedObsUri    = '';
										var derivedObsStream = '';
										if (bcastUrl !== '') {
											var lastSlash = bcastUrl.lastIndexOf('/');
											if (lastSlash !== -1) {
												derivedObsUri    = bcastUrl.substring(0, lastSlash);
												derivedObsStream = bcastUrl.substring(lastSlash + 1);
											}
										}
										vars.broadcast_url = bcastUrl;
										vars.obs_uri       = derivedObsUri;
										vars.obs_stream    = derivedObsStream;

										/* Refresh the visible RTMP URL + stream key
										 * cells in the External-encoder side panel.
										 * Wrapper data attributes are emitted by
										 * broadcaster.php; the value span + copy
										 * button are addressed relative to the
										 * wrapper so the markup can be reordered
										 * without breaking this code. */
										var placeholder = 'No channel selected';
										var rtmpWrap    = document.querySelector('[data-wpstream-portal-bc-rtmp]');
										if (rtmpWrap) {
											var rtmpVal = rtmpWrap.querySelector('.wpstream_portal_share_row_val');
											var rtmpBtn = rtmpWrap.querySelector('.wpstream_portal_copy_btn');
											if (rtmpVal) { rtmpVal.textContent = derivedObsUri !== '' ? derivedObsUri : placeholder; }
											if (rtmpBtn) { rtmpBtn.setAttribute('data-wpstream-portal-copy-value', derivedObsUri); }
										}
										var keyWrap = document.querySelector('[data-wpstream-portal-bc-streamkey]');
										if (keyWrap) {
											var keyVal = keyWrap.querySelector('.wpstream_portal_share_row_val');
											var keyBtn = keyWrap.querySelector('.wpstream_portal_copy_btn');
											if (keyVal) { keyVal.textContent = derivedObsStream !== '' ? derivedObsStream : placeholder; }
											if (keyBtn) { keyBtn.setAttribute('data-wpstream-portal-copy-value', derivedObsStream); }
										}

										/* Keep the External-encoder setup modal in
										 * sync with the side panel â€” ONE data source.
										 * Pass the freshly derived obs_uri/obs_stream
										 * directly so the modal's Server / Stream-Key /
										 * Larix-RTMP / QR all reflect the newly-picked
										 * channel without a second fetch. */
										if (window.wpstreamPortal && window.wpstreamPortal.broadcaster &&
											window.wpstreamPortal.broadcaster.encoderSetup) {
											window.wpstreamPortal.broadcaster.encoderSetup.refresh(derivedObsUri, derivedObsStream);
										}
										/* embed_url â€” fresh per channel so the External-encoder
										 * preview iframe (broadcaster.js, mode === 'external')
										 * reads the right value on its next click. Empty-string
										 * guard mirrors the other vars.* updates above. */
										vars.embed_url       = response.embedUrl || '';

										/* If the External iframe is currently visible (user is
										 * in external mode and just picked a different channel),
										 * update its src live so the preview reflects the new
										 * channel without a tab toggle. about:blank fallback
										 * keeps the iframe from reloading the parent page when
										 * the embed URL is missing. */
										var liveExternalIframe = document.getElementById('wpstreamPortalExternalEmbed');
										if (liveExternalIframe && getComputedStyle(liveExternalIframe).display !== 'none') {
											/* Append startMuted=true exactly like broadcaster.js's
											 * external-tab handler does â€” browsers BLOCK unmuted
											 * autoplay, so without this the preview would not auto-
											 * play after a channel switch. about:blank fallback when
											 * there is no embed URL. */
											var switchEmbedUrl = vars.embed_url || '';
											if (switchEmbedUrl !== '' && switchEmbedUrl.indexOf('startMuted') === -1) {
												switchEmbedUrl += '&startMuted=true';
											}
											liveExternalIframe.src = switchEmbedUrl !== '' ? switchEmbedUrl : 'about:blank';
										}

										/* Refresh embed-code strip + copy-button payload
										 * to point at the newly-picked channel. If baker
										 * didn't return an embedUrl, leave the existing
										 * page-load snippet alone as a fallback.
										 *
										 * FORMAT LOCKSTEP: this string MUST stay byte-identical
										 * to the PHP helper wpstream_portal_build_embed_iframe()
										 * in dashboard-files/dashboard-helpers.php. Both emit a
										 * self-contained 560x315 (16:9) iframe whose aspect ratio
										 * survives wp_kses / editor stripping of inline styles â€”
										 * so the copied snippet is identical whether the page
										 * loaded fresh (PHP) or the channel was switched (this JS).
										 * Change one, change the other.
										 *
										 * XSS note: embedUrl is interpolated raw but is only ever
										 * assigned via textContent (below) and as an attribute
										 * value copied to the clipboard â€” it is never injected as
										 * innerHTML, so it is not parsed as live HTML. The PHP
										 * helper esc_attr()s the same value for its echo path. */
										if (response.embedUrl) {
											var embedSnippet = '<iframe src="' + response.embedUrl + '" width="560" height="315" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen class="wpstream-embedded-player"></iframe>';
											var embedCodeEl = document.querySelector('.wpstream_portal_bc_embed_code code');
											/* Scope the copy button to the embed strip â€” a page-wide
											 * query returns the FIRST .wpstream_portal_copy_btn (the
											 * RTMP one in the External panel), which left the embed
											 * strip's own copy button on the stale page-load value. */
											var copyBtnEl   = document.querySelector('.wpstream_portal_bc_embed .wpstream_portal_copy_btn[data-wpstream-portal-copy]');
											if (embedCodeEl) { embedCodeEl.textContent = embedSnippet; }
											if (copyBtnEl)   { copyBtnEl.setAttribute('data-wpstream-portal-copy-value', embedSnippet); }
										}

										/* Refresh the Astro + Shopify embed tabs. Both
										 * display the raw contentId string baker returns
										 * in the same /channel/info row as embedUrl (the
										 * AJAX handler normalises it to a defined string,
										 * '' when absent). Scope to the two named tab
										 * panels so the Web-Embed-first DOM ordering the
										 * dropdown-refresh above relies on is left intact.
										 * Runs unconditionally (NOT gated on embedUrl): a
										 * channel may have a contentId without an embedUrl,
										 * and the empty-string case must still CLEAR the
										 * stale value rather than leave the previous
										 * channel's contentId showing. */
										var newContentId = response.contentId || '';
										['astro', 'shopify', 'wix'].forEach(function (panelId) {
											var panel = document.querySelector('.wpstream_portal_bc_embed [data-wpstream-portal-tab-panel="' + panelId + '"]');
											if (!panel) { return; }
											var codeEl = panel.querySelector('.wpstream_portal_bc_embed_code code');
											var copyEl = panel.querySelector('.wpstream_portal_copy_btn[data-wpstream-portal-copy]');
											if (codeEl) { codeEl.textContent = newContentId; }
											if (copyEl) { copyEl.setAttribute('data-wpstream-portal-copy-value', newContentId); }
										});
									} else {
										/* Failure â†’ clear stale values so Phase 1
										 * doesn't short-circuit on the old channel's
										 * cached state. */
										vars.channel_status  = '';
										vars.whip_url        = '';
										vars.is_channel_live = '';
									}

									/* Delegate the broadcaster STATE + QoS rebind to
									 * broadcaster.js, which owns the lifecycle state
									 * machine and the QoS meter socket. This closes the
									 * stale-sidebar bug: it repoints config.qos_url_data,
									 * tears down/resets/reopens the QoS socket + live-stat
									 * cells for the selected channel, and re-runs the
									 * state machine so the Setup/Live/External panel +
									 * Start/End buttons reflect the SELECTED channel â€” all
									 * WITHOUT disturbing any in-progress browser publish.
									 * Runs in both success + failure branches (on failure
									 * `response` carries no qos_url_data, so onChannelChange
									 * resets the stats and closes the old socket). Null-safe
									 * so host pages without broadcaster.js are unaffected. */
									if (window.wpstreamPortal && window.wpstreamPortal.broadcaster &&
										typeof window.wpstreamPortal.broadcaster.onChannelChange === 'function') {
										window.wpstreamPortal.broadcaster.onChannelChange(pickedId, response);
									}

									/* MODULE -> HOST SEAM (AGENTS.md rule 2): tell the
									 * host page which channel the dropdown now shows,
									 * as a CustomEvent on the module root. The host
									 * (e.g. the dashboard's Statistics / Channel
									 * settings panels) listens on document and never
									 * reads module markup for this. Fired after the
									 * check_event_status response is applied and the
									 * module state is rebound, in both the success and
									 * failure branches. detail.domain is '' when the
									 * option carries no domain (host applies its own
									 * fallback). The picker may be rendered detached
									 * (outside the card, in a host slot), so fall back
									 * to the page's module root when closest() misses. */
									var bcRoot = popover.closest('.wpstream_portal_broadcaster') ||
										document.querySelector('.wpstream_portal_broadcaster');
									if (bcRoot && typeof window.CustomEvent === 'function') {
										bcRoot.dispatchEvent(new CustomEvent('wpstream-portal-bc:channel-change', {
											bubbles: true,
											detail:  { channelId: pickedId, domain: pickedDomain, response: response }
										}));
									}
								})
								.catch(function (err) {
									console.error('Dropdown channel/info refresh failed:', err);
								})
								/* Always lift the grey-out once the request settles â€” success
								   OR failure â€” so the panel can never stay stuck dimmed. */
								.finally(function () {
									if (bcSidePanel) { bcSidePanel.classList.remove('wpstream_portal_bc_side--loading'); }
								});
						}
					}

					self._close();
				});
			}

			/* Outside click closes the dropdown */
			document.addEventListener('mousedown', function (e) {
				var wrap = document.getElementById('wpstream_portal_channel_select_wrap');
				if (wrap && !wrap.contains(e.target)) {
					self._close();
				}
			});
		},

		/**
		 * Toggle the channel popover open/closed.
		 * @private
		 */
		_toggle: function () {
			var popover = document.getElementById('wpstream_portal_channel_popover');
			var btn     = document.getElementById('wpstream_portal_channel_select_btn');
			if (!popover) return;

			var isOpen = popover.classList.contains('wpstream_portal_popover_open');
			if (isOpen) {
				this._close();
			} else {
				/* Clear any inline display:none so the CSS open class wins */
				wpstreamBroadcasterDisplay(popover, '');
				popover.classList.add('wpstream_portal_popover_open');
				if (btn) btn.setAttribute('aria-expanded', 'true');
			}
		},

		/**
		 * Close the channel popover.
		 * @private
		 */
		_close: function () {
			var popover = document.getElementById('wpstream_portal_channel_popover');
			var btn     = document.getElementById('wpstream_portal_channel_select_btn');
			if (!popover) return;
			popover.classList.remove('wpstream_portal_popover_open');
			wpstreamBroadcasterDisplay(popover, 'none');
			if (btn) btn.setAttribute('aria-expanded', 'false');
		}
	};

	/* ============================================================
	   CAM / MIC TOGGLE BUTTONS  (SUPERSEDED â€” now owned by broadcaster.js)
	   ------------------------------------------------------------
	   This was the Stage-1 mock: a delegated click that flipped the
	   .on / .off class + swapped the inline SVG on the cam/mic toggle
	   buttons (#videoToggle / #audioToggle) for visual demo only.

	   Stage-4 broadcaster.js now implements the REAL toggle â€” it flips
	   MediaStreamTrack.enabled on the captured getUserMedia stream AND
	   updates the same .on/.off class + glyph (see broadcaster.js
	   setToggleButtonState / toggleVideo / toggleAudio). Running both
	   handlers double-flipped the class: broadcaster.js set .off, this
	   mock immediately toggled it back to .on, so the button appeared
	   inert even though the track muted. The mock is therefore disabled
	   (init() is a no-op). The cam/mic toggle is a broadcaster-domain
	   concern; this stub stays only to keep the namespace export stable.
	   ============================================================ */
	var bcToggle = {
		/**
		 * No-op. Real cam/mic toggle lives in broadcaster.js (Stage 4).
		 */
		init: function () {}
	};

	/* ============================================================
	   EXTERNAL-ENCODER SETUP MODAL
	   Drives the "Go Live with External Streaming App" modal opened
	   by the broadcaster's "Full encoder setup" button.

	   Responsibilities (and ONLY these â€” open/close + copy are served
	   by the module UI handler, moduleUi, below):
		 1. Encoder switching â€” the <select data-wpstream-portal-encoder-pick>
			change shows the matching .wpstream_portal_encoder_block and
			hides the rest (same UX as the plugin's
			.wpstream_external_broadcast_options handler).
		 2. Value rendering â€” fills every block's Server / Stream-Key
			spans (+ their copy-button payloads) and the Larix combined
			RTMP + QR image, reading from window.wpstream_broadcaster_vars
			(obs_uri / obs_stream) so there is ONE source of truth shared
			with the External-encoder side panel.

	   refresh() is exposed so the channel-dropdown change handler can
	   call it after it updates wpstream_broadcaster_vars â€” keeping the
	   modal in sync with the side panel without a second fetch path.
	   ============================================================ */
	var encoderSetup = {

		/** Placeholder text shown when no channel is selected / no ingest URL. */
		_placeholder: 'No channel selected',

		/**
		 * Show one encoder block, hide all others.
		 * @param {string} value  The encoder block key (matches the
		 *                         <option> value, e.g. "wpstream_obs_settings").
		 * @private
		 */
		_showBlock: function (value) {
			var blocks = document.querySelectorAll('[data-wpstream-portal-encoder-block]');
			blocks.forEach(function (block) {
				var match = block.getAttribute('data-wpstream-portal-encoder-block') === value;
				wpstreamBroadcasterDisplay(block, match ? '' : 'none');
			});
			/* Re-render the QR once the Larix block becomes visible so its
			   <img> gets a src only when shown (avoids an off-screen request). */
			encoderSetup.refresh();
		},

		/**
		 * Build the tec-it QR image URL for a Larix deep link.
		 * Matches plugin/public/js/start_streaming.js:391+398 exactly,
		 * including the double-encode (the data= param holds the already
		 * encoded larix:// URL) so the QR scans identically.
		 * @param {string} larixRtmp  The combined obs_uri + obs_stream string.
		 * @returns {string}          The QR image src, or '' when no RTMP.
		 * @private
		 */
		_buildQrSrc: function (larixRtmp) {
			if (!larixRtmp) { return ''; }
			var larixQr = 'larix://set/v1?conn[][url]=' + encodeURIComponent(larixRtmp);
			if (typeof window.qrcode !== 'function') { return ''; }
            var qr = window.qrcode(0, 'M');
            qr.addData(larixQr);
            qr.make();
            return qr.createDataURL(4, 8);
		},

		/**
		 * Re-render all modal values from the current
		 * wpstream_broadcaster_vars (obs_uri / obs_stream). Idempotent â€”
		 * safe to call on modal open, encoder switch, or channel change.
		 *
		 * @param {string} [uriOverride]  Optional obs_uri to use instead of
		 *                                the localized var (used by the
		 *                                channel-change handler which already
		 *                                holds the fresh derived values).
		 * @param {string} [keyOverride]  Optional obs_stream override.
		 */
		refresh: function (uriOverride, keyOverride) {
			var vars = (typeof window.wpstream_broadcaster_vars === 'object' && window.wpstream_broadcaster_vars)
				? window.wpstream_broadcaster_vars : {};

			/* Resolve the single source of truth. Explicit overrides win
			   (the channel-change handler passes freshly derived values);
			   otherwise read the localized vars. */
			var uri = (typeof uriOverride === 'string') ? uriOverride : (vars.obs_uri || '');
			var key = (typeof keyOverride === 'string') ? keyOverride : (vars.obs_stream || '');

			/* Larix combined RTMP â€” obs_uri + '/' + obs_stream
			   (obs_uri stops before the last '/'), so the slash goes back in. */
			var larixRtmp = (uri !== '' && key !== '') ? (uri + '/' + key) : '';

			var ph = encoderSetup._placeholder;

			/* Standard two-field blocks â€” Server / URL / Address span + key span. */
			document.querySelectorAll('[data-wpstream-portal-encoder-uri]').forEach(function (span) {
				span.textContent = uri !== '' ? uri : ph;
				var btn = span.parentElement ? span.parentElement.querySelector('.wpstream_portal_copy_btn') : null;
				if (btn) { btn.setAttribute('data-wpstream-portal-copy-value', uri); }
			});
			document.querySelectorAll('[data-wpstream-portal-encoder-key]').forEach(function (span) {
				span.textContent = key !== '' ? key : ph;
				var btn = span.parentElement ? span.parentElement.querySelector('.wpstream_portal_copy_btn') : null;
				if (btn) { btn.setAttribute('data-wpstream-portal-copy-value', key); }
			});

			/* Larix combined-RTMP span + copy payload. */
			document.querySelectorAll('[data-wpstream-portal-larix-rtmp]').forEach(function (span) {
				span.textContent = larixRtmp !== '' ? larixRtmp : ph;
				var btn = span.parentElement ? span.parentElement.querySelector('.wpstream_portal_copy_btn') : null;
				if (btn) { btn.setAttribute('data-wpstream-portal-copy-value', larixRtmp); }
			});

			/* Larix QR image â€” only set src when the Larix block is visible
			   so the tec-it request fires lazily and the stream key isn't
			   sent to a third party until the user actually opens Larix. */
			document.querySelectorAll('[data-wpstream-portal-larix-qr]').forEach(function (img) {
				var block = img.closest('[data-wpstream-portal-encoder-block]');
				var visible = block && getComputedStyle(block).display !== 'none';
				var src = (visible && larixRtmp !== '') ? encoderSetup._buildQrSrc(larixRtmp) : '';
				/* Avoid re-triggering an identical request on every refresh. */
				if (img.getAttribute('src') !== src) { img.setAttribute('src', src); }
			});
		},

		/**
		 * Initialise the encoder <select> change handler + a one-time
		 * refresh when the modal is opened.
		 */
		init: function () {
			/* Encoder switching via the select. Delegated so it survives
			   re-renders of the modal markup. */
			document.addEventListener('change', function (e) {
				var sel = e.target.closest('[data-wpstream-portal-encoder-pick]');
				if (!sel) return;
				encoderSetup._showBlock(sel.value);
			});

			/* When the "Full encoder setup" button opens the modal, refresh
			   values so they reflect the currently-selected channel even if
			   it changed since page load. The module UI handler
			   (moduleUi) opens the modal from the root's own click listener,
			   which runs before this document-level one; we just re-render. */
			document.addEventListener('click', function (e) {
				var opener = e.target.closest('[data-wpstream-portal-modal-open="wpstream_portal_encoder_setup_modal"]');
				if (!opener) return;
				/* Defer one tick so the modal is visible (display cleared)
				   before we compute QR visibility. */
				setTimeout(function () { encoderSetup.refresh(); }, 0);
			});
		}
	};

	/* ============================================================
	   BROADCASTER EXTERNAL-ENCODER STATE â€” postMessage listener.

	       The embed iframe (loaded from player.wpstream.net when the user
	       clicks the External-encoder tab) emits live_update events via
	       window.postMessage. Listen for them and swap the encoder-state
	       title + subtitle so the user sees "Waiting for encoderâ€¦" become
	       "Live on air" the moment the player reports the stream is up.

	       Origin check is strict: only messages from the player origin are
	       trusted. The data-shape check rejects anything that isn't a
	       'player.event' / 'live_update' payload, so other postMessage
	       chatter on the page is ignored.
	       ============================================================ */
	function applyEncoderState(status) {
		/* Authoritative reconciliation â€” a STOPPED channel cannot be streaming,
		   so the label must never read a live-ish headline for it. These
		   live_update events come ONLY from the embedded player iframe, which
		   goes stale: after a channel switch the previous channel's player keeps
		   posting its last forward state ('onair'/'starting'/'paused'), and a
		   channel whose embed src is 'about:blank' freezes on its last value.
		   On a switch from a 'starting' channel A to a 'stopped' channel B,
		   channel A's iframe keeps posting 'starting', which would override the
		   correct 'stopped' headline onChannelChange just set for B â€” channel A
		   context bleeding onto B. So 'starting'/'paused' are NOT harmless
		   interim copy after a switch; they must be gated too.

		   baker's channel_status (for the CURRENTLY selected channel â€” the
		   dropdown handler refreshes wpstream_broadcaster_vars.channel_status on
		   every switch, so reading it live here is correct) is the source of
		   truth for "is there a live event":
			 - 'onair'   stays strict: allowed only when channel_status==='active'.
			 - 'starting'/'paused': allowed when channel_status is 'active' OR
			   'starting' (the event is genuinely coming up for THIS channel).
			 - otherwise downgrade to 'stopped'.
		   'init'/'stopped'/'ended' are left untouched (not live-ish / already
		   non-live). The browser-live lockout and a meter-confirmed external
		   stream set the on-air copy through setEncoderPanelOnAir(), which does
		   NOT go through this gate. */
		if (status === 'onair' || status === 'starting' || status === 'paused') {
			var bv = (typeof window.wpstream_broadcaster_vars === 'object' && window.wpstream_broadcaster_vars)
				? window.wpstream_broadcaster_vars : null;
			var chStatus = (bv && bv.channel_status) ? String(bv.channel_status) : '';
			// 'onair' demands a fully active event; 'starting'/'paused' also
			// accept a channel baker still reports as 'starting'.
			var allowed = (chStatus === 'active') ||
						  ((status === 'starting' || status === 'paused') && chStatus === 'starting');
			if (!allowed) {
				status = 'stopped';
			}
		}
		var copy = BC_ENCODER_STATE_COPY[status];
		if (!copy) return;
		writeEncoderState(status, copy);
	}

	/**
	 * Write one state and its copy into the encoder-state block. Every path that
	 * changes the block ends here.
	 *
	 * @param {string} state  Value for the data-wpstream-portal-bc-encoder-state attribute.
	 * @param {Object} copy   BC_ENCODER_STATE_COPY entry ({title, sub}).
	 * @return {void}
	 */
	function writeEncoderState(state, copy) {
		var wrap  = document.querySelector('[data-wpstream-portal-bc-encoder-state]');
		if (!wrap) return;
		wrap.setAttribute('data-wpstream-portal-bc-encoder-state', state);
		var titleEl = wrap.querySelector('[data-wpstream-portal-bc-encoder-title]');
		var subEl   = wrap.querySelector('[data-wpstream-portal-bc-encoder-subtitle]');
		if (titleEl) { titleEl.textContent = copy.title; }
		if (subEl)   { subEl.textContent   = copy.sub; }
	}

	/**
	 * Show the encoder-state block as on air or waiting, from a confirmed signal:
	 * the meter's onair sample, socket teardown, a channel switch or a tab click.
	 *
	 * The browser-live lockout always wins: while this browser publishes the viewed
	 * channel the block stays on air whatever the caller passes.
	 *
	 * @param {boolean} onAir  True when the signal confirms the channel is on air.
	 * @return {void}
	 */
	function setEncoderPanelOnAir(onAir) {
		if (onAir || browserBroadcastIsLive()) {
			writeEncoderState('onair', BC_ENCODER_STATE_COPY.onair);
		} else {
			writeEncoderState('waiting', BC_ENCODER_STATE_COPY.stopped);
		}
	}

	window.addEventListener('message', function (event) {
		if (event.origin !== 'https://player.wpstream.net') return;

		var data = event.data;
		if (!data || data.source !== 'player.iframe') return;
		if (data.type !== 'player.event') return;
		if (data.event !== 'live_update') return;

		var status = data.details && data.details.update && data.details.update.status;

		switch (status) {
			case 'stopped':
				log('[wpstream player.iframe] live_update â†’ stopped', data);
				applyEncoderState('stopped');
				break;
			case 'init':
				log('[wpstream player.iframe] live_update â†’ init', data);
				applyEncoderState('init');
				break;
			case 'starting':
				log('[wpstream player.iframe] live_update â†’ starting', data);
				applyEncoderState('starting');
				break;
			case 'onair':
				log('[wpstream player.iframe] live_update â†’ onair', data);
				applyEncoderState('onair');
				break;
			case 'paused':
				log('[wpstream player.iframe] live_update â†’ paused', data);
				applyEncoderState('paused');
				break;
			case 'ended':
				log('[wpstream player.iframe] live_update â†’ ended', data);
				applyEncoderState('ended');
				break;
		}
	});

	/* ============================================================
	   MODULE UI HANDLER (moduleUi)
	   ------------------------------------------------------------
	   The broadcaster markup uses the generic data-wpstream-portal-*
	   hooks for its modal (Full encoder setup), its embed-code tabs and
	   its copy buttons. Those hooks used to be served ONLY by the host's
	   Slice 0 click delegation in dashboard-2026.js. The module now ships
	   as one folder with no shared files, so it serves its own hooks:
	   ONE delegated click listener per .wpstream_portal_broadcaster root
	   (the encoder modal is rendered inside the root).

	   Contract (same as the host's Slice 0 helpers, so the markup needs
	   no change):
	     data-wpstream-portal-modal-open / -modal-close  display toggle +
	       aria-modal + role=dialog; Escape closes the open module modal.
	     data-wpstream-portal-tab-group + data-wpstream-portal-tab
	       wpstream_portal_tab_active + aria-selected on the buttons,
	       display toggle on [data-wpstream-portal-tab-panel] inside the
	       matching [data-wpstream-portal-tab-panel-group].
	     data-wpstream-portal-copy  value from -copy-value, clipboard write
	       (execCommand fallback), "check glyph + Copied" feedback with the
	       wpstream_portal_copy_btn_copied class for 2 s.

	   No double handling: every click handled here calls
	   e.preventDefault(); the host's document-level delegation returns
	   early on e.defaultPrevented, so a click is never handled twice.
	   Lookups are scoped to the root, so the module never touches host
	   markup.
	   ============================================================ */
	var moduleUi = {

		/** @private The module modal element currently open (or null). */
		_openModal: null,

		/**
		 * Open a module modal (same visual contract as the host modal.open).
		 * @param {Element} el  The [data-wpstream-portal-modal] element.
		 */
		openModal: function ( el ) {
			wpstreamBroadcasterDisplay(el, '');
			el.setAttribute( 'aria-modal', 'true' );
			el.setAttribute( 'role', 'dialog' );
			moduleUi._openModal = el;

			// Bring focus into the modal, like the host helper does.
			var firstFocusable = el.querySelector(
				'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
			);
			if ( firstFocusable ) {
				setTimeout( function () { firstFocusable.focus(); }, 50 );
			}
		},

		/**
		 * Close a module modal. Same fade-out as the host modal.close
		 * (instant when the OS asks for reduced motion).
		 * @param {Element} el  The [data-wpstream-portal-modal] element.
		 */
		closeModal: function ( el ) {
			el.removeAttribute( 'aria-modal' );
			if ( moduleUi._openModal === el ) { moduleUi._openModal = null; }

			// Reduced motion â†’ instant hide, no transition.
			var reduce = window.matchMedia &&
				window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			if ( reduce ) {
				wpstreamBroadcasterDisplay(el, 'none');
				el.classList.remove('wpstream-bc-fading');
				
				return;
			}

			// Fade the overlay out over --dur-2, then hide it.
			
			el.classList.add('wpstream-bc-fading');
			window.setTimeout( function () {
				wpstreamBroadcasterDisplay(el, 'none');
				el.classList.remove('wpstream-bc-fading');
				
			}, 170 ); // slightly over --dur-2 (0.16s) so the fade completes
		},

		/**
		 * Activate a tab inside one root (same class/panel contract as the
		 * host tabs.activate, scoped to the root).
		 * @param {Element} root     The broadcaster root.
		 * @param {string}  groupId  data-wpstream-portal-tab-group value.
		 * @param {string}  tabId    data-wpstream-portal-tab value.
		 */
		activateTab: function ( root, groupId, tabId ) {
			// Tab buttons: active class + aria-selected.
			root.querySelectorAll( '[data-wpstream-portal-tab-group="' + groupId + '"] [data-wpstream-portal-tab], [data-wpstream-portal-tab-group="' + groupId + '"][data-wpstream-portal-tab]' ).forEach( function ( btn ) {
				var isActive = btn.getAttribute( 'data-wpstream-portal-tab' ) === tabId;
				btn.classList.toggle( 'wpstream_portal_tab_active', isActive );
				btn.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );

			// Panels: show the matching one, hide the rest.
			var panelGroup = root.querySelector( '[data-wpstream-portal-tab-panel-group="' + groupId + '"]' );
			if ( panelGroup ) {
				panelGroup.querySelectorAll( '[data-wpstream-portal-tab-panel]' ).forEach( function ( panel ) {
					var isActive = panel.getAttribute( 'data-wpstream-portal-tab-panel' ) === tabId;
					wpstreamBroadcasterDisplay(panel, isActive ? '' : 'none');
				} );
			}
		},

		/**
		 * Copy text to the clipboard and show the "Copied" feedback on the
		 * button (same behaviour as the host copyToClipboard).
		 * @param {string}  text      Text to copy.
		 * @param {Element} buttonEl  The copy button.
		 */
		copy: function ( text, buttonEl ) {
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( function () {
					moduleUi._showCopied( buttonEl );
				} ).catch( function () {
					moduleUi._fallbackCopy( text, buttonEl );
				} );
			} else {
				moduleUi._fallbackCopy( text, buttonEl );
			}
		},

		/**
		 * Fallback clipboard write using document.execCommand.
		 * @private
		 */
		_fallbackCopy: function ( text, buttonEl ) {
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.classList.add('wpstream-bc-copy-buffer');
			document.body.appendChild( ta );
			ta.focus();
			ta.select();
			try { document.execCommand( 'copy' ); } catch ( e ) {}
			document.body.removeChild( ta );
			moduleUi._showCopied( buttonEl );
		},

		/**
		 * Morph the button into a check glyph + "Copied" with the success
		 * class, then revert after 2 s. The first snapshot of the button's
		 * markup is kept across rapid re-clicks so the label is never lost.
		 * @private
		 */
		_showCopied: function ( buttonEl ) {
			if ( ! buttonEl ) { return; }
			if ( buttonEl._wpstreamCopyTimer ) {
				clearTimeout( buttonEl._wpstreamCopyTimer );
			} else {
				buttonEl._wpstreamCopyOriginal = buttonEl.innerHTML;
			}
			// Static, trusted SVG string â€” no user input flows through innerHTML.
			buttonEl.innerHTML =
				'<svg width="12" height="12" viewBox="0 0 24 24" fill="none" ' +
				'stroke="currentColor" stroke-width="2.5" stroke-linecap="round" ' +
				'stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg> Copied';
			buttonEl.classList.add( 'wpstream_portal_copy_btn_copied' );
			buttonEl._wpstreamCopyTimer = setTimeout( function () {
				buttonEl.innerHTML = buttonEl._wpstreamCopyOriginal;
				buttonEl.classList.remove( 'wpstream_portal_copy_btn_copied' );
				buttonEl._wpstreamCopyTimer = null;
			}, 2000 );
		},

		/**
		 * Root click handler â€” walks up from the click target (stopping at the
		 * root) and serves the first module hook it finds.
		 * @param {Element} root  The broadcaster root the listener is bound to.
		 * @param {Event}   e     The click event.
		 */
		onRootClick: function ( root, e ) {
			var el = e.target;
			while ( el && el !== root.parentNode ) {
				if ( el.getAttribute ) {
					// Modal open â€” only modals rendered inside this root.
					var openId = el.getAttribute( 'data-wpstream-portal-modal-open' );
					if ( openId ) {
						var openEl = root.querySelector( '[data-wpstream-portal-modal="' + openId + '"]' );
						if ( openEl ) {
							e.preventDefault();
							moduleUi.openModal( openEl );
						}
						return;
					}

					// Modal close.
					var closeId = el.getAttribute( 'data-wpstream-portal-modal-close' );
					if ( closeId ) {
						var closeEl = root.querySelector( '[data-wpstream-portal-modal="' + closeId + '"]' );
						if ( closeEl ) {
							e.preventDefault();
							moduleUi.closeModal( closeEl );
						}
						return;
					}

					// Tab.
					var tabGroup = el.getAttribute( 'data-wpstream-portal-tab-group' );
					var tabId    = el.getAttribute( 'data-wpstream-portal-tab' );
					if ( tabGroup && tabId ) {
						e.preventDefault();
						moduleUi.activateTab( root, tabGroup, tabId );
						return;
					}

					// Copy to clipboard.
					var copyAttr = el.getAttribute( 'data-wpstream-portal-copy' );
					if ( copyAttr !== null ) {
						e.preventDefault();
						moduleUi.copy( el.getAttribute( 'data-wpstream-portal-copy-value' ) || copyAttr, el );
						return;
					}
				}
				if ( el === root ) { return; }
				el = el.parentElement;
			}
		},

		/**
		 * Bind one click listener per broadcaster root + one Escape handler.
		 * Idempotent per root (a JS property flag marks bound roots â€” no DOM
		 * attribute, so the rendered markup is unchanged).
		 */
		init: function () {
			document.querySelectorAll( '.wpstream_portal_broadcaster' ).forEach( function ( root ) {
				if ( root._wpstreamBcUiBound ) { return; }
				root._wpstreamBcUiBound = true;
				root.addEventListener( 'click', function ( e ) { moduleUi.onRootClick( root, e ); } );
			} );

			// Escape closes the open module modal.
			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' && moduleUi._openModal ) {
					moduleUi.closeModal( moduleUi._openModal );
				}
			} );
		}
	};

	/**
	 * getChannelStatus( channelId, domain )
	 *
	 * Public module API (window.wpstreamPortal.broadcaster.getChannelStatus):
	 * the channel's current status via the module's own check_event_status
	 * AJAX action. Used by host code that needs the status (the channel-detail
	 * settings-lock poll in dashboard-2026.js) without reaching into the module.
	 *
	 * @param  {string|number} channelId  Per-user channel id.
	 * @param  {string}        [domain]   Channel domain; when omitted the
	 *                                    module resolves the selected one.
	 * @return {Promise<object>}          ajaxPost() promise (parsed response).
	 */
	function getChannelStatus( channelId, domain ) {
		var params = { channel_id: channelId };
		if ( domain ) { params.domain = domain; }
		return ajaxPost( ACTIONS.check_event_status, params );
	}

	/**
	 * updateMeter( channelId, response )
	 *
	 * Public module API (window.wpstreamPortal.broadcaster.updateMeter): pass in
	 * a status read so a meter address that appears or changes after page load is
	 * used, and a failed meter connection is retried. A status for another channel
	 * or without an address changes nothing: only onChannelChange clears the meter.
	 *
	 * @param  {(number|string)} channelId  Channel the status belongs to.
	 * @param  {object}          response   Result of getChannelStatus().
	 * @return {void}
	 */
	function updateMeter( channelId, response ) {
		if ( String( channelId ) !== String( config.channel_id ) || ! response || ! response.qos_url_data ) {
			return;
		}
		// A changed address replaces the open socket; startQosStats() is idempotent.
		var urlChanged = config.qos_url_data !== response.qos_url_data;
		if ( urlChanged ) {
			stopQosStats();
			config.qos_url_data = response.qos_url_data;
		}
		startQosStats( ! urlChanged );
	}

	/**
	 * getStatistics()
	 *
	 * Public module API (window.wpstreamPortal.broadcaster.getStatistics): the text
	 * each live stat shows now, for a host that starts listening to the
	 * wpstream-portal-bc:statistics event after the module has booted.
	 *
	 * @return {Object} Values keyed by viewers, duration, bitrate and health.
	 */
	function getStatistics() {
		var values = {};
		[ 'viewers', 'duration', 'bitrate', 'health' ].forEach( function ( name ) {
			var cell = document.querySelector( '[data-wpstream-portal-bc-stat="' + name + '"]' );
			if ( cell ) values[ name ] = cell.textContent;
		} );
		return values;
	}

	/**
	 * stop()
	 *
	 * Public module API (window.wpstreamPortal.broadcaster.stop): stop the
	 * browser broadcast if one is live or starting. Idempotent â€” a no-op when
	 * nothing is running, so a host can call it unconditionally (the
	 * channel-detail Reset handler does).
	 *
	 * @return {void}
	 */
	function stop() {
		if ( bcTurningOn && ! streamingStarted ) {
			cancelBroadcastStartup();
		} else if ( streamingStarted || pendingReconnect || considerReconnect ) {
			// considerReconnect covers the reconnect gap after the pending timer
			// fired, when streamingStarted is false but a reconnect is still queued.
			stopStreaming();
		}
	}

	/* ============================================================
	   INIT â€” moved sub-modules + module UI handler. broadcaster.js runs
	   inside DOMContentLoaded, so the DOM is ready here.
	   ============================================================ */
	bcEncoderTabs.init();
	channelSelect.init();
	bcToggle.init();
	encoderSetup.init();
	moduleUi.init();
	// Bind only module roots; host screens use the same public channel-change seam.
	document.querySelectorAll('.wpstream_portal_broadcaster').forEach(function (root) {
		root.addEventListener('change', saveRecording);
	});
	renderRecording();


	// ==================================================================
	// Public API surface (window.wpstreamPortal.broadcaster).
	// ------------------------------------------------------------------
	// broadcaster.js owns the broadcast lifecycle state machine, the QoS
	// socket and ALL of the widget's UI behaviour (encoder tabs, channel
	// select, encoder-setup modal, encoder-state headline, module UI hooks).
	// Host pages talk to the module ONLY through this object:
	//   onChannelChange( channelId, response )  state + QoS rebind
	//   getChannelStatus( channelId, domain )   status via check_event_status
	//   updateMeter( channelId, response )      adopt or retry the QoS meter
	//   getStatistics()                         current live-stat text
	//   stop()                                  stop a live / starting broadcast
	//   applyEncoderState( status )             encoder-state headline
	//   encoderTabs / channelSelect / bcToggle / encoderSetup  sub-modules
	//
	// MERGE (not overwrite): the host script creates window.wpstreamPortal
	// with its own members, so we create the namespace if absent and only
	// ADD our members â€” never clobber it.
	// ==================================================================
	window.wpstreamPortal = window.wpstreamPortal || {};
	window.wpstreamPortal.broadcaster = window.wpstreamPortal.broadcaster || {};
	window.wpstreamPortal.broadcaster.onChannelChange   = onChannelChange;
	window.wpstreamPortal.broadcaster.getChannelStatus  = getChannelStatus;
	window.wpstreamPortal.broadcaster.updateMeter       = updateMeter;
	window.wpstreamPortal.broadcaster.getStatistics     = getStatistics;
	window.wpstreamPortal.broadcaster.stop              = stop;
	window.wpstreamPortal.broadcaster.applyEncoderState = applyEncoderState;
	window.wpstreamPortal.broadcaster.encoderTabs       = bcEncoderTabs;
	window.wpstreamPortal.broadcaster.channelSelect     = channelSelect;
	window.wpstreamPortal.broadcaster.bcToggle          = bcToggle;
	window.wpstreamPortal.broadcaster.encoderSetup      = encoderSetup;
});
