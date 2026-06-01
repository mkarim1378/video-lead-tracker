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
			VLT_DB::maybe_seed_video_registry();
			update_option( 'vlt_db_version', VLT_DB_VERSION );
		}

		VLT_Settings::init();
		VLT_CPT::init();
		VLT_Videos_Admin::init();
		VLT_Admin::init();
		VLT_Exporter::init();
		VLT_REST_Controller::init();
		VLT_Frontend::init();

		// Event retention cron.
		add_action( 'vlt_daily_cleanup', [ self::class, 'run_event_retention' ] );
		if ( ! wp_next_scheduled( 'vlt_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'vlt_daily_cleanup' );
		}
	}

	public static function run_event_retention() {
		$days = (int) VLT_Settings::get( 'event_retention_days' );
		if ( $days <= 0 ) {
			return;
		}

		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$deleted = $wpdb->query( $wpdb->prepare(
			'DELETE FROM ' . $wpdb->prefix . 'vlt_video_events WHERE event_at < %s',
			$cutoff
		) );

		if ( $deleted ) {
			VLT_Logger::info( "Event retention: deleted {$deleted} video events older than {$days} days.", 'cron_retention' );
		}
	}
}
