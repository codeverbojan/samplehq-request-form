<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Api;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Api\MigrationEndpoints;
use SampleHQForm\Connection\ConnectionManager;
use SampleHQForm\Connection\MigrationEngine;

class MigrationEndpointsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private MigrationEndpoints $endpoints;
	private MigrationEngine|Mockery\MockInterface $engine;
	private ConnectionManager|Mockery\MockInterface $connection;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->engine     = Mockery::mock( MigrationEngine::class );
		$this->connection = Mockery::mock( ConnectionManager::class );

		Monkey\Functions\stubs( [
			'current_user_can' => static fn() => true,
		] );

		$this->endpoints = new MigrationEndpoints( $this->engine, $this->connection );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function mock_request( array $params = [] ): Mockery\MockInterface {
		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )
			->andReturnUsing( static fn( $key ) => $params[ $key ] ?? null );
		return $request;
	}

	public function test_check_connected_admin_when_connected(): void {
		$this->connection->shouldReceive( 'is_connected' )->andReturn( true );

		$this->assertTrue( $this->endpoints->check_connected_admin() );
	}

	public function test_check_connected_admin_when_disconnected(): void {
		$this->connection->shouldReceive( 'is_connected' )->andReturn( false );

		$this->assertFalse( $this->endpoints->check_connected_admin() );
	}

	public function test_check_connected_admin_when_not_admin(): void {
		Monkey\Functions\stubs( [
			'current_user_can' => static fn() => false,
		] );
		$this->connection->shouldNotReceive( 'is_connected' );

		$this->assertFalse( $this->endpoints->check_connected_admin() );
	}

	public function test_preview_returns_engine_data(): void {
		$preview = [
			'categories'  => 5,
			'samples'     => 20,
			'submissions' => 100,
			'platform'    => [ 'samples' => 10 ],
			'error'       => null,
		];
		$this->engine->shouldReceive( 'get_preview' )->once()->andReturn( $preview );

		$response = $this->endpoints->preview( $this->mock_request() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $preview, $response->get_data() );
	}

	public function test_preview_passes_through_error(): void {
		$preview = [
			'categories'  => 0,
			'samples'     => 0,
			'submissions' => 0,
			'platform'    => null,
			'error'       => 'Connection failed',
		];
		$this->engine->shouldReceive( 'get_preview' )->once()->andReturn( $preview );

		$response = $this->endpoints->preview( $this->mock_request() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Connection failed', $response->get_data()['error'] );
	}

	public function test_start_begins_migration(): void {
		$progress = [
			'phase'    => MigrationEngine::PHASE_CATEGORIES,
			'migrated' => [ 'categories' => 0, 'samples' => 0, 'submissions' => 0 ],
			'errors'   => [],
		];
		$this->engine->shouldReceive( 'get_progress' )->once()->andReturn( null );
		$this->engine->shouldReceive( 'start_migration' )->with( false )->once()->andReturn( $progress );

		$response = $this->endpoints->start( $this->mock_request() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( MigrationEngine::PHASE_CATEGORIES, $response->get_data()['phase'] );
	}

	public function test_start_with_submissions_flag(): void {
		$progress = [
			'phase'               => MigrationEngine::PHASE_CATEGORIES,
			'include_submissions' => true,
		];
		$this->engine->shouldReceive( 'get_progress' )->once()->andReturn( null );
		$this->engine->shouldReceive( 'start_migration' )->with( true )->once()->andReturn( $progress );

		$response = $this->endpoints->start( $this->mock_request( [ 'include_submissions' => true ] ) );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_start_without_submissions_defaults_false(): void {
		$progress = [ 'phase' => MigrationEngine::PHASE_CATEGORIES ];
		$this->engine->shouldReceive( 'get_progress' )->once()->andReturn( null );
		$this->engine->shouldReceive( 'start_migration' )->with( false )->once()->andReturn( $progress );

		$response = $this->endpoints->start( $this->mock_request( [] ) );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_start_rejects_when_already_running(): void {
		$this->engine->shouldReceive( 'get_progress' )->once()->andReturn( [
			'phase' => MigrationEngine::PHASE_SAMPLES,
		] );

		$response = $this->endpoints->start( $this->mock_request() );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'migration_in_progress', $response->get_data()['code'] );
	}

	public function test_start_allows_restart_after_complete(): void {
		$progress = [ 'phase' => MigrationEngine::PHASE_CATEGORIES ];
		$this->engine->shouldReceive( 'get_progress' )->once()->andReturn( [
			'phase' => MigrationEngine::PHASE_COMPLETE,
		] );
		$this->engine->shouldReceive( 'start_migration' )->with( false )->once()->andReturn( $progress );

		$response = $this->endpoints->start( $this->mock_request() );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_start_allows_restart_after_error(): void {
		$progress = [ 'phase' => MigrationEngine::PHASE_CATEGORIES ];
		$this->engine->shouldReceive( 'get_progress' )->once()->andReturn( [
			'phase' => MigrationEngine::PHASE_ERROR,
		] );
		$this->engine->shouldReceive( 'start_migration' )->with( false )->once()->andReturn( $progress );

		$response = $this->endpoints->start( $this->mock_request() );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_start_allows_restart_after_cancelled(): void {
		$progress = [ 'phase' => MigrationEngine::PHASE_CATEGORIES ];
		$this->engine->shouldReceive( 'get_progress' )->once()->andReturn( [
			'phase' => MigrationEngine::PHASE_CANCELLED,
		] );
		$this->engine->shouldReceive( 'start_migration' )->with( false )->once()->andReturn( $progress );

		$response = $this->endpoints->start( $this->mock_request() );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_progress_returns_engine_data(): void {
		$data = [
			'phase'    => MigrationEngine::PHASE_SAMPLES,
			'migrated' => [ 'categories' => 5, 'samples' => 3, 'submissions' => 0 ],
		];
		$this->engine->shouldReceive( 'get_progress' )->once()->andReturn( $data );

		$response = $this->endpoints->progress( $this->mock_request() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $data, $response->get_data() );
	}

	public function test_progress_returns_idle_when_no_migration(): void {
		$this->engine->shouldReceive( 'get_progress' )->once()->andReturn( null );

		$response = $this->endpoints->progress( $this->mock_request() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'idle', $response->get_data()['phase'] );
	}

	public function test_cancel_returns_cancelled(): void {
		$this->engine->shouldReceive( 'cancel_migration' )->once();

		$response = $this->endpoints->cancel( $this->mock_request() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'cancelled', $response->get_data()['status'] );
	}

	public function test_register_routes_hooks_rest_api_init(): void {
		Monkey\Functions\expect( 'add_action' )
			->with( 'rest_api_init', Mockery::type( 'array' ) )
			->once();

		$this->endpoints->register();
	}
}
