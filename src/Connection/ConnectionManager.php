<?php
/**
 * Manages the plugin's connection to the SampleHQ platform.
 *
 * @package SampleHQForm\Connection
 */

declare( strict_types=1 );

namespace SampleHQForm\Connection;

use SampleHQForm\Database\FormsTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles state token generation, connection storage, encryption,
 * and disconnect for the plugin-to-platform connection.
 */
class ConnectionManager {

	/**
	 * Forms table for clearing shq_form_id on disconnect.
	 *
	 * @var FormsTable
	 */
	private FormsTable $forms;

	/**
	 * HMAC signer for outgoing API requests.
	 *
	 * @var ConnectionVerifier
	 */
	private ConnectionVerifier $verifier;

	/**
	 * Constructor.
	 *
	 * @param FormsTable         $forms    Forms table instance.
	 * @param ConnectionVerifier $verifier HMAC signer.
	 */
	public function __construct( FormsTable $forms, ConnectionVerifier $verifier ) {
		$this->forms    = $forms;
		$this->verifier = $verifier;
	}

	/**
	 * WordPress option key for the active connection.
	 *
	 * @var string
	 */
	public const CONNECTION_OPTION = 'shqf_connection';

	/**
	 * WordPress option key for the pending connect state token.
	 *
	 * @var string
	 */
	public const STATE_OPTION = 'shqf_connect_state';

	/**
	 * Length of the random state token.
	 *
	 * @var int
	 */
	private const STATE_LENGTH = 32;

	/**
	 * State token TTL in seconds (30 minutes).
	 *
	 * @var int
	 */
	private const STATE_TTL = 1800;

	/**
	 * AES cipher used for secret encryption.
	 *
	 * @var string
	 */
	private const CIPHER = 'aes-256-gcm';

	/**
	 * GCM authentication tag length in bytes.
	 *
	 * @var int
	 */
	private const TAG_LENGTH = 16;

	/**
	 * Default platform URL (production).
	 *
	 * @var string
	 */
	private const DEFAULT_PLATFORM_URL = 'https://samplehq.io';

	/**
	 * Get the platform base URL for the current environment.
	 *
	 * Uses SHQF_PLATFORM_URL constant for local dev / staging overrides.
	 *
	 * @return string Platform URL without trailing slash.
	 */
	public static function platform_url(): string {
		if ( defined( 'SHQF_PLATFORM_URL' ) && SHQF_PLATFORM_URL ) {
			return rtrim( (string) SHQF_PLATFORM_URL, '/' );
		}

		return self::DEFAULT_PLATFORM_URL;
	}

	/**
	 * Whether the plugin is currently connected to a platform workspace.
	 *
	 * @return bool True if connected.
	 */
	public function is_connected(): bool {
		$connection = get_option( self::CONNECTION_OPTION, [] );

		return ! empty( $connection['workspace_id'] );
	}

	/**
	 * Required keys in the connection option.
	 *
	 * @var list<string>
	 */
	private const REQUIRED_KEYS = [
		'workspace_url',
		'workspace_id',
		'workspace_name',
		'connection_secret',
		'connected_by',
		'connected_at',
	];

	/**
	 * Get the stored connection data with the secret decrypted.
	 *
	 * @return array{workspace_url: string, workspace_id: int, workspace_name: string, connection_secret: string, connected_by: string, connected_at: int}|null Connection data or null if not connected / decryption fails.
	 */
	public function get_connection(): ?array {
		$connection = get_option( self::CONNECTION_OPTION, [] );

		if ( empty( $connection['workspace_id'] ) ) {
			return null;
		}

		foreach ( self::REQUIRED_KEYS as $key ) {
			if ( ! isset( $connection[ $key ] ) ) {
				return null;
			}
		}

		$secret = self::decrypt_secret( $connection['connection_secret'] );

		if ( '' === $secret ) {
			return null;
		}

		$connection['connection_secret'] = $secret;

		return $connection;
	}

