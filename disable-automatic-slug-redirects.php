<?php
/**
 * Plugin Name:       Disable Automatic Slug Redirects
 * Plugin URI:        https://github.com/imuxmantayyab/disable-automatic-slug-redirects
 * Description:       Prevent WordPress from automatically redirecting old URLs after changing page, post, or custom post type slugs.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            Usman Tayyab
 * Author URI:        https://www.linkedin.com/in/imuxmantayyab/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       disable-automatic-slug-redirects
 * Domain Path:       /languages
 *
 * @package Disable_Automatic_Slug_Redirects
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'DASR_VERSION', '1.0.1' );
define( 'DASR_PLUGIN_FILE', __FILE__ );
define( 'DASR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DASR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DASR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'DASR_OPTION_NAME', 'dasr_settings' );

/**
 * Autoload / require the plugin classes.
 */
require_once DASR_PLUGIN_DIR . 'includes/class-dasr-activator.php';
require_once DASR_PLUGIN_DIR . 'includes/class-dasr-deactivator.php';
require_once DASR_PLUGIN_DIR . 'includes/class-dasr-settings.php';
require_once DASR_PLUGIN_DIR . 'includes/class-dasr-core.php';
require_once DASR_PLUGIN_DIR . 'includes/class-dasr-plugin.php';

/**
 * Activation / deactivation hooks.
 */
register_activation_hook( __FILE__, array( 'DASR_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DASR_Deactivator', 'deactivate' ) );

/**
 * Kick off the plugin once all plugins are loaded so we can safely detect
 * registered custom post types.
 */
function dasr_run_plugin() {
	return DASR_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'dasr_run_plugin' );
