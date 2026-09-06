<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Admin {

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu',             [ self::class, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts',  [ self::class, 'enqueue_assets' ] );
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'vlt-' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'vlt-admin',
			VLT_PLUGIN_URL . 'assets/css/vlt-admin.css',
			[],
			VLT_VERSION
		);
		wp_enqueue_script(
			'vlt-admin-api',
			VLT_PLUGIN_URL . 'assets/js/vlt-admin-api.js',
			[],
			VLT_VERSION,
			true
		);
		wp_enqueue_script(
			'vlt-admin-ui',
			VLT_PLUGIN_URL . 'assets/js/vlt-admin-ui.js',
			[ 'vlt-admin-api' ],
			VLT_VERSION,
			true
		);
		wp_enqueue_script(
			'vlt-admin',
			VLT_PLUGIN_URL . 'assets/js/vlt-admin.js',
			[ 'vlt-admin-ui' ],
			VLT_VERSION,
			true
		);
		wp_enqueue_script(
			'vlt-admin-pages',
			VLT_PLUGIN_URL . 'assets/js/vlt-admin-pages.js',
			[ 'vlt-admin' ],
			VLT_VERSION,
			true
		);
		wp_localize_script( 'vlt-admin-api', 'vltAdminData', [
			'restBase' => esc_url_raw( rest_url( 'vlt/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'adminUrl' => esc_url_raw( admin_url( 'admin.php' ) ),
			'i18n'     => [
				'yes'            => __( 'Yes', 'video-lead-tracker' ),
				'no'             => __( 'No', 'video-lead-tracker' ),
				'na'             => __( 'N/A', 'video-lead-tracker' ),
				'noLeads'        => __( 'No leads yet.', 'video-lead-tracker' ),
				'noVideos'       => __( 'No videos yet.', 'video-lead-tracker' ),
				'noViewers'      => __( 'No viewers yet.', 'video-lead-tracker' ),
				'loadError'      => __( 'Could not refresh data.', 'video-lead-tracker' ),
				'customize'      => __( 'Customize Widgets', 'video-lead-tracker' ),
				'save'           => __( 'Done', 'video-lead-tracker' ),
				'clear'          => __( 'Clear', 'video-lead-tracker' ),
				'selectVideo'    => __( 'Select a video', 'video-lead-tracker' ),
				'funnelHint'     => __( 'Choose a video to view its conversion funnel.', 'video-lead-tracker' ),
				'heatmapHint'    => __( 'Choose a video to explore watch heatmap and drop-off.', 'video-lead-tracker' ),
				'noHeatmap'      => __( 'No heatmap data yet', 'video-lead-tracker' ),
				'noHeatmapHint'  => __( 'Data appears after viewers start watching this video.', 'video-lead-tracker' ),
				'notFound'       => __( 'Video not found', 'video-lead-tracker' ),
				'missingVideo'   => __( 'The selected video is missing or inactive.', 'video-lead-tracker' ),
				'otpOff'         => __( '(OTP off)', 'video-lead-tracker' ),
				'exportCsv'      => __( 'Export CSV', 'video-lead-tracker' ),
				'metric'         => __( 'Metric:', 'video-lead-tracker' ),
				'totalViews'     => __( 'Total Views', 'video-lead-tracker' ),
				'uniqueVisitors' => __( 'Unique Visitors', 'video-lead-tracker' ),
				'uniqueLeads'    => __( 'Unique Leads', 'video-lead-tracker' ),
				'dropOff'        => __( 'Drop-off Curve', 'video-lead-tracker' ),
				'bucketHint'     => __( 'Bucket size: %d second(s). Hover over a point for details.', 'video-lead-tracker' ),
				'duration'       => __( 'Duration %s', 'video-lead-tracker' ),
				'distribution'   => __( 'Watch Distribution', 'video-lead-tracker' ),
				'topViewers'     => __( 'Top Viewers', 'video-lead-tracker' ),
				'viewer'         => __( 'Viewer', 'video-lead-tracker' ),
				'watchPct'       => __( 'Watch %', 'video-lead-tracker' ),
				'uniqueWatch'    => __( 'Unique Watch', 'video-lead-tracker' ),
				'sessions'       => __( 'Sessions', 'video-lead-tracker' ),
				'completed'      => __( 'Completed', 'video-lead-tracker' ),
				'firstPlay'      => __( 'First Play', 'video-lead-tracker' ),
			],
		] );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Video Lead Tracker', 'video-lead-tracker' ),
			__( 'Video Lead Tracker', 'video-lead-tracker' ),
			'manage_options',
			'vlt-overview',
			VLT_Admin_UI::wrap_page( 'vlt-overview', [ self::class, 'render_overview' ] ),
			'dashicons-video-alt3',
			30
		);

		$submenus = [
			[ 'vlt-overview',        __( 'Overview',        'video-lead-tracker' ), [ self::class,        'render_overview' ] ],
			[ 'vlt-videos',          __( 'Videos',          'video-lead-tracker' ), [ 'VLT_Videos_Admin', 'render_page' ] ],
			[ 'vlt-leads',           __( 'Leads',           'video-lead-tracker' ), [ self::class,        'render_leads' ] ],
			[ 'vlt-video-analytics', __( 'Video Analytics', 'video-lead-tracker' ), [ self::class,        'render_video_analytics' ] ],
			[ 'vlt-funnel',          __( 'Funnel',          'video-lead-tracker' ), [ self::class,        'render_funnel' ] ],
			[ 'vlt-heatmap',         __( 'Heatmap',         'video-lead-tracker' ), [ self::class,        'render_heatmap' ] ],
			[ 'vlt-settings',        __( 'Settings',        'video-lead-tracker' ), [ 'VLT_Settings',     'render_page' ] ],
			[ 'vlt-logs',            __( 'Logs',            'video-lead-tracker' ), [ self::class,        'render_logs' ] ],
		];

		foreach ( $submenus as [ $slug, $label, $callback ] ) {
			add_submenu_page(
				'vlt-overview',
				$label,
				$label,
				'manage_options',
				$slug,
				VLT_Admin_UI::wrap_page( $slug, $callback )
			);
		}
	}

	// -------------------------------------------------------------------------
	// Overview dashboard
	// -------------------------------------------------------------------------

	/**
	 * Build overview payload (shared by SSR + REST).
	 *
	 * @param string $filter_video_key Video key or empty for all.
	 * @return array
	 */
	public static function get_overview_data( $filter_video_key = '' ) {
		global $wpdb;
		$p = $wpdb->prefix;

		$filter_video_key = sanitize_key( $filter_video_key );
		$filter_vid_id    = 0;
		if ( $filter_video_key ) {
			foreach ( VLT_DB::get_all_videos() as $v ) {
				if ( $v->video_key === $filter_video_key ) {
					$filter_vid_id = (int) $v->id;
					break;
				}
			}
			if ( ! $filter_vid_id ) {
				$filter_video_key = '';
			}
		}

		$cache_key = 'vlt_overview_cache_' . $filter_vid_id;
		$cache     = get_transient( $cache_key );

		if ( is_array( $cache ) ) {
			[ $total_leads, $verified_leads, $total_sessions, $active_videos, $watch_hours,
			  $recent_leads, $top_videos ] = $cache;
		} else {
			if ( $filter_vid_id ) {
				$total_leads    = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT( DISTINCT lead_id ) FROM {$p}vlt_video_user_summary WHERE video_id = %d AND lead_id IS NOT NULL",
					$filter_vid_id
				) );
				$verified_leads = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT( DISTINCT s.lead_id )
					 FROM {$p}vlt_video_user_summary s
					 JOIN {$p}vlt_leads l ON l.id = s.lead_id
					 WHERE s.video_id = %d AND l.is_verified = 1",
					$filter_vid_id
				) );
				$total_sessions = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COALESCE( SUM(sessions_count), 0 ) FROM {$p}vlt_video_user_summary WHERE video_id = %d",
					$filter_vid_id
				) );
				$active_videos  = 1;
				$watch_secs     = (float) $wpdb->get_var( $wpdb->prepare(
					"SELECT COALESCE( SUM(unique_watch_seconds), 0 ) FROM {$p}vlt_video_user_summary WHERE video_id = %d",
					$filter_vid_id
				) );
				$watch_hours    = number_format( $watch_secs / 3600, 1 );

				$recent_leads = $wpdb->get_results( $wpdb->prepare(
					"SELECT l.primary_name, l.normalized_mobile, l.is_verified, l.first_seen_at,
					        COALESCE( s.unique_watch_percent, 0 ) AS avg_watch
					 FROM {$p}vlt_video_user_summary s
					 JOIN {$p}vlt_leads l ON l.id = s.lead_id
					 WHERE s.video_id = %d
					 ORDER BY l.first_seen_at DESC
					 LIMIT 10",
					$filter_vid_id
				) );

				$top_videos = $wpdb->get_results( $wpdb->prepare(
					"SELECT v.title, v.video_key, v.duration_seconds,
					        COUNT( DISTINCT CASE WHEN s.lead_id IS NOT NULL
					            THEN CONCAT( 'l', s.lead_id )
					            ELSE s.visitor_uuid END )               AS viewers,
					        COALESCE( AVG(s.unique_watch_percent), 0 )  AS avg_completion,
					        COALESCE( SUM(s.unique_watch_seconds), 0 ) / 3600 AS watch_hours,
					        COALESCE( SUM(s.reached_end), 0 )           AS completions
					 FROM {$p}vlt_videos v
					 LEFT JOIN {$p}vlt_video_user_summary s ON s.video_id = v.id
					 WHERE v.id = %d
					 GROUP BY v.id",
					$filter_vid_id
				) );
			} else {
				$total_leads    = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_leads" );
				$verified_leads = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_leads WHERE is_verified = 1" );
				$total_sessions = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_sessions" );
				$active_videos  = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_videos WHERE is_active = 1" );
				$watch_secs     = (float) $wpdb->get_var( "SELECT COALESCE( SUM(unique_watch_seconds), 0 ) FROM {$p}vlt_video_user_summary" );
				$watch_hours    = number_format( $watch_secs / 3600, 1 );

				$recent_leads = $wpdb->get_results(
					"SELECT l.primary_name, l.normalized_mobile, l.is_verified, l.first_seen_at,
					        COALESCE( AVG(s.unique_watch_percent), 0 ) AS avg_watch
					 FROM {$p}vlt_leads l
					 LEFT JOIN {$p}vlt_video_user_summary s ON s.lead_id = l.id
					 GROUP BY l.id
					 ORDER BY l.first_seen_at DESC
					 LIMIT 10"
				);

				$top_videos = $wpdb->get_results(
					"SELECT v.title, v.video_key, v.duration_seconds,
					        COUNT( DISTINCT CASE WHEN s.lead_id IS NOT NULL
					            THEN CONCAT( 'l', s.lead_id )
					            ELSE s.visitor_uuid END )               AS viewers,
					        COALESCE( AVG(s.unique_watch_percent), 0 )  AS avg_completion,
					        COALESCE( SUM(s.unique_watch_seconds), 0 ) / 3600 AS watch_hours,
					        COALESCE( SUM(s.reached_end), 0 )           AS completions
					 FROM {$p}vlt_videos v
					 LEFT JOIN {$p}vlt_video_user_summary s ON s.video_id = v.id
					 WHERE v.is_active = 1
					 GROUP BY v.id
					 ORDER BY watch_hours DESC
					 LIMIT 8"
				);
			}

			set_transient( $cache_key,
				[ $total_leads, $verified_leads, $total_sessions, $active_videos, $watch_hours,
				  $recent_leads, $top_videos ],
				5 * MINUTE_IN_SECONDS
			);
		}

		$recent_rows = [];
		foreach ( (array) $recent_leads as $row ) {
			$recent_rows[] = [
				'name'      => $row->primary_name ?: '—',
				'mobile'    => self::mask_mobile( $row->normalized_mobile ),
				'verified'  => (bool) $row->is_verified,
				'avg_watch' => round( (float) $row->avg_watch, 1 ) . '%',
				'first_seen'=> wp_date( get_option( 'date_format' ), strtotime( $row->first_seen_at ) ),
			];
		}

		$top_rows = [];
		foreach ( (array) $top_videos as $video ) {
			$top_rows[] = [
				'title'          => $video->title ?: $video->video_key,
				'duration'       => $video->duration_seconds ? self::format_duration( (int) $video->duration_seconds ) : '',
				'viewers'        => number_format_i18n( (int) $video->viewers ),
				'completions'    => number_format_i18n( (int) $video->completions ),
				'avg_completion' => round( (float) $video->avg_completion, 1 ) . '%',
				'watch_hours'    => number_format( (float) $video->watch_hours, 2 ),
			];
		}

		return [
			'video_key'    => $filter_video_key,
			'kpis'         => [
				[
					'key'   => 'total_leads',
					'label' => __( 'Total Leads', 'video-lead-tracker' ),
					'value' => number_format_i18n( $total_leads ),
					'icon'  => 'dashicons-groups',
				],
				[
					'key'   => 'verified',
					'label' => __( 'Verified', 'video-lead-tracker' ),
					'value' => number_format_i18n( $verified_leads ),
					'icon'  => 'dashicons-yes-alt',
				],
				[
					'key'   => 'sessions',
					'label' => __( 'Sessions', 'video-lead-tracker' ),
					'value' => number_format_i18n( $total_sessions ),
					'icon'  => 'dashicons-clock',
				],
				[
					'key'   => 'videos',
					'label' => __( 'Videos', 'video-lead-tracker' ),
					'value' => number_format_i18n( $active_videos ),
					'icon'  => 'dashicons-video-alt3',
				],
				[
					'key'   => 'watch_hours',
					'label' => __( 'Watch Hours', 'video-lead-tracker' ),
					'value' => $watch_hours,
					'icon'  => 'dashicons-visibility',
				],
			],
			'recent_leads' => $recent_rows,
			'top_videos'   => $top_rows,
		];
	}

	public static function render_overview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$filter_video_key = sanitize_key( $_GET['video'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$data             = self::get_overview_data( $filter_video_key );

		ob_start();
		?>
		<button type="button" class="vlt-btn vlt-btn--ghost vlt-kpi-customize-btn">
			<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
			<?php esc_html_e( 'Customize Widgets', 'video-lead-tracker' ); ?>
		</button>
		<?php
		$actions = ob_get_clean();

		VLT_Admin_UI::open( [
			'page'              => 'vlt-overview',
			'title'             => __( 'Overview', 'video-lead-tracker' ),
			'subtitle'          => __( 'Campaign performance at a glance.', 'video-lead-tracker' ),
			'show_video_filter' => true,
			'ajax_video_filter' => true,
			'actions_html'      => $actions,
		] );

		VLT_Admin_UI::render( 'pages/overview', [ 'data' => $data ] );

		VLT_Admin_UI::close();
	}

	// -------------------------------------------------------------------------
	// Leads report + lead detail (Phase 16)
	// -------------------------------------------------------------------------

	public static function render_leads() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$lead_id = isset( $_GET['lead_id'] ) ? absint( $_GET['lead_id'] ) : 0;
		if ( $lead_id ) {
			self::render_lead_detail( $lead_id );
		} else {
			self::render_lead_list();
		}
	}

	private static function render_lead_list() {
		global $wpdb;
		$p = $wpdb->prefix;

		// ---- Sanitized input ----
		$search  = sanitize_text_field( $_GET['s']       ?? '' );
		$orderby = sanitize_key(        $_GET['orderby'] ?? 'first_seen_at' );
		$order   = strtoupper( sanitize_key( $_GET['order'] ?? 'DESC' ) );
		$paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

		$per_page = 20;
		$offset   = ( $paged - 1 ) * $per_page;

		$allowed_cols = [ 'id', 'primary_name', 'first_seen_at', 'last_seen_at', 'is_verified', 'avg_watch', 'videos_count', 'sessions_count' ];
		if ( ! in_array( $orderby, $allowed_cols, true ) ) {
			$orderby = 'first_seen_at';
		}
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'DESC';
		}

		// ---- Video filter ----
		$filter_video_key = sanitize_key( $_GET['video'] ?? '' );
		$filter_vid_id    = 0;
		if ( $filter_video_key ) {
			foreach ( VLT_DB::get_all_videos() as $v ) {
				if ( $v->video_key === $filter_video_key ) {
					$filter_vid_id = (int) $v->id;
					break;
				}
			}
		}

		if ( $filter_vid_id ) {
			$join_sql    = "JOIN {$p}vlt_video_user_summary s ON s.lead_id = l.id AND s.video_id = %d";
			$join_params = [ $filter_vid_id ];
		} else {
			$join_sql    = "LEFT JOIN {$p}vlt_video_user_summary s ON s.lead_id = l.id";
			$join_params = [];
		}

		// ---- WHERE clause ----
		$where        = '';
		$where_params = [];
		if ( $search !== '' ) {
			$like         = '%' . $wpdb->esc_like( $search ) . '%';
			$where        = 'WHERE ( l.primary_name LIKE %s OR l.normalized_mobile LIKE %s )';
			$where_params = [ $like, $like ];
		}

		// ---- Total count ----
		$all_params = array_merge( $join_params, $where_params );
		$count_sql  = "SELECT COUNT( DISTINCT l.id ) FROM {$p}vlt_leads l $join_sql $where";
		$total      = (int) ( $all_params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, $all_params ) )
			: $wpdb->get_var( $count_sql )
		);

		// ---- Main query ----
		$sql = "SELECT l.id, l.primary_name, l.normalized_mobile, l.is_verified,
		               l.first_seen_at, l.last_seen_at,
		               COUNT( DISTINCT s.video_id )               AS videos_count,
		               COALESCE( AVG(s.unique_watch_percent), 0 ) AS avg_watch,
		               ( SELECT COUNT(*) FROM {$p}vlt_sessions WHERE lead_id = l.id ) AS sessions_count
		        FROM {$p}vlt_leads l
		        $join_sql
		        $where
		        GROUP BY l.id
		        ORDER BY $orderby $order
		        LIMIT %d OFFSET %d";

		$leads = $wpdb->get_results(
			$wpdb->prepare( $sql, array_merge( $join_params, $where_params, [ $per_page, $offset ] ) )
		);

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$paged       = min( $paged, $total_pages );
		$offset      = ( $paged - 1 ) * $per_page;
		$base_url    = add_query_arg(
			array_filter( [ 'video' => $filter_video_key ?: null ] ),
			admin_url( 'admin.php?page=vlt-leads' )
		);
		$sort_base   = add_query_arg( array_filter( [
			's'       => $search ?: null,
			'orderby' => ( $orderby !== 'first_seen_at' ) ? $orderby : null,
			'order'   => ( $order  !== 'DESC'           ) ? $order   : null,
		] ), $base_url );

		$showing_from = min( $offset + 1, max( $total, 1 ) );
		$showing_to   = min( $offset + $per_page, $total );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Leads', 'video-lead-tracker' ); ?></h1>
			<a href="<?php echo esc_url( VLT_Exporter::export_url( 'leads' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Export CSV', 'video-lead-tracker' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php self::video_filter_select( 'vlt-leads' ); ?>

			<form method="get" class="vlt-search-form">
				<input type="hidden" name="page" value="vlt-leads">
				<?php if ( $filter_video_key ) : ?>
					<input type="hidden" name="video" value="<?php echo esc_attr( $filter_video_key ); ?>">
				<?php endif; ?>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
				       placeholder="<?php esc_attr_e( 'Search leads…', 'video-lead-tracker' ); ?>"
				       class="vlt-search-input">
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'video-lead-tracker' ); ?></button>
				<?php if ( $search ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button button-link"><?php esc_html_e( 'Clear', 'video-lead-tracker' ); ?></a>
				<?php endif; ?>
			</form>

			<div class="tablenav top">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: 1: first result number 2: last result number 3: total results */
							esc_html__( 'Showing %1$d to %2$d of %3$d', 'video-lead-tracker' ),
							$showing_from, $showing_to, $total
						);
						?>
					</span>
					<?php if ( $total_pages > 1 ) : ?>
						<?php echo paginate_links( [ // phpcs:ignore
							'base'      => add_query_arg( 'paged', '%#%', $sort_base ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						] ); ?>
					<?php endif; ?>
				</div>
			</div>

			<table class="widefat striped vlt-table">
				<thead>
					<tr>
						<th class="vlt-col-num"><?php echo self::sort_link( '#', 'id', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
						<th><?php echo self::sort_link( __( 'Name', 'video-lead-tracker' ), 'primary_name', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
						<th><?php esc_html_e( 'Mobile', 'video-lead-tracker' ); ?></th>
						<th><?php echo self::sort_link( __( 'Verified', 'video-lead-tracker' ), 'is_verified', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
						<th><?php echo self::sort_link( __( 'Videos Watched', 'video-lead-tracker' ), 'videos_count', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
						<th><?php echo self::sort_link( __( 'Avg Watch', 'video-lead-tracker' ), 'avg_watch', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
						<th><?php echo self::sort_link( __( 'Sessions', 'video-lead-tracker' ), 'sessions_count', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
						<th><?php echo self::sort_link( __( 'First Seen', 'video-lead-tracker' ), 'first_seen_at', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $leads ) : ?>
					<?php foreach ( $leads as $i => $row ) :
						$detail_url = add_query_arg( [ 'page' => 'vlt-leads', 'lead_id' => $row->id ], admin_url( 'admin.php' ) );
					?>
					<tr>
						<td class="vlt-muted vlt-col-num"><?php echo esc_html( $row->id ); ?></td>
						<td><a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $row->primary_name ?: '—' ); ?></a></td>
						<td><code class="vlt-mono"><?php echo esc_html( $row->normalized_mobile ); ?></code></td>
						<td>
							<?php if ( $row->is_verified ) : ?>
								<span class="vlt-badge vlt-badge--green"><?php esc_html_e( 'Yes', 'video-lead-tracker' ); ?></span>
							<?php else : ?>
								<span class="vlt-badge"><?php esc_html_e( 'No', 'video-lead-tracker' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->videos_count ) ); ?></td>
						<td>
							<div class="vlt-progress">
								<div class="vlt-progress-track">
									<div class="vlt-progress-bar" style="width:<?php echo esc_attr( min( 100, (int) round( $row->avg_watch ) ) ); ?>%"></div>
								</div>
								<span><?php echo esc_html( round( (float) $row->avg_watch, 1 ) . '%' ); ?></span>
							</div>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->sessions_count ) ); ?></td>
						<td class="vlt-muted"><?php echo esc_html( wp_date( 'Y-m-d', strtotime( $row->first_seen_at ) ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="8" class="vlt-empty"><?php esc_html_e( 'No leads found.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php echo paginate_links( [ // phpcs:ignore
						'base'      => add_query_arg( 'paged', '%#%', $sort_base ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $total_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					] ); ?>
				</div>
			</div>
			<?php endif; ?>

		</div>
		<?php
	}

	private static function render_lead_detail( $lead_id ) {
		global $wpdb;
		$p = $wpdb->prefix;

		$lead = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$p}vlt_leads WHERE id = %d LIMIT 1",
			$lead_id
		) );

		if ( ! $lead ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Lead not found.', 'video-lead-tracker' ) . '</p></div>';
			return;
		}

		$video_history = $wpdb->get_results( $wpdb->prepare(
			"SELECT v.title, v.video_key, v.duration_seconds,
			        s.unique_watch_seconds, s.total_watch_seconds, s.unique_watch_percent,
			        s.sessions_count, s.reached_end, s.first_play_at, s.last_activity_at
			 FROM {$p}vlt_video_user_summary s
			 JOIN {$p}vlt_videos v ON v.id = s.video_id
			 WHERE s.lead_id = %d
			 ORDER BY s.first_play_at ASC",
			$lead_id
		) );

		$sessions = $wpdb->get_results( $wpdb->prepare(
			"SELECT session_uuid, started_at, last_activity_at, device_type, browser, os, landing_url
			 FROM {$p}vlt_sessions
			 WHERE lead_id = %d
			 ORDER BY started_at DESC
			 LIMIT 20",
			$lead_id
		) );

		$date_fmt = get_option( 'date_format' ) . ' H:i';
		$back_url = admin_url( 'admin.php?page=vlt-leads' );
		?>
		<div class="wrap vlt-lead-detail">

			<h1 class="wp-heading-inline">
				<a href="<?php echo esc_url( $back_url ); ?>" class="vlt-back-link">&larr; <?php esc_html_e( 'Leads', 'video-lead-tracker' ); ?></a>
				<?php echo esc_html( $lead->primary_name ?: __( '(no name)', 'video-lead-tracker' ) ); ?>
			</h1>
			<hr class="wp-header-end">

			<div class="vlt-lead-header">
				<div class="vlt-lead-meta">
					<div class="vlt-meta-item">
						<span class="vlt-meta-label"><?php esc_html_e( 'Mobile', 'video-lead-tracker' ); ?></span>
						<code class="vlt-mono"><?php echo esc_html( $lead->normalized_mobile ); ?></code>
					</div>
					<div class="vlt-meta-item">
						<span class="vlt-meta-label"><?php esc_html_e( 'Verified', 'video-lead-tracker' ); ?></span>
						<?php if ( $lead->is_verified ) : ?>
							<span class="vlt-badge vlt-badge--green"><?php esc_html_e( 'Yes', 'video-lead-tracker' ); ?></span>
						<?php else : ?>
							<span class="vlt-badge"><?php esc_html_e( 'No', 'video-lead-tracker' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="vlt-meta-item">
						<span class="vlt-meta-label"><?php esc_html_e( 'First Seen', 'video-lead-tracker' ); ?></span>
						<span><?php echo esc_html( wp_date( $date_fmt, strtotime( $lead->first_seen_at ) ) ); ?></span>
					</div>
					<div class="vlt-meta-item">
						<span class="vlt-meta-label"><?php esc_html_e( 'Last Seen', 'video-lead-tracker' ); ?></span>
						<span><?php echo esc_html( $lead->last_seen_at ? wp_date( $date_fmt, strtotime( $lead->last_seen_at ) ) : '—' ); ?></span>
					</div>
				</div>
			</div>

			<h2 class="vlt-section-title"><?php esc_html_e( 'Video Watch History', 'video-lead-tracker' ); ?></h2>
			<table class="widefat striped vlt-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Video',        'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Watch %',      'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Unique Watch', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Sessions',     'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Completed',    'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'First Play',   'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Last Activity','video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $video_history ) : ?>
					<?php foreach ( $video_history as $v ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $v->title ?: $v->video_key ); ?></strong>
							<?php if ( $v->duration_seconds ) : ?>
								<br><span class="vlt-muted"><?php echo esc_html( self::format_duration( (int) $v->duration_seconds ) ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<div class="vlt-progress">
								<div class="vlt-progress-track">
									<div class="vlt-progress-bar" style="width:<?php echo esc_attr( min( 100, (int) round( $v->unique_watch_percent ) ) ); ?>%"></div>
								</div>
								<span><?php echo esc_html( round( (float) $v->unique_watch_percent, 1 ) . '%' ); ?></span>
							</div>
						</td>
						<td><?php echo esc_html( round( (float) $v->unique_watch_seconds ) . 's' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $v->sessions_count ) ); ?></td>
						<td>
							<?php if ( $v->reached_end ) : ?>
								<span class="vlt-badge vlt-badge--green"><?php esc_html_e( 'Yes', 'video-lead-tracker' ); ?></span>
							<?php else : ?>
								<span class="vlt-badge"><?php esc_html_e( 'No', 'video-lead-tracker' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="vlt-muted"><?php echo esc_html( $v->first_play_at ? wp_date( $date_fmt, strtotime( $v->first_play_at ) ) : '—' ); ?></td>
						<td class="vlt-muted"><?php echo esc_html( $v->last_activity_at ? wp_date( $date_fmt, strtotime( $v->last_activity_at ) ) : '—' ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="7" class="vlt-empty"><?php esc_html_e( 'No video watch history for this lead.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

			<h2 class="vlt-section-title"><?php esc_html_e( 'Recent Sessions', 'video-lead-tracker' ); ?></h2>
			<table class="widefat striped vlt-table">
				<thead>
					<tr>
						<th>UUID</th>
						<th><?php esc_html_e( 'Started',     'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Duration',    'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Device',      'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Browser',     'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'OS',          'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Landing URL', 'video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $sessions ) : ?>
					<?php foreach ( $sessions as $sess ) :
						$dur = ( $sess->last_activity_at && $sess->started_at )
							? max( 0, strtotime( $sess->last_activity_at ) - strtotime( $sess->started_at ) )
							: 0;
					?>
					<tr>
						<td><code class="vlt-mono" title="<?php echo esc_attr( $sess->session_uuid ); ?>"><?php echo esc_html( substr( $sess->session_uuid, 0, 8 ) . '…' ); ?></code></td>
						<td class="vlt-muted"><?php echo esc_html( wp_date( $date_fmt, strtotime( $sess->started_at ) ) ); ?></td>
						<td><?php echo esc_html( $dur > 0 ? self::format_duration( $dur ) : '—' ); ?></td>
						<td><?php echo esc_html( $sess->device_type ?: '—' ); ?></td>
						<td><?php echo esc_html( $sess->browser ?: '—' ); ?></td>
						<td><?php echo esc_html( $sess->os ?: '—' ); ?></td>
						<td class="vlt-url-cell">
							<?php if ( $sess->landing_url ) :
								$path = wp_parse_url( $sess->landing_url, PHP_URL_PATH ) ?: $sess->landing_url;
							?>
								<a href="<?php echo esc_url( $sess->landing_url ); ?>" target="_blank" rel="noopener noreferrer"
								   title="<?php echo esc_attr( $sess->landing_url ); ?>">
									<?php echo esc_html( $path ); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="7" class="vlt-empty"><?php esc_html_e( 'No sessions recorded.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

		</div><!-- .wrap.vlt-lead-detail -->
		<?php
	}


	// -------------------------------------------------------------------------
	// Video Analytics / Funnel / Heatmap — delegated to VLT_Admin_Analytics
	// -------------------------------------------------------------------------

	public static function render_video_analytics() {
		VLT_Admin_Analytics::render_video_analytics();
	}

	public static function render_funnel() {
		VLT_Admin_Analytics::render_funnel();
	}

	public static function render_heatmap() {
		VLT_Admin_Analytics::render_heatmap();
	}


	public static function render_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$p = $wpdb->prefix;

		$level   = isset( $_GET['vlt_level'] ) ? sanitize_key( $_GET['vlt_level'] ) : '';
		$allowed = [ '', 'error', 'warning', 'info', 'debug' ];
		if ( ! in_array( $level, $allowed, true ) ) {
			$level = '';
		}

		$where = $level ? $wpdb->prepare( 'WHERE level = %s', $level ) : '';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_logs $where" );
		$rows  = $wpdb->get_results(
			"SELECT id, level, context, message, metadata, created_at
			 FROM {$p}vlt_logs $where
			 ORDER BY id DESC
			 LIMIT 200"
		);

		$level_colors = [
			'error'   => '#c22f3a',
			'warning' => '#d97f00',
			'info'    => '#2271b1',
			'debug'   => '#646970',
		];

		$filter_url = admin_url( 'admin.php?page=vlt-logs' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Logs', 'video-lead-tracker' ); ?></h1>
			<button type="button" class="page-title-action vlt-purge-logs-btn">
				<?php esc_html_e( 'Purge All Logs', 'video-lead-tracker' ); ?>
			</button>
			<hr class="wp-header-end">

			<div style="margin:12px 0;display:flex;gap:6px;align-items:center">
				<span style="font-size:13px"><?php esc_html_e( 'Filter:', 'video-lead-tracker' ); ?></span>
				<a href="<?php echo esc_url( $filter_url ); ?>"
				   class="button<?php echo $level === '' ? ' button-primary' : ''; ?>">
					<?php esc_html_e( 'All', 'video-lead-tracker' ); ?>
				</a>
				<?php foreach ( [ 'error', 'warning', 'info', 'debug' ] as $lvl ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'vlt_level', $lvl, $filter_url ) ); ?>"
					   class="button<?php echo $level === $lvl ? ' button-primary' : ''; ?>">
						<?php echo esc_html( ucfirst( $lvl ) ); ?>
					</a>
				<?php endforeach; ?>
				<span class="vlt-muted" style="margin-left:8px">
					<?php printf(
						/* translators: %d = number of log entries shown */
						esc_html__( 'Showing last %d of %d entries', 'video-lead-tracker' ),
						min( 200, $total ),
						$total
					); ?>
				</span>
			</div>

			<table class="widefat striped vlt-table">
				<thead>
					<tr>
						<th style="width:50px">ID</th>
						<th style="width:80px"><?php esc_html_e( 'Level',   'video-lead-tracker' ); ?></th>
						<th style="width:130px"><?php esc_html_e( 'Context', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Message', 'video-lead-tracker' ); ?></th>
						<th style="width:150px"><?php esc_html_e( 'Time',    'video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $rows ) : ?>
					<?php foreach ( $rows as $row ) :
						$color = $level_colors[ $row->level ] ?? '#646970';
					?>
					<tr>
						<td class="vlt-muted"><?php echo esc_html( $row->id ); ?></td>
						<td>
							<span class="vlt-badge" style="background:<?php echo esc_attr( $color ); ?>;color:#fff">
								<?php echo esc_html( strtoupper( $row->level ) ); ?>
							</span>
						</td>
						<td class="vlt-muted"><?php echo esc_html( $row->context ?: '—' ); ?></td>
						<td>
							<?php echo esc_html( $row->message ); ?>
							<?php if ( $row->metadata ) : ?>
								<details style="margin-top:4px">
									<summary class="vlt-muted" style="cursor:pointer;font-size:11px"><?php esc_html_e( 'metadata', 'video-lead-tracker' ); ?></summary>
									<pre style="font-size:11px;white-space:pre-wrap;margin:4px 0 0"><?php echo esc_html( $row->metadata ); ?></pre>
								</details>
							<?php endif; ?>
						</td>
						<td class="vlt-muted"><?php echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $row->created_at ) ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5" class="vlt-empty"><?php esc_html_e( 'No log entries.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Render a video filter <select>.
	 *
	 * @param  string $page      Admin page slug (e.g. 'vlt-overview').
	 * @param  array  $keep_get  Extra GET params to preserve in the form (key => value).
	 * @return object|null       Selected video DB row, or null when "All Videos" selected.
	 */
	private static function video_filter_select( $page, array $keep_get = [] ) {
		return VLT_Admin_UI::render_video_filter( $page, $keep_get, false );
	}

	/**
	 * Build a sortable column header link.
	 * Clicking the same column toggles ASC ↔ DESC; clicking a new column defaults to ASC.
	 */
	private static function sort_link( $label, $col, $current_col, $current_dir, $base_url ) {
		$dir = ( $current_col === $col && $current_dir === 'ASC' ) ? 'DESC' : 'ASC';
		$url = add_query_arg( [ 'orderby' => $col, 'order' => $dir, 'paged' => 1 ], $base_url );

		$icon = '';
		if ( $current_col === $col ) {
			$icon = $current_dir === 'ASC'
				? ' <span class="dashicons dashicons-arrow-up-alt2" style="vertical-align:middle;font-size:14px;width:14px;height:14px"></span>'
				: ' <span class="dashicons dashicons-arrow-down-alt2" style="vertical-align:middle;font-size:14px;width:14px;height:14px"></span>';
		}

		return '<a href="' . esc_url( $url ) . '" style="text-decoration:none;color:inherit;white-space:nowrap">'
			. esc_html( $label ) . $icon . '</a>';
	}

	/**
	 * Mask the middle digits of a normalized mobile number.
	 * 98912xxxxxxxx → 98912•••xxxx
	 */
	public static function mask_mobile( $mobile ) {
		$mobile = (string) $mobile;
		if ( strlen( $mobile ) < 9 ) {
			return $mobile;
		}
		return substr( $mobile, 0, 5 ) . '•••' . substr( $mobile, -4 );
	}

	/**
	 * Format seconds as M:SS or H:MM:SS.
	 */
	public static function format_duration( $seconds ) {
		$h = (int) floor( $seconds / 3600 );
		$m = (int) floor( ( $seconds % 3600 ) / 60 );
		$s = (int) ( $seconds % 60 );

		return $h > 0
			? sprintf( '%d:%02d:%02d', $h, $m, $s )
			: sprintf( '%d:%02d', $m, $s );
	}
}
