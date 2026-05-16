<?php
/**
 * PHPStan bootstrap file for plugin constants.
 *
 * This file defines the constants that the main plugin file creates at runtime.
 * PHPStan needs these to analyze src/ files that reference them.
 *
 * @package SampleHQForm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SHQF_VERSION', '1.0.2' );
define( 'SHQF_FILE', __DIR__ . '/samplehq-request-form.php' );
define( 'SHQF_DIR', __DIR__ . '/' );
define( 'SHQF_URL', 'https://example.com/wp-content/plugins/samplehq-request-form/' );
