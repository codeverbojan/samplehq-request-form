<?php
/**
 * Email field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email input field with email validation.
 */
class EmailField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'email';
	}

	/**
	 * Render the email input HTML.
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
		$html .= '<input type="email" id="' . esc_attr( $id ) . '" name="' . $name . '"';
		$html .= ' value="' . esc_attr( (string) $value ) . '"' . $attrs;
		$html .= ' autocomplete="email" class="shqf-input shqf-input--email" />';
		$html .= $this->render_error( $field, $context );

		return $this->wrap( $field, $html, $context );
	}

	/**
	 * Validate the submitted email.
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

		if ( ! $this->is_empty( $value ) && ! is_email( (string) $value ) ) {
			/* translators: %s: field label */
			return sprintf( __( '%s must be a valid email address.', 'samplehq-request-form' ), $field['label'] ?? 'Email' );
		}

		return null;
	}

	/**
	 * Sanitize the submitted email.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string Sanitized email.
	 */
	public function sanitize( mixed $value, array $field ): string {
		return sanitize_email( (string) $value );
	}
}
