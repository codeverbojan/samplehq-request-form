<?php
/**
 * Tests for the SampleCategoryMapTable repository.
 *
 * @package SampleHQForm\Tests\Unit\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Database;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\SampleCategoryMapTable;

/**
 * SampleCategoryMapTable unit tests.
 */
class SampleCategoryMapTableTest extends TestCase {

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
	 * @var SampleCategoryMapTable
	 */
	private SampleCategoryMapTable $table;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		Monkey\Functions\stubs( [
			'absint' => static fn( $n ) => abs( (int) $n ),
		] );

		$this->table = new SampleCategoryMapTable( $this->wpdb );
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Add should INSERT IGNORE.
	 */
	public function test_add(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'query' )->once()->with( 'sql' )->andReturn( 1 );

		$this->assertTrue( $this->table->add( 1, 2 ) );
	}

	/**
	 * Remove should delete the specific association.
	 */
	public function test_remove(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with(
				'wp_shqf_sample_category_map',
				[ 'sample_id' => 1, 'category_id' => 2 ],
				[ '%d', '%d' ]
			)
			->andReturn( 1 );

		$this->assertTrue( $this->table->remove( 1, 2 ) );
	}

	/**
	 * Remove all for sample should delete by sample_id.
	 */
	public function test_remove_all_for_sample(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_shqf_sample_category_map', [ 'sample_id' => 5 ], [ '%d' ] )
			->andReturn( 3 );

		$this->assertTrue( $this->table->remove_all_for_sample( 5 ) );
	}

	/**
	 * Get categories for sample should return integer array.
	 */
	public function test_get_categories_for_sample(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_col' )->once()->with( 'sql' )->andReturn( [ '1', '3', '7' ] );

		$result = $this->table->get_categories_for_sample( 42 );
		$this->assertSame( [ 1, 3, 7 ], $result );
	}

	/**
	 * Get samples for category should return integer array.
	 */
	public function test_get_samples_for_category(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_col' )->once()->with( 'sql' )->andReturn( [ '10', '20' ] );

		$result = $this->table->get_samples_for_category( 5 );
		$this->assertSame( [ 10, 20 ], $result );
	}

	/**
	 * Sync should remove all then re-add.
	 */
	public function test_sync(): void {
		// First: remove all existing.
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_shqf_sample_category_map', [ 'sample_id' => 1 ], [ '%d' ] )
			->andReturn( 2 );

		// Then: add each new association.
		$this->wpdb->shouldReceive( 'prepare' )->times( 3 )->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'query' )->times( 3 )->andReturn( 1 );

		$this->table->sync( 1, [ 2, 5, 8 ] );

		// No assertion needed -- Mockery verifies the calls.
	}

	/**
	 * Sync with empty array should just remove all.
	 */
	public function test_sync_with_empty_array(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->andReturn( 0 );

		$this->table->sync( 1, [] );
	}

	/**
	 * Batch get_categories_for_samples returns map keyed by sample ID.
	 */
	public function test_get_categories_for_samples(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'batch-sql' );
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'batch-sql', ARRAY_A )
			->andReturn( [
				[ 'sample_id' => '1', 'category_id' => '10' ],
				[ 'sample_id' => '1', 'category_id' => '20' ],
				[ 'sample_id' => '3', 'category_id' => '10' ],
			] );

		$result = $this->table->get_categories_for_samples( [ 1, 2, 3 ] );

		$this->assertSame( [ 10, 20 ], $result[1] );
		$this->assertSame( [], $result[2] );
		$this->assertSame( [ 10 ], $result[3] );
	}

	/**
	 * Batch get with empty input returns empty array.
	 */
	public function test_get_categories_for_samples_empty_input(): void {
		$result = $this->table->get_categories_for_samples( [] );
		$this->assertSame( [], $result );
	}

	/**
	 * Batch get filters out zero/negative IDs.
	 */
	public function test_get_categories_for_samples_filters_invalid_ids(): void {
		$result = $this->table->get_categories_for_samples( [ 0, -1 ] );
		$this->assertSame( [], $result );
	}
}
