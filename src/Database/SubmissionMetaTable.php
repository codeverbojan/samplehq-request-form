<?php
/**
 * Submission meta database repository (EAV pattern).
 *
 * @package SampleHQForm\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD operations for the shqf_submission_meta table.
 *
 * Stores form submission field values in an EAV pattern.
 * field_value_short is auto-populated for indexed search.
 */
class SubmissionMetaTable {

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
	 * Maximum length for the indexed field_value_short column.
	 *
	 * @var int
	 */
	private const SHORT_VALUE_MAX = 191;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'shqf_submission_meta';
	}

	/**
	 * Add a meta row for a submission.
	 *
	 * Auto-populates field_value_short with the first 191 chars for indexing.
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $field_key    Field identifier.
	 * @param mixed  $field_value  Field value (string or JSON-encodable).
	 * @return int The new meta row ID.
	 * @throws \RuntimeException If the database insert fails.
	 */
	public function add( int $submission_id, string $field_key, mixed $field_value ): int {
		$value_str = is_string( $field_value ) ? $field_value : wp_json_encode( $field_value );

		if ( false === $value_str ) {
			$value_str = '';
		}

		$short = mb_substr( $value_str, 0, self::SHORT_VALUE_MAX );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->insert(
			$this->table,
			[
				'submission_id'     => $submission_id,
				'field_key'         => sanitize_text_field( $field_key ),
				'field_value'       => $value_str,
				'field_value_short' => $short,
			],
			[ '%d', '%s', '%s', '%s' ]
		);

		if ( false === $result ) {
			throw new \RuntimeException( 'Failed to insert submission meta.' );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Bulk add multiple meta rows for a submission.
	 *
	 * @param int                  $submission_id Submission ID.
	 * @param array<string, mixed> $fields        Field key => value pairs.
	 * @return void
	 */
	public function add_many( int $submission_id, array $fields ): void {
		foreach ( $fields as $key => $value ) {
			$this->add( $submission_id, $key, $value );
		}
	}

	/**
	 * Get all meta for a submission.
	 *
	 * @param int $submission_id Submission ID.
	 * @return array<string, mixed> Associative array of field_key => field_value.
	 */
	public function get_all( int $submission_id ): array {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT field_key, field_value FROM {$this->table} WHERE submission_id = %d",
			$submission_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $rows ) ) {
			return [];
		}

		$meta = [];
		foreach ( $rows as $row ) {
			$meta[ $row['field_key'] ] = $row['field_value'];
		}

		return $meta;
	}

	/**
	 * Batch-load specific meta keys for multiple submissions.
	 *
	 * Returns a nested array keyed by submission_id => field_key => value.
	 *
	 * @param int[]    $submission_ids Submission IDs.
	 * @param string[] $field_keys     Meta keys to load.
	 * @return array<int, array<string, string>> Keyed by submission_id.
	 */
	public function get_batch( array $submission_ids, array $field_keys ): array {
		if ( empty( $submission_ids ) || empty( $field_keys ) ) {
			return [];
		}

		$id_placeholders  = implode( ',', array_fill( 0, count( $submission_ids ), '%d' ) );
		$key_placeholders = implode( ',', array_fill( 0, count( $field_keys ), '%s' ) );
		$values           = array_merge( array_map( 'absint', $submission_ids ), array_map( 'sanitize_text_field', $field_keys ) );

		$sql = $this->wpdb->prepare(
			"SELECT submission_id, field_key, field_value FROM {$this->table} WHERE submission_id IN ({$id_placeholders}) AND field_key IN ({$key_placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$values
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		$result = [];
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$sid                                 = (int) $row['submission_id'];
				$result[ $sid ][ $row['field_key'] ] = $row['field_value'];
			}
		}

		return $result;
	}

	/**
	 * Get a single meta value for a submission.
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $field_key    Field identifier.
	 * @return string|null The field value or null if not found.
	 */
	public function get( int $submission_id, string $field_key ): ?string {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT field_value FROM {$this->table} WHERE submission_id = %d AND field_key = %s",
			$submission_id,
			$field_key
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$value = $this->wpdb->get_var( $sql );

		return null !== $value ? (string) $value : null;
	}

	/**
	 * Delete all meta for a submission.
	 *
	 * @param int $submission_id Submission ID.
	 * @return bool True on success.
	 */
	public function delete_all( int $submission_id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete(
			$this->table,
			[ 'submission_id' => $submission_id ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Delete a single meta row.
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $field_key    Field identifier.
	 * @return bool True on success.
	 */
	public function delete( int $submission_id, string $field_key ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete(
			$this->table,
			[
				'submission_id' => $submission_id,
				'field_key'     => $field_key,
			],
			[ '%d', '%s' ]
		);

		return false !== $result;
	}

	/**
	 * Count distinct submissions that include a specific sample.
	 *
	 * Sample picker data is stored as JSON array: [{"id":X,"quantity":N}, ...].
	 * We search field_value for the JSON pattern containing the sample ID.
	 *
	 * @param int $sample_id Sample ID.
	 * @return int Number of submissions.
	 */
	public function count_submissions_for_sample( int $sample_id ): int {
		$pattern = '%"id":' . $sample_id . ',%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		// Table name is from $wpdb->prefix, not user input.
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(DISTINCT submission_id) FROM {$this->table} WHERE field_value LIKE %s",
				$pattern
			)
		);
		// phpcs:enable
	}
}
