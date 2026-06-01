<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots Dynamic Tag registration.
 * Loaded conditionally after `elementor/loaded` fires — do not include directly.
 * The three tag classes below extend Elementor base classes; they are safe here
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
			$manager->register( new VLT_Tag_VideoTitle() );
		}
	}

	/** Shared helper: resolve the vlt_videos row linked to the current post. */
	public static function get_post_video() {
		$video_key = (string) get_post_meta( get_the_ID(), '_vlt_video_key', true );
		return $video_key ? VLT_DB::get_video_by_key( $video_key ) : null;
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

// -------------------------------------------------------------------------
// Text Tag (Tag — renders HTML output directly)
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
