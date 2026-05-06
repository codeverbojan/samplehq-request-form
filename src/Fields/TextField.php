<?php
/**
 * Text field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Standard single-line text input field.
 */
class TextField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'text';
	}

	/**
	 * Render the text input HTML.
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
		if ( isset( $validation['min_length'] ) ) {
			$attrs .= ' minlength="' . esc_attr( (string) (int) $validation['min_length'] ) . '"';
		}
		if ( isset( $validation['max_length'] ) ) {
			$attrs .= ' maxlength="' . esc_attr( (string) (int) $validation['max_length'] ) . '"';
		}
		if ( ! empty( $validation['pattern'] ) ) {
			$attrs .= ' pattern="' . esc_attr( $validation['pattern'] ) . '"';
		}

		$html  = $this->render_label( $field, $context );
		$html .= '<input type="text" id="' . esc_attr( $id ) . '" name="' . $name . '"';
		$html .= ' value="' . esc_attr( (string) $value ) . '"' . $attrs;
		$html .= ' class="shqf-input shqf-input--text" />';
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

		if ( $this->is_empty( $value ) ) {
			return null;
		}

		$str        = (string) $value;
		$len        = mb_strlen( $str );
		$validation = $field['validation'] ?? [];
		$label      = $field['label'] ?? 'This field';
		$custom_msg = $validation['format_message'] ?? '';

		if ( isset( $validation['min_length'] ) && $len < (int) $validation['min_length'] ) {
			if ( '' !== $custom_msg ) {
				return $custom_msg;
			}
			/* translators: 1: field label, 2: min length */
			return sprintf( __( '%1$s must be at least %2$d characters.', 'samplehq-request-form' ), $label, (int) $validation['min_length'] );
		}

		if ( isset( $validation['max_length'] ) && $len > (int) $validation['max_length'] ) {
			if ( '' !== $custom_msg ) {
				return $custom_msg;
			}
			/* translators: 1: field label, 2: max length */
			return sprintf( __( '%1$s must be at most %2$d characters.', 'samplehq-request-form' ), $label, (int) $validation['max_length'] );
		}

		if ( ! empty( $validation['pattern'] ) ) {
			$pattern = $validation['pattern'];
			// Wrap in delimiters, escape slashes inside.
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- User-provided regex may be invalid; @ suppresses PHP warning, treats as non-match.
			if ( ! @preg_match( '/' . str_replace( '/', '\\/', $pattern ) . '/', $str ) ) {
				if ( '' !== $custom_msg ) {
					return $custom_msg;
				}
				/* translators: %s: field label */
				return sprintf( __( '%s does not match the required format.', 'samplehq-request-form' ), $label );
			}
		}

		return null;
	}

	/**
	 * Sanitize the submitted value.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string Sanitized string.
	 */
	public function sanitize( mixed $value, array $field ): string {
		return sanitize_text_field( (string) $value );
	}
}
