<?php
/**
 * Tests for the ProductPageAssets class.
 *
 * @package SampleHQForm\Tests\Unit\WooCommerce
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\FormsTable;
use SampleHQForm\Forms\FormRenderer;
use SampleHQForm\Spam\FormToken;
use SampleHQForm\WooCommerce\ProductPageAssets;

/**
 * ProductPageAssets unit tests.
 */
class ProductPageAssetsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * @var FormsTable&Mockery\MockInterface
	 */
	private $forms;

	/**
	 * @var FormRenderer&Mockery\MockInterface
	 */
	private $renderer;

	/**
	 * @var FormToken&Mockery\MockInterface
	 */
	private $token;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->forms    = Mockery::mock( FormsTable::class );
		$this->renderer = Mockery::mock( FormRenderer::class );
		$this->token    = Mockery::mock( FormToken::class );

		Functions\stubs( [
			'esc_attr'   => static fn( $v ) => $v,
			'esc_attr__' => static fn( $v ) => $v,
			'esc_html'   => static fn( $v ) => $v,
			'esc_html__' => static fn( $v ) => $v,
			'__'         => static fn( $v ) => $v,
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
	 * register() hooks into wp_enqueue_scripts and wp_footer.
	 */
	public function test_register_hooks(): void {
		$hooks = [];
		Functions\expect( 'add_action' )
			->twice()
			->andReturnUsing( static function ( $hook ) use ( &$hooks ) {
				$hooks[] = $hook;
			} );

		$assets = new ProductPageAssets( $this->forms, $this->renderer, $this->token );
		$assets->register();

		$this->assertContains( 'wp_enqueue_scripts', $hooks );
		$this->assertContains( 'wp_footer', $hooks );
	}

	/**
	 * maybe_enqueue does nothing when not on a WooCommerce page.
	 */
	public function test_enqueue_skipped_when_not_wc_page(): void {
		Functions\when( 'is_product' )->justReturn( false );
		Functions\when( 'is_shop' )->justReturn( false );
		Functions\when( 'is_product_category' )->justReturn( false );
		Functions\when( 'is_product_tag' )->justReturn( false );

		$assets = new ProductPageAssets( $this->forms, $this->renderer, $this->token );
		$assets->maybe_enqueue();

		$this->assertTrue( true );
	}

	/**
	 * maybe_render_modal outputs nothing when not on a product page.
	 */
	public function test_render_modal_skipped_when_not_product_page(): void {
		Functions\when( 'is_product' )->justReturn( false );

		$assets = new ProductPageAssets( $this->forms, $this->renderer, $this->token );

		ob_start();
		$assets->maybe_render_modal();
		$html = ob_get_clean();

		$this->assertEmpty( $html );
	}

	/**
	 * maybe_render_modal outputs nothing when no form is configured.
	 */
	public function test_render_modal_skipped_when_no_form(): void {
		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( 0 );

		$this->forms->shouldReceive( 'list_all' )
			->once()
			->andReturn( [] );

		$assets = new ProductPageAssets( $this->forms, $this->renderer, $this->token );

		ob_start();
		$assets->maybe_render_modal();
		$html = ob_get_clean();

		$this->assertEmpty( $html );
	}

	/**
	 * maybe_render_modal outputs dialog HTML with form content.
	 */
	public function test_render_modal_outputs_dialog(): void {
		Functions\when( 'is_product' )->justReturn( true );

		Functions\when( 'get_option' )->alias( static function ( $key, $default = false ) {
			return match ( $key ) {
				'shqf_woo_form_id' => 5,
				default            => $default,
			};
		} );

		$this->forms->shouldReceive( 'get' )
			->with( 5 )
			->andReturn( [
				'id'     => 5,
				'title'  => 'Sample Request Form',
				'status' => 'published',
				'config' => [ 'fields' => [] ],
			] );

		$this->token->shouldReceive( 'generate' )->with( 5 )->andReturn( 'test-token-123' );

		Functions\expect( 'rest_url' )
			->once()
			->with( 'samplehq-form/v1/submissions' )
			->andReturn( 'https://example.com/wp-json/samplehq-form/v1/submissions' );

		Functions\expect( 'wp_localize_script' )
			->once()
			->with( 'shqf-form-frontend', 'shqfFormData_5', Mockery::type( 'array' ) );

		Functions\expect( 'current_user_can' )->andReturn( false );

		$this->renderer->shouldReceive( 'render' )
			->once()
			->with( 5, [ 'fields' => [] ], Mockery::type( 'array' ) )
			->andReturn( '<form class="shqf-form" data-form-id="5">...</form>' );

		$assets = new ProductPageAssets( $this->forms, $this->renderer, $this->token );

		ob_start();
		$assets->maybe_render_modal();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="shqf-woo-modal"', $html );
		$this->assertStringContainsString( 'role="dialog"', $html );
		$this->assertStringContainsString( 'aria-modal="true"', $html );
		$this->assertStringContainsString( 'aria-labelledby="shqf-woo-modal-title"', $html );
		$this->assertStringContainsString( 'Sample Request Form', $html );
		$this->assertStringContainsString( 'shqf-form', $html );
		$this->assertStringContainsString( 'style="display:none;"', $html );
	}

	/**
	 * Falls back to first published form when no form_id configured.
	 */
	public function test_render_modal_fallback_to_first_form(): void {
		Functions\when( 'is_product' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( 0 );

		$this->forms->shouldReceive( 'list_all' )
			->with( [ 'status' => 'published', 'limit' => 1 ] )
			->andReturn( [
				[
					'id'     => 3,
					'title'  => 'Default Form',
					'status' => 'published',
					'config' => [],
				],
			] );

		$this->token->shouldReceive( 'generate' )->andReturn( 'tok' );
		Functions\expect( 'rest_url' )->andReturn( 'https://example.com/api' );
		Functions\expect( 'wp_localize_script' )->once();
		Functions\expect( 'current_user_can' )->andReturn( false );

		$this->renderer->shouldReceive( 'render' )
			->once()
			->andReturn( '<form>...</form>' );

		$assets = new ProductPageAssets( $this->forms, $this->renderer, $this->token );

		ob_start();
		$assets->maybe_render_modal();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Default Form', $html );
	}

	/**
	 * Falls back to first published form when configured form was deleted.
	 */
	public function test_render_modal_fallback_when_configured_form_deleted(): void {
		Functions\when( 'is_product' )->justReturn( true );

		Functions\when( 'get_option' )->alias( static function ( $key, $default = false ) {
			return match ( $key ) {
				'shqf_woo_form_id' => 99,
				default            => $default,
			};
		} );

		$this->forms->shouldReceive( 'get' )
			->with( 99 )
			->andReturn( null );

		$this->forms->shouldReceive( 'list_all' )
			->with( [ 'status' => 'published', 'limit' => 1 ] )
			->andReturn( [
				[
					'id'     => 2,
					'title'  => 'Fallback Form',
					'status' => 'published',
					'config' => [],
				],
			] );

		$this->token->shouldReceive( 'generate' )->andReturn( 'tok' );
		Functions\expect( 'rest_url' )->andReturn( 'https://example.com/api' );
		Functions\expect( 'wp_localize_script' )->once();
		Functions\expect( 'current_user_can' )->andReturn( false );

		$this->renderer->shouldReceive( 'render' )
			->once()
			->andReturn( '<form>...</form>' );

		$assets = new ProductPageAssets( $this->forms, $this->renderer, $this->token );

		ob_start();
		$assets->maybe_render_modal();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Fallback Form', $html );
	}

	/**
	 * Trashed configured form triggers fallback for non-admins.
	 */
	public function test_render_modal_skipped_for_trashed_configured_form(): void {
		Functions\when( 'is_product' )->justReturn( true );

		Functions\when( 'get_option' )->alias( static function ( $key, $default = false ) {
			return match ( $key ) {
				'shqf_woo_form_id' => 8,
				default            => $default,
			};
		} );

		$this->forms->shouldReceive( 'get' )
			->with( 8 )
			->andReturn( [ 'id' => 8, 'title' => 'Trashed', 'status' => 'trash', 'config' => [] ] );

		$this->forms->shouldReceive( 'list_all' )
			->with( [ 'status' => 'published', 'limit' => 1 ] )
			->andReturn( [] );

		$assets = new ProductPageAssets( $this->forms, $this->renderer, $this->token );

		ob_start();
		$assets->maybe_render_modal();
		$html = ob_get_clean();

		$this->assertEmpty( $html );
	}

	/**
	 * Unpublished forms are hidden from non-admins.
	 */
	public function test_render_modal_skipped_for_draft_form_non_admin(): void {
		Functions\when( 'is_product' )->justReturn( true );

		Functions\when( 'get_option' )->alias( static function ( $key, $default = false ) {
			return match ( $key ) {
				'shqf_woo_form_id' => 7,
				default            => $default,
			};
		} );

		$this->forms->shouldReceive( 'get' )
			->with( 7 )
			->andReturn( [ 'id' => 7, 'title' => 'Draft', 'status' => 'draft', 'config' => [] ] );

		Functions\expect( 'current_user_can' )->with( 'manage_options' )->andReturn( false );

		$assets = new ProductPageAssets( $this->forms, $this->renderer, $this->token );

		ob_start();
		$assets->maybe_render_modal();
		$html = ob_get_clean();

		$this->assertEmpty( $html );
	}
}
