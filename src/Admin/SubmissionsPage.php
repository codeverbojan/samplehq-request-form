<?php
/**
 * Submissions admin page.
 *
 * Extracted from AdminMenu.php during Phase 6 refactoring.
 * Source: AdminMenu.php.backup lines 425-528, 1649-2128.
 *
 * @package SampleHQForm\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the submissions list, detail view, and handles submission actions.
 * Also handles CSV export.
 */
class SubmissionsPage {

	/**
	 * Render the submissions page.
	 *
	 * @return void
	 */
	public function render(): void {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );

		// Submission detail view.
		if ( 'view' === $action ) {
			$this->render_submission_detail();
			return;
		}

		$submissions     = new \SampleHQForm\Database\SubmissionsTable( $wpdb );
		$submission_meta = new \SampleHQForm\Database\SubmissionMetaTable( $wpdb );
		$forms_table     = new \SampleHQForm\Database\FormsTable( $wpdb );

		// Single-row + bulk actions handled by handle_actions() on admin_init.

		$list_table = new SubmissionListTable( $submissions, $forms_table, $submission_meta );
		$list_table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Submissions', 'samplehq-request-form' ) . '</h1>';

		// Export CSV button -- carries current filters.
		$export_params = [
			'page'   => 'shqf-submissions',
			'action' => 'export_csv',
		];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cur_status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
		if ( ! empty( $cur_status ) ) {
			$export_params['status'] = $cur_status;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cur_form = absint( $_GET['form_id'] ?? 0 );
		if ( $cur_form > 0 ) {
			$export_params['form_id'] = $cur_form;
		}
		$export_url = wp_nonce_url( admin_url( 'admin.php?' . http_build_query( $export_params ) ), 'shqf_export_submissions' );
		echo ' <a href="' . esc_url( $export_url ) . '" class="page-title-action">';
		echo esc_html__( 'Export CSV', 'samplehq-request-form' ) . '</a>';

		echo '<hr class="wp-header-end">';
		AdminNotice::render();

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="shqf-submissions" />';
		$list_table->views();
		$list_table->search_box( __( 'Search Submissions', 'samplehq-request-form' ), 'shqf-submission-search' );
		$list_table->display();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle submission single-row and bulk actions on admin_init (before output).
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			return;
		}

		global $wpdb;
		$submissions     = new \SampleHQForm\Database\SubmissionsTable( $wpdb );
		$submission_meta = new \SampleHQForm\Database\SubmissionMetaTable( $wpdb );
		$forms_table     = new \SampleHQForm\Database\FormsTable( $wpdb );

		// Single row actions.
		$this->process_submission_actions( $submissions, $submission_meta, $forms_table );

