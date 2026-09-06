/* Video Lead Tracker — Phase 1 page controllers (Funnel / Heatmap / Analytics detail) */
( function ( window, document ) {
	'use strict';

	function i18n( key, fallback ) {
		var map = ( window.vltAdminData && window.vltAdminData.i18n ) || {};
		return map[ key ] || fallback || key;
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

	function emptyState( icon, title, message ) {
		return '<div class="vlt-empty-state">' +
			'<span class="dashicons dashicons-' + escAttr( icon ) + '" aria-hidden="true"></span>' +
			'<strong class="vlt-empty-state-title">' + escHtml( title ) + '</strong>' +
			'<p class="vlt-empty-state-msg">' + escHtml( message ) + '</p>' +
			'</div>';
	}

	function setLoading( el, on ) {
		if ( ! el ) return;
		el.classList.toggle( 'is-loading', !! on );
	}

	/* ---- Funnel ---- */

	function initFunnelPage() {
		var root = document.getElementById( 'vlt-funnel-root' );
		if ( ! root || ! window.vltApi ) return;
		var abort = null;

		document.addEventListener( 'vlt:video-id-filter', function ( e ) {
			var app = document.querySelector( '.vlt-app[data-page="vlt-funnel"]' );
			if ( ! app ) return;
			var id = ( e.detail && e.detail.videoId ) || 0;
			refresh( id );
		} );

		function refresh( videoId ) {
			if ( abort ) abort.abort();
			abort = window.AbortController ? new AbortController() : null;
			setLoading( root, true );
			window.vltApi.get( 'admin/funnel', { video_id: videoId || undefined }, abort ? { signal: abort.signal } : {} )
				.then( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) throw new Error( 'bad' );
					renderFunnel( res.data );
				} )
				.catch( function ( err ) {
					if ( err && err.name === 'AbortError' ) return;
					if ( window.vltUi ) window.vltUi.toast( i18n( 'loadError', 'Could not refresh data.' ), 'error' );
					setLoading( root, false );
				} );
		}

		function renderFunnel( data ) {
			root.setAttribute( 'data-video-id', data.video_id || 0 );
			if ( ! data.video_id ) {
				root.innerHTML = emptyState( 'filter', i18n( 'selectVideo', 'Select a video' ), i18n( 'funnelHint', 'Choose a video to view its conversion funnel.' ) );
				setLoading( root, false );
				return;
			}
			if ( ! data.video ) {
				root.innerHTML = emptyState( 'warning', i18n( 'notFound', 'Video not found' ), i18n( 'missingVideo', 'The selected video is missing or inactive.' ) );
				setLoading( root, false );
				return;
			}
			var stepsHtml = ( data.steps || [] ).map( function ( step ) {
				var inactive = ! step.active;
				var isNa = step.count === null;
				var countHtml;
				if ( inactive || isNa ) {
					countHtml = '<span class="vlt-muted">' + escHtml( i18n( 'na', 'N/A' ) ) + '</span>';
				} else {
					countHtml = escHtml( step.count_label ) +
						' <span class="vlt-muted">(' + escHtml( step.pct_label ) + '%)</span>';
					if ( step.step_conversion ) {
						countHtml += ' <span class="vlt-funnel-conv">' + escHtml( step.step_conversion ) + '</span>';
					}
				}
				return '<div class="vlt-funnel-step' + ( inactive ? ' is-inactive' : '' ) + '">' +
					'<span class="vlt-funnel-label">' + escHtml( step.label ) +
					( inactive ? ' <span class="vlt-muted">' + escHtml( i18n( 'otpOff', '(OTP off)' ) ) + '</span>' : '' ) +
					'</span>' +
					'<div class="vlt-funnel-bar-wrap"><div class="vlt-funnel-bar" style="--vlt-bar:' + escAttr( step.bar_pct || 0 ) + '%"></div></div>' +
					'<span class="vlt-funnel-count">' + countHtml + '</span>' +
					'</div>';
			} ).join( '' );

			root.innerHTML =
				'<div class="vlt-panel">' +
				'<div class="vlt-panel-head"><h2 class="vlt-panel-title" id="vlt-funnel-title">' + escHtml( data.video.title ) + '</h2></div>' +
				'<div class="vlt-panel-body"><div class="vlt-funnel" id="vlt-funnel-steps">' + stepsHtml + '</div></div>' +
				'</div>';
			setLoading( root, false );
		}
	}

	/* ---- Heatmap ---- */

	function initHeatmapPage() {
		var root = document.getElementById( 'vlt-heatmap-root' );
		if ( ! root || ! window.vltApi ) return;
		var abort = null;

		if ( window.vltHeatmap ) window.vltHeatmap.mount();

		document.addEventListener( 'vlt:video-id-filter', function ( e ) {
			var app = document.querySelector( '.vlt-app[data-page="vlt-heatmap"]' );
			if ( ! app ) return;
			refresh( ( e.detail && e.detail.videoId ) || 0 );
		} );

		function refresh( videoId ) {
			if ( abort ) abort.abort();
			abort = window.AbortController ? new AbortController() : null;
			setLoading( root, true );
			window.vltApi.get( 'admin/heatmap', { video_id: videoId || undefined }, abort ? { signal: abort.signal } : {} )
				.then( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) throw new Error( 'bad' );
					renderHeatmap( res.data );
				} )
				.catch( function ( err ) {
					if ( err && err.name === 'AbortError' ) return;
					if ( window.vltUi ) window.vltUi.toast( i18n( 'loadError', 'Could not refresh data.' ), 'error' );
					setLoading( root, false );
				} );
		}

		function renderHeatmap( data ) {
			root.setAttribute( 'data-video-id', data.video_id || 0 );
			if ( ! data.video_id ) {
				root.innerHTML = emptyState( 'chart-line', i18n( 'selectVideo', 'Select a video' ), i18n( 'heatmapHint', 'Choose a video to explore watch heatmap and drop-off.' ) );
				setLoading( root, false );
				return;
			}
			if ( ! data.buckets || ! data.buckets.length ) {
				root.innerHTML = emptyState( 'chart-area', i18n( 'noHeatmap', 'No heatmap data yet' ), i18n( 'noHeatmapHint', 'Data appears after viewers start watching this video.' ) );
				setLoading( root, false );
				return;
			}

			var exportBtn = data.export_url
				? '<a class="vlt-btn vlt-btn--secondary" href="' + escAttr( data.export_url ) + '" id="vlt-hm-export">' + escHtml( i18n( 'exportCsv', 'Export CSV' ) ) + '</a>'
				: '';

			root.innerHTML =
				'<div class="vlt-panel">' +
				'<div class="vlt-panel-head vlt-hm-toolbar">' +
				'<span class="vlt-hm-label">' + escHtml( i18n( 'metric', 'Metric:' ) ) + '</span>' +
				'<button type="button" class="vlt-btn vlt-btn--primary vlt-hm-metric" data-metric="total">' + escHtml( i18n( 'totalViews', 'Total Views' ) ) + '</button>' +
				'<button type="button" class="vlt-btn vlt-btn--ghost vlt-hm-metric" data-metric="unique_visitors">' + escHtml( i18n( 'uniqueVisitors', 'Unique Visitors' ) ) + '</button>' +
				'<button type="button" class="vlt-btn vlt-btn--ghost vlt-hm-metric" data-metric="unique_leads">' + escHtml( i18n( 'uniqueLeads', 'Unique Leads' ) ) + '</button>' +
				'<button type="button" class="vlt-btn vlt-btn--ghost vlt-hm-metric" data-metric="drop_off">' + escHtml( i18n( 'dropOff', 'Drop-off Curve' ) ) + '</button>' +
				exportBtn +
				'</div>' +
				'<div class="vlt-panel-body">' +
				'<div class="vlt-hm-wrap" id="vlt-hm-wrap"' +
				' data-buckets="' + escAttr( JSON.stringify( data.buckets ) ) + '"' +
				' data-max-total="' + escAttr( data.max_total ) + '"' +
				' data-max-unique="' + escAttr( data.max_unique ) + '"' +
				' data-max-drop-off="' + escAttr( data.max_drop_off ) + '"' +
				' data-bucket-size="' + escAttr( data.bucket_size ) + '">' +
				'<div class="vlt-hm-chart" id="vlt-hm-chart"></div>' +
				'<div class="vlt-hm-axis" id="vlt-hm-axis"></div>' +
				'</div>' +
				'<p class="vlt-muted vlt-hm-hint">' + escHtml( i18n( 'bucketHint', 'Bucket size: %d second(s). Hover over a point for details.' ).replace( '%d', data.bucket_size ) ) + '</p>' +
				'</div></div>';

			if ( window.vltHeatmap ) window.vltHeatmap.mount();
			setLoading( root, false );
		}
	}

	/* ---- Analytics detail ---- */

	function initAnalyticsDetailPage() {
		var root = document.getElementById( 'vlt-analytics-detail-root' );
		if ( ! root || ! window.vltApi ) return;
		var abort = null;

		document.addEventListener( 'vlt:video-id-filter', function ( e ) {
			var app = document.querySelector( '.vlt-app[data-page="vlt-video-analytics"]' );
			if ( ! app || ! root ) return;
			var id = ( e.detail && e.detail.videoId ) || 0;
			if ( ! id ) {
				window.location.href = ( window.vltAdminData && window.vltAdminData.adminUrl ? window.vltAdminData.adminUrl : 'admin.php' ) + '?page=vlt-video-analytics';
				return;
			}
			refresh( id );
		} );

		function refresh( videoId ) {
			if ( abort ) abort.abort();
			abort = window.AbortController ? new AbortController() : null;
			setLoading( root, true );
			window.vltApi.get( 'admin/videos-analytics/' + videoId, null, abort ? { signal: abort.signal } : {} )
				.then( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) throw new Error( 'bad' );
					renderDetail( res.data );
				} )
				.catch( function ( err ) {
					if ( err && err.name === 'AbortError' ) return;
					if ( window.vltUi ) window.vltUi.toast( i18n( 'loadError', 'Could not refresh data.' ), 'error' );
					setLoading( root, false );
				} );
		}

		function renderDetail( data ) {
			root.setAttribute( 'data-video-id', data.video.id );
			var titleEl = document.querySelector( '.vlt-app[data-page="vlt-video-analytics"] .vlt-page-title' );
			var subEl = document.querySelector( '.vlt-app[data-page="vlt-video-analytics"] .vlt-page-subtitle' );
			if ( titleEl ) titleEl.textContent = data.video.title;
			if ( subEl ) {
				subEl.textContent = data.video.duration
					? i18n( 'duration', 'Duration %s' ).replace( '%s', data.video.duration )
					: '';
			}
			var sum = document.getElementById( 'vlt-analytics-export-summary' );
			var ranges = document.getElementById( 'vlt-analytics-export-ranges' );
			if ( sum && data.export ) sum.href = data.export.summary;
			if ( ranges && data.export ) ranges.href = data.export.ranges;

			var kpis = ( data.kpis || [] ).map( function ( card ) {
				return '<div class="vlt-kpi-card" data-kpi-key="' + escAttr( card.key ) + '">' +
					'<span class="vlt-kpi-icon dashicons ' + escAttr( card.icon ) + ' vlt-kpi-icon--' + escAttr( card.key ) + '" aria-hidden="true"></span>' +
					'<strong class="vlt-kpi-number">' + escHtml( card.value ) + '</strong>' +
					'<span class="vlt-kpi-label">' + escHtml( card.label ) + '</span></div>';
			} ).join( '' );

			var dist = ( data.distribution || [] ).map( function ( bucket, idx ) {
				return '<div class="vlt-dist-row">' +
					'<span class="vlt-dist-label">' + escHtml( bucket.label ) + '</span>' +
					'<div class="vlt-dist-bar-wrap"><div class="vlt-dist-bar vlt-dist-bar--' + idx + '" style="--vlt-bar:' + escAttr( bucket.pct ) + '%"></div></div>' +
					'<span class="vlt-dist-count">' + escHtml( bucket.count_label ) + '</span></div>';
			} ).join( '' );

			var rows = ( data.top_viewers || [] );
			var viewersHtml;
			if ( ! rows.length ) {
				viewersHtml = '<tr><td colspan="6" class="vlt-empty">' + escHtml( i18n( 'noViewers', 'No viewers yet.' ) ) + '</td></tr>';
			} else {
				viewersHtml = rows.map( function ( v ) {
					var viewerCell = v.lead_url
						? '<a class="vlt-link" href="' + escAttr( v.lead_url ) + '">' + escHtml( v.name ) + '</a><br><code class="vlt-mono">' + escHtml( v.mobile ) + '</code>'
						: '<span class="vlt-muted">' + escHtml( v.name ) + '</span><br><code class="vlt-mono vlt-mono--sm">' + escHtml( v.mobile ) + '</code>';
					var badge = v.completed
						? '<span class="vlt-badge vlt-badge--success">' + escHtml( i18n( 'yes', 'Yes' ) ) + '</span>'
						: '<span class="vlt-badge">' + escHtml( i18n( 'no', 'No' ) ) + '</span>';
					return '<tr><td>' + viewerCell + '</td>' +
						'<td><div class="vlt-progress"><div class="vlt-progress-track"><div class="vlt-progress-bar" style="--vlt-bar:' + escAttr( v.watch_pct_int ) + '%"></div></div><span>' + escHtml( v.watch_pct ) + '</span></div></td>' +
						'<td>' + escHtml( v.unique_watch ) + '</td>' +
						'<td>' + escHtml( v.sessions ) + '</td>' +
						'<td>' + badge + '</td>' +
						'<td class="vlt-muted">' + escHtml( v.first_play ) + '</td></tr>';
				} ).join( '' );
			}

			root.innerHTML =
				'<div class="vlt-kpi-row">' + kpis + '</div>' +
				'<section class="vlt-panel vlt-panel--spaced"><div class="vlt-panel-head"><h2 class="vlt-panel-title">' + escHtml( i18n( 'distribution', 'Watch Distribution' ) ) + '</h2></div>' +
				'<div class="vlt-panel-body"><div class="vlt-distribution" id="vlt-analytics-distribution">' + dist + '</div></div></section>' +
				'<section class="vlt-panel vlt-panel--spaced"><div class="vlt-panel-head"><h2 class="vlt-panel-title">' + escHtml( i18n( 'topViewers', 'Top Viewers' ) ) + '</h2></div>' +
				'<div class="vlt-panel-body"><table class="vlt-data-table" id="vlt-analytics-viewers"><thead><tr>' +
				'<th>' + escHtml( i18n( 'viewer', 'Viewer' ) ) + '</th>' +
				'<th>' + escHtml( i18n( 'watchPct', 'Watch %' ) ) + '</th>' +
				'<th>' + escHtml( i18n( 'uniqueWatch', 'Unique Watch' ) ) + '</th>' +
				'<th>' + escHtml( i18n( 'sessions', 'Sessions' ) ) + '</th>' +
				'<th>' + escHtml( i18n( 'completed', 'Completed' ) ) + '</th>' +
				'<th>' + escHtml( i18n( 'firstPlay', 'First Play' ) ) + '</th>' +
				'</tr></thead><tbody>' + viewersHtml + '</tbody></table></div></section>';

			setLoading( root, false );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initFunnelPage();
		initHeatmapPage();
		initAnalyticsDetailPage();
	} );
} )( window, document );
