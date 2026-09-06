/* Video Lead Tracker — In-app AJAX navigation between VLT admin pages */
( function ( window, document ) {
	'use strict';

	var navAbort = null;
	var navigating = false;

	function i18n( key, fallback ) {
		var map = ( window.vltAdminData && window.vltAdminData.i18n ) || {};
		return map[ key ] || fallback || key;
	}

	function isVltAdminUrl( url ) {
		try {
			var u = new URL( url, window.location.origin );
			if ( u.origin !== window.location.origin ) return false;
			if ( ! /\/wp-admin\/admin\.php$/i.test( u.pathname ) ) return false;
			var page = u.searchParams.get( 'page' ) || '';
			return page.indexOf( 'vlt-' ) === 0;
		} catch ( e ) {
			return false;
		}
	}

	function pageSlugFromUrl( url ) {
		try {
			return new URL( url, window.location.origin ).searchParams.get( 'page' ) || '';
		} catch ( e ) {
			return '';
		}
	}

	function setNavActive( slug ) {
		document.querySelectorAll( '.vlt-app-nav-link' ).forEach( function ( link ) {
			var href = link.getAttribute( 'href' ) || '';
			var on = pageSlugFromUrl( href ) === slug;
			link.classList.toggle( 'is-active', on );
			if ( on ) link.setAttribute( 'aria-current', 'page' );
			else link.removeAttribute( 'aria-current' );
		} );
		// Sync WP admin menu highlight when possible.
		document.querySelectorAll( '#adminmenu a[href*="page=vlt-"]' ).forEach( function ( a ) {
			var on = pageSlugFromUrl( a.href ) === slug;
			var li = a.closest( 'li' );
			if ( ! li ) return;
			li.classList.toggle( 'current', on );
			a.classList.toggle( 'current', on );
		} );
	}

	function remountPageControllers() {
		if ( window.vltUi && typeof window.vltUi.mount === 'function' ) {
			window.vltUi.mount();
		}
		if ( window.vltAdmin && typeof window.vltAdmin.mount === 'function' ) {
			window.vltAdmin.mount();
		}
		if ( window.vltAdminPages && typeof window.vltAdminPages.mount === 'function' ) {
			window.vltAdminPages.mount();
		}
		document.dispatchEvent( new CustomEvent( 'vlt:page-loaded', {
			detail: { page: document.querySelector( '.vlt-app' ) && document.querySelector( '.vlt-app' ).getAttribute( 'data-page' ) },
		} ) );
	}

	function swapContent( doc, url ) {
		var nextApp = doc.querySelector( '.vlt-app' );
		var nextContent = doc.querySelector( '.vlt-app-content' );
		var curApp = document.querySelector( '.vlt-app' );
		var curContent = document.querySelector( '.vlt-app-content' );
		if ( ! nextApp || ! nextContent || ! curApp || ! curContent ) {
			window.location.href = url;
			return false;
		}

		var theme = ( window.vltUi && window.vltUi.getTheme ) ? window.vltUi.getTheme() : 'dark';
		curApp.setAttribute( 'data-page', nextApp.getAttribute( 'data-page' ) || pageSlugFromUrl( url ) );
		curApp.setAttribute( 'data-theme', theme );
		curApp.setAttribute( 'dir', nextApp.getAttribute( 'dir' ) || curApp.getAttribute( 'dir' ) || 'ltr' );
		curContent.innerHTML = nextContent.innerHTML;
		document.title = doc.title || document.title;
		setNavActive( curApp.getAttribute( 'data-page' ) );
		if ( window.vltUi && window.vltUi.applyTheme ) window.vltUi.applyTheme( theme );
		return true;
	}

	function navigate( url, opts ) {
		opts = opts || {};
		if ( navigating ) return;
		if ( ! isVltAdminUrl( url ) ) {
			window.location.href = url;
			return;
		}

		var cur = window.location.href;
		if ( url === cur || url === window.location.pathname + window.location.search ) {
			return;
		}

		navigating = true;
		if ( navAbort ) navAbort.abort();
		navAbort = window.AbortController ? new AbortController() : null;

		var content = document.querySelector( '.vlt-app-content' );
		if ( content ) content.classList.add( 'is-navigating' );

		fetch( url, {
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'VLT-Admin-Nav' },
			signal: navAbort ? navAbort.signal : undefined,
		} )
			.then( function ( res ) {
				if ( ! res.ok ) throw new Error( 'http' );
				return res.text();
			} )
			.then( function ( html ) {
				var doc = new DOMParser().parseFromString( html, 'text/html' );
				if ( ! doc.querySelector( '.vlt-app' ) ) {
					window.location.href = url;
					return;
				}
				if ( ! swapContent( doc, url ) ) return;
				if ( ! opts.replace ) {
					window.history.pushState( { vltNav: true }, '', url );
				} else {
					window.history.replaceState( { vltNav: true }, '', url );
				}
				window.scrollTo( 0, 0 );
				remountPageControllers();
			} )
			.catch( function ( err ) {
				if ( err && err.name === 'AbortError' ) return;
				if ( window.vltUi ) {
					window.vltUi.toast( i18n( 'navError', 'Could not load page.' ), 'error' );
				}
				window.location.href = url;
			} )
			.finally( function () {
				navigating = false;
				if ( content ) content.classList.remove( 'is-navigating' );
			} );
	}

	function onClick( e ) {
		var link = e.target.closest( 'a.vlt-app-nav-link' );
		if ( ! link ) {
			// Also intercept WP submenu links to VLT pages while inside the shell.
			link = e.target.closest( '#adminmenu a[href*="page=vlt-"]' );
		}
		if ( ! link ) return;
		if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) return;
		var url = link.href;
		if ( ! isVltAdminUrl( url ) ) return;
		if ( ! document.querySelector( '.vlt-app' ) ) return;
		e.preventDefault();
		navigate( url );
	}

	function onPopState() {
		if ( ! document.querySelector( '.vlt-app' ) ) return;
		navigate( window.location.href, { replace: true } );
	}

	document.addEventListener( 'click', onClick );
	window.addEventListener( 'popstate', onPopState );

	window.vltAdminNav = {
		navigate: navigate,
		isVltAdminUrl: isVltAdminUrl,
	};
} )( window, document );
