<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Leads + Logs admin surfaces (Phase 2).
 */
class VLT_Admin_Ops {

	public static function render_leads() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$lead_id = isset( $_GET['lead_id'] ) ? absint( $_GET['lead_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $lead_id ) {
			self::render_lead_detail( $lead_id );
			return;
		}
		self::render_lead_list();
	}

	/**
	 * @param array $args
	 * @return array
	 */
	public static function get_leads_list( array $args = [] ) {
		global $wpdb;
		$p = $wpdb->prefix;

		$search  = sanitize_text_field( $args['s'] ?? '' );
		$orderby = sanitize_key( $args['orderby'] ?? 'first_seen_at' );
		$order   = strtoupper( sanitize_key( $args['order'] ?? 'DESC' ) );
		$paged   = max( 1, (int) ( $args['paged'] ?? 1 ) );
		$video   = sanitize_key( $args['video'] ?? '' );

		$per_page = 20;
		$offset   = ( $paged - 1 ) * $per_page;

		$allowed_cols = [ 'id', 'primary_name', 'first_seen_at', 'last_seen_at', 'is_verified', 'avg_watch', 'videos_count', 'sessions_count' ];
		if ( ! in_array( $orderby, $allowed_cols, true ) ) {
			$orderby = 'first_seen_at';
		}
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'DESC';
		}

		$filter_vid_id = 0;
		if ( $video ) {
			foreach ( VLT_DB::get_all_videos() as $v ) {
				if ( $v->video_key === $video ) {
					$filter_vid_id = (int) $v->id;
					break;
				}
			}
			if ( ! $filter_vid_id ) {
				$video = '';
			}
		}

		if ( $filter_vid_id ) {
			$join_sql    = "JOIN {$p}vlt_video_user_summary s ON s.lead_id = l.id AND s.video_id = %d";
			$join_params = [ $filter_vid_id ];
		} else {
			$join_sql    = "LEFT JOIN {$p}vlt_video_user_summary s ON s.lead_id = l.id";
			$join_params = [];
		}

		$where        = '';
		$where_params = [];
		if ( $search !== '' ) {
			$like         = '%' . $wpdb->esc_like( $search ) . '%';
			$where        = 'WHERE ( l.primary_name LIKE %s OR l.normalized_mobile LIKE %s )';
			$where_params = [ $like, $like ];
		}

		$all_params = array_merge( $join_params, $where_params );
		$count_sql  = "SELECT COUNT( DISTINCT l.id ) FROM {$p}vlt_leads l $join_sql $where";
		$total      = (int) ( $all_params
			? $wpdb->get_var( $wpdb->prepare( $count_sql, $all_params ) )
			: $wpdb->get_var( $count_sql )
		);

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$paged       = min( $paged, $total_pages );
		$offset      = ( $paged - 1 ) * $per_page;

		$sql = "SELECT l.id, l.primary_name, l.normalized_mobile, l.is_verified,
		               l.first_seen_at, l.last_seen_at,
		               COUNT( DISTINCT s.video_id )               AS videos_count,
		               COALESCE( AVG(s.unique_watch_percent), 0 ) AS avg_watch,
		               ( SELECT COUNT(*) FROM {$p}vlt_sessions WHERE lead_id = l.id ) AS sessions_count
		        FROM {$p}vlt_leads l
		        $join_sql
		        $where
		        GROUP BY l.id
		        ORDER BY $orderby $order
		        LIMIT %d OFFSET %d";

		$leads = $wpdb->get_results(
			$wpdb->prepare( $sql, array_merge( $join_params, $where_params, [ $per_page, $offset ] ) )
		);

