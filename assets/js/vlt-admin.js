/* Video Lead Tracker — Admin UI */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.vlt-tab-nav' ).forEach( initTabGroup );
	} );

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
	}

	function activate( activeBtn, allButtons, storageKey ) {
		allButtons.forEach( function ( btn ) {
			var panel = document.getElementById( btn.dataset.tab );

			if ( btn === activeBtn ) {
				btn.classList.add( 'nav-tab-active' );
				if ( panel ) panel.style.display = '';
			} else {
				btn.classList.remove( 'nav-tab-active' );
				if ( panel ) panel.style.display = 'none';
			}
		} );

		localStorage.setItem( storageKey, activeBtn.dataset.tab );
	}

	/* ---- Data Management resets ---- */

	document.querySelectorAll( '.vlt-reset-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var panel       = btn.closest( '.vlt-reset-panel' );
			var scope       = btn.getAttribute( 'data-scope' );
			var confirmText = btn.getAttribute( 'data-confirm-text' ) || '';
			var body        = { scope: scope };

			if ( scope === 'full' ) {
				var typed = panel ? panel.querySelector( '.vlt-reset-typed-confirm' ) : null;
				if ( ! typed || typed.value.trim() !== confirmText ) {
					// translators: %s = required confirmation word
					alert( 'Type ' + confirmText + ' to confirm.' );
					return;
				}
			} else {
				var cb = panel ? panel.querySelector( '.vlt-reset-confirm-cb' ) : null;
				if ( ! cb || ! cb.checked ) {
					alert( 'Check the confirmation box first.' );
					return;
				}
				var idInput = panel ? panel.querySelector( '.vlt-reset-id-input' ) : null;
				if ( idInput ) {
					var idVal = idInput.value;
					if ( ! idVal ) { alert( 'Select or enter a value first.' ); return; }
					body[ idInput.name ] = parseInt( idVal, 10 ) || idVal;
				}
			}

			var origText = btn.textContent;
			btn.disabled    = true;
			btn.textContent = '…';

			var restBase = ( window.vltAdminData && window.vltAdminData.restBase ) || '';
			var nonce    = ( window.vltAdminData && window.vltAdminData.nonce )    || '';

			fetch( restBase + 'admin/reset', {
				method:  'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
				body:    JSON.stringify( body ),
			} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( data.success ) {
					btn.textContent      = '✓ Done';
					btn.style.background = '#1a9e6a';
					btn.style.color      = '#fff';
					btn.style.border     = 'none';
				} else {
					btn.disabled    = false;
					btn.textContent = origText;
					alert( ( data.message || data.data && data.data.message ) || 'Reset failed.' );
				}
			} )
			.catch( function () {
				btn.disabled    = false;
				btn.textContent = origText;
				alert( 'Network error. Please try again.' );
			} );
		} );
	} );

	/* ---- Purge Logs ---- */

	document.querySelectorAll( '.vlt-purge-logs-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			if ( ! window.confirm( 'Delete all log entries? This cannot be undone.' ) ) {
				return;
			}

			var origText = btn.textContent;
			btn.disabled    = true;
			btn.textContent = '…';

			var restBase = ( window.vltAdminData && window.vltAdminData.restBase ) || '';
			var nonce    = ( window.vltAdminData && window.vltAdminData.nonce )    || '';

			fetch( restBase + 'admin/purge-logs', {
				method:  'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
				body:    '{}',
			} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( data.success ) {
					// Reload to show empty log table.
					window.location.reload();
				} else {
					btn.disabled    = false;
					btn.textContent = origText;
					alert( 'Purge failed.' );
				}
			} )
			.catch( function () {
				btn.disabled    = false;
				btn.textContent = origText;
				alert( 'Network error. Please try again.' );
			} );
		} );
	} );

	/* ---- Data Management — ID preview lookup ---- */

	document.querySelectorAll( '.vlt-reset-id-input[name="lead_id"], .vlt-reset-id-input[name="page_id"]' ).forEach( function ( input ) {
		var preview = input.parentElement.querySelector( '.vlt-dm-preview' );
		var type    = preview ? preview.getAttribute( 'data-lookup-type' ) : null;
		if ( ! preview || ! type ) return;

		var timer = null;
		input.addEventListener( 'input', function () {
			clearTimeout( timer );
			preview.textContent = '';
			preview.style.color = '';
			if ( ! input.value ) return;
			preview.textContent = '…';
			timer = setTimeout( function () {
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
			}, 500 );
		} );
	} );

	/* ---- Heatmap ---- */

	document.addEventListener( 'DOMContentLoaded', function () {
		initHeatmap();
	} );

	function initHeatmap() {
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
					b.classList.remove( 'button-primary' );
				} );
				btn.classList.add( 'button-primary' );
				metric = btn.getAttribute( 'data-metric' );
				drawLine( buckets, metric, maxTotal, maxUnique, maxDropOff, bucketSize );
			} );
		} );
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

		var lineColor  = isDropOff ? '#c22f3a' : '#2d2c74';
		var n          = buckets.length;

		// SVG viewport (fixed logical size, scales via viewBox).
		var VW = 900, VH = 200;
		var padL = 46, padR = 20, padT = 10, padB = 36;
		var cW = VW - padL - padR;
		var cH = VH - padT - padB;
		var ns = 'http://www.w3.org/2000/svg';

		var svg = document.createElementNS( ns, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 ' + VW + ' ' + VH );
		svg.setAttribute( 'width', '100%' );
		svg.style.display = 'block';

		// Y grid lines + labels (5 levels).
		for ( var gi = 0; gi <= 4; gi++ ) {
			var gy     = padT + ( cH / 4 ) * gi;
			var yLabel = Math.round( maxVal * ( 4 - gi ) / 4 );

			var gLine = document.createElementNS( ns, 'line' );
			gLine.setAttribute( 'x1', padL );     gLine.setAttribute( 'y1', gy );
			gLine.setAttribute( 'x2', padL + cW ); gLine.setAttribute( 'y2', gy );
			gLine.setAttribute( 'stroke', '#e8e8e8' ); gLine.setAttribute( 'stroke-width', '1' );
			svg.appendChild( gLine );

			var gTxt = document.createElementNS( ns, 'text' );
			gTxt.setAttribute( 'x', padL - 6 ); gTxt.setAttribute( 'y', gy + 4 );
			gTxt.setAttribute( 'text-anchor', 'end' );
			gTxt.setAttribute( 'font-size', '11' ); gTxt.setAttribute( 'fill', '#8c8f94' );
			gTxt.textContent = yLabel;
			svg.appendChild( gTxt );
		}

		// Map each bucket to an SVG point.
		var pts = buckets.map( function ( b, i ) {
			var val = isDropOff
				? ( parseInt( b.drop_off, 10 ) || 0 )
				: ( parseInt( b[ metric ] || b.total, 10 ) || 0 );
			var x = padL + ( n > 1 ? i / ( n - 1 ) : 0.5 ) * cW;
			var y = padT + cH - ( val / maxVal ) * cH;
			return { x: x, y: y, val: val, second: b.second };
		} );

		// Filled area under the line.
		var baseY     = padT + cH;
		var areaCoord = pts[ 0 ].x + ',' + baseY + ' '
			+ pts.map( function ( p ) { return p.x + ',' + p.y; } ).join( ' ' )
			+ ' ' + pts[ n - 1 ].x + ',' + baseY;
		var area = document.createElementNS( ns, 'polygon' );
		area.setAttribute( 'points', areaCoord );
		area.setAttribute( 'fill', lineColor );
		area.setAttribute( 'fill-opacity', '0.08' );
		svg.appendChild( area );

		// Polyline.
		var poly = document.createElementNS( ns, 'polyline' );
		poly.setAttribute( 'points', pts.map( function ( p ) { return p.x + ',' + p.y; } ).join( ' ' ) );
		poly.setAttribute( 'fill', 'none' );
		poly.setAttribute( 'stroke', lineColor );
		poly.setAttribute( 'stroke-width', '2' );
		poly.setAttribute( 'stroke-linejoin', 'round' );
		poly.setAttribute( 'stroke-linecap', 'round' );
		svg.appendChild( poly );

		// Top-3 exit seconds (drop-off mode only).
		var top3 = [];
		if ( isDropOff ) {
			top3 = pts.slice()
				.sort( function ( a, b ) { return b.val - a.val; } )
				.slice( 0, 3 )
				.map( function ( p ) { return p.second; } );
		}

		// Dots + native tooltips (thin out on dense charts).
		var dotEvery = Math.max( 1, Math.ceil( n / 60 ) );
		pts.forEach( function ( p, i ) {
			var isTop = isDropOff && top3.indexOf( p.second ) !== -1 && p.val > 0;
			if ( ! isTop && i % dotEvery !== 0 ) return;

			var c = document.createElementNS( ns, 'circle' );
			c.setAttribute( 'cx', p.x ); c.setAttribute( 'cy', p.y );
			c.setAttribute( 'r',  isTop ? 5 : 3 );
			c.setAttribute( 'fill',         isTop ? '#c22f3a' : lineColor );
			c.setAttribute( 'stroke',        '#fff' );
			c.setAttribute( 'stroke-width', '1.5' );
			var t = document.createElementNS( ns, 'title' );
			t.textContent = formatTime( p.second ) + '–' + formatTime( p.second + bucketSize ) + ': ' + p.val;
			c.appendChild( t );
			svg.appendChild( c );
		} );

		// X axis baseline.
		var bl = document.createElementNS( ns, 'line' );
		bl.setAttribute( 'x1', padL );      bl.setAttribute( 'y1', padT + cH );
		bl.setAttribute( 'x2', padL + cW ); bl.setAttribute( 'y2', padT + cH );
		bl.setAttribute( 'stroke', '#c3c4c7' ); bl.setAttribute( 'stroke-width', '1' );
		svg.appendChild( bl );

		// X axis labels (up to ~10 labels).
		var labelEvery = Math.max( 1, Math.ceil( n / 10 ) );
		pts.forEach( function ( p, i ) {
			if ( i % labelEvery !== 0 && i !== n - 1 ) return;

			var tk = document.createElementNS( ns, 'line' );
			tk.setAttribute( 'x1', p.x ); tk.setAttribute( 'y1', padT + cH );
			tk.setAttribute( 'x2', p.x ); tk.setAttribute( 'y2', padT + cH + 4 );
			tk.setAttribute( 'stroke', '#c3c4c7' ); tk.setAttribute( 'stroke-width', '1' );
			svg.appendChild( tk );

			var xt = document.createElementNS( ns, 'text' );
			xt.setAttribute( 'x', p.x ); xt.setAttribute( 'y', VH - padB + 16 );
			xt.setAttribute( 'text-anchor', 'middle' );
			xt.setAttribute( 'font-size', '10' ); xt.setAttribute( 'fill', '#8c8f94' );
			xt.textContent = formatTime( p.second );
			svg.appendChild( xt );
		} );

		chart.appendChild( svg );
	}

	function formatTime( seconds ) {
		seconds = parseInt( seconds, 10 ) || 0;
		var h = Math.floor( seconds / 3600 );
		var m = Math.floor( ( seconds % 3600 ) / 60 );
		var s = seconds % 60;
		var mm = m < 10 ? '0' + m : '' + m;
		var ss = s < 10 ? '0' + s : '' + s;
		return h > 0 ? h + ':' + mm + ':' + ss : m + ':' + ss;
	}

	/* ---- KPI widget show/hide ---- */

	document.addEventListener( 'DOMContentLoaded', function () {
		var LS_KEY    = 'vlt_hidden_kpi';
		var btn       = document.querySelector( '.vlt-kpi-customize-btn' );
		var cards     = document.querySelectorAll( '.vlt-kpi-card[data-kpi-key]' );

		if ( ! btn || ! cards.length ) return;

		var hidden = JSON.parse( localStorage.getItem( LS_KEY ) || '[]' );

		function applyVisibility() {
			cards.forEach( function ( card ) {
				var key = card.getAttribute( 'data-kpi-key' );
				card.style.display = hidden.indexOf( key ) !== -1 ? 'none' : '';
			} );
		}

		applyVisibility();

		btn.addEventListener( 'click', function () {
			var existing = document.getElementById( 'vlt-kpi-picker' );
			if ( existing ) { existing.remove(); return; }

			var picker = document.createElement( 'div' );
			picker.id = 'vlt-kpi-picker';
			picker.style.cssText = 'background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px 16px;margin:8px 0;display:inline-block;';

			cards.forEach( function ( card ) {
				var key   = card.getAttribute( 'data-kpi-key' );
				var label = card.querySelector( '.vlt-kpi-label' );
				var text  = label ? label.textContent : key;

				var row   = document.createElement( 'label' );
				row.style.cssText = 'display:block;margin:4px 0;cursor:pointer;';

				var cb    = document.createElement( 'input' );
				cb.type   = 'checkbox';
				cb.checked = hidden.indexOf( key ) === -1;
				cb.style.marginRight = '6px';
				cb.addEventListener( 'change', function () {
					if ( cb.checked ) {
						hidden = hidden.filter( function ( k ) { return k !== key; } );
					} else {
						if ( hidden.indexOf( key ) === -1 ) hidden.push( key );
					}
					localStorage.setItem( LS_KEY, JSON.stringify( hidden ) );
					applyVisibility();
				} );

				row.appendChild( cb );
				row.appendChild( document.createTextNode( text ) );
				picker.appendChild( row );
			} );

			btn.insertAdjacentElement( 'afterend', picker );
		} );
	} );

} )();
