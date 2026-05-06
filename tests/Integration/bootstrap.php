<?php
/**
 * PHPUnit bootstrap for integration tests.
 *
 * Loads real WordPress from the wp-env container (no WP_UnitTestCase needed).
 * Run via: npm run test:integration
 *
 * @package SampleHQForm\Tests\Integration
 */

declare( strict_types=1 );

// Load Composer autoloader (plugin classes + test classes).
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Load WordPress from the container's ABSPATH.
$_wp_load = '/var/www/html/wp-load.php';

if ( ! file_exists( $_wp_load ) ) {
	echo "WordPress not found at {$_wp_load}.\n";
	echo "Integration tests must run inside wp-env: npm run test:integration\n";
	exit( 1 );
}

// Suppress WordPress output during test bootstrap.
define( 'WP_USE_THEMES', false );
require_once $_wp_load;

// Ensure the plugin is active and tables exist.
if ( ! class_exists( \SampleHQForm\Plugin::class ) ) {
	echo "Plugin not loaded. Is it activated in wp-env?\n";
	exit( 1 );
}

echo "Integration tests: WordPress " . get_bloginfo( 'version' ) . ", PHP " . PHP_VERSION . "\n";
