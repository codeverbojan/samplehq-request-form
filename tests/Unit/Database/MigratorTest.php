<?php
/**
 * Tests for the Migrator class.
 *
 * @package SampleHQForm\Tests\Unit\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Database;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\Migrator;

/**
 * Migrator unit tests.
 */
class MigratorTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Mock wpdb instance.
	 *
	 * @var \wpdb&Mockery\MockInterface
	 */
	private $wpdb;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Migrator should skip when already at latest version.
	 */
	public function test_skips_when_at_latest_version(): void {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->with( Migrator::VERSION_OPTION, 0 )
			->andReturn( Migrator::LATEST_VERSION );

		// update_option should NOT be called.
		Monkey\Functions\expect( 'update_option' )->never();

		$migrator = new Migrator( $this->wpdb );
		$migrator->migrate_to_latest();
	}

	/**
	 * Migrator should run migration and update version when behind.
	 */
	public function test_runs_migration_when_behind(): void {
		// Lock stubs: add_option succeeds (lock acquired), then released.
		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migrating', Mockery::type( 'int' ), '', 'no' )
			->once()
			->andReturn( true );
		Monkey\Functions\expect( 'delete_option' )
			->with( 'shqf_migrating' )
			->once();

		// get_option called twice: once before lock, once after (re-check).
		Monkey\Functions\expect( 'get_option' )
			->with( Migrator::VERSION_OPTION, 0 )
			->twice()
			->andReturn( 0 );

		Monkey\Functions\expect( 'update_option' )
			->with( Migrator::VERSION_OPTION, Mockery::type( 'int' ), true )
			->times( Migrator::LATEST_VERSION );

		$migrator = Mockery::mock( Migrator::class, [ $this->wpdb ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$this->wpdb->shouldReceive( 'get_charset_collate' )
			->once()
			->andReturn( 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci' );

		$migrator->shouldReceive( 'run_dbdelta' )
			->once()
			->andReturn( [] );

		// migrate_to_2 + migrate_to_3: column/index-exists check + ALTER TABLE.
		$this->wpdb->dbname = 'test_db';
		$this->wpdb->shouldReceive( 'prepare' )
			->andReturn( '' );
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( '0' );
		$this->wpdb->shouldReceive( 'query' )
			->twice()
			->andReturn( true );

		$migrator->migrate_to_latest();
	}

	/**
	 * Migrator should skip when lock is already held by another process.
	 */
	public function test_skips_when_lock_held(): void {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->with( Migrator::VERSION_OPTION, 0 )
			->andReturn( 0 );

		// add_option fails (lock already exists in DB).
		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migrating', Mockery::type( 'int' ), '', 'no' )
			->once()
			->andReturn( false );

		// Lock timestamp is recent (not expired).
		Monkey\Functions\expect( 'get_option' )
			->once()
			->with( 'shqf_migrating' )
			->andReturn( time() );

		// Migration methods should NOT be called.
		Monkey\Functions\expect( 'update_option' )->never();

		$migrator = new Migrator( $this->wpdb );
		$migrator->migrate_to_latest();
	}

	/**
	 * Lock is released even if migration throws an exception.
	 */
	public function test_lock_released_on_exception(): void {
		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migrating', Mockery::type( 'int' ), '', 'no' )
			->once()
			->andReturn( true );
		Monkey\Functions\expect( 'delete_option' )
			->with( 'shqf_migrating' )
			->once();

		// Re-check after lock returns 0 (needs migration).
		Monkey\Functions\expect( 'get_option' )
			->with( Migrator::VERSION_OPTION, 0 )
			->twice()
			->andReturn( 0 );

		$migrator = Mockery::mock( Migrator::class, [ $this->wpdb ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$this->wpdb->shouldReceive( 'get_charset_collate' )
			->once()
			->andReturn( '' );

		$migrator->shouldReceive( 'run_dbdelta' )
			->once()
			->andThrow( new \RuntimeException( 'DB error' ) );

		$this->expectException( \RuntimeException::class );
		$migrator->migrate_to_latest();
		// delete_option expectation verifies the lock was released.
	}

	/**
	 * Expired lock is recovered and migration proceeds.
	 */
	public function test_expired_lock_is_recovered(): void {
		$expired_time = time() - 200;

		// add_option fails (lock option exists).
		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migrating', Mockery::type( 'int' ), '', 'no' )
			->once()
			->andReturn( false );

		// Route get_option calls by argument.
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing(
				function ( $key, $default = false ) use ( $expired_time ) {
					if ( 'shqf_migrating' === $key ) {
						return $expired_time;
					}
					return 0; // VERSION_OPTION returns 0.
				}
			);

		// Route update_option calls (lock overwrite + version bumps).
		Monkey\Functions\expect( 'update_option' )->andReturn( true );

		// Release lock after migration.
		Monkey\Functions\expect( 'delete_option' )
			->with( 'shqf_migrating' )
			->once();

		$migrator = Mockery::mock( Migrator::class, [ $this->wpdb ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$this->wpdb->shouldReceive( 'get_charset_collate' )
			->once()
			->andReturn( '' );

		$migrator->shouldReceive( 'run_dbdelta' )
			->once()
			->andReturn( [] );

		// migrate_to_2 + migrate_to_3: column/index check + ALTER TABLE.
		$this->wpdb->dbname = 'test_db';
		$this->wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( '0' );
		$this->wpdb->shouldReceive( 'query' )->twice()->andReturn( true );

		$migrator->migrate_to_latest();
	}

	/**
	 * get_current_version returns 0 when no option exists.
	 */
	public function test_get_current_version_returns_zero_by_default(): void {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->with( Migrator::VERSION_OPTION, 0 )
			->andReturn( 0 );

		$migrator = new Migrator( $this->wpdb );
		$this->assertSame( 0, $migrator->get_current_version() );
	}

	/**
	 * get_current_version returns stored integer value.
	 */
	public function test_get_current_version_returns_stored_value(): void {
		Monkey\Functions\expect( 'get_option' )
			->once()
			->with( Migrator::VERSION_OPTION, 0 )
			->andReturn( '1' ); // WordPress returns strings from options.

		$migrator = new Migrator( $this->wpdb );
		$this->assertSame( 1, $migrator->get_current_version() );
	}

	/**
	 * get_table_names returns all 8 table names with correct prefix.
	 */
	public function test_get_table_names_returns_all_tables(): void {
		$migrator = new Migrator( $this->wpdb );
		$tables   = $migrator->get_table_names();

		$this->assertCount( 8, $tables );
		$this->assertSame( 'wp_shqf_samples', $tables[0] );
		$this->assertSame( 'wp_shqf_sample_categories', $tables[1] );
		$this->assertSame( 'wp_shqf_sample_category_map', $tables[2] );
		$this->assertSame( 'wp_shqf_sample_images', $tables[3] );
		$this->assertSame( 'wp_shqf_forms', $tables[4] );
		$this->assertSame( 'wp_shqf_submissions', $tables[5] );
		$this->assertSame( 'wp_shqf_submission_meta', $tables[6] );
		$this->assertSame( 'wp_shqf_rate_limits', $tables[7] );
	}

	/**
	 * drop_all_tables should drop tables in reverse order (children first).
	 */
	public function test_drop_all_tables_in_reverse_order(): void {
		$dropped = [];
		$this->wpdb->shouldReceive( 'query' )
			->times( 8 )
			->andReturnUsing(
				function ( string $sql ) use ( &$dropped ) {
					$dropped[] = $sql;
					return true;
				}
			);

		Monkey\Functions\expect( 'delete_option' )
			->once()
			->with( Migrator::VERSION_OPTION );

		$migrator = new Migrator( $this->wpdb );
		$migrator->drop_all_tables();

		// Children (rate_limits, submission_meta, submissions) should be
		// dropped before parents (samples, forms, etc).
		$this->assertStringContainsString( 'wp_shqf_rate_limits', $dropped[0] );
		$this->assertStringContainsString( 'wp_shqf_submission_meta', $dropped[1] );
		$this->assertStringContainsString( 'wp_shqf_submissions', $dropped[2] );
		$this->assertStringContainsString( 'wp_shqf_forms', $dropped[3] );
	}

	/**
	 * delete_all_options should delete all plugin options.
	 */
	public function test_delete_all_options(): void {
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( Migrator::VERSION_OPTION );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_collect_ip' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_ip_retention_days' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_turnstile_site_key' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_turnstile_secret_key' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_email_defaults' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_connection' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_connect_state' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_migration_progress' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_woo_enabled' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_woo_button_text' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_woo_form_id' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_woo_product_filter' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_woo_sample_tag' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_woo_sample_categories' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_woo_max_quantity' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_woo_show_loop_badge' );
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_woo_badge_text' );

		// Advisory lock cleanup.
		Monkey\Functions\expect( 'delete_option' )
			->once()->with( 'shqf_migrating' );

		Monkey\Functions\expect( 'delete_transient' )
			->once()
			->with( 'shqf_unread_count' );

		// Transient cleanup query.
		$this->wpdb->options = 'wp_options';
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 0 );

		Monkey\Functions\expect( 'delete_metadata' )
			->once()
			->with( 'user', 0, 'shqf_dismissed_cta', '', true );
		Monkey\Functions\expect( 'delete_metadata' )
			->once()
			->with( 'user', 0, 'shqf_samples_per_page', '', true );
		Monkey\Functions\expect( 'delete_metadata' )
			->once()
			->with( 'user', 0, 'shqf_forms_per_page', '', true );
		Monkey\Functions\expect( 'delete_metadata' )
			->once()
			->with( 'user', 0, 'shqf_submissions_per_page', '', true );

		$migrator = new Migrator( $this->wpdb );
		$migrator->delete_all_options();
	}

	/**
	 * Schema SQL should contain all required table names.
	 */
	public function test_migration_sql_contains_all_tables(): void {
		// Lock stubs.
		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migrating', Mockery::type( 'int' ), '', 'no' )
			->once()
			->andReturn( true );
		Monkey\Functions\expect( 'delete_option' )
			->with( 'shqf_migrating' )
			->once();

		Monkey\Functions\expect( 'get_option' )
			->with( Migrator::VERSION_OPTION, 0 )
			->twice()
			->andReturn( 0 );

		Monkey\Functions\expect( 'update_option' )
			->with( Migrator::VERSION_OPTION, Mockery::type( 'int' ), true )
			->times( Migrator::LATEST_VERSION );

		$captured_sql = '';

		$this->wpdb->shouldReceive( 'get_charset_collate' )
			->once()
			->andReturn( '' );

		$migrator = Mockery::mock( Migrator::class, [ $this->wpdb ] )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$migrator->shouldReceive( 'run_dbdelta' )
			->once()
			->with( Mockery::capture( $captured_sql ) )
			->andReturn( [] );

		// migrate_to_2 + migrate_to_3: column/index-exists check + ALTER TABLE.
		$this->wpdb->dbname = 'test_db';
		$this->wpdb->shouldReceive( 'prepare' )
			->andReturn( '' );
		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( '0' );
		$this->wpdb->shouldReceive( 'query' )
			->twice()
			->andReturn( true );

		$migrator->migrate_to_latest();

		// Verify all 8 CREATE TABLE statements are present.
		$this->assertStringContainsString( 'CREATE TABLE wp_shqf_samples', $captured_sql );
		$this->assertStringContainsString( 'CREATE TABLE wp_shqf_sample_categories', $captured_sql );
		$this->assertStringContainsString( 'CREATE TABLE wp_shqf_sample_category_map', $captured_sql );
		$this->assertStringContainsString( 'CREATE TABLE wp_shqf_sample_images', $captured_sql );
		$this->assertStringContainsString( 'CREATE TABLE wp_shqf_forms', $captured_sql );
		$this->assertStringContainsString( 'CREATE TABLE wp_shqf_submissions', $captured_sql );
		$this->assertStringContainsString( 'CREATE TABLE wp_shqf_submission_meta', $captured_sql );
		$this->assertStringContainsString( 'CREATE TABLE wp_shqf_rate_limits', $captured_sql );

		// Verify key indexes exist.
		$this->assertStringContainsString( 'UNIQUE KEY sku', $captured_sql );
		$this->assertStringContainsString( 'UNIQUE KEY slug', $captured_sql );
		$this->assertStringContainsString( 'KEY submission_field', $captured_sql );
		$this->assertStringContainsString( 'KEY field_value_short', $captured_sql );
	}

	/**
	 * Migration 3 adds composite index on (status, is_read) if not exists.
	 */
	public function test_migrate_to_3_adds_composite_index(): void {
		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migrating', Mockery::type( 'int' ), '', 'no' )
			->once()
			->andReturn( true );
		Monkey\Functions\expect( 'delete_option' )
			->with( 'shqf_migrating' )
			->once();

		// Start at version 2 — only migrate_to_3 runs.
		Monkey\Functions\expect( 'get_option' )
			->with( Migrator::VERSION_OPTION, 0 )
			->twice()
			->andReturn( 2 );

		Monkey\Functions\expect( 'update_option' )
			->with( Migrator::VERSION_OPTION, 3, true )
			->once();

		$this->wpdb->dbname = 'test_db';

		// Index does not exist yet.
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'index-check-sql' );
		$this->wpdb->shouldReceive( 'get_var' )->once()->with( 'index-check-sql' )->andReturn( '0' );

		$captured_sql = '';
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( Mockery::capture( $captured_sql ) )
			->andReturn( true );

		$migrator = new Migrator( $this->wpdb );
		$migrator->migrate_to_latest();

		$this->assertStringContainsString( 'ADD INDEX idx_status_is_read', $captured_sql );
		$this->assertStringContainsString( 'status, is_read', $captured_sql );
	}

	/**
	 * Migration 3 skips index creation when index already exists.
	 */
	public function test_migrate_to_3_skips_existing_index(): void {
		Monkey\Functions\expect( 'add_option' )
			->with( 'shqf_migrating', Mockery::type( 'int' ), '', 'no' )
			->once()
			->andReturn( true );
		Monkey\Functions\expect( 'delete_option' )
			->with( 'shqf_migrating' )
			->once();

		Monkey\Functions\expect( 'get_option' )
			->with( Migrator::VERSION_OPTION, 0 )
			->twice()
			->andReturn( 2 );

		Monkey\Functions\expect( 'update_option' )
			->with( Migrator::VERSION_OPTION, 3, true )
			->once();

		$this->wpdb->dbname = 'test_db';

		// Index already exists.
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'index-check-sql' );
		$this->wpdb->shouldReceive( 'get_var' )->once()->with( 'index-check-sql' )->andReturn( '2' );

		// query should NOT be called (no ALTER TABLE).
		$this->wpdb->shouldReceive( 'query' )->never();

		$migrator = new Migrator( $this->wpdb );
		$migrator->migrate_to_latest();
	}
}
