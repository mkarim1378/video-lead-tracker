<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Settings {

	private static $defaults = [
		'enable_tracking'         => true,
		'tracking_page_id'        => 0,
		'video_key'               => 'main-training-video',
		'video_title'             => '',
		'video_url'               => '',
		'video_duration'          => 0,
		'delete_on_uninstall'     => false,
		'form_title'              => 'Watch the Free Training',
		'name_label'              => 'Full Name',
		'mobile_label'            => 'Mobile Number',
		'submit_button_text'      => 'Watch Now',
		'success_message'         => 'Welcome! Your video is ready.',
		'min_valid_range_seconds' => 1,
		'heartbeat_interval'      => 10,
		'heatmap_default_bucket'  => 5,
		'track_anonymous'         => true,
		'ip_storage_mode'         => 'hash', // disabled | hash | raw
		'store_user_agent'        => false,
		'enable_otp'              => false,
		'enable_xlsx'             => true,
		'enable_csv_fallback'     => true,
	];

	private static $cache = null;

	public static function init() {
		// Settings UI registered in Phase 3.
	}

	public static function get( $key, $default = null ) {
		if ( null === self::$cache ) {
			self::$cache = (array) get_option( 'vlt_settings', [] );
		}

		if ( array_key_exists( $key, self::$cache ) ) {
			return self::$cache[ $key ];
		}

		if ( null !== $default ) {
			return $default;
		}

		return self::$defaults[ $key ] ?? null;
	}

	public static function all() {
		if ( null === self::$cache ) {
			self::$cache = (array) get_option( 'vlt_settings', [] );
		}
		return array_merge( self::$defaults, self::$cache );
	}

	public static function update( array $values ) {
		$merged       = array_merge( self::all(), $values );
		self::$cache  = $merged;
		update_option( 'vlt_settings', $merged );
	}
}
