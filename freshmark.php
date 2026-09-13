<?php
/**
 * Plugin bootstrap file.
 *
 * @link              https://farhanali.me
 * @since             1.0.0
 * @package           Freshmark
 *
 * @wordpress-plugin
 * Plugin Name:       Freshmark
 * Plugin URI:        https://wordpress.org/plugins/freshmark
 * Description:       Flags pages and posts that haven't been touched in 6+ months and nudges site owners to keep their content fresh.
 * Version:           1.0.0
 * Author:            Farhan Ali
 * Author URI:        https://farhanali.me/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       freshmark
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.2
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define( 'FRESHMARK_VERSION', '1.0.0' );
define( 'FRESHMARK_PLUGIN_FILE', __FILE__ );
define( 'FRESHMARK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FRESHMARK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FRESHMARK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FRESHMARK_CRON_HOOK', 'freshmark_weekly_digest_check' );

require FRESHMARK_PLUGIN_DIR . 'includes/class-freshmark-scanner.php';
require FRESHMARK_PLUGIN_DIR . 'includes/class-freshmark-cron.php';
require FRESHMARK_PLUGIN_DIR . 'includes/class-freshmark-activator.php';
require FRESHMARK_PLUGIN_DIR . 'includes/class-freshmark-deactivator.php';

register_activation_hook( __FILE__, array( 'FRESHMARK_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FRESHMARK_Deactivator', 'deactivate' ) );

new FRESHMARK_Cron();

if ( is_admin() ) {
	require FRESHMARK_PLUGIN_DIR . 'admin/class-freshmark-admin.php';
	new FRESHMARK_Admin();
}
