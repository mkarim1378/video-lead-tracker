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
		visitorUuid:   null,
		sessionUuid:   null,
		leadId:        null,
		identityToken: null,
	};
	window.vltState = vltState;

	// DOM refs
	var wrapper, formContainer, videoContainer, loadingEl;
	var formEl, nameInput, mobileInput, submitBtn, errorEl;

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		wrapper = document.querySelector( '.vlt-wrapper' );
		if ( ! wrapper ) return;

		formContainer  = wrapper.querySelector( '.vlt-form-container' );
		videoContainer = wrapper.querySelector( '.vlt-video-container' );
		loadingEl      = wrapper.querySelector( '.vlt-loading' );
		formEl         = wrapper.querySelector( '.vlt-lead-form' );
		nameInput      = wrapper.querySelector( '#vlt-name' );
		mobileInput    = wrapper.querySelector( '#vlt-mobile' );
		submitBtn      = wrapper.querySelector( '.vlt-submit-btn' );
		errorEl        = wrapper.querySelector( '.vlt-form-error' );

		loadIdentity();
		showLoading();
		initSession();

		if ( formEl ) {
			formEl.addEventListener( 'submit', handleFormSubmit );
		}
	} );

	// -------------------------------------------------------------------------
	// Identity — localStorage with cookie fallback
	// -------------------------------------------------------------------------

	function loadIdentity() {
		var data = null;

		try {
			var raw = localStorage.getItem( STORAGE_KEY );
			if ( raw ) data = JSON.parse( raw );
		} catch ( e ) {}

		if ( ! data || ! data.visitor_uuid ) {
			try {
				var cookie = getCookie( COOKIE_KEY );
				if ( cookie ) data = JSON.parse( decodeURIComponent( cookie ) );
			} catch ( e ) {}
		}

		if ( data && data.visitor_uuid ) {
			vltState.visitorUuid   = data.visitor_uuid;
			vltState.leadId        = data.lead_id        || null;
			vltState.identityToken = data.identity_token || null;
		}
	}

	function saveIdentity( data ) {
		var payload = {
			visitor_uuid:   data.visitor_uuid   || vltState.visitorUuid,
			lead_id:        data.lead_id        || null,
			identity_token: data.identity_token || null,
			last_seen_at:   new Date().toISOString(),
		};

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

				if ( res.known_lead && ! res.show_form ) {
					showVideo();
				} else {
					showForm();
				}
			} )
			.catch( function () {
				// If the API is not ready yet (Phases 6-8 pending), fall through to form.
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
				vltState.leadId        = res.lead_id;
				vltState.identityToken = res.identity_token;

				saveIdentity( {
					visitor_uuid:   vltState.visitorUuid,
					lead_id:        res.lead_id,
					identity_token: res.identity_token,
				} );

				showVideo();
			} )
			.catch( function ( err ) {
				showError( ( err && err.message ) || i18n.submitError || 'Submission failed.' );
				setSubmitting( false );
			} );
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
