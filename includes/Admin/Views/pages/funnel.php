<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array $data From VLT_Admin::get_funnel_data()
 */
$video_id = (int) ( $data['video_id'] ?? 0 );
$video    = $data['video'] ?? null;
$steps    = $data['steps'] ?? [];
?>
<div class="vlt-funnel-page" id="vlt-funnel-root" data-video-id="<?php echo esc_attr( (string) $video_id ); ?>">

	<?php if ( ! $video_id ) : ?>
		<?php
		VLT_Admin_UI::render( 'partials/empty-state', [
			'icon'    => 'filter',
			'title'   => __( 'Select a video', 'video-lead-tracker' ),
			'message' => __( 'Choose a video to view its conversion funnel.', 'video-lead-tracker' ),
		] );
		?>
	<?php elseif ( ! $video ) : ?>
		<?php
		VLT_Admin_UI::render( 'partials/empty-state', [
			'icon'    => 'warning',
			'title'   => __( 'Video not found', 'video-lead-tracker' ),
			'message' => __( 'The selected video is missing or inactive.', 'video-lead-tracker' ),
		] );
		?>
	<?php else : ?>
		<div class="vlt-panel">
			<div class="vlt-panel-head">
				<h2 class="vlt-panel-title" id="vlt-funnel-title"><?php echo esc_html( $video['title'] ); ?></h2>
			</div>
			<div class="vlt-panel-body">
				<div class="vlt-funnel" id="vlt-funnel-steps">
					<?php foreach ( $steps as $step ) :
						$inactive = empty( $step['active'] );
						$is_na    = array_key_exists( 'count', $step ) && $step['count'] === null;
						?>
						<div class="vlt-funnel-step<?php echo $inactive ? ' is-inactive' : ''; ?>">
							<span class="vlt-funnel-label">
								<?php echo esc_html( $step['label'] ); ?>
								<?php if ( $inactive ) : ?>
									<span class="vlt-muted"><?php esc_html_e( '(OTP off)', 'video-lead-tracker' ); ?></span>
								<?php endif; ?>
							</span>
							<div class="vlt-funnel-bar-wrap">
								<div class="vlt-funnel-bar" style="--vlt-bar:<?php echo esc_attr( (string) ( $step['bar_pct'] ?? 0 ) ); ?>%"></div>
							</div>
							<span class="vlt-funnel-count">
								<?php if ( $inactive || $is_na ) : ?>
									<span class="vlt-muted"><?php esc_html_e( 'N/A', 'video-lead-tracker' ); ?></span>
								<?php else : ?>
									<?php echo esc_html( $step['count_label'] ); ?>
									<span class="vlt-muted">(<?php echo esc_html( $step['pct_label'] ); ?>%)</span>
									<?php if ( isset( $step['step_conversion'] ) && $step['step_conversion'] !== null ) : ?>
										<span class="vlt-funnel-conv"><?php echo esc_html( $step['step_conversion'] ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
