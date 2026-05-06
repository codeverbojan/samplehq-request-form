<?php
/**
 * Rate limits database repository.
 *
 * @package SampleHQForm\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IP-based rate limiting using a custom table.
 *
 * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic check-and-increment.
 * Default limit: 10 submissions per IP per form per hour (configurable via filter).
 */
class RateLimitsTable {

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
	 * Default rate limit per IP per form per window.
	 *
	 * @var int
	 */
	private const DEFAULT_LIMIT = 10;

	/**
	 * Default rate window in seconds (1 hour).
	 *
	 * @var int
	 */
	private const DEFAULT_WINDOW = 3600;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'shqf_rate_limits';
	}

	/**
	 * Check if an IP is rate limited for a form, and increment the counter if not.
	 *
	 * Uses a single atomic query: INSERT with ON DUPLICATE KEY UPDATE.
	 * If the window has expired, resets the counter. If within window, increments.
	 *
	 * @param string $ip_address Submitter IP address.
	 * @param int    $form_id    Form ID.
	 * @return bool True if the request is ALLOWED. False if rate limited.
	 */
	public function check_and_increment( string $ip_address, int $form_id ): bool {
		$limit  = $this->get_limit();
		$window = $this->get_window_seconds();
		$now    = current_time( 'mysql', true );

		// Atomic upsert with LAST_INSERT_ID() trick: the new count is stored in
		// the connection's LAST_INSERT_ID so we can read it without a separate SELECT.
		// This eliminates the TOCTOU race between INSERT and SELECT.
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"INSERT INTO {$this->table} (ip_address, form_id, count, window_start)
			VALUES (%s, %d, 1, %s)
			ON DUPLICATE KEY UPDATE
				count = LAST_INSERT_ID(IF(window_start < DATE_SUB(%s, INTERVAL %d SECOND), 1, count + 1)),
				window_start = IF(window_start < DATE_SUB(%s, INTERVAL %d SECOND), %s, window_start)",
			$ip_address,
			$form_id,
			$now,
			$now,
			$window,
			$now,
			$window,
			$now
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );

		// Fail closed: if the query errors, deny the request rather than allowing
		// untracked submissions through.
		if ( false === $result ) {
			return false;
		}

		// For an INSERT (new row), count starts at 1 and insert_id is the auto-generated
		// value (0 for this table since PK is composite). For an UPDATE, insert_id holds
		// the value passed to LAST_INSERT_ID() — the new count.
		// If it was an INSERT (rows_affected=1), count is always 1 (allowed).
		// If it was an UPDATE (rows_affected=2 for ON DUPLICATE KEY), read the count.
		if ( 1 === (int) $this->wpdb->rows_affected ) {
			return true; // New row inserted — count is 1, always allowed.
		}

		$count = (int) $this->wpdb->insert_id;

		return $count <= $limit;
	}

	/**
	 * Get the rate limit for submissions per IP per form per window.
	 *
	 * Filterable via 'shqf_rate_limit' filter.
	 *
	 * @return int Maximum submissions allowed.
	 */
	private function get_limit(): int {
		return (int) apply_filters( 'shqf_rate_limit', self::DEFAULT_LIMIT );
	}

	/**
	 * Get the rate window duration in seconds.
	 *
	 * Filterable via 'shqf_rate_window' filter.
	 *
	 * @return int Window duration in seconds.
	 */
	private function get_window_seconds(): int {
		return (int) apply_filters( 'shqf_rate_window', self::DEFAULT_WINDOW );
	}

	/**
	 * Purge expired rate limit entries.
	 *
	 * Called by daily wp-cron. Removes rows where window_start is older than 24 hours.
	 *
	 * @return int Number of rows deleted.
	 */
	public function cleanup(): int {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"DELETE FROM {$this->table} WHERE window_start < DATE_SUB(%s, INTERVAL 24 HOUR)",
			current_time( 'mysql', true )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $sql );

		return false !== $result ? (int) $result : 0;
	}

	/**
	 * Delete all rate limit entries for a specific form.
	 *
	 * Called when a form is deleted to clean up orphaned records.
	 *
	 * @param int $form_id Form ID.
	 * @return bool True on success.
	 */
	/**
	 * Delete all rate limit entries for a specific IP address.
	 *
	 * Called by the Privacy API eraser to remove PII.
	 *
	 * @param string $ip_address IP address.
	 * @return bool True on success.
	 */
	public function clear_for_ip( string $ip_address ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, [ 'ip_address' => $ip_address ], [ '%s' ] );

		return false !== $result;
	}

	/**
	 * Delete all rate limit entries for a specific form.
	 *
	 * @param int $form_id Form ID.
	 * @return bool True on success.
	 */
	public function clear_for_form( int $form_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, [ 'form_id' => $form_id ], [ '%d' ] );

		return false !== $result;
	}
}
