<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array $data From VLT_Admin::get_heatmap_data()
 */
$video_id = (int) ( $data['video_id'] ?? 0 );
$buckets  = $data['buckets'] ?? [];
$bucket   = (int) ( $data['bucket_size'] ?? 1 );
$export   = $data['export_url'] ?? '';
?>
<div class="vlt-heatmap-page" id="vlt-heatmap-root" data-video-id="<?php echo esc_attr( (string) $video_id ); ?>">

	<?php if ( ! $video_id ) : ?>
		<?php
		VLT_Admin_UI::render( 'partials/empty-state', [
			'icon'    => 'chart-line',
			'title'   => __( 'Select a video', 'video-lead-tracker' ),
			'message' => __( 'Choose a video to explore watch heatmap and drop-off.', 'video-lead-tracker' ),
		] );
		?>
	<?php elseif ( empty( $buckets ) ) : ?>
		<?php
		VLT_Admin_UI::render( 'partials/empty-state', [
			'icon'    => 'chart-area',
			'title'   => __( 'No heatmap data yet', 'video-lead-tracker' ),
			'message' => __( 'Data appears after viewers start watching this video.', 'video-lead-tracker' ),
		] );
		?>
	<?php else : ?>
		<div class="vlt-panel">
			<div class="vlt-panel-head vlt-hm-toolbar">
				<span class="vlt-hm-label"><?php esc_html_e( 'Metric:', 'video-lead-tracker' ); ?></span>
				<button type="button" class="vlt-btn vlt-btn--primary vlt-hm-metric" data-metric="total"><?php esc_html_e( 'Total Views', 'video-lead-tracker' ); ?></button>
				<button type="button" class="vlt-btn vlt-btn--ghost vlt-hm-metric" data-metric="unique_visitors"><?php esc_html_e( 'Unique Visitors', 'video-lead-tracker' ); ?></button>
				<button type="button" class="vlt-btn vlt-btn--ghost vlt-hm-metric" data-metric="unique_leads"><?php esc_html_e( 'Unique Leads', 'video-lead-tracker' ); ?></button>
				<button type="button" class="vlt-btn vlt-btn--ghost vlt-hm-metric" data-metric="drop_off"><?php esc_html_e( 'Drop-off Curve', 'video-lead-tracker' ); ?></button>
				<?php if ( $export ) : ?>
					<a class="vlt-btn vlt-btn--secondary" href="<?php echo esc_url( $export ); ?>" id="vlt-hm-export"><?php esc_html_e( 'Export CSV', 'video-lead-tracker' ); ?></a>
				<?php endif; ?>
			</div>
			<div class="vlt-panel-body">
				<div class="vlt-hm-wrap"
				     id="vlt-hm-wrap"
				     data-buckets="<?php echo esc_attr( wp_json_encode( $buckets ) ); ?>"
				     data-max-total="<?php echo esc_attr( (string) $data['max_total'] ); ?>"
				     data-max-unique="<?php echo esc_attr( (string) $data['max_unique'] ); ?>"
				     data-max-drop-off="<?php echo esc_attr( (string) $data['max_drop_off'] ); ?>"
				     data-bucket-size="<?php echo esc_attr( (string) $bucket ); ?>">
					<div class="vlt-hm-chart" id="vlt-hm-chart"></div>
					<div class="vlt-hm-axis" id="vlt-hm-axis"></div>
				</div>
				<p class="vlt-muted vlt-hm-hint">
					<?php
					printf(
						/* translators: %d = bucket size in seconds */
						esc_html__( 'Bucket size: %d second(s). Hover over a point for details.', 'video-lead-tracker' ),
						$bucket
					);
					?>
				</p>
			</div>
		</div>
	<?php endif; ?>
</div>
