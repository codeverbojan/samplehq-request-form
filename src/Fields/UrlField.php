<?php
/**
 * URL field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL input field with protocol validation.
 */
class UrlField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'url';
	}

	/**
	 * Render the URL input HTML.
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
		$html .= '<input type="url" id="' . esc_attr( $id ) . '" name="' . $name . '"';
		$html .= ' value="' . esc_attr( (string) $value ) . '"' . $attrs;
		$html .= ' autocomplete="url"';
		$html .= ' class="shqf-input shqf-input--url" />';
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

		if ( ! filter_var( $str, FILTER_VALIDATE_URL ) ) {
			/* translators: %s: field label */
			return sprintf( __( '%s must be a valid URL.', 'samplehq-request-form' ), $field['label'] ?? 'This field' );
		}

		return null;
	}

	/**
	 * Sanitize the submitted value.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string Sanitized URL.
	 */
	public function sanitize( mixed $value, array $field ): string {
		return esc_url_raw( (string) $value );
	}
}
