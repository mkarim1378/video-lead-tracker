<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $args extracted: $page, $title, $subtitle, $show_header, $show_video_filter, $ajax_video_filter, $actions_html, $legacy */
$nav_items = VLT_Admin_UI::nav_items();
?>
<script>
( function () {
	try {
		var t = localStorage.getItem( 'vlt_admin_theme' ) || 'dark';
		if ( t !== 'light' && t !== 'dark' ) t = 'dark';
		if ( document.body ) document.body.setAttribute( 'data-vlt-theme', t );
	} catch ( e ) { /* ignore */ }
}() );
</script>
<div class="vlt-app<?php echo ! empty( $legacy ) ? ' vlt-app--legacy' : ''; ?>"
     data-page="<?php echo esc_attr( $page ); ?>"
     data-theme="dark"
     dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
<script>
( function () {
	try {
		var app = document.currentScript.parentElement;
		var t = localStorage.getItem( 'vlt_admin_theme' ) || 'dark';
		if ( t !== 'light' && t !== 'dark' ) t = 'dark';
		if ( app ) app.setAttribute( 'data-theme', t );
		if ( document.body ) document.body.setAttribute( 'data-vlt-theme', t );
	} catch ( e ) { /* ignore */ }
}() );
</script>

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
			<?php
			VLT_Admin_UI::render( 'partials/page-header', [
				'page'              => $page,
				'title'             => $title ?? '',
				'subtitle'          => $subtitle ?? '',
				'show_video_filter' => ! empty( $show_video_filter ),
				'ajax_video_filter' => ! empty( $ajax_video_filter ),
				'actions_html'      => $actions_html ?? '',
			] );
			?>
		<?php endif; ?>

		<main class="vlt-app-main" id="vlt-app-main">
