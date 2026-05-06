<?php
/**
 * Tests for the AdminCategoriesEndpoints REST API.
 *
 * @package SampleHQForm\Tests\Unit\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Api;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Api\AdminCategoriesEndpoints;
use SampleHQForm\Database\SampleCategoriesTable;

/**
 * AdminCategoriesEndpoints unit tests.
 */
class AdminCategoriesEndpointsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private AdminCategoriesEndpoints $endpoints;
	private $categories;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->categories = Mockery::mock( SampleCategoriesTable::class );

		Monkey\Functions\stubs( [
			'__'               => static fn( $s ) => $s,
			'current_user_can' => static fn() => true,
			'absint'           => static fn( $n ) => abs( (int) $n ),
			'sanitize_text_field' => static fn( $s ) => $s,
		] );

		$this->endpoints = new AdminCategoriesEndpoints( $this->categories );
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

	public function test_list_categories(): void {
		$this->categories->shouldReceive( 'list_all' )->once()->andReturn( [
			[ 'id' => 1, 'name' => 'Textiles' ],
			[ 'id' => 2, 'name' => 'Paper' ],
		] );

		$response = $this->endpoints->list_categories();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 2, $response->get_data() );
	}

	public function test_create_category(): void {
		$this->categories->shouldReceive( 'create' )->once()
			->with( [ 'name' => 'Metals', 'parent_id' => 0 ] )
			->andReturn( 5 );
		$this->categories->shouldReceive( 'get' )->with( 5 )
			->andReturn( [ 'id' => 5, 'name' => 'Metals' ] );

		$response = $this->endpoints->create_category( $this->mock_request( [ 'name' => 'Metals' ] ) );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'Metals', $response->get_data()['name'] );
	}

	public function test_create_category_empty_name(): void {
		$response = $this->endpoints->create_category( $this->mock_request( [ 'name' => '' ] ) );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_register(): void {
		Monkey\Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', Mockery::type( 'array' ) );

		$this->endpoints->register();
	}
}
