<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for the FormsTable repository.
 *
 * @package SampleHQForm\Tests\Unit\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Database;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\FormsTable;

/**
 * FormsTable unit tests.
 */
class FormsTableTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $wpdb;
	private FormsTable $table;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		// Allow unique_slug calls on all create tests.
		$this->wpdb->shouldReceive( 'prepare' )->andReturn( 'slug-check' )->byDefault();
		$this->wpdb->shouldReceive( 'get_var' )->andReturn( null )->byDefault();

		Monkey\Functions\stubs( [
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'sanitize_title'     => static fn( $s ) => strtolower( str_replace( ' ', '-', trim( strip_tags( (string) $s ) ) ) ),
			'wp_kses_post'       => static fn( $s ) => (string) $s,
			'wp_json_encode'     => static fn( $v ) => json_encode( $v ),
			'absint'             => static fn( $n ) => abs( (int) $n ),
			'current_time'       => static fn() => '2026-04-23 10:00:00',
		] );

		$this->table = new FormsTable( $this->wpdb );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create should insert and return ID.
	 */
	public function test_create_returns_id(): void {
		$this->wpdb->insert_id = 7;

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_forms',
				Mockery::on(
					static function ( array $row ): bool {
						return 'Sample Request' === $row['title']
							&& 'sample-request' === $row['slug']
							&& 'draft' === $row['status']
							&& 0 === $row['submissions_count'];
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$id = $this->table->create( [ 'title' => 'Sample Request' ] );
		$this->assertSame( 7, $id );
	}

	/**
	 * Create should throw without title.
	 */
	public function test_create_throws_without_title(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->table->create( [] );
	}

	/**
	 * Create should throw on insert failure.
	 */
	public function test_create_throws_on_failure(): void {
		$this->wpdb->shouldReceive( 'insert' )->once()->andReturn( false );

		$this->expectException( \RuntimeException::class );
		$this->table->create( [ 'title' => 'Fail' ] );
	}

	/**
	 * Create config should include schema_version.
	 */
	public function test_create_includes_schema_version(): void {
		$this->wpdb->insert_id = 1;

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_forms',
				Mockery::on(
					static function ( array $row ): bool {
						$config = json_decode( $row['config'], true );
						return 1 === ( $config['schema_version'] ?? null );
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$this->table->create( [ 'title' => 'Test' ] );
	}

	/**
	 * Create should accept custom config.
	 */
	public function test_create_with_custom_config(): void {
		$this->wpdb->insert_id = 1;
		$custom_config         = [ 'schema_version' => 1, 'fields' => [ [ 'type' => 'text' ] ] ];

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_forms',
				Mockery::on(
					static function ( array $row ) use ( $custom_config ): bool {
						$config = json_decode( $row['config'], true );
						return $config === $custom_config;
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$this->table->create( [ 'title' => 'Test', 'config' => $custom_config ] );
	}

	/**
	 * Get should return decoded JSON fields.
	 */
	public function test_get_decodes_json(): void {
		$raw_row = [
			'id'     => '1',
			'title'  => 'Test',
			'config' => '{"schema_version":1,"fields":[]}',
			'email_config' => '{"recipients":["admin@test.com"]}',
			'spam_config'  => null,
			'settings'     => null,
		];

		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->with( 'sql', ARRAY_A )->andReturn( $raw_row );

		$result = $this->table->get( 1 );

		$this->assertIsArray( $result );
		$this->assertIsArray( $result['config'] );
		$this->assertSame( 1, $result['config']['schema_version'] );
		$this->assertIsArray( $result['email_config'] );
		$this->assertNull( $result['spam_config'] );
	}

	/**
	 * Get by slug should work.
	 */
	public function test_get_by_slug(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn( [
			'id'           => '1',
			'slug'         => 'test',
			'config'       => '{}',
			'email_config' => null,
			'spam_config'  => null,
			'settings'     => null,
		] );

		$result = $this->table->get_by_slug( 'test' );
		$this->assertSame( '1', $result['id'] );
	}

	/**
	 * Get returns null for non-existent form.
	 */
	public function test_get_returns_null(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		$this->assertNull( $this->table->get( 999 ) );
	}

	/**
	 * Update should modify allowed fields.
	 */
	public function test_update(): void {
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_shqf_forms',
				Mockery::on(
					static function ( array $row ): bool {
						return 'New Title' === $row['title']
							&& 'published' === $row['status']
							&& isset( $row['updated_at'] );
					}
				),
				[ 'id' => 1 ],
				Mockery::type( 'array' ),
				[ '%d' ]
			)
			->andReturn( 1 );

		$this->assertTrue( $this->table->update( 1, [
			'title'  => 'New Title',
			'status' => 'published',
		] ) );
	}

	/**
	 * Update with empty data returns false.
	 */
	public function test_update_empty(): void {
		$this->assertFalse( $this->table->update( 1, [] ) );
	}

	/**
	 * Invalid status defaults to draft.
	 */
	public function test_invalid_status_defaults_to_draft(): void {
		$this->wpdb->insert_id = 1;

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_forms',
				Mockery::on(
					static function ( array $row ): bool {
						return 'draft' === $row['status'];
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$this->table->create( [ 'title' => 'Test', 'status' => 'INVALID' ] );
	}

	/**
	 * Delete should remove the row.
	 */
	public function test_delete(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_shqf_forms', [ 'id' => 3 ], [ '%d' ] )
			->andReturn( 1 );

		$this->assertTrue( $this->table->delete( 3 ) );
	}

	/**
	 * Increment submissions count uses atomic SQL.
	 */
	public function test_increment_submissions_count(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'query' )->once()->with( 'sql' )->andReturn( 1 );

		$this->assertTrue( $this->table->increment_submissions_count( 5 ) );
	}

	/**
	 * Default config has required structure.
	 */
	public function test_default_config(): void {
		$config = $this->table->default_config();

		$this->assertSame( 1, $config['schema_version'] );
		$this->assertIsArray( $config['fields'] );
		$this->assertIsArray( $config['appearance'] );
		$this->assertIsArray( $config['behavior'] );
		$this->assertSame( '#0F766E', $config['appearance']['primary_color'] );
		$this->assertSame( 'Submit Request', $config['behavior']['submit_button_text'] );
	}

	/**
	 * List returns decoded rows.
	 */
	public function test_list_decodes_json(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( [
			[
				'id'           => '1',
				'config'       => '{"schema_version":1}',
				'email_config' => null,
				'spam_config'  => null,
				'settings'     => null,
			],
		] );

		$results = $this->table->list_all();
		$this->assertCount( 1, $results );
		$this->assertIsArray( $results[0]['config'] );
	}

	/**
	 * Invalid JSON in config column decodes to empty array (not raw string).
	 */
	public function test_get_invalid_json_returns_empty_array(): void {
		$raw_row = [
			'id'           => '1',
			'title'        => 'Corrupted',
			'config'       => '{bad json!!',
			'email_config' => 'not-json',
			'spam_config'  => null,
			'settings'     => '42',
		];

		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_row' )->once()->with( 'sql', ARRAY_A )->andReturn( $raw_row );

		$result = $this->table->get( 1 );

		$this->assertIsArray( $result['config'] );
		$this->assertSame( [], $result['config'] );
		$this->assertIsArray( $result['email_config'] );
		$this->assertSame( [], $result['email_config'] );
		$this->assertNull( $result['spam_config'] );
		// '42' decodes to int 42, not an array — should return [].
		$this->assertIsArray( $result['settings'] );
		$this->assertSame( [], $result['settings'] );
	}

	/**
	 * Count returns integer.
	 */
	public function test_count(): void {
		$this->wpdb->shouldReceive( 'get_var' )->once()->andReturn( '12' );

		$this->assertSame( 12, $this->table->count() );
	}
}
