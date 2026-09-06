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

	/* ---- Leads list AJAX ---- */

	function initLeadsPage() {
		var root = document.getElementById( 'vlt-leads-root' );
		if ( ! root || ! window.vltApi ) return;
		var abort = null;
		var state = {
			s: '',
			orderby: root.getAttribute( 'data-orderby' ) || 'first_seen_at',
			order: root.getAttribute( 'data-order' ) || 'DESC',
			paged: parseInt( root.getAttribute( 'data-paged' ), 10 ) || 1,
			video: root.getAttribute( 'data-video' ) || '',
		};
		var searchInput = document.getElementById( 'vlt-leads-search-input' );
		if ( searchInput ) state.s = searchInput.value || '';

		var debounceTimer = null;
		var form = document.getElementById( 'vlt-leads-search' );
		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				state.s = searchInput ? searchInput.value.trim() : '';
				state.paged = 1;
				refresh();
			} );
		}
		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				clearTimeout( debounceTimer );
				debounceTimer = setTimeout( function () {
					state.s = searchInput.value.trim();
					state.paged = 1;
					refresh();
				}, 300 );
			} );
		}
		var clearBtn = document.getElementById( 'vlt-leads-search-clear' );
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				if ( searchInput ) searchInput.value = '';
				state.s = '';
				state.paged = 1;
				refresh();
			} );
		}

		document.querySelectorAll( '#vlt-leads-table th[data-sort]' ).forEach( function ( th ) {
			th.style.cursor = 'pointer';
			function applySort( col ) {
				if ( state.orderby === col ) {
					state.order = state.order === 'ASC' ? 'DESC' : 'ASC';
				} else {
					state.orderby = col;
					state.order = 'ASC';
				}
				state.paged = 1;
				updateSortIndicators();
				refresh();
			}
			th.addEventListener( 'click', function () {
				applySort( th.getAttribute( 'data-sort' ) );
			} );
			th.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					applySort( th.getAttribute( 'data-sort' ) );
				}
			} );
		} );

		function updateSortIndicators() {
			document.querySelectorAll( '#vlt-leads-table th[data-sort]' ).forEach( function ( th ) {
				var col = th.getAttribute( 'data-sort' );
				th.classList.remove( 'is-sorted-asc', 'is-sorted-desc' );
				th.removeAttribute( 'aria-sort' );
				if ( col === state.orderby ) {
					th.classList.add( state.order === 'ASC' ? 'is-sorted-asc' : 'is-sorted-desc' );
					th.setAttribute( 'aria-sort', state.order === 'ASC' ? 'ascending' : 'descending' );
				}
			} );
		}
		updateSortIndicators();

		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '#vlt-leads-pagination [data-page]' );
			if ( ! btn || btn.disabled ) return;
			var dir = btn.getAttribute( 'data-page' );
			var pag = document.getElementById( 'vlt-leads-pagination' );
			var total = pag ? parseInt( pag.getAttribute( 'data-total-pages' ), 10 ) : 1;
			if ( dir === 'prev' ) state.paged = Math.max( 1, state.paged - 1 );
			if ( dir === 'next' ) state.paged = Math.min( total, state.paged + 1 );
			refresh();
		} );

		document.addEventListener( 'vlt:video-filter', function ( e ) {
			var app = document.querySelector( '.vlt-app[data-page="vlt-leads"]' );
			if ( ! app ) return;
			state.video = ( e.detail && e.detail.video ) || '';
			state.paged = 1;
			refresh();
		} );

		function syncUrl() {
			try {
				var url = new URL( window.location.href );
				[ 's', 'orderby', 'order', 'paged', 'video' ].forEach( function ( k ) {
					var v = state[ k ];
					if ( ! v || ( k === 'orderby' && v === 'first_seen_at' ) || ( k === 'order' && v === 'DESC' ) || ( k === 'paged' && Number( v ) === 1 ) ) {
						url.searchParams.delete( k );
					} else {
						url.searchParams.set( k, v );
					}
				} );
				window.history.replaceState( {}, '', url.toString() );
			} catch ( err ) { /* ignore */ }
		}

		function refresh() {
			if ( abort ) abort.abort();
			abort = window.AbortController ? new AbortController() : null;
			root.classList.add( 'is-loading' );
			syncUrl();
			window.vltApi.get( 'admin/leads', {
				s: state.s || undefined,
				orderby: state.orderby,
				order: state.order,
				paged: state.paged,
				video: state.video || undefined,
			}, abort ? { signal: abort.signal } : {} )
				.then( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) throw new Error( 'bad' );
					render( res.data );
				} )
				.catch( function ( err ) {
					if ( err && err.name === 'AbortError' ) return;
					if ( window.vltUi ) window.vltUi.toast( i18n( 'loadError', 'Could not refresh data.' ), 'error' );
					root.classList.remove( 'is-loading' );
				} );
		}

		function render( data ) {
			state.paged = data.paged;
			state.orderby = data.orderby;
			state.order = data.order;
			root.setAttribute( 'data-video', data.video || '' );
			updateSortIndicators();
			var count = document.getElementById( 'vlt-leads-count' );
			if ( count ) {
				count.textContent = i18n( 'showingRange', 'Showing %1$d to %2$d of %3$d' )
					.replace( '%1$d', data.from ).replace( '%2$d', data.to ).replace( '%3$d', data.total );
			}
			var labels = [];
			document.querySelectorAll( '#vlt-leads-table thead th' ).forEach( function ( th ) {
				labels.push( th.textContent.trim() );
			} );
			var tbody = document.getElementById( 'vlt-leads-tbody' );
			if ( ! tbody ) return;
			if ( ! data.rows.length ) {
				tbody.innerHTML = '<tr><td colspan="8" class="vlt-empty">' + escHtml( i18n( 'noLeadsFound', 'No leads found.' ) ) + '</td></tr>';
			} else {
				tbody.innerHTML = data.rows.map( function ( row ) {
					var badge = row.verified
						? '<span class="vlt-badge vlt-badge--success">' + escHtml( i18n( 'yes', 'Yes' ) ) + '</span>'
						: '<span class="vlt-badge">' + escHtml( i18n( 'no', 'No' ) ) + '</span>';
					return '<tr>' +
						'<td class="vlt-muted" data-label="' + escAttr( labels[0] || '#' ) + '">' + escHtml( row.id ) + '</td>' +
						'<td data-label="' + escAttr( labels[1] || 'Name' ) + '"><a class="vlt-link" href="' + escAttr( row.url ) + '">' + escHtml( row.name ) + '</a></td>' +
						'<td data-label="' + escAttr( labels[2] || 'Mobile' ) + '"><code class="vlt-mono">' + escHtml( row.mobile ) + '</code></td>' +
						'<td data-label="' + escAttr( labels[3] || 'Verified' ) + '">' + badge + '</td>' +
						'<td data-label="' + escAttr( labels[4] || 'Videos' ) + '">' + escHtml( row.videos_count ) + '</td>' +
						'<td data-label="' + escAttr( labels[5] || 'Avg Watch' ) + '"><div class="vlt-progress"><div class="vlt-progress-track"><div class="vlt-progress-bar" style="--vlt-bar:' + escAttr( row.avg_watch_int ) + '%"></div></div><span>' + escHtml( row.avg_watch ) + '</span></div></td>' +
						'<td data-label="' + escAttr( labels[6] || 'Sessions' ) + '">' + escHtml( row.sessions ) + '</td>' +
						'<td class="vlt-muted" data-label="' + escAttr( labels[7] || 'First Seen' ) + '">' + escHtml( row.first_seen ) + '</td></tr>';
				} ).join( '' );
			}
			var pag = document.getElementById( 'vlt-leads-pagination' );
			if ( pag ) {
				pag.setAttribute( 'data-total-pages', data.total_pages );
				pag.setAttribute( 'data-paged', data.paged );
				var label = pag.querySelector( '.vlt-muted' );
				if ( label ) label.textContent = data.paged + ' / ' + data.total_pages;
				var prev = pag.querySelector( '[data-page="prev"]' );
				var next = pag.querySelector( '[data-page="next"]' );
				if ( prev ) prev.disabled = data.paged <= 1;
				if ( next ) next.disabled = data.paged >= data.total_pages;
				pag.style.display = data.total_pages > 1 ? '' : 'none';
			}
			root.classList.remove( 'is-loading' );
		}
	}

	/* ---- Logs AJAX ---- */

	function initLogsPage() {
		var root = document.getElementById( 'vlt-logs-root' );
		if ( ! root || ! window.vltApi ) return;
		var abort = null;

		document.querySelectorAll( '.vlt-log-level' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				refresh( btn.getAttribute( 'data-level' ) || '' );
			} );
		} );

		function refresh( level ) {
			if ( abort ) abort.abort();
			abort = window.AbortController ? new AbortController() : null;
			root.classList.add( 'is-loading' );
			if ( window.vltUi ) window.vltUi.syncQueryParam( 'vlt_level', level || null );
			window.vltApi.get( 'admin/logs', { level: level || undefined }, abort ? { signal: abort.signal } : {} )
				.then( function ( res ) {
					if ( ! res || ! res.success || ! res.data ) throw new Error( 'bad' );
					render( res.data );
				} )
				.catch( function ( err ) {
					if ( err && err.name === 'AbortError' ) return;
					if ( window.vltUi ) window.vltUi.toast( i18n( 'loadError', 'Could not refresh data.' ), 'error' );
					root.classList.remove( 'is-loading' );
				} );
		}

		function render( data ) {
			root.setAttribute( 'data-level', data.level || '' );
			document.querySelectorAll( '.vlt-log-level' ).forEach( function ( btn ) {
				var on = ( btn.getAttribute( 'data-level' ) || '' ) === ( data.level || '' );
				btn.classList.toggle( 'vlt-btn--primary', on );
				btn.classList.toggle( 'vlt-btn--ghost', ! on );
			} );
			var count = document.getElementById( 'vlt-logs-count' );
			if ( count ) {
				count.textContent = i18n( 'logsCount', 'Showing last %1$d of %2$d entries' )
					.replace( '%1$d', data.shown ).replace( '%2$d', data.total );
			}
			var labels = [];
			document.querySelectorAll( '#vlt-logs-table thead th' ).forEach( function ( th ) {
				labels.push( th.textContent.trim() );
			} );
			var tbody = document.getElementById( 'vlt-logs-tbody' );
			if ( ! tbody ) return;
			if ( ! data.rows.length ) {
				tbody.innerHTML = '<tr><td colspan="5" class="vlt-empty">' + escHtml( i18n( 'noLogs', 'No log entries.' ) ) + '</td></tr>';
			} else {
				tbody.innerHTML = data.rows.map( function ( row ) {
					var meta = row.metadata
						? '<details class="vlt-log-meta"><summary>metadata</summary><pre>' + escHtml( row.metadata ) + '</pre></details>'
						: '';
					return '<tr>' +
						'<td class="vlt-muted" data-label="' + escAttr( labels[0] || 'ID' ) + '">' + escHtml( row.id ) + '</td>' +
						'<td data-label="' + escAttr( labels[1] || 'Level' ) + '"><span class="vlt-badge vlt-badge--' + escAttr( row.level ) + '">' + escHtml( String( row.level ).toUpperCase() ) + '</span></td>' +
						'<td class="vlt-muted" data-label="' + escAttr( labels[2] || 'Context' ) + '">' + escHtml( row.context ) + '</td>' +
						'<td data-label="' + escAttr( labels[3] || 'Message' ) + '">' + escHtml( row.message ) + meta + '</td>' +
						'<td class="vlt-muted" data-label="' + escAttr( labels[4] || 'Time' ) + '">' + escHtml( row.time ) + '</td></tr>';
				} ).join( '' );
			}
			root.classList.remove( 'is-loading' );
		}
	}

	/* ---- Videos copy + delete modal ---- */

	function initVideosPage() {
		document.querySelectorAll( '.vlt-copy-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var text = btn.getAttribute( 'data-copy' ) || '';
				if ( ! text ) return;
				var done = function () {
					if ( window.vltUi ) window.vltUi.toast( i18n( 'copied', 'Copied to clipboard.' ), 'success' );
				};
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text ).then( done ).catch( function () {
						window.prompt( 'Copy:', text );
					} );
				} else {
					window.prompt( 'Copy:', text );
				}
			} );
		} );

		document.querySelectorAll( '.vlt-video-delete-form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				if ( form.getAttribute( 'data-confirmed' ) === '1' ) return;
				e.preventDefault();
				var btn = form.querySelector( '.vlt-video-delete-btn' );
				if ( ! window.vltUi || ! window.vltUi.confirm ) {
					if ( window.confirm( btn ? btn.getAttribute( 'data-confirm-body' ) : 'Delete?' ) ) {
						form.setAttribute( 'data-confirmed', '1' );
						form.submit();
					}
					return;
				}
				window.vltUi.confirm( {
					title: ( btn && btn.getAttribute( 'data-confirm-title' ) ) || 'Delete?',
					body: ( btn && btn.getAttribute( 'data-confirm-body' ) ) || '',
					danger: true,
				} ).then( function ( ok ) {
					if ( ! ok ) return;
					form.setAttribute( 'data-confirmed', '1' );
					form.submit();
				} );
			} );
		} );

		var notice = document.querySelector( '[data-vlt-toast]' );
		if ( notice && window.vltUi ) {
			window.vltUi.toast( notice.textContent.trim(), 'success' );
			notice.remove();
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initFunnelPage();
		initHeatmapPage();
		initAnalyticsDetailPage();
		initLeadsPage();
		initLogsPage();
		initVideosPage();
	} );
} )( window, document );
