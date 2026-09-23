<?php
/**
 * Main plugin bootstrap class.
 *
 * @package Disable_Automatic_Slug_Redirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DASR_Plugin
 *
 * Singleton responsible for wiring up translations, the settings screen,
 * and the core redirect/meta-suppression logic.
 */
final class DASR_Plugin {

	/**
	 * Single instance of this class.
	 *
	 * @var DASR_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings handler instance.
	 *
	 * @var DASR_Settings
	 */
	public $settings;

	/**
	 * Core logic handler instance.
	 *
	 * @var DASR_Core
	 */
	public $core;

	/**
	 * Get (and lazily create) the singleton instance.
	 *
	 * @return DASR_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor. Kept private to enforce the singleton pattern.
	 */
	private function __construct() {
		$this->load_textdomain();

		$this->settings = new DASR_Settings();
		$this->core     = new DASR_Core( $this->settings );

		$this->settings->init();
		$this->core->init();
	}

	/**
	 * Load the plugin translations.
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'disable-automatic-slug-redirects',
			false,
			dirname( DASR_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Prevent cloning of the instance.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing of the instance.
	 */
	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Cannot unserialize a singleton.', 'disable-automatic-slug-redirects' ),
			'1.0.0'
		);
	}
}
