<?php
/**
 * Submissions admin list table.
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
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;

/**
 * WP_List_Table for the Submissions admin page.
 */
class SubmissionListTable extends \WP_List_Table {

	/**
	 * Submissions repository.
	 *
	 * @var SubmissionsTable
	 */
	private SubmissionsTable $submissions;

	/**
	 * Forms repository.
	 *
	 * @var FormsTable
	 */
	private FormsTable $forms;

	/**
	 * Submission meta repository.
	 *
	 * @var SubmissionMetaTable
	 */
	private SubmissionMetaTable $meta;

	/**
	 * Form title cache.
	 *
	 * @var array<int, string>
	 */
	private array $form_names = [];

	/**
	 * Batch-loaded meta cache (submission_id => key => value).
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $meta_cache = [];

	/**
	 * Constructor.
	 *
	 * @param SubmissionsTable    $submissions Submissions repository.
	 * @param FormsTable          $forms       Forms repository.
	 * @param SubmissionMetaTable $meta        Submission meta repository.
	 */
	public function __construct( SubmissionsTable $submissions, FormsTable $forms, SubmissionMetaTable $meta ) {
		parent::__construct(
			[
				'singular' => 'submission',
				'plural'   => 'submissions',
				'ajax'     => false,
			]
		);
		$this->submissions = $submissions;
		$this->forms       = $forms;
		$this->meta        = $meta;
	}

	/**
	 * Define table columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'cb'         => '<input type="checkbox" />',
			'email'      => __( 'Email', 'samplehq-request-form' ),
			'name'       => __( 'Name', 'samplehq-request-form' ),
			'form'       => __( 'Form', 'samplehq-request-form' ),
			'status'     => __( 'Status', 'samplehq-request-form' ),
			'is_starred' => '<span class="dashicons dashicons-star-empty" title="' . esc_attr__( 'Starred', 'samplehq-request-form' ) . '"></span>',
			'created_at' => __( 'Date', 'samplehq-request-form' ),
		];
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	public function get_sortable_columns(): array {
		return [
			'email'      => [ 'email', false ],
			'created_at' => [ 'created_at', true ],
		];
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	public function get_bulk_actions(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$current_status = sanitize_text_field( wp_unslash( $_REQUEST['status'] ?? '' ) );

		$actions = [];

		if ( 'trash' !== $current_status ) {
			$actions['bulk_read']       = __( 'Mark as Read', 'samplehq-request-form' );
			$actions['bulk_spam']       = __( 'Mark as Spam', 'samplehq-request-form' );
			$actions['bulk_trash']      = __( 'Move to Trash', 'samplehq-request-form' );
			$actions['bulk_retry_sync'] = __( 'Retry Sync', 'samplehq-request-form' );
		}

		if ( 'trash' === $current_status || 'spam' === $current_status ) {
			$actions['bulk_restore'] = __( 'Restore', 'samplehq-request-form' );
		}

		if ( 'trash' === $current_status ) {
			$actions['bulk_delete'] = __( 'Delete Permanently', 'samplehq-request-form' );
		}

		return $actions;
	}

	/**
	 * Render status view tabs (All, New, Spam, Trash).
	 *
	 * @return array<string, string>
	 */
	protected function get_views(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$current = sanitize_text_field( wp_unslash( $_REQUEST['status'] ?? '' ) );
		$base    = admin_url( 'admin.php?page=shqf-submissions' );

		// Preserve form_id filter in view tab links.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view_form_id = absint( $_REQUEST['form_id'] ?? 0 );
		if ( $view_form_id > 0 ) {
			$base = add_query_arg( 'form_id', $view_form_id, $base );
		}

		$new_count   = $this->submissions->count( [ 'status' => 'new' ] );
		$spam_count  = $this->submissions->count( [ 'status' => 'spam' ] );
		$trash_count = $this->submissions->count( [ 'status' => 'trash' ] );
		$all_count   = $new_count; // "All" = only active submissions (excludes spam + trash).

		$views = [];

		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( $base ),
			'' === $current ? 'current' : '',
			esc_html__( 'All', 'samplehq-request-form' ),
			number_format_i18n( $all_count )
		);

		$views['new'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( $base . '&status=new' ),
			'new' === $current ? 'current' : '',
			esc_html__( 'New', 'samplehq-request-form' ),
			number_format_i18n( $new_count )
		);

		$views['spam'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( $base . '&status=spam' ),
			'spam' === $current ? 'current' : '',
			esc_html__( 'Spam', 'samplehq-request-form' ),
			number_format_i18n( $spam_count )
		);

		$views['trash'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
			esc_url( $base . '&status=trash' ),
			'trash' === $current ? 'current' : '',
			esc_html__( 'Trash', 'samplehq-request-form' ),
			number_format_i18n( $trash_count )
		);

		return $views;
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$per_page = $this->get_items_per_page( 'shqf_submissions_per_page', 20 );
		$page     = $this->get_pagenum();

