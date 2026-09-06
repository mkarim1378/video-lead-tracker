/* Video Lead Tracker — Admin UI primitives (toast, modal, video filter) */
( function ( window, document ) {
	'use strict';

	var confirmResolver = null;

	function toastHost() {
		return document.getElementById( 'vlt-toast-host' );
	}

	function showToast( message, type ) {
		var host = toastHost();
		if ( ! host || ! message ) return;
		type = type || 'info';
		var el = document.createElement( 'div' );
		el.className = 'vlt-toast vlt-toast--' + type;
		el.setAttribute( 'role', 'status' );
		el.textContent = message;
		host.appendChild( el );
		requestAnimationFrame( function () { el.classList.add( 'is-visible' ); } );
		setTimeout( function () {
			el.classList.remove( 'is-visible' );
			setTimeout( function () { el.remove(); }, 250 );
		}, 3200 );
	}

	function getModal() {
		return document.getElementById( 'vlt-confirm-modal' );
	}

	function closeModal() {
		var modal = getModal();
		if ( ! modal ) return;
		modal.hidden = true;
		document.body.classList.remove( 'vlt-modal-open' );
		if ( confirmResolver ) {
			confirmResolver( false );
			confirmResolver = null;
		}
	}

	function confirm( opts ) {
		opts = opts || {};
		var modal = getModal();
		if ( ! modal ) {
			return Promise.resolve( window.confirm( opts.body || opts.title || 'Confirm?' ) );
		}
		return new Promise( function ( resolve ) {
			confirmResolver = resolve;
			document.getElementById( 'vlt-confirm-title' ).textContent = opts.title || '';
			document.getElementById( 'vlt-confirm-body' ).textContent = opts.body || '';
			var ok = document.getElementById( 'vlt-confirm-ok' );
			ok.textContent = opts.confirmLabel || ok.getAttribute( 'data-default-label' ) || 'Confirm';
			ok.className = 'vlt-btn ' + ( opts.danger === false ? 'vlt-btn--primary' : 'vlt-btn--danger' );
			modal.hidden = false;
			document.body.classList.add( 'vlt-modal-open' );
			ok.focus();
		} );
	}

	function syncQueryParam( key, value ) {
		try {
			var url = new URL( window.location.href );
			if ( value ) {
				url.searchParams.set( key, value );
			} else {
				url.searchParams.delete( key );
			}
			window.history.replaceState( {}, '', url.toString() );
		} catch ( e ) { /* ignore */ }
	}

	function syncVideoQuery( videoKey ) {
		syncQueryParam( 'video', videoKey );
	}

	function initVideoFilter() {
		document.querySelectorAll( '[data-vlt-video-filter]' ).forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				var key = select.value || '';
				syncVideoQuery( key );
				document.dispatchEvent( new CustomEvent( 'vlt:video-filter', {
					detail: { video: key, page: select.closest( '.vlt-app' ) && select.closest( '.vlt-app' ).getAttribute( 'data-page' ) },
				} ) );
				var wrap = select.closest( '.vlt-video-filter' );
				if ( ! wrap ) return;
				var clearBtn = wrap.querySelector( '[data-vlt-video-filter-clear]' );
				if ( key && ! clearBtn ) {
					var btn = document.createElement( 'button' );
					btn.type = 'button';
					btn.className = 'vlt-btn vlt-btn--ghost vlt-video-filter-clear';
					btn.setAttribute( 'data-vlt-video-filter-clear', '' );
					btn.textContent = ( window.vltAdminData && window.vltAdminData.i18n && window.vltAdminData.i18n.clear ) || 'Clear';
					wrap.appendChild( btn );
				} else if ( ! key && clearBtn ) {
					clearBtn.remove();
				}
			} );
		} );

		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-vlt-video-filter-clear]' );
			if ( ! btn ) return;
			var wrap = btn.closest( '.vlt-video-filter' );
			var select = wrap && wrap.querySelector( '[data-vlt-video-filter]' );
			if ( ! select ) return;
			select.value = '';
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	}

	function initVideoIdFilter() {
		document.querySelectorAll( '[data-vlt-video-id-filter]' ).forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				var id = select.value || '';
				syncQueryParam( 'video_id', id );
				document.dispatchEvent( new CustomEvent( 'vlt:video-id-filter', {
					detail: {
						videoId: id ? parseInt( id, 10 ) : 0,
						page: select.closest( '.vlt-app' ) && select.closest( '.vlt-app' ).getAttribute( 'data-page' ),
					},
				} ) );
			} );
		} );
	}

	function initModal() {
		var modal = getModal();
		if ( ! modal ) return;
		var ok = document.getElementById( 'vlt-confirm-ok' );
		if ( ok && ! ok.getAttribute( 'data-default-label' ) ) {
			ok.setAttribute( 'data-default-label', ok.textContent );
		}
		modal.querySelectorAll( '[data-vlt-modal-dismiss]' ).forEach( function ( el ) {
			el.addEventListener( 'click', closeModal );
		} );
		if ( ok ) {
			ok.addEventListener( 'click', function () {
				var resolve = confirmResolver;
				confirmResolver = null;
				modal.hidden = true;
				document.body.classList.remove( 'vlt-modal-open' );
				if ( resolve ) resolve( true );
			} );
		}
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && modal && ! modal.hidden ) closeModal();
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initVideoFilter();
		initVideoIdFilter();
		initModal();
	} );

	window.vltUi = {
		toast: showToast,
		confirm: confirm,
		closeModal: closeModal,
		syncVideoQuery: syncVideoQuery,
		syncQueryParam: syncQueryParam,
	};
} )( window, document );
