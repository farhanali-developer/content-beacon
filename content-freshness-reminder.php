<?php
/**
 * Plugin bootstrap file.
 *
 * @link              https://farhanali.me
 * @since             1.0.0
 * @package           Content_Freshness_Reminder
 *
 * @wordpress-plugin
 * Plugin Name:       Content Freshness Reminder
 * Plugin URI:        https://wordpress.org/plugins/content-freshness-reminder
 * Description:       Flags pages and posts that haven't been touched in 6+ months and nudges site owners to keep their content fresh.
 * Version:           1.0.0
 * Author:            Farhan Ali
 * Author URI:        https://farhanali.me/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       content-freshness-reminder
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
define( 'CFR_VERSION', '1.0.0' );
define( 'CFR_PLUGIN_FILE', __FILE__ );
define( 'CFR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CFR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CFR_CRON_HOOK', 'cfr_weekly_digest_check' );

require CFR_PLUGIN_DIR . 'includes/class-cfr-scanner.php';
require CFR_PLUGIN_DIR . 'includes/class-cfr-cron.php';
require CFR_PLUGIN_DIR . 'includes/class-cfr-activator.php';
require CFR_PLUGIN_DIR . 'includes/class-cfr-deactivator.php';

register_activation_hook( __FILE__, array( 'CFR_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CFR_Deactivator', 'deactivate' ) );

new CFR_Cron();

if ( is_admin() ) {
	require CFR_PLUGIN_DIR . 'admin/class-cfr-admin.php';
	new CFR_Admin();
}
