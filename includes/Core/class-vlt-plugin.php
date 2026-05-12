<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Plugin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function init() {
		VLT_Settings::init();
		VLT_Admin::init();
		VLT_REST_Controller::init();
		VLT_Frontend::init();
	}
}
