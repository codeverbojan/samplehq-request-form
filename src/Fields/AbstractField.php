<?php
/**
 * Abstract base for field types.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class with shared rendering helpers for field types.
 *
 * Provides consistent ADA-compliant HTML patterns: labels, error associations,
 * required indicators, and wrapper markup.
 */
abstract class AbstractField implements FieldInterface {

	/**
	 * Build the HTML ID for a field input.
	 *
	 * @param array<string, mixed> $field   Field config.
	 * @param array<string, mixed> $context Render context with field_prefix.
	 * @return string HTML-safe ID.
	 */
	protected function get_input_id( array $field, array $context = [] ): string {
		$prefix = $context['field_prefix'] ?? 'shqf';
		return esc_attr( $prefix . '-' . ( $field['id'] ?? $field['key'] ?? 'field' ) );
	}

	/**
	 * Build the HTML name attribute for a field input.
	 *
	 * @param array<string, mixed> $field Field config.
	 * @return string Name attribute value.
	 */
	protected function get_input_name( array $field ): string {
		return esc_attr( 'shqf_fields[' . ( $field['key'] ?? $field['id'] ?? '' ) . ']' );
	}

	/**
	 * Get the error ID for aria-describedby association.
	 *
	 * @param array<string, mixed> $field   Field config.
	 * @param array<string, mixed> $context Render context.
	 * @return string Error element ID.
	 */
	protected function get_error_id( array $field, array $context = [] ): string {
		return $this->get_input_id( $field, $context ) . '-error';
	}

	/**
	 * Render the label HTML.
	 *
	 * @param array<string, mixed> $field   Field config.
	 * @param array<string, mixed> $context Render context.
	 * @return string Label HTML.
	 */
	protected function render_label( array $field, array $context = [] ): string {
		$label    = esc_html( $field['label'] ?? '' );
		$for      = $this->get_input_id( $field, $context );
		$required = '';
		$display  = $context['display'] ?? [];

		if ( ! empty( $field['required'] ) ) {
			$required = ' <span class="shqf-required" aria-hidden="true">*</span>';
		}

		// Hidden label position: render as screen-reader-only for accessibility.
		$label_pos = $display['label_position'] ?? 'top';
		if ( 'hidden' === $label_pos ) {
			return '<label for="' . esc_attr( $for ) . '" class="shqf-label shqf-sr-only">' . $label . $required . '</label>';
		}

		return '<label for="' . esc_attr( $for ) . '" class="shqf-label">' . $label . $required . '</label>';
	}

	/**
	 * Render an inline error message container.
	 *
	 * The container is always rendered (for JS to populate), but only shows
	 * content when there's a server-side error.
	 *
	 * @param array<string, mixed> $field   Field config.
	 * @param array<string, mixed> $context Render context (may contain 'errors' array).
	 * @return string Error HTML.
	 */
	protected function render_error( array $field, array $context = [] ): string {
		$error_id = $this->get_error_id( $field, $context );
		$key      = $field['id'] ?? $field['key'] ?? '';
		$errors   = $context['errors'] ?? [];
		$message  = $errors[ $key ] ?? '';

		$html = '<div id="' . esc_attr( $error_id ) . '" class="shqf-error"';
		if ( empty( $message ) ) {
			$html .= ' style="display:none"';
		} else {
			$html .= ' role="alert"';
		}
		$html .= '>' . esc_html( $message ) . '</div>';

		return $html;
	}

	/**
	 * Build common input attributes.
	 *
	 * @param array<string, mixed> $field   Field config.
	 * @param array<string, mixed> $context Render context.
	 * @return string Attribute string.
	 */
	protected function build_common_attrs( array $field, array $context = [] ): string {
		$attrs   = '';
		$display = $context['display'] ?? [];

		if ( ! empty( $field['required'] ) ) {
			$attrs .= ' required aria-required="true"';
		}

		// Placeholder logic based on display mode.
		$placeholder_mode = $display['placeholder_mode'] ?? 'show';
		$placeholder      = $field['placeholder'] ?? '';
		$label            = $field['label'] ?? '';

		if ( 'placeholder_as_label' === $placeholder_mode ) {
			// Use the label text as placeholder (label is hidden via render_label).
			$ph_text = '' !== $placeholder ? $placeholder : $label;
			if ( '' !== $ph_text ) {
				$attrs .= ' placeholder="' . esc_attr( $ph_text ) . '"';
			}
		} elseif ( 'label_only' !== $placeholder_mode && '' !== $placeholder ) {
			// Default: show placeholder if set.
			$attrs .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		}

		// Aria-describedby for error association.
		$attrs .= ' aria-describedby="' . esc_attr( $this->get_error_id( $field, $context ) ) . '"';

		return $attrs;
	}

