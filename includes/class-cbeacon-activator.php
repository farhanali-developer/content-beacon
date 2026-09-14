<?php
/**
 * Fired during plugin activation.
 *
 * @package Content_Beacon
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class CBEACON_Activator {

	/**
	 * Seed default settings and schedule the weekly digest check.
	 */
	public static function activate() {
		if ( false === get_option( CBEACON_Scanner::OPTION_NAME, false ) ) {
			add_option( CBEACON_Scanner::OPTION_NAME, CBEACON_Scanner::default_settings() );
		}

		if ( ! wp_next_scheduled( CBEACON_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', CBEACON_CRON_HOOK );
		}
	}
}