		// Bulk actions.
		$this->process_submission_bulk_actions( $submissions, $submission_meta, $forms_table );
	}

	/**
	 * Handle CSV export of submissions.
	 *
	 * @return void
	 */
	public function handle_csv_export(): void {
		global $wpdb;

		$filters = [];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
		if ( ! empty( $status ) ) {
			$filters['status'] = $status;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = absint( $_GET['form_id'] ?? 0 );
		if ( $form_id > 0 ) {
			$filters['form_id'] = $form_id;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		if ( ! empty( $search ) ) {
			$filters['search'] = $search;
		}

		$exporter = new \SampleHQForm\Export\CsvExporter(
			new \SampleHQForm\Database\SubmissionsTable( $wpdb ),
			new \SampleHQForm\Database\SubmissionMetaTable( $wpdb )
		);

		$exporter->export( $filters );
	}

	/**
	 * Process single submission row actions (star, spam, trash, restore, delete).
	 *
	 * @param \SampleHQForm\Database\SubmissionsTable    $submissions     Submissions repo.
	 * @param \SampleHQForm\Database\SubmissionMetaTable $submission_meta Meta repo.
	 * @param \SampleHQForm\Database\FormsTable          $forms           Forms repo.
	 * @return void
	 */
	private function process_submission_actions(
		\SampleHQForm\Database\SubmissionsTable $submissions,
		\SampleHQForm\Database\SubmissionMetaTable $submission_meta,
		\SampleHQForm\Database\FormsTable $forms
	): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = absint( $_GET['id'] ?? 0 );

		$single_actions = [ 'star', 'unstar', 'mark_spam', 'to_trash', 'restore', 'delete_permanently' ];

		if ( ! in_array( $action, $single_actions, true ) || 0 === $id ) {
			return;
		}

		check_admin_referer( 'shqf_submission_action_' . $id );

		switch ( $action ) {
			case 'star':
				$submissions->set_starred( $id, true );
				AdminNotice::success( __( 'Submission starred.', 'samplehq-request-form' ) );
				break;
			case 'unstar':
				$submissions->set_starred( $id, false );
				AdminNotice::success( __( 'Submission unstarred.', 'samplehq-request-form' ) );
				break;
			case 'mark_spam':
				$submissions->update_status( $id, 'spam' );
				AdminNotice::success( __( 'Submission marked as spam.', 'samplehq-request-form' ) );
				break;
			case 'to_trash':
				$submissions->update_status( $id, 'trash' );
				AdminNotice::success( __( 'Submission moved to trash.', 'samplehq-request-form' ) );
				break;
			case 'restore':
				$submissions->update_status( $id, 'new' );
				AdminNotice::success( __( 'Submission restored.', 'samplehq-request-form' ) );
				break;
			case 'delete_permanently':
				if ( $this->delete_submission_cascade( $id, $submissions, $submission_meta, $forms ) ) {
					AdminNotice::success( __( 'Submission permanently deleted.', 'samplehq-request-form' ) );
				} else {
					AdminNotice::error( __( 'Submission not found.', 'samplehq-request-form' ) );
				}
				break;
		}

		delete_transient( 'shqf_unread_count' );
		wp_safe_redirect( admin_url( 'admin.php?page=shqf-submissions' ) );
		exit;
	}

	/**
	 * Process bulk submission actions.
	 *
	 * @param \SampleHQForm\Database\SubmissionsTable    $submissions     Submissions repo.
	 * @param \SampleHQForm\Database\SubmissionMetaTable $submission_meta Meta repo.
	 * @param \SampleHQForm\Database\FormsTable          $forms           Forms repo.
	 * @return void
	 */
	private function process_submission_bulk_actions(
		\SampleHQForm\Database\SubmissionsTable $submissions,
		\SampleHQForm\Database\SubmissionMetaTable $submission_meta,
		\SampleHQForm\Database\FormsTable $forms
	): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
		if ( empty( $action ) || '-1' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = sanitize_text_field( wp_unslash( $_GET['action2'] ?? '' ) );
		}

		$bulk_actions = [ 'bulk_read', 'bulk_spam', 'bulk_trash', 'bulk_restore', 'bulk_delete' ];
		if ( ! in_array( $action, $bulk_actions, true ) ) {
			return;
		}

		check_admin_referer( 'bulk-submissions' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ids = array_map( 'absint', (array) ( $_GET['submission_ids'] ?? [] ) );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return;
		}

		$count = 0;

		foreach ( $ids as $id ) {
			switch ( $action ) {
				case 'bulk_read':
					$submissions->set_read( $id, true );
					break;
				case 'bulk_spam':
					$submissions->update_status( $id, 'spam' );
					break;
				case 'bulk_trash':
					$submissions->update_status( $id, 'trash' );
					break;
				case 'bulk_restore':
					$submissions->update_status( $id, 'new' );
					break;
				case 'bulk_delete':
					$this->delete_submission_cascade( $id, $submissions, $submission_meta, $forms );
					break;
			}
			++$count;
		}

		/* translators: %d: number of submissions affected */
		AdminNotice::success( sprintf( __( '%d submission(s) updated.', 'samplehq-request-form' ), $count ) );
		wp_safe_redirect( admin_url( 'admin.php?page=shqf-submissions' ) );
		exit;
	}

	/**
	 * Delete a submission with full cascade (meta + form count decrement).
	 *
	 * @param int                                        $id              Submission ID.
	 * @param \SampleHQForm\Database\SubmissionsTable    $submissions     Submissions repo.
	 * @param \SampleHQForm\Database\SubmissionMetaTable $submission_meta Meta repo.
	 * @param \SampleHQForm\Database\FormsTable          $forms           Forms repo.
	 * @return bool True if submission existed and was deleted.
	 */
	private function delete_submission_cascade(
		int $id,
		\SampleHQForm\Database\SubmissionsTable $submissions,
		\SampleHQForm\Database\SubmissionMetaTable $submission_meta,
		\SampleHQForm\Database\FormsTable $forms
	): bool {
		$submission = $submissions->get( $id );
		if ( null === $submission ) {
			return false;
		}

		$form_id = (int) ( $submission['form_id'] ?? 0 );

		$submission_meta->delete_all( $id );
		$submissions->delete( $id );

		if ( $form_id > 0 ) {
			$forms->decrement_submissions_count( $form_id );
		}

		return true;
	}

	/**
	 * Render the submission detail page with postbox layout.
	 *
	 * @return void
	 */
	private function render_submission_detail(): void {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id = absint( $_GET['id'] ?? 0 );

		$submissions     = new \SampleHQForm\Database\SubmissionsTable( $wpdb );
		$submission_meta = new \SampleHQForm\Database\SubmissionMetaTable( $wpdb );
		$forms_table     = new \SampleHQForm\Database\FormsTable( $wpdb );
		$samples_table   = new \SampleHQForm\Database\SamplesTable( $wpdb );
		$images_table    = new \SampleHQForm\Database\SampleImagesTable( $wpdb );

		$submission = $submissions->get( $id );

		if ( null === $submission ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>';
			echo esc_html__( 'Submission not found.', 'samplehq-request-form' );
			echo '</p></div></div>';
			return;
		}

		// Mark as read and clear unread count cache.
		$submissions->set_read( $id, true );
		delete_transient( 'shqf_unread_count' );

		$meta = $submission_meta->get_all( $id );
		$name = trim( ( $submission['first_name'] ?? '' ) . ' ' . ( $submission['last_name'] ?? '' ) );

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">';
		echo esc_html__( 'Submission', 'samplehq-request-form' ) . ' #' . esc_html( (string) $submission['id'] );
		echo '</h1>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-submissions' ) ) . '" class="page-title-action">';
		echo esc_html__( 'Back to Submissions', 'samplehq-request-form' ) . '</a>';
		echo '<hr class="wp-header-end">';
		AdminNotice::render();

		echo '<div id="poststuff">';
		echo '<div id="post-body" class="metabox-holder columns-2">';

		// --- Main content ---
		echo '<div id="post-body-content">';

		// Submitted fields metabox.
		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Submitted Fields', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		if ( ! empty( $meta ) ) {
			echo '<table class="widefat striped">';
			echo '<thead><tr><th>' . esc_html__( 'Field', 'samplehq-request-form' ) . '</th>';
			echo '<th>' . esc_html__( 'Value', 'samplehq-request-form' ) . '</th></tr></thead>';
			echo '<tbody>';
			foreach ( $meta as $key => $value ) {
				// Skip internal meta keys (email logs, etc.).
				if ( str_starts_with( $key, '_' ) ) {
					continue;
				}
				$display = self::format_meta_value( $key, $value );
				echo '<tr>';
				echo '<td><strong>' . esc_html( $key ) . '</strong></td>';
				echo '<td>' . esc_html( $display ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="description">' . esc_html__( 'No field data submitted.', 'samplehq-request-form' ) . '</p>';
		}
		echo '</div></div>';

		// Selected Samples section (if any sample_picker meta exists).
		$this->render_submission_samples( $meta, $samples_table, $images_table );

		echo '</div>'; // #post-body-content

		// --- Sidebar ---
		echo '<div id="postbox-container-1" class="postbox-container">';

		$status  = $submission['status'] ?? 'new';
		$starred = ! empty( $submission['is_starred'] );

		// Actions box.
		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Actions', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';

		// Status display + change links.
		echo '<p><strong>' . esc_html__( 'Status:', 'samplehq-request-form' ) . '</strong> ';
		echo '<span class="shqf-status shqf-status--' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span></p>';

		// Star toggle.
		$star_action = $starred ? 'unstar' : 'star';
		$star_label  = $starred ? __( 'Unstar', 'samplehq-request-form' ) : __( 'Star', 'samplehq-request-form' );
		$star_url    = wp_nonce_url(
			admin_url( 'admin.php?page=shqf-submissions&action=' . $star_action . '&id=' . $id ),
			'shqf_submission_action_' . $id
		);
		echo '<p><a href="' . esc_url( $star_url ) . '" class="button">';
		echo '<span class="dashicons dashicons-star-' . ( $starred ? 'filled' : 'empty' ) . '" style="vertical-align:text-bottom;"></span> ';
		echo esc_html( $star_label ) . '</a></p>';

		echo '<hr>';

		// Status change links.
		if ( 'spam' !== $status ) {
			$spam_url = wp_nonce_url(
				admin_url( 'admin.php?page=shqf-submissions&action=mark_spam&id=' . $id ),
				'shqf_submission_action_' . $id
			);
			echo '<a href="' . esc_url( $spam_url ) . '" class="button" style="margin-bottom:6px;">';
			echo esc_html__( 'Mark as Spam', 'samplehq-request-form' ) . '</a> ';
		}

		if ( 'trash' !== $status ) {
			$trash_url = wp_nonce_url(
				admin_url( 'admin.php?page=shqf-submissions&action=to_trash&id=' . $id ),
				'shqf_submission_action_' . $id
			);
			echo '<a href="' . esc_url( $trash_url ) . '" class="button" style="margin-bottom:6px;">';
			echo esc_html__( 'Move to Trash', 'samplehq-request-form' ) . '</a> ';
		}

		if ( 'trash' === $status || 'spam' === $status ) {
			$restore_url = wp_nonce_url(
				admin_url( 'admin.php?page=shqf-submissions&action=restore&id=' . $id ),
				'shqf_submission_action_' . $id
			);
			echo '<a href="' . esc_url( $restore_url ) . '" class="button" style="margin-bottom:6px;">';
			echo esc_html__( 'Restore', 'samplehq-request-form' ) . '</a> ';
		}

		if ( 'trash' === $status ) {
			$delete_url = wp_nonce_url(
				admin_url( 'admin.php?page=shqf-submissions&action=delete_permanently&id=' . $id ),
				'shqf_submission_action_' . $id
			);
			echo '<a href="' . esc_url( $delete_url ) . '" class="button delete" onclick="return confirm(\''
				. esc_js( __( 'Delete permanently?', 'samplehq-request-form' ) ) . '\');" style="margin-bottom:6px;">';
			echo esc_html__( 'Delete Permanently', 'samplehq-request-form' ) . '</a>';
		}

		echo '</div></div>';

		// Contact info box.
		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Contact', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<table class="form-table shqf-detail-table">';
		if ( ! empty( $name ) ) {
			echo '<tr><th>' . esc_html__( 'Name', 'samplehq-request-form' ) . '</th>';
			echo '<td>' . esc_html( $name ) . '</td></tr>';
		}
		echo '<tr><th>' . esc_html__( 'Email', 'samplehq-request-form' ) . '</th>';
		echo '<td>' . esc_html( $submission['email'] ?? '--' ) . '</td></tr>';
		echo '</table>';
		echo '</div></div>';

		// Details box.
		$form_id    = (int) ( $submission['form_id'] ?? 0 );
		$form       = $form_id > 0 ? $forms_table->get( $form_id ) : null;
		$form_title = $form['title'] ?? __( 'Unknown', 'samplehq-request-form' );

		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Details', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<table class="form-table shqf-detail-table">';
		echo '<tr><th>' . esc_html__( 'Form', 'samplehq-request-form' ) . '</th>';
		if ( null !== $form ) {
			echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=shqf-forms&action=edit&id=' . $form_id ) ) . '">' . esc_html( $form_title ) . '</a></td></tr>';
		} else {
			echo '<td><em>' . esc_html( $form_title ) . '</em></td></tr>';
		}
		echo '<tr><th>' . esc_html__( 'Source', 'samplehq-request-form' ) . '</th>';
		echo '<td>' . esc_html( $submission['source_url'] ?? '--' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'IP', 'samplehq-request-form' ) . '</th>';
		echo '<td>' . esc_html( $submission['ip_address'] ?? '--' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Submitted', 'samplehq-request-form' ) . '</th>';
		echo '<td>' . esc_html( $submission['created_at'] ?? '--' ) . '</td></tr>';
		$synced = ! empty( $submission['synced_to_shq'] )
			? __( 'Yes', 'samplehq-request-form' )
			: __( 'No', 'samplehq-request-form' );
		echo '<tr><th>' . esc_html__( 'Synced', 'samplehq-request-form' ) . '</th>';
		echo '<td>' . esc_html( $synced ) . '</td></tr>';
		echo '</table>';
		echo '</div></div>';

		echo '</div>'; // #postbox-container-1

		echo '</div>'; // #post-body
		echo '</div>'; // #poststuff

		echo '</div>'; // .wrap
	}

	/**
	 * Render selected samples section in submission detail.
	 *
	 * @param array<string, mixed>                     $meta    Submission meta (key => value).
	 * @param \SampleHQForm\Database\SamplesTable      $samples Samples repo.
	 * @param \SampleHQForm\Database\SampleImagesTable $images  Images repo.
	 * @return void
	 */
	private function render_submission_samples( array $meta, \SampleHQForm\Database\SamplesTable $samples, \SampleHQForm\Database\SampleImagesTable $images ): void {
		// Find sample picker values in meta (JSON arrays of {id, quantity}).
		$selected = [];
		foreach ( $meta as $key => $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}
			$decoded = json_decode( $value, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			// Check if this looks like a sample picker value.
			foreach ( $decoded as $item ) {
				if ( is_array( $item ) && isset( $item['id'] ) ) {
					$selected[] = $item;
				}
			}
		}

		if ( empty( $selected ) ) {
			return;
		}

		echo '<div class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Selected Samples', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th></th>';
		echo '<th>' . esc_html__( 'Sample', 'samplehq-request-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Quantity', 'samplehq-request-form' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $selected as $item ) {
			$sample_id = (int) $item['id'];
			$qty       = (int) ( $item['quantity'] ?? 1 );
			$is_woo    = 'woocommerce' === ( $item['source'] ?? '' );

			if ( $is_woo ) {
				$this->render_woo_sample_row( $item, $sample_id, $qty );
			} else {
				$this->render_library_sample_row( $samples, $images, $sample_id, $qty );
			}
		}

		echo '</tbody></table>';
		echo '</div></div>';
	}

	/**
	 * Render a library sample row in the submission detail.
	 *
	 * @param \SampleHQForm\Database\SamplesTable      $samples   Samples repo.
	 * @param \SampleHQForm\Database\SampleImagesTable $images    Images repo.
	 * @param int                                      $sample_id Sample ID.
	 * @param int                                      $qty       Quantity.
	 * @return void
	 */
	private function render_library_sample_row( \SampleHQForm\Database\SamplesTable $samples, \SampleHQForm\Database\SampleImagesTable $images, int $sample_id, int $qty ): void {
		$sample = $samples->get( $sample_id );

		$thumb    = '';
		$image_id = $images->get_featured( $sample_id );
		if ( $image_id ) {
			$thumb = wp_get_attachment_image(
				$image_id,
				[ 40, 40 ],
				false,
				[ 'style' => 'object-fit:cover;border-radius:4px;' ]
			);
		}

		$name = $sample['name'] ?? __( 'Deleted Sample', 'samplehq-request-form' );

		echo '<tr>';
		echo '<td style="width:50px;">' . wp_kses_post( $thumb ) . '</td>';
		echo '<td>' . esc_html( $name ) . '</td>';
		echo '<td>' . esc_html( (string) $qty ) . '</td>';
		echo '</tr>';
	}

	/**
	 * Render a WooCommerce product row in the submission detail.
	 *
	 * Uses stored name/SKU from submission meta. Links to the WC product edit
	 * page if the product still exists. Shows "(deleted)" if removed.
	 *
	 * @param array<string, mixed> $item      Selection data with source, name, sku.
	 * @param int                  $product_id WC product ID.
	 * @param int                  $qty       Quantity.
	 * @return void
	 */
	private function render_woo_sample_row( array $item, int $product_id, int $qty ): void {
		$stored_name = $item['name'] ?? '';
		$stored_sku  = $item['sku'] ?? '';

		// Check if the WC product still exists.
		$product = null;
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( ! $product instanceof \WC_Product ) {
				$product = null;
			}
		}

		$thumb = '';
		if ( null !== $product ) {
			$image_id = $product->get_image_id();
			if ( $image_id ) {
				$thumb = wp_get_attachment_image(
					(int) $image_id,
					[ 40, 40 ],
					false,
					[ 'style' => 'object-fit:cover;border-radius:4px;' ]
				);
			}
		}

		// Build name display with WC badge and link.
		$name_display = '';
		if ( null !== $product ) {
			$edit_url     = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
			$name_display = '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $stored_name ) . '</a>';
		} elseif ( '' !== $stored_name ) {
			$name_display = esc_html( $stored_name ) . ' <em>(' . esc_html__( 'deleted', 'samplehq-request-form' ) . ')</em>';
		} else {
			$name_display = '<em>' . esc_html__( 'Deleted Product', 'samplehq-request-form' ) . '</em>';
		}

		if ( '' !== $stored_sku ) {
			$name_display .= ' <code>' . esc_html( $stored_sku ) . '</code>';
		}

		$name_display .= ' <span class="shqf-badge shqf-badge--woo">' . esc_html__( 'WooCommerce', 'samplehq-request-form' ) . '</span>';

		echo '<tr>';
		echo '<td style="width:50px;">' . wp_kses_post( $thumb ) . '</td>';
		echo '<td>' . wp_kses_post( $name_display ) . '</td>';
		echo '<td>' . esc_html( (string) $qty ) . '</td>';
		echo '</tr>';
	}

	/**
	 * Format a submission meta value for human-readable display.
	 *
	 * Handles composite fields (name object, address object, sample picker array)
	 * by formatting them into readable strings instead of raw JSON.
	 *
	 * @param string $key   The field key.
	 * @param mixed  $value The raw value (string or JSON string).
	 * @return string Formatted display value.
	 */
	public static function format_meta_value( string $key, mixed $value ): string {
		// Handle both PHP arrays (from FormProcessor) and JSON strings (from DB).
		if ( is_array( $value ) ) {
			$decoded = $value;
		} elseif ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( null === $decoded || ! is_array( $decoded ) ) {
				return $value;
			}
		} else {
			return (string) $value;
		}

		// Name field: { "first_name": "...", "last_name": "..." }.
		if ( isset( $decoded['first_name'] ) || isset( $decoded['last_name'] ) ) {
			return trim( ( $decoded['first_name'] ?? '' ) . ' ' . ( $decoded['last_name'] ?? '' ) );
		}

		// Address field: { "street": "...", "city": "...", ... }.
		if ( isset( $decoded['street'] ) || isset( $decoded['city'] ) ) {
			$parts = array_filter(
				[
					$decoded['street'] ?? '',
					$decoded['street2'] ?? '',
					$decoded['city'] ?? '',
					$decoded['state'] ?? '',
					$decoded['zip'] ?? '',
					$decoded['country'] ?? '',
				]
			);
			return implode( ', ', $parts );
		}

		// Sample picker: [ { "id": 33, "quantity": 1, "name": "...", ... }, ... ].
		if ( isset( $decoded[0]['id'] ) ) {
			$items = [];
			foreach ( $decoded as $item ) {
				$name    = $item['name'] ?? ( 'Sample #' . ( $item['id'] ?? '?' ) );
				$qty     = (int) ( $item['quantity'] ?? 1 );
				$items[] = $qty > 1 ? "{$name} (x{$qty})" : $name;
			}
			return implode( ', ', $items );
		}

		// Generic object: show key=value pairs.
		if ( ! isset( $decoded[0] ) ) {
			$parts = [];
			foreach ( $decoded as $k => $v ) {
				if ( is_string( $v ) && '' !== $v ) {
					$parts[] = $v;
				}
			}
			if ( ! empty( $parts ) ) {
				return implode( ', ', $parts );
			}
		}

		return $value;
	}
}
