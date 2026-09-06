<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var string $variant kpi|table-row
 * @var int    $count
 * @var int    $cols
 */
$variant = $variant ?? 'kpi';
$count   = max( 1, (int) ( $count ?? 4 ) );
$cols    = max( 1, (int) ( $cols ?? 5 ) );

if ( 'kpi' === $variant ) :
	?>
	<div class="vlt-kpi-row vlt-skeleton-row" aria-hidden="true">
		<?php for ( $i = 0; $i < $count; $i++ ) : ?>
			<div class="vlt-kpi-card vlt-kpi-card--skeleton">
				<span class="vlt-skeleton vlt-skeleton--icon"></span>
				<span class="vlt-skeleton vlt-skeleton--number"></span>
				<span class="vlt-skeleton vlt-skeleton--label"></span>
			</div>
		<?php endfor; ?>
	</div>
	<?php
elseif ( 'table-row' === $variant ) :
	for ( $r = 0; $r < $count; $r++ ) :
		?>
		<tr class="vlt-skeleton-tr" aria-hidden="true">
			<?php for ( $c = 0; $c < $cols; $c++ ) : ?>
				<td><span class="vlt-skeleton vlt-skeleton--cell"></span></td>
			<?php endfor; ?>
		</tr>
		<?php
	endfor;
endif;
