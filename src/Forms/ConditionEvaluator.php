<?php
/**
 * Conditional logic evaluator.
 *
 * @package SampleHQForm\Forms
 */

declare( strict_types=1 );

namespace SampleHQForm\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates field visibility conditions against submitted values.
 *
 * Condition schema (per field):
 * {
 *   "conditions": {
 *     "logic": "all" | "any",
 *     "rules": [
 *       { "field_key": "country", "operator": "equals", "value": "US" }
 *     ]
 *   }
 * }
 *
 * Supported operators: equals, not_equals, contains, not_contains, empty, not_empty.
 */
class ConditionEvaluator {

	/**
	 * Check if a field should be visible based on its conditions.
	 *
	 * @param array<string, mixed> $field_config     Field config with optional 'conditions' key.
	 * @param array<string, mixed> $submitted_fields All submitted field values keyed by field key.
	 * @return bool True if the field is visible (conditions met or no conditions).
	 */
	public function is_visible( array $field_config, array $submitted_fields ): bool {
		$conditions = $field_config['conditions'] ?? null;

		if ( empty( $conditions ) || empty( $conditions['rules'] ) ) {
			return true;
		}

		$rules = $conditions['rules'];
		$logic = $conditions['logic'] ?? 'all';

		foreach ( $rules as $rule ) {
			$result = $this->evaluate_rule( $rule, $submitted_fields );

			if ( 'any' === $logic && $result ) {
				return true;
			}

			if ( 'all' === $logic && ! $result ) {
				return false;
			}
		}

		// 'all' logic: all rules passed. 'any' logic: no rule matched.
		return 'all' === $logic;
	}

	/**
	 * Evaluate a single condition rule.
	 *
	 * @param array<string, mixed> $rule             The rule: field_key, operator, value.
	 * @param array<string, mixed> $submitted_fields Submitted values.
	 * @return bool True if the rule matches.
	 */
	private function evaluate_rule( array $rule, array $submitted_fields ): bool {
		$field_key      = $rule['field_key'] ?? '';
		$operator       = $rule['operator'] ?? 'equals';
		$expected_value = (string) ( $rule['value'] ?? '' );
		$actual_value   = $submitted_fields[ $field_key ] ?? null;

		// Normalize array values to string for comparison.
		if ( is_array( $actual_value ) ) {
			$actual_str = implode( ',', $actual_value );
		} else {
			$actual_str = (string) ( $actual_value ?? '' );
		}

		switch ( $operator ) {
			case 'equals':
				return $actual_str === $expected_value;

			case 'not_equals':
				return $actual_str !== $expected_value;

			case 'contains':
				return '' !== $expected_value && false !== strpos( $actual_str, $expected_value );

			case 'not_contains':
				return '' === $expected_value || false === strpos( $actual_str, $expected_value );

			case 'empty':
				return '' === trim( $actual_str );

			case 'not_empty':
				return '' !== trim( $actual_str );

			default:
				return true;
		}
	}
}
