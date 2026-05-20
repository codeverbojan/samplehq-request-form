<?php
/**
 * Admin menu registration.
 *
 * @package SampleHQForm\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Admin;

use SampleHQForm\Connection\ConnectionManager;
use SampleHQForm\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's admin menu and submenus.
 *
 * Menu structure per SPEC.md Section 5.1:
 * SampleHQ Forms (top-level, dashicons-clipboard)
 *   |-- Dashboard
 *   |-- Sample Library
 *   |-- Forms
 *   |-- Submissions
 *   |-- Settings
 */
class AdminMenu {

	/**
	 * Required capability for accessing the plugin admin pages.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Top-level menu slug.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'shqf-dashboard';

	/**
	 * Dashboard page controller.
	 *
	 * @var DashboardPage
	 */
	private DashboardPage $dashboard;

	/**
	 * Settings page controller.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings;

	/**
	 * Categories page controller.
	 *
	 * @var CategoriesPage
	 */
	private CategoriesPage $categories_page;

	/**
	 * Samples page controller.
	 *
	 * @var SamplesPage
	 */
	private SamplesPage $samples_page;

	/**
	 * Forms page controller.
	 *
	 * @var FormsPage
	 */
	private FormsPage $forms_page;

	/**
	 * Submissions page controller.
	 *
	 * @var SubmissionsPage
	 */
	private SubmissionsPage $submissions_page;

	/**
	 * Connection manager for platform connect/disconnect.
	 *
	 * @var ?ConnectionManager
	 */
	private ?ConnectionManager $connection_manager;

	/**
	 * Constructor.
	 *
	 * @param ?\SampleHQForm\Forms\FormRenderer $renderer           Form renderer.
	 * @param ?\SampleHQForm\Spam\FormToken     $form_token         Form token handler.
	 * @param ?ConnectionManager                $connection_manager Connection manager.
	 */
	public function __construct( ?\SampleHQForm\Forms\FormRenderer $renderer = null, ?\SampleHQForm\Spam\FormToken $form_token = null, ?ConnectionManager $connection_manager = null ) {
		$this->connection_manager = $connection_manager;
		$this->dashboard          = new DashboardPage();
		$this->settings           = new SettingsPage( $connection_manager );
		$this->categories_page    = new CategoriesPage();
		$this->samples_page       = new SamplesPage( $this->categories_page );
		$this->forms_page         = new FormsPage( $renderer, $form_token );
		$this->submissions_page   = new SubmissionsPage();
	}

