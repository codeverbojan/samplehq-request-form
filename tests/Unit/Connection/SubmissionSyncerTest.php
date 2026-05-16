<?php
/**
 * Tests for the SubmissionSyncer.
 *
 * @package SampleHQForm\Tests\Unit\Connection
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Connection;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Connection\ConnectionManager;
use SampleHQForm\Connection\ConnectionVerifier;
use SampleHQForm\Connection\DataMapper;
use SampleHQForm\Connection\SubmissionSyncer;
use SampleHQForm\Database\FormsTable;
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;

class SubmissionSyncerTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private ConnectionManager $connection;
	private ConnectionVerifier $verifier;
	private DataMapper $mapper;
	private SubmissionsTable $submissions;
	private SubmissionMetaTable $submission_meta;
	private FormsTable $forms;
	private SubmissionSyncer $syncer;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->connection      = \Mockery::mock( ConnectionManager::class );
		$this->verifier        = \Mockery::mock( ConnectionVerifier::class );
		$this->mapper          = \Mockery::mock( DataMapper::class );
		$this->submissions     = \Mockery::mock( SubmissionsTable::class );
		$this->submission_meta = \Mockery::mock( SubmissionMetaTable::class );
		$this->forms           = \Mockery::mock( FormsTable::class );

		$this->syncer = new SubmissionSyncer(
			$this->connection,
			$this->verifier,
			$this->mapper,
			$this->submissions,
			$this->submission_meta,
			$this->forms
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function mock_connection(): array {
		return [
			'workspace_url'     => 'https://acme.samplehq.io',
			'workspace_id'      => 42,
			'workspace_name'    => 'Acme',
			'connection_secret' => 'test-secret',
			'connected_by'      => 'admin@acme.com',
			'connected_at'      => 1700000000,
		];
	}

	private function mock_submission( array $overrides = [] ): array {
		return array_merge(
			[
				'id'            => 100,
				'form_id'       => 1,
				'email'         => 'test@example.com',
				'synced_to_shq' => 0,
				'status'        => 'new',
				'source_url'    => 'https://example.com',
				'created_at'    => '2026-01-15 10:30:00',
			],
			$overrides
		);
	}

	// ── schedule() ───────────────────────────────────────────────────

	public function test_schedule_queues_cron_event_when_connected(): void {
		$this->connection->shouldReceive( 'is_connected' )->once()->andReturn( true );

		Monkey\Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with(
				\Mockery::on( fn( $ts ) => abs( $ts - time() ) < 2 ),
				'shqf_sync_submission',
				[ 100 ]
			);

		$this->syncer->schedule( 100, 1 );
	}

	public function test_schedule_skips_when_not_connected(): void {
		$this->connection->shouldReceive( 'is_connected' )->once()->andReturn( false );

		Monkey\Functions\expect( 'wp_schedule_single_event' )->never();

		$this->syncer->schedule( 100, 1 );
	}

	// ── process() success ────────────────────────────────────────────

	public function test_process_syncs_and_marks_synced_on_201(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [ 'email' => 'test@example.com' ] );

		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [ 'email' => 'test@example.com' ] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( '{"email":"test@example.com"}' );

		$this->verifier->shouldReceive( 'sign_request' )
			->once()
			->with( 'POST', 'https://acme.samplehq.io/wp-json/samplehq/v1/plugin/submissions', '{"email":"test@example.com"}', 'test-secret' )
			->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( [ 'response' => [ 'code' => 201 ], 'body' => '{"accepted":true,"request_id":555}' ] );

		Monkey\Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 201 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"accepted":true,"request_id":555}' );

		$this->submissions->shouldReceive( 'mark_synced' )->with( 100, 555 )->once()->andReturn( true );

		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_attempts' )->once()->andReturn( true );

		$this->syncer->process( 100 );
	}

	public function test_process_marks_synced_on_200_duplicate(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( '{}' );
		$this->verifier->shouldReceive( 'sign_request' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_remote_post' )->once()->andReturn( 'response' );
		Monkey\Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 200 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"accepted":true,"duplicate":true}' );

		$this->submissions->shouldReceive( 'mark_synced' )->with( 100, 0 )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'delete' )->twice()->andReturn( true );

		$this->syncer->process( 100 );
	}

	// ── process() skip conditions ────────────────────────────────────

	public function test_process_skips_when_not_connected(): void {
		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( null );

		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_error', 'Not connected to platform' )->once()->andReturn( 1 );

		$this->submissions->shouldNotReceive( 'get' );

		$this->syncer->process( 100 );
	}

	public function test_process_skips_when_submission_not_found(): void {
		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $this->mock_connection() );
		$this->submissions->shouldReceive( 'get' )->with( 999 )->once()->andReturn( null );

		$this->submissions->shouldNotReceive( 'mark_synced' );
		$this->mapper->shouldNotReceive( 'map' );

		$this->syncer->process( 999 );
	}

	public function test_process_skips_already_synced(): void {
		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $this->mock_connection() );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn(
			$this->mock_submission( [ 'synced_to_shq' => 1 ] )
		);

		$this->mapper->shouldNotReceive( 'map' );

		$this->syncer->process( 100 );
	}

	public function test_process_stores_error_when_form_not_found(): void {
		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $this->mock_connection() );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $this->mock_submission() );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( null );

		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_error', 'Form not found' )->once()->andReturn( 1 );

		$this->syncer->process( 100 );
	}

	// ── Retry logic ──────────────────────────────────────────────────

	public function test_5xx_error_schedules_retry_with_exponential_backoff(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( '{}' );
		$this->verifier->shouldReceive( 'sign_request' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_remote_post' )->once()->andReturn( 'response' );
		Monkey\Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 500 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"code":"internal_error","message":"Server error"}' );

		// Attempt 1 (first failure): get returns 0
		$this->submission_meta->shouldReceive( 'get' )->with( 100, '_sync_attempts' )->once()->andReturn( null );

		// Store error
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_error', 'Server error' )->once()->andReturn( 1 );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_attempts' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_attempts', '1' )->once()->andReturn( 1 );

		Monkey\Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'shqf_sync_submission', [ 100 ] )
			->andReturn( false );

		// Schedule retry with 30s delay (BASE_DELAY * 2^0)
		Monkey\Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with(
				\Mockery::on( fn( $ts ) => abs( $ts - time() - 30 ) < 2 ),
				'shqf_sync_submission',
				[ 100 ]
			);

		$this->syncer->process( 100 );
	}

	public function test_401_error_does_not_retry(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( '{}' );
		$this->verifier->shouldReceive( 'sign_request' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_remote_post' )->once()->andReturn( 'response' );
		Monkey\Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 401 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"code":"invalid_signature","message":"Auth failed"}' );

		$this->submission_meta->shouldReceive( 'get' )->with( 100, '_sync_attempts' )->once()->andReturn( null );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_error', 'Auth failed' )->once()->andReturn( 1 );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_attempts' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_attempts', '1' )->once()->andReturn( 1 );

		Monkey\Functions\expect( 'wp_schedule_single_event' )->never();

		$this->syncer->process( 100 );
	}

	public function test_max_attempts_reached_stops_retrying(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( '{}' );
		$this->verifier->shouldReceive( 'sign_request' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_remote_post' )->once()->andReturn( 'response' );
		Monkey\Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 500 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"message":"Server error"}' );

		// Already at attempt 2
		$this->submission_meta->shouldReceive( 'get' )->with( 100, '_sync_attempts' )->once()->andReturn( '2' );

		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_error', 'Server error' )->once()->andReturn( 1 );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_attempts' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_attempts', '3' )->once()->andReturn( 1 );

		Monkey\Functions\expect( 'wp_schedule_single_event' )->never();

		$this->syncer->process( 100 );
	}

	public function test_network_error_retries(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( '{}' );
		$this->verifier->shouldReceive( 'sign_request' )->once()->andReturn( [] );

		$wp_error = \Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )->once()->andReturn( 'cURL error 28: Connection timed out' );

		Monkey\Functions\expect( 'wp_remote_post' )->once()->andReturn( $wp_error );
		Monkey\Functions\expect( 'is_wp_error' )->once()->andReturn( true );

		$this->submission_meta->shouldReceive( 'get' )->with( 100, '_sync_attempts' )->once()->andReturn( null );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_error', 'cURL error 28: Connection timed out' )->once()->andReturn( 1 );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_attempts' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_attempts', '1' )->once()->andReturn( 1 );

		Monkey\Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'shqf_sync_submission', [ 100 ] )
			->andReturn( false );

		Monkey\Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with(
				\Mockery::on( fn( $ts ) => abs( $ts - time() - 30 ) < 2 ),
				'shqf_sync_submission',
				[ 100 ]
			);

		$this->syncer->process( 100 );
	}

	public function test_429_rate_limit_does_not_retry(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( '{}' );
		$this->verifier->shouldReceive( 'sign_request' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_remote_post' )->once()->andReturn( 'response' );
		Monkey\Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 429 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"code":"rate_limited","message":"Too many requests"}' );

		$this->submission_meta->shouldReceive( 'get' )->with( 100, '_sync_attempts' )->once()->andReturn( null );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_error', 'Too many requests' )->once()->andReturn( 1 );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_attempts' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_attempts', '1' )->once()->andReturn( 1 );

		Monkey\Functions\expect( 'wp_schedule_single_event' )->never();

		$this->syncer->process( 100 );
	}

	public function test_retry_skipped_when_event_already_scheduled(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( '{}' );
		$this->verifier->shouldReceive( 'sign_request' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_remote_post' )->once()->andReturn( 'response' );
		Monkey\Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 502 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"message":"Bad gateway"}' );

		$this->submission_meta->shouldReceive( 'get' )->with( 100, '_sync_attempts' )->once()->andReturn( null );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_error', 'Bad gateway' )->once()->andReturn( 1 );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_attempts' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_attempts', '1' )->once()->andReturn( 1 );

		Monkey\Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'shqf_sync_submission', [ 100 ] )
			->andReturn( time() + 30 );

		Monkey\Functions\expect( 'wp_schedule_single_event' )->never();

		$this->syncer->process( 100 );
	}

	public function test_process_sends_content_type_json_header(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( '{}' );
		$this->verifier->shouldReceive( 'sign_request' )->once()->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				\Mockery::type( 'string' ),
				\Mockery::on( function ( $args ) {
					return isset( $args['headers']['Content-Type'] )
						&& 'application/json' === $args['headers']['Content-Type'];
				} )
			)
			->andReturn( [ 'response' => [ 'code' => 201 ] ] );

		Monkey\Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 201 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"accepted":true,"request_id":1}' );

		$this->submissions->shouldReceive( 'mark_synced' )->with( 100, 1 )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'delete' )->twice()->andReturn( true );

		$this->syncer->process( 100 );
	}

	public function test_201_without_request_id_still_marks_synced(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( '{}' );
		$this->verifier->shouldReceive( 'sign_request' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_remote_post' )->once()->andReturn( 'response' );
		Monkey\Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 201 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '{"accepted":true}' );

		$this->submissions->shouldReceive( 'mark_synced' )->with( 100, 0 )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'delete' )->twice()->andReturn( true );

		$this->syncer->process( 100 );
	}

	// ── 15C.2: Edge-case sync paths ─────────────────────────────────

	public function test_process_stores_error_when_json_encode_fails(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [ 'bad' => NAN ] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( false );

		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_error', 'Failed to encode payload' )->once()->andReturn( 1 );

		Monkey\Functions\expect( 'wp_remote_post' )->never();

		$this->syncer->process( 100 );
	}

	public function test_non_json_error_body_uses_http_code_fallback(): void {
		$conn       = $this->mock_connection();
		$submission = $this->mock_submission();

		$this->connection->shouldReceive( 'get_connection' )->once()->andReturn( $conn );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()->andReturn( $submission );
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [ 'id' => 1, 'title' => 'Form', 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 100 )->once()->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->once()->andReturn( '{}' );
		$this->verifier->shouldReceive( 'sign_request' )->once()->andReturn( [] );

		Monkey\Functions\expect( 'wp_remote_post' )->once()->andReturn( 'response' );
		Monkey\Functions\expect( 'is_wp_error' )->once()->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->once()->andReturn( 502 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->once()->andReturn( '<html><body>Bad Gateway</body></html>' );

		$this->submission_meta->shouldReceive( 'get' )->with( 100, '_sync_attempts' )->once()->andReturn( null );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_error', 'HTTP 502' )->once()->andReturn( 1 );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_attempts' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'add' )->with( 100, '_sync_attempts', '1' )->once()->andReturn( 1 );

		Monkey\Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'shqf_sync_submission', [ 100 ] )
			->andReturn( false );

		Monkey\Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with(
				\Mockery::on( fn( $ts ) => abs( $ts - time() - 30 ) < 2 ),
				'shqf_sync_submission',
				[ 100 ]
			);

		$this->syncer->process( 100 );
	}

	// ── register() ───────────────────────────────────────────────────

	public function test_register_hooks_action_listeners(): void {
		Monkey\Actions\expectAdded( 'shqf_submission_created' )
			->once()
			->with( [ $this->syncer, 'schedule' ], 10, 2 );

		Monkey\Actions\expectAdded( 'shqf_sync_submission' )
			->once()
			->with( [ $this->syncer, 'process' ], 10, 1 );

		$this->syncer->register();
	}

	// ── retry() ──────────────────────────────────────────────────────

	public function test_retry_clears_error_and_schedules_cron(): void {
		$this->connection->shouldReceive( 'is_connected' )->once()->andReturn( true );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()
			->andReturn( $this->mock_submission() );

		Monkey\Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'shqf_sync_submission', [ 100 ] )
			->andReturn( false );

		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_error' )->once()->andReturn( true );
		$this->submission_meta->shouldReceive( 'delete' )->with( 100, '_sync_attempts' )->once()->andReturn( true );

		Monkey\Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with(
				\Mockery::on( fn( $ts ) => abs( $ts - time() ) < 2 ),
				'shqf_sync_submission',
				[ 100 ]
			);

		$this->assertTrue( $this->syncer->retry( 100 ) );
	}

	public function test_retry_returns_false_when_not_connected(): void {
		$this->connection->shouldReceive( 'is_connected' )->once()->andReturn( false );

		$this->assertFalse( $this->syncer->retry( 100 ) );
	}

	public function test_retry_returns_false_when_submission_not_found(): void {
		$this->connection->shouldReceive( 'is_connected' )->once()->andReturn( true );
		$this->submissions->shouldReceive( 'get' )->with( 999 )->once()->andReturn( null );

		$this->assertFalse( $this->syncer->retry( 999 ) );
	}

	public function test_retry_returns_false_when_already_synced(): void {
		$this->connection->shouldReceive( 'is_connected' )->once()->andReturn( true );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()
			->andReturn( $this->mock_submission( [ 'synced_to_shq' => 1 ] ) );

		$this->assertFalse( $this->syncer->retry( 100 ) );
	}

	public function test_retry_returns_false_when_submission_trashed(): void {
		$this->connection->shouldReceive( 'is_connected' )->once()->andReturn( true );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()
			->andReturn( $this->mock_submission( [ 'status' => 'trash' ] ) );

		Monkey\Functions\expect( 'wp_schedule_single_event' )->never();

		$this->assertFalse( $this->syncer->retry( 100 ) );
	}

	public function test_retry_returns_false_when_submission_spam(): void {
		$this->connection->shouldReceive( 'is_connected' )->once()->andReturn( true );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()
			->andReturn( $this->mock_submission( [ 'status' => 'spam' ] ) );

		Monkey\Functions\expect( 'wp_schedule_single_event' )->never();

		$this->assertFalse( $this->syncer->retry( 100 ) );
	}

	public function test_retry_returns_false_when_cron_already_scheduled(): void {
		$this->connection->shouldReceive( 'is_connected' )->once()->andReturn( true );
		$this->submissions->shouldReceive( 'get' )->with( 100 )->once()
			->andReturn( $this->mock_submission() );

		Monkey\Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'shqf_sync_submission', [ 100 ] )
			->andReturn( time() + 30 );

		Monkey\Functions\expect( 'wp_schedule_single_event' )->never();

		$this->assertFalse( $this->syncer->retry( 100 ) );
	}
}
