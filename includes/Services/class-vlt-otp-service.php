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

		if ( ! self::dispatch( $normalized_mobile, $message ) ) {
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

	/**
	 * Route to the configured SMS provider and send the message.
	 *
	 * @return bool  true on success, false on failure.
	 */
	private static function dispatch( $mobile, $message ) {
		$provider = sanitize_key( (string) VLT_Settings::get( 'otp_provider' ) );
		$api_key  = (string) VLT_Settings::get( 'otp_api_key' );
		$sender   = (string) VLT_Settings::get( 'otp_sender' );
		$username = (string) VLT_Settings::get( 'otp_username' );

		switch ( $provider ) {
			case 'payamito':
				return self::send_payamito( $mobile, $message, $username, $api_key, $sender );
			case 'kavenegar':
				return self::send_kavenegar( $mobile, $message, $api_key, $sender );
			case 'sms_ir':
				return self::send_smsir( $mobile, $message, $api_key, $sender );
			default:
				// No real provider — log the code so it can be tested without an SMS account.
				VLT_Logger::info( 'OTP (no provider): ' . $message, 'otp' );
				return true;
		}
	}

	// -------------------------------------------------------------------------
	// Provider implementations
	// -------------------------------------------------------------------------

	/**
	 * Send via Payamito SmartSMS REST API.
	 * Docs: https://rest.payamak-panel.com/api/SmartSMS/Send
	 * username = panel username; api_key = ApiKey from developer settings (used as password).
	 */
	private static function send_payamito( $mobile, $message, $username, $api_key, $sender ) {
		if ( ! $username || ! $api_key || ! $sender ) {
			VLT_Logger::error( 'Payamito: username, API key, or sender not configured.', 'otp' );
			return false;
		}

		$response = wp_remote_post(
			'https://rest.payamak-panel.com/api/SmartSMS/Send',
			[
				'body'    => [
					'username' => $username,
					'password' => $api_key,
					'to'       => $mobile,
					'text'     => $message,
					'from'     => $sender,
				],
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			VLT_Logger::error( 'Payamito HTTP error: ' . $response->get_error_message(), 'otp' );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Success: RetStatus = 1 and StrRetStatus = "Ok".
		if ( 200 === $code && isset( $data['RetStatus'] ) && 1 === (int) $data['RetStatus'] ) {
			VLT_Logger::info( 'Payamito OTP sent. Value: ' . ( $data['Value'] ?? '' ), 'otp' );
			return true;
		}

		// Log the error code from Value for diagnosis.
		$error_value = $data['Value'] ?? $data['RetStatus'] ?? 'unknown';
		VLT_Logger::error( 'Payamito send failed. Value/RetStatus: ' . $error_value, 'otp' );
		return false;
	}

	private static function send_kavenegar( $mobile, $message, $api_key, $sender ) {
		if ( ! $api_key ) {
			VLT_Logger::error( 'Kavenegar: API key not configured.', 'otp' );
			return false;
		}

		$url = 'https://api.kavenegar.com/v1/' . rawurlencode( $api_key ) . '/sms/send.json';

		$response = wp_remote_post( $url, [
			'body'    => [
				'receptor' => $mobile,
				'sender'   => $sender,
				'message'  => $message,
			],
			'timeout' => 10,
		] );

		if ( is_wp_error( $response ) ) {
			VLT_Logger::error( 'Kavenegar HTTP error: ' . $response->get_error_message(), 'otp' );
			return false;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			$msg  = isset( $data['return']['message'] ) ? $data['return']['message'] : $body;
			VLT_Logger::error( 'Kavenegar send failed. HTTP: ' . $http_code . ' — ' . $msg, 'otp' );
			return false;
		}

		return true;
	}

	private static function send_smsir( $mobile, $message, $api_key, $sender = '' ) {
		if ( ! $api_key ) {
			VLT_Logger::error( 'SMS.ir: API key not configured.', 'otp' );
			return false;
		}

		$response = wp_remote_post( 'https://api.sms.ir/v1/send/bulk', [
			'headers' => [
				'Content-Type' => 'application/json',
				'x-api-key'    => $api_key,
			],
			'body'    => wp_json_encode( [
				'lineNumber' => $sender ?: '',
				'messages'   => [ $message ],
				'mobiles'    => [ $mobile ],
			] ),
			'timeout' => 10,
		] );

		if ( is_wp_error( $response ) ) {
			VLT_Logger::error( 'SMS.ir HTTP error: ' . $response->get_error_message(), 'otp' );
			return false;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			$msg  = isset( $data['message'] ) ? $data['message'] : $body;
			VLT_Logger::error( 'SMS.ir send failed. HTTP: ' . $http_code . ' — ' . $msg, 'otp' );
			return false;
		}

		return true;
	}
}
