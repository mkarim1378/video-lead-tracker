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

	/**
	 * Recompute and upsert the vlt_video_user_summary row for a viewer.
	 *
	 * @param int         $video_id
	 * @param int|null    $lead_id
	 * @param string|null $visitor_uuid
	 */
	public static function aggregate( $video_id, $lead_id, $visitor_uuid ) {
		$raw_rows = VLT_DB::get_video_ranges_for( $video_id, $lead_id, $visitor_uuid );

		if ( empty( $raw_rows ) ) {
			return;
		}

		$pairs          = [];
		$total_watch    = 0.0;
		$max_video_time = 0.0;

		foreach ( $raw_rows as $row ) {
			$from = (float) $row->from_second;
			$to   = (float) $row->to_second;

			$pairs[]        = [ $from, $to ];
			$total_watch   += (float) $row->duration_seconds;
			$max_video_time = max( $max_video_time, $to );
		}

		$merged       = self::merge_ranges( $pairs );
		$unique_watch = array_reduce( $merged, function ( $carry, $pair ) {
			return $carry + ( $pair[1] - $pair[0] );
		}, 0.0 );

		$video    = VLT_DB::get_video_by_id( $video_id );
		$duration = $video ? (float) $video->duration_seconds : 0.0;

		$unique_percent = 0.0;
		if ( $duration > 0 ) {
			$unique_percent = round( min( 100.0, ( $unique_watch / $duration ) * 100.0 ), 2 );
		}

		// Reached end: explicit 'ended' event OR max position within 2 s of video duration.
		$reached_end = VLT_DB::has_video_event_type( $video_id, $lead_id, $visitor_uuid, 'ended' );
		if ( ! $reached_end && $duration > 0 && $max_video_time >= ( $duration - 2.0 ) ) {
			$reached_end = true;
		}

		$sessions_count = VLT_DB::count_video_sessions( $video_id, $lead_id, $visitor_uuid );
		$first_play_at  = VLT_DB::get_first_video_event_at( $video_id, $lead_id, $visitor_uuid, 'play' );
		$now            = current_time( 'mysql', true );

		VLT_DB::upsert_video_user_summary( $video_id, $lead_id, $visitor_uuid, [
			'sessions_count'         => $sessions_count,
			'started'                => 1,
			'reached_end'            => $reached_end ? 1 : 0,
			'first_play_at'          => $first_play_at,
			'last_activity_at'       => $now,
			'total_watch_seconds'    => round( $total_watch,    3 ),
			'unique_watch_seconds'   => round( $unique_watch,   3 ),
			'max_video_time_seconds' => round( $max_video_time, 3 ),
			'unique_watch_percent'   => $unique_percent,
			'raw_ranges_json'        => wp_json_encode( $pairs ),
			'merged_ranges_json'     => wp_json_encode( $merged ),
			'updated_at'             => $now,
		] );
	}

	/**
	 * Update per-second heatmap counts for a single newly committed watch range.
	 *
	 * Increments total_views_count for every integer second covered by [from, to].
	 * On the first occurrence of each second for this visitor, also increments
	 * unique_visitors_count (and unique_leads_count when a lead is identified).
	 *
	 * @param int         $video_id
	 * @param int|null    $lead_id
	 * @param string      $visitor_uuid
	 * @param float       $from_second
	 * @param float       $to_second
	 */
	public static function update_heatmap( $video_id, $lead_id, $visitor_uuid, $from_second, $to_second ) {
		// second_index N covers the interval [N, N+1).
		// A range [from, to] covers seconds floor(from) … ceil(to)-1.
		$start_sec = (int) floor( $from_second );
		$end_sec   = (int) ceil( $to_second ) - 1;

		if ( $start_sec > $end_sec ) {
			return;
		}

		$now      = current_time( 'mysql', true );
		$has_lead = (bool) $lead_id;

		for ( $sec = $start_sec; $sec <= $end_sec; $sec++ ) {
			VLT_DB::heatmap_increment_total( $video_id, $sec, $now );

			if ( VLT_DB::heatmap_try_unique( $video_id, $sec, $visitor_uuid, $lead_id, $now ) ) {
				VLT_DB::heatmap_increment_unique( $video_id, $sec, $has_lead, $now );
			}
		}
	}
}
