/* Video Lead Tracker — Video Event Tracker (Phase 11) */
/* global vltConfig, vltState, vltApiFetch */

( function () {
	'use strict';

	var cfg              = window.vltConfig || {};
	var VIDEO_KEY        = cfg.videoKey     || '';
	var heartbeatSecs    = ( cfg.settings && cfg.settings.heartbeatInterval ) || 10;

	var videoEl          = null;
	var isSeeking        = false;
	var heartbeatTimer   = null;

	// -------------------------------------------------------------------------
	// Boot — triggered by vlt-frontend.js after video becomes visible
	// -------------------------------------------------------------------------

	document.addEventListener( 'vlt:videoReady', function () {
		var wrapper = document.querySelector( '.vlt-wrapper' );
		if ( ! wrapper ) return;

		videoEl = wrapper.querySelector( 'video.vlt-video' );
		if ( ! videoEl ) return;

		attachListeners();
	} );

	// -------------------------------------------------------------------------
	// HTML5 event listeners
	// -------------------------------------------------------------------------

	function attachListeners() {
		videoEl.addEventListener( 'loadedmetadata', onLoadedMetadata );
		videoEl.addEventListener( 'play',           onPlay );
		videoEl.addEventListener( 'pause',          onPause );
		videoEl.addEventListener( 'seeking',        onSeeking );
		videoEl.addEventListener( 'seeked',         onSeeked );
		videoEl.addEventListener( 'ended',          onEnded );
		videoEl.addEventListener( 'ratechange',     onRateChange );
		videoEl.addEventListener( 'error',          onError );
		// timeupdate is intentionally not forwarded — heartbeat covers progress.
	}

	function onLoadedMetadata() {
		sendEvent( 'video_loaded', {
			duration_seconds: videoEl.duration || null,
		} );
	}

	function onPlay() {
		sendEvent( 'play' );
		startHeartbeat();
	}

	function onPause() {
		stopHeartbeat();
		// Suppress the spurious pause that browsers fire while seeking.
		if ( ! isSeeking && ! videoEl.ended ) {
			sendEvent( 'pause' );
		}
	}

	function onSeeking() {
		isSeeking = true;
		sendEvent( 'seek_start' );
	}

	function onSeeked() {
		isSeeking = false;
		sendEvent( 'seek_end' );
	}

	function onEnded() {
		stopHeartbeat();
		sendEvent( 'ended' );
	}

	function onRateChange() {
		sendEvent( 'rate_change' );
	}

	function onError() {
		var code = videoEl.error ? videoEl.error.code : null;
		sendEvent( 'error', { error_code: code } );
	}

	// -------------------------------------------------------------------------
	// Heartbeat (fires while playing, replaces noisy timeupdate)
	// -------------------------------------------------------------------------

	function startHeartbeat() {
		stopHeartbeat();
		heartbeatTimer = setInterval( function () {
			if ( videoEl && ! videoEl.paused && ! videoEl.ended ) {
				sendEvent( 'heartbeat' );
			}
		}, heartbeatSecs * 1000 );
	}

	function stopHeartbeat() {
		if ( heartbeatTimer ) {
			clearInterval( heartbeatTimer );
			heartbeatTimer = null;
		}
	}

	// -------------------------------------------------------------------------
	// Dispatch helper
	// -------------------------------------------------------------------------

	function sendEvent( eventType, extra ) {
		if ( ! window.vltState || ! window.vltState.sessionUuid ) return;
		if ( ! VIDEO_KEY ) return;

		var body = {
			visitor_uuid:       window.vltState.visitorUuid,
			session_uuid:       window.vltState.sessionUuid,
			lead_id:            window.vltState.leadId || null,
			video_key:          VIDEO_KEY,
			event_type:         eventType,
			video_time_seconds: videoEl ? videoEl.currentTime        : null,
			playback_rate:      videoEl ? videoEl.playbackRate        : null,
			duration_seconds:   ( videoEl && videoEl.duration ) ? videoEl.duration : null,
		};

		if ( extra ) {
			Object.assign( body, extra );
		}

		if ( window.vltApiFetch ) {
			window.vltApiFetch( 'track/video-event', body ).catch( function () {} );
		}
	}

} )();
