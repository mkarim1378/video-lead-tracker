<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor Widget: VLT Video Gate
 *
 * Loaded conditionally after `elementor/loaded` — do not include directly.
 * Compatible with Elementor 3.5+ and Elementor 4.x.
 *
 * Asset strategy: enqueueing is delegated to VLT_Frontend::enqueue_assets()
 * inside render() so that wp_localize_script() runs with the correct vltConfig
 * per post. get_script_depends() / get_style_depends() intentionally return []
 * to avoid Elementor enqueueing scripts without the required localization data.
 */
class VLT_Widget extends \Elementor\Widget_Base {

	// -------------------------------------------------------------------------
	// Widget identity
	// -------------------------------------------------------------------------

	public function get_name() {
		return 'vlt-video-gate';
	}

	public function get_title() {
		return __( 'VLT Video Gate', 'video-lead-tracker' );
	}

	public function get_icon() {
		return 'eicon-play';
	}

	public function get_categories() {
		return [ 'vlt' ];
	}

	public function get_keywords() {
		return [ 'vlt', 'video', 'lead', 'gate', 'form', 'tracker' ];
	}

	// Enqueueing is handled inside render(); returning [] prevents Elementor
	// from loading scripts without the required vltConfig localization.
	public function get_script_depends() {
		return [];
	}

	public function get_style_depends() {
		return [];
	}

	// -------------------------------------------------------------------------
	// Controls
	// -------------------------------------------------------------------------

