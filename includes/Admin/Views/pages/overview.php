<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Overview KPI + tables (SSR first paint / AJAX re-target root).
 *
 * @var array $data From VLT_Admin::get_overview_data()
 */
$kpis         = $data['kpis'];
$recent_leads = $data['recent_leads'];
$top_videos   = $data['top_videos'];
$video_key    = $data['video_key'];
?>
<div class="vlt-overview" id="vlt-overview-root" data-video="<?php echo esc_attr( $video_key ); ?>">

	<div class="vlt-kpi-row" id="vlt-overview-kpis">
		<?php foreach ( $kpis as $card ) : ?>
			<div class="vlt-kpi-card" data-kpi-key="<?php echo esc_attr( $card['key'] ); ?>">
				<span class="vlt-kpi-icon dashicons <?php echo esc_attr( $card['icon'] ); ?> vlt-kpi-icon--<?php echo esc_attr( $card['key'] ); ?>" aria-hidden="true"></span>
				<strong class="vlt-kpi-number"><?php echo esc_html( $card['value'] ); ?></strong>
				<span class="vlt-kpi-label"><?php echo esc_html( $card['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="vlt-overview-grid">
		<section class="vlt-panel" id="vlt-overview-leads">
			<div class="vlt-panel-head">
				<h2 class="vlt-panel-title"><?php esc_html_e( 'Recent Leads', 'video-lead-tracker' ); ?></h2>
			</div>
			<div class="vlt-panel-body">
				<table class="vlt-data-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Mobile', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Verified', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Avg Watch', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'First Seen', 'video-lead-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( $recent_leads ) : ?>
						<?php foreach ( $recent_leads as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['name'] ); ?></td>
							<td><code class="vlt-mono"><?php echo esc_html( $row['mobile'] ); ?></code></td>
							<td>
								<?php if ( $row['verified'] ) : ?>
									<span class="vlt-badge vlt-badge--success"><?php esc_html_e( 'Yes', 'video-lead-tracker' ); ?></span>
								<?php else : ?>
									<span class="vlt-badge"><?php esc_html_e( 'No', 'video-lead-tracker' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['avg_watch'] ); ?></td>
							<td class="vlt-muted"><?php echo esc_html( $row['first_seen'] ); ?></td>
						</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="5" class="vlt-empty"><?php esc_html_e( 'No leads yet.', 'video-lead-tracker' ); ?></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
			<div class="vlt-panel-foot">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vlt-leads' . ( $video_key ? '&video=' . rawurlencode( $video_key ) : '' ) ) ); ?>" class="vlt-btn vlt-btn--secondary vlt-btn--block">
					<?php esc_html_e( 'View all leads', 'video-lead-tracker' ); ?>
				</a>
			</div>
		</section>

		<section class="vlt-panel" id="vlt-overview-videos">
			<div class="vlt-panel-head">
				<h2 class="vlt-panel-title"><?php esc_html_e( 'Top Videos', 'video-lead-tracker' ); ?></h2>
			</div>
			<div class="vlt-panel-body">
				<table class="vlt-data-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Video', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Viewers', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Completions', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Avg %', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Watch hrs', 'video-lead-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( $top_videos ) : ?>
						<?php foreach ( $top_videos as $video ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $video['title'] ); ?></strong>
								<?php if ( $video['duration'] ) : ?>
									<br><span class="vlt-muted"><?php echo esc_html( $video['duration'] ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $video['viewers'] ); ?></td>
							<td><?php echo esc_html( $video['completions'] ); ?></td>
							<td><?php echo esc_html( $video['avg_completion'] ); ?></td>
							<td><?php echo esc_html( $video['watch_hours'] ); ?></td>
						</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="5" class="vlt-empty"><?php esc_html_e( 'No videos yet.', 'video-lead-tracker' ); ?></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
			<div class="vlt-panel-foot">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vlt-video-analytics' ) ); ?>" class="vlt-btn vlt-btn--secondary vlt-btn--block">
					<?php esc_html_e( 'View analytics', 'video-lead-tracker' ); ?>
				</a>
			</div>
		</section>
	</div>
</div>