	/**
	 * Register the admin menu hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menus' ] );
		add_action( 'admin_init', [ $this, 'handle_early_redirects' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_filter( 'set-screen-option', [ $this, 'save_screen_option' ], 10, 3 );
		add_action( 'wp_ajax_shqf_check_connection', [ $this, 'ajax_check_connection' ] );
	}

	/**
	 * Enqueue admin CSS on plugin pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $hook_suffix required by admin_enqueue_scripts hook signature.
		// Only load on our plugin pages.
		$screen = get_current_screen();
		if ( null === $screen || false === strpos( $screen->id, 'shqf' ) ) {
			return;
		}

		wp_enqueue_style(
			'shqf-admin',
			SHQF_URL . 'assets/build/css/admin/admin.css',
			[],
			SHQF_VERSION
		);

		wp_enqueue_script(
			'shqf-admin-utils',
			SHQF_URL . 'assets/build/admin-utils.js',
			[],
			SHQF_VERSION,
			true
		);
	}

	/**
	 * Register per-page screen option for samples list.
	 *
	 * @return void
	 */
	public function add_samples_screen_options(): void {
		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Samples per page', 'samplehq-request-form' ),
				'default' => 20,
				'option'  => 'shqf_samples_per_page',
			]
		);
	}

	/**
	 * Register per-page screen option for forms list.
	 *
	 * @return void
	 */
	public function add_forms_screen_options(): void {
		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Forms per page', 'samplehq-request-form' ),
				'default' => 20,
				'option'  => 'shqf_forms_per_page',
			]
		);
	}

	/**
	 * Register per-page screen option for submissions list.
	 *
	 * @return void
	 */
	public function add_submissions_screen_options(): void {
		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Submissions per page', 'samplehq-request-form' ),
				'default' => 20,
				'option'  => 'shqf_submissions_per_page',
			]
		);
	}

	/**
	 * Allow saving our custom screen options.
	 *
	 * @param mixed  $status Screen option value (false to skip).
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return mixed
	 */
	public function save_screen_option( $status, string $option, $value ) {
		$our_options = [ 'shqf_samples_per_page', 'shqf_forms_per_page', 'shqf_submissions_per_page' ];
		if ( in_array( $option, $our_options, true ) ) {
			return (int) $value;
		}
		return $status;
	}

	/**
	 * Handle redirects that must happen before page output.
	 *
	 * Called on admin_init, before any HTML is rendered.
	 *
	 * @return void
	 */
	public function handle_early_redirects(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );

		// Create new form from template -> redirect to edit.
		if ( 'shqf-forms' === $page && 'new' === $action ) {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You do not have permission to create forms.', 'samplehq-request-form' ) );
			}

			check_admin_referer( 'shqf_new_form' );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$template_slug = sanitize_key( wp_unslash( $_GET['template'] ?? 'blank' ) );
			$template      = \SampleHQForm\Forms\FormTemplates::get( $template_slug );

			global $wpdb;
			$forms_table = new \SampleHQForm\Database\FormsTable( $wpdb );

			try {
				$form_data = [
					'title'      => $template ? $template['form_title'] : __( 'Untitled Form', 'samplehq-request-form' ),
					'created_by' => get_current_user_id(),
				];

				if ( $template && ! empty( $template['config'] ) ) {
					$form_data['config'] = $template['config'];
				}

				$new_id = $forms_table->create( $form_data );
				AdminNotice::success( __( 'Form created.', 'samplehq-request-form' ) );
				wp_safe_redirect( admin_url( 'admin.php?page=shqf-forms&action=edit&id=' . $new_id ) );
				exit;
			} catch ( \Exception $e ) {
				AdminNotice::error( __( 'Failed to create form. Please try again.', 'samplehq-request-form' ) );
				wp_safe_redirect( admin_url( 'admin.php?page=shqf-forms' ) );
				exit;
			}
		}

		// Sample save (POST redirect must happen before output).
		if ( 'shqf-samples' === $page && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
			if ( current_user_can( self::CAPABILITY ) ) {
				$this->samples_page->handle_save();
			}
		}

		// Settings save (POST redirect must happen before output).
		if ( 'shqf-settings' === $page && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
			if ( current_user_can( self::CAPABILITY ) ) {
				$this->settings->handle_save();
			}
		}

		// Category save/delete (POST redirect must happen before output).
		if ( 'shqf-categories' === $page || ( 'shqf-samples' === $page && 'categories' === $action ) ) {
			if ( current_user_can( self::CAPABILITY ) ) {
				$this->categories_page->handle_save();
			}
		}

		// CSV export for submissions (must be before the shqf-forms guard).
		if ( 'shqf-submissions' === $page && 'export_csv' === $action ) {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'samplehq-request-form' ) );
			}
			check_admin_referer( 'shqf_export_submissions' );
			$this->submissions_page->handle_csv_export();
		}

		// Sample single-row actions (archive, trash, restore, delete_permanently).
		if ( 'shqf-samples' === $page && in_array( $action, [ 'archive', 'restore', 'trash', 'delete' ], true ) ) {
			$this->samples_page->handle_action( $action );
		}

		// Dismiss SampleHQ CTA.
		if ( 'shqf-dashboard' === $page && 'dismiss_shq_cta' === $action ) {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'samplehq-request-form' ) );
			}
			check_admin_referer( 'shqf_dismiss_cta' );
			update_user_meta( get_current_user_id(), 'shqf_dismissed_cta', '1' );
			wp_safe_redirect( admin_url( 'admin.php?page=shqf-dashboard' ) );
			exit;
		}

		// Initiate SampleHQ connection (redirect to platform).
		if ( 'shqf-settings' === $page && 'connect' === $action && null !== $this->connection_manager ) {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'samplehq-request-form' ) );
			}
			check_admin_referer( 'shqf_connect' );
			$connect_url = $this->connection_manager->get_connect_url( wp_get_current_user() );
			Plugin::allow_redirect_host( $connect_url );
			wp_safe_redirect( $connect_url );
			exit;
		}

		// Disconnect from SampleHQ.
		if ( 'shqf-settings' === $page && 'disconnect' === $action ) {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'Unauthorized.', 'samplehq-request-form' ) );
			}
			check_admin_referer( 'shqf_disconnect' );
			if ( null !== $this->connection_manager ) {
				$this->connection_manager->disconnect();
			}
			AdminNotice::success( __( 'Disconnected from SampleHQ.', 'samplehq-request-form' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=shqf-settings&tab=connection' ) );
			exit;
		}

		// Submission single-row actions (star, spam, trash, restore, delete) -- must run before output.
		if ( 'shqf-submissions' === $page && ! empty( $action ) && 'view' !== $action && 'export_csv' !== $action ) {
			$this->submissions_page->handle_actions();
		}

		if ( 'shqf-forms' !== $page ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage forms.', 'samplehq-request-form' ) );
		}

		// Bulk actions (POST from list table).
		$this->forms_page->handle_bulk_action();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = absint( $_GET['id'] ?? 0 );

		// Duplicate form.
		if ( 'duplicate' === $action && $id > 0 ) {
			check_admin_referer( 'shqf_duplicate_form_' . $id );

			global $wpdb;
			$forms_table = new \SampleHQForm\Database\FormsTable( $wpdb );
			$original    = $forms_table->get( $id );

			if ( null === $original ) {
				AdminNotice::error( __( 'Form not found.', 'samplehq-request-form' ) );
				wp_safe_redirect( admin_url( 'admin.php?page=shqf-forms' ) );
				exit;
			}

			try {
				/* translators: %s: original form title */
				$new_title = sprintf( __( 'Copy of %s', 'samplehq-request-form' ), $original['title'] ?? '' );
				$new_id    = $forms_table->create(
					[
						'title'      => $new_title,
						'config'     => $original['config'] ?? [],
						'status'     => 'draft',
						'created_by' => get_current_user_id(),
					]
				);
				AdminNotice::success( __( 'Form duplicated.', 'samplehq-request-form' ) );
				wp_safe_redirect( admin_url( 'admin.php?page=shqf-forms&action=edit&id=' . $new_id ) );
				exit;
			} catch ( \Exception $e ) {
				AdminNotice::error( __( 'Failed to duplicate form.', 'samplehq-request-form' ) );
				wp_safe_redirect( admin_url( 'admin.php?page=shqf-forms' ) );
				exit;
			}
		}

		// Export form as JSON.
		if ( 'export' === $action && $id > 0 ) {
			check_admin_referer( 'shqf_export_form_' . $id );

			global $wpdb;
			$forms_table = new \SampleHQForm\Database\FormsTable( $wpdb );
			$form        = $forms_table->get( $id );

			if ( null === $form ) {
				AdminNotice::error( __( 'Form not found.', 'samplehq-request-form' ) );
				wp_safe_redirect( admin_url( 'admin.php?page=shqf-forms' ) );
				exit;
			}

			$export = [
				'plugin'  => 'samplehq-request-form',
				'version' => SHQF_VERSION,
				'form'    => [
					'title'  => $form['title'] ?? '',
					'config' => $form['config'] ?? [],
				],
			];

			$filename = sanitize_file_name( ( $form['slug'] ?? 'form' ) . '-export.json' );

			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Cache-Control: no-cache, no-store, must-revalidate' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			exit;
		}

		// Trash form (soft delete).
		if ( 'trash' === $action && $id > 0 ) {
			check_admin_referer( 'shqf_trash_form_' . $id );

			global $wpdb;
			$forms_table = new \SampleHQForm\Database\FormsTable( $wpdb );
			$forms_table->update( $id, [ 'status' => 'trash' ] );

			if ( (int) get_option( 'shqf_woo_form_id', 0 ) === $id ) {
				delete_option( 'shqf_woo_form_id' );
			}

			AdminNotice::success( __( 'Form moved to trash.', 'samplehq-request-form' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=shqf-forms' ) );
			exit;
		}

		// Restore form from trash.
		if ( 'restore' === $action && $id > 0 ) {
			check_admin_referer( 'shqf_restore_form_' . $id );

			global $wpdb;
			$forms_table = new \SampleHQForm\Database\FormsTable( $wpdb );
			$forms_table->update( $id, [ 'status' => 'draft' ] );

			AdminNotice::success( __( 'Form restored.', 'samplehq-request-form' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=shqf-forms' ) );
			exit;
		}

		// Delete form permanently (only from trash).
		if ( 'delete' === $action && $id > 0 ) {
			check_admin_referer( 'shqf_delete_form_' . $id );

			global $wpdb;
			$forms_table       = new \SampleHQForm\Database\FormsTable( $wpdb );
			$submissions_table = new \SampleHQForm\Database\SubmissionsTable( $wpdb );
			$submission_meta   = new \SampleHQForm\Database\SubmissionMetaTable( $wpdb );
			$rate_limits       = new \SampleHQForm\Database\RateLimitsTable( $wpdb );

			// Cascade: delete submissions + meta + rate limits for this form.
			$max_batches = 200;
			do {
				$form_submissions = $submissions_table->list_all(
					[
						'form_id' => $id,
						'limit'   => 500,
					]
				);
				foreach ( $form_submissions as $sub ) {
					$submission_meta->delete_all( (int) $sub['id'] );
					$submissions_table->delete( (int) $sub['id'] );
				}
				--$max_batches;
			} while ( ! empty( $form_submissions ) && $max_batches > 0 );
			$rate_limits->clear_for_form( $id );
			$forms_table->delete( $id );

			if ( (int) get_option( 'shqf_woo_form_id', 0 ) === $id ) {
				delete_option( 'shqf_woo_form_id' );
			}

			AdminNotice::success( __( 'Form permanently deleted.', 'samplehq-request-form' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=shqf-forms' ) );
			exit;
		}
	}

	/**
	 * Add the top-level menu and submenus.
	 *
	 * @return void
	 */
	public function add_menus(): void {
		add_menu_page(
			__( 'SampleHQ Forms', 'samplehq-request-form' ),
			__( 'SampleHQ Forms', 'samplehq-request-form' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this->dashboard, 'render' ],
			'dashicons-clipboard',
			30
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'samplehq-request-form' ),
			__( 'Dashboard', 'samplehq-request-form' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this->dashboard, 'render' ]
		);

		$hook_samples = add_submenu_page(
			self::MENU_SLUG,
			__( 'Sample Library', 'samplehq-request-form' ),
			__( 'Sample Library', 'samplehq-request-form' ),
			self::CAPABILITY,
			'shqf-samples',
			[ $this->samples_page, 'render' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Categories', 'samplehq-request-form' ),
			__( 'Categories', 'samplehq-request-form' ),
			self::CAPABILITY,
			'shqf-categories',
			[ $this->categories_page, 'render' ]
		);

		$hook_forms = add_submenu_page(
			self::MENU_SLUG,
			__( 'Forms', 'samplehq-request-form' ),
			__( 'Forms', 'samplehq-request-form' ),
			self::CAPABILITY,
			'shqf-forms',
			[ $this->forms_page, 'render' ]
		);

		// Submissions menu with unread count badge.
		$submissions_label = __( 'Submissions', 'samplehq-request-form' );
		$unread_count      = $this->dashboard->get_unread_submission_count();
		if ( $unread_count > 0 ) {
			$submissions_label .= ' <span class="awaiting-mod count-' . esc_attr( (string) $unread_count ) . '">'
				. '<span class="pending-count">' . number_format_i18n( $unread_count ) . '</span></span>';
		}

		$hook_submissions = add_submenu_page(
			self::MENU_SLUG,
			__( 'Submissions', 'samplehq-request-form' ),
			$submissions_label,
			self::CAPABILITY,
			'shqf-submissions',
			[ $this->submissions_page, 'render' ]
		);

		// Register screen options for per-page settings on list table pages.
		if ( $hook_samples ) {
			add_action( "load-{$hook_samples}", [ $this, 'add_samples_screen_options' ] );
		}
		if ( $hook_forms ) {
			add_action( "load-{$hook_forms}", [ $this, 'add_forms_screen_options' ] );
		}
		if ( $hook_submissions ) {
			add_action( "load-{$hook_submissions}", [ $this, 'add_submissions_screen_options' ] );
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'samplehq-request-form' ),
			__( 'Settings', 'samplehq-request-form' ),
			self::CAPABILITY,
			'shqf-settings',
			[ $this->settings, 'render' ]
		);
	}

	/**
	 * AJAX handler: check whether the site is connected to SampleHQ.
	 *
	 * @return void
	 */
	public function ajax_check_connection(): void {
		check_ajax_referer( 'shqf_connection_poll' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$connected = $this->connection_manager instanceof ConnectionManager
			&& $this->connection_manager->is_connected();

		wp_send_json_success( [ 'connected' => $connected ] );
	}
}
