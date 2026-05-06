<?php
/**
 * Sample-category mapping database repository.
 *
 * @package SampleHQForm\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Operations for the shqf_sample_category_map junction table.
 *
 * Manages many-to-many relationships between samples and categories.
 */
class SampleCategoryMapTable {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Full table name with prefix.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'shqf_sample_category_map';
	}

	/**
	 * Add a category association for a sample.
	 *
	 * Silently succeeds if the association already exists (INSERT IGNORE).
	 *
	 * @param int $sample_id   Sample ID.
	 * @param int $category_id Category ID.
	 * @return bool True on success.
	 */
	public function add( int $sample_id, int $category_id ): bool {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"INSERT IGNORE INTO {$this->table} (sample_id, category_id) VALUES (%d, %d)",
			$sample_id,
			$category_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );

		return false !== $result;
	}

	/**
	 * Remove a category association from a sample.
	 *
	 * @param int $sample_id   Sample ID.
	 * @param int $category_id Category ID.
	 * @return bool True on success.
	 */
	public function remove( int $sample_id, int $category_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete(
			$this->table,
			[
				'sample_id'   => $sample_id,
				'category_id' => $category_id,
			],
			[ '%d', '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Remove all category associations for a sample.
	 *
	 * @param int $sample_id Sample ID.
	 * @return bool True on success.
	 */
	public function remove_all_for_sample( int $sample_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, [ 'sample_id' => $sample_id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Remove all sample associations for a category.
	 *
	 * @param int $category_id Category ID.
	 * @return bool True on success.
	 */
	public function remove_all_for_category( int $category_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, [ 'category_id' => $category_id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Get all category IDs for a sample.
	 *
	 * @param int $sample_id Sample ID.
	 * @return int[] Array of category IDs.
	 */
	public function get_categories_for_sample( int $sample_id ): array {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT category_id FROM {$this->table} WHERE sample_id = %d",
			$sample_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $this->wpdb->get_col( $sql );

		return array_map( 'intval', $results );
	}

	/**
	 * Get sample counts grouped by category ID in a single query.
	 *
	 * @return array<int, int> Map of category_id => sample count.
	 */
	public function count_by_category(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from constructor.
			"SELECT category_id, COUNT(*) AS cnt FROM {$this->table} GROUP BY category_id",
			ARRAY_A
		);

		$counts = [];
		foreach ( $rows as $row ) {
			$counts[ (int) $row['category_id'] ] = (int) $row['cnt'];
		}

		return $counts;
	}

	/**
	 * Get all sample IDs for a category.
	 *
	 * @param int $category_id Category ID.
	 * @return int[] Array of sample IDs.
	 */
	public function get_samples_for_category( int $category_id ): array {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT sample_id FROM {$this->table} WHERE category_id = %d",
			$category_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $this->wpdb->get_col( $sql );

		return array_map( 'intval', $results );
	}

	/**
	 * Get category IDs for multiple samples in a single query.
	 *
	 * @param int[] $sample_ids Array of sample IDs.
	 * @return array<int, int[]> Map of sample_id => category_id[].
	 */
	public function get_categories_for_samples( array $sample_ids ): array {
		$sample_ids = array_values(
			array_filter(
				array_map( 'intval', $sample_ids ),
				static fn( int $id ) => $id > 0
			)
		);

		if ( empty( $sample_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $sample_ids ), '%d' ) );

		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from constructor, placeholders from array_fill.
			"SELECT sample_id, category_id FROM {$this->table} WHERE sample_id IN ({$placeholders})",
			$sample_ids
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		$map = array_fill_keys( $sample_ids, [] );
		foreach ( $rows as $row ) {
			$map[ (int) $row['sample_id'] ][] = (int) $row['category_id'];
		}

		return $map;
	}

	/**
	 * Sync category associations for a sample.
	 *
	 * Replaces all existing associations with the provided list.
	 *
	 * @param int   $sample_id    Sample ID.
	 * @param int[] $category_ids Array of category IDs.
	 * @return void
	 */
	public function sync( int $sample_id, array $category_ids ): void {
		$this->remove_all_for_sample( $sample_id );

		foreach ( $category_ids as $category_id ) {
			$this->add( $sample_id, absint( $category_id ) );
		}
	}
}
