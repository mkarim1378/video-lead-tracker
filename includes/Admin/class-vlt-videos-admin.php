<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Videos_Admin {

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	public static function init() {
		add_action( 'add_meta_boxes', [ self::class, 'register_meta_boxes' ] );
		add_action( 'save_post',      [ self::class, 'handle_meta_box_save' ], 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Meta boxes
	// -------------------------------------------------------------------------

	public static function register_meta_boxes() {
		// CPT posts: video URL + poster URL fields, auto-creates vlt_videos on save.
		add_meta_box(
			'vlt_video_cpt',
			__( 'Video Settings', 'video-lead-tracker' ),
			[ self::class, 'render_meta_box_cpt' ],
			VLT_CPT::POST_TYPE,
			'normal',
			'high'
		);

		// Regular posts and pages: dropdown of existing vlt_videos.
		foreach ( [ 'post', 'page' ] as $pt ) {
			add_meta_box(
				'vlt_video_post',
				__( 'Video Lead Tracker', 'video-lead-tracker' ),
				[ self::class, 'render_meta_box_post' ],
				$pt,
				'side',
				'default'
			);
		}
	}

	public static function render_meta_box_cpt( $post ) {
		wp_nonce_field( 'vlt_meta_box_save', 'vlt_meta_box_nonce' );

		$video_key  = get_post_meta( $post->ID, '_vlt_video_key', true );
		$video_url  = get_post_meta( $post->ID, '_vlt_meta_video_url',  true );
		$poster_url = get_post_meta( $post->ID, '_vlt_meta_poster_url', true );

		// If already linked to a video in registry, use its values as fallback.
		if ( $video_key && ( ! $video_url || ! $poster_url ) ) {
			$v = VLT_DB::get_video_by_key( $video_key );
			if ( $v ) {
				$video_url  = $video_url  ?: ( $v->video_url  ?? '' );
				$poster_url = $poster_url ?: ( $v->poster_url ?? '' );
			}
		}
		?>
		<table class="form-table" style="margin:0">
			<tr>
				<th style="padding:6px 4px 6px 0;font-weight:600;width:100px">
					<label for="vlt_mb_video_url"><?php esc_html_e( 'Video URL', 'video-lead-tracker' ); ?></label>
				</th>
				<td style="padding:6px 0">
					<div style="display:flex;align-items:center;gap:8px">
						<input type="url" id="vlt_mb_video_url" name="vlt_video_url"
						       value="<?php echo esc_attr( $video_url ); ?>" class="widefat">
						<a id="vlt-mb-test-link"
						   href="<?php echo $video_url ? esc_url( $video_url ) : '#'; ?>"
						   target="_blank" rel="noopener"
						   style="white-space:nowrap;font-size:12px<?php echo $video_url ? '' : ';visibility:hidden'; ?>">
							<?php esc_html_e( 'Test ↗', 'video-lead-tracker' ); ?>
						</a>
					</div>
					<p class="description" style="margin:4px 0 0"><?php esc_html_e( 'Direct URL to the MP4 hosted on your CDN.', 'video-lead-tracker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th style="padding:6px 4px 6px 0;font-weight:600">
					<label for="vlt_mb_poster_url"><?php esc_html_e( 'Poster URL', 'video-lead-tracker' ); ?></label>
				</th>
				<td style="padding:6px 0">
					<input type="url" id="vlt_mb_poster_url" name="vlt_poster_url"
					       value="<?php echo esc_attr( $poster_url ); ?>" class="widefat">
					<div id="vlt-mb-poster-wrap" style="margin-top:8px<?php echo $poster_url ? '' : ';display:none'; ?>">
						<img id="vlt-mb-poster-img"
						     src="<?php echo esc_url( $poster_url ); ?>"
						     alt=""
						     style="max-width:160px;max-height:90px;border:1px solid #c3c4c7;border-radius:2px">
					</div>
				</td>
			</tr>
			<?php if ( $video_key ) : ?>
			<tr>
				<th style="padding:6px 4px 6px 0;font-weight:600"><?php esc_html_e( 'Video Key', 'video-lead-tracker' ); ?></th>
				<td style="padding:6px 0">
					<code><?php echo esc_html( $video_key ); ?></code>
					<p class="description" style="margin:2px 0 0"><?php esc_html_e( 'Use [vlt_video] in the content to embed this video.', 'video-lead-tracker' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<script>
		( function () {
			var videoIn  = document.getElementById( 'vlt_mb_video_url' );
			var testLink = document.getElementById( 'vlt-mb-test-link' );
			var posterIn = document.getElementById( 'vlt_mb_poster_url' );
			var posterWr = document.getElementById( 'vlt-mb-poster-wrap' );
			var posterIm = document.getElementById( 'vlt-mb-poster-img' );

			if ( videoIn && testLink ) {
				videoIn.addEventListener( 'input', function () {
					var u = videoIn.value.trim();
					testLink.href = u || '#';
					testLink.style.visibility = u ? '' : 'hidden';
				} );
			}

			if ( posterIn && posterWr && posterIm ) {
				posterIn.addEventListener( 'blur', function () {
					var u = posterIn.value.trim();
					if ( u ) {
						posterIm.src = u;
						posterWr.style.display = '';
					} else {
						posterWr.style.display = 'none';
					}
				} );
			}
		}() );
		</script>
		<?php
	}

	public static function render_meta_box_post( $post ) {
		wp_nonce_field( 'vlt_meta_box_save', 'vlt_meta_box_nonce' );

		$current_key = get_post_meta( $post->ID, '_vlt_video_key', true );
		$videos      = VLT_DB::get_all_videos();

		// Build a map of key → poster for the preview.
		$poster_map = [];
		foreach ( $videos as $v ) {
			if ( $v->poster_url ) {
				$poster_map[ esc_js( $v->video_key ) ] = esc_url( $v->poster_url );
			}
		}
		?>
		<p>
			<label for="vlt_post_video_key" style="display:block;margin-bottom:4px;font-weight:600">
				<?php esc_html_e( 'Linked Video', 'video-lead-tracker' ); ?>
			</label>
			<select id="vlt_post_video_key" name="vlt_video_key_select" style="width:100%">
				<option value=""><?php esc_html_e( '— None —', 'video-lead-tracker' ); ?></option>
				<?php foreach ( $videos as $v ) : ?>
					<option value="<?php echo esc_attr( $v->video_key ); ?>"
					        data-poster="<?php echo esc_attr( $v->poster_url ?? '' ); ?>"
					        <?php selected( $current_key, $v->video_key ); ?>>
						<?php echo esc_html( $v->title ?: $v->video_key ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<div id="vlt-post-poster-wrap" style="margin-top:8px<?php echo ( $current_key && self::get_video_poster( $current_key, $videos ) ) ? '' : ';display:none'; ?>">
			<img id="vlt-post-poster-img"
			     src="<?php echo esc_url( self::get_video_poster( $current_key, $videos ) ); ?>"
			     alt=""
			     style="max-width:100%;border:1px solid #c3c4c7;border-radius:2px">
		</div>
		<p class="description" style="margin-top:4px">
			<?php esc_html_e( 'Place [vlt_video] in the content to embed the selected video.', 'video-lead-tracker' ); ?>
		</p>
		<script>
		( function () {
			var sel  = document.getElementById( 'vlt_post_video_key' );
			var wrap = document.getElementById( 'vlt-post-poster-wrap' );
			var img  = document.getElementById( 'vlt-post-poster-img' );
			if ( ! sel ) return;
			sel.addEventListener( 'change', function () {
				var opt    = sel.options[ sel.selectedIndex ];
				var poster = opt ? opt.getAttribute( 'data-poster' ) : '';
				if ( poster ) {
					img.src         = poster;
					wrap.style.display = '';
				} else {
					wrap.style.display = 'none';
				}
			} );
		}() );
		</script>
		<?php
	}

	private static function get_video_poster( $video_key, $videos ) {
		foreach ( $videos as $v ) {
			if ( $v->video_key === $video_key ) {
				return $v->poster_url ?? '';
			}
		}
		return '';
	}

	public static function handle_meta_box_save( $post_id, $post ) {
		if ( ! isset( $_POST['vlt_meta_box_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['vlt_meta_box_nonce'], 'vlt_meta_box_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// ---- CPT: URL fields → auto-create/update vlt_videos ----
		if ( VLT_CPT::POST_TYPE === $post->post_type ) {
			$video_url  = esc_url_raw( $_POST['vlt_video_url']  ?? '' );
			$poster_url = esc_url_raw( $_POST['vlt_poster_url'] ?? '' );

			update_post_meta( $post_id, '_vlt_meta_video_url',  $video_url );
			update_post_meta( $post_id, '_vlt_meta_poster_url', $poster_url );

			if ( $video_url ) {
				$slug = $post->post_name ?: sanitize_title( $post->post_title );
				if ( $slug ) {
					self::upsert_video_from_cpt( $post_id, $slug, $post->post_title, $video_url, $poster_url );
				}
			}
			return;
		}

		// ---- post / page: dropdown → _vlt_video_key meta ----
		$selected_key = sanitize_key( $_POST['vlt_video_key_select'] ?? '' );
		if ( $selected_key ) {
			update_post_meta( $post_id, '_vlt_video_key', $selected_key );
		} else {
			delete_post_meta( $post_id, '_vlt_video_key' );
		}
	}

	private static function upsert_video_from_cpt( $post_id, $slug, $title, $video_url, $poster_url ) {
		$now      = current_time( 'mysql' );
		$existing = VLT_DB::get_video_by_key( $slug );

		if ( $existing ) {
			VLT_DB::update_video( (int) $existing->id, [
				'title'      => $title,
				'video_url'  => $video_url,
				'poster_url' => $poster_url,
				'is_active'  => 1,
				'updated_at' => $now,
			] );
		} else {
			VLT_DB::create_video( [
				'video_key'  => $slug,
				'title'      => $title,
				'video_url'  => $video_url,
				'poster_url' => $poster_url,
				'is_active'  => 1,
				'created_at' => $now,
				'updated_at' => $now,
			] );
		}

		update_post_meta( $post_id, '_vlt_video_key', $slug );
	}

	// -------------------------------------------------------------------------
	// Admin page dispatcher
	// -------------------------------------------------------------------------

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::handle_actions();

		$action   = sanitize_key( $_GET['action'] ?? '' );
		$video_id = absint( $_GET['video_id'] ?? 0 );

		if ( 'edit' === $action && $video_id ) {
			$video = VLT_DB::get_video_by_id( $video_id );
			if ( $video ) {
				self::render_form( $video );
				return;
			}
		}

		if ( 'add' === $action ) {
			self::render_form( null );
			return;
		}

		self::render_list();
	}

	// -------------------------------------------------------------------------
	// Action handlers
	// -------------------------------------------------------------------------

	private static function handle_actions() {
		$action = sanitize_key( $_POST['vlt_video_action'] ?? '' );
		if ( ! $action ) {
			return;
		}

		check_admin_referer( 'vlt_video_save' );

		if ( 'delete' === $action ) {
			$id = absint( $_POST['video_id'] ?? 0 );
			if ( $id ) {
				VLT_DB::delete_video( $id );
				wp_redirect( add_query_arg( [ 'page' => 'vlt-videos', 'deleted' => 1 ], admin_url( 'admin.php' ) ) );
				exit;
			}
			return;
		}

		$data = self::collect_and_sanitize();
		if ( is_wp_error( $data ) ) {
			set_transient( 'vlt_video_error_' . get_current_user_id(), $data->get_error_message(), 60 );
			return;
		}

		$video_id = absint( $_POST['video_id'] ?? 0 );
		$now      = current_time( 'mysql' );

		if ( $video_id ) {
			$data['updated_at'] = $now;
			VLT_DB::update_video( $video_id, $data );
			wp_redirect( add_query_arg( [ 'page' => 'vlt-videos', 'updated' => 1 ], admin_url( 'admin.php' ) ) );
		} else {
			$data['created_at'] = $now;
			$data['updated_at'] = $now;
			VLT_DB::create_video( $data );
			wp_redirect( add_query_arg( [ 'page' => 'vlt-videos', 'added' => 1 ], admin_url( 'admin.php' ) ) );
		}
		exit;
	}

	private static function collect_and_sanitize() {
		$key = sanitize_key( $_POST['video_key'] ?? '' );
		if ( ! $key ) {
			return new WP_Error( 'missing_key', __( 'Video Key is required.', 'video-lead-tracker' ) );
		}

		$existing_by_key = VLT_DB::get_video_by_key( $key );
		$editing_id      = absint( $_POST['video_id'] ?? 0 );
		if ( $existing_by_key && (int) $existing_by_key->id !== $editing_id ) {
			return new WP_Error( 'duplicate_key', __( 'This Video Key is already in use. Choose a different slug.', 'video-lead-tracker' ) );
		}

		return [
			'video_key'          => $key,
			'title'              => sanitize_text_field( $_POST['title']              ?? '' ),
			'video_url'          => esc_url_raw( $_POST['video_url']                  ?? '' ),
			'poster_url'         => esc_url_raw( $_POST['poster_url']                 ?? '' ),
			'duration_seconds'   => absint( $_POST['duration_seconds']                ?? 0 ),
			'form_title'         => sanitize_text_field( $_POST['form_title']         ?? '' ),
			'name_label'         => sanitize_text_field( $_POST['name_label']         ?? '' ),
			'mobile_label'       => sanitize_text_field( $_POST['mobile_label']       ?? '' ),
			'submit_button_text' => sanitize_text_field( $_POST['submit_button_text'] ?? '' ),
			'success_message'    => sanitize_textarea_field( $_POST['success_message'] ?? '' ),
			'enable_otp'         => ! empty( $_POST['enable_otp'] ) ? 1 : 0,
			'is_active'          => ! empty( $_POST['is_active'] )  ? 1 : 0,
		];
	}

	// -------------------------------------------------------------------------
	// List view
	// -------------------------------------------------------------------------

	private static function render_list() {
		$videos   = VLT_DB::get_all_videos();
		$base_url = admin_url( 'admin.php?page=vlt-videos' );
		$error    = get_transient( 'vlt_video_error_' . get_current_user_id() );
		if ( $error ) {
			delete_transient( 'vlt_video_error_' . get_current_user_id() );
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Videos', 'video-lead-tracker' ); ?></h1>
			<a href="<?php echo esc_url( add_query_arg( 'action', 'add', $base_url ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'video-lead-tracker' ); ?>
			</a>

			<?php if ( ! empty( $_GET['added'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Video added.', 'video-lead-tracker' ); ?></p></div>
			<?php elseif ( ! empty( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Video updated.', 'video-lead-tracker' ); ?></p></div>
			<?php elseif ( ! empty( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Video deleted.', 'video-lead-tracker' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php if ( empty( $videos ) ) : ?>
				<p><?php esc_html_e( 'No videos registered yet.', 'video-lead-tracker' ); ?>
				   <a href="<?php echo esc_url( add_query_arg( 'action', 'add', $base_url ) ); ?>"><?php esc_html_e( 'Add your first video.', 'video-lead-tracker' ); ?></a>
				</p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Key',          'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Title',        'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Linked Post',  'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Duration',     'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'OTP',          'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Active',       'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Shortcode',    'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Actions',      'video-lead-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $videos as $v ) :
						$linked_post = self::get_linked_post( $v->video_key );
					?>
						<tr>
							<td><code><?php echo esc_html( $v->video_key ); ?></code></td>
							<td><?php echo esc_html( $v->title ?: '—' ); ?></td>
							<td>
								<?php if ( $linked_post ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $linked_post->ID ) ); ?>">
										<?php echo esc_html( $linked_post->post_title ?: '(' . __( 'no title', 'video-lead-tracker' ) . ')' ); ?>
									</a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo $v->duration_seconds ? esc_html( $v->duration_seconds ) . 's' : '—'; ?></td>
							<td><?php echo $v->enable_otp ? '✓' : '—'; ?></td>
							<td><?php echo $v->is_active ? '✓' : '—'; ?></td>
							<td><code>[vlt_video key="<?php echo esc_attr( $v->video_key ); ?>"]</code></td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'video_id' => $v->id ], $base_url ) ); ?>">
									<?php esc_html_e( 'Edit', 'video-lead-tracker' ); ?>
								</a>
								&nbsp;|&nbsp;
								<form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this video? Analytics data will be preserved.', 'video-lead-tracker' ); ?>');">
									<?php wp_nonce_field( 'vlt_video_save' ); ?>
									<input type="hidden" name="vlt_video_action" value="delete">
									<input type="hidden" name="video_id" value="<?php echo (int) $v->id; ?>">
									<button type="submit" class="button-link" style="color:#a00;"><?php esc_html_e( 'Delete', 'video-lead-tracker' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function get_linked_post( $video_key ) {
		$posts = get_posts( [
			'post_type'      => VLT_CPT::POST_TYPE,
			'posts_per_page' => 1,
			'meta_key'       => '_vlt_video_key',
			'meta_value'     => $video_key,
			'post_status'    => 'any',
			'fields'         => 'ids',
		] );
		return $posts ? get_post( $posts[0] ) : null;
	}

	// -------------------------------------------------------------------------
	// Add / Edit form
	// -------------------------------------------------------------------------

	private static function render_form( $video ) {
		$is_edit  = ! is_null( $video );
		$base_url = admin_url( 'admin.php?page=vlt-videos' );

		$f = [
			'video_key'          => $is_edit ? $video->video_key          : '',
			'title'              => $is_edit ? $video->title               : '',
			'video_url'          => $is_edit ? ( $video->video_url  ?? '' ) : '',
			'poster_url'         => $is_edit ? ( $video->poster_url ?? '' ) : '',
			'duration_seconds'   => $is_edit ? $video->duration_seconds    : '',
			'form_title'         => $is_edit ? $video->form_title          : __( 'Watch the Free Training', 'video-lead-tracker' ),
			'name_label'         => $is_edit ? $video->name_label          : __( 'Full Name', 'video-lead-tracker' ),
			'mobile_label'       => $is_edit ? $video->mobile_label        : __( 'Mobile Number', 'video-lead-tracker' ),
			'submit_button_text' => $is_edit ? $video->submit_button_text  : __( 'Watch Now', 'video-lead-tracker' ),
			'success_message'    => $is_edit ? $video->success_message     : __( 'Welcome! Your video is ready.', 'video-lead-tracker' ),
			'enable_otp'         => $is_edit ? (bool) $video->enable_otp  : false,
			'is_active'          => $is_edit ? (bool) $video->is_active    : true,
		];
		?>
		<div class="wrap">
			<h1><?php echo $is_edit ? esc_html__( 'Edit Video', 'video-lead-tracker' ) : esc_html__( 'Add Video', 'video-lead-tracker' ); ?></h1>
			<a href="<?php echo esc_url( $base_url ); ?>">&larr; <?php esc_html_e( 'Back to Videos', 'video-lead-tracker' ); ?></a>

			<form method="post" style="margin-top:20px;">
				<?php wp_nonce_field( 'vlt_video_save' ); ?>
				<input type="hidden" name="vlt_video_action" value="save">
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="video_id" value="<?php echo (int) $video->id; ?>">
				<?php endif; ?>

				<table class="form-table" role="presentation">

					<tr>
						<th><label for="vlt_video_key"><?php esc_html_e( 'Video Key', 'video-lead-tracker' ); ?> <span style="color:red">*</span></label></th>
						<td>
							<input type="text" id="vlt_video_key" name="video_key" value="<?php echo esc_attr( $f['video_key'] ); ?>"
							       class="regular-text" <?php echo $is_edit ? 'readonly' : ''; ?> required />
							<p class="description"><?php esc_html_e( 'Unique slug used in the shortcode key attribute. Cannot be changed after creation.', 'video-lead-tracker' ); ?></p>
						</td>
					</tr>

					<tr>
						<th><label for="vlt_title"><?php esc_html_e( 'Title', 'video-lead-tracker' ); ?></label></th>
						<td>
							<input type="text" id="vlt_title" name="title" value="<?php echo esc_attr( $f['title'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Display name shown in admin reports.', 'video-lead-tracker' ); ?></p>
						</td>
					</tr>

					<tr>
						<th><label for="vlt_video_url"><?php esc_html_e( 'Video URL', 'video-lead-tracker' ); ?></label></th>
						<td>
							<div style="display:flex;align-items:center;gap:8px">
								<input type="url" id="vlt_video_url" name="video_url"
								       value="<?php echo esc_attr( $f['video_url'] ); ?>" class="large-text" />
								<a id="vlt-form-test-link"
								   href="<?php echo $f['video_url'] ? esc_url( $f['video_url'] ) : '#'; ?>"
								   target="_blank" rel="noopener"
								   style="white-space:nowrap<?php echo $f['video_url'] ? '' : ';visibility:hidden'; ?>">
									<?php esc_html_e( 'Test ↗', 'video-lead-tracker' ); ?>
								</a>
							</div>
						</td>
					</tr>

					<tr>
						<th><label for="vlt_poster_url"><?php esc_html_e( 'Poster Image URL', 'video-lead-tracker' ); ?></label></th>
						<td>
							<input type="url" id="vlt_poster_url" name="poster_url"
							       value="<?php echo esc_attr( $f['poster_url'] ); ?>" class="large-text" />
							<div id="vlt-form-poster-wrap" style="margin-top:8px<?php echo $f['poster_url'] ? '' : ';display:none'; ?>">
								<img id="vlt-form-poster-img"
								     src="<?php echo esc_url( $f['poster_url'] ); ?>"
								     alt=""
								     style="max-width:240px;max-height:135px;border:1px solid #c3c4c7;border-radius:2px">
							</div>
							<p class="description"><?php esc_html_e( 'Thumbnail shown before the video plays.', 'video-lead-tracker' ); ?></p>
						</td>
					</tr>

					<tr>
						<th><label for="vlt_duration"><?php esc_html_e( 'Duration (seconds)', 'video-lead-tracker' ); ?></label></th>
						<td>
							<input type="number" id="vlt_duration" name="duration_seconds" value="<?php echo esc_attr( (string) $f['duration_seconds'] ); ?>" min="0" class="small-text" />
							<p class="description"><?php esc_html_e( 'Used to calculate watch percentage. Set 0 to auto-detect.', 'video-lead-tracker' ); ?></p>
						</td>
					</tr>

					<tr><th colspan="2"><h2 style="margin:0;padding:16px 0 4px;"><?php esc_html_e( 'Lead Form', 'video-lead-tracker' ); ?></h2></th></tr>

					<tr>
						<th><label for="vlt_form_title"><?php esc_html_e( 'Form Title', 'video-lead-tracker' ); ?></label></th>
						<td><input type="text" id="vlt_form_title" name="form_title" value="<?php echo esc_attr( $f['form_title'] ); ?>" class="regular-text" /></td>
					</tr>

					<tr>
						<th><label for="vlt_name_label"><?php esc_html_e( 'Name Field Label', 'video-lead-tracker' ); ?></label></th>
						<td><input type="text" id="vlt_name_label" name="name_label" value="<?php echo esc_attr( $f['name_label'] ); ?>" class="regular-text" /></td>
					</tr>

					<tr>
						<th><label for="vlt_mobile_label"><?php esc_html_e( 'Mobile Field Label', 'video-lead-tracker' ); ?></label></th>
						<td><input type="text" id="vlt_mobile_label" name="mobile_label" value="<?php echo esc_attr( $f['mobile_label'] ); ?>" class="regular-text" /></td>
					</tr>

					<tr>
						<th><label for="vlt_submit_btn"><?php esc_html_e( 'Submit Button Text', 'video-lead-tracker' ); ?></label></th>
						<td><input type="text" id="vlt_submit_btn" name="submit_button_text" value="<?php echo esc_attr( $f['submit_button_text'] ); ?>" class="regular-text" /></td>
					</tr>

					<tr>
						<th><label for="vlt_success_message"><?php esc_html_e( 'Success Message', 'video-lead-tracker' ); ?></label></th>
						<td><textarea id="vlt_success_message" name="success_message" rows="2" class="large-text"><?php echo esc_textarea( $f['success_message'] ); ?></textarea></td>
					</tr>

					<tr><th colspan="2"><h2 style="margin:0;padding:16px 0 4px;"><?php esc_html_e( 'Options', 'video-lead-tracker' ); ?></h2></th></tr>

					<tr>
						<th><?php esc_html_e( 'OTP Verification', 'video-lead-tracker' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_otp" value="1" <?php checked( $f['enable_otp'] ); ?> />
								<?php esc_html_e( 'Require SMS OTP before showing this video', 'video-lead-tracker' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'OTP provider settings are configured in Settings → OTP.', 'video-lead-tracker' ); ?></p>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Active', 'video-lead-tracker' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="is_active" value="1" <?php checked( $f['is_active'] ); ?> />
								<?php esc_html_e( 'Video is active and visible via shortcode', 'video-lead-tracker' ); ?>
							</label>
						</td>
					</tr>

				</table>

				<?php submit_button( $is_edit ? __( 'Update Video', 'video-lead-tracker' ) : __( 'Add Video', 'video-lead-tracker' ) ); ?>
			</form>
		</div>

		<script>
		( function () {
			var videoIn  = document.getElementById( 'vlt_video_url' );
			var testLink = document.getElementById( 'vlt-form-test-link' );
			var posterIn = document.getElementById( 'vlt_poster_url' );
			var posterWr = document.getElementById( 'vlt-form-poster-wrap' );
			var posterIm = document.getElementById( 'vlt-form-poster-img' );

			if ( videoIn && testLink ) {
				videoIn.addEventListener( 'input', function () {
					var u = videoIn.value.trim();
					testLink.href = u || '#';
					testLink.style.visibility = u ? '' : 'hidden';
				} );
			}

			if ( posterIn && posterWr && posterIm ) {
				posterIn.addEventListener( 'blur', function () {
					var u = posterIn.value.trim();
					if ( u ) {
						posterIm.src = u;
						posterWr.style.display = '';
					} else {
						posterWr.style.display = 'none';
					}
				} );
			}
		}() );
		</script>
		<?php
	}
}
