<?php
/**
 * Core scanning logic shared by the admin notice, dashboard widget,
 * post list columns, and the email digest.
 *
 * @package Content_Freshness_Reminder
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class CFR_Scanner {

	const OPTION_NAME = 'cfr_settings';

	/**
	 * Default plugin settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'threshold_months'    => 6,
			'post_types'          => array( 'post', 'page' ),
			'email_digest_enabled' => false,
		);
	}

	/**
	 * Get plugin settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $settings, self::default_settings() );
	}

	/**
	 * Post types the plugin is allowed to monitor (public, viewable types).
	 *
	 * @return array post_type => label
	 */
	public static function get_monitorable_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );

		$choices = array();
		foreach ( $post_types as $post_type ) {
			$choices[ $post_type->name ] = $post_type->labels->name;
		}

		return $choices;
	}

	/**
	 * Cutoff timestamp (GMT) beyond which content is considered stale.
	 *
	 * @param int $threshold_months Optional. Defaults to the saved setting.
	 * @return int Unix timestamp.
	 */
	public static function get_cutoff_timestamp( $threshold_months = null ) {
		if ( null === $threshold_months ) {
			$settings         = self::get_settings();
			$threshold_months = $settings['threshold_months'];
		}

		return strtotime( '-' . absint( $threshold_months ) . ' months', current_time( 'timestamp', true ) );
	}

	/**
	 * Find published content that hasn't been modified since the cutoff date.
	 *
	 * @param array $args {
	 *     Optional overrides.
	 *
	 *     @type int    $limit  Max number of results. 0 for no limit.
	 *     @type string $fields Passed straight to WP_Query (e.g. 'ids' for a lightweight count-only query).
	 * }
	 * @return WP_Post[]|int[]
	 */
	public static function get_stale_content( $args = array() ) {
		$settings = self::get_settings();

		if ( empty( $settings['post_types'] ) ) {
			return array();
		}

		$limit  = isset( $args['limit'] ) ? (int) $args['limit'] : 0;
		$cutoff = gmdate( 'Y-m-d H:i:s', self::get_cutoff_timestamp( $settings['threshold_months'] ) );

		$query_args = array(
			'post_type'              => $settings['post_types'],
			'post_status'            => 'publish',
			'posts_per_page'         => $limit > 0 ? $limit : -1,
			'orderby'                => 'modified',
			'order'                  => 'ASC',
			'date_query'             => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => $cutoff,
				),
			),
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( ! empty( $args['fields'] ) ) {
			$query_args['fields'] = $args['fields'];
		}

		$query = new WP_Query( $query_args );

		return $query->posts;
	}

	/**
	 * Count of stale content, cached for a day to keep admin screens fast.
	 *
	 * Uses an ids-only query so a cache miss doesn't hydrate every stale
	 * post's full object just to count them.
	 *
	 * @return int
	 */
	public static function get_stale_count() {
		$cached = get_transient( 'cfr_stale_count' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$count = count( self::get_stale_content( array( 'fields' => 'ids' ) ) );
		set_transient( 'cfr_stale_count', $count, DAY_IN_SECONDS );

		return $count;
	}

	/**
	 * Clear the cached stale count, e.g. after a post is saved.
	 */
	public static function clear_cache() {
		delete_transient( 'cfr_stale_count' );
	}

	/**
	 * Human friendly "since March 2025" style string for a post's last modified date.
	 *
	 * @param WP_Post $post
	 * @return string
	 */
	public static function get_freshness_label( $post ) {
		return get_the_modified_date( 'F Y', $post );
	}

	/**
	 * Whole months since a post was last modified.
	 *
	 * @param WP_Post $post
	 * @return int
	 */
	public static function get_months_since_modified( $post ) {
		$modified = get_post_modified_time( 'U', true, $post );
		$now      = current_time( 'timestamp', true );

		if ( ! $modified ) {
			return 0;
		}

		$diff = $now - $modified;

		return (int) floor( $diff / ( DAY_IN_SECONDS * 30 ) );
	}

	/**
	 * Whether a single post is currently stale, per saved settings.
	 *
	 * @param WP_Post $post
	 * @return bool
	 */
	public static function is_stale( $post ) {
		$modified = get_post_modified_time( 'U', true, $post );

		if ( ! $modified ) {
			return false;
		}

		return $modified < self::get_cutoff_timestamp();
	}
}
