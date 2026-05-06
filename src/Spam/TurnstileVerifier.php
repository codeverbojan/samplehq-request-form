<?php
/**
 * Cloudflare Turnstile CAPTCHA verification.
 *
 * @package SampleHQForm\Spam
 */

declare( strict_types=1 );

namespace SampleHQForm\Spam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies Cloudflare Turnstile challenge tokens.
 *
 * Optional integration -- if site key and secret key are not configured
 * in plugin settings, all verification calls return true (pass-through).
 */
class TurnstileVerifier {

	/**
	 * Turnstile verification API URL.
	 *
	 * @var string
	 */
	private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * Check if Turnstile is configured (both keys present).
	 *
	 * @return bool True if configured.
	 */
	public function is_enabled(): bool {
		return '' !== $this->get_site_key() && '' !== $this->get_secret_key();
	}

	/**
	 * Get the Turnstile site key.
	 *
	 * @return string Site key or empty string.
	 */
	public function get_site_key(): string {
		return (string) get_option( 'shqf_turnstile_site_key', '' );
	}

	/**
	 * Get the Turnstile secret key.
	 *
	 * @return string Secret key or empty string.
	 */
	private function get_secret_key(): string {
		return (string) get_option( 'shqf_turnstile_secret_key', '' );
	}

	/**
	 * Verify a Turnstile token.
	 *
	 * Returns true if Turnstile is not configured (pass-through).
	 *
	 * @param string $token    The cf-turnstile-response token from the client.
	 * @param string $remote_ip The user's IP address (optional, improves accuracy).
	 * @return bool True if verified or not configured.
	 */
	public function verify( string $token, string $remote_ip = '' ): bool {
		if ( ! $this->is_enabled() ) {
			return true;
		}

		if ( empty( $token ) ) {
			return false;
		}

		$body = [
			'secret'   => $this->get_secret_key(),
			'response' => $token,
		];

		if ( ! empty( $remote_ip ) ) {
			$body['remoteip'] = $remote_ip;
		}

		$response = wp_remote_post(
			self::VERIFY_URL,
			[
				'body'    => $body,
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->log_api_failure( $response->get_error_message() );
			return false;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code >= 400 ) {
			$this->log_api_failure( 'HTTP ' . $status_code );
			return false;
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $result ) ) {
			$this->log_api_failure( 'Invalid JSON response' );
			return false;
		}

		return ! empty( $result['success'] );
	}

	/**
	 * Transient key for tracking Turnstile API failures.
	 *
	 * @var string
	 */
	public const FAILURE_TRANSIENT = 'shqf_turnstile_api_failure';

	/**
	 * Log a Turnstile API failure and flag it for admin notification.
	 *
	 * @param string $reason Human-readable failure reason.
	 * @return void
	 */
	private function log_api_failure( string $reason ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[SampleHQ Form] Turnstile API failure: ' . $reason );

		set_transient( self::FAILURE_TRANSIENT, $reason, HOUR_IN_SECONDS );
	}

	/**
	 * Check whether a recent Turnstile API failure has been recorded.
	 *
	 * Used by admin notice to alert site owners.
	 *
	 * @return string|false Failure reason or false if none.
	 */
	public static function get_recent_failure() {
		return get_transient( self::FAILURE_TRANSIENT );
	}
}
