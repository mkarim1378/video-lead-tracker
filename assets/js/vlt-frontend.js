/* Video Lead Tracker — Frontend */
/* global vltConfig */

( function () {
	'use strict';

	var cfg      = window.vltConfig || {};
	var REST     = cfg.restBase    || '';
	var NONCE    = cfg.nonce       || '';
	var VIDEO_KEY = cfg.videoKey   || '';
	var i18n     = cfg.i18n        || {};

	var STORAGE_KEY = 'vlt_identity';
	var COOKIE_KEY  = 'vlt_id';

	// Live state object — mutated in place so external modules share the reference.
	var vltState = {
		visitorUuid:      null,
		sessionUuid:      null,
		leadId:           null,
		identityToken:    null,
		mobileHash:       null,
		normalizedMobile: null,
	};
	window.vltState = vltState;

	// DOM refs
	var wrapper, formContainer, videoContainer, loadingEl;
	var formEl, nameInput, mobileInput, submitBtn, errorEl;
	var otpContainer, otpForm, otpInput, otpSubmitBtn, otpErrorEl, resendBtn, resendCountdownEl;

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		wrapper = document.querySelector( '.vlt-wrapper' );
		if ( ! wrapper ) return;

		formContainer  = wrapper.querySelector( '.vlt-form-container' );
		videoContainer = wrapper.querySelector( '.vlt-video-container' );
		loadingEl      = wrapper.querySelector( '.vlt-loading' );
		formEl             = wrapper.querySelector( '.vlt-lead-form' );
		nameInput          = wrapper.querySelector( '#vlt-name' );
		mobileInput        = wrapper.querySelector( '#vlt-mobile' );
		submitBtn          = wrapper.querySelector( '.vlt-lead-form .vlt-submit-btn' );
		errorEl            = wrapper.querySelector( '.vlt-lead-form .vlt-form-error' );
		otpContainer       = wrapper.querySelector( '.vlt-otp-container' );
		otpForm            = wrapper.querySelector( '.vlt-otp-form' );
		otpInput           = wrapper.querySelector( '#vlt-otp-code' );
		otpSubmitBtn       = wrapper.querySelector( '.vlt-otp-form .vlt-submit-btn' );
		otpErrorEl         = wrapper.querySelector( '.vlt-otp-form .vlt-form-error' );
		resendBtn          = wrapper.querySelector( '.vlt-resend-btn' );
		resendCountdownEl  = wrapper.querySelector( '.vlt-resend-countdown' );

		loadIdentity();
		showLoading();
		initSession();

		if ( formEl ) {
			formEl.addEventListener( 'submit', handleFormSubmit );
		}
		if ( otpForm ) {
			otpForm.addEventListener( 'submit', handleOtpSubmit );
		}
		if ( resendBtn ) {
			resendBtn.addEventListener( 'click', handleOtpResend );
		}
	} );

	// -------------------------------------------------------------------------
	// Identity — localStorage with cookie fallback
	// -------------------------------------------------------------------------

	function loadIdentity() {
		var lsData     = null;
		var cookieData = null;

		try {
			var raw = localStorage.getItem( STORAGE_KEY );
			if ( raw ) lsData = JSON.parse( raw );
		} catch ( e ) {}

		try {
			var cookie = getCookie( COOKIE_KEY );
			if ( cookie ) cookieData = JSON.parse( decodeURIComponent( cookie ) );
		} catch ( e ) {}

		// Cross-restore: localStorage exists but cookie is gone → rebuild cookie.
		if ( lsData && lsData.visitor_uuid && ! cookieData ) {
			setCookie( COOKIE_KEY, encodeURIComponent( JSON.stringify( lsData ) ), 365 );
		}

		// Cross-restore: cookie exists but localStorage is gone → rebuild localStorage.
		if ( cookieData && cookieData.visitor_uuid && ! lsData ) {
			try { localStorage.setItem( STORAGE_KEY, JSON.stringify( cookieData ) ); } catch ( e ) {}
			lsData = cookieData;
		}

		var data = lsData || cookieData;
		if ( data && data.visitor_uuid ) {
			vltState.visitorUuid   = data.visitor_uuid;
			vltState.leadId        = data.lead_id        || null;
			vltState.identityToken = data.identity_token || null;
			vltState.mobileHash    = data.mobile_hash    || null;
		}
	}

	function saveIdentity( data ) {
		// Read existing payload so created_at and mobile_hash are preserved across saves.
		var existing = {};
		try {
			var raw = localStorage.getItem( STORAGE_KEY );
			if ( raw ) existing = JSON.parse( raw );
		} catch ( e ) {}

		var payload = {
			visitor_uuid:   data.visitor_uuid   || vltState.visitorUuid,
			lead_id:        data.lead_id        || null,
			mobile_hash:    data.mobile_hash    || existing.mobile_hash || null,
			identity_token: data.identity_token || null,
			created_at:     existing.created_at || new Date().toISOString(),
			last_seen_at:   new Date().toISOString(),
		};

		// Sync mobileHash into live state if provided.
		if ( payload.mobile_hash ) {
			vltState.mobileHash = payload.mobile_hash;
		}

		try { localStorage.setItem( STORAGE_KEY, JSON.stringify( payload ) ); } catch ( e ) {}
		setCookie( COOKIE_KEY, encodeURIComponent( JSON.stringify( payload ) ), 365 );
	}
	window.vltSaveIdentity = saveIdentity;

	function clearIdentity() {
		try { localStorage.removeItem( STORAGE_KEY ); } catch ( e ) {}
		setCookie( COOKIE_KEY, '', -1 );
		vltState.visitorUuid   = null;
		vltState.leadId        = null;
		vltState.identityToken = null;
		vltState.mobileHash    = null;
	}

	// -------------------------------------------------------------------------
	// Session init
	// -------------------------------------------------------------------------

	function initSession() {
		var body = {
			page_url: window.location.href,
			referrer: document.referrer || '',
			utm:      getUtmParams(),
		};
		if ( vltState.visitorUuid   ) body.visitor_uuid   = vltState.visitorUuid;
		if ( vltState.identityToken ) body.identity_token = vltState.identityToken;

		apiFetch( 'session/init', body )
			.then( function ( res ) {
				vltState.visitorUuid   = res.visitor_uuid;
				vltState.sessionUuid   = res.session_uuid;
				vltState.leadId        = res.lead_id        || null;
				vltState.identityToken = res.identity_token || null;

				if ( res.identity_invalid ) {
					clearIdentity();
				}

				if ( res.identity_token ) {
					saveIdentity( {
						visitor_uuid:   res.visitor_uuid,
						lead_id:        res.lead_id,
						identity_token: res.identity_token,
					} );
				}

				startPageTracking();

				if ( res.known_lead && ! res.show_form ) {
					showVideo();
				} else {
					showForm();
				}
			} )
			.catch( function () {
				showForm();
			} );
	}

	// -------------------------------------------------------------------------
	// Form submit
	// -------------------------------------------------------------------------

	function handleFormSubmit( e ) {
		e.preventDefault();
		hideError();
		setSubmitting( true );

		var name   = nameInput   ? nameInput.value.trim()   : '';
		var mobile = mobileInput ? mobileInput.value.trim() : '';

		if ( ! name || ! mobile ) {
			showError( i18n.fillAllFields || 'Please fill in all fields.' );
			setSubmitting( false );
			return;
		}

		apiFetch( 'lead/submit', {
			visitor_uuid: vltState.visitorUuid,
			session_uuid: vltState.sessionUuid,
			name:         name,
			mobile:       mobile,
		} )
			.then( function ( res ) {
				vltState.leadId           = res.lead_id;
				vltState.identityToken    = res.identity_token;
				vltState.normalizedMobile = res.normalized_mobile || null;

				saveIdentity( {
					visitor_uuid:   vltState.visitorUuid,
					lead_id:        res.lead_id,
					identity_token: res.identity_token,
					mobile_hash:    res.mobile_hash || null,
				} );

				if ( cfg.otp && cfg.otp.enabled ) {
					showOtpStep();
				} else {
					showVideo();
				}
			} )
			.catch( function ( err ) {
				showError( ( err && err.message ) || i18n.submitError || 'Submission failed.' );
				setSubmitting( false );
			} );
	}

	// -------------------------------------------------------------------------
	// OTP step
	// -------------------------------------------------------------------------

	function showOtpStep() {
		var hint = otpContainer && otpContainer.querySelector( '.vlt-otp-hint' );
		if ( hint ) {
			hint.textContent = ( i18n.otpSentTo || 'A verification code was sent to' )
				+ ' ' + maskMobile( vltState.normalizedMobile );
		}

		if ( loadingEl      ) loadingEl.style.display      = 'none';
		if ( formContainer  ) formContainer.style.display  = 'none';
		if ( videoContainer ) videoContainer.style.display = 'none';
		setAriaHidden( formContainer,  true );
		setAriaHidden( videoContainer, true );

		if ( otpContainer ) {
			otpContainer.style.display = '';
			setAriaHidden( otpContainer, false );
		}

		sendOtp();
		if ( otpInput ) otpInput.focus();
	}

	function sendOtp() {
		apiFetch( 'otp/send', {
			normalized_mobile: vltState.normalizedMobile,
			visitor_uuid:      vltState.visitorUuid,
			session_uuid:      vltState.sessionUuid,
		} )
			.then( function () {
				startResendCountdown( ( cfg.otp && cfg.otp.cooldown ) || 60 );
			} )
			.catch( function ( err ) {
				showOtpError( ( err && err.message ) || i18n.otpSendError || 'Failed to send code.' );
			} );
	}

	function handleOtpSubmit( e ) {
		e.preventDefault();
		hideOtpError();
		if ( otpSubmitBtn ) otpSubmitBtn.disabled = true;

		var code = otpInput ? otpInput.value.trim() : '';
		if ( ! code ) {
			showOtpError( i18n.enterCode || 'Please enter the verification code.' );
			if ( otpSubmitBtn ) otpSubmitBtn.disabled = false;
			return;
		}

		apiFetch( 'otp/verify', {
			normalized_mobile: vltState.normalizedMobile,
			code:              code,
			visitor_uuid:      vltState.visitorUuid,
		} )
			.then( function () {
				if ( otpContainer ) {
					otpContainer.style.display = 'none';
					setAriaHidden( otpContainer, true );
				}
				showVideo();
			} )
			.catch( function ( err ) {
				showOtpError( ( err && err.message ) || i18n.otpInvalid || 'Invalid code. Please try again.' );
				if ( otpSubmitBtn ) otpSubmitBtn.disabled = false;
			} );
	}

	function handleOtpResend() {
		if ( resendBtn ) resendBtn.disabled = true;
		hideOtpError();
		sendOtp();
	}

	function startResendCountdown( seconds ) {
		if ( resendBtn        ) resendBtn.disabled        = true;
		if ( resendCountdownEl ) resendCountdownEl.textContent = '(' + seconds + 's)';

		var remaining = seconds;
		var timer = setInterval( function () {
			remaining -= 1;
			if ( remaining <= 0 ) {
				clearInterval( timer );
				if ( resendBtn         ) resendBtn.disabled         = false;
				if ( resendCountdownEl ) resendCountdownEl.textContent = '';
			} else {
				if ( resendCountdownEl ) resendCountdownEl.textContent = '(' + remaining + 's)';
			}
		}, 1000 );
	}

	function maskMobile( mobile ) {
		if ( ! mobile || mobile.length < 8 ) return mobile || '';
		return mobile.slice( 0, 4 ) + '****' + mobile.slice( -4 );
	}

	function showOtpError( msg ) {
		if ( otpErrorEl ) {
			otpErrorEl.textContent  = msg;
			otpErrorEl.style.display = '';
		}
	}

	function hideOtpError() {
		if ( otpErrorEl ) otpErrorEl.style.display = 'none';
	}

	// -------------------------------------------------------------------------
	// Page tracking — Phase 10
	// -------------------------------------------------------------------------

	var pageTrackingStarted = false;
	var visibleSeconds      = 0;     // cumulative visible time (seconds)
	var visibleSince        = null;  // Date.now() when page last became visible
	var heartbeatTimer      = null;

	function startPageTracking() {
		if ( pageTrackingStarted || ! vltState.sessionUuid ) return;
		pageTrackingStarted = true;

		visibleSince = document.hidden ? null : Date.now();

		sendPageEvent( 'page_view', {
			referrer: document.referrer || null,
			utm:      getUtmParams(),
		} );

		document.addEventListener( 'visibilitychange', onVisibilityChange );

		var interval = ( cfg.settings && cfg.settings.heartbeatInterval ) || 10;
		heartbeatTimer = setInterval( function () {
			if ( ! document.hidden ) {
				sendPageEvent( 'heartbeat' );
			}
		}, interval * 1000 );

		// pagehide fires reliably on mobile; fall back to beforeunload on desktop.
		window.addEventListener( 'pagehide', onPageUnload );
		if ( ! ( 'onpagehide' in window ) ) {
			window.addEventListener( 'beforeunload', onPageUnload );
		}
	}

	function onVisibilityChange() {
		if ( document.hidden ) {
			if ( visibleSince !== null ) {
				visibleSeconds += ( Date.now() - visibleSince ) / 1000;
				visibleSince = null;
			}
			sendPageEvent( 'page_hidden' );
		} else {
			visibleSince = Date.now();
			sendPageEvent( 'page_visible' );
		}
	}

	function onPageUnload() {
		if ( visibleSince !== null ) {
			visibleSeconds += ( Date.now() - visibleSince ) / 1000;
			visibleSince = null;
		}
		sendPageEventBeacon( 'page_unload' );
	}

	function getVisibleSeconds() {
		var extra = ( visibleSince !== null ) ? ( Date.now() - visibleSince ) / 1000 : 0;
		return Math.round( visibleSeconds + extra );
	}

	function buildPageEventBody( eventType, metadata ) {
		var body = {
			visitor_uuid:         vltState.visitorUuid,
			session_uuid:         vltState.sessionUuid,
			lead_id:              vltState.leadId || null,
			page_id:              cfg.pageId || null,
			page_url:             window.location.href,
			event_type:           eventType,
			time_on_page_seconds: getVisibleSeconds(),
		};
		if ( metadata ) body.metadata = metadata;
		return body;
	}

	function sendPageEvent( eventType, metadata ) {
		if ( ! vltState.sessionUuid ) return;
		apiFetch( 'track/page', buildPageEventBody( eventType, metadata ) ).catch( function () {} );
	}

	function sendPageEventBeacon( eventType ) {
		if ( ! vltState.sessionUuid ) return;

		var payload = JSON.stringify( buildPageEventBody( eventType ) );
		var url     = REST + 'track/page';
		var sent    = false;

		if ( navigator.sendBeacon ) {
			sent = navigator.sendBeacon( url, new Blob( [ payload ], { type: 'application/json' } ) );
		}

		if ( ! sent ) {
			fetch( url, {
				method:    'POST',
				headers:   { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
				body:      payload,
				keepalive: true,
			} ).catch( function () {} );
		}
	}

	// -------------------------------------------------------------------------
	// UI helpers
	// -------------------------------------------------------------------------

	function showLoading() {
		setAriaHidden( formContainer,  true );
		setAriaHidden( videoContainer, true );
		if ( loadingEl ) loadingEl.style.display = '';
		if ( formContainer  ) formContainer.style.display  = 'none';
		if ( videoContainer ) videoContainer.style.display = 'none';
	}

	function showForm() {
		if ( loadingEl      ) loadingEl.style.display      = 'none';
		if ( videoContainer ) videoContainer.style.display = 'none';
		setAriaHidden( videoContainer, true );
		setAriaHidden( formContainer, false );
		if ( formContainer ) formContainer.style.display = '';
		if ( nameInput ) nameInput.focus();
	}

	function showVideo() {
		if ( loadingEl     ) loadingEl.style.display     = 'none';
		if ( formContainer ) formContainer.style.display = 'none';
		setAriaHidden( formContainer,  true );
		setAriaHidden( videoContainer, false );
		if ( videoContainer ) videoContainer.style.display = '';

		// Signal other modules (video tracker — Phase 11) that the video is ready.
		document.dispatchEvent( new CustomEvent( 'vlt:videoReady', {
			detail: { state: vltState, videoKey: VIDEO_KEY },
		} ) );
	}

	function showError( msg ) {
		if ( errorEl ) {
			errorEl.textContent = msg;
			errorEl.style.display = '';
		}
	}

	function hideError() {
		if ( errorEl ) errorEl.style.display = 'none';
	}

	function setSubmitting( submitting ) {
		if ( ! submitBtn ) return;
		submitBtn.disabled    = submitting;
		submitBtn.textContent = submitting
			? ( i18n.submitting || 'Please wait…' )
			: ( cfg.submitText  || 'Watch Now' );
	}

	function setAriaHidden( el, hidden ) {
		if ( el ) el.setAttribute( 'aria-hidden', hidden ? 'true' : 'false' );
	}

	// -------------------------------------------------------------------------
	// API helper
	// -------------------------------------------------------------------------

	function apiFetch( endpoint, body ) {
		return fetch( REST + endpoint, {
			method:  'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   NONCE,
			},
			body: JSON.stringify( body ),
		} ).then( function ( res ) {
			return res.json().then( function ( data ) {
				if ( ! res.ok || ! data.success ) {
					var err = new Error( data.message || 'API error' );
					err.code = data.code || 'api_error';
					throw err;
				}
				return data;
			} );
		} );
	}
	window.vltApiFetch = apiFetch;

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------

	function getUtmParams() {
		var params = new URLSearchParams( window.location.search );
		var keys   = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ];
		var result = {};
		keys.forEach( function ( k ) {
			if ( params.has( k ) ) result[ k ] = params.get( k );
		} );
		return result;
	}

	function getCookie( name ) {
		var match = document.cookie.match(
			new RegExp( '(?:^|; )' + name.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + '=([^;]*)' )
		);
		return match ? decodeURIComponent( match[ 1 ] ) : null;
	}

	function setCookie( name, value, days ) {
		var expires = new Date( Date.now() + days * 864e5 ).toUTCString();
		document.cookie = name + '=' + value + '; expires=' + expires + '; path=/; SameSite=Lax';
	}

} )();
