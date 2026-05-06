<?php
/**
 * Checkbox field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkbox group field wrapped in fieldset/legend for ADA compliance.
 *
 * Supports multiple selections. Values are stored as an array.
 */
class CheckboxField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'checkbox';
	}

	/**
	 * Render the checkbox group HTML.
	 *
	 * Uses fieldset/legend per WCAG for grouped checkboxes.
	 *
	 * @param array<string, mixed> $field   Field configuration with 'options' array.
	 * @param mixed                $value   Current selected values (array or string).
	 * @param array<string, mixed> $context Render context.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string {
		$name     = $this->get_input_name( $field );
		$prefix   = $this->get_input_id( $field, $context );
		$options  = $field['options'] ?? [];
		$selected = is_array( $value ) ? $value : ( ! $this->is_empty( $value ) ? [ $value ] : [] );
		$attrs    = '';

		if ( ! empty( $field['required'] ) ) {
			$attrs = ' aria-required="true"';
		}

		$html  = '<fieldset class="shqf-fieldset"' . $attrs;
		$html .= ' aria-describedby="' . esc_attr( $this->get_error_id( $field, $context ) ) . '">';
		$html .= '<legend class="shqf-legend">' . esc_html( $field['label'] ?? '' );

		if ( ! empty( $field['required'] ) ) {
			$html .= ' <span class="shqf-required" aria-hidden="true">*</span>';
		}

		$html .= '</legend>';

		// Choice layout classes from config.
		$choice_config  = $field['config'] ?? [];
		$choice_layout  = $choice_config['choice_layout'] ?? 'vertical';
		$choice_columns = (int) ( $choice_config['choice_columns'] ?? 1 );
		$items_class    = 'shqf-checkbox-items';
		if ( 'horizontal' === $choice_layout ) {
			$items_class .= ' shqf-choices--horizontal';
		} elseif ( $choice_columns > 1 ) {
			$items_class .= ' shqf-choices--cols-' . min( $choice_columns, 3 );
		}

		$html .= '<div class="' . esc_attr( $items_class ) . '">';

		foreach ( $options as $index => $option ) {
			$opt_value = (string) ( $option['value'] ?? '' );
			$opt_label = $option['label'] ?? $opt_value;
			$opt_id    = $prefix . '-' . $index;
			$checked   = in_array( $opt_value, array_map( 'strval', $selected ), true ) ? ' checked' : '';

			$html .= '<div class="shqf-checkbox-item">';
			$html .= '<input type="checkbox" id="' . esc_attr( $opt_id ) . '" name="' . $name . '[]"';
			$html .= ' value="' . esc_attr( $opt_value ) . '"' . $checked . ' />';
			$html .= '<label for="' . esc_attr( $opt_id ) . '">' . esc_html( $opt_label ) . '</label>';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= $this->render_error( $field, $context );
		$html .= '</fieldset>';

		return $this->wrap( $field, $html, $context );
	}

	/**
	 * Validate the submitted values are among allowed options.
	 *
	 * @param mixed                $value Submitted values (array or string).
	 * @param array<string, mixed> $field Field configuration.
	 * @return string|null Error message or null.
	 */
	public function validate( mixed $value, array $field ): ?string {
		$values = is_array( $value ) ? $value : ( ! $this->is_empty( $value ) ? [ $value ] : [] );

		if ( ! empty( $field['required'] ) && empty( $values ) ) {
			$label = $field['label'] ?? 'This field';
			/* translators: %s: field label */
			return sprintf( __( '%s is required.', 'samplehq-request-form' ), $label );
		}

		if ( empty( $values ) ) {
			return null;
		}

		$valid_values = array_map( 'strval', array_column( $field['options'] ?? [], 'value' ) );
		foreach ( $values as $v ) {
			if ( ! in_array( (string) $v, $valid_values, true ) ) {
				/* translators: %s: field label */
				return sprintf( __( '%s contains an invalid selection.', 'samplehq-request-form' ), $field['label'] ?? 'This field' );
			}
		}

		return null;
	}

	/**
	 * Sanitize the submitted values.
	 *
	 * @param mixed                $value Submitted values.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string[] Sanitized array of values.
	 */
	public function sanitize( mixed $value, array $field ): array {
		$values = is_array( $value ) ? $value : ( ! empty( $value ) ? [ $value ] : [] );

		return array_map( 'sanitize_text_field', $values );
	}
}
