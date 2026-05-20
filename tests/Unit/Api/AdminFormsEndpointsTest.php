<?php
/**
 * Tests for the AdminFormsEndpoints REST API.
 *
 * @package SampleHQForm\Tests\Unit\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Api;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Api\AdminFormsEndpoints;
use SampleHQForm\Database\FormsTable;
use SampleHQForm\Database\RateLimitsTable;
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;

/**
 * AdminFormsEndpoints unit tests.
 */
class AdminFormsEndpointsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private AdminFormsEndpoints $endpoints;
	private $forms;
	private $submissions;
	private $submission_meta;
	private $rate_limits;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->forms           = Mockery::mock( FormsTable::class );
		$this->submissions     = Mockery::mock( SubmissionsTable::class );
		$this->submission_meta = Mockery::mock( SubmissionMetaTable::class );
		$this->rate_limits     = Mockery::mock( RateLimitsTable::class );

		Monkey\Functions\stubs( [
			'__'                  => static fn( $s ) => $s,
			'current_user_can'    => static fn() => true,
			'get_current_user_id' => static fn() => 1,
			'absint'              => static fn( $n ) => abs( (int) $n ),
		] );

		$this->endpoints = new AdminFormsEndpoints(
			$this->forms,
			$this->submissions,
			$this->submission_meta,
			$this->rate_limits
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

	public function test_create_form(): void {
		$this->forms->shouldReceive( 'create' )->once()->andReturn( 10 );

		$response = $this->endpoints->create_form( $this->mock_request( [ 'title' => 'My Form' ] ) );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 10, $response->get_data()['id'] );
	}

	public function test_get_form(): void {
		$this->forms->shouldReceive( 'get' )->with( 5 )->andReturn( [
			'id'     => '5',
			'title'  => 'Test Form',
			'config' => [ 'schema_version' => 1 ],
		] );

		$response = $this->endpoints->get_form( $this->mock_request( [ 'id' => 5 ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Test Form', $response->get_data()['title'] );
	}

	public function test_get_form_not_found(): void {
		$this->forms->shouldReceive( 'get' )->andReturn( null );

		$response = $this->endpoints->get_form( $this->mock_request( [ 'id' => 999 ] ) );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_update_form(): void {
		$this->forms->shouldReceive( 'get' )->with( 5 )->andReturn( [ 'id' => '5' ] );
		$this->forms->shouldReceive( 'update' )->with( 5, Mockery::type( 'array' ) )->once();

		$response = $this->endpoints->update_form( $this->mock_request( [ 'id' => 5, 'title' => 'Updated' ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['updated'] );
	}

	public function test_delete_form(): void {
		$this->forms->shouldReceive( 'get' )->with( 3 )->andReturn( [ 'id' => '3' ] );
		$this->submissions->shouldReceive( 'list_all' )
			->with( [ 'form_id' => 3, 'limit' => 500 ] )
			->andReturn( [] );
		$this->rate_limits->shouldReceive( 'clear_for_form' )->with( 3 )->once();
		$this->forms->shouldReceive( 'delete' )->with( 3 )->once();
		Monkey\Functions\when( 'get_option' )->justReturn( 0 );

		$response = $this->endpoints->delete_form( $this->mock_request( [ 'id' => 3 ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['deleted'] );
	}

	public function test_delete_form_cascades_submissions(): void {
		$this->forms->shouldReceive( 'get' )->with( 7 )->andReturn( [ 'id' => '7' ] );

		$this->submissions->shouldReceive( 'list_all' )
			->with( [ 'form_id' => 7, 'limit' => 500 ] )
			->andReturn(
				[ [ 'id' => '10' ], [ 'id' => '11' ] ],
				[]
			);
		$this->submission_meta->shouldReceive( 'delete_all' )->with( 10 )->once();
		$this->submission_meta->shouldReceive( 'delete_all' )->with( 11 )->once();
		$this->submissions->shouldReceive( 'delete' )->with( 10 )->once();
		$this->submissions->shouldReceive( 'delete' )->with( 11 )->once();

		$this->rate_limits->shouldReceive( 'clear_for_form' )->with( 7 )->once();
		$this->forms->shouldReceive( 'delete' )->with( 7 )->once();
		Monkey\Functions\when( 'get_option' )->justReturn( 0 );

		$response = $this->endpoints->delete_form( $this->mock_request( [ 'id' => 7 ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['deleted'] );
	}

	public function test_delete_form_clears_woo_option_when_matching(): void {
		$this->forms->shouldReceive( 'get' )->with( 5 )->andReturn( [ 'id' => '5' ] );
		$this->submissions->shouldReceive( 'list_all' )->andReturn( [] );
		$this->rate_limits->shouldReceive( 'clear_for_form' )->with( 5 )->once();
		$this->forms->shouldReceive( 'delete' )->with( 5 )->once();

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_woo_form_id', 0 )
			->andReturn( 5 );
		Monkey\Functions\expect( 'delete_option' )
			->once()
			->with( 'shqf_woo_form_id' );

		$response = $this->endpoints->delete_form( $this->mock_request( [ 'id' => 5 ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['deleted'] );
	}

	public function test_list_forms(): void {
		$forms = [
			[ 'id' => '1', 'title' => 'Form A' ],
			[ 'id' => '2', 'title' => 'Form B' ],
		];

		$this->forms->shouldReceive( 'list_all' )->once()->andReturn( $forms );
		$this->forms->shouldReceive( 'count' )->once()->andReturn( 2 );

		$response = $this->endpoints->list_forms( $this->mock_request( [] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $response->get_data() );
		$this->assertSame( '2', $response->get_headers()['X-WP-Total'] );
	}

	public function test_create_form_validation_error(): void {
		$this->forms->shouldReceive( 'create' )
			->andThrow( new \InvalidArgumentException( 'Title is required.' ) );

		$response = $this->endpoints->create_form( $this->mock_request( [] ) );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_update_form_not_found(): void {
		$this->forms->shouldReceive( 'get' )->with( 999 )->andReturn( null );

		$response = $this->endpoints->update_form( $this->mock_request( [ 'id' => 999, 'title' => 'X' ] ) );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_delete_form_not_found(): void {
		$this->forms->shouldReceive( 'get' )->with( 999 )->andReturn( null );

		$response = $this->endpoints->delete_form( $this->mock_request( [ 'id' => 999 ] ) );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_register(): void {
		Monkey\Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', Mockery::type( 'array' ) );

		$this->endpoints->register();
	}
}
