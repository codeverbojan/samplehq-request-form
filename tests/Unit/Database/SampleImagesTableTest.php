<?php
/**
 * Tests for the SampleImagesTable repository.
 *
 * @package SampleHQForm\Tests\Unit\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Database;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\SampleImagesTable;

/**
 * SampleImagesTable unit tests.
 */
class SampleImagesTableTest extends TestCase {

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
	 * @var SampleImagesTable
	 */
	private SampleImagesTable $table;

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

		$this->table = new SampleImagesTable( $this->wpdb );
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Add should insert and return ID.
	 */
	public function test_add(): void {
		$this->wpdb->insert_id = 10;

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_sample_images',
				[
					'sample_id'     => 1,
					'attachment_id' => 42,
					'sort_order'    => 0,
				],
				[ '%d', '%d', '%d' ]
			)
			->andReturn( 1 );

		$id = $this->table->add( 1, 42 );
		$this->assertSame( 10, $id );
	}

	/**
	 * Add with custom sort order.
	 */
	public function test_add_with_sort_order(): void {
		$this->wpdb->insert_id = 11;

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_sample_images',
				[
					'sample_id'     => 1,
					'attachment_id' => 43,
					'sort_order'    => 3,
				],
				[ '%d', '%d', '%d' ]
			)
			->andReturn( 1 );

		$this->assertSame( 11, $this->table->add( 1, 43, 3 ) );
	}

	/**
	 * Remove should delete by ID.
	 */
	public function test_remove(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_shqf_sample_images', [ 'id' => 5 ], [ '%d' ] )
			->andReturn( 1 );

		$this->assertTrue( $this->table->remove( 5 ) );
	}

	/**
	 * Remove all for sample should delete by sample_id.
	 */
	public function test_remove_all_for_sample(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_shqf_sample_images', [ 'sample_id' => 3 ], [ '%d' ] )
			->andReturn( 2 );

		$this->assertTrue( $this->table->remove_all_for_sample( 3 ) );
	}

	/**
	 * Get for sample should return ordered images.
	 */
	public function test_get_for_sample(): void {
		$expected = [
			[ 'id' => '1', 'attachment_id' => '42', 'sort_order' => '0' ],
			[ 'id' => '2', 'attachment_id' => '43', 'sort_order' => '1' ],
		];

		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->with( 'sql', ARRAY_A )->andReturn( $expected );

		$result = $this->table->get_for_sample( 1 );
		$this->assertCount( 2, $result );
	}

	/**
	 * Get for sample with no images returns empty array.
	 */
	public function test_get_for_sample_empty(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( null );

		$this->assertSame( [], $this->table->get_for_sample( 99 ) );
	}

	/**
	 * Get featured should return first image's attachment ID.
	 */
	public function test_get_featured(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_var' )->once()->with( 'sql' )->andReturn( '42' );

		$this->assertSame( 42, $this->table->get_featured( 1 ) );
	}

	/**
	 * Get featured with no images returns null.
	 */
	public function test_get_featured_no_images(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_var' )->once()->andReturn( null );

		$this->assertNull( $this->table->get_featured( 99 ) );
	}

	/**
	 * Reorder should update sort_order for each image.
	 */
	public function test_reorder(): void {
		$this->wpdb->shouldReceive( 'update' )
			->times( 3 )
			->andReturn( 1 );

		$this->assertTrue( $this->table->reorder( 1, [ 5, 3, 8 ] ) );
	}

	/**
	 * Batch get_featured_for_samples returns map keyed by sample ID.
	 */
	public function test_get_featured_for_samples(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'batch-sql' );
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'batch-sql', ARRAY_A )
			->andReturn( [
				[ 'sample_id' => '1', 'attachment_id' => '100' ],
				[ 'sample_id' => '3', 'attachment_id' => '200' ],
			] );

		$result = $this->table->get_featured_for_samples( [ 1, 2, 3 ] );

		$this->assertSame( 100, $result[1] );
		$this->assertNull( $result[2] );
		$this->assertSame( 200, $result[3] );
	}

	/**
	 * Batch get_featured with empty input returns empty array.
	 */
	public function test_get_featured_for_samples_empty_input(): void {
		$result = $this->table->get_featured_for_samples( [] );
		$this->assertSame( [], $result );
	}

	/**
	 * Batch get_featured filters out zero/negative IDs.
	 */
	public function test_get_featured_for_samples_filters_invalid_ids(): void {
		$result = $this->table->get_featured_for_samples( [ 0, -1 ] );
		$this->assertSame( [], $result );
	}

	/**
	 * Batch get_featured picks lowest-id image on sort_order tie.
	 */
	public function test_get_featured_for_samples_tie_picks_lowest_id(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'batch-sql' );
		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->with( 'batch-sql', ARRAY_A )
			->andReturn( [
				// Two images for sample 1 both with sort_order=0 — lowest id wins.
				[ 'sample_id' => '1', 'attachment_id' => '50' ],
				[ 'sample_id' => '1', 'attachment_id' => '60' ],
			] );

		$result = $this->table->get_featured_for_samples( [ 1 ] );

		$this->assertSame( 50, $result[1] );
	}
}
