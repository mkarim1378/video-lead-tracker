<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a video's SMS messages when a lead watches past the configured points.
 * Detection runs on each committed watch range; delivery runs in WP-Cron.
 */
class VLT_SMS_Triggers {

	const CRON_HOOK    = 'vlt_send_trigger_sms';
	const MAX_ATTEMPTS = 3;

	/** Minutes to wait before retry N (1-based). */
	const RETRY_DELAYS = [ 1 => 1, 2 => 5 ];

	public static function init() {
		add_action( self::CRON_HOOK, [ self::class, 'deliver' ] );
	}

	/**
	 * Queue SMS for every trigger point that the range [from, to] played through.
	 *
	 * @param object $video         vlt_videos row.
	 * @param string $visitor_uuid
	 * @param string $session_uuid
	 * @param float  $from
	 * @param float  $to
	 */
	public static function evaluate( $video, $visitor_uuid, $session_uuid, $from, $to ) {
		if ( empty( $video->enable_sms_triggers ) ) {
			return;
		}

		$due = [];
		foreach ( VLT_DB::get_sms_triggers( (int) $video->id, true ) as $trigger ) {
			$t = (float) $trigger->trigger_second;
			// Played through t: a seek that lands past t starts a new range at from > t.
			if ( $from < $t && $t <= $to ) {
				$due[] = $trigger;
			}
		}
		if ( ! $due ) {
			return;
		}

		// Resolve the lead on the server: the client-sent lead_id is not trusted here.
		$visitor = VLT_DB::get_visitor_by_uuid( $visitor_uuid );
		if ( ! $visitor || empty( $visitor->lead_id ) ) {
			return;
		}
		$lead = VLT_DB::get_lead_by_id( (int) $visitor->lead_id );
		if ( ! $lead ) {
			return;
		}

		$otp_on = $video->enable_otp && VLT_Settings::get( 'enable_otp' );
		$cap    = max( 1, (int) VLT_Settings::get( 'sms_daily_cap_per_lead' ) );
		$since  = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$now    = current_time( 'mysql', true );

		foreach ( $due as $trigger ) {
			$skip = null;
			if ( $otp_on && ! $lead->is_verified ) {
				$skip = 'unverified';
			} elseif ( VLT_DB::count_recent_sms( $lead->normalized_mobile, $since ) >= $cap ) {
				$skip = 'daily_cap';
			}

			$log_id = VLT_DB::insert_sms_log( [
				'trigger_id'        => (int) $trigger->id,
				'video_id'          => (int) $video->id,
				'lead_id'           => (int) $lead->id,
				'normalized_mobile' => $lead->normalized_mobile,
				'session_uuid'      => $session_uuid ?: null,
				'message'           => self::render_message( $trigger->message_template, $lead, $video ),
				'status'            => $skip ? 'skipped' : 'queued',
				'skip_reason'       => $skip,
				'queued_at'         => $now,
			] );

			if ( $log_id && ! $skip ) {
				wp_schedule_single_event( time(), self::CRON_HOOK, [ $log_id ] );
			}
		}
	}

	/**
	 * Cron handler: send one queued message, rescheduling on failure.
	 *
	 * @param int $log_id
	 */
	public static function deliver( $log_id ) {
		$log = VLT_DB::get_sms_log( $log_id );
		if ( ! $log || 'queued' !== $log->status ) {
			return;
		}

		$attempts = (int) $log->attempts + 1;
		$result   = VLT_SMS_Gateway::send( $log->normalized_mobile, $log->message, 'sms_trigger' );

		if ( true === $result ) {
			VLT_DB::update_sms_log( (int) $log->id, [
				'status'   => 'sent',
				'attempts' => $attempts,
				'sent_at'  => current_time( 'mysql', true ),
				'error'    => '',
			] );
			return;
		}

		$error = is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error';

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			VLT_DB::update_sms_log( (int) $log->id, [
				'status'   => 'failed',
				'attempts' => $attempts,
				'error'    => $error,
			] );
			return;
		}

		VLT_DB::update_sms_log( (int) $log->id, [
			'attempts' => $attempts,
			'error'    => $error,
		] );
		wp_schedule_single_event( time() + self::RETRY_DELAYS[ $attempts ] * MINUTE_IN_SECONDS, self::CRON_HOOK, [ (int) $log->id ] );
	}

	/**
	 * @return string
	 */
	private static function render_message( $template, $lead, $video ) {
		return strtr( (string) $template, [
			'{name}'        => (string) $lead->primary_name,
			'{video_title}' => (string) ( $video->title ?: $video->video_key ),
			'{site_name}'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		] );
	}
}
