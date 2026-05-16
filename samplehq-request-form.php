<?php
/**
 * SampleHQ Request Form
 *
 * @package           SampleHQForm
 * @author            SampleHQ
 * @copyright         2026 SampleHQ
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       SampleHQ Request Form
 * Plugin URI:        https://samplehq.io/wordpress-plugin
 * Description:       A complete sample request management system with a sample library, visual form builder, and submissions dashboard.
 * Version:           1.0.2
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            SampleHQ
 * Author URI:        https://samplehq.io
 * Text Domain:       samplehq-request-form
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Keep version in sync with the plugin header above.
if ( ! defined( 'SHQF_VERSION' ) ) {
	define( 'SHQF_VERSION', '1.0.2' );
}
if ( ! defined( 'SHQF_FILE' ) ) {
	define( 'SHQF_FILE', __FILE__ );
}
if ( ! defined( 'SHQF_DIR' ) ) {
	define( 'SHQF_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SHQF_URL' ) ) {
	define( 'SHQF_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * PSR-4 autoloader for the SampleHQForm namespace.
 *
 * Maps SampleHQForm\ to the src/ directory. This replaces Composer's autoloader
 * in production so that no vendor/ directory needs to ship with the plugin.
 *
 * Note: Composer's autoload section in composer.json also maps SampleHQForm\ to src/
 * for dev tooling (PHPStan, PHPCS, tests). In production only this spl_autoload runs.
 *
 * @param string $class_name The fully-qualified class name.
 * @return void
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'SampleHQForm\\';
		$len    = strlen( $prefix );

		if ( strncmp( $class_name, $prefix, $len ) !== 0 ) {
			return;
		}

		$relative = substr( $class_name, $len );

		// Reject path traversal attempts (e.g., ".." in class names).
		if ( str_contains( $relative, '..' ) ) {
			return;
		}

		$file = SHQF_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

$GLOBALS['samplehq_request_form'] = SampleHQForm\Plugin::boot();
