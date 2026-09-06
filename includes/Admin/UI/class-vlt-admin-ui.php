<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SaaS admin shell helpers (Phase 0 foundation).
 */
class VLT_Admin_UI {

	/** @var bool */
	private static $shell_open = false;

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_filter( 'admin_body_class', [ self::class, 'body_class' ] );
	}

	/**
	 * Add body class on all VLT admin screens.
	 *
	 * @param string $classes
	 * @return string
	 */
	public static function body_class( $classes ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $page && strpos( $page, 'vlt-' ) === 0 ) {
			$classes .= ' vlt-admin-screen';
		}
		return $classes;
	}

	/**
	 * Nav items for the in-app shell.
	 *
	 * @return array<int, array{slug:string,label:string,icon:string}>
	 */
	public static function nav_items() {
		return [
			[ 'slug' => 'vlt-overview',        'label' => __( 'Overview',        'video-lead-tracker' ), 'icon' => 'dashicons-dashboard' ],
			[ 'slug' => 'vlt-videos',          'label' => __( 'Videos',          'video-lead-tracker' ), 'icon' => 'dashicons-video-alt3' ],
			[ 'slug' => 'vlt-leads',           'label' => __( 'Leads',           'video-lead-tracker' ), 'icon' => 'dashicons-groups' ],
			[ 'slug' => 'vlt-video-analytics', 'label' => __( 'Analytics',       'video-lead-tracker' ), 'icon' => 'dashicons-chart-area' ],
			[ 'slug' => 'vlt-funnel',          'label' => __( 'Funnel',          'video-lead-tracker' ), 'icon' => 'dashicons-filter' ],
			[ 'slug' => 'vlt-heatmap',         'label' => __( 'Heatmap',         'video-lead-tracker' ), 'icon' => 'dashicons-chart-line' ],
			[ 'slug' => 'vlt-settings',        'label' => __( 'Settings',        'video-lead-tracker' ), 'icon' => 'dashicons-admin-generic' ],
			[ 'slug' => 'vlt-logs',            'label' => __( 'Logs',            'video-lead-tracker' ), 'icon' => 'dashicons-media-text' ],
		];
	}

	/**
	 * Open the SaaS shell.
	 *
	 * @param array $args {
	 *   @type string $page              Current page slug.
	 *   @type string $title             Page title (optional when legacy).
	 *   @type string $subtitle          One-line description.
	 *   @type bool   $show_header       Show page header block. Default true.
	 *   @type bool   $show_video_filter Show shared video filter in header.
	 *   @type bool   $ajax_video_filter Filter dispatches JS event instead of form submit.
	 *   @type string $actions_html      Extra header actions markup.
	 *   @type bool   $legacy            Content still has its own .wrap / h1.
	 * }
	 */
	public static function open( array $args = [] ) {
		$defaults = [
			'page'              => isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'vlt-overview', // phpcs:ignore WordPress.Security.NonceVerification
			'title'             => '',
			'subtitle'          => '',
			'show_header'       => true,
			'show_video_filter' => false,
			'ajax_video_filter' => false,
			'actions_html'      => '',
			'legacy'            => false,
		];
		$args = array_merge( $defaults, $args );

		if ( $args['legacy'] ) {
			$args['show_header'] = false;
		}

		self::$shell_open = true;
		self::render( 'shell-open', $args );
	}

	/**
	 * Close the SaaS shell (toast + modal hosts included).
	 */
	public static function close() {
		if ( ! self::$shell_open ) {
			return;
		}
		self::render( 'shell-close' );
		self::$shell_open = false;
	}

	/**
	 * Render a view file from includes/Admin/Views/.
	 *
	 * @param string $name Relative path without .php (e.g. 'partials/video-filter').
	 * @param array  $args Extracted into the view scope.
	 */
	public static function render( $name, array $args = [] ) {
		$path = VLT_PLUGIN_DIR . 'includes/Admin/Views/' . $name . '.php';
		if ( ! file_exists( $path ) ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional view locals.
		extract( $args, EXTR_SKIP );
		include $path;
	}

	/**
	 * Shared video filter control.
	 *
	 * @param string $page       Admin page slug.
	 * @param array  $keep_get   Extra GET params to preserve (non-AJAX fallback).
	 * @param bool   $ajax       When true, change fires vlt:video-filter (no submit).
	 * @return object|null Selected video row or null.
	 */
	public static function render_video_filter( $page, array $keep_get = [], $ajax = false ) {
		$videos      = VLT_DB::get_all_videos();
		$current_key = sanitize_key( $_GET['video'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$selected    = null;

		if ( $current_key ) {
			foreach ( $videos as $v ) {
				if ( $v->video_key === $current_key ) {
					$selected = $v;
					break;
				}
			}
			if ( ! $selected ) {
				$current_key = '';
			}
		}

		self::render( 'partials/video-filter', [
			'page'        => $page,
			'videos'      => $videos,
			'current_key' => $current_key,
			'keep_get'    => $keep_get,
			'ajax'        => (bool) $ajax,
		] );

		return $selected;
	}

	/**
	 * Wrap a submenu callback so legacy pages sit inside the shell.
	 * Pages that open their own shell are skipped.
	 *
	 * @param string   $slug
	 * @param callable $callback
	 * @return callable
	 */
	public static function wrap_page( $slug, $callback ) {
		$self_shell = [ 'vlt-overview', 'vlt-video-analytics', 'vlt-funnel', 'vlt-heatmap' ];
		return static function () use ( $slug, $callback, $self_shell ) {
			if ( in_array( $slug, $self_shell, true ) ) {
				call_user_func( $callback );
				return;
			}
			self::open( [
				'page'   => $slug,
				'legacy' => true,
			] );
			call_user_func( $callback );
			self::close();
		};
	}

	/**
	 * Video picker by numeric ID (Funnel / Heatmap).
	 *
	 * @param string $page
	 * @param int    $current_id
	 * @param bool   $ajax
	 * @param array  $videos Optional preloaded rows (id, title, video_key, duration_seconds).
	 */
	public static function render_video_id_filter( $page, $current_id = 0, $ajax = true, array $videos = [] ) {
		if ( empty( $videos ) ) {
			global $wpdb;
			$videos = $wpdb->get_results(
				"SELECT id, title, video_key, duration_seconds FROM {$wpdb->prefix}vlt_videos WHERE is_active = 1 ORDER BY id ASC"
			);
		}
		self::render( 'partials/video-id-filter', [
			'page'       => $page,
			'videos'     => $videos,
			'current_id' => (int) $current_id,
			'ajax'       => (bool) $ajax,
		] );
	}
}
