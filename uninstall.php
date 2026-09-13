<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Freshmark
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'freshmark_settings' );
delete_transient( 'freshmark_stale_count' );
delete_metadata( 'user', 0, 'freshmark_notice_dismissed_until', '', true );

$freshmark_timestamp = wp_next_scheduled( 'freshmark_weekly_digest_check' );
if ( $freshmark_timestamp ) {
	wp_unschedule_event( $freshmark_timestamp, 'freshmark_weekly_digest_check' );
}
