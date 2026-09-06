<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var string $page
 * @var array  $videos
 * @var int    $current_id
 * @var bool   $ajax
 */
?>
<div class="vlt-video-filter vlt-video-id-filter<?php echo $ajax ? ' vlt-video-filter--ajax' : ''; ?>"
     data-page="<?php echo esc_attr( $page ); ?>">
	<label class="vlt-video-filter-label" for="vlt-video-id-filter-<?php echo esc_attr( $page ); ?>">
		<?php esc_html_e( 'Video', 'video-lead-tracker' ); ?>
	</label>
	<?php if ( $ajax ) : ?>
		<select id="vlt-video-id-filter-<?php echo esc_attr( $page ); ?>"
		        class="vlt-select vlt-video-filter-select"
		        data-vlt-video-id-filter>
			<option value=""><?php esc_html_e( '— Select a video —', 'video-lead-tracker' ); ?></option>
			<?php foreach ( $videos as $v ) : ?>
				<option value="<?php echo esc_attr( (int) $v->id ); ?>" <?php selected( $current_id, (int) $v->id ); ?>>
					<?php
					echo esc_html( $v->title ?: $v->video_key );
					if ( ! empty( $v->duration_seconds ) ) {
						echo esc_html( ' (' . VLT_Admin::format_duration( (int) $v->duration_seconds ) . ')' );
					}
					?>
				</option>
			<?php endforeach; ?>
		</select>
	<?php else : ?>
		<form method="get" class="vlt-video-filter-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>">
			<select id="vlt-video-id-filter-<?php echo esc_attr( $page ); ?>"
			        class="vlt-select"
			        name="video_id"
			        onchange="this.form.submit()">
				<option value=""><?php esc_html_e( '— Select a video —', 'video-lead-tracker' ); ?></option>
				<?php foreach ( $videos as $v ) : ?>
					<option value="<?php echo esc_attr( (int) $v->id ); ?>" <?php selected( $current_id, (int) $v->id ); ?>>
						<?php echo esc_html( $v->title ?: $v->video_key ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>
	<?php endif; ?>
</div>
