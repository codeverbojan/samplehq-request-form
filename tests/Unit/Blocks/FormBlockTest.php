<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for the FormBlock Gutenberg block.
 *
 * @package SampleHQForm\Tests\Unit\Blocks
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Blocks;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Blocks\FormBlock;
use SampleHQForm\Database\FormsTable;
use SampleHQForm\Fields\FieldRegistry;
use SampleHQForm\Forms\FormRenderer;
use SampleHQForm\Spam\FormToken;

/**
 * FormBlock unit tests.
 */
class FormBlockTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $forms;
	private FormRenderer $renderer;
	private $token;
	private FormBlock $block;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->forms = Mockery::mock( FormsTable::class );
		$this->token = Mockery::mock( FormToken::class );

		$registry       = new FieldRegistry();
		$this->renderer = new FormRenderer( $registry );

		Monkey\Functions\stubs( [
			'absint'             => static fn( $n ) => abs( (int) $n ),
			'esc_html__'         => static fn( $s ) => $s,
			'esc_html'           => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ),
			'esc_attr'           => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ),
			'esc_url'            => static fn( $s ) => (string) $s,
			'__'                 => static fn( $s ) => $s,
			'rest_url'           => static fn( $s ) => 'https://example.com/wp-json/' . $s,
			'current_user_can'   => static fn() => true,
			'wp_strip_all_tags'  => static fn( $s ) => strip_tags( (string) $s ),
			'sanitize_hex_color' => static fn( $s ) => (string) $s,
			'wp_localize_script' => static fn() => true,
			'wp_enqueue_script'  => static fn() => null,
			'wp_enqueue_style'   => static fn() => null,
			'get_option'         => static fn( $key, $default = false ) => $default,
			'plugins_url'        => static fn( $path = '', $plugin = '' ) => 'https://example.com/wp-content/plugins/samplehq-request-form/' . $path,
		] );

		$this->block = new FormBlock( $this->forms, $this->renderer, $this->token );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Register hooks init and block_categories_all.
	 */
	public function test_register(): void {
		Monkey\Functions\expect( 'add_action' )
			->once()
			->with( 'init', Mockery::type( 'array' ) );

		Monkey\Functions\expect( 'add_filter' )
			->once()
			->with( 'block_categories_all', Mockery::type( 'array' ) );

		$this->block->register();
	}

	/**
	 * Category registration adds samplehq-forms to the list.
	 */
	public function test_register_category(): void {
		$existing   = [ [ 'slug' => 'common', 'title' => 'Common' ] ];
		$categories = $this->block->register_category( $existing );

		$this->assertSame( 'samplehq-forms', $categories[0]['slug'] );
		$this->assertCount( 2, $categories );
	}

	/**
	 * Render with valid published form returns form HTML.
	 */
	public function test_render_valid_form(): void {
		$this->forms->shouldReceive( 'get' )->with( 5 )->andReturn( [
			'id'     => '5',
			'status' => 'published',
			'config' => [
				'schema_version' => 1,
				'fields'         => [],
				'appearance'     => [],
				'behavior'       => [ 'submit_button_text' => 'Send' ],
			],
		] );

		$this->token->shouldReceive( 'generate' )->with( 5 )->andReturn( 'block-token' );

		$html = $this->block->render( [ 'formId' => 5 ] );

		$this->assertStringContainsString( '<form class="shqf-form"', $html );
		$this->assertStringContainsString( 'data-form-id="5"', $html );
		$this->assertStringContainsString( 'value="block-token"', $html );
	}

	/**
	 * Render without formId shows admin error.
	 */
	public function test_render_no_form_id(): void {
		$html = $this->block->render( [] );

		$this->assertStringContainsString( 'shqf-block-error', $html );
		$this->assertStringContainsString( 'select a form', $html );
	}

	/**
	 * Render without formId returns empty for non-admins.
	 */
	public function test_render_no_form_id_non_admin(): void {
		Monkey\Functions\stubs( [ 'current_user_can' => static fn() => false ] );

		$html = $this->block->render( [] );
		$this->assertSame( '', $html );
	}

	/**
	 * Render with nonexistent form shows admin error.
	 */
	public function test_render_missing_form(): void {
		$this->forms->shouldReceive( 'get' )->with( 999 )->andReturn( null );

		$html = $this->block->render( [ 'formId' => 999 ] );

		$this->assertStringContainsString( 'shqf-block-error', $html );
	}

	/**
	 * Render with draft form returns empty for non-admins.
	 */
	public function test_render_draft_form_non_admin(): void {
		Monkey\Functions\stubs( [ 'current_user_can' => static fn() => false ] );

		$this->forms->shouldReceive( 'get' )->andReturn( [
			'id'     => '5',
			'status' => 'draft',
		] );

		$html = $this->block->render( [ 'formId' => 5 ] );
		$this->assertSame( '', $html );
	}
}
