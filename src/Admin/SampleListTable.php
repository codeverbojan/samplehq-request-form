<?php
/**
 * Sample Library admin list table.
 *
 * @package SampleHQForm\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SampleImagesTable;
use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Database\SubmissionMetaTable;

/**
 * WP_List_Table for the Sample Library admin page.
 *
 * Displays samples with columns for image, name, SKU, categories, status.
 * Supports search, status filtering, bulk actions (archive, delete), pagination.
 */
class SampleListTable extends \WP_List_Table {

	/**
	 * Samples repository.
	 *
	 * @var SamplesTable
	 */
	private SamplesTable $samples;

	/**
	 * Category map repository.
	 *
	 * @var SampleCategoryMapTable
	 */
	private SampleCategoryMapTable $category_map;

	/**
	 * Images repository.
	 *
	 * @var SampleImagesTable
	 */
	private SampleImagesTable $images;

	/**
	 * Categories repository.
	 *
	 * @var SampleCategoriesTable
	 */
	private SampleCategoriesTable $categories;

	/**
	 * Submission meta repository.
	 *
	 * @var SubmissionMetaTable
	 */
	private SubmissionMetaTable $submission_meta;

	/**
	 * Constructor.
	 *
	 * @param SamplesTable           $samples         Samples repository.
	 * @param SampleCategoryMapTable $category_map    Category map repository.
	 * @param SampleImagesTable      $images          Images repository.
	 * @param SampleCategoriesTable  $categories      Categories repository.
	 * @param SubmissionMetaTable    $submission_meta  Submission meta repository.
	 */
	public function __construct(
		SamplesTable $samples,
		SampleCategoryMapTable $category_map,
		SampleImagesTable $images,
		SampleCategoriesTable $categories,
		SubmissionMetaTable $submission_meta
	) {
		parent::__construct(
			[
				'singular' => 'sample',
				'plural'   => 'samples',
				'ajax'     => false,
			]
		);

		$this->samples         = $samples;
		$this->category_map    = $category_map;
		$this->images          = $images;
		$this->categories      = $categories;
		$this->submission_meta = $submission_meta;
	}

	/**
	 * Define table columns.
	 *
	 * @return array<string, string> Column slug => label.
	 */
	public function get_columns(): array {
		return [
			'cb'         => '<input type="checkbox" />',
			'image'      => '',
			'name'       => __( 'Name', 'samplehq-request-form' ),
			'sku'        => __( 'SKU', 'samplehq-request-form' ),
			'categories' => __( 'Categories', 'samplehq-request-form' ),
			'requests'   => __( 'Requests', 'samplehq-request-form' ),
			'status'     => __( 'Status', 'samplehq-request-form' ),
			'created_at' => __( 'Date', 'samplehq-request-form' ),
		];
	}

	/**
	 * Define sortable columns.
	 *
	 * @return array<string, array<int, string|bool>> Column slug => [orderby, default_desc].
	 */
	public function get_sortable_columns(): array {
		return [
			'name'       => [ 'name', false ],
			'sku'        => [ 'sku', false ],
			'created_at' => [ 'created_at', true ],
		];
	}

