<?php
/**
 * Admin-side UI: settings page, dashboard widget, admin notice, and the
 * post list "Freshness" column.
 *
 * @package Freshmark
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class FRESHMARK_Admin {

	const COLUMN_KEY       = 'freshmark_freshness';
	const SETTINGS_GROUP   = 'freshmark_settings_group';
	const SETTINGS_SLUG    = 'freshmark-settings';
	const DISMISS_META_KEY = 'freshmark_notice_dismissed_until';
	const NONCE_ACTION     = 'freshmark_dismiss_notice';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_freshmark_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
		add_filter( 'plugin_action_links_' . FRESHMARK_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );

		$this->register_column_hooks();
	}

	/**
	 * Enqueue the badge CSS on post list screens and the dismiss-notice JS on the Dashboard.
	 *
	 * @param string $hook_suffix Current admin page hook, e.g. 'edit.php', 'index.php'.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'edit.php' === $hook_suffix ) {
			wp_enqueue_style(
				'freshmark-admin',
				FRESHMARK_PLUGIN_URL . 'admin/css/freshmark-admin.css',
				array(),
				FRESHMARK_VERSION
			);
		}

		if ( 'index.php' === $hook_suffix ) {
			wp_enqueue_script(
				'freshmark-admin',
				FRESHMARK_PLUGIN_URL . 'admin/js/freshmark-admin.js',
				array(),
				FRESHMARK_VERSION,
				true
			);
		}
	}

	/**
	 * Wire up the "Freshness" column for every monitored post type.
	 */
	private function register_column_hooks() {
		$settings = FRESHMARK_Scanner::get_settings();

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
				$new_columns[ self::COLUMN_KEY ] = __( 'Freshness', 'freshmark' );
			}
			$new_columns[ $key ] = $label;
		}

		if ( ! isset( $new_columns[ self::COLUMN_KEY ] ) ) {
			$new_columns[ self::COLUMN_KEY ] = __( 'Freshness', 'freshmark' );
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

		$is_stale = FRESHMARK_Scanner::is_stale( $post );
		$date     = get_the_modified_date( get_option( 'date_format' ), $post );

		printf(
			'<span class="freshmark-badge %1$s">%2$s</span><br><span class="freshmark-badge-date">%3$s</span>',
			$is_stale ? 'freshmark-badge-stale' : 'freshmark-badge-fresh',
			$is_stale ? esc_html__( 'Stale', 'freshmark' ) : esc_html__( 'Fresh', 'freshmark' ),
			esc_html( $date )
		);
	}

	/**
	 * Register the Settings > Freshmark page.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Freshmark', 'freshmark' ),
			__( 'Freshmark', 'freshmark' ),
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
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'freshmark' ) . '</a>' );

		return $links;
	}

	/**
	 * Register the setting + sanitizer with the Settings API.
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			FRESHMARK_Scanner::OPTION_NAME,
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
		$defaults = FRESHMARK_Scanner::default_settings();
		$clean    = array();

		$months                    = isset( $input['threshold_months'] ) ? absint( $input['threshold_months'] ) : $defaults['threshold_months'];
		$clean['threshold_months'] = max( 1, min( 60, $months ) );

		$allowed_types     = array_keys( FRESHMARK_Scanner::get_monitorable_post_types() );
		$submitted_types   = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? array_map( 'sanitize_key', $input['post_types'] ) : array();
		$clean['post_types'] = array_values( array_intersect( $allowed_types, $submitted_types ) );

		if ( empty( $clean['post_types'] ) ) {
			$clean['post_types'] = $defaults['post_types'];
		}

		$clean['email_digest_enabled'] = ! empty( $input['email_digest_enabled'] );

		FRESHMARK_Scanner::clear_cache();

		return $clean;
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = FRESHMARK_Scanner::get_settings();
		$post_types = FRESHMARK_Scanner::get_monitorable_post_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Freshmark', 'freshmark' ); ?></h1>
			<p><?php esc_html_e( 'Get nudged when pages or posts have gone stale so nothing on the site is quietly abandoned.', 'freshmark' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="freshmark_threshold_months"><?php esc_html_e( 'Stale after', 'freshmark' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="freshmark_threshold_months"
								name="<?php echo esc_attr( FRESHMARK_Scanner::OPTION_NAME ); ?>[threshold_months]"
								value="<?php echo esc_attr( $settings['threshold_months'] ); ?>"
								min="1"
								max="60"
								class="small-text"
							/>
							<?php esc_html_e( 'months without an update', 'freshmark' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Monitor', 'freshmark' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $post_types as $post_type => $label ) : ?>
									<label style="display:block;margin-bottom:4px;">
										<input
											type="checkbox"
											name="<?php echo esc_attr( FRESHMARK_Scanner::OPTION_NAME ); ?>[post_types][]"
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
						<th scope="row"><?php esc_html_e( 'Weekly email digest', 'freshmark' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( FRESHMARK_Scanner::OPTION_NAME ); ?>[email_digest_enabled]"
									value="1"
									<?php checked( ! empty( $settings['email_digest_enabled'] ) ); ?>
								/>
								<?php
								printf(
									/* translators: %s: admin email address */
									esc_html__( 'Email a weekly summary of stale content to %s', 'freshmark' ),
									'<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>'
								);
								?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Sent via wp_mail(). Delivery depends on your site\'s mail setup — if emails go missing, install an SMTP plugin (e.g. WP Mail SMTP) to route them through a real mail provider.', 'freshmark' ); ?>
							</p>
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
			'freshmark_dashboard_widget',
			__( 'Freshmark', 'freshmark' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the Dashboard widget contents.
	 */
	public function render_dashboard_widget() {
		$stale_posts = FRESHMARK_Scanner::get_stale_content( array( 'limit' => 10 ) );

		if ( empty( $stale_posts ) ) {
			echo '<p>' . esc_html__( 'Nothing looks abandoned right now. Nice work keeping things current!', 'freshmark' ) . '</p>';
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
						esc_html__( "Hasn't been updated since %s", 'freshmark' ),
						esc_html( FRESHMARK_Scanner::get_freshness_label( $post ) )
					)
				)
			);
		}
		echo '</ul>';

		printf(
			'<p><a href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'options-general.php?page=' . self::SETTINGS_SLUG ) ),
			esc_html__( 'Adjust freshness settings', 'freshmark' )
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

		$count = FRESHMARK_Scanner::get_stale_count();

		if ( $count < 1 ) {
			return;
		}

		$message = sprintf(
			/* translators: %d: number of stale posts/pages */
			_n(
				'%d page or post hasn\'t been updated in a while.',
				'%d pages and posts haven\'t been updated in a while.',
				$count,
				'freshmark'
			),
			$count
		);

		$nonce = wp_create_nonce( self::NONCE_ACTION );
		?>
		<div class="notice notice-warning is-dismissible freshmark-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<p>
				<strong><?php esc_html_e( 'Freshmark:', 'freshmark' ); ?></strong>
				<?php echo esc_html( $message ); ?>
				<?php echo wp_kses_post( '&nbsp;' ); ?>
				<a href="#freshmark_dashboard_widget"><?php esc_html_e( 'See the details below.', 'freshmark' ); ?></a>
			</p>
		</div>
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
