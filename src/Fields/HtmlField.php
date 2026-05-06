<?php
/**
 * HTML content field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display-only HTML content block.
 *
 * Renders sanitized HTML content in the form. No input, no validation,
 * no submitted data. Used for instructions, section headings, or legal text.
 */
class HtmlField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'html';
	}

	/**
	 * Render the HTML content block.
	 *
	 * Content is sanitized with wp_kses_post (allows safe HTML like p, a, strong, em).
	 *
	 * @param array<string, mixed> $field   Field configuration with 'content' key.
	 * @param mixed                $value   Ignored (no input).
	 * @param array<string, mixed> $context Render context.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string {
		$content = wp_kses_post( $field['content'] ?? '' );

		if ( '' === $content ) {
			return '';
		}

		$width_class = $this->get_width_class( $field );

		return '<div class="shqf-field ' . $width_class . ' shqf-field--html">'
			. '<div class="shqf-html-content">' . $content . '</div>'
			. '</div>';
	}

	/**
	 * Validate -- HTML fields have no input to validate.
	 *
	 * @param mixed                $value Ignored.
	 * @param array<string, mixed> $field Ignored.
	 * @return string|null Always null.
	 */
	public function validate( mixed $value, array $field ): ?string {
		return null;
	}

	/**
	 * Sanitize -- HTML fields submit no data.
	 *
	 * @param mixed                $value Ignored.
	 * @param array<string, mixed> $field Ignored.
	 * @return string Always empty string.
	 */
	public function sanitize( mixed $value, array $field ): string {
		return '';
	}
}
