<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Integration;

class PluginActivationTest extends TestCase {

	public function test_all_tables_exist(): void {
		global $wpdb;

		$tables = [
			'shqf_samples',
			'shqf_sample_categories',
			'shqf_sample_category_map',
			'shqf_sample_images',
			'shqf_forms',
			'shqf_submissions',
			'shqf_submission_meta',
			'shqf_rate_limits',
		];

		foreach ( $tables as $table ) {
			$full_name = $wpdb->prefix . $table;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->get_var( "SHOW TABLES LIKE '{$full_name}'" );
			$this->assertSame( $full_name, $result, "Table {$full_name} should exist." );
		}
	}

	public function test_db_version_option_set(): void {
		$version = get_option( 'shqf_db_version', 0 );
		$this->assertGreaterThanOrEqual( 2, (int) $version );
	}

	public function test_plugin_constants_defined(): void {
		$this->assertTrue( defined( 'SHQF_VERSION' ) );
		$this->assertTrue( defined( 'SHQF_DIR' ) );
		$this->assertTrue( defined( 'SHQF_FILE' ) );
		// SHQF_URL uses plugin_dir_url() which may not resolve in CLI context.
	}

	public function test_admin_menu_hook_registered(): void {
		$this->assertGreaterThan( 0, has_action( 'admin_menu' ) );
	}

	public function test_rest_api_routes_registered(): void {
		$this->assertGreaterThan( 0, has_action( 'rest_api_init' ) );
	}
}
