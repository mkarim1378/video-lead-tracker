<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var bool  $is_edit
 * @var array $f
 * @var object|null $video
 * @var string $error
 * @var array  $sms_rows       [ 'id', 'time', 'message' ]
 * @var array  $sms_stats      trigger_id => [ status => count ]
 * @var bool   $global_otp
 * @var bool   $sms_configured
 */
$settings_url = admin_url( 'admin.php?page=vlt-settings' );
?>
<?php if ( $error ) : ?>
	<div class="vlt-inline-notice vlt-inline-notice--error"><?php echo esc_html( $error ); ?></div>
<?php endif; ?>
<form method="post" class="vlt-video-form">
	<?php wp_nonce_field( 'vlt_video_save' ); ?>
	<input type="hidden" name="vlt_video_action" value="save">
	<?php if ( $is_edit ) : ?>
		<input type="hidden" name="video_id" value="<?php echo (int) $video->id; ?>">
	<?php endif; ?>

	<div class="vlt-form-grid">
		<section class="vlt-panel">
			<div class="vlt-panel-head"><h2 class="vlt-panel-title"><?php esc_html_e( 'Media', 'video-lead-tracker' ); ?></h2></div>
			<div class="vlt-panel-body vlt-form-fields">
				<label class="vlt-field">
					<span><?php esc_html_e( 'Video Key', 'video-lead-tracker' ); ?> <span class="vlt-req">*</span></span>
					<input type="text" id="vlt_video_key" name="video_key" value="<?php echo esc_attr( $f['video_key'] ); ?>"
					       class="vlt-input" <?php echo $is_edit ? 'readonly' : ''; ?> required />
					<span class="vlt-field-hint"><?php esc_html_e( 'Unique slug used in the shortcode key attribute. Cannot be changed after creation.', 'video-lead-tracker' ); ?></span>
				</label>
				<label class="vlt-field">
					<span><?php esc_html_e( 'Title', 'video-lead-tracker' ); ?></span>
					<input type="text" id="vlt_title" name="title" value="<?php echo esc_attr( $f['title'] ); ?>" class="vlt-input" />
				</label>
				<label class="vlt-field">
					<span><?php esc_html_e( 'Video URL', 'video-lead-tracker' ); ?></span>
					<div class="vlt-field-row">
						<input type="url" id="vlt_video_url" name="video_url" value="<?php echo esc_attr( $f['video_url'] ); ?>" class="vlt-input" />
						<a id="vlt-form-test-link" class="vlt-btn vlt-btn--ghost<?php echo $f['video_url'] ? '' : ' is-hidden'; ?>"
						   href="<?php echo $f['video_url'] ? esc_url( $f['video_url'] ) : '#'; ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Test', 'video-lead-tracker' ); ?></a>
					</div>
				</label>
				<label class="vlt-field">
					<span><?php esc_html_e( 'Poster Image URL', 'video-lead-tracker' ); ?></span>
					<input type="url" id="vlt_poster_url" name="poster_url" value="<?php echo esc_attr( $f['poster_url'] ); ?>" class="vlt-input" />
					<div id="vlt-form-poster-wrap" class="vlt-poster-preview<?php echo $f['poster_url'] ? '' : ' is-hidden'; ?>">
						<img id="vlt-form-poster-img" src="<?php echo esc_url( $f['poster_url'] ); ?>" alt="">
					</div>
				</label>
				<label class="vlt-field">
					<span><?php esc_html_e( 'Audio URL', 'video-lead-tracker' ); ?></span>
					<input type="url" id="vlt_audio_url" name="audio_url" value="<?php echo esc_attr( $f['audio_url'] ); ?>" class="vlt-input" placeholder="https://example.com/audio.mp3" />
				</label>
				<label class="vlt-field">
					<span><?php esc_html_e( 'Duration (seconds)', 'video-lead-tracker' ); ?></span>
					<input type="number" id="vlt_duration" name="duration_seconds" value="<?php echo esc_attr( (string) $f['duration_seconds'] ); ?>" min="0" class="vlt-input vlt-input--sm" />
				</label>
			</div>
		</section>

		<section class="vlt-panel">
			<div class="vlt-panel-head"><h2 class="vlt-panel-title"><?php esc_html_e( 'Lead Form', 'video-lead-tracker' ); ?></h2></div>
			<div class="vlt-panel-body vlt-form-fields">
				<label class="vlt-field">
					<span><?php esc_html_e( 'Form Title', 'video-lead-tracker' ); ?></span>
					<input type="text" name="form_title" value="<?php echo esc_attr( $f['form_title'] ); ?>" class="vlt-input" />
				</label>
				<label class="vlt-field">
					<span><?php esc_html_e( 'Name Field Label', 'video-lead-tracker' ); ?></span>
					<input type="text" name="name_label" value="<?php echo esc_attr( $f['name_label'] ); ?>" class="vlt-input" />
				</label>
				<label class="vlt-field">
					<span><?php esc_html_e( 'Mobile Field Label', 'video-lead-tracker' ); ?></span>
					<input type="text" name="mobile_label" value="<?php echo esc_attr( $f['mobile_label'] ); ?>" class="vlt-input" />
				</label>
				<label class="vlt-field">
					<span><?php esc_html_e( 'Submit Button Text', 'video-lead-tracker' ); ?></span>
					<input type="text" name="submit_button_text" value="<?php echo esc_attr( $f['submit_button_text'] ); ?>" class="vlt-input" />
				</label>
				<label class="vlt-field">
					<span><?php esc_html_e( 'Success Message', 'video-lead-tracker' ); ?></span>
					<textarea name="success_message" rows="2" class="vlt-input"><?php echo esc_textarea( $f['success_message'] ); ?></textarea>
				</label>
			</div>
		</section>

		<section class="vlt-panel">
			<div class="vlt-panel-head"><h2 class="vlt-panel-title"><?php esc_html_e( 'Options', 'video-lead-tracker' ); ?></h2></div>
			<div class="vlt-panel-body vlt-form-fields">
				<label class="vlt-check">
					<input type="checkbox" name="enable_otp" value="1" <?php checked( $f['enable_otp'] ); ?> />
					<span><?php esc_html_e( 'Require SMS OTP before showing this video', 'video-lead-tracker' ); ?></span>
				</label>
				<label class="vlt-check">
					<input type="checkbox" name="enable_sms_triggers" value="1" id="vlt_enable_sms" <?php checked( $f['enable_sms_triggers'] ); ?> />
					<span><?php esc_html_e( 'Send SMS during video playback', 'video-lead-tracker' ); ?></span>
				</label>

				<div id="vlt-sms-triggers" class="vlt-sms-triggers<?php echo $f['enable_sms_triggers'] ? '' : ' is-hidden'; ?>"
				     data-global-otp="<?php echo $global_otp ? '1' : '0'; ?>">

					<div class="vlt-inline-notice vlt-inline-notice--warning vlt-sms-otp-warning<?php echo ( $f['enable_otp'] && $global_otp ) ? ' is-hidden' : ''; ?>">
						<?php esc_html_e( 'OTP verification is off for this video, so mobile numbers are not verified and the SMS may be sent to a number that does not belong to the viewer.', 'video-lead-tracker' ); ?>
						<?php if ( ! $global_otp ) : ?>
							<a class="vlt-link" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'OTP is also off in Settings.', 'video-lead-tracker' ); ?></a>
						<?php endif; ?>
					</div>

					<?php if ( ! $sms_configured ) : ?>
						<div class="vlt-inline-notice vlt-inline-notice--warning">
							<?php esc_html_e( 'The SMS provider is not configured yet, so messages will fail.', 'video-lead-tracker' ); ?>
							<a class="vlt-link" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Open Settings', 'video-lead-tracker' ); ?></a>
						</div>
					<?php endif; ?>

					<div class="vlt-sms-rows">
						<?php
						if ( ! $sms_rows ) {
							$sms_rows = [ [ 'id' => 0, 'time' => '', 'message' => '' ] ];
						}
						foreach ( $sms_rows as $i => $row ) {
							$stats = $sms_stats[ (int) $row['id'] ] ?? [];
							include __DIR__ . '/../partials/sms-trigger-row.php';
						}
						?>
					</div>

					<template id="vlt-sms-row-template">
						<?php
						$i     = '__INDEX__';
						$row   = [ 'id' => 0, 'time' => '', 'message' => '' ];
						$stats = [];
						include __DIR__ . '/../partials/sms-trigger-row.php';
						?>
					</template>

					<button type="button" class="vlt-btn vlt-btn--ghost vlt-sms-add">
						<?php esc_html_e( '+ Add another send time', 'video-lead-tracker' ); ?>
					</button>
					<p class="vlt-field-hint">
						<?php esc_html_e( 'Each lead gets each message once, up to about 10 seconds after they watch past that point. Skipping past it with the seek bar does not count.', 'video-lead-tracker' ); ?>
						<?php if ( $is_edit ) : ?>
							<a class="vlt-link" href="<?php echo esc_url( admin_url( 'admin.php?page=vlt-sms-log&video_id=' . (int) $video->id ) ); ?>"><?php esc_html_e( 'View SMS log', 'video-lead-tracker' ); ?></a>
						<?php endif; ?>
					</p>
				</div>
				<label class="vlt-check">
					<input type="checkbox" name="is_active" value="1" <?php checked( $f['is_active'] ); ?> />
					<span><?php esc_html_e( 'Video is active and visible via shortcode', 'video-lead-tracker' ); ?></span>
				</label>
			</div>
		</section>
	</div>

	<div class="vlt-form-actions">
		<button type="submit" class="vlt-btn vlt-btn--primary">
			<?php echo esc_html( $is_edit ? __( 'Update Video', 'video-lead-tracker' ) : __( 'Add Video', 'video-lead-tracker' ) ); ?>
		</button>
	</div>
</form>

<script>
( function () {
	var videoIn  = document.getElementById( 'vlt_video_url' );
	var testLink = document.getElementById( 'vlt-form-test-link' );
	var posterIn = document.getElementById( 'vlt_poster_url' );
	var posterWr = document.getElementById( 'vlt-form-poster-wrap' );
	var posterIm = document.getElementById( 'vlt-form-poster-img' );
	if ( videoIn && testLink ) {
		videoIn.addEventListener( 'input', function () {
			var u = videoIn.value.trim();
			testLink.href = u || '#';
			testLink.classList.toggle( 'is-hidden', ! u );
		} );
	}
	if ( posterIn && posterWr && posterIm ) {
		posterIn.addEventListener( 'blur', function () {
			var u = posterIn.value.trim();
			if ( u ) { posterIm.src = u; posterWr.classList.remove( 'is-hidden' ); }
			else { posterWr.classList.add( 'is-hidden' ); }
		} );
	}
} )();
</script>
