<?php
/**
 * File upload REST endpoint.
 *
 * @package SampleHQForm\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\FormsTable;
use SampleHQForm\Database\RateLimitsTable;
use SampleHQForm\Forms\FormValidator;
use SampleHQForm\Spam\FormToken;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Handles file uploads for form file_upload fields.
 *
 * POST /samplehq-form/v1/upload
 *
 * Security layers:
 * - Form token validation (CSRF)
 * - IP rate limiting
 * - Honeypot check
 * - Server-side MIME verification via finfo
 * - File size limits from field config
 * - Protected upload directory with .htaccess deny-all
 * - No direct URL returned (attachment ID only)
 * - Attachment tagged with _shqf_pending for orphan cleanup
 */
class UploadEndpoint {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const NAMESPACE = 'samplehq-form/v1';

	/**
	 * Forms repository.
	 *
	 * @var FormsTable
	 */
	private FormsTable $forms;

	/**
	 * Form token validator.
	 *
	 * @var FormToken
	 */
	private FormToken $token;

	/**
	 * Rate limits repository.
	 *
	 * @var RateLimitsTable
	 */
	private RateLimitsTable $rate_limits;

	/**
	 * Constructor.
	 *
	 * @param FormsTable      $forms       Forms repository.
	 * @param FormToken       $token       Token validator.
	 * @param RateLimitsTable $rate_limits Rate limits repository.
	 */
	public function __construct( FormsTable $forms, FormToken $token, RateLimitsTable $rate_limits ) {
		$this->forms       = $forms;
		$this->token       = $token;
		$this->rate_limits = $rate_limits;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the upload route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/upload',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_upload' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'form_id'    => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ): bool {
							return is_numeric( $value ) && (int) $value > 0;
						},
					],
					'shqf_token' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'shqf_hp'    => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Handle a file upload.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The response.
	 */
	public function handle_upload( WP_REST_Request $request ): WP_REST_Response {
		$form_id = absint( $request->get_param( 'form_id' ) );

		if ( 0 === $form_id ) {
			return new WP_REST_Response( [ 'message' => 'Missing form_id.' ], 400 );
		}

		// Honeypot check -- silently reject bots.
		$honeypot = $request->get_param( 'shqf_hp' );
		if ( ! empty( $honeypot ) ) {
			return new WP_REST_Response( [ 'attachment_id' => wp_rand( 1000, 9999 ) ], 200 );
		}

		// Token validation (CSRF) -- peek without consuming so the token
		// remains valid for the subsequent form submission.
		$token_value = $request->get_param( 'shqf_token' );
		if ( empty( $token_value ) || ! $this->token->peek( $form_id, $token_value ) ) {
			return new WP_REST_Response( [ 'message' => 'Invalid or expired session.' ], 403 );
		}

		// Rate limiting.
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( ! current_user_can( 'manage_options' ) && ! $this->rate_limits->check_and_increment( $ip, $form_id ) ) {
			$response = new WP_REST_Response( [ 'message' => 'Too many requests.' ], 429 );
			$response->header( 'Retry-After', '3600' );
			return $response;
		}

		$form = $this->forms->get( $form_id );
		if ( null === $form || 'published' !== ( $form['status'] ?? '' ) ) {
			return new WP_REST_Response( [ 'message' => 'Form not found.' ], 404 );
		}

		$file_field_config = $this->find_file_field_config( $form['config'] ?? [] );
		if ( null === $file_field_config ) {
			return new WP_REST_Response( [ 'message' => 'This form does not accept file uploads.' ], 400 );
		}

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_REST_Response( [ 'message' => 'No file uploaded.' ], 400 );
		}

		$file = $files['file'];

		if ( ! empty( $file['error'] ) ) {
			return new WP_REST_Response( [ 'message' => 'Upload failed.' ], 400 );
		}

		// Validate file size.
		$max_mb    = (int) ( $file_field_config['validation']['max_size_mb'] ?? 5 );
		$max_bytes = $max_mb * 1048576;
		if ( ( $file['size'] ?? 0 ) > $max_bytes ) {
			return new WP_REST_Response(
				/* translators: %d: max file size in MB */
				[ 'message' => sprintf( __( 'File exceeds maximum size of %d MB.', 'samplehq-request-form' ), $max_mb ) ],
				400
			);
		}

		// Server-side MIME verification (not client-reported).
		$allowed_types = $file_field_config['validation']['allowed_types'] ?? [
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'application/pdf',
		];

		$finfo     = finfo_open( FILEINFO_MIME_TYPE );
		$real_mime = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $real_mime, $allowed_types, true ) ) {
			return new WP_REST_Response( [ 'message' => __( 'File type not allowed.', 'samplehq-request-form' ) ], 400 );
		}

		// Redirect uploads to protected directory.
		$upload_dir_filter = static function ( array $dirs ): array {
			$dirs['subdir'] = '/shqf';
			$dirs['path']   = $dirs['basedir'] . '/shqf';
			$dirs['url']    = $dirs['baseurl'] . '/shqf';
			return $dirs;
		};
		add_filter( 'upload_dir', $upload_dir_filter );

		// Ensure protected directory exists with .htaccess.
		$this->ensure_protected_directory();

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$upload_overrides = [
			'test_form' => false,
			'mimes'     => $this->allowed_types_to_mimes( $allowed_types ),
		];

		$uploaded = wp_handle_upload( $file, $upload_overrides );

		remove_filter( 'upload_dir', $upload_dir_filter );

		if ( isset( $uploaded['error'] ) ) {
			return new WP_REST_Response( [ 'message' => $uploaded['error'] ], 400 );
		}

		// Create WP attachment.
		$attachment = [
			'post_mime_type' => $uploaded['type'],
			'post_title'     => sanitize_file_name( $file['name'] ),
			'post_status'    => 'inherit',
		];

		$attach_id = wp_insert_attachment( $attachment, $uploaded['file'] );

		if ( 0 === $attach_id ) {
			return new WP_REST_Response( [ 'message' => 'Failed to create attachment.' ], 500 );
		}

		$metadata = wp_generate_attachment_metadata( $attach_id, $uploaded['file'] );
		wp_update_attachment_metadata( $attach_id, $metadata );

		// Tag as pending for orphan cleanup.
		update_post_meta( $attach_id, '_shqf_pending', time() );
		update_post_meta( $attach_id, '_shqf_form_id', $form_id );

		// Return only the attachment ID -- no direct URL.
		return new WP_REST_Response(
			[
				'attachment_id' => $attach_id,
				'filename'      => sanitize_file_name( $file['name'] ),
			],
			200
		);
	}

	/**
	 * Ensure the protected upload directory exists with .htaccess deny-all.
	 *
	 * @return void
	 */
	private function ensure_protected_directory(): void {
		$upload_dir = wp_upload_dir();
		$shqf_dir   = $upload_dir['basedir'] . '/shqf';

		if ( ! is_dir( $shqf_dir ) ) {
			wp_mkdir_p( $shqf_dir );
		}

		// Apache: deny direct access.
		$htaccess = $shqf_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Order deny,allow\nDeny from all\n" );
		}

		// Nginx: deny direct access (admin must include this in server config).
		$nginx_conf = $shqf_dir . '/nginx.conf';
		if ( ! file_exists( $nginx_conf ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $nginx_conf, "# Include this in your Nginx server block:\n# location ~* /wp-content/uploads/shqf/ { deny all; return 403; }\n" );
		}

		// Fallback: prevent directory listing on any server.
		$index = $shqf_dir . '/index.html';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, '' );
		}
	}

	/**
	 * Find the first file_upload field config in the form.
	 *
	 * @param array<string, mixed> $config Form config.
	 * @return array<string, mixed>|null Field config or null.
	 */
	private function find_file_field_config( array $config ): ?array {
		$fields = FormValidator::flatten_fields( $config['fields'] ?? [] );

		foreach ( $fields as $field ) {
			if ( 'file_upload' === ( $field['type'] ?? '' ) ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Convert MIME type array to WP mimes format.
	 *
	 * @param string[] $allowed_types MIME types.
	 * @return array<string, string> Extension => MIME type.
	 */
	private function allowed_types_to_mimes( array $allowed_types ): array {
		$map = [
			'image/jpeg'      => 'jpg|jpeg',
			'image/png'       => 'png',
			'image/gif'       => 'gif',
			'image/webp'      => 'webp',
			'application/pdf' => 'pdf',
		];

		$mimes = [];
		foreach ( $allowed_types as $type ) {
			$ext = $map[ $type ] ?? null;
			if ( null !== $ext ) {
				$mimes[ $ext ] = $type;
			}
		}

		return $mimes;
	}
}
