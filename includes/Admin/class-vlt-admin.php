<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Admin {

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', [ self::class, 'register_menu' ] );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Video Lead Tracker', 'video-lead-tracker' ),
			__( 'Video Lead Tracker', 'video-lead-tracker' ),
			'manage_options',
			'vlt-overview',
			[ self::class, 'render_overview' ],
			'dashicons-video-alt3',
			30
		);

		$submenus = [
			[ 'vlt-overview',        __( 'Overview', 'video-lead-tracker' ),        [ self::class, 'render_overview' ] ],
			[ 'vlt-leads',           __( 'Leads', 'video-lead-tracker' ),           [ self::class, 'render_placeholder' ] ],
			[ 'vlt-video-analytics', __( 'Video Analytics', 'video-lead-tracker' ), [ self::class, 'render_placeholder' ] ],
			[ 'vlt-heatmap',         __( 'Heatmap', 'video-lead-tracker' ),         [ self::class, 'render_placeholder' ] ],
			[ 'vlt-exports',         __( 'Exports', 'video-lead-tracker' ),         [ self::class, 'render_placeholder' ] ],
			[ 'vlt-settings',        __( 'Settings', 'video-lead-tracker' ),        [ 'VLT_Settings', 'render_page' ] ],
			[ 'vlt-logs',            __( 'Logs', 'video-lead-tracker' ),            [ self::class, 'render_placeholder' ] ],
		];

		foreach ( $submenus as [ $slug, $label, $callback ] ) {
			add_submenu_page( 'vlt-overview', $label, $label, 'manage_options', $slug, $callback );
		}
	}

	public static function render_overview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Video Lead Tracker', 'video-lead-tracker' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s plugin version */
					esc_html__( 'Plugin active — version %s. Dashboard will be built in Phase 15.', 'video-lead-tracker' ),
					esc_html( VLT_VERSION )
				);
				?>
			</p>
		</div>
		<?php
	}

	public static function render_placeholder() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'This section is under development.', 'video-lead-tracker' ); ?></p>
		</div>
		<?php
	}
}
