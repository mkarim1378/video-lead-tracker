<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * One SMS trigger row in the video form.
 *
 * @var int|string $i      Row index, or "__INDEX__" inside the JS template.
 * @var array      $row    [ 'id', 'time', 'message' ]
 * @var array      $stats  status => count for this trigger.
 */
$name = 'sms_triggers[' . $i . ']';
?>
<div class="vlt-sms-row">
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo (int) $row['id']; ?>">
	<label class="vlt-field">
		<span><?php esc_html_e( 'Send at (mm:ss)', 'video-lead-tracker' ); ?></span>
		<input type="text" name="<?php echo esc_attr( $name ); ?>[time]" value="<?php echo esc_attr( $row['time'] ); ?>"
		       class="vlt-input vlt-input--sm vlt-sms-time" placeholder="05:30" dir="ltr" inputmode="numeric"
		       pattern="\d+(:\d{1,2}){0,2}" autocomplete="off" />
	</label>
	<label class="vlt-field">
		<span><?php esc_html_e( 'Message', 'video-lead-tracker' ); ?></span>
		<textarea name="<?php echo esc_attr( $name ); ?>[message]" rows="3" class="vlt-input vlt-sms-message"><?php echo esc_textarea( $row['message'] ); ?></textarea>
		<span class="vlt-field-hint">
			<span class="vlt-sms-counter" aria-live="polite"></span>
			· <?php esc_html_e( 'Placeholders:', 'video-lead-tracker' ); ?> <code>{name}</code> <code>{video_title}</code> <code>{site_name}</code>
		</span>
	</label>
	<div class="vlt-sms-row-foot">
		<span class="vlt-muted">
			<?php
			if ( $stats ) {
				printf(
					/* translators: 1: sent count 2: failed count 3: skipped count */
					esc_html__( 'Sent: %1$d · Failed: %2$d · Skipped: %3$d', 'video-lead-tracker' ),
					(int) ( $stats['sent'] ?? 0 ),
					(int) ( $stats['failed'] ?? 0 ),
					(int) ( $stats['skipped'] ?? 0 )
				);
			}
			?>
		</span>
		<button type="button" class="vlt-btn vlt-btn--ghost vlt-btn--danger-text vlt-sms-remove"><?php esc_html_e( 'Remove', 'video-lead-tracker' ); ?></button>
	</div>
</div>
