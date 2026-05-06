<?php
/**
 * CSV exporter for submissions.
 *
 * @package SampleHQForm\Export
 */

declare( strict_types=1 );

namespace SampleHQForm\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\SubmissionsTable;
use SampleHQForm\Database\SubmissionMetaTable;

/**
 * Exports submissions as a CSV download.
 *
 * Handles UTF-8 BOM for Excel, formula injection prevention,
 * and dynamic meta columns.
 */
class CsvExporter {

	/**
	 * Submissions repository.
	 *
	 * @var SubmissionsTable
	 */
	private SubmissionsTable $submissions;

	/**
	 * Submission meta repository.
	 *
	 * @var SubmissionMetaTable
	 */
	private SubmissionMetaTable $meta;

	/**
	 * Constructor.
	 *
	 * @param SubmissionsTable   $submissions Submissions repository.
	 * @param SubmissionMetaTable $meta        Submission meta repository.
	 */
	public function __construct( SubmissionsTable $submissions, SubmissionMetaTable $meta ) {
		$this->submissions = $submissions;
		$this->meta        = $meta;
	}

	/**
	 * Stream a CSV export to the browser and exit.
	 *
	 * @param array<string, mixed> $filters Submission filters (status, form_id, search).
	 * @return void
	 */
	public function export( array $filters = [] ): void {
		$filters['limit'] = 10000;

		$rows = $this->submissions->list_all( $filters );

		// Collect all unique meta keys across submissions.
		$meta_keys = [];
		$meta_data = [];
		foreach ( $rows as $row ) {
			$row_meta                = $this->meta->get_all( (int) $row['id'] );
			$meta_data[ $row['id'] ] = $row_meta;
			foreach ( array_keys( $row_meta ) as $key ) {
				$meta_keys[ $key ] = true;
			}
		}
		$meta_keys = array_keys( $meta_keys );

		$filename = 'submissions-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );

		// Header row.
		$headers = [ 'ID', 'Email', 'First Name', 'Last Name', 'Status', 'Source URL', 'Date' ];
		foreach ( $meta_keys as $key ) {
			$headers[] = $key;
		}

		// UTF-8 BOM for Excel compatibility.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, $headers );

		// Data rows.
		foreach ( $rows as $row ) {
			$csv_row = [
				$row['id'],
				self::safe( $row['email'] ?? '' ),
				self::safe( $row['first_name'] ?? '' ),
				self::safe( $row['last_name'] ?? '' ),
				self::safe( $row['status'] ?? '' ),
				self::safe( $row['source_url'] ?? '' ),
				self::safe( $row['created_at'] ?? '' ),
			];

			$row_meta = $meta_data[ $row['id'] ] ?? [];
			foreach ( $meta_keys as $key ) {
				$val = $row_meta[ $key ] ?? '';
				if ( is_array( $val ) ) {
					$val = implode( ', ', $val );
				}
				$csv_row[] = self::safe( (string) $val );
			}

			fputcsv( $output, $csv_row );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

	/**
	 * Sanitize a value for CSV output to prevent formula injection.
	 *
	 * @param string $value Raw cell value.
	 * @return string Safe cell value.
	 */
	public static function safe( string $value ): string {
		if ( '' === $value ) {
			return $value;
		}

		$first = $value[0];
		if ( in_array( $first, [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
