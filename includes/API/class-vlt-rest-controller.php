<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_REST_Controller {

	const NAMESPACE = 'vlt/v1';

	public static function init() {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes() {
		// Routes registered in Phase 6.
	}

	protected static function success( array $data = [] ) {
		return new WP_REST_Response( array_merge( [ 'success' => true ], $data ), 200 );
	}

	protected static function error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, [ 'status' => $status ] );
	}
}