	/**
	 * Define bulk actions.
	 *
	 * @return array<string, string> Action slug => label.
	 */
	public function get_bulk_actions(): array {
		return [
			'archive' => __( 'Archive', 'samplehq-request-form' ),
			'delete'  => __( 'Delete Permanently', 'samplehq-request-form' ),
		];
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = $this->get_items_per_page( 'shqf_samples_per_page', 20 );
		$page     = $this->get_pagenum();

		$filters = [
			'limit'  => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
		if ( ! empty( $status ) ) {
			$filters['status'] = $status;
		} else {
			$filters['exclude_status'] = 'trashed';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		if ( ! empty( $search ) ) {
			$filters['search'] = $search;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$category_id = absint( $_GET['category_id'] ?? 0 );
		if ( $category_id > 0 ) {
			$filters['category_id'] = $category_id;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$month = sanitize_text_field( wp_unslash( $_GET['m'] ?? '' ) );
		if ( ! empty( $month ) ) {
			$filters['month'] = $month;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ?? '' ) );
		if ( ! empty( $orderby ) ) {
			$filters['orderby'] = $orderby;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = sanitize_text_field( wp_unslash( $_GET['order'] ?? '' ) );
		if ( ! empty( $order ) ) {
			$filters['order'] = $order;
		}

		$this->items = $this->samples->list_all( $filters );

		$count_filters = array_filter(
			[
				'status'      => $status,
				'search'      => $search,
				'category_id' => $category_id > 0 ? $category_id : null,
				'month'       => ! empty( $month ) ? $month : null,
			]
		);
		$total         = $this->samples->count( $count_filters );

		$this->set_pagination_args(
			[
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			]
		);

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param array<string, mixed> $item The sample row.
	 * @return string HTML checkbox.
	 */
	protected function column_cb( $item ): string {
		return '<input type="checkbox" name="sample_ids[]" value="' . esc_attr( $item['id'] ) . '" />';
	}

	/**
	 * Render the image column.
	 *
	 * @param array<string, mixed> $item The sample row.
	 * @return string HTML image or empty.
	 */
	protected function column_image( $item ): string {
		$image_id = $this->images->get_featured( (int) $item['id'] );
		if ( ! $image_id ) {
			return '<span class="shqf-no-image">--</span>';
		}

		$url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
		if ( ! $url ) {
			return '<span class="shqf-no-image">--</span>';
		}

		return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $item['name'] ?? '' ) . '" width="40" height="40" style="object-fit:cover;border-radius:4px;" />';
	}

	/**
	 * Render the name column with row actions.
	 *
	 * @param array<string, mixed> $item The sample row.
	 * @return string HTML name with actions.
	 */
	protected function column_name( $item ): string {
		$id     = (int) $item['id'];
		$status = $item['status'] ?? 'active';

		$edit_url = admin_url( 'admin.php?page=shqf-samples&action=edit&id=' . $id );

		if ( 'trashed' === $status ) {
			// Trash view: Restore + Delete Permanently.
			$restore_url = wp_nonce_url(
				admin_url( 'admin.php?page=shqf-samples&action=restore&id=' . $id ),
				'shqf_restore_sample_' . $id
			);
			$delete_url  = wp_nonce_url(
				admin_url( 'admin.php?page=shqf-samples&action=delete&id=' . $id ),
				'shqf_delete_sample_' . $id
			);

			$actions = [
				'restore' => '<a href="' . esc_url( $restore_url ) . '">' . esc_html__( 'Restore', 'samplehq-request-form' ) . '</a>',
				'delete'  => '<a href="' . esc_url( $delete_url ) . '" class="submitdelete">' . esc_html__( 'Delete Permanently', 'samplehq-request-form' ) . '</a>',
			];

			return '<strong>' . esc_html( $item['name'] ?? '' ) . '</strong>'
				. $this->row_actions( $actions );
		}

		// Active/Archived view: Edit + View Requests + Trash.
		$trash_url    = wp_nonce_url(
			admin_url( 'admin.php?page=shqf-samples&action=trash&id=' . $id ),
			'shqf_trash_sample_' . $id
		);
		$requests_url = admin_url( 'admin.php?page=shqf-submissions&sample_id=' . $id );

		$actions = [
			'edit'     => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'samplehq-request-form' ) . '</a>',
			'requests' => '<a href="' . esc_url( $requests_url ) . '">' . esc_html__( 'View Requests', 'samplehq-request-form' ) . '</a>',
			'trash'    => '<a href="' . esc_url( $trash_url ) . '" class="submitdelete">' . esc_html__( 'Trash', 'samplehq-request-form' ) . '</a>',
		];

		return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item['name'] ?? '' ) . '</a></strong>'
			. $this->row_actions( $actions );
	}

	/**
	 * Render the SKU column.
	 *
	 * @param array<string, mixed> $item The sample row.
	 * @return string SKU or dash.
	 */
	protected function column_sku( $item ): string {
		return esc_html( $item['sku'] ?? '--' );
	}

	/**
	 * Render the categories column.
	 *
	 * @param array<string, mixed> $item The sample row.
	 * @return string Comma-separated category IDs (names in future).
	 */
	protected function column_categories( $item ): string {
		$cat_ids = $this->category_map->get_categories_for_sample( (int) $item['id'] );

		if ( empty( $cat_ids ) ) {
			return '<span class="shqf-no-categories">--</span>';
		}

		return esc_html( implode( ', ', $cat_ids ) );
	}

	/**
	 * Render the requests column.
	 *
	 * @param array<string, mixed> $item The sample row.
	 * @return string Request count linked to submissions.
	 */
	protected function column_requests( $item ): string {
		$count = $this->submission_meta->count_submissions_for_sample( (int) $item['id'] );
		if ( 0 === $count ) {
			return '<span class="shqf-no-categories">0</span>';
		}
		$url = admin_url( 'admin.php?page=shqf-submissions&sample_id=' . (int) $item['id'] );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( number_format_i18n( $count ) ) . '</a>';
	}

	/**
	 * Render the status column.
	 *
	 * @param array<string, mixed> $item The sample row.
	 * @return string Status badge HTML.
	 */
	protected function column_status( $item ): string {
		$status = $item['status'] ?? 'active';
		$label  = 'active' === $status
			? __( 'Active', 'samplehq-request-form' )
			: __( 'Archived', 'samplehq-request-form' );

		return '<span class="shqf-status shqf-status--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Render the date column.
	 *
	 * @param array<string, mixed> $item The sample row.
	 * @return string Formatted date.
	 */
	protected function column_created_at( $item ): string {
		if ( empty( $item['created_at'] ) ) {
			return '--';
		}

		return esc_html( wp_date( get_option( 'date_format' ), strtotime( $item['created_at'] ) ) );
	}

	/**
	 * Default column renderer.
	 *
	 * @param array<string, mixed> $item        The sample row.
	 * @param string               $column_name Column slug.
	 * @return string Column value.
	 */
	protected function column_default( $item, $column_name ): string {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * Get status filter views (All, Active, Archived).
	 *
	 * @return array<string, string> View links.
	 */
	protected function get_views(): array {
		$base_url = admin_url( 'admin.php?page=shqf-samples' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );

		$active   = $this->samples->count( [ 'status' => 'active' ] );
		$archived = $this->samples->count( [ 'status' => 'archived' ] );
		$total    = $active + $archived; // "All" excludes trashed (standard WP pattern).

		$views = [];

		$views['all'] = '<a href="' . esc_url( $base_url ) . '"'
			. ( empty( $current ) ? ' class="current"' : '' ) . '>'
			. sprintf(
				/* translators: %s: total form count */
				__( 'All (%s)', 'samplehq-request-form' ),
				number_format_i18n( $total )
			) . '</a>';

		$views['active'] = '<a href="' . esc_url( add_query_arg( 'status', 'active', $base_url ) ) . '"'
			. ( 'active' === $current ? ' class="current"' : '' ) . '>'
			. sprintf(
				/* translators: %s: active count */
				__( 'Active (%s)', 'samplehq-request-form' ),
				number_format_i18n( $active )
			) . '</a>';

		$views['archived'] = '<a href="' . esc_url( add_query_arg( 'status', 'archived', $base_url ) ) . '"'
			. ( 'archived' === $current ? ' class="current"' : '' ) . '>'
			. sprintf(
				/* translators: %s: archived count */
				__( 'Archived (%s)', 'samplehq-request-form' ),
				number_format_i18n( $archived )
			) . '</a>';

		$trashed = $this->samples->count( [ 'status' => 'trashed' ] );
		if ( $trashed > 0 ) {
			$views['trashed'] = '<a href="' . esc_url( add_query_arg( 'status', 'trashed', $base_url ) ) . '"'
				. ( 'trashed' === $current ? ' class="current"' : '' ) . '>'
				. sprintf(
					/* translators: %s: trashed form count */
					__( 'Trash (%s)', 'samplehq-request-form' ),
					number_format_i18n( $trashed )
				) . '</a>';
		}

		return $views;
	}

	/**
	 * Render filter dropdowns above the table.
	 *
	 * @param string $which 'top' or 'bottom'.
	 * @return void
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		echo '<div class="alignleft actions">';

		// Category filter.
		$all_cats = $this->categories->list_all();
		if ( ! empty( $all_cats ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_cat = absint( $_GET['category_id'] ?? 0 );

			echo '<label for="filter-by-category" class="screen-reader-text">';
			echo esc_html__( 'Filter by category', 'samplehq-request-form' );
			echo '</label>';
			echo '<select name="category_id" id="filter-by-category">';
			echo '<option value="">' . esc_html__( 'All Categories', 'samplehq-request-form' ) . '</option>';
			foreach ( $all_cats as $cat ) {
				$cat_id = (int) $cat['id'];
				echo '<option value="' . esc_attr( (string) $cat_id ) . '"' . selected( $current_cat, $cat_id, false ) . '>';
				echo esc_html( $cat['name'] ) . '</option>';
			}
			echo '</select>';
		}

		// Month filter.
		$months = $this->samples->get_distinct_months();
		if ( ! empty( $months ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_month = sanitize_text_field( wp_unslash( $_GET['m'] ?? '' ) );

			echo '<label for="filter-by-date" class="screen-reader-text">';
			echo esc_html__( 'Filter by date', 'samplehq-request-form' );
			echo '</label>';
			echo '<select name="m" id="filter-by-date">';
			echo '<option value="">' . esc_html__( 'All Dates', 'samplehq-request-form' ) . '</option>';
			foreach ( $months as $m ) {
				$value = sprintf( '%04d%02d', $m['year'], $m['month'] );
				$label = wp_date( 'F Y', mktime( 0, 0, 0, $m['month'], 1, $m['year'] ) );
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $current_month, $value, false ) . '>';
				echo esc_html( (string) $label ) . '</option>';
			}
			echo '</select>';
		}

		submit_button( __( 'Filter', 'samplehq-request-form' ), '', 'filter_action', false );

		echo '</div>';
	}

	/**
	 * Message displayed when no samples exist.
	 *
	 * @return void
	 */
	public function no_items(): void {
		echo '<div class="shqf-empty-state-box">';
		echo '<span class="dashicons dashicons-format-gallery"></span>';
		echo '<p>' . esc_html__( 'No samples found. Add your first sample to get started.', 'samplehq-request-form' ) . '</p>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-samples&action=new' ) ) . '" class="button button-primary">';
		echo esc_html__( 'Add Sample', 'samplehq-request-form' ) . '</a>';
		echo '</div>';
	}
}
