<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array $data From VLT_Admin::get_video_analytics_detail()
 */
$video   = $data['video'];
$kpis    = $data['kpis'];
$dist    = $data['distribution'];
$viewers = $data['top_viewers'];
?>
<div class="vlt-analytics-detail" id="vlt-analytics-detail-root" data-video-id="<?php echo esc_attr( (string) $video['id'] ); ?>">

	<?php VLT_Admin_UI::render( 'partials/kpi-row', [ 'kpis' => $kpis ] ); ?>

	<section class="vlt-panel vlt-panel--spaced">
		<div class="vlt-panel-head">
			<h2 class="vlt-panel-title"><?php esc_html_e( 'Watch Distribution', 'video-lead-tracker' ); ?></h2>
		</div>
		<div class="vlt-panel-body">
			<div class="vlt-distribution" id="vlt-analytics-distribution">
				<?php foreach ( $dist as $idx => $bucket ) : ?>
					<div class="vlt-dist-row">
						<span class="vlt-dist-label"><?php echo esc_html( $bucket['label'] ); ?></span>
						<div class="vlt-dist-bar-wrap">
							<div class="vlt-dist-bar vlt-dist-bar--<?php echo esc_attr( (string) $idx ); ?>"
							     style="--vlt-bar:<?php echo esc_attr( (string) $bucket['pct'] ); ?>%"></div>
						</div>
						<span class="vlt-dist-count"><?php echo esc_html( $bucket['count_label'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="vlt-panel vlt-panel--spaced">
		<div class="vlt-panel-head">
			<h2 class="vlt-panel-title"><?php esc_html_e( 'Top Viewers', 'video-lead-tracker' ); ?></h2>
		</div>
		<div class="vlt-panel-body">
			<table class="vlt-data-table" id="vlt-analytics-viewers">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Viewer', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Watch %', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Unique Watch', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Sessions', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Completed', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'First Play', 'video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $viewers ) : ?>
					<?php foreach ( $viewers as $v ) : ?>
					<tr>
						<td>
							<?php if ( $v['lead_url'] ) : ?>
								<a class="vlt-link" href="<?php echo esc_url( $v['lead_url'] ); ?>"><?php echo esc_html( $v['name'] ); ?></a>
								<br><code class="vlt-mono"><?php echo esc_html( $v['mobile'] ); ?></code>
							<?php else : ?>
								<span class="vlt-muted"><?php echo esc_html( $v['name'] ); ?></span>
								<br><code class="vlt-mono vlt-mono--sm"><?php echo esc_html( $v['mobile'] ); ?></code>
							<?php endif; ?>
						</td>
						<td>
							<div class="vlt-progress">
								<div class="vlt-progress-track">
									<div class="vlt-progress-bar" style="--vlt-bar:<?php echo esc_attr( (string) $v['watch_pct_int'] ); ?>%"></div>
								</div>
								<span><?php echo esc_html( $v['watch_pct'] ); ?></span>
							</div>
						</td>
						<td><?php echo esc_html( $v['unique_watch'] ); ?></td>
						<td><?php echo esc_html( $v['sessions'] ); ?></td>
						<td>
							<?php if ( $v['completed'] ) : ?>
								<span class="vlt-badge vlt-badge--success"><?php esc_html_e( 'Yes', 'video-lead-tracker' ); ?></span>
							<?php else : ?>
								<span class="vlt-badge"><?php esc_html_e( 'No', 'video-lead-tracker' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="vlt-muted"><?php echo esc_html( $v['first_play'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="6" class="vlt-empty"><?php esc_html_e( 'No viewers yet.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>
</div>
