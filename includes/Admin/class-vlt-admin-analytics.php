<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics admin surfaces — Video Analytics, Funnel, Heatmap (Phase 1).
 */
class VLT_Admin_Analytics {

	public static function render_video_analytics() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$video_id = isset( $_GET['video_id'] ) ? absint( $_GET['video_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $video_id ) {
			self::render_video_analytics_detail( $video_id );
		} else {
			self::render_video_analytics_list();
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_videos_analytics_list() {
		global $wpdb;
		$p = $wpdb->prefix;

		$videos = $wpdb->get_results(
			"SELECT v.id, v.title, v.video_key, v.duration_seconds,
			        COUNT( DISTINCT CASE WHEN s.lead_id IS NOT NULL
			            THEN CONCAT( 'l', s.lead_id )
			            ELSE s.visitor_uuid END )               AS viewers,
			        COUNT( DISTINCT s.lead_id )                 AS leads_count,
			        COALESCE( AVG(s.unique_watch_percent), 0 )  AS avg_completion,
			        COALESCE( SUM(s.unique_watch_seconds), 0 ) / 3600 AS watch_hours,
			        COALESCE( SUM(s.reached_end), 0 )           AS completions,
			        MIN(s.first_play_at)                        AS first_play_at
			 FROM {$p}vlt_videos v
			 LEFT JOIN {$p}vlt_video_user_summary s ON s.video_id = v.id
			 WHERE v.is_active = 1
			 GROUP BY v.id
			 ORDER BY watch_hours DESC"
		);

		$peak_exit_map = [];
		$exit_rows     = $wpdb->get_results(
			"SELECT video_id, FLOOR(last_position / 5) * 5 AS bucket, COUNT(*) AS cnt
			 FROM {$p}vlt_video_user_summary
			 WHERE last_position IS NOT NULL
			 GROUP BY video_id, bucket"
		);
		foreach ( $exit_rows as $er ) {
			$vid = (int) $er->video_id;
			$cnt = (int) $er->cnt;
			if ( ! isset( $peak_exit_map[ $vid ] ) || $cnt > $peak_exit_map[ $vid ]['cnt'] ) {
				$peak_exit_map[ $vid ] = [ 'bucket' => (int) $er->bucket, 'cnt' => $cnt ];
			}
		}

		$rows = [];
		foreach ( (array) $videos as $v ) {
			$peak = $peak_exit_map[ (int) $v->id ] ?? null;
			$avg  = (float) $v->avg_completion;
			$rows[] = [
				'id'          => (int) $v->id,
				'title'       => $v->title ?: $v->video_key,
				'duration'    => $v->duration_seconds ? VLT_Admin::format_duration( (int) $v->duration_seconds ) : '',
				'viewers'     => number_format_i18n( (int) $v->viewers ),
				'leads'       => number_format_i18n( (int) $v->leads_count ),
				'avg_pct'     => round( $avg, 1 ) . '%',
				'avg_pct_int' => min( 100, (int) round( $avg ) ),
				'watch_hours' => number_format( (float) $v->watch_hours, 2 ),
				'completions' => number_format_i18n( (int) $v->completions ),
				'top_exit'    => $peak ? VLT_Admin::format_duration( $peak['bucket'] ) : '—',
				'first_play'  => $v->first_play_at ? wp_date( 'Y-m-d', strtotime( $v->first_play_at ) ) : '—',
			];
		}
		return $rows;
	}

	/**
	 * @param int $video_id
	 * @return array|WP_Error
	 */
	public static function get_video_analytics_detail( $video_id ) {
		global $wpdb;
		$p        = $wpdb->prefix;
		$video_id = absint( $video_id );

		$video = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$p}vlt_videos WHERE id = %d LIMIT 1",
			$video_id
		) );
		if ( ! $video ) {
			return new WP_Error( 'not_found', __( 'Video not found.', 'video-lead-tracker' ), [ 'status' => 404 ] );
		}

		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT( DISTINCT CASE WHEN s.lead_id IS NOT NULL
			                THEN CONCAT('l', s.lead_id)
			                ELSE s.visitor_uuid END )               AS viewers,
			        COALESCE( AVG(s.unique_watch_percent), 0 )      AS avg_completion,
			        COALESCE( SUM(s.unique_watch_seconds), 0 ) / 3600 AS watch_hours,
			        COALESCE( SUM(s.reached_end), 0 )               AS completions,
			        COALESCE( SUM(s.sessions_count), 0 )            AS total_sessions
			 FROM {$p}vlt_video_user_summary s WHERE s.video_id = %d",
			$video_id
		) );

		$dist = $wpdb->get_row( $wpdb->prepare(
			"SELECT SUM( CASE WHEN unique_watch_percent < 25 THEN 1 ELSE 0 END )                               AS b0,
			        SUM( CASE WHEN unique_watch_percent >= 25 AND unique_watch_percent < 50 THEN 1 ELSE 0 END ) AS b25,
			        SUM( CASE WHEN unique_watch_percent >= 50 AND unique_watch_percent < 75 THEN 1 ELSE 0 END ) AS b50,
			        SUM( CASE WHEN unique_watch_percent >= 75 THEN 1 ELSE 0 END )                               AS b75,
			        COUNT(*)                                                                                    AS total
			 FROM {$p}vlt_video_user_summary WHERE video_id = %d",
			$video_id
		) );

		$top_viewers = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.primary_name, l.normalized_mobile, l.is_verified,
			        s.unique_watch_percent, s.unique_watch_seconds, s.sessions_count,
			        s.reached_end, s.first_play_at, s.visitor_uuid, s.lead_id
			 FROM {$p}vlt_video_user_summary s
			 LEFT JOIN {$p}vlt_leads l ON l.id = s.lead_id
			 WHERE s.video_id = %d
			 ORDER BY s.unique_watch_percent DESC
			 LIMIT 20",
			$video_id
		) );

		$peak_exit = $wpdb->get_row( $wpdb->prepare(
			"SELECT FLOOR(last_position / 5) * 5 AS bucket_start, COUNT(*) AS cnt
			 FROM {$p}vlt_video_user_summary
			 WHERE video_id = %d AND last_position IS NOT NULL
			 GROUP BY bucket_start
			 ORDER BY cnt DESC
			 LIMIT 1",
			$video_id
		) );

		$dist_total  = max( 1, (int) ( $dist->total ?? 1 ) );
		$raw_buckets = [
			[ '0–25%',   (int) ( $dist->b0  ?? 0 ) ],
			[ '25–50%',  (int) ( $dist->b25 ?? 0 ) ],
			[ '50–75%',  (int) ( $dist->b50 ?? 0 ) ],
			[ '75–100%', (int) ( $dist->b75 ?? 0 ) ],
		];
		$distribution = [];
		foreach ( $raw_buckets as [ $label, $count ] ) {
			$pct            = round( $count / $dist_total * 100, 1 );
			$distribution[] = [
				'label'       => $label,
				'count'       => $count,
				'pct'         => $pct,
				'count_label' => number_format_i18n( $count ) . ' (' . $pct . '%)',
			];
		}

		$viewers = [];
		foreach ( (array) $top_viewers as $v ) {
			$pct = (float) $v->unique_watch_percent;
			if ( $v->lead_id ) {
				$name   = $v->primary_name ?: __( '(no name)', 'video-lead-tracker' );
				$mobile = VLT_Admin::mask_mobile( $v->normalized_mobile );
				$url    = add_query_arg( [ 'page' => 'vlt-leads', 'lead_id' => $v->lead_id ], admin_url( 'admin.php' ) );
			} else {
				$name   = __( 'Anonymous', 'video-lead-tracker' );
				$mobile = substr( (string) $v->visitor_uuid, 0, 8 ) . '…';
				$url    = '';
			}
			$viewers[] = [
				'name'          => $name,
				'mobile'        => $mobile,
				'lead_url'      => $url,
				'watch_pct'     => round( $pct, 1 ) . '%',
				'watch_pct_int' => min( 100, (int) round( $pct ) ),
				'unique_watch'  => round( (float) $v->unique_watch_seconds ) . 's',
				'sessions'      => number_format_i18n( (int) $v->sessions_count ),
				'completed'     => (bool) $v->reached_end,
				'first_play'    => $v->first_play_at ? wp_date( 'Y-m-d H:i', strtotime( $v->first_play_at ) ) : '—',
			];
		}

		$peak_exit_value = $peak_exit && $peak_exit->bucket_start !== null
			? VLT_Admin::format_duration( (int) $peak_exit->bucket_start )
			: '—';

		return [
			'video' => [
				'id'       => (int) $video->id,
				'title'    => $video->title ?: $video->video_key,
				'duration' => $video->duration_seconds ? VLT_Admin::format_duration( (int) $video->duration_seconds ) : '',
			],
			'kpis'         => [
				[ 'key' => 'viewers',     'label' => __( 'Viewers', 'video-lead-tracker' ),     'value' => number_format_i18n( (int) $stats->viewers ),         'icon' => 'dashicons-groups' ],
				[ 'key' => 'avg_pct',     'label' => __( 'Avg %', 'video-lead-tracker' ),       'value' => round( (float) $stats->avg_completion, 1 ) . '%',  'icon' => 'dashicons-chart-bar' ],
				[ 'key' => 'watch_hours', 'label' => __( 'Watch Hours', 'video-lead-tracker' ), 'value' => number_format( (float) $stats->watch_hours, 2 ),   'icon' => 'dashicons-visibility' ],
				[ 'key' => 'completions', 'label' => __( 'Completions', 'video-lead-tracker' ), 'value' => number_format_i18n( (int) $stats->completions ),   'icon' => 'dashicons-yes-alt' ],
				[ 'key' => 'sessions',    'label' => __( 'Sessions', 'video-lead-tracker' ),    'value' => number_format_i18n( (int) $stats->total_sessions ), 'icon' => 'dashicons-clock' ],
				[ 'key' => 'peak_exit',   'label' => __( 'Peak Exit', 'video-lead-tracker' ),   'value' => $peak_exit_value,                                   'icon' => 'dashicons-exit' ],
			],
			'distribution' => $distribution,
			'top_viewers'  => $viewers,
			'export'       => [
				'summary' => VLT_Exporter::export_url( 'video_summary', [ 'video_id' => $video_id ] ),
				'ranges'  => VLT_Exporter::export_url( 'video_ranges', [ 'video_id' => $video_id ] ),
			],
		];
	}

	private static function render_video_analytics_list() {
		$videos = self::get_videos_analytics_list();

		ob_start();
		?>
		<a class="vlt-btn vlt-btn--secondary" href="<?php echo esc_url( VLT_Exporter::export_url( 'videos' ) ); ?>">
			<?php esc_html_e( 'Export Comparison CSV', 'video-lead-tracker' ); ?>
		</a>
		<?php
		$actions = ob_get_clean();

		VLT_Admin_UI::open( [
			'page'         => 'vlt-video-analytics',
			'title'        => __( 'Video Analytics', 'video-lead-tracker' ),
			'subtitle'     => __( 'Compare performance across active videos.', 'video-lead-tracker' ),
			'actions_html' => $actions,
		] );
		VLT_Admin_UI::render( 'pages/analytics-list', [ 'videos' => $videos ] );
		VLT_Admin_UI::close();
	}

	private static function render_video_analytics_detail( $video_id ) {
		$data = self::get_video_analytics_detail( $video_id );
		if ( is_wp_error( $data ) ) {
			VLT_Admin_UI::open( [
				'page'  => 'vlt-video-analytics',
				'title' => __( 'Video Analytics', 'video-lead-tracker' ),
			] );
			VLT_Admin_UI::render( 'partials/empty-state', [
				'icon'    => 'warning',
				'title'   => __( 'Video not found', 'video-lead-tracker' ),
				'message' => $data->get_error_message(),
			] );
			VLT_Admin_UI::close();
			return;
		}

		global $wpdb;
		$videos = $wpdb->get_results(
			"SELECT id, title, video_key, duration_seconds FROM {$wpdb->prefix}vlt_videos WHERE is_active = 1 ORDER BY id ASC"
		);

		ob_start();
		VLT_Admin_UI::render_video_id_filter( 'vlt-video-analytics', (int) $video_id, true, $videos );
		?>
		<a class="vlt-btn vlt-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=vlt-video-analytics' ) ); ?>">
			<?php esc_html_e( 'All videos', 'video-lead-tracker' ); ?>
		</a>
		<a class="vlt-btn vlt-btn--secondary" id="vlt-analytics-export-summary" href="<?php echo esc_url( $data['export']['summary'] ); ?>">
			<?php esc_html_e( 'Export Summary', 'video-lead-tracker' ); ?>
		</a>
		<a class="vlt-btn vlt-btn--secondary" id="vlt-analytics-export-ranges" href="<?php echo esc_url( $data['export']['ranges'] ); ?>">
			<?php esc_html_e( 'Export Ranges', 'video-lead-tracker' ); ?>
		</a>
		<?php
		$actions = ob_get_clean();

		$subtitle = $data['video']['duration']
			? sprintf(
				/* translators: %s = duration */
				__( 'Duration %s', 'video-lead-tracker' ),
				$data['video']['duration']
			)
			: '';

		VLT_Admin_UI::open( [
			'page'         => 'vlt-video-analytics',
			'title'        => $data['video']['title'],
			'subtitle'     => $subtitle,
			'actions_html' => $actions,
		] );
		VLT_Admin_UI::render( 'pages/analytics-detail', [ 'data' => $data ] );
		VLT_Admin_UI::close();
	}

	/**
	 * @param int $video_id
	 * @return array
	 */
	public static function get_funnel_data( $video_id ) {
		global $wpdb;
		$p        = $wpdb->prefix;
		$video_id = absint( $video_id );

		$result = [
			'video_id' => $video_id,
			'video'    => null,
			'steps'    => [],
		];

		if ( ! $video_id ) {
			return $result;
		}

		$video = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, title, video_key, enable_otp FROM {$p}vlt_videos WHERE id = %d AND is_active = 1 LIMIT 1",
			$video_id
		) );
		if ( ! $video ) {
			return $result;
		}

		$ve = "COUNT(DISTINCT CASE WHEN lead_id IS NOT NULL THEN CONCAT('l',lead_id) ELSE visitor_uuid END)";

		$page_visitors = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT CASE WHEN lead_id IS NOT NULL THEN CONCAT('l',lead_id) ELSE visitor_uuid END)
			 FROM {$p}vlt_video_events WHERE video_id = %d AND event_type = 'video_loaded'",
			$video_id
		) );
		$leads_created = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT lead_id) FROM {$p}vlt_video_user_summary WHERE video_id = %d AND lead_id IS NOT NULL",
			$video_id
		) );
		$otp_verified  = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT s.lead_id)
			 FROM {$p}vlt_video_user_summary s
			 JOIN {$p}vlt_leads l ON l.id = s.lead_id
			 WHERE s.video_id = %d AND l.is_verified = 1",
			$video_id
		) );
		$video_started = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT {$ve} FROM {$p}vlt_video_user_summary WHERE video_id = %d AND started = 1",
			$video_id
		) );
		$reached_half  = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT {$ve} FROM {$p}vlt_video_user_summary WHERE video_id = %d AND unique_watch_percent >= 50",
			$video_id
		) );
		$reached_end   = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT {$ve} FROM {$p}vlt_video_user_summary WHERE video_id = %d AND reached_end = 1",
			$video_id
		) );

		$otp_on = (bool) $video->enable_otp;
		$raw    = [
			[ 'label' => __( 'Page Visitors', 'video-lead-tracker' ), 'count' => $page_visitors, 'active' => true ],
			[ 'label' => __( 'Leads Created', 'video-lead-tracker' ), 'count' => $leads_created,  'active' => true ],
			[ 'label' => __( 'OTP Verified', 'video-lead-tracker' ),  'count' => $otp_on ? $otp_verified : null, 'active' => $otp_on ],
			[ 'label' => __( 'Video Started', 'video-lead-tracker' ), 'count' => $video_started,  'active' => true ],
			[ 'label' => __( 'Reached 50%', 'video-lead-tracker' ),   'count' => $reached_half,   'active' => true ],
			[ 'label' => __( 'Reached End', 'video-lead-tracker' ),   'count' => $reached_end,    'active' => true ],
		];

		$base              = max( 1, $page_visitors );
		$prev_active_count = null;
		$steps             = [];
		foreach ( $raw as $step ) {
			$is_na   = ( $step['count'] === null );
			$display = $is_na ? 0 : (int) $step['count'];
			$pct     = $is_na || ! $step['active'] ? 0 : round( $display / $base * 100, 1 );
			$conv    = null;
			if ( $step['active'] && ! $is_na && $prev_active_count !== null && $prev_active_count > 0 ) {
				$conv = round( $display / $prev_active_count * 100, 1 ) . '% ' . __( 'from prev', 'video-lead-tracker' );
			}
			if ( $step['active'] && ! $is_na ) {
				$prev_active_count = $display;
			}
			$steps[] = [
				'label'           => $step['label'],
				'count'           => $step['count'],
				'active'          => (bool) $step['active'],
				'bar_pct'         => min( 100, $pct ),
				'pct_label'       => $pct,
				'count_label'     => number_format_i18n( $display ),
				'step_conversion' => $conv,
			];
		}

		$result['video'] = [
			'id'    => (int) $video->id,
			'title' => $video->title ?: $video->video_key,
		];
		$result['steps'] = $steps;
		return $result;
	}

	public static function render_funnel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$video_id = isset( $_GET['video_id'] ) ? absint( $_GET['video_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$videos   = $wpdb->get_results(
			"SELECT id, title, video_key, duration_seconds FROM {$wpdb->prefix}vlt_videos WHERE is_active = 1 ORDER BY id ASC"
		);
		$data = self::get_funnel_data( $video_id );

		ob_start();
		VLT_Admin_UI::render_video_id_filter( 'vlt-funnel', $video_id, true, $videos );
		$actions = ob_get_clean();

		VLT_Admin_UI::open( [
			'page'         => 'vlt-funnel',
			'title'        => __( 'Funnel Analytics', 'video-lead-tracker' ),
			'subtitle'     => __( 'Track conversion from visit to video completion.', 'video-lead-tracker' ),
			'actions_html' => $actions,
		] );
		VLT_Admin_UI::render( 'pages/funnel', [ 'data' => $data ] );
		VLT_Admin_UI::close();
	}

	/**
	 * @param int $video_id
	 * @return array
	 */
	public static function get_heatmap_data( $video_id ) {
		global $wpdb;
		$p        = $wpdb->prefix;
		$video_id = absint( $video_id );
		$bucket   = max( 1, (int) VLT_Settings::get( 'heatmap_default_bucket' ) );

		$result = [
			'video_id'     => $video_id,
			'bucket_size'  => $bucket,
			'buckets'      => [],
			'max_total'    => 1,
			'max_unique'   => 1,
			'max_drop_off' => 1,
			'export_url'   => $video_id ? VLT_Exporter::export_url( 'heatmap', [ 'video_id' => $video_id ] ) : '',
		];

		if ( ! $video_id ) {
			return $result;
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT FLOOR(second_index / %d) * %d AS bucket_start,
			        SUM(total_views_count)         AS total,
			        SUM(unique_visitors_count)     AS unique_visitors,
			        SUM(unique_leads_count)        AS unique_leads
			 FROM {$p}vlt_video_heatmap
			 WHERE video_id = %d
			 GROUP BY bucket_start
			 ORDER BY bucket_start ASC",
			$bucket, $bucket, $video_id
		) );

		$heatmap_data = [];
		$max_total    = 1;
		$max_unique   = 1;
		$max_drop_off = 1;

		foreach ( (array) $rows as $row ) {
			$entry = [
				'second'          => (int) $row->bucket_start,
				'total'           => (int) $row->total,
				'unique_visitors' => (int) $row->unique_visitors,
				'unique_leads'    => (int) $row->unique_leads,
				'drop_off'        => 0,
			];
			$heatmap_data[] = $entry;
			$max_total       = max( $max_total, $entry['total'] );
			$max_unique      = max( $max_unique, $entry['unique_visitors'] );
		}

		$drop_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT FLOOR(last_position / %d) * %d AS bucket_start, COUNT(*) AS cnt
			 FROM {$p}vlt_video_user_summary
			 WHERE video_id = %d AND last_position IS NOT NULL
			 GROUP BY bucket_start",
			$bucket, $bucket, $video_id
		) );
		$drop_map = [];
		foreach ( (array) $drop_rows as $dr ) {
			$drop_map[ (int) $dr->bucket_start ] = (int) $dr->cnt;
		}
		foreach ( $heatmap_data as &$entry ) {
			$cnt               = $drop_map[ $entry['second'] ] ?? 0;
			$entry['drop_off'] = $cnt;
			$max_drop_off      = max( $max_drop_off, $cnt );
		}
		unset( $entry );

		$result['buckets']      = $heatmap_data;
		$result['max_total']    = $max_total;
		$result['max_unique']   = $max_unique;
		$result['max_drop_off'] = $max_drop_off;
		return $result;
	}

	public static function render_heatmap() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$video_id = isset( $_GET['video_id'] ) ? absint( $_GET['video_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$videos   = $wpdb->get_results(
			"SELECT id, title, video_key, duration_seconds FROM {$wpdb->prefix}vlt_videos WHERE is_active = 1 ORDER BY created_at DESC"
		);
		$data = self::get_heatmap_data( $video_id );

		ob_start();
		VLT_Admin_UI::render_video_id_filter( 'vlt-heatmap', $video_id, true, $videos );
		$actions = ob_get_clean();

		VLT_Admin_UI::open( [
			'page'         => 'vlt-heatmap',
			'title'        => __( 'Heatmap', 'video-lead-tracker' ),
			'subtitle'     => __( 'See where viewers watch and where they drop off.', 'video-lead-tracker' ),
			'actions_html' => $actions,
		] );
		VLT_Admin_UI::render( 'pages/heatmap', [ 'data' => $data ] );
		VLT_Admin_UI::close();
	}
}
