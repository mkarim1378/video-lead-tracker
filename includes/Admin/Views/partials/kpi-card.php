<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array $card [ key, label, value, icon ]
 */
$key   = $card['key'] ?? '';
$icon  = $card['icon'] ?? 'dashicons-chart-bar';
$value = $card['value'] ?? '';
$label = $card['label'] ?? '';
?>
<div class="vlt-kpi-card"<?php echo $key ? ' data-kpi-key="' . esc_attr( $key ) . '"' : ''; ?>>
	<span class="vlt-kpi-icon dashicons <?php echo esc_attr( $icon ); ?><?php echo $key ? ' vlt-kpi-icon--' . esc_attr( $key ) : ''; ?>" aria-hidden="true"></span>
	<strong class="vlt-kpi-number"><?php echo esc_html( $value ); ?></strong>
	<span class="vlt-kpi-label"><?php echo esc_html( $label ); ?></span>
</div>
