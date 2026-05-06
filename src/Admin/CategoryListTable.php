<?php
/**
 * Category admin list table.
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

/**
 * WP_List_Table for the Sample Categories admin page.
 *
 * Displays categories with columns for name, description, slug, count.
 * Supports bulk delete, sortable columns, row actions on hover.
 */
class CategoryListTable extends \WP_List_Table {

	/**
	 * Categories repository.
	 *
	 * @var SampleCategoriesTable
	 */
	private SampleCategoriesTable $categories;

	/**
	 * Category map repository.
	 *
	 * @var SampleCategoryMapTable
	 */
	private SampleCategoryMapTable $category_map;

	/**
	 * Pre-computed sample counts per category.
	 *
	 * @var array<int, int>
	 */
	private array $counts = [];

	/**
	 * Constructor.
	 *
	 * @param SampleCategoriesTable  $categories   Categories repository.
	 * @param SampleCategoryMapTable $category_map Category map repository.
	 */
	public function __construct(
		SampleCategoriesTable $categories,
		SampleCategoryMapTable $category_map
	) {
		parent::__construct(
			[
				'singular' => 'category',
				'plural'   => 'categories',
				'ajax'     => false,
			]
		);
		$this->categories   = $categories;
		$this->category_map = $category_map;
	}

	/**
	 * Define columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return [
			'cb'          => '<input type="checkbox" />',
			'name'        => __( 'Name', 'samplehq-request-form' ),
			'description' => __( 'Description', 'samplehq-request-form' ),
			'slug'        => __( 'Slug', 'samplehq-request-form' ),
			'count'       => __( 'Count', 'samplehq-request-form' ),
		];
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	protected function get_sortable_columns(): array {
		return [
			'name' => [ 'name', true ],
			'slug' => [ 'slug', false ],
		];
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return [
			'bulk_delete' => __( 'Delete', 'samplehq-request-form' ),
		];
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string, mixed> $item Category row.
	 * @return string
	 */
	protected function column_cb( $item ): string {
		return '<input type="checkbox" name="cat_ids[]" value="' . esc_attr( (string) $item['id'] ) . '" />';
	}

	/**
	 * Name column with row actions.
	 *
	 * @param array<string, mixed> $item Category row.
	 * @return string
	 */
	protected function column_name( $item ): string {
		$cat_id    = (int) $item['id'];
		$parent_id = (int) ( $item['parent_id'] ?? 0 );
		$edit_url  = admin_url( 'admin.php?page=shqf-categories&edit_cat=' . $cat_id );

		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=shqf-categories&delete_cat=' . $cat_id ),
			'shqf_delete_category_' . $cat_id
		);

		$actions = [
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'samplehq-request-form' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '" class="delete" onclick="return confirm(\''
				. esc_js( __( 'Delete this category?', 'samplehq-request-form' ) ) . '\');">'
				. esc_html__( 'Delete', 'samplehq-request-form' ) . '</a>',
		];

		$prefix = $parent_id > 0 ? '&mdash; ' : '';

		return '<strong><a href="' . esc_url( $edit_url ) . '" class="row-title">'
			. $prefix . esc_html( $item['name'] )
			. '</a></strong>' . $this->row_actions( $actions );
	}

	/**
	 * Description column.
	 *
	 * @param array<string, mixed> $item Category row.
	 * @return string
	 */
	protected function column_description( $item ): string {
		$desc = $item['description'] ?? '';
		return '' !== $desc ? esc_html( $desc ) : '&mdash;';
	}

	/**
	 * Slug column.
	 *
	 * @param array<string, mixed> $item Category row.
	 * @return string
	 */
	protected function column_slug( $item ): string {
		return esc_html( $item['slug'] ?? '' );
	}

	/**
	 * Count column.
	 *
	 * @param array<string, mixed> $item Category row.
	 * @return string
	 */
	protected function column_count( $item ): string {
		$cat_id       = (int) $item['id'];
		$sample_count = $this->counts[ $cat_id ] ?? 0;

		if ( 0 === $sample_count ) {
			return '0';
		}

		$url = admin_url( 'admin.php?page=shqf-samples&category=' . $cat_id );
		return '<a href="' . esc_url( $url ) . '">' . esc_html( (string) $sample_count ) . '</a>';
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$all_cats = $this->categories->list_all();

		// Batch-load sample counts (single query instead of N+1).
		$this->counts = $this->category_map->count_by_category();

		// Sort: parents first, then children grouped under their parent.
		$sorted = $this->sort_hierarchically( $all_cats );

		$this->items = $sorted;

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];

		$this->set_pagination_args(
			[
				'total_items' => count( $sorted ),
				'per_page'    => count( $sorted ),
				'total_pages' => 1,
			]
		);
	}

	/**
	 * Sort categories so children appear directly after their parent.
	 *
	 * @param array<int, array<string, mixed>> $cats All categories.
	 * @return array<int, array<string, mixed>> Sorted list.
	 */
	private function sort_hierarchically( array $cats ): array {
		$by_parent = [];
		foreach ( $cats as $cat ) {
			$pid = (int) ( $cat['parent_id'] ?? 0 );
			$by_parent[ $pid ][] = $cat;
		}

		$result = [];
		$this->walk_tree( $by_parent, 0, $result );

		return $result;
	}

	/**
	 * Recursively walk the category tree.
	 *
	 * @param array<int, array<int, array<string, mixed>>> $by_parent Categories grouped by parent_id.
	 * @param int                                           $parent_id Current parent.
	 * @param array<int, array<string, mixed>>             &$result   Output array.
	 * @return void
	 */
	private function walk_tree( array $by_parent, int $parent_id, array &$result ): void {
		if ( ! isset( $by_parent[ $parent_id ] ) ) {
			return;
		}
		foreach ( $by_parent[ $parent_id ] as $cat ) {
			$result[] = $cat;
			$this->walk_tree( $by_parent, (int) $cat['id'], $result );
		}
	}
}
