<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_REST_Controller {

	const NAMESPACE = 'vlt/v1';

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	public static function init() {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	// -------------------------------------------------------------------------
	// Route registration
	// -------------------------------------------------------------------------

	public static function register_routes() {
		$ns = self::NAMESPACE;

		register_rest_route( $ns, '/session/init', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'handle_session_init' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $ns, '/lead/submit', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'handle_lead_submit' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $ns, '/track/page', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'handle_track_page' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $ns, '/track/video-event', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'handle_track_video_event' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $ns, '/track/video-range', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'handle_track_video_range' ],
			'permission_callback' => '__return_true',
		] );
	}

	// -------------------------------------------------------------------------
	// Handlers — implemented in Phases 7, 8, 10, 11, 12
	// -------------------------------------------------------------------------

	public static function handle_session_init( WP_REST_Request $request ) {
		// Full implementation: Phase 7
		return self::error( 'not_implemented', 'Session init coming in Phase 7.', 501 );
	}

	public static function handle_lead_submit( WP_REST_Request $request ) {
		// Full implementation: Phase 8
		return self::error( 'not_implemented', 'Lead submit coming in Phase 8.', 501 );
	}

	public static function handle_track_page( WP_REST_Request $request ) {
		// Full implementation: Phase 10
		return self::error( 'not_implemented', 'Page tracking coming in Phase 10.', 501 );
	}

	public static function handle_track_video_event( WP_REST_Request $request ) {
		// Full implementation: Phase 11
		return self::error( 'not_implemented', 'Video event tracking coming in Phase 11.', 501 );
	}

	public static function handle_track_video_range( WP_REST_Request $request ) {
		// Full implementation: Phase 12
		return self::error( 'not_implemented', 'Video range tracking coming in Phase 12.', 501 );
	}

	// -------------------------------------------------------------------------
	// Response helpers
	// -------------------------------------------------------------------------

	public static function success( array $data = [] ) {
		return new WP_REST_Response( array_merge( [ 'success' => true ], $data ), 200 );
	}

	public static function error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, [ 'status' => $status ] );
	}

	// -------------------------------------------------------------------------
	// Request helpers
	// -------------------------------------------------------------------------

	/**
	 * Read a param from the JSON request body.
	 */
	public static function param( WP_REST_Request $request, $key, $default = null ) {
		$body = $request->get_json_params();
		return $body[ $key ] ?? $default;
	}

	/**
	 * Read and sanitize a string param.
	 */
	public static function str_param( WP_REST_Request $request, $key, $default = '' ) {
		return sanitize_text_field( (string) self::param( $request, $key, $default ) );
	}

	/**
	 * Read and cast a float param.
	 */
	public static function float_param( WP_REST_Request $request, $key, $default = null ) {
		$val = self::param( $request, $key );
		return is_numeric( $val ) ? (float) $val : $default;
	}

	// -------------------------------------------------------------------------
	// Identity token
	// -------------------------------------------------------------------------

	/**
	 * Generate a random opaque identity token (sent to client, not stored).
	 */
	public static function generate_identity_token() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Hash a token for safe storage (SHA-256 keyed with WP auth salt).
	 */
	public static function hash_token( $token ) {
		return hash( 'sha256', $token . wp_salt( 'secure_auth' ) );
	}

	/**
	 * Validate a visitor's identity token against the stored hash.
	 *
	 * @return object|null  Visitor row or null if invalid.
	 */
	public static function validate_token( $visitor_uuid, $token ) {
		global $wpdb;

		if ( ! $visitor_uuid || ! $token ) {
			return null;
		}

		$hash = self::hash_token( $token );

		return $wpdb->get_row( $wpdb->prepare(
			'SELECT id, lead_id, identity_token_hash FROM ' . $wpdb->prefix . 'vlt_visitors
			 WHERE visitor_uuid = %s AND identity_token_hash = %s
			 LIMIT 1',
			$visitor_uuid,
			$hash
		) );
	}

	// -------------------------------------------------------------------------
	// UUID
	// -------------------------------------------------------------------------

	public static function generate_uuid() {
		return wp_generate_uuid4();
	}

	// -------------------------------------------------------------------------
	// IP / User-agent
	// -------------------------------------------------------------------------

	public static function get_client_ip() {
		$candidates = [
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		];

		foreach ( $candidates as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			$ip = trim( explode( ',', $_SERVER[ $header ] )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return '';
	}

	/**
	 * Returns null | raw IP | SHA-256 hash depending on the ip_storage_mode setting.
	 */
	public static function hash_ip( $ip ) {
		$mode = VLT_Settings::get( 'ip_storage_mode' );

		if ( 'disabled' === $mode || '' === $ip ) {
			return null;
		}
		if ( 'raw' === $mode ) {
			return sanitize_text_field( $ip );
		}
		// Default: hash
		return hash( 'sha256', $ip . wp_salt( 'auth' ) );
	}

	/**
	 * Returns null or raw UA string depending on store_user_agent setting.
	 */
	public static function get_user_agent() {
		if ( ! VLT_Settings::get( 'store_user_agent' ) ) {
			return null;
		}
		return sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	}

	/**
	 * SHA-256 hash of the raw user-agent string (for lead_names table).
	 */
	public static function hash_user_agent( $ua ) {
		if ( ! $ua ) {
			return null;
		}
		return hash( 'sha256', $ua );
	}

	// -------------------------------------------------------------------------
	// Basic device detection
	// -------------------------------------------------------------------------

	public static function detect_device( $ua ) {
		$ua = strtolower( (string) $ua );

		if ( strpos( $ua, 'ipad' ) !== false || strpos( $ua, 'tablet' ) !== false ) {
			return 'tablet';
		}
		if ( strpos( $ua, 'mobile' ) !== false || strpos( $ua, 'android' ) !== false
			|| strpos( $ua, 'iphone' ) !== false ) {
			return 'mobile';
		}
		return 'desktop';
	}

	// -------------------------------------------------------------------------
	// UTM extraction
	// -------------------------------------------------------------------------

	public static function extract_utm( array $body ) {
		$keys   = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ];
		$utm    = is_array( $body['utm'] ?? null ) ? $body['utm'] : [];
		$result = [];

		foreach ( $keys as $key ) {
			$result[ $key ] = sanitize_text_field( (string) ( $utm[ $key ] ?? '' ) ) ?: null;
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Rate limiting (transient-based, per IP or per mobile)
	// -------------------------------------------------------------------------

	/**
	 * Returns true if the request is within the allowed rate, false if it exceeds it.
	 *
	 * @param string $type       Label for the bucket (e.g. 'session_init', 'lead_submit').
	 * @param string $identifier IP address, mobile hash, or any string key.
	 * @param int    $limit      Max requests allowed in the window.
	 * @param int    $window     Window size in seconds.
	 */
	public static function check_rate_limit( $type, $identifier, $limit, $window ) {
		$key   = 'vlt_rl_' . $type . '_' . md5( $identifier );
		$count = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		// Increment. If first hit, also set the TTL window.
		set_transient( $key, $count + 1, $window );

		return true;
	}
}
