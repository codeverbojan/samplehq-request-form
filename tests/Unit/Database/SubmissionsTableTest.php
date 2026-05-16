<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for the SubmissionsTable repository.
 *
 * @package SampleHQForm\Tests\Unit\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Database;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\SubmissionsTable;

/**
 * SubmissionsTable unit tests.
 */
class SubmissionsTableTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $wpdb;
	private SubmissionsTable $table;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		Monkey\Functions\stubs( [
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'sanitize_email'     => static fn( $s ) => (string) $s,
			'esc_url_raw'        => static fn( $s ) => (string) $s,
			'absint'             => static fn( $n ) => abs( (int) $n ),
			'current_time'       => static fn() => '2026-04-23 12:00:00',
		] );

		$this->table = new SubmissionsTable( $this->wpdb );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create denormalizes email, first_name, last_name.
	 */
	public function test_create_denormalizes_fields(): void {
		$this->wpdb->insert_id = 100;

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_submissions',
				Mockery::on(
					static function ( array $row ): bool {
						return 5 === $row['form_id']
							&& 'new' === $row['status']
							&& 'john@example.com' === $row['email']
							&& 'John' === $row['first_name']
							&& 'Doe' === $row['last_name']
							&& 'https://example.com/samples' === $row['source_url']
							&& '192.168.1.1' === $row['ip_address']
							&& 0 === $row['is_starred']
							&& 0 === $row['is_read'];
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$id = $this->table->create(
			5,
			[
				'email'      => 'john@example.com',
				'first_name' => 'John',
				'last_name'  => 'Doe',
			],
			[
				'source_url' => 'https://example.com/samples',
				'ip_address' => '192.168.1.1',
				'user_agent' => 'Mozilla/5.0',
			]
		);

		$this->assertSame( 100, $id );
	}

	/**
	 * Create throws on insert failure.
	 */
	public function test_create_throws_on_failure(): void {
		$this->wpdb->shouldReceive( 'insert' )->once()->andReturn( false );

		$this->expectException( \RuntimeException::class );
		$this->table->create( 1, [] );
	}

	/**
	 * Create with minimal data (no fields, no meta).
	 */
	public function test_create_minimal(): void {
		$this->wpdb->insert_id = 1;

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_submissions',
				Mockery::on(
					static function ( array $row ): bool {
						return null === $row['email']
							&& null === $row['first_name']
							&& null === $row['source_url']
							&& null === $row['ip_address'];
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$this->assertSame( 1, $this->table->create( 1, [] ) );
	}

	/**
	 * Update status validates input.
	 */
	public function test_update_status(): void {
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_shqf_submissions',
				Mockery::on(
					static function ( array $row ): bool {
						return 'spam' === $row['status'] && isset( $row['updated_at'] );
					}
				),
				[ 'id' => 10 ],
				[ '%s', '%s' ],
				[ '%d' ]
			)
			->andReturn( 1 );

		$this->assertTrue( $this->table->update_status( 10, 'spam' ) );
	}

	/**
	 * Invalid status defaults to new.
	 */
	public function test_invalid_status_defaults_to_new(): void {
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_shqf_submissions',
				Mockery::on(
					static function ( array $row ): bool {
						return 'new' === $row['status'];
					}
				),
				Mockery::type( 'array' ),
				Mockery::type( 'array' ),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$this->table->update_status( 1, 'INVALID' );
	}

	/**
	 * Set starred toggles the flag.
	 */
	public function test_set_starred(): void {
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_shqf_submissions',
				Mockery::on(
					static function ( array $row ): bool {
						return 1 === $row['is_starred'];
					}
				),
				[ 'id' => 5 ],
				[ '%d', '%s' ],
				[ '%d' ]
			)
			->andReturn( 1 );

		$this->assertTrue( $this->table->set_starred( 5, true ) );
	}

	/**
	 * Set read toggles the flag.
	 */
	public function test_set_read(): void {
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_shqf_submissions',
				Mockery::on(
					static function ( array $row ): bool {
						return 1 === $row['is_read'];
					}
				),
				[ 'id' => 3 ],
				[ '%d', '%s' ],
				[ '%d' ]
			)
			->andReturn( 1 );

		$this->assertTrue( $this->table->set_read( 3, true ) );
	}

	/**
	 * Mark synced stores the SHQ request ID.
	 */
	public function test_mark_synced(): void {
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_shqf_submissions',
				Mockery::on(
					static function ( array $row ): bool {
						return 1 === $row['synced_to_shq'] && 999 === $row['shq_request_id'];
					}
				),
				[ 'id' => 7 ],
				[ '%d', '%d', '%s' ],
				[ '%d' ]
			)
			->andReturn( 1 );

		$this->assertTrue( $this->table->mark_synced( 7, 999 ) );
	}

	/**
	 * Delete removes the row.
	 */
	public function test_delete(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_shqf_submissions', [ 'id' => 3 ], [ '%d' ] )
			->andReturn( 1 );

		$this->assertTrue( $this->table->delete( 3 ) );
	}

	/**
	 * Find by email returns matching submissions.
	 */
	public function test_find_by_email(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->with( 'sql', ARRAY_A )
			->andReturn( [ [ 'id' => '1', 'email' => 'test@test.com' ] ] );

		$results = $this->table->find_by_email( 'test@test.com' );
		$this->assertCount( 1, $results );
	}

	/**
	 * Find by email returns empty array when no matches.
	 */
	public function test_find_by_email_empty(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( null );

		$this->assertSame( [], $this->table->find_by_email( 'none@test.com' ) );
	}

	/**
	 * List with form_id filter.
	 */
	public function test_list_with_form_filter(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->with( 'sql', ARRAY_A )
			->andReturn( [ [ 'id' => '1' ] ] );

		$results = $this->table->list_all( [ 'form_id' => 5 ] );
		$this->assertCount( 1, $results );
	}

	/**
	 * Count returns integer.
	 */
	public function test_count(): void {
		$this->wpdb->shouldReceive( 'get_var' )->once()->andReturn( '42' );

		$this->assertSame( 42, $this->table->count() );
	}

	/**
	 * User agent is truncated to 500 chars.
	 */
	public function test_user_agent_truncated(): void {
		$this->wpdb->insert_id = 1;
		$long_ua               = str_repeat( 'A', 1000 );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_submissions',
				Mockery::on(
					static function ( array $row ) {
						return 500 === strlen( $row['user_agent'] );
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$this->table->create( 1, [], [ 'user_agent' => $long_ua ] );
	}

	public function test_get_distinct_months_uses_prepare(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				Mockery::on(
					static fn( string $sql ) => str_contains( $sql, 'SELECT DISTINCT YEAR' )
						&& str_contains( $sql, 'status NOT IN' )
				),
				'trash',
				'spam'
			)
			->andReturn( "SELECT DISTINCT YEAR(created_at) AS year, MONTH(created_at) AS month FROM wp_shqf_submissions WHERE status NOT IN ('trash','spam') ORDER BY year DESC, month DESC" );

		$this->wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( [
				[ 'year' => '2026', 'month' => '5' ],
				[ 'year' => '2026', 'month' => '4' ],
			] );

		$result = $this->table->get_distinct_months();

		$this->assertCount( 2, $result );
		$this->assertSame( 2026, $result[0]['year'] );
		$this->assertSame( 5, $result[0]['month'] );
		$this->assertSame( 4, $result[1]['month'] );
	}

	public function test_get_distinct_months_returns_empty_for_no_results(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( '' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );

		$this->assertSame( [], $this->table->get_distinct_months() );
	}

	public function test_get_distinct_months_returns_empty_on_null_db_error(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( '' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( null );

		$this->assertSame( [], $this->table->get_distinct_months() );
	}

	// ── get_sync_failure_summary() ───────────────────────────────────

	public function test_get_sync_failure_summary_categorizes_errors(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				Mockery::on(
					static fn( string $sql ) => str_contains( $sql, 'COUNT(DISTINCT' )
						&& str_contains( $sql, 'INNER JOIN' )
						&& str_contains( $sql, 'synced_to_shq = 0' )
				),
				Mockery::andAnyOtherArgs()
			)
			->andReturn( 'prepared_sql' );

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->with( 'prepared_sql', ARRAY_A )
			->andReturn( [ 'total' => '5', 'auth' => '2', 'plan_limit' => '1' ] );

		$summary = $this->table->get_sync_failure_summary();

		$this->assertSame( 5, $summary['total'] );
		$this->assertSame( 2, $summary['auth'] );
		$this->assertSame( 1, $summary['plan_limit'] );
		$this->assertSame( 2, $summary['other'] );
	}

	public function test_get_sync_failure_summary_returns_zero_when_none(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared_sql' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->with( 'prepared_sql', ARRAY_A )
			->andReturn( [ 'total' => '0', 'auth' => '0', 'plan_limit' => '0' ] );

		$summary = $this->table->get_sync_failure_summary();

		$this->assertSame( 0, $summary['total'] );
		$this->assertSame( 0, $summary['auth'] );
		$this->assertSame( 0, $summary['plan_limit'] );
		$this->assertSame( 0, $summary['other'] );
	}

	public function test_get_sync_failure_summary_returns_zero_on_null_result(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared_sql' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		$summary = $this->table->get_sync_failure_summary();

		$this->assertSame( 0, $summary['total'] );
		$this->assertSame( 0, $summary['other'] );
	}

	public function test_get_sync_failure_summary_other_is_remainder(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared_sql' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->with( 'prepared_sql', ARRAY_A )
			->andReturn( [ 'total' => '10', 'auth' => '3', 'plan_limit' => '2' ] );

		$summary = $this->table->get_sync_failure_summary();

		$this->assertSame( 5, $summary['other'] );
	}

	public function test_get_sync_failure_summary_uses_prepare_with_patterns(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				Mockery::type( 'string' ),
				'%auth%',
				'%signature%',
				'%401%',
				'%not connected%',
				'%no connection%',
				'%timestamp%',
				'%decrypt%',
				'%plan%',
				'%limit%',
				'%auth%',
				'%signature%',
				'%401%',
				'%not connected%',
				'%no connection%',
				'%timestamp%',
				'%decrypt%',
				'_sync_error',
				'trash',
				'spam'
			)
			->andReturn( 'prepared_sql' );

		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn( [ 'total' => '0', 'auth' => '0', 'plan_limit' => '0' ] );

		$this->table->get_sync_failure_summary();
	}

	// ── get_sync_failure_ids() ───────────────────────────────────────

	public function test_get_sync_failure_ids_returns_int_array(): void {
		$this->wpdb->shouldReceive( 'prepare' )
			->once()
			->with(
				Mockery::on(
					static fn( string $sql ) => str_contains( $sql, 'SELECT DISTINCT s.id' )
				),
				'_sync_error',
				'trash',
				'spam'
			)
			->andReturn( 'prepared_sql' );

		$this->wpdb->shouldReceive( 'get_col' )
			->once()
			->with( 'prepared_sql' )
			->andReturn( [ '10', '20', '30' ] );

		$ids = $this->table->get_sync_failure_ids();

		$this->assertSame( [ 10, 20, 30 ], $ids );
	}

	public function test_get_sync_failure_ids_returns_empty_when_none(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'prepared_sql' );
		$this->wpdb->shouldReceive( 'get_col' )->once()->with( 'prepared_sql' )->andReturn( [] );

		$this->assertSame( [], $this->table->get_sync_failure_ids() );
	}
}
