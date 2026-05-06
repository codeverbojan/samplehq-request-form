<?php
/**
 * Public REST API endpoints.
 *
 * @package SampleHQForm\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Forms\FormProcessor;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Registers the public form submission REST endpoint.
 *
 * POST /samplehq-form/v1/submissions
 *
 * This endpoint is public (no authentication required). CSRF protection
 * is handled via form tokens, not WordPress nonces.
 */
class PublicEndpoints {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const NAMESPACE = 'samplehq-form/v1';

	/**
	 * Form processor.
	 *
	 * @var FormProcessor
	 */
	private FormProcessor $processor;

	/**
	 * Constructor.
	 *
	 * @param FormProcessor $processor Form submission processor.
	 */
	public function __construct( FormProcessor $processor ) {
		$this->processor = $processor;
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
	 * Register the submission route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/submissions',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_submission' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'form_id'               => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'shqf_token'            => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'shqf_hp'               => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'shqf_fields'           => [
						'type'    => 'object',
						'default' => [],
					],
					'cf_turnstile_response' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Handle a form submission.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The response.
	 */
	public function handle_submission( WP_REST_Request $request ): WP_REST_Response {
		$referer    = wp_get_referer();
		$user_agent = $request->get_header( 'user-agent' );

		// Use REMOTE_ADDR as the trusted IP source. X-Forwarded-For is spoofable.
		// Site owners behind a proxy can override via the 'shqf_client_ip' filter.
		$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

		/**
		 * Filter the client IP address used for rate limiting.
		 *
		 * Use this to handle trusted proxy setups (e.g., reading X-Forwarded-For
		 * from a known load balancer).
		 *
		 * @param string          $ip_address The detected IP address.
		 * @param WP_REST_Request $request    The REST request.
		 */
		$ip_address = apply_filters( 'shqf_client_ip', $ip_address, $request );

		$data = [
			'form_id'               => $request->get_param( 'form_id' ),
			'shqf_token'            => $request->get_param( 'shqf_token' ),
			'shqf_hp'               => $request->get_param( 'shqf_hp' ),
			'shqf_fields'           => $request->get_param( 'shqf_fields' ),
			'cf_turnstile_response' => $request->get_param( 'cf_turnstile_response' ),
			'source_url'            => ! empty( $referer ) ? esc_url_raw( $referer ) : '',
			'ip_address'            => $ip_address,
			'user_agent'            => ! empty( $user_agent ) ? $user_agent : '',
		];

		$result      = $this->processor->process( $data );
		$status_code = $result['status_code'];

		unset( $result['status_code'] );

		$response = new WP_REST_Response( $result, $status_code );

		if ( 429 === $status_code ) {
			$response->header( 'Retry-After', '3600' );
		}

		return $response;
	}
}
