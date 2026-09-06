<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $videos From VLT_Admin::get_videos_analytics_list() */
?>
<div class="vlt-analytics" id="vlt-analytics-list-root">
	<div class="vlt-panel">
		<div class="vlt-panel-body">
			<?php if ( empty( $videos ) ) : ?>
				<?php
				VLT_Admin_UI::render( 'partials/empty-state', [
					'icon'    => 'video-alt3',
					'title'   => __( 'No videos yet', 'video-lead-tracker' ),
					'message' => __( 'Add an active video to see analytics.', 'video-lead-tracker' ),
				] );
				?>
			<?php else : ?>
				<table class="vlt-data-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Video', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Viewers', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Leads', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Avg %', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Watch hrs', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Completions', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Top Exit', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'First Play', 'video-lead-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $videos as $v ) :
						$detail_url = add_query_arg( [ 'page' => 'vlt-video-analytics', 'video_id' => $v['id'] ], admin_url( 'admin.php' ) );
						?>
						<tr>
							<td>
								<a class="vlt-link" href="<?php echo esc_url( $detail_url ); ?>">
									<strong><?php echo esc_html( $v['title'] ); ?></strong>
								</a>
								<?php if ( $v['duration'] ) : ?>
									<br><span class="vlt-muted"><?php echo esc_html( $v['duration'] ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $v['viewers'] ); ?></td>
							<td><?php echo esc_html( $v['leads'] ); ?></td>
							<td>
								<div class="vlt-progress">
									<div class="vlt-progress-track">
										<div class="vlt-progress-bar" style="--vlt-bar:<?php echo esc_attr( (string) $v['avg_pct_int'] ); ?>%"></div>
									</div>
									<span><?php echo esc_html( $v['avg_pct'] ); ?></span>
								</div>
							</td>
							<td><?php echo esc_html( $v['watch_hours'] ); ?></td>
							<td><?php echo esc_html( $v['completions'] ); ?></td>
							<td class="vlt-muted"><?php echo esc_html( $v['top_exit'] ); ?></td>
							<td class="vlt-muted"><?php echo esc_html( $v['first_play'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
