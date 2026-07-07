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
  poster_url text DEFAULT NULL,
  audio_url text DEFAULT NULL,
  duration_seconds int(10) UNSIGNED DEFAULT NULL,
  form_title varchar(255) DEFAULT NULL,
  name_label varchar(190) DEFAULT NULL,
  mobile_label varchar(190) DEFAULT NULL,
  submit_button_text varchar(190) DEFAULT NULL,
  success_message text DEFAULT NULL,
  enable_otp tinyint(1) NOT NULL DEFAULT 0,
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
  last_position decimal(10,3) DEFAULT NULL,
  raw_ranges_json longtext DEFAULT NULL,
  merged_ranges_json longtext DEFAULT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY video_id (video_id),
  KEY lead_id (lead_id),
  KEY visitor_uuid (visitor_uuid),
  KEY video_lead (video_id,lead_id),
  KEY video_visitor (video_id,visitor_uuid),
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
	// Videos
	// -------------------------------------------------------------------------

	/**
	 * @return object|null
	 */
	public static function get_video_by_key( $video_key ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . $wpdb->prefix . 'vlt_videos WHERE video_key = %s LIMIT 1',
			$video_key
		) );
	}

	public static function create_video( array $data ) {
		return self::insert( 'vlt_videos', $data );
	}

	public static function update_video( $id, array $data ) {
		return self::update_where( 'vlt_videos', $data, [ 'id' => $id ] );
	}

	/**
	 * @return object[]
	 */
	public static function get_all_videos() {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT * FROM ' . $wpdb->prefix . 'vlt_videos ORDER BY id ASC'
		);
	}

	/**
	 * @return object|null  First registered video.
	 */
	public static function get_first_video() {
		global $wpdb;
		return $wpdb->get_row(
			'SELECT * FROM ' . $wpdb->prefix . 'vlt_videos ORDER BY id ASC LIMIT 1'
		);
	}

	public static function delete_video( $id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'vlt_videos', [ 'id' => (int) $id ], [ '%d' ] );
	}

	/**
	 * Migrate single-video Settings → vlt_videos registry (runs once on 1.1 → 1.2 upgrade).
	 * Only inserts when the table is empty so it never double-inserts.
	 */
	public static function maybe_seed_video_registry() {
		global $wpdb;

		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'vlt_videos' );
		if ( $count > 0 ) {
			return;
		}

		$s   = (array) get_option( 'vlt_settings', [] );
		$now = current_time( 'mysql' );

		$key = sanitize_key( $s['video_key'] ?? 'main-training-video' );
		if ( ! $key ) {
			$key = 'main-training-video';
		}

		$wpdb->insert(
			$wpdb->prefix . 'vlt_videos',
			[
				'video_key'          => $key,
				'title'              => $s['video_title']         ?? '',
				'video_url'          => $s['video_url']           ?? '',
				'duration_seconds'   => (int) ( $s['video_duration']    ?? 0 ),
				'form_title'         => $s['form_title']          ?? 'Watch the Free Training',
				'name_label'         => $s['name_label']          ?? 'Full Name',
				'mobile_label'       => $s['mobile_label']        ?? 'Mobile Number',
				'submit_button_text' => $s['submit_button_text']  ?? 'Watch Now',
				'success_message'    => $s['success_message']     ?? 'Welcome! Your video is ready.',
				'enable_otp'         => ! empty( $s['enable_otp'] ) ? 1 : 0,
				'is_active'          => 1,
				'created_at'         => $now,
				'updated_at'         => $now,
			],
			[ '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);
	}

	public static function create_video_event( array $data ) {
		return self::insert( 'vlt_video_events', $data );
	}

	public static function create_video_range( array $data ) {
		return self::insert( 'vlt_video_ranges', $data );
	}

	/**
	 * @return object|null
	 */
	public static function get_video_by_id( $video_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . $wpdb->prefix . 'vlt_videos WHERE id = %d LIMIT 1',
			(int) $video_id
		) );
	}

	/**
	 * Fetch all watch ranges for a given video + viewer.
	 * When lead_id is set, queries by lead; otherwise by visitor_uuid (anonymous).
	 *
	 * @return object[]
	 */
	public static function get_video_ranges_for( $video_id, $lead_id, $visitor_uuid ) {
		global $wpdb;

		if ( $lead_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				'SELECT from_second, to_second, duration_seconds, session_uuid
				   FROM ' . $wpdb->prefix . 'vlt_video_ranges
				  WHERE video_id = %d AND lead_id = %d',
				(int) $video_id, (int) $lead_id
			) );
		}

		return $wpdb->get_results( $wpdb->prepare(
			'SELECT from_second, to_second, duration_seconds, session_uuid
			   FROM ' . $wpdb->prefix . 'vlt_video_ranges
			  WHERE video_id = %d AND visitor_uuid = %s AND lead_id IS NULL',
			(int) $video_id, $visitor_uuid
		) );
	}

	/**
	 * @return bool
	 */
	public static function has_video_event_type( $video_id, $lead_id, $visitor_uuid, $event_type ) {
		global $wpdb;

		if ( $lead_id ) {
			return (bool) $wpdb->get_var( $wpdb->prepare(
				'SELECT 1 FROM ' . $wpdb->prefix . 'vlt_video_events
				  WHERE video_id = %d AND lead_id = %d AND event_type = %s LIMIT 1',
				(int) $video_id, (int) $lead_id, $event_type
			) );
		}

		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT 1 FROM ' . $wpdb->prefix . 'vlt_video_events
			  WHERE video_id = %d AND visitor_uuid = %s AND lead_id IS NULL AND event_type = %s LIMIT 1',
			(int) $video_id, $visitor_uuid, $event_type
		) );
	}

	/**
	 * @return string|null  MySQL datetime or null.
	 */
	public static function get_first_video_event_at( $video_id, $lead_id, $visitor_uuid, $event_type ) {
		global $wpdb;

		if ( $lead_id ) {
			return $wpdb->get_var( $wpdb->prepare(
				'SELECT MIN(event_at) FROM ' . $wpdb->prefix . 'vlt_video_events
				  WHERE video_id = %d AND lead_id = %d AND event_type = %s',
				(int) $video_id, (int) $lead_id, $event_type
			) );
		}

		return $wpdb->get_var( $wpdb->prepare(
			'SELECT MIN(event_at) FROM ' . $wpdb->prefix . 'vlt_video_events
			  WHERE video_id = %d AND visitor_uuid = %s AND lead_id IS NULL AND event_type = %s',
			(int) $video_id, $visitor_uuid, $event_type
		) );
	}

	/**
	 * @return int
	 */
	public static function count_video_sessions( $video_id, $lead_id, $visitor_uuid ) {
		global $wpdb;

		if ( $lead_id ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(DISTINCT session_uuid) FROM ' . $wpdb->prefix . 'vlt_video_ranges
				  WHERE video_id = %d AND lead_id = %d',
				(int) $video_id, (int) $lead_id
			) );
		}

		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(DISTINCT session_uuid) FROM ' . $wpdb->prefix . 'vlt_video_ranges
			  WHERE video_id = %d AND visitor_uuid = %s AND lead_id IS NULL',
			(int) $video_id, $visitor_uuid
		) );
	}

	/**
	 * Insert or update a vlt_video_user_summary row.
	 */
	public static function upsert_video_user_summary( $video_id, $lead_id, $visitor_uuid, array $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'vlt_video_user_summary';

		if ( $lead_id ) {
			$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE video_id = %d AND lead_id = %d LIMIT 1",
				(int) $video_id, (int) $lead_id
			) );
		} else {
			$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE video_id = %d AND visitor_uuid = %s AND lead_id IS NULL LIMIT 1",
				(int) $video_id, $visitor_uuid
			) );
		}

		if ( $existing_id ) {
			self::update_where( 'vlt_video_user_summary', $data, [ 'id' => $existing_id ] );
		} else {
			self::insert( 'vlt_video_user_summary', array_merge( [
				'video_id'     => (int) $video_id,
				'lead_id'      => $lead_id,
				'visitor_uuid' => $visitor_uuid,
			], $data ) );
		}
	}

	/**
	 * Update last_position for drop-off analytics.
	 * Only updates existing rows; does not create new summary rows.
	 * Called for every video_exit event whose reason is not "ended".
	 */
	public static function update_last_position( $video_id, $lead_id, $visitor_uuid, $position ) {
		global $wpdb;

		$table = $wpdb->prefix . 'vlt_video_user_summary';
		$pos   = max( 0.0, (float) $position );

		if ( $lead_id ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET last_position = %f WHERE video_id = %d AND lead_id = %d LIMIT 1",
				$pos, (int) $video_id, (int) $lead_id
			) );
		} elseif ( $visitor_uuid ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET last_position = %f WHERE video_id = %d AND visitor_uuid = %s AND lead_id IS NULL LIMIT 1",
				$pos, (int) $video_id, $visitor_uuid
			) );
		}
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
	// Page visits
	// -------------------------------------------------------------------------

	public static function create_page_visit( array $data ) {
		return self::insert( 'vlt_page_visits', $data );
	}

	// -------------------------------------------------------------------------
	// Bulk lead attachment (after form submit)
	// -------------------------------------------------------------------------

	/**
	 * Attach a lead_id to all anonymous records belonging to a visitor.
	 * Used in Phase 8 when a visitor submits the form.
	 */
	/**
	 * Attach a lead_id to all anonymous records belonging to a visitor.
	 * Also migrates anonymous vlt_video_user_summary rows to the lead.
	 *
	 * @return int[]  Video IDs whose summary rows were migrated (caller should re-aggregate).
	 */
	public static function attach_lead_to_visitor( $visitor_uuid, $lead_id ) {
		global $wpdb;

		$lead_id      = (int) $lead_id;
		$visitor_uuid = sanitize_text_field( $visitor_uuid );
		$now          = current_time( 'mysql', true );
		$p            = $wpdb->prefix;

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$p}vlt_visitors SET lead_id = %d, updated_at = %s WHERE visitor_uuid = %s",
			$lead_id, $now, $visitor_uuid
		) );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$p}vlt_sessions SET lead_id = %d, updated_at = %s WHERE visitor_uuid = %s AND lead_id IS NULL",
			$lead_id, $now, $visitor_uuid
		) );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$p}vlt_page_visits SET lead_id = %d WHERE visitor_uuid = %s AND lead_id IS NULL",
			$lead_id, $visitor_uuid
		) );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$p}vlt_video_events SET lead_id = %d WHERE visitor_uuid = %s AND lead_id IS NULL",
			$lead_id, $visitor_uuid
		) );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$p}vlt_video_ranges SET lead_id = %d WHERE visitor_uuid = %s AND lead_id IS NULL",
			$lead_id, $visitor_uuid
		) );

		// Collect video IDs with anonymous summary rows before migrating them.
		$migrated_video_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT video_id FROM {$p}vlt_video_user_summary
			 WHERE visitor_uuid = %s AND lead_id IS NULL",
			$visitor_uuid
		) );

		if ( $migrated_video_ids ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$p}vlt_video_user_summary SET lead_id = %d, updated_at = %s
				 WHERE visitor_uuid = %s AND lead_id IS NULL",
				$lead_id, $now, $visitor_uuid
			) );
		}

		return array_map( 'intval', $migrated_video_ids );
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
  visitor_uuid char(36) NOT NULL,
  lead_id bigint(20) UNSIGNED DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY video_second_visitor (video_id,second_index,visitor_uuid),
  KEY video_id (video_id),
  KEY lead_id (lead_id)
) $collate;" );
	}

	// -------------------------------------------------------------------------
	// Heatmap (Phase 14)
	// -------------------------------------------------------------------------

	/**
	 * Increment total_views_count for one second, creating the row if needed.
	 */
	public static function heatmap_increment_total( $video_id, $second_index, $now ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vlt_video_heatmap';

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table}
			 (video_id, second_index, total_views_count, unique_leads_count, unique_visitors_count, updated_at)
			 VALUES (%d, %d, 1, 0, 0, %s)
			 ON DUPLICATE KEY UPDATE total_views_count = total_views_count + 1, updated_at = VALUES(updated_at)",
			(int) $video_id, (int) $second_index, $now
		) );
	}

	/**
	 * INSERT IGNORE the viewer into heatmap_uniques for one second.
	 *
	 * @return bool  True if this is the first time this visitor watched this second.
	 */
	public static function heatmap_try_unique( $video_id, $second_index, $visitor_uuid, $lead_id, $now ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vlt_video_heatmap_uniques';

		if ( $lead_id ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$table} (video_id, second_index, visitor_uuid, lead_id, created_at)
				 VALUES (%d, %d, %s, %d, %s)",
				(int) $video_id, (int) $second_index, $visitor_uuid, (int) $lead_id, $now
			) );
		} else {
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$table} (video_id, second_index, visitor_uuid, created_at)
				 VALUES (%d, %d, %s, %s)",
				(int) $video_id, (int) $second_index, $visitor_uuid, $now
			) );
		}

		return $wpdb->rows_affected > 0;
	}

	/**
	 * Increment unique counters for one second.
	 * Always increments unique_visitors_count; also unique_leads_count when $has_lead is true.
	 */
	public static function heatmap_increment_unique( $video_id, $second_index, $has_lead, $now ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vlt_video_heatmap';

		$set = $has_lead
			? 'unique_visitors_count = unique_visitors_count + 1, unique_leads_count = unique_leads_count + 1'
			: 'unique_visitors_count = unique_visitors_count + 1';

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET {$set}, updated_at = %s WHERE video_id = %d AND second_index = %d",
			$now, (int) $video_id, (int) $second_index
		) );
	}
}
