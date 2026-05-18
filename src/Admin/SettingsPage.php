<?php
/**
 * Settings admin page.
 *
 * Extracted from AdminMenu.php during Phase 2 refactoring.
 * Source: AdminMenu.php.backup lines 561-609, 2346-2639.
 *
 * @package SampleHQForm\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the plugin settings page with 4 tabs:
 * General, Spam Protection, Email, SampleHQ Connection.
 */
class SettingsPage {

	private const SECRET_MASK = '••••••••••••••••';

	/**
	 * Connection manager instance (null when connection feature unavailable).
	 *
	 * @var \SampleHQForm\Connection\ConnectionManager|null
	 */
	private ?\SampleHQForm\Connection\ConnectionManager $connection_manager;

	/**
	 * Constructor.
	 *
	 * @param \SampleHQForm\Connection\ConnectionManager|null $connection_manager Optional connection manager.
	 */
	public function __construct( ?\SampleHQForm\Connection\ConnectionManager $connection_manager = null ) {
		$this->connection_manager = $connection_manager;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		// Save is handled by handle_save() on admin_init.

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'general' ) );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Settings', 'samplehq-request-form' ) . '</h1>';
		AdminNotice::render();

		// Tabs.
		echo '<nav class="nav-tab-wrapper">';
		$tabs = [
			'general'    => __( 'General', 'samplehq-request-form' ),
			'spam'       => __( 'Spam Protection', 'samplehq-request-form' ),
			'email'      => __( 'Email', 'samplehq-request-form' ),
			'connection' => __( 'SampleHQ Connection', 'samplehq-request-form' ),
		];

		$is_connected = $this->connection_manager ? $this->connection_manager->is_connected() : ! empty( get_option( 'shqf_connection', [] )['workspace_id'] );
		if ( $is_connected ) {
			$tabs['migration'] = __( 'Migration', 'samplehq-request-form' );
		}

		if ( \SampleHQForm\WooCommerce\WooDetector::is_active() ) {
			$tabs['woocommerce'] = __( 'WooCommerce', 'samplehq-request-form' );
		}
		foreach ( $tabs as $tab_key => $tab_label ) {
			$class = ( $active_tab === $tab_key ) ? ' nav-tab-active' : '';
			$url   = admin_url( 'admin.php?page=shqf-settings&tab=' . $tab_key );
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $class ) . '">';
			echo esc_html( $tab_label ) . '</a>';
		}
		echo '</nav>';

		switch ( $active_tab ) {
			case 'spam':
				$this->render_settings_spam_tab();
				break;
			case 'email':
				$this->render_settings_email_tab();
				break;
			case 'connection':
				$this->render_settings_connection_tab();
				break;
			case 'migration':
				if ( $is_connected ) {
					$this->render_settings_migration_tab();
				}
				break;
			case 'woocommerce':
				$this->render_settings_woocommerce_tab();
				break;
			default:
				$this->render_settings_general_tab();
				break;
		}

		echo '</div>'; // .wrap
	}

	/**
	 * Handle settings save on admin_init (before output).
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! isset( $_POST['shqf_settings_nonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['shqf_settings_nonce'] ) ),
			'shqf_save_settings'
		) ) {
			return;
		}

		$tab = sanitize_text_field( wp_unslash( $_POST['shqf_settings_tab'] ?? 'general' ) );

		switch ( $tab ) {
			case 'spam':
				$turnstile_site_key   = sanitize_text_field( wp_unslash( $_POST['shqf_turnstile_site_key'] ?? '' ) );
				$turnstile_secret_key = sanitize_text_field( wp_unslash( $_POST['shqf_turnstile_secret_key'] ?? '' ) );
				update_option( 'shqf_turnstile_site_key', $turnstile_site_key );
				if ( self::SECRET_MASK !== $turnstile_secret_key ) {
					update_option( 'shqf_turnstile_secret_key', $turnstile_secret_key );
				}
				break;

			case 'email':
				$raw_emails         = sanitize_text_field( wp_unslash( $_POST['shqf_notification_email'] ?? '' ) );
				$emails             = array_filter( array_map( 'sanitize_email', explode( ',', $raw_emails ) ) );
				$notification_email = implode( ', ', $emails );
				$from_name          = sanitize_text_field( wp_unslash( $_POST['shqf_from_name'] ?? '' ) );
				$send_confirmation  = ! empty( $_POST['shqf_send_confirmation'] ) ? '1' : '0';
				update_option(
					'shqf_email_defaults',
					[
						'notification_email' => $notification_email,
						'from_name'          => $from_name,
						'send_confirmation'  => $send_confirmation,
					]
				);
				break;

			case 'general':
				$collect_ip     = ! empty( $_POST['shqf_collect_ip'] ) ? '1' : '0';
				$retention_days = absint( $_POST['shqf_ip_retention_days'] ?? 90 );
				update_option( 'shqf_collect_ip', $collect_ip );
				update_option( 'shqf_ip_retention_days', $retention_days );
				break;

			case 'woocommerce':
				if ( ! \SampleHQForm\WooCommerce\WooDetector::is_active() ) {
					return;
				}
				$was_enabled = get_option( 'shqf_woo_enabled', '' );
				$now_enabled = ! empty( $_POST['shqf_woo_enabled'] ) ? '1' : '';
				// shqf_woo_enabled is autoloaded (checked on every page in plugins_loaded).
				update_option( 'shqf_woo_enabled', $now_enabled );
				// All other WC options: autoload false (only read on product/shop pages).
				update_option( 'shqf_woo_form_id', absint( $_POST['shqf_woo_form_id'] ?? 0 ), false );
				update_option( 'shqf_woo_button_text', sanitize_text_field( wp_unslash( $_POST['shqf_woo_button_text'] ?? '' ) ), false );
				update_option( 'shqf_woo_product_filter', sanitize_text_field( wp_unslash( $_POST['shqf_woo_product_filter'] ?? 'all' ) ), false );
				update_option( 'shqf_woo_sample_tag', sanitize_text_field( wp_unslash( $_POST['shqf_woo_sample_tag'] ?? '' ) ), false );
				update_option( 'shqf_woo_sample_categories', array_map( 'absint', (array) ( $_POST['shqf_woo_sample_categories'] ?? [] ) ), false );
				update_option( 'shqf_woo_max_quantity', absint( $_POST['shqf_woo_max_quantity'] ?? 3 ), false );
				update_option( 'shqf_woo_show_loop_badge', ! empty( $_POST['shqf_woo_show_loop_badge'] ) ? '1' : '', false );
				update_option( 'shqf_woo_badge_text', sanitize_text_field( wp_unslash( $_POST['shqf_woo_badge_text'] ?? '' ) ), false );
				// Clear WC product cache when settings change.
				$this->clear_woo_cache();
				// Show guidance notice when integration is first enabled.
				if ( '1' === $now_enabled && '' === $was_enabled ) {
					$this->set_woo_enabled_notice();
				}
				break;

			default:
				return;
		}

		AdminNotice::success( __( 'Settings saved.', 'samplehq-request-form' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=shqf-settings&tab=' . $tab ) );
		exit;
	}

	/**
	 * Render the General settings tab (privacy & data).
	 *
	 * @return void
	 */
	private function render_settings_general_tab(): void {
		$collect_ip     = get_option( 'shqf_collect_ip', '1' );
		$retention_days = (int) get_option( 'shqf_ip_retention_days', 90 );

		echo '<form method="post" action="">';
		wp_nonce_field( 'shqf_save_settings', 'shqf_settings_nonce' );
		echo '<input type="hidden" name="shqf_settings_tab" value="general" />';

		echo '<div id="poststuff">';
		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Privacy & Data', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<table class="form-table">';

		echo '<tr><th>' . esc_html__( 'Collect IP Address', 'samplehq-request-form' ) . '</th>';
		echo '<td><label>';
		echo '<input type="checkbox" name="shqf_collect_ip" value="1"' . checked( $collect_ip, '1', false ) . ' />';
		echo ' ' . esc_html__( 'Record IP address and user agent with submissions.', 'samplehq-request-form' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'Used for spam protection and rate limiting.', 'samplehq-request-form' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th><label for="shqf-retention">' . esc_html__( 'IP Retention (days)', 'samplehq-request-form' ) . '</label></th>';
		echo '<td><input type="number" id="shqf-retention" name="shqf_ip_retention_days" min="1" max="365" class="small-text"';
		echo ' value="' . esc_attr( (string) $retention_days ) . '" />';
		echo '<p class="description">' . esc_html__( 'IP addresses older than this will be automatically purged.', 'samplehq-request-form' ) . '</p></td></tr>';

		echo '</table>';
		echo '</div></div>';
		echo '</div>';

		submit_button();
		echo '</form>';
	}

	/**
	 * Render the Spam Protection settings tab.
	 *
	 * @return void
	 */
	private function render_settings_spam_tab(): void {
		$turnstile_site_key   = get_option( 'shqf_turnstile_site_key', '' );
		$turnstile_secret_key = get_option( 'shqf_turnstile_secret_key', '' );

		echo '<form method="post" action="">';
		wp_nonce_field( 'shqf_save_settings', 'shqf_settings_nonce' );
		echo '<input type="hidden" name="shqf_settings_tab" value="spam" />';

		echo '<div id="poststuff">';
		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Cloudflare Turnstile', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<p class="description">' . esc_html__( 'Cloudflare Turnstile adds an invisible CAPTCHA to your forms. Free at cloudflare.com/products/turnstile.', 'samplehq-request-form' ) . '</p>';
		echo '<table class="form-table">';

		echo '<tr><th><label for="shqf-turnstile-site">' . esc_html__( 'Site Key', 'samplehq-request-form' ) . '</label></th>';
		echo '<td><input type="text" id="shqf-turnstile-site" name="shqf_turnstile_site_key" class="regular-text"';
		echo ' value="' . esc_attr( $turnstile_site_key ) . '" autocomplete="off" /></td></tr>';

		$secret_display = '' !== $turnstile_secret_key ? self::SECRET_MASK : '';
		echo '<tr><th><label for="shqf-turnstile-secret">' . esc_html__( 'Secret Key', 'samplehq-request-form' ) . '</label></th>';
		echo '<td><input type="password" id="shqf-turnstile-secret" name="shqf_turnstile_secret_key" class="regular-text"';
		echo ' value="' . esc_attr( $secret_display ) . '" autocomplete="off" />';
		echo '<p class="description">' . esc_html__( 'Leave both fields empty to disable Turnstile. Existing honeypot and rate limiting remain active.', 'samplehq-request-form' ) . '</p></td></tr>';

		echo '</table>';
		echo '</div></div>';

		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Built-in Protection', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<p>' . esc_html__( 'The following protections are always active and require no configuration:', 'samplehq-request-form' ) . '</p>';
		echo '<ul style="list-style:disc;margin-left:20px;">';
		echo '<li>' . esc_html__( 'Honeypot field -- hidden field that catches bots', 'samplehq-request-form' ) . '</li>';
		echo '<li>' . esc_html__( 'Rate limiting -- 10 submissions per form per IP per hour', 'samplehq-request-form' ) . '</li>';
		echo '<li>' . esc_html__( 'CSRF token -- prevents cross-site request forgery', 'samplehq-request-form' ) . '</li>';
		echo '</ul>';
		echo '</div></div>';

		echo '</div>';

		submit_button();
		echo '</form>';
	}

	/**
	 * Render the Email settings tab.
	 *
	 * @return void
	 */
	private function render_settings_email_tab(): void {
		$defaults           = get_option( 'shqf_email_defaults', [] );
		$notification_email = $defaults['notification_email'] ?? '';
		$from_name          = $defaults['from_name'] ?? '';
		$send_confirmation  = $defaults['send_confirmation'] ?? '1';

		echo '<form method="post" action="">';
		wp_nonce_field( 'shqf_save_settings', 'shqf_settings_nonce' );
		echo '<input type="hidden" name="shqf_settings_tab" value="email" />';

		echo '<div id="poststuff">';

		// Admin notification settings.
		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Admin Notification', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<table class="form-table">';

		echo '<tr><th><label for="shqf-notification-email">' . esc_html__( 'Send To', 'samplehq-request-form' ) . '</label></th>';
		echo '<td><input type="text" id="shqf-notification-email" name="shqf_notification_email" class="regular-text"';
		echo ' value="' . esc_attr( $notification_email ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" />';
		echo '<p class="description">' . esc_html__( 'Email address for admin notifications. Separate multiple addresses with commas. Leave blank to use the site admin email.', 'samplehq-request-form' ) . '</p></td></tr>';

		echo '<tr><th><label for="shqf-from-name">' . esc_html__( 'From Name', 'samplehq-request-form' ) . '</label></th>';
		echo '<td><input type="text" id="shqf-from-name" name="shqf_from_name" class="regular-text"';
		echo ' value="' . esc_attr( $from_name ) . '" placeholder="' . esc_attr( get_bloginfo( 'name' ) ) . '" />';
		echo '<p class="description">' . esc_html__( 'Name shown in the "From" field of emails. Leave blank to use the site name.', 'samplehq-request-form' ) . '</p></td></tr>';

		echo '</table>';
		echo '</div></div>';

		// Confirmation email settings.
		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Submitter Confirmation', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<table class="form-table">';

		echo '<tr><th>' . esc_html__( 'Confirmation Email', 'samplehq-request-form' ) . '</th>';
		echo '<td><label>';
		echo '<input type="checkbox" name="shqf_send_confirmation" value="1"' . checked( $send_confirmation, '1', false ) . ' />';
		echo ' ' . esc_html__( 'Send a confirmation email to the person who submitted the form.', 'samplehq-request-form' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'The confirmation includes their submitted data and a thank-you message. Individual forms can override this setting.', 'samplehq-request-form' ) . '</p>';
		echo '</td></tr>';

		echo '</table>';
		echo '</div></div>';

		echo '</div>';

		submit_button();
		echo '</form>';
	}

	/**
	 * Render the WooCommerce settings tab.
	 *
	 * @return void
	 */
	private function render_settings_woocommerce_tab(): void {
		$enabled        = get_option( 'shqf_woo_enabled', '' );
		$button_text    = get_option( 'shqf_woo_button_text', __( 'Request a Sample', 'samplehq-request-form' ) );
		$product_filter = get_option( 'shqf_woo_product_filter', 'all' );
		$sample_tag     = get_option( 'shqf_woo_sample_tag', 'sample-available' );
		$max_quantity   = (int) get_option( 'shqf_woo_max_quantity', 3 );

		echo '<form method="post" action="">';
		wp_nonce_field( 'shqf_save_settings', 'shqf_settings_nonce' );
		echo '<input type="hidden" name="shqf_settings_tab" value="woocommerce" />';

		echo '<div id="poststuff">';

		// Master toggle.
		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'WooCommerce Integration', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<table class="form-table">';

		echo '<tr><th>' . esc_html__( 'Enable', 'samplehq-request-form' ) . '</th>';
		echo '<td><label>';
		echo '<input type="checkbox" name="shqf_woo_enabled" value="1"' . checked( $enabled, '1', false ) . ' />';
		echo ' ' . esc_html__( 'Use WooCommerce products as your sample library.', 'samplehq-request-form' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'When enabled, forms with WooCommerce source will show your WC products instead of the built-in sample library.', 'samplehq-request-form' ) . '</p>';
		echo '</td></tr>';

		// Form selector.
		$form_id = (int) get_option( 'shqf_woo_form_id', 0 );
		echo '<tr><th><label for="shqf-woo-form">' . esc_html__( 'Form', 'samplehq-request-form' ) . '</label></th>';
		echo '<td><select id="shqf-woo-form" name="shqf_woo_form_id">';
		echo '<option value="0">' . esc_html__( 'Default (first published form)', 'samplehq-request-form' ) . '</option>';
		global $wpdb;
		$forms = ( new \SampleHQForm\Database\FormsTable( $wpdb ) )->list_all(
			[
				'status' => 'published',
				'limit'  => 50,
			]
		);
		foreach ( $forms as $f ) {
			echo '<option value="' . esc_attr( (string) $f['id'] ) . '"' . selected( $form_id, (int) $f['id'], false ) . '>';
			echo esc_html( $f['title'] ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Which form to show when the product page button is clicked.', 'samplehq-request-form' ) . '</p>';
		echo '</td></tr>';

		echo '</table>';
		echo '</div></div>';

		// Product page button.
		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Product Page Button', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<table class="form-table">';

		echo '<tr><th><label for="shqf-woo-btn-text">' . esc_html__( 'Button Text', 'samplehq-request-form' ) . '</label></th>';
		echo '<td><input type="text" id="shqf-woo-btn-text" name="shqf_woo_button_text" class="regular-text"';
		echo ' value="' . esc_attr( $button_text ) . '" />';
		echo '<p class="description">' . esc_html__( 'Label for the button on single product pages.', 'samplehq-request-form' ) . '</p>';
		echo '</td></tr>';

		echo '</table>';
		echo '</div></div>';

		// Shop loop badge settings.
		$show_badge = get_option( 'shqf_woo_show_loop_badge', '1' );
		$badge_text = get_option( 'shqf_woo_badge_text', __( 'Free sample available', 'samplehq-request-form' ) );

		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Shop Page Badge', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<table class="form-table">';

		echo '<tr><th>' . esc_html__( 'Show Badge', 'samplehq-request-form' ) . '</th>';
		echo '<td><label>';
		echo '<input type="checkbox" name="shqf_woo_show_loop_badge" value="1"' . checked( $show_badge, '1', false ) . ' />';
		echo ' ' . esc_html__( 'Show "Free sample available" badge on shop/archive product listings.', 'samplehq-request-form' );
		echo '</label></td></tr>';

		echo '<tr><th><label for="shqf-woo-badge-text">' . esc_html__( 'Badge Text', 'samplehq-request-form' ) . '</label></th>';
		echo '<td><input type="text" id="shqf-woo-badge-text" name="shqf_woo_badge_text" class="regular-text"';
		echo ' value="' . esc_attr( $badge_text ) . '" />';
		echo '<p class="description">' . esc_html__( 'Text shown below the Add to Cart button on shop pages.', 'samplehq-request-form' ) . '</p>';
		echo '</td></tr>';

		echo '</table>';
		echo '</div></div>';

		// Product filtering.
		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Product Filter', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<table class="form-table">';

		echo '<tr><th><label for="shqf-woo-filter">' . esc_html__( 'Which products', 'samplehq-request-form' ) . '</label></th>';
		echo '<td><select id="shqf-woo-filter" name="shqf_woo_product_filter">';
		$filters = [
			'all'      => __( 'All published products', 'samplehq-request-form' ),
			'tagged'   => __( 'Only products with a specific tag', 'samplehq-request-form' ),
			'category' => __( 'Only products in specific categories', 'samplehq-request-form' ),
		];
		foreach ( $filters as $filt_key => $filt_label ) {
			echo '<option value="' . esc_attr( $filt_key ) . '"' . selected( $product_filter, $filt_key, false ) . '>';
			echo esc_html( $filt_label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th><label for="shqf-woo-tag">' . esc_html__( 'Sample Tag', 'samplehq-request-form' ) . '</label></th>';
		echo '<td><input type="text" id="shqf-woo-tag" name="shqf_woo_sample_tag" class="regular-text"';
		echo ' value="' . esc_attr( $sample_tag ) . '" />';
		echo '<p class="description">' . esc_html__( 'Product tag slug that marks a product as sample-eligible (used when filter is "tagged").', 'samplehq-request-form' ) . '</p></td></tr>';

		// Product categories (for category filter mode).
		$selected_cats = (array) get_option( 'shqf_woo_sample_categories', [] );
		$wc_categories = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			]
		);
		if ( ! is_wp_error( $wc_categories ) && ! empty( $wc_categories ) ) {
			echo '<tr><th><label for="shqf-woo-cats">' . esc_html__( 'Sample Categories', 'samplehq-request-form' ) . '</label></th>';
			echo '<td><select id="shqf-woo-cats" name="shqf_woo_sample_categories[]" multiple style="min-width:300px;min-height:120px;">';
			foreach ( $wc_categories as $wc_cat ) {
				echo '<option value="' . esc_attr( (string) $wc_cat->term_id ) . '"' . selected( in_array( (int) $wc_cat->term_id, array_map( 'intval', $selected_cats ), true ), true, false ) . '>';
				echo esc_html( $wc_cat->name ) . '</option>';
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Product categories to include (used when filter is "category"). Hold Ctrl/Cmd to select multiple.', 'samplehq-request-form' ) . '</p>';
			echo '</td></tr>';
		}

		echo '<tr><th><label for="shqf-woo-max-qty">' . esc_html__( 'Max Quantity', 'samplehq-request-form' ) . '</label></th>';
		echo '<td><input type="number" id="shqf-woo-max-qty" name="shqf_woo_max_quantity" min="1" max="100" class="small-text"';
		echo ' value="' . esc_attr( (string) $max_quantity ) . '" />';
		echo '<p class="description">' . esc_html__( 'Maximum quantity per sample in the picker.', 'samplehq-request-form' ) . '</p></td></tr>';

		echo '</table>';
		echo '</div></div>';

		echo '</div>';

		submit_button();
		echo '</form>';
	}

	/**
	 * Set admin notice after enabling WC integration.
	 *
	 * @return void
	 */
	private function set_woo_enabled_notice(): void {
		AdminNotice::success(
			__( 'WooCommerce integration enabled. The "Request a Sample" button will now appear on eligible product pages.', 'samplehq-request-form' )
		);
	}

	/**
	 * Clear WooCommerce product source transient cache.
	 *
	 * @return void
	 */
	private function clear_woo_cache(): void {
		\SampleHQForm\Fields\ProductSource\WooCommerceSource::clear_cache();
	}

	/**
	 * Render the SampleHQ Connection settings tab.
	 *
	 * @return void
	 */
	private function render_settings_connection_tab(): void {
		$connection   = get_option( 'shqf_connection', [] );
		$is_connected = $this->connection_manager ? $this->connection_manager->is_connected() : ! empty( $connection['workspace_id'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['shqf_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			echo esc_html( sanitize_text_field( wp_unslash( $_GET['shqf_error'] ) ) );
			echo '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['shqf_connected'] ) && $is_connected ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Successfully connected to SampleHQ!', 'samplehq-request-form' );
			echo '</p></div>';
		}

		echo '<div id="poststuff">';

		if ( $is_connected ) {
			$this->render_connection_connected( $connection );
		} else {
			$this->render_connection_disconnected();
		}

		echo '</div>';
	}

	/**
	 * Render the connected state of the connection tab.
	 *
	 * @param array<string, mixed> $connection Connection data.
	 * @return void
	 */
	private function render_connection_connected( array $connection ): void {
		echo '<div class="shqf-connection-status shqf-connection-status--connected">';
		echo '<span class="dashicons dashicons-yes-alt"></span>';
		echo '<strong>' . esc_html__( 'Connected', 'samplehq-request-form' ) . '</strong>';
		echo '</div>';

		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Connection Details', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';

		echo '<table class="shqf-connection-details">';
		echo '<tr><th>' . esc_html__( 'Workspace', 'samplehq-request-form' ) . '</th>';
		echo '<td>' . esc_html( $connection['workspace_name'] ?? '' ) . '</td></tr>';

		if ( ! empty( $connection['workspace_url'] ) ) {
			echo '<tr><th>' . esc_html__( 'URL', 'samplehq-request-form' ) . '</th>';
			echo '<td><a href="' . esc_url( $connection['workspace_url'] ) . '" target="_blank" rel="noopener">';
			echo esc_html( $connection['workspace_url'] ) . '</a></td></tr>';
		}

		if ( ! empty( $connection['connected_by'] ) ) {
			echo '<tr><th>' . esc_html__( 'Connected by', 'samplehq-request-form' ) . '</th>';
			echo '<td>' . esc_html( $connection['connected_by'] ) . '</td></tr>';
		}

		if ( ! empty( $connection['connected_at'] ) ) {
			$date = wp_date( get_option( 'date_format' ), (int) $connection['connected_at'] );
			echo '<tr><th>' . esc_html__( 'Connected on', 'samplehq-request-form' ) . '</th>';
			echo '<td>' . esc_html( (string) $date ) . '</td></tr>';
		}
		echo '</table>';

		echo '<p style="margin-top:16px;">';
		echo '<a href="' . esc_url(
			wp_nonce_url(
				admin_url( 'admin.php?page=shqf-settings&tab=connection&action=disconnect' ),
				'shqf_disconnect'
			)
		) . '" class="button" data-shqf-confirm="' . esc_attr__( 'Disconnect from SampleHQ? Submission sync will stop.', 'samplehq-request-form' ) . '">';
		echo esc_html__( 'Disconnect', 'samplehq-request-form' ) . '</a>';
		echo '</p>';

		echo '</div></div>';
	}

	/**
	 * Render the Migration settings tab.
	 *
	 * @return void
	 */
	private function render_settings_migration_tab(): void {
		$rest_url = rest_url( 'samplehq-form/v1/migration/' );
		$nonce    = wp_create_nonce( 'wp_rest' );

		echo '<div id="poststuff">';
		echo '<div id="shqf-migration-wizard" data-rest-url="' . esc_attr( $rest_url ) . '" data-nonce="' . esc_attr( $nonce ) . '">';

		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Migration Wizard', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';

		// Step 1: Preview (default visible).
		echo '<div id="shqf-mig-preview">';
		echo '<p>' . esc_html__( 'Migrate your categories, samples, and optionally submissions to your SampleHQ workspace.', 'samplehq-request-form' ) . '</p>';
		echo '<p>' . esc_html__( 'Loading preview...', 'samplehq-request-form' ) . '</p>';
		echo '</div>';

		// Step 2: Running (hidden).
		echo '<div id="shqf-mig-running" style="display:none;">';
		echo '<p><strong id="shqf-mig-phase">' . esc_html__( 'Starting...', 'samplehq-request-form' ) . '</strong></p>';
		echo '<div class="shqf-progress-bar" style="background:#e0e0e0;border-radius:4px;height:24px;margin:16px 0;">';
		echo '<div id="shqf-mig-bar" style="background:#2271b1;height:100%;border-radius:4px;width:0%;transition:width .3s;"></div>';
		echo '</div>';
		echo '<p id="shqf-mig-detail"></p>';
		echo '<p style="margin-top:16px;"><button type="button" id="shqf-mig-cancel" class="button">' . esc_html__( 'Cancel', 'samplehq-request-form' ) . '</button></p>';
		echo '</div>';

		// Step 3: Complete (hidden).
		echo '<div id="shqf-mig-complete" style="display:none;">';
		echo '<div class="notice notice-success inline"><p><strong>' . esc_html__( 'Migration complete.', 'samplehq-request-form' ) . '</strong></p></div>';
		echo '<div id="shqf-mig-summary"></div>';
		echo '</div>';

		echo '</div></div>';
		echo '</div>';
		echo '</div>';

		$this->render_migration_js();
	}

	/**
	 * Enqueue the migration-wizard script with localized strings.
	 *
	 * @return void
	 */
	private function render_migration_js(): void {
		wp_enqueue_script(
			'shqf-migration-wizard',
			SHQF_URL . 'assets/build/migration-wizard.js',
			[],
			SHQF_VERSION,
			true
		);

		wp_localize_script(
			'shqf-migration-wizard',
			'shqfMigration',
			[
				'migrateDescription' => __( 'Migrate your categories, samples, and optionally submissions to your SampleHQ workspace.', 'samplehq-request-form' ),
				'categories'         => __( 'Categories', 'samplehq-request-form' ),
				'samples'            => __( 'Samples', 'samplehq-request-form' ),
				'submissions'        => __( 'Submissions', 'samplehq-request-form' ),
				'planLimit'          => __( 'plan limit:', 'samplehq-request-form' ),
				'available'          => __( 'available', 'samplehq-request-form' ),
				'alsoMigrateSubs'    => __( 'Also migrate submissions', 'samplehq-request-form' ),
				'startMigration'     => __( 'Start Migration', 'samplehq-request-form' ),
				'nothingToMigrate'   => __( 'Nothing to migrate. Your sample library is empty.', 'samplehq-request-form' ),
				'failedPreview'      => __( 'Failed to load preview. Please reload the page.', 'samplehq-request-form' ),
				'alreadyRunning'     => __( 'Migration already running...', 'samplehq-request-form' ),
				'lostConnection'     => __( 'Lost connection. Please reload the page.', 'samplehq-request-form' ),
				'migratingCats'      => __( 'Migrating categories...', 'samplehq-request-form' ),
				'migratingSamples'   => __( 'Migrating samples...', 'samplehq-request-form' ),
				'migratingSubs'      => __( 'Migrating submissions...', 'samplehq-request-form' ),
				'migrationError'     => __( 'Migration error', 'samplehq-request-form' ),
				'migrationCancelled' => __( 'Migration cancelled', 'samplehq-request-form' ),
				'catsCreated'        => __( 'Categories created', 'samplehq-request-form' ),
				'catsUpdated'        => __( 'Categories updated', 'samplehq-request-form' ),
				'samplesCreated'     => __( 'Samples created', 'samplehq-request-form' ),
				'samplesUpdated'     => __( 'Samples updated', 'samplehq-request-form' ),
				'samplesSkipped'     => __( 'Samples skipped', 'samplehq-request-form' ),
				'subsAccepted'       => __( 'Submissions accepted', 'samplehq-request-form' ),
				'subsDuplicates'     => __( 'Submissions duplicates', 'samplehq-request-form' ),
				'planLimitReached'   => __( 'Plan limit reached. Not all samples could be migrated.', 'samplehq-request-form' ),
				'errorsOccurred'     => __( 'error(s) occurred during migration.', 'samplehq-request-form' ),
				'cancelled'          => __( 'Migration cancelled.', 'samplehq-request-form' ),
			]
		);
	}

	/**
	 * Render the disconnected connection state.
	 *
	 * @return void
	 */
	private function render_connection_disconnected(): void {
		echo '<div class="shqf-connection-status shqf-connection-status--disconnected">';
		echo '<span class="dashicons dashicons-admin-plugins"></span>';
		echo '<strong>' . esc_html__( 'Not connected', 'samplehq-request-form' ) . '</strong>';
		echo '</div>';

		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Connect to SampleHQ', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';

		echo '<p>' . esc_html__( 'Connect this plugin to your SampleHQ workspace to sync submissions to the cloud platform. If you do not have an account yet, one will be created during the connection process.', 'samplehq-request-form' ) . '</p>';

		echo '<p>' . esc_html__( 'When connected, new submissions are saved locally and then pushed to your workspace where you can manage them alongside CRM integrations, shipping, and team workflows.', 'samplehq-request-form' ) . '</p>';

		$connect_action_url = wp_nonce_url(
			admin_url( 'admin.php?page=shqf-settings&tab=connection&action=connect' ),
			'shqf_connect'
		);

		echo '<p style="margin-top:16px;">';
		echo '<a href="' . esc_url( $connect_action_url ) . '" class="button button-primary">';
		echo esc_html__( 'Connect to SampleHQ', 'samplehq-request-form' ) . '</a>';
		echo '</p>';

		echo '<p class="description" style="margin-top:12px;">';
		echo esc_html__( 'The plugin works fully without connecting. Connecting enables cloud sync for submissions.', 'samplehq-request-form' );
		echo '</p>';

		echo '</div></div>';
	}
}
