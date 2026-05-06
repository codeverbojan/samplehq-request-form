<?php
declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\WooCommerce\ProductButton;

class ProductButtonTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubs( [
			'esc_attr'       => static fn( $v ) => (string) $v,
			'esc_html'       => static fn( $v ) => (string) $v,
			'esc_url'        => static fn( $v ) => (string) $v,
			'esc_attr__'     => static fn( $v ) => (string) $v,
			'esc_html__'     => static fn( $v ) => (string) $v,
			'get_permalink'  => static fn() => 'https://example.com/product/test/',
			'__'             => static fn( $v ) => $v,
		] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// --- render_single ---

	public function test_render_single_outputs_button(): void {
		$product = new \WC_Product( [
			'id' => 42, 'name' => 'Kraft Mailer Box', 'sku' => 'KMB-001',
			'stock_status' => 'instock', 'status' => 'publish', 'type' => 'simple',
		] );
		$GLOBALS['product'] = $product;

		Functions\when( 'get_option' )->alias( static fn( $k, $d = false ) => match ( $k ) {
			'shqf_woo_product_filter' => 'all',
			'shqf_woo_button_text'    => 'Request a Sample',
			default                   => $d,
		} );

		$button = new ProductButton();
		ob_start();
		$button->render_single();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'data-product-id="42"', $html );
		$this->assertStringContainsString( 'data-product-name="Kraft Mailer Box"', $html );
		$this->assertStringContainsString( 'data-product-sku="KMB-001"', $html );
		$this->assertStringContainsString( 'shqf-woo-request-btn', $html );
		$this->assertStringContainsString( 'shqf-woo-request-wrap', $html );
		$this->assertStringContainsString( 'Request a Sample', $html );

		unset( $GLOBALS['product'] );
	}

	public function test_render_single_skipped_for_out_of_stock(): void {
		$GLOBALS['product'] = new \WC_Product( [
			'id' => 10, 'stock_status' => 'outofstock', 'status' => 'publish', 'type' => 'simple',
		] );
		ob_start();
		( new ProductButton() )->render_single();
		$this->assertEmpty( ob_get_clean() );
		unset( $GLOBALS['product'] );
	}

	public function test_render_single_skipped_for_draft(): void {
		$GLOBALS['product'] = new \WC_Product( [
			'id' => 11, 'stock_status' => 'instock', 'status' => 'draft', 'type' => 'simple',
		] );
		ob_start();
		( new ProductButton() )->render_single();
		$this->assertEmpty( ob_get_clean() );
		unset( $GLOBALS['product'] );
	}

	public function test_render_single_skipped_for_external_product(): void {
		$GLOBALS['product'] = new \WC_Product( [
			'id' => 12, 'stock_status' => 'instock', 'status' => 'publish', 'type' => 'external',
		] );
		ob_start();
		( new ProductButton() )->render_single();
		$this->assertEmpty( ob_get_clean() );
		unset( $GLOBALS['product'] );
	}

	public function test_render_single_skipped_for_grouped_product(): void {
		$GLOBALS['product'] = new \WC_Product( [
			'id' => 13, 'stock_status' => 'instock', 'status' => 'publish', 'type' => 'grouped',
		] );
		ob_start();
		( new ProductButton() )->render_single();
		$this->assertEmpty( ob_get_clean() );
		unset( $GLOBALS['product'] );
	}

	public function test_render_single_shows_for_variable_product(): void {
		$GLOBALS['product'] = new \WC_Product( [
			'id' => 14, 'name' => 'Variable', 'sku' => 'V-1',
			'stock_status' => 'instock', 'status' => 'publish', 'type' => 'variable',
		] );
		Functions\when( 'get_option' )->alias( static fn( $k, $d = false ) => match ( $k ) {
			'shqf_woo_product_filter' => 'all',
			'shqf_woo_button_text'    => 'Request a Sample',
			default                   => $d,
		} );

		ob_start();
		( new ProductButton() )->render_single();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'data-product-id="14"', $html );
		unset( $GLOBALS['product'] );
	}

	// --- Filters ---

	public function test_tag_filter_excludes_untagged(): void {
		$GLOBALS['product'] = new \WC_Product( [
			'id' => 20, 'stock_status' => 'instock', 'status' => 'publish', 'type' => 'simple',
		] );
		Functions\when( 'get_option' )->alias( static fn( $k, $d = false ) => match ( $k ) {
			'shqf_woo_product_filter' => 'tagged',
			'shqf_woo_sample_tag'     => 'sample-available',
			default                   => $d,
		} );
		Functions\expect( 'get_the_terms' )->with( 20, 'product_tag' )->andReturn( false );

		ob_start();
		( new ProductButton() )->render_single();
		$this->assertEmpty( ob_get_clean() );
		unset( $GLOBALS['product'] );
	}

	public function test_category_filter_excludes_wrong_category(): void {
		$GLOBALS['product'] = new \WC_Product( [
			'id' => 30, 'stock_status' => 'instock', 'status' => 'publish',
			'type' => 'simple', 'category_ids' => [ 5, 6 ],
		] );
		Functions\when( 'get_option' )->alias( static fn( $k, $d = false ) => match ( $k ) {
			'shqf_woo_product_filter'    => 'category',
			'shqf_woo_sample_categories' => [ 10, 11 ],
			default                      => $d,
		} );

		ob_start();
		( new ProductButton() )->render_single();
		$this->assertEmpty( ob_get_clean() );
		unset( $GLOBALS['product'] );
	}

	// --- register ---

	public function test_register_hooks(): void {
		$hooks = [];
		Functions\expect( 'add_action' )
			->once()
			->andReturnUsing( static function ( $hook ) use ( &$hooks ) {
				$hooks[] = [ 'action', $hook ];
			} );
		Functions\expect( 'add_filter' )
			->once()
			->andReturnUsing( static function ( $hook, $cb, $pri, $args ) use ( &$hooks ) {
				$hooks[] = [ 'filter', $hook, $args ];
			} );

		( new ProductButton() )->register();

		$this->assertSame( 'woocommerce_after_add_to_cart_form', $hooks[0][1] );
		$this->assertSame( 'woocommerce_loop_add_to_cart_link', $hooks[1][1] );
		$this->assertSame( 3, $hooks[1][2] ); // 3 accepted args.
	}

	// --- filter_loop_link ---

	public function test_filter_loop_link_appends_badge(): void {
		$product = new \WC_Product( [
			'id' => 33, 'name' => 'Kraft', 'sku' => 'K-1',
			'stock_status' => 'instock', 'status' => 'publish', 'type' => 'simple',
		] );
		Functions\when( 'get_option' )->alias( static fn( $k, $d = false ) => match ( $k ) {
			'shqf_woo_product_filter'   => 'all',
			'shqf_woo_show_loop_badge'  => '1',
			'shqf_woo_badge_text'       => 'Free sample available',
			default                     => $d,
		} );

		$result = ( new ProductButton() )->filter_loop_link( '<a>Add to cart</a>', $product );

		$this->assertStringContainsString( 'Add to cart</a>', $result );
		$this->assertStringContainsString( 'shqf-woo-sample-badge', $result );
		$this->assertStringContainsString( '#request-sample', $result );
		$this->assertStringContainsString( 'Free sample available', $result );
		$this->assertStringContainsString( 'aria-label', $result );
	}

	public function test_filter_loop_link_uses_custom_badge_text(): void {
		$product = new \WC_Product( [
			'id' => 33, 'name' => 'Kraft', 'sku' => 'K-1',
			'stock_status' => 'instock', 'status' => 'publish', 'type' => 'simple',
		] );
		Functions\when( 'get_option' )->alias( static fn( $k, $d = false ) => match ( $k ) {
			'shqf_woo_product_filter'   => 'all',
			'shqf_woo_show_loop_badge'  => '1',
			'shqf_woo_badge_text'       => 'Get a free sample',
			default                     => $d,
		} );

		$result = ( new ProductButton() )->filter_loop_link( '<a>Add to cart</a>', $product );

		$this->assertStringContainsString( 'Get a free sample', $result );
		$this->assertStringNotContainsString( 'Free sample available', $result );
	}

	public function test_filter_loop_link_hidden_when_badge_disabled(): void {
		$product = new \WC_Product( [
			'id' => 33, 'name' => 'Kraft', 'sku' => 'K-1',
			'stock_status' => 'instock', 'status' => 'publish', 'type' => 'simple',
		] );
		Functions\when( 'get_option' )->alias( static fn( $k, $d = false ) => match ( $k ) {
			'shqf_woo_product_filter'   => 'all',
			'shqf_woo_show_loop_badge'  => '',
			default                     => $d,
		} );

		$result = ( new ProductButton() )->filter_loop_link( '<a>Add to cart</a>', $product );

		$this->assertSame( '<a>Add to cart</a>', $result );
	}

	public function test_filter_loop_link_empty_badge_text_uses_default(): void {
		$product = new \WC_Product( [
			'id' => 33, 'name' => 'Kraft', 'sku' => 'K-1',
			'stock_status' => 'instock', 'status' => 'publish', 'type' => 'simple',
		] );
		Functions\when( 'get_option' )->alias( static fn( $k, $d = false ) => match ( $k ) {
			'shqf_woo_product_filter'   => 'all',
			'shqf_woo_show_loop_badge'  => '1',
			'shqf_woo_badge_text'       => '',
			default                     => $d,
		} );

		$result = ( new ProductButton() )->filter_loop_link( '<a>Add to cart</a>', $product );

		$this->assertStringContainsString( 'Free sample available', $result );
	}

	public function test_filter_loop_link_skips_external(): void {
		$product = new \WC_Product( [
			'id' => 40, 'stock_status' => 'instock', 'status' => 'publish', 'type' => 'external',
		] );
		Functions\when( 'get_option' )->alias( static fn( $k, $d = false ) => match ( $k ) {
			'shqf_woo_show_loop_badge' => '1',
			default                    => $d,
		} );
		$result = ( new ProductButton() )->filter_loop_link( '<a>Buy</a>', $product );
		$this->assertSame( '<a>Buy</a>', $result );
	}
}
