<?php
/**
 * Tests for WooCommerceSource.
 *
 * @package SampleHQForm\Tests\Unit\Fields\ProductSource
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Fields\ProductSource;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Fields\ProductSource\ProductSourceInterface;
use SampleHQForm\Fields\ProductSource\WooCommerceSource;

class WooCommerceSourceTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private WooCommerceSource $source;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'get_option'                 => static function ( $key, $default = false ) {
				$options = [
					'shqf_woo_product_filter'  => 'all',
					'shqf_woo_max_quantity'    => 3,
					'shqf_woo_sample_tag'      => 'sample-available',
					'shqf_woo_cache_version'   => 0,
				];
				return $options[ $key ] ?? $default;
			},
			'get_transient'              => static fn() => false,
			'set_transient'              => static fn() => true,
			'wp_json_encode'             => static fn( $v ) => json_encode( $v ),
			'sanitize_title'             => static fn( $s ) => strtolower( str_replace( ' ', '-', $s ) ),
			'wp_get_attachment_image_url' => static fn() => 'https://example.com/product.jpg',
			'is_wp_error'                => static fn() => false,
			'wp_get_object_terms'        => static fn() => [],
			'_prime_post_caches'         => static fn() => null,
		] );

		$this->source = new WooCommerceSource();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_implements_interface(): void {
		$this->assertInstanceOf( ProductSourceInterface::class, $this->source );
	}

	public function test_get_samples_maps_wc_products_correctly(): void {
		$products = [
			new \WC_Product( [
				'id'                => 33,
				'name'              => 'Kraft Mailer Box',
				'sku'               => 'KMB-1084',
				'short_description' => 'Recyclable kraft mailer',
				'stock_status'      => 'instock',
				'status'            => 'publish',
				'type'              => 'simple',
				'image_id'          => 100,
			] ),
			new \WC_Product( [
				'id'                => 34,
				'name'              => 'Poly Bag',
				'sku'               => 'PB-1215',
				'short_description' => 'Clear poly bag',
				'stock_status'      => 'instock',
				'status'            => 'publish',
				'type'              => 'simple',
				'image_id'          => 0,
			] ),
		];

		Monkey\Functions\expect( 'wc_get_products' )
			->once()
			->andReturn( $products );

		$result = $this->source->get_samples();

		$this->assertCount( 2, $result );

		// First product.
		$this->assertSame( 33, $result[0]['id'] );
		$this->assertSame( 'Kraft Mailer Box', $result[0]['name'] );
		$this->assertSame( 'KMB-1084', $result[0]['sku'] );
		$this->assertSame( 'Recyclable kraft mailer', $result[0]['description'] );
		$this->assertSame( 3, $result[0]['max_quantity'] );
		$this->assertSame( 'active', $result[0]['status'] );
		$this->assertArrayHasKey( 'category_names', $result[0] );
		$this->assertArrayHasKey( 'image_id', $result[0] );
		$this->assertSame( 100, $result[0]['image_id'] );

		// Second product (no image).
		$this->assertSame( 34, $result[1]['id'] );
		$this->assertSame( 'Poly Bag', $result[1]['name'] );
		$this->assertSame( 0, $result[1]['image_id'] );
	}

	public function test_get_samples_excludes_grouped_product_type(): void {
		$captured_args = null;

		Monkey\Functions\expect( 'wc_get_products' )
			->once()
			->andReturnUsing( static function ( $args ) use ( &$captured_args ) {
				$captured_args = $args;
				return [];
			} );

		$this->source->get_samples();

		$this->assertNotNull( $captured_args );
		$this->assertContains( 'simple', $captured_args['type'] );
		$this->assertContains( 'variable', $captured_args['type'] );
		$this->assertNotContains( 'grouped', $captured_args['type'] );
	}

	public function test_get_samples_filters_by_tag(): void {
		Monkey\tearDown();
		Monkey\setUp();

		$captured_args = null;
		Monkey\Functions\stubs( [
			'get_option'      => static function ( $key, $default = false ) {
				$opts = [
					'shqf_woo_product_filter' => 'tagged',
					'shqf_woo_sample_tag'     => 'sample-available',
					'shqf_woo_max_quantity'   => 3,
					'shqf_woo_cache_version'  => 0,
				];
				return $opts[ $key ] ?? $default;
			},
			'get_transient'   => static fn() => false,
			'set_transient'   => static fn() => true,
			'wp_json_encode'  => static fn( $v ) => json_encode( $v ),
			'sanitize_title'  => static fn( $s ) => strtolower( str_replace( ' ', '-', $s ) ),
			'wc_get_products' => static function ( $args ) use ( &$captured_args ) {
				$captured_args = $args;
				return [];
			},
		] );

		$source = new WooCommerceSource();
		$source->get_samples();

		$this->assertNotNull( $captured_args );
		$this->assertSame( [ 'sample-available' ], $captured_args['tag'] );
	}

	public function test_get_samples_filters_by_category(): void {
		Monkey\tearDown();
		Monkey\setUp();

		$captured_args = null;
		Monkey\Functions\stubs( [
			'get_option'      => static function ( $key, $default = false ) {
				$opts = [
					'shqf_woo_product_filter'    => 'category',
					'shqf_woo_sample_categories' => [ 17, 18 ],
					'shqf_woo_max_quantity'       => 3,
					'shqf_woo_cache_version'     => 0,
				];
				return $opts[ $key ] ?? $default;
			},
			'get_transient'   => static fn() => false,
			'set_transient'   => static fn() => true,
			'wp_json_encode'  => static fn( $v ) => json_encode( $v ),
			'is_wp_error'     => static fn() => false,
			'wc_get_products' => static function ( $args ) use ( &$captured_args ) {
				$captured_args = $args;
				return [];
			},
			'get_terms'       => static fn() => [ 17 => 'boxes', 18 => 'bags' ],
		] );

		$source = new WooCommerceSource();
		$source->get_samples();

		$this->assertNotNull( $captured_args );
		$this->assertSame( [ 'boxes', 'bags' ], $captured_args['category'] );
	}

	public function test_get_samples_category_filter_handles_wp_error(): void {
		Monkey\tearDown();
		Monkey\setUp();

		$captured_args = null;
		Monkey\Functions\stubs( [
			'get_option'      => static function ( $key, $default = false ) {
				$opts = [
					'shqf_woo_product_filter'    => 'category',
					'shqf_woo_sample_categories' => [ 17 ],
					'shqf_woo_max_quantity'       => 3,
					'shqf_woo_cache_version'     => 0,
				];
				return $opts[ $key ] ?? $default;
			},
			'get_transient'   => static fn() => false,
			'set_transient'   => static fn() => true,
			'wp_json_encode'  => static fn( $v ) => json_encode( $v ),
			'is_wp_error'     => static fn() => true,
			'get_terms'       => static fn() => 'wp_error_mock',
			'wc_get_products' => static function ( $args ) use ( &$captured_args ) {
				$captured_args = $args;
				return [];
			},
		] );

		$source = new WooCommerceSource();
		$source->get_samples();

		// When get_terms returns error, category resolves to empty array (graceful degradation).
		$this->assertSame( [], $captured_args['category'] );
	}

	public function test_get_samples_uses_description_fallback(): void {
		$product = new \WC_Product( [
			'id'          => 35,
			'name'        => 'No Short Desc',
			'sku'         => '',
			'description' => 'Full description here',
		] );

		Monkey\Functions\expect( 'wc_get_products' )
			->once()
			->andReturn( [ $product ] );

		$result = $this->source->get_samples();

		$this->assertSame( 'Full description here', $result[0]['description'] );
	}

	public function test_get_samples_caches_results(): void {
		$cached = [
			[ 'id' => 33, 'name' => 'Cached Product', 'status' => 'active' ],
		];

		// Fresh source with cache-returning transient.
		Monkey\tearDown();
		Monkey\setUp();
		Monkey\Functions\stubs( [
			'get_option'     => static function ( $key, $default = false ) {
				$options = [
					'shqf_woo_product_filter' => 'all',
					'shqf_woo_cache_version'  => 0,
				];
				return $options[ $key ] ?? $default;
			},
			'wp_json_encode' => static fn( $v ) => json_encode( $v ),
			'get_transient'  => static fn() => $cached,
		] );

		$source = new WooCommerceSource();
		$result = $source->get_samples();

		$this->assertCount( 1, $result );
		$this->assertSame( 'Cached Product', $result[0]['name'] );
	}

	public function test_get_sample_returns_mapped_product(): void {
		$product = new \WC_Product( [
			'id'                => 33,
			'name'              => 'Kraft Mailer',
			'sku'               => 'KMB-1084',
			'short_description' => 'A mailer box',
		] );

		Monkey\Functions\expect( 'wc_get_product' )
			->once()
			->with( 33 )
			->andReturn( $product );

		$result = $this->source->get_sample( 33 );

		$this->assertNotNull( $result );
		$this->assertSame( 33, $result['id'] );
		$this->assertSame( 'Kraft Mailer', $result['name'] );
		// get_sample() must NOT include batch-only fields (used for validation, not rendering).
		$this->assertArrayNotHasKey( 'category_names', $result );
		$this->assertArrayNotHasKey( 'image_id', $result );
	}

	public function test_get_sample_returns_null_when_not_found(): void {
		Monkey\Functions\expect( 'wc_get_product' )
			->once()
			->with( 999 )
			->andReturn( false );

		$this->assertNull( $this->source->get_sample( 999 ) );
	}

	public function test_get_image_url_returns_null_when_no_image(): void {
		$product = new \WC_Product( [
			'id'       => 33,
			'name'     => 'No Image',
			'image_id' => 0,
		] );

		Monkey\Functions\expect( 'wc_get_product' )
			->once()
			->with( 33 )
			->andReturn( $product );

		$this->assertNull( $this->source->get_image_url( 33 ) );
	}

	public function test_get_image_url_returns_url_when_image_exists(): void {
		$product = new \WC_Product( [
			'id'       => 33,
			'name'     => 'Has Image',
			'image_id' => 42,
		] );

		Monkey\Functions\expect( 'wc_get_product' )
			->once()
			->with( 33 )
			->andReturn( $product );

		$url = $this->source->get_image_url( 33, 'medium' );
		$this->assertSame( 'https://example.com/product.jpg', $url );
	}

	/**
	 * Variation filter hook defaults to false (no variations shown).
	 */
	public function test_variations_excluded_by_default(): void {
		$variable = new \WC_Product( [
			'id'   => 40,
			'name' => 'Variable Product',
			'sku'  => 'VP-001',
			'type' => 'variable',
		] );

		Monkey\Functions\expect( 'wc_get_products' )
			->once()
			->andReturn( [ $variable ] );

		Monkey\Functions\expect( 'apply_filters' )
			->with( 'shqf_woo_show_variations', false )
			->andReturn( false );

		$result = $this->source->get_samples();

		// Variable parent included, but no variations.
		$this->assertCount( 1, $result );
		$this->assertSame( 'Variable Product', $result[0]['name'] );
	}

	/**
	 * Variation filter hook when enabled adds variation children.
	 */
	public function test_variations_included_when_filter_enabled(): void {
		Monkey\tearDown();
		Monkey\setUp();

		$variation = new \WC_Product( [
			'id'           => 41,
			'name'         => 'Variable Product - Large',
			'sku'          => 'VP-001-L',
			'type'         => 'variation',
			'stock_status' => 'instock',
		] );

		$variable = new \WC_Product( [
			'id'       => 40,
			'name'     => 'Variable Product',
			'sku'      => 'VP-001',
			'type'     => 'variable',
			'children' => [ 41 ],
		] );

		Monkey\Functions\stubs( [
			'get_option'          => static function ( $key, $default = false ) {
				$options = [
					'shqf_woo_product_filter' => 'all',
					'shqf_woo_max_quantity'   => 3,
					'shqf_woo_cache_version'  => 0,
				];
				return $options[ $key ] ?? $default;
			},
			'get_transient'       => static fn() => false,
			'set_transient'       => static fn() => true,
			'wp_json_encode'      => static fn( $v ) => json_encode( $v ),
			'wp_get_object_terms' => static fn() => [],
			'_prime_post_caches'  => static fn() => null,
			'wp_get_attachment_image_url' => static fn() => null,
			'is_wp_error'         => static fn() => false,
		] );

		Monkey\Functions\expect( 'wc_get_products' )
			->once()
			->andReturn( [ $variable ] );

		Monkey\Functions\expect( 'apply_filters' )
			->with( 'shqf_woo_show_variations', false )
			->andReturn( true );

		Monkey\Functions\expect( 'wc_get_product' )
			->with( 41 )
			->andReturn( $variation );

		$source = new WooCommerceSource();
		$result = $source->get_samples();

		// Parent + 1 variation.
		$this->assertCount( 2, $result );
		$this->assertSame( 'Variable Product', $result[0]['name'] );
		$this->assertSame( 'Variable Product - Large', $result[1]['name'] );
	}

	/**
	 * Batch category loading includes category_names in mapped data.
	 */
	public function test_get_samples_includes_batch_loaded_categories(): void {
		Monkey\tearDown();
		Monkey\setUp();

		$term1 = (object) [ 'term_id' => 5, 'name' => 'Boxes', 'object_id' => 33 ];
		$term2 = (object) [ 'term_id' => 6, 'name' => 'Bags', 'object_id' => 34 ];
		$term3 = (object) [ 'term_id' => 5, 'name' => 'Boxes', 'object_id' => 34 ];

		Monkey\Functions\stubs( [
			'get_option'          => static function ( $key, $default = false ) {
				$options = [
					'shqf_woo_product_filter' => 'all',
					'shqf_woo_max_quantity'   => 3,
					'shqf_woo_cache_version'  => 0,
				];
				return $options[ $key ] ?? $default;
			},
			'get_transient'       => static fn() => false,
			'set_transient'       => static fn() => true,
			'wp_json_encode'      => static fn( $v ) => json_encode( $v ),
			'is_wp_error'         => static fn() => false,
			'_prime_post_caches'  => static fn() => null,
			'wp_get_attachment_image_url' => static fn() => null,
		] );

		Monkey\Functions\expect( 'wc_get_products' )
			->once()
			->andReturn( [
				new \WC_Product( [ 'id' => 33, 'name' => 'Product A', 'sku' => 'A', 'type' => 'simple' ] ),
				new \WC_Product( [ 'id' => 34, 'name' => 'Product B', 'sku' => 'B', 'type' => 'simple' ] ),
			] );

		Monkey\Functions\expect( 'wp_get_object_terms' )
			->once()
			->with( [ 33, 34 ], 'product_cat', [ 'fields' => 'all_with_object_id' ] )
			->andReturn( [ $term1, $term2, $term3 ] );

		$source = new WooCommerceSource();
		$result = $source->get_samples();

		$this->assertSame( [ 'Boxes' ], $result[0]['category_names'] );
		$this->assertSame( [ 'Bags', 'Boxes' ], $result[1]['category_names'] );
	}

	/**
	 * Batch image priming includes image_id in mapped data.
	 */
	public function test_get_samples_includes_batch_primed_image_ids(): void {
		Monkey\tearDown();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'get_option'         => static function ( $key, $default = false ) {
				$options = [
					'shqf_woo_product_filter' => 'all',
					'shqf_woo_max_quantity'   => 3,
					'shqf_woo_cache_version'  => 0,
				];
				return $options[ $key ] ?? $default;
			},
			'get_transient'      => static fn() => false,
			'set_transient'      => static fn() => true,
			'wp_json_encode'     => static fn( $v ) => json_encode( $v ),
			'is_wp_error'        => static fn() => false,
			'wp_get_object_terms' => static fn() => [],
			'_prime_post_caches' => static fn() => null,
		] );

		Monkey\Functions\expect( 'wc_get_products' )
			->once()
			->andReturn( [
				new \WC_Product( [ 'id' => 33, 'name' => 'With Image', 'sku' => 'A', 'type' => 'simple', 'image_id' => 100 ] ),
				new \WC_Product( [ 'id' => 34, 'name' => 'No Image', 'sku' => 'B', 'type' => 'simple', 'image_id' => 0 ] ),
			] );

		$source = new WooCommerceSource();
		$result = $source->get_samples();

		// image_id is stored (not resolved URL) -- callers resolve at render time.
		$this->assertSame( 100, $result[0]['image_id'] );
		$this->assertSame( 0, $result[1]['image_id'] );
	}

	/**
	 * Variations inherit parent categories via get_category_ids() fallback.
	 */
	public function test_variation_categories_fall_back_to_parent(): void {
		Monkey\tearDown();
		Monkey\setUp();

		// Parent product has category term 5 assigned directly.
		$parent_term = (object) [ 'term_id' => 5, 'name' => 'Boxes', 'object_id' => 40 ];

		Monkey\Functions\stubs( [
			'get_option'          => static function ( $key, $default = false ) {
				$options = [
					'shqf_woo_product_filter' => 'all',
					'shqf_woo_max_quantity'   => 3,
					'shqf_woo_cache_version'  => 0,
				];
				return $options[ $key ] ?? $default;
			},
			'get_transient'       => static fn() => false,
			'set_transient'       => static fn() => true,
			'wp_json_encode'      => static fn( $v ) => json_encode( $v ),
			'is_wp_error'         => static fn() => false,
			'_prime_post_caches'  => static fn() => null,
		] );

		// wp_get_object_terms returns category only for parent (40), not variation (41).
		Monkey\Functions\expect( 'wp_get_object_terms' )
			->once()
			->andReturn( [ $parent_term ] );

		$parent = new \WC_Product( [
			'id' => 40, 'name' => 'Variable', 'sku' => 'V', 'type' => 'variable',
			'children' => [ 41 ], 'category_ids' => [ 5 ],
		] );
		$variation = new \WC_Product( [
			'id' => 41, 'name' => 'Variable - Large', 'sku' => 'V-L', 'type' => 'variation',
			'stock_status' => 'instock', 'category_ids' => [ 5 ],
		] );

		Monkey\Functions\expect( 'wc_get_products' )
			->once()
			->andReturn( [ $parent ] );

		Monkey\Functions\expect( 'apply_filters' )
			->with( 'shqf_woo_show_variations', false )
			->andReturn( true );

		Monkey\Functions\expect( 'wc_get_product' )
			->with( 41 )
			->andReturn( $variation );

		$source = new WooCommerceSource();
		$result = $source->get_samples();

		// Parent gets category from wp_get_object_terms batch.
		$this->assertSame( [ 'Boxes' ], $result[0]['category_names'] );
		// Variation gets category via get_category_ids() fallback (parent resolution).
		$this->assertSame( [ 'Boxes' ], $result[1]['category_names'] );
	}

	/**
	 * Cache prefix constant is accessible.
	 */
	public function test_cache_prefix_constant(): void {
		$this->assertSame( 'shqf_woo_samples_', WooCommerceSource::CACHE_PREFIX );
	}

	/**
	 * clear_cache increments the version option.
	 */
	public function test_clear_cache_increments_version(): void {
		Monkey\tearDown();
		Monkey\setUp();

		$stored_version = 5;
		Monkey\Functions\expect( 'get_option' )
			->once()
			->with( WooCommerceSource::CACHE_VERSION_OPTION, 0 )
			->andReturn( $stored_version );

		Monkey\Functions\expect( 'update_option' )
			->once()
			->with( WooCommerceSource::CACHE_VERSION_OPTION, 6, true );

		WooCommerceSource::clear_cache();
	}

	/**
	 * clear_cache starts from 0 when no version exists.
	 */
	public function test_clear_cache_initializes_version(): void {
		Monkey\tearDown();
		Monkey\setUp();

		Monkey\Functions\expect( 'get_option' )
			->once()
			->with( WooCommerceSource::CACHE_VERSION_OPTION, 0 )
			->andReturn( 0 );

		Monkey\Functions\expect( 'update_option' )
			->once()
			->with( WooCommerceSource::CACHE_VERSION_OPTION, 1, true );

		WooCommerceSource::clear_cache();
	}
}
