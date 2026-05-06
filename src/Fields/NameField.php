<?php
/**
 * Name composite field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composite name field: first name + last name in a fieldset.
 *
 * Stores value as an associative array: ['first_name' => '...', 'last_name' => '...'].
 */
class NameField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'name';
	}

	/**
	 * Render the name field HTML.
	 *
	 * Two inputs in a fieldset with autocomplete attributes.
	 *
	 * @param array<string, mixed> $field   Field configuration.
	 * @param mixed                $value   Current value (array with first_name, last_name).
	 * @param array<string, mixed> $context Render context.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string {
		$base_id    = $this->get_input_id( $field, $context );
		$base_name  = 'shqf_fields[' . esc_attr( $field['key'] ?? 'name' ) . ']';
		$values     = is_array( $value ) ? $value : [];
		$first_name = $values['first_name'] ?? '';
		$last_name  = $values['last_name'] ?? '';
		$attrs      = '';

		if ( ! empty( $field['required'] ) ) {
			$attrs = ' aria-required="true"';
		}

		$html  = '<fieldset class="shqf-fieldset shqf-fieldset--name"' . $attrs;
		$html .= ' aria-describedby="' . esc_attr( $this->get_error_id( $field, $context ) ) . '">';
		$html .= '<legend class="shqf-legend">' . esc_html( $field['label'] ?? '' );

		if ( ! empty( $field['required'] ) ) {
			$html .= ' <span class="shqf-required" aria-hidden="true">*</span>';
		}

		$html .= '</legend>';
		$html .= '<div class="shqf-name-fields">';

		// First name.
		$html .= '<div class="shqf-name-field shqf-name-field--first">';
		$html .= '<label for="' . esc_attr( $base_id . '-first' ) . '">' . esc_html__( 'First Name', 'samplehq-request-form' ) . '</label>';
		$html .= '<input type="text" id="' . esc_attr( $base_id . '-first' ) . '"';
		$html .= ' name="' . $base_name . '[first_name]"';
		$html .= ' value="' . esc_attr( (string) $first_name ) . '"';
		$html .= ' autocomplete="given-name"';
		if ( ! empty( $field['required'] ) ) {
			$html .= ' required';
		}
		$html .= ' class="shqf-input" />';
		$html .= '</div>';

		// Last name.
		$html .= '<div class="shqf-name-field shqf-name-field--last">';
		$html .= '<label for="' . esc_attr( $base_id . '-last' ) . '">' . esc_html__( 'Last Name', 'samplehq-request-form' ) . '</label>';
		$html .= '<input type="text" id="' . esc_attr( $base_id . '-last' ) . '"';
		$html .= ' name="' . $base_name . '[last_name]"';
		$html .= ' value="' . esc_attr( (string) $last_name ) . '"';
		$html .= ' autocomplete="family-name"';
		if ( ! empty( $field['required'] ) ) {
			$html .= ' required';
		}
		$html .= ' class="shqf-input" />';
		$html .= '</div>';

		$html .= '</div>';
		$html .= $this->render_error( $field, $context );
		$html .= '</fieldset>';

		return $this->wrap( $field, $html, $context );
	}

	/**
	 * Validate the name field.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string|null Error message or null.
	 */
	public function validate( mixed $value, array $field ): ?string {
		$values = is_array( $value ) ? $value : [];

		if ( ! empty( $field['required'] ) ) {
			$first = trim( (string) ( $values['first_name'] ?? '' ) );
			$last  = trim( (string) ( $values['last_name'] ?? '' ) );

			if ( '' === $first || '' === $last ) {
				/* translators: %s: field label */
				return sprintf( __( '%s is required.', 'samplehq-request-form' ), $field['label'] ?? 'Name' );
			}
		}

		return null;
	}

	/**
	 * Sanitize the name field values.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return array{first_name: string, last_name: string} Sanitized name parts.
	 */
	public function sanitize( mixed $value, array $field ): array {
		$values = is_array( $value ) ? $value : [];

		return [
			'first_name' => sanitize_text_field( (string) ( $values['first_name'] ?? '' ) ),
			'last_name'  => sanitize_text_field( (string) ( $values['last_name'] ?? '' ) ),
		];
	}
}
