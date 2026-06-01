<?php
/**
 * Plugin Name: Video Lead Tracker
 * Description: Gates an HTML5 video behind a lead form, tracks watch ranges, and provides analytics inside WordPress admin.
 * Version:     1.2.5
 * Author:      Mohamad Karim
 * Author-URI:  https://m-karim.ir
 * Text Domain: video-lead-tracker
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VLT_VERSION',     '1.2.5' );
define( 'VLT_PLUGIN_FILE', __FILE__ );
define( 'VLT_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'VLT_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'VLT_DB_VERSION',  '1.3' );

spl_autoload_register( function ( $class ) {
	$map = [
		'VLT_Plugin'          => 'Core/class-vlt-plugin.php',
		'VLT_Activator'       => 'Core/class-vlt-activator.php',
		'VLT_Deactivator'     => 'Core/class-vlt-deactivator.php',
		'VLT_Logger'          => 'Core/class-vlt-logger.php',
		'VLT_DB'              => 'Database/class-vlt-db.php',
		'VLT_Settings'        => 'Settings/class-vlt-settings.php',
		'VLT_Admin'           => 'Admin/class-vlt-admin.php',
		'VLT_Videos_Admin'    => 'Admin/class-vlt-videos-admin.php',
		'VLT_REST_Controller' => 'API/class-vlt-rest-controller.php',
		'VLT_Frontend'        => 'Frontend/class-vlt-frontend.php',
		'VLT_Tracker'         => 'Services/class-vlt-tracker.php',
		'VLT_Aggregator'      => 'Services/class-vlt-aggregator.php',
		'VLT_Exporter'        => 'Export/class-vlt-exporter.php',
		'VLT_OTP_Service'     => 'Services/class-vlt-otp-service.php',
		'VLT_CPT'             => 'CPT/class-vlt-cpt.php',
	];

	if ( isset( $map[ $class ] ) ) {
		require_once VLT_PLUGIN_DIR . 'includes/' . $map[ $class ];
	}
} );

register_activation_hook( __FILE__, [ 'VLT_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'VLT_Deactivator', 'deactivate' ] );

add_action( 'plugins_loaded', [ 'VLT_Plugin', 'get_instance' ] );
