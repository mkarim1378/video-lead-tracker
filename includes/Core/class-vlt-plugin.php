<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Plugin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function init() {
		load_plugin_textdomain(
			'video-lead-tracker',
			false,
			dirname( plugin_basename( VLT_PLUGIN_FILE ) ) . '/languages'
		);

		if ( VLT_DB::needs_upgrade() ) {
			VLT_DB::create_tables();
			update_option( 'vlt_db_version', VLT_DB_VERSION );
		}

		VLT_Settings::init();
		VLT_Admin::init();
		VLT_Exporter::init();
		VLT_REST_Controller::init();
		VLT_Frontend::init();
	}
}
