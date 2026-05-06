<?php
/**
 * Hidden field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hidden input field for passing data without user interaction.
 *
 * No label, no wrapper, no validation. Value comes from config (default_value).
 */
class HiddenField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'hidden';
	}

	/**
	 * Render a hidden input.
	 *
	 * No label, no wrapper div, no error container.
	 *
	 * @param array<string, mixed> $field   Field configuration.
	 * @param mixed                $value   Current value.
	 * @param array<string, mixed> $context Render context.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string {
		$name    = $this->get_input_name( $field );
		$out_val = '' !== (string) $value ? (string) $value : (string) ( $field['default_value'] ?? '' );

		return '<input type="hidden" name="' . $name . '" value="' . esc_attr( $out_val ) . '" />';
	}

	/**
	 * Validate -- hidden fields skip validation.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string|null Always null.
	 */
	public function validate( mixed $value, array $field ): ?string {
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
