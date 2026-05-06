<?php
/**
 * Verifies that the plugin's upload directory is not directly accessible.
 *
 * @package SampleHQForm\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Health check: attempts to access a test file in the protected upload
 * directory via HTTP. If accessible, sets a transient to surface an admin notice.
 */
class UploadProtectionCheck {

	/**
	 * Transient key storing the check result.
	 *
	 * @var string
	 */
	public const TRANSIENT_KEY = 'shqf_upload_protection_status';

	/**
	 * Name of the probe file placed in the protected directory.
	 *
	 * @var string
	 */
	private const PROBE_FILE = '__shqf_test__.txt';

	/**
	 * Run the protection check.
	 *
	 * Creates a probe file, attempts to fetch it via HTTP, and stores
	 * the result in a transient (24h TTL).
	 *
	 * @return bool True if directory is protected, false if exposed.
	 */
	public function run(): bool {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			set_transient( self::TRANSIENT_KEY, 'protected', DAY_IN_SECONDS );
			return true;
		}

		$shqf_dir = $upload_dir['basedir'] . '/shqf';
		$shqf_url = $upload_dir['baseurl'] . '/shqf';

		if ( ! is_dir( $shqf_dir ) ) {
			set_transient( self::TRANSIENT_KEY, 'protected', DAY_IN_SECONDS );
			return true;
		}

		$probe_path = $shqf_dir . '/' . self::PROBE_FILE;
		$probe_url  = $shqf_url . '/' . self::PROBE_FILE;

		// Create probe file temporarily for the check.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $probe_path, 'shqf-protection-test' );

		try {
			$response = wp_remote_head(
				$probe_url,
				[
					'timeout'     => 5,
					'sslverify'   => false,
					'redirection' => 0,
				]
			);

			if ( is_wp_error( $response ) ) {
				set_transient( self::TRANSIENT_KEY, 'protected', DAY_IN_SECONDS );
				return true;
			}

			$status_code = (int) wp_remote_retrieve_response_code( $response );

			// 403/404 = directory is protected. 200 = exposed.
			$is_protected = 200 !== $status_code;

			set_transient(
				self::TRANSIENT_KEY,
				$is_protected ? 'protected' : 'exposed',
				DAY_IN_SECONDS
			);

			return $is_protected;
		} finally {
			// Always clean up the probe file.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup of temp probe file; failure is harmless.
			@unlink( $probe_path );
		}
	}

	/**
	 * Check whether the upload directory has been flagged as exposed.
	 *
	 * @return bool True if exposed (needs admin attention).
	 */
	public static function is_exposed(): bool {
		return 'exposed' === get_transient( self::TRANSIENT_KEY );
	}
}
