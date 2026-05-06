<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for core field types: Text, Email, Phone, Textarea, Number.
 *
 * @package SampleHQForm\Tests\Unit\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Fields;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Fields\TextField;
use SampleHQForm\Fields\EmailField;
use SampleHQForm\Fields\PhoneField;
use SampleHQForm\Fields\TextareaField;
use SampleHQForm\Fields\NumberField;

/**
 * Core field types unit tests.
 */
class CoreFieldsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'esc_attr'                => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_html'                => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_textarea'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'sanitize_text_field'     => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'sanitize_email'          => static fn( $s ) => (string) $s,
			'sanitize_textarea_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'is_email'                => static fn( $s ) => (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ),
			'__'                      => static fn( $s ) => $s,
		] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// --- TextField ---

	/**
	 * TextField renders correct HTML structure.
	 */
	public function test_text_renders_input(): void {
		$field  = new TextField();
		$config = [ 'id' => 'f_1', 'key' => 'first_name', 'label' => 'First Name', 'required' => true ];
		$html   = $field->render( $config, 'John' );

		$this->assertStringContainsString( '<input type="text"', $html );
		$this->assertStringContainsString( 'value="John"', $html );
		$this->assertStringContainsString( '<label for=', $html );
		$this->assertStringContainsString( 'First Name', $html );
		$this->assertStringContainsString( 'required', $html );
		$this->assertStringContainsString( 'aria-required="true"', $html );
		$this->assertStringContainsString( 'aria-describedby=', $html );
		$this->assertStringContainsString( 'shqf-field--text', $html );
	}

	/**
	 * TextField validates required.
	 */
	public function test_text_validates_required(): void {
		$field = new TextField();
		$this->assertNotNull( $field->validate( '', [ 'label' => 'Name', 'required' => true ] ) );
		$this->assertNull( $field->validate( 'John', [ 'label' => 'Name', 'required' => true ] ) );
		$this->assertNull( $field->validate( '', [ 'label' => 'Name', 'required' => false ] ) );
	}

	/**
	 * TextField sanitizes XSS.
	 */
	public function test_text_sanitizes_xss(): void {
		$field  = new TextField();
		$result = $field->sanitize( '<script>alert(1)</script>Hello', [] );
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringContainsString( 'Hello', $result );
	}

	/**
	 * TextField type identifier.
	 */
	public function test_text_type(): void {
		$this->assertSame( 'text', ( new TextField() )->get_type() );
	}

	// --- EmailField ---

	/**
	 * EmailField renders with autocomplete="email".
	 */
	public function test_email_renders_with_autocomplete(): void {
		$field  = new EmailField();
		$config = [ 'id' => 'f_2', 'key' => 'email', 'label' => 'Email' ];
		$html   = $field->render( $config, '' );

		$this->assertStringContainsString( '<input type="email"', $html );
		$this->assertStringContainsString( 'autocomplete="email"', $html );
	}

	/**
	 * EmailField validates email format.
	 */
	public function test_email_validates_format(): void {
		$field  = new EmailField();
		$config = [ 'label' => 'Email', 'required' => true ];

		$this->assertNull( $field->validate( 'john@example.com', $config ) );
		$this->assertNotNull( $field->validate( 'not-an-email', $config ) );
		$this->assertNotNull( $field->validate( '', $config ) );
	}

	/**
	 * EmailField allows empty when not required.
	 */
	public function test_email_allows_empty_optional(): void {
		$field = new EmailField();
		$this->assertNull( $field->validate( '', [ 'label' => 'Email', 'required' => false ] ) );
	}

	/**
	 * EmailField does not validate format on empty optional.
	 */
	public function test_email_skips_format_check_on_empty(): void {
		$field = new EmailField();
		$this->assertNull( $field->validate( '', [ 'label' => 'Email' ] ) );
	}

	// --- PhoneField ---

	/**
	 * PhoneField renders with autocomplete="tel".
	 */
	public function test_phone_renders_with_autocomplete(): void {
		$field = new PhoneField();
		$html  = $field->render( [ 'id' => 'f_3', 'key' => 'phone', 'label' => 'Phone' ], '' );

		$this->assertStringContainsString( '<input type="tel"', $html );
		$this->assertStringContainsString( 'autocomplete="tel"', $html );
	}

	/**
	 * PhoneField type identifier.
	 */
	public function test_phone_type(): void {
		$this->assertSame( 'phone', ( new PhoneField() )->get_type() );
	}

	// --- TextareaField ---

	/**
	 * TextareaField renders textarea with rows.
	 */
	public function test_textarea_renders(): void {
		$field = new TextareaField();
		$html  = $field->render( [ 'id' => 'f_4', 'key' => 'message', 'label' => 'Message' ], 'Hello world' );

		$this->assertStringContainsString( '<textarea', $html );
		$this->assertStringContainsString( 'rows="5"', $html );
		$this->assertStringContainsString( 'Hello world', $html );
		$this->assertStringContainsString( '</textarea>', $html );
	}

	/**
	 * TextareaField respects custom rows.
	 */
	public function test_textarea_custom_rows(): void {
		$field = new TextareaField();
		$html  = $field->render(
			[ 'id' => 'f_4', 'key' => 'msg', 'label' => 'Msg', 'validation' => [ 'rows' => 10 ] ],
			''
		);

		$this->assertStringContainsString( 'rows="10"', $html );
	}

	/**
	 * TextareaField sanitizes.
	 */
	public function test_textarea_sanitize(): void {
		$field = new TextareaField();
		$this->assertSame( 'clean text', $field->sanitize( '<b>clean text</b>', [] ) );
	}

	// --- NumberField ---

	/**
	 * NumberField renders with min/max/step attributes.
	 */
	public function test_number_renders_with_constraints(): void {
		$field  = new NumberField();
		$config = [
			'id'         => 'f_5',
			'key'        => 'quantity',
			'label'      => 'Quantity',
			'validation' => [ 'min' => 1, 'max' => 100, 'step' => 1 ],
		];
		$html   = $field->render( $config, '5' );

		$this->assertStringContainsString( '<input type="number"', $html );
		$this->assertStringContainsString( 'min="1"', $html );
		$this->assertStringContainsString( 'max="100"', $html );
		$this->assertStringContainsString( 'step="1"', $html );
		$this->assertStringContainsString( 'value="5"', $html );
	}

	/**
	 * NumberField validates non-numeric input.
	 */
	public function test_number_validates_numeric(): void {
		$field = new NumberField();
		$this->assertNotNull( $field->validate( 'abc', [ 'label' => 'Qty', 'required' => true ] ) );
		$this->assertNull( $field->validate( '42', [ 'label' => 'Qty', 'required' => true ] ) );
		$this->assertNull( $field->validate( '3.14', [ 'label' => 'Qty' ] ) );
	}

	/**
	 * NumberField validates min/max constraints.
	 */
	public function test_number_validates_min_max(): void {
		$field  = new NumberField();
		$config = [ 'label' => 'Qty', 'validation' => [ 'min' => 1, 'max' => 10 ] ];

		$this->assertNull( $field->validate( '5', $config ) );
		$this->assertNotNull( $field->validate( '0', $config ) );
		$this->assertNotNull( $field->validate( '11', $config ) );
	}

	/**
	 * NumberField sanitizes non-numeric to empty string.
	 */
	public function test_number_sanitize_non_numeric(): void {
		$field = new NumberField();
		$this->assertSame( '42', $field->sanitize( '42', [] ) );
		$this->assertSame( '', $field->sanitize( 'abc', [] ) );
	}

	// --- Array input rejection ---

	/**
	 * All scalar fields reject array input.
	 */
	public function test_scalar_fields_reject_array_input(): void {
		$config = [ 'label' => 'Test', 'required' => false ];

		$this->assertNotNull( ( new TextField() )->validate( [ 'a', 'b' ], $config ) );
		$this->assertNotNull( ( new EmailField() )->validate( [ 'a' ], $config ) );
		$this->assertNotNull( ( new PhoneField() )->validate( [ '123' ], $config ) );
		$this->assertNotNull( ( new TextareaField() )->validate( [ 'x' ], $config ) );
		$this->assertNotNull( ( new NumberField() )->validate( [ '5' ], $config ) );
	}

	// --- Shared rendering tests ---

	/**
	 * Half-width fields get the correct CSS class.
	 */
	public function test_half_width_class(): void {
		$field = new TextField();
		$html  = $field->render( [ 'id' => 'f_1', 'key' => 'x', 'label' => 'X', 'width' => 'half' ], '' );
		$this->assertStringContainsString( 'shqf-field--half', $html );
	}

	/**
	 * Full-width fields get the correct CSS class.
	 */
	public function test_full_width_class(): void {
		$field = new TextField();
		$html  = $field->render( [ 'id' => 'f_1', 'key' => 'x', 'label' => 'X', 'width' => 'full' ], '' );
		$this->assertStringContainsString( 'shqf-field--full', $html );
	}

	/**
	 * Error message renders when present in context.
	 */
	public function test_error_renders_when_present(): void {
		$field   = new TextField();
		$context = [ 'errors' => [ 'f_1' => 'This field is required.' ] ];
		$html    = $field->render( [ 'id' => 'f_1', 'key' => 'name', 'label' => 'Name' ], '', $context );

		$this->assertStringContainsString( 'This field is required.', $html );
		$this->assertStringContainsString( 'role="alert"', $html );
	}

	/**
	 * Error container is hidden when no error and lacks role="alert".
	 */
	public function test_error_hidden_when_empty(): void {
		$field = new TextField();
		$html  = $field->render( [ 'id' => 'f_1', 'key' => 'name', 'label' => 'Name' ], '' );

		$this->assertStringContainsString( 'style="display:none"', $html );
		$this->assertStringNotContainsString( 'role="alert"', $html );
	}

	/**
	 * Required indicator renders for required fields.
	 */
	public function test_required_indicator(): void {
		$field = new TextField();
		$html  = $field->render( [ 'id' => 'f_1', 'key' => 'x', 'label' => 'X', 'required' => true ], '' );
		$this->assertStringContainsString( 'shqf-required', $html );
		$this->assertStringContainsString( '*', $html );
	}

	/**
	 * No required indicator for optional fields.
	 */
	public function test_no_required_indicator_when_optional(): void {
		$field = new TextField();
		$html  = $field->render( [ 'id' => 'f_1', 'key' => 'x', 'label' => 'X' ], '' );
		$this->assertStringNotContainsString( 'shqf-required', $html );
	}

	// --- Conditional logic data attributes ---

	public function test_conditions_data_attribute_rendered(): void {
		Monkey\Functions\stubs( [
			'wp_json_encode' => static fn( $d ) => json_encode( $d ),
		] );

		$field  = new TextField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'industry',
			'label'      => 'Industry',
			'conditions' => [
				'logic' => 'all',
				'rules' => [
					[ 'field_key' => 'country', 'operator' => 'equals', 'value' => 'US' ],
				],
			],
		];

		$html = $field->render( $config, '' );

		$this->assertStringContainsString( 'data-conditions=', $html );
		$this->assertStringContainsString( 'country', $html );
		$this->assertStringContainsString( 'equals', $html );
	}

	public function test_no_conditions_attribute_when_absent(): void {
		$field = new TextField();
		$html  = $field->render( [ 'id' => 'f_1', 'key' => 'x', 'label' => 'X' ], '' );

		$this->assertStringNotContainsString( 'data-conditions', $html );
	}

	public function test_no_conditions_attribute_when_empty_rules(): void {
		$field  = new TextField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'x',
			'label'      => 'X',
			'conditions' => [ 'logic' => 'all', 'rules' => [] ],
		];

		$html = $field->render( $config, '' );

		$this->assertStringNotContainsString( 'data-conditions', $html );
	}

	public function test_field_key_data_attribute_rendered(): void {
		$field = new TextField();
		$html  = $field->render( [ 'id' => 'f_1', 'key' => 'company', 'label' => 'Company' ], '' );

		$this->assertStringContainsString( 'data-field-key="company"', $html );
	}

	public function test_conditions_json_is_escaped(): void {
		Monkey\Functions\stubs( [
			'wp_json_encode' => static fn( $d ) => json_encode( $d ),
		] );

		$field  = new TextField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'x',
			'label'      => 'X',
			'conditions' => [
				'logic' => 'all',
				'rules' => [
					[ 'field_key' => 'test', 'operator' => 'equals', 'value' => '"xss&<>' ],
				],
			],
		];

		$html = $field->render( $config, '' );

		// esc_attr should escape the JSON -- no raw quotes or angle brackets.
		$this->assertStringNotContainsString( '"xss&<>', $html );
		$this->assertStringContainsString( 'data-conditions=', $html );
	}

	// --- Display context tests ---

	public function test_label_hidden_renders_sr_only(): void {
		$field   = new TextField();
		$context = [ 'display' => [ 'label_position' => 'hidden' ] ];
		$html    = $field->render( [ 'id' => 'f_1', 'key' => 'x', 'label' => 'Name' ], '', $context );

		$this->assertStringContainsString( 'shqf-sr-only', $html );
		$this->assertStringContainsString( 'shqf-field--label-hidden', $html );
		$this->assertStringContainsString( 'Name', $html );
	}

	public function test_label_left_adds_class(): void {
		$field   = new TextField();
		$context = [ 'display' => [ 'label_position' => 'left' ] ];
		$html    = $field->render( [ 'id' => 'f_1', 'key' => 'x', 'label' => 'Name' ], '', $context );

		$this->assertStringContainsString( 'shqf-field--label-left', $html );
		$this->assertStringNotContainsString( 'shqf-sr-only', $html );
	}

	public function test_label_top_no_extra_class(): void {
		$field   = new TextField();
		$context = [ 'display' => [ 'label_position' => 'top' ] ];
		$html    = $field->render( [ 'id' => 'f_1', 'key' => 'x', 'label' => 'Name' ], '', $context );

		$this->assertStringNotContainsString( 'shqf-field--label-left', $html );
		$this->assertStringNotContainsString( 'shqf-field--label-hidden', $html );
		$this->assertStringNotContainsString( 'shqf-sr-only', $html );
	}

	public function test_placeholder_mode_label_only_no_placeholder(): void {
		$field   = new TextField();
		$context = [ 'display' => [ 'placeholder_mode' => 'label_only' ] ];
		$html    = $field->render(
			[ 'id' => 'f_1', 'key' => 'x', 'label' => 'Name', 'placeholder' => 'Enter name' ],
			'',
			$context
		);

		$this->assertStringNotContainsString( 'placeholder=', $html );
	}

	public function test_placeholder_as_label_uses_label_text(): void {
		$field   = new TextField();
		$context = [ 'display' => [ 'placeholder_mode' => 'placeholder_as_label' ] ];
		$html    = $field->render(
			[ 'id' => 'f_1', 'key' => 'x', 'label' => 'Your Name', 'placeholder' => '' ],
			'',
			$context
		);

		// Label text used as placeholder when no explicit placeholder set.
		$this->assertStringContainsString( 'placeholder="Your Name"', $html );
	}

	public function test_placeholder_as_label_prefers_explicit_placeholder(): void {
		$field   = new TextField();
		$context = [ 'display' => [ 'placeholder_mode' => 'placeholder_as_label' ] ];
		$html    = $field->render(
			[ 'id' => 'f_1', 'key' => 'x', 'label' => 'Name', 'placeholder' => 'Type here' ],
			'',
			$context
		);

		$this->assertStringContainsString( 'placeholder="Type here"', $html );
	}

	public function test_no_display_context_uses_defaults(): void {
		$field = new TextField();
		$html  = $field->render(
			[ 'id' => 'f_1', 'key' => 'x', 'label' => 'Name', 'placeholder' => 'Enter' ],
			''
		);

		// Default: top label (no extra class), show placeholder.
		$this->assertStringNotContainsString( 'shqf-field--label-left', $html );
		$this->assertStringNotContainsString( 'shqf-field--label-hidden', $html );
		$this->assertStringContainsString( 'placeholder="Enter"', $html );
	}

	// --- Description / help text ---

	public function test_description_renders_when_set(): void {
		$field = new TextField();
		$html  = $field->render(
			[ 'id' => 'f_1', 'key' => 'x', 'label' => 'Name', 'description' => 'Enter your full name' ],
			''
		);

		$this->assertStringContainsString( 'shqf-help-text', $html );
		$this->assertStringContainsString( 'Enter your full name', $html );
	}

	public function test_description_not_rendered_when_empty(): void {
		$field = new TextField();
		$html  = $field->render( [ 'id' => 'f_1', 'key' => 'x', 'label' => 'Name' ], '' );

		$this->assertStringNotContainsString( 'shqf-help-text', $html );
	}

	public function test_description_is_escaped(): void {
		$field = new TextField();
		$html  = $field->render(
			[ 'id' => 'f_1', 'key' => 'x', 'label' => 'Name', 'description' => '<script>alert(1)</script>' ],
			''
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'shqf-help-text', $html );
	}

	// --- Validation rules ---

	public function test_custom_required_message(): void {
		$field  = new TextField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'x',
			'label'      => 'Name',
			'required'   => true,
			'validation' => [ 'required_message' => 'Please fill in your name.' ],
		];

		$error = $field->validate( '', $config );
		$this->assertSame( 'Please fill in your name.', $error );
	}

	public function test_default_required_message_when_no_custom(): void {
		$field  = new TextField();
		$config = [ 'id' => 'f_1', 'key' => 'x', 'label' => 'Email', 'required' => true ];

		$error = $field->validate( '', $config );
		$this->assertSame( 'Email is required.', $error );
	}

	public function test_min_length_validation(): void {
		$field  = new TextField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'x',
			'label'      => 'Code',
			'validation' => [ 'min_length' => 5 ],
		];

		$this->assertNotNull( $field->validate( 'ab', $config ) );
		$this->assertNull( $field->validate( 'abcde', $config ) );
	}

	public function test_max_length_validation(): void {
		$field  = new TextField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'x',
			'label'      => 'Code',
			'validation' => [ 'max_length' => 5 ],
		];

		$this->assertNotNull( $field->validate( 'abcdef', $config ) );
		$this->assertNull( $field->validate( 'abcde', $config ) );
	}

	public function test_pattern_validation(): void {
		$field  = new TextField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'x',
			'label'      => 'Code',
			'validation' => [ 'pattern' => '[A-Z]{2}\\d{3}' ],
		];

		$this->assertNull( $field->validate( 'AB123', $config ) );
		$this->assertNotNull( $field->validate( 'ab123', $config ) );
		$this->assertNotNull( $field->validate( 'ABC', $config ) );
	}

	public function test_pattern_with_custom_format_message(): void {
		$field  = new TextField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'x',
			'label'      => 'Code',
			'validation' => [
				'pattern'        => '[A-Z]{2}\\d{3}',
				'format_message' => 'Enter a code like AB123',
			],
		];

		$error = $field->validate( 'invalid', $config );
		$this->assertSame( 'Enter a code like AB123', $error );
	}

	public function test_empty_value_skips_length_and_pattern(): void {
		$field  = new TextField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'x',
			'label'      => 'Code',
			'validation' => [ 'min_length' => 5, 'pattern' => '[A-Z]+' ],
		];

		// Empty non-required field passes.
		$this->assertNull( $field->validate( '', $config ) );
	}

	public function test_textarea_min_length_validation(): void {
		$field  = new TextareaField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'msg',
			'label'      => 'Message',
			'validation' => [ 'min_length' => 10 ],
		];

		$this->assertNotNull( $field->validate( 'short', $config ) );
		$this->assertNull( $field->validate( 'This is long enough.', $config ) );
	}

	public function test_text_renders_minlength_maxlength_pattern_attrs(): void {
		$field  = new TextField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'x',
			'label'      => 'Code',
			'validation' => [ 'min_length' => 3, 'max_length' => 10, 'pattern' => '[A-Z]+' ],
		];

		$html = $field->render( $config, '' );

		$this->assertStringContainsString( 'minlength="3"', $html );
		$this->assertStringContainsString( 'maxlength="10"', $html );
		$this->assertStringContainsString( 'pattern="[A-Z]+"', $html );
	}

	public function test_format_message_data_attribute(): void {
		$field  = new TextField();
		$config = [
			'id'         => 'f_1',
			'key'        => 'x',
			'label'      => 'Code',
			'validation' => [ 'format_message' => 'Custom error' ],
		];

		$html = $field->render( $config, '' );
		$this->assertStringContainsString( 'data-format-message="Custom error"', $html );
	}
}
