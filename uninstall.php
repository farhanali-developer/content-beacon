<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Content_Freshness_Reminder
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cfr_settings' );
delete_transient( 'cfr_stale_count' );
delete_metadata( 'user', 0, 'cfr_notice_dismissed_until', '', true );

$timestamp = wp_next_scheduled( 'cfr_weekly_digest_check' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'cfr_weekly_digest_check' );
}
