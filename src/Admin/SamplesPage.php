<?php
/**
 * Samples admin page.
 *
 * Extracted from AdminMenu.php during Phase 4 refactoring.
 * Source: AdminMenu.php.backup lines 466-504, 535-554, 1217-1275, 2138-2214.
 *
 * @package SampleHQForm\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the sample library page, CSV import, and handles sample actions.
 */
class SamplesPage {

	/**
	 * Categories page controller (for inline categories view).
	 *
	 * @var CategoriesPage
	 */
	private CategoriesPage $categories_page;

	/**
	 * Constructor.
	 *
	 * @param CategoriesPage $categories_page Categories page for delegation.
	 */
	public function __construct( CategoriesPage $categories_page ) {
		$this->categories_page = $categories_page;
	}

	/**
	 * Render the sample library page.
	 *
	 * @return void
	 */
	public function render(): void {
		global $wpdb;

		$samples_table   = new \SampleHQForm\Database\SamplesTable( $wpdb );
		$categories      = new \SampleHQForm\Database\SampleCategoriesTable( $wpdb );
		$category_map    = new \SampleHQForm\Database\SampleCategoryMapTable( $wpdb );
		$images_table    = new \SampleHQForm\Database\SampleImagesTable( $wpdb );
		$submission_meta = new \SampleHQForm\Database\SubmissionMetaTable( $wpdb );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );

		// Add/edit sample page.
		if ( 'new' === $action || 'edit' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$sample_id = absint( $_GET['id'] ?? 0 );
			$edit_page = new SampleEditPage( $samples_table, $categories, $category_map, $images_table );
			$edit_page->render( $sample_id );
			return;
		}

		// CSV import page.
		if ( 'import' === $action ) {
			$this->render_csv_import( $samples_table, $categories, $category_map );
			return;
		}

		// Categories management page.
		if ( 'categories' === $action ) {
			$this->categories_page->render_categories( $categories, $category_map );
			return;
		}

