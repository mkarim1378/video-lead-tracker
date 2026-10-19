<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page listing video-triggered SMS (queued / sent / failed / skipped).
 */
class VLT_SMS_Log_Admin {

	const LIMIT = 200;

	/**
	 * @return string[]  Allowed status filters ('' = all).
	 */
	public static function statuses() {
		return [
			''        => __( 'All', 'video-lead-tracker' ),
			'sent'    => __( 'Sent', 'video-lead-tracker' ),
			'queued'  => __( 'Queued', 'video-lead-tracker' ),
			'failed'  => __( 'Failed', 'video-lead-tracker' ),
			'skipped' => __( 'Skipped', 'video-lead-tracker' ),
		];
	}

	/**
	 * @return array
	 */
	public static function get_rows( $status, $video_id ) {
		global $wpdb;
		$p = $wpdb->prefix;

		$where = [ '1=1' ];
		if ( $status ) {
			$where[] = $wpdb->prepare( 'l.status = %s', $status );
		}
		if ( $video_id ) {
			$where[] = $wpdb->prepare( 'l.video_id = %d', $video_id );
		}
		$where_sql = implode( ' AND ', $where );

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_sms_log l WHERE $where_sql" );
		$rows  = $wpdb->get_results(
			"SELECT l.*, v.title AS video_title, v.video_key, t.trigger_second, ld.primary_name
			 FROM {$p}vlt_sms_log l
			 LEFT JOIN {$p}vlt_videos v ON v.id = l.video_id
			 LEFT JOIN {$p}vlt_sms_triggers t ON t.id = l.trigger_id
			 LEFT JOIN {$p}vlt_leads ld ON ld.id = l.lead_id
			 WHERE $where_sql
			 ORDER BY l.id DESC
			 LIMIT " . self::LIMIT
		);

		return [
			'total' => $total,
			'rows'  => (array) $rows,
		];
	}

	/**
	 * Human label for skip_reason.
	 */
	public static function skip_label( $reason ) {
		switch ( $reason ) {
			case 'unverified':
				return __( 'Mobile not verified with OTP', 'video-lead-tracker' );
			case 'daily_cap':
				return __( 'Daily SMS limit reached', 'video-lead-tracker' );
			default:
				return (string) $reason;
		}
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$status   = sanitize_key( $_GET['status'] ?? '' );
		$video_id = absint( $_GET['video_id'] ?? 0 );
		// phpcs:enable
		if ( ! array_key_exists( $status, self::statuses() ) ) {
			$status = '';
		}

		$data = self::get_rows( $status, $video_id );

		VLT_Admin_UI::open( [
			'page'     => 'vlt-sms-log',
			'title'    => __( 'SMS Log', 'video-lead-tracker' ),
			'subtitle' => __( 'Messages sent to leads during video playback.', 'video-lead-tracker' ),
		] );
		VLT_Admin_UI::render( 'pages/sms-log', [
			'data'     => $data,
			'status'   => $status,
			'video_id' => $video_id,
			'video'    => $video_id ? VLT_DB::get_video_by_id( $video_id ) : null,
		] );
		VLT_Admin_UI::close();
	}
}
