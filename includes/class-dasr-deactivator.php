<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Disable_Automatic_Slug_Redirects
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DASR_Deactivator
 */
class DASR_Deactivator {

	/**
	 * Runs on plugin deactivation.
	 *
	 * Deliberately leaves saved options in place so re-activating the
	 * plugin restores the previous configuration. Options are only removed
	 * on uninstall (see uninstall.php).
	 */
	public static function deactivate() {
		// Nothing to clean up on deactivation. Reserved for future use
		// (e.g. clearing transients/caches related to redirect handling).
	}
}
