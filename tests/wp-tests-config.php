<?php
/**
 * WordPress test suite config for integration tests.
 *
 * Used by wp-phpunit inside the wp-env tests-cli container.
 * DB credentials match the wp-env tests-mysql container defaults.
 *
 * @package SampleHQForm\Tests
 */

define( 'DB_NAME', 'tests-wordpress' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'password' );
define( 'DB_HOST', 'tests-mysql' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );

define( 'ABSPATH', '/var/www/html/' );