		$filters = [
			'limit'  => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$form_id = absint( $_REQUEST['form_id'] ?? 0 );
		if ( $form_id > 0 ) {
			$filters['form_id'] = $form_id;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$status = sanitize_text_field( wp_unslash( $_REQUEST['status'] ?? '' ) );
		if ( ! empty( $status ) ) {
			$filters['status'] = $status;
		} else {
			// "All" view: only show active submissions (exclude spam + trash).
			$filters['status'] = 'new';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) );
		if ( ! empty( $search ) ) {
			$filters['search'] = $search;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$month = sanitize_text_field( wp_unslash( $_REQUEST['m'] ?? '' ) );
		if ( ! empty( $month ) ) {
			$filters['month'] = $month;
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

		$this->items = $this->submissions->list_all( $filters );
		$total       = $this->submissions->count(
			array_filter(
				[
					'form_id' => $form_id > 0 ? $form_id : null,
					'status'  => $status,
					'search'  => $search,
					'month'   => ! empty( $month ) ? $month : null,
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

		// Batch-preload meta for items that need email/name fallback.
		$need_meta = [];
		foreach ( $this->items as $item ) {
			$has_email = ! empty( $item['email'] );
			$has_name  = ! empty( trim( ( $item['first_name'] ?? '' ) . ( $item['last_name'] ?? '' ) ) );
			if ( ! $has_email || ! $has_name ) {
				$need_meta[] = (int) $item['id'];
			}
		}
		if ( ! empty( $need_meta ) ) {
			$this->meta_cache = $this->meta->get_batch(
				$need_meta,
				[ 'email', 'email_address', 'name' ]
			);
		}
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string, mixed> $item The submission row.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return '<input type="checkbox" name="submission_ids[]" value="' . esc_attr( $item['id'] ) . '" />';
	}

	/**
	 * Email column with row actions.
	 *
	 * @param array<string, mixed> $item The submission row.
	 * @return string
	 */
	protected function column_email( $item ): string {
		$id       = (int) $item['id'];
		$view_url = admin_url( 'admin.php?page=shqf-submissions&action=view&id=' . $id );
		$status   = $item['status'] ?? 'new';

		$actions = [
			'view' => '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'samplehq-request-form' ) . '</a>',
		];

		if ( 'spam' !== $status ) {
			$actions['spam'] = '<a href="' . esc_url(
				wp_nonce_url(
					admin_url( 'admin.php?page=shqf-submissions&action=mark_spam&id=' . $id ),
					'shqf_submission_action_' . $id
				)
			) . '">' . esc_html__( 'Spam', 'samplehq-request-form' ) . '</a>';
		}

		if ( 'trash' !== $status ) {
			$actions['trash'] = '<a href="' . esc_url(
				wp_nonce_url(
					admin_url( 'admin.php?page=shqf-submissions&action=to_trash&id=' . $id ),
					'shqf_submission_action_' . $id
				)
			) . '">' . esc_html__( 'Trash', 'samplehq-request-form' ) . '</a>';
		}

		if ( 'trash' === $status || 'spam' === $status ) {
			$actions['restore'] = '<a href="' . esc_url(
				wp_nonce_url(
					admin_url( 'admin.php?page=shqf-submissions&action=restore&id=' . $id ),
					'shqf_submission_action_' . $id
				)
			) . '">' . esc_html__( 'Restore', 'samplehq-request-form' ) . '</a>';
		}

		if ( 'trash' === $status ) {
			$actions['delete'] = '<a href="' . esc_url(
				wp_nonce_url(
					admin_url( 'admin.php?page=shqf-submissions&action=delete_permanently&id=' . $id ),
					'shqf_submission_action_' . $id
				)
			) . '" class="delete" onclick="return confirm(\''
				. esc_js( __( 'Delete permanently?', 'samplehq-request-form' ) ) . '\');">'
				. esc_html__( 'Delete', 'samplehq-request-form' ) . '</a>';
		}

		$email = $item['email'] ?? '';
		if ( empty( $email ) ) {
			$sid   = (int) $item['id'];
			$cache = $this->meta_cache[ $sid ] ?? [];
			$email = $cache['email'] ?? $cache['email_address'] ?? '--';
		}
		$email_esc = esc_html( $email );
		$bold      = empty( $item['is_read'] ) ? '<strong>' . $email_esc . '</strong>' : $email_esc;

		return '<a href="' . esc_url( $view_url ) . '">' . $bold . '</a>' . $this->row_actions( $actions );
	}

	/**
	 * Name column.
	 *
	 * @param array<string, mixed> $item The submission row.
	 * @return string
	 */
	protected function column_name( $item ): string {
		$first = $item['first_name'] ?? '';
		$last  = $item['last_name'] ?? '';
		$name  = trim( $first . ' ' . $last );

		if ( empty( $name ) ) {
			$sid       = (int) $item['id'];
			$cache     = $this->meta_cache[ $sid ] ?? [];
			$meta_name = $cache['name'] ?? null;
			if ( null !== $meta_name ) {
				$decoded = json_decode( $meta_name, true );
				if ( is_array( $decoded ) ) {
					$name = trim( ( $decoded['first'] ?? '' ) . ' ' . ( $decoded['last'] ?? '' ) );
				} else {
					$name = $meta_name;
				}
			}
		}

		return esc_html( ! empty( $name ) ? $name : '--' );
	}

	/**
	 * Form column -- shows form title linked to editor.
	 *
	 * @param array<string, mixed> $item The submission row.
	 * @return string
	 */
	protected function column_form( $item ): string {
		$form_id = (int) ( $item['form_id'] ?? 0 );
		if ( 0 === $form_id ) {
			return '--';
		}
		$form = $this->forms->get( $form_id );
		if ( null === $form ) {
			$this->form_names[ $form_id ] = __( 'Deleted', 'samplehq-request-form' );
			return '<em>' . esc_html( $this->form_names[ $form_id ] ) . '</em>';
		}
		$this->form_names[ $form_id ] = $form['title'] ?? '';
		$url                          = admin_url( 'admin.php?page=shqf-forms&action=edit&id=' . $form_id );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $this->form_names[ $form_id ] ) . '</a>';
	}

	/**
	 * Status column.
	 *
	 * @param array<string, mixed> $item The submission row.
	 * @return string
	 */
	protected function column_status( $item ): string {
		$status = $item['status'] ?? 'new';
		return '<span class="shqf-status shqf-status--' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
	}

	/**
	 * Starred column with toggle link.
	 *
	 * @param array<string, mixed> $item The submission row.
	 * @return string
	 */
	protected function column_is_starred( $item ): string {
		$id      = (int) $item['id'];
		$starred = ! empty( $item['is_starred'] );
		$action  = $starred ? 'unstar' : 'star';
		$icon    = $starred ? 'dashicons-star-filled' : 'dashicons-star-empty';
		$url     = wp_nonce_url(
			admin_url( 'admin.php?page=shqf-submissions&action=' . $action . '&id=' . $id ),
			'shqf_submission_action_' . $id
		);

		return '<a href="' . esc_url( $url ) . '" class="shqf-star-toggle"><span class="dashicons ' . esc_attr( $icon ) . '"></span></a>';
	}

	/**
	 * Date column.
	 *
	 * @param array<string, mixed> $item The submission row.
	 * @return string
	 */
	protected function column_created_at( $item ): string {
		if ( empty( $item['created_at'] ) ) {
			return '--';
		}
		return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] ) ) );
	}

