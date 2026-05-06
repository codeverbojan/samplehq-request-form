<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for choice field types: Select, Radio, Checkbox.
 *
 * @package SampleHQForm\Tests\Unit\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Fields;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Fields\SelectField;
use SampleHQForm\Fields\RadioField;
use SampleHQForm\Fields\CheckboxField;

/**
 * Choice field types unit tests.
 */
class ChoiceFieldsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'esc_attr'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_html'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'selected'            => static function ( $s1, $s2, $echo = true ) {
				return (string) $s1 === (string) $s2 ? ' selected="selected"' : '';
			},
			'checked'             => static function ( $s1, $s2, $echo = true ) {
				return (string) $s1 === (string) $s2 ? ' checked="checked"' : '';
			},
			'__'                  => static fn( $s ) => $s,
		] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Get a standard set of options for testing.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_test_options(): array {
		return [
			[ 'label' => 'Option A', 'value' => 'a' ],
			[ 'label' => 'Option B', 'value' => 'b' ],
			[ 'label' => 'Option C', 'value' => 'c' ],
		];
	}

	// --- SelectField ---

	/**
	 * SelectField renders <select> with options.
	 */
	public function test_select_renders(): void {
		$field = new SelectField();
		$html  = $field->render(
			[
				'id'      => 'f_1',
				'key'     => 'industry',
				'label'   => 'Industry',
				'options' => $this->get_test_options(),
			],
			'b'
		);

		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( '</select>', $html );
		$this->assertStringContainsString( '<option value="a">', $html );
		$this->assertStringContainsString( 'selected="selected"', $html );
		$this->assertStringContainsString( '<label for=', $html );
	}

	/**
	 * SelectField renders placeholder option.
	 */
	public function test_select_has_placeholder(): void {
		$field = new SelectField();
		$html  = $field->render(
			[ 'id' => 'f_1', 'key' => 'x', 'label' => 'X', 'options' => [] ],
			''
		);

		$this->assertStringContainsString( '<option value="">Select an option</option>', $html );
	}

	/**
	 * SelectField validates against allowed values.
	 */
	public function test_select_validates_options(): void {
		$field  = new SelectField();
		$config = [ 'label' => 'Pick', 'options' => $this->get_test_options() ];

		$this->assertNull( $field->validate( 'a', $config ) );
		$this->assertNotNull( $field->validate( 'invalid', $config ) );
	}

	/**
	 * SelectField allows empty when not required.
	 */
	public function test_select_optional_empty(): void {
		$field = new SelectField();
		$this->assertNull( $field->validate( '', [ 'label' => 'X', 'options' => $this->get_test_options() ] ) );
	}

	// --- RadioField ---

	/**
	 * RadioField renders fieldset/legend with radio inputs.
	 */
	public function test_radio_renders_fieldset(): void {
		$field = new RadioField();
		$html  = $field->render(
			[
				'id'       => 'f_2',
				'key'      => 'size',
				'label'    => 'Size',
				'options'  => $this->get_test_options(),
				'required' => true,
			],
			'a'
		);

		$this->assertStringContainsString( '<fieldset', $html );
		$this->assertStringContainsString( '<legend', $html );
		$this->assertStringContainsString( 'Size', $html );
		$this->assertStringContainsString( '<input type="radio"', $html );
		$this->assertStringContainsString( 'checked="checked"', $html );
		$this->assertStringContainsString( 'aria-required="true"', $html );
		$this->assertStringContainsString( 'shqf-required', $html );
	}

	/**
	 * RadioField each option has its own label.
	 */
	public function test_radio_options_have_labels(): void {
		$field = new RadioField();
		$html  = $field->render(
			[ 'id' => 'f_2', 'key' => 'x', 'label' => 'X', 'options' => $this->get_test_options() ],
			''
		);

		$this->assertStringContainsString( 'Option A', $html );
		$this->assertStringContainsString( 'Option B', $html );
		$this->assertStringContainsString( 'Option C', $html );
		// Each option should have a unique ID.
		$this->assertStringContainsString( 'shqf-f_2-0', $html );
		$this->assertStringContainsString( 'shqf-f_2-1', $html );
	}

	/**
	 * RadioField validates allowed values.
	 */
	public function test_radio_validates(): void {
		$field  = new RadioField();
		$config = [ 'label' => 'X', 'options' => $this->get_test_options() ];

		$this->assertNull( $field->validate( 'b', $config ) );
		$this->assertNotNull( $field->validate( 'z', $config ) );
	}

	// --- CheckboxField ---

	/**
	 * CheckboxField renders fieldset/legend with checkbox inputs.
	 */
	public function test_checkbox_renders_fieldset(): void {
		$field = new CheckboxField();
		$html  = $field->render(
			[
				'id'      => 'f_3',
				'key'     => 'interests',
				'label'   => 'Interests',
				'options' => $this->get_test_options(),
			],
			[ 'a', 'c' ]
		);

		$this->assertStringContainsString( '<fieldset', $html );
		$this->assertStringContainsString( '<legend', $html );
		$this->assertStringContainsString( '<input type="checkbox"', $html );
		$this->assertStringContainsString( 'name="shqf_fields[interests][]"', $html );
		// 'a' and 'c' should be checked.
		$this->assertSame( 2, substr_count( $html, 'checked' ) );
	}

	/**
	 * CheckboxField validates all values against options.
	 */
	public function test_checkbox_validates_all_values(): void {
		$field  = new CheckboxField();
		$config = [ 'label' => 'X', 'options' => $this->get_test_options() ];

		$this->assertNull( $field->validate( [ 'a', 'b' ], $config ) );
		$this->assertNotNull( $field->validate( [ 'a', 'invalid' ], $config ) );
	}

	/**
	 * CheckboxField required validation.
	 */
	public function test_checkbox_required(): void {
		$field  = new CheckboxField();
		$config = [ 'label' => 'X', 'options' => $this->get_test_options(), 'required' => true ];

		$this->assertNotNull( $field->validate( [], $config ) );
		$this->assertNull( $field->validate( [ 'a' ], $config ) );
	}

	/**
	 * CheckboxField sanitizes each value.
	 */
	public function test_checkbox_sanitizes_array(): void {
		$field  = new CheckboxField();
		$result = $field->sanitize( [ '<b>a</b>', 'b' ], [] );

		$this->assertSame( [ 'a', 'b' ], $result );
	}

	/**
	 * CheckboxField handles string value (single selection).
	 */
	public function test_checkbox_handles_string_value(): void {
		$field = new CheckboxField();
		$this->assertSame( [ 'a' ], $field->sanitize( 'a', [] ) );
	}

	// --- Choice layout CSS classes ---

	public function test_radio_horizontal_layout_class(): void {
		$field  = new RadioField();
		$config = [
			'id'      => 'f_1',
			'key'     => 'color',
			'label'   => 'Color',
			'options' => [ [ 'label' => 'Red', 'value' => 'red' ] ],
			'config'  => [ 'choice_layout' => 'horizontal' ],
		];

		$html = $field->render( $config, '' );
		$this->assertStringContainsString( 'shqf-choices--horizontal', $html );
	}

	public function test_radio_columns_class(): void {
		$field  = new RadioField();
		$config = [
			'id'      => 'f_1',
			'key'     => 'color',
			'label'   => 'Color',
			'options' => [ [ 'label' => 'Red', 'value' => 'red' ] ],
			'config'  => [ 'choice_columns' => 2 ],
		];

		$html = $field->render( $config, '' );
		$this->assertStringContainsString( 'shqf-choices--cols-2', $html );
	}

	public function test_checkbox_horizontal_layout_class(): void {
		$field  = new CheckboxField();
		$config = [
			'id'      => 'f_1',
			'key'     => 'colors',
			'label'   => 'Colors',
			'options' => [ [ 'label' => 'Red', 'value' => 'red' ] ],
			'config'  => [ 'choice_layout' => 'horizontal' ],
		];

		$html = $field->render( $config, [] );
		$this->assertStringContainsString( 'shqf-choices--horizontal', $html );
	}

	public function test_radio_default_no_layout_class(): void {
		$field  = new RadioField();
		$config = [
			'id'      => 'f_1',
			'key'     => 'color',
			'label'   => 'Color',
			'options' => [ [ 'label' => 'Red', 'value' => 'red' ] ],
		];

		$html = $field->render( $config, '' );
		$this->assertStringNotContainsString( 'shqf-choices--horizontal', $html );
		$this->assertStringNotContainsString( 'shqf-choices--cols', $html );
	}
}
