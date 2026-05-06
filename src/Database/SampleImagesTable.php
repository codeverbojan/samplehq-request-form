<?php
/**
 * Sample images database repository.
 *
 * @package SampleHQForm\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD operations for the shqf_sample_images table.
 *
 * Manages multiple images per sample. The first image (lowest sort_order)
 * is considered the "featured" image for picker grid view.
 */
class SampleImagesTable {

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
		$this->table = $wpdb->prefix . 'shqf_sample_images';
	}

	/**
	 * Add an image to a sample.
	 *
	 * @param int $sample_id     Sample ID.
	 * @param int $attachment_id WP media library attachment ID.
	 * @param int $sort_order    Display order (0 = first/featured).
	 * @return int The new image row ID.
	 */
	public function add( int $sample_id, int $attachment_id, int $sort_order = 0 ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->insert(
			$this->table,
			[
				'sample_id'     => $sample_id,
				'attachment_id' => $attachment_id,
				'sort_order'    => $sort_order,
			],
			[ '%d', '%d', '%d' ]
		);

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Remove an image by its row ID.
	 *
	 * @param int $id Image row ID.
	 * @return bool True on success.
	 */
	public function remove( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete( $this->table, [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Remove all images for a sample.
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
	 * Get all images for a sample, ordered by sort_order.
	 *
	 * @param int $sample_id Sample ID.
	 * @return array<int, array<string, mixed>> Array of image rows.
	 */
	public function get_for_sample( int $sample_id ): array {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT * FROM {$this->table} WHERE sample_id = %d ORDER BY sort_order ASC",
			$sample_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $this->wpdb->get_results( $sql, ARRAY_A );

		return ! empty( $results ) ? $results : [];
	}

	/**
	 * Get the featured (first) image attachment ID for a sample.
	 *
	 * @param int $sample_id Sample ID.
	 * @return int|null Attachment ID or null if no images.
	 */
	public function get_featured( int $sample_id ): ?int {
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT attachment_id FROM {$this->table} WHERE sample_id = %d ORDER BY sort_order ASC LIMIT 1",
			$sample_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->get_var( $sql );

		return null !== $result ? (int) $result : null;
	}

	/**
	 * Get the featured (first) image attachment ID for multiple samples in a single query.
	 *
	 * @param int[] $sample_ids Array of sample IDs.
	 * @return array<int, int|null> Map of sample_id => attachment_id (null if no image).
	 */
	public function get_featured_for_samples( array $sample_ids ): array {
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

		// Fetch all images for the requested samples, ordered so the featured
		// image (lowest sort_order, then lowest id for ties) comes first per sample.
		$sql = $this->wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- table name from constructor, placeholders from array_fill.
			"SELECT sample_id, attachment_id FROM {$this->table} WHERE sample_id IN ({$placeholders}) ORDER BY sort_order ASC, id ASC",
			$sample_ids
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		// First row per sample_id wins (featured image).
		$map = array_fill_keys( $sample_ids, null );
		foreach ( $rows as $row ) {
			$sid = (int) $row['sample_id'];
			if ( null === $map[ $sid ] ) {
				$map[ $sid ] = (int) $row['attachment_id'];
			}
		}

		return $map;
	}

	/**
	 * Reorder images for a sample.
	 *
	 * @param int   $sample_id Sample ID.
	 * @param int[] $image_ids Ordered array of image row IDs.
	 * @return bool True on success.
	 */
	public function reorder( int $sample_id, array $image_ids ): bool {
		foreach ( $image_ids as $sort_order => $image_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->update(
				$this->table,
				[ 'sort_order' => $sort_order ],
				[
					'id'        => absint( $image_id ),
					'sample_id' => $sample_id,
				],
				[ '%d' ],
				[ '%d', '%d' ]
			);
		}

		return true;
	}
}
