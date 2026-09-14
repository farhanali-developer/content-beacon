<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Content_Beacon
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class CBEACON_Deactivator {

	/**
	 * Clear the scheduled cron event. Settings are left in place.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( CBEACON_CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, CBEACON_CRON_HOOK );
		}

		delete_transient( 'cbeacon_stale_count' );
	}
}
