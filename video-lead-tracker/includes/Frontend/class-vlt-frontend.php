<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Frontend {

	public static function init() {
		add_shortcode( 'vlt_video_lead_gate', [ self::class, 'render_shortcode' ] );
	}

	public static function render_shortcode( $atts ) {
		// Full render implemented in Phase 4.
		return '<div class="vlt-wrapper"><!-- Video Lead Tracker shortcode registered --></div>';
	}
}
