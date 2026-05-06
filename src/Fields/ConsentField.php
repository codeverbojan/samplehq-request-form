<?php
/**
 * Consent/GDPR checkbox field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single required consent checkbox with configurable label.
 *
 * Used for GDPR consent, terms acceptance, privacy policy agreement.
 * Always required -- consent must be explicitly given.
 * Stores "1" when checked.
 */
class ConsentField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'consent';
	}

	/**
	 * Render the consent checkbox.
	 *
	 * The consent_text config key supports safe HTML (links to privacy policy, etc.)
	 * sanitized via wp_kses_post.
	 *
	 * @param array<string, mixed> $field   Field configuration with 'consent_text' key.
	 * @param mixed                $value   Current value ("1" if checked).
	 * @param array<string, mixed> $context Render context.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string {
		$id      = $this->get_input_id( $field, $context );
		$name    = $this->get_input_name( $field );
		$checked = '1' === (string) $value ? ' checked' : '';

		// Consent text can contain safe HTML (links, etc.).
		$consent_text = wp_kses_post( $field['consent_text'] ?? $field['label'] ?? '' );

		$html  = '<div class="shqf-consent-item">';
		$html .= '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . $name . '"';
		$html .= ' value="1"' . $checked;
		$html .= ' required aria-required="true"';
		$html .= ' aria-describedby="' . esc_attr( $this->get_error_id( $field, $context ) ) . '" />';
		$html .= '<label for="' . esc_attr( $id ) . '" class="shqf-consent-label">';
		$html .= $consent_text;
		$html .= ' <span class="shqf-required" aria-hidden="true">*</span>';
		$html .= '</label>';
		$html .= '</div>';
		$html .= $this->render_error( $field, $context );

		return $this->wrap( $field, $html, $context );
	}

	/**
	 * Validate that consent was given.
	 *
	 * Consent fields are always required.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string|null Error message or null.
	 */
	public function validate( mixed $value, array $field ): ?string {
		if ( '1' !== (string) $value ) {
			$label = $field['label'] ?? __( 'Consent', 'samplehq-request-form' );
			/* translators: %s: field label */
			return sprintf( __( '%s is required.', 'samplehq-request-form' ), $label );
		}

		return null;
	}

	/**
	 * Sanitize the submitted value.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string "1" if checked, "" if not.
	 */
	public function sanitize( mixed $value, array $field ): string {
		return '1' === (string) $value ? '1' : '';
	}
}
