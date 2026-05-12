<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Aggregator {

	// Full aggregation logic — Phase 13 / 14.

	/**
	 * Merge overlapping or near-contiguous watch ranges.
	 * Tolerance: 0.25 seconds between adjacent ranges.
	 *
	 * @param  array $ranges  Array of [from, to] pairs.
	 * @return array          Merged array of [from, to] pairs.
	 */
	public static function merge_ranges( array $ranges ) {
		if ( empty( $ranges ) ) {
			return [];
		}

		usort( $ranges, function ( $a, $b ) {
			return $a[0] <=> $b[0];
		} );

		$merged = [ $ranges[0] ];

		foreach ( array_slice( $ranges, 1 ) as $range ) {
			$last = &$merged[ count( $merged ) - 1 ];
			if ( $range[0] <= $last[1] + 0.25 ) {
				$last[1] = max( $last[1], $range[1] );
			} else {
				$merged[] = $range;
			}
		}

		return $merged;
	}
}