	/**
	 * Get the CSS class for the field width.
	 *
	 * @param array<string, mixed> $field Field config.
	 * @return string CSS class.
	 */
	protected function get_width_class( array $field ): string {
		$width = $field['width'] ?? 'full';
		return 'half' === $width ? 'shqf-field--half' : 'shqf-field--full';
	}

	/**
	 * Wrap field content in a standard wrapper div.
	 *
	 * @param array<string, mixed> $field   Field config.
	 * @param string               $content Inner HTML (label + input + error).
	 * @param array<string, mixed> $context Render context (optional, for display settings).
	 * @return string Wrapped HTML.
	 */
	protected function wrap( array $field, string $content, array $context = [] ): string {
		$width_class = $this->get_width_class( $field );
		$type_class  = 'shqf-field--' . esc_attr( $this->get_type() );
		$conditions  = '';

		// Display classes based on form-level display settings.
		$display       = $context['display'] ?? [];
		$label_pos     = $display['label_position'] ?? 'top';
		$display_class = '';
		if ( 'left' === $label_pos ) {
			$display_class = ' shqf-field--label-left';
		} elseif ( 'hidden' === $label_pos ) {
			$display_class = ' shqf-field--label-hidden';
		}

		// Output conditional logic rules as a data attribute for frontend JS.
		if ( ! empty( $field['conditions']['rules'] ) ) {
			$conditions = ' data-conditions="' . esc_attr( (string) wp_json_encode( $field['conditions'] ) ) . '"';
		}

		$key_attr = '';
		if ( ! empty( $field['key'] ) ) {
			$key_attr = ' data-field-key="' . esc_attr( $field['key'] ) . '"';
		}

		// Custom format error message for JS validation.
		$format_msg_attr = '';
		$format_msg      = $field['validation']['format_message'] ?? '';
		if ( '' !== $format_msg ) {
			$format_msg_attr = ' data-format-message="' . esc_attr( $format_msg ) . '"';
		}

		// Description / help text below the input.
		$description = '';
		if ( ! empty( $field['description'] ) ) {
			$description = '<small class="shqf-help-text">' . esc_html( $field['description'] ) . '</small>';
		}

		return '<div class="shqf-field ' . $width_class . ' ' . $type_class . $display_class . '"' . $conditions . $key_attr . $format_msg_attr . '>' . $content . $description . '</div>';
	}

	/**
	 * Check if a value is empty for required validation.
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if empty.
	 */
	protected function is_empty( mixed $value ): bool {
		if ( null === $value ) {
			return true;
		}

		if ( is_string( $value ) ) {
			return '' === trim( $value );
		}

		if ( is_array( $value ) ) {
			return empty( $value );
		}

		return false;
	}

	/**
	 * Default required field validation.
	 *
	 * Subclasses should call this first, then add type-specific checks.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string|null Error message or null.
	 */
	protected function validate_required( mixed $value, array $field ): ?string {
		if ( ! empty( $field['required'] ) && $this->is_empty( $value ) ) {
			// Custom required message override.
			$custom = $field['validation']['required_message'] ?? '';
			if ( '' !== $custom ) {
				return $custom;
			}
			$label = $field['label'] ?? 'This field';
			/* translators: %s: field label */
			return sprintf( __( '%s is required.', 'samplehq-request-form' ), $label );
		}

		return null;
	}

	/**
	 * Validate that a value is a scalar (string or numeric), not an array.
	 *
	 * Rejects array input on fields that expect string values, preventing
	 * data corruption where PHP casts arrays to the string "Array".
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string|null Error message if invalid, null if valid.
	 */
	protected function validate_scalar( mixed $value, array $field ): ?string {
		if ( is_array( $value ) ) {
			/* translators: %s: field label */
			return sprintf( __( '%s has an invalid value.', 'samplehq-request-form' ), $field['label'] ?? 'This field' );
		}

		return null;
	}
}
