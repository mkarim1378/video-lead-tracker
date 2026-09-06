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
		<?php VLT_Admin_UI::render( 'partials/kpi-card', [ 'card' => $card ] ); ?>
	<?php endforeach; ?>
</div>
