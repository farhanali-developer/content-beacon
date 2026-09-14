<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Content_Beacon
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cbeacon_settings' );
delete_transient( 'cbeacon_stale_count' );
delete_metadata( 'user', 0, 'cbeacon_notice_dismissed_until', '', true );

$cbeacon_timestamp = wp_next_scheduled( 'cbeacon_weekly_digest_check' );
if ( $cbeacon_timestamp ) {
	wp_unschedule_event( $cbeacon_timestamp, 'cbeacon_weekly_digest_check' );
}
