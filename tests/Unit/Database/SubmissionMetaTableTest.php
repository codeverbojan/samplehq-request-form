<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for the SubmissionMetaTable repository.
 *
 * @package SampleHQForm\Tests\Unit\Database
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Database;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\SubmissionMetaTable;

/**
 * SubmissionMetaTable unit tests.
 */
class SubmissionMetaTableTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $wpdb;
	private SubmissionMetaTable $table;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';

		Monkey\Functions\stubs( [
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'wp_json_encode'     => static fn( $v ) => json_encode( $v ),
		] );

		$this->table = new SubmissionMetaTable( $this->wpdb );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Add stores string value with short copy.
	 */
	public function test_add_string_value(): void {
		$this->wpdb->insert_id = 1;

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_submission_meta',
				Mockery::on(
					static function ( array $row ): bool {
						return 1 === $row['submission_id']
							&& 'email' === $row['field_key']
							&& 'john@example.com' === $row['field_value']
							&& 'john@example.com' === $row['field_value_short'];
					}
				),
				[ '%d', '%s', '%s', '%s' ]
			)
			->andReturn( 1 );

		$id = $this->table->add( 1, 'email', 'john@example.com' );
		$this->assertSame( 1, $id );
	}

	/**
	 * Add stores array value as JSON.
	 */
	public function test_add_array_value(): void {
		$this->wpdb->insert_id = 2;
		$value                 = [ [ 'id' => 1, 'quantity' => 2 ], [ 'id' => 3, 'quantity' => 1 ] ];

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_submission_meta',
				Mockery::on(
					static function ( array $row ) use ( $value ): bool {
						return 'samples' === $row['field_key']
							&& json_encode( $value ) === $row['field_value'];
					}
				),
				[ '%d', '%s', '%s', '%s' ]
			)
			->andReturn( 1 );

		$this->table->add( 1, 'samples', $value );
	}

	/**
	 * Add truncates field_value_short to 191 chars.
	 */
	public function test_add_truncates_short_value(): void {
		$this->wpdb->insert_id = 3;
		$long_value            = str_repeat( 'X', 300 );

		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_shqf_submission_meta',
				Mockery::on(
					static function ( array $row ): bool {
						return 191 === mb_strlen( $row['field_value_short'] )
							&& 300 === strlen( $row['field_value'] );
					}
				),
				[ '%d', '%s', '%s', '%s' ]
			)
			->andReturn( 1 );

		$this->table->add( 1, 'long_field', $long_value );
	}

	/**
	 * Add throws on failure.
	 */
	public function test_add_throws_on_failure(): void {
		$this->wpdb->shouldReceive( 'insert' )->once()->andReturn( false );

		$this->expectException( \RuntimeException::class );
		$this->table->add( 1, 'key', 'val' );
	}

	/**
	 * Add many inserts all fields.
	 */
	public function test_add_many(): void {
		$this->wpdb->insert_id = 1;
		$this->wpdb->shouldReceive( 'insert' )->times( 3 )->andReturn( 1 );

		$this->table->add_many( 1, [
			'email'      => 'test@test.com',
			'first_name' => 'John',
			'message'    => 'Hello',
		] );
	}

	/**
	 * Get all returns associative array.
	 */
	public function test_get_all(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->with( 'sql', ARRAY_A )
			->andReturn( [
				[ 'field_key' => 'email', 'field_value' => 'john@test.com' ],
				[ 'field_key' => 'first_name', 'field_value' => 'John' ],
			] );

		$meta = $this->table->get_all( 1 );

		$this->assertSame( 'john@test.com', $meta['email'] );
		$this->assertSame( 'John', $meta['first_name'] );
	}

	/**
	 * Get all returns empty array for no meta.
	 */
	public function test_get_all_empty(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_results' )->once()->andReturn( null );

		$this->assertSame( [], $this->table->get_all( 99 ) );
	}

	/**
	 * Get single value.
	 */
	public function test_get_single(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_var' )->once()->with( 'sql' )->andReturn( 'john@test.com' );

		$this->assertSame( 'john@test.com', $this->table->get( 1, 'email' ) );
	}

	/**
	 * Get returns null for missing key.
	 */
	public function test_get_returns_null(): void {
		$this->wpdb->shouldReceive( 'prepare' )->once()->andReturn( 'sql' );
		$this->wpdb->shouldReceive( 'get_var' )->once()->andReturn( null );

		$this->assertNull( $this->table->get( 1, 'missing' ) );
	}

	/**
	 * Delete all removes all meta for a submission.
	 */
	public function test_delete_all(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with( 'wp_shqf_submission_meta', [ 'submission_id' => 5 ], [ '%d' ] )
			->andReturn( 3 );

		$this->assertTrue( $this->table->delete_all( 5 ) );
	}

	/**
	 * Delete single removes one meta row.
	 */
	public function test_delete_single(): void {
		$this->wpdb->shouldReceive( 'delete' )
			->once()
			->with(
				'wp_shqf_submission_meta',
				[ 'submission_id' => 1, 'field_key' => 'email' ],
				[ '%d', '%s' ]
			)
			->andReturn( 1 );

		$this->assertTrue( $this->table->delete( 1, 'email' ) );
	}
}
