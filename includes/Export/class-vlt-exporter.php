<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Exporter {

	public static function init() {
		add_action( 'admin_post_vlt_export', [ self::class, 'handle' ] );
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', '', [ 'response' => 403 ] );
		}

		check_admin_referer( 'vlt_export' );

		$type = isset( $_GET['vlt_type'] ) ? sanitize_key( $_GET['vlt_type'] ) : '';

		switch ( $type ) {
			case 'leads':
				self::export_leads();
				break;
			case 'videos':
				self::export_videos();
				break;
			case 'heatmap':
				self::export_heatmap( absint( $_GET['video_id'] ?? 0 ) );
				break;
			case 'video_summary':
				self::export_video_summary( absint( $_GET['video_id'] ?? 0 ) );
				break;
			case 'video_ranges':
				self::export_video_ranges( absint( $_GET['video_id'] ?? 0 ) );
				break;
			default:
				wp_die( esc_html__( 'Unknown export type.', 'video-lead-tracker' ) );
		}
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	private static function stream_headers( $filename ) {
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		// UTF-8 BOM — makes Excel open the file correctly on Windows.
		echo "\xEF\xBB\xBF";
	}

	// -------------------------------------------------------------------------
	// Leads
	// -------------------------------------------------------------------------

	private static function export_leads() {
		global $wpdb;
		$p = $wpdb->prefix;

		$rows = $wpdb->get_results(
			"SELECT l.primary_name,
			        l.normalized_mobile,
			        l.is_verified,
			        COALESCE( COUNT( DISTINCT s.video_id ), 0 )      AS videos_watched,
			        COALESCE( AVG( s.unique_watch_percent ), 0 )      AS avg_watch_pct,
			        COALESCE( SUM( s.sessions_count ), 0 )            AS total_sessions,
			        l.created_at,
			        l.updated_at
			 FROM {$p}vlt_leads l
			 LEFT JOIN {$p}vlt_video_user_summary s ON s.lead_id = l.id
			 GROUP BY l.id
			 ORDER BY l.created_at DESC"
		);

		self::stream_headers( 'vlt-leads-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'Name', 'Mobile', 'Verified', 'Videos Watched', 'Avg Watch %', 'Sessions', 'First Seen', 'Last Seen' ] );

		foreach ( $rows as $row ) {
			fputcsv( $out, [
				$row->primary_name,
				$row->normalized_mobile,
				$row->is_verified ? 'Yes' : 'No',
				(int) $row->videos_watched,
				round( (float) $row->avg_watch_pct, 1 ),
				(int) $row->total_sessions,
				$row->created_at,
				$row->updated_at,
			] );
		}

		fclose( $out );
		exit;
	}

	// -------------------------------------------------------------------------
	// Video summary
	// -------------------------------------------------------------------------

	private static function export_videos() {
		global $wpdb;
		$p = $wpdb->prefix;

		$rows = $wpdb->get_results(
			"SELECT v.title,
			        v.video_key,
			        v.duration_seconds,
			        COUNT( DISTINCT CASE WHEN s.lead_id IS NOT NULL
			            THEN CONCAT( 'l', s.lead_id )
			            ELSE s.visitor_uuid END )                         AS viewers,
			        COALESCE( AVG( s.unique_watch_percent ), 0 )          AS avg_completion,
			        COALESCE( SUM( s.unique_watch_seconds ), 0 ) / 3600   AS watch_hours,
			        COALESCE( SUM( s.reached_end ), 0 )                   AS completions,
			        COALESCE( SUM( s.sessions_count ), 0 )                AS total_sessions
			 FROM {$p}vlt_videos v
			 LEFT JOIN {$p}vlt_video_user_summary s ON s.video_id = v.id
			 WHERE v.is_active = 1
			 GROUP BY v.id
			 ORDER BY watch_hours DESC"
		);

		self::stream_headers( 'vlt-videos-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'Title', 'Video Key', 'Duration (s)', 'Viewers', 'Avg Watch %', 'Watch Hours', 'Completions', 'Sessions' ] );

		foreach ( $rows as $row ) {
			fputcsv( $out, [
				$row->title,
				$row->video_key,
				(int) $row->duration_seconds,
				(int) $row->viewers,
				round( (float) $row->avg_completion, 1 ),
				round( (float) $row->watch_hours, 4 ),
				(int) $row->completions,
				(int) $row->total_sessions,
			] );
		}

		fclose( $out );
		exit;
	}

	// -------------------------------------------------------------------------
	// Heatmap (per-second)
	// -------------------------------------------------------------------------

	private static function export_heatmap( $video_id ) {
		global $wpdb;
		$p = $wpdb->prefix;

		if ( ! $video_id ) {
			wp_die( esc_html__( 'No video selected.', 'video-lead-tracker' ) );
		}

		$video = $wpdb->get_row( $wpdb->prepare(
			"SELECT title, video_key FROM {$p}vlt_videos WHERE id = %d LIMIT 1",
			$video_id
		) );

		if ( ! $video ) {
			wp_die( esc_html__( 'Video not found.', 'video-lead-tracker' ) );
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT second_index, total_views_count, unique_visitors_count, unique_leads_count
			 FROM {$p}vlt_video_heatmap
			 WHERE video_id = %d
			 ORDER BY second_index ASC",
			$video_id
		) );

		$slug = sanitize_file_name( $video->video_key ?: $video->title );
		self::stream_headers( 'vlt-heatmap-' . $slug . '-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'Second', 'Total Views', 'Unique Visitors', 'Unique Leads' ] );

		foreach ( $rows as $row ) {
			fputcsv( $out, [
				(int) $row->second_index,
				(int) $row->total_views_count,
				(int) $row->unique_visitors_count,
				(int) $row->unique_leads_count,
			] );
		}

		fclose( $out );
		exit;
	}

	// -------------------------------------------------------------------------
	// Per-video viewer summary
	// -------------------------------------------------------------------------

	private static function export_video_summary( $video_id ) {
		global $wpdb;
		$p = $wpdb->prefix;

		if ( ! $video_id ) {
			wp_die( esc_html__( 'No video selected.', 'video-lead-tracker' ) );
		}

		$video = $wpdb->get_row( $wpdb->prepare(
			"SELECT title, video_key FROM {$p}vlt_videos WHERE id = %d LIMIT 1",
			$video_id
		) );

		if ( ! $video ) {
			wp_die( esc_html__( 'Video not found.', 'video-lead-tracker' ) );
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.primary_name, l.normalized_mobile, l.is_verified,
			        s.unique_watch_percent, s.unique_watch_seconds,
			        s.sessions_count, s.reached_end,
			        s.last_position, s.first_play_at,
			        s.visitor_uuid, s.lead_id
			 FROM {$p}vlt_video_user_summary s
			 LEFT JOIN {$p}vlt_leads l ON l.id = s.lead_id
			 WHERE s.video_id = %d
			 ORDER BY s.unique_watch_percent DESC",
			$video_id
		) );

		$slug = sanitize_file_name( $video->video_key ?: $video->title );
		self::stream_headers( 'vlt-summary-' . $slug . '-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'Name', 'Mobile', 'Verified', 'Watch %', 'Watch Seconds', 'Sessions', 'Completed', 'Last Position', 'First Play', 'Visitor UUID' ] );

		foreach ( $rows as $row ) {
			fputcsv( $out, [
				$row->lead_id ? ( $row->primary_name ?: '' ) : '',
				$row->lead_id ? $row->normalized_mobile : '',
				$row->lead_id ? ( $row->is_verified ? 'Yes' : 'No' ) : 'Anonymous',
				round( (float) $row->unique_watch_percent, 1 ),
				round( (float) $row->unique_watch_seconds ),
				(int) $row->sessions_count,
				$row->reached_end ? 'Yes' : 'No',
				$row->last_position !== null ? round( (float) $row->last_position, 1 ) : '',
				$row->first_play_at ?: '',
				$row->lead_id ? '' : $row->visitor_uuid,
			] );
		}

		fclose( $out );
		exit;
	}

	// -------------------------------------------------------------------------
	// Per-video raw watch ranges
	// -------------------------------------------------------------------------

	private static function export_video_ranges( $video_id ) {
		global $wpdb;
		$p = $wpdb->prefix;

		if ( ! $video_id ) {
			wp_die( esc_html__( 'No video selected.', 'video-lead-tracker' ) );
		}

		$video = $wpdb->get_row( $wpdb->prepare(
			"SELECT title, video_key FROM {$p}vlt_videos WHERE id = %d LIMIT 1",
			$video_id
		) );

		if ( ! $video ) {
			wp_die( esc_html__( 'Video not found.', 'video-lead-tracker' ) );
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT s.visitor_uuid, s.lead_id, s.raw_ranges_json,
			        l.primary_name, l.normalized_mobile
			 FROM {$p}vlt_video_user_summary s
			 LEFT JOIN {$p}vlt_leads l ON l.id = s.lead_id
			 WHERE s.video_id = %d",
			$video_id
		) );

		$slug = sanitize_file_name( $video->video_key ?: $video->title );
		self::stream_headers( 'vlt-ranges-' . $slug . '-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'Viewer', 'Mobile', 'Range Start (s)', 'Range End (s)' ] );

		foreach ( $rows as $row ) {
			$viewer = $row->lead_id ? ( $row->primary_name ?: $row->normalized_mobile ) : substr( $row->visitor_uuid, 0, 8 );
			$mobile = $row->lead_id ? $row->normalized_mobile : '';
			$ranges = json_decode( $row->raw_ranges_json ?: '[]', true );
			if ( is_array( $ranges ) ) {
				foreach ( $ranges as $range ) {
					if ( isset( $range[0], $range[1] ) ) {
						fputcsv( $out, [ $viewer, $mobile, round( (float) $range[0], 2 ), round( (float) $range[1], 2 ) ] );
					}
				}
			}
		}

		fclose( $out );
		exit;
	}

	// -------------------------------------------------------------------------
	// URL builder (used by admin views)
	// -------------------------------------------------------------------------

	public static function export_url( $type, $extra = [] ) {
		return wp_nonce_url(
			add_query_arg(
				array_merge( [ 'action' => 'vlt_export', 'vlt_type' => $type ], $extra ),
				admin_url( 'admin-post.php' )
			),
			'vlt_export'
		);
	}
}
