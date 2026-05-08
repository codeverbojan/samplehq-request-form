<?php
/**
 * HMAC-SHA256 request signing for plugin-to-platform API calls.
 *
 * @package SampleHQForm\Connection
 */

declare( strict_types=1 );

namespace SampleHQForm\Connection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signs outgoing HTTP requests with HMAC-SHA256 headers.
 *
 * The platform verifies these signatures to authenticate the plugin.
 * Signature message format: method + "\n" + full_url + "\n" + timestamp + "\n" + SHA256(body)
 */
class ConnectionVerifier {

	/**
	 * Sign an outgoing request and return the authentication headers.
	 *
	 * @param string   $method    HTTP method (GET, POST, DELETE, etc.).
	 * @param string   $url       Full URL including query parameters.
	 * @param string   $body      Request body (empty string for GET/DELETE).
	 * @param string   $secret    Raw connection secret.
	 * @param int|null $timestamp Unix timestamp override (null = current time).
	 * @return array<string, string> Headers to attach to the request.
	 */
	public function sign_request( string $method, string $url, string $body, string $secret, ?int $timestamp = null ): array {
		$ts        = (string) ( $timestamp ?? time() );
		$body_hash = hash( 'sha256', $body );
		$message   = $this->build_message( strtoupper( $method ), $url, $ts, $body_hash );
		$signature = hash_hmac( 'sha256', $message, $secret );

		return [
			'X-SHQF-Site'      => site_url(),
			'X-SHQF-Timestamp' => $ts,
			'X-SHQF-Signature' => $signature,
			'Content-Type'     => 'application/json',
		];
	}

	/**
	 * Build the canonical message string for HMAC signing.
	 *
	 * @param string $method    Uppercase HTTP method.
	 * @param string $url       Full URL with query parameters.
	 * @param string $timestamp Unix timestamp as string.
	 * @param string $body_hash SHA-256 hex hash of the request body.
	 * @return string The canonical message.
	 */
	private function build_message( string $method, string $url, string $timestamp, string $body_hash ): string {
		return $method . "\n" . $url . "\n" . $timestamp . "\n" . $body_hash;
	}
}
