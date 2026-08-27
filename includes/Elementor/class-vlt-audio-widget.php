<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor Widget: VLT Audio Player
 *
 * Loaded conditionally after `elementor/loaded` — do not include directly.
 * Reuses VLT_Frontend::render_audio_player_shortcode() for output.
 */
class VLT_Audio_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'vlt-audio-player';
	}

	public function get_title() {
		return __( 'VLT Audio Player', 'video-lead-tracker' );
	}

	public function get_icon() {
		return 'eicon-headphones';
	}

	public function get_categories() {
		return [ 'vlt' ];
	}

	public function get_keywords() {
		return [ 'vlt', 'audio', 'player', 'waveform', 'mp3' ];
	}

	public function get_script_depends() {
		return [];
	}

	public function get_style_depends() {
		return [];
	}

	protected function register_controls() {

		$this->start_controls_section( 'section_audio', [
			'label' => __( 'Audio', 'video-lead-tracker' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'video_key', [
			'label'       => __( 'Video Key', 'video-lead-tracker' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => __( 'Leave blank to use post meta', 'video-lead-tracker' ),
			'description' => __( 'Resolves audio_url from the Videos registry. If blank, uses the video linked via the post meta box.', 'video-lead-tracker' ),
		] );

		$this->add_control( 'label', [
			'label'       => __( 'Label', 'video-lead-tracker' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => __( 'Audio version of this video', 'video-lead-tracker' ),
		] );

		$this->add_control( 'tag', [
			'label'       => __( 'Tag Badge', 'video-lead-tracker' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => __( 'Listen while you work', 'video-lead-tracker' ),
		] );

		$this->add_control( 'download', [
			'label'        => __( 'Show Download Button', 'video-lead-tracker' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'video-lead-tracker' ),
			'label_off'    => __( 'No', 'video-lead-tracker' ),
			'return_value' => 'yes',
			'default'      => 'yes',
		] );

		$this->end_controls_section();
	}

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$video_key = sanitize_key( $settings['video_key'] ?? '' );
		$is_editor = $this->is_editor_mode();

		if ( ! $video_key ) {
			$post_id   = VLT_Frontend::get_context_post_id();
			$video_key = $post_id
				? sanitize_key( (string) get_post_meta( $post_id, '_vlt_video_key', true ) )
				: '';
		}

		// Resolve audio for editor preview messaging.
		$audio_url = '';
		$video     = null;
		if ( $video_key ) {
			$video = VLT_DB::get_video_by_key( $video_key );
			if ( $video && ! empty( $video->audio_url ) ) {
				$audio_url = (string) $video->audio_url;
			}
		}

		if ( ! $audio_url ) {
			if ( $is_editor ) {
				$this->render_placeholder(
					$video_key
						? __( 'No audio URL found for this video key.', 'video-lead-tracker' )
						: __( 'Set a Video Key in the widget panel, or link a video with an audio URL via the post meta box.', 'video-lead-tracker' )
				);
			}
			return;
		}

		if ( $is_editor ) {
			$this->render_editor_preview( $video, $settings );
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo VLT_Frontend::render_audio_player_shortcode( [
			'key'      => $video_key,
			'label'    => $settings['label'] ?? '',
			'tag'      => $settings['tag'] ?? '',
			'download' => ( 'yes' === ( $settings['download'] ?? 'yes' ) ) ? '1' : '0',
		] );
	}

	protected function content_template() {
		$fallback_key = esc_js( __( 'from post meta', 'video-lead-tracker' ) );
		$widget_label = esc_html__( 'VLT Audio Player', 'video-lead-tracker' );
		?>
		<div style="padding:32px;text-align:center;background:#f0f0f1;border:2px dashed #c3c4c7;border-radius:4px;color:#50575e;">
			<span class="eicon-headphones" style="font-size:36px;display:block;margin-bottom:10px;color:#93003a;"></span>
			<strong><?php echo $widget_label; // phpcs:ignore WordPress.Security.EscapeOutput ?></strong>
			<p style="margin:8px 0 0;font-size:13px;">
				<# var k = settings.video_key || '<?php echo $fallback_key; ?>'; #>
				Key: {{ k }}
			</p>
		</div>
		<?php
	}

	private function is_editor_mode() {
		return isset( \Elementor\Plugin::$instance->editor )
			&& \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	private function render_placeholder( $message ) {
		?>
		<div style="padding:32px;text-align:center;background:#f0f0f1;border:2px dashed #c3c4c7;border-radius:4px;color:#50575e;">
			<span class="eicon-headphones" style="font-size:36px;display:block;margin-bottom:10px;color:#93003a;"></span>
			<strong><?php esc_html_e( 'VLT Audio Player', 'video-lead-tracker' ); ?></strong>
			<p style="margin:8px 0 0;font-size:13px;"><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	private function render_editor_preview( $video, $settings ) {
		$label = ! empty( $settings['label'] )
			? $settings['label']
			: __( 'Audio version of this video', 'video-lead-tracker' );
		$tag = ! empty( $settings['tag'] )
			? $settings['tag']
			: __( 'Listen while you work', 'video-lead-tracker' );
		$title = $video && ! empty( $video->title ) ? $video->title : ( $video->video_key ?? '' );
		?>
		<div style="padding:20px 24px;background:#1a1a1a;border-radius:8px;color:#fff;">
			<span class="eicon-headphones" style="font-size:28px;display:block;margin-bottom:8px;opacity:.85;"></span>
			<strong style="font-size:14px;"><?php echo esc_html( $label ); ?></strong>
			<?php if ( $tag ) : ?>
				<span style="display:inline-block;margin-inline-start:8px;font-size:11px;padding:2px 8px;border-radius:3px;background:rgba(255,255,255,.15);"><?php echo esc_html( $tag ); ?></span>
			<?php endif; ?>
			<p style="margin:8px 0 0;font-size:12px;opacity:.7;">
				<?php echo esc_html( $title ); ?>
				— <?php esc_html_e( 'VLT Audio Player — editor preview', 'video-lead-tracker' ); ?>
			</p>
		</div>
		<?php
	}
}
