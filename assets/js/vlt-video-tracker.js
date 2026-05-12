/* Video Lead Tracker — Video Event + Range Tracker (Phase 11 + 12) */
/* global vltConfig, vltState, vltApiFetch */

( function () {
	'use strict';

	var cfg           = window.vltConfig || {};
	var VIDEO_KEY     = cfg.videoKey    || '';
	var heartbeatSecs = ( cfg.settings && cfg.settings.heartbeatInterval ) || 10;
	var minValidRange = ( cfg.settings && cfg.settings.minValidRange )     || 1;

	// ---- Phase 11: event tracking state ----
	var videoEl            = null;
	var isSeeking          = false;
	var heartbeatTimer     = null;

	// ---- Phase 12: range tracking state ----
	var isPlaying          = false;
	var rangeStart         = null;   // video time (seconds) when current range began
	var wasPlayingOnSeek   = false;  // whether we were playing when seeking started

	// -------------------------------------------------------------------------
	// Boot — triggered by vlt-frontend.js after video becomes visible
	// -------------------------------------------------------------------------

	document.addEventListener( 'vlt:videoReady', function () {
		var wrapper = document.querySelector( '.vlt-wrapper' );
		if ( ! wrapper ) return;

		videoEl = wrapper.querySelector( 'video.vlt-video' );
		if ( ! videoEl ) return;

		attachVideoListeners();
		attachUnloadListeners();
	} );

	// -------------------------------------------------------------------------
	// HTML5 video event listeners (Phase 11)
	// -------------------------------------------------------------------------

	function attachVideoListeners() {
		videoEl.addEventListener( 'loadedmetadata', onLoadedMetadata );
		videoEl.addEventListener( 'play',           onPlay );
		videoEl.addEventListener( 'pause',          onPause );
		videoEl.addEventListener( 'seeking',        onSeeking );
		videoEl.addEventListener( 'seeked',         onSeeked );
		videoEl.addEventListener( 'ended',          onEnded );
		videoEl.addEventListener( 'ratechange',     onRateChange );
		videoEl.addEventListener( 'error',          onError );
		// timeupdate intentionally not forwarded — heartbeat covers progress.
	}

	function onLoadedMetadata() {
		sendEvent( 'video_loaded', { duration_seconds: videoEl.duration || null } );
	}

	function onPlay() {
		isPlaying = true;
		startRange();
		sendEvent( 'play' );
		startHeartbeat();
	}

	function onPause() {
		stopHeartbeat();
		if ( isSeeking ) return; // suppress spurious pause fired during seek

		isPlaying = false;
		if ( ! videoEl.ended ) {
			commitRange( 'pause' );
			sendEvent( 'pause' );
		}
	}

	function onSeeking() {
		isSeeking = true;
		if ( isPlaying ) {
			commitRange( 'seek' );
			wasPlayingOnSeek = true;
		}
		sendEvent( 'seek_start' );
	}

	function onSeeked() {
		isSeeking = false;
		sendEvent( 'seek_end' );
		// If video was playing before the seek, restart the range at new position.
		if ( wasPlayingOnSeek && ! videoEl.paused ) {
			startRange();
		}
		wasPlayingOnSeek = false;
	}

	function onEnded() {
		stopHeartbeat();
		isPlaying = false;
		commitRange( 'ended' );
		sendEvent( 'ended' );
	}

	function onRateChange() {
		sendEvent( 'rate_change' );
	}

	function onError() {
		isPlaying  = false;
		rangeStart = null;
		sendEvent( 'error', { error_code: videoEl.error ? videoEl.error.code : null } );
	}

	// -------------------------------------------------------------------------
	// Heartbeat — video progress + range checkpoint (Phases 10/12)
	// -------------------------------------------------------------------------

	function startHeartbeat() {
		stopHeartbeat();
		heartbeatTimer = setInterval( function () {
			if ( ! videoEl || videoEl.paused || videoEl.ended ) return;
			sendEvent( 'heartbeat' );
			commitRange( 'heartbeat' ); // save current segment and restart
			startRange();
		}, heartbeatSecs * 1000 );
	}

	function stopHeartbeat() {
		if ( heartbeatTimer ) {
			clearInterval( heartbeatTimer );
			heartbeatTimer = null;
		}
	}

	// -------------------------------------------------------------------------
	// Unload listeners — commit on page hide / close (Phase 12)
	// -------------------------------------------------------------------------

	function attachUnloadListeners() {
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden && isPlaying ) {
				commitRangeBeacon( 'page_hidden' );
			}
		} );

		window.addEventListener( 'pagehide', function () {
			if ( isPlaying ) commitRangeBeacon( 'unload' );
		} );

		if ( ! ( 'onpagehide' in window ) ) {
			window.addEventListener( 'beforeunload', function () {
				if ( isPlaying ) commitRangeBeacon( 'unload' );
			} );
		}
	}

	// -------------------------------------------------------------------------
	// Range tracking (Phase 12)
	// -------------------------------------------------------------------------

	function startRange() {
		rangeStart = videoEl ? videoEl.currentTime : null;
	}

	/**
	 * Commit the active range via regular fetch (used for all mid-session commits).
	 */
	function commitRange( reason ) {
		if ( rangeStart === null || ! videoEl ) return;
		if ( ! window.vltState || ! window.vltState.sessionUuid ) return;

		var toSecond = videoEl.currentTime;
		var duration = toSecond - rangeStart;

		if ( toSecond <= rangeStart || duration < minValidRange ) {
			rangeStart = null;
			return;
		}

		var body = buildRangeBody( rangeStart, toSecond, reason );
		rangeStart = null;

		if ( window.vltApiFetch ) {
			window.vltApiFetch( 'track/video-range', body ).catch( function () {} );
		}
	}

	/**
	 * Commit the active range via sendBeacon (used on page hide / unload).
	 */
	function commitRangeBeacon( reason ) {
		if ( rangeStart === null || ! videoEl ) return;
		if ( ! window.vltState || ! window.vltState.sessionUuid ) return;

		var toSecond = videoEl.currentTime;
		var duration = toSecond - rangeStart;

		if ( toSecond <= rangeStart || duration < minValidRange ) {
			rangeStart = null;
			return;
		}

		var payload = JSON.stringify( buildRangeBody( rangeStart, toSecond, reason ) );
		rangeStart  = null;

		var url  = ( cfg.restBase || '' ) + 'track/video-range';
		var sent = false;

		if ( navigator.sendBeacon ) {
			sent = navigator.sendBeacon( url, new Blob( [ payload ], { type: 'application/json' } ) );
		}

		if ( ! sent ) {
			fetch( url, {
				method:    'POST',
				headers:   { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
				body:      payload,
				keepalive: true,
			} ).catch( function () {} );
		}
	}

	function buildRangeBody( fromSecond, toSecond, reason ) {
		return {
			visitor_uuid:     window.vltState.visitorUuid,
			session_uuid:     window.vltState.sessionUuid,
			lead_id:          window.vltState.leadId || null,
			video_key:        VIDEO_KEY,
			from_second:      fromSecond,
			to_second:        toSecond,
			duration_seconds: toSecond - fromSecond,
			playback_rate:    videoEl ? videoEl.playbackRate : null,
			committed_reason: reason,
		};
	}

	// -------------------------------------------------------------------------
	// Video event dispatch (Phase 11)
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
			video_time_seconds: videoEl ? videoEl.currentTime  : null,
			playback_rate:      videoEl ? videoEl.playbackRate  : null,
			duration_seconds:   ( videoEl && videoEl.duration ) ? videoEl.duration : null,
		};

		if ( extra ) Object.assign( body, extra );

		if ( window.vltApiFetch ) {
			window.vltApiFetch( 'track/video-event', body ).catch( function () {} );
		}
	}

} )();
