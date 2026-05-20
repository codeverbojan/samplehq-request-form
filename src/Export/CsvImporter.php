<?php
/**
 * CSV importer for samples.
 *
 * @package SampleHQForm\Export
 */

declare( strict_types=1 );

namespace SampleHQForm\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SamplesTable;

/**
 * Parses a CSV file and creates samples in the database.
 *
 * Expected CSV columns: name (required), sku, description, category, max_quantity.
 * Categories are created on-the-fly if they don't exist.
 */
class CsvImporter {

	/**
	 * Samples repository.
	 *
	 * @var SamplesTable
	 */
	private SamplesTable $samples;

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
	 * Constructor.
	 *
	 * @param SamplesTable           $samples      Samples repository.
	 * @param SampleCategoriesTable  $categories   Categories repository.
	 * @param SampleCategoryMapTable $category_map Category map repository.
	 */
	public function __construct(
		SamplesTable $samples,
		SampleCategoriesTable $categories,
		SampleCategoryMapTable $category_map
	) {
		$this->samples      = $samples;
		$this->categories   = $categories;
		$this->category_map = $category_map;
	}

	/**
	 * Import samples from a CSV file.
	 *
	 * @param string $file_path Absolute path to the CSV file.
	 * @return array{created: int, skipped: int, errors: string[]} Import results.
	 */
	public function import( string $file_path ): array {
		$result = [
			'created' => 0,
			'skipped' => 0,
			'errors'  => [],
		];

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			$result['errors'][] = __( 'CSV file not found or not readable.', 'samplehq-request-form' );
			return $result;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			$result['errors'][] = __( 'Could not open CSV file.', 'samplehq-request-form' );
			return $result;
		}

		// Read header row.
		$header = fgetcsv( $handle, 0, ',', '"', '\\' );
		if ( false === $header || empty( $header ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
			$result['errors'][] = __( 'CSV file is empty or has no header row.', 'samplehq-request-form' );
			return $result;
		}

		$header   = array_map( 'strtolower', array_map( 'trim', $header ) );
		$name_col = array_search( 'name', $header, true );

		if ( false === $name_col ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
			$result['errors'][] = __( 'CSV must have a "name" column.', 'samplehq-request-form' );
			return $result;
		}

		$row_num = 1;

		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			++$row_num;

			$data = $this->map_row( $header, $row );

			if ( empty( $data['name'] ) ) {
				++$result['skipped'];
				/* translators: %d: row number */
				$result['errors'][] = sprintf( __( 'Row %d: missing name, skipped.', 'samplehq-request-form' ), $row_num );
				continue;
			}

			try {
				$sample_id = $this->samples->create(
					[
						'name'         => $data['name'],
						'sku'          => $data['sku'] ?? null,
						'description'  => $data['description'] ?? null,
						'max_quantity' => isset( $data['max_quantity'] ) ? (int) $data['max_quantity'] : 0,
					]
				);

				// Handle category.
				if ( ! empty( $data['category'] ) ) {
					$category_id = $this->find_or_create_category( $data['category'] );
					$this->category_map->add( $sample_id, $category_id );
				}

				++$result['created'];
			} catch ( \Exception $e ) {
				++$result['skipped'];
				/* translators: 1: row number, 2: error message */
				$result['errors'][] = sprintf( __( 'Row %1$d: %2$s', 'samplehq-request-form' ), $row_num, $e->getMessage() );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );

		return $result;
	}

	/**
	 * Map a CSV row to sample data using the header.
	 *
	 * @param string[] $header Column names from the header row.
	 * @param string[] $row    Values from the current row.
	 * @return array<string, string> Mapped data.
	 */
	private function map_row( array $header, array $row ): array {
		$data = [];

		foreach ( $header as $index => $col ) {
			$value = $row[ $index ] ?? '';
			if ( '' !== trim( $value ) ) {
				$data[ $col ] = trim( $value );
			}
		}

		return $data;
	}

	/**
	 * Find an existing category by name, or create a new one.
	 *
	 * @param string $name Category name.
	 * @return int Category ID.
	 */
	private function find_or_create_category( string $name ): int {
		$slug     = sanitize_title( $name );
		$existing = $this->categories->get_by_slug( $slug );

		if ( null !== $existing ) {
			return (int) $existing['id'];
		}

		return $this->categories->create(
			[
				'name' => $name,
				'slug' => $slug,
			]
		);
	}
}
