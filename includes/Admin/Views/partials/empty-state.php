<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var string $title
 * @var string $message
 * @var string $icon dashicons class without prefix, e.g. 'video-alt3'
 */
$icon = $icon ?? 'info';
?>
<div class="vlt-empty-state">
	<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
	<?php if ( ! empty( $title ) ) : ?>
		<strong class="vlt-empty-state-title"><?php echo esc_html( $title ); ?></strong>
	<?php endif; ?>
	<?php if ( ! empty( $message ) ) : ?>
		<p class="vlt-empty-state-msg"><?php echo esc_html( $message ); ?></p>
	<?php endif; ?>
</div>
