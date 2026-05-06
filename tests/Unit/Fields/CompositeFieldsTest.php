<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for composite field types: Name, Address.
 *
 * @package SampleHQForm\Tests\Unit\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Fields;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Fields\NameField;
use SampleHQForm\Fields\AddressField;

/**
 * Composite field types unit tests.
 */
class CompositeFieldsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'esc_attr'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_html'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_html__'          => static fn( $s ) => $s,
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'__'                  => static fn( $s ) => $s,
		] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// --- NameField ---

	/**
	 * NameField renders fieldset with two inputs.
	 */
	public function test_name_renders_fieldset(): void {
		$field = new NameField();
		$html  = $field->render(
			[ 'id' => 'f_1', 'key' => 'name', 'label' => 'Full Name', 'required' => true ],
			[ 'first_name' => 'John', 'last_name' => 'Doe' ]
		);

		$this->assertStringContainsString( '<fieldset', $html );
		$this->assertStringContainsString( '<legend', $html );
		$this->assertStringContainsString( 'Full Name', $html );
		$this->assertStringContainsString( 'value="John"', $html );
		$this->assertStringContainsString( 'value="Doe"', $html );
		$this->assertStringContainsString( 'autocomplete="given-name"', $html );
		$this->assertStringContainsString( 'autocomplete="family-name"', $html );
		$this->assertStringContainsString( 'First Name', $html );
		$this->assertStringContainsString( 'Last Name', $html );
	}

	/**
	 * NameField has two labeled inputs.
	 */
	public function test_name_has_labeled_inputs(): void {
		$field = new NameField();
		$html  = $field->render(
			[ 'id' => 'f_1', 'key' => 'name', 'label' => 'Name' ],
			[]
		);

		// Should have 2 <label> elements (one for each sub-field) + legend.
		$this->assertSame( 2, substr_count( $html, '<label for=' ) );
		$this->assertSame( 2, substr_count( $html, '<input type="text"' ) );
	}

	/**
	 * NameField validates required - both parts needed.
	 */
	public function test_name_validates_required(): void {
		$field  = new NameField();
		$config = [ 'label' => 'Name', 'required' => true ];

		$this->assertNull( $field->validate( [ 'first_name' => 'John', 'last_name' => 'Doe' ], $config ) );
		$this->assertNotNull( $field->validate( [ 'first_name' => 'John', 'last_name' => '' ], $config ) );
		$this->assertNotNull( $field->validate( [ 'first_name' => '', 'last_name' => 'Doe' ], $config ) );
		$this->assertNotNull( $field->validate( [], $config ) );
	}

	/**
	 * NameField optional allows empty.
	 */
	public function test_name_optional(): void {
		$field = new NameField();
		$this->assertNull( $field->validate( [], [ 'label' => 'Name' ] ) );
	}

	/**
	 * NameField sanitizes both parts.
	 */
	public function test_name_sanitizes(): void {
		$field  = new NameField();
		$result = $field->sanitize( [ 'first_name' => '<b>John</b>', 'last_name' => '<i>Doe</i>' ], [] );

		$this->assertSame( 'John', $result['first_name'] );
		$this->assertSame( 'Doe', $result['last_name'] );
	}

	/**
	 * NameField sanitize handles missing keys.
	 */
	public function test_name_sanitize_missing_keys(): void {
		$field  = new NameField();
		$result = $field->sanitize( 'not-an-array', [] );

		$this->assertSame( '', $result['first_name'] );
		$this->assertSame( '', $result['last_name'] );
	}

	// --- AddressField ---

	/**
	 * AddressField renders fieldset with 5 sub-fields.
	 */
	public function test_address_renders_fieldset(): void {
		$field = new AddressField();
		$html  = $field->render(
			[ 'id' => 'f_2', 'key' => 'address', 'label' => 'Shipping Address', 'required' => true ],
			[
				'street'  => '123 Main St',
				'city'    => 'Springfield',
				'state'   => 'IL',
				'zip'     => '62704',
				'country' => 'US',
			]
		);

		$this->assertStringContainsString( '<fieldset', $html );
		$this->assertStringContainsString( 'Shipping Address', $html );
		$this->assertStringContainsString( 'value="123 Main St"', $html );
		$this->assertStringContainsString( 'value="Springfield"', $html );
		$this->assertStringContainsString( 'autocomplete="street-address"', $html );
		$this->assertStringContainsString( 'autocomplete="address-level2"', $html );
		$this->assertStringContainsString( 'autocomplete="address-level1"', $html );
		$this->assertStringContainsString( 'autocomplete="postal-code"', $html );
		$this->assertStringContainsString( 'autocomplete="country-name"', $html );
	}

	/**
	 * AddressField has 5 labeled inputs.
	 */
	public function test_address_has_five_inputs(): void {
		$field = new AddressField();
		$html  = $field->render(
			[ 'id' => 'f_2', 'key' => 'addr', 'label' => 'Address' ],
			[]
		);

		$this->assertSame( 5, substr_count( $html, '<input type="text"' ) );
		$this->assertSame( 5, substr_count( $html, '<label for=' ) );
	}

	/**
	 * AddressField validates required (street + city).
	 */
	public function test_address_validates_required(): void {
		$field  = new AddressField();
		$config = [ 'label' => 'Address', 'required' => true ];

		$this->assertNull( $field->validate( [ 'street' => '123 Main', 'city' => 'Town' ], $config ) );
		$this->assertNotNull( $field->validate( [ 'street' => '123 Main', 'city' => '' ], $config ) );
		$this->assertNotNull( $field->validate( [ 'street' => '', 'city' => 'Town' ], $config ) );
		$this->assertNotNull( $field->validate( [], $config ) );
	}

	/**
	 * AddressField optional allows empty.
	 */
	public function test_address_optional(): void {
		$field = new AddressField();
		$this->assertNull( $field->validate( [], [ 'label' => 'Address' ] ) );
	}

	/**
	 * AddressField sanitizes all sub-fields.
	 */
	public function test_address_sanitizes_all(): void {
		$field  = new AddressField();
		$result = $field->sanitize(
			[
				'street'  => '<script>alert(1)</script>123 Main',
				'city'    => '<b>Town</b>',
				'state'   => 'IL',
				'zip'     => '62704',
				'country' => 'US',
			],
			[]
		);

		$this->assertStringNotContainsString( '<script>', $result['street'] );
		$this->assertStringContainsString( '123 Main', $result['street'] );
		$this->assertSame( 'Town', $result['city'] );
		$this->assertSame( 'IL', $result['state'] );
	}

	/**
	 * AddressField sanitize handles missing keys.
	 */
	public function test_address_sanitize_missing_keys(): void {
		$field  = new AddressField();
		$result = $field->sanitize( [], [] );

		$this->assertSame( '', $result['street'] );
		$this->assertSame( '', $result['city'] );
		$this->assertSame( '', $result['state'] );
		$this->assertSame( '', $result['zip'] );
		$this->assertSame( '', $result['country'] );
	}

	/**
	 * AddressField type identifier.
	 */
	public function test_address_type(): void {
		$this->assertSame( 'address', ( new AddressField() )->get_type() );
	}

	/**
	 * NameField type identifier.
	 */
	public function test_name_type(): void {
		$this->assertSame( 'name', ( new NameField() )->get_type() );
	}
}
