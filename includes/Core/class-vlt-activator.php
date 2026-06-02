<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Activator {

	public static function activate() {
		VLT_DB::create_tables();
		VLT_DB::create_heatmap_uniques_table();
		update_option( 'vlt_db_version', VLT_DB_VERSION );

		// Register CPT/taxonomies before flushing so their rewrite rules are
		// included in the freshly-built rules table. The activation hook runs
		// before init, so register_post_type hasn't been called yet.
		VLT_CPT::register_post_type();
		VLT_CPT::register_taxonomies();

		flush_rewrite_rules();
	}
}
