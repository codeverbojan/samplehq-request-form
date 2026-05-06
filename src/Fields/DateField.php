<?php
/**
 * Date field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML5 date input field (YYYY-MM-DD).
 */
class DateField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'date';
	}

	/**
	 * Render the date input HTML.
	 *
	 * @param array<string, mixed> $field   Field configuration.
	 * @param mixed                $value   Current value (YYYY-MM-DD string).
	 * @param array<string, mixed> $context Render context.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string {
		$id    = $this->get_input_id( $field, $context );
		$name  = $this->get_input_name( $field );
		$attrs = $this->build_common_attrs( $field, $context );

		$min = $field['validation']['min'] ?? '';
		$max = $field['validation']['max'] ?? '';

		if ( '' !== $min ) {
			$attrs .= ' min="' . esc_attr( $min ) . '"';
		}
		if ( '' !== $max ) {
			$attrs .= ' max="' . esc_attr( $max ) . '"';
		}

		$html  = $this->render_label( $field, $context );
		$html .= '<input type="date" id="' . esc_attr( $id ) . '" name="' . $name . '"';
		$html .= ' value="' . esc_attr( (string) $value ) . '"' . $attrs;
		$html .= ' class="shqf-input shqf-input--date" />';
		$html .= $this->render_error( $field, $context );

		return $this->wrap( $field, $html, $context );
	}

	/**
	 * Validate the submitted value.
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

		$str = trim( (string) $value );
		if ( '' === $str ) {
			return null;
		}

		// Must be YYYY-MM-DD and a real calendar date.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $str ) ) {
			/* translators: %s: field label */
			return sprintf( __( '%s must be a valid date.', 'samplehq-request-form' ), $field['label'] ?? 'This field' );
		}

		$parts = explode( '-', $str );
		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			/* translators: %s: field label */
			return sprintf( __( '%s must be a valid date.', 'samplehq-request-form' ), $field['label'] ?? 'This field' );
		}

		return null;
	}

	/**
	 * Sanitize the submitted value.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string Sanitized date string (YYYY-MM-DD or empty).
	 */
	public function sanitize( mixed $value, array $field ): string {
		$str = sanitize_text_field( (string) $value );

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $str ) ) {
			return $str;
		}

		return '';
	}
}
