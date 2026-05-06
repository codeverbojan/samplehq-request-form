<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for SampleListTable.
 *
 * @package SampleHQForm\Tests\Unit\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Admin;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Admin\SampleListTable;
use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SampleImagesTable;
use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Database\SubmissionMetaTable;

/**
 * SampleListTable unit tests.
 */
class SampleListTableTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $samples;
	private $category_map;
	private $images;
	private $categories;
	private $submission_meta;
	private SampleListTable $table;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->samples         = Mockery::mock( SamplesTable::class );
		$this->category_map    = Mockery::mock( SampleCategoryMapTable::class );
		$this->images          = Mockery::mock( SampleImagesTable::class );
		$this->categories      = Mockery::mock( SampleCategoriesTable::class );
		$this->submission_meta = Mockery::mock( SubmissionMetaTable::class );

		Monkey\Functions\stubs( [
			'__'                         => static fn( $s ) => $s,
			'esc_html__'                 => static fn( $s ) => $s,
			'esc_attr'                   => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ),
			'esc_html'                   => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ),
			'esc_url'                    => static fn( $s ) => (string) $s,
			'admin_url'                  => static fn( $s ) => 'https://example.com/wp-admin/' . $s,
			'wp_nonce_url'               => static fn( $url ) => $url . '&_wpnonce=abc',
			'wp_get_attachment_image_url' => static fn() => 'https://example.com/image.jpg',
			'wp_date'                    => static fn( $f, $t ) => gmdate( $f, $t ),
			'get_option'                 => static fn( $k, $d = false ) => $d,
			'number_format_i18n'         => static fn( $n ) => number_format( $n ),
			'add_query_arg'              => static fn( $k, $v, $url ) => $url . '&' . $k . '=' . $v,
			'sanitize_text_field'        => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'wp_unslash'                 => static fn( $s ) => $s,
			'absint'                     => static fn( $n ) => abs( (int) $n ),
		] );

		$this->table = new SampleListTable( $this->samples, $this->category_map, $this->images, $this->categories, $this->submission_meta );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Get columns returns expected column keys.
	 */
	public function test_get_columns(): void {
		$columns = $this->table->get_columns();

		$this->assertArrayHasKey( 'cb', $columns );
		$this->assertArrayHasKey( 'name', $columns );
		$this->assertArrayHasKey( 'sku', $columns );
		$this->assertArrayHasKey( 'categories', $columns );
		$this->assertArrayHasKey( 'requests', $columns );
		$this->assertArrayHasKey( 'status', $columns );
		$this->assertArrayHasKey( 'created_at', $columns );
		$this->assertArrayHasKey( 'image', $columns );
	}

	/**
	 * Bulk actions include archive and delete.
	 */
	public function test_bulk_actions(): void {
		$actions = $this->table->get_bulk_actions();

		$this->assertArrayHasKey( 'archive', $actions );
		$this->assertArrayHasKey( 'delete', $actions );
	}

	/**
	 * Column output escapes HTML in name.
	 */
	public function test_column_name_escapes_html(): void {
		$item = [ 'id' => '1', 'name' => '<script>alert(1)</script>' ];

		// Use reflection to call protected method.
		$method = new \ReflectionMethod( $this->table, 'column_name' );
		$method->setAccessible( true );

		// row_actions needs to be stubbed.
		Monkey\Functions\stubs( [ 'wp_nonce_url' => static fn( $url ) => $url ] );

		$output = $method->invoke( $this->table, $item );

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
	}

	/**
	 * Status column renders correct label.
	 */
	public function test_column_status(): void {
		$method = new \ReflectionMethod( $this->table, 'column_status' );
		$method->setAccessible( true );

		$active   = $method->invoke( $this->table, [ 'status' => 'active' ] );
		$archived = $method->invoke( $this->table, [ 'status' => 'archived' ] );

		$this->assertStringContainsString( 'Active', $active );
		$this->assertStringContainsString( 'shqf-status--active', $active );
		$this->assertStringContainsString( 'Archived', $archived );
		$this->assertStringContainsString( 'shqf-status--archived', $archived );
	}

	/**
	 * Image column shows placeholder when no image.
	 */
	public function test_column_image_no_image(): void {
		$this->images->shouldReceive( 'get_featured' )->with( 1 )->andReturn( null );

		$method = new \ReflectionMethod( $this->table, 'column_image' );
		$method->setAccessible( true );

		$output = $method->invoke( $this->table, [ 'id' => '1' ] );

		$this->assertStringContainsString( '--', $output );
	}

	/**
	 * Image column shows image when available.
	 */
	public function test_column_image_with_image(): void {
		$this->images->shouldReceive( 'get_featured' )->with( 1 )->andReturn( 42 );

		$method = new \ReflectionMethod( $this->table, 'column_image' );
		$method->setAccessible( true );

		$output = $method->invoke( $this->table, [ 'id' => '1', 'name' => 'Test' ] );

		$this->assertStringContainsString( '<img', $output );
		$this->assertStringContainsString( 'alt="Test"', $output );
	}

	/**
	 * Categories column shows IDs.
	 */
	public function test_column_categories(): void {
		$this->category_map->shouldReceive( 'get_categories_for_sample' )->with( 5 )->andReturn( [ 1, 3 ] );

		$method = new \ReflectionMethod( $this->table, 'column_categories' );
		$method->setAccessible( true );

		$output = $method->invoke( $this->table, [ 'id' => '5' ] );

		$this->assertStringContainsString( '1, 3', $output );
	}

	/**
	 * Categories column shows dash when empty.
	 */
	public function test_column_categories_empty(): void {
		$this->category_map->shouldReceive( 'get_categories_for_sample' )->andReturn( [] );

		$method = new \ReflectionMethod( $this->table, 'column_categories' );
		$method->setAccessible( true );

		$output = $method->invoke( $this->table, [ 'id' => '5' ] );

		$this->assertStringContainsString( '--', $output );
	}
}
