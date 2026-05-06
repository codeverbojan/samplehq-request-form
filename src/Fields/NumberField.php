<?php
/**
 * Number field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Numeric input field with optional min/max validation.
 */
class NumberField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'number';
	}

	/**
	 * Render the number input HTML.
	 *
	 * @param array<string, mixed> $field   Field configuration.
	 * @param mixed                $value   Current value.
	 * @param array<string, mixed> $context Render context.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string {
		$id    = $this->get_input_id( $field, $context );
		$name  = $this->get_input_name( $field );
		$attrs = $this->build_common_attrs( $field, $context );

		$validation = $field['validation'] ?? [];

		if ( isset( $validation['min'] ) ) {
			$attrs .= ' min="' . esc_attr( (string) $validation['min'] ) . '"';
		}

		if ( isset( $validation['max'] ) ) {
			$attrs .= ' max="' . esc_attr( (string) $validation['max'] ) . '"';
		}

		if ( isset( $validation['step'] ) ) {
			$attrs .= ' step="' . esc_attr( (string) $validation['step'] ) . '"';
		}

		$html  = $this->render_label( $field, $context );
		$html .= '<input type="number" id="' . esc_attr( $id ) . '" name="' . $name . '"';
		$html .= ' value="' . esc_attr( (string) $value ) . '"' . $attrs;
		$html .= ' class="shqf-input shqf-input--number" />';
		$html .= $this->render_error( $field, $context );

		return $this->wrap( $field, $html, $context );
	}

	/**
	 * Validate the submitted number.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string|null Error message or null.
	 */
	public function validate( mixed $value, array $field ): ?string {
		$scalar_error = $this->validate_scalar( $value, $field );
		if ( null !== $scalar_error ) {
			return $scalar_error;
		}

		$required_error = $this->validate_required( $value, $field );
		if ( null !== $required_error ) {
			return $required_error;
		}

		if ( $this->is_empty( $value ) ) {
			return null;
		}

		if ( ! is_numeric( $value ) ) {
			/* translators: %s: field label */
			return sprintf( __( '%s must be a number.', 'samplehq-request-form' ), $field['label'] ?? 'This field' );
		}

		$validation = $field['validation'] ?? [];
		$num_value  = (float) $value;

		if ( isset( $validation['min'] ) && $num_value < (float) $validation['min'] ) {
			/* translators: 1: field label, 2: minimum value */
			return sprintf( __( '%1$s must be at least %2$s.', 'samplehq-request-form' ), $field['label'] ?? 'This field', $validation['min'] );
		}

		if ( isset( $validation['max'] ) && $num_value > (float) $validation['max'] ) {
			/* translators: 1: field label, 2: maximum value */
			return sprintf( __( '%1$s must be at most %2$s.', 'samplehq-request-form' ), $field['label'] ?? 'This field', $validation['max'] );
		}

		return null;
	}

	/**
	 * Sanitize the submitted number.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string Sanitized numeric string (or empty string if not numeric).
	 */
	public function sanitize( mixed $value, array $field ): string {
		$str = sanitize_text_field( (string) $value );

		return is_numeric( $str ) ? $str : '';
	}
}
