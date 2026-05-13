<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Videos_Admin {

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle form actions before any output.
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
			// Store error in transient for display.
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

		// Uniqueness check: if adding new, or if key changed during edit.
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
							<th><?php esc_html_e( 'Key',      'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Title',    'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Duration', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'OTP',      'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Active',   'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Shortcode','video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Actions',  'video-lead-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $videos as $v ) : ?>
						<tr>
							<td><code><?php echo esc_html( $v->video_key ); ?></code></td>
							<td><?php echo esc_html( $v->title ?: '—' ); ?></td>
							<td><?php echo $v->duration_seconds ? esc_html( $v->duration_seconds ) . 's' : '—'; ?></td>
							<td><?php echo $v->enable_otp ? '✓' : '—'; ?></td>
							<td><?php echo $v->is_active ? '✓' : '—'; ?></td>
							<td><code>[video_lead_tracker key="<?php echo esc_attr( $v->video_key ); ?>"]</code></td>
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

	// -------------------------------------------------------------------------
	// Add / Edit form
	// -------------------------------------------------------------------------

	private static function render_form( $video ) {
		$is_edit  = ! is_null( $video );
		$base_url = admin_url( 'admin.php?page=vlt-videos' );

		$f = [
			'video_key'          => $is_edit ? $video->video_key          : '',
			'title'              => $is_edit ? $video->title               : '',
			'video_url'          => $is_edit ? $video->video_url           : '',
			'poster_url'         => $is_edit ? $video->poster_url          : '',
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
						<td><input type="url" id="vlt_video_url" name="video_url" value="<?php echo esc_attr( $f['video_url'] ); ?>" class="large-text" /></td>
					</tr>

					<tr>
						<th><label for="vlt_poster_url"><?php esc_html_e( 'Poster Image URL', 'video-lead-tracker' ); ?></label></th>
						<td>
							<input type="url" id="vlt_poster_url" name="poster_url" value="<?php echo esc_attr( $f['poster_url'] ); ?>" class="large-text" />
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
		<?php
	}
}
