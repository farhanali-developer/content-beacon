<?php
/**
 * Admin-side UI: settings page, dashboard widget, admin notice, and the
 * post list "Freshness" column.
 *
 * @package Content_Freshness_Reminder
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class CFR_Admin {

	const COLUMN_KEY       = 'cfr_freshness';
	const SETTINGS_GROUP   = 'cfr_settings_group';
	const SETTINGS_SLUG    = 'cfr-settings';
	const DISMISS_META_KEY = 'cfr_notice_dismissed_until';
	const NONCE_ACTION     = 'cfr_dismiss_notice';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
		add_action( 'wp_ajax_cfr_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
		add_filter( 'plugin_action_links_' . CFR_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );

		$this->register_column_hooks();
	}

	/**
	 * Wire up the "Freshness" column for every monitored post type.
	 */
	private function register_column_hooks() {
		$settings = CFR_Scanner::get_settings();

		foreach ( $settings['post_types'] as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_freshness_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_freshness_column' ), 10, 2 );
		}
	}

	/**
	 * Insert the Freshness column just before the Date column.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function add_freshness_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) {
				$new_columns[ self::COLUMN_KEY ] = __( 'Freshness', 'content-freshness-reminder' );
			}
			$new_columns[ $key ] = $label;
		}

		if ( ! isset( $new_columns[ self::COLUMN_KEY ] ) ) {
			$new_columns[ self::COLUMN_KEY ] = __( 'Freshness', 'content-freshness-reminder' );
		}

		return $new_columns;
	}

	/**
	 * Render the Freshness column cell.
	 *
	 * @param string $column
	 * @param int    $post_id
	 */
	public function render_freshness_column( $column, $post_id ) {
		if ( self::COLUMN_KEY !== $column ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			echo '&#8212;';
			return;
		}

		$is_stale = CFR_Scanner::is_stale( $post );
		$date     = get_the_modified_date( get_option( 'date_format' ), $post );

		printf(
			'<span class="cfr-badge %1$s">%2$s</span><br><span class="cfr-badge-date">%3$s</span>',
			$is_stale ? 'cfr-badge-stale' : 'cfr-badge-fresh',
			$is_stale ? esc_html__( 'Stale', 'content-freshness-reminder' ) : esc_html__( 'Fresh', 'content-freshness-reminder' ),
			esc_html( $date )
		);

		$this->print_badge_styles_once();
	}

	/**
	 * Print the (tiny) badge CSS a single time per page load.
	 */
	private function print_badge_styles_once() {
		static $printed = false;

		if ( $printed ) {
			return;
		}
		$printed = true;

		echo '<style>
			.cfr-badge{display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;line-height:1.6;}
			.cfr-badge-stale{background:#fbeaea;color:#a0281f;}
			.cfr-badge-fresh{background:#eafbea;color:#1f7a2e;}
			.cfr-badge-date{color:#646970;font-size:12px;}
		</style>';
	}

	/**
	 * Register the Settings > Content Freshness page.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Content Freshness Reminder', 'content-freshness-reminder' ),
			__( 'Content Freshness', 'content-freshness-reminder' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Add a "Settings" link on the Plugins list row.
	 *
	 * @param array $links
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::SETTINGS_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'content-freshness-reminder' ) . '</a>' );

		return $links;
	}

	/**
	 * Register the setting + sanitizer with the Settings API.
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			CFR_Scanner::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = CFR_Scanner::default_settings();
		$clean    = array();

		$months                    = isset( $input['threshold_months'] ) ? absint( $input['threshold_months'] ) : $defaults['threshold_months'];
		$clean['threshold_months'] = max( 1, min( 60, $months ) );

		$allowed_types     = array_keys( CFR_Scanner::get_monitorable_post_types() );
		$submitted_types   = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? array_map( 'sanitize_key', $input['post_types'] ) : array();
		$clean['post_types'] = array_values( array_intersect( $allowed_types, $submitted_types ) );

		if ( empty( $clean['post_types'] ) ) {
			$clean['post_types'] = $defaults['post_types'];
		}

		$clean['email_digest_enabled'] = ! empty( $input['email_digest_enabled'] );

		CFR_Scanner::clear_cache();

		return $clean;
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = CFR_Scanner::get_settings();
		$post_types = CFR_Scanner::get_monitorable_post_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Content Freshness Reminder', 'content-freshness-reminder' ); ?></h1>
			<p><?php esc_html_e( 'Get nudged when pages or posts have gone stale so nothing on the site is quietly abandoned.', 'content-freshness-reminder' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="cfr_threshold_months"><?php esc_html_e( 'Stale after', 'content-freshness-reminder' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="cfr_threshold_months"
								name="<?php echo esc_attr( CFR_Scanner::OPTION_NAME ); ?>[threshold_months]"
								value="<?php echo esc_attr( $settings['threshold_months'] ); ?>"
								min="1"
								max="60"
								class="small-text"
							/>
							<?php esc_html_e( 'months without an update', 'content-freshness-reminder' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Monitor', 'content-freshness-reminder' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $post_types as $post_type => $label ) : ?>
									<label style="display:block;margin-bottom:4px;">
										<input
											type="checkbox"
											name="<?php echo esc_attr( CFR_Scanner::OPTION_NAME ); ?>[post_types][]"
											value="<?php echo esc_attr( $post_type ); ?>"
											<?php checked( in_array( $post_type, $settings['post_types'], true ) ); ?>
										/>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Weekly email digest', 'content-freshness-reminder' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( CFR_Scanner::OPTION_NAME ); ?>[email_digest_enabled]"
									value="1"
									<?php checked( ! empty( $settings['email_digest_enabled'] ) ); ?>
								/>
								<?php
								printf(
									/* translators: %s: admin email address */
									esc_html__( 'Email a weekly summary of stale content to %s', 'content-freshness-reminder' ),
									'<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>'
								);
								?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register the Dashboard widget.
	 */
	public function add_dashboard_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'cfr_dashboard_widget',
			__( 'Content Freshness', 'content-freshness-reminder' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the Dashboard widget contents.
	 */
	public function render_dashboard_widget() {
		$stale_posts = CFR_Scanner::get_stale_content( array( 'limit' => 10 ) );

		if ( empty( $stale_posts ) ) {
			echo '<p>' . esc_html__( 'Nothing looks abandoned right now. Nice work keeping things current!', 'content-freshness-reminder' ) . '</p>';
			return;
		}

		echo '<ul style="margin:0;">';
		foreach ( $stale_posts as $post ) {
			$edit_link = get_edit_post_link( $post );
			$title     = $edit_link
				? sprintf( '<a href="%1$s"><strong>%2$s</strong></a>', esc_url( $edit_link ), esc_html( get_the_title( $post ) ) )
				: '<strong>' . esc_html( get_the_title( $post ) ) . '</strong>';

			echo wp_kses_post(
				sprintf(
					'<li style="margin-bottom:8px;">%1$s <br><span style="color:#646970;">%2$s</span></li>',
					$title,
					sprintf(
						/* translators: %s: month and year, e.g. "March 2025" */
						esc_html__( "Hasn't been updated since %s", 'content-freshness-reminder' ),
						esc_html( CFR_Scanner::get_freshness_label( $post ) )
					)
				)
			);
		}
		echo '</ul>';

		printf(
			'<p><a href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'options-general.php?page=' . self::SETTINGS_SLUG ) ),
			esc_html__( 'Adjust freshness settings', 'content-freshness-reminder' )
		);
	}

	/**
	 * Show a dismissible nag on the Dashboard when stale content is found.
	 */
	public function maybe_render_notice() {
		$screen = get_current_screen();

		if ( ! $screen || 'dashboard' !== $screen->id || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$dismissed_until = (int) get_user_meta( get_current_user_id(), self::DISMISS_META_KEY, true );

		if ( $dismissed_until && time() < $dismissed_until ) {
			return;
		}

		$count = CFR_Scanner::get_stale_count();

		if ( $count < 1 ) {
			return;
		}

		$message = sprintf(
			/* translators: %d: number of stale posts/pages */
			_n(
				'%d page or post hasn\'t been updated in a while.',
				'%d pages and posts haven\'t been updated in a while.',
				$count,
				'content-freshness-reminder'
			),
			$count
		);

		$nonce = wp_create_nonce( self::NONCE_ACTION );
		?>
		<div class="notice notice-warning is-dismissible cfr-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<p>
				<strong><?php esc_html_e( 'Content Freshness Reminder:', 'content-freshness-reminder' ); ?></strong>
				<?php echo esc_html( $message ); ?>
				<?php echo wp_kses_post( '&nbsp;' ); ?>
				<a href="#cfr_dashboard_widget"><?php esc_html_e( 'See the details below.', 'content-freshness-reminder' ); ?></a>
			</p>
		</div>
		<script>
		( function() {
			var notice = document.currentScript.previousElementSibling;
			if ( ! notice ) {
				return;
			}
			notice.addEventListener( 'click', function( e ) {
				if ( ! e.target.classList.contains( 'notice-dismiss' ) ) {
					return;
				}
				var data = new FormData();
				data.append( 'action', 'cfr_dismiss_notice' );
				data.append( 'nonce', notice.getAttribute( 'data-nonce' ) );
				fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: data } );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * AJAX handler: remember that the current user dismissed the notice for a week.
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}

		update_user_meta( get_current_user_id(), self::DISMISS_META_KEY, time() + WEEK_IN_SECONDS );
		wp_send_json_success();
	}
}
