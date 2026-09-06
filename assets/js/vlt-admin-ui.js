/* Video Lead Tracker — Admin UI primitives (toast, modal, video filter) */
( function ( window, document ) {
	'use strict';

	var confirmResolver = null;
	var lastFocus = null;
	var trapHandler = null;

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

	function getFocusable( root ) {
		if ( ! root ) return [];
		var nodes = root.querySelectorAll(
			'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
		);
		return Array.prototype.filter.call( nodes, function ( el ) {
			return ! el.hasAttribute( 'hidden' ) && el.getAttribute( 'aria-hidden' ) !== 'true';
		} );
	}

	function releaseFocusTrap() {
		if ( trapHandler ) {
			document.removeEventListener( 'keydown', trapHandler, true );
			trapHandler = null;
		}
	}

	function restoreFocus() {
		if ( lastFocus && typeof lastFocus.focus === 'function' ) {
			try { lastFocus.focus(); } catch ( e ) { /* ignore */ }
		}
		lastFocus = null;
	}

	function closeModal() {
		var modal = getModal();
		if ( ! modal ) return;
		modal.hidden = true;
		modal.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'vlt-modal-open' );
		releaseFocusTrap();
		restoreFocus();
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
			lastFocus = document.activeElement;
			document.getElementById( 'vlt-confirm-title' ).textContent = opts.title || '';
			document.getElementById( 'vlt-confirm-body' ).textContent = opts.body || '';
			var ok = document.getElementById( 'vlt-confirm-ok' );
			ok.textContent = opts.confirmLabel || ok.getAttribute( 'data-default-label' ) || 'Confirm';
			ok.className = 'vlt-btn ' + ( opts.danger === false ? 'vlt-btn--primary' : 'vlt-btn--danger' );
			modal.hidden = false;
			modal.setAttribute( 'aria-hidden', 'false' );
			document.body.classList.add( 'vlt-modal-open' );

			releaseFocusTrap();
			trapHandler = function ( e ) {
				if ( modal.hidden ) return;
				if ( e.key === 'Escape' ) {
					e.preventDefault();
					closeModal();
					return;
				}
				if ( e.key !== 'Tab' ) return;
				var nodes = getFocusable( modal.querySelector( '.vlt-modal-dialog' ) );
				if ( ! nodes.length ) return;
				var first = nodes[ 0 ];
				var last = nodes[ nodes.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			};
			document.addEventListener( 'keydown', trapHandler, true );
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

	function getTheme() {
		try {
			var t = localStorage.getItem( 'vlt_admin_theme' ) || 'dark';
			return ( t === 'light' ) ? 'light' : 'dark';
		} catch ( e ) {
			return 'dark';
		}
	}

	function applyTheme( theme ) {
		theme = theme === 'light' ? 'light' : 'dark';
		try { localStorage.setItem( 'vlt_admin_theme', theme ); } catch ( e ) { /* ignore */ }
		if ( document.body ) document.body.setAttribute( 'data-vlt-theme', theme );
		document.querySelectorAll( '.vlt-app' ).forEach( function ( app ) {
			app.setAttribute( 'data-theme', theme );
		} );
		var label = theme === 'light'
			? ( ( window.vltAdminData && window.vltAdminData.i18n && window.vltAdminData.i18n.themeLight ) || 'Light' )
			: ( ( window.vltAdminData && window.vltAdminData.i18n && window.vltAdminData.i18n.themeDark ) || 'Dark' );
		document.querySelectorAll( '[data-vlt-theme-label]' ).forEach( function ( el ) {
			el.textContent = label;
		} );
		document.querySelectorAll( '[data-vlt-theme-toggle]' ).forEach( function ( btn ) {
			btn.setAttribute( 'aria-pressed', theme === 'light' ? 'true' : 'false' );
		} );
	}

	function toggleTheme() {
		applyTheme( getTheme() === 'light' ? 'dark' : 'light' );
	}

	function initTheme() {
		applyTheme( getTheme() );
		if ( ! window.__vltThemeBound ) {
			window.__vltThemeBound = true;
			document.addEventListener( 'click', function ( e ) {
				if ( ! e.target.closest( '[data-vlt-theme-toggle]' ) ) return;
				e.preventDefault();
				toggleTheme();
			} );
		}
	}

	function syncVideoQuery( videoKey ) {
		syncQueryParam( 'video', videoKey );
	}

	function kpiSkeletonHtml( count ) {
		count = count || 4;
		var html = '';
		for ( var i = 0; i < count; i++ ) {
			html += '<div class="vlt-kpi-card vlt-kpi-card--skeleton" aria-hidden="true">' +
				'<span class="vlt-skeleton vlt-skeleton--icon"></span>' +
				'<span class="vlt-skeleton vlt-skeleton--number"></span>' +
				'<span class="vlt-skeleton vlt-skeleton--label"></span>' +
				'</div>';
		}
		return html;
	}

	function tableSkeletonHtml( cols, rows ) {
		cols = cols || 5;
		rows = rows || 5;
		var html = '';
		for ( var r = 0; r < rows; r++ ) {
			html += '<tr class="vlt-skeleton-tr" aria-hidden="true">';
			for ( var c = 0; c < cols; c++ ) {
				html += '<td><span class="vlt-skeleton vlt-skeleton--cell"></span></td>';
			}
			html += '</tr>';
		}
		return html;
	}

	function initFlashToasts() {
		document.querySelectorAll( '[data-vlt-toast-success]' ).forEach( function ( el ) {
			var msg = el.getAttribute( 'data-vlt-toast-success' );
			if ( msg ) showToast( msg, 'success' );
			el.removeAttribute( 'data-vlt-toast-success' );
		} );
		document.querySelectorAll( '[data-vlt-toast-error]' ).forEach( function ( el ) {
			var msg = el.getAttribute( 'data-vlt-toast-error' );
			if ( msg ) showToast( msg, 'error' );
			el.removeAttribute( 'data-vlt-toast-error' );
		} );
	}

	function initVideoFilter() {
		if ( window.__vltVideoFilterBound ) return;
		window.__vltVideoFilterBound = true;

		document.addEventListener( 'change', function ( e ) {
			var select = e.target.closest( '[data-vlt-video-filter]' );
			if ( ! select ) return;
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
		if ( window.__vltVideoIdFilterBound ) return;
		window.__vltVideoIdFilterBound = true;

		document.addEventListener( 'change', function ( e ) {
			var select = e.target.closest( '[data-vlt-video-id-filter]' );
			if ( ! select ) return;
			var id = select.value || '';
			syncQueryParam( 'video_id', id );
			document.dispatchEvent( new CustomEvent( 'vlt:video-id-filter', {
				detail: {
					videoId: id ? parseInt( id, 10 ) : 0,
					page: select.closest( '.vlt-app' ) && select.closest( '.vlt-app' ).getAttribute( 'data-page' ),
				},
			} ) );
		} );
	}

	function initModal() {
		var modal = getModal();
		if ( ! modal ) return;
		modal.setAttribute( 'aria-hidden', modal.hidden ? 'true' : 'false' );
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
				modal.setAttribute( 'aria-hidden', 'true' );
				document.body.classList.remove( 'vlt-modal-open' );
				releaseFocusTrap();
				restoreFocus();
				if ( resolve ) resolve( true );
			} );
		}
	}

	function mountUi() {
		initTheme();
		initVideoFilter();
		initVideoIdFilter();
		initModal();
		initFlashToasts();
	}

	document.addEventListener( 'DOMContentLoaded', mountUi );
	document.addEventListener( 'vlt:page-loaded', function () {
		initTheme();
		initFlashToasts();
	} );

	window.vltUi = {
		toast: showToast,
		confirm: confirm,
		closeModal: closeModal,
		syncVideoQuery: syncVideoQuery,
		syncQueryParam: syncQueryParam,
		kpiSkeletonHtml: kpiSkeletonHtml,
		tableSkeletonHtml: tableSkeletonHtml,
		applyTheme: applyTheme,
		getTheme: getTheme,
		mount: mountUi,
	};
} )( window, document );
