<?php
/**
 * Forms admin list table.
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

use SampleHQForm\Database\FormsTable;

/**
 * WP_List_Table for the Forms admin page.
 */
class FormListTable extends \WP_List_Table {

	/**
	 * Forms repository.
	 *
	 * @var FormsTable
	 */
	private FormsTable $forms;

	/**
	 * Constructor.
	 *
	 * @param FormsTable $forms Forms repository.
	 */
	public function __construct( FormsTable $forms ) {
		parent::__construct(
			[
				'singular' => 'form',
				'plural'   => 'forms',
				'ajax'     => false,
			]
		);
		$this->forms = $forms;
	}

	/**
	 * Define table columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'cb'                => '<input type="checkbox" />',
			'title'             => __( 'Title', 'samplehq-request-form' ),
			'shortcode'         => __( 'Shortcode', 'samplehq-request-form' ),
			'status'            => __( 'Status', 'samplehq-request-form' ),
			'submissions_count' => __( 'Submissions', 'samplehq-request-form' ),
			'updated_at'        => __( 'Last Modified', 'samplehq-request-form' ),
			'created_at'        => __( 'Date Created', 'samplehq-request-form' ),
		];
	}

	/**
	 * Define bulk actions (context-sensitive based on current view).
	 *
	 * @return array<string, string>
	 */
	public function get_bulk_actions(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$current_status = sanitize_text_field( wp_unslash( $_REQUEST['status'] ?? '' ) );

		if ( 'trash' === $current_status ) {
			return [
				'restore'          => __( 'Restore', 'samplehq-request-form' ),
				'delete_permanent' => __( 'Delete Permanently', 'samplehq-request-form' ),
			];
		}

		return [
			'publish' => __( 'Publish', 'samplehq-request-form' ),
			'draft'   => __( 'Move to Draft', 'samplehq-request-form' ),
			'trash'   => __( 'Move to Trash', 'samplehq-request-form' ),
		];
	}

