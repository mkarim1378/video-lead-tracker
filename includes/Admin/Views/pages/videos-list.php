<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var array  $videos
 * @var string $base_url
 * @var string|false $error
 * @var string $notice  added|updated|deleted|''
 */
?>
<div class="vlt-videos" id="vlt-videos-root">
	<?php if ( $notice ) : ?>
		<div class="vlt-inline-notice vlt-inline-notice--success" data-vlt-toast="<?php echo esc_attr( $notice ); ?>">
			<?php
			if ( 'added' === $notice ) {
				esc_html_e( 'Video added.', 'video-lead-tracker' );
			} elseif ( 'updated' === $notice ) {
				esc_html_e( 'Video updated.', 'video-lead-tracker' );
			} else {
				esc_html_e( 'Video deleted.', 'video-lead-tracker' );
			}
			?>
		</div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="vlt-inline-notice vlt-inline-notice--error"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>

	<?php if ( empty( $videos ) ) : ?>
		<?php
		VLT_Admin_UI::render( 'partials/empty-state', [
			'icon'    => 'video-alt3',
			'title'   => __( 'No videos registered yet', 'video-lead-tracker' ),
			'message' => __( 'Add your first gated video to start tracking leads.', 'video-lead-tracker' ),
		] );
		?>
		<p class="vlt-empty-cta">
			<a class="vlt-btn vlt-btn--primary" href="<?php echo esc_url( add_query_arg( 'action', 'add', $base_url ) ); ?>">
				<?php esc_html_e( 'Add Video', 'video-lead-tracker' ); ?>
			</a>
		</p>
	<?php else : ?>
		<div class="vlt-video-grid">
			<?php foreach ( $videos as $v ) :
				$linked_post = VLT_Videos_Admin::get_linked_post_public( $v->video_key );
				$shortcode   = '[vlt_video key="' . $v->video_key . '"]';
				$edit_url    = add_query_arg( [ 'action' => 'edit', 'video_id' => $v->id ], $base_url );
				?>
				<article class="vlt-video-card">
					<?php if ( ! empty( $v->poster_url ) ) : ?>
						<div class="vlt-video-card-media">
							<img src="<?php echo esc_url( $v->poster_url ); ?>" alt="">
						</div>
					<?php else : ?>
						<div class="vlt-video-card-media vlt-video-card-media--empty">
							<span class="dashicons dashicons-format-video" aria-hidden="true"></span>
						</div>
					<?php endif; ?>
					<div class="vlt-video-card-body">
						<div class="vlt-video-card-top">
							<strong class="vlt-video-card-title"><?php echo esc_html( $v->title ?: $v->video_key ); ?></strong>
							<div class="vlt-video-card-badges">
								<?php if ( $v->is_active ) : ?>
									<span class="vlt-badge vlt-badge--success"><?php esc_html_e( 'Active', 'video-lead-tracker' ); ?></span>
								<?php else : ?>
									<span class="vlt-badge"><?php esc_html_e( 'Inactive', 'video-lead-tracker' ); ?></span>
								<?php endif; ?>
								<?php if ( $v->enable_otp ) : ?>
									<span class="vlt-badge vlt-badge--info"><?php esc_html_e( 'OTP', 'video-lead-tracker' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<code class="vlt-mono"><?php echo esc_html( $v->video_key ); ?></code>
						<p class="vlt-muted">
							<?php
							echo $v->duration_seconds
								? esc_html( VLT_Admin::format_duration( (int) $v->duration_seconds ) )
								: '—';
							?>
							<?php if ( $linked_post ) : ?>
								· <a class="vlt-link" href="<?php echo esc_url( get_edit_post_link( $linked_post->ID ) ); ?>"><?php echo esc_html( $linked_post->post_title ?: __( '(no title)', 'video-lead-tracker' ) ); ?></a>
							<?php endif; ?>
						</p>
						<div class="vlt-video-card-shortcode">
							<code class="vlt-copy-source"><?php echo esc_html( $shortcode ); ?></code>
							<button type="button" class="vlt-btn vlt-btn--ghost vlt-copy-btn" data-copy="<?php echo esc_attr( $shortcode ); ?>">
								<?php esc_html_e( 'Copy', 'video-lead-tracker' ); ?>
							</button>
						</div>
						<?php if ( ! empty( $v->audio_url ) ) :
							$audio_sc = '[vlt_audio_player key="' . $v->video_key . '"]';
							?>
							<div class="vlt-video-card-shortcode">
								<code class="vlt-copy-source vlt-mono--sm"><?php echo esc_html( $audio_sc ); ?></code>
								<button type="button" class="vlt-btn vlt-btn--ghost vlt-copy-btn" data-copy="<?php echo esc_attr( $audio_sc ); ?>">
									<?php esc_html_e( 'Copy', 'video-lead-tracker' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
					<div class="vlt-video-card-actions">
						<a class="vlt-btn vlt-btn--secondary" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'video-lead-tracker' ); ?></a>
						<form method="post" class="vlt-inline-form vlt-video-delete-form">
							<?php wp_nonce_field( 'vlt_video_save' ); ?>
							<input type="hidden" name="vlt_video_action" value="delete">
							<input type="hidden" name="video_id" value="<?php echo (int) $v->id; ?>">
							<button type="submit" class="vlt-btn vlt-btn--ghost vlt-btn--danger-text vlt-video-delete-btn"
							        data-confirm-title="<?php esc_attr_e( 'Delete video?', 'video-lead-tracker' ); ?>"
							        data-confirm-body="<?php esc_attr_e( 'Analytics data will be preserved. This cannot be undone.', 'video-lead-tracker' ); ?>">
								<?php esc_html_e( 'Delete', 'video-lead-tracker' ); ?>
							</button>
						</form>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
