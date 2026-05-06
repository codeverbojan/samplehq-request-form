<?php
/**
 * Tests for the RateLimitsTable repository.
 *
 * @package SampleHQForm\Tests\Unit\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Database;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\RateLimitsTable;

/**
 * RateLimitsTable unit tests.
 */
class RateLimitsTableTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $wpdb;
	private RateLimitsTable $table;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		Monkey\Functions\stubs( [
			'current_time'  => static fn() => '2026-04-23 12:00:00',
			'apply_filters' => static function ( string $hook, $default ) {
				return $default;
			},
		] );

		$this->table = new RateLimitsTable( $this->wpdb );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * First request (new row inserted) should always be allowed.
	 */
	public function test_first_request_allowed(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'upsert_sql' );
		$this->wpdb->shouldReceive( 'query' )->once()->with( 'upsert_sql' )->andReturn( 1 );
		$this->wpdb->rows_affected = 1; // INSERT = 1 row affected.

		$this->assertTrue( $this->table->check_and_increment( '192.168.1.1', 5 ) );
	}

	/**
	 * Request at the limit should still be allowed (count = 10 <= 10).
	 */
	public function test_request_at_limit_allowed(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql1' );
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 2 );
		$this->wpdb->rows_affected = 2; // ON DUPLICATE KEY UPDATE = 2 rows affected.
		$this->wpdb->insert_id     = 10; // LAST_INSERT_ID() = new count.

		$this->assertTrue( $this->table->check_and_increment( '10.0.0.1', 1 ) );
	}

	/**
	 * Request over the limit should be denied (count = 11 > 10).
	 */
	public function test_request_over_limit_denied(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql1' );
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 2 );
		$this->wpdb->rows_affected = 2;
		$this->wpdb->insert_id     = 11;

		$this->assertFalse( $this->table->check_and_increment( '10.0.0.1', 1 ) );
	}

	/**
	 * Custom limit via filter should be respected.
	 */
	public function test_custom_limit_via_filter(): void {
		Monkey\Functions\stubs( [
			'apply_filters' => static function ( string $hook, $default ) {
				if ( 'shqf_rate_limit' === $hook ) {
					return 5;
				}
				return $default;
			},
		] );

		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql1' );
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 2 );
		$this->wpdb->rows_affected = 2;
		$this->wpdb->insert_id     = 6;

		// Count 6 > custom limit 5 = denied.
		$this->assertFalse( $this->table->check_and_increment( '10.0.0.1', 1 ) );
	}

	/**
	 * Query failure fails closed (denies the request).
	 */
	public function test_query_failure_fails_closed(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql1' );
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( false );

		$this->assertFalse( $this->table->check_and_increment( '10.0.0.1', 1 ) );
	}

	/**
	 * No separate SELECT query after the upsert (race condition fix).
	 */
	public function test_no_separate_select_after_upsert(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql1' );
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 2 );
		$this->wpdb->shouldReceive( 'get_var' )->never();
		$this->wpdb->rows_affected = 2;
		$this->wpdb->insert_id     = 5;

		$this->table->check_and_increment( '10.0.0.1', 1 );
	}

	/**
	 * Window reset (count reset to 1) should always be allowed.
	 */
	public function test_window_reset_allowed(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql1' );
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 2 );
		$this->wpdb->rows_affected = 2;
		$this->wpdb->insert_id     = 1; // Reset to 1.

		$this->assertTrue( $this->table->check_and_increment( '10.0.0.1', 1 ) );
	}

	/**
	 * Cleanup deletes expired rows.
	 */
	public function test_cleanup(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'delete_sql' );
		$this->wpdb->shouldReceive( 'query' )->once()->with( 'delete_sql' )->andReturn( 5 );

		$this->assertSame( 5, $this->table->cleanup() );
	}

	/**
	 * Cleanup returns 0 on failure.
	 */
	public function test_cleanup_returns_zero_on_failure(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( false );

		$this->assertSame( 0, $this->table->cleanup() );
	}

	/**
	 * clear_for_ip deletes all rows for a given IP.
	 */
	public function test_clear_for_ip(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_shqf_rate_limits', [ 'ip_address' => '192.168.1.1' ], [ '%s' ] )
			->andReturn( 2 );

		$this->assertTrue( $this->table->clear_for_ip( '192.168.1.1' ) );
	}

	/**
	 * clear_for_form deletes all rows for a given form.
	 */
	public function test_clear_for_form(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_shqf_rate_limits', [ 'form_id' => 5 ], [ '%d' ] )
			->andReturn( 1 );

		$this->assertTrue( $this->table->clear_for_form( 5 ) );
	}
}
