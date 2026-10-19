<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared SMS gateway used by OTP and video SMS triggers.
 * Credentials live under the otp_* settings keys (kept for backward compatibility).
 */
class VLT_SMS_Gateway {

	/**
	 * Provider keys → labels for the settings dropdown.
	 *
	 * @return array
	 */
	public static function providers() {
		return [
			'payamito'  => __( 'Payamito (پیامیتو)', 'video-lead-tracker' ),
			'kavenegar' => __( 'Kavenegar (کاوه‌نگار)', 'video-lead-tracker' ),
			'sms_ir'    => __( 'SMS.ir', 'video-lead-tracker' ),
			'none'      => __( 'No provider (log only, for testing)', 'video-lead-tracker' ),
		];
	}

	/**
	 * Whether the selected provider has the credentials it needs.
	 * The log-only provider always counts as configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$provider = sanitize_key( (string) VLT_Settings::get( 'otp_provider' ) );
		$api_key  = (string) VLT_Settings::get( 'otp_api_key' );

		switch ( $provider ) {
			case 'payamito':
				return $api_key && VLT_Settings::get( 'otp_username' ) && VLT_Settings::get( 'otp_sender' );
			case 'kavenegar':
			case 'sms_ir':
				return (bool) $api_key;
			default:
				return true;
		}
	}

	/**
	 * Send a plain-text SMS through the configured provider.
	 *
	 * @param string $mobile   Normalized mobile (98XXXXXXXXXX).
	 * @param string $message
	 * @param string $context  Log context (e.g. 'otp', 'sms_trigger').
	 * @return true|WP_Error
	 */
	public static function send( $mobile, $message, $context = 'sms' ) {
		$provider = sanitize_key( (string) VLT_Settings::get( 'otp_provider' ) );
		$api_key  = (string) VLT_Settings::get( 'otp_api_key' );
		$sender   = (string) VLT_Settings::get( 'otp_sender' );
		$username = (string) VLT_Settings::get( 'otp_username' );

		switch ( $provider ) {
			case 'payamito':
				$result = self::send_payamito( $mobile, $message, $username, $api_key, $sender );
				break;
			case 'kavenegar':
				$result = self::send_kavenegar( $mobile, $message, $api_key, $sender );
				break;
			case 'sms_ir':
				$result = self::send_smsir( $mobile, $message, $api_key, $sender );
				break;
			default:
				// No real provider — log the message so it can be tested without an SMS account.
				VLT_Logger::info( 'SMS (no provider): ' . $message, $context, [ 'mobile_hash' => hash( 'sha256', $mobile ) ] );
				return true;
		}

		if ( is_wp_error( $result ) ) {
			VLT_Logger::error( $result->get_error_message(), $context );
		}

		return $result;
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
			return new WP_Error( 'not_configured', 'Payamito: username, API key, or sender not configured.' );
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
			return new WP_Error( 'http_error', 'Payamito HTTP error: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		// Success: RetStatus = 1 and StrRetStatus = "Ok".
		if ( 200 === $code && isset( $data['RetStatus'] ) && 1 === (int) $data['RetStatus'] ) {
			return true;
		}

		$error_value = $data['Value'] ?? $data['RetStatus'] ?? 'unknown';
		return new WP_Error( 'send_failed', 'Payamito send failed. Value/RetStatus: ' . $error_value );
	}

	private static function send_kavenegar( $mobile, $message, $api_key, $sender ) {
		if ( ! $api_key ) {
			return new WP_Error( 'not_configured', 'Kavenegar: API key not configured.' );
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
			return new WP_Error( 'http_error', 'Kavenegar HTTP error: ' . $response->get_error_message() );
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			$msg  = isset( $data['return']['message'] ) ? $data['return']['message'] : $body;
			return new WP_Error( 'send_failed', 'Kavenegar send failed. HTTP: ' . $http_code . ' — ' . $msg );
		}

		return true;
	}

	private static function send_smsir( $mobile, $message, $api_key, $sender = '' ) {
		if ( ! $api_key ) {
			return new WP_Error( 'not_configured', 'SMS.ir: API key not configured.' );
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
			return new WP_Error( 'http_error', 'SMS.ir HTTP error: ' . $response->get_error_message() );
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			$msg  = isset( $data['message'] ) ? $data['message'] : $body;
			return new WP_Error( 'send_failed', 'SMS.ir send failed. HTTP: ' . $http_code . ' — ' . $msg );
		}

		return true;
	}
}
