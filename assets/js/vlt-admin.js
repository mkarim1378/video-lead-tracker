/* Video Lead Tracker — Admin UI */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		mountAdmin();
	} );

	function mountAdmin() {
		bindDataManagementOnce();
		document.querySelectorAll( '.vlt-tab-nav' ).forEach( initTabGroup );
		initOverviewPage();
		initKpiCustomize();
	}

	window.vltAdmin = {
		mount: mountAdmin,
	};

	/**
	 * Wire up one tab nav + its panels.
	 * Reads the persisted active tab from localStorage (keyed by data-storage-key).
	 * Falls back to the first tab if nothing is stored or the stored ID is missing.
	 *
	 * HTML contract:
	 *   <nav class="vlt-tab-nav" data-storage-key="some_unique_key">
	 *     <button type="button" class="nav-tab" data-tab="panel-id">Label</button>
	 *     ...
	 *   </nav>
	 *   <div class="vlt-tab-panel" id="panel-id">...</div>
	 */
	function initTabGroup( nav ) {
		var storageKey = nav.dataset.storageKey || 'vlt_active_tab';
		var buttons    = Array.prototype.slice.call( nav.querySelectorAll( '[data-tab]' ) );

		if ( ! buttons.length ) return;

		nav.setAttribute( 'role', 'tablist' );

		buttons.forEach( function ( btn ) {
			var panelId = btn.dataset.tab;
			var tabId   = 'vlt-tabbtn-' + panelId;
			btn.setAttribute( 'role', 'tab' );
			btn.setAttribute( 'id', tabId );
			btn.setAttribute( 'aria-controls', panelId );
			var panel = document.getElementById( panelId );
			if ( panel ) {
				panel.setAttribute( 'role', 'tabpanel' );
				panel.setAttribute( 'aria-labelledby', tabId );
				panel.setAttribute( 'tabindex', '0' );
			}
		} );

		// Find the button to activate initially.
		var savedId    = localStorage.getItem( storageKey );
		var activeBtn  = buttons.find( function ( b ) { return b.dataset.tab === savedId; } )
		              || buttons[ 0 ];

		activate( activeBtn, buttons, storageKey );

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				activate( btn, buttons, storageKey );
			} );
		} );

		nav.addEventListener( 'keydown', function ( e ) {
			var idx = buttons.indexOf( document.activeElement );
			if ( idx < 0 ) return;
			var rtl = document.documentElement.getAttribute( 'dir' ) === 'rtl'
				|| document.body.classList.contains( 'rtl' );
			var next = null;
			if ( e.key === 'ArrowRight' || e.key === 'ArrowLeft' ) {
				var dir = e.key === 'ArrowRight' ? 1 : -1;
				if ( rtl ) dir *= -1;
				next = buttons[ ( idx + dir + buttons.length ) % buttons.length ];
			} else if ( e.key === 'Home' ) {
				next = buttons[ 0 ];
			} else if ( e.key === 'End' ) {
				next = buttons[ buttons.length - 1 ];
			}
			if ( ! next ) return;
			e.preventDefault();
			activate( next, buttons, storageKey );
			next.focus();
		} );
	}

	function activate( activeBtn, allButtons, storageKey ) {
		allButtons.forEach( function ( btn ) {
			var panel = document.getElementById( btn.dataset.tab );
			var on = btn === activeBtn;

			if ( on ) {
				btn.classList.add( 'nav-tab-active', 'is-active' );
			} else {
				btn.classList.remove( 'nav-tab-active', 'is-active' );
			}
			btn.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			btn.tabIndex = on ? 0 : -1;

			if ( panel ) {
				panel.style.display = on ? '' : 'none';
				panel.hidden = ! on;
				panel.setAttribute( 'aria-hidden', on ? 'false' : 'true' );
			}
		} );

		// Hide the main settings form submit button when a tab that has its own
		// form (or no form at all) is active, so users don't click the wrong button.
		var noFormTabs  = [ 'vlt-tab-data-management', 'vlt-tab-content-type', 'vlt-tab-shortcodes' ];
		var submitWrap  = document.getElementById( 'vlt-main-submit-wrap' );
		if ( submitWrap ) {
			submitWrap.style.display = noFormTabs.indexOf( activeBtn.dataset.tab ) === -1 ? '' : 'none';
		}

		localStorage.setItem( storageKey, activeBtn.dataset.tab );
	}

	/* ---- Data Management (delegated — safe across AJAX page swaps) ---- */

	function bindDataManagementOnce() {
		if ( window.__vltDmBound ) return;
		window.__vltDmBound = true;

		document.addEventListener( 'click', function ( e ) {
			var resetBtn = e.target.closest( '.vlt-reset-btn' );
			if ( resetBtn ) {
				handleResetClick( resetBtn );
				return;
			}
			var purgeBtn = e.target.closest( '.vlt-purge-logs-btn' );
			if ( purgeBtn ) {
				handlePurgeClick( purgeBtn );
			}
		} );

		document.addEventListener( 'input', function ( e ) {
			var input = e.target.closest( '.vlt-reset-id-input[name="lead_id"], .vlt-reset-id-input[name="page_id"]' );
			if ( ! input ) return;
			handleLookupInput( input );
		} );
	}

	function handleResetClick( btn ) {
		var panel       = btn.closest( '.vlt-reset-panel' );
		var scope       = btn.getAttribute( 'data-scope' );
		var confirmText = btn.getAttribute( 'data-confirm-text' ) || '';
		var body        = { scope: scope };
		var toast       = window.vltUi && window.vltUi.toast ? window.vltUi.toast : function ( m ) { alert( m ); };

		if ( scope === 'full' ) {
			var typed = panel ? panel.querySelector( '.vlt-reset-typed-confirm' ) : null;
			if ( ! typed || typed.value.trim() !== confirmText ) {
				toast( 'Type ' + confirmText + ' to confirm.', 'error' );
				return;
			}
		} else {
			var cb = panel ? panel.querySelector( '.vlt-reset-confirm-cb' ) : null;
			if ( ! cb || ! cb.checked ) {
				toast( 'Check the confirmation box first.', 'error' );
				return;
			}
			var idInput = panel ? panel.querySelector( '.vlt-reset-id-input' ) : null;
			if ( idInput ) {
				var idVal = idInput.value;
				if ( ! idVal ) { toast( 'Select or enter a value first.', 'error' ); return; }
				body[ idInput.name ] = parseInt( idVal, 10 ) || idVal;
			}
		}

		var run = function () {
			var origText = btn.textContent;
			btn.disabled    = true;
			btn.textContent = '…';
			var api = window.vltApi;
			var req = api
				? api.post( 'admin/reset', body )
				: fetch( ( window.vltAdminData.restBase || '' ) + 'admin/reset', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.vltAdminData.nonce || '' },
					body: JSON.stringify( body ),
				} ).then( function ( r ) { return r.json(); } );

			req.then( function ( data ) {
				if ( data.success ) {
					btn.textContent = '✓ Done';
					btn.classList.add( 'vlt-btn--primary' );
					toast( 'Reset complete.', 'success' );
				} else {
					btn.disabled    = false;
					btn.textContent = origText;
					toast( ( data.message || ( data.data && data.data.message ) ) || 'Reset failed.', 'error' );
				}
			} ).catch( function () {
				btn.disabled    = false;
				btn.textContent = origText;
				toast( 'Network error. Please try again.', 'error' );
			} );
		};

		if ( window.vltUi && window.vltUi.confirm ) {
			window.vltUi.confirm( {
				title: 'Confirm reset',
				body: 'This action cannot be undone.',
				danger: true,
			} ).then( function ( ok ) { if ( ok ) run(); } );
		} else {
			run();
		}
	}

	function handlePurgeClick( btn ) {
		var proceed = function () {
			var origText = btn.textContent;
			btn.disabled    = true;
			btn.textContent = '…';
			var api = window.vltApi;
			var req = api
				? api.post( 'admin/purge-logs', {} )
				: fetch( ( window.vltAdminData.restBase || '' ) + 'admin/purge-logs', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.vltAdminData.nonce || '' },
					body: '{}',
				} ).then( function ( r ) { return r.json(); } );

			req.then( function ( data ) {
				if ( data.success ) {
					if ( window.vltAdminNav && window.vltAdminNav.navigate ) {
						window.vltAdminNav.navigate( window.location.href, { replace: true } );
					} else {
						window.location.reload();
					}
				} else {
					btn.disabled    = false;
					btn.textContent = origText;
					if ( window.vltUi ) window.vltUi.toast( 'Purge failed.', 'error' );
				}
			} ).catch( function () {
				btn.disabled    = false;
				btn.textContent = origText;
				if ( window.vltUi ) window.vltUi.toast( 'Network error. Please try again.', 'error' );
			} );
		};

		if ( window.vltUi && window.vltUi.confirm ) {
			window.vltUi.confirm( {
				title: 'Purge all logs?',
				body: 'Delete all log entries? This cannot be undone.',
				danger: true,
			} ).then( function ( ok ) { if ( ok ) proceed(); } );
		} else if ( window.confirm( 'Delete all log entries? This cannot be undone.' ) ) {
			proceed();
		}
	}

	var lookupTimers = new WeakMap();

	function handleLookupInput( input ) {
		var preview = input.parentElement.querySelector( '.vlt-dm-preview' );
		var type    = preview ? preview.getAttribute( 'data-lookup-type' ) : null;
		if ( ! preview || ! type ) return;

		var prev = lookupTimers.get( input );
		if ( prev ) clearTimeout( prev );
		preview.textContent = '';
		preview.style.color = '';
		if ( ! input.value ) return;
		preview.textContent = '…';
		lookupTimers.set( input, setTimeout( function () {
			var restBase = ( window.vltAdminData && window.vltAdminData.restBase ) || '';
			var nonce    = ( window.vltAdminData && window.vltAdminData.nonce )    || '';
			fetch( restBase + 'admin/lookup?type=' + type + '&id=' + parseInt( input.value, 10 ), {
				headers: { 'X-WP-Nonce': nonce },
			} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( data.success ) {
					preview.textContent = data.label + ' \u2014 ' + data.sub;
					preview.style.color = '#1a9e6a';
				} else {
					preview.textContent = 'Not found';
					preview.style.color = '#c22f3a';
				}
			} )
			.catch( function () { preview.textContent = ''; } );
		}, 500 ) );
	}

	/* ---- Heatmap (exposed for AJAX remount) ---- */

	function formatTime( seconds ) {
		seconds = parseInt( seconds, 10 ) || 0;
		var h = Math.floor( seconds / 3600 );
		var m = Math.floor( ( seconds % 3600 ) / 60 );
		var s = seconds % 60;
		var mm = m < 10 ? '0' + m : '' + m;
		var ss = s < 10 ? '0' + s : '' + s;
		return h > 0 ? h + ':' + mm + ':' + ss : m + ':' + ss;
	}

	function drawLine( buckets, metric, maxTotal, maxUnique, maxDropOff, bucketSize ) {
		var chart = document.getElementById( 'vlt-hm-chart' );
		var axis  = document.getElementById( 'vlt-hm-axis' );
		if ( ! chart ) return;

		chart.innerHTML = '';
		if ( axis ) axis.innerHTML = '';
		if ( ! buckets.length ) return;

		var isDropOff  = metric === 'drop_off';
		var maxVal     = isDropOff ? maxDropOff : ( metric === 'total' ? maxTotal : maxUnique );
		if ( maxVal < 1 ) maxVal = 1;

		var lineColor  = isDropOff ? '#c22f3a' : '#1a9aa8';
		var n          = buckets.length;

		var VW = 900, VH = 200;
		var padL = 46, padR = 20, padT = 10, padB = 36;
		var cW = VW - padL - padR;
		var cH = VH - padT - padB;
		var ns = 'http://www.w3.org/2000/svg';

		var svg = document.createElementNS( ns, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 ' + VW + ' ' + VH );
		svg.setAttribute( 'width', '100%' );
		svg.style.display = 'block';

		for ( var gi = 0; gi <= 4; gi++ ) {
			var gy     = padT + ( cH / 4 ) * gi;
			var yLabel = Math.round( maxVal * ( 4 - gi ) / 4 );

			var gLine = document.createElementNS( ns, 'line' );
			gLine.setAttribute( 'x1', padL );     gLine.setAttribute( 'y1', gy );
			gLine.setAttribute( 'x2', padL + cW ); gLine.setAttribute( 'y2', gy );
			gLine.setAttribute( 'stroke', '#2a3a3f' ); gLine.setAttribute( 'stroke-width', '1' );
			svg.appendChild( gLine );

			var gTxt = document.createElementNS( ns, 'text' );
			gTxt.setAttribute( 'x', padL - 6 ); gTxt.setAttribute( 'y', gy + 4 );
			gTxt.setAttribute( 'text-anchor', 'end' );
			gTxt.setAttribute( 'font-size', '11' ); gTxt.setAttribute( 'fill', '#8fa3a9' );
			gTxt.textContent = yLabel;
			svg.appendChild( gTxt );
		}

		var pts = buckets.map( function ( b, i ) {
			var val = isDropOff
				? ( parseInt( b.drop_off, 10 ) || 0 )
				: ( parseInt( b[ metric ] || b.total, 10 ) || 0 );
			var x = padL + ( n > 1 ? i / ( n - 1 ) : 0.5 ) * cW;
			var y = padT + cH - ( val / maxVal ) * cH;
			return { x: x, y: y, val: val, second: b.second };
		} );

		var baseY     = padT + cH;
		var areaCoord = pts[ 0 ].x + ',' + baseY + ' '
			+ pts.map( function ( p ) { return p.x + ',' + p.y; } ).join( ' ' )
			+ ' ' + pts[ n - 1 ].x + ',' + baseY;
		var area = document.createElementNS( ns, 'polygon' );
		area.setAttribute( 'points', areaCoord );
		area.setAttribute( 'fill', lineColor );
		area.setAttribute( 'fill-opacity', '0.12' );
		svg.appendChild( area );

		var poly = document.createElementNS( ns, 'polyline' );
		poly.setAttribute( 'points', pts.map( function ( p ) { return p.x + ',' + p.y; } ).join( ' ' ) );
		poly.setAttribute( 'fill', 'none' );
		poly.setAttribute( 'stroke', lineColor );
		poly.setAttribute( 'stroke-width', '2' );
		poly.setAttribute( 'stroke-linejoin', 'round' );
		poly.setAttribute( 'stroke-linecap', 'round' );
		svg.appendChild( poly );

		var top3 = [];
		if ( isDropOff ) {
			top3 = pts.slice()
				.sort( function ( a, b ) { return b.val - a.val; } )
				.slice( 0, 3 )
				.map( function ( p ) { return p.second; } );
		}

		var dotEvery = Math.max( 1, Math.ceil( n / 60 ) );
		pts.forEach( function ( p, i ) {
			var isTop = isDropOff && top3.indexOf( p.second ) !== -1 && p.val > 0;
			if ( ! isTop && i % dotEvery !== 0 ) return;

			var c = document.createElementNS( ns, 'circle' );
			c.setAttribute( 'cx', p.x ); c.setAttribute( 'cy', p.y );
			c.setAttribute( 'r',  isTop ? 5 : 3 );
			c.setAttribute( 'fill',         isTop ? '#c22f3a' : lineColor );
			c.setAttribute( 'stroke',        '#182225' );
			c.setAttribute( 'stroke-width', '1.5' );
			var t = document.createElementNS( ns, 'title' );
			t.textContent = formatTime( p.second ) + '–' + formatTime( p.second + bucketSize ) + ': ' + p.val;
			c.appendChild( t );
			svg.appendChild( c );
		} );

		var bl = document.createElementNS( ns, 'line' );
		bl.setAttribute( 'x1', padL );      bl.setAttribute( 'y1', padT + cH );
		bl.setAttribute( 'x2', padL + cW ); bl.setAttribute( 'y2', padT + cH );
		bl.setAttribute( 'stroke', '#2a3a3f' ); bl.setAttribute( 'stroke-width', '1' );
		svg.appendChild( bl );

		var labelEvery = Math.max( 1, Math.ceil( n / 10 ) );
		pts.forEach( function ( p, i ) {
			if ( i % labelEvery !== 0 && i !== n - 1 ) return;

			var tk = document.createElementNS( ns, 'line' );
			tk.setAttribute( 'x1', p.x ); tk.setAttribute( 'y1', padT + cH );
			tk.setAttribute( 'x2', p.x ); tk.setAttribute( 'y2', padT + cH + 4 );
			tk.setAttribute( 'stroke', '#2a3a3f' ); tk.setAttribute( 'stroke-width', '1' );
			svg.appendChild( tk );

			var xt = document.createElementNS( ns, 'text' );
			xt.setAttribute( 'x', p.x ); xt.setAttribute( 'y', VH - padB + 16 );
			xt.setAttribute( 'text-anchor', 'middle' );
			xt.setAttribute( 'font-size', '10' ); xt.setAttribute( 'fill', '#8fa3a9' );
			xt.textContent = formatTime( p.second );
			svg.appendChild( xt );
		} );

		chart.appendChild( svg );
	}

	function mountHeatmap() {
		var wrap = document.getElementById( 'vlt-hm-wrap' );
		if ( ! wrap ) return;

		var buckets    = JSON.parse( wrap.getAttribute( 'data-buckets' )     || '[]' );
		var maxTotal   = parseInt(   wrap.getAttribute( 'data-max-total' ),   10 ) || 1;
		var maxUnique  = parseInt(   wrap.getAttribute( 'data-max-unique' ),  10 ) || 1;
		var maxDropOff = parseInt(   wrap.getAttribute( 'data-max-drop-off' ), 10 ) || 1;
		var bucketSize = parseInt(   wrap.getAttribute( 'data-bucket-size' ), 10 ) || 1;
		var metric     = 'total';

		drawLine( buckets, metric, maxTotal, maxUnique, maxDropOff, bucketSize );

		document.querySelectorAll( '.vlt-hm-metric' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				document.querySelectorAll( '.vlt-hm-metric' ).forEach( function ( b ) {
					b.classList.remove( 'vlt-btn--primary' );
					b.classList.add( 'vlt-btn--ghost' );
				} );
				btn.classList.add( 'vlt-btn--primary' );
				btn.classList.remove( 'vlt-btn--ghost' );
				metric = btn.getAttribute( 'data-metric' );
				drawLine( buckets, metric, maxTotal, maxUnique, maxDropOff, bucketSize );
			} );
		} );
	}

	window.vltHeatmap = { mount: mountHeatmap };

	/* ---- Overview AJAX + KPI customize (Phase 0) ---- */

	/* Overview + KPI customize are mounted via window.vltAdmin.mount() */

	function i18n( key, fallback ) {
		var map = ( window.vltAdminData && window.vltAdminData.i18n ) || {};
		return map[ key ] || fallback || key;
	}

	var overviewScope = null;

	function initOverviewPage() {
		var root = document.getElementById( 'vlt-overview-root' );
		if ( ! root || ! window.vltApi ) return;

		if ( overviewScope ) overviewScope.abort();
		overviewScope = window.AbortController ? new AbortController() : null;
		var signal = overviewScope ? overviewScope.signal : undefined;

		var abort = null;

		document.addEventListener( 'vlt:video-filter', function ( e ) {
			var app = document.querySelector( '.vlt-app[data-page="vlt-overview"]' );
			if ( ! app ) return;
			var video = ( e.detail && e.detail.video ) || '';
			refreshOverview( video );
		}, signal ? { signal: signal } : false );

		function refreshOverview( video ) {
			if ( abort ) abort.abort();
			abort = ( window.AbortController ) ? new AbortController() : null;
			setOverviewLoading( true );

			window.vltApi.get( 'admin/overview', { video: video }, abort ? { signal: abort.signal } : {} )
				.then( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) throw new Error( 'bad response' );
					renderOverview( res.data );
				} )
				.catch( function ( err ) {
					if ( err && err.name === 'AbortError' ) return;
					if ( window.vltUi ) window.vltUi.toast( i18n( 'loadError', 'Could not refresh overview data.' ), 'error' );
					setOverviewLoading( false );
				} );
		}

		function setOverviewLoading( on ) {
			if ( ! on ) {
				root.classList.remove( 'is-loading' );
				return;
			}
			root.classList.add( 'is-loading' );
			var kpiRow = document.getElementById( 'vlt-overview-kpis' );
			if ( kpiRow && window.vltUi && window.vltUi.kpiSkeletonHtml ) {
				kpiRow.innerHTML = window.vltUi.kpiSkeletonHtml( kpiRow.querySelectorAll( '.vlt-kpi-card' ).length || 4 );
			}
			var leadsBody = root.querySelector( '#vlt-overview-leads tbody' );
			if ( leadsBody && window.vltUi && window.vltUi.tableSkeletonHtml ) {
				leadsBody.innerHTML = window.vltUi.tableSkeletonHtml( 5, 4 );
			}
			var videosBody = root.querySelector( '#vlt-overview-videos tbody' );
			if ( videosBody && window.vltUi && window.vltUi.tableSkeletonHtml ) {
				videosBody.innerHTML = window.vltUi.tableSkeletonHtml( 5, 4 );
			}
		}

		function renderOverview( data ) {
			root.setAttribute( 'data-video', data.video_key || '' );
			renderKpis( data.kpis || [] );
			renderLeads( data.recent_leads || [] );
			renderVideos( data.top_videos || [] );
			updateViewAllLinks( data.video_key || '' );
			setOverviewLoading( false );
			applyKpiVisibility();
		}

		function renderKpis( kpis ) {
			var row = document.getElementById( 'vlt-overview-kpis' );
			if ( ! row ) return;
			row.innerHTML = kpis.map( function ( card ) {
				return '<div class="vlt-kpi-card" data-kpi-key="' + escAttr( card.key ) + '">' +
					'<span class="vlt-kpi-icon dashicons ' + escAttr( card.icon ) + ' vlt-kpi-icon--' + escAttr( card.key ) + '" aria-hidden="true"></span>' +
					'<strong class="vlt-kpi-number">' + escHtml( card.value ) + '</strong>' +
					'<span class="vlt-kpi-label">' + escHtml( card.label ) + '</span>' +
					'</div>';
			} ).join( '' );
		}

		function renderLeads( rows ) {
			var tbody = root.querySelector( '#vlt-overview-leads tbody' );
			if ( ! tbody ) return;
			if ( ! rows.length ) {
				tbody.innerHTML = '<tr><td colspan="5" class="vlt-empty">' + escHtml( i18n( 'noLeads', 'No leads yet.' ) ) + '</td></tr>';
				return;
			}
			tbody.innerHTML = rows.map( function ( row ) {
				var badge = row.verified
					? '<span class="vlt-badge vlt-badge--success">' + escHtml( i18n( 'yes', 'Yes' ) ) + '</span>'
					: '<span class="vlt-badge">' + escHtml( i18n( 'no', 'No' ) ) + '</span>';
				return '<tr>' +
					'<td>' + escHtml( row.name ) + '</td>' +
					'<td><code class="vlt-mono">' + escHtml( row.mobile ) + '</code></td>' +
					'<td>' + badge + '</td>' +
					'<td>' + escHtml( row.avg_watch ) + '</td>' +
					'<td class="vlt-muted">' + escHtml( row.first_seen ) + '</td>' +
					'</tr>';
			} ).join( '' );
		}

		function renderVideos( rows ) {
			var tbody = root.querySelector( '#vlt-overview-videos tbody' );
			if ( ! tbody ) return;
			if ( ! rows.length ) {
				tbody.innerHTML = '<tr><td colspan="5" class="vlt-empty">' + escHtml( i18n( 'noVideos', 'No videos yet.' ) ) + '</td></tr>';
				return;
			}
			tbody.innerHTML = rows.map( function ( video ) {
				var title = '<strong>' + escHtml( video.title ) + '</strong>';
				if ( video.duration ) {
					title += '<br><span class="vlt-muted">' + escHtml( video.duration ) + '</span>';
				}
				return '<tr>' +
					'<td>' + title + '</td>' +
					'<td>' + escHtml( video.viewers ) + '</td>' +
					'<td>' + escHtml( video.completions ) + '</td>' +
					'<td>' + escHtml( video.avg_completion ) + '</td>' +
					'<td>' + escHtml( video.watch_hours ) + '</td>' +
					'</tr>';
			} ).join( '' );
		}

		function updateViewAllLinks( videoKey ) {
			var leadsLink = root.querySelector( '#vlt-overview-leads .vlt-panel-foot a' );
			if ( leadsLink ) {
				var base = ( window.vltAdminData && window.vltAdminData.adminUrl ) || 'admin.php';
				var url = base + '?page=vlt-leads';
				if ( videoKey ) url += '&video=' + encodeURIComponent( videoKey );
				leadsLink.setAttribute( 'href', url );
			}
		}
	}

	var KPI_LS_KEY = 'vlt_hidden_kpi';

	function getHiddenKpis() {
		try { return JSON.parse( localStorage.getItem( KPI_LS_KEY ) || '[]' ); }
		catch ( e ) { return []; }
	}

	function applyKpiVisibility() {
		var hidden = getHiddenKpis();
		document.querySelectorAll( '.vlt-kpi-card[data-kpi-key]' ).forEach( function ( card ) {
			var key = card.getAttribute( 'data-kpi-key' );
			card.style.display = hidden.indexOf( key ) !== -1 ? 'none' : '';
		} );
	}

	function initKpiCustomize() {
		applyKpiVisibility();
		if ( window.__vltKpiCustomizeBound ) return;
		window.__vltKpiCustomizeBound = true;

		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.vlt-kpi-customize-btn' );
			if ( btn ) {
				var existing = document.getElementById( 'vlt-kpi-picker' );
				if ( existing ) { existing.remove(); return; }

				var cards = document.querySelectorAll( '#vlt-overview-kpis .vlt-kpi-card[data-kpi-key]' );
				if ( ! cards.length ) return;

				var hidden = getHiddenKpis();
				var picker = document.createElement( 'div' );
				picker.id = 'vlt-kpi-picker';
				picker.className = 'vlt-kpi-picker-popover';
				picker.setAttribute( 'role', 'dialog' );
				picker.setAttribute( 'aria-label', i18n( 'customize', 'Customize Widgets' ) );

				cards.forEach( function ( card ) {
					var key   = card.getAttribute( 'data-kpi-key' );
					var label = card.querySelector( '.vlt-kpi-label' );
					var text  = label ? label.textContent : key;
					var row   = document.createElement( 'label' );
					var cb    = document.createElement( 'input' );
					cb.type = 'checkbox';
					cb.checked = hidden.indexOf( key ) === -1;
					cb.addEventListener( 'change', function () {
						var list = getHiddenKpis();
						if ( cb.checked ) {
							list = list.filter( function ( k ) { return k !== key; } );
						} else if ( list.indexOf( key ) === -1 ) {
							list.push( key );
						}
						localStorage.setItem( KPI_LS_KEY, JSON.stringify( list ) );
						applyKpiVisibility();
					} );
					row.appendChild( cb );
					row.appendChild( document.createTextNode( ' ' + text ) );
					picker.appendChild( row );
				} );

				btn.parentNode.appendChild( picker );
				return;
			}

			var picker = document.getElementById( 'vlt-kpi-picker' );
			if ( ! picker ) return;
			if ( e.target.closest( '#vlt-kpi-picker' ) || e.target.closest( '.vlt-kpi-customize-btn' ) ) return;
			picker.remove();
		} );
	}

	function escHtml( str ) {
		return String( str == null ? '' : str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function escAttr( str ) {
		return escHtml( str ).replace( /'/g, '&#39;' );
	}

} )();
