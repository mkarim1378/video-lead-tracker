<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Admin {

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu',             [ self::class, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts',  [ self::class, 'enqueue_assets' ] );
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'vlt-' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'vlt-admin',
			VLT_PLUGIN_URL . 'assets/css/vlt-admin.css',
			[],
			VLT_VERSION
		);
		wp_enqueue_script(
			'vlt-admin',
			VLT_PLUGIN_URL . 'assets/js/vlt-admin.js',
			[],
			VLT_VERSION,
			true
		);
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Video Lead Tracker', 'video-lead-tracker' ),
			__( 'Video Lead Tracker', 'video-lead-tracker' ),
			'manage_options',
			'vlt-overview',
			[ self::class, 'render_overview' ],
			'dashicons-video-alt3',
			30
		);

		$submenus = [
			[ 'vlt-overview',        __( 'Overview',        'video-lead-tracker' ), [ self::class,  'render_overview' ] ],
			[ 'vlt-leads',           __( 'Leads',           'video-lead-tracker' ), [ self::class,  'render_placeholder' ] ],
			[ 'vlt-video-analytics', __( 'Video Analytics', 'video-lead-tracker' ), [ self::class,  'render_placeholder' ] ],
			[ 'vlt-heatmap',         __( 'Heatmap',         'video-lead-tracker' ), [ self::class,  'render_placeholder' ] ],
			[ 'vlt-exports',         __( 'Exports',         'video-lead-tracker' ), [ self::class,  'render_placeholder' ] ],
			[ 'vlt-settings',        __( 'Settings',        'video-lead-tracker' ), [ 'VLT_Settings', 'render_page' ] ],
			[ 'vlt-logs',            __( 'Logs',            'video-lead-tracker' ), [ self::class,  'render_placeholder' ] ],
		];

		foreach ( $submenus as [ $slug, $label, $callback ] ) {
			add_submenu_page( 'vlt-overview', $label, $label, 'manage_options', $slug, $callback );
		}
	}

	// -------------------------------------------------------------------------
	// Overview dashboard (Phase 15)
	// -------------------------------------------------------------------------

	public static function render_overview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$p = $wpdb->prefix;

		// KPI queries — no user input, no prepare needed.
		$total_leads    = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_leads" );
		$verified_leads = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_leads WHERE is_verified = 1" );
		$total_sessions = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_sessions" );
		$active_videos  = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_videos WHERE is_active = 1" );
		$watch_secs     = (float) $wpdb->get_var( "SELECT COALESCE( SUM(unique_watch_seconds), 0 ) FROM {$p}vlt_video_user_summary" );
		$watch_hours    = number_format( $watch_secs / 3600, 1 );

		// Recent leads — last 10, with average watch percent across all their videos.
		$recent_leads = $wpdb->get_results(
			"SELECT l.primary_name, l.normalized_mobile, l.is_verified, l.first_seen_at,
			        COALESCE( AVG(s.unique_watch_percent), 0 ) AS avg_watch
			 FROM {$p}vlt_leads l
			 LEFT JOIN {$p}vlt_video_user_summary s ON s.lead_id = l.id
			 GROUP BY l.id
			 ORDER BY l.first_seen_at DESC
			 LIMIT 10"
		);

		// Top videos — ordered by total unique watch hours descending.
		$top_videos = $wpdb->get_results(
			"SELECT v.title, v.video_key, v.duration_seconds,
			        COUNT( DISTINCT CASE WHEN s.lead_id IS NOT NULL
			            THEN CONCAT( 'l', s.lead_id )
			            ELSE s.visitor_uuid END )               AS viewers,
			        COALESCE( AVG(s.unique_watch_percent), 0 )  AS avg_completion,
			        COALESCE( SUM(s.unique_watch_seconds), 0 ) / 3600 AS watch_hours,
			        COALESCE( SUM(s.reached_end), 0 )           AS completions
			 FROM {$p}vlt_videos v
			 LEFT JOIN {$p}vlt_video_user_summary s ON s.video_id = v.id
			 WHERE v.is_active = 1
			 GROUP BY v.id
			 ORDER BY watch_hours DESC
			 LIMIT 8"
		);

		?>
		<div class="wrap vlt-overview">

			<h1 class="wp-heading-inline"><?php esc_html_e( 'Video Lead Tracker', 'video-lead-tracker' ); ?></h1>

			<!-- KPI cards -->
			<div class="vlt-kpi-row">
				<?php
				$cards = [
					[ __( 'Total Leads', 'video-lead-tracker' ), number_format_i18n( $total_leads ),    'dashicons-groups',     '#2271b1' ],
					[ __( 'Verified',    'video-lead-tracker' ), number_format_i18n( $verified_leads ), 'dashicons-yes-alt',    '#1a9e6a' ],
					[ __( 'Sessions',    'video-lead-tracker' ), number_format_i18n( $total_sessions ), 'dashicons-clock',      '#6e3ec3' ],
					[ __( 'Videos',      'video-lead-tracker' ), number_format_i18n( $active_videos ),  'dashicons-video-alt3', '#e06800' ],
					[ __( 'Watch Hours', 'video-lead-tracker' ), $watch_hours,                          'dashicons-visibility', '#c22f3a' ],
				];
				foreach ( $cards as [ $label, $value, $icon, $color ] ) :
				?>
				<div class="vlt-kpi-card">
					<span class="vlt-kpi-icon dashicons <?php echo esc_attr( $icon ); ?>" style="color:<?php echo esc_attr( $color ); ?>"></span>
					<strong class="vlt-kpi-number"><?php echo esc_html( $value ); ?></strong>
					<span class="vlt-kpi-label"><?php echo esc_html( $label ); ?></span>
				</div>
				<?php endforeach; ?>
			</div><!-- .vlt-kpi-row -->

			<div class="vlt-overview-grid">

				<!-- Recent Leads -->
				<div class="vlt-overview-box">
					<h2><?php esc_html_e( 'Recent Leads', 'video-lead-tracker' ); ?></h2>
					<table class="widefat striped vlt-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name',       'video-lead-tracker' ); ?></th>
								<th><?php esc_html_e( 'Mobile',     'video-lead-tracker' ); ?></th>
								<th><?php esc_html_e( 'Verified',   'video-lead-tracker' ); ?></th>
								<th><?php esc_html_e( 'Avg Watch',  'video-lead-tracker' ); ?></th>
								<th><?php esc_html_e( 'First Seen', 'video-lead-tracker' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php if ( $recent_leads ) : ?>
							<?php foreach ( $recent_leads as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row->primary_name ?: '—' ); ?></td>
								<td><code class="vlt-mono"><?php echo esc_html( self::mask_mobile( $row->normalized_mobile ) ); ?></code></td>
								<td>
									<?php if ( $row->is_verified ) : ?>
										<span class="vlt-badge vlt-badge--green"><?php esc_html_e( 'Yes', 'video-lead-tracker' ); ?></span>
									<?php else : ?>
										<span class="vlt-badge"><?php esc_html_e( 'No', 'video-lead-tracker' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( round( (float) $row->avg_watch, 1 ) . '%' ); ?></td>
								<td class="vlt-muted"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $row->first_seen_at ) ) ); ?></td>
							</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="5" class="vlt-empty"><?php esc_html_e( 'No leads yet.', 'video-lead-tracker' ); ?></td></tr>
						<?php endif; ?>
						</tbody>
					</table>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=vlt-leads' ) ); ?>" class="button vlt-view-all">
						<?php esc_html_e( 'View all leads →', 'video-lead-tracker' ); ?>
					</a>
				</div>

				<!-- Top Videos -->
				<div class="vlt-overview-box">
					<h2><?php esc_html_e( 'Top Videos', 'video-lead-tracker' ); ?></h2>
					<table class="widefat striped vlt-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Video',       'video-lead-tracker' ); ?></th>
								<th><?php esc_html_e( 'Viewers',     'video-lead-tracker' ); ?></th>
								<th><?php esc_html_e( 'Completions', 'video-lead-tracker' ); ?></th>
								<th><?php esc_html_e( 'Avg %',       'video-lead-tracker' ); ?></th>
								<th><?php esc_html_e( 'Watch hrs',   'video-lead-tracker' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php if ( $top_videos ) : ?>
							<?php foreach ( $top_videos as $video ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $video->title ?: $video->video_key ); ?></strong>
									<?php if ( $video->duration_seconds ) : ?>
										<br><span class="vlt-muted"><?php echo esc_html( self::format_duration( (int) $video->duration_seconds ) ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( number_format_i18n( (int) $video->viewers ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (int) $video->completions ) ); ?></td>
								<td><?php echo esc_html( round( (float) $video->avg_completion, 1 ) . '%' ); ?></td>
								<td><?php echo esc_html( number_format( (float) $video->watch_hours, 2 ) ); ?></td>
							</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="5" class="vlt-empty"><?php esc_html_e( 'No videos yet.', 'video-lead-tracker' ); ?></td></tr>
						<?php endif; ?>
						</tbody>
					</table>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=vlt-video-analytics' ) ); ?>" class="button vlt-view-all">
						<?php esc_html_e( 'View analytics →', 'video-lead-tracker' ); ?>
					</a>
				</div>

			</div><!-- .vlt-overview-grid -->

		</div><!-- .wrap.vlt-overview -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Placeholder (pages implemented in later phases)
	// -------------------------------------------------------------------------

	public static function render_placeholder() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'This section is under development.', 'video-lead-tracker' ); ?></p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Mask the middle digits of a normalized mobile number.
	 * 98912xxxxxxxx → 98912•••xxxx
	 */
	private static function mask_mobile( $mobile ) {
		$mobile = (string) $mobile;
		if ( strlen( $mobile ) < 9 ) {
			return $mobile;
		}
		return substr( $mobile, 0, 5 ) . '•••' . substr( $mobile, -4 );
	}

	/**
	 * Format seconds as M:SS or H:MM:SS.
	 */
	private static function format_duration( $seconds ) {
		$h = (int) floor( $seconds / 3600 );
		$m = (int) floor( ( $seconds % 3600 ) / 60 );
		$s = (int) ( $seconds % 60 );

		return $h > 0
			? sprintf( '%d:%02d:%02d', $h, $m, $s )
			: sprintf( '%d:%02d', $m, $s );
	}
}
