<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'vlt_settings', [] );

if ( ! empty( $settings['delete_on_uninstall'] ) ) {
	global $wpdb;

	$tables = [
		'vlt_leads',
		'vlt_lead_names',
		'vlt_visitors',
		'vlt_sessions',
		'vlt_page_visits',
		'vlt_videos',
		'vlt_video_events',
		'vlt_video_ranges',
		'vlt_video_user_summary',
		'vlt_video_heatmap',
		'vlt_video_heatmap_uniques',
		'vlt_otp_codes',
		'vlt_logs',
	];

	foreach ( $tables as $table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	delete_option( 'vlt_settings' );
	delete_option( 'vlt_db_version' );
}
