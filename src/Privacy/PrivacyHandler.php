<?php
/**
 * WordPress Privacy API integration.
 *
 * Registers personal data exporter and eraser for GDPR compliance.
 *
 * @package SampleHQForm\Privacy
 */

declare( strict_types=1 );

namespace SampleHQForm\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\RateLimitsTable;
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;

/**
 * Handles WordPress Privacy API hooks for personal data export and erasure.
 */
class PrivacyHandler {

	private const PAGE_SIZE = 50;

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
	 * Rate limits repository.
	 *
	 * @var RateLimitsTable
	 */
	private RateLimitsTable $rate_limits;

	/**
	 * Constructor.
	 *
	 * @param SubmissionsTable    $submissions Submissions repository.
	 * @param SubmissionMetaTable $meta        Submission meta repository.
	 * @param RateLimitsTable     $rate_limits Rate limits repository.
	 */
	public function __construct( SubmissionsTable $submissions, SubmissionMetaTable $meta, RateLimitsTable $rate_limits ) {
		$this->submissions = $submissions;
		$this->meta        = $meta;
		$this->rate_limits = $rate_limits;
	}

	/**
	 * Register privacy hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
	}

	/**
	 * Register the personal data exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters.
	 * @return array<string, array<string, mixed>> Modified exporters.
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['samplehq-request-form'] = [
			'exporter_friendly_name' => __( 'SampleHQ Form Submissions', 'samplehq-request-form' ),
			'callback'               => [ $this, 'export_personal_data' ],
		];

		return $exporters;
	}

	/**
	 * Register the personal data eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers.
	 * @return array<string, array<string, mixed>> Modified erasers.
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['samplehq-request-form'] = [
			'eraser_friendly_name' => __( 'SampleHQ Form Submissions', 'samplehq-request-form' ),
			'callback'             => [ $this, 'erase_personal_data' ],
		];

		return $erasers;
	}

	/**
	 * Export personal data for a given email address.
	 *
	 * @param string $email_address The email to export data for.
	 * @param int    $page          Page number (for pagination).
	 * @return array{data: array<int, array{group_id: string, group_label: string, item_id: string, data: array<int, array{name: string, value: string}>}>, done: bool}
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		$offset      = ( $page - 1 ) * self::PAGE_SIZE;
		$submissions = $this->submissions->find_by_email( $email_address, self::PAGE_SIZE, $offset );
		$export_data = [];

		foreach ( $submissions as $submission ) {
			$sub_id = (int) $submission['id'];
			$meta   = $this->meta->get_all( $sub_id );

			$data = [
				[
					'name'  => __( 'Submission ID', 'samplehq-request-form' ),
					'value' => (string) $sub_id,
				],
				[
					'name'  => __( 'Email', 'samplehq-request-form' ),
					'value' => $submission['email'] ?? '',
				],
				[
					'name'  => __( 'Name', 'samplehq-request-form' ),
					'value' => trim( ( $submission['first_name'] ?? '' ) . ' ' . ( $submission['last_name'] ?? '' ) ),
				],
				[
					'name'  => __( 'Source URL', 'samplehq-request-form' ),
					'value' => $submission['source_url'] ?? '',
				],
				[
					'name'  => __( 'IP Address', 'samplehq-request-form' ),
					'value' => $submission['ip_address'] ?? '',
				],
				[
					'name'  => __( 'User Agent', 'samplehq-request-form' ),
					'value' => $submission['user_agent'] ?? '',
				],
				[
					'name'  => __( 'Date', 'samplehq-request-form' ),
					'value' => $submission['created_at'] ?? '',
				],
			];

			// Add submitted field values.
			foreach ( $meta as $key => $value ) {
				$display = is_string( $value ) ? $value : wp_json_encode( $value );
				$data[]  = [
					'name'  => $key,
					'value' => (string) $display,
				];
			}

			$export_data[] = [
				'group_id'    => 'samplehq-form-submissions',
				'group_label' => __( 'Form Submissions', 'samplehq-request-form' ),
				'item_id'     => 'submission-' . $sub_id,
				'data'        => $data,
			];
		}

		return [
			'data' => $export_data,
			'done' => count( $submissions ) < self::PAGE_SIZE,
		];
	}

	/**
	 * Erase personal data for a given email address.
	 *
	 * Deletes all submissions and their meta for the email.
	 * Anonymizes IP addresses.
	 *
	 * @param string $email_address The email to erase data for.
	 * @param int    $page          Page number (for pagination).
	 * @return array{items_removed: int, items_retained: int, messages: string[], done: bool}
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		$submissions = $this->submissions->find_by_email( $email_address, self::PAGE_SIZE );
		$removed     = 0;
		$ips_cleaned = [];

		foreach ( $submissions as $submission ) {
			$sub_id = (int) $submission['id'];

			// Clean rate limits for this IP (once per unique IP).
			$ip = $submission['ip_address'] ?? '';
			if ( ! empty( $ip ) && ! isset( $ips_cleaned[ $ip ] ) ) {
				$this->rate_limits->clear_for_ip( $ip );
				$ips_cleaned[ $ip ] = true;
			}

			// Delete all field meta.
			$this->meta->delete_all( $sub_id );

			// Delete the submission.
			$this->submissions->delete( $sub_id );

			++$removed;
		}

		return [
			'items_removed'  => $removed,
			'items_retained' => 0,
			'messages'       => [],
			'done'           => count( $submissions ) < self::PAGE_SIZE,
		];
	}
}
