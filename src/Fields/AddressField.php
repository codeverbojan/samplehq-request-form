<?php
/**
 * Address composite field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composite address field: street, city, state, zip, country.
 *
 * Each sub-field has the correct autocomplete attribute per WCAG.
 * Value stored as an associative array.
 */
class AddressField extends AbstractField {

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'address';
	}

	/**
	 * Address sub-fields definition.
	 *
	 * @return array<int, array{key: string, label: string, autocomplete: string, width: string}>
	 */
	private function get_sub_fields(): array {
		return [
			[
				'key'          => 'street',
				'label'        => __( 'Street Address', 'samplehq-request-form' ),
				'autocomplete' => 'street-address',
				'width'        => 'full',
			],
			[
				'key'          => 'city',
				'label'        => __( 'City', 'samplehq-request-form' ),
				'autocomplete' => 'address-level2',
				'width'        => 'half',
			],
			[
				'key'          => 'state',
				'label'        => __( 'State / Province', 'samplehq-request-form' ),
				'autocomplete' => 'address-level1',
				'width'        => 'half',
			],
			[
				'key'          => 'zip',
				'label'        => __( 'ZIP / Postal Code', 'samplehq-request-form' ),
				'autocomplete' => 'postal-code',
				'width'        => 'half',
			],
			[
				'key'          => 'country',
				'label'        => __( 'Country', 'samplehq-request-form' ),
				'autocomplete' => 'country-name',
				'width'        => 'half',
			],
		];
	}

	/**
	 * Render the address field HTML.
	 *
	 * @param array<string, mixed> $field   Field configuration.
	 * @param mixed                $value   Current value (array with sub-field keys).
	 * @param array<string, mixed> $context Render context.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string {
		$base_id   = $this->get_input_id( $field, $context );
		$base_name = 'shqf_fields[' . esc_attr( $field['key'] ?? 'address' ) . ']';
		$values    = is_array( $value ) ? $value : [];
		$attrs     = '';

		if ( ! empty( $field['required'] ) ) {
			$attrs = ' aria-required="true"';
		}

		$html  = '<fieldset class="shqf-fieldset shqf-fieldset--address"' . $attrs;
		$html .= ' aria-describedby="' . esc_attr( $this->get_error_id( $field, $context ) ) . '">';
		$html .= '<legend class="shqf-legend">' . esc_html( $field['label'] ?? '' );

		if ( ! empty( $field['required'] ) ) {
			$html .= ' <span class="shqf-required" aria-hidden="true">*</span>';
		}

		$html .= '</legend>';
		$html .= '<div class="shqf-address-fields">';

		foreach ( $this->get_sub_fields() as $sub ) {
			$sub_id    = $base_id . '-' . $sub['key'];
			$sub_value = $values[ $sub['key'] ] ?? '';
			$width_cls = 'half' === $sub['width'] ? 'shqf-address-field--half' : 'shqf-address-field--full';

			$html .= '<div class="shqf-address-field ' . $width_cls . '">';
			$html .= '<label for="' . esc_attr( $sub_id ) . '">' . esc_html( $sub['label'] ) . '</label>';
			$html .= '<input type="text" id="' . esc_attr( $sub_id ) . '"';
			$html .= ' name="' . $base_name . '[' . esc_attr( $sub['key'] ) . ']"';
			$html .= ' value="' . esc_attr( (string) $sub_value ) . '"';
			$html .= ' autocomplete="' . esc_attr( $sub['autocomplete'] ) . '"';
			$html .= ' class="shqf-input" />';
			$html .= '</div>';
		}

		$html .= '</div>';
		$html .= $this->render_error( $field, $context );
		$html .= '</fieldset>';

		return $this->wrap( $field, $html, $context );
	}

	/**
	 * Validate the address field.
	 *
	 * When required, at least street and city must be filled.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return string|null Error message or null.
	 */
	public function validate( mixed $value, array $field ): ?string {
		$values = is_array( $value ) ? $value : [];

		if ( ! empty( $field['required'] ) ) {
			$street = trim( (string) ( $values['street'] ?? '' ) );
			$city   = trim( (string) ( $values['city'] ?? '' ) );

			if ( '' === $street || '' === $city ) {
				/* translators: %s: field label */
				return sprintf( __( '%s is required. Please provide at least street and city.', 'samplehq-request-form' ), $field['label'] ?? 'Address' );
			}
		}

		return null;
	}

	/**
	 * Sanitize the address field values.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return array{street: string, city: string, state: string, zip: string, country: string}
	 */
	public function sanitize( mixed $value, array $field ): array {
		$values = is_array( $value ) ? $value : [];

		return [
			'street'  => sanitize_text_field( (string) ( $values['street'] ?? '' ) ),
			'city'    => sanitize_text_field( (string) ( $values['city'] ?? '' ) ),
			'state'   => sanitize_text_field( (string) ( $values['state'] ?? '' ) ),
			'zip'     => sanitize_text_field( (string) ( $values['zip'] ?? '' ) ),
			'country' => sanitize_text_field( (string) ( $values['country'] ?? '' ) ),
		];
	}
}
