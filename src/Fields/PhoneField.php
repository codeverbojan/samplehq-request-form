<?php
/**
 * Phone field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Phone/telephone input field.
 */
class PhoneField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'phone';
	}

	/**
	 * Render the phone input HTML.
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

		$html  = $this->render_label( $field, $context );
		$html .= '<input type="tel" id="' . esc_attr( $id ) . '" name="' . $name . '"';
		$html .= ' value="' . esc_attr( (string) $value ) . '"' . $attrs;
		$html .= ' autocomplete="tel" class="shqf-input shqf-input--phone" />';
		$html .= $this->render_error( $field, $context );

		return $this->wrap( $field, $html, $context );
	}

	/**
	 * Validate the submitted phone number.
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

		return $this->validate_required( $value, $field );
	}

	/**
	 * Sanitize the submitted phone number.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string Sanitized phone string.
	 */
	public function sanitize( mixed $value, array $field ): string {
		return sanitize_text_field( (string) $value );
	}
}
