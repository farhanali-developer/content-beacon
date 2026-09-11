<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Content_Freshness_Reminder
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class CFR_Deactivator {

	/**
	 * Clear the scheduled cron event. Settings are left in place.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( CFR_CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, CFR_CRON_HOOK );
		}

		delete_transient( 'cfr_stale_count' );
	}
}
