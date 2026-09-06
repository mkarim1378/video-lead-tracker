<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data From VLT_Admin_Ops::get_logs() */
$level = $data['level'];
?>
<div class="vlt-logs" id="vlt-logs-root" data-level="<?php echo esc_attr( $level ); ?>">

	<div class="vlt-toolbar vlt-logs-filters" id="vlt-logs-filters">
		<span class="vlt-muted"><?php esc_html_e( 'Filter:', 'video-lead-tracker' ); ?></span>
		<button type="button" class="vlt-btn <?php echo $level === '' ? 'vlt-btn--primary' : 'vlt-btn--ghost'; ?> vlt-log-level" data-level="">
			<?php esc_html_e( 'All', 'video-lead-tracker' ); ?>
		</button>
		<?php foreach ( [ 'error', 'warning', 'info', 'debug' ] as $lvl ) : ?>
			<button type="button" class="vlt-btn <?php echo $level === $lvl ? 'vlt-btn--primary' : 'vlt-btn--ghost'; ?> vlt-log-level" data-level="<?php echo esc_attr( $lvl ); ?>">
				<?php echo esc_html( ucfirst( $lvl ) ); ?>
			</button>
		<?php endforeach; ?>
		<span class="vlt-muted" id="vlt-logs-count">
			<?php
			printf(
				/* translators: 1: shown 2: total */
				esc_html__( 'Showing last %1$d of %2$d entries', 'video-lead-tracker' ),
				(int) $data['shown'],
				(int) $data['total']
			);
			?>
		</span>
	</div>

	<div class="vlt-panel">
		<div class="vlt-panel-body">
			<table class="vlt-data-table" id="vlt-logs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Level', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Context', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Message', 'video-lead-tracker' ); ?></th>
						<th><?php esc_html_e( 'Time', 'video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody id="vlt-logs-tbody">
				<?php if ( $data['rows'] ) : ?>
					<?php foreach ( $data['rows'] as $row ) : ?>
					<tr>
						<td class="vlt-muted"><?php echo esc_html( (string) $row['id'] ); ?></td>
						<td><span class="vlt-badge vlt-badge--<?php echo esc_attr( $row['level'] ); ?>"><?php echo esc_html( strtoupper( $row['level'] ) ); ?></span></td>
						<td class="vlt-muted"><?php echo esc_html( $row['context'] ); ?></td>
						<td>
							<?php echo esc_html( $row['message'] ); ?>
							<?php if ( $row['metadata'] ) : ?>
								<details class="vlt-log-meta">
									<summary><?php esc_html_e( 'metadata', 'video-lead-tracker' ); ?></summary>
									<pre><?php echo esc_html( $row['metadata'] ); ?></pre>
								</details>
							<?php endif; ?>
						</td>
						<td class="vlt-muted"><?php echo esc_html( $row['time'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="5" class="vlt-empty"><?php esc_html_e( 'No log entries.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
