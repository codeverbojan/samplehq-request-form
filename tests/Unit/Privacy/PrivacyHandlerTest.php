<?php
/**
 * Tests for PrivacyHandler.
 *
 * @package SampleHQForm\Tests\Unit\Privacy
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Privacy;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\RateLimitsTable;
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;
use SampleHQForm\Privacy\PrivacyHandler;

/**
 * PrivacyHandler unit tests.
 */
class PrivacyHandlerTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $submissions;
	private $meta;
	private $rate_limits;
	private PrivacyHandler $handler;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'__'            => static fn( $s ) => $s,
			'wp_json_encode' => static fn( $v ) => json_encode( $v ),
		] );

		$this->submissions = Mockery::mock( SubmissionsTable::class );
		$this->meta        = Mockery::mock( SubmissionMetaTable::class );
		$this->rate_limits = Mockery::mock( RateLimitsTable::class );
		$this->handler     = new PrivacyHandler( $this->submissions, $this->meta, $this->rate_limits );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_exporter(): void {
		$exporters = $this->handler->register_exporter( [] );

		$this->assertArrayHasKey( 'samplehq-request-form', $exporters );
		$this->assertSame( 'SampleHQ Form Submissions', $exporters['samplehq-request-form']['exporter_friendly_name'] );
	}

	public function test_register_eraser(): void {
		$erasers = $this->handler->register_eraser( [] );

		$this->assertArrayHasKey( 'samplehq-request-form', $erasers );
		$this->assertSame( 'SampleHQ Form Submissions', $erasers['samplehq-request-form']['eraser_friendly_name'] );
	}

	public function test_export_returns_submission_data(): void {
		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'john@example.com', 50, 0 )
			->andReturn( [
				[
					'id'         => '10',
					'email'      => 'john@example.com',
					'first_name' => 'John',
					'last_name'  => 'Doe',
					'source_url' => 'https://example.com',
					'ip_address' => '192.168.1.1',
					'created_at' => '2026-01-01 10:00:00',
				],
			] );

		$this->meta->shouldReceive( 'get_all' )
			->with( 10 )
			->andReturn( [ 'company' => 'Acme' ] );

		$result = $this->handler->export_personal_data( 'john@example.com' );

		$this->assertTrue( $result['done'] );
		$this->assertCount( 1, $result['data'] );

		$item = $result['data'][0];
		$this->assertSame( 'samplehq-form-submissions', $item['group_id'] );
		$this->assertSame( 'submission-10', $item['item_id'] );

		// Check data includes email, name, IP, and field meta.
		$names = array_column( $item['data'], 'name' );
		$this->assertContains( 'Email', $names );
		$this->assertContains( 'IP Address', $names );
		$this->assertContains( 'User Agent', $names );
		$this->assertContains( 'company', $names );
	}

	public function test_export_no_submissions(): void {
		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'nobody@example.com', 50, 0 )
			->andReturn( [] );

		$result = $this->handler->export_personal_data( 'nobody@example.com' );

		$this->assertTrue( $result['done'] );
		$this->assertCount( 0, $result['data'] );
	}

	public function test_export_paginates_with_done_false_on_full_page(): void {
		$full_page = array_fill( 0, 50, [
			'id'         => '1',
			'email'      => 'bulk@example.com',
			'first_name' => 'B',
			'last_name'  => 'U',
			'source_url' => '',
			'ip_address' => '',
			'created_at' => '2026-01-01',
		] );

		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'bulk@example.com', 50, 0 )
			->andReturn( $full_page );

		$this->meta->shouldReceive( 'get_all' )->andReturn( [] );

		$result = $this->handler->export_personal_data( 'bulk@example.com', 1 );

		$this->assertFalse( $result['done'] );
		$this->assertCount( 50, $result['data'] );
	}

	public function test_export_page_2_uses_correct_offset(): void {
		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'bulk@example.com', 50, 50 )
			->andReturn( [
				[
					'id'         => '51',
					'email'      => 'bulk@example.com',
					'first_name' => 'B',
					'last_name'  => 'U',
					'source_url' => '',
					'ip_address' => '',
					'created_at' => '2026-01-01',
				],
			] );

		$this->meta->shouldReceive( 'get_all' )->andReturn( [] );

		$result = $this->handler->export_personal_data( 'bulk@example.com', 2 );

		$this->assertTrue( $result['done'] );
		$this->assertCount( 1, $result['data'] );
	}

	public function test_erase_deletes_submissions_meta_and_rate_limits(): void {
		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'john@example.com', 50 )
			->andReturn( [
				[ 'id' => '10', 'ip_address' => '192.168.1.1' ],
				[ 'id' => '20', 'ip_address' => '192.168.1.1' ],
			] );

		// Rate limits cleared once per unique IP.
		$this->rate_limits->shouldReceive( 'clear_for_ip' )->with( '192.168.1.1' )->once();

		$this->meta->shouldReceive( 'delete_all' )->with( 10 )->once();
		$this->meta->shouldReceive( 'delete_all' )->with( 20 )->once();
		$this->submissions->shouldReceive( 'delete' )->with( 10 )->once();
		$this->submissions->shouldReceive( 'delete' )->with( 20 )->once();

		$result = $this->handler->erase_personal_data( 'john@example.com' );

		$this->assertTrue( $result['done'] );
		$this->assertSame( 2, $result['items_removed'] );
		$this->assertSame( 0, $result['items_retained'] );
	}

	public function test_erase_no_submissions(): void {
		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'nobody@example.com', 50 )
			->andReturn( [] );

		$result = $this->handler->erase_personal_data( 'nobody@example.com' );

		$this->assertTrue( $result['done'] );
		$this->assertSame( 0, $result['items_removed'] );
	}

	public function test_erase_paginates_with_done_false_on_full_page(): void {
		$full_page = [];
		for ( $i = 1; $i <= 50; $i++ ) {
			$full_page[] = [ 'id' => (string) $i, 'ip_address' => '10.0.0.1' ];
		}

		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'bulk@example.com', 50 )
			->andReturn( $full_page );

		$this->rate_limits->shouldReceive( 'clear_for_ip' )->once();
		$this->meta->shouldReceive( 'delete_all' )->times( 50 );
		$this->submissions->shouldReceive( 'delete' )->times( 50 );

		$result = $this->handler->erase_personal_data( 'bulk@example.com' );

		$this->assertFalse( $result['done'] );
		$this->assertSame( 50, $result['items_removed'] );
	}
}
