<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Activator {

	public static function activate() {
		VLT_DB::create_tables();
		VLT_DB::create_heatmap_uniques_table();
		update_option( 'vlt_db_version', VLT_DB_VERSION );
		flush_rewrite_rules();
	}
}
