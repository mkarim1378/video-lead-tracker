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

	/* ---- Heatmap ---- */

	document.addEventListener( 'DOMContentLoaded', function () {
		initHeatmap();
	} );

	function initHeatmap() {
		var wrap = document.getElementById( 'vlt-hm-wrap' );
		if ( ! wrap ) return;

		var buckets     = JSON.parse( wrap.getAttribute( 'data-buckets' )     || '[]' );
		var maxTotal    = parseInt(  wrap.getAttribute( 'data-max-total' ),    10 ) || 1;
		var maxUnique   = parseInt(  wrap.getAttribute( 'data-max-unique' ),   10 ) || 1;
		var maxDropOff  = parseInt(  wrap.getAttribute( 'data-max-drop-off' ), 10 ) || 1;
		var bucketSize  = parseInt(  wrap.getAttribute( 'data-bucket-size' ),  10 ) || 1;
		var metric      = 'total';

		drawBars( buckets, metric, maxTotal, maxUnique, maxDropOff, bucketSize );

		document.querySelectorAll( '.vlt-hm-metric' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				document.querySelectorAll( '.vlt-hm-metric' ).forEach( function ( b ) {
					b.classList.remove( 'button-primary' );
				} );
				btn.classList.add( 'button-primary' );
				metric = btn.getAttribute( 'data-metric' );
				drawBars( buckets, metric, maxTotal, maxUnique, maxDropOff, bucketSize );
			} );
		} );
	}

	function drawBars( buckets, metric, maxTotal, maxUnique, maxDropOff, bucketSize ) {
		var chart = document.getElementById( 'vlt-hm-chart' );
		var axis  = document.getElementById( 'vlt-hm-axis' );
		if ( ! chart ) return;

		chart.innerHTML = '';
		if ( axis ) axis.innerHTML = '';

		if ( ! buckets.length ) return;

		var isDropOff = metric === 'drop_off';
		var maxVal;
		if ( isDropOff ) {
			maxVal = maxDropOff;
		} else if ( metric === 'total' ) {
			maxVal = maxTotal;
		} else {
			maxVal = maxUnique;
		}
		if ( maxVal < 1 ) maxVal = 1;

		// Compute top-3 exit buckets for drop-off highlight.
		var top3Seconds = [];
		if ( isDropOff ) {
			var sorted = buckets.slice().sort( function ( a, b ) {
				return ( b.drop_off || 0 ) - ( a.drop_off || 0 );
			} );
			top3Seconds = sorted.slice( 0, 3 ).map( function ( b ) { return b.second; } );
		}

		var labelEvery = Math.max( 1, Math.ceil( buckets.length / 10 ) );

		buckets.forEach( function ( b, i ) {
			var val = isDropOff
				? ( parseInt( b.drop_off, 10 ) || 0 )
				: ( parseInt( b[ metric ] || b.total, 10 ) || 0 );
			var pct    = Math.round( ( val / maxVal ) * 100 );
			var bar    = document.createElement( 'div' );
			bar.className    = 'vlt-hm-bar';
			bar.style.height = pct + '%';
			bar.title        = formatTime( b.second ) + '–' + formatTime( b.second + bucketSize ) + ': ' + val;

			if ( isDropOff && top3Seconds.indexOf( b.second ) !== -1 && val > 0 ) {
				bar.style.background = '#c22f3a';
			}

			chart.appendChild( bar );

			if ( axis ) {
				var tick = document.createElement( 'div' );
				tick.className = 'vlt-hm-tick';
				if ( i % labelEvery === 0 ) {
					tick.className   += ' vlt-hm-tick--label';
					tick.textContent  = formatTime( b.second );
				}
				axis.appendChild( tick );
			}
		} );
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

} )();
