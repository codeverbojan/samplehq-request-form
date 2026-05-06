<?php
/**
 * Sample categories database repository.
 *
 * @package SampleHQForm\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD operations for the shqf_sample_categories table.
 */
class SampleCategoriesTable {

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
		$this->table = $wpdb->prefix . 'shqf_sample_categories';
	}

	/**
	 * Create a new category.
	 *
	 * @param array<string, mixed> $data Category data: name (required), slug, description, parent_id, sort_order.
	 * @return int The new category ID.
	 * @throws \InvalidArgumentException If required fields are missing.
	 * @throws \RuntimeException If the database insert fails.
	 */
	public function create( array $data ): int {
		if ( empty( $data['name'] ) ) {
			throw new \InvalidArgumentException( 'Category name is required.' );
		}

		$name = sanitize_text_field( $data['name'] );
		$slug = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $name );
		$slug = $this->unique_slug( $slug );

		$row = [
			'name'        => $name,
			'slug'        => $slug,
			'description' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'parent_id'   => isset( $data['parent_id'] ) ? absint( $data['parent_id'] ) : 0,
			'sort_order'  => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			'created_at'  => current_time( 'mysql', true ),
		];

		$formats = [ '%s', '%s', '%s', '%d', '%d', '%s' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->insert( $this->table, $row, $formats );

		if ( false === $result ) {
			throw new \RuntimeException( 'Failed to insert category into database.' );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get a category by ID.
	 *
	 * @param int $id Category ID.
	 * @return array<string, mixed>|null Category data or null if not found.
	 */
	public function get( int $id ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return ! empty( $row ) ? $row : null;
	}

	/**
	 * Get a category by slug.
	 *
	 * @param string $slug Category slug.
	 * @return array<string, mixed>|null Category data or null if not found.
	 */
	public function get_by_slug( string $slug ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE slug = %s", $slug );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return ! empty( $row ) ? $row : null;
	}

	/**
	 * Update a category.
	 *
	 * @param int                  $id   Category ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool True on success.
	 */
	public function update( int $id, array $data ): bool {
		$allowed = [ 'name', 'slug', 'description', 'parent_id', 'sort_order', 'shq_category_id' ];
		$row     = [];
		$formats = [];

		foreach ( $allowed as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}

			switch ( $field ) {
				case 'name':
					$row['name'] = sanitize_text_field( $data['name'] );
					$formats[]   = '%s';
					break;
				case 'slug':
					$row['slug'] = sanitize_title( $data['slug'] );
					$formats[]   = '%s';
					break;
				case 'description':
					$row['description'] = null !== $data['description'] ? wp_kses_post( $data['description'] ) : null;
					$formats[]          = '%s';
					break;
				case 'parent_id':
					$row['parent_id'] = absint( $data['parent_id'] );
					$formats[]        = '%d';
					break;
				case 'sort_order':
					$row['sort_order'] = (int) $data['sort_order'];
					$formats[]         = '%d';
					break;
				case 'shq_category_id':
					if ( null !== $data['shq_category_id'] ) {
						$row['shq_category_id'] = absint( $data['shq_category_id'] );
						$formats[]              = '%d';
					}
					break;
			}
		}

		$clear_shq_id = array_key_exists( 'shq_category_id', $data ) && null === $data['shq_category_id'];

		if ( empty( $row ) && ! $clear_shq_id ) {
			return false;
		}

		if ( ! empty( $row ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->wpdb->update( $this->table, $row, [ 'id' => $id ], $formats, [ '%d' ] );

			if ( false === $result ) {
				return false;
			}
		}

		if ( $clear_shq_id ) {
			$null_sql = $this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$this->table} SET shq_category_id = NULL WHERE id = %d",
				$id
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->query( $null_sql );
		}

		return true;
	}

	/**
	 * Delete a category.
	 *
	 * Does NOT cascade to sample_category_map. The caller (service layer)
	 * is responsible for cleaning up related mappings first.
	 *
	 * @param int $id Category ID.
	 * @return bool True on success.
	 */
	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * List all categories, optionally filtered by parent.
	 *
	 * @param int|null $parent_id Filter by parent ID (null = all).
	 * @return array<int, array<string, mixed>> Array of category rows.
	 */
	public function list_all( ?int $parent_id = null ): array {
		if ( null !== $parent_id ) {
			$sql = $this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table} WHERE parent_id = %d ORDER BY sort_order ASC, name ASC",
				$parent_id
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT * FROM {$this->table} ORDER BY sort_order ASC, name ASC";
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $this->wpdb->get_results( $sql, ARRAY_A );

		return ! empty( $results ) ? $results : [];
	}

	/**
	 * Generate a unique slug by appending -2, -3, etc. if needed.
	 *
	 * @param string $slug  Base slug.
	 * @param int    $exclude_id Category ID to exclude (for updates).
	 * @return string Unique slug.
	 */
	private function unique_slug( string $slug, int $exclude_id = 0 ): string {
		$original = $slug;
		$i        = 2;

		while ( true ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $this->wpdb->prepare( "SELECT id FROM {$this->table} WHERE slug = %s", $slug );

			if ( $exclude_id > 0 ) {
				$sql .= $this->wpdb->prepare( ' AND id != %d', $exclude_id );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$exists = $this->wpdb->get_var( $sql );

			if ( null === $exists ) {
				return $slug;
			}

			$slug = $original . '-' . $i;
			++$i;
		}
	}
}
