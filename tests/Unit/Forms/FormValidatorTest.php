<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for FormValidator.
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
use SampleHQForm\Forms\FormValidator;

/**
 * FormValidator unit tests.
 */
class FormValidatorTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private FieldRegistry $registry;
	private FormValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->registry  = new FieldRegistry();
		$this->validator = new FormValidator( $this->registry );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Valid data returns no errors.
	 */
	public function test_valid_data_no_errors(): void {
		$mock = Mockery::mock( FieldInterface::class );
		$mock->shouldReceive( 'get_type' )->andReturn( 'text' );
		$mock->shouldReceive( 'validate' )->with( 'John', Mockery::type( 'array' ) )->andReturn( null );
		$this->registry->register( $mock );

		$config = [
			'fields' => [
				[ 'id' => 'f_1', 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true ],
			],
		];

		$errors = $this->validator->validate( [ 'name' => 'John' ], $config );
		$this->assertSame( [], $errors );
	}

	/**
	 * Invalid data returns errors keyed by field ID.
	 */
	public function test_invalid_data_returns_errors(): void {
		$mock = Mockery::mock( FieldInterface::class );
		$mock->shouldReceive( 'get_type' )->andReturn( 'text' );
		$mock->shouldReceive( 'validate' )->with( '', Mockery::type( 'array' ) )->andReturn( 'Name is required.' );
		$this->registry->register( $mock );

		$config = [
			'fields' => [
				[ 'id' => 'f_1', 'key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true ],
			],
		];

		$errors = $this->validator->validate( [ 'name' => '' ], $config );
		$this->assertArrayHasKey( 'f_1', $errors );
		$this->assertSame( 'Name is required.', $errors['f_1'] );
	}

	/**
	 * Missing field values are passed as null.
	 */
	public function test_missing_values_passed_as_null(): void {
		$mock = Mockery::mock( FieldInterface::class );
		$mock->shouldReceive( 'get_type' )->andReturn( 'email' );
		$mock->shouldReceive( 'validate' )->with( null, Mockery::type( 'array' ) )->andReturn( 'Email is required.' );
		$this->registry->register( $mock );

		$config = [
			'fields' => [
				[ 'id' => 'f_2', 'key' => 'email', 'type' => 'email', 'required' => true ],
			],
		];

		$errors = $this->validator->validate( [], $config );
		$this->assertArrayHasKey( 'f_2', $errors );
	}

	/**
	 * Disabled fields are skipped.
	 */
	public function test_disabled_fields_skipped(): void {
		$mock = Mockery::mock( FieldInterface::class );
		$mock->shouldReceive( 'get_type' )->andReturn( 'text' );
		$mock->shouldReceive( 'validate' )->never();
		$this->registry->register( $mock );

		$config = [
			'fields' => [
				[ 'id' => 'f_1', 'key' => 'name', 'type' => 'text', 'enabled' => false ],
			],
		];

		$errors = $this->validator->validate( [], $config );
		$this->assertSame( [], $errors );
	}

	/**
	 * Unknown field types are skipped gracefully.
	 */
	public function test_unknown_types_skipped(): void {
		$config = [
			'fields' => [
				[ 'id' => 'f_1', 'key' => 'x', 'type' => 'nonexistent' ],
			],
		];

		$errors = $this->validator->validate( [ 'x' => 'val' ], $config );
		$this->assertSame( [], $errors );
	}

	/**
	 * Multiple fields: some valid, some invalid.
	 */
	public function test_multiple_fields_partial_errors(): void {
		$text_field = Mockery::mock( FieldInterface::class );
		$text_field->shouldReceive( 'get_type' )->andReturn( 'text' );
		$text_field->shouldReceive( 'validate' )->andReturnUsing(
			static function ( $value ) {
				return empty( $value ) ? 'Required.' : null;
			}
		);

		$email_field = Mockery::mock( FieldInterface::class );
		$email_field->shouldReceive( 'get_type' )->andReturn( 'email' );
		$email_field->shouldReceive( 'validate' )->andReturn( null );

		$this->registry->register( $text_field );
		$this->registry->register( $email_field );

		$config = [
			'fields' => [
				[ 'id' => 'f_1', 'key' => 'name', 'type' => 'text', 'required' => true ],
				[ 'id' => 'f_2', 'key' => 'email', 'type' => 'email', 'required' => true ],
			],
		];

		$errors = $this->validator->validate( [ 'name' => '', 'email' => 'test@test.com' ], $config );

		$this->assertCount( 1, $errors );
		$this->assertArrayHasKey( 'f_1', $errors );
		$this->assertArrayNotHasKey( 'f_2', $errors );
	}

	/**
	 * Sanitize all returns sanitized values for all fields.
	 */
	public function test_sanitize_all(): void {
		$mock = Mockery::mock( FieldInterface::class );
		$mock->shouldReceive( 'get_type' )->andReturn( 'text' );
		$mock->shouldReceive( 'sanitize' )->with( '<b>John</b>', Mockery::type( 'array' ) )->andReturn( 'John' );
		$this->registry->register( $mock );

		$config = [
			'fields' => [
				[ 'id' => 'f_1', 'key' => 'name', 'type' => 'text' ],
			],
		];

		$sanitized = $this->validator->sanitize_all( [ 'name' => '<b>John</b>' ], $config );

		$this->assertSame( [ 'name' => 'John' ], $sanitized );
	}

	/**
	 * Sanitize all skips disabled and unknown fields.
	 */
	public function test_sanitize_all_skips_disabled(): void {
		$config = [
			'fields' => [
				[ 'id' => 'f_1', 'key' => 'name', 'type' => 'text', 'enabled' => false ],
				[ 'id' => 'f_2', 'key' => 'x', 'type' => 'unknown' ],
			],
		];

		$sanitized = $this->validator->sanitize_all( [ 'name' => 'test', 'x' => 'val' ], $config );
		$this->assertSame( [], $sanitized );
	}

	/**
	 * Conditionally hidden required field skips validation.
	 */
	public function test_hidden_by_condition_skips_validation(): void {
		$mock = Mockery::mock( FieldInterface::class );
		$mock->shouldReceive( 'get_type' )->andReturn( 'text' );
		// validate() should NOT be called for a hidden field.
		$mock->shouldReceive( 'validate' )->never();
		$this->registry->register( $mock );

		$config = [
			'fields' => [
				[
					'id'         => 'f_state',
					'key'        => 'state',
					'type'       => 'text',
					'required'   => true,
					'conditions' => [
						'logic' => 'all',
						'rules' => [
							[ 'field_key' => 'country', 'operator' => 'equals', 'value' => 'US' ],
						],
					],
				],
			],
		];

		// Country is UK, so state field should be hidden and not validated.
		$errors = $this->validator->validate( [ 'country' => 'UK', 'state' => '' ], $config );
		$this->assertSame( [], $errors );
	}

	/**
	 * Conditionally hidden field excluded from sanitized output.
	 */
	public function test_hidden_by_condition_excluded_from_sanitize(): void {
		$mock = Mockery::mock( FieldInterface::class );
		$mock->shouldReceive( 'get_type' )->andReturn( 'text' );
		// sanitize() should NOT be called for a hidden field.
		$mock->shouldReceive( 'sanitize' )->never();
		$this->registry->register( $mock );

		$config = [
			'fields' => [
				[
					'id'         => 'f_state',
					'key'        => 'state',
					'type'       => 'text',
					'conditions' => [
						'logic' => 'all',
						'rules' => [
							[ 'field_key' => 'country', 'operator' => 'equals', 'value' => 'US' ],
						],
					],
				],
			],
		];

		// User submitted state data but country=UK, so state is hidden.
		$sanitized = $this->validator->sanitize_all( [ 'country' => 'UK', 'state' => 'secret' ], $config );
		$this->assertArrayNotHasKey( 'state', $sanitized );
	}

	/**
	 * Conditionally visible field IS validated.
	 */
	public function test_visible_by_condition_is_validated(): void {
		$mock = Mockery::mock( FieldInterface::class );
		$mock->shouldReceive( 'get_type' )->andReturn( 'text' );
		$mock->shouldReceive( 'validate' )->once()->andReturn( 'State is required.' );
		$this->registry->register( $mock );

		$config = [
			'fields' => [
				[
					'id'         => 'f_state',
					'key'        => 'state',
					'type'       => 'text',
					'required'   => true,
					'conditions' => [
						'logic' => 'all',
						'rules' => [
							[ 'field_key' => 'country', 'operator' => 'equals', 'value' => 'US' ],
						],
					],
				],
			],
		];

		// Country IS US, so state field is visible and should be validated.
		$errors = $this->validator->validate( [ 'country' => 'US', 'state' => '' ], $config );
		$this->assertArrayHasKey( 'f_state', $errors );
	}

	// -------------------------------------------------------------------------
	// flatten_fields() tests
	// -------------------------------------------------------------------------

	public function test_flatten_fields_returns_flat_fields_unchanged(): void {
		$fields = [
			[ 'id' => 'f_1', 'type' => 'text', 'key' => 'name' ],
			[ 'id' => 'f_2', 'type' => 'email', 'key' => 'email' ],
		];

		$result = FormValidator::flatten_fields( $fields );
		$this->assertSame( $fields, $result );
	}

	public function test_flatten_fields_extracts_row_children(): void {
		$fields = [
			[ 'id' => 'f_1', 'type' => 'text', 'key' => 'name' ],
			[
				'id'      => 'row_1',
				'type'    => 'row',
				'columns' => [
					[
						'width'  => '1fr',
						'fields' => [
							[ 'id' => 'f_2', 'type' => 'text', 'key' => 'first_name' ],
						],
					],
					[
						'width'  => '1fr',
						'fields' => [
							[ 'id' => 'f_3', 'type' => 'text', 'key' => 'last_name' ],
						],
					],
				],
			],
			[ 'id' => 'f_4', 'type' => 'email', 'key' => 'email' ],
		];

		$result = FormValidator::flatten_fields( $fields );

		$this->assertCount( 4, $result );
		$this->assertSame( 'name', $result[0]['key'] );
		$this->assertSame( 'first_name', $result[1]['key'] );
		$this->assertSame( 'last_name', $result[2]['key'] );
		$this->assertSame( 'email', $result[3]['key'] );
	}

	public function test_flatten_fields_handles_empty_row(): void {
		$fields = [
			[
				'id'      => 'row_1',
				'type'    => 'row',
				'columns' => [],
			],
		];

		$result = FormValidator::flatten_fields( $fields );
		$this->assertSame( [], $result );
	}

	public function test_flatten_fields_handles_row_with_empty_columns(): void {
		$fields = [
			[
				'id'      => 'row_1',
				'type'    => 'row',
				'columns' => [
					[ 'width' => '1fr', 'fields' => [] ],
					[ 'width' => '1fr' ], // Missing 'fields' key.
				],
			],
		];

		$result = FormValidator::flatten_fields( $fields );
		$this->assertSame( [], $result );
	}

	public function test_flatten_fields_handles_empty_array(): void {
		$this->assertSame( [], FormValidator::flatten_fields( [] ) );
	}

	public function test_flatten_fields_handles_nested_row_recursively(): void {
		$fields = [
			[
				'id'      => 'row_outer',
				'type'    => 'row',
				'columns' => [
					[
						'width'  => '1fr',
						'fields' => [
							[ 'id' => 'f_1', 'type' => 'text', 'key' => 'name' ],
							[
								'id'      => 'row_inner',
								'type'    => 'row',
								'columns' => [
									[
										'width'  => '1fr',
										'fields' => [
											[ 'id' => 'f_2', 'type' => 'text', 'key' => 'city' ],
										],
									],
								],
							],
						],
					],
				],
			],
		];

		$result = FormValidator::flatten_fields( $fields );

		$this->assertCount( 2, $result );
		$this->assertSame( 'name', $result[0]['key'] );
		$this->assertSame( 'city', $result[1]['key'] );
	}

	public function test_flatten_fields_handles_row_missing_columns_key(): void {
		$fields = [
			[ 'id' => 'row_1', 'type' => 'row' ], // No 'columns' key.
		];

		$result = FormValidator::flatten_fields( $fields );
		$this->assertSame( [], $result );
	}

	// -------------------------------------------------------------------------
	// Validation / sanitization with row groups
	// -------------------------------------------------------------------------

	public function test_validate_fields_inside_row(): void {
		$mock = Mockery::mock( FieldInterface::class );
		$mock->shouldReceive( 'get_type' )->andReturn( 'text' );
		$mock->shouldReceive( 'validate' )->andReturnUsing(
			static function ( $value ) {
				return empty( $value ) ? 'Required.' : null;
			}
		);
		$this->registry->register( $mock );

		$config = [
			'fields' => [
				[
					'id'      => 'row_1',
					'type'    => 'row',
					'columns' => [
						[
							'width'  => '1fr',
							'fields' => [
								[ 'id' => 'f_fn', 'key' => 'first_name', 'type' => 'text', 'required' => true ],
							],
						],
						[
							'width'  => '1fr',
							'fields' => [
								[ 'id' => 'f_ln', 'key' => 'last_name', 'type' => 'text', 'required' => true ],
							],
						],
					],
				],
			],
		];

		$errors = $this->validator->validate( [ 'first_name' => 'John', 'last_name' => '' ], $config );

		$this->assertCount( 1, $errors );
		$this->assertArrayHasKey( 'f_ln', $errors );
		$this->assertArrayNotHasKey( 'f_fn', $errors );
	}

	public function test_sanitize_fields_inside_row(): void {
		$mock = Mockery::mock( FieldInterface::class );
		$mock->shouldReceive( 'get_type' )->andReturn( 'text' );
		$mock->shouldReceive( 'sanitize' )->andReturnUsing(
			static function ( $value ) {
				return trim( strip_tags( (string) $value ) );
			}
		);
		$this->registry->register( $mock );

		$config = [
			'fields' => [
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
			],
		];

		$sanitized = $this->validator->sanitize_all(
			[ 'first_name' => ' <b>John</b> ', 'last_name' => 'Doe' ],
			$config
		);

		$this->assertSame( 'John', $sanitized['first_name'] );
		$this->assertSame( 'Doe', $sanitized['last_name'] );
	}

	public function test_conditional_field_inside_row_skipped_when_hidden(): void {
		$mock = Mockery::mock( FieldInterface::class );
		$mock->shouldReceive( 'get_type' )->andReturn( 'text' );
		$mock->shouldReceive( 'validate' )->never();
		$this->registry->register( $mock );

		$config = [
			'fields' => [
				[
					'id'      => 'row_1',
					'type'    => 'row',
					'columns' => [
						[
							'width'  => '1fr',
							'fields' => [
								[
									'id'         => 'f_state',
									'key'        => 'state',
									'type'       => 'text',
									'required'   => true,
									'conditions' => [
										'logic' => 'all',
										'rules' => [
											[ 'field_key' => 'country', 'operator' => 'equals', 'value' => 'US' ],
										],
									],
								],
							],
						],
					],
				],
			],
		];

		$errors = $this->validator->validate( [ 'country' => 'UK', 'state' => '' ], $config );
		$this->assertSame( [], $errors );
	}
}
