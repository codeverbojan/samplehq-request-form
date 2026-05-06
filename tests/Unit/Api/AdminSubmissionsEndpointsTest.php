<?php
/**
 * Tests for the AdminSubmissionsEndpoints REST API.
 *
 * @package SampleHQForm\Tests\Unit\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Api;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Api\AdminSubmissionsEndpoints;
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;

/**
 * AdminSubmissionsEndpoints unit tests.
 */
class AdminSubmissionsEndpointsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private AdminSubmissionsEndpoints $endpoints;
	private $submissions;
	private $submission_meta;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->submissions     = Mockery::mock( SubmissionsTable::class );
		$this->submission_meta = Mockery::mock( SubmissionMetaTable::class );

		Monkey\Functions\stubs( [
			'__'               => static fn( $s ) => $s,
			'current_user_can' => static fn() => true,
			'absint'           => static fn( $n ) => abs( (int) $n ),
		] );

		$this->endpoints = new AdminSubmissionsEndpoints(
			$this->submissions,
			$this->submission_meta
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $params
	 * @return \WP_REST_Request&Mockery\MockInterface
	 */
	private function mock_request( array $params = [] ) {
		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )
			->andReturnUsing( static fn( $key ) => $params[ $key ] ?? null );
		return $request;
	}

	public function test_get_submission_with_meta(): void {
		$this->submissions->shouldReceive( 'get' )->with( 7 )->andReturn( [ 'id' => '7', 'email' => 'test@test.com' ] );
		$this->submission_meta->shouldReceive( 'get_all' )->with( 7 )->andReturn( [
			'email'   => 'test@test.com',
			'message' => 'Hello',
		] );

		$response = $this->endpoints->get_submission( $this->mock_request( [ 'id' => 7 ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'meta', $response->get_data() );
		$this->assertSame( 'Hello', $response->get_data()['meta']['message'] );
	}

	public function test_get_submission_not_found(): void {
		$this->submissions->shouldReceive( 'get' )->andReturn( null );

		$response = $this->endpoints->get_submission( $this->mock_request( [ 'id' => 999 ] ) );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_toggle_star(): void {
		$this->submissions->shouldReceive( 'set_starred' )->with( 5, true )->once()->andReturn( true );

		$response = $this->endpoints->toggle_star( $this->mock_request( [ 'id' => 5, 'starred' => true ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['starred'] );
	}

	public function test_toggle_read(): void {
		$this->submissions->shouldReceive( 'set_read' )->with( 3, true )->once()->andReturn( true );

		$response = $this->endpoints->toggle_read( $this->mock_request( [ 'id' => 3, 'read' => true ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['read'] );
	}

	public function test_update_status(): void {
		$this->submissions->shouldReceive( 'update_status' )->with( 3, 'spam' )->once()->andReturn( true );

		$response = $this->endpoints->update_submission_status( $this->mock_request( [ 'id' => 3, 'status' => 'spam' ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'spam', $response->get_data()['status'] );
	}

	public function test_list_submissions(): void {
		$items = [
			[ 'id' => '1', 'email' => 'a@a.com' ],
			[ 'id' => '2', 'email' => 'b@b.com' ],
		];

		$this->submissions->shouldReceive( 'list_all' )->once()->andReturn( $items );
		$this->submissions->shouldReceive( 'count' )->once()->andReturn( 2 );

		$response = $this->endpoints->list_submissions( $this->mock_request( [] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $response->get_data() );
		$this->assertSame( '2', $response->get_headers()['X-WP-Total'] );
	}

	public function test_register(): void {
		Monkey\Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', Mockery::type( 'array' ) );

		$this->endpoints->register();
	}
}