	protected function register_controls() {

		// ---- Section: Video -------------------------------------------------
		$this->start_controls_section( 'section_video', [
			'label' => __( 'Video', 'video-lead-tracker' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'video_key', [
			'label'       => __( 'Video Key', 'video-lead-tracker' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => __( 'Leave blank to use post meta', 'video-lead-tracker' ),
			'description' => __( 'Slug from the Videos list. If blank, the video linked via the post meta box is used automatically.', 'video-lead-tracker' ),
		] );

		$this->add_control( 'show_poster', [
			'label'        => __( 'Show Poster Image', 'video-lead-tracker' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'video-lead-tracker' ),
			'label_off'    => __( 'No', 'video-lead-tracker' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->end_controls_section();

		// ---- Section: Form Overrides ----------------------------------------
		$this->start_controls_section( 'section_form', [
			'label' => __( 'Form Overrides', 'video-lead-tracker' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'form_title', [
			'label'       => __( 'Form Title', 'video-lead-tracker' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => __( '(use video default)', 'video-lead-tracker' ),
		] );

		$this->add_control( 'button_label', [
			'label'       => __( 'Button Text', 'video-lead-tracker' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => __( '(use video default)', 'video-lead-tracker' ),
		] );

		$this->end_controls_section();
	}

	// -------------------------------------------------------------------------
	// Render (PHP — server-side, used on frontend and for initial editor paint)
	// -------------------------------------------------------------------------

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$video_key = sanitize_key( $settings['video_key'] ?? '' );

		// Resolve from post meta when no explicit key is set (same as [vlt_video]).
		if ( ! $video_key ) {
			$post_id   = VLT_Frontend::get_context_post_id();
			$video_key = $post_id
				? sanitize_key( (string) get_post_meta( $post_id, '_vlt_video_key', true ) )
				: '';
		}

		$is_editor = $this->is_editor_mode();

		if ( ! $video_key ) {
			if ( $is_editor ) {
				$this->render_placeholder(
					__( 'Set a Video Key in the widget panel, or link a video via the post meta box.', 'video-lead-tracker' )
				);
			}
			return;
		}

		$video = VLT_DB::get_video_by_key( $video_key );

		if ( ! $video ) {
			if ( $is_editor ) {
				$this->render_placeholder(
					/* translators: %s: video key slug */
					sprintf( __( 'No video found for key: %s', 'video-lead-tracker' ), esc_html( $video_key ) )
				);
			}
			return;
		}

		$video_url  = esc_url( $video->video_url  ?? '' );
		$poster_url = 'yes' === ( $settings['show_poster'] ?? 'yes' )
			? esc_url( $video->poster_url ?? '' )
			: '';

		// Editor: show a static poster-based preview — do not run the JS gate.
		if ( $is_editor ) {
			$this->render_editor_preview( $video, $poster_url );
			return;
		}

		// Frontend: enqueue + localize assets, then render the gated player.
		VLT_Frontend::enqueue_assets( $video->video_key, $video_url, (int) $video->duration_seconds, $video );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo VLT_Frontend::render_html( [
			'video_key'    => $video->video_key,
			'video_url'    => $video_url,
			'poster_url'   => $poster_url,
			'form_title'   => ( $settings['form_title'] ?? '' )
				?: ( $video->form_title ?: __( 'Watch the Free Training', 'video-lead-tracker' ) ),
			'name_label'   => $video->name_label   ?: __( 'Full Name',     'video-lead-tracker' ),
			'mobile_label' => $video->mobile_label ?: __( 'Mobile Number', 'video-lead-tracker' ),
			'submit_text'  => ( $settings['button_label'] ?? '' )
				?: ( $video->submit_button_text ?: __( 'Watch Now', 'video-lead-tracker' ) ),
		] );
	}

	// -------------------------------------------------------------------------
	// JS template — live preview inside Elementor builder (Underscore.js / lodash)
	// -------------------------------------------------------------------------

	protected function content_template() {
		$fallback_key = esc_js( __( 'from post meta', 'video-lead-tracker' ) );
		$widget_label = esc_html__( 'VLT Video Gate', 'video-lead-tracker' );
		?>
		<div style="padding:40px;text-align:center;background:#f0f0f1;border:2px dashed #c3c4c7;border-radius:4px;color:#50575e;">
			<span class="eicon-play" style="font-size:40px;display:block;margin-bottom:12px;color:#93003a;"></span>
			<strong><?php echo $widget_label; // phpcs:ignore WordPress.Security.EscapeOutput ?></strong>
			<p style="margin:8px 0 0;font-size:13px;">
				<# var k = settings.video_key || '<?php echo $fallback_key; ?>'; #>
				Key: {{ k }}
			</p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function is_editor_mode() {
		return isset( \Elementor\Plugin::$instance->editor )
			&& \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	private function render_placeholder( $message ) {
		?>
		<div style="padding:40px;text-align:center;background:#f0f0f1;border:2px dashed #c3c4c7;border-radius:4px;color:#50575e;">
			<span class="eicon-play" style="font-size:40px;display:block;margin-bottom:12px;color:#93003a;"></span>
			<strong><?php esc_html_e( 'VLT Video Gate', 'video-lead-tracker' ); ?></strong>
			<p style="margin:8px 0 0;font-size:13px;"><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	private function render_editor_preview( $video, $poster_url ) {
		?>
		<div style="position:relative;background:#000;overflow:hidden;min-height:200px;display:flex;align-items:center;justify-content:center;">
			<?php if ( $poster_url ) : ?>
				<img src="<?php echo esc_url( $poster_url ); ?>"
				     alt="<?php echo esc_attr( $video->title ?: $video->video_key ); ?>"
				     style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.55;">
			<?php endif; ?>
			<div style="position:relative;z-index:1;text-align:center;color:#fff;padding:20px;">
				<span class="eicon-play" style="font-size:52px;display:block;margin-bottom:10px;"></span>
				<strong style="font-size:16px;"><?php echo esc_html( $video->title ?: $video->video_key ); ?></strong>
				<p style="margin:6px 0 0;font-size:12px;opacity:.8;"><?php esc_html_e( 'VLT Video Gate — editor preview', 'video-lead-tracker' ); ?></p>
			</div>
		</div>
		<?php
	}
}
