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
		'tracking_page_id'        => 0,
		'video_key'               => 'main-training-video',
		'video_title'             => '',
		'video_url'               => '',
		'video_duration'          => 0,
		'delete_on_uninstall'     => false,
		// Form
		'form_title'              => 'Watch the Free Training',
		'name_label'              => 'Full Name',
		'mobile_label'            => 'Mobile Number',
		'submit_button_text'      => 'Watch Now',
		'success_message'         => 'Welcome! Your video is ready.',
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
		'otp_api_key'             => '',
		'otp_sender'              => '',
		'otp_template'            => 'Your verification code is: {code}',
		'otp_expiry'              => 120,
		'otp_resend_cooldown'     => 60,
		'otp_max_attempts'        => 3,
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
					'<label><input type="checkbox" id="%s" name="%s" value="1" %s /> %s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( $value, true, false ),
					esc_html( $field['check_label'] ?? __( 'Enable', 'video-lead-tracker' ) )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" min="%s" class="small-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( (string) ( $field['min'] ?? 0 ) )
				);
				break;

			case 'url':
				printf(
					'<input type="url" id="%s" name="%s" value="%s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'password':
				printf(
					'<input type="password" id="%s" name="%s" value="%s" class="regular-text" autocomplete="new-password" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" rows="3" class="large-text">%s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( (string) $value )
				);
				break;

			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
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
				] );
				break;

			default:
				printf(
					'<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
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

		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			add_settings_error( 'vlt_messages', 'vlt_message', __( 'Settings saved.', 'video-lead-tracker' ), 'updated' );
		}
		settings_errors( 'vlt_messages' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Video Lead Tracker — Settings', 'video-lead-tracker' ); ?></h1>

			<nav class="nav-tab-wrapper vlt-tab-nav" data-storage-key="vlt_settings_tab">
				<?php foreach ( $sections as $tab_id => $section ) : ?>
					<button type="button"
					        class="nav-tab"
					        data-tab="vlt-tab-<?php echo esc_attr( $tab_id ); ?>">
						<?php echo esc_html( $section['title'] ); ?>
					</button>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<?php foreach ( $sections as $tab_id => $section ) : ?>
					<div class="vlt-tab-panel" id="vlt-tab-<?php echo esc_attr( $tab_id ); ?>">
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
				<?php endforeach; ?>

				<?php submit_button( __( 'Save Settings', 'video-lead-tracker' ) ); ?>
			</form>
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
						'key'   => 'tracking_page_id',
						'label' => __( 'Tracking Page', 'video-lead-tracker' ),
						'type'  => 'page_select',
						'desc'  => __( 'Page where the [video_lead_tracker] shortcode is placed.', 'video-lead-tracker' ),
					],
					[
						'key'   => 'video_key',
						'label' => __( 'Video Key', 'video-lead-tracker' ),
						'type'  => 'text',
						'desc'  => __( 'Unique slug for the video. Must match the key attribute of the [video_lead_tracker] shortcode.', 'video-lead-tracker' ),
					],
					[
						'key'   => 'video_title',
						'label' => __( 'Video Title', 'video-lead-tracker' ),
						'type'  => 'text',
						'desc'  => __( 'Display name used in admin reports.', 'video-lead-tracker' ),
					],
					[
						'key'   => 'video_url',
						'label' => __( 'Video URL', 'video-lead-tracker' ),
						'type'  => 'url',
						'desc'  => __( 'Direct URL to the MP4 video file.', 'video-lead-tracker' ),
					],
					[
						'key'   => 'video_duration',
						'label' => __( 'Video Duration (seconds)', 'video-lead-tracker' ),
						'type'  => 'number',
						'min'   => 0,
						'desc'  => __( 'Used to calculate unique watch percent. Set to 0 to auto-detect from the player.', 'video-lead-tracker' ),
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

			'form' => [
				'title'  => __( 'Lead Form', 'video-lead-tracker' ),
				'fields' => [
					[
						'key'   => 'form_title',
						'label' => __( 'Form Title', 'video-lead-tracker' ),
						'type'  => 'text',
						'desc'  => '',
					],
					[
						'key'   => 'name_label',
						'label' => __( 'Name Field Label', 'video-lead-tracker' ),
						'type'  => 'text',
						'desc'  => '',
					],
					[
						'key'   => 'mobile_label',
						'label' => __( 'Mobile Field Label', 'video-lead-tracker' ),
						'type'  => 'text',
						'desc'  => '',
					],
					[
						'key'   => 'submit_button_text',
						'label' => __( 'Submit Button Text', 'video-lead-tracker' ),
						'type'  => 'text',
						'desc'  => '',
					],
					[
						'key'   => 'success_message',
						'label' => __( 'Success Message', 'video-lead-tracker' ),
						'type'  => 'textarea',
						'desc'  => __( 'Shown briefly after a successful form submission.', 'video-lead-tracker' ),
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
						'key'   => 'otp_api_key',
						'label' => __( 'API Key', 'video-lead-tracker' ),
						'type'  => 'password',
						'desc'  => __( 'Stored encrypted. Leave blank to keep the existing value when saving.', 'video-lead-tracker' ),
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
