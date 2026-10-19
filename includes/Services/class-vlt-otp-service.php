<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_OTP_Service {

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Generate and send an OTP for the given (already-normalized) mobile number.
	 *
	 * @return true|WP_Error
	 */
	public static function send( $normalized_mobile, $visitor_uuid = null, $session_uuid = null, $ip_hash = null ) {
		global $wpdb;

		// Ensure the optional table exists.
		VLT_DB::create_otp_table();

		// Enforce resend cooldown per mobile.
		$cooldown = (int) VLT_Settings::get( 'otp_resend_cooldown' );
		$last_at  = $wpdb->get_var( $wpdb->prepare(
			'SELECT created_at FROM ' . $wpdb->prefix . 'vlt_otp_codes
			 WHERE normalized_mobile = %s
			 ORDER BY created_at DESC LIMIT 1',
			$normalized_mobile
		) );

		if ( $last_at ) {
			$elapsed = time() - (int) strtotime( $last_at );
			if ( $elapsed < $cooldown ) {
				$wait = $cooldown - $elapsed;
				return new WP_Error( 'cooldown', sprintf( __( 'Please wait %d seconds before requesting a new code.', 'video-lead-tracker' ), $wait ) );
			}
		}

		// Generate code and store its hash.
		$code    = self::generate_code();
		$hash    = self::hash_code( $code );
		$expiry  = (int) VLT_Settings::get( 'otp_expiry' );
		$now     = current_time( 'mysql', true );
		$expires = gmdate( 'Y-m-d H:i:s', time() + $expiry );

		$wpdb->insert(
			$wpdb->prefix . 'vlt_otp_codes',
			array_filter( [
				'normalized_mobile' => $normalized_mobile,
				'otp_hash'          => $hash,
				'visitor_uuid'      => $visitor_uuid,
				'session_uuid'      => $session_uuid,
				'expires_at'        => $expires,
				'ip_hash'           => $ip_hash,
				'created_at'        => $now,
			], function ( $v ) { return $v !== null; } ),
			'%s'
		);

		// Build and send the message.
		$template = (string) VLT_Settings::get( 'otp_template' );
		$message  = str_replace( '{code}', $code, $template );

		if ( is_wp_error( VLT_SMS_Gateway::send( $normalized_mobile, $message, 'otp' ) ) ) {
			VLT_Logger::error( 'OTP send failed', 'otp', [ 'mobile_hash' => hash( 'sha256', $normalized_mobile ) ] );
			return new WP_Error( 'send_failed', __( 'Failed to send verification code. Please try again.', 'video-lead-tracker' ) );
		}

		return true;
	}

	/**
	 * Verify an OTP code.
	 * Does NOT mark the lead as verified — the REST handler does that after this returns true.
	 *
	 * @return true|WP_Error
	 */
	public static function verify( $normalized_mobile, $code ) {
		global $wpdb;

		$max_attempts = (int) VLT_Settings::get( 'otp_max_attempts' );
		$now          = current_time( 'mysql', true );

		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . $wpdb->prefix . 'vlt_otp_codes
			 WHERE normalized_mobile = %s
			   AND expires_at > %s
			   AND verified_at IS NULL
			 ORDER BY created_at DESC LIMIT 1',
			$normalized_mobile,
			$now
		) );

		if ( ! $row ) {
			return new WP_Error( 'otp_expired', __( 'Verification code has expired or does not exist.', 'video-lead-tracker' ) );
		}

		if ( (int) $row->attempts_count >= $max_attempts ) {
			return new WP_Error( 'otp_locked', __( 'Maximum attempts exceeded. Please request a new code.', 'video-lead-tracker' ) );
		}

		// Increment attempt counter before checking the code to prevent brute-force.
		$wpdb->update(
			$wpdb->prefix . 'vlt_otp_codes',
			[ 'attempts_count' => (int) $row->attempts_count + 1 ],
			[ 'id' => $row->id ],
			[ '%d' ],
			[ '%d' ]
		);

		if ( ! hash_equals( $row->otp_hash, self::hash_code( $code ) ) ) {
			return new WP_Error( 'otp_invalid', __( 'Invalid verification code.', 'video-lead-tracker' ) );
		}

		// Mark as verified.
		$wpdb->update(
			$wpdb->prefix . 'vlt_otp_codes',
			[ 'verified_at' => $now ],
			[ 'id' => $row->id ],
			[ '%s' ],
			[ '%d' ]
		);

		return true;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function generate_code() {
		return str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
	}

	private static function hash_code( $code ) {
		return hash( 'sha256', $code . wp_salt( 'secure_auth' ) );
	}
}
