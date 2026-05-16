<?php
/**
 * Syncs new submissions to the connected SampleHQ platform.
 *
 * @package SampleHQForm\Connection
 */

declare( strict_types=1 );

namespace SampleHQForm\Connection;

use SampleHQForm\Database\FormsTable;
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queues and executes submission sync to the platform via wp-cron.
 *
 * Hooks into `shqf_submission_created` to schedule a cron event, then
 * processes the sync with retry logic and error classification.
 */
class SubmissionSyncer {

	private const CRON_HOOK = 'shqf_sync_submission';

	private const MAX_ATTEMPTS = 3;

	/**
	 * Base delay in seconds for exponential backoff (30s, 60s).
	 *
	 * @var int
	 */
	private const BASE_DELAY = 30;

	/**
	 * HTTP errors that should NOT be retried.
	 *
	 * @var int[]
	 */
	private const NON_RETRYABLE = [ 400, 401, 402, 403, 404, 422, 429 ];

	/**
	 * Connection manager.
	 *
	 * @var ConnectionManager
	 */
	private ConnectionManager $connection;

	/**
	 * HMAC request signer.
	 *
	 * @var ConnectionVerifier
	 */
	private ConnectionVerifier $verifier;

	/**
	 * Submission-to-payload mapper.
	 *
	 * @var DataMapper
	 */
	private DataMapper $mapper;

	/**
	 * Submissions table repository.
	 *
	 * @var SubmissionsTable
	 */
	private SubmissionsTable $submissions;

	/**
	 * Submission meta table repository.
	 *
	 * @var SubmissionMetaTable
	 */
	private SubmissionMetaTable $submission_meta;

	/**
	 * Forms table repository.
	 *
	 * @var FormsTable
	 */
	private FormsTable $forms;

	/**
	 * Constructor.
	 *
	 * @param ConnectionManager   $connection      Connection manager.
	 * @param ConnectionVerifier  $verifier        HMAC signer.
	 * @param DataMapper          $mapper          Payload mapper.
	 * @param SubmissionsTable    $submissions     Submissions repository.
	 * @param SubmissionMetaTable $submission_meta Submission meta repository.
	 * @param FormsTable          $forms           Forms repository.
	 */
	public function __construct(
		ConnectionManager $connection,
		ConnectionVerifier $verifier,
		DataMapper $mapper,
		SubmissionsTable $submissions,
		SubmissionMetaTable $submission_meta,
		FormsTable $forms
	) {
		$this->connection      = $connection;
		$this->verifier        = $verifier;
		$this->mapper          = $mapper;
		$this->submissions     = $submissions;
		$this->submission_meta = $submission_meta;
		$this->forms           = $forms;
	}

	/**
	 * Register WordPress hooks for sync scheduling and execution.
	 */
	public function register(): void {
		add_action( 'shqf_submission_created', [ $this, 'schedule' ], 10, 2 );
		add_action( self::CRON_HOOK, [ $this, 'process' ], 10, 1 );
	}

	/**
	 * Retry a failed sync: clear error state and reschedule.
	 *
	 * @param int $submission_id Submission ID.
	 * @return bool True if retry was scheduled, false if not applicable.
	 */
	public function retry( int $submission_id ): bool {
		if ( ! $this->connection->is_connected() ) {
			return false;
		}

		$submission = $this->submissions->get( $submission_id );
		if ( null === $submission || ! empty( $submission['synced_to_shq'] ) ) {
			return false;
		}

		if ( in_array( $submission['status'] ?? '', [ 'trash', 'spam' ], true ) ) {
			return false;
		}

		if ( wp_next_scheduled( self::CRON_HOOK, [ $submission_id ] ) ) {
			return false;
		}

		$this->clear_error( $submission_id );
		wp_schedule_single_event( time(), self::CRON_HOOK, [ $submission_id ] );

		return true;
	}

	/**
	 * Schedule a sync job via wp-cron (fires immediately on next cron tick).
	 *
	 * @param int $submission_id Submission ID.
	 * @param int $form_id       Form ID (unused, required by action signature).
	 */
	public function schedule( int $submission_id, int $form_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! $this->connection->is_connected() ) {
			return;
		}

