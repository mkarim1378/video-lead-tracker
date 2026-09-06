<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin CSS/JS enqueue for VLT screens.
 */
class VLT_Admin_Assets {

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * Enqueue SaaS admin assets on VLT pages.
	 *
	 * @param string $hook
	 */
	public static function enqueue( $hook ) {
		if ( strpos( $hook, 'vlt-' ) === false ) {
			return;
		}

		self::enqueue_style();
		self::enqueue_scripts();
		self::localize();
	}

	/**
	 * Shared stylesheet (also used on post meta box screens).
	 */
	public static function enqueue_style() {
		wp_enqueue_style(
			'vlt-admin',
			VLT_PLUGIN_URL . 'assets/css/vlt-admin.css',
			[],
			VLT_VERSION
		);
	}

	/**
	 * Core admin JS stack.
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script(
			'vlt-admin-api',
			VLT_PLUGIN_URL . 'assets/js/vlt-admin-api.js',
			[],
			VLT_VERSION,
			true
		);
		wp_enqueue_script(
			'vlt-admin-ui',
			VLT_PLUGIN_URL . 'assets/js/vlt-admin-ui.js',
			[ 'vlt-admin-api' ],
			VLT_VERSION,
			true
		);
		wp_enqueue_script(
			'vlt-admin',
			VLT_PLUGIN_URL . 'assets/js/vlt-admin.js',
			[ 'vlt-admin-ui' ],
			VLT_VERSION,
			true
		);
		wp_enqueue_script(
			'vlt-admin-pages',
			VLT_PLUGIN_URL . 'assets/js/vlt-admin-pages.js',
			[ 'vlt-admin' ],
			VLT_VERSION,
			true
		);
	}

	/**
	 * REST + i18n bootstrap for admin JS.
	 */
	public static function localize() {
		wp_localize_script( 'vlt-admin-api', 'vltAdminData', [
			'restBase' => esc_url_raw( rest_url( 'vlt/v1/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'adminUrl' => esc_url_raw( admin_url( 'admin.php' ) ),
			'i18n'     => [
				'yes'            => __( 'Yes', 'video-lead-tracker' ),
				'no'             => __( 'No', 'video-lead-tracker' ),
				'na'             => __( 'N/A', 'video-lead-tracker' ),
				'noLeads'        => __( 'No leads yet.', 'video-lead-tracker' ),
				'noVideos'       => __( 'No videos yet.', 'video-lead-tracker' ),
				'noViewers'      => __( 'No viewers yet.', 'video-lead-tracker' ),
				'loadError'      => __( 'Could not refresh data.', 'video-lead-tracker' ),
				'customize'      => __( 'Customize Widgets', 'video-lead-tracker' ),
				'save'           => __( 'Done', 'video-lead-tracker' ),
				'clear'          => __( 'Clear', 'video-lead-tracker' ),
				'selectVideo'    => __( 'Select a video', 'video-lead-tracker' ),
				'funnelHint'     => __( 'Choose a video to view its conversion funnel.', 'video-lead-tracker' ),
				'heatmapHint'    => __( 'Choose a video to explore watch heatmap and drop-off.', 'video-lead-tracker' ),
				'noHeatmap'      => __( 'No heatmap data yet', 'video-lead-tracker' ),
				'noHeatmapHint'  => __( 'Data appears after viewers start watching this video.', 'video-lead-tracker' ),
				'notFound'       => __( 'Video not found', 'video-lead-tracker' ),
				'missingVideo'   => __( 'The selected video is missing or inactive.', 'video-lead-tracker' ),
				'otpOff'         => __( '(OTP off)', 'video-lead-tracker' ),
				'exportCsv'      => __( 'Export CSV', 'video-lead-tracker' ),
				'metric'         => __( 'Metric:', 'video-lead-tracker' ),
				'totalViews'     => __( 'Total Views', 'video-lead-tracker' ),
				'uniqueVisitors' => __( 'Unique Visitors', 'video-lead-tracker' ),
				'uniqueLeads'    => __( 'Unique Leads', 'video-lead-tracker' ),
				'dropOff'        => __( 'Drop-off Curve', 'video-lead-tracker' ),
				'bucketHint'     => __( 'Bucket size: %d second(s). Hover over a point for details.', 'video-lead-tracker' ),
				'duration'       => __( 'Duration %s', 'video-lead-tracker' ),
				'distribution'   => __( 'Watch Distribution', 'video-lead-tracker' ),
				'topViewers'     => __( 'Top Viewers', 'video-lead-tracker' ),
				'viewer'         => __( 'Viewer', 'video-lead-tracker' ),
				'watchPct'       => __( 'Watch %', 'video-lead-tracker' ),
				'uniqueWatch'    => __( 'Unique Watch', 'video-lead-tracker' ),
				'sessions'       => __( 'Sessions', 'video-lead-tracker' ),
				'completed'      => __( 'Completed', 'video-lead-tracker' ),
				'firstPlay'      => __( 'First Play', 'video-lead-tracker' ),
				'noLeadsFound'   => __( 'No leads found.', 'video-lead-tracker' ),
				'showingRange'   => __( 'Showing %1$d to %2$d of %3$d', 'video-lead-tracker' ),
				'logsCount'      => __( 'Showing last %1$d of %2$d entries', 'video-lead-tracker' ),
				'noLogs'         => __( 'No log entries.', 'video-lead-tracker' ),
				'copied'         => __( 'Copied to clipboard.', 'video-lead-tracker' ),
				'settingsSaved'  => __( 'Settings saved.', 'video-lead-tracker' ),
			],
		] );
	}
}
