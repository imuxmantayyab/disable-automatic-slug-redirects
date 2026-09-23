<?php
/**
 * Settings screen: Settings -> Slug Redirects.
 *
 * @package Disable_Automatic_Slug_Redirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DASR_Settings
 *
 * Registers the admin settings page and handles storing/reading/sanitizing
 * the plugin's options via the Settings API.
 */
class DASR_Settings {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'dasr-slug-redirects';

	/**
	 * Settings group name (used by register_setting()).
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'dasr_settings_group';

	/**
	 * Cached, already-sanitized options.
	 *
	 * @var array|null
	 */
	private $cached_options = null;

	/**
	 * Hook admin menu + settings registration.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_purge_old_slugs' ) );
	}

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	public function get_defaults() {
		return array(
			'enabled'           => 1,
			'apply_to_post'     => 1,
			'apply_to_page'     => 1,
			'apply_to_cpts'     => array(),
			'redirect_children' => 1,
			'disable_canonical' => 0,
		);
	}

	/**
	 * Get the plugin's current options, merged with defaults and sanitized.
	 *
	 * @return array
	 */
	public function get_options() {
		if ( null !== $this->cached_options ) {
			return $this->cached_options;
		}

		$defaults = $this->get_defaults();
		$stored   = get_option( DASR_OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$options = wp_parse_args( $stored, $defaults );

		// Guard against stale/removed post types lingering in storage.
		$options['apply_to_cpts'] = array_values(
			array_intersect(
				array_map( 'sanitize_key', (array) $options['apply_to_cpts'] ),
				array_keys( $this->get_custom_post_types() )
			)
		);

		$this->cached_options = $options;

		return $this->cached_options;
	}

	/**
	 * Whether the plugin's redirect-suppression behaviour is active at all.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$options = $this->get_options();

		return ! empty( $options['enabled'] );
	}

	/**
	 * Whether the "disable core canonical redirects" advanced option is on.
	 *
	 * @return bool
	 */
	public function is_canonical_disabled() {
		$options = $this->get_options();

		return $this->is_enabled() && ! empty( $options['disable_canonical'] );
	}

	/**
	 * Whether automatic child-page redirection when a parent is redirected is enabled.
	 *
	 * @return bool
	 */
	public function is_children_redirection_enabled() {
		$options = $this->get_options();

		return $this->is_enabled() && ! empty( $options['redirect_children'] );
	}

	/**
	 * List of post types (built-in + custom) that the plugin should apply to,
	 * based on the saved settings.
	 *
	 * @return string[]
	 */
	public function get_target_post_types() {
		if ( ! $this->is_enabled() ) {
			return array();
		}

		$options    = $this->get_options();
		$post_types = array();

		if ( ! empty( $options['apply_to_post'] ) ) {
			$post_types[] = 'post';
		}

		if ( ! empty( $options['apply_to_page'] ) ) {
			$post_types[] = 'page';
		}

		if ( ! empty( $options['apply_to_cpts'] ) ) {
			$post_types = array_merge( $post_types, (array) $options['apply_to_cpts'] );
		}

		return array_values( array_unique( $post_types ) );
	}

	/**
	 * Get the list of public custom post types (excluding post/page),
	 * keyed by post type slug with the label as the value.
	 *
	 * @return array<string, string>
	 */
	public function get_custom_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
				'_builtin' => false,
			),
			'objects'
		);

		$list = array();

		foreach ( $post_types as $post_type ) {
			// Attachments technically don't get old-slug redirects, skip them.
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			$list[ $post_type->name ] = $post_type->labels->singular_name;
		}

		return $list;
	}

	/**
	 * Register the settings, sections, and fields via the Settings API.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			DASR_OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => $this->get_defaults(),
			)
		);

		add_settings_section(
			'dasr_main_section',
			__( 'Slug Redirect Behavior', 'disable-automatic-slug-redirects' ),
			array( $this, 'render_main_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'dasr_enabled',
			__( 'Enable / Disable', 'disable-automatic-slug-redirects' ),
			array( $this, 'render_enabled_field' ),
			self::PAGE_SLUG,
			'dasr_main_section'
		);

		add_settings_field(
			'dasr_apply_to',
			__( 'Apply To', 'disable-automatic-slug-redirects' ),
			array( $this, 'render_apply_to_field' ),
			self::PAGE_SLUG,
			'dasr_main_section'
		);

		add_settings_field(
			'dasr_redirect_children',
			__( 'Parent & Child Pages', 'disable-automatic-slug-redirects' ),
			array( $this, 'render_redirect_children_field' ),
			self::PAGE_SLUG,
			'dasr_main_section'
		);

		add_settings_section(
			'dasr_advanced_section',
			__( 'Advanced', 'disable-automatic-slug-redirects' ),
			array( $this, 'render_advanced_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'dasr_disable_canonical',
			__( 'Canonical Redirects', 'disable-automatic-slug-redirects' ),
			array( $this, 'render_disable_canonical_field' ),
			self::PAGE_SLUG,
			'dasr_advanced_section'
		);
	}

	/**
	 * Sanitize submitted settings before saving.
	 *
	 * @param array $input Raw submitted values.
	 * @return array
	 */
	public function sanitize_options( $input ) {
		$defaults = $this->get_defaults();
		$output   = array();

		$output['enabled']           = ! empty( $input['enabled'] ) ? 1 : 0;
		$output['apply_to_post']     = ! empty( $input['apply_to_post'] ) ? 1 : 0;
		$output['apply_to_page']     = ! empty( $input['apply_to_page'] ) ? 1 : 0;
		$output['redirect_children'] = ! empty( $input['redirect_children'] ) ? 1 : 0;

		$valid_cpts             = array_keys( $this->get_custom_post_types() );
		$submitted_cpts         = isset( $input['apply_to_cpts'] ) && is_array( $input['apply_to_cpts'] )
			? array_map( 'sanitize_key', wp_unslash( $input['apply_to_cpts'] ) )
			: array();
		$output['apply_to_cpts'] = array_values( array_intersect( $submitted_cpts, $valid_cpts ) );

		$output['disable_canonical'] = ! empty( $input['disable_canonical'] ) ? 1 : 0;

		add_settings_error(
			DASR_OPTION_NAME,
			'dasr_settings_updated',
			__( 'Slug redirect settings saved.', 'disable-automatic-slug-redirects' ),
			'success'
		);

		return wp_parse_args( $output, $defaults );
	}

	/**
	 * Register the "Settings -> Slug Redirects" admin menu page.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Slug Redirects', 'disable-automatic-slug-redirects' ),
			__( 'Slug Redirects', 'disable-automatic-slug-redirects' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Intro text for the main section.
	 */
	public function render_main_section_intro() {
		echo '<p>' . esc_html__( 'Control whether WordPress redirects old URLs to a new one after a slug is changed. When disabled here, old URLs will return a normal 404 instead of redirecting.', 'disable-automatic-slug-redirects' ) . '</p>';
	}

	/**
	 * Intro text for the advanced section.
	 */
	public function render_advanced_section_intro() {
		echo '<p>' . esc_html__( 'These options affect behavior beyond old-slug redirects. Only change them if you understand the impact.', 'disable-automatic-slug-redirects' ) . '</p>';
	}

	/**
	 * Render the master enable/disable checkbox.
	 */
	public function render_enabled_field() {
		$options = $this->get_options();
		?>
		<label for="dasr_enabled">
			<input
				type="checkbox"
				id="dasr_enabled"
				name="<?php echo esc_attr( DASR_OPTION_NAME ); ?>[enabled]"
				value="1"
				<?php checked( 1, $options['enabled'] ); ?>
			/>
			<?php esc_html_e( 'Prevent WordPress from redirecting old slugs to new ones.', 'disable-automatic-slug-redirects' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the per-post-type checkboxes.
	 */
	public function render_apply_to_field() {
		$options     = $this->get_options();
		$custom_types = $this->get_custom_post_types();
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Apply to', 'disable-automatic-slug-redirects' ); ?></legend>

			<label for="dasr_apply_to_post">
				<input
					type="checkbox"
					id="dasr_apply_to_post"
					name="<?php echo esc_attr( DASR_OPTION_NAME ); ?>[apply_to_post]"
					value="1"
					<?php checked( 1, $options['apply_to_post'] ); ?>
				/>
				<?php esc_html_e( 'Posts', 'disable-automatic-slug-redirects' ); ?>
			</label>
			<br />

			<label for="dasr_apply_to_page">
				<input
					type="checkbox"
					id="dasr_apply_to_page"
					name="<?php echo esc_attr( DASR_OPTION_NAME ); ?>[apply_to_page]"
					value="1"
					<?php checked( 1, $options['apply_to_page'] ); ?>
				/>
				<?php esc_html_e( 'Pages', 'disable-automatic-slug-redirects' ); ?>
			</label>

			<?php if ( ! empty( $custom_types ) ) : ?>
				<p style="margin-bottom: 4px;"><strong><?php esc_html_e( 'Custom Post Types', 'disable-automatic-slug-redirects' ); ?></strong></p>
				<?php foreach ( $custom_types as $slug => $label ) : ?>
					<label for="dasr_apply_to_cpt_<?php echo esc_attr( $slug ); ?>">
						<input
							type="checkbox"
							id="dasr_apply_to_cpt_<?php echo esc_attr( $slug ); ?>"
							name="<?php echo esc_attr( DASR_OPTION_NAME ); ?>[apply_to_cpts][]"
							value="<?php echo esc_attr( $slug ); ?>"
							<?php checked( in_array( $slug, $options['apply_to_cpts'], true ), true ); ?>
						/>
						<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code>
					</label>
					<br />
				<?php endforeach; ?>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No public custom post types were found on this site.', 'disable-automatic-slug-redirects' ); ?></p>
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'Only the content types checked above will have their old-slug redirects disabled. Unchecked types keep the default WordPress behavior.', 'disable-automatic-slug-redirects' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render the "Redirect Child Pages" checkbox.
	 */
	public function render_redirect_children_field() {
		$options = $this->get_options();
		?>
		<label for="dasr_redirect_children">
			<input
				type="checkbox"
				id="dasr_redirect_children"
				name="<?php echo esc_attr( DASR_OPTION_NAME ); ?>[redirect_children]"
				value="1"
				<?php checked( 1, $options['redirect_children'] ); ?>
			/>
			<?php esc_html_e( 'Automatically redirect child pages when parent or ancestor URL is redirected.', 'disable-automatic-slug-redirects' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When you rename a parent page slug or configure a parent URL redirect (e.g. in Rank Math, Redirection plugin, or standard page hierarchy), all child pages under that parent (e.g. /parent/child/) will automatically 301-redirect to their new parent location (/new-parent/child/).', 'disable-automatic-slug-redirects' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the advanced "disable canonical redirects" checkbox.
	 */
	public function render_disable_canonical_field() {
		$options = $this->get_options();
		?>
		<label for="dasr_disable_canonical">
			<input
				type="checkbox"
				id="dasr_disable_canonical"
				name="<?php echo esc_attr( DASR_OPTION_NAME ); ?>[disable_canonical]"
				value="1"
				<?php checked( 1, $options['disable_canonical'] ); ?>
			/>
			<?php esc_html_e( 'Also completely disable WordPress\'s core canonical redirect handler (redirect_canonical).', 'disable-automatic-slug-redirects' ); ?>
		</label>
		<p class="description">
			<strong><?php esc_html_e( 'Warning:', 'disable-automatic-slug-redirects' ); ?></strong>
			<?php esc_html_e( 'redirect_canonical() also handles unrelated redirects such as trailing slashes, pagination, and scheme/host normalization. Only enable this if you understand and accept that those redirects will stop working too.', 'disable-automatic-slug-redirects' ); ?>
		</p>
		<?php
	}

	/**
	 * Handle admin request to purge existing _wp_old_slug rows from the database.
	 */
	public function handle_purge_old_slugs() {
		if ( ! isset( $_POST['dasr_purge_old_slugs'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'dasr_purge_old_slugs_action', 'dasr_purge_nonce' );

		$deleted = $this->delete_all_old_slugs();

		add_settings_error(
			DASR_OPTION_NAME,
			'dasr_purged_old_slugs',
			/* translators: %d: number of rows deleted */
			sprintf( _n( '%d old slug record was deleted from the database.', '%d old slug records were deleted from the database.', $deleted, 'disable-automatic-slug-redirects' ), $deleted ),
			'success'
		);
	}

	/**
	 * Count the total number of _wp_old_slug records in the database.
	 *
	 * @return int
	 */
	public function get_old_slug_count() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_old_slug'" );
	}

	/**
	 * Delete all _wp_old_slug records from the database.
	 *
	 * @return int
	 */
	public function delete_all_old_slugs() {
		global $wpdb;

		return (int) $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_wp_old_slug'" );
	}

	/**
	 * Render the full settings page markup.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$old_slug_count = $this->get_old_slug_count();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Disable Automatic Slug Redirects', 'disable-automatic-slug-redirects' ); ?></h1>

			<?php settings_errors( DASR_OPTION_NAME ); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Changes', 'disable-automatic-slug-redirects' ) );
				?>
			</form>

			<hr style="margin: 30px 0 20px;" />

			<h2><?php esc_html_e( 'Old Slug Database Cleanup', 'disable-automatic-slug-redirects' ); ?></h2>
			<p>
				<?php
				/* translators: %d: count of old slug rows */
				printf( esc_html( _n( 'There is currently %d historical old slug stored in your database.', 'There are currently %d historical old slugs stored in your database.', $old_slug_count, 'disable-automatic-slug-redirects' ) ), $old_slug_count );
				?>
			</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'dasr_purge_old_slugs_action', 'dasr_purge_nonce' ); ?>
				<input type="hidden" name="dasr_purge_old_slugs" value="1" />
				<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to permanently delete all historical old slug entries from the database?', 'disable-automatic-slug-redirects' ) ); ?>');" <?php disabled( 0, $old_slug_count ); ?>>
					<?php esc_html_e( 'Purge Stored Old Slugs', 'disable-automatic-slug-redirects' ); ?>
				</button>
			</form>

			<hr style="margin: 30px 0 20px;" />

			<div class="notice notice-info inline" style="margin: 0; padding: 12px 16px;">
				<p>
					<strong><?php esc_html_e( 'Browser Cache Notice:', 'disable-automatic-slug-redirects' ); ?></strong>
					<?php esc_html_e( 'Web browsers aggressively cache 301 (Permanent) redirects. If you recently visited an old URL before activating this plugin, your browser may still redirect from its local cache. Test your changed URLs in an Incognito / Private browsing window or clear your browser cache to verify the 404 response immediately.', 'disable-automatic-slug-redirects' ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}
