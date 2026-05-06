<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for the Shortcode class.
 *
 * @package SampleHQForm\Tests\Unit
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\FormsTable;
use SampleHQForm\Fields\FieldRegistry;
use SampleHQForm\Forms\FormRenderer;
use SampleHQForm\Shortcode;
use SampleHQForm\Spam\FormToken;

/**
 * Shortcode unit tests.
 */
class ShortcodeTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $forms;
	private FormRenderer $renderer;
	private $token;
	private Shortcode $shortcode;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->forms = Mockery::mock( FormsTable::class );
		$this->token = Mockery::mock( FormToken::class );

		$registry       = new FieldRegistry();
		$this->renderer = new FormRenderer( $registry );

		Monkey\Functions\stubs( [
			'absint'              => static fn( $n ) => abs( (int) $n ),
			'esc_attr'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_html'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_url'             => static fn( $s ) => (string) $s,
			'esc_html__'          => static fn( $s ) => $s,
			'__'                  => static fn( $s ) => $s,
			'rest_url'            => static fn( $s ) => 'https://example.com/wp-json/' . $s,
			'wp_strip_all_tags'   => static fn( $s ) => strip_tags( (string) $s ),
			'sanitize_hex_color'  => static fn( $s ) => (string) $s,
			'current_user_can'    => static fn() => true,
			'get_option'          => static fn( $key, $default = false ) => $default,
			'shortcode_atts'      => static function ( $defaults, $atts, $tag ) {
				return array_merge( $defaults, is_array( $atts ) ? $atts : [] );
			},
			'wp_localize_script'  => static fn() => true,
			'plugins_url'         => static fn( $path = '', $plugin = '' ) => 'https://example.com/wp-content/plugins/samplehq-request-form/' . $path,
		] );

		$this->shortcode = new Shortcode( $this->forms, $this->renderer, $this->token );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Register hooks add_shortcode.
	 */
	public function test_register(): void {
		Monkey\Functions\expect( 'add_shortcode' )
			->once()
			->with( 'samplehq_form', Mockery::type( 'array' ) );

		$this->shortcode->register();
	}

	/**
	 * Render with valid form produces form HTML.
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

		$this->token->shouldReceive( 'generate' )->with( 5 )->andReturn( 'csrf-token-abc' );

		// Stub asset enqueue.
		Monkey\Functions\stubs( [
			'wp_enqueue_script' => null,
			'wp_enqueue_style'  => null,
		] );

		$html = $this->shortcode->render( [ 'id' => '5' ] );

		$this->assertStringContainsString( '<form class="shqf-form"', $html );
		$this->assertStringContainsString( 'value="csrf-token-abc"', $html );
		$this->assertStringContainsString( 'data-form-id="5"', $html );
		$this->assertStringContainsString( 'Send', $html );
	}

	/**
	 * Render without ID shows error to admins.
	 */
	public function test_render_no_id_admin(): void {
		$html = $this->shortcode->render( [] );

		$this->assertStringContainsString( 'shqf-shortcode-error', $html );
		$this->assertStringContainsString( 'form ID', $html );
	}

	/**
	 * Render without ID shows nothing to non-admins.
	 */
	public function test_render_no_id_non_admin(): void {
		Monkey\Functions\stubs( [ 'current_user_can' => static fn() => false ] );

		$html = $this->shortcode->render( [] );

		$this->assertSame( '', $html );
	}

	/**
	 * Render with non-existent form shows error to admins.
	 */
	public function test_render_form_not_found(): void {
		$this->forms->shouldReceive( 'get' )->with( 999 )->andReturn( null );

		$html = $this->shortcode->render( [ 'id' => '999' ] );

		$this->assertStringContainsString( 'Form not found', $html );
	}

	/**
	 * Admin can preview draft forms via shortcode.
	 */
	public function test_render_draft_form_visible_to_admin(): void {
		$this->forms->shouldReceive( 'get' )->andReturn( [
			'id'     => '5',
			'status' => 'draft',
			'config' => [ 'fields' => [], 'appearance' => [], 'behavior' => [ 'submit_button_text' => 'Send' ] ],
		] );

		$this->token->shouldReceive( 'generate' )->with( 5 )->andReturn( 'token' );

		Monkey\Functions\stubs( [
			'wp_enqueue_script' => null,
			'wp_enqueue_style'  => null,
		] );

		$html = $this->shortcode->render( [ 'id' => '5' ] );

		$this->assertStringContainsString( '<form class="shqf-form"', $html );
	}

	/**
	 * Non-admin cannot see draft forms.
	 */
	public function test_render_draft_form_hidden_from_visitors(): void {
		Monkey\Functions\stubs( [ 'current_user_can' => static fn() => false ] );

		$this->forms->shouldReceive( 'get' )->andReturn( [
			'id'     => '5',
			'status' => 'draft',
			'config' => [],
		] );

		$html = $this->shortcode->render( [ 'id' => '5' ] );

		$this->assertSame( '', $html );
	}

	/**
	 * Non-admin visitors see empty string for errors.
	 */
	public function test_non_admin_sees_nothing_for_missing_form(): void {
		Monkey\Functions\stubs( [ 'current_user_can' => static fn() => false ] );

		$this->forms->shouldReceive( 'get' )->andReturn( null );

		$html = $this->shortcode->render( [ 'id' => '999' ] );

		$this->assertSame( '', $html );
	}
}
