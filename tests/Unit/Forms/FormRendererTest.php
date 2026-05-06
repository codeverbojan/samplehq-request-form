<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for FormRenderer.
 *
 * @package SampleHQForm\Tests\Unit\Forms
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Forms;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Fields\FieldInterface;
use SampleHQForm\Fields\FieldRegistry;
use SampleHQForm\Forms\FormRenderer;

/**
 * FormRenderer unit tests.
 */
class FormRendererTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private FieldRegistry $registry;
	private FormRenderer $renderer;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'esc_attr'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_html'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_url'             => static function ( $s ): string {
				$s = (string) $s;
				// Minimal esc_url: strip javascript/data protocols like WP core does.
				if ( preg_match( '/^\s*(javascript|data|vbscript)\s*:/i', $s ) ) {
					return '';
				}
				return $s;
			},
			'esc_html__'          => static fn( $s ) => $s,
			'esc_attr__'          => static fn( $s ) => $s,
			'__'                  => static fn( $s ) => $s,
			'wp_strip_all_tags'   => static fn( $s ) => strip_tags( (string) $s ),
			'sanitize_hex_color'  => static function ( $color ): ?string {
				// Match real WP behavior: return null for invalid hex colors.
				if ( preg_match( '/^#([0-9a-fA-F]{3}){1,2}$/', (string) $color ) ) {
					return (string) $color;
				}
				return null;
			},
			'absint'              => static fn( $n ) => abs( (int) $n ),
			'get_option'          => static fn( $key, $default = false ) => $default,
		] );

		$this->registry = new FieldRegistry();
		$this->renderer = new FormRenderer( $this->registry );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Get a minimal form config for testing.
	 *
	 * @return array<string, mixed>
	 */
	private function get_config(): array {
		return [
			'schema_version' => 1,
			'fields'         => [
				[ 'id' => 'f_1', 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true ],
			],
			'appearance'     => [
				'primary_color'     => '#0F766E',
				'button_color'      => '#0F766E',
				'button_text_color' => '#FFFFFF',
				'border_radius'     => 8,
			],
			'behavior'       => [
				'submit_button_text' => 'Submit Request',
			],
		];
	}

	/**
	 * Render produces a form element with correct structure.
	 */
	public function test_renders_form_structure(): void {
		$mock_field = Mockery::mock( FieldInterface::class );
		$mock_field->shouldReceive( 'get_type' )->andReturn( 'email' );
		$mock_field->shouldReceive( 'render' )->once()->andReturn( '<div>email field</div>' );
		$this->registry->register( $mock_field );

		$html = $this->renderer->render( 1, $this->get_config(), [ 'token' => 'abc123' ] );

		$this->assertStringContainsString( '<form class="shqf-form"', $html );
		$this->assertStringContainsString( '</form>', $html );
		$this->assertStringContainsString( 'data-form-id="1"', $html );
		$this->assertStringContainsString( 'novalidate', $html );
	}

	/**
	 * Render includes CSRF token hidden field.
	 */
	public function test_includes_csrf_token(): void {
		$mock_field = Mockery::mock( FieldInterface::class );
		$mock_field->shouldReceive( 'get_type' )->andReturn( 'email' );
		$mock_field->shouldReceive( 'render' )->andReturn( '' );
		$this->registry->register( $mock_field );

		$html = $this->renderer->render( 1, $this->get_config(), [ 'token' => 'secret-token-xyz' ] );

		$this->assertStringContainsString( 'name="shqf_token"', $html );
		$this->assertStringContainsString( 'value="secret-token-xyz"', $html );
	}

	/**
	 * Render includes honeypot field.
	 */
	public function test_includes_honeypot(): void {
		$mock_field = Mockery::mock( FieldInterface::class );
		$mock_field->shouldReceive( 'get_type' )->andReturn( 'email' );
		$mock_field->shouldReceive( 'render' )->andReturn( '' );
		$this->registry->register( $mock_field );

		$html = $this->renderer->render( 1, $this->get_config() );

		$this->assertStringContainsString( 'name="shqf_hp"', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( 'tabindex="-1"', $html );
		$this->assertStringContainsString( 'position:absolute;left:-9999px', $html );
	}

	/**
	 * Render includes submit button with configurable text.
	 */
	public function test_includes_submit_button(): void {
		$html = $this->renderer->render( 1, $this->get_config() );

		$this->assertStringContainsString( '<button type="submit"', $html );
		$this->assertStringContainsString( 'Submit Request', $html );
	}

	/**
	 * Render includes aria-live region for messages.
	 */
	public function test_includes_aria_live_region(): void {
		$html = $this->renderer->render( 1, $this->get_config() );

		$this->assertStringContainsString( 'aria-live="polite"', $html );
		$this->assertStringContainsString( 'shqf-form-messages', $html );
	}

	/**
	 * Render applies CSS custom properties from appearance.
	 */
	public function test_applies_css_custom_properties(): void {
		$mock_field = Mockery::mock( FieldInterface::class );
		$mock_field->shouldReceive( 'get_type' )->andReturn( 'email' );
		$mock_field->shouldReceive( 'render' )->andReturn( '' );
		$this->registry->register( $mock_field );

		$html = $this->renderer->render( 1, $this->get_config() );

		$this->assertStringContainsString( '--shqf-primary:#0F766E', $html );
		$this->assertStringContainsString( '--shqf-button-bg:#0F766E', $html );
		$this->assertStringContainsString( '--shqf-radius:8px', $html );
	}

	/**
	 * Render skips disabled fields.
	 */
	public function test_skips_disabled_fields(): void {
		$config = $this->get_config();
		$config['fields'][0]['enabled'] = false;

		$mock_field = Mockery::mock( FieldInterface::class );
		$mock_field->shouldReceive( 'get_type' )->andReturn( 'email' );
		$mock_field->shouldReceive( 'render' )->never();
		$this->registry->register( $mock_field );

		$this->renderer->render( 1, $config );
	}

	/**
	 * Render skips unknown field types gracefully.
	 */
	public function test_skips_unknown_field_types(): void {
		$config                     = $this->get_config();
		$config['fields'][0]['type'] = 'nonexistent';

		$html = $this->renderer->render( 1, $config );

		// Should still produce a valid form, just without the field.
		$this->assertStringContainsString( '<form', $html );
		$this->assertStringContainsString( '</form>', $html );
	}

	/**
	 * Render includes form_id hidden field.
	 */
	public function test_includes_form_id(): void {
		$html = $this->renderer->render( 42, $this->get_config() );

		$this->assertStringContainsString( 'name="shqf_form_id"', $html );
		$this->assertStringContainsString( 'value="42"', $html );
	}

	/**
	 * Render applies custom CSS.
	 */
	public function test_applies_custom_css(): void {
		$config = $this->get_config();
		$config['appearance']['custom_css'] = '.shqf-form { background: red; }';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( '<style>', $html );
		$this->assertStringContainsString( '.shqf-form { background: red; }', $html );
	}

	/**
	 * Custom CSS is stripped of HTML tags.
	 */
	public function test_custom_css_stripped_of_html(): void {
		$config = $this->get_config();
		$config['appearance']['custom_css'] = '</style><script>alert(1)</script><style>.x{}';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( '<script>', $html );
	}

	/**
	 * Custom CSS blocks expression() XSS vector.
	 */
	public function test_custom_css_blocks_expression(): void {
		$config = $this->get_config();
		$config['appearance']['custom_css'] = '.x { width: expression(alert(1)); }';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'expression(', $html );
		$this->assertStringContainsString( '/* blocked */', $html );
	}

	/**
	 * Custom CSS blocks javascript: in url().
	 */
	public function test_custom_css_blocks_javascript_url(): void {
		$config = $this->get_config();
		$config['appearance']['custom_css'] = '.x { background: url(javascript:alert(1)); }';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	/**
	 * Custom CSS blocks @import.
	 */
	public function test_custom_css_blocks_import(): void {
		$config = $this->get_config();
		$config['appearance']['custom_css'] = '@import url("https://evil.com/track.css"); .x{}';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( '@import', $html );
	}

	/**
	 * Custom CSS handles non-string values gracefully.
	 */
	public function test_custom_css_ignores_non_string(): void {
		$config = $this->get_config();
		$config['appearance']['custom_css'] = [ 'not', 'a', 'string' ];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( '<style>', $html );
	}

	// --- Multi-step form tests ---

	public function test_multistep_renders_progress_bar(): void {
		$config          = $this->get_config();
		$config['steps'] = [
			[ 'label' => 'Step 1' ],
			[ 'label' => 'Step 2' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-progress', $html );
		$this->assertStringContainsString( 'role="progressbar"', $html );
		$this->assertStringContainsString( 'aria-valuemax="2"', $html );
	}

	public function test_multistep_renders_step_labels(): void {
		$config          = $this->get_config();
		$config['steps'] = [
			[ 'label' => 'Contact Info' ],
			[ 'label' => 'Samples' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'Contact Info', $html );
		$this->assertStringContainsString( 'Samples', $html );
		$this->assertStringContainsString( 'shqf-step-label--active', $html );
		$this->assertStringContainsString( 'aria-current="step"', $html );
	}

	public function test_multistep_renders_step_divs(): void {
		$this->registry->register( new \SampleHQForm\Fields\EmailField() );

		$config = $this->get_config();
		$config['steps'] = [
			[ 'label' => 'Step 1' ],
			[ 'label' => 'Step 2' ],
		];
		$config['fields'] = [
			[ 'id' => 'f_1', 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'step_index' => 0 ],
			[ 'id' => 'f_2', 'key' => 'phone', 'type' => 'email', 'label' => 'Phone', 'step_index' => 1 ],
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'data-step="0"', $html );
		$this->assertStringContainsString( 'data-step="1"', $html );
	}

	public function test_multistep_renders_nav_buttons(): void {
		$config          = $this->get_config();
		$config['steps'] = [
			[ 'label' => 'Step 1' ],
			[ 'label' => 'Step 2' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-button--prev', $html );
		$this->assertStringContainsString( 'shqf-button--next', $html );
		$this->assertStringContainsString( 'shqf-button--submit', $html );
	}

	public function test_single_step_has_no_progress(): void {
		$config = $this->get_config();
		$html   = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'shqf-progress', $html );
		$this->assertStringNotContainsString( 'shqf-button--prev', $html );
		$this->assertStringNotContainsString( 'shqf-button--next', $html );
	}

	/**
	 * Extended custom properties are output when set in appearance config.
	 */
	public function test_applies_extended_custom_properties(): void {
		$config = $this->get_config();
		$config['appearance']['text_color']    = '#0D0D0D';
		$config['appearance']['muted_color']   = '#7A7A7A';
		$config['appearance']['border_color']  = '#E8E8E8';
		$config['appearance']['label_color']   = '#374151';
		$config['appearance']['input_bg']      = '#FFFFFF';
		$config['appearance']['surface_bg']    = '#FAFAFA';
		$config['appearance']['error_color']   = '#E42313';
		$config['appearance']['success_color'] = '#22C55E';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( '--shqf-text-color:#0D0D0D', $html );
		$this->assertStringContainsString( '--shqf-muted-color:#7A7A7A', $html );
		$this->assertStringContainsString( '--shqf-border-color:#E8E8E8', $html );
		$this->assertStringContainsString( '--shqf-label-color:#374151', $html );
		$this->assertStringContainsString( '--shqf-input-bg:#FFFFFF', $html );
		$this->assertStringContainsString( '--shqf-surface-bg:#FAFAFA', $html );
		$this->assertStringContainsString( '--shqf-error-color:#E42313', $html );
		$this->assertStringContainsString( '--shqf-success-color:#22C55E', $html );
	}

	/**
	 * Extended properties are omitted when not set (use CSS defaults).
	 */
	public function test_omits_extended_properties_when_unset(): void {
		$html = $this->renderer->render( 1, $this->get_config() );

		$this->assertStringNotContainsString( '--shqf-text-color', $html );
		$this->assertStringNotContainsString( '--shqf-muted-color', $html );
		$this->assertStringNotContainsString( '--shqf-border-color', $html );
		$this->assertStringNotContainsString( '--shqf-label-color', $html );
		$this->assertStringNotContainsString( '--shqf-input-bg', $html );
		$this->assertStringNotContainsString( '--shqf-surface-bg', $html );
		$this->assertStringNotContainsString( '--shqf-error-color', $html );
		$this->assertStringNotContainsString( '--shqf-success-color', $html );
	}

	/**
	 * Invalid hex colors are silently omitted (sanitize_hex_color returns null).
	 */
	public function test_invalid_hex_color_omitted(): void {
		$config = $this->get_config();
		$config['appearance']['text_color']   = 'not-a-color';
		$config['appearance']['primary_color'] = 'rgb(255,0,0)';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( '--shqf-text-color', $html );
		$this->assertStringNotContainsString( '--shqf-primary', $html );
	}

	// --- Layout mode tests ---

	public function test_wizard_layout_adds_modifier_class(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-form-wrapper--wizard', $html );
	}

	public function test_grid_layout_adds_modifier_class(): void {
		$config           = $this->get_config();
		$config['layout'] = 'grid';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-form-wrapper--grid', $html );
	}

	public function test_list_layout_adds_modifier_class(): void {
		$config           = $this->get_config();
		$config['layout'] = 'list';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-form-wrapper--list', $html );
	}

	public function test_no_layout_has_no_modifier_class(): void {
		$config = $this->get_config();
		// No layout key.

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'class="shqf-form-wrapper"', $html );
		$this->assertStringNotContainsString( 'shqf-form-wrapper--', $html );
	}

	public function test_invalid_layout_has_no_modifier_class(): void {
		$config           = $this->get_config();
		$config['layout'] = 'foobar';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'shqf-form-wrapper--foobar', $html );
	}

	// --- Title / subtitle tests ---

	public function test_renders_title_and_subtitle(): void {
		$config             = $this->get_config();
		$config['title']    = 'Request a Sample';
		$config['subtitle'] = 'Select the samples you want';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( '<h2 class="shqf-title">Request a Sample</h2>', $html );
		$this->assertStringContainsString( '<p class="shqf-subtitle">Select the samples you want</p>', $html );
		$this->assertStringContainsString( 'shqf-header', $html );
	}

	public function test_renders_title_only(): void {
		$config          = $this->get_config();
		$config['title'] = 'My Form';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-title', $html );
		$this->assertStringNotContainsString( 'shqf-subtitle', $html );
	}

	public function test_no_header_when_no_title_or_subtitle(): void {
		$config = $this->get_config();

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'shqf-header', $html );
		$this->assertStringNotContainsString( 'shqf-title', $html );
	}

	public function test_title_is_escaped(): void {
		$config          = $this->get_config();
		$config['title'] = '<script>alert(1)</script>';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	// --- Stepper tests (wizard layout) ---

	public function test_wizard_renders_stepper(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';
		$config['steps']  = [
			[ 'label' => 'Samples' ],
			[ 'label' => 'Your Info' ],
			[ 'label' => 'Review' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-stepper', $html );
		$this->assertStringContainsString( 'shqf-step-dot', $html );
		$this->assertStringContainsString( 'shqf-step-line', $html );
		$this->assertStringContainsString( 'Samples', $html );
		$this->assertStringContainsString( 'Your Info', $html );
		$this->assertStringContainsString( 'Review', $html );
	}

	public function test_wizard_stepper_first_step_active(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';
		$config['steps']  = [
			[ 'label' => 'Step A' ],
			[ 'label' => 'Step B' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-step-dot--active', $html );
		$this->assertStringContainsString( 'shqf-step-dot--pending', $html );
		$this->assertStringContainsString( 'shqf-stepper-step--active', $html );
		$this->assertStringContainsString( 'shqf-stepper-step--pending', $html );
	}

	public function test_wizard_stepper_has_correct_dot_numbers(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';
		$config['steps']  = [
			[ 'label' => 'A' ],
			[ 'label' => 'B' ],
			[ 'label' => 'C' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( '>1</span>', $html );
		$this->assertStringContainsString( '>2</span>', $html );
		$this->assertStringContainsString( '>3</span>', $html );
	}

	public function test_wizard_stepper_lines_between_dots(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';
		$config['steps']  = [
			[ 'label' => 'A' ],
			[ 'label' => 'B' ],
			[ 'label' => 'C' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		// 3 steps should produce exactly 2 lines.
		$this->assertSame( 2, substr_count( $html, 'shqf-step-line' ) );
	}

	public function test_non_wizard_multistep_uses_old_steps_nav(): void {
		$config          = $this->get_config();
		$config['steps'] = [
			[ 'label' => 'Step 1' ],
			[ 'label' => 'Step 2' ],
		];
		$config['fields'][0]['step_index'] = 0;
		// No layout key -- defaults to old behavior.

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-steps-nav', $html );
		$this->assertStringContainsString( 'shqf-progress', $html );
		$this->assertStringNotContainsString( 'shqf-stepper', $html );
		// Should NOT use tablist/tab roles (incorrect ARIA pattern).
		$this->assertStringNotContainsString( 'role="tablist"', $html );
		$this->assertStringNotContainsString( 'role="tab"', $html );
	}

	public function test_wizard_stepper_has_nav_landmark(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';
		$config['steps']  = [
			[ 'label' => 'A' ],
			[ 'label' => 'B' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( '<nav class="shqf-stepper"', $html );
		$this->assertStringContainsString( 'aria-label=', $html );
	}

	public function test_wizard_stepper_has_aria_current(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';
		$config['steps']  = [
			[ 'label' => 'A' ],
			[ 'label' => 'B' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'aria-current="step"', $html );
	}

	public function test_wizard_stepper_has_sr_only_step_position(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';
		$config['steps']  = [
			[ 'label' => 'Samples' ],
			[ 'label' => 'Info' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-sr-only', $html );
		$this->assertStringContainsString( 'Step 1 of 2:', $html );
		$this->assertStringContainsString( 'Step 2 of 2:', $html );
	}

	// --- Edge case tests ---

	public function test_subtitle_only_renders_header(): void {
		$config             = $this->get_config();
		$config['subtitle'] = 'Choose your samples';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-header', $html );
		$this->assertStringContainsString( 'shqf-subtitle', $html );
		$this->assertStringNotContainsString( 'shqf-title', $html );
	}

	public function test_wizard_with_single_step_no_stepper(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';
		$config['steps']  = [
			[ 'label' => 'Only Step' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		// Single step = not multi-step, so no stepper rendered.
		$this->assertStringNotContainsString( 'shqf-stepper', $html );
		// But layout class still applies.
		$this->assertStringContainsString( 'shqf-form-wrapper--wizard', $html );
	}

	public function test_wizard_with_no_steps_key(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';
		// No steps key at all.

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'shqf-stepper', $html );
		$this->assertStringContainsString( 'shqf-form-wrapper--wizard', $html );
	}

	// --- Structural components (divider, selection bar, bottom bar) ---

	public function test_grid_layout_has_divider(): void {
		$config           = $this->get_config();
		$config['layout'] = 'grid';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-divider', $html );
		$this->assertStringContainsString( 'role="separator"', $html );
		$this->assertStringContainsString( 'Your Details', $html );
	}

	public function test_list_layout_has_divider(): void {
		$config           = $this->get_config();
		$config['layout'] = 'list';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-divider', $html );
	}

	public function test_wizard_layout_no_divider(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'shqf-divider', $html );
	}

	public function test_no_layout_no_divider(): void {
		$config = $this->get_config();

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'shqf-divider', $html );
	}

	public function test_custom_divider_text(): void {
		$config                 = $this->get_config();
		$config['layout']       = 'grid';
		$config['divider_text'] = 'Contact Information';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'Contact Information', $html );
	}

	public function test_divider_text_is_escaped(): void {
		$config                 = $this->get_config();
		$config['layout']       = 'grid';
		$config['divider_text'] = '<script>alert(1)</script>';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_grid_layout_has_selection_bar(): void {
		$config           = $this->get_config();
		$config['layout'] = 'grid';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-selection-bar', $html );
		$this->assertStringContainsString( 'shqf-selection-bar__count', $html );
		$this->assertStringContainsString( 'shqf-selection-bar__clear', $html );
	}

	public function test_wizard_layout_has_selection_bar(): void {
		$config           = $this->get_config();
		$config['layout'] = 'wizard';

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-selection-bar', $html );
	}

	public function test_no_layout_no_selection_bar(): void {
		$config = $this->get_config();

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'shqf-selection-bar', $html );
	}

	public function test_bottom_bar_wraps_buttons(): void {
		$config = $this->get_config();

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-bottom-bar', $html );
		$this->assertStringContainsString( 'shqf-button--submit', $html );
	}

	public function test_multistep_bottom_bar_has_all_buttons(): void {
		$config          = $this->get_config();
		$config['steps'] = [
			[ 'label' => 'Step 1' ],
			[ 'label' => 'Step 2' ],
		];
		$config['fields'][0]['step_index'] = 0;

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-bottom-bar', $html );
		$this->assertStringContainsString( 'shqf-button--prev', $html );
		$this->assertStringContainsString( 'shqf-button--next', $html );
		$this->assertStringContainsString( 'shqf-button--submit', $html );
	}

	// -------------------------------------------------------------------------
	// Row group rendering
	// -------------------------------------------------------------------------

	public function test_row_renders_grid_container_with_columns(): void {
		$text = Mockery::mock( FieldInterface::class );
		$text->shouldReceive( 'get_type' )->andReturn( 'text' );
		$text->shouldReceive( 'render' )->andReturn( '<div class="shqf-field">field</div>' );
		$this->registry->register( $text );

		$config           = $this->get_config();
		$config['fields'] = [
			[
				'id'      => 'row_1',
				'type'    => 'row',
				'columns' => [
					[
						'width'  => '1fr',
						'fields' => [
							[ 'id' => 'f_fn', 'key' => 'first_name', 'type' => 'text' ],
						],
					],
					[
						'width'  => '1fr',
						'fields' => [
							[ 'id' => 'f_ln', 'key' => 'last_name', 'type' => 'text' ],
						],
					],
				],
			],
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'class="shqf-field-row"', $html );
		$this->assertStringContainsString( 'grid-template-columns:1fr 1fr', $html );
		$this->assertSame( 2, substr_count( $html, 'shqf-field-row__col' ) );
		$this->assertSame( 2, substr_count( $html, '<div class="shqf-field">field</div>' ) );
	}

	public function test_row_renders_asymmetric_column_widths(): void {
		$text = Mockery::mock( FieldInterface::class );
		$text->shouldReceive( 'get_type' )->andReturn( 'text' );
		$text->shouldReceive( 'render' )->andReturn( '<div>field</div>' );
		$this->registry->register( $text );

		$config           = $this->get_config();
		$config['fields'] = [
			[
				'id'      => 'row_1',
				'type'    => 'row',
				'columns' => [
					[
						'width'  => '1fr',
						'fields' => [ [ 'id' => 'f_1', 'key' => 'a', 'type' => 'text' ] ],
					],
					[
						'width'  => '2fr',
						'fields' => [ [ 'id' => 'f_2', 'key' => 'b', 'type' => 'text' ] ],
					],
				],
			],
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'grid-template-columns:1fr 2fr', $html );
	}

	public function test_row_with_empty_columns_renders_nothing(): void {
		$config           = $this->get_config();
		$config['fields'] = [
			[
				'id'      => 'row_1',
				'type'    => 'row',
				'columns' => [],
			],
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'shqf-field-row', $html );
	}

	public function test_row_skips_disabled_child_fields(): void {
		$text = Mockery::mock( FieldInterface::class );
		$text->shouldReceive( 'get_type' )->andReturn( 'text' );
		$text->shouldReceive( 'render' )->once()->andReturn( '<div>visible</div>' );
		$this->registry->register( $text );

		$config           = $this->get_config();
		$config['fields'] = [
			[
				'id'      => 'row_1',
				'type'    => 'row',
				'columns' => [
					[
						'width'  => '1fr',
						'fields' => [
							[ 'id' => 'f_1', 'key' => 'a', 'type' => 'text' ],
						],
					],
					[
						'width'  => '1fr',
						'fields' => [
							[ 'id' => 'f_2', 'key' => 'b', 'type' => 'text', 'enabled' => false ],
						],
					],
				],
			],
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( '<div>visible</div>', $html );
	}

	public function test_row_renders_inside_multistep(): void {
		$text = Mockery::mock( FieldInterface::class );
		$text->shouldReceive( 'get_type' )->andReturn( 'text' );
		$text->shouldReceive( 'render' )->andReturn( '<div>field</div>' );
		$this->registry->register( $text );

		$config = $this->get_config();
		$config['steps']  = [
			[ 'label' => 'Step 1' ],
			[ 'label' => 'Step 2' ],
		];
		$config['fields'] = [
			[
				'id'         => 'row_1',
				'type'       => 'row',
				'step_index' => 0,
				'columns'    => [
					[
						'width'  => '1fr',
						'fields' => [
							[ 'id' => 'f_1', 'key' => 'first', 'type' => 'text' ],
						],
					],
					[
						'width'  => '1fr',
						'fields' => [
							[ 'id' => 'f_2', 'key' => 'last', 'type' => 'text' ],
						],
					],
				],
			],
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-field-row', $html );
		$this->assertStringContainsString( 'shqf-step', $html );
	}

	public function test_row_mixed_with_regular_fields(): void {
		$text = Mockery::mock( FieldInterface::class );
		$text->shouldReceive( 'get_type' )->andReturn( 'text' );
		$text->shouldReceive( 'render' )->andReturn( '<div>text</div>' );

		$email = Mockery::mock( FieldInterface::class );
		$email->shouldReceive( 'get_type' )->andReturn( 'email' );
		$email->shouldReceive( 'render' )->once()->andReturn( '<div>email</div>' );

		$this->registry->register( $text );
		$this->registry->register( $email );

		$config           = $this->get_config();
		$config['fields'] = [
			[
				'id'      => 'row_1',
				'type'    => 'row',
				'columns' => [
					[
						'width'  => '1fr',
						'fields' => [
							[ 'id' => 'f_1', 'key' => 'first', 'type' => 'text' ],
						],
					],
					[
						'width'  => '1fr',
						'fields' => [
							[ 'id' => 'f_2', 'key' => 'last', 'type' => 'text' ],
						],
					],
				],
			],
			[ 'id' => 'f_3', 'key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true ],
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'shqf-field-row', $html );
		$this->assertStringContainsString( '<div>email</div>', $html );
	}

	public function test_row_column_width_defaults_to_1fr(): void {
		$text = Mockery::mock( FieldInterface::class );
		$text->shouldReceive( 'get_type' )->andReturn( 'text' );
		$text->shouldReceive( 'render' )->andReturn( '<div>f</div>' );
		$this->registry->register( $text );

		$config           = $this->get_config();
		$config['fields'] = [
			[
				'id'      => 'row_1',
				'type'    => 'row',
				'columns' => [
					[ 'fields' => [ [ 'id' => 'f_1', 'key' => 'a', 'type' => 'text' ] ] ],
					[ 'fields' => [ [ 'id' => 'f_2', 'key' => 'b', 'type' => 'text' ] ] ],
				],
			],
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'grid-template-columns:1fr 1fr', $html );
	}

	public function test_row_three_columns(): void {
		$text = Mockery::mock( FieldInterface::class );
		$text->shouldReceive( 'get_type' )->andReturn( 'text' );
		$text->shouldReceive( 'render' )->andReturn( '<div>f</div>' );
		$this->registry->register( $text );

		$config           = $this->get_config();
		$config['fields'] = [
			[
				'id'      => 'row_1',
				'type'    => 'row',
				'columns' => [
					[ 'width' => '1fr', 'fields' => [ [ 'id' => 'f_1', 'key' => 'a', 'type' => 'text' ] ] ],
					[ 'width' => '1fr', 'fields' => [ [ 'id' => 'f_2', 'key' => 'b', 'type' => 'text' ] ] ],
					[ 'width' => '1fr', 'fields' => [ [ 'id' => 'f_3', 'key' => 'c', 'type' => 'text' ] ] ],
				],
			],
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'grid-template-columns:1fr 1fr 1fr', $html );
		$this->assertSame( 3, substr_count( $html, 'shqf-field-row__col' ) );
	}

	public function test_row_invalid_width_falls_back_to_1fr(): void {
		$text = Mockery::mock( FieldInterface::class );
		$text->shouldReceive( 'get_type' )->andReturn( 'text' );
		$text->shouldReceive( 'render' )->andReturn( '<div>f</div>' );
		$this->registry->register( $text );

		$config           = $this->get_config();
		$config['fields'] = [
			[
				'id'      => 'row_1',
				'type'    => 'row',
				'columns' => [
					[ 'width' => '1fr;display:none;--x:', 'fields' => [ [ 'id' => 'f_1', 'key' => 'a', 'type' => 'text' ] ] ],
					[ 'width' => '1fr', 'fields' => [ [ 'id' => 'f_2', 'key' => 'b', 'type' => 'text' ] ] ],
				],
			],
		];

		$html = $this->renderer->render( 1, $config );

		// Malicious width should be replaced with default 1fr.
		$this->assertStringContainsString( 'grid-template-columns:1fr 1fr', $html );
	}

	// -------------------------------------------------------------------------
	// Redirect URL + Analytics data attributes
	// -------------------------------------------------------------------------

	public function test_redirect_url_data_attribute_when_configured(): void {
		$config             = $this->get_config();
		$config['behavior'] = [
			'success_type' => 'redirect',
			'redirect_url' => 'https://example.com/thank-you',
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'data-redirect-url="https://example.com/thank-you"', $html );
	}

	public function test_no_redirect_url_when_success_type_is_message(): void {
		$config             = $this->get_config();
		$config['behavior'] = [
			'success_type' => 'message',
			'redirect_url' => 'https://example.com/thank-you',
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'data-redirect-url', $html );
	}

	public function test_no_redirect_url_when_url_is_empty(): void {
		$config             = $this->get_config();
		$config['behavior'] = [
			'success_type' => 'redirect',
			'redirect_url' => '',
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'data-redirect-url', $html );
	}

	public function test_analytics_data_attribute_when_enabled(): void {
		$config             = $this->get_config();
		$config['title']    = 'My Form';
		$config['behavior'] = [
			'analytics_events' => true,
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'data-analytics="1"', $html );
		$this->assertStringContainsString( 'data-form-title="My Form"', $html );
	}

	public function test_no_analytics_attribute_when_disabled(): void {
		$config             = $this->get_config();
		$config['behavior'] = [
			'analytics_events' => false,
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringNotContainsString( 'data-analytics', $html );
	}

	public function test_redirect_and_analytics_coexist(): void {
		$config             = $this->get_config();
		$config['title']    = 'Combo Form';
		$config['behavior'] = [
			'success_type'     => 'redirect',
			'redirect_url'     => 'https://example.com/thanks',
			'analytics_events' => true,
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'data-redirect-url="https://example.com/thanks"', $html );
		$this->assertStringContainsString( 'data-analytics="1"', $html );
		$this->assertStringContainsString( 'data-form-title="Combo Form"', $html );
	}

	public function test_redirect_url_preserves_query_params(): void {
		$config             = $this->get_config();
		$config['behavior'] = [
			'success_type' => 'redirect',
			'redirect_url' => 'https://example.com/thanks?form=123&utm=test',
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'data-redirect-url=', $html );
		// esc_url encodes & as &amp; in attributes; verify the URL is present.
		$this->assertStringContainsString( 'form=123', $html );
		$this->assertStringContainsString( 'utm=test', $html );
	}

	public function test_redirect_url_strips_javascript_protocol(): void {
		$config             = $this->get_config();
		$config['behavior'] = [
			'success_type' => 'redirect',
			'redirect_url' => 'javascript:alert(1)',
		];

		$html = $this->renderer->render( 1, $config );

		// esc_url strips javascript: protocol -> outputs empty data attribute.
		// JS side: empty string is falsy, so no redirect fires. Safe.
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringContainsString( 'data-redirect-url=""', $html );
	}

	public function test_analytics_title_with_special_chars_escaped(): void {
		$config             = $this->get_config();
		$config['title']    = '"Test" <Form> & \'Quotes\'';
		$config['behavior'] = [
			'analytics_events' => true,
		];

		$html = $this->renderer->render( 1, $config );

		$this->assertStringContainsString( 'data-analytics="1"', $html );
		// esc_attr escapes quotes and angle brackets.
		$this->assertStringNotContainsString( '<Form>', $html );
		$this->assertStringContainsString( 'data-form-title=', $html );
	}
}
