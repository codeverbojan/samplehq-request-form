<?php
/**
 * Select/dropdown field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dropdown select field with predefined options.
 */
class SelectField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'select';
	}

	/**
	 * Render the select HTML.
	 *
	 * @param array<string, mixed> $field   Field configuration with 'options' array.
	 * @param mixed                $value   Current selected value.
	 * @param array<string, mixed> $context Render context.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string {
		$id      = $this->get_input_id( $field, $context );
		$name    = $this->get_input_name( $field );
		$attrs   = $this->build_common_attrs( $field, $context );
		$options = $field['options'] ?? [];

		$html  = $this->render_label( $field, $context );
		$html .= '<select id="' . esc_attr( $id ) . '" name="' . $name . '"' . $attrs;
		$html .= ' class="shqf-input shqf-input--select">';

		// Placeholder option.
		$placeholder = $field['placeholder'] ?? __( 'Select an option', 'samplehq-request-form' );
		$html       .= '<option value="">' . esc_html( $placeholder ) . '</option>';

		foreach ( $options as $option ) {
			$opt_value = $option['value'] ?? '';
			$opt_label = $option['label'] ?? $opt_value;
			$selected  = selected( (string) $value, (string) $opt_value, false );
			$html     .= '<option value="' . esc_attr( $opt_value ) . '"' . $selected . '>' . esc_html( $opt_label ) . '</option>';
		}

		$html .= '</select>';
		$html .= $this->render_error( $field, $context );

		return $this->wrap( $field, $html, $context );
	}

	/**
	 * Validate the submitted value is one of the allowed options.
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

		$valid_values = array_column( $field['options'] ?? [], 'value' );
		if ( ! in_array( (string) $value, array_map( 'strval', $valid_values ), true ) ) {
			/* translators: %s: field label */
			return sprintf( __( '%s contains an invalid selection.', 'samplehq-request-form' ), $field['label'] ?? 'This field' );
		}

		return null;
	}

	/**
	 * Sanitize the submitted value.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string Sanitized value.
	 */
	public function sanitize( mixed $value, array $field ): string {
		return sanitize_text_field( (string) $value );
	}
}
