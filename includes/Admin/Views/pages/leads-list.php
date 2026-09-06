<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data From VLT_Admin_Ops::get_leads_list() */
$orderby = $data['orderby'];
$order   = $data['order'];
$video   = $data['video'];
$search  = $data['s'];
?>
<div class="vlt-leads" id="vlt-leads-root"
     data-video="<?php echo esc_attr( $video ); ?>"
     data-orderby="<?php echo esc_attr( $orderby ); ?>"
     data-order="<?php echo esc_attr( $order ); ?>"
     data-paged="<?php echo esc_attr( (string) $data['paged'] ); ?>">

	<div class="vlt-toolbar">
		<form class="vlt-search-form" id="vlt-leads-search" action="#">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
			       placeholder="<?php esc_attr_e( 'Search leads…', 'video-lead-tracker' ); ?>"
			       class="vlt-input vlt-search-input" id="vlt-leads-search-input" autocomplete="off">
			<button type="submit" class="vlt-btn vlt-btn--secondary"><?php esc_html_e( 'Search', 'video-lead-tracker' ); ?></button>
			<?php if ( $search ) : ?>
				<button type="button" class="vlt-btn vlt-btn--ghost" id="vlt-leads-search-clear"><?php esc_html_e( 'Clear', 'video-lead-tracker' ); ?></button>
			<?php endif; ?>
		</form>
		<span class="vlt-muted" id="vlt-leads-count">
			<?php
			printf(
				/* translators: 1: first 2: last 3: total */
				esc_html__( 'Showing %1$d to %2$d of %3$d', 'video-lead-tracker' ),
				(int) $data['from'],
				(int) $data['to'],
				(int) $data['total']
			);
			?>
		</span>
	</div>

	<div class="vlt-panel">
		<div class="vlt-panel-body vlt-table-scroll">
			<table class="vlt-data-table vlt-data-table--cards" id="vlt-leads-table">
				<thead>
					<tr>
						<th data-sort="id" scope="col" tabindex="0"><?php esc_html_e( '#', 'video-lead-tracker' ); ?></th>
						<th data-sort="primary_name" scope="col" tabindex="0"><?php esc_html_e( 'Name', 'video-lead-tracker' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Mobile', 'video-lead-tracker' ); ?></th>
						<th data-sort="is_verified" scope="col" tabindex="0"><?php esc_html_e( 'Verified', 'video-lead-tracker' ); ?></th>
						<th data-sort="videos_count" scope="col" tabindex="0"><?php esc_html_e( 'Videos', 'video-lead-tracker' ); ?></th>
						<th data-sort="avg_watch" scope="col" tabindex="0"><?php esc_html_e( 'Avg Watch', 'video-lead-tracker' ); ?></th>
						<th data-sort="sessions_count" scope="col" tabindex="0"><?php esc_html_e( 'Sessions', 'video-lead-tracker' ); ?></th>
						<th data-sort="first_seen_at" scope="col" tabindex="0"><?php esc_html_e( 'First Seen', 'video-lead-tracker' ); ?></th>
					</tr>
				</thead>
				<tbody id="vlt-leads-tbody">
				<?php if ( $data['rows'] ) : ?>
					<?php foreach ( $data['rows'] as $row ) : ?>
					<tr>
						<td class="vlt-muted" data-label="<?php esc_attr_e( '#', 'video-lead-tracker' ); ?>"><?php echo esc_html( (string) $row['id'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Name', 'video-lead-tracker' ); ?>"><a class="vlt-link" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['name'] ); ?></a></td>
						<td data-label="<?php esc_attr_e( 'Mobile', 'video-lead-tracker' ); ?>"><code class="vlt-mono"><?php echo esc_html( $row['mobile'] ); ?></code></td>
						<td data-label="<?php esc_attr_e( 'Verified', 'video-lead-tracker' ); ?>">
							<?php if ( $row['verified'] ) : ?>
								<span class="vlt-badge vlt-badge--success"><?php esc_html_e( 'Yes', 'video-lead-tracker' ); ?></span>
							<?php else : ?>
								<span class="vlt-badge"><?php esc_html_e( 'No', 'video-lead-tracker' ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Videos', 'video-lead-tracker' ); ?>"><?php echo esc_html( $row['videos_count'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Avg Watch', 'video-lead-tracker' ); ?>">
							<div class="vlt-progress">
								<div class="vlt-progress-track">
									<div class="vlt-progress-bar" style="--vlt-bar:<?php echo esc_attr( (string) $row['avg_watch_int'] ); ?>%"></div>
								</div>
								<span><?php echo esc_html( $row['avg_watch'] ); ?></span>
							</div>
						</td>
						<td data-label="<?php esc_attr_e( 'Sessions', 'video-lead-tracker' ); ?>"><?php echo esc_html( $row['sessions'] ); ?></td>
						<td class="vlt-muted" data-label="<?php esc_attr_e( 'First Seen', 'video-lead-tracker' ); ?>"><?php echo esc_html( $row['first_seen'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="8" class="vlt-empty"><?php esc_html_e( 'No leads found.', 'video-lead-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php if ( $data['total_pages'] > 1 ) : ?>
		<div class="vlt-panel-foot vlt-pagination" id="vlt-leads-pagination"
		     data-total-pages="<?php echo esc_attr( (string) $data['total_pages'] ); ?>"
		     data-paged="<?php echo esc_attr( (string) $data['paged'] ); ?>">
			<button type="button" class="vlt-btn vlt-btn--ghost" data-page="prev" <?php disabled( $data['paged'] <= 1 ); ?>>&laquo;</button>
			<span class="vlt-muted"><?php echo esc_html( (string) $data['paged'] . ' / ' . $data['total_pages'] ); ?></span>
			<button type="button" class="vlt-btn vlt-btn--ghost" data-page="next" <?php disabled( $data['paged'] >= $data['total_pages'] ); ?>>&raquo;</button>
		</div>
		<?php endif; ?>
	</div>
</div>
