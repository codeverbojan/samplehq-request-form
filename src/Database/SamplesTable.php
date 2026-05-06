<?php
/**
 * Samples database repository.
 *
 * @package SampleHQForm\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD operations for the shqf_samples table.
 */
class SamplesTable {

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
		$this->table = $wpdb->prefix . 'shqf_samples';
	}

	/**
	 * Create a new sample.
	 *
	 * @param array<string, mixed> $data Sample data: name (required), sku, description,
	 *                                   max_quantity, status, sort_order.
	 * @return int The new sample ID.
	 * @throws \InvalidArgumentException If required fields are missing.
	 * @throws \RuntimeException If the database insert fails.
	 */
	public function create( array $data ): int {
		if ( empty( $data['name'] ) ) {
			throw new \InvalidArgumentException( 'Sample name is required.' );
		}

		$now = current_time( 'mysql', true );

		$row = [
			'name'         => sanitize_text_field( $data['name'] ),
			'sku'          => isset( $data['sku'] ) ? sanitize_text_field( $data['sku'] ) : null,
			'description'  => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : null,
			'max_quantity' => isset( $data['max_quantity'] ) ? absint( $data['max_quantity'] ) : 0,
			'status'       => $this->validate_status( $data['status'] ?? 'active' ),
			'sort_order'   => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			'created_at'   => $now,
			'updated_at'   => $now,
		];

		$formats = [ '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' ];

		// custom_fields column added in migration v2 -- only include if data is provided.
		if ( isset( $data['custom_fields'] ) && null !== $data['custom_fields'] ) {
			$row['custom_fields'] = is_string( $data['custom_fields'] ) ? $data['custom_fields'] : wp_json_encode( $data['custom_fields'] );
			$formats[]            = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->insert( $this->table, $row, $formats );

		if ( false === $result ) {
			throw new \RuntimeException( 'Failed to insert sample into database.' );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get a sample by ID.
	 *
	 * @param int $id Sample ID.
	 * @return array<string, mixed>|null Sample data or null if not found.
	 */
	public function get( int $id ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return ! empty( $row ) ? $row : null;
	}

	/**
	 * Update a sample.
	 *
	 * @param int                  $id   Sample ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( int $id, array $data ): bool {
		$allowed = [ 'name', 'sku', 'description', 'custom_fields', 'max_quantity', 'status', 'sort_order', 'shq_sample_id' ];
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
				case 'sku':
					$row['sku'] = null !== $data['sku'] ? sanitize_text_field( $data['sku'] ) : null;
					$formats[]  = '%s';
					break;
				case 'description':
					$row['description'] = null !== $data['description'] ? wp_kses_post( $data['description'] ) : null;
					$formats[]          = '%s';
					break;
				case 'custom_fields':
					if ( null === $data['custom_fields'] ) {
						$row['custom_fields'] = null;
					} else {
						$row['custom_fields'] = is_string( $data['custom_fields'] ) ? $data['custom_fields'] : wp_json_encode( $data['custom_fields'] );
					}
					$formats[] = '%s';
					break;
				case 'max_quantity':
					$row['max_quantity'] = absint( $data['max_quantity'] );
					$formats[]           = '%d';
					break;
				case 'status':
					$row['status'] = $this->validate_status( $data['status'] );
					$formats[]     = '%s';
					break;
				case 'sort_order':
					$row['sort_order'] = (int) $data['sort_order'];
					$formats[]         = '%d';
					break;
				case 'shq_sample_id':
					if ( null !== $data['shq_sample_id'] ) {
						$row['shq_sample_id'] = absint( $data['shq_sample_id'] );
						$formats[]            = '%d';
					}
					break;
			}
		}

		$clear_shq_id = array_key_exists( 'shq_sample_id', $data ) && null === $data['shq_sample_id'];

		if ( empty( $row ) && ! $clear_shq_id ) {
			return false;
		}

		if ( ! empty( $row ) ) {
			$row['updated_at'] = current_time( 'mysql', true );
			$formats[]         = '%s';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->wpdb->update( $this->table, $row, [ 'id' => $id ], $formats, [ '%d' ] );

			if ( false === $result ) {
				return false;
			}
		}

		if ( $clear_shq_id ) {
			$null_sql = $this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$this->table} SET shq_sample_id = NULL, updated_at = %s WHERE id = %d",
				current_time( 'mysql', true ),
				$id
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->query( $null_sql );
		}

		return true;
	}

	/**
	 * Archive a sample (soft delete).
	 *
	 * @param int $id Sample ID.
	 * @return bool True on success.
	 */
	public function archive( int $id ): bool {
		return $this->update( $id, [ 'status' => 'archived' ] );
	}

	/**
	 * Permanently delete a sample.
	 *
	 * Does NOT cascade to sample_images or sample_category_map. The caller
	 * (service layer) is responsible for cleaning up related tables first.
	 *
	 * @param int $id Sample ID.
	 * @return bool True on success.
	 */
	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * List samples with optional filters.
	 *
	 * @param array<string, mixed> $filters Optional: status, search, orderby, order, limit, offset.
	 * @return array<int, array<string, mixed>> Array of sample rows.
	 */
	public function list_all( array $filters = [] ): array {
		$where   = [];
		$values  = [];
		$orderby = 'sort_order';
		$order   = 'ASC';
		$limit   = 50;
		$offset  = 0;

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $this->validate_status( $filters['status'] );
		}

		if ( ! empty( $filters['exclude_status'] ) ) {
			$where[]  = 'status != %s';
			$values[] = sanitize_text_field( $filters['exclude_status'] );
		}

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $this->wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$where[]  = '(name LIKE %s OR sku LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		if ( ! empty( $filters['category_id'] ) ) {
			$cat_map_table = $this->wpdb->prefix . 'shqf_sample_category_map';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is from wpdb prefix (safe).
			$where[]  = "id IN (SELECT sample_id FROM {$cat_map_table} WHERE category_id = %d)";
			$values[] = absint( $filters['category_id'] );
		}

		if ( ! empty( $filters['month'] ) ) {
			$month_val = sanitize_text_field( $filters['month'] );
			// Expected format: YYYYMM (e.g. 202604).
			if ( preg_match( '/^(\d{4})(\d{2})$/', $month_val, $m ) ) {
				$where[]  = 'YEAR(created_at) = %d AND MONTH(created_at) = %d';
				$values[] = (int) $m[1];
				$values[] = (int) $m[2];
			}
		}

		if ( ! empty( $filters['orderby'] ) && in_array( $filters['orderby'], [ 'name', 'sku', 'created_at', 'sort_order' ], true ) ) {
			$orderby = $filters['orderby'];
		}

		if ( ! empty( $filters['order'] ) && in_array( strtoupper( $filters['order'] ), [ 'ASC', 'DESC' ], true ) ) {
			$order = strtoupper( $filters['order'] );
		}

		if ( isset( $filters['limit'] ) ) {
			$limit = absint( $filters['limit'] );
		}

		if ( isset( $filters['offset'] ) ) {
			$offset = absint( $filters['offset'] );
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Orderby and order are validated above via whitelist -- safe to interpolate.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM {$this->table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$values[] = $limit;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql uses prepare() with whitelisted orderby/order.
		$results = $this->wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare( $sql, $values ),
			ARRAY_A
		);

		return ! empty( $results ) ? $results : [];
	}

	/**
	 * Count samples with optional filters.
	 *
	 * @param array<string, mixed> $filters Optional: status, search, category_id, month.
	 * @return int Total count.
	 */
	public function count( array $filters = [] ): int {
		$where  = [];
		$values = [];

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $this->validate_status( $filters['status'] );
		}

		if ( ! empty( $filters['exclude_status'] ) ) {
			$where[]  = 'status != %s';
			$values[] = sanitize_text_field( $filters['exclude_status'] );
		}

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $this->wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$where[]  = '(name LIKE %s OR sku LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}

		if ( ! empty( $filters['category_id'] ) ) {
			$cat_map_table = $this->wpdb->prefix . 'shqf_sample_category_map';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is from wpdb prefix (safe).
			$where[]  = "id IN (SELECT sample_id FROM {$cat_map_table} WHERE category_id = %d)";
			$values[] = absint( $filters['category_id'] );
		}

		if ( ! empty( $filters['month'] ) ) {
			$month_val = sanitize_text_field( $filters['month'] );
			if ( preg_match( '/^(\d{4})(\d{2})$/', $month_val, $m ) ) {
				$where[]  = 'YEAR(created_at) = %d AND MONTH(created_at) = %d';
				$values[] = (int) $m[1];
				$values[] = (int) $m[2];
			}
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT COUNT(*) FROM {$this->table} {$where_clause}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			return (int) $this->wpdb->get_var( $this->wpdb->prepare( $sql, $values ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Get distinct year-month values for date filtering.
	 *
	 * @return array<int, array{year: int, month: int}> Sorted descending.
	 */
	public function get_distinct_months(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $this->wpdb->get_results(
			"SELECT DISTINCT YEAR(created_at) AS year, MONTH(created_at) AS month FROM {$this->table} WHERE status != 'trashed' ORDER BY year DESC, month DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return [];
		}

		return array_map(
			static fn( $row ) => [
				'year'  => (int) $row['year'],
				'month' => (int) $row['month'],
			],
			$results
		);
	}

	/**
	 * Validate and normalize a status value.
	 *
	 * @param string $status The status to validate.
	 * @return string Valid status.
	 */
	private function validate_status( string $status ): string {
		$valid = [ 'active', 'archived', 'trashed' ];

		return in_array( $status, $valid, true ) ? $status : 'active';
	}
}
