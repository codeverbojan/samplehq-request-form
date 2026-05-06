<?php
/**
 * Submissions database repository.
 *
 * @package SampleHQForm\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD operations for the shqf_submissions table.
 */
class SubmissionsTable {

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
	 * Valid submission statuses.
	 *
	 * @var string[]
	 */
	private const VALID_STATUSES = [ 'new', 'spam', 'trash' ];

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'shqf_submissions';
	}

	/**
	 * Create a new submission.
	 *
	 * Denormalizes email, first_name, last_name from the provided field data
	 * for search and display performance.
	 *
	 * @param int                  $form_id    Form ID.
	 * @param array<string, mixed> $fields     Field key => value pairs (email, first_name, etc.).
	 * @param array<string, mixed> $meta       Additional metadata: source_url, ip_address, user_agent.
	 * @return int The new submission ID.
	 * @throws \RuntimeException If the database insert fails.
	 */
	public function create( int $form_id, array $fields, array $meta = [] ): int {
		$now = current_time( 'mysql', true );

		$row = [
			'form_id'       => $form_id,
			'status'        => 'new',
			'email'         => isset( $fields['email'] ) ? sanitize_email( $fields['email'] ) : null,
			'first_name'    => isset( $fields['first_name'] ) ? sanitize_text_field( $fields['first_name'] ) : null,
			'last_name'     => isset( $fields['last_name'] ) ? sanitize_text_field( $fields['last_name'] ) : null,
			'source_url'    => isset( $meta['source_url'] ) ? esc_url_raw( $meta['source_url'] ) : null,
			'ip_address'    => isset( $meta['ip_address'] ) ? sanitize_text_field( $meta['ip_address'] ) : null,
			'user_agent'    => isset( $meta['user_agent'] ) ? sanitize_text_field( substr( $meta['user_agent'], 0, 500 ) ) : null,
			'is_starred'    => 0,
			'is_read'       => 0,
			'synced_to_shq' => 0,
			'created_at'    => $now,
			'updated_at'    => $now,
		];

		$formats = [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->insert( $this->table, $row, $formats );

		if ( false === $result ) {
			throw new \RuntimeException( 'Failed to insert submission into database.' );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get a submission by ID.
	 *
	 * @param int $id Submission ID.
	 * @return array<string, mixed>|null Submission data or null.
	 */
	public function get( int $id ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return ! empty( $row ) ? $row : null;
	}

	/**
	 * Update submission status.
	 *
	 * @param int    $id     Submission ID.
	 * @param string $status New status (new, spam, trash).
	 * @return bool True on success.
	 */
	public function update_status( int $id, string $status ): bool {
		$status = $this->validate_status( $status );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->update(
			$this->table,
			[
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Toggle the starred flag.
	 *
	 * @param int  $id      Submission ID.
	 * @param bool $starred Whether to star or unstar.
	 * @return bool True on success.
	 */
	public function set_starred( int $id, bool $starred ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->update(
			$this->table,
			[
				'is_starred' => $starred ? 1 : 0,
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Toggle the read flag.
	 *
	 * @param int  $id   Submission ID.
	 * @param bool $read Whether to mark read or unread.
	 * @return bool True on success.
	 */
	public function set_read( int $id, bool $read ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->update(
			$this->table,
			[
				'is_read'    => $read ? 1 : 0,
				'updated_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Mark a submission as synced to SampleHQ.
	 *
	 * @param int $id         Submission ID.
	 * @param int $request_id SampleHQ sample_request ID.
	 * @return bool True on success.
	 */
	public function mark_synced( int $id, int $request_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->update(
			$this->table,
			[
				'synced_to_shq'  => 1,
				'shq_request_id' => $request_id,
				'updated_at'     => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%d', '%d', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Delete a submission permanently.
	 *
	 * Does NOT cascade to submission_meta. The caller is responsible for
	 * cleaning up related meta rows first.
	 *
	 * @param int $id Submission ID.
	 * @return bool True on success.
	 */
	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * List submissions with optional filters.
	 *
	 * @param array<string, mixed> $filters Optional: form_id, status, search, is_starred,
	 *                                      orderby, order, limit, offset.
	 * @return array<int, array<string, mixed>> Array of submission rows.
	 */
	public function list_all( array $filters = [] ): array {
		$where   = [];
		$values  = [];
		$orderby = 'created_at';
		$order   = 'DESC';
		$limit   = 50;
		$offset  = 0;

		if ( ! empty( $filters['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$values[] = absint( $filters['form_id'] );
		}

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $this->validate_status( $filters['status'] );
		}

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $this->wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$where[]  = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		if ( isset( $filters['is_starred'] ) ) {
			$where[]  = 'is_starred = %d';
			$values[] = $filters['is_starred'] ? 1 : 0;
		}

		if ( ! empty( $filters['month'] ) ) {
			$month_val = sanitize_text_field( $filters['month'] );
			if ( preg_match( '/^(\d{4})(\d{2})$/', $month_val, $m ) ) {
				$where[]  = 'YEAR(created_at) = %d AND MONTH(created_at) = %d';
				$values[] = (int) $m[1];
				$values[] = (int) $m[2];
			}
		}

		if ( ! empty( $filters['orderby'] ) && in_array( $filters['orderby'], [ 'created_at', 'updated_at', 'email', 'status' ], true ) ) {
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

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM {$this->table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$values[] = $limit;
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $this->wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare( $sql, $values ),
			ARRAY_A
		);

		return ! empty( $results ) ? $results : [];
	}

	/**
	 * Count submissions with optional filters.
	 *
	 * @param array<string, mixed> $filters Optional: form_id, status, search, is_starred.
	 * @return int Total count.
	 */
	public function count( array $filters = [] ): int {
		$where  = [];
		$values = [];

		if ( ! empty( $filters['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$values[] = absint( $filters['form_id'] );
		}

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $this->validate_status( $filters['status'] );
		}

		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $this->wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$where[]  = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		if ( isset( $filters['is_starred'] ) ) {
			$where[]  = 'is_starred = %d';
			$values[] = $filters['is_starred'] ? 1 : 0;
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
	 * Find submissions by email for Privacy API.
	 *
	 * @param string $email  Email address.
	 * @param int    $limit  Maximum number of results (0 = no limit).
	 * @param int    $offset Number of rows to skip.
	 * @return array<int, array<string, mixed>> Matching submissions.
	 */
	public function find_by_email( string $email, int $limit = 0, int $offset = 0 ): array {
		$limit_clause = '';
		if ( $limit > 0 ) {
			$limit_clause = $this->wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT * FROM {$this->table} WHERE email = %s ORDER BY created_at DESC",
			sanitize_email( $email )
		) . $limit_clause;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $this->wpdb->get_results( $sql, ARRAY_A );

		return ! empty( $results ) ? $results : [];
	}

	/**
	 * Validate and normalize a status value.
	 *
	 * @param string $status The status to validate.
	 * @return string Valid status.
	 */
	private function validate_status( string $status ): string {
		return in_array( $status, self::VALID_STATUSES, true ) ? $status : 'new';
	}

	/**
	 * Nullify IP address and user agent on submissions older than the retention period.
	 *
	 * @param int $days Retention period in days.
	 * @return int Number of rows updated.
	 */
	public function purge_old_ip_data( int $days ): int {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"UPDATE {$this->table} SET ip_address = NULL, user_agent = NULL WHERE ip_address IS NOT NULL AND created_at < DATE_SUB(%s, INTERVAL %d DAY)",
			current_time( 'mysql', true ),
			$days
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );

		return false !== $result ? (int) $result : 0;
	}

	/**
	 * Get distinct year-month values for date filtering.
	 *
	 * @return array<int, array{year: int, month: int}> Sorted descending.
	 */
	public function get_distinct_months(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $this->wpdb->get_results(
			"SELECT DISTINCT YEAR(created_at) AS year, MONTH(created_at) AS month FROM {$this->table} WHERE status NOT IN ('trash','spam') ORDER BY year DESC, month DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
}
