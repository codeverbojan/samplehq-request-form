<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for the SampleCategoriesTable repository.
 *
 * @package SampleHQForm\Tests\Unit\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Database;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\SampleCategoriesTable;

/**
 * SampleCategoriesTable unit tests.
 */
class SampleCategoriesTableTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Mock wpdb instance.
	 *
	 * @var \wpdb&Mockery\MockInterface
	 */
	private $wpdb;

	/**
	 * Table instance under test.
	 *
	 * @var SampleCategoriesTable
	 */
	private SampleCategoriesTable $table;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		Monkey\Functions\stubs( [
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'sanitize_title'     => static fn( $s ) => strtolower( str_replace( ' ', '-', trim( strip_tags( (string) $s ) ) ) ),
			'wp_kses_post'       => static fn( $s ) => (string) $s,
			'absint'             => static fn( $n ) => abs( (int) $n ),
			'current_time'       => static fn() => '2026-04-23 10:00:00',
		] );

		$this->table = new SampleCategoriesTable( $this->wpdb );
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create should insert and return ID.
	 */
	public function test_create_returns_id(): void {
		$this->wpdb->insert_id = 5;

		// unique_slug check.
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'slug-check' );
		$this->wpdb->shouldReceive( 'get_var' )->once()->with( 'slug-check' )->andReturn( null );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_sample_categories',
				Mockery::on(
					static function ( array $row ): bool {
						return 'Mailers' === $row['name']
							&& 'mailers' === $row['slug'];
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$id = $this->table->create( [ 'name' => 'Mailers' ] );
		$this->assertSame( 5, $id );
	}

	/**
	 * Create should throw without name.
	 */
	public function test_create_throws_without_name(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->table->create( [] );
	}

	/**
	 * Create should use custom slug if provided.
	 */
	public function test_create_uses_custom_slug(): void {
		$this->wpdb->insert_id = 1;

		// unique_slug check.
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'slug-check' );
		$this->wpdb->shouldReceive( 'get_var' )->once()->with( 'slug-check' )->andReturn( null );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_sample_categories',
				Mockery::on(
					static function ( array $row ): bool {
						return 'custom-slug' === $row['slug'];
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$this->table->create( [ 'name' => 'Test', 'slug' => 'custom-slug' ] );
	}

	/**
	 * Get by slug should return row.
	 */
	public function test_get_by_slug(): void {
		$expected = [ 'id' => '1', 'name' => 'Mailers', 'slug' => 'mailers' ];

		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->with( 'sql', ARRAY_A )->andReturn( $expected );

		$result = $this->table->get_by_slug( 'mailers' );
		$this->assertSame( $expected, $result );
	}

	/**
	 * List all should return categories ordered by sort_order.
	 */
	public function test_list_all(): void {
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( [ [ 'id' => '1' ], [ 'id' => '2' ] ] );

		$results = $this->table->list_all();
		$this->assertCount( 2, $results );
	}

	/**
	 * List with parent filter should use prepare.
	 */
	public function test_list_all_with_parent(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'sql', ARRAY_A )
			->andReturn( [] );

		$results = $this->table->list_all( 0 );
		$this->assertSame( [], $results );
	}

	/**
	 * Delete should remove row.
	 */
	public function test_delete(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_shqf_sample_categories', [ 'id' => 3 ], [ '%d' ] )
			->andReturn( 1 );

		$this->assertTrue( $this->table->delete( 3 ) );
	}
}
