<?php
/**
 * Forms admin page.
 *
 * Extracted from AdminMenu.php during Phase 5 refactoring.
 * Source: AdminMenu.php.backup lines 1293-1356, 1363-1440, 1452-1491, 1501-1551, 1561-1642, 2227-2339.
 *
 * @package SampleHQForm\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the forms list, form builder, preview, template selector, and import pages.
 * Handles form bulk actions.
 */
class FormsPage {

	/**
	 * Form renderer for preview.
	 *
	 * @var ?\SampleHQForm\Forms\FormRenderer
	 */
	private ?\SampleHQForm\Forms\FormRenderer $renderer;

	/**
	 * Form token for preview CSRF.
	 *
	 * @var ?\SampleHQForm\Spam\FormToken
	 */
	private ?\SampleHQForm\Spam\FormToken $form_token;

	/**
	 * Constructor.
	 *
	 * @param ?\SampleHQForm\Forms\FormRenderer $renderer   Form renderer.
	 * @param ?\SampleHQForm\Spam\FormToken     $form_token Form token handler.
	 */
	public function __construct( ?\SampleHQForm\Forms\FormRenderer $renderer = null, ?\SampleHQForm\Spam\FormToken $form_token = null ) {
		$this->renderer   = $renderer;
		$this->form_token = $form_token;
	}

	/**
	 * Render the forms page.
	 *
	 * @return void
	 */
	public function render(): void {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );

		$forms_table = new \SampleHQForm\Database\FormsTable( $wpdb );

		// Form preview.
		if ( 'preview' === $action ) {
			$this->render_form_preview();
			return;
		}

		// "new", "duplicate", "export", "delete" handled by handle_early_redirects().

		// Template selector (choose a template before creating a new form).
		if ( 'templates' === $action ) {
			$this->render_template_selector();
			return;
		}

		// Form import.
		if ( 'import' === $action ) {
			$this->render_form_import( $forms_table );
			return;
		}

		// Form builder (edit).
		if ( 'edit' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$form_id = absint( $_GET['id'] ?? 0 );
			$this->render_form_builder( $forms_table, $form_id );
			return;
		}

