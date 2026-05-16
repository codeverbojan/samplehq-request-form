<?php
/**
 * Tests for the Plugin bootstrap class.
 *
 * @package SampleHQForm\Tests\Unit
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Plugin;

/**
 * Plugin bootstrap tests.
 */
class PluginTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Set up Brain\Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Plugin::reset();

		// Set up global $wpdb mock for services instantiated in register_hooks().
		$wpdb         = \Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		// Stub WP functions used by register_hooks() service wiring.
		// Return LATEST_VERSION for shqf_db_version so maybe_upgrade() skips migration.
		Monkey\Functions\stubs( [
			'add_shortcode' => null,
			'get_option'    => static fn( $k, $d = false ) =>
				'shqf_db_version' === $k ? \SampleHQForm\Database\Migrator::LATEST_VERSION : $d,
		] );
	}

	/**
	 * Tear down Brain\Monkey after each test.
	 */
	protected function tearDown(): void {
		Plugin::reset();
		unset( $GLOBALS['wpdb'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Plugin::boot() should return a Plugin instance.
	 */
	public function test_boot_returns_plugin_instance(): void {
		Monkey\Functions\expect( 'register_activation_hook' )
			->once()
			->with( SHQF_FILE, \Mockery::type( 'array' ) );

		Monkey\Functions\expect( 'register_deactivation_hook' )
			->once()
			->with( SHQF_FILE, \Mockery::type( 'array' ) );

		$plugin = Plugin::boot();

		$this->assertInstanceOf( Plugin::class, $plugin );
	}

	/**
	 * Plugin constants should be defined.
	 */
	public function test_constants_are_defined(): void {
		$this->assertTrue( defined( 'SHQF_VERSION' ) );
		$this->assertTrue( defined( 'SHQF_FILE' ) );
		$this->assertTrue( defined( 'SHQF_DIR' ) );
		$this->assertTrue( defined( 'SHQF_URL' ) );
		$this->assertSame( '1.0.1', SHQF_VERSION );
	}

	/**
	 * Second boot() call should return the same instance (boot guard).
	 */
	public function test_boot_returns_same_instance_on_second_call(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
		] );

		$first  = Plugin::boot();
		$second = Plugin::boot();

		$this->assertSame( $first, $second );
	}

	/**
	 * Hooks should only be registered once even if boot() is called twice.
	 */
	public function test_hooks_registered_only_once(): void {
		Monkey\Functions\expect( 'register_activation_hook' )->once();
		Monkey\Functions\expect( 'register_deactivation_hook' )->once();

		Plugin::boot();
		Plugin::boot();
	}

	/**
	 * After reset(), boot() should create a new instance.
	 */
	public function test_reset_allows_fresh_boot(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
		] );

		$first = Plugin::boot();
		Plugin::reset();
		$second = Plugin::boot();

		$this->assertNotSame( $first, $second );
	}

	/**
	 * Activate triggers the database migrator.
	 */
	public function test_activate_triggers_migrator(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_next_scheduled'          => static fn() => false,
			'wp_schedule_event'          => static fn() => true,
			'wp_schedule_single_event'   => static fn() => true,
		] );

		// Set up global $wpdb mock for the Migrator.
		$wpdb         = \Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wpdb'] = $wpdb;

		// Migrator checks current version -- return latest so it skips actual migration.
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_db_version', 0 )
			->andReturn( \SampleHQForm\Database\Migrator::LATEST_VERSION );

		$plugin = Plugin::boot();
		$plugin->activate();

		// If we got here without error, the migrator was called and skipped correctly.
		$this->assertTrue( true );

		unset( $GLOBALS['wpdb'] );
	}

	/**
	 * Deactivate runs without error.
	 */
	public function test_deactivate_runs_without_error(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_next_scheduled'          => static fn() => false,
			'wp_unschedule_hook'         => null,
		] );

		$plugin = Plugin::boot();
		$plugin->deactivate();

		$this->assertTrue( true ); // No exception = pass.
	}

	/**
	 * Hook callbacks use named methods, not closures (removable by other plugins).
	 */
	public function test_hooks_use_named_methods_not_closures(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
		] );

		$plugin = Plugin::boot();

		$this->assertSame(
			10,
			has_action( 'plugins_loaded', [ $plugin, 'wire_woocommerce' ] ),
			'wire_woocommerce should be registered on plugins_loaded.'
		);

		$this->assertSame(
			10,
			has_action( 'shqf_check_upload_protection', [ Plugin::class, 'check_upload_protection' ] ),
			'check_upload_protection should be registered on shqf_check_upload_protection.'
		);

		$this->assertSame(
			10,
			has_action( 'shqf_daily_cleanup', [ $plugin, 'run_daily_cleanup' ] ),
			'run_daily_cleanup should be registered on shqf_daily_cleanup.'
		);

		$this->assertSame(
			10,
			has_action( 'elementor/widgets/register', [ Plugin::class, 'register_elementor_widget' ] ),
			'register_elementor_widget should be registered on elementor/widgets/register.'
		);
	}

	/**
	 * Named hook callbacks are removable (not anonymous closures).
	 */
	public function test_named_hooks_are_removable(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
		] );

		$plugin = Plugin::boot();

		remove_action( 'plugins_loaded', [ $plugin, 'wire_woocommerce' ] );
		$this->assertFalse(
			has_action( 'plugins_loaded', [ $plugin, 'wire_woocommerce' ] ),
			'wire_woocommerce should be removable.'
		);

		remove_action( 'shqf_daily_cleanup', [ $plugin, 'run_daily_cleanup' ] );
		$this->assertFalse(
			has_action( 'shqf_daily_cleanup', [ $plugin, 'run_daily_cleanup' ] ),
			'run_daily_cleanup should be removable.'
		);
	}

	// ── 15G.3: Activation lifecycle ────────────────────────────────

	public function test_activate_schedules_daily_cleanup_cron(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_schedule_single_event'   => null,
		] );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_db_version', 0 )
			->andReturn( \SampleHQForm\Database\Migrator::LATEST_VERSION );

		Monkey\Functions\expect( 'wp_next_scheduled' )
			->with( 'shqf_daily_cleanup' )
			->once()
			->andReturn( false );

		Monkey\Functions\expect( 'wp_schedule_event' )
			->once()
			->with( \Mockery::type( 'int' ), 'daily', 'shqf_daily_cleanup' )
			->andReturn( true );

		$plugin = Plugin::boot();
		$plugin->activate();
	}

	public function test_activate_skips_scheduling_when_already_scheduled(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_schedule_single_event'   => null,
		] );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_db_version', 0 )
			->andReturn( \SampleHQForm\Database\Migrator::LATEST_VERSION );

		Monkey\Functions\expect( 'wp_next_scheduled' )
			->with( 'shqf_daily_cleanup' )
			->once()
			->andReturn( 1717000000 );

		Monkey\Functions\expect( 'wp_schedule_event' )->never();

		$plugin = Plugin::boot();
		$plugin->activate();
	}

	public function test_activate_schedules_upload_protection_check(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_next_scheduled'          => static fn() => false,
			'wp_schedule_event'          => null,
		] );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_db_version', 0 )
			->andReturn( \SampleHQForm\Database\Migrator::LATEST_VERSION );

		Monkey\Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with( \Mockery::on( fn( $ts ) => $ts > time() && $ts <= time() + 15 ), 'shqf_check_upload_protection' );

		$plugin = Plugin::boot();
		$plugin->activate();
	}

	// ── 15G.4: Deactivation lifecycle ──────────────────────────────

	public function test_deactivate_unschedules_daily_cleanup(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_unschedule_hook'         => null,
		] );

		$next_ts = 1717000000;
		Monkey\Functions\expect( 'wp_next_scheduled' )
			->with( 'shqf_daily_cleanup' )
			->once()
			->andReturn( $next_ts );

		Monkey\Functions\expect( 'wp_unschedule_event' )
			->once()
			->with( $next_ts, 'shqf_daily_cleanup' );

		$plugin = Plugin::boot();
		$plugin->deactivate();
	}

	public function test_deactivate_unschedules_migration_and_sync_hooks(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_next_scheduled'          => static fn() => false,
		] );

		Monkey\Functions\expect( 'wp_unschedule_hook' )
			->with( \SampleHQForm\Connection\MigrationEngine::CRON_HOOK )
			->once();

		Monkey\Functions\expect( 'wp_unschedule_hook' )
			->with( 'shqf_sync_submission' )
			->once();

		$plugin = Plugin::boot();
		$plugin->deactivate();
	}

	public function test_deactivate_cancels_in_progress_migration(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_next_scheduled'          => static fn() => false,
			'wp_unschedule_hook'         => null,
			'get_option'                 => static fn( $k, $d = false ) => match ( $k ) {
				'shqf_db_version'          => \SampleHQForm\Database\Migrator::LATEST_VERSION,
				'shqf_migration_progress'  => [ 'phase' => 'categories', 'progress' => 50 ],
				default                    => $d,
			},
		] );

		Monkey\Functions\expect( 'update_option' )
			->once()
			->with( 'shqf_migration_progress', \Mockery::on( fn( $v ) => $v['phase'] === 'cancelled' ), false );

		$plugin = Plugin::boot();
		$plugin->deactivate();
	}

	// ── 15G.5: Daily cleanup routine ──────────────────────────────

	public function test_daily_cleanup_runs_rate_limit_cleanup(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_upload_dir'              => static fn() => [ 'error' => 'skip' ],
			'set_transient'              => null,
			'current_time'               => static fn() => '2026-01-01 00:00:00',
			'get_posts'                  => static fn() => [],
			'get_option'                 => static fn( $k, $d = false ) => match ( $k ) {
				'shqf_db_version'  => \SampleHQForm\Database\Migrator::LATEST_VERSION,
				'shqf_collect_ip'  => false,
				default            => $d,
			},
		] );

		$this->wpdb()->shouldReceive( 'prepare' )
			->once()
			->with( \Mockery::on( fn( $sql ) => str_contains( $sql, 'DELETE FROM' ) && str_contains( $sql, 'window_start' ) ), \Mockery::type( 'string' ) )
			->andReturn( 'DELETE ...' );
		$this->wpdb()->shouldReceive( 'query' )
			->once()
			->with( 'DELETE ...' )
			->andReturn( 3 );

		$plugin = Plugin::boot();
		$plugin->run_daily_cleanup();
	}

	public function test_daily_cleanup_purges_ip_data_when_collect_ip_enabled(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_upload_dir'              => static fn() => [ 'error' => 'skip' ],
			'set_transient'              => null,
			'current_time'               => static fn() => '2026-01-01 00:00:00',
			'get_posts'                  => static fn() => [],
			'get_option'                 => static fn( $k, $d = false ) => match ( $k ) {
				'shqf_db_version'        => \SampleHQForm\Database\Migrator::LATEST_VERSION,
				'shqf_ip_retention_days' => 30,
				'shqf_collect_ip'        => true,
				default                  => $d,
			},
		] );

		// Rate limits cleanup.
		$this->wpdb()->shouldReceive( 'prepare' )
			->with( \Mockery::on( fn( $sql ) => str_contains( $sql, 'DELETE FROM' ) ), \Mockery::any() )
			->andReturn( 'DELETE ...' );
		$this->wpdb()->shouldReceive( 'query' )
			->with( 'DELETE ...' )
			->andReturn( 0 );

		// IP purge -- verify retention days forwarded correctly.
		$this->wpdb()->shouldReceive( 'prepare' )
			->once()
			->with( \Mockery::on( fn( $sql ) => str_contains( $sql, 'UPDATE' ) && str_contains( $sql, 'ip_address = NULL' ) ), \Mockery::type( 'string' ), 30 )
			->andReturn( 'UPDATE ...' );
		$this->wpdb()->shouldReceive( 'query' )
			->once()
			->with( 'UPDATE ...' )
			->andReturn( 5 );

		$plugin = Plugin::boot();
		$plugin->run_daily_cleanup();
	}

	public function test_daily_cleanup_skips_ip_purge_when_collect_ip_disabled(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_upload_dir'              => static fn() => [ 'error' => 'skip' ],
			'set_transient'              => null,
			'current_time'               => static fn() => '2026-01-01 00:00:00',
			'get_posts'                  => static fn() => [],
			'get_option'                 => static fn( $k, $d = false ) => match ( $k ) {
				'shqf_db_version'  => \SampleHQForm\Database\Migrator::LATEST_VERSION,
				'shqf_collect_ip'  => false,
				default            => $d,
			},
		] );

		// Rate limits cleanup only -- no UPDATE query for IP purge.
		$this->wpdb()->shouldReceive( 'prepare' )
			->with( \Mockery::on( fn( $sql ) => str_contains( $sql, 'DELETE FROM' ) ), \Mockery::any() )
			->andReturn( 'DELETE ...' );
		$this->wpdb()->shouldReceive( 'query' )
			->with( 'DELETE ...' )
			->andReturn( 0 );
		$this->wpdb()->shouldNotReceive( 'prepare' )
			->with( \Mockery::on( fn( $sql ) => str_contains( $sql, 'UPDATE' ) ), \Mockery::any(), \Mockery::any() );

		$plugin = Plugin::boot();
		$plugin->run_daily_cleanup();
	}

	public function test_daily_cleanup_deletes_orphan_uploads(): void {
		Monkey\Functions\stubs( [
			'register_activation_hook'   => null,
			'register_deactivation_hook' => null,
			'wp_upload_dir'              => static fn() => [ 'error' => 'skip' ],
			'set_transient'              => null,
			'current_time'               => static fn() => '2026-01-01 00:00:00',
			'get_option'                 => static fn( $k, $d = false ) => match ( $k ) {
				'shqf_db_version'  => \SampleHQForm\Database\Migrator::LATEST_VERSION,
				'shqf_collect_ip'  => false,
				default            => $d,
			},
		] );

		// Rate limits cleanup.
		$this->wpdb()->shouldReceive( 'prepare' )->andReturn( 'SQL' );
		$this->wpdb()->shouldReceive( 'query' )->andReturn( 0 );

		Monkey\Functions\expect( 'get_posts' )
			->once()
			->with( \Mockery::on( fn( $args ) =>
				$args['post_type'] === 'attachment'
				&& $args['meta_key'] === '_shqf_pending'
				&& $args['posts_per_page'] === 50
				&& $args['fields'] === 'ids'
			) )
			->andReturn( [ 101, 102, 103 ] );

		Monkey\Functions\expect( 'wp_delete_attachment' )
			->times( 3 )
			->with( \Mockery::anyOf( 101, 102, 103 ), true );

		$plugin = Plugin::boot();
		$plugin->run_daily_cleanup();
	}

	private function wpdb(): \Mockery\MockInterface {
		return $GLOBALS['wpdb'];
	}
}
