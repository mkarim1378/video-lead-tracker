<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_Settings {

	const OPTION_NAME  = 'vlt_settings';
	const OPTION_GROUP = 'vlt_option_group';
	const PAGE_SLUG    = 'vlt-settings';

	private static $defaults = [
		// General
		'enable_tracking'         => true,
		'delete_on_uninstall'     => false,
		// Tracking
		'min_valid_range_seconds' => 1,
		'heartbeat_interval'      => 10,
		'heatmap_default_bucket'  => 5,
		'track_anonymous'         => true,
		'ip_storage_mode'         => 'hash',
		'store_user_agent'        => false,
		// OTP
		'enable_otp'              => false,
		'otp_provider'            => 'payamito',
		'otp_username'            => '',
		'otp_api_key'             => '',
		'otp_sender'              => '',
		'otp_template'            => 'Your verification code is: {code}',
		'otp_expiry'              => 120,
		'otp_resend_cooldown'     => 60,
		'otp_max_attempts'        => 3,
		// Data Management
		'event_retention_days'    => 0,
		// Export
		'enable_xlsx'             => true,
		'enable_csv_fallback'     => true,
	];

	private static $cache = null;

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	public static function init() {
		add_action( 'admin_init', [ self::class, 'register' ] );
	}

	// -------------------------------------------------------------------------
	// Option read/write
	// -------------------------------------------------------------------------

	public static function get( $key, $default = null ) {
		if ( null === self::$cache ) {
			self::$cache = (array) get_option( self::OPTION_NAME, [] );
		}
		if ( array_key_exists( $key, self::$cache ) ) {
			return self::$cache[ $key ];
		}
		return $default ?? ( self::$defaults[ $key ] ?? null );
	}

	public static function all() {
		if ( null === self::$cache ) {
			self::$cache = (array) get_option( self::OPTION_NAME, [] );
		}
		return array_merge( self::$defaults, self::$cache );
	}

	public static function update( array $values ) {
		$merged      = array_merge( self::all(), $values );
		self::$cache = $merged;
		update_option( self::OPTION_NAME, $merged );
	}

	// -------------------------------------------------------------------------
	// WordPress Settings API registration
	// -------------------------------------------------------------------------

	public static function register() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[ 'sanitize_callback' => [ self::class, 'sanitize' ] ]
		);
		// Fields are rendered manually in render_page() — no add_settings_section/field needed.
	}

	// -------------------------------------------------------------------------
	// Sanitize callback
	// -------------------------------------------------------------------------

	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return self::$defaults;
		}

		$clean    = [];
		$all_keys = self::all_field_configs();

		foreach ( $all_keys as $field ) {
			$key  = $field['key'];
			$type = $field['type'];
			$val  = $input[ $key ] ?? null;

			switch ( $type ) {
				case 'checkbox':
					$clean[ $key ] = ! empty( $val );
					break;
				case 'number':
					$clean[ $key ] = absint( $val );
					break;
				case 'url':
					$clean[ $key ] = esc_url_raw( (string) $val );
					break;
				case 'password':
					$clean[ $key ] = sanitize_text_field( (string) $val );
					break;
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( (string) $val );
					break;
				case 'select':
					$options        = array_keys( $field['options'] ?? [] );
					$clean[ $key ]  = in_array( $val, $options, true ) ? $val : ( self::$defaults[ $key ] ?? '' );
					break;
				case 'page_select':
					$clean[ $key ] = absint( $val );
					break;
				default:
					$clean[ $key ] = sanitize_text_field( (string) $val );
			}
		}

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Field renderer
	// -------------------------------------------------------------------------

	public static function render_field( $field ) {
		$key     = $field['key'];
		$type    = $field['type'];
		$value   = self::get( $key );
		$desc    = $field['desc'] ?? '';
		$name    = self::OPTION_NAME . '[' . $key . ']';
		$id      = 'vlt_' . $key;

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<label class="vlt-check"><input type="checkbox" id="%s" name="%s" value="1" %s /> <span>%s</span></label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( $value, true, false ),
					esc_html( $field['check_label'] ?? __( 'Enable', 'video-lead-tracker' ) )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" min="%s" class="vlt-input vlt-input--sm small-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( (string) ( $field['min'] ?? 0 ) )
				);
				break;

			case 'url':
				printf(
					'<input type="url" id="%s" name="%s" value="%s" class="vlt-input regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'password':
				printf(
					'<input type="password" id="%s" name="%s" value="%s" class="vlt-input regular-text" autocomplete="new-password" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" rows="3" class="vlt-input large-text">%s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( (string) $value )
				);
				break;

			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="vlt-select">';
				foreach ( ( $field['options'] ?? [] ) as $opt_val => $opt_label ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $opt_val ),
						selected( $value, $opt_val, false ),
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;

			case 'page_select':
				wp_dropdown_pages( [
					'name'              => $name,
					'id'                => $id,
					'selected'          => (int) $value,
					'show_option_none'  => __( '— Select a page —', 'video-lead-tracker' ),
					'option_none_value' => '0',
					'class'             => 'vlt-select',
				] );
				break;

			default:
				printf(
					'<input type="text" id="%s" name="%s" value="%s" class="vlt-input regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
		}

		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
	}

	// -------------------------------------------------------------------------
	// Page renderer (tabbed)
	// -------------------------------------------------------------------------

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$sections = self::sections();

		$flash_toast = '';
		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$flash_toast = __( 'Settings saved.', 'video-lead-tracker' );
		}

		VLT_Admin_UI::open( [
			'page'     => 'vlt-settings',
			'title'    => __( 'Settings', 'video-lead-tracker' ),
			'subtitle' => __( 'Configure tracking, OTP, and data tools.', 'video-lead-tracker' ),
		] );
		?>
		<div class="vlt-settings"<?php echo $flash_toast ? ' data-vlt-toast-success="' . esc_attr( $flash_toast ) . '"' : ''; ?>>

			<nav class="vlt-tab-nav vlt-settings-tabs" data-storage-key="vlt_settings_tab" role="tablist">
				<?php foreach ( $sections as $tab_id => $section ) : ?>
					<button type="button"
					        class="vlt-tab-btn"
					        data-tab="vlt-tab-<?php echo esc_attr( $tab_id ); ?>">
						<?php echo esc_html( $section['title'] ); ?>
					</button>
				<?php endforeach; ?>
				<button type="button" class="vlt-tab-btn" data-tab="vlt-tab-data-management">
					<?php esc_html_e( 'Data Management', 'video-lead-tracker' ); ?>
				</button>
				<button type="button" class="vlt-tab-btn" data-tab="vlt-tab-content-type">
					<?php esc_html_e( 'Content Type', 'video-lead-tracker' ); ?>
				</button>
				<button type="button" class="vlt-tab-btn" data-tab="vlt-tab-shortcodes">
					<?php esc_html_e( 'Shortcodes', 'video-lead-tracker' ); ?>
				</button>
			</nav>

			<form method="post" action="options.php" class="vlt-settings-form">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<?php foreach ( $sections as $tab_id => $section ) : ?>
					<div class="vlt-tab-panel vlt-panel" id="vlt-tab-<?php echo esc_attr( $tab_id ); ?>">
						<div class="vlt-panel-body">
							<table class="form-table" role="presentation">
								<?php foreach ( $section['fields'] as $field ) : ?>
									<tr>
										<th scope="row">
											<label for="vlt_<?php echo esc_attr( $field['key'] ); ?>">
												<?php echo esc_html( $field['label'] ); ?>
											</label>
										</th>
										<td><?php self::render_field( $field ); ?></td>
									</tr>
								<?php endforeach; ?>
							</table>
						</div>
					</div>
				<?php endforeach; ?>

				<div id="vlt-main-submit-wrap" class="vlt-form-actions">
					<button type="submit" class="vlt-btn vlt-btn--primary"><?php esc_html_e( 'Save Settings', 'video-lead-tracker' ); ?></button>
				</div>
			</form>

			<div class="vlt-tab-panel vlt-panel" id="vlt-tab-data-management">
				<div class="vlt-panel-body">
					<?php self::render_data_management(); ?>
				</div>
			</div>

			<div class="vlt-tab-panel vlt-panel" id="vlt-tab-content-type">
				<div class="vlt-panel-body">
					<?php VLT_CPT::render_tab(); ?>
				</div>
			</div>

			<div class="vlt-tab-panel vlt-panel" id="vlt-tab-shortcodes">
				<div class="vlt-panel-body">
					<?php self::render_shortcodes_tab(); ?>
				</div>
			</div>

		</div>
		<?php
		VLT_Admin_UI::close();
	}

	private static function render_data_management() {
		$videos = VLT_DB::get_all_videos();
		?>
		<div class="vlt-dm-grid">

			<!-- Video Analytics Reset -->
			<div class="vlt-reset-panel">
				<h3><?php esc_html_e( 'Reset Video Analytics', 'video-lead-tracker' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Clears all watch ranges, heatmap data, and viewer summaries for the selected video. The video registry entry is not deleted.', 'video-lead-tracker' ); ?></p>
				<p>
					<select class="vlt-select vlt-reset-id-input" name="video_id">
						<option value=""><?php esc_html_e( '— Select a video —', 'video-lead-tracker' ); ?></option>
						<?php foreach ( $videos as $v ) : ?>
							<option value="<?php echo esc_attr( $v->id ); ?>"><?php echo esc_html( $v->title ?: $v->video_key ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p><label class="vlt-check">
					<input type="checkbox" class="vlt-reset-confirm-cb">
					<span><?php esc_html_e( 'I understand this action is irreversible.', 'video-lead-tracker' ); ?></span>
				</label></p>
				<button type="button" class="vlt-btn vlt-btn--secondary vlt-reset-btn" data-scope="video">
					<?php esc_html_e( 'Reset Video Analytics', 'video-lead-tracker' ); ?>
				</button>
			</div>

			<!-- Lead Reset -->
			<div class="vlt-reset-panel">
				<h3><?php esc_html_e( 'Delete Lead &amp; Data', 'video-lead-tracker' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Permanently deletes a lead record and all associated sessions, watch data, and page visits. Find the Lead ID on the Leads page.', 'video-lead-tracker' ); ?></p>
				<p>
					<input type="number" class="vlt-input vlt-reset-id-input" name="lead_id" min="1"
					       placeholder="<?php esc_attr_e( 'Lead ID', 'video-lead-tracker' ); ?>">
					<span class="vlt-dm-preview" data-lookup-type="lead"></span>
				</p>
				<p><label class="vlt-check">
					<input type="checkbox" class="vlt-reset-confirm-cb">
					<span><?php esc_html_e( 'I understand this action is irreversible.', 'video-lead-tracker' ); ?></span>
				</label></p>
				<button type="button" class="vlt-btn vlt-btn--secondary vlt-reset-btn" data-scope="lead">
					<?php esc_html_e( 'Delete Lead', 'video-lead-tracker' ); ?>
				</button>
			</div>

			<!-- Page Analytics Reset -->
			<div class="vlt-reset-panel">
				<h3><?php esc_html_e( 'Reset Page Analytics', 'video-lead-tracker' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Clears all page visit events for the given WordPress page ID.', 'video-lead-tracker' ); ?></p>
				<p>
					<input type="number" class="vlt-input vlt-reset-id-input" name="page_id" min="1"
					       placeholder="<?php esc_attr_e( 'Page ID', 'video-lead-tracker' ); ?>">
					<span class="vlt-dm-preview" data-lookup-type="page"></span>
				</p>
				<p><label class="vlt-check">
					<input type="checkbox" class="vlt-reset-confirm-cb">
					<span><?php esc_html_e( 'I understand this action is irreversible.', 'video-lead-tracker' ); ?></span>
				</label></p>
				<button type="button" class="vlt-btn vlt-btn--secondary vlt-reset-btn" data-scope="page">
					<?php esc_html_e( 'Reset Page Analytics', 'video-lead-tracker' ); ?>
				</button>
			</div>

			<!-- Full Reset -->
			<div class="vlt-reset-panel vlt-reset-panel--danger">
				<h3><?php esc_html_e( 'Full Analytics Reset', 'video-lead-tracker' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Permanently deletes ALL analytics data: leads, sessions, watch ranges, heatmap, page visits, video events, and logs. Plugin settings and video registry are preserved.', 'video-lead-tracker' ); ?></p>
				<p>
					<input type="text" class="vlt-input vlt-reset-typed-confirm"
					       placeholder="<?php esc_attr_e( 'Type DELETE to confirm', 'video-lead-tracker' ); ?>">
				</p>
				<button type="button" class="vlt-btn vlt-btn--danger vlt-reset-btn" data-scope="full" data-confirm-text="DELETE">
					<?php esc_html_e( 'Reset All Analytics', 'video-lead-tracker' ); ?>
				</button>
			</div>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Shortcodes documentation tab
	// -------------------------------------------------------------------------

	private static function render_shortcodes_tab() {
		$shortcodes = [
			[
				'name'        => 'video_lead_tracker',
				'alias'       => 'vlt_video_lead_gate',
				'description' => __( 'Renders the gated video player. Shows a lead capture form before the video for unknown visitors, and the video directly for known leads.', 'video-lead-tracker' ),
				'attributes'  => [
					[ 'key' => 'video_key', 'required' => false, 'desc' => __( 'Video key from the Videos registry. Falls back to the first registered video.', 'video-lead-tracker' ) ],
					[ 'key' => 'src',       'required' => false, 'desc' => __( 'Direct URL to the video file. Overrides the registry.', 'video-lead-tracker' ) ],
					[ 'key' => 'poster',    'required' => false, 'desc' => __( 'Poster image URL. Overrides the registry.', 'video-lead-tracker' ) ],
				],
				'example'     => '[video_lead_tracker video_key="main-training-video"]',
			],
			[
				'name'        => 'vlt_video',
				'description' => __( 'Embeds the gated video player. Resolves video from the CPT post meta if no key is provided.', 'video-lead-tracker' ),
				'attributes'  => [
					[ 'key' => 'key', 'required' => false, 'desc' => __( 'Video key. If omitted, reads from the post\'s linked video meta.', 'video-lead-tracker' ) ],
				],
				'example'     => '[vlt_video key="main-training-video"]',
			],
			[
				'name'        => 'vlt_audio_player',
				'description' => __( 'Renders an audio player with waveform visualization, play/pause, seek, speed toggle, and optional download.', 'video-lead-tracker' ),
				'attributes'  => [
					[ 'key' => 'src',      'required' => false, 'desc' => __( 'Direct URL to the audio file (MP3).', 'video-lead-tracker' ) ],
					[ 'key' => 'key',      'required' => false, 'desc' => __( 'Video key. Resolves audio_url from the video registry.', 'video-lead-tracker' ) ],
					[ 'key' => 'label',    'required' => false, 'desc' => __( 'Label text above the waveform. Default: "Audio version of this video".', 'video-lead-tracker' ) ],
					[ 'key' => 'tag',      'required' => false, 'desc' => __( 'Tag badge text. Default: "Listen while you work".', 'video-lead-tracker' ) ],
					[ 'key' => 'download', 'required' => false, 'desc' => __( 'Show download button. "1" (default) or "0".', 'video-lead-tracker' ) ],
				],
				'example'     => '[vlt_audio_player key="main-training-video"]',
			],
			[
				'name'        => 'vlt_quick',
				'description' => __( 'Quick answer / featured snippet box with a blue left border and lightning icon.', 'video-lead-tracker' ),
				'attributes'  => [
					[ 'key' => 'icon', 'required' => false, 'desc' => __( 'Icon emoji. Default: ⚡.', 'video-lead-tracker' ) ],
				],
				'example'     => "[vlt_quick]\nپاسخ سریع اینجا قرار می‌گیرد.\n[/vlt_quick]",
			],
			[
				'name'        => 'vlt_midcta',
				'description' => __( 'Mid-page call-to-action block with dark gradient background, icon, text, and button.', 'video-lead-tracker' ),
				'attributes'  => [
					[ 'key' => 'icon',        'required' => false, 'desc' => __( 'Icon emoji. Default: 🚗.', 'video-lead-tracker' ) ],
					[ 'key' => 'title',       'required' => false, 'desc' => __( 'Bold title text.', 'video-lead-tracker' ) ],
					[ 'key' => 'text',        'required' => false, 'desc' => __( 'Description text (lighter color).', 'video-lead-tracker' ) ],
					[ 'key' => 'button_url',  'required' => false, 'desc' => __( 'CTA button URL.', 'video-lead-tracker' ) ],
					[ 'key' => 'button_text', 'required' => false, 'desc' => __( 'CTA button label.', 'video-lead-tracker' ) ],
				],
				'example'     => '[vlt_midcta icon="🚗" title="می‌خوای مسلط بشی؟" text="این ویدیو فقط نمونه‌ست" button_url="#product" button_text="مشاهده دوره ←"]',
			],
			[
				'name'        => 'vlt_cta',
				'description' => __( 'Full-width product CTA section with gradient background, badge, checkmark points, and button.', 'video-lead-tracker' ),
				'attributes'  => [
					[ 'key' => 'badge',       'required' => false, 'desc' => __( 'Small badge text above the title.', 'video-lead-tracker' ) ],
					[ 'key' => 'title',       'required' => false, 'desc' => __( 'Main heading.', 'video-lead-tracker' ) ],
					[ 'key' => 'text',        'required' => false, 'desc' => __( 'Description paragraph.', 'video-lead-tracker' ) ],
					[ 'key' => 'points',      'required' => false, 'desc' => __( 'Checkmark bullet points, separated by pipe (|).', 'video-lead-tracker' ) ],
					[ 'key' => 'button_url',  'required' => false, 'desc' => __( 'CTA button URL.', 'video-lead-tracker' ) ],
					[ 'key' => 'button_text', 'required' => false, 'desc' => __( 'CTA button label.', 'video-lead-tracker' ) ],
				],
				'example'     => '[vlt_cta badge="🚗 ویژه خودروهای چینی" title="روی هر خودروی چینی‌ای مسلط شو" text="توضیحات دوره" points="پارامترخوانی|کالیبراسیون سنسورها|دمونتاژ کامل" button_url="#product" button_text="ثبت‌نام در دوره"]',
			],
			[
				'name'        => 'vlt_callout',
				'description' => __( 'Warning / caution box with red tint background.', 'video-lead-tracker' ),
				'attributes'  => [
					[ 'key' => 'icon', 'required' => false, 'desc' => __( 'Icon emoji. Default: ⚠️.', 'video-lead-tracker' ) ],
				],
				'example'     => "[vlt_callout]\nمتن هشدار اینجا قرار می‌گیرد.\n[/vlt_callout]",
			],
			[
				'name'        => 'vlt_note',
				'description' => __( 'Tip / note box with green background.', 'video-lead-tracker' ),
				'attributes'  => [
					[ 'key' => 'icon', 'required' => false, 'desc' => __( 'Icon emoji. Default: ✅.', 'video-lead-tracker' ) ],
				],
				'example'     => "[vlt_note]\nنکته طلایی اینجا قرار می‌گیرد.\n[/vlt_note]",
			],
		];
		?>
		<h2><?php esc_html_e( 'Available Shortcodes', 'video-lead-tracker' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Use these shortcodes in any post, page, or Elementor template to embed video gates, audio players, and styled content blocks.', 'video-lead-tracker' ); ?></p>

		<div class="vlt-sc-list">
		<?php foreach ( $shortcodes as $sc ) : ?>
			<div class="vlt-sc-card">
				<h3>
					[<?php echo esc_html( $sc['name'] ); ?>]
					<?php if ( ! empty( $sc['alias'] ) ) : ?>
						<span class="vlt-sc-alias">(alias: [<?php echo esc_html( $sc['alias'] ); ?>])</span>
					<?php endif; ?>
				</h3>
				<p class="vlt-sc-desc"><?php echo esc_html( $sc['description'] ); ?></p>

				<?php if ( ! empty( $sc['attributes'] ) ) : ?>
				<table>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Attribute', 'video-lead-tracker' ); ?></th>
							<th><?php esc_html_e( 'Description', 'video-lead-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $sc['attributes'] as $attr ) : ?>
						<tr>
							<td><code><?php echo esc_html( $attr['key'] ); ?></code></td>
							<td><?php echo esc_html( $attr['desc'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>

				<?php if ( ! empty( $sc['example'] ) ) : ?>
					<div class="vlt-sc-example"><?php echo esc_html( $sc['example'] ); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Field configuration
	// -------------------------------------------------------------------------

	private static function sections() {
		return [
			'general'  => [
				'title'  => __( 'General', 'video-lead-tracker' ),
				'fields' => [
					[
						'key'         => 'enable_tracking',
						'label'       => __( 'Enable Tracking', 'video-lead-tracker' ),
						'type'        => 'checkbox',
						'check_label' => __( 'Track visits, leads, and video events', 'video-lead-tracker' ),
						'desc'        => __( 'Master switch. Disable to stop all data collection without removing data.', 'video-lead-tracker' ),
					],
					[
						'key'         => 'delete_on_uninstall',
						'label'       => __( 'Delete Data on Uninstall', 'video-lead-tracker' ),
						'type'        => 'checkbox',
						'check_label' => __( 'Permanently delete all plugin tables and settings when the plugin is removed', 'video-lead-tracker' ),
						'desc'        => __( 'Warning: this is irreversible. Leave unchecked to preserve data across reinstalls.', 'video-lead-tracker' ),
					],
				],
			],

			'tracking' => [
				'title'  => __( 'Tracking', 'video-lead-tracker' ),
				'fields' => [
					[
						'key'   => 'min_valid_range_seconds',
						'label' => __( 'Minimum Valid Range (seconds)', 'video-lead-tracker' ),
						'type'  => 'number',
						'min'   => 1,
						'desc'  => __( 'Watch segments shorter than this value are ignored. Recommended: 1.', 'video-lead-tracker' ),
					],
					[
						'key'   => 'heartbeat_interval',
						'label' => __( 'Heartbeat Interval (seconds)', 'video-lead-tracker' ),
						'type'  => 'number',
						'min'   => 5,
						'desc'  => __( 'How often the JS tracker commits the current watch range. Recommended: 10.', 'video-lead-tracker' ),
					],
					[
						'key'     => 'heatmap_default_bucket',
						'label'   => __( 'Heatmap Default Bucket Size', 'video-lead-tracker' ),
						'type'    => 'select',
						'options' => [
							'1'  => __( '1 second', 'video-lead-tracker' ),
							'5'  => __( '5 seconds', 'video-lead-tracker' ),
							'10' => __( '10 seconds', 'video-lead-tracker' ),
							'30' => __( '30 seconds', 'video-lead-tracker' ),
						],
						'desc'    => __( 'Default grouping size for the heatmap chart.', 'video-lead-tracker' ),
					],
					[
						'key'         => 'track_anonymous',
						'label'       => __( 'Track Before Form Submit', 'video-lead-tracker' ),
						'type'        => 'checkbox',
						'check_label' => __( 'Track anonymous page and video events before the lead submits the form', 'video-lead-tracker' ),
						'desc'        => __( 'Anonymous events are attached to the lead record once the form is submitted.', 'video-lead-tracker' ),
					],
					[
						'key'     => 'ip_storage_mode',
						'label'   => __( 'IP Address Storage', 'video-lead-tracker' ),
						'type'    => 'select',
						'options' => [
							'disabled' => __( 'Do not store', 'video-lead-tracker' ),
							'hash'     => __( 'Store as SHA-256 hash (recommended)', 'video-lead-tracker' ),
							'raw'      => __( 'Store raw IP', 'video-lead-tracker' ),
						],
						'desc'    => '',
					],
					[
						'key'         => 'store_user_agent',
						'label'       => __( 'Store User Agent', 'video-lead-tracker' ),
						'type'        => 'checkbox',
						'check_label' => __( 'Save the full user agent string in session records', 'video-lead-tracker' ),
						'desc'        => '',
					],
					[
						'key'   => 'event_retention_days',
						'label' => __( 'Event Retention (days)', 'video-lead-tracker' ),
						'type'  => 'number',
						'min'   => 0,
						'desc'  => __( 'Auto-delete raw video events older than this many days. Set to 0 to disable.', 'video-lead-tracker' ),
					],
				],
			],

			'otp' => [
				'title'  => __( 'OTP (optional)', 'video-lead-tracker' ),
				'fields' => [
					[
						'key'         => 'enable_otp',
						'label'       => __( 'Enable OTP Verification', 'video-lead-tracker' ),
						'type'        => 'checkbox',
						'check_label' => __( 'Require SMS OTP before showing the video', 'video-lead-tracker' ),
						'desc'        => __( 'When enabled, the user must verify their mobile number via OTP before accessing the video.', 'video-lead-tracker' ),
					],
					[
						'key'     => 'otp_provider',
						'label'   => __( 'SMS Provider', 'video-lead-tracker' ),
						'type'    => 'select',
						'options' => [ 'payamito' => __( 'Payamito (پیامیتو)', 'video-lead-tracker' ) ],
						'desc'    => __( 'Select your SMS provider for OTP delivery.', 'video-lead-tracker' ),
					],
					[
						'key'   => 'otp_username',
						'label' => __( 'Account Username', 'video-lead-tracker' ),
						'type'  => 'text',
						'desc'  => __( 'Your Payamito panel username (نام کاربری).', 'video-lead-tracker' ),
					],
					[
						'key'   => 'otp_api_key',
						'label' => __( 'API Key (password)', 'video-lead-tracker' ),
						'type'  => 'password',
						'desc'  => __( 'ApiKey from Payamito developer settings (not your login password). Leave blank to keep existing value.', 'video-lead-tracker' ),
					],
					[
						'key'   => 'otp_sender',
						'label' => __( 'Sender Number / Line', 'video-lead-tracker' ),
						'type'  => 'text',
						'desc'  => '',
					],
					[
						'key'   => 'otp_template',
						'label' => __( 'Message Template', 'video-lead-tracker' ),
						'type'  => 'textarea',
						'desc'  => __( 'Use {code} as the placeholder for the OTP code.', 'video-lead-tracker' ),
					],
					[
						'key'   => 'otp_expiry',
						'label' => __( 'OTP Expiry (seconds)', 'video-lead-tracker' ),
						'type'  => 'number',
						'min'   => 30,
						'desc'  => __( 'How long the OTP remains valid. Recommended: 120.', 'video-lead-tracker' ),
					],
					[
						'key'   => 'otp_resend_cooldown',
						'label' => __( 'Resend Cooldown (seconds)', 'video-lead-tracker' ),
						'type'  => 'number',
						'min'   => 10,
						'desc'  => __( 'Minimum seconds between resend requests per mobile.', 'video-lead-tracker' ),
					],
					[
						'key'   => 'otp_max_attempts',
						'label' => __( 'Max Verification Attempts', 'video-lead-tracker' ),
						'type'  => 'number',
						'min'   => 1,
						'desc'  => __( 'Max wrong OTP entries before the code is invalidated.', 'video-lead-tracker' ),
					],
				],
			],

			'export' => [
				'title'  => __( 'Export', 'video-lead-tracker' ),
				'fields' => [
					[
						'key'         => 'enable_xlsx',
						'label'       => __( 'Enable XLSX Export', 'video-lead-tracker' ),
						'type'        => 'checkbox',
						'check_label' => __( 'Generate Excel-compatible XLSX files', 'video-lead-tracker' ),
						'desc'        => __( 'Requires a PHP XLSX library. Implemented in Phase 18.', 'video-lead-tracker' ),
					],
					[
						'key'         => 'enable_csv_fallback',
						'label'       => __( 'Enable CSV Fallback', 'video-lead-tracker' ),
						'type'        => 'checkbox',
						'check_label' => __( 'Always provide a CSV download option (UTF-8 with BOM)', 'video-lead-tracker' ),
						'desc'        => '',
					],
				],
			],
		];
	}

	private static function all_field_configs() {
		$fields = [];
		foreach ( self::sections() as $section ) {
			foreach ( $section['fields'] as $field ) {
				$fields[] = $field;
			}
		}
		return $fields;
	}
}
