<?php
/**
 * Plugin bootstrap file.
 *
 * @link              https://farhanali.me
 * @since             1.0.0
 * @package           Content_Beacon
 *
 * @wordpress-plugin
 * Plugin Name:       Content Beacon
 * Plugin URI:        https://wordpress.org/plugins/content-beacon
 * Description:       Flags pages and posts that haven't been touched in 6+ months and nudges site owners to keep their content fresh.
 * Version:           1.0.0
 * Author:            Farhan Ali
 * Author URI:        https://farhanali.me/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       content-beacon
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
define( 'CBEACON_VERSION', '1.0.0' );
define( 'CBEACON_PLUGIN_FILE', __FILE__ );
define( 'CBEACON_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CBEACON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CBEACON_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CBEACON_CRON_HOOK', 'cbeacon_weekly_digest_check' );

require CBEACON_PLUGIN_DIR . 'includes/class-cbeacon-scanner.php';
require CBEACON_PLUGIN_DIR . 'includes/class-cbeacon-cron.php';
require CBEACON_PLUGIN_DIR . 'includes/class-cbeacon-activator.php';
require CBEACON_PLUGIN_DIR . 'includes/class-cbeacon-deactivator.php';

register_activation_hook( __FILE__, array( 'CBEACON_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CBEACON_Deactivator', 'deactivate' ) );

new CBEACON_Cron();

if ( is_admin() ) {
	require CBEACON_PLUGIN_DIR . 'admin/class-cbeacon-admin.php';
	new CBEACON_Admin();
}
