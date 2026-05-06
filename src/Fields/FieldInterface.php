<?php
/**
 * Field type interface.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for all field types.
 *
 * Each field type implements rendering, validation, and sanitization.
 */
interface FieldInterface {

	/**
	 * Get the field type identifier.
	 *
	 * @return string Type name (e.g., 'text', 'email', 'select').
	 */
	public function get_type(): string;

	/**
	 * Render the field HTML.
	 *
	 * Must produce ADA-compliant markup: visible labels, aria attributes,
	 * autocomplete attributes, fieldset/legend for groups.
	 *
	 * @param array<string, mixed> $field Field configuration from the form config JSON.
	 * @param mixed                $value Current value (for pre-filling).
	 * @param array<string, mixed> $context Render context: form_id, field_prefix, errors.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string;

	/**
	 * Validate a submitted value.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string|null Error message if invalid, null if valid.
	 */
	public function validate( mixed $value, array $field ): ?string;

	/**
	 * Sanitize a submitted value for safe storage.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return mixed Sanitized value.
	 */
	public function sanitize( mixed $value, array $field ): mixed;
}
