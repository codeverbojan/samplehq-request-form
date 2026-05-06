<?php
/**
 * Form token (CSRF protection) for public form submissions.
 *
 * @package SampleHQForm\Spam
 */

declare( strict_types=1 );

namespace SampleHQForm\Spam;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and validates form tokens for CSRF protection.
 *
 * Uses WordPress transients to store tokens. Each token is keyed by
 * form_id + random nonce and expires after 24 hours. This works for
 * unauthenticated users (unlike WP nonces which require a user session).
 */
class FormToken {

	/**
	 * Token transient prefix.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'shqf_token_';

	/**
	 * Token lifetime in seconds (24 hours).
	 *
	 * @var int
	 */
	private const TOKEN_EXPIRY = DAY_IN_SECONDS;

	/**
	 * Generate a new form token and store it in a transient.
	 *
	 * @param int $form_id The form ID.
	 * @return string The generated token value.
	 */
	public function generate( int $form_id ): string {
		$token = wp_generate_password( 32, false );
		$key   = self::TRANSIENT_PREFIX . $form_id . '_' . $token;

		set_transient( $key, $form_id, self::TOKEN_EXPIRY );

		return $token;
	}

	/**
	 * Validate a submitted form token and consume it (one-time use).
	 *
	 * Deletes the transient after validation to prevent replay attacks.
	 *
	 * @param int    $form_id The form ID.
	 * @param string $token   The submitted token value.
	 * @return bool True if the token is valid.
	 */
	public function validate( int $form_id, string $token ): bool {
		if ( ! $this->peek( $form_id, $token ) ) {
			return false;
		}

		$key = self::TRANSIENT_PREFIX . $form_id . '_' . $token;
		delete_transient( $key );

		return true;
	}

	/**
	 * Validate a submitted form token without consuming it.
	 *
	 * Use this for intermediate requests (e.g., file uploads) that must verify
	 * the token is valid but leave it intact for the final form submission.
	 *
	 * @param int    $form_id The form ID.
	 * @param string $token   The submitted token value.
	 * @return bool True if the token is valid.
	 */
	public function peek( int $form_id, string $token ): bool {
		if ( empty( $token ) ) {
			return false;
		}

		$key    = self::TRANSIENT_PREFIX . $form_id . '_' . $token;
		$stored = get_transient( $key );

		if ( false === $stored ) {
			return false;
		}

		return (int) $stored === $form_id;
	}
}
