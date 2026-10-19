<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array       $data      From VLT_SMS_Log_Admin::get_rows()
 * @var string      $status
 * @var int         $video_id
 * @var object|null $video
 */
$base_url = admin_url( 'admin.php?page=vlt-sms-log' );
$badges   = [
	'sent'    => 'success',
	'queued'  => 'info',
	'failed'  => 'error',
	'skipped' => 'warning',
];
?>
<div class="vlt-logs">

	<div class="vlt-toolbar vlt-logs-filters">
		<span class="vlt-muted"><?php esc_html_e( 'Filter:', 'video-lead-tracker' ); ?></span>
		<?php foreach ( VLT_SMS_Log_Admin::statuses() as $key => $label ) : ?>
			<a class="vlt-btn <?php echo $status === $key ? 'vlt-btn--primary' : 'vlt-btn--ghost'; ?>"
			   href="<?php echo esc_url( add_query_arg( array_filter( [ 'status' => $key, 'video_id' => $video_id ] ), $base_url ) ); ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
		<?php if ( $video ) : ?>
			<span class="vlt-badge vlt-badge--info"><?php echo esc_html( $video->title ?: $video->video_key ); ?></span>
			<a class="vlt-link" href="<?php echo esc_url( add_query_arg( array_filter( [ 'status' => $status ] ), $base_url ) ); ?>"><?php esc_html_e( 'All videos', 'video-lead-tracker' ); ?></a>
		<?php endif; ?>
		<span class="vlt-muted">
			<?php
			printf(
				/* translators: 1: shown 2: total */
				esc_html__( 'Showing last %1$d of %2$d entries', 'video-lead-tracker' ),
				(int) min( VLT_SMS_Log_Admin::LIMIT, $data['total'] ),
				(int) $data['total']
			);
			?>
		</span>
	</div>

	<div class="vlt-panel">
		<div class="vlt-panel-body vlt-table-scroll">
			<table class="vlt-data-table vlt-data-table--cards">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Time', 'video-lead-tracker' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Video', 'video-lead-tracker' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Send at', 'video-lead-tracker' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lead', 'video-lead-tracker' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'video-lead-tracker' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Message', 'video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( $data['rows'] ) : ?>
					<?php foreach ( $data['rows'] as $row ) : ?>
					<tr>
						<td class="vlt-muted" data-label="<?php esc_attr_e( 'Time', 'video-lead-tracker' ); ?>">
							<?php echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( ( $row->sent_at ?: $row->queued_at ) . ' UTC' ) ) ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Video', 'video-lead-tracker' ); ?>"><?php echo esc_html( $row->video_title ?: ( $row->video_key ?: '—' ) ); ?></td>
						<td class="vlt-mono" data-label="<?php esc_attr_e( 'Send at', 'video-lead-tracker' ); ?>">
							<?php echo null !== $row->trigger_second ? esc_html( VLT_Videos_Admin::format_time( $row->trigger_second ) ) : '—'; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Lead', 'video-lead-tracker' ); ?>">
							<?php echo esc_html( $row->primary_name ?: '—' ); ?>
							<span class="vlt-muted vlt-mono" dir="ltr"><?php echo esc_html( VLT_Admin::mask_mobile( $row->normalized_mobile ) ); ?></span>
						</td>
						<td data-label="<?php esc_attr_e( 'Status', 'video-lead-tracker' ); ?>">
							<span class="vlt-badge vlt-badge--<?php echo esc_attr( $badges[ $row->status ] ?? 'debug' ); ?>">
								<?php echo esc_html( VLT_SMS_Log_Admin::statuses()[ $row->status ] ?? $row->status ); ?>
							</span>
							<?php if ( 'skipped' === $row->status && $row->skip_reason ) : ?>
								<div class="vlt-muted"><?php echo esc_html( VLT_SMS_Log_Admin::skip_label( $row->skip_reason ) ); ?></div>
							<?php elseif ( $row->error ) : ?>
								<div class="vlt-muted"><?php echo esc_html( $row->error ); ?></div>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Message', 'video-lead-tracker' ); ?>"><?php echo esc_html( $row->message ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="6" class="vlt-empty"><?php esc_html_e( 'No SMS sent yet.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
