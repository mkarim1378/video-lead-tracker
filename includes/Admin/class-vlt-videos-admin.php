<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Videos_Admin {

	/** @var string Validation error from the current POST, shown on the re-rendered form. */
	private static $form_error = '';

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	public static function init() {
		add_action( 'add_meta_boxes',        [ self::class, 'register_meta_boxes' ] );
		add_action( 'save_post',             [ self::class, 'handle_meta_box_save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_media_on_cpt_screen' ] );
	}

	public static function enqueue_media_on_cpt_screen( $hook ) {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$needs_style = in_array( $screen->post_type, [ VLT_CPT::POST_TYPE, 'post', 'page' ], true );
		if ( $needs_style ) {
			VLT_Admin_Assets::enqueue_style();
		}
		if ( $screen->post_type === VLT_CPT::POST_TYPE ) {
			wp_enqueue_media();
		}
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
		$audio_url  = get_post_meta( $post->ID, '_vlt_meta_audio_url',  true );

		// If already linked to a video in registry, use its values as fallback.
		if ( $video_key && ( ! $video_url || ! $poster_url || ! $audio_url ) ) {
			$v = VLT_DB::get_video_by_key( $video_key );
			if ( $v ) {
				$video_url  = $video_url  ?: ( $v->video_url  ?? '' );
				$poster_url = $poster_url ?: ( $v->poster_url ?? '' );
				$audio_url  = $audio_url  ?: ( $v->audio_url  ?? '' );
			}
		}
		$copy_label   = esc_attr__( 'Copy',    'video-lead-tracker' );
		$copied_label = esc_attr__( 'Copied!', 'video-lead-tracker' );
		$select_label = esc_attr__( 'Select Image',    'video-lead-tracker' );
		$use_label    = esc_attr__( 'Use This Image',  'video-lead-tracker' );
		$media_title  = esc_attr__( 'Select Poster Image', 'video-lead-tracker' );
		?>
		<div class="vlt-metabox">
		<table class="vlt-metabox-table">
			<tr>
				<th>
					<label for="vlt_mb_video_url"><?php esc_html_e( 'Video URL', 'video-lead-tracker' ); ?></label>
				</th>
				<td>
					<div class="vlt-metabox-row">
						<input type="url" id="vlt_mb_video_url" name="vlt_video_url"
						       value="<?php echo esc_attr( $video_url ); ?>" class="widefat">
						<a id="vlt-mb-test-link" class="vlt-metabox-test<?php echo $video_url ? '' : ' is-hidden'; ?>"
						   href="<?php echo $video_url ? esc_url( $video_url ) : '#'; ?>"
						   target="_blank" rel="noopener">
							<?php esc_html_e( 'Test ↗', 'video-lead-tracker' ); ?>
						</a>
					</div>
					<p class="vlt-metabox-hint"><?php esc_html_e( 'Direct URL to the MP4 hosted on your CDN.', 'video-lead-tracker' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Poster Image', 'video-lead-tracker' ); ?></th>
				<td>
					<input type="hidden" id="vlt_mb_poster_url" name="vlt_poster_url"
					       value="<?php echo esc_attr( $poster_url ); ?>">
					<div id="vlt-mb-poster-wrap" class="vlt-metabox-poster<?php echo $poster_url ? '' : ' is-hidden'; ?>">
						<img id="vlt-mb-poster-img"
						     src="<?php echo esc_url( $poster_url ); ?>"
						     alt="">
					</div>
					<div class="vlt-metabox-actions">
						<button type="button" class="button" id="vlt-mb-poster-select"
						        data-title="<?php echo $media_title; ?>"
						        data-button="<?php echo $use_label; ?>">
							<?php esc_html_e( 'Select Image', 'video-lead-tracker' ); ?>
						</button>
						<button type="button" class="button-link-delete<?php echo $poster_url ? '' : ' is-hidden'; ?>" id="vlt-mb-poster-remove">
							<?php esc_html_e( 'Remove', 'video-lead-tracker' ); ?>
						</button>
					</div>
				</td>
			</tr>
			<tr>
				<th>
					<label for="vlt_mb_audio_url"><?php esc_html_e( 'Audio URL', 'video-lead-tracker' ); ?></label>
				</th>
				<td>
					<div class="vlt-metabox-row">
						<input type="url" id="vlt_mb_audio_url" name="vlt_audio_url"
						       value="<?php echo esc_attr( $audio_url ); ?>" class="widefat"
						       placeholder="https://example.com/audio.mp3">
						<a id="vlt-mb-audio-test-link" class="vlt-metabox-test<?php echo $audio_url ? '' : ' is-hidden'; ?>"
						   href="<?php echo $audio_url ? esc_url( $audio_url ) : '#'; ?>"
						   target="_blank" rel="noopener">
							<?php esc_html_e( 'Test ↗', 'video-lead-tracker' ); ?>
						</a>
					</div>
					<p class="vlt-metabox-hint"><?php esc_html_e( 'Direct URL to the audio file (MP3). Use [vlt_audio_player] shortcode to embed.', 'video-lead-tracker' ); ?></p>
				</td>
			</tr>
			<?php if ( $video_key ) : ?>
			<tr>
				<th><?php esc_html_e( 'Shortcode', 'video-lead-tracker' ); ?></th>
				<td>
					<div class="vlt-metabox-row">
						<code id="vlt-mb-shortcode">[vlt_video key="<?php echo esc_attr( $video_key ); ?>"]</code>
						<button type="button" class="button button-small vlt-metabox-copy" id="vlt-mb-copy-sc"
						        data-copy="<?php echo $copy_label; ?>"
						        data-copied="<?php echo $copied_label; ?>">
							<?php esc_html_e( 'Copy', 'video-lead-tracker' ); ?>
						</button>
					</div>
					<p class="vlt-metabox-hint">
						<?php esc_html_e( 'Paste this shortcode anywhere in the content to embed the video.', 'video-lead-tracker' ); ?>
					</p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		</div>
		<script>
		( function () {
			// ── Video URL test link ──────────────────────────────────────────────
			var videoIn  = document.getElementById( 'vlt_mb_video_url' );
			var testLink = document.getElementById( 'vlt-mb-test-link' );

			if ( videoIn && testLink ) {
				videoIn.addEventListener( 'input', function () {
					var u = videoIn.value.trim();
					testLink.href = u || '#';
					testLink.classList.toggle( 'is-hidden', ! u );
				} );
			}

			// ── Audio URL test link ─────────────────────────────────────────────
			var audioIn     = document.getElementById( 'vlt_mb_audio_url' );
			var audioTestLnk = document.getElementById( 'vlt-mb-audio-test-link' );

			if ( audioIn && audioTestLnk ) {
				audioIn.addEventListener( 'input', function () {
					var u = audioIn.value.trim();
					audioTestLnk.href = u || '#';
					audioTestLnk.classList.toggle( 'is-hidden', ! u );
				} );
			}

			// ── Poster — WordPress media picker ──────────────────────────────────
			var posterIn  = document.getElementById( 'vlt_mb_poster_url' );
			var posterWr  = document.getElementById( 'vlt-mb-poster-wrap' );
			var posterIm  = document.getElementById( 'vlt-mb-poster-img' );
			var selectBtn = document.getElementById( 'vlt-mb-poster-select' );
			var removeBtn = document.getElementById( 'vlt-mb-poster-remove' );

			// wp.media is loaded in the footer — check it inside the click handler,
			// not at script-parse time (when the footer has not yet been rendered).
			if ( selectBtn ) {
				var mediaFrame;
				selectBtn.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					if ( typeof wp === 'undefined' || ! wp.media ) {
						return;
					}
					if ( mediaFrame ) {
						mediaFrame.open();
						return;
					}
					mediaFrame = wp.media( {
						title:    selectBtn.dataset.title  || 'Select Image',
						button:   { text: selectBtn.dataset.button || 'Use This Image' },
						multiple: false,
						library:  { type: 'image' },
					} );
					mediaFrame.on( 'select', function () {
						var att = mediaFrame.state().get( 'selection' ).first().toJSON();
						var url = att.url || '';
						posterIn.value = url;
						posterIm.src   = url;
						posterWr.classList.toggle( 'is-hidden', ! url );
						if ( removeBtn ) removeBtn.classList.toggle( 'is-hidden', ! url );
					} );
					mediaFrame.open();
				} );
			}

			if ( removeBtn ) {
				removeBtn.addEventListener( 'click', function () {
					posterIn.value = '';
					posterIm.src   = '';
					posterWr.classList.add( 'is-hidden' );
					removeBtn.classList.add( 'is-hidden' );
				} );
			}

			// ── Shortcode copy button ────────────────────────────────────────────
			var copyBtn = document.getElementById( 'vlt-mb-copy-sc' );
			var scEl    = document.getElementById( 'vlt-mb-shortcode' );

			if ( copyBtn && scEl ) {
				copyBtn.addEventListener( 'click', function () {
					var text     = scEl.textContent || scEl.innerText || '';
					var doLabel  = function () {
						copyBtn.textContent = copyBtn.dataset.copied || 'Copied!';
						setTimeout( function () {
							copyBtn.textContent = copyBtn.dataset.copy || 'Copy';
						}, 2000 );
					};
					if ( navigator.clipboard && navigator.clipboard.writeText ) {
						navigator.clipboard.writeText( text ).then( doLabel );
					} else {
						var ta = document.createElement( 'textarea' );
						ta.value = text;
						ta.style.cssText = 'position:fixed;opacity:0';
						document.body.appendChild( ta );
						ta.select();
						try { document.execCommand( 'copy' ); } catch ( _e ) {}
						document.body.removeChild( ta );
						doLabel();
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
		<div class="vlt-metabox">
		<p>
			<label for="vlt_post_video_key" class="vlt-metabox-label">
				<?php esc_html_e( 'Linked Video', 'video-lead-tracker' ); ?>
			</label>
			<select id="vlt_post_video_key" name="vlt_video_key_select" class="vlt-metabox-select">
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
		<div id="vlt-post-poster-wrap" class="vlt-metabox-preview<?php echo ( $current_key && self::get_video_poster( $current_key, $videos ) ) ? '' : ' is-hidden'; ?>">
			<img id="vlt-post-poster-img"
			     src="<?php echo esc_url( self::get_video_poster( $current_key, $videos ) ); ?>"
			     alt="">
		</div>
		<p class="vlt-metabox-hint">
			<?php esc_html_e( 'Place [vlt_video] in the content to embed the selected video.', 'video-lead-tracker' ); ?>
		</p>
		</div>
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
					img.src = poster;
					wrap.classList.remove( 'is-hidden' );
				} else {
					wrap.classList.add( 'is-hidden' );
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
			$audio_url  = esc_url_raw( $_POST['vlt_audio_url']  ?? '' );

			update_post_meta( $post_id, '_vlt_meta_video_url',  $video_url );
			update_post_meta( $post_id, '_vlt_meta_poster_url', $poster_url );
			update_post_meta( $post_id, '_vlt_meta_audio_url',  $audio_url );

			if ( $video_url ) {
				$slug = $post->post_name ?: sanitize_title( $post->post_title );
				if ( $slug ) {
					self::upsert_video_from_cpt( $post_id, $slug, $post->post_title, $video_url, $poster_url, $audio_url );
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

	private static function upsert_video_from_cpt( $post_id, $slug, $title, $video_url, $poster_url, $audio_url = '' ) {
		$now      = current_time( 'mysql' );
		$existing = VLT_DB::get_video_by_key( $slug );

		if ( $existing ) {
			VLT_DB::update_video( (int) $existing->id, [
				'title'      => $title,
				'video_url'  => $video_url,
				'poster_url' => $poster_url,
				'audio_url'  => $audio_url,
				'is_active'  => 1,
				'updated_at' => $now,
			] );
		} else {
			VLT_DB::create_video( [
				'video_key'  => $slug,
				'title'      => $title,
				'video_url'  => $video_url,
				'poster_url' => $poster_url,
				'audio_url'  => $audio_url,
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
		if ( ! is_wp_error( $data ) ) {
			$triggers = self::collect_sms_triggers( $data );
			if ( is_wp_error( $triggers ) ) {
				$data = $triggers;
			}
		}
		if ( is_wp_error( $data ) ) {
			// The form re-renders in this same request, so the message is shown there directly.
			self::$form_error = $data->get_error_message();
			return;
		}

		$video_id = absint( $_POST['video_id'] ?? 0 );
		$now      = current_time( 'mysql' );

		if ( $video_id ) {
			$data['updated_at'] = $now;
			VLT_DB::update_video( $video_id, $data );
			VLT_DB::save_sms_triggers( $video_id, $triggers );
			wp_redirect( add_query_arg( [ 'page' => 'vlt-videos', 'updated' => 1 ], admin_url( 'admin.php' ) ) );
		} else {
			$data['created_at'] = $now;
			$data['updated_at'] = $now;
			$video_id = VLT_DB::create_video( $data );
			if ( $video_id ) {
				VLT_DB::save_sms_triggers( $video_id, $triggers );
			}
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
			'audio_url'          => esc_url_raw( $_POST['audio_url']                  ?? '' ),
			'duration_seconds'   => absint( $_POST['duration_seconds']                ?? 0 ),
			'form_title'         => sanitize_text_field( $_POST['form_title']         ?? '' ),
			'name_label'         => sanitize_text_field( $_POST['name_label']         ?? '' ),
			'mobile_label'       => sanitize_text_field( $_POST['mobile_label']       ?? '' ),
			'submit_button_text' => sanitize_text_field( $_POST['submit_button_text'] ?? '' ),
			'success_message'    => sanitize_textarea_field( $_POST['success_message'] ?? '' ),
			'enable_otp'          => ! empty( $_POST['enable_otp'] ) ? 1 : 0,
			'enable_sms_triggers' => ! empty( $_POST['enable_sms_triggers'] ) ? 1 : 0,
			'is_active'           => ! empty( $_POST['is_active'] )  ? 1 : 0,
		];
	}

	/**
	 * Validate the posted SMS trigger rows. Rows are kept even when the feature is
	 * switched off, so turning it back on restores them.
	 *
	 * @param array $video Sanitized video fields.
	 * @return array|WP_Error  Rows for VLT_DB::save_sms_triggers().
	 */
	private static function collect_sms_triggers( array $video ) {
		$posted   = self::posted_sms_rows();
		$duration = (int) $video['duration_seconds'];
		$rows     = [];
		$seen     = [];

		foreach ( $posted as $row ) {
			$time    = $row['time'];
			$message = $row['message'];

			if ( '' === $time && '' === $message ) {
				continue;
			}

			$second = self::parse_time( $time );

			// While the feature is off the rows are hidden, so drop incomplete ones instead of
			// blocking the save with an error the admin cannot see.
			if ( ! $video['enable_sms_triggers'] && ( ! $second || '' === $message || isset( $seen[ $second ] ) || ( $duration && $second > $duration ) ) ) {
				continue;
			}

			if ( null === $second || $second <= 0 ) {
				/* translators: %s: value typed by the admin */
				return new WP_Error( 'sms_time', sprintf( __( 'SMS send time "%s" is not valid. Use mm:ss, e.g. 05:30.', 'video-lead-tracker' ), $time ) );
			}
			if ( $duration && $second > $duration ) {
				/* translators: %s: send time as mm:ss */
				return new WP_Error( 'sms_time', sprintf( __( 'SMS send time %s is after the end of the video.', 'video-lead-tracker' ), $time ) );
			}
			if ( isset( $seen[ $second ] ) ) {
				/* translators: %s: send time as mm:ss */
				return new WP_Error( 'sms_time', sprintf( __( 'Two SMS messages use the same send time (%s).', 'video-lead-tracker' ), $time ) );
			}
			if ( '' === $message ) {
				/* translators: %s: send time as mm:ss */
				return new WP_Error( 'sms_message', sprintf( __( 'The SMS at %s has no message text.', 'video-lead-tracker' ), $time ) );
			}

			$seen[ $second ] = true;
			$rows[]          = [
				'id'               => $row['id'],
				'trigger_second'   => $second,
				'message_template' => $message,
			];
		}

		if ( $video['enable_sms_triggers'] && ! $rows ) {
			return new WP_Error( 'sms_empty', __( 'Add at least one send time and message, or turn off SMS during playback.', 'video-lead-tracker' ) );
		}

		return $rows;
	}

	/**
	 * @return array  Sanitized posted rows: [ 'id' => int, 'time' => string, 'message' => string ].
	 */
	private static function posted_sms_rows() {
		$posted = isset( $_POST['sms_triggers'] ) && is_array( $_POST['sms_triggers'] ) ? wp_unslash( $_POST['sms_triggers'] ) : [];
		$rows   = [];
		foreach ( $posted as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rows[] = [
				'id'      => absint( $row['id'] ?? 0 ),
				'time'    => trim( sanitize_text_field( $row['time'] ?? '' ) ),
				'message' => trim( sanitize_textarea_field( $row['message'] ?? '' ) ),
			];
		}
		return $rows;
	}

	/**
	 * Parse "ss", "mm:ss" or "hh:mm:ss" into seconds.
	 *
	 * @return int|null
	 */
	private static function parse_time( $value ) {
		if ( ! preg_match( '/^\d+(:\d{1,2}){0,2}$/', $value ) ) {
			return null;
		}

		$seconds = 0;
		$parts   = array_map( 'intval', explode( ':', $value ) );
		$count   = count( $parts );
		foreach ( $parts as $i => $part ) {
			if ( $i > 0 && $part > 59 ) {
				return null;
			}
			$seconds += $part * ( 60 ** ( $count - 1 - $i ) );
		}
		return $seconds;
	}

	/**
	 * @return string  "mm:ss" (or "h:mm:ss" past an hour).
	 */
	public static function format_time( $seconds ) {
		$seconds = (int) round( (float) $seconds );
		$h       = intdiv( $seconds, 3600 );
		$m       = intdiv( $seconds % 3600, 60 );
		$s       = $seconds % 60;
		return $h ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%02d:%02d', $m, $s );
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

		$notice = '';
		if ( ! empty( $_GET['added'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$notice = 'added';
		} elseif ( ! empty( $_GET['updated'] ) ) { // phpcs:ignore
			$notice = 'updated';
		} elseif ( ! empty( $_GET['deleted'] ) ) { // phpcs:ignore
			$notice = 'deleted';
		}

		ob_start();
		?>
		<a class="vlt-btn vlt-btn--primary" href="<?php echo esc_url( add_query_arg( 'action', 'add', $base_url ) ); ?>">
			<?php esc_html_e( 'Add New', 'video-lead-tracker' ); ?>
		</a>
		<?php
		$actions = ob_get_clean();

		VLT_Admin_UI::open( [
			'page'         => 'vlt-videos',
			'title'        => __( 'Videos', 'video-lead-tracker' ),
			'subtitle'     => __( 'Manage gated videos, shortcodes, and lead forms.', 'video-lead-tracker' ),
			'actions_html' => $actions,
		] );
		VLT_Admin_UI::render( 'pages/videos-list', [
			'videos'   => $videos,
			'base_url' => $base_url,
			'error'    => $error,
			'notice'   => $notice,
		] );
		VLT_Admin_UI::close();
	}

	/**
	 * Public accessor for Views.
	 *
	 * @param string $video_key
	 * @return WP_Post|null
	 */
	public static function get_linked_post_public( $video_key ) {
		return self::get_linked_post( $video_key );
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
			'audio_url'          => $is_edit ? ( $video->audio_url  ?? '' ) : '',
			'duration_seconds'   => $is_edit ? $video->duration_seconds    : '',
			'form_title'         => $is_edit ? $video->form_title          : __( 'Watch the Free Training', 'video-lead-tracker' ),
			'name_label'         => $is_edit ? $video->name_label          : __( 'Full Name', 'video-lead-tracker' ),
			'mobile_label'       => $is_edit ? $video->mobile_label        : __( 'Mobile Number', 'video-lead-tracker' ),
			'submit_button_text' => $is_edit ? $video->submit_button_text  : __( 'Watch Now', 'video-lead-tracker' ),
			'success_message'    => $is_edit ? $video->success_message     : __( 'Welcome! Your video is ready.', 'video-lead-tracker' ),
			'enable_otp'          => $is_edit ? (bool) $video->enable_otp  : false,
			'enable_sms_triggers' => $is_edit ? (bool) ( $video->enable_sms_triggers ?? false ) : false,
			'is_active'           => $is_edit ? (bool) $video->is_active    : true,
		];

		$sms_stats = [];
		$sms_rows  = [];
		if ( $is_edit ) {
			$sms_stats = VLT_DB::get_sms_trigger_stats( (int) $video->id );
			foreach ( VLT_DB::get_sms_triggers( (int) $video->id ) as $t ) {
				$sms_rows[] = [
					'id'      => (int) $t->id,
					'time'    => self::format_time( $t->trigger_second ),
					'message' => $t->message_template,
				];
			}
		}

		// After a failed save, keep what the admin typed instead of the stored values.
		if ( self::$form_error ) {
			foreach ( $f as $key => $value ) {
				if ( is_bool( $value ) ) {
					$f[ $key ] = ! empty( $_POST[ $key ] );
				} elseif ( isset( $_POST[ $key ] ) && ( 'video_key' !== $key || ! $is_edit ) ) {
					$f[ $key ] = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
				}
			}
			$sms_rows = self::posted_sms_rows();
		}

		ob_start();
		?>
		<a class="vlt-btn vlt-btn--ghost" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( '← Back to Videos', 'video-lead-tracker' ); ?></a>
		<?php
		$actions = ob_get_clean();

		VLT_Admin_UI::open( [
			'page'         => 'vlt-videos',
			'title'        => $is_edit ? __( 'Edit Video', 'video-lead-tracker' ) : __( 'Add Video', 'video-lead-tracker' ),
			'subtitle'     => __( 'Media, lead form copy, and playback options.', 'video-lead-tracker' ),
			'actions_html' => $actions,
		] );
		VLT_Admin_UI::render( 'pages/videos-form', [
			'is_edit'        => $is_edit,
			'f'              => $f,
			'video'          => $video,
			'error'          => self::$form_error,
			'sms_rows'       => $sms_rows,
			'sms_stats'      => $sms_stats,
			'global_otp'     => (bool) VLT_Settings::get( 'enable_otp' ),
			'sms_configured' => VLT_SMS_Gateway::is_configured(),
		] );
		VLT_Admin_UI::close();
	}
}