		$rows = [];
		foreach ( (array) $leads as $row ) {
			$avg = (float) $row->avg_watch;
			$rows[] = [
				'id'            => (int) $row->id,
				'name'          => $row->primary_name ?: '—',
				'mobile'        => $row->normalized_mobile,
				'verified'      => (bool) $row->is_verified,
				'videos_count'  => number_format_i18n( (int) $row->videos_count ),
				'avg_watch'     => round( $avg, 1 ) . '%',
				'avg_watch_int' => min( 100, (int) round( $avg ) ),
				'sessions'      => number_format_i18n( (int) $row->sessions_count ),
				'first_seen'    => wp_date( 'Y-m-d', strtotime( $row->first_seen_at ) ),
				'url'           => add_query_arg( [ 'page' => 'vlt-leads', 'lead_id' => $row->id ], admin_url( 'admin.php' ) ),
			];
		}

		return [
			'video'       => $video,
			's'           => $search,
			'orderby'     => $orderby,
			'order'       => $order,
			'paged'       => $paged,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total_pages,
			'from'        => min( $offset + 1, max( $total, 1 ) ),
			'to'          => min( $offset + $per_page, $total ),
			'rows'        => $rows,
			'export_url'  => VLT_Exporter::export_url( 'leads' ),
		];
	}

	private static function render_lead_list() {
		$data = self::get_leads_list( [
			's'       => $_GET['s'] ?? '', // phpcs:ignore WordPress.Security.NonceVerification
			'orderby' => $_GET['orderby'] ?? 'first_seen_at', // phpcs:ignore
			'order'   => $_GET['order'] ?? 'DESC', // phpcs:ignore
			'paged'   => $_GET['paged'] ?? 1, // phpcs:ignore
			'video'   => $_GET['video'] ?? '', // phpcs:ignore
		] );

		ob_start();
		?>
		<a class="vlt-btn vlt-btn--secondary" href="<?php echo esc_url( $data['export_url'] ); ?>">
			<?php esc_html_e( 'Export CSV', 'video-lead-tracker' ); ?>
		</a>
		<?php
		$actions = ob_get_clean();

		VLT_Admin_UI::open( [
			'page'              => 'vlt-leads',
			'title'             => __( 'Leads', 'video-lead-tracker' ),
			'subtitle'          => __( 'Search and review captured leads.', 'video-lead-tracker' ),
			'show_video_filter' => true,
			'ajax_video_filter' => true,
			'actions_html'      => $actions,
		] );
		VLT_Admin_UI::render( 'pages/leads-list', [ 'data' => $data ] );
		VLT_Admin_UI::close();
	}

	private static function render_lead_detail( $lead_id ) {
		VLT_Admin::render_lead_detail( $lead_id );
	}

	/**
	 * @param string $level
	 * @return array
	 */
	public static function get_logs( $level = '' ) {
		global $wpdb;
		$p = $wpdb->prefix;

		$level   = sanitize_key( $level );
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

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[] = [
				'id'       => (int) $row->id,
				'level'    => $row->level,
				'context'  => $row->context ?: '—',
				'message'  => $row->message,
				'metadata' => $row->metadata ?: '',
				'time'     => wp_date( 'Y-m-d H:i:s', strtotime( $row->created_at ) ),
			];
		}

		return [
			'level'   => $level,
			'total'   => $total,
			'shown'   => min( 200, $total ),
			'rows'    => $out,
		];
	}

	public static function render_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$level = isset( $_GET['vlt_level'] ) ? sanitize_key( $_GET['vlt_level'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$data  = self::get_logs( $level );

		ob_start();
		?>
		<button type="button" class="vlt-btn vlt-btn--danger vlt-purge-logs-btn">
			<?php esc_html_e( 'Purge All Logs', 'video-lead-tracker' ); ?>
		</button>
		<?php
		$actions = ob_get_clean();

		VLT_Admin_UI::open( [
			'page'         => 'vlt-logs',
			'title'        => __( 'Logs', 'video-lead-tracker' ),
			'subtitle'     => __( 'Plugin diagnostics and operational messages.', 'video-lead-tracker' ),
			'actions_html' => $actions,
		] );
		VLT_Admin_UI::render( 'pages/logs', [ 'data' => $data ] );
		VLT_Admin_UI::close();
	}
}