		wp_schedule_single_event( time(), self::CRON_HOOK, [ $submission_id ] );
	}

	/**
	 * Process the sync for a single submission.
	 *
	 * @param int $submission_id Submission ID to sync.
	 */
	public function process( int $submission_id ): void {
		$conn = $this->connection->get_connection();
		if ( null === $conn ) {
			$this->store_error( $submission_id, 'Not connected to platform' );
			return;
		}

		$submission = $this->submissions->get( $submission_id );
		if ( null === $submission ) {
			return;
		}

		if ( ! empty( $submission['synced_to_shq'] ) ) {
			return;
		}

		$form = $this->forms->get( (int) ( $submission['form_id'] ?? 0 ) );
		if ( null === $form ) {
			$this->store_error( $submission_id, 'Form not found' );
			return;
		}

		$meta    = $this->submission_meta->get_all( $submission_id );
		$payload = $this->mapper->map( $submission, $meta, $form );
		$body    = wp_json_encode( $payload );

		if ( false === $body ) {
			$this->store_error( $submission_id, 'Failed to encode payload' );
			return;
		}

		$url     = rtrim( $conn['workspace_url'], '/' ) . '/wp-json/samplehq/v1/plugin/submissions';
		$headers = $this->verifier->sign_request( 'POST', $url, $body, $conn['connection_secret'] );

		$headers['Content-Type'] = 'application/json';

		$response = wp_remote_post(
			$url,
			[
				'headers'   => $headers,
				'body'      => $body,
				'timeout'   => 15,
				'sslverify' => ! defined( 'SHQF_PLATFORM_URL' ),
			]
		);

		$this->handle_response( $submission_id, $response );
	}

	/**
	 * Handle the HTTP response from the platform.
	 *
	 * @param int                            $submission_id Submission ID.
	 * @param array<string, mixed>|\WP_Error $response      wp_remote_post result.
	 */
	private function handle_response( int $submission_id, $response ): void {
		if ( is_wp_error( $response ) ) {
			$this->handle_failure( $submission_id, 0, $response->get_error_message() );
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 === $code || 201 === $code ) {
			$request_id = (int) ( $data['request_id'] ?? 0 );

			if ( 201 === $code && 0 === $request_id && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'shqf_sync: 201 but no request_id for submission %d', $submission_id ) );
			}

			$this->submissions->mark_synced( $submission_id, $request_id );
			$this->clear_error( $submission_id );
			return;
		}

		$message = $data['message'] ?? ( $data['code'] ?? "HTTP {$code}" );
		$this->handle_failure( $submission_id, $code, (string) $message );
	}

	/**
	 * Handle a sync failure: store error, optionally schedule retry.
	 *
	 * @param int    $submission_id Submission ID.
	 * @param int    $http_code     HTTP status code (0 for network errors).
	 * @param string $message       Error message.
	 */
	private function handle_failure( int $submission_id, int $http_code, string $message ): void {
		$attempts = $this->get_attempts( $submission_id ) + 1;
		$this->store_error( $submission_id, $message, $attempts );

		if ( in_array( $http_code, self::NON_RETRYABLE, true ) ) {
			return;
		}

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			return;
		}

		if ( wp_next_scheduled( self::CRON_HOOK, [ $submission_id ] ) ) {
			return;
		}

		$delay = self::BASE_DELAY * ( 2 ** ( $attempts - 1 ) );
		wp_schedule_single_event( time() + $delay, self::CRON_HOOK, [ $submission_id ] );
	}

	/**
	 * Get the current sync attempt count from submission meta.
	 *
	 * @param int $submission_id Submission ID.
	 * @return int Current attempt count.
	 */
	private function get_attempts( int $submission_id ): int {
		$value = $this->submission_meta->get( $submission_id, '_sync_attempts' );

		return null !== $value ? (int) $value : 0;
	}

	/**
	 * Store sync error details in submission meta.
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $message       Error message.
	 * @param int    $attempts      Total attempt count.
	 */
	private function store_error( int $submission_id, string $message, int $attempts = 0 ): void {
		$this->submission_meta->delete( $submission_id, '_sync_error' );
		$this->submission_meta->add( $submission_id, '_sync_error', $message );

		if ( $attempts > 0 ) {
			$this->submission_meta->delete( $submission_id, '_sync_attempts' );
			$this->submission_meta->add( $submission_id, '_sync_attempts', (string) $attempts );
		}
	}

	/**
	 * Clear sync error meta after successful sync.
	 *
	 * @param int $submission_id Submission ID.
	 */
	private function clear_error( int $submission_id ): void {
		$this->submission_meta->delete( $submission_id, '_sync_error' );
		$this->submission_meta->delete( $submission_id, '_sync_attempts' );
	}
}
