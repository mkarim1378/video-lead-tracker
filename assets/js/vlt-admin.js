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

	/* ---- Heatmap ---- */

	document.addEventListener( 'DOMContentLoaded', function () {
		initHeatmap();
	} );

	function initHeatmap() {
		var wrap = document.getElementById( 'vlt-hm-wrap' );
		if ( ! wrap ) return;

		var buckets    = JSON.parse( wrap.getAttribute( 'data-buckets' )    || '[]' );
		var maxTotal   = parseInt(  wrap.getAttribute( 'data-max-total' ),   10 ) || 1;
		var maxUnique  = parseInt(  wrap.getAttribute( 'data-max-unique' ),  10 ) || 1;
		var bucketSize = parseInt(  wrap.getAttribute( 'data-bucket-size' ), 10 ) || 1;
		var metric     = 'total';

		drawBars( buckets, metric, maxTotal, maxUnique, bucketSize );

		document.querySelectorAll( '.vlt-hm-metric' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				document.querySelectorAll( '.vlt-hm-metric' ).forEach( function ( b ) {
					b.classList.remove( 'button-primary' );
				} );
				btn.classList.add( 'button-primary' );
				metric = btn.getAttribute( 'data-metric' );
				drawBars( buckets, metric, maxTotal, maxUnique, bucketSize );
			} );
		} );
	}

	function drawBars( buckets, metric, maxTotal, maxUnique, bucketSize ) {
		var chart = document.getElementById( 'vlt-hm-chart' );
		var axis  = document.getElementById( 'vlt-hm-axis' );
		if ( ! chart ) return;

		chart.innerHTML = '';
		if ( axis ) axis.innerHTML = '';

		if ( ! buckets.length ) return;

		var maxVal = metric === 'total' ? maxTotal : maxUnique;
		if ( maxVal < 1 ) maxVal = 1;

		var labelEvery = Math.max( 1, Math.ceil( buckets.length / 10 ) );

		buckets.forEach( function ( b, i ) {
			var val    = parseInt( b[ metric ] || b.total, 10 ) || 0;
			var pct    = Math.round( ( val / maxVal ) * 100 );
			var bar    = document.createElement( 'div' );
			bar.className   = 'vlt-hm-bar';
			bar.style.height = pct + '%';
			bar.title = formatTime( b.second ) + '–' + formatTime( b.second + bucketSize ) + ': ' + val;
			chart.appendChild( bar );

			if ( axis ) {
				var tick = document.createElement( 'div' );
				tick.className = 'vlt-hm-tick';
				if ( i % labelEvery === 0 ) {
					tick.className += ' vlt-hm-tick--label';
					tick.textContent = formatTime( b.second );
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
