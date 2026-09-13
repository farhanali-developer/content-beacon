<?php
/**
 * Fired during plugin activation.
 *
 * @package Freshmark
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class FRESHMARK_Activator {

	/**
	 * Seed default settings and schedule the weekly digest check.
	 */
	public static function activate() {
		if ( false === get_option( FRESHMARK_Scanner::OPTION_NAME, false ) ) {
			add_option( FRESHMARK_Scanner::OPTION_NAME, FRESHMARK_Scanner::default_settings() );
		}

		if ( ! wp_next_scheduled( FRESHMARK_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', FRESHMARK_CRON_HOOK );
		}
	}
}
