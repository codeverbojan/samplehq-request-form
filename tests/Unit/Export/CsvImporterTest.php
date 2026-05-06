<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for the CsvImporter.
 *
 * @package SampleHQForm\Tests\Unit\Export
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Export;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Export\CsvImporter;

/**
 * CsvImporter unit tests.
 */
class CsvImporterTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $samples;
	private $categories;
	private $category_map;
	private CsvImporter $importer;
	private string $tmp_dir;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->samples      = Mockery::mock( SamplesTable::class );
		$this->categories   = Mockery::mock( SampleCategoriesTable::class );
		$this->category_map = Mockery::mock( SampleCategoryMapTable::class );

		Monkey\Functions\stubs( [
			'sanitize_title'     => static fn( $s ) => strtolower( str_replace( ' ', '-', trim( strip_tags( (string) $s ) ) ) ),
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'__'                 => static fn( $s ) => $s,
		] );

		$this->importer = new CsvImporter( $this->samples, $this->categories, $this->category_map );
		$this->tmp_dir  = sys_get_temp_dir();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Write a temporary CSV file.
	 *
	 * @param string $content CSV content.
	 * @return string File path.
	 */
	private function write_csv( string $content ): string {
		$path = tempnam( $this->tmp_dir, 'shqf_test_' );
		file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $path;
	}

	/**
	 * Import valid CSV creates samples.
	 */
	public function test_import_valid_csv(): void {
		$csv  = "name,sku,description,category,max_quantity\n";
		$csv .= "Kraft Mailer,KM-001,A kraft mailer,Mailers,5\n";
		$csv .= "Poly Bag,PB-001,A poly bag,Bags,3\n";
		$path = $this->write_csv( $csv );

		$this->samples->shouldReceive( 'create' )->twice()->andReturn( 1, 2 );
		$this->categories->shouldReceive( 'get_by_slug' )->with( 'mailers' )->andReturn( null );
		$this->categories->shouldReceive( 'create' )->with( Mockery::on(
			static fn( $d ) => 'Mailers' === $d['name']
		) )->andReturn( 10 );
		$this->categories->shouldReceive( 'get_by_slug' )->with( 'bags' )->andReturn( [ 'id' => '20' ] );
		$this->category_map->shouldReceive( 'add' )->twice();

		$result = $this->importer->import( $path );

		$this->assertSame( 2, $result['created'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertEmpty( $result['errors'] );

		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Import skips rows with missing name.
	 */
	public function test_import_skips_missing_name(): void {
		$csv  = "name,sku\n";
		$csv .= ",SKU-001\n";
		$csv .= "Valid Sample,SKU-002\n";
		$path = $this->write_csv( $csv );

		$this->samples->shouldReceive( 'create' )->once()->andReturn( 1 );

		$result = $this->importer->import( $path );

		$this->assertSame( 1, $result['created'] );
		$this->assertSame( 1, $result['skipped'] );
		$this->assertCount( 1, $result['errors'] );

		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Import fails on nonexistent file.
	 */
	public function test_import_nonexistent_file(): void {
		$result = $this->importer->import( '/nonexistent/path.csv' );

		$this->assertSame( 0, $result['created'] );
		$this->assertCount( 1, $result['errors'] );
	}

	/**
	 * Import fails on CSV without name column.
	 */
	public function test_import_no_name_column(): void {
		$csv  = "sku,description\n";
		$csv .= "SKU-001,A product\n";
		$path = $this->write_csv( $csv );

		$result = $this->importer->import( $path );

		$this->assertSame( 0, $result['created'] );
		$this->assertStringContainsString( 'name', $result['errors'][0] );

		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Import handles database errors gracefully.
	 */
	public function test_import_handles_db_error(): void {
		$csv  = "name\n";
		$csv .= "Sample 1\n";
		$path = $this->write_csv( $csv );

		$this->samples->shouldReceive( 'create' )
			->once()
			->andThrow( new \RuntimeException( 'DB error' ) );

		$result = $this->importer->import( $path );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 1, $result['skipped'] );
		$this->assertStringContainsString( 'DB error', $result['errors'][0] );

		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Import with no data rows.
	 */
	public function test_import_empty_data(): void {
		$path = $this->write_csv( "name,sku\n" );

		$result = $this->importer->import( $path );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 0, $result['skipped'] );
		$this->assertEmpty( $result['errors'] );

		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Import creates categories on the fly.
	 */
	public function test_creates_category_on_fly(): void {
		$csv  = "name,category\n";
		$csv .= "Test,New Category\n";
		$path = $this->write_csv( $csv );

		$this->samples->shouldReceive( 'create' )->once()->andReturn( 1 );
		$this->categories->shouldReceive( 'get_by_slug' )->with( 'new-category' )->andReturn( null );
		$this->categories->shouldReceive( 'create' )->once()->andReturn( 5 );
		$this->category_map->shouldReceive( 'add' )->with( 1, 5 )->once();

		$result = $this->importer->import( $path );

		$this->assertSame( 1, $result['created'] );

		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Import reuses existing category.
	 */
	public function test_reuses_existing_category(): void {
		$csv  = "name,category\n";
		$csv .= "Test,Existing\n";
		$path = $this->write_csv( $csv );

		$this->samples->shouldReceive( 'create' )->once()->andReturn( 1 );
		$this->categories->shouldReceive( 'get_by_slug' )->with( 'existing' )->andReturn( [ 'id' => '7' ] );
		$this->categories->shouldReceive( 'create' )->never();
		$this->category_map->shouldReceive( 'add' )->with( 1, 7 )->once();

		$result = $this->importer->import( $path );

		$this->assertSame( 1, $result['created'] );

		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
