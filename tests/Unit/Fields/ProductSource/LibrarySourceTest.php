<?php
/**
 * Tests for LibrarySource.
 *
 * @package SampleHQForm\Tests\Unit\Fields\ProductSource
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Fields\ProductSource;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Database\SampleImagesTable;
use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Fields\ProductSource\LibrarySource;
use SampleHQForm\Fields\ProductSource\ProductSourceInterface;

class LibrarySourceTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $samples;
	private $images;
	private $categories;
	private $category_map;
	private LibrarySource $source;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->samples      = Mockery::mock( SamplesTable::class );
		$this->images       = Mockery::mock( SampleImagesTable::class );
		$this->categories   = Mockery::mock( SampleCategoriesTable::class );
		$this->category_map = Mockery::mock( SampleCategoryMapTable::class );

		Monkey\Functions\stubs( [
			'wp_get_attachment_image_url' => static fn() => 'https://example.com/image.jpg',
		] );

		$this->source = new LibrarySource(
			$this->samples,
			$this->images,
			$this->categories,
			$this->category_map
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_implements_interface(): void {
		$this->assertInstanceOf( ProductSourceInterface::class, $this->source );
	}

	public function test_get_samples_delegates_to_samples_table(): void {
		$rows = [
			[ 'id' => '1', 'name' => 'Kraft Mailer', 'status' => 'active' ],
			[ 'id' => '2', 'name' => 'Poly Bag', 'status' => 'active' ],
		];
		$this->samples->shouldReceive( 'list_all' )
			->once()
			->with( [ 'status' => 'active' ] )
			->andReturn( $rows );

		$result = $this->source->get_samples( [ 'status' => 'active' ] );

		$this->assertCount( 2, $result );
		$this->assertSame( 'Kraft Mailer', $result[0]['name'] );
	}

	public function test_get_sample_delegates_to_samples_table(): void {
		$row = [ 'id' => '5', 'name' => 'Bubble Wrap', 'status' => 'active' ];
		$this->samples->shouldReceive( 'get' )->once()->with( 5 )->andReturn( $row );

		$result = $this->source->get_sample( 5 );

		$this->assertSame( 'Bubble Wrap', $result['name'] );
	}

	public function test_get_sample_returns_null_when_not_found(): void {
		$this->samples->shouldReceive( 'get' )->once()->with( 999 )->andReturn( null );

		$this->assertNull( $this->source->get_sample( 999 ) );
	}

	public function test_get_categories_maps_to_standard_shape(): void {
		$this->categories->shouldReceive( 'list_all' )->once()->andReturn( [
			[ 'id' => '1', 'name' => 'Boxes', 'slug' => 'boxes', 'parent_id' => '0' ],
			[ 'id' => '2', 'name' => 'Bags', 'slug' => 'bags', 'parent_id' => '0' ],
		] );

		$result = $this->source->get_categories();

		$this->assertCount( 2, $result );
		$this->assertSame( 1, $result[0]['id'] );
		$this->assertSame( 'Boxes', $result[0]['name'] );
		$this->assertSame( 'boxes', $result[0]['slug'] );
	}

	public function test_get_image_url_returns_url_from_attachment(): void {
		$this->images->shouldReceive( 'get_featured' )->once()->with( 1 )->andReturn( 42 );

		$url = $this->source->get_image_url( 1, 'medium' );

		$this->assertSame( 'https://example.com/image.jpg', $url );
	}

	public function test_get_image_url_returns_null_when_no_image(): void {
		$this->images->shouldReceive( 'get_featured' )->once()->with( 1 )->andReturn( null );

		$this->assertNull( $this->source->get_image_url( 1 ) );
	}

	public function test_get_sample_category_names(): void {
		$this->category_map->shouldReceive( 'get_categories_for_sample' )
			->once()->with( 1 )->andReturn( [ 1, 3 ] );

		$this->categories->shouldReceive( 'list_all' )->once()->andReturn( [
			[ 'id' => '1', 'name' => 'Boxes', 'slug' => 'boxes' ],
			[ 'id' => '2', 'name' => 'Bags', 'slug' => 'bags' ],
			[ 'id' => '3', 'name' => 'Packaging', 'slug' => 'packaging' ],
		] );

		$names = $this->source->get_sample_category_names( 1 );

		$this->assertSame( [ 'Boxes', 'Packaging' ], $names );
	}
}