		$list_table = new SampleListTable( $samples_table, $category_map, $images_table, $categories, $submission_meta );
		$list_table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Sample Library', 'samplehq-request-form' ) . '</h1>';
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=shqf-samples&action=new' ) ) . '" class="page-title-action">';
		echo esc_html__( 'Add Sample', 'samplehq-request-form' ) . '</a>';
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=shqf-samples&action=import' ) ) . '" class="page-title-action">';
		echo esc_html__( 'Import CSV', 'samplehq-request-form' ) . '</a>';
		echo '<hr class="wp-header-end">';
		AdminNotice::render();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="shqf-samples" />';
		if ( ! empty( $current_status ) ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $current_status ) . '" />';
		}
		$list_table->views();
		$list_table->search_box( __( 'Search Samples', 'samplehq-request-form' ), 'shqf-sample-search' );
		$list_table->display();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle sample single-row actions before output.
	 *
	 * @param string $action The action to perform.
	 * @return void
	 */
	public function handle_action( string $action ): void {
		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'samplehq-request-form' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = absint( $_GET['id'] ?? 0 );
		if ( 0 === $id ) {
			return;
		}

		global $wpdb;
		$samples      = new \SampleHQForm\Database\SamplesTable( $wpdb );
		$category_map = new \SampleHQForm\Database\SampleCategoryMapTable( $wpdb );
		$images       = new \SampleHQForm\Database\SampleImagesTable( $wpdb );

		if ( 'archive' === $action ) {
			check_admin_referer( 'shqf_archive_sample_' . $id );
			$samples->archive( $id );
			AdminNotice::success( __( 'Sample archived.', 'samplehq-request-form' ) );
		} elseif ( 'trash' === $action ) {
			check_admin_referer( 'shqf_trash_sample_' . $id );
			$samples->update( $id, [ 'status' => 'trashed' ] );
			AdminNotice::success( __( 'Sample moved to trash.', 'samplehq-request-form' ) );
		} elseif ( 'restore' === $action ) {
			check_admin_referer( 'shqf_restore_sample_' . $id );
			$samples->update( $id, [ 'status' => 'active' ] );
			AdminNotice::success( __( 'Sample restored.', 'samplehq-request-form' ) );
		} elseif ( 'delete' === $action ) {
			check_admin_referer( 'shqf_delete_sample_' . $id );
			$images->remove_all_for_sample( $id );
			$category_map->remove_all_for_sample( $id );
			$samples->delete( $id );
			AdminNotice::success( __( 'Sample permanently deleted.', 'samplehq-request-form' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=shqf-samples' ) );
		exit;
	}

	/**
	 * Handle sample save on admin_init (before output).
	 *
	 * @return void
	 */
	public function handle_save(): void {
		// Nonce is verified inside SampleEditPage::handle_save().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['shqf_sample_nonce'] ) ) {
			return;
		}

		global $wpdb;

		$samples_table = new \SampleHQForm\Database\SamplesTable( $wpdb );
		$categories    = new \SampleHQForm\Database\SampleCategoriesTable( $wpdb );
		$category_map  = new \SampleHQForm\Database\SampleCategoryMapTable( $wpdb );
		$images_table  = new \SampleHQForm\Database\SampleImagesTable( $wpdb );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$sample_id = absint( $_GET['id'] ?? $_POST['sample_id'] ?? 0 );

		$edit_page = new SampleEditPage( $samples_table, $categories, $category_map, $images_table );
		$edit_page->handle_save( $sample_id );
	}

	/**
	 * Render the CSV import page.
	 *
	 * @param \SampleHQForm\Database\SamplesTable           $samples      Samples repo.
	 * @param \SampleHQForm\Database\SampleCategoriesTable  $categories   Categories repo.
	 * @param \SampleHQForm\Database\SampleCategoryMapTable $category_map Category map repo.
	 * @return void
	 */
	private function render_csv_import(
		\SampleHQForm\Database\SamplesTable $samples,
		\SampleHQForm\Database\SampleCategoriesTable $categories,
		\SampleHQForm\Database\SampleCategoryMapTable $category_map
	): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Import Samples from CSV', 'samplehq-request-form' ) . '</h1>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=shqf-samples' ) ) . '">';
		echo '&larr; ' . esc_html__( 'Back to Sample Library', 'samplehq-request-form' ) . '</a></p>';

		// Handle upload on POST.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) && ! empty( $_FILES['csv_file'] ) ) {
			$this->process_csv_upload( $samples, $categories, $category_map );
		}

		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'shqf_csv_import', 'shqf_import_nonce' );
		echo '<p>' . esc_html__( 'Upload a CSV file with columns: name (required), sku, description, category, max_quantity.', 'samplehq-request-form' ) . '</p>';
		echo '<input type="file" name="csv_file" accept=".csv" required />';
		echo '<br><br>';
		submit_button( __( 'Import', 'samplehq-request-form' ), 'primary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Process the CSV file upload.
	 *
	 * @param \SampleHQForm\Database\SamplesTable           $samples      Samples repo.
	 * @param \SampleHQForm\Database\SampleCategoriesTable  $categories   Categories repo.
	 * @param \SampleHQForm\Database\SampleCategoryMapTable $category_map Category map repo.
	 * @return void
	 */
	private function process_csv_upload(
		\SampleHQForm\Database\SamplesTable $samples,
		\SampleHQForm\Database\SampleCategoriesTable $categories,
		\SampleHQForm\Database\SampleCategoryMapTable $category_map
	): void {
		if ( ! isset( $_POST['shqf_import_nonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['shqf_import_nonce'] ) ),
			'shqf_csv_import'
		) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed.', 'samplehq-request-form' ) . '</p></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = $_FILES['csv_file'] ?? null;
		if ( empty( $file['tmp_name'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'No file uploaded.', 'samplehq-request-form' ) . '</p></div>';
			return;
		}

		$importer = new \SampleHQForm\Export\CsvImporter( $samples, $categories, $category_map );
		$result   = $importer->import( $file['tmp_name'] );

		echo '<div class="notice notice-success"><p>';
		echo esc_html(
			sprintf(
				/* translators: 1: created count, 2: skipped count */
				__( 'Import complete: %1$d created, %2$d skipped.', 'samplehq-request-form' ),
				$result['created'],
				$result['skipped']
			)
		);
		echo '</p></div>';

		if ( ! empty( $result['errors'] ) ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Issues:', 'samplehq-request-form' ) . '</strong></p>';
			echo '<ul>';
			foreach ( $result['errors'] as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul></div>';
		}
	}
}
