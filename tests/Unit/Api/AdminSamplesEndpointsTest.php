<?php
/**
 * Tests for the AdminSamplesEndpoints REST API.
 *
 * @package SampleHQForm\Tests\Unit\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Api;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Api\AdminSamplesEndpoints;
use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SampleImagesTable;
use SampleHQForm\Database\SamplesTable;

/**
 * AdminSamplesEndpoints unit tests.
 */
class AdminSamplesEndpointsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private AdminSamplesEndpoints $endpoints;
	private $samples;
	private $categories;
	private $category_map;
	private $images;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->samples      = Mockery::mock( SamplesTable::class );
		$this->categories   = Mockery::mock( SampleCategoriesTable::class );
		$this->category_map = Mockery::mock( SampleCategoryMapTable::class );
		$this->images       = Mockery::mock( SampleImagesTable::class );

		Monkey\Functions\stubs( [
			'__'                  => static fn( $s ) => $s,
			'current_user_can'    => static fn() => true,
			'get_current_user_id' => static fn() => 1,
			'absint'              => static fn( $n ) => abs( (int) $n ),
		] );

		$this->endpoints = new AdminSamplesEndpoints(
			$this->samples,
			$this->categories,
			$this->category_map,
			$this->images
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

	public function test_admin_permission(): void {
		$this->assertTrue( $this->endpoints->check_admin_permission() );
	}

	public function test_create_sample(): void {
		$this->samples->shouldReceive( 'create' )->once()->andReturn( 42 );
		$this->category_map->shouldReceive( 'sync' )->once()->with( 42, [ 1, 3 ] );

		$request  = $this->mock_request( [ 'name' => 'Kraft Mailer', 'categories' => [ 1, 3 ] ] );
		$response = $this->endpoints->create_sample( $request );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 42, $response->get_data()['id'] );
	}

	public function test_create_sample_validation_error(): void {
		$this->samples->shouldReceive( 'create' )
			->andThrow( new \InvalidArgumentException( 'Sample name is required.' ) );

		$request  = $this->mock_request( [] );
		$response = $this->endpoints->create_sample( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_get_sample(): void {
		$this->samples->shouldReceive( 'get' )->with( 5 )->andReturn( [ 'id' => '5', 'name' => 'Test' ] );
		$this->category_map->shouldReceive( 'get_categories_for_sample' )->with( 5 )->andReturn( [ 1 ] );
		$this->images->shouldReceive( 'get_for_sample' )->with( 5 )->andReturn( [] );

		$request  = $this->mock_request( [ 'id' => 5 ] );
		$response = $this->endpoints->get_sample( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Test', $response->get_data()['name'] );
		$this->assertArrayHasKey( 'categories', $response->get_data() );
		$this->assertArrayHasKey( 'images', $response->get_data() );
	}

	public function test_get_sample_not_found(): void {
		$this->samples->shouldReceive( 'get' )->andReturn( null );

		$response = $this->endpoints->get_sample( $this->mock_request( [ 'id' => 999 ] ) );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_delete_sample_cascades(): void {
		$this->samples->shouldReceive( 'get' )->with( 3 )->andReturn( [ 'id' => '3' ] );
		$this->images->shouldReceive( 'remove_all_for_sample' )->with( 3 )->once();
		$this->category_map->shouldReceive( 'remove_all_for_sample' )->with( 3 )->once();
		$this->samples->shouldReceive( 'delete' )->with( 3 )->once()->andReturn( true );

		$response = $this->endpoints->delete_sample( $this->mock_request( [ 'id' => 3 ] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['deleted'] );
	}

	public function test_list_samples_uses_batch_queries(): void {
		$samples = [
			[ 'id' => '1', 'name' => 'A' ],
			[ 'id' => '2', 'name' => 'B' ],
		];

		$this->samples->shouldReceive( 'list_all' )->once()->andReturn( $samples );
		$this->samples->shouldReceive( 'count' )->once()->andReturn( 2 );

		$this->category_map->shouldReceive( 'get_categories_for_samples' )
			->once()
			->with( [ 1, 2 ] )
			->andReturn( [ 1 => [ 5, 6 ], 2 => [] ] );

		$this->images->shouldReceive( 'get_featured_for_samples' )
			->once()
			->with( [ 1, 2 ] )
			->andReturn( [ 1 => 100, 2 => null ] );

		$request  = $this->mock_request( [] );
		$response = $this->endpoints->list_samples( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( [ 5, 6 ], $data[0]['categories'] );
		$this->assertSame( 100, $data[0]['featured_image'] );
		$this->assertSame( [], $data[1]['categories'] );
		$this->assertNull( $data[1]['featured_image'] );
	}

	public function test_update_sample(): void {
		$this->samples->shouldReceive( 'get' )->with( 5 )->andReturn( [ 'id' => '5', 'name' => 'Old' ] );
		$this->samples->shouldReceive( 'update' )->with( 5, Mockery::type( 'array' ) )->once();
		$this->category_map->shouldReceive( 'sync' )->with( 5, [ 2, 4 ] )->once();

		$response = $this->endpoints->update_sample(
			$this->mock_request( [ 'id' => 5, 'name' => 'Updated', 'categories' => [ 2, 4 ] ] )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['updated'] );
	}

	public function test_update_sample_not_found(): void {
		$this->samples->shouldReceive( 'get' )->with( 999 )->andReturn( null );

		$response = $this->endpoints->update_sample( $this->mock_request( [ 'id' => 999, 'name' => 'X' ] ) );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_register(): void {
		Monkey\Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', Mockery::type( 'array' ) );

		$this->endpoints->register();
	}
}