		$list_table = new FormListTable( $forms_table );
		$list_table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Forms', 'samplehq-request-form' ) . '</h1>';
		$templates_url = admin_url( 'admin.php?page=shqf-forms&action=templates' );
		echo ' <a href="' . esc_url( $templates_url ) . '" class="page-title-action">';
		echo esc_html__( 'Add New', 'samplehq-request-form' ) . '</a>';
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=shqf-forms&action=import' ) ) . '" class="page-title-action">';
		echo esc_html__( 'Import', 'samplehq-request-form' ) . '</a>';
		echo '<hr class="wp-header-end">';
		AdminNotice::render();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );

		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="shqf-forms" />';
		if ( ! empty( $current_status ) ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $current_status ) . '" />';
		}
		wp_nonce_field( 'shqf_bulk_forms', '_shqf_bulk_nonce' );
		$list_table->views();
		$list_table->search_box( __( 'Search Forms', 'samplehq-request-form' ), 'shqf-form-search' );
		$list_table->display();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle form bulk actions (Publish, Draft, Trash, Restore, Delete).
	 *
	 * @return void
	 */
	public function handle_bulk_action(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$bulk_action = sanitize_text_field( wp_unslash( $_POST['action'] ?? '' ) );
		if ( '-1' === $bulk_action || '' === $bulk_action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$bulk_action = sanitize_text_field( wp_unslash( $_POST['action2'] ?? '' ) );
		}
		if ( '-1' === $bulk_action || '' === $bulk_action ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$form_ids = array_map( 'absint', (array) ( $_POST['form_ids'] ?? [] ) );
		$form_ids = array_filter( $form_ids );
		if ( empty( $form_ids ) ) {
			return;
		}

		check_admin_referer( 'shqf_bulk_forms', '_shqf_bulk_nonce' );

		global $wpdb;
		$forms_table = new \SampleHQForm\Database\FormsTable( $wpdb );

		$count      = 0;
		$status_map = [
			'publish' => 'published',
			'draft'   => 'draft',
			'trash'   => 'trash',
			'restore' => 'draft',
		];

		if ( isset( $status_map[ $bulk_action ] ) ) {
			$new_status = $status_map[ $bulk_action ];
			foreach ( $form_ids as $fid ) {
				$forms_table->update( $fid, [ 'status' => $new_status ] );
				++$count;
			}
		} elseif ( 'delete_permanent' === $bulk_action ) {
			$submissions_table = new \SampleHQForm\Database\SubmissionsTable( $wpdb );
			$submission_meta   = new \SampleHQForm\Database\SubmissionMetaTable( $wpdb );
			$rate_limits       = new \SampleHQForm\Database\RateLimitsTable( $wpdb );

			foreach ( $form_ids as $fid ) {
				// Delete in batches to handle forms with many submissions.
				do {
					$subs = $submissions_table->list_all(
						[
							'form_id' => $fid,
							'limit'   => 500,
						]
					);
					foreach ( $subs as $sub ) {
						$submission_meta->delete_all( (int) $sub['id'] );
						$submissions_table->delete( (int) $sub['id'] );
					}
				} while ( ! empty( $subs ) );

				$rate_limits->clear_for_form( $fid );
				$forms_table->delete( $fid );
				++$count;
			}
		}

		if ( $count > 0 ) {
			/* translators: %d: number of forms affected */
			AdminNotice::success( sprintf( __( '%d form(s) updated.', 'samplehq-request-form' ), $count ) );
		}

		// Preserve status view after redirect.
		$redirect_url = admin_url( 'admin.php?page=shqf-forms' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$redirect_status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
		if ( ! empty( $redirect_status ) ) {
			$redirect_url = add_query_arg( 'status', $redirect_status, $redirect_url );
		}
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render the React form builder page.
	 *
	 * @param \SampleHQForm\Database\FormsTable $forms   Forms repository.
	 * @param int                               $form_id Form ID.
	 * @return void
	 */
	private function render_form_builder( \SampleHQForm\Database\FormsTable $forms, int $form_id ): void {
		$form = $forms->get( $form_id );

		if ( null === $form ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>';
			echo esc_html__( 'Form not found.', 'samplehq-request-form' );
			echo '</p></div></div>';
			return;
		}

		// Enqueue the form builder React app + Google Fonts for accurate previews.
		\SampleHQForm\Helpers\Assets::enqueue_admin_script( 'form-builder' );
		\SampleHQForm\Helpers\Assets::enqueue_google_fonts();
		wp_enqueue_media();

		$config_json = wp_json_encode( $form['config'] ?? [] );

		// Pass form metadata for the builder's sidebar.
		$form_meta = wp_json_encode(
			[
				'status'            => $form['status'] ?? 'draft',
				'slug'              => $form['slug'] ?? '',
				'submissions_count' => (int) ( $form['submissions_count'] ?? 0 ),
				'shortcode'         => '[samplehq_form id="' . $form_id . '"]',
				'preview_url'       => admin_url( 'admin.php?page=shqf-forms&action=preview&id=' . $form_id ),
				'back_url'          => admin_url( 'admin.php?page=shqf-forms' ),
			]
		);

		// Clean page -- no WP postbox wrapper. The React builder owns the layout.
		echo '<div class="wrap shqf-builder-wrap">';
		AdminNotice::render();
		$woo_enabled = \SampleHQForm\WooCommerce\WooDetector::is_active() && get_option( 'shqf_woo_enabled' );

		echo '<div id="shqf-form-builder-root"';
		echo ' data-form-id="' . esc_attr( (string) $form_id ) . '"';
		echo ' data-form-title="' . esc_attr( $form['title'] ?? '' ) . '"';
		echo ' data-config="' . esc_attr( (string) $config_json ) . '"';
		echo ' data-form-meta="' . esc_attr( (string) $form_meta ) . '"';
		echo ' data-woo-enabled="' . esc_attr( $woo_enabled ? '1' : '0' ) . '"';
		echo '></div>';
		echo '</div>';
	}

	/**
	 * Render a standalone form preview page.
	 *
	 * @return void
	 */
	private function render_form_preview(): void {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = absint( $_GET['id'] ?? 0 );

		if ( 0 === $form_id ) {
			echo '<div class="wrap"><p>' . esc_html__( 'No form ID specified.', 'samplehq-request-form' ) . '</p></div>';
			return;
		}

		$forms_table = new \SampleHQForm\Database\FormsTable( $wpdb );
		$form        = $forms_table->get( $form_id );

		if ( null === $form ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Form not found.', 'samplehq-request-form' ) . '</p></div>';
			return;
		}

		$config     = $form['config'] ?? [];
		$renderer   = $this->renderer ?? new \SampleHQForm\Forms\FormRenderer( new \SampleHQForm\Fields\FieldRegistry() );
		$form_token = $this->form_token ?? new \SampleHQForm\Spam\FormToken();
		$token      = $form_token->generate( $form_id );
		$action_url = rest_url( 'samplehq-form/v1/submissions' );

		\SampleHQForm\Helpers\Assets::enqueue_google_fonts();
		\SampleHQForm\Helpers\Assets::enqueue_public_script( 'form-frontend' );
		\SampleHQForm\Helpers\Assets::enqueue_public_style( 'form-frontend' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Form Preview', 'samplehq-request-form' ) . '</h1>';
		echo '<p class="description">';
		echo esc_html__( 'This is how the form appears to visitors.', 'samplehq-request-form' );
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=shqf-forms' ) ) . '">';
		echo esc_html__( 'Back to Forms', 'samplehq-request-form' ) . '</a></p>';
		echo '<hr>';
		echo '<div style="max-width:640px;margin:2rem auto;">';
		// FormRenderer handles all escaping internally.
		$form_html = $renderer->render(
			$form_id,
			$config,
			[
				'token'      => $token,
				'action_url' => $action_url,
			]
		);
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $form_html;
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render the template selector page.
	 *
	 * @return void
	 */
	private function render_template_selector(): void {
		$templates = \SampleHQForm\Forms\FormTemplates::get_all();

		// SVG icons for each template (inline, no external dependencies).
		$icons = [
			'layers'      => '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>',
			'layout-grid' => '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg>',
			'list'        => '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>',
			'plus'        => '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>',
		];

		// Short labels shown under the icon.
		$icon_labels = [
			'wizard'    => __( 'Multi-Step', 'samplehq-request-form' ),
			'grid'      => __( 'Card Grid', 'samplehq-request-form' ),
			'checklist' => __( 'Checklist', 'samplehq-request-form' ),
			'blank'     => __( 'Blank', 'samplehq-request-form' ),
		];

		echo '<div class="wrap shqf-template-selector">';
		echo '<div class="shqf-template-header">';
		echo '<h1>' . esc_html__( 'Choose a Template', 'samplehq-request-form' ) . '</h1>';
		echo '<p>' . esc_html__( 'Pick a pre-built form template to get started, or start from scratch.', 'samplehq-request-form' ) . '</p>';
		echo '</div>';

		echo '<div class="shqf-template-grid">';

		$is_first = true;
		foreach ( $templates as $slug => $template ) {
			$is_blank    = 'blank' === $slug;
			$card_class  = 'shqf-template-card';
			$card_class .= $is_blank ? ' shqf-template-card--blank' : '';

			$create_url = wp_nonce_url(
				admin_url( 'admin.php?page=shqf-forms&action=new&template=' . $slug ),
				'shqf_new_form'
			);

			$icon_svg = $icons[ $template['icon'] ] ?? $icons['plus'];

			echo '<div class="' . esc_attr( $card_class ) . '">';

			// Icon area.
			$icon_class = $is_blank ? 'shqf-template-icon shqf-template-icon--blank' : 'shqf-template-icon';
			echo '<div class="' . esc_attr( $icon_class ) . '">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG is hardcoded above, not user input.
			echo $icon_svg;
			echo '<span class="shqf-template-icon-label">' . esc_html( $icon_labels[ $slug ] ?? '' ) . '</span>';
			echo '</div>';

			// Body.
			echo '<div class="shqf-template-body">';
			echo '<h3 class="shqf-template-title">' . esc_html( $template['title'] ) . '</h3>';
			echo '<p class="shqf-template-desc">' . esc_html( $template['description'] ) . '</p>';

			// Button with aria-label for screen reader differentiation.
			/* translators: %s: template name */
			$aria_label = sprintf( __( 'Use template: %s', 'samplehq-request-form' ), $template['title'] );

			if ( $is_blank ) {
				echo '<a href="' . esc_url( $create_url ) . '" class="shqf-template-btn shqf-template-btn--blank"';
				echo ' aria-label="' . esc_attr__( 'Create blank form', 'samplehq-request-form' ) . '">';
				echo esc_html__( 'Create Blank', 'samplehq-request-form' ) . '</a>';
			} elseif ( $is_first ) {
				echo '<a href="' . esc_url( $create_url ) . '" class="shqf-template-btn shqf-template-btn--primary"';
				echo ' aria-label="' . esc_attr( $aria_label ) . '">';
				echo esc_html__( 'Use Template', 'samplehq-request-form' ) . '</a>';
			} else {
				echo '<a href="' . esc_url( $create_url ) . '" class="shqf-template-btn shqf-template-btn--outline"';
				echo ' aria-label="' . esc_attr( $aria_label ) . '">';
				echo esc_html__( 'Use Template', 'samplehq-request-form' ) . '</a>';
			}

			echo '</div>';
			echo '</div>';

			$is_first = false;
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render the form import page.
	 *
	 * @param \SampleHQForm\Database\FormsTable $forms Forms repository.
	 * @return void
	 */
	private function render_form_import( \SampleHQForm\Database\FormsTable $forms ): void {
		// Handle upload on POST.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
			$this->process_form_import( $forms );
		}

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Import Form', 'samplehq-request-form' ) . '</h1>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-forms' ) ) . '" class="page-title-action">';
		echo esc_html__( 'Back to Forms', 'samplehq-request-form' ) . '</a>';
		echo '<hr class="wp-header-end">';
		AdminNotice::render();

		echo '<div id="poststuff">';
		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Import Form from JSON', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<p>' . esc_html__( 'Upload a .json file exported from SampleHQ Request Form to create a new form with the same configuration.', 'samplehq-request-form' ) . '</p>';

		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'shqf_import_form', 'shqf_form_import_nonce' );
		echo '<input type="file" name="form_json" accept=".json" required />';
		echo '<br><br>';
		submit_button( __( 'Import Form', 'samplehq-request-form' ), 'primary', 'submit', false );
		echo '</form>';

		echo '</div></div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Process the form JSON import.
	 *
	 * @param \SampleHQForm\Database\FormsTable $forms Forms repository.
	 * @return void
	 */
	private function process_form_import( \SampleHQForm\Database\FormsTable $forms ): void {
		if ( ! isset( $_POST['shqf_form_import_nonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['shqf_form_import_nonce'] ) ),
			'shqf_import_form'
		) ) {
			AdminNotice::error( __( 'Security check failed.', 'samplehq-request-form' ) );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = $_FILES['form_json'] ?? null;
		if ( empty( $file['tmp_name'] ) ) {
			AdminNotice::error( __( 'No file uploaded.', 'samplehq-request-form' ) );
			return;
		}

		// Validate file size (max 1MB).
		if ( ( $file['size'] ?? 0 ) > 1048576 ) {
			AdminNotice::error( __( 'File is too large. Maximum size is 1 MB.', 'samplehq-request-form' ) );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json_string = file_get_contents( $file['tmp_name'] );
		if ( false === $json_string ) {
			AdminNotice::error( __( 'Could not read the uploaded file.', 'samplehq-request-form' ) );
			return;
		}

		$data = json_decode( $json_string, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			AdminNotice::error( __( 'Invalid JSON file.', 'samplehq-request-form' ) );
			return;
		}

		// Validate plugin identifier.
		if ( ( $data['plugin'] ?? '' ) !== 'samplehq-request-form' ) {
			AdminNotice::error( __( 'This file was not exported from SampleHQ Request Form.', 'samplehq-request-form' ) );
			return;
		}

		// Validate structure.
		if ( empty( $data['form']['title'] ) || ! isset( $data['form']['config'] ) ) {
			AdminNotice::error( __( 'Invalid form export file. Missing title or config.', 'samplehq-request-form' ) );
			return;
		}

		$config = $data['form']['config'];
		if ( ! is_array( $config ) ) {
			AdminNotice::error( __( 'Invalid form config in export file.', 'samplehq-request-form' ) );
			return;
		}

		// Only allow known config keys to prevent arbitrary data injection.
		$allowed_keys = [ 'schema_version', 'fields', 'appearance', 'behavior' ];
		$config       = array_intersect_key( $config, array_flip( $allowed_keys ) );

		try {
			/* translators: %s: original form title */
			$title  = sprintf( __( 'Imported: %s', 'samplehq-request-form' ), sanitize_text_field( $data['form']['title'] ) );
			$new_id = $forms->create(
				[
					'title'      => $title,
					'config'     => $config,
					'status'     => 'draft',
					'created_by' => get_current_user_id(),
				]
			);
			AdminNotice::success( __( 'Form imported successfully.', 'samplehq-request-form' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=shqf-forms&action=edit&id=' . $new_id ) );
			exit;
		} catch ( \Exception $e ) {
			AdminNotice::error( __( 'Failed to import form.', 'samplehq-request-form' ) );
		}
	}
}