	/**
	 * Default column.
	 *
	 * @param array<string, mixed> $item        The submission row.
	 * @param string               $column_name Column slug.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
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

		// Form filter.
		$all_forms = $this->forms->list_all( [ 'limit' => 100 ] );
		if ( ! empty( $all_forms ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_form = absint( $_REQUEST['form_id'] ?? 0 );

			echo '<label for="filter-by-form" class="screen-reader-text">';
			echo esc_html__( 'Filter by form', 'samplehq-request-form' );
			echo '</label>';
			echo '<select name="form_id" id="filter-by-form">';
			echo '<option value="">' . esc_html__( 'All Forms', 'samplehq-request-form' ) . '</option>';
			foreach ( $all_forms as $f ) {
				$fid = (int) $f['id'];
				echo '<option value="' . esc_attr( (string) $fid ) . '"' . selected( $current_form, $fid, false ) . '>';
				echo esc_html( $f['title'] ?? '' ) . '</option>';
			}
			echo '</select>';
		}

		// Month filter.
		$months = $this->submissions->get_distinct_months();
		if ( ! empty( $months ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$current_month = sanitize_text_field( wp_unslash( $_REQUEST['m'] ?? '' ) );

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
	 * No items message.
	 *
	 * @return void
	 */
	public function no_items(): void {
		echo '<div class="shqf-empty-state-box">';
		echo '<span class="dashicons dashicons-email-alt"></span>';
		echo '<p>' . esc_html__( 'No submissions yet. Once visitors submit your forms, they will appear here.', 'samplehq-request-form' ) . '</p>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-forms' ) ) . '" class="button button-primary">';
		echo esc_html__( 'View Forms', 'samplehq-request-form' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Override single_row to add unread/starred CSS classes.
	 *
	 * @param array<string, mixed> $item The submission row data.
	 * @return void
	 */
	public function single_row( $item ): void {
		$extra = '';
		if ( empty( $item['is_read'] ) ) {
			$extra .= ' shqf-submission-unread';
		}
		if ( ! empty( $item['is_starred'] ) ) {
			$extra .= ' shqf-submission-starred';
		}

		// Capture the parent's row to inject our extra classes alongside WP's own.
		ob_start();
		parent::single_row( $item );
		$row = ob_get_clean();

		if ( '' !== $extra ) {
			$row = preg_replace( '/^<tr\b/', '<tr class="' . esc_attr( trim( $extra ) ) . '"', $row, 1 );
			// If parent already set a class attribute, merge instead of duplicate.
			$row = preg_replace( '/class="([^"]*)" class="/', 'class="$1 ', $row, 1 );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parent already escaped.
		echo $row;
	}
}
