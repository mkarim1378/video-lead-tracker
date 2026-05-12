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
			[ 'vlt-overview',        __( 'Overview',        'video-lead-tracker' ), [ self::class,   'render_overview' ] ],
			[ 'vlt-leads',           __( 'Leads',           'video-lead-tracker' ), [ self::class,   'render_leads' ] ],
			[ 'vlt-video-analytics', __( 'Video Analytics', 'video-lead-tracker' ), [ self::class,   'render_video_analytics' ] ],
			[ 'vlt-heatmap',         __( 'Heatmap',         'video-lead-tracker' ), [ self::class,   'render_heatmap' ] ],
			[ 'vlt-settings',        __( 'Settings',        'video-lead-tracker' ), [ 'VLT_Settings', 'render_page' ] ],
			[ 'vlt-logs',            __( 'Logs',            'video-lead-tracker' ), [ self::class,   'render_logs' ] ],
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

		$cache     = get_transient( 'vlt_overview_cache' );
		$cache_hit = is_array( $cache );

		if ( $cache_hit ) {
			[ $total_leads, $verified_leads, $total_sessions, $active_videos, $watch_hours,
			  $recent_leads, $top_videos ] = $cache;
		} else {
			$total_leads    = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_leads" );
			$verified_leads = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_leads WHERE is_verified = 1" );
			$total_sessions = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_sessions" );
			$active_videos  = (int)   $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_videos WHERE is_active = 1" );
			$watch_secs     = (float) $wpdb->get_var( "SELECT COALESCE( SUM(unique_watch_seconds), 0 ) FROM {$p}vlt_video_user_summary" );
			$watch_hours    = number_format( $watch_secs / 3600, 1 );

			$recent_leads = $wpdb->get_results(
				"SELECT l.primary_name, l.normalized_mobile, l.is_verified, l.first_seen_at,
				        COALESCE( AVG(s.unique_watch_percent), 0 ) AS avg_watch
				 FROM {$p}vlt_leads l
				 LEFT JOIN {$p}vlt_video_user_summary s ON s.lead_id = l.id
				 GROUP BY l.id
				 ORDER BY l.first_seen_at DESC
				 LIMIT 10"
			);

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

			set_transient( 'vlt_overview_cache',
				[ $total_leads, $verified_leads, $total_sessions, $active_videos, $watch_hours,
				  $recent_leads, $top_videos ],
				5 * MINUTE_IN_SECONDS
			);
		}

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
	// Leads report + lead detail (Phase 16)
	// -------------------------------------------------------------------------

	public static function render_leads() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$lead_id = isset( $_GET['lead_id'] ) ? absint( $_GET['lead_id'] ) : 0;
		if ( $lead_id ) {
			self::render_lead_detail( $lead_id );
		} else {
			self::render_lead_list();
		}
	}

	private static function render_lead_list() {
		global $wpdb;
		$p = $wpdb->prefix;

		// ---- Sanitized input ----
		$search  = sanitize_text_field( $_GET['s']       ?? '' );
		$orderby = sanitize_key(        $_GET['orderby'] ?? 'first_seen_at' );
		$order   = strtoupper( sanitize_key( $_GET['order'] ?? 'DESC' ) );
		$paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

		$per_page = 20;
		$offset   = ( $paged - 1 ) * $per_page;

		$allowed_cols = [ 'primary_name', 'first_seen_at', 'last_seen_at', 'is_verified', 'avg_watch', 'videos_count', 'sessions_count' ];
		if ( ! in_array( $orderby, $allowed_cols, true ) ) {
			$orderby = 'first_seen_at';
		}
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'DESC';
		}

		// ---- WHERE clause ----
		$where        = '';
		$where_params = [];
		if ( $search !== '' ) {
			$like         = '%' . $wpdb->esc_like( $search ) . '%';
			$where        = 'WHERE ( l.primary_name LIKE %s OR l.normalized_mobile LIKE %s )';
			$where_params = [ $like, $like ];
		}

		// ---- Total count ----
		$count_sql = "SELECT COUNT(*) FROM {$p}vlt_leads l $where";
		$total     = (int) ( $where_params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, $where_params ) )
			: $wpdb->get_var( $count_sql )
		);

		// ---- Main query ----
		$sql = "SELECT l.id, l.primary_name, l.normalized_mobile, l.is_verified,
		               l.first_seen_at, l.last_seen_at,
		               COUNT( DISTINCT s.video_id )               AS videos_count,
		               COALESCE( AVG(s.unique_watch_percent), 0 ) AS avg_watch,
		               ( SELECT COUNT(*) FROM {$p}vlt_sessions WHERE lead_id = l.id ) AS sessions_count
		        FROM {$p}vlt_leads l
		        LEFT JOIN {$p}vlt_video_user_summary s ON s.lead_id = l.id
		        $where
		        GROUP BY l.id
		        ORDER BY $orderby $order
		        LIMIT %d OFFSET %d";

		$leads = $wpdb->get_results(
			$wpdb->prepare( $sql, array_merge( $where_params, [ $per_page, $offset ] ) )
		);

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$paged       = min( $paged, $total_pages );
		$offset      = ( $paged - 1 ) * $per_page;
		$base_url    = admin_url( 'admin.php?page=vlt-leads' );
		$sort_base   = add_query_arg( array_filter( [
			's'       => $search ?: null,
			'orderby' => ( $orderby !== 'first_seen_at' ) ? $orderby : null,
			'order'   => ( $order  !== 'DESC'           ) ? $order   : null,
		] ), $base_url );

		$showing_from = min( $offset + 1, max( $total, 1 ) );
		$showing_to   = min( $offset + $per_page, $total );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Leads', 'video-lead-tracker' ); ?></h1>
			<a href="<?php echo esc_url( VLT_Exporter::export_url( 'leads' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Export CSV', 'video-lead-tracker' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="get" class="vlt-search-form">
				<input type="hidden" name="page" value="vlt-leads">
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
				       placeholder="<?php esc_attr_e( 'Search leads\xe2\x80\xa6', 'video-lead-tracker' ); ?>"
				       class="vlt-search-input">
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'video-lead-tracker' ); ?></button>
				<?php if ( $search ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button button-link"><?php esc_html_e( 'Clear', 'video-lead-tracker' ); ?></a>
				<?php endif; ?>
			</form>

			<div class="tablenav top">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: 1: first result number 2: last result number 3: total results */
							esc_html__( 'Showing %1$d to %2$d of %3$d', 'video-lead-tracker' ),
							$showing_from, $showing_to, $total
						);
						?>
					</span>
					<?php if ( $total_pages > 1 ) : ?>
						<?php echo paginate_links( [ // phpcs:ignore
							'base'      => add_query_arg( 'paged', '%#%', $sort_base ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						] ); ?>
					<?php endif; ?>
				</div>
			</div>

			<table class="widefat striped vlt-table">
				<thead>
					<tr>
						<th class="vlt-col-num">#</th>
						<th><?php echo self::sort_link( __( 'Name', 'video-lead-tracker' ), 'primary_name', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
						<th><?php esc_html_e( 'Mobile', 'video-lead-tracker' ); ?></th>
						<th><?php echo self::sort_link( __( 'Verified', 'video-lead-tracker' ), 'is_verified', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
						<th><?php echo self::sort_link( __( 'Videos Watched', 'video-lead-tracker' ), 'videos_count', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
						<th><?php echo self::sort_link( __( 'Avg Watch', 'video-lead-tracker' ), 'avg_watch', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
						<th><?php echo self::sort_link( __( 'Sessions', 'video-lead-tracker' ), 'sessions_count', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
						<th><?php echo self::sort_link( __( 'First Seen', 'video-lead-tracker' ), 'first_seen_at', $orderby, $order, $sort_base ); // phpcs:ignore ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $leads ) : ?>
					<?php foreach ( $leads as $i => $row ) :
						$detail_url = add_query_arg( [ 'page' => 'vlt-leads', 'lead_id' => $row->id ], admin_url( 'admin.php' ) );
					?>
					<tr>
						<td class="vlt-muted vlt-col-num"><?php echo esc_html( $offset + $i + 1 ); ?></td>
						<td><a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $row->primary_name ?: '—' ); ?></a></td>
						<td><code class="vlt-mono"><?php echo esc_html( self::mask_mobile( $row->normalized_mobile ) ); ?></code></td>
						<td>
							<?php if ( $row->is_verified ) : ?>
								<span class="vlt-badge vlt-badge--green"><?php esc_html_e( 'Yes', 'video-lead-tracker' ); ?></span>
							<?php else : ?>
								<span class="vlt-badge"><?php esc_html_e( 'No', 'video-lead-tracker' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->videos_count ) ); ?></td>
						<td>
							<div class="vlt-progress">
								<div class="vlt-progress-bar" style="width:<?php echo esc_attr( min( 100, (int) round( $row->avg_watch ) ) ); ?>%"></div>
								<span><?php echo esc_html( round( (float) $row->avg_watch, 1 ) . '%' ); ?></span>
							</div>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->sessions_count ) ); ?></td>
						<td class="vlt-muted"><?php echo esc_html( wp_date( 'Y-m-d', strtotime( $row->first_seen_at ) ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="8" class="vlt-empty"><?php esc_html_e( 'No leads found.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php echo paginate_links( [ // phpcs:ignore
						'base'      => add_query_arg( 'paged', '%#%', $sort_base ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $total_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					] ); ?>
				</div>
			</div>
			<?php endif; ?>

		</div>
		<?php
	}

	private static function render_lead_detail( $lead_id ) {
		global $wpdb;
		$p = $wpdb->prefix;

		$lead = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$p}vlt_leads WHERE id = %d LIMIT 1",
			$lead_id
		) );

		if ( ! $lead ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Lead not found.', 'video-lead-tracker' ) . '</p></div>';
			return;
		}

		$video_history = $wpdb->get_results( $wpdb->prepare(
			"SELECT v.title, v.video_key, v.duration_seconds,
			        s.unique_watch_seconds, s.total_watch_seconds, s.unique_watch_percent,
			        s.sessions_count, s.reached_end, s.first_play_at, s.last_activity_at
			 FROM {$p}vlt_video_user_summary s
			 JOIN {$p}vlt_videos v ON v.id = s.video_id
			 WHERE s.lead_id = %d
			 ORDER BY s.first_play_at ASC",
			$lead_id
		) );

		$sessions = $wpdb->get_results( $wpdb->prepare(
			"SELECT session_uuid, started_at, last_activity_at, device_type, browser, os, landing_url
			 FROM {$p}vlt_sessions
			 WHERE lead_id = %d
			 ORDER BY started_at DESC
			 LIMIT 20",
			$lead_id
		) );

		$date_fmt = get_option( 'date_format' ) . ' H:i';
		$back_url = admin_url( 'admin.php?page=vlt-leads' );
		?>
		<div class="wrap vlt-lead-detail">

			<h1 class="wp-heading-inline">
				<a href="<?php echo esc_url( $back_url ); ?>" class="vlt-back-link">&larr; <?php esc_html_e( 'Leads', 'video-lead-tracker' ); ?></a>
				<?php echo esc_html( $lead->primary_name ?: __( '(no name)', 'video-lead-tracker' ) ); ?>
			</h1>
			<hr class="wp-header-end">

			<div class="vlt-lead-header">
				<div class="vlt-lead-meta">
					<div class="vlt-meta-item">
						<span class="vlt-meta-label"><?php esc_html_e( 'Mobile', 'video-lead-tracker' ); ?></span>
						<code class="vlt-mono"><?php echo esc_html( $lead->normalized_mobile ); ?></code>
					</div>
					<div class="vlt-meta-item">
						<span class="vlt-meta-label"><?php esc_html_e( 'Verified', 'video-lead-tracker' ); ?></span>
						<?php if ( $lead->is_verified ) : ?>
							<span class="vlt-badge vlt-badge--green"><?php esc_html_e( 'Yes', 'video-lead-tracker' ); ?></span>
						<?php else : ?>
							<span class="vlt-badge"><?php esc_html_e( 'No', 'video-lead-tracker' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="vlt-meta-item">
						<span class="vlt-meta-label"><?php esc_html_e( 'First Seen', 'video-lead-tracker' ); ?></span>
						<span><?php echo esc_html( wp_date( $date_fmt, strtotime( $lead->first_seen_at ) ) ); ?></span>
					</div>
					<div class="vlt-meta-item">
						<span class="vlt-meta-label"><?php esc_html_e( 'Last Seen', 'video-lead-tracker' ); ?></span>
						<span><?php echo esc_html( $lead->last_seen_at ? wp_date( $date_fmt, strtotime( $lead->last_seen_at ) ) : '—' ); ?></span>
					</div>
				</div>
			</div>

			<h2 class="vlt-section-title"><?php esc_html_e( 'Video Watch History', 'video-lead-tracker' ); ?></h2>
			<table class="widefat striped vlt-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Video',        'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Watch %',      'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Unique Watch', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Sessions',     'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Completed',    'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'First Play',   'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Last Activity','video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $video_history ) : ?>
					<?php foreach ( $video_history as $v ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $v->title ?: $v->video_key ); ?></strong>
							<?php if ( $v->duration_seconds ) : ?>
								<br><span class="vlt-muted"><?php echo esc_html( self::format_duration( (int) $v->duration_seconds ) ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<div class="vlt-progress">
								<div class="vlt-progress-bar" style="width:<?php echo esc_attr( min( 100, (int) round( $v->unique_watch_percent ) ) ); ?>%"></div>
								<span><?php echo esc_html( round( (float) $v->unique_watch_percent, 1 ) . '%' ); ?></span>
							</div>
						</td>
						<td><?php echo esc_html( round( (float) $v->unique_watch_seconds ) . 's' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $v->sessions_count ) ); ?></td>
						<td>
							<?php if ( $v->reached_end ) : ?>
								<span class="vlt-badge vlt-badge--green"><?php esc_html_e( 'Yes', 'video-lead-tracker' ); ?></span>
							<?php else : ?>
								<span class="vlt-badge"><?php esc_html_e( 'No', 'video-lead-tracker' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="vlt-muted"><?php echo esc_html( $v->first_play_at ? wp_date( $date_fmt, strtotime( $v->first_play_at ) ) : '—' ); ?></td>
						<td class="vlt-muted"><?php echo esc_html( $v->last_activity_at ? wp_date( $date_fmt, strtotime( $v->last_activity_at ) ) : '—' ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="7" class="vlt-empty"><?php esc_html_e( 'No video watch history for this lead.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

			<h2 class="vlt-section-title"><?php esc_html_e( 'Recent Sessions', 'video-lead-tracker' ); ?></h2>
			<table class="widefat striped vlt-table">
				<thead>
					<tr>
						<th>UUID</th>
						<th><?php esc_html_e( 'Started',     'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Duration',    'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Device',      'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Browser',     'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'OS',          'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Landing URL', 'video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $sessions ) : ?>
					<?php foreach ( $sessions as $sess ) :
						$dur = ( $sess->last_activity_at && $sess->started_at )
							? max( 0, strtotime( $sess->last_activity_at ) - strtotime( $sess->started_at ) )
							: 0;
					?>
					<tr>
						<td><code class="vlt-mono" title="<?php echo esc_attr( $sess->session_uuid ); ?>"><?php echo esc_html( substr( $sess->session_uuid, 0, 8 ) . '…' ); ?></code></td>
						<td class="vlt-muted"><?php echo esc_html( wp_date( $date_fmt, strtotime( $sess->started_at ) ) ); ?></td>
						<td><?php echo esc_html( $dur > 0 ? self::format_duration( $dur ) : '—' ); ?></td>
						<td><?php echo esc_html( $sess->device_type ?: '—' ); ?></td>
						<td><?php echo esc_html( $sess->browser ?: '—' ); ?></td>
						<td><?php echo esc_html( $sess->os ?: '—' ); ?></td>
						<td class="vlt-url-cell">
							<?php if ( $sess->landing_url ) :
								$path = wp_parse_url( $sess->landing_url, PHP_URL_PATH ) ?: $sess->landing_url;
							?>
								<a href="<?php echo esc_url( $sess->landing_url ); ?>" target="_blank" rel="noopener noreferrer"
								   title="<?php echo esc_attr( $sess->landing_url ); ?>">
									<?php echo esc_html( $path ); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="7" class="vlt-empty"><?php esc_html_e( 'No sessions recorded.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

		</div><!-- .wrap.vlt-lead-detail -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Video Analytics (Phase 17)
	// -------------------------------------------------------------------------

	public static function render_video_analytics() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$video_id = isset( $_GET['video_id'] ) ? absint( $_GET['video_id'] ) : 0;
		if ( $video_id ) {
			self::render_video_analytics_detail( $video_id );
		} else {
			self::render_video_analytics_list();
		}
	}

	private static function render_video_analytics_list() {
		global $wpdb;
		$p = $wpdb->prefix;

		$videos = $wpdb->get_results(
			"SELECT v.id, v.title, v.video_key, v.duration_seconds,
			        COUNT( DISTINCT CASE WHEN s.lead_id IS NOT NULL
			            THEN CONCAT( 'l', s.lead_id )
			            ELSE s.visitor_uuid END )               AS viewers,
			        COALESCE( AVG(s.unique_watch_percent), 0 )  AS avg_completion,
			        COALESCE( SUM(s.unique_watch_seconds), 0 ) / 3600 AS watch_hours,
			        COALESCE( SUM(s.reached_end), 0 )           AS completions,
			        MIN(s.first_play_at)                        AS first_play_at
			 FROM {$p}vlt_videos v
			 LEFT JOIN {$p}vlt_video_user_summary s ON s.video_id = v.id
			 WHERE v.is_active = 1
			 GROUP BY v.id
			 ORDER BY watch_hours DESC"
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Video Analytics', 'video-lead-tracker' ); ?></h1>
			<a href="<?php echo esc_url( VLT_Exporter::export_url( 'videos' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Export CSV', 'video-lead-tracker' ); ?>
			</a>
			<hr class="wp-header-end">

			<table class="widefat striped vlt-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Video',       'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Viewers',     'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Avg %',       'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Watch hrs',   'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Completions', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'First Play',  'video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $videos ) : ?>
					<?php foreach ( $videos as $v ) :
						$detail_url = add_query_arg( [ 'page' => 'vlt-video-analytics', 'video_id' => $v->id ], admin_url( 'admin.php' ) );
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( $detail_url ); ?>">
								<strong><?php echo esc_html( $v->title ?: $v->video_key ); ?></strong>
							</a>
							<?php if ( $v->duration_seconds ) : ?>
								<br><span class="vlt-muted"><?php echo esc_html( self::format_duration( (int) $v->duration_seconds ) ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $v->viewers ) ); ?></td>
						<td>
							<div class="vlt-progress">
								<div class="vlt-progress-bar" style="width:<?php echo esc_attr( min( 100, (int) round( $v->avg_completion ) ) ); ?>%"></div>
								<span><?php echo esc_html( round( (float) $v->avg_completion, 1 ) . '%' ); ?></span>
							</div>
						</td>
						<td><?php echo esc_html( number_format( (float) $v->watch_hours, 2 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $v->completions ) ); ?></td>
						<td class="vlt-muted"><?php echo esc_html( $v->first_play_at ? wp_date( 'Y-m-d', strtotime( $v->first_play_at ) ) : '—' ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="6" class="vlt-empty"><?php esc_html_e( 'No videos found.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function render_video_analytics_detail( $video_id ) {
		global $wpdb;
		$p = $wpdb->prefix;

		$video = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$p}vlt_videos WHERE id = %d LIMIT 1",
			$video_id
		) );

		if ( ! $video ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Video not found.', 'video-lead-tracker' ) . '</p></div>';
			return;
		}

		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT( DISTINCT CASE WHEN s.lead_id IS NOT NULL
			                THEN CONCAT('l', s.lead_id)
			                ELSE s.visitor_uuid END )               AS viewers,
			        COALESCE( AVG(s.unique_watch_percent), 0 )      AS avg_completion,
			        COALESCE( SUM(s.unique_watch_seconds), 0 ) / 3600 AS watch_hours,
			        COALESCE( SUM(s.reached_end), 0 )               AS completions,
			        COALESCE( SUM(s.sessions_count), 0 )            AS total_sessions
			 FROM {$p}vlt_video_user_summary s WHERE s.video_id = %d",
			$video_id
		) );

		$dist = $wpdb->get_row( $wpdb->prepare(
			"SELECT SUM( CASE WHEN unique_watch_percent < 25 THEN 1 ELSE 0 END )                                   AS b0,
			        SUM( CASE WHEN unique_watch_percent >= 25 AND unique_watch_percent < 50 THEN 1 ELSE 0 END )     AS b25,
			        SUM( CASE WHEN unique_watch_percent >= 50 AND unique_watch_percent < 75 THEN 1 ELSE 0 END )     AS b50,
			        SUM( CASE WHEN unique_watch_percent >= 75 THEN 1 ELSE 0 END )                                   AS b75,
			        COUNT(*)                                                                                         AS total
			 FROM {$p}vlt_video_user_summary WHERE video_id = %d",
			$video_id
		) );

		$top_viewers = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.primary_name, l.normalized_mobile, l.is_verified,
			        s.unique_watch_percent, s.unique_watch_seconds, s.sessions_count,
			        s.reached_end, s.first_play_at, s.visitor_uuid, s.lead_id
			 FROM {$p}vlt_video_user_summary s
			 LEFT JOIN {$p}vlt_leads l ON l.id = s.lead_id
			 WHERE s.video_id = %d
			 ORDER BY s.unique_watch_percent DESC
			 LIMIT 20",
			$video_id
		) );

		$back_url   = admin_url( 'admin.php?page=vlt-video-analytics' );
		$dist_total = max( 1, (int) ( $dist->total ?? 1 ) );
		$dist_colors = [ '#f0a0a0', '#f5c842', '#6ab04c', '#2271b1' ];
		$dist_buckets = [
			[ '0–25%',   (int) ( $dist->b0  ?? 0 ) ],
			[ '25–50%',  (int) ( $dist->b25 ?? 0 ) ],
			[ '50–75%',  (int) ( $dist->b50 ?? 0 ) ],
			[ '75–100%', (int) ( $dist->b75 ?? 0 ) ],
		];
		?>
		<div class="wrap">

			<h1 class="wp-heading-inline">
				<a href="<?php echo esc_url( $back_url ); ?>" class="vlt-back-link">&larr; <?php esc_html_e( 'Video Analytics', 'video-lead-tracker' ); ?></a>
				<?php echo esc_html( $video->title ?: $video->video_key ); ?>
			</h1>
			<?php if ( $video->duration_seconds ) : ?>
				<span class="vlt-muted" style="margin-left:10px"><?php echo esc_html( self::format_duration( (int) $video->duration_seconds ) ); ?></span>
			<?php endif; ?>
			<hr class="wp-header-end">

			<div class="vlt-kpi-row">
				<?php
				$kpi_cards = [
					[ __( 'Viewers',     'video-lead-tracker' ), number_format_i18n( (int) $stats->viewers ),                   'dashicons-groups',     '#2271b1' ],
					[ __( 'Avg %',       'video-lead-tracker' ), round( (float) $stats->avg_completion, 1 ) . '%',               'dashicons-chart-bar',  '#1a9e6a' ],
					[ __( 'Watch Hours', 'video-lead-tracker' ), number_format( (float) $stats->watch_hours, 2 ),                'dashicons-visibility', '#c22f3a' ],
					[ __( 'Completions', 'video-lead-tracker' ), number_format_i18n( (int) $stats->completions ),                'dashicons-yes-alt',    '#6e3ec3' ],
					[ __( 'Sessions',    'video-lead-tracker' ), number_format_i18n( (int) $stats->total_sessions ),             'dashicons-clock',      '#e06800' ],
				];
				foreach ( $kpi_cards as [ $label, $value, $icon, $color ] ) :
				?>
				<div class="vlt-kpi-card">
					<span class="vlt-kpi-icon dashicons <?php echo esc_attr( $icon ); ?>" style="color:<?php echo esc_attr( $color ); ?>"></span>
					<strong class="vlt-kpi-number"><?php echo esc_html( $value ); ?></strong>
					<span class="vlt-kpi-label"><?php echo esc_html( $label ); ?></span>
				</div>
				<?php endforeach; ?>
			</div>

			<h2 class="vlt-section-title"><?php esc_html_e( 'Watch Distribution', 'video-lead-tracker' ); ?></h2>
			<div class="vlt-distribution">
				<?php foreach ( $dist_buckets as $idx => [ $label, $count ] ) :
					$pct = round( $count / $dist_total * 100, 1 );
				?>
				<div class="vlt-dist-row">
					<span class="vlt-dist-label"><?php echo esc_html( $label ); ?></span>
					<div class="vlt-dist-bar-wrap">
						<div class="vlt-dist-bar" style="width:<?php echo esc_attr( $pct ); ?>%;background:<?php echo esc_attr( $dist_colors[ $idx ] ); ?>"></div>
					</div>
					<span class="vlt-dist-count"><?php echo esc_html( number_format_i18n( $count ) . ' (' . $pct . '%)' ); ?></span>
				</div>
				<?php endforeach; ?>
			</div>

			<h2 class="vlt-section-title"><?php esc_html_e( 'Top Viewers', 'video-lead-tracker' ); ?></h2>
			<table class="widefat striped vlt-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Viewer',       'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Watch %',      'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Unique Watch', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Sessions',     'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Completed',    'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'First Play',   'video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $top_viewers ) : ?>
					<?php foreach ( $top_viewers as $v ) : ?>
					<tr>
						<td>
							<?php if ( $v->lead_id ) :
								$lead_url = add_query_arg( [ 'page' => 'vlt-leads', 'lead_id' => $v->lead_id ], admin_url( 'admin.php' ) );
							?>
								<a href="<?php echo esc_url( $lead_url ); ?>"><?php echo esc_html( $v->primary_name ?: __( '(no name)', 'video-lead-tracker' ) ); ?></a>
								<br><code class="vlt-mono"><?php echo esc_html( self::mask_mobile( $v->normalized_mobile ) ); ?></code>
							<?php else : ?>
								<span class="vlt-muted"><?php esc_html_e( 'Anonymous', 'video-lead-tracker' ); ?></span>
								<br><code class="vlt-mono" style="font-size:11px"><?php echo esc_html( substr( $v->visitor_uuid, 0, 8 ) . '…' ); ?></code>
							<?php endif; ?>
						</td>
						<td>
							<div class="vlt-progress">
								<div class="vlt-progress-bar" style="width:<?php echo esc_attr( min( 100, (int) round( $v->unique_watch_percent ) ) ); ?>%"></div>
								<span><?php echo esc_html( round( (float) $v->unique_watch_percent, 1 ) . '%' ); ?></span>
							</div>
						</td>
						<td><?php echo esc_html( round( (float) $v->unique_watch_seconds ) . 's' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $v->sessions_count ) ); ?></td>
						<td>
							<?php if ( $v->reached_end ) : ?>
								<span class="vlt-badge vlt-badge--green"><?php esc_html_e( 'Yes', 'video-lead-tracker' ); ?></span>
							<?php else : ?>
								<span class="vlt-badge"><?php esc_html_e( 'No', 'video-lead-tracker' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="vlt-muted"><?php echo esc_html( $v->first_play_at ? wp_date( 'Y-m-d H:i', strtotime( $v->first_play_at ) ) : '—' ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="6" class="vlt-empty"><?php esc_html_e( 'No viewers yet.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Heatmap (Phase 17)
	// -------------------------------------------------------------------------

	public static function render_heatmap() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$p = $wpdb->prefix;

		$video_id = isset( $_GET['video_id'] ) ? absint( $_GET['video_id'] ) : 0;

		$all_videos = $wpdb->get_results(
			"SELECT id, title, video_key, duration_seconds FROM {$p}vlt_videos WHERE is_active = 1 ORDER BY created_at DESC"
		);

		// Auto-calculate bucket size so the chart has at most ~120 bars.
		$bucket = 1;
		if ( $video_id ) {
			$selected_video = null;
			foreach ( $all_videos as $v ) {
				if ( (int) $v->id === $video_id ) {
					$selected_video = $v;
					break;
				}
			}
			$duration_s = $selected_video ? (int) $selected_video->duration_seconds : 0;
			if ( $duration_s > 0 ) {
				$ideal        = (int) ceil( $duration_s / 120 );
				$bucket_steps = [ 1, 5, 10, 30, 60, 300, 600 ];
				foreach ( $bucket_steps as $step ) {
					if ( $step >= $ideal ) {
						$bucket = $step;
						break;
					}
				}
				if ( $bucket === 1 && $ideal > 600 ) {
					$bucket = (int) ceil( $ideal / 60 ) * 60;
				}
			}
		}

		$heatmap_data = [];
		$max_total    = 1;
		$max_unique   = 1;

		if ( $video_id ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT FLOOR(second_index / %d) * %d AS bucket_start,
				        SUM(total_views_count)         AS total,
				        SUM(unique_visitors_count)     AS unique_visitors,
				        SUM(unique_leads_count)        AS unique_leads
				 FROM {$p}vlt_video_heatmap
				 WHERE video_id = %d
				 GROUP BY bucket_start
				 ORDER BY bucket_start ASC",
				$bucket, $bucket, $video_id
			) );

			foreach ( $rows as $row ) {
				$entry = [
					'second'          => (int) $row->bucket_start,
					'total'           => (int) $row->total,
					'unique_visitors' => (int) $row->unique_visitors,
					'unique_leads'    => (int) $row->unique_leads,
				];
				$heatmap_data[] = $entry;
				$max_total  = max( $max_total,  $entry['total'] );
				$max_unique = max( $max_unique, $entry['unique_visitors'] );
			}
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Heatmap', 'video-lead-tracker' ); ?></h1>
			<?php if ( $video_id ) : ?>
				<a href="<?php echo esc_url( VLT_Exporter::export_url( 'heatmap', [ 'video_id' => $video_id ] ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Export Heatmap CSV', 'video-lead-tracker' ); ?>
				</a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<form method="get" class="vlt-hm-selector">
				<input type="hidden" name="page" value="vlt-heatmap">
				<select name="video_id" class="vlt-hm-select" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( '— Select a video —', 'video-lead-tracker' ); ?></option>
					<?php foreach ( $all_videos as $v ) : ?>
						<option value="<?php echo esc_attr( $v->id ); ?>" <?php selected( $video_id, (int) $v->id ); ?>>
							<?php echo esc_html(
								( $v->title ?: $v->video_key )
								. ( $v->duration_seconds ? ' (' . self::format_duration( (int) $v->duration_seconds ) . ')' : '' )
							); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</form>

			<?php if ( ! $video_id ) : ?>
				<p class="vlt-muted" style="margin-top:20px"><?php esc_html_e( 'Select a video to view its heatmap.', 'video-lead-tracker' ); ?></p>

			<?php elseif ( empty( $heatmap_data ) ) : ?>
				<p class="vlt-muted" style="margin-top:20px"><?php esc_html_e( 'No heatmap data yet.', 'video-lead-tracker' ); ?></p>

			<?php else : ?>

				<div class="vlt-hm-toolbar">
					<span class="vlt-hm-label"><?php esc_html_e( 'Metric:', 'video-lead-tracker' ); ?></span>
					<button type="button" class="button button-primary vlt-hm-metric" data-metric="total"><?php esc_html_e( 'Total Views',      'video-lead-tracker' ); ?></button>
					<button type="button" class="button vlt-hm-metric"               data-metric="unique_visitors"><?php esc_html_e( 'Unique Visitors', 'video-lead-tracker' ); ?></button>
					<button type="button" class="button vlt-hm-metric"               data-metric="unique_leads"><?php esc_html_e( 'Unique Leads',    'video-lead-tracker' ); ?></button>
				</div>

				<div class="vlt-hm-wrap"
				     id="vlt-hm-wrap"
				     data-buckets="<?php echo esc_attr( wp_json_encode( $heatmap_data ) ); ?>"
				     data-max-total="<?php echo esc_attr( $max_total ); ?>"
				     data-max-unique="<?php echo esc_attr( $max_unique ); ?>"
				     data-bucket-size="<?php echo esc_attr( $bucket ); ?>">
					<div class="vlt-hm-chart" id="vlt-hm-chart"></div>
					<div class="vlt-hm-axis"  id="vlt-hm-axis"></div>
				</div>

				<p class="vlt-muted" style="font-size:12px;margin-top:6px">
					<?php printf(
						/* translators: %d = bucket size in seconds */
						esc_html__( 'Bucket size: %d second(s). Hover over a bar for details.', 'video-lead-tracker' ),
						$bucket
					); ?>
				</p>

			<?php endif; ?>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Logs (debug log viewer)
	// -------------------------------------------------------------------------

	public static function render_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$p = $wpdb->prefix;

		$level   = isset( $_GET['vlt_level'] ) ? sanitize_key( $_GET['vlt_level'] ) : '';
		$allowed = [ '', 'error', 'warning', 'info', 'debug' ];
		if ( ! in_array( $level, $allowed, true ) ) {
			$level = '';
		}

		$where = $level ? $wpdb->prepare( 'WHERE level = %s', $level ) : '';

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vlt_logs $where" );
		$rows  = $wpdb->get_results(
			"SELECT id, level, context, message, metadata, created_at
			 FROM {$p}vlt_logs $where
			 ORDER BY id DESC
			 LIMIT 200"
		);

		$level_colors = [
			'error'   => '#c22f3a',
			'warning' => '#d97f00',
			'info'    => '#2271b1',
			'debug'   => '#646970',
		];

		$filter_url = admin_url( 'admin.php?page=vlt-logs' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Logs', 'video-lead-tracker' ); ?></h1>
			<hr class="wp-header-end">

			<div style="margin:12px 0;display:flex;gap:6px;align-items:center">
				<span style="font-size:13px"><?php esc_html_e( 'Filter:', 'video-lead-tracker' ); ?></span>
				<a href="<?php echo esc_url( $filter_url ); ?>"
				   class="button<?php echo $level === '' ? ' button-primary' : ''; ?>">
					<?php esc_html_e( 'All', 'video-lead-tracker' ); ?>
				</a>
				<?php foreach ( [ 'error', 'warning', 'info', 'debug' ] as $lvl ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'vlt_level', $lvl, $filter_url ) ); ?>"
					   class="button<?php echo $level === $lvl ? ' button-primary' : ''; ?>">
						<?php echo esc_html( ucfirst( $lvl ) ); ?>
					</a>
				<?php endforeach; ?>
				<span class="vlt-muted" style="margin-left:8px">
					<?php printf(
						/* translators: %d = number of log entries shown */
						esc_html__( 'Showing last %d of %d entries', 'video-lead-tracker' ),
						min( 200, $total ),
						$total
					); ?>
				</span>
			</div>

			<table class="widefat striped vlt-table">
				<thead>
					<tr>
						<th style="width:50px">ID</th>
						<th style="width:80px"><?php esc_html_e( 'Level',   'video-lead-tracker' ); ?></th>
						<th style="width:130px"><?php esc_html_e( 'Context', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Message', 'video-lead-tracker' ); ?></th>
						<th style="width:150px"><?php esc_html_e( 'Time',    'video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $rows ) : ?>
					<?php foreach ( $rows as $row ) :
						$color = $level_colors[ $row->level ] ?? '#646970';
					?>
					<tr>
						<td class="vlt-muted"><?php echo esc_html( $row->id ); ?></td>
						<td>
							<span class="vlt-badge" style="background:<?php echo esc_attr( $color ); ?>;color:#fff">
								<?php echo esc_html( strtoupper( $row->level ) ); ?>
							</span>
						</td>
						<td class="vlt-muted"><?php echo esc_html( $row->context ?: '—' ); ?></td>
						<td>
							<?php echo esc_html( $row->message ); ?>
							<?php if ( $row->metadata ) : ?>
								<details style="margin-top:4px">
									<summary class="vlt-muted" style="cursor:pointer;font-size:11px"><?php esc_html_e( 'metadata', 'video-lead-tracker' ); ?></summary>
									<pre style="font-size:11px;white-space:pre-wrap;margin:4px 0 0"><?php echo esc_html( $row->metadata ); ?></pre>
								</details>
							<?php endif; ?>
						</td>
						<td class="vlt-muted"><?php echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $row->created_at ) ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5" class="vlt-empty"><?php esc_html_e( 'No log entries.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a sortable column header link.
	 * Clicking the same column toggles ASC ↔ DESC; clicking a new column defaults to ASC.
	 */
	private static function sort_link( $label, $col, $current_col, $current_dir, $base_url ) {
		$dir = ( $current_col === $col && $current_dir === 'ASC' ) ? 'DESC' : 'ASC';
		$url = add_query_arg( [ 'orderby' => $col, 'order' => $dir, 'paged' => 1 ], $base_url );

		$icon = '';
		if ( $current_col === $col ) {
			$icon = $current_dir === 'ASC'
				? ' <span class="dashicons dashicons-arrow-up-alt2" style="vertical-align:middle;font-size:14px;width:14px;height:14px"></span>'
				: ' <span class="dashicons dashicons-arrow-down-alt2" style="vertical-align:middle;font-size:14px;width:14px;height:14px"></span>';
		}

		return '<a href="' . esc_url( $url ) . '" style="text-decoration:none;color:inherit;white-space:nowrap">'
			. esc_html( $label ) . $icon . '</a>';
	}

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
