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
		// Rate limit: 60 requests / 60 s per IP.
		$ip = self::get_client_ip();
		if ( ! self::check_rate_limit( 'session_init', $ip, 60, 60 ) ) {
			return self::error( 'rate_limited', 'Too many requests.', 429 );
		}

		$body           = $request->get_json_params() ?: [];
		$visitor_uuid   = sanitize_text_field( $body['visitor_uuid']   ?? '' );
		$identity_token = sanitize_text_field( $body['identity_token'] ?? '' );
		$page_url       = esc_url_raw( $body['page_url']  ?? '' );
		$referrer       = esc_url_raw( $body['referrer']  ?? '' );
		$utm            = self::extract_utm( $body );

		$raw_ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$ip_hash   = self::hash_ip( $ip );
		$ua_stored = self::get_user_agent();
		$device    = self::detect_device( $raw_ua );
		$browser   = self::detect_browser( $raw_ua );
		$os        = self::detect_os( $raw_ua );
		$now       = current_time( 'mysql', true );

		$identity_invalid = false;
		$visitor_row      = null;
		$lead_id          = null;
		$token_to_return  = null;

		// ---- Attempt to restore existing visitor ----
		if ( $visitor_uuid && $identity_token ) {
			$visitor_row = self::validate_token( $visitor_uuid, $identity_token );
			if ( ! $visitor_row ) {
				$identity_invalid = true;
				$visitor_uuid     = '';
			}
		}

		if ( $visitor_row ) {
			// Known visitor — refresh last_seen_at, reuse token.
			$lead_id         = $visitor_row->lead_id ? (int) $visitor_row->lead_id : null;
			$token_to_return = $identity_token;

			VLT_DB::update_visitor( (int) $visitor_row->id, [
				'last_seen_at' => $now,
				'updated_at'   => $now,
			] );
		} else {
			// New visitor — generate fresh identity.
			$visitor_uuid    = self::generate_uuid();
			$token_to_return = self::generate_identity_token();

			VLT_DB::create_visitor( [
				'visitor_uuid'        => $visitor_uuid,
				'identity_token_hash' => self::hash_token( $token_to_return ),
				'first_seen_at'       => $now,
				'last_seen_at'        => $now,
				'created_at'          => $now,
				'updated_at'          => $now,
			] );
		}

		// ---- Always create a fresh session for this page load ----
		$session_uuid = self::generate_uuid();

		VLT_DB::create_session( [
			'session_uuid'    => $session_uuid,
			'visitor_uuid'    => $visitor_uuid,
			'lead_id'         => $lead_id,
			'started_at'      => $now,
			'landing_url'     => $page_url,
			'referrer'        => $referrer,
			'utm_source'      => $utm['utm_source'],
			'utm_medium'      => $utm['utm_medium'],
			'utm_campaign'    => $utm['utm_campaign'],
			'utm_content'     => $utm['utm_content'],
			'utm_term'        => $utm['utm_term'],
			'ip_hash'         => $ip_hash,
			'user_agent'      => $ua_stored,
			'device_type'     => $device,
			'browser'         => $browser,
			'os'              => $os,
			'created_at'      => $now,
			'updated_at'      => $now,
		] );

		$known_lead = ! is_null( $lead_id );

		return self::success( [
			'visitor_uuid'     => $visitor_uuid,
			'session_uuid'     => $session_uuid,
			'known_lead'       => $known_lead,
			'lead_id'          => $lead_id,
			'identity_token'   => $token_to_return,
			'show_form'        => ! $known_lead,
			'identity_invalid' => $identity_invalid,
		] );
	}

	public static function handle_lead_submit( WP_REST_Request $request ) {
		// Rate limit: 10 submissions / 5 min per IP.
		$ip = self::get_client_ip();
		if ( ! self::check_rate_limit( 'lead_submit', $ip, 10, 300 ) ) {
			return self::error( 'rate_limited', 'Too many requests.', 429 );
		}

		$body         = $request->get_json_params() ?: [];
		$visitor_uuid = sanitize_text_field( $body['visitor_uuid'] ?? '' );
		$session_uuid = sanitize_text_field( $body['session_uuid'] ?? '' );
		$name         = sanitize_text_field( $body['name']         ?? '' );
		$mobile_raw   = sanitize_text_field( $body['mobile']       ?? '' );

		// ---- Validate ----
		if ( '' === $name ) {
			return self::error( 'missing_name', 'Name is required.', 422 );
		}
		if ( '' === $mobile_raw ) {
			return self::error( 'missing_mobile', 'Mobile number is required.', 422 );
		}

		$normalized_mobile = self::normalize_mobile( $mobile_raw );
		if ( ! $normalized_mobile ) {
			return self::error( 'invalid_mobile', 'Invalid mobile number format.', 422 );
		}

		$mobile_hash = hash( 'sha256', $normalized_mobile );
		$ip_hash     = self::hash_ip( $ip );
		$ua_raw      = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$ua_hash     = self::hash_user_agent( $ua_raw );
		$now         = current_time( 'mysql', true );

		// ---- Find or create lead ----
		$lead    = VLT_DB::get_lead_by_mobile( $normalized_mobile );
		$lead_id = null;

		if ( $lead ) {
			$lead_id = (int) $lead->id;
			$update  = [ 'last_seen_at' => $now, 'updated_at' => $now ];

			// Promote primary_name only if it was blank.
			if ( empty( $lead->primary_name ) ) {
				$update['primary_name'] = $name;
			}
			VLT_DB::update_lead( $lead_id, $update );

		} else {
			$lead_id = VLT_DB::create_lead( [
				'primary_name'      => $name,
				'normalized_mobile' => $normalized_mobile,
				'mobile_hash'       => $mobile_hash,
				'is_verified'       => 0,
				'first_seen_at'     => $now,
				'last_seen_at'      => $now,
				'created_at'        => $now,
				'updated_at'        => $now,
			] );

			if ( ! $lead_id ) {
				VLT_Logger::error( 'Failed to create lead', 'lead_submit', [ 'mobile_hash' => $mobile_hash ] );
				return self::error( 'db_error', 'Could not create lead record.', 500 );
			}
		}

		// ---- Always record the submitted name in history ----
		VLT_DB::create_lead_name( [
			'lead_id'          => $lead_id,
			'submitted_name'   => $name,
			'normalized_mobile'=> $normalized_mobile,
			'visitor_uuid'     => $visitor_uuid ?: null,
			'session_uuid'     => $session_uuid ?: null,
			'ip_hash'          => $ip_hash,
			'user_agent_hash'  => $ua_hash,
			'submitted_at'     => $now,
		] );

		// ---- Generate fresh identity token ----
		$new_token  = self::generate_identity_token();
		$token_hash = self::hash_token( $new_token );

		// ---- Attach visitor & bulk-migrate anonymous data ----
		if ( $visitor_uuid ) {
			// Bulk-update: sessions, page_visits, video_events, video_ranges → lead_id.
			VLT_DB::attach_lead_to_visitor( $visitor_uuid, $lead_id );

			// Update visitor row with lead_id + new token hash.
			$visitor = VLT_DB::get_visitor_by_uuid( $visitor_uuid );
			if ( $visitor ) {
				VLT_DB::update_visitor( (int) $visitor->id, [
					'lead_id'             => $lead_id,
					'identity_token_hash' => $token_hash,
					'last_seen_at'        => $now,
					'updated_at'          => $now,
				] );
			}
		}

		return self::success( [
			'lead_id'           => $lead_id,
			'identity_token'    => $new_token,
			'normalized_mobile' => $normalized_mobile,
			'mobile_hash'       => $mobile_hash,
			'show_video'        => true,
		] );
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
	// Basic device / browser / OS detection
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

	public static function detect_browser( $ua ) {
		$ua = strtolower( (string) $ua );

		if ( strpos( $ua, 'opr/' ) !== false || strpos( $ua, 'opera' ) !== false ) return 'opera';
		if ( strpos( $ua, 'edg/' ) !== false || strpos( $ua, 'edge/' ) !== false )  return 'edge';
		if ( strpos( $ua, 'chrome/' ) !== false )                                   return 'chrome';
		if ( strpos( $ua, 'firefox/' ) !== false )                                  return 'firefox';
		if ( strpos( $ua, 'safari/' ) !== false )                                   return 'safari';
		if ( strpos( $ua, 'msie' ) !== false || strpos( $ua, 'trident/' ) !== false ) return 'ie';
		return 'other';
	}

	public static function detect_os( $ua ) {
		$ua = strtolower( (string) $ua );

		if ( strpos( $ua, 'iphone' ) !== false || strpos( $ua, 'ipad' ) !== false ) return 'ios';
		if ( strpos( $ua, 'android' ) !== false )                                   return 'android';
		if ( strpos( $ua, 'windows' ) !== false )                                   return 'windows';
		if ( strpos( $ua, 'mac os' ) !== false || strpos( $ua, 'darwin' ) !== false ) return 'macos';
		if ( strpos( $ua, 'linux' ) !== false )                                     return 'linux';
		return 'other';
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
	// Mobile normalization
	// -------------------------------------------------------------------------

	/**
	 * Normalize an Iranian mobile number to the canonical 98xxxxxxxxxx (12-digit) format.
	 * Accepts: 09xxxxxxxxx, 9xxxxxxxxx, +98xxxxxxxxxx, 0098xxxxxxxxxx, 98xxxxxxxxxx.
	 *
	 * @return string|null  Normalized number or null if invalid.
	 */
	public static function normalize_mobile( $mobile ) {
		$mobile = preg_replace( '/[^\d+]/', '', trim( (string) $mobile ) );

		// Strip leading + sign.
		if ( substr( $mobile, 0, 1 ) === '+' ) {
			$mobile = substr( $mobile, 1 );
		}

		// Strip leading 00 country-code prefix.
		if ( substr( $mobile, 0, 2 ) === '00' ) {
			$mobile = substr( $mobile, 2 );
		}

		// 09xxxxxxxxx (11 digits, local format) → 98xxxxxxxxxx
		if ( substr( $mobile, 0, 2 ) === '09' && strlen( $mobile ) === 11 ) {
			$mobile = '98' . substr( $mobile, 1 );
		}

		// 9xxxxxxxxx (10 digits, no leading 0) → 98xxxxxxxxxx
		if ( substr( $mobile, 0, 1 ) === '9' && strlen( $mobile ) === 10 ) {
			$mobile = '98' . $mobile;
		}

		return preg_match( '/^98\d{10}$/', $mobile ) ? $mobile : null;
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
