<?php
/**
 * Cron schedule, weekly digest email, and cache invalidation.
 *
 * Loaded on every request (not just wp-admin) since WP-Cron requests
 * (wp-cron.php) are not admin requests.
 *
 * @package Content_Beacon
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class CBEACON_Cron {

	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_weekly_schedule' ) );
		add_action( CBEACON_CRON_HOOK, array( $this, 'maybe_send_digest' ) );
		add_action( 'save_post', array( 'CBEACON_Scanner', 'clear_cache' ) );
		add_action( 'transition_post_status', array( $this, 'clear_cache_on_transition' ), 10, 3 );
	}

	/**
	 * Register a "weekly" cron interval; WordPress core doesn't ship one.
	 *
	 * @param array $schedules
	 * @return array
	 */
	public function add_weekly_schedule( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'content-beacon' ),
			);
		}

		return $schedules;
	}

	/**
	 * Invalidate the cached stale count whenever a post's status changes.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function clear_cache_on_transition( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status ) {
			CBEACON_Scanner::clear_cache();
		}
	}

	/**
	 * Weekly cron callback: email the site admin a digest of stale content.
	 */
	public function maybe_send_digest() {
		$settings = CBEACON_Scanner::get_settings();

		if ( empty( $settings['email_digest_enabled'] ) ) {
			return;
		}

		$stale_posts = CBEACON_Scanner::get_stale_content( array( 'limit' => 50 ) );

		if ( empty( $stale_posts ) ) {
			return;
		}

		$lines = array();
		foreach ( $stale_posts as $post ) {
			$lines[] = sprintf(
				/* translators: 1: post title, 2: last modified month/year, 3: edit link */
				__( '- "%1$s" — last updated %2$s (%3$s)', 'content-beacon' ),
				html_entity_decode( get_the_title( $post ), ENT_QUOTES ),
				CBEACON_Scanner::get_freshness_label( $post ),
				get_edit_post_link( $post, 'text' )
			);
		}

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf(
			/* translators: 1: site name, 2: number of stale items */
			__( '[%1$s] %2$d page(s)/post(s) need a content refresh', 'content-beacon' ),
			$site_name,
			count( $stale_posts )
		);

		$body  = __( 'The following content hasn\'t been updated in a while:', 'content-beacon' ) . "\n\n";
		$body .= implode( "\n", $lines );
		$body .= "\n\n" . sprintf(
			/* translators: %s: site name */
			__( 'Sent by Content Beacon on %s.', 'content-beacon' ),
			$site_name
		);

		wp_mail( get_option( 'admin_email' ), $subject, $body );
	}
}
