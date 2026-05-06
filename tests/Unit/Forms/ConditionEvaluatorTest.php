<?php
/**
 * Tests for the ConditionEvaluator.
 *
 * @package SampleHQForm\Tests\Unit\Forms
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Forms;

use PHPUnit\Framework\TestCase;
use SampleHQForm\Forms\ConditionEvaluator;

/**
 * ConditionEvaluator unit tests.
 */
class ConditionEvaluatorTest extends TestCase {

	private ConditionEvaluator $evaluator;

	protected function setUp(): void {
		parent::setUp();
		$this->evaluator = new ConditionEvaluator();
	}

	public function test_no_conditions_returns_visible(): void {
		$this->assertTrue( $this->evaluator->is_visible( [], [] ) );
		$this->assertTrue( $this->evaluator->is_visible( [ 'conditions' => null ], [] ) );
		$this->assertTrue( $this->evaluator->is_visible( [ 'conditions' => [] ], [] ) );
		$this->assertTrue( $this->evaluator->is_visible( [ 'conditions' => [ 'rules' => [] ] ], [] ) );
	}

	public function test_equals_operator(): void {
		$field = [
			'conditions' => [
				'logic' => 'all',
				'rules' => [ [ 'field_key' => 'country', 'operator' => 'equals', 'value' => 'US' ] ],
			],
		];

		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'country' => 'US' ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [ 'country' => 'UK' ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [] ) );
	}

	public function test_not_equals_operator(): void {
		$field = [
			'conditions' => [
				'logic' => 'all',
				'rules' => [ [ 'field_key' => 'type', 'operator' => 'not_equals', 'value' => 'none' ] ],
			],
		];

		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'type' => 'premium' ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [ 'type' => 'none' ] ) );
	}

	public function test_contains_operator(): void {
		$field = [
			'conditions' => [
				'logic' => 'all',
				'rules' => [ [ 'field_key' => 'message', 'operator' => 'contains', 'value' => 'urgent' ] ],
			],
		];

		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'message' => 'This is urgent!' ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [ 'message' => 'No rush.' ] ) );
	}

	public function test_not_contains_operator(): void {
		$field = [
			'conditions' => [
				'logic' => 'all',
				'rules' => [ [ 'field_key' => 'name', 'operator' => 'not_contains', 'value' => 'test' ] ],
			],
		];

		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'name' => 'John' ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [ 'name' => 'test user' ] ) );
	}

	public function test_empty_operator(): void {
		$field = [
			'conditions' => [
				'logic' => 'all',
				'rules' => [ [ 'field_key' => 'company', 'operator' => 'empty' ] ],
			],
		];

		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'company' => '' ] ) );
		$this->assertTrue( $this->evaluator->is_visible( $field, [] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [ 'company' => 'Acme' ] ) );
	}

	public function test_not_empty_operator(): void {
		$field = [
			'conditions' => [
				'logic' => 'all',
				'rules' => [ [ 'field_key' => 'email', 'operator' => 'not_empty' ] ],
			],
		];

		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'email' => 'a@b.com' ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [ 'email' => '' ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [] ) );
	}

	public function test_all_logic_requires_all_rules(): void {
		$field = [
			'conditions' => [
				'logic' => 'all',
				'rules' => [
					[ 'field_key' => 'country', 'operator' => 'equals', 'value' => 'US' ],
					[ 'field_key' => 'state', 'operator' => 'not_empty' ],
				],
			],
		];

		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'country' => 'US', 'state' => 'CA' ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [ 'country' => 'US', 'state' => '' ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [ 'country' => 'UK', 'state' => 'London' ] ) );
	}

	public function test_any_logic_requires_one_rule(): void {
		$field = [
			'conditions' => [
				'logic' => 'any',
				'rules' => [
					[ 'field_key' => 'type', 'operator' => 'equals', 'value' => 'business' ],
					[ 'field_key' => 'type', 'operator' => 'equals', 'value' => 'enterprise' ],
				],
			],
		];

		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'type' => 'business' ] ) );
		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'type' => 'enterprise' ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [ 'type' => 'personal' ] ) );
	}

	public function test_array_value_joined_for_comparison(): void {
		$field = [
			'conditions' => [
				'logic' => 'all',
				'rules' => [ [ 'field_key' => 'interests', 'operator' => 'contains', 'value' => 'packaging' ] ],
			],
		];

		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'interests' => [ 'packaging', 'labels' ] ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [ 'interests' => [ 'labels', 'tape' ] ] ) );
	}

	public function test_unknown_operator_defaults_to_visible(): void {
		$field = [
			'conditions' => [
				'logic' => 'all',
				'rules' => [ [ 'field_key' => 'x', 'operator' => 'invalid_op', 'value' => 'y' ] ],
			],
		];

		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'x' => 'z' ] ) );
	}

	/**
	 * Field that was originally inside a row group evaluates conditions the same way.
	 * The evaluator is per-field and agnostic to nesting.
	 */
	public function test_field_from_row_evaluates_conditions_normally(): void {
		// Simulates a field that lived inside a row column, now flattened.
		$field = [
			'id'         => 'f_state',
			'key'        => 'state',
			'type'       => 'text',
			'conditions' => [
				'logic' => 'all',
				'rules' => [
					[ 'field_key' => 'country', 'operator' => 'equals', 'value' => 'US' ],
				],
			],
		];

		$this->assertTrue( $this->evaluator->is_visible( $field, [ 'country' => 'US' ] ) );
		$this->assertFalse( $this->evaluator->is_visible( $field, [ 'country' => 'UK' ] ) );
	}
}
