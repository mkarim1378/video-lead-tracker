<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_DB {

	// Full table creation implemented in Phase 2.

	public static function create_tables() {}

	public static function get_version() {
		return get_option( 'vlt_db_version', '0' );
	}

	public static function needs_upgrade() {
		return version_compare( self::get_version(), VLT_DB_VERSION, '<' );
	}
}
