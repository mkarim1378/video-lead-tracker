<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array $kpis list of [ key, label, value, icon ]
 */
?>
<div class="vlt-kpi-row">
	<?php foreach ( $kpis as $card ) : ?>
		<div class="vlt-kpi-card"<?php echo ! empty( $card['key'] ) ? ' data-kpi-key="' . esc_attr( $card['key'] ) . '"' : ''; ?>>
			<span class="vlt-kpi-icon dashicons <?php echo esc_attr( $card['icon'] ); ?><?php echo ! empty( $card['key'] ) ? ' vlt-kpi-icon--' . esc_attr( $card['key'] ) : ''; ?>" aria-hidden="true"></span>
			<strong class="vlt-kpi-number"><?php echo esc_html( $card['value'] ); ?></strong>
			<span class="vlt-kpi-label"><?php echo esc_html( $card['label'] ); ?></span>
		</div>
	<?php endforeach; ?>
</div>
