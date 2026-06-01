<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Frontend {

	private static $enqueued = false;

	public static function init() {
		add_shortcode( 'video_lead_tracker',  [ self::class, 'render_shortcode' ] );
		add_shortcode( 'vlt_video_lead_gate', [ self::class, 'render_shortcode' ] ); // legacy alias
		add_shortcode( 'vlt_video',           [ self::class, 'render_vlt_video_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'register_assets' ] );
		add_action( 'wp_head', [ self::class, 'inject_single_cpt_json_ld' ] );
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
		wp_register_script(
			'vlt-video-tracker',
			VLT_PLUGIN_URL . 'assets/js/vlt-video-tracker.js',
			[ 'vlt-frontend' ],
			VLT_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// Shortcode
	// -------------------------------------------------------------------------

	public static function render_shortcode( $atts ) {
		// Normalize key/url aliases.
		if ( is_array( $atts ) ) {
			if ( isset( $atts['key'] ) && ! isset( $atts['video_key'] ) ) {
				$atts['video_key'] = $atts['key'];
			}
			if ( isset( $atts['url'] ) && ! isset( $atts['src'] ) ) {
				$atts['src'] = $atts['url'];
			}
		}

		$atts = shortcode_atts(
			[ 'video_key' => '', 'src' => '', 'poster' => '' ],
			$atts,
			'video_lead_tracker'
		);

		$video_key = sanitize_key( $atts['video_key'] );

		// Look up video in registry; fall back to first registered video.
		$video = $video_key ? VLT_DB::get_video_by_key( $video_key ) : null;
		if ( ! $video ) {
			$video = VLT_DB::get_first_video();
		}

		if ( ! $video ) {
			return '<p>' . esc_html__( 'No video configured. Please add a video in Video Lead Tracker → Videos.', 'video-lead-tracker' ) . '</p>';
		}

		// Shortcode src/poster attributes override the registry (backwards compat).
		$video_url  = $atts['src']    ? esc_url( $atts['src'] )    : esc_url( $video->video_url  ?? '' );
		$poster_url = $atts['poster'] ? esc_url( $atts['poster'] ) : esc_url( $video->poster_url ?? '' );

		self::enqueue_assets( $video->video_key, $video_url, (int) $video->duration_seconds, $video );

		return self::render_html( [
			'video_key'    => $video->video_key,
			'video_url'    => $video_url,
			'poster_url'   => $poster_url,
			'form_title'   => $video->form_title         ?: __( 'Watch the Free Training', 'video-lead-tracker' ),
			'name_label'   => $video->name_label         ?: __( 'Full Name', 'video-lead-tracker' ),
			'mobile_label' => $video->mobile_label       ?: __( 'Mobile Number', 'video-lead-tracker' ),
			'submit_text'  => $video->submit_button_text ?: __( 'Watch Now', 'video-lead-tracker' ),
		] );
	}

	// -------------------------------------------------------------------------
	// Asset enqueue + localize
	// -------------------------------------------------------------------------

	public static function enqueue_assets( $video_key, $video_url, $video_duration, $video = null ) {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;

		wp_enqueue_style( 'vlt-frontend' );
		wp_enqueue_script( 'vlt-frontend' );
		wp_enqueue_script( 'vlt-video-tracker' );

		$submit_text = ( $video && $video->submit_button_text )
			? $video->submit_button_text
			: __( 'Watch Now', 'video-lead-tracker' );

		$otp_enabled = $video ? (bool) $video->enable_otp : false;

		wp_localize_script(
			'vlt-frontend',
			'vltConfig',
			[
				'restBase'   => rest_url( 'vlt/v1/' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'videoKey'   => $video_key,
				'videoUrl'   => $video_url,
				'duration'   => $video_duration,
				'pageId'     => (int) get_the_ID(),
				'submitText' => $submit_text,
				'settings'   => [
					'minValidRange'     => (int) VLT_Settings::get( 'min_valid_range_seconds' ),
					'heartbeatInterval' => (int) VLT_Settings::get( 'heartbeat_interval' ),
					'trackAnonymous'    => (bool) VLT_Settings::get( 'track_anonymous' ),
				],
				'otp'        => [
					'enabled'  => $otp_enabled,
					'cooldown' => (int) VLT_Settings::get( 'otp_resend_cooldown' ),
				],
				'i18n'       => [
					'fillAllFields' => __( 'Please fill in all fields.', 'video-lead-tracker' ),
					'submitting'    => __( 'Please wait…', 'video-lead-tracker' ),
					'submitError'   => __( 'Submission failed. Please try again.', 'video-lead-tracker' ),
					'loadError'     => __( 'Could not connect. Please refresh the page.', 'video-lead-tracker' ),
					'otpSentTo'     => __( 'A verification code was sent to', 'video-lead-tracker' ),
					'enterCode'     => __( 'Please enter the verification code.', 'video-lead-tracker' ),
					'otpSendError'  => __( 'Failed to send verification code. Please try again.', 'video-lead-tracker' ),
					'otpInvalid'    => __( 'Invalid or expired code. Please try again.', 'video-lead-tracker' ),
					'resend'        => __( 'Resend Code', 'video-lead-tracker' ),
					'verify'        => __( 'Verify', 'video-lead-tracker' ),
				],
			]
		);
	}

	// -------------------------------------------------------------------------
	// HTML render
	// -------------------------------------------------------------------------

	public static function render_html( $args ) {
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
								maxlength="11"
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

			<?php /* OTP verification step — hidden until OTP is triggered */ ?>
			<div class="vlt-otp-container" style="display:none;" aria-hidden="true">
				<div class="vlt-form-inner">
					<h2 class="vlt-form-title"><?php esc_html_e( 'Enter Verification Code', 'video-lead-tracker' ); ?></h2>
					<p class="vlt-otp-hint"></p>

					<form class="vlt-otp-form" novalidate>
						<div class="vlt-field">
							<label for="vlt-otp-code"><?php esc_html_e( 'Verification Code', 'video-lead-tracker' ); ?></label>
							<input
								type="text"
								id="vlt-otp-code"
								name="otp_code"
								inputmode="numeric"
								autocomplete="one-time-code"
								maxlength="6"
								required
							/>
						</div>

						<button type="submit" class="vlt-submit-btn">
							<?php esc_html_e( 'Verify', 'video-lead-tracker' ); ?>
						</button>

						<div class="vlt-form-error" role="alert" style="display:none;"></div>
					</form>

					<div class="vlt-otp-resend">
						<button type="button" class="vlt-resend-btn" disabled>
							<?php esc_html_e( 'Resend Code', 'video-lead-tracker' ); ?>
						</button>
						<span class="vlt-resend-countdown"></span>
					</div>
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

	// -------------------------------------------------------------------------
	// [vlt_video] shortcode — CPT-aware embed
	// -------------------------------------------------------------------------

	public static function render_vlt_video_shortcode( $atts ) {
		$atts      = shortcode_atts( [ 'key' => '' ], $atts, 'vlt_video' );
		$video_key = sanitize_key( $atts['key'] );

		// Fall back to the video_key linked via post meta (set by meta box or CPT save).
		if ( ! $video_key ) {
			$video_key = sanitize_key( (string) get_post_meta( get_the_ID(), '_vlt_video_key', true ) );
		}

		if ( ! $video_key ) {
			return '';
		}

		$video = VLT_DB::get_video_by_key( $video_key );
		if ( ! $video ) {
			return '';
		}

		$video_url  = esc_url( $video->video_url  ?? '' );
		$poster_url = esc_url( $video->poster_url ?? '' );

		self::enqueue_assets( $video->video_key, $video_url, (int) $video->duration_seconds, $video );

		$output = self::render_html( [
			'video_key'    => $video->video_key,
			'video_url'    => $video_url,
			'poster_url'   => $poster_url,
			'form_title'   => $video->form_title         ?: __( 'Watch the Free Training', 'video-lead-tracker' ),
			'name_label'   => $video->name_label         ?: __( 'Full Name', 'video-lead-tracker' ),
			'mobile_label' => $video->mobile_label       ?: __( 'Mobile Number', 'video-lead-tracker' ),
			'submit_text'  => $video->submit_button_text ?: __( 'Watch Now', 'video-lead-tracker' ),
		] );

		// CPT single pages emit JSON-LD via wp_head; inject inline for all other contexts.
		if ( ! is_singular( VLT_CPT::POST_TYPE ) ) {
			$post_id = get_the_ID();
			$output .= self::build_json_ld_script(
				get_the_title( $post_id ),
				get_post_field( 'post_excerpt', $post_id ) ?: '',
				$video,
				$post_id
			);
		}

		return $output;
	}

	// -------------------------------------------------------------------------
	// JSON-LD — VideoObject structured data
	// -------------------------------------------------------------------------

	public static function inject_single_cpt_json_ld() {
		if ( ! is_singular( VLT_CPT::POST_TYPE ) ) {
			return;
		}
		$post_id   = get_the_ID();
		$video_key = get_post_meta( $post_id, '_vlt_video_key', true );
		if ( ! $video_key ) {
			return;
		}
		$video = VLT_DB::get_video_by_key( $video_key );
		if ( ! $video ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::build_json_ld_script(
			get_the_title( $post_id ),
			get_post_field( 'post_excerpt', $post_id ) ?: '',
			$video,
			$post_id
		);
	}

	private static function build_json_ld_script( $name, $description, $video, $post_id = null ) {
		$data = [
			'@context'   => 'https://schema.org',
			'@type'      => 'VideoObject',
			'name'       => $name ?: ( $video->title ?? '' ),
			'contentUrl' => $video->video_url ?? '',
		];

		$desc = wp_strip_all_tags( $description );
		if ( $desc ) {
			$data['description'] = $desc;
		}
		if ( ! empty( $video->poster_url ) ) {
			$data['thumbnailUrl'] = $video->poster_url;
		}
		if ( $post_id ) {
			$data['uploadDate'] = get_the_date( 'c', $post_id );
		}

		return '<script type="application/ld+json">'
			. wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. "</script>\n";
	}
}
