<?php
/**
 * File upload field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File upload field that stores a WP attachment ID.
 *
 * The frontend uploads the file via a separate AJAX endpoint
 * (POST /samplehq-form/v1/upload), which returns the attachment ID.
 * That ID is submitted as the field value in the regular form submission.
 *
 * Config options:
 * - allowed_types: array of MIME types (default: image/jpeg, image/png, application/pdf)
 * - max_size_mb: max file size in MB (default: 5)
 */
class FileUploadField extends AbstractField {

	/**
	 * Default allowed MIME types.
	 *
	 * @var string[]
	 */
	private const DEFAULT_ALLOWED_TYPES = [
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'application/pdf',
	];

	/**
	 * Post meta key used to tag uploads from this plugin.
	 *
	 * @var string
	 */
	public const META_FORM_ID = '_shqf_form_id';

	/**
	 * Default max file size in MB.
	 *
	 * @var int
	 */
	private const DEFAULT_MAX_SIZE_MB = 5;

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'file_upload';
	}

	/**
	 * Render the file upload input.
	 *
	 * @param array<string, mixed> $field   Field configuration.
	 * @param mixed                $value   Current value (attachment ID).
	 * @param array<string, mixed> $context Render context.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string {
		$id      = $this->get_input_id( $field, $context );
		$name    = $this->get_input_name( $field );
		$max_mb  = (int) ( $field['validation']['max_size_mb'] ?? self::DEFAULT_MAX_SIZE_MB );
		$allowed = $field['validation']['allowed_types'] ?? self::DEFAULT_ALLOWED_TYPES;
		$accept  = $this->mime_to_accept( $allowed );
		$attrs   = '';

		if ( ! empty( $field['required'] ) ) {
			$attrs .= ' required aria-required="true"';
		}

		$attrs .= ' aria-describedby="' . esc_attr( $this->get_error_id( $field, $context ) ) . '"';

		$html  = $this->render_label( $field, $context );
		$html .= '<input type="file" id="' . esc_attr( $id ) . '" name="' . $name . '"';
		$html .= ' accept="' . esc_attr( $accept ) . '"' . $attrs;
		$html .= ' data-max-size="' . esc_attr( (string) ( $max_mb * 1048576 ) ) . '"';
		$html .= ' class="shqf-input shqf-input--file" />';
		$html .= '<input type="hidden" name="' . $name . '_id" id="' . esc_attr( $id ) . '-id" value="' . esc_attr( (string) $value ) . '" />';
		$html .= '<p class="shqf-file-info">';
		$html .= esc_html(
			sprintf(
			/* translators: 1: allowed file types, 2: max file size in MB */
				__( 'Allowed: %1$s. Max size: %2$d MB.', 'samplehq-request-form' ),
				implode( ', ', $this->mime_to_extensions( $allowed ) ),
				$max_mb
			)
		);
		$html .= '</p>';
		$html .= $this->render_error( $field, $context );

		return $this->wrap( $field, $html, $context );
	}

	/**
	 * Validate the submitted value (attachment ID).
	 *
	 * @param mixed                $value Submitted value (attachment ID or empty).
	 * @param array<string, mixed> $field Field configuration.
	 * @return string|null Error message or null.
	 */
	public function validate( mixed $value, array $field ): ?string {
		$scalar_error = $this->validate_scalar( $value, $field );
		if ( null !== $scalar_error ) {
			return $scalar_error;
		}

		$str = trim( (string) $value );

		if ( ! empty( $field['required'] ) && ( '' === $str || '0' === $str ) ) {
			$label = $field['label'] ?? 'This field';
			/* translators: %s: field label */
			return sprintf( __( '%s is required.', 'samplehq-request-form' ), $label );
		}

		if ( '' === $str ) {
			return null;
		}

		// Must be a positive integer (attachment ID).
		if ( ! ctype_digit( $str ) || '0' === $str ) {
			/* translators: %s: field label */
			return sprintf( __( '%s has an invalid value.', 'samplehq-request-form' ), $field['label'] ?? 'This field' );
		}

		return null;
	}

	/**
	 * Sanitize the submitted value.
	 *
	 * @param mixed                $value Submitted value (attachment ID).
	 * @param array<string, mixed> $field Field configuration.
	 * @return string Sanitized attachment ID or empty.
	 */
	public function sanitize( mixed $value, array $field ): string {
		$id = absint( $value );
		return $id > 0 ? (string) $id : '';
	}

	/**
	 * Convert MIME types to accept attribute value.
	 *
	 * @param string[] $types MIME types.
	 * @return string Accept attribute value.
	 */
	private function mime_to_accept( array $types ): string {
		return implode( ',', $types );
	}

	/**
	 * Convert MIME types to human-readable extensions.
	 *
	 * @param string[] $types MIME types.
	 * @return string[] Extensions.
	 */
	private function mime_to_extensions( array $types ): array {
		$map = [
			'image/jpeg'      => 'JPG',
			'image/png'       => 'PNG',
			'image/gif'       => 'GIF',
			'image/webp'      => 'WebP',
			'application/pdf' => 'PDF',
		];

		$extensions = [];
		foreach ( $types as $type ) {
			$extensions[] = $map[ $type ] ?? strtoupper( explode( '/', $type )[1] ?? $type );
		}

		return $extensions;
	}
}
