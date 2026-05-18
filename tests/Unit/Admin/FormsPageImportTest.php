<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Admin;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Admin\FormsPage;

class FormsPageImportTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
		] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_sanitizes_strings(): void {
		$input  = [ 'title' => '<script>alert("xss")</script>Hello' ];
		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertSame( 'alert("xss")Hello', $result['title'] );
	}

	public function test_preserves_integers(): void {
		$input  = [ 'schema_version' => 1, 'border_radius' => 8 ];
		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertSame( 1, $result['schema_version'] );
		$this->assertSame( 8, $result['border_radius'] );
	}

	public function test_preserves_booleans(): void {
		$input  = [ 'required' => true, 'disabled' => false ];
		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertTrue( $result['required'] );
		$this->assertFalse( $result['disabled'] );
	}

	public function test_preserves_floats(): void {
		$input  = [ 'opacity' => 0.75 ];
		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertSame( 0.75, $result['opacity'] );
	}

	public function test_nullifies_unexpected_types(): void {
		$input  = [ 'bad' => (object) [ 'x' => 1 ] ];
		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertNull( $result['bad'] );
	}

	public function test_recurses_into_nested_arrays(): void {
		$input = [
			'appearance' => [
				'primary_color' => '#0F766E',
				'border_radius' => 8,
			],
			'behavior'   => [
				'success_message' => '<b>Thanks</b>',
			],
		];

		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertSame( '#0F766E', $result['appearance']['primary_color'] );
		$this->assertSame( 8, $result['appearance']['border_radius'] );
		$this->assertSame( 'Thanks', $result['behavior']['success_message'] );
	}

	public function test_sanitizes_deeply_nested_structures(): void {
		$input = [
			'fields' => [
				[
					'type'    => 'text',
					'label'   => '<img onerror=alert(1)>Name',
					'options' => [
						[ 'value' => '<script>x</script>A' ],
					],
				],
			],
		];

		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertSame( 'text', $result['fields'][0]['type'] );
		$this->assertSame( 'Name', $result['fields'][0]['label'] );
		$this->assertSame( 'xA', $result['fields'][0]['options'][0]['value'] );
	}

	public function test_preserves_numeric_keys(): void {
		$input  = [ 0 => 'first', 1 => 'second' ];
		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertSame( [ 0 => 'first', 1 => 'second' ], $result );
	}

	public function test_sanitizes_string_keys(): void {
		$input  = [ '<b>bad_key</b>' => 'value' ];
		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertArrayHasKey( 'bad_key', $result );
		$this->assertSame( 'value', $result['bad_key'] );
	}

	public function test_handles_empty_array(): void {
		$result = FormsPage::sanitize_config_recursive( [] );

		$this->assertSame( [], $result );
	}

	public function test_preserves_null_values(): void {
		$input  = [ 'optional_field' => null ];
		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertArrayHasKey( 'optional_field', $result );
		$this->assertNull( $result['optional_field'] );
	}

	public function test_preserves_empty_strings(): void {
		$input  = [ 'placeholder' => '' ];
		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertSame( '', $result['placeholder'] );
	}

	public function test_preserves_numeric_strings_as_strings(): void {
		$input  = [ 'zip_code' => '90210', 'zero' => '0' ];
		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertSame( '90210', $result['zip_code'] );
		$this->assertSame( '0', $result['zero'] );
	}

	public function test_handles_mixed_types_in_single_array(): void {
		$input = [
			'name'     => 'Test <em>Form</em>',
			'version'  => 2,
			'active'   => true,
			'ratio'    => 1.5,
			'children' => [ 'a', 'b' ],
		];

		$result = FormsPage::sanitize_config_recursive( $input );

		$this->assertSame( 'Test Form', $result['name'] );
		$this->assertSame( 2, $result['version'] );
		$this->assertTrue( $result['active'] );
		$this->assertSame( 1.5, $result['ratio'] );
		$this->assertSame( [ 'a', 'b' ], $result['children'] );
	}
}
