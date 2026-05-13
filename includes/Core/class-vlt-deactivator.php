<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Deactivator {

	public static function deactivate() {
		wp_clear_scheduled_hook( 'vlt_daily_cleanup' );
		flush_rewrite_rules();
	}
}