	/**
	 * Generate a state token for the connection flow and store it in wp_options.
	 *
	 * @param int $user_id The WordPress user ID initiating the connection.
	 * @return string The generated state token.
	 */
	public function generate_state( int $user_id ): string {
		$token = wp_generate_password( self::STATE_LENGTH, false );

		update_option(
			self::STATE_OPTION,
			[
				'token'      => $token,
				'site_url'   => site_url(),
				'return_url' => admin_url( 'admin.php?page=shqf-settings&tab=connection&action=callback' ),
				'user_id'    => $user_id,
				'created_at' => time(),
			],
			false
		);

		return $token;
	}

	/**
	 * Build the full redirect URL for the "Connect to SampleHQ" action.
	 *
	 * @param \WP_User $user The current WordPress admin user.
	 * @return string The URL to redirect the browser to.
	 */
	public function get_connect_url( \WP_User $user ): string {
		$token = $this->generate_state( $user->ID );
		$state = get_option( self::STATE_OPTION, [] );

		return add_query_arg(
			[
				'state'      => $token,
				'site_url'   => $state['site_url'],
				'return_url' => $state['return_url'],
				'email'      => $user->user_email,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
			],
			self::platform_url() . '/connect/wordpress'
		);
	}

	/**
	 * Validate the POST callback from the platform after email verification.
	 *
	 * Checks state token match, TTL, user_id match, and HMAC signature.
	 *
	 * @param array<string, string> $post_data The $_POST data (state, connection_token, signature).
	 * @param int                   $user_id   The current logged-in user's ID.
	 * @return array{workspace_url: string, workspace_id: int, workspace_name: string, connection_secret: string, connected_by: string}|\WP_Error Decoded token data or error.
	 */
	public function validate_callback( array $post_data, int $user_id ): array|\WP_Error {
		$state = get_option( self::STATE_OPTION, [] );

		if ( empty( $state['token'] ) ) {
			return new \WP_Error( 'shqf_no_state', __( 'No pending connection. Please try again.', 'samplehq-request-form' ) );
		}

		if ( ! hash_equals( $state['token'], $post_data['state'] ?? '' ) ) {
			return new \WP_Error( 'shqf_state_mismatch', __( 'Connection state mismatch. Please try again.', 'samplehq-request-form' ) );
		}

		if ( ( time() - ( $state['created_at'] ?? 0 ) ) > self::STATE_TTL ) {
			delete_option( self::STATE_OPTION );
			return new \WP_Error( 'shqf_state_expired', __( 'Connection expired. Please try again.', 'samplehq-request-form' ) );
		}

		if ( ( $state['user_id'] ?? 0 ) !== $user_id ) {
			return new \WP_Error( 'shqf_user_mismatch', __( 'This connection was initiated by a different user. Please ask them to complete it.', 'samplehq-request-form' ) );
		}

		$token_b64 = $post_data['connection_token'] ?? '';
		$signature = $post_data['signature'] ?? '';

		if ( '' === $token_b64 || '' === $signature ) {
			return new \WP_Error( 'shqf_missing_data', __( 'Invalid callback data.', 'samplehq-request-form' ) );
		}

		$expected_sig = hash_hmac( 'sha256', $token_b64, $state['token'] );

		if ( ! hash_equals( $expected_sig, $signature ) ) {
			return new \WP_Error( 'shqf_invalid_signature', __( 'Invalid connection signature.', 'samplehq-request-form' ) );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$json = base64_decode( $token_b64, true );

		if ( false === $json ) {
			return new \WP_Error( 'shqf_invalid_token', __( 'Could not decode connection data.', 'samplehq-request-form' ) );
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || empty( $data['workspace_id'] ) || empty( $data['connection_secret'] ) ) {
			return new \WP_Error( 'shqf_invalid_token', __( 'Connection data is incomplete.', 'samplehq-request-form' ) );
		}

		delete_option( self::STATE_OPTION );

		return $data;
	}

	/**
	 * Store a validated connection.
	 *
	 * Call only after validate_callback() succeeds (which consumes the state token).
	 *
	 * @param array{workspace_url: string, workspace_id: int, workspace_name: string, connection_secret: string, connected_by: string} $data Decoded connection data from the platform.
	 * @return true|\WP_Error True on success, WP_Error if encryption fails.
	 */
	public function store_connection( array $data ): true|\WP_Error {
		$encrypted = self::encrypt_secret( $data['connection_secret'] );

		if ( '' === $encrypted ) {
			return new \WP_Error( 'shqf_encrypt_failed', __( 'Failed to encrypt the connection secret. Please try again.', 'samplehq-request-form' ) );
		}

		update_option(
			self::CONNECTION_OPTION,
			[
				'workspace_url'     => $data['workspace_url'],
				'workspace_id'      => (int) $data['workspace_id'],
				'workspace_name'    => $data['workspace_name'],
				'connection_secret' => $encrypted,
				'connected_by'      => $data['connected_by'],
				'connected_at'      => time(),
			],
			false
		);

		return true;
	}

	/**
	 * Disconnect from the platform.
	 *
	 * 1. Reads current connection (workspace_url + secret for DELETE notification).
	 * 2. Deletes the shqf_connection option.
	 * 3. Clears shq_form_id on all forms.
	 * 4. Sends best-effort DELETE to the platform (fire and forget).
	 *
	 * @return void
	 */
	public function disconnect(): void {
		$connection = $this->get_connection();

		delete_option( self::CONNECTION_OPTION );

		$this->forms->clear_all_shq_ids();

		if ( null === $connection ) {
			return;
		}

		$url     = rtrim( $connection['workspace_url'], '/' ) . '/wp-json/samplehq/v1/plugin/connection';
		$headers = $this->verifier->sign_request( 'DELETE', $url, '', $connection['connection_secret'] );

		wp_remote_request(
			$url,
			[
				'method'  => 'DELETE',
				'headers' => $headers,
				'timeout' => 5,
			]
		);
	}

	/**
	 * Encrypt a connection secret for storage using AES-256-GCM.
	 *
	 * @param string $raw_secret The raw secret to encrypt.
	 * @return string Base64-encoded ciphertext (IV + tag + ciphertext).
	 */
	public static function encrypt_secret( string $raw_secret ): string {
		$key    = self::derive_key();
		$iv_len = openssl_cipher_iv_length( self::CIPHER );
		$iv     = openssl_random_pseudo_bytes( $iv_len );
		$tag    = '';
		$cipher = openssl_encrypt( $raw_secret, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH );

		if ( false === $cipher ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $iv . $tag . $cipher );
	}

	/**
	 * Decrypt a stored connection secret (AES-256-GCM).
	 *
	 * @param string $encrypted Base64-encoded ciphertext (IV + tag + ciphertext).
	 * @return string The raw secret, or empty string on failure.
	 */
	public static function decrypt_secret( string $encrypted ): string {
		if ( '' === $encrypted ) {
			return '';
		}

		$key    = self::derive_key();
		$iv_len = openssl_cipher_iv_length( self::CIPHER );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$raw = base64_decode( $encrypted, true );

		if ( false === $raw || strlen( $raw ) <= $iv_len + self::TAG_LENGTH ) {
			return '';
		}

		$iv     = substr( $raw, 0, $iv_len );
		$tag    = substr( $raw, $iv_len, self::TAG_LENGTH );
		$cipher = substr( $raw, $iv_len + self::TAG_LENGTH );
		$plain  = openssl_decrypt( $cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );

		return false === $plain ? '' : $plain;
	}

	/**
	 * Derive the encryption key from the WordPress auth salt using HKDF.
	 *
	 * @return string 32-byte key with domain separation.
	 */
	private static function derive_key(): string {
		return hash_hkdf( 'sha256', wp_salt( 'auth' ), 32, 'shqf_connection_secret' );
	}
}
