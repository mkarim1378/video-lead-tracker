<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var string $page
 * @var string $title
 * @var string $subtitle
 * @var bool   $show_video_filter
 * @var bool   $ajax_video_filter
 * @var string $actions_html
 */
?>
<header class="vlt-page-header">
	<div class="vlt-page-header-text">
		<?php if ( ! empty( $title ) ) : ?>
			<h1 class="vlt-page-title"><?php echo esc_html( $title ); ?></h1>
		<?php endif; ?>
		<?php if ( ! empty( $subtitle ) ) : ?>
			<p class="vlt-page-subtitle"><?php echo esc_html( $subtitle ); ?></p>
		<?php endif; ?>
	</div>
	<div class="vlt-page-header-tools">
		<?php if ( ! empty( $show_video_filter ) ) : ?>
			<?php VLT_Admin_UI::render_video_filter( $page, [], ! empty( $ajax_video_filter ) ); ?>
		<?php endif; ?>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted admin markup from callers.
		echo $actions_html ?? '';
		?>
	</div>
</header>
