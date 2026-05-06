<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for the SamplesTable repository.
 *
 * @package SampleHQForm\Tests\Unit\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Database;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\SamplesTable;

/**
 * SamplesTable unit tests.
 */
class SamplesTableTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Mock wpdb instance.
	 *
	 * @var \wpdb&Mockery\MockInterface
	 */
	private $wpdb;

	/**
	 * SamplesTable instance under test.
	 *
	 * @var SamplesTable
	 */
	private SamplesTable $table;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		// Stub common WP functions.
		Monkey\Functions\stubs( [
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'wp_kses_post'       => static fn( $s ) => (string) $s,
			'absint'             => static fn( $n ) => abs( (int) $n ),
			'current_time'       => static fn() => '2026-04-23 10:00:00',
		] );

		$this->table = new SamplesTable( $this->wpdb );
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create should insert a row and return the ID.
	 */
	public function test_create_returns_id(): void {
		$this->wpdb->insert_id = 42;

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_samples',
				Mockery::on(
					static function ( array $row ): bool {
						return $row['name'] === 'Test Sample'
							&& $row['sku'] === 'TS-001'
							&& $row['status'] === 'active'
							&& $row['max_quantity'] === 5
							&& $row['created_at'] === '2026-04-23 10:00:00';
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$id = $this->table->create( [
			'name'         => 'Test Sample',
			'sku'          => 'TS-001',
			'max_quantity' => 5,
		] );

		$this->assertSame( 42, $id );
	}

	/**
	 * Create should throw on database insert failure.
	 */
	public function test_create_throws_on_insert_failure(): void {
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to insert sample' );

		$this->table->create( [ 'name' => 'Will Fail' ] );
	}

	/**
	 * Create should throw when name is missing.
	 */
	public function test_create_throws_without_name(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Sample name is required.' );

		$this->table->create( [ 'sku' => 'NO-NAME' ] );
	}

	/**
	 * Create should handle null optional fields.
	 */
	public function test_create_with_minimal_data(): void {
		$this->wpdb->insert_id = 1;

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_samples',
				Mockery::on(
					static function ( array $row ): bool {
						return $row['name'] === 'Minimal'
							&& $row['sku'] === null
							&& $row['description'] === null
							&& $row['max_quantity'] === 0;
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$id = $this->table->create( [ 'name' => 'Minimal' ] );
		$this->assertSame( 1, $id );
	}

	/**
	 * Get should return sample data.
	 */
	public function test_get_returns_row(): void {
		$expected = [ 'id' => '1', 'name' => 'Test' ];

		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared_sql' );

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->with( 'prepared_sql', ARRAY_A )
			->andReturn( $expected );

		$result = $this->table->get( 1 );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Get should return null for non-existent ID.
	 */
	public function test_get_returns_null_for_missing(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared_sql' );

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( null );

		$this->assertNull( $this->table->get( 999 ) );
	}

	/**
	 * Update should modify the row.
	 */
	public function test_update_returns_true(): void {
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_shqf_samples',
				Mockery::on(
					static function ( array $row ): bool {
						return $row['name'] === 'Updated'
							&& isset( $row['updated_at'] );
					}
				),
				[ 'id' => 1 ],
				Mockery::type( 'array' ),
				[ '%d' ]
			)
			->andReturn( 1 );

		$this->assertTrue( $this->table->update( 1, [ 'name' => 'Updated' ] ) );
	}

	/**
	 * Update with empty data should return false.
	 */
	public function test_update_with_empty_data(): void {
		$this->assertFalse( $this->table->update( 1, [] ) );
	}

	/**
	 * Update should ignore unknown fields.
	 */
	public function test_update_ignores_unknown_fields(): void {
		$this->assertFalse( $this->table->update( 1, [ 'hacker_field' => 'drop tables' ] ) );
	}

	/**
	 * Archive should set status to archived.
	 */
	public function test_archive(): void {
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_shqf_samples',
				Mockery::on(
					static function ( array $row ): bool {
						return $row['status'] === 'archived';
					}
				),
				[ 'id' => 5 ],
				Mockery::type( 'array' ),
				[ '%d' ]
			)
			->andReturn( 1 );

		$this->assertTrue( $this->table->archive( 5 ) );
	}

	/**
	 * Delete should remove the row.
	 */
	public function test_delete(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_shqf_samples', [ 'id' => 3 ], [ '%d' ] )
			->andReturn( 1 );

		$this->assertTrue( $this->table->delete( 3 ) );
	}

	/**
	 * Invalid status should default to active.
	 */
	public function test_invalid_status_defaults_to_active(): void {
		$this->wpdb->insert_id = 1;

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_samples',
				Mockery::on(
					static function ( array $row ): bool {
						return $row['status'] === 'active';
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$this->table->create( [ 'name' => 'Test', 'status' => 'INVALID' ] );
	}

	/**
	 * List should use prepared statements with filters.
	 */
	public function test_list_with_status_filter(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared_sql' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'prepared_sql', ARRAY_A )
			->andReturn( [ [ 'id' => '1' ] ] );

		$results = $this->table->list_all( [ 'status' => 'active' ] );
		$this->assertCount( 1, $results );
	}

	/**
	 * List should return empty array when no results.
	 */
	public function test_list_returns_empty_array(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared_sql' );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( null );

		$results = $this->table->list_all();
		$this->assertSame( [], $results );
	}

	/**
	 * Count should return integer.
	 */
	public function test_count_returns_int(): void {
		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( '5' );

		$this->assertSame( 5, $this->table->count() );
	}

	/**
	 * Count with filter should use prepare.
	 */
	public function test_count_with_search_filter(): void {
		$this->wpdb->shouldReceive( 'esc_like' )
			->once()
			->with( 'kraft' )
			->andReturn( 'kraft' );

		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'prepared_sql' );

		$this->wpdb->shouldReceive( 'get_var' )
			->once()
			->with( 'prepared_sql' )
			->andReturn( '3' );

		$this->assertSame( 3, $this->table->count( [ 'search' => 'kraft' ] ) );
	}
}
