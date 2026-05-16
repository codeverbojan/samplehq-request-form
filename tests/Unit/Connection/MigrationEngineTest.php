<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Connection;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Connection\ConnectionManager;
use SampleHQForm\Connection\ConnectionVerifier;
use SampleHQForm\Connection\DataMapper;
use SampleHQForm\Connection\MigrationEngine;
use SampleHQForm\Database\FormsTable;
use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;

class MigrationEngineTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private ConnectionManager $connection;
	private ConnectionVerifier $verifier;
	private SamplesTable $samples;
	private SampleCategoriesTable $categories;
	private SampleCategoryMapTable $category_map;
	private SubmissionsTable $submissions;
	private SubmissionMetaTable $submission_meta;
	private FormsTable $forms;
	private DataMapper $mapper;
	private MigrationEngine $engine;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->connection      = \Mockery::mock( ConnectionManager::class );
		$this->verifier        = \Mockery::mock( ConnectionVerifier::class );
		$this->samples         = \Mockery::mock( SamplesTable::class );
		$this->categories      = \Mockery::mock( SampleCategoriesTable::class );
		$this->category_map    = \Mockery::mock( SampleCategoryMapTable::class );
		$this->submissions     = \Mockery::mock( SubmissionsTable::class );
		$this->submission_meta = \Mockery::mock( SubmissionMetaTable::class );
		$this->forms           = \Mockery::mock( FormsTable::class );
		$this->mapper          = \Mockery::mock( DataMapper::class );

		$this->engine = new MigrationEngine(
			$this->connection,
			$this->verifier,
			$this->samples,
			$this->categories,
			$this->category_map,
			$this->submissions,
			$this->submission_meta,
			$this->forms,
			$this->mapper
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

	// ------------------------------------------------------------------
	// get_categories_ordered -- topological sort
	// ------------------------------------------------------------------

	public function test_categories_ordered_parents_before_children(): void {
		$this->categories->shouldReceive( 'list_all' )
			->once()
			->andReturn( [
				[ 'id' => 3, 'name' => 'Child B',   'slug' => 'child-b',   'parent_id' => 1 ],
				[ 'id' => 1, 'name' => 'Parent',    'slug' => 'parent',    'parent_id' => 0 ],
				[ 'id' => 2, 'name' => 'Child A',   'slug' => 'child-a',   'parent_id' => 1 ],
				[ 'id' => 4, 'name' => 'Top Level', 'slug' => 'top-level', 'parent_id' => 0 ],
			] );

		$result = $this->engine->get_categories_ordered();

		$ids = array_column( $result, 'id' );

		$this->assertSame( [ 1, 2, 3, 4 ], $ids );

		$parent_pos = array_search( 1, $ids, true );
		$child_a_pos = array_search( 2, $ids, true );
		$child_b_pos = array_search( 3, $ids, true );

		$this->assertLessThan( $child_a_pos, $parent_pos );
		$this->assertLessThan( $child_b_pos, $parent_pos );
	}

	public function test_categories_ordered_three_levels_deep(): void {
		$this->categories->shouldReceive( 'list_all' )
			->once()
			->andReturn( [
				[ 'id' => 3, 'name' => 'Grandchild', 'slug' => 'grandchild', 'parent_id' => 2 ],
				[ 'id' => 2, 'name' => 'Child',      'slug' => 'child',      'parent_id' => 1 ],
				[ 'id' => 1, 'name' => 'Root',       'slug' => 'root',       'parent_id' => 0 ],
			] );

		$result = $this->engine->get_categories_ordered();
		$ids = array_column( $result, 'id' );

		$this->assertSame( [ 1, 2, 3 ], $ids );
	}

	public function test_categories_ordered_empty_list(): void {
		$this->categories->shouldReceive( 'list_all' )->once()->andReturn( [] );

		$this->assertSame( [], $this->engine->get_categories_ordered() );
	}

	public function test_categories_ordered_all_roots(): void {
		$this->categories->shouldReceive( 'list_all' )
			->once()
			->andReturn( [
				[ 'id' => 3, 'name' => 'C', 'slug' => 'c', 'parent_id' => 0 ],
				[ 'id' => 1, 'name' => 'A', 'slug' => 'a', 'parent_id' => 0 ],
				[ 'id' => 2, 'name' => 'B', 'slug' => 'b', 'parent_id' => 0 ],
			] );

		$result = $this->engine->get_categories_ordered();
		$ids = array_column( $result, 'id' );

		$this->assertSame( [ 1, 2, 3 ], $ids );
	}

	// ------------------------------------------------------------------
	// get_active_samples
	// ------------------------------------------------------------------

	public function test_get_active_samples_filters_by_status(): void {
		$this->samples->shouldReceive( 'list_all' )
			->with( \Mockery::on( function ( $filters ) {
				return 'active' === $filters['status'];
			} ) )
			->once()
			->andReturn( [
				[ 'id' => 1, 'name' => 'Marble', 'status' => 'active' ],
				[ 'id' => 2, 'name' => 'Granite', 'status' => 'active' ],
			] );

		$result = $this->engine->get_active_samples();

		$this->assertCount( 2, $result );
		$this->assertSame( 'Marble', $result[0]['name'] );
	}

	// ------------------------------------------------------------------
	// get_unsynced_submissions
	// ------------------------------------------------------------------

	public function test_get_unsynced_submissions_excludes_synced(): void {
		$this->submissions->shouldReceive( 'list_all' )
			->with( \Mockery::on( function ( $filters ) {
				return 'new' === $filters['status'];
			} ) )
			->once()
			->andReturn( [
				[ 'id' => 1, 'synced_to_shq' => 0 ],
				[ 'id' => 2, 'synced_to_shq' => 1 ],
				[ 'id' => 3, 'synced_to_shq' => 0 ],
			] );

		$result = $this->engine->get_unsynced_submissions();

		$this->assertCount( 2, $result );
		$this->assertSame( 1, $result[0]['id'] );
		$this->assertSame( 3, $result[1]['id'] );
	}

	public function test_get_unsynced_submissions_empty_when_all_synced(): void {
		$this->submissions->shouldReceive( 'list_all' )
			->once()
			->andReturn( [
				[ 'id' => 1, 'synced_to_shq' => 1 ],
			] );

		$this->assertSame( [], $this->engine->get_unsynced_submissions() );
	}

	// ------------------------------------------------------------------
	// get_preview
	// ------------------------------------------------------------------

	public function test_get_preview_includes_counts_and_platform_status(): void {
		$this->categories->shouldReceive( 'list_all' )->andReturn( [
			[ 'id' => 1, 'name' => 'Cat', 'slug' => 'cat', 'parent_id' => 0 ],
		] );

		$this->samples->shouldReceive( 'list_all' )->andReturn( [
			[ 'id' => 1, 'name' => 'S1', 'status' => 'active' ],
			[ 'id' => 2, 'name' => 'S2', 'status' => 'active' ],
		] );

		$this->submissions->shouldReceive( 'list_all' )->andReturn( [
			[ 'id' => 1, 'synced_to_shq' => 0 ],
		] );

		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		Monkey\Functions\expect( 'wp_remote_get' )->once()->andReturn( [
			'response' => [ 'code' => 200 ],
			'body'     => wp_json_encode( [ 'plan' => 'starter', 'max_samples' => 50, 'current_samples' => 10 ] ),
		] );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( wp_json_encode( [ 'plan' => 'starter', 'max_samples' => 50, 'current_samples' => 10 ] ) );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );

		$preview = $this->engine->get_preview();

		$this->assertSame( 1, $preview['categories'] );
		$this->assertSame( 2, $preview['samples'] );
		$this->assertSame( 1, $preview['submissions'] );
		$this->assertSame( 'starter', $preview['platform']['plan'] );
		$this->assertNull( $preview['error'] );
	}

	public function test_get_preview_returns_error_when_disconnected(): void {
		$this->categories->shouldReceive( 'list_all' )->andReturn( [] );
		$this->samples->shouldReceive( 'list_all' )->andReturn( [] );
		$this->submissions->shouldReceive( 'list_all' )->andReturn( [] );
		$this->connection->shouldReceive( 'get_connection' )->andReturn( null );

		$preview = $this->engine->get_preview();

		$this->assertNull( $preview['platform'] );
		$this->assertSame( 'Not connected to platform', $preview['error'] );
	}

	// ------------------------------------------------------------------
	// migrate_category
	// ------------------------------------------------------------------

	public function test_migrate_category_sends_correct_payload(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )
			->once()
			->with(
				$conn['workspace_url'] . '/wp-json/samplehq/v1/plugin/categories',
				\Mockery::on( function ( $args ) {
					$body = json_decode( $args['body'], true );
					return 'Flooring' === $body['name']
						&& 'flooring' === $body['slug']
						&& 'Stone floors' === $body['description'];
				} )
			)
			->andReturn( [
				'response' => [ 'code' => 201 ],
				'body'     => wp_json_encode( [ 'term_id' => 99, 'status' => 'created' ] ),
			] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 201 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( wp_json_encode( [ 'term_id' => 99, 'status' => 'created' ] ) );

		$this->categories->shouldReceive( 'update' )
			->once()
			->with( 5, [ 'shq_category_id' => 99 ] );

		$result = $this->engine->migrate_category(
			[ 'id' => 5, 'name' => 'Flooring', 'slug' => 'flooring', 'description' => 'Stone floors', 'parent_id' => 0 ]
		);

		$this->assertSame( 'created', $result['status'] );
		$this->assertSame( 99, $result['term_id'] );
		$this->assertNull( $result['error'] );
	}

	public function test_migrate_category_with_parent_slug(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )
			->once()
			->with( \Mockery::any(), \Mockery::on( function ( $args ) {
				$body = json_decode( $args['body'], true );
				return 'parent-slug' === $body['parent_slug'];
			} ) )
			->andReturn( [
				'response' => [ 'code' => 201 ],
				'body'     => wp_json_encode( [ 'term_id' => 100, 'status' => 'created' ] ),
			] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 201 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( wp_json_encode( [ 'term_id' => 100, 'status' => 'created' ] ) );

		$this->categories->shouldReceive( 'update' )->once();

		$result = $this->engine->migrate_category(
			[ 'id' => 6, 'name' => 'Marble', 'slug' => 'marble', 'parent_id' => 10 ],
			[ 10 => 'parent-slug' ]
		);

		$this->assertSame( 'created', $result['status'] );
	}

	public function test_migrate_category_error_returns_error_status(): void {
		$this->connection->shouldReceive( 'get_connection' )->andReturn( null );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );

		$result = $this->engine->migrate_category(
			[ 'id' => 1, 'name' => 'Test', 'slug' => 'test', 'parent_id' => 0 ]
		);

		$this->assertSame( 'error', $result['status'] );
		$this->assertNotNull( $result['error'] );
	}

	// ------------------------------------------------------------------
	// migrate_all_categories
	// ------------------------------------------------------------------

	public function test_migrate_all_categories_processes_in_order(): void {
		$this->categories->shouldReceive( 'list_all' )->andReturn( [
			[ 'id' => 2, 'name' => 'Child', 'slug' => 'child', 'parent_id' => 1 ],
			[ 'id' => 1, 'name' => 'Parent', 'slug' => 'parent', 'parent_id' => 0 ],
		] );

		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		$call_order = [];

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )
			->twice()
			->andReturnUsing( function ( $url, $args ) use ( &$call_order ) {
				$body = json_decode( $args['body'], true );
				$call_order[] = $body['name'];
				return [
					'response' => [ 'code' => 201 ],
					'body'     => json_encode( [ 'term_id' => count( $call_order ) + 100, 'status' => 'created' ] ),
				];
			} );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 201 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturnUsing( function () use ( &$call_order ) {
				return json_encode( [ 'term_id' => count( $call_order ) + 100, 'status' => 'created' ] );
			} );

		$this->categories->shouldReceive( 'update' )->twice();

		$result = $this->engine->migrate_all_categories();

		$this->assertSame( [ 'Parent', 'Child' ], $call_order );
		$this->assertSame( 2, $result['created'] );
		$this->assertSame( 0, $result['updated'] );
		$this->assertEmpty( $result['errors'] );
	}

	// ------------------------------------------------------------------
	// migrate_sample_batch
	// ------------------------------------------------------------------

	public function test_migrate_sample_batch_creates_samples(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		$this->category_map->shouldReceive( 'get_categories_for_sample' )->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )
			->twice()
			->andReturn( [
				'response' => [ 'code' => 201 ],
				'body'     => json_encode( [ 'post_id' => 50, 'status' => 'created' ] ),
			] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 201 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( json_encode( [ 'post_id' => 50, 'status' => 'created' ] ) );

		$this->samples->shouldReceive( 'update' )->twice()->with( \Mockery::anyOf( 1, 2 ), [ 'shq_sample_id' => 50 ] );

		$result = $this->engine->migrate_sample_batch( [
			[ 'id' => 1, 'name' => 'Marble White', 'sku' => 'MRB-001' ],
			[ 'id' => 2, 'name' => 'Granite Black', 'sku' => 'GRN-001' ],
		] );

		$this->assertSame( 2, $result['created'] );
		$this->assertSame( 0, $result['errors'] ? count( $result['errors'] ) : 0 );
		$this->assertFalse( $result['plan_limit_reached'] );
	}

	public function test_migrate_sample_batch_stops_on_plan_limit(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		$this->category_map->shouldReceive( 'get_categories_for_sample' )->andReturn( [] );

		$responses = [
			[ 'code' => 201, 'body' => json_encode( [ 'post_id' => 50, 'status' => 'created' ] ) ],
			[ 'code' => 422, 'body' => json_encode( [ 'code' => 'plan_limit_reached', 'message' => 'Plan limit reached' ] ) ],
		];
		$response_index = 0;

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )
			->times( 2 )
			->andReturnUsing( function () use ( &$responses, &$response_index ) {
				$r = $responses[ $response_index ];
				++$response_index;
				return [ 'response' => [ 'code' => $r['code'] ], 'body' => $r['body'] ];
			} );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )
			->andReturnUsing( function ( $resp ) {
				return (int) $resp['response']['code'];
			} );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturnUsing( function ( $resp ) {
				return $resp['body'];
			} );

		$this->samples->shouldReceive( 'update' )->once()->with( 1, [ 'shq_sample_id' => 50 ] );

		$result = $this->engine->migrate_sample_batch( [
			[ 'id' => 1, 'name' => 'S1', 'sku' => 'S-001' ],
			[ 'id' => 2, 'name' => 'S2', 'sku' => 'S-002' ],
			[ 'id' => 3, 'name' => 'S3', 'sku' => 'S-003' ],
		] );

		$this->assertSame( 1, $result['created'] );
		$this->assertTrue( $result['plan_limit_reached'] );
		$this->assertSame( 2, $response_index, 'Third sample should never be sent' );
	}

	public function test_migrate_sample_includes_category_slug(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		$this->category_map->shouldReceive( 'get_categories_for_sample' )
			->with( 1 )
			->andReturn( [ 5 ] );

		$this->categories->shouldReceive( 'get' )
			->with( 5 )
			->andReturn( [ 'id' => 5, 'slug' => 'stone' ] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )
			->once()
			->with( \Mockery::any(), \Mockery::on( function ( $args ) {
				$body = json_decode( $args['body'], true );
				return 'stone' === $body['category'];
			} ) )
			->andReturn( [
				'response' => [ 'code' => 201 ],
				'body'     => json_encode( [ 'post_id' => 50, 'status' => 'created' ] ),
			] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 201 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( json_encode( [ 'post_id' => 50, 'status' => 'created' ] ) );

		$this->samples->shouldReceive( 'update' )->once();

		$this->engine->migrate_sample_batch( [
			[ 'id' => 1, 'name' => 'Marble', 'sku' => 'MRB-001' ],
		] );
	}

	public function test_migrate_sample_includes_custom_fields(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );
		$this->category_map->shouldReceive( 'get_categories_for_sample' )->andReturn( [] );

		$custom = json_encode( [ 'color' => 'white', 'finish' => 'polished' ] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )
			->once()
			->with( \Mockery::any(), \Mockery::on( function ( $args ) {
				$body = json_decode( $args['body'], true );
				return isset( $body['custom_fields'] )
					&& 'white' === $body['custom_fields']['color']
					&& 'polished' === $body['custom_fields']['finish'];
			} ) )
			->andReturn( [
				'response' => [ 'code' => 201 ],
				'body'     => json_encode( [ 'post_id' => 50, 'status' => 'created' ] ),
			] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 201 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( json_encode( [ 'post_id' => 50, 'status' => 'created' ] ) );

		$this->samples->shouldReceive( 'update' )->once();

		$this->engine->migrate_sample_batch( [
			[ 'id' => 1, 'name' => 'Marble', 'sku' => 'MRB-001', 'custom_fields' => $custom ],
		] );
	}

	public function test_migrate_sample_network_error_records_error(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );
		$this->category_map->shouldReceive( 'get_categories_for_sample' )->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );

		$wp_error = \Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )->andReturn( 'Connection timed out' );

		Monkey\Functions\expect( 'wp_remote_request' )->once()->andReturn( $wp_error );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( true );

		$result = $this->engine->migrate_sample_batch( [
			[ 'id' => 1, 'name' => 'Marble', 'sku' => 'MRB-001' ],
		] );

		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 'Connection timed out', $result['errors'][0]['error'] );
		$this->assertFalse( $result['plan_limit_reached'] );
	}

	public function test_migrate_sample_updates_shq_sample_id(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );
		$this->category_map->shouldReceive( 'get_categories_for_sample' )->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )
			->once()
			->andReturn( [
				'response' => [ 'code' => 200 ],
				'body'     => json_encode( [ 'post_id' => 77, 'status' => 'updated' ] ),
			] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( json_encode( [ 'post_id' => 77, 'status' => 'updated' ] ) );

		$this->samples->shouldReceive( 'update' )
			->once()
			->with( 1, [ 'shq_sample_id' => 77 ] );

		$result = $this->engine->migrate_sample_batch( [
			[ 'id' => 1, 'name' => 'Existing', 'sku' => 'EXT-001' ],
		] );

		$this->assertSame( 1, $result['updated'] );
	}

	// ------------------------------------------------------------------
	// Batch size constants
	// ------------------------------------------------------------------

	public function test_sample_batch_size_is_ten(): void {
		$this->assertSame( 10, $this->engine->get_sample_batch_size() );
	}

	public function test_submission_batch_size_is_fifty(): void {
		$this->assertSame( 50, $this->engine->get_submission_batch_size() );
	}

	// ------------------------------------------------------------------
	// Edge cases
	// ------------------------------------------------------------------

	public function test_categories_ordered_orphaned_parent_treated_as_root(): void {
		$this->categories->shouldReceive( 'list_all' )
			->once()
			->andReturn( [
				[ 'id' => 10, 'name' => 'Orphan', 'slug' => 'orphan', 'parent_id' => 999 ],
				[ 'id' => 1,  'name' => 'Root',   'slug' => 'root',   'parent_id' => 0 ],
			] );

		$result = $this->engine->get_categories_ordered();
		$ids = array_column( $result, 'id' );

		$this->assertCount( 2, $result );
		$this->assertContains( 10, $ids );
		$this->assertContains( 1, $ids );
	}

	public function test_migrate_sample_batch_empty_returns_zeroes(): void {
		$result = $this->engine->migrate_sample_batch( [] );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 0, $result['updated'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertEmpty( $result['errors'] );
		$this->assertFalse( $result['plan_limit_reached'] );
	}

	// ------------------------------------------------------------------
	// Submission migration
	// ------------------------------------------------------------------

	public function test_migrate_submission_batch_accepts_and_marks_synced(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		$this->forms->shouldReceive( 'get' )->with( 1 )->andReturn( [ 'id' => 1, 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->andReturn( [ 'email' => 'a@example.com' ] );
		$this->mapper->shouldReceive( 'map' )->andReturn( [ 'email' => 'a@example.com', 'plugin_submission_id' => 'sub-1' ] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )->once()->andReturn( [
			'response' => [ 'code' => 200 ],
			'body'     => json_encode( [ 'results' => [
				[ 'index' => 0, 'status' => 'accepted', 'platform_id' => 555 ],
			] ] ),
		] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( json_encode( [ 'results' => [
				[ 'index' => 0, 'status' => 'accepted', 'platform_id' => 555 ],
			] ] ) );

		$this->submissions->shouldReceive( 'mark_synced' )->once()->with( 10, 555 );

		$result = $this->engine->migrate_submission_batch( [
			[ 'id' => 10, 'form_id' => 1, 'email' => 'a@example.com', 'synced_to_shq' => 0 ],
		] );

		$this->assertSame( 1, $result['accepted'] );
		$this->assertSame( 0, $result['duplicates'] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_migrate_submission_batch_handles_duplicates(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		$this->forms->shouldReceive( 'get' )->andReturn( [ 'id' => 1, 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->andReturn( [ 'email' => 'b@example.com' ] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )->once()->andReturn( [
			'response' => [ 'code' => 200 ],
			'body'     => json_encode( [ 'results' => [
				[ 'index' => 0, 'status' => 'duplicate' ],
			] ] ),
		] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( json_encode( [ 'results' => [ [ 'index' => 0, 'status' => 'duplicate' ] ] ] ) );

		$this->submissions->shouldReceive( 'mark_synced' )->once()->with( 20, 0 );

		$result = $this->engine->migrate_submission_batch( [
			[ 'id' => 20, 'form_id' => 1, 'synced_to_shq' => 0 ],
		] );

		$this->assertSame( 0, $result['accepted'] );
		$this->assertSame( 1, $result['duplicates'] );
	}

	public function test_migrate_submission_batch_form_not_found(): void {
		$this->forms->shouldReceive( 'get' )->with( 99 )->andReturn( null );

		$result = $this->engine->migrate_submission_batch( [
			[ 'id' => 30, 'form_id' => 99, 'synced_to_shq' => 0 ],
		] );

		$this->assertSame( 0, $result['accepted'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 'Form not found', $result['errors'][0]['error'] );
	}

	public function test_migrate_submission_batch_empty_returns_zeroes(): void {
		$result = $this->engine->migrate_submission_batch( [] );

		$this->assertSame( 0, $result['accepted'] );
		$this->assertSame( 0, $result['duplicates'] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_migrate_submission_batch_network_error(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		$this->forms->shouldReceive( 'get' )->andReturn( [ 'id' => 1, 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->andReturn( [ 'email' => 'c@example.com' ] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );

		$wp_error = \Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )->andReturn( 'Timeout' );

		Monkey\Functions\expect( 'wp_remote_request' )->once()->andReturn( $wp_error );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( true );

		$result = $this->engine->migrate_submission_batch( [
			[ 'id' => 40, 'form_id' => 1, 'synced_to_shq' => 0 ],
		] );

		$this->assertSame( 0, $result['accepted'] );
		$this->assertCount( 1, $result['errors'] );
	}

	// ------------------------------------------------------------------
	// Progress tracking
	// ------------------------------------------------------------------

	public function test_start_migration_initializes_progress(): void {
		$this->categories->shouldReceive( 'list_all' )->andReturn( [
			[ 'id' => 1, 'name' => 'Cat', 'slug' => 'cat', 'parent_id' => 0 ],
		] );
		$this->samples->shouldReceive( 'list_all' )->andReturn( [
			[ 'id' => 1, 'name' => 'S1', 'status' => 'active' ],
		] );
		$this->submissions->shouldReceive( 'list_all' )->andReturn( [] );

		Monkey\Functions\expect( 'update_option' )->once()->with(
			'shqf_migration_progress',
			\Mockery::on( function ( $data ) {
				return 'categories' === $data['phase']
					&& 1 === $data['categories_total']
					&& 1 === $data['samples_total']
					&& 0 === $data['submissions_total']
					&& false === $data['include_submissions'];
			} ),
			false
		);
		Monkey\Functions\expect( 'wp_next_scheduled' )->andReturn( false );
		Monkey\Functions\expect( 'wp_schedule_single_event' )->once();

		$progress = $this->engine->start_migration( false );

		$this->assertSame( 'categories', $progress['phase'] );
		$this->assertSame( 1, $progress['categories_total'] );
		$this->assertSame( 1, $progress['samples_total'] );
		$this->assertSame( 0, $progress['submissions_total'] );
	}

	public function test_start_migration_with_submissions(): void {
		$this->categories->shouldReceive( 'list_all' )->andReturn( [] );
		$this->samples->shouldReceive( 'list_all' )->andReturn( [] );
		$this->submissions->shouldReceive( 'list_all' )->andReturn( [
			[ 'id' => 1, 'synced_to_shq' => 0 ],
			[ 'id' => 2, 'synced_to_shq' => 0 ],
		] );

		Monkey\Functions\expect( 'update_option' )->once();
		Monkey\Functions\expect( 'wp_next_scheduled' )->andReturn( false );
		Monkey\Functions\expect( 'wp_schedule_single_event' )->once();

		$progress = $this->engine->start_migration( true );

		$this->assertTrue( $progress['include_submissions'] );
		$this->assertSame( 2, $progress['submissions_total'] );
	}

	public function test_get_progress_returns_stored_state(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_progress', null )
			->andReturn( [ 'phase' => 'samples', 'samples_completed' => 5 ] );

		$progress = $this->engine->get_progress();

		$this->assertSame( 'samples', $progress['phase'] );
		$this->assertSame( 5, $progress['samples_completed'] );
	}

	public function test_get_progress_returns_null_when_no_migration(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_progress', null )
			->andReturn( null );

		$this->assertNull( $this->engine->get_progress() );
	}

	public function test_cancel_migration_sets_cancelled_phase(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_progress', null )
			->andReturn( [ 'phase' => 'samples' ] );

		Monkey\Functions\expect( 'update_option' )
			->once()
			->with(
				'shqf_migration_progress',
				\Mockery::on( function ( $data ) {
					return 'cancelled' === $data['phase'];
				} ),
				false
			);

		Monkey\Functions\expect( 'wp_next_scheduled' )->with( 'shqf_run_migration' )->andReturn( 12345 );
		Monkey\Functions\expect( 'wp_unschedule_event' )->once()->with( 12345, 'shqf_run_migration' );

		$this->engine->cancel_migration();
	}

	public function test_process_next_batch_skips_completed(): void {
		Monkey\Functions\expect( 'add_option' )->with( 'shqf_migration_lock', \Mockery::type( 'int' ), '', 'no' )->andReturn( true );
		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_migration_lock' )->once();
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_progress', null )
			->andReturn( [ 'phase' => 'complete' ] );

		Monkey\Functions\expect( 'update_option' )->never();

		$this->engine->process_next_batch();
		$this->assertTrue( true );
	}

	public function test_process_next_batch_skips_cancelled(): void {
		Monkey\Functions\expect( 'add_option' )->with( 'shqf_migration_lock', \Mockery::type( 'int' ), '', 'no' )->andReturn( true );
		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_migration_lock' )->once();
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_progress', null )
			->andReturn( [ 'phase' => 'cancelled' ] );

		Monkey\Functions\expect( 'update_option' )->never();

		$this->engine->process_next_batch();
		$this->assertTrue( true );
	}

	public function test_process_next_batch_skips_null_progress(): void {
		Monkey\Functions\expect( 'add_option' )->with( 'shqf_migration_lock', \Mockery::type( 'int' ), '', 'no' )->andReturn( true );
		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_migration_lock' )->once();
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_progress', null )
			->andReturn( null );

		Monkey\Functions\expect( 'update_option' )->never();

		$this->engine->process_next_batch();
		$this->assertTrue( true );
	}

	public function test_process_next_batch_skips_when_locked(): void {
		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migration_lock', \Mockery::type( 'int' ), '', 'no' )
			->andReturn( false );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_lock', 0 )
			->andReturn( time() );

		$this->engine->process_next_batch();
		$this->assertTrue( true );
	}

	// ------------------------------------------------------------------
	// acquire_lock -- atomicity and stale lock recovery
	// ------------------------------------------------------------------

	public function test_stale_lock_is_reclaimed(): void {
		$stale_time = time() - 200;

		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migration_lock', \Mockery::type( 'int' ), '', 'no' )
			->twice()
			->andReturnValues( [ false, true ] );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_lock', 0 )
			->once()
			->andReturn( $stale_time );

		Monkey\Functions\expect( 'delete_option' )
			->with( 'shqf_migration_lock' )
			->twice();

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_progress', null )
			->andReturn( [ 'phase' => 'complete' ] );

		$this->engine->process_next_batch();
		$this->assertTrue( true );
	}

	public function test_non_stale_lock_not_reclaimed(): void {
		$recent_time = time() - 30;

		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migration_lock', \Mockery::type( 'int' ), '', 'no' )
			->once()
			->andReturn( false );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_lock', 0 )
			->once()
			->andReturn( $recent_time );

		Monkey\Functions\expect( 'delete_option' )->never();
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_progress', null )
			->never();

		$this->engine->process_next_batch();
		$this->assertTrue( true );
	}

	public function test_stale_reclaim_race_second_add_fails(): void {
		$stale_time = time() - 200;

		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migration_lock', \Mockery::type( 'int' ), '', 'no' )
			->twice()
			->andReturnValues( [ false, false ] );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_lock', 0 )
			->once()
			->andReturn( $stale_time );

		Monkey\Functions\expect( 'delete_option' )
			->with( 'shqf_migration_lock' )
			->once();

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_progress', null )
			->never();

		$this->engine->process_next_batch();
		$this->assertTrue( true );
	}

	// ------------------------------------------------------------------
	// 15C.4: Recovery scenarios
	// ------------------------------------------------------------------

	public function test_stale_lock_reclaimed_and_work_continues(): void {
		$stale_time = time() - 200;

		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migration_lock', \Mockery::type( 'int' ), '', 'no' )
			->twice()
			->andReturnValues( [ false, true ] );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_lock', 0 )
			->once()
			->andReturn( $stale_time );

		Monkey\Functions\expect( 'delete_option' )
			->with( 'shqf_migration_lock' )
			->twice();

		$this->samples->shouldReceive( 'list_all' )->andReturn( [] );

		$saved = null;
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_progress', null )
			->andReturn( [
				'phase'                  => 'samples',
				'include_submissions'    => false,
				'categories_total'       => 2,
				'categories_completed'   => 2,
				'categories_created'     => 2,
				'categories_updated'     => 0,
				'samples_total'          => 5,
				'samples_completed'      => 3,
				'samples_created'        => 3,
				'samples_updated'        => 0,
				'samples_skipped'        => 0,
				'submissions_total'      => 0,
				'submissions_completed'  => 0,
				'submissions_accepted'   => 0,
				'submissions_duplicates' => 0,
				'plan_limit_reached'     => false,
				'errors'                 => [],
			] );

		Monkey\Functions\expect( 'update_option' )
			->once()
			->with(
				'shqf_migration_progress',
				\Mockery::on( function ( $data ) use ( &$saved ) {
					$saved = $data;
					return true;
				} ),
				false
			);

		$this->engine->process_next_batch();

		$this->assertSame( 'complete', $saved['phase'] );
	}

	public function test_start_migration_from_error_restarts_at_categories(): void {
		$this->categories->shouldReceive( 'list_all' )->andReturn( [
			[ 'id' => 1, 'name' => 'Cat', 'slug' => 'cat', 'parent_id' => 0 ],
		] );
		$this->samples->shouldReceive( 'list_all' )->andReturn( [
			[ 'id' => 1, 'name' => 'S1', 'status' => 'active' ],
		] );
		$this->submissions->shouldReceive( 'list_all' )->andReturn( [] );

		Monkey\Functions\expect( 'update_option' )
			->once()
			->with(
				'shqf_migration_progress',
				\Mockery::on( fn( $data ) => 'categories' === $data['phase'] ),
				false
			);
		Monkey\Functions\expect( 'wp_next_scheduled' )->andReturn( false );
		Monkey\Functions\expect( 'wp_schedule_single_event' )->once();

		$progress = $this->engine->start_migration( false );

		$this->assertSame( 'categories', $progress['phase'] );
		$this->assertSame( 1, $progress['categories_total'] );
		$this->assertSame( 0, $progress['categories_completed'] );
	}

	// ------------------------------------------------------------------
	// 15C.3: Partial failure scenarios
	// ------------------------------------------------------------------

	public function test_parent_failure_child_still_receives_parent_slug(): void {
		$this->categories->shouldReceive( 'list_all' )->andReturn( [
			[ 'id' => 2, 'name' => 'Child', 'slug' => 'child', 'parent_id' => 1 ],
			[ 'id' => 1, 'name' => 'Parent', 'slug' => 'parent', 'parent_id' => 0 ],
		] );

		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		$call_index    = 0;
		$child_payload = null;

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )
			->twice()
			->andReturnUsing( function () use ( &$call_index, &$child_payload ) {
				$args = func_get_args();
				++$call_index;
				if ( 1 === $call_index ) {
					return [ 'response' => [ 'code' => 500 ], 'body' => '{"message":"Server error"}' ];
				}
				$child_payload = json_decode( $args[1]['body'], true );
				return [ 'response' => [ 'code' => 201 ], 'body' => json_encode( [ 'term_id' => 200, 'status' => 'created' ] ) ];
			} );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )
			->andReturnUsing( fn( $resp ) => (int) $resp['response']['code'] );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturnUsing( fn( $resp ) => $resp['body'] );

		$this->categories->shouldReceive( 'update' )->once()->with( 2, [ 'shq_category_id' => 200 ] );

		$result = $this->engine->migrate_all_categories();

		$this->assertSame( 1, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertSame( 'Parent', $result['errors'][0]['name'] );
		$this->assertNotNull( $child_payload );
		$this->assertSame( 'parent', $child_payload['parent_slug'] );
	}

	public function test_plan_limit_on_first_sample_breaks_immediately(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );
		$this->category_map->shouldReceive( 'get_categories_for_sample' )->andReturn( [] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )
			->once()
			->andReturn( [
				'response' => [ 'code' => 422 ],
				'body'     => json_encode( [ 'code' => 'plan_limit_reached', 'message' => 'Plan limit reached' ] ),
			] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 422 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( json_encode( [ 'code' => 'plan_limit_reached', 'message' => 'Plan limit reached' ] ) );

		$result = $this->engine->migrate_sample_batch( [
			[ 'id' => 1, 'name' => 'S1', 'sku' => 'S-001' ],
			[ 'id' => 2, 'name' => 'S2', 'sku' => 'S-002' ],
		] );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 0, $result['updated'] );
		$this->assertTrue( $result['plan_limit_reached'] );
		$this->assertEmpty( $result['errors'] );
	}

	public function test_submission_batch_fewer_results_than_items_sent(): void {
		$conn = $this->mock_connection();
		$this->connection->shouldReceive( 'get_connection' )->andReturn( $conn );
		$this->verifier->shouldReceive( 'sign_request' )->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		$this->forms->shouldReceive( 'get' )->andReturn( [ 'id' => 1, 'config' => [] ] );
		$this->submission_meta->shouldReceive( 'get_all' )->andReturn( [] );
		$this->mapper->shouldReceive( 'map' )->andReturn( [ 'email' => 'test@example.com' ] );

		$response_body = json_encode( [ 'results' => [
			[ 'index' => 0, 'status' => 'accepted', 'platform_id' => 100 ],
		] ] );

		Monkey\Functions\expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		Monkey\Functions\expect( 'wp_remote_request' )->once()->andReturn( [
			'response' => [ 'code' => 200 ],
			'body'     => $response_body,
		] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->andReturn( $response_body );

		$this->submissions->shouldReceive( 'mark_synced' )->once()->with( 10, 100 );

		$result = $this->engine->migrate_submission_batch( [
			[ 'id' => 10, 'form_id' => 1, 'synced_to_shq' => 0 ],
			[ 'id' => 20, 'form_id' => 1, 'synced_to_shq' => 0 ],
			[ 'id' => 30, 'form_id' => 1, 'synced_to_shq' => 0 ],
		] );

		$this->assertSame( 1, $result['accepted'] );
		$this->assertSame( 0, $result['duplicates'] );
		$this->assertEmpty( $result['errors'] );
	}

	// ------------------------------------------------------------------
	// 15C.5: walk_tree safety
	// ------------------------------------------------------------------

	public function test_circular_parent_reference_does_not_infinite_loop(): void {
		$this->categories->shouldReceive( 'list_all' )
			->once()
			->andReturn( [
				[ 'id' => 1, 'name' => 'A', 'slug' => 'a', 'parent_id' => 2 ],
				[ 'id' => 2, 'name' => 'B', 'slug' => 'b', 'parent_id' => 1 ],
				[ 'id' => 3, 'name' => 'Normal', 'slug' => 'normal', 'parent_id' => 0 ],
			] );

		$result = $this->engine->get_categories_ordered();
		$ids    = array_column( $result, 'id' );

		$this->assertSame( [ 3 ], $ids );
	}

	public function test_deep_hierarchy_twenty_levels_all_emitted_in_order(): void {
		$categories = [];
		for ( $i = 1; $i <= 20; $i++ ) {
			$categories[] = [
				'id'        => $i,
				'name'      => "Level {$i}",
				'slug'      => "level-{$i}",
				'parent_id' => 1 === $i ? 0 : $i - 1,
			];
		}
		shuffle( $categories );

		$this->categories->shouldReceive( 'list_all' )
			->once()
			->andReturn( $categories );

		$result = $this->engine->get_categories_ordered();
		$ids    = array_column( $result, 'id' );

		$this->assertCount( 20, $result );
		$this->assertSame( range( 1, 20 ), $ids );
	}

	public function test_empty_library_categories_phase_advances_to_samples(): void {
		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migration_lock', \Mockery::type( 'int' ), '', 'no' )
			->andReturn( true );
		Monkey\Functions\expect( 'delete_option' )
			->with( 'shqf_migration_lock' )
			->once();

		$this->categories->shouldReceive( 'list_all' )->andReturn( [] );

		$saved = null;
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_migration_progress', null )
			->andReturn( [
				'phase'                  => 'categories',
				'include_submissions'    => false,
				'categories_total'       => 0,
				'categories_completed'   => 0,
				'categories_created'     => 0,
				'categories_updated'     => 0,
				'samples_total'          => 0,
				'samples_completed'      => 0,
				'samples_created'        => 0,
				'samples_updated'        => 0,
				'samples_skipped'        => 0,
				'submissions_total'      => 0,
				'submissions_completed'  => 0,
				'submissions_accepted'   => 0,
				'submissions_duplicates' => 0,
				'plan_limit_reached'     => false,
				'errors'                 => [],
			] );

		Monkey\Functions\expect( 'update_option' )
			->once()
			->with(
				'shqf_migration_progress',
				\Mockery::on( function ( $data ) use ( &$saved ) {
					$saved = $data;
					return true;
				} ),
				false
			);

		Monkey\Functions\expect( 'wp_next_scheduled' )
			->with( 'shqf_run_migration' )
			->andReturn( false );
		Monkey\Functions\expect( 'wp_schedule_single_event' )->once();

		$this->engine->process_next_batch();

		$this->assertSame( 'samples', $saved['phase'] );
		$this->assertSame( 0, $saved['categories_completed'] );
		$this->assertEmpty( $saved['errors'] );
	}
}
