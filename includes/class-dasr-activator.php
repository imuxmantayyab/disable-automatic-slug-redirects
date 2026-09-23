<?php
/**
 * Fired during plugin activation.
 *
 * @package Disable_Automatic_Slug_Redirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DASR_Activator
 */
class DASR_Activator {

	/**
	 * Runs on plugin activation. Seeds default options if none exist yet;
	 * never overwrites settings from a previous activation.
	 */
	public static function activate() {
		if ( false === get_option( DASR_OPTION_NAME, false ) ) {
			$defaults = array(
				'enabled'           => 1,
				'apply_to_post'     => 1,
				'apply_to_page'     => 1,
				'apply_to_cpts'     => array(),
				'redirect_children' => 1,
				'disable_canonical' => 0,
			);

			add_option( DASR_OPTION_NAME, $defaults );
		}
	}
}
