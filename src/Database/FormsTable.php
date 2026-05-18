<?php
/**
 * Forms database repository.
 *
 * @package SampleHQForm\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD operations for the shqf_forms table.
 */
class FormsTable {

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
	 * Valid form statuses.
	 *
	 * @var string[]
	 */
	private const VALID_STATUSES = [ 'published', 'draft', 'trash' ];

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'shqf_forms';
	}

	/**
	 * Create a new form.
	 *
	 * @param array<string, mixed> $data Form data: title (required), slug, config, status,
	 *                                   email_config, spam_config, settings, created_by.
	 * @return int The new form ID.
	 * @throws \InvalidArgumentException If required fields are missing.
	 * @throws \RuntimeException If the database insert fails.
	 */
	public function create( array $data ): int {
		if ( empty( $data['title'] ) ) {
			throw new \InvalidArgumentException( 'Form title is required.' );
		}

		$title = sanitize_text_field( $data['title'] );
		$slug  = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $title );
		$slug  = $this->unique_slug( $slug );
		$now   = current_time( 'mysql', true );

		$config = $data['config'] ?? $this->default_config();
		if ( is_array( $config ) ) {
			$config = wp_json_encode( $config );
		}

		$row = [
			'title'             => $title,
			'slug'              => $slug,
			'status'            => $this->validate_status( $data['status'] ?? 'draft' ),
			'config'            => $config,
			'email_config'      => $this->encode_json( $data['email_config'] ?? null ),
			'spam_config'       => $this->encode_json( $data['spam_config'] ?? null ),
			'settings'          => $this->encode_json( $data['settings'] ?? null ),
			'submissions_count' => 0,
			'created_by'        => isset( $data['created_by'] ) ? absint( $data['created_by'] ) : null,
			'created_at'        => $now,
			'updated_at'        => $now,
		];

		$formats = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' ];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->insert( $this->table, $row, $formats );

		if ( false === $result ) {
			throw new \RuntimeException( 'Failed to insert form into database.' );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Get a form by ID.
	 *
	 * @param int $id Form ID.
	 * @return array<string, mixed>|null Form data or null if not found.
	 */
	public function get( int $id ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return ! empty( $row ) ? $this->decode_row( $row ) : null;
	}

	/**
	 * Get a form by slug.
	 *
	 * @param string $slug Form slug.
	 * @return array<string, mixed>|null Form data or null if not found.
	 */
	public function get_by_slug( string $slug ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE slug = %s", $slug );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $this->wpdb->get_row( $sql, ARRAY_A );

		return ! empty( $row ) ? $this->decode_row( $row ) : null;
	}

	/**
	 * Update a form.
	 *
	 * @param int                  $id   Form ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool True on success.
	 */
	public function update( int $id, array $data ): bool {
		$allowed = [ 'title', 'slug', 'status', 'config', 'email_config', 'spam_config', 'settings', 'shq_form_id' ];
		$row     = [];
		$formats = [];

		foreach ( $allowed as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}

			switch ( $field ) {
				case 'title':
					$row['title'] = sanitize_text_field( $data['title'] );
					$formats[]    = '%s';
					break;
				case 'slug':
					$row['slug'] = sanitize_title( $data['slug'] );
					$formats[]   = '%s';
					break;
				case 'status':
					$row['status'] = $this->validate_status( $data['status'] );
					$formats[]     = '%s';
					break;
				case 'config':
				case 'email_config':
				case 'spam_config':
				case 'settings':
					$row[ $field ] = $this->encode_json( $data[ $field ] );
					$formats[]     = '%s';
					break;
				case 'shq_form_id':
					if ( null !== $data['shq_form_id'] ) {
						$row['shq_form_id'] = absint( $data['shq_form_id'] );
						$formats[]          = '%d';
					}
					// When null, skip -- handled separately below via raw query.
					break;
			}
		}

		// Handle nullable shq_form_id = null (clear the link).
		$clear_shq_id = array_key_exists( 'shq_form_id', $data ) && null === $data['shq_form_id'];

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
			// wpdb::update cannot set a column to NULL -- use a direct query.
			$null_sql = $this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$this->table} SET shq_form_id = NULL, updated_at = %s WHERE id = %d",
				current_time( 'mysql', true ),
				$id
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$this->wpdb->query( $null_sql );
		}

		return true;
	}

	/**
	 * Delete a form.
	 *
	 * Does NOT cascade to submissions. The caller (service layer) is
	 * responsible for cleaning up related submissions first.
	 *
	 * @param int $id Form ID.
	 * @return bool True on success.
	 */
	public function delete( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * List forms with optional filters.
	 *
	 * @param array<string, mixed> $filters Optional: status, search, orderby, order, limit, offset.
	 * @return array<int, array<string, mixed>> Array of form rows with decoded JSON fields.
	 */
	public function list_all( array $filters = [] ): array {
		$where   = [];
		$values  = [];
		$orderby = 'created_at';
		$order   = 'DESC';
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
			$where[]  = 'title LIKE %s';
			$values[] = $like;
		}

		if ( ! empty( $filters['orderby'] ) && in_array( $filters['orderby'], [ 'title', 'created_at', 'updated_at', 'submissions_count' ], true ) ) {
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

		if ( empty( $results ) ) {
			return [];
		}

		return array_map( [ $this, 'decode_row' ], $results );
	}

	/**
	 * Count forms with optional filters.
	 *
	 * @param array<string, mixed> $filters Optional: status, search.
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
			$where[]  = 'title LIKE %s';
			$values[] = $like;
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
	 * Atomically increment the submissions count for a form.
	 *
	 * Uses SQL atomic increment to prevent race conditions on concurrent submissions.
	 *
	 * @param int $id Form ID.
	 * @return bool True on success.
	 */
	public function increment_submissions_count( int $id ): bool {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"UPDATE {$this->table} SET submissions_count = submissions_count + 1 WHERE id = %d",
			$id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $this->wpdb->query( $sql );

		return false !== $result;
	}

	/**
	 * Atomically decrement the submissions count for a form.
	 *
	 * Uses GREATEST to prevent going below zero.
	 *
	 * @param int $id Form ID.
	 * @return bool True on success.
	 */
	public function decrement_submissions_count( int $id ): bool {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"UPDATE {$this->table} SET submissions_count = GREATEST(submissions_count - 1, 0) WHERE id = %d",
			$id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $this->wpdb->query( $sql );

		return false !== $result;
	}

	/**
	 * Clear shq_form_id on all forms (used during disconnect).
	 *
	 * @return int Number of rows updated.
	 */
	public function clear_all_shq_ids(): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $this->wpdb->query( "UPDATE {$this->table} SET shq_form_id = NULL WHERE shq_form_id IS NOT NULL" );

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Get the default form config with schema_version.
	 *
	 * @return array<string, mixed> Default config structure.
	 */
	public function default_config(): array {
		return [
			'schema_version' => 1,
			'fields'         => [],
			'appearance'     => [
				'primary_color'     => '#0F766E',
				'button_color'      => '#0F766E',
				'button_text_color' => '#FFFFFF',
				'border_radius'     => 8,
				'layout'            => 'single_column',
				'label_position'    => 'above',
			],
			'behavior'       => [
				'success_type'       => 'message',
				'success_message'    => 'Thank you! Your sample request has been submitted.',
				'redirect_url'       => '',
				'submit_button_text' => 'Submit Request',
			],
		];
	}

	/**
	 * Validate and normalize a status value.
	 *
	 * @param string $status The status to validate.
	 * @return string Valid status.
	 */
	private function validate_status( string $status ): string {
		return in_array( $status, self::VALID_STATUSES, true ) ? $status : 'draft';
	}

	/**
	 * Encode a value to JSON string for storage.
	 *
	 * @param mixed $value The value to encode (array, string, or null).
	 * @return string|null JSON string or null.
	 */
	private function encode_json( mixed $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_string( $value ) ) {
			return $value;
		}

		$encoded = wp_json_encode( $value );

		return false !== $encoded ? $encoded : null;
	}

	/**
	 * Decode JSON fields in a form row.
	 *
	 * @param array<string, mixed> $row Raw database row.
	 * @return array<string, mixed> Row with decoded JSON fields.
	 */
	private function decode_row( array $row ): array {
		$json_fields = [ 'config', 'email_config', 'spam_config', 'settings' ];

		foreach ( $json_fields as $field ) {
			if ( isset( $row[ $field ] ) && is_string( $row[ $field ] ) ) {
				$decoded       = json_decode( $row[ $field ], true );
				$row[ $field ] = ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) )
					? $decoded
					: [];
			}
		}

		return $row;
	}

	/**
	 * Generate a unique slug by appending -2, -3, etc. if needed.
	 *
	 * @param string $slug Base slug.
	 * @return string Unique slug.
	 */
	private function unique_slug( string $slug ): string {
		$original = $slug;
		$i        = 2;

		while ( true ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $this->wpdb->prepare( "SELECT id FROM {$this->table} WHERE slug = %s", $slug );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$exists = $this->wpdb->get_var( $sql );

			if ( null === $exists ) {
				return $slug;
			}

			$slug = $original . '-' . $i;
			++$i;
		}
	}
}
