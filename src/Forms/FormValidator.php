<?php
/**
 * Server-side form validation.
 *
 * @package SampleHQForm\Forms
 */

declare( strict_types=1 );

namespace SampleHQForm\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Fields\FieldRegistry;

/**
 * Validates submitted form data against the form configuration.
 *
 * Iterates through enabled fields, delegates to each field type's validate()
 * method, and collects errors keyed by field ID. Fields hidden by conditional
 * logic are skipped.
 */
class FormValidator {

	/**
	 * Field type registry.
	 *
	 * @var FieldRegistry
	 */
	private FieldRegistry $fields;

	/**
	 * Condition evaluator.
	 *
	 * @var ConditionEvaluator
	 */
	private ConditionEvaluator $conditions;

	/**
	 * Constructor.
	 *
	 * @param FieldRegistry           $fields     Field type registry.
	 * @param ConditionEvaluator|null $conditions Condition evaluator (optional, auto-created).
	 */
	public function __construct( FieldRegistry $fields, ?ConditionEvaluator $conditions = null ) {
		$this->fields     = $fields;
		$this->conditions = $conditions ?? new ConditionEvaluator();
	}

	/**
	 * Flatten a fields array that may contain row groups.
	 *
	 * Row fields (type=row) contain nested fields inside columns.
	 * This method extracts all leaf fields into a single flat array
	 * so that validation and sanitization can iterate uniformly.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields array (may contain rows).
	 * @return array<int, array<string, mixed>> Flat array of non-row fields.
	 */
	public static function flatten_fields( array $fields ): array {
		$flat = [];

		foreach ( $fields as $field ) {
			if ( 'row' === ( $field['type'] ?? '' ) ) {
				foreach ( $field['columns'] ?? [] as $column ) {
					// Recurse to handle any nested rows defensively.
					foreach ( self::flatten_fields( $column['fields'] ?? [] ) as $child ) {
						$flat[] = $child;
					}
				}
				continue;
			}

			$flat[] = $field;
		}

		return $flat;
	}

	/**
	 * Validate submitted data against a form config.
	 *
	 * @param array<string, mixed> $submitted_fields Submitted field values keyed by field key.
	 * @param array<string, mixed> $config           Form config JSON (decoded).
	 * @return array<string, string> Errors keyed by field ID. Empty array = valid.
	 */
	public function validate( array $submitted_fields, array $config ): array {
		$errors     = [];
		$field_defs = self::flatten_fields( $config['fields'] ?? [] );

		foreach ( $field_defs as $field_config ) {
			if ( empty( $field_config['enabled'] ?? true ) ) {
				continue;
			}

			// Skip fields hidden by conditional logic.
			if ( ! $this->conditions->is_visible( $field_config, $submitted_fields ) ) {
				continue;
			}

			$type       = $field_config['type'] ?? '';
			$field_impl = $this->fields->get( $type );

			if ( null === $field_impl ) {
				continue;
			}

			$key   = $field_config['key'] ?? $field_config['id'] ?? '';
			$value = $submitted_fields[ $key ] ?? null;

			$error = $field_impl->validate( $value, $field_config );

			if ( null !== $error ) {
				$field_id            = $field_config['id'] ?? $key;
				$errors[ $field_id ] = $error;
			}
		}

		return $errors;
	}

	/**
	 * Sanitize all submitted field values based on the form config.
	 *
	 * Fields hidden by conditional logic are excluded from the sanitized output.
	 *
	 * @param array<string, mixed> $submitted_fields Submitted field values keyed by field key.
	 * @param array<string, mixed> $config           Form config JSON (decoded).
	 * @return array<string, mixed> Sanitized values keyed by field key.
	 */
	public function sanitize_all( array $submitted_fields, array $config ): array {
		$sanitized  = [];
		$field_defs = self::flatten_fields( $config['fields'] ?? [] );

		foreach ( $field_defs as $field_config ) {
			if ( empty( $field_config['enabled'] ?? true ) ) {
				continue;
			}

			// Skip fields hidden by conditional logic.
			if ( ! $this->conditions->is_visible( $field_config, $submitted_fields ) ) {
				continue;
			}

			$type       = $field_config['type'] ?? '';
			$field_impl = $this->fields->get( $type );

			if ( null === $field_impl ) {
				continue;
			}

			$key   = $field_config['key'] ?? $field_config['id'] ?? '';
			$value = $submitted_fields[ $key ] ?? null;

			$sanitized[ $key ] = $field_impl->sanitize( $value, $field_config );
		}

		return $sanitized;
	}
}
