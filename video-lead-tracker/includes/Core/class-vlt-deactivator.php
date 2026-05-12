<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Deactivator {

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
