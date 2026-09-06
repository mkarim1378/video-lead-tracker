<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var string $page
 * @var array  $videos
 * @var string $current_key
 * @var array  $keep_get
 * @var bool   $ajax
 */
$clear_url = admin_url( 'admin.php?page=' . $page );
if ( ! empty( $keep_get ) ) {
	$clear_url = add_query_arg( $keep_get, $clear_url );
}
?>
<div class="vlt-video-filter<?php echo $ajax ? ' vlt-video-filter--ajax' : ''; ?>"
     data-page="<?php echo esc_attr( $page ); ?>">
	<?php if ( $ajax ) : ?>
		<label class="vlt-video-filter-label" for="vlt-video-filter-select">
			<?php esc_html_e( 'Video', 'video-lead-tracker' ); ?>
		</label>
		<select id="vlt-video-filter-select"
		        class="vlt-select vlt-video-filter-select"
		        name="video"
		        data-vlt-video-filter>
			<option value=""><?php esc_html_e( '— All Videos —', 'video-lead-tracker' ); ?></option>
			<?php foreach ( $videos as $v ) : ?>
				<option value="<?php echo esc_attr( $v->video_key ); ?>" <?php selected( $current_key, $v->video_key ); ?>>
					<?php echo esc_html( $v->title ?: $v->video_key ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( $current_key ) : ?>
			<button type="button" class="vlt-btn vlt-btn--ghost vlt-video-filter-clear" data-vlt-video-filter-clear>
				<?php esc_html_e( 'Clear', 'video-lead-tracker' ); ?>
			</button>
		<?php endif; ?>
	<?php else : ?>
		<form method="get" class="vlt-video-filter-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>">
			<?php foreach ( $keep_get as $k => $val ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( (string) $val ); ?>">
			<?php endforeach; ?>
			<label class="screen-reader-text" for="vlt-video-filter-select-<?php echo esc_attr( $page ); ?>">
				<?php esc_html_e( 'Video', 'video-lead-tracker' ); ?>
			</label>
			<select id="vlt-video-filter-select-<?php echo esc_attr( $page ); ?>"
			        class="vlt-select vlt-video-filter-select"
			        name="video"
			        onchange="this.form.submit()">
				<option value=""><?php esc_html_e( '— All Videos —', 'video-lead-tracker' ); ?></option>
				<?php foreach ( $videos as $v ) : ?>
					<option value="<?php echo esc_attr( $v->video_key ); ?>" <?php selected( $current_key, $v->video_key ); ?>>
						<?php echo esc_html( $v->title ?: $v->video_key ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if ( $current_key ) : ?>
				<a href="<?php echo esc_url( $clear_url ); ?>" class="vlt-btn vlt-btn--ghost">
					<?php esc_html_e( 'Clear', 'video-lead-tracker' ); ?>
				</a>
			<?php endif; ?>
		</form>
	<?php endif; ?>
</div>
