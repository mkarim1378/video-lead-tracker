<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $args extracted: $page, $title, $subtitle, $show_header, $show_video_filter, $ajax_video_filter, $actions_html, $legacy */
$nav_items = VLT_Admin_UI::nav_items();
?>
<div class="vlt-app<?php echo ! empty( $legacy ) ? ' vlt-app--legacy' : ''; ?>"
     data-page="<?php echo esc_attr( $page ); ?>"
     dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">

	<nav class="vlt-app-nav" aria-label="<?php esc_attr_e( 'Video Lead Tracker', 'video-lead-tracker' ); ?>">
		<div class="vlt-app-brand">
			<span class="dashicons dashicons-video-alt3" aria-hidden="true"></span>
			<span class="vlt-app-brand-text"><?php esc_html_e( 'Video Lead Tracker', 'video-lead-tracker' ); ?></span>
		</div>
		<ul class="vlt-app-nav-list">
			<?php foreach ( $nav_items as $item ) :
				$active = ( $page === $item['slug'] );
				$url    = admin_url( 'admin.php?page=' . $item['slug'] );
				?>
				<li>
					<a href="<?php echo esc_url( $url ); ?>"
					   class="vlt-app-nav-link<?php echo $active ? ' is-active' : ''; ?>"
					   <?php echo $active ? 'aria-current="page"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
						<span class="vlt-app-nav-label"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>

	<div class="vlt-app-content">
		<?php if ( ! empty( $show_header ) ) : ?>
			<header class="vlt-page-header">
				<div class="vlt-page-header-text">
					<?php if ( $title ) : ?>
						<h1 class="vlt-page-title"><?php echo esc_html( $title ); ?></h1>
					<?php endif; ?>
					<?php if ( $subtitle ) : ?>
						<p class="vlt-page-subtitle"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
				</div>
				<div class="vlt-page-header-tools">
					<?php if ( ! empty( $show_video_filter ) ) : ?>
						<?php VLT_Admin_UI::render_video_filter( $page, [], ! empty( $ajax_video_filter ) ); ?>
					<?php endif; ?>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted admin markup from callers.
					echo $actions_html;
					?>
				</div>
			</header>
		<?php endif; ?>

		<main class="vlt-app-main" id="vlt-app-main">