	/**
	 * Define sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	public function get_sortable_columns(): array {
		return [
			'title'             => [ 'title', false ],
			'submissions_count' => [ 'submissions_count', true ],
			'updated_at'        => [ 'updated_at', true ],
			'created_at'        => [ 'created_at', true ],
		];
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = $this->get_items_per_page( 'shqf_forms_per_page', 20 );
		$page     = $this->get_pagenum();

		$filters = [
			'limit'  => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$status = sanitize_text_field( wp_unslash( $_REQUEST['status'] ?? '' ) );
		if ( ! empty( $status ) ) {
			$filters['status'] = $status;
		} else {
			$filters['exclude_status'] = 'trash';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) );
		if ( ! empty( $search ) ) {
			$filters['search'] = $search;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ?? '' ) );
		if ( ! empty( $orderby ) ) {
			$filters['orderby'] = $orderby;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = sanitize_text_field( wp_unslash( $_REQUEST['order'] ?? '' ) );
		if ( ! empty( $order ) ) {
			$filters['order'] = $order;
		}

		$this->items = $this->forms->list_all( $filters );
		$total       = $this->forms->count(
			array_filter(
				[
					'status' => $status,
					'search' => $search,
				]
			)
		);

		$this->set_pagination_args(
			[
				'total_items' => $total,
				'per_page'    => $per_page,
			]
		);

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string, mixed> $item The form row.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return '<input type="checkbox" name="form_ids[]" value="' . esc_attr( $item['id'] ) . '" />';
	}

	/**
	 * Title column with row actions.
	 *
	 * @param array<string, mixed> $item The form row.
	 * @return string
	 */
	protected function column_title( $item ): string {
		$id     = (int) $item['id'];
		$status = $item['status'] ?? 'draft';

		$edit_url    = admin_url( 'admin.php?page=shqf-forms&action=edit&id=' . $id );
		$preview_url = admin_url( 'admin.php?page=shqf-forms&action=preview&id=' . $id );

		if ( 'trash' === $status ) {
			// Trash view: Restore + Delete Permanently.
			$restore_url = wp_nonce_url(
				admin_url( 'admin.php?page=shqf-forms&action=restore&id=' . $id ),
				'shqf_restore_form_' . $id
			);
			$delete_url  = wp_nonce_url(
				admin_url( 'admin.php?page=shqf-forms&action=delete&id=' . $id ),
				'shqf_delete_form_' . $id
			);

			$actions = [
				'restore' => '<a href="' . esc_url( $restore_url ) . '">' . esc_html__( 'Restore', 'samplehq-request-form' ) . '</a>',
				'delete'  => '<a href="' . esc_url( $delete_url ) . '" class="submitdelete">' . esc_html__( 'Delete Permanently', 'samplehq-request-form' ) . '</a>',
			];

			return '<strong>' . esc_html( $item['title'] ?? '' ) . '</strong>'
				. $this->row_actions( $actions );
		}

		// Active view: Edit, Preview, Duplicate, Export, Trash.
		$duplicate_url = wp_nonce_url(
			admin_url( 'admin.php?page=shqf-forms&action=duplicate&id=' . $id ),
			'shqf_duplicate_form_' . $id
		);
		$trash_url     = wp_nonce_url(
			admin_url( 'admin.php?page=shqf-forms&action=trash&id=' . $id ),
			'shqf_trash_form_' . $id
		);
		$export_url    = wp_nonce_url(
			admin_url( 'admin.php?page=shqf-forms&action=export&id=' . $id ),
			'shqf_export_form_' . $id
		);

		$actions = [
			'edit'      => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'samplehq-request-form' ) . '</a>',
			'duplicate' => '<a href="' . esc_url( $duplicate_url ) . '">' . esc_html__( 'Duplicate', 'samplehq-request-form' ) . '</a>',
			'export'    => '<a href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export', 'samplehq-request-form' ) . '</a>',
			'view'      => '<a href="' . esc_url( $preview_url ) . '" target="_blank">' . esc_html__( 'Preview', 'samplehq-request-form' ) . '</a>',
			'trash'     => '<a href="' . esc_url( $trash_url ) . '" class="submitdelete">' . esc_html__( 'Trash', 'samplehq-request-form' ) . '</a>',
		];

		return '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item['title'] ?? '' ) . '</a></strong>'
			. $this->row_actions( $actions );
	}

	/**
	 * Shortcode column.
	 *
	 * @param array<string, mixed> $item The form row.
	 * @return string
	 */
	protected function column_shortcode( $item ): string {
		return '<code>[samplehq_form id="' . esc_attr( $item['id'] ) . '"]</code>';
	}

	/**
	 * Status column.
	 *
	 * @param array<string, mixed> $item The form row.
	 * @return string
	 */
	protected function column_status( $item ): string {
		$status = $item['status'] ?? 'draft';
		return '<span class="shqf-status shqf-status--' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
	}

	/**
	 * Submissions count column.
	 *
	 * @param array<string, mixed> $item The form row.
	 * @return string
	 */
	protected function column_submissions_count( $item ): string {
		$count = (int) ( $item['submissions_count'] ?? 0 );
		if ( 0 === $count ) {
			return '0';
		}
		$url = admin_url( 'admin.php?page=shqf-submissions&form_id=' . (int) $item['id'] );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( number_format_i18n( $count ) ) . '</a>';
	}

	/**
	 * Date column.
	 *
	 * @param array<string, mixed> $item The form row.
	 * @return string
	 */
	protected function column_created_at( $item ): string {
		if ( empty( $item['created_at'] ) ) {
			return '--';
		}
		$timestamp = strtotime( $item['created_at'] );
		if ( false === $timestamp ) {
			return '--';
		}
		$date = wp_date( get_option( 'date_format' ), $timestamp );
		return esc_html( $date ? (string) $date : '--' );
	}

	/**
	 * Last Modified column.
	 *
	 * @param array<string, mixed> $item The form row.
	 * @return string
	 */
	protected function column_updated_at( $item ): string {
		if ( empty( $item['updated_at'] ) ) {
			return '--';
		}
		$timestamp = strtotime( $item['updated_at'] );
		if ( false === $timestamp ) {
			return '--';
		}
		$date = wp_date( get_option( 'date_format' ), $timestamp );
		return esc_html( $date ? (string) $date : '--' );
	}

	/**
	 * Default column.
	 *
	 * @param array<string, mixed> $item        The form row.
	 * @param string               $column_name Column slug.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
	}

	/**
	 * No items message.
	 *
	 * @return void
	 */
	public function no_items(): void {
		echo '<div class="shqf-empty-state-box">';
		echo '<span class="dashicons dashicons-feedback"></span>';
		echo '<p>' . esc_html__( 'No forms found. Create your first form to get started.', 'samplehq-request-form' ) . '</p>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-forms&action=templates' ) ) . '" class="button button-primary">';
		echo esc_html__( 'Create Form', 'samplehq-request-form' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Status filter tabs above the table.
	 *
	 * @return array<string, string> View links keyed by slug.
	 */
	protected function get_views(): array {
		$base_url = admin_url( 'admin.php?page=shqf-forms' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$current = sanitize_text_field( wp_unslash( $_REQUEST['status'] ?? '' ) );

		$published = $this->forms->count( [ 'status' => 'published' ] );
		$draft     = $this->forms->count( [ 'status' => 'draft' ] );
		$trashed   = $this->forms->count( [ 'status' => 'trash' ] );
		$total     = $published + $draft; // "All" excludes trashed.

		$views = [];

		$views['all'] = '<a href="' . esc_url( $base_url ) . '"'
			. ( empty( $current ) ? ' class="current"' : '' ) . '>'
			/* translators: %s: total form count */
			. sprintf( __( 'All (%s)', 'samplehq-request-form' ), number_format_i18n( $total ) )
			. '</a>';

		$views['published'] = '<a href="' . esc_url( add_query_arg( 'status', 'published', $base_url ) ) . '"'
			. ( 'published' === $current ? ' class="current"' : '' ) . '>'
			/* translators: %s: published form count */
			. sprintf( __( 'Published (%s)', 'samplehq-request-form' ), number_format_i18n( $published ) )
			. '</a>';

		$views['draft'] = '<a href="' . esc_url( add_query_arg( 'status', 'draft', $base_url ) ) . '"'
			. ( 'draft' === $current ? ' class="current"' : '' ) . '>'
			/* translators: %s: draft form count */
			. sprintf( __( 'Draft (%s)', 'samplehq-request-form' ), number_format_i18n( $draft ) )
			. '</a>';

		if ( $trashed > 0 ) {
			$views['trash'] = '<a href="' . esc_url( add_query_arg( 'status', 'trash', $base_url ) ) . '"'
				. ( 'trash' === $current ? ' class="current"' : '' ) . '>'
				/* translators: %s: trashed form count */
				. sprintf( __( 'Trash (%s)', 'samplehq-request-form' ), number_format_i18n( $trashed ) )
				. '</a>';
		}

		return $views;
	}
}
