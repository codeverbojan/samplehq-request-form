<?php
/**
 * Tests for SubmissionsPage WooCommerce rendering.
 *
 * @package SampleHQForm\Tests\Unit\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Admin\SubmissionsPage;
use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Database\SampleImagesTable;

/**
 * SubmissionsPage WooCommerce-specific rendering tests.
 *
 * Tests the render_woo_sample_row and render_library_sample_row methods
 * via the render_submission_samples private method using reflection.
 */
class SubmissionsPageTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * @var SamplesTable&Mockery\MockInterface
	 */
	private $samples;

	/**
	 * @var SampleImagesTable&Mockery\MockInterface
	 */
	private $images;

	/**
	 * @var SubmissionsPage
	 */
	private SubmissionsPage $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->samples = Mockery::mock( SamplesTable::class );
		$this->images  = Mockery::mock( SampleImagesTable::class );

		$this->page = new SubmissionsPage();

		Functions\stubs( [
			'esc_html'               => static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ),
			'esc_html__'             => static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ),
			'esc_url'                => static fn( $v ) => (string) $v,
			'wp_kses_post'           => static fn( $v ) => (string) $v,
			'__'                     => static fn( $v ) => $v,
			'wp_get_attachment_image' => static fn() => '',
			'admin_url'              => static fn( $v ) => 'https://example.com/wp-admin/' . $v,
		] );
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Invoke the private render_submission_samples method.
	 *
	 * @param array<string, mixed> $meta Submission meta.
	 * @return string Rendered HTML.
	 */
	private function invoke_render_samples( array $meta ): string {
		$method = new \ReflectionMethod( SubmissionsPage::class, 'render_submission_samples' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $this->page, $meta, $this->samples, $this->images );
		return ob_get_clean();
	}

	/**
	 * Library sample row renders name and quantity from SamplesTable.
	 */
	public function test_library_sample_renders_name(): void {
		$meta = [
			'samples' => json_encode( [
				[ 'id' => 1, 'quantity' => 3 ],
			] ),
		];

		$this->samples->shouldReceive( 'get' )
			->with( 1 )
			->andReturn( [ 'id' => 1, 'name' => 'Kraft Mailer', 'status' => 'active' ] );

		$this->images->shouldReceive( 'get_featured' )
			->with( 1 )
			->andReturn( null );

		$html = $this->invoke_render_samples( $meta );

		$this->assertStringContainsString( 'Kraft Mailer', $html );
		$this->assertStringContainsString( '3', $html );
		$this->assertStringNotContainsString( 'WooCommerce', $html );
	}

	/**
	 * Library sample shows "Deleted Sample" when no longer exists.
	 */
	public function test_library_deleted_sample_fallback(): void {
		$meta = [
			'samples' => json_encode( [
				[ 'id' => 999, 'quantity' => 1 ],
			] ),
		];

		$this->samples->shouldReceive( 'get' )
			->with( 999 )
			->andReturn( null );

		$this->images->shouldReceive( 'get_featured' )
			->with( 999 )
			->andReturn( null );

		$html = $this->invoke_render_samples( $meta );

		$this->assertStringContainsString( 'Deleted Sample', $html );
	}

	/**
	 * WC product row renders stored name, SKU, and WooCommerce badge.
	 */
	public function test_woo_sample_renders_name_sku_badge(): void {
		$meta = [
			'samples' => json_encode( [
				[
					'id'       => 42,
					'quantity' => 2,
					'source'   => 'woocommerce',
					'name'     => 'Kraft Box 10x8x4',
					'sku'      => 'KMB-1084',
				],
			] ),
		];

		// WC product still exists.
		Functions\when( 'wc_get_product' )->justReturn( false );

		$html = $this->invoke_render_samples( $meta );

		$this->assertStringContainsString( 'Kraft Box 10x8x4', $html );
		$this->assertStringContainsString( 'KMB-1084', $html );
		$this->assertStringContainsString( 'WooCommerce', $html );
		$this->assertStringContainsString( 'shqf-badge--woo', $html );
	}

	/**
	 * WC product row shows "(deleted)" when product no longer exists.
	 */
	public function test_woo_deleted_product_shows_stored_name(): void {
		$meta = [
			'samples' => json_encode( [
				[
					'id'       => 50,
					'quantity' => 1,
					'source'   => 'woocommerce',
					'name'     => 'Discontinued Box',
					'sku'      => 'DB-001',
				],
			] ),
		];

		Functions\when( 'wc_get_product' )->justReturn( false );

		$html = $this->invoke_render_samples( $meta );

		$this->assertStringContainsString( 'Discontinued Box', $html );
		$this->assertStringContainsString( 'deleted', $html );
		$this->assertStringContainsString( 'DB-001', $html );
	}

	/**
	 * WC product row links to edit page when product exists.
	 */
	public function test_woo_existing_product_links_to_edit(): void {
		$product = new \WC_Product( [
			'id'       => 42,
			'name'     => 'Kraft Box',
			'image_id' => 0,
		] );

		$meta = [
			'samples' => json_encode( [
				[
					'id'       => 42,
					'quantity' => 1,
					'source'   => 'woocommerce',
					'name'     => 'Kraft Box',
					'sku'      => 'KB-001',
				],
			] ),
		];

		Functions\when( 'wc_get_product' )->justReturn( $product );

		$html = $this->invoke_render_samples( $meta );

		$this->assertStringContainsString( 'post.php?post=42&action=edit', $html );
		$this->assertStringNotContainsString( 'deleted', $html );
	}

	/**
	 * Empty meta produces no output.
	 */
	public function test_empty_meta_no_output(): void {
		$html = $this->invoke_render_samples( [] );
		$this->assertEmpty( $html );
	}

	/**
	 * Mixed library + WC products render correctly.
	 */
	public function test_mixed_library_and_woo(): void {
		$meta = [
			'samples' => json_encode( [
				[ 'id' => 1, 'quantity' => 1 ],
				[
					'id'       => 42,
					'quantity' => 2,
					'source'   => 'woocommerce',
					'name'     => 'WC Box',
					'sku'      => 'WCB-001',
				],
			] ),
		];

		$this->samples->shouldReceive( 'get' )
			->with( 1 )
			->andReturn( [ 'id' => 1, 'name' => 'Library Sample' ] );

		$this->images->shouldReceive( 'get_featured' )
			->with( 1 )
			->andReturn( null );

		Functions\when( 'wc_get_product' )->justReturn( false );

		$html = $this->invoke_render_samples( $meta );

		$this->assertStringContainsString( 'Library Sample', $html );
		$this->assertStringContainsString( 'WC Box', $html );
		$this->assertStringContainsString( 'WooCommerce', $html );
	}
}
