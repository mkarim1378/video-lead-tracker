<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VLT_CPT {

	const OPTION_NAME  = 'vlt_cpt_config';
	const OPTION_GROUP = 'vlt_cpt_group';
	const POST_TYPE    = 'vlt_video';

	private static $defaults = [
		'enabled'      => false,
		'singular'     => '',
		'plural'       => '',
		'slug'         => '',
		'menu_icon'    => 'dashicons-video-alt3',
		'description'  => '',
		'supports'     => [
			'editor'    => true,
			'thumbnail' => true,
			'excerpt'   => false,
			'comments'  => false,
		],
		'has_archive'  => true,
		'archive_slug' => '',
	];

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	public static function init() {
		add_action( 'init',         [ self::class, 'register_post_type' ] );
		add_action( 'admin_init',   [ self::class, 'register_setting' ] );
		add_action( 'update_option_' . self::OPTION_NAME, [ self::class, 'on_config_updated' ], 10, 0 );
	}

	// -------------------------------------------------------------------------
	// Settings API
	// -------------------------------------------------------------------------

	public static function register_setting() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[ 'sanitize_callback' => [ self::class, 'sanitize' ] ]
		);
	}

	public static function on_config_updated() {
		flush_rewrite_rules();
	}

	// -------------------------------------------------------------------------
	// Config helpers
	// -------------------------------------------------------------------------

	public static function get_config() {
		$stored  = (array) get_option( self::OPTION_NAME, [] );
		$config  = array_merge( self::$defaults, $stored );
		// Merge nested supports array.
		$config['supports'] = array_merge(
			self::$defaults['supports'],
			(array) ( $stored['supports'] ?? [] )
		);
		return $config;
	}

	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return self::$defaults;
		}

		$slug = sanitize_title( $input['slug'] ?? '' );

		$clean = [
			'enabled'      => ! empty( $input['enabled'] ),
			'singular'     => sanitize_text_field( $input['singular']    ?? '' ),
			'plural'       => sanitize_text_field( $input['plural']      ?? '' ),
			'slug'         => $slug ?: 'video-lessons',
			'menu_icon'    => sanitize_text_field( $input['menu_icon']   ?? 'dashicons-video-alt3' ) ?: 'dashicons-video-alt3',
			'description'  => sanitize_textarea_field( $input['description'] ?? '' ),
			'has_archive'  => ! empty( $input['has_archive'] ),
			'archive_slug' => sanitize_title( $input['archive_slug'] ?? '' ),
			'supports'     => [
				'editor'    => ! empty( $input['supports']['editor'] ),
				'thumbnail' => ! empty( $input['supports']['thumbnail'] ),
				'excerpt'   => ! empty( $input['supports']['excerpt'] ),
				'comments'  => ! empty( $input['supports']['comments'] ),
			],
		];

		return $clean;
	}

	// -------------------------------------------------------------------------
	// CPT registration
	// -------------------------------------------------------------------------

	public static function register_post_type() {
		$cfg = self::get_config();
		if ( empty( $cfg['enabled'] ) ) {
			return;
		}

		$singular = $cfg['singular'] ?: __( 'Video Lesson', 'video-lead-tracker' );
		$plural   = $cfg['plural']   ?: __( 'Video Lessons', 'video-lead-tracker' );
		$slug     = $cfg['slug']     ?: 'video-lessons';

		$supports = [ 'title' ];
		foreach ( [ 'editor', 'thumbnail', 'excerpt', 'comments' ] as $feature ) {
			if ( ! empty( $cfg['supports'][ $feature ] ) ) {
				$supports[] = $feature;
			}
		}

		$archive = false;
		if ( $cfg['has_archive'] ) {
			$archive = $cfg['archive_slug'] ?: $slug;
		}

		register_post_type( self::POST_TYPE, [
			'labels'            => [
				'name'               => $plural,
				'singular_name'      => $singular,
				/* translators: post type label */
				'add_new_item'       => sprintf( __( 'Add New %s', 'video-lead-tracker' ), $singular ),
				'edit_item'          => sprintf( __( 'Edit %s', 'video-lead-tracker' ), $singular ),
				'new_item'           => sprintf( __( 'New %s', 'video-lead-tracker' ), $singular ),
				'view_item'          => sprintf( __( 'View %s', 'video-lead-tracker' ), $singular ),
				'search_items'       => sprintf( __( 'Search %s', 'video-lead-tracker' ), $plural ),
				'not_found'          => sprintf( __( 'No %s found.', 'video-lead-tracker' ), strtolower( $plural ) ),
				'not_found_in_trash' => sprintf( __( 'No %s found in Trash.', 'video-lead-tracker' ), strtolower( $plural ) ),
				'all_items'          => sprintf( __( 'All %s', 'video-lead-tracker' ), $plural ),
				'menu_name'          => $plural,
			],
			'public'            => true,
			'show_in_rest'      => true,   // required for Elementor Theme Builder
			'show_in_nav_menus' => true,
			'has_archive'       => $archive,
			'rewrite'           => [ 'slug' => $slug, 'with_front' => true ],
			'menu_icon'         => $cfg['menu_icon'],
			'supports'          => $supports,
			'description'       => $cfg['description'],
		] );
	}

	// -------------------------------------------------------------------------
	// Settings tab renderer
	// -------------------------------------------------------------------------

	public static function render_tab() {
		$cfg = self::get_config();
		?>
		<form method="post" action="options.php" id="vlt-cpt-form">
			<?php settings_fields( self::OPTION_GROUP ); ?>

			<table class="form-table" role="presentation">

				<tr>
					<th scope="row"><?php esc_html_e( 'Custom Post Type', 'video-lead-tracker' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="vlt-cpt-enabled"
							       name="vlt_cpt_config[enabled]" value="1"
							       <?php checked( $cfg['enabled'] ); ?>>
							<?php esc_html_e( 'Enable custom post type on this site', 'video-lead-tracker' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Disabling this hides the post type from WordPress without deleting existing posts.', 'video-lead-tracker' ); ?>
						</p>
					</td>
				</tr>

				<tr class="vlt-cpt-field">
					<th scope="row">
						<label for="vlt-cpt-singular"><?php esc_html_e( 'Singular Name', 'video-lead-tracker' ); ?></label>
					</th>
					<td>
						<input type="text" id="vlt-cpt-singular"
						       name="vlt_cpt_config[singular]"
						       value="<?php echo esc_attr( $cfg['singular'] ); ?>"
						       class="regular-text"
						       placeholder="<?php esc_attr_e( 'e.g. Video Lesson', 'video-lead-tracker' ); ?>">
					</td>
				</tr>

				<tr class="vlt-cpt-field">
					<th scope="row">
						<label for="vlt-cpt-plural"><?php esc_html_e( 'Plural Name', 'video-lead-tracker' ); ?></label>
					</th>
					<td>
						<input type="text" id="vlt-cpt-plural"
						       name="vlt_cpt_config[plural]"
						       value="<?php echo esc_attr( $cfg['plural'] ); ?>"
						       class="regular-text"
						       placeholder="<?php esc_attr_e( 'e.g. Video Lessons', 'video-lead-tracker' ); ?>">
					</td>
				</tr>

				<tr class="vlt-cpt-field">
					<th scope="row">
						<label for="vlt-cpt-slug"><?php esc_html_e( 'URL Slug', 'video-lead-tracker' ); ?></label>
					</th>
					<td>
						<input type="text" id="vlt-cpt-slug"
						       name="vlt_cpt_config[slug]"
						       value="<?php echo esc_attr( $cfg['slug'] ); ?>"
						       class="regular-text"
						       placeholder="video-lessons"
						       data-original="<?php echo esc_attr( $cfg['slug'] ); ?>">
						<p class="description">
							<?php esc_html_e( 'Used in post URLs. Changing this after posts are published will break existing links.', 'video-lead-tracker' ); ?>
						</p>
					</td>
				</tr>

				<tr class="vlt-cpt-field">
					<th scope="row">
						<label for="vlt-cpt-icon"><?php esc_html_e( 'Menu Icon', 'video-lead-tracker' ); ?></label>
					</th>
					<td>
						<input type="text" id="vlt-cpt-icon"
						       name="vlt_cpt_config[menu_icon]"
						       value="<?php echo esc_attr( $cfg['menu_icon'] ); ?>"
						       class="regular-text"
						       placeholder="dashicons-video-alt3">
						<p class="description">
							<?php esc_html_e( 'A dashicons class name.', 'video-lead-tracker' ); ?>
							<a href="https://developer.wordpress.org/resource/dashicons/" target="_blank" rel="noopener">
								<?php esc_html_e( 'Browse icons ↗', 'video-lead-tracker' ); ?>
							</a>
						</p>
					</td>
				</tr>

				<tr class="vlt-cpt-field">
					<th scope="row"><?php esc_html_e( 'Supports', 'video-lead-tracker' ); ?></th>
					<td>
						<?php
						$support_opts = [
							'editor'    => __( 'Body Content (Editor)', 'video-lead-tracker' ),
							'thumbnail' => __( 'Featured Image', 'video-lead-tracker' ),
							'excerpt'   => __( 'Excerpt', 'video-lead-tracker' ),
							'comments'  => __( 'Comments', 'video-lead-tracker' ),
						];
						foreach ( $support_opts as $feat => $feat_label ) :
						?>
							<label style="display:inline-block;margin-right:16px;margin-bottom:4px">
								<input type="checkbox"
								       name="vlt_cpt_config[supports][<?php echo esc_attr( $feat ); ?>]"
								       value="1"
								       <?php checked( ! empty( $cfg['supports'][ $feat ] ) ); ?>>
								<?php echo esc_html( $feat_label ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Title is always enabled.', 'video-lead-tracker' ); ?></p>
					</td>
				</tr>

				<tr class="vlt-cpt-field">
					<th scope="row">
						<label for="vlt-cpt-description"><?php esc_html_e( 'Description', 'video-lead-tracker' ); ?></label>
					</th>
					<td>
						<input type="text" id="vlt-cpt-description"
						       name="vlt_cpt_config[description]"
						       value="<?php echo esc_attr( $cfg['description'] ); ?>"
						       class="large-text">
					</td>
				</tr>

				<tr class="vlt-cpt-field">
					<th scope="row"><?php esc_html_e( 'Archive Page', 'video-lead-tracker' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="vlt-cpt-has-archive"
							       name="vlt_cpt_config[has_archive]" value="1"
							       <?php checked( $cfg['has_archive'] ); ?>>
							<?php esc_html_e( 'Enable archive page', 'video-lead-tracker' ); ?>
						</label>
						<div id="vlt-cpt-archive-row" style="margin-top:8px<?php echo $cfg['has_archive'] ? '' : ';display:none'; ?>">
							<input type="text" id="vlt-cpt-archive-slug"
							       name="vlt_cpt_config[archive_slug]"
							       value="<?php echo esc_attr( $cfg['archive_slug'] ); ?>"
							       class="regular-text"
							       placeholder="<?php esc_attr_e( 'Archive URL slug (leave blank to use post slug)', 'video-lead-tracker' ); ?>">
						</div>
					</td>
				</tr>

			</table>

			<?php submit_button( __( 'Save Content Type Settings', 'video-lead-tracker' ) ); ?>
		</form>

		<script>
		( function () {
			var enabled = document.getElementById( 'vlt-cpt-enabled' );
			var fields  = document.querySelectorAll( '.vlt-cpt-field' );
			var slugIn  = document.getElementById( 'vlt-cpt-slug' );
			var hasArc  = document.getElementById( 'vlt-cpt-has-archive' );
			var arcRow  = document.getElementById( 'vlt-cpt-archive-row' );
			var form    = document.getElementById( 'vlt-cpt-form' );

			function toggleFields() {
				var on = enabled && enabled.checked;
				fields.forEach( function ( row ) {
					row.style.opacity = on ? '' : '0.45';
					row.querySelectorAll( 'input, select, textarea' ).forEach( function ( el ) {
						el.disabled = ! on;
					} );
				} );
				if ( enabled ) enabled.disabled = false;
			}

			if ( enabled ) {
				toggleFields();
				enabled.addEventListener( 'change', toggleFields );
			}

			if ( hasArc && arcRow ) {
				hasArc.addEventListener( 'change', function () {
					arcRow.style.display = hasArc.checked ? '' : 'none';
				} );
			}

			if ( form && slugIn ) {
				form.addEventListener( 'submit', function ( e ) {
					var orig = slugIn.getAttribute( 'data-original' );
					if ( orig && orig !== slugIn.value.trim() ) {
						if ( ! window.confirm( '<?php echo esc_js( __( 'The URL slug has changed. Existing post URLs will break unless you update your permalink settings. Continue saving?', 'video-lead-tracker' ) ); ?>' ) ) {
							e.preventDefault();
						}
					}
				} );
			}
		}() );
		</script>
		<?php
	}
}
