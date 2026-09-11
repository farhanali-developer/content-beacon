<?php
/**
 * Fired during plugin activation.
 *
 * @package Content_Freshness_Reminder
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class CFR_Activator {

	/**
	 * Seed default settings and schedule the weekly digest check.
	 */
	public static function activate() {
		if ( false === get_option( CFR_Scanner::OPTION_NAME, false ) ) {
			add_option( CFR_Scanner::OPTION_NAME, CFR_Scanner::default_settings() );
		}

		if ( ! wp_next_scheduled( CFR_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', CFR_CRON_HOOK );
		}
	}
}
