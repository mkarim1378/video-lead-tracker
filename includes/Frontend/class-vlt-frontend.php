<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Frontend {

	private static $enqueued = false;

	public static function init() {
		add_shortcode( 'vlt_video_lead_gate', [ self::class, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'register_assets' ] );
	}

	// Register (not enqueue) so scripts are available when shortcode calls enqueue.
	public static function register_assets() {
		wp_register_style(
			'vlt-frontend',
			VLT_PLUGIN_URL . 'assets/css/vlt-frontend.css',
			[],
			VLT_VERSION
		);
		wp_register_script(
			'vlt-frontend',
			VLT_PLUGIN_URL . 'assets/js/vlt-frontend.js',
			[],
			VLT_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Shortcode
	// -------------------------------------------------------------------------

	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'video_key' => VLT_Settings::get( 'video_key' ),
				'src'       => VLT_Settings::get( 'video_url' ),
				'poster'    => '',
				'title'     => VLT_Settings::get( 'video_title' ),
			],
			$atts,
			'vlt_video_lead_gate'
		);

		$video_key      = sanitize_key( $atts['video_key'] );
		$video_url      = esc_url( $atts['src'] );
		$poster_url     = esc_url( $atts['poster'] );
		$video_duration = (int) VLT_Settings::get( 'video_duration' );

		self::enqueue_assets( $video_key, $video_url, $video_duration );

		return self::render_html( [
			'video_key'      => $video_key,
			'video_url'      => $video_url,
			'poster_url'     => $poster_url,
			'form_title'     => VLT_Settings::get( 'form_title' ),
			'name_label'     => VLT_Settings::get( 'name_label' ),
			'mobile_label'   => VLT_Settings::get( 'mobile_label' ),
			'submit_text'    => VLT_Settings::get( 'submit_button_text' ),
		] );
	}

	// -------------------------------------------------------------------------
	// Asset enqueue + localize
	// -------------------------------------------------------------------------

	private static function enqueue_assets( $video_key, $video_url, $video_duration ) {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;

		wp_enqueue_style( 'vlt-frontend' );
		wp_enqueue_script( 'vlt-frontend' );

		wp_localize_script(
			'vlt-frontend',
			'vltConfig',
			[
				'restBase'   => rest_url( 'vlt/v1/' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'videoKey'   => $video_key,
				'videoUrl'   => $video_url,
				'duration'   => $video_duration,
				'submitText' => VLT_Settings::get( 'submit_button_text' ),
				'settings'   => [
					'minValidRange'     => (int) VLT_Settings::get( 'min_valid_range_seconds' ),
					'heartbeatInterval' => (int) VLT_Settings::get( 'heartbeat_interval' ),
					'trackAnonymous'    => (bool) VLT_Settings::get( 'track_anonymous' ),
				],
				'i18n'       => [
					'fillAllFields' => __( 'Please fill in all fields.', 'video-lead-tracker' ),
					'submitting'    => __( 'Please wait…', 'video-lead-tracker' ),
					'submitError'   => __( 'Submission failed. Please try again.', 'video-lead-tracker' ),
					'loadError'     => __( 'Could not connect. Please refresh the page.', 'video-lead-tracker' ),
				],
			]
		);
	}

	// -------------------------------------------------------------------------
	// HTML render
	// -------------------------------------------------------------------------

	private static function render_html( $args ) {
		ob_start();
		?>
		<div class="vlt-wrapper" data-video-key="<?php echo esc_attr( $args['video_key'] ); ?>">

			<?php /* Loading spinner — visible on first load */ ?>
			<div class="vlt-loading" aria-live="polite">
				<div class="vlt-spinner" aria-hidden="true"></div>
			</div>

			<?php /* Lead form — hidden until JS decides to show it */ ?>
			<div class="vlt-form-container" style="display:none;" aria-hidden="true">
				<div class="vlt-form-inner">
					<?php if ( $args['form_title'] ) : ?>
						<h2 class="vlt-form-title"><?php echo esc_html( $args['form_title'] ); ?></h2>
					<?php endif; ?>

					<form class="vlt-lead-form" novalidate>
						<div class="vlt-field">
							<label for="vlt-name"><?php echo esc_html( $args['name_label'] ); ?></label>
							<input
								type="text"
								id="vlt-name"
								name="name"
								autocomplete="name"
								required
							/>
						</div>

						<div class="vlt-field">
							<label for="vlt-mobile"><?php echo esc_html( $args['mobile_label'] ); ?></label>
							<input
								type="tel"
								id="vlt-mobile"
								name="mobile"
								autocomplete="tel"
								inputmode="numeric"
								required
							/>
						</div>

						<button type="submit" class="vlt-submit-btn">
							<?php echo esc_html( $args['submit_text'] ); ?>
						</button>

						<div class="vlt-form-error" role="alert" style="display:none;"></div>
					</form>
				</div>
			</div>

			<?php /* Video player — hidden until lead is known */ ?>
			<div class="vlt-video-container" style="display:none;" aria-hidden="true">
				<video
					class="vlt-video"
					controls
					preload="metadata"
					playsinline
					<?php if ( $args['poster_url'] ) : ?>
						poster="<?php echo esc_url( $args['poster_url'] ); ?>"
					<?php endif; ?>
				>
					<?php if ( $args['video_url'] ) : ?>
						<source src="<?php echo esc_url( $args['video_url'] ); ?>" type="video/mp4" />
					<?php endif; ?>
					<?php esc_html_e( 'Your browser does not support the video tag.', 'video-lead-tracker' ); ?>
				</video>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}
}
