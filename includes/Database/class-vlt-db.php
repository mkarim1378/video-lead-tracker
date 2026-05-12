<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_DB {

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = $wpdb->get_charset_collate();

		foreach ( self::schema( $wpdb->prefix, $collate ) as $sql ) {
			dbDelta( $sql );
		}
	}

	public static function get_version() {
		return get_option( 'vlt_db_version', '0' );
	}

	public static function needs_upgrade() {
		return version_compare( self::get_version(), VLT_DB_VERSION, '<' );
	}

	// -------------------------------------------------------------------------
	// Core schema (11 tables)
	// -------------------------------------------------------------------------

	private static function schema( $p, $collate ) {
		return [

			"CREATE TABLE {$p}vlt_leads (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  primary_name varchar(190) DEFAULT NULL,
  normalized_mobile varchar(32) NOT NULL,
  mobile_hash char(64) NOT NULL,
  is_verified tinyint(1) NOT NULL DEFAULT 0,
  first_seen_at datetime NOT NULL,
  last_seen_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY normalized_mobile (normalized_mobile),
  KEY mobile_hash (mobile_hash),
  KEY first_seen_at (first_seen_at),
  KEY last_seen_at (last_seen_at)
) $collate;",

			"CREATE TABLE {$p}vlt_lead_names (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id bigint(20) UNSIGNED NOT NULL,
  submitted_name varchar(190) NOT NULL,
  normalized_mobile varchar(32) NOT NULL,
  visitor_uuid char(36) DEFAULT NULL,
  session_uuid char(36) DEFAULT NULL,
  ip_hash char(64) DEFAULT NULL,
  user_agent_hash char(64) DEFAULT NULL,
  submitted_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY lead_id (lead_id),
  KEY normalized_mobile (normalized_mobile),
  KEY submitted_at (submitted_at)
) $collate;",

			"CREATE TABLE {$p}vlt_visitors (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  visitor_uuid char(36) NOT NULL,
  lead_id bigint(20) UNSIGNED DEFAULT NULL,
  identity_token_hash char(64) DEFAULT NULL,
  first_seen_at datetime NOT NULL,
  last_seen_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY visitor_uuid (visitor_uuid),
  KEY lead_id (lead_id),
  KEY last_seen_at (last_seen_at)
) $collate;",

			"CREATE TABLE {$p}vlt_sessions (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  session_uuid char(36) NOT NULL,
  visitor_uuid char(36) NOT NULL,
  lead_id bigint(20) UNSIGNED DEFAULT NULL,
  started_at datetime NOT NULL,
  ended_at datetime DEFAULT NULL,
  last_activity_at datetime DEFAULT NULL,
  landing_url text DEFAULT NULL,
  referrer text DEFAULT NULL,
  utm_source varchar(190) DEFAULT NULL,
  utm_medium varchar(190) DEFAULT NULL,
  utm_campaign varchar(190) DEFAULT NULL,
  utm_content varchar(190) DEFAULT NULL,
  utm_term varchar(190) DEFAULT NULL,
  ip_hash char(64) DEFAULT NULL,
  user_agent text DEFAULT NULL,
  device_type varchar(50) DEFAULT NULL,
  browser varchar(100) DEFAULT NULL,
  os varchar(100) DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY session_uuid (session_uuid),
  KEY visitor_uuid (visitor_uuid),
  KEY lead_id (lead_id),
  KEY started_at (started_at),
  KEY last_activity_at (last_activity_at),
  KEY utm_source (utm_source),
  KEY utm_campaign (utm_campaign)
) $collate;",

			"CREATE TABLE {$p}vlt_page_visits (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  session_uuid char(36) NOT NULL,
  visitor_uuid char(36) NOT NULL,
  lead_id bigint(20) UNSIGNED DEFAULT NULL,
  page_id bigint(20) UNSIGNED DEFAULT NULL,
  page_url text NOT NULL,
  event_type varchar(50) NOT NULL,
  event_at datetime NOT NULL,
  time_on_page_seconds int(10) UNSIGNED DEFAULT NULL,
  metadata longtext DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY session_uuid (session_uuid),
  KEY visitor_uuid (visitor_uuid),
  KEY lead_id (lead_id),
  KEY event_type (event_type),
  KEY event_at (event_at)
) $collate;",

			"CREATE TABLE {$p}vlt_videos (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  video_key varchar(190) NOT NULL,
  title varchar(255) DEFAULT NULL,
  page_id bigint(20) UNSIGNED DEFAULT NULL,
  video_url text DEFAULT NULL,
  duration_seconds int(10) UNSIGNED DEFAULT NULL,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY video_key (video_key),
  KEY page_id (page_id),
  KEY is_active (is_active)
) $collate;",

			"CREATE TABLE {$p}vlt_video_events (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  video_id bigint(20) UNSIGNED NOT NULL,
  session_uuid char(36) NOT NULL,
  visitor_uuid char(36) NOT NULL,
  lead_id bigint(20) UNSIGNED DEFAULT NULL,
  event_type varchar(50) NOT NULL,
  video_time_seconds decimal(10,3) DEFAULT NULL,
  from_second decimal(10,3) DEFAULT NULL,
  to_second decimal(10,3) DEFAULT NULL,
  playback_rate decimal(5,2) DEFAULT NULL,
  duration_seconds decimal(10,3) DEFAULT NULL,
  event_at datetime NOT NULL,
  metadata longtext DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY video_id (video_id),
  KEY session_uuid (session_uuid),
  KEY visitor_uuid (visitor_uuid),
  KEY lead_id (lead_id),
  KEY event_type (event_type),
  KEY event_at (event_at),
  KEY video_time_seconds (video_time_seconds)
) $collate;",

			"CREATE TABLE {$p}vlt_video_ranges (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  video_id bigint(20) UNSIGNED NOT NULL,
  session_uuid char(36) NOT NULL,
  visitor_uuid char(36) NOT NULL,
  lead_id bigint(20) UNSIGNED DEFAULT NULL,
  from_second decimal(10,3) NOT NULL,
  to_second decimal(10,3) NOT NULL,
  duration_seconds decimal(10,3) NOT NULL,
  playback_rate decimal(5,2) DEFAULT NULL,
  committed_reason varchar(50) DEFAULT NULL,
  event_at datetime NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY video_id (video_id),
  KEY session_uuid (session_uuid),
  KEY visitor_uuid (visitor_uuid),
  KEY lead_id (lead_id),
  KEY from_second (from_second),
  KEY to_second (to_second),
  KEY event_at (event_at)
) $collate;",

			"CREATE TABLE {$p}vlt_video_user_summary (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  video_id bigint(20) UNSIGNED NOT NULL,
  lead_id bigint(20) UNSIGNED DEFAULT NULL,
  visitor_uuid char(36) DEFAULT NULL,
  sessions_count int(10) UNSIGNED NOT NULL DEFAULT 0,
  started tinyint(1) NOT NULL DEFAULT 0,
  reached_end tinyint(1) NOT NULL DEFAULT 0,
  first_play_at datetime DEFAULT NULL,
  last_activity_at datetime DEFAULT NULL,
  total_watch_seconds decimal(12,3) NOT NULL DEFAULT 0.000,
  unique_watch_seconds decimal(12,3) NOT NULL DEFAULT 0.000,
  max_video_time_seconds decimal(10,3) NOT NULL DEFAULT 0.000,
  unique_watch_percent decimal(6,2) NOT NULL DEFAULT 0.00,
  raw_ranges_json longtext DEFAULT NULL,
  merged_ranges_json longtext DEFAULT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY video_id (video_id),
  KEY lead_id (lead_id),
  KEY visitor_uuid (visitor_uuid),
  KEY started (started),
  KEY reached_end (reached_end),
  KEY unique_watch_percent (unique_watch_percent),
  KEY last_activity_at (last_activity_at)
) $collate;",

			"CREATE TABLE {$p}vlt_video_heatmap (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  video_id bigint(20) UNSIGNED NOT NULL,
  second_index int(10) UNSIGNED NOT NULL,
  total_views_count bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  unique_leads_count bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  unique_visitors_count bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY video_second (video_id,second_index),
  KEY video_id (video_id),
  KEY second_index (second_index)
) $collate;",

			"CREATE TABLE {$p}vlt_logs (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  level varchar(20) NOT NULL,
  context varchar(100) DEFAULT NULL,
  message text NOT NULL,
  metadata longtext DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY level (level),
  KEY context (context),
  KEY created_at (created_at)
) $collate;",

		];
	}

	// -------------------------------------------------------------------------
	// Optional tables (created on demand when features are enabled)
	// -------------------------------------------------------------------------

	public static function create_otp_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		dbDelta( "CREATE TABLE {$p}vlt_otp_codes (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  normalized_mobile varchar(32) NOT NULL,
  otp_hash char(64) NOT NULL,
  visitor_uuid char(36) DEFAULT NULL,
  session_uuid char(36) DEFAULT NULL,
  expires_at datetime NOT NULL,
  verified_at datetime DEFAULT NULL,
  attempts_count int(10) UNSIGNED NOT NULL DEFAULT 0,
  ip_hash char(64) DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY normalized_mobile (normalized_mobile),
  KEY visitor_uuid (visitor_uuid),
  KEY expires_at (expires_at),
  KEY created_at (created_at)
) $collate;" );
	}

	// -------------------------------------------------------------------------
	// Generic helpers
	// -------------------------------------------------------------------------

	/**
	 * Insert a row, automatically skipping null values so DB columns keep their DEFAULT NULL.
	 * All values are treated as strings (%s); MySQL coerces types correctly on insert.
	 *
	 * @return int|false  New row ID or false on failure.
	 */
	private static function insert( $table, array $data ) {
		global $wpdb;

		$clean  = array_filter( $data, function ( $v ) { return $v !== null; } );
		$format = array_fill( 0, count( $clean ), '%s' );

		$wpdb->insert( $wpdb->prefix . $table, $clean, $format );

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update rows matching $where, skipping null values in $data.
	 *
	 * @return bool
	 */
	private static function update_where( $table, array $data, array $where ) {
		global $wpdb;

		$clean        = array_filter( $data, function ( $v ) { return $v !== null; } );
		$data_format  = array_fill( 0, count( $clean ), '%s' );
		$where_format = array_fill( 0, count( $where ), '%s' );

		return false !== $wpdb->update( $wpdb->prefix . $table, $clean, $where, $data_format, $where_format );
	}

	// -------------------------------------------------------------------------
	// Visitors
	// -------------------------------------------------------------------------

	public static function create_visitor( array $data ) {
		return self::insert( 'vlt_visitors', $data );
	}

	/**
	 * @return object|null
	 */
	public static function get_visitor_by_uuid( $visitor_uuid ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . $wpdb->prefix . 'vlt_visitors WHERE visitor_uuid = %s LIMIT 1',
			$visitor_uuid
		) );
	}

	public static function update_visitor( $id, array $data ) {
		return self::update_where( 'vlt_visitors', $data, [ 'id' => $id ] );
	}

	// -------------------------------------------------------------------------
	// Sessions
	// -------------------------------------------------------------------------

	public static function create_session( array $data ) {
		return self::insert( 'vlt_sessions', $data );
	}

	public static function update_session( $session_uuid, array $data ) {
		return self::update_where( 'vlt_sessions', $data, [ 'session_uuid' => $session_uuid ] );
	}

	// -------------------------------------------------------------------------
	// Leads (stubs — full implementation Phase 8)
	// -------------------------------------------------------------------------

	/**
	 * @return object|null
	 */
	public static function get_lead_by_mobile( $normalized_mobile ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . $wpdb->prefix . 'vlt_leads WHERE normalized_mobile = %s LIMIT 1',
			$normalized_mobile
		) );
	}

	public static function create_lead( array $data ) {
		return self::insert( 'vlt_leads', $data );
	}

	public static function update_lead( $id, array $data ) {
		return self::update_where( 'vlt_leads', $data, [ 'id' => $id ] );
	}

	public static function create_lead_name( array $data ) {
		return self::insert( 'vlt_lead_names', $data );
	}

	// -------------------------------------------------------------------------
	// Bulk lead attachment (after form submit)
	// -------------------------------------------------------------------------

	/**
	 * Attach a lead_id to all anonymous records belonging to a visitor.
	 * Used in Phase 8 when a visitor submits the form.
	 */
	public static function attach_lead_to_visitor( $visitor_uuid, $lead_id ) {
		global $wpdb;

		$lead_id      = (int) $lead_id;
		$visitor_uuid = sanitize_text_field( $visitor_uuid );

		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . $wpdb->prefix . 'vlt_visitors SET lead_id = %d, updated_at = %s WHERE visitor_uuid = %s',
			$lead_id,
			current_time( 'mysql', true ),
			$visitor_uuid
		) );

		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . $wpdb->prefix . 'vlt_sessions SET lead_id = %d, updated_at = %s WHERE visitor_uuid = %s AND lead_id IS NULL',
			$lead_id,
			current_time( 'mysql', true ),
			$visitor_uuid
		) );

		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . $wpdb->prefix . 'vlt_page_visits SET lead_id = %d WHERE visitor_uuid = %s AND lead_id IS NULL',
			$lead_id,
			$visitor_uuid
		) );

		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . $wpdb->prefix . 'vlt_video_events SET lead_id = %d WHERE visitor_uuid = %s AND lead_id IS NULL',
			$lead_id,
			$visitor_uuid
		) );

		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . $wpdb->prefix . 'vlt_video_ranges SET lead_id = %d WHERE visitor_uuid = %s AND lead_id IS NULL',
			$lead_id,
			$visitor_uuid
		) );
	}

	// -------------------------------------------------------------------------

	public static function create_heatmap_uniques_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		dbDelta( "CREATE TABLE {$p}vlt_video_heatmap_uniques (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  video_id bigint(20) UNSIGNED NOT NULL,
  second_index int(10) UNSIGNED NOT NULL,
  lead_id bigint(20) UNSIGNED DEFAULT NULL,
  visitor_uuid char(36) DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY video_second_lead (video_id,second_index,lead_id),
  KEY video_id (video_id),
  KEY lead_id (lead_id),
  KEY visitor_uuid (visitor_uuid)
) $collate;" );
	}
}
