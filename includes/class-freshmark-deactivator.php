<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Freshmark
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class FRESHMARK_Deactivator {

	/**
	 * Clear the scheduled cron event. Settings are left in place.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( FRESHMARK_CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, FRESHMARK_CRON_HOOK );
		}

		delete_transient( 'freshmark_stale_count' );
	}
}
