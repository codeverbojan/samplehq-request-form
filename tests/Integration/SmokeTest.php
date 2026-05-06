<?php
/**
 * Smoke test to verify integration test infrastructure works.
 *
 * @package SampleHQForm\Tests\Integration
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Integration;

class SmokeTest extends TestCase {

	public function test_wordpress_loaded(): void {
		$this->assertTrue( function_exists( 'add_action' ) );
		$this->assertTrue( defined( 'ABSPATH' ) );
	}

	public function test_plugin_active(): void {
		$this->assertTrue( function_exists( 'is_plugin_active' ) || class_exists( \SampleHQForm\Plugin::class ) );
	}

	public function test_database_tables_exist(): void {
		global $wpdb;
		$table  = $wpdb->prefix . 'shqf_samples';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
		$this->assertSame( $table, $result );
	}
}
