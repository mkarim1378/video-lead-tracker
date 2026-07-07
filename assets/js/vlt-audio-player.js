/* Video Lead Tracker — Audio Player */

( function () {
	'use strict';

	var BARS   = 54;
	var speeds = [ 1, 1.5, 2 ];

	// Persian digits helper
	function fa( s ) {
		return String( s ).replace( /\d/g, function ( d ) {
			return '\u06F0\u06F1\u06F2\u06F3\u06F4\u06F5\u06F6\u06F7\u06F8\u06F9'[ d ];
		} );
	}

	function formatTime( s ) {
		s = Math.max( 0, Math.floor( s ) );
		var m = Math.floor( s / 60 );
		var sec = s % 60;
		return fa( String( m ).padStart( 2, '0' ) + ':' + String( sec ).padStart( 2, '0' ) );
	}

	function initPlayer( wrap ) {
		var au     = wrap.querySelector( 'audio' );
		var btn    = wrap.querySelector( '.vlt-ap-play' );
		var wave   = wrap.querySelector( '.vlt-ap-wave' );
		var curEl  = wrap.querySelector( '.vlt-ap-cur' );
		var durEl  = wrap.querySelector( '.vlt-ap-dur' );
		var spdBtn = wrap.querySelector( '.vlt-ap-speed' );
		var dlBtn  = wrap.querySelector( '.vlt-ap-dl' );

		if ( ! wave ) return;

		var bars    = [];
		var cur     = 0;
		var dur     = 0;
		var playing = false;
		var raf     = null;
		var last    = 0;
		var rate    = 1;
		var sIdx    = 0;

		var hasReal = au && au.getAttribute( 'src' ) && au.getAttribute( 'src' ) !== '';

		// Detect if waveform is visually RTL (bars render right-to-left)
		var isRtl = window.getComputedStyle( wave ).direction === 'rtl';

		// Build waveform bars
		for ( var i = 0; i < BARS; i++ ) {
			var b = document.createElement( 'span' );
			b.style.height = ( 22 + Math.abs( Math.sin( i * 0.65 ) ) * 52 + ( i % 3 ) * 9 ) + '%';
			wave.appendChild( b );
			bars.push( b );
		}

		function paint() {
			var k = Math.round( ( dur ? cur / dur : 0 ) * BARS );
			for ( var j = 0; j < bars.length; j++ ) {
				// In RTL the DOM order is reversed visually, so paint from the end
				var idx = isRtl ? ( bars.length - 1 - j ) : j;
				if ( idx < k ) {
					bars[ j ].classList.add( 'vlt-ap-on' );
				} else {
					bars[ j ].classList.remove( 'vlt-ap-on' );
				}
			}
			if ( curEl ) curEl.textContent = formatTime( cur );
			if ( durEl ) durEl.textContent = formatTime( dur );
		}

		function tick( t ) {
			if ( ! playing ) return;
			if ( ! last ) last = t;
			cur += ( ( t - last ) / 1000 ) * rate;
			last = t;
			if ( cur >= dur ) {
				cur = dur;
				stop();
				paint();
				return;
			}
			paint();
			raf = requestAnimationFrame( tick );
		}

		function play() {
			playing = true;
			wrap.classList.add( 'playing' );
			last = 0;
			if ( hasReal ) {
				au.playbackRate = rate;
				au.play().catch( function () {} );
			} else {
				raf = requestAnimationFrame( tick );
			}
		}

		function stop() {
			playing = false;
			wrap.classList.remove( 'playing' );
			if ( hasReal ) au.pause();
			if ( raf ) cancelAnimationFrame( raf );
		}

		// Play / Pause
		btn.addEventListener( 'click', function () {
			playing ? stop() : play();
		} );

		btn.addEventListener( 'keydown', function ( e ) {
			if ( e.key === ' ' || e.key === 'Enter' ) {
				e.preventDefault();
				playing ? stop() : play();
			}
		} );

		// Seek via waveform click — direction-aware
		wave.addEventListener( 'click', function ( e ) {
			var r = wave.getBoundingClientRect();
			var pct;
			if ( isRtl ) {
				// RTL: right edge = 0%, left edge = 100%
				pct = ( r.right - e.clientX ) / r.width;
			} else {
				// LTR: left edge = 0%, right edge = 100%
				pct = ( e.clientX - r.left ) / r.width;
			}
			pct = Math.min( Math.max( pct, 0 ), 1 );
			cur = pct * dur;
			if ( hasReal ) au.currentTime = cur;
			paint();
		} );

		// Speed toggle
		if ( spdBtn ) {
			spdBtn.addEventListener( 'click', function () {
				sIdx = ( sIdx + 1 ) % speeds.length;
				rate = speeds[ sIdx ];
				spdBtn.textContent = fa( String( rate ) ) + '\u00D7';
				if ( hasReal ) au.playbackRate = rate;
			} );
		}

		// Download button
		if ( dlBtn && hasReal ) {
			dlBtn.addEventListener( 'click', function () {
				var a  = document.createElement( 'a' );
				a.href = au.getAttribute( 'src' );
				a.download = '';
				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );
			} );
		}

		// Real audio events
		if ( hasReal ) {
			au.addEventListener( 'loadedmetadata', function () {
				dur = au.duration || 0;
				paint();
			} );
			au.addEventListener( 'timeupdate', function () {
				cur = au.currentTime || 0;
				paint();
			} );
			au.addEventListener( 'ended', stop );
		}

		paint();
	}

	// Boot on DOMContentLoaded
	document.addEventListener( 'DOMContentLoaded', function () {
		var players = document.querySelectorAll( '.vlt-ap' );
		for ( var i = 0; i < players.length; i++ ) {
			initPlayer( players[ i ] );
		}
	} );
} )();
