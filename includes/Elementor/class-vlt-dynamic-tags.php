<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots Dynamic Tag registration.
 * Loaded conditionally after `elementor/loaded` fires — do not include directly.
 * Tag classes below extend Elementor base classes; they are safe here
 * because this file is required only after Elementor's own classes are defined.
 */
class VLT_Dynamic_Tags {

	const GROUP_NAME = 'vlt-video';

	public static function init() {
		add_action( 'elementor/dynamic_tags/register', [ self::class, 'register_tags' ] );
	}

	public static function register_tags( $manager ) {
		$manager->register_group( self::GROUP_NAME, [
			'title' => __( 'VLT Video', 'video-lead-tracker' ),
		] );

		if ( method_exists( $manager, 'register' ) ) {
			$manager->register( new VLT_Tag_VideoUrl() );
			$manager->register( new VLT_Tag_PosterUrl() );
			$manager->register( new VLT_Tag_PosterImage() );
			$manager->register( new VLT_Tag_AudioUrl() );
			$manager->register( new VLT_Tag_VideoTitle() );
			$manager->register( new VLT_Tag_Excerpt() );
			$manager->register( new VLT_Tag_Duration() );
			$manager->register( new VLT_Tag_VideoKey() );
		}
	}

	/** @return int Post ID for front-end / Theme Builder context. */
	public static function get_context_post_id() {
		return VLT_Frontend::get_context_post_id();
	}

	/** Shared helper: resolve the vlt_videos row linked to the current post. */
	public static function get_post_video() {
		$post_id = self::get_context_post_id();
		if ( ! $post_id ) {
			return null;
		}
		$video_key = (string) get_post_meta( $post_id, '_vlt_video_key', true );
		return $video_key ? VLT_DB::get_video_by_key( $video_key ) : null;
	}

	/**
	 * Format duration_seconds as M:SS or H:MM:SS.
	 *
	 * @param int $seconds
	 * @return string
	 */
	public static function format_duration( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		if ( $seconds <= 0 ) {
			return '';
		}
		$h = (int) floor( $seconds / 3600 );
		$m = (int) floor( ( $seconds % 3600 ) / 60 );
		$s = $seconds % 60;
		if ( $h > 0 ) {
			return sprintf( '%d:%02d:%02d', $h, $m, $s );
		}
		return sprintf( '%d:%02d', $m, $s );
	}
}

// -------------------------------------------------------------------------
// URL Tags (Data_Tag — returns raw value, no HTML wrapper)
// -------------------------------------------------------------------------

class VLT_Tag_VideoUrl extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name() {
		return 'vlt-video-url';
	}

	public function get_title() {
		return __( 'VLT Video URL', 'video-lead-tracker' );
	}

	public function get_group() {
		return VLT_Dynamic_Tags::GROUP_NAME;
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ];
	}

	public function get_value( array $options = [] ) {
		$video = VLT_Dynamic_Tags::get_post_video();
		return $video ? esc_url( $video->video_url ?? '' ) : '';
	}
}

class VLT_Tag_PosterUrl extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name() {
		return 'vlt-poster-url';
	}

	public function get_title() {
		return __( 'VLT Poster URL', 'video-lead-tracker' );
	}

	public function get_group() {
		return VLT_Dynamic_Tags::GROUP_NAME;
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ];
	}

	public function get_value( array $options = [] ) {
		$video = VLT_Dynamic_Tags::get_post_video();
		return $video ? esc_url( $video->poster_url ?? '' ) : '';
	}
}

class VLT_Tag_AudioUrl extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name() {
		return 'vlt-audio-url';
	}

	public function get_title() {
		return __( 'VLT Audio URL', 'video-lead-tracker' );
	}

	public function get_group() {
		return VLT_Dynamic_Tags::GROUP_NAME;
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ];
	}

	public function get_value( array $options = [] ) {
		$video = VLT_Dynamic_Tags::get_post_video();
		return $video ? esc_url( $video->audio_url ?? '' ) : '';
	}
}

/**
 * IMAGE category tag for Image widget / background controls.
 * Returns [ id => attachment_id|0, url => poster_url ].
 */
class VLT_Tag_PosterImage extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name() {
		return 'vlt-poster-image';
	}

	public function get_title() {
		return __( 'VLT Poster Image', 'video-lead-tracker' );
	}

	public function get_group() {
		return VLT_Dynamic_Tags::GROUP_NAME;
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY ];
	}

	public function get_value( array $options = [] ) {
		$video = VLT_Dynamic_Tags::get_post_video();
		$url   = $video ? (string) ( $video->poster_url ?? '' ) : '';
		if ( ! $url ) {
			return [
				'id'  => 0,
				'url' => '',
			];
		}

		$attachment_id = 0;
		if ( function_exists( 'attachment_url_to_postid' ) ) {
			$attachment_id = (int) attachment_url_to_postid( $url );
		}

		return [
			'id'  => $attachment_id,
			'url' => esc_url( $url ),
		];
	}
}

// -------------------------------------------------------------------------
// Text Tags (Tag — renders HTML output directly)
// -------------------------------------------------------------------------

class VLT_Tag_VideoTitle extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'vlt-video-title';
	}

	public function get_title() {
		return __( 'VLT Video Title', 'video-lead-tracker' );
	}

	public function get_group() {
		return VLT_Dynamic_Tags::GROUP_NAME;
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	protected function render() {
		$video = VLT_Dynamic_Tags::get_post_video();
		if ( $video && $video->title ) {
			echo esc_html( $video->title );
		}
	}
}

class VLT_Tag_Excerpt extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'vlt-excerpt';
	}

	public function get_title() {
		return __( 'VLT Post Excerpt', 'video-lead-tracker' );
	}

	public function get_group() {
		return VLT_Dynamic_Tags::GROUP_NAME;
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	protected function render() {
		$post_id = VLT_Dynamic_Tags::get_context_post_id();
		if ( ! $post_id ) {
			return;
		}
		$excerpt = get_the_excerpt( $post_id );
		if ( $excerpt ) {
			echo esc_html( $excerpt );
		}
	}
}

class VLT_Tag_Duration extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'vlt-duration';
	}

	public function get_title() {
		return __( 'VLT Duration', 'video-lead-tracker' );
	}

	public function get_group() {
		return VLT_Dynamic_Tags::GROUP_NAME;
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	protected function render() {
		$video = VLT_Dynamic_Tags::get_post_video();
		if ( ! $video ) {
			return;
		}
		$formatted = VLT_Dynamic_Tags::format_duration( $video->duration_seconds ?? 0 );
		if ( $formatted ) {
			echo esc_html( $formatted );
		}
	}
}

class VLT_Tag_VideoKey extends \Elementor\Core\DynamicTags\Tag {

	public function get_name() {
		return 'vlt-video-key';
	}

	public function get_title() {
		return __( 'VLT Video Key', 'video-lead-tracker' );
	}

	public function get_group() {
		return VLT_Dynamic_Tags::GROUP_NAME;
	}

	public function get_categories() {
		return [ \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY ];
	}

	protected function render() {
		$video = VLT_Dynamic_Tags::get_post_video();
		if ( $video && ! empty( $video->video_key ) ) {
			echo esc_html( $video->video_key );
		}
	}
}
