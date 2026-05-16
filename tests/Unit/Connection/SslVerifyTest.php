<?php
/**
 * Regression guard: all wp_remote_* calls in src/Connection/ must include sslverify.
 *
 * @package SampleHQForm\Tests\Unit\Connection
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Connection;

use PHPUnit\Framework\TestCase;

/**
 * Static analysis test that prevents future HTTP calls from omitting the
 * sslverify flag (which causes SSL cert errors in local dev with Herd).
 */
class SslVerifyTest extends TestCase {

	/**
	 * Source directory containing all Connection classes.
	 */
	private const SOURCE_DIR = __DIR__ . '/../../../src/Connection';

	/**
	 * Every wp_remote_* call must have a sslverify key in its arguments array.
	 * This is a grep-style test -- not mocked, not runtime.
	 */
	public function test_all_http_calls_have_sslverify(): void {
		$dir = realpath( self::SOURCE_DIR );
		$this->assertNotFalse( $dir, 'src/Connection directory must exist' );

		$files = $this->get_php_files( $dir );
		$this->assertNotEmpty( $files, 'src/Connection must contain PHP files' );

		$violations = [];

		foreach ( $files as $file ) {
			$content  = file_get_contents( $file );
			$basename = $this->relative_path( $dir, $file );

			preg_match_all(
				'/wp_remote_(request|post|get|head)\s*\(/',
				$content,
				$matches,
				PREG_OFFSET_CAPTURE
			);

			if ( empty( $matches[0] ) ) {
				continue;
			}

			foreach ( $matches[0] as [ $match, $offset ] ) {
				$line = substr_count( substr( $content, 0, $offset ), "\n" ) + 1;

				// Scan 600 chars before (variable-built args) and 600 chars after the call.
				$start = max( 0, $offset - 600 );
				$block = substr( $content, $start, 1200 );

				if ( ! str_contains( $block, 'sslverify' ) ) {
					$violations[] = sprintf( '%s:%d %s', $basename, $line, trim( $match ) );
				}
			}
		}

		$this->assertEmpty(
			$violations,
			"The following wp_remote_* calls are missing 'sslverify':\n  - " . implode( "\n  - ", $violations )
		);
	}

	/**
	 * The sslverify value must use the environment-aware pattern:
	 * `! defined( 'SHQF_PLATFORM_URL' )` -- true in production, false in local dev.
	 * Verifies both the constant name AND the negation operator are present.
	 */
	public function test_sslverify_uses_environment_aware_pattern(): void {
		$dir   = realpath( self::SOURCE_DIR );
		$files = $this->get_php_files( $dir );

		$violations = [];

		foreach ( $files as $file ) {
			$content  = file_get_contents( $file );
			$basename = $this->relative_path( $dir, $file );

			preg_match_all(
				'/wp_remote_(request|post|get|head)\s*\(/',
				$content,
				$matches,
				PREG_OFFSET_CAPTURE
			);

			if ( empty( $matches[0] ) ) {
				continue;
			}

			foreach ( $matches[0] as [ $match, $offset ] ) {
				$line  = substr_count( substr( $content, 0, $offset ), "\n" ) + 1;
				$start = max( 0, $offset - 600 );
				$block = substr( $content, $start, 1200 );

				if ( ! str_contains( $block, 'sslverify' ) ) {
					continue;
				}

				if ( ! str_contains( $block, 'SHQF_PLATFORM_URL' ) ) {
					$violations[] = sprintf(
						'%s:%d uses sslverify but not the SHQF_PLATFORM_URL pattern',
						$basename,
						$line
					);
				} elseif ( ! preg_match( '/!\s*defined\s*\(\s*[\'"]SHQF_PLATFORM_URL/', $block ) ) {
					$violations[] = sprintf(
						'%s:%d has SHQF_PLATFORM_URL but missing negation (! defined)',
						$basename,
						$line
					);
				}
			}
		}

		$this->assertEmpty(
			$violations,
			"The following calls use sslverify incorrectly:\n  - " . implode( "\n  - ", $violations )
		);
	}

	/**
	 * Recursively collect all PHP files under a directory.
	 *
	 * @return list<string>
	 */
	private function get_php_files( string $dir ): array {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);

		$files = [];

		foreach ( $iterator as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * Get a path relative to the base directory for readable error messages.
	 */
	private function relative_path( string $base, string $path ): string {
		if ( str_starts_with( $path, $base ) ) {
			return ltrim( substr( $path, strlen( $base ) ), '/' );
		}

		return basename( $path );
	}
}
