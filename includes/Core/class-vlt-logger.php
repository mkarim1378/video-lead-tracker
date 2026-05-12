<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Logger {

	const DEBUG   = 'debug';
	const INFO    = 'info';
	const WARNING = 'warning';
	const ERROR   = 'error';

	public static function debug( $message, $context = null, $metadata = null ) {
		self::write( self::DEBUG, $message, $context, $metadata );
	}

	public static function info( $message, $context = null, $metadata = null ) {
		self::write( self::INFO, $message, $context, $metadata );
	}

	public static function warning( $message, $context = null, $metadata = null ) {
		self::write( self::WARNING, $message, $context, $metadata );
	}

	public static function error( $message, $context = null, $metadata = null ) {
		self::write( self::ERROR, $message, $context, $metadata );
	}

	private static function write( $level, $message, $context, $metadata ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'vlt_logs',
			[
				'level'      => $level,
				'context'    => $context ? sanitize_text_field( (string) $context ) : null,
				'message'    => sanitize_textarea_field( (string) $message ),
				'metadata'   => $metadata ? wp_json_encode( $metadata ) : null,
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%s' ]
		);
	}
}
