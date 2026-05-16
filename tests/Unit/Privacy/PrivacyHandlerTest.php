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
			'__'             => static fn( $s ) => $s,
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

	// ── Privacy policy content ──────────────────────────────────────

	public function test_register_hooks_privacy_policy_content(): void {
		$this->handler->register();

		$this->assertNotFalse(
			has_action( 'admin_init', [ $this->handler, 'add_privacy_policy_content' ] )
		);
	}

	public function test_privacy_policy_text_mentions_samplehq(): void {
		$text = $this->handler->get_privacy_policy_text();

		$this->assertStringContainsString( 'samplehq.io', $text );
		$this->assertStringContainsString( 'samplehq.io/privacy', $text );
	}

	public function test_privacy_policy_text_documents_ip_handling(): void {
		$text = $this->handler->get_privacy_policy_text();

		$this->assertStringContainsString( 'IP address', $text );
		$this->assertStringContainsString( 'spam protection', $text );
		$this->assertStringContainsString( 'diagnostic purposes', $text );
		$this->assertStringContainsString( 'IP addresses and user agent strings are not sent to SampleHQ', $text );
	}

	public function test_privacy_policy_text_documents_turnstile(): void {
		$text = $this->handler->get_privacy_policy_text();

		$this->assertStringContainsString( 'Cloudflare Turnstile', $text );
		$this->assertStringContainsString( 'cloudflare.com/privacypolicy', $text );
		$this->assertStringContainsString( 'verification token', $text );
		$this->assertStringContainsString( 'No form field data', $text );
	}

	public function test_privacy_policy_text_documents_form_submissions(): void {
		$text = $this->handler->get_privacy_policy_text();

		$this->assertStringContainsString( 'Form Submissions', $text );
		$this->assertStringContainsString( 'personal data tools', $text );
		$this->assertStringContainsString( 'page URL', $text );
	}

	public function test_add_privacy_policy_content_calls_wp_function(): void {
		Monkey\Functions\expect( 'wp_add_privacy_policy_content' )
			->once()
			->with( 'SampleHQ Request Form', Mockery::type( 'string' ) );

		$this->handler->add_privacy_policy_content();
	}

	// ── Internal meta filtering ────────────────────────────────────

	public function test_export_excludes_internal_meta_keys(): void {
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
			->andReturn( [
				'company'          => 'Acme',
				'phone'            => '555-1234',
				'job_title'        => 'Engineer',
				'_sync_error'      => 'timeout',
				'_sync_attempts'   => 3,
				'_sync_request_id' => 'req-abc',
			] );

		$result = $this->handler->export_personal_data( 'john@example.com' );
		$item   = $result['data'][0];
		$names  = array_column( $item['data'], 'name' );

		$this->assertContains( 'company', $names );
		$this->assertContains( 'phone', $names );
		$this->assertContains( 'job_title', $names );
		$this->assertNotContains( '_sync_error', $names );
		$this->assertNotContains( '_sync_attempts', $names );
		$this->assertNotContains( '_sync_request_id', $names );
	}

	// ── 15G.1: Export completeness ─────────────────────────────────

	public function test_export_includes_all_pii_fields_with_correct_values(): void {
		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'pii@example.com', 50, 0 )
			->andReturn( [
				[
					'id'         => '42',
					'email'      => 'pii@example.com',
					'first_name' => 'Jane',
					'last_name'  => 'Smith',
					'source_url' => 'https://example.com/contact',
					'ip_address' => '10.0.0.5',
					'user_agent' => 'Mozilla/5.0 TestBrowser',
					'created_at' => '2026-03-15 14:30:00',
				],
			] );

		$this->meta->shouldReceive( 'get_all' )->with( 42 )->andReturn( [] );

		$result = $this->handler->export_personal_data( 'pii@example.com' );
		$item   = $result['data'][0];
		$pairs  = [];
		foreach ( $item['data'] as $field ) {
			$pairs[ $field['name'] ] = $field['value'];
		}

		$this->assertSame( '42', $pairs['Submission ID'] );
		$this->assertSame( 'pii@example.com', $pairs['Email'] );
		$this->assertSame( 'Jane Smith', $pairs['Name'] );
		$this->assertSame( 'https://example.com/contact', $pairs['Source URL'] );
		$this->assertSame( '10.0.0.5', $pairs['IP Address'] );
		$this->assertSame( 'Mozilla/5.0 TestBrowser', $pairs['User Agent'] );
		$this->assertSame( '2026-03-15 14:30:00', $pairs['Date'] );
	}

	public function test_export_with_many_meta_fields_includes_all_non_internal(): void {
		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'meta@example.com', 50, 0 )
			->andReturn( [
				[
					'id'         => '99',
					'email'      => 'meta@example.com',
					'first_name' => 'M',
					'last_name'  => 'T',
					'source_url' => '',
					'ip_address' => '',
					'created_at' => '2026-01-01',
				],
			] );

		$meta = [];
		for ( $i = 1; $i <= 55; $i++ ) {
			$meta[ "field_{$i}" ] = "value_{$i}";
		}
		$meta['_internal_a']  = 'hidden';
		$meta['_internal_b']  = 'hidden';
		$meta['array_field']  = [ 'a', 'b', 'c' ];

		$this->meta->shouldReceive( 'get_all' )->with( 99 )->andReturn( $meta );

		$result = $this->handler->export_personal_data( 'meta@example.com' );
		$item   = $result['data'][0];
		$names  = array_column( $item['data'], 'name' );

		for ( $i = 1; $i <= 55; $i++ ) {
			$this->assertContains( "field_{$i}", $names, "field_{$i} should be exported" );
		}
		$this->assertContains( 'array_field', $names );
		$this->assertNotContains( '_internal_a', $names );
		$this->assertNotContains( '_internal_b', $names );

		$values = array_column( $item['data'], 'value', 'name' );
		$this->assertSame( '["a","b","c"]', $values['array_field'] );

		// 7 core fields + 56 meta fields (55 regular + 1 array) = 63 total
		$this->assertCount( 63, $item['data'] );
	}

	public function test_export_sixty_submissions_paginates_across_two_pages(): void {
		$page_1 = [];
		for ( $i = 1; $i <= 50; $i++ ) {
			$page_1[] = [
				'id'         => (string) $i,
				'email'      => 'paged@example.com',
				'first_name' => "U{$i}",
				'last_name'  => 'Test',
				'source_url' => '',
				'ip_address' => '',
				'created_at' => '2026-01-01',
			];
		}

		$page_2 = [];
		for ( $i = 51; $i <= 60; $i++ ) {
			$page_2[] = [
				'id'         => (string) $i,
				'email'      => 'paged@example.com',
				'first_name' => "U{$i}",
				'last_name'  => 'Test',
				'source_url' => '',
				'ip_address' => '',
				'created_at' => '2026-01-01',
			];
		}

		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'paged@example.com', 50, 0 )
			->once()
			->andReturn( $page_1 );

		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'paged@example.com', 50, 50 )
			->once()
			->andReturn( $page_2 );

		$this->meta->shouldReceive( 'get_all' )->andReturn( [] );

		$result_1 = $this->handler->export_personal_data( 'paged@example.com', 1 );
		$this->assertFalse( $result_1['done'], 'Page 1 should not be done' );
		$this->assertCount( 50, $result_1['data'] );

		$result_2 = $this->handler->export_personal_data( 'paged@example.com', 2 );
		$this->assertTrue( $result_2['done'], 'Page 2 should be done' );
		$this->assertCount( 10, $result_2['data'] );

		$this->assertSame( 'submission-51', $result_2['data'][0]['item_id'] );
	}

	// ── 15G.2: Erasure correctness ─────────────────────────────────

	public function test_erase_fifty_five_submissions_requires_two_pages(): void {
		$page_1 = [];
		for ( $i = 1; $i <= 50; $i++ ) {
			$page_1[] = [ 'id' => (string) $i, 'ip_address' => '10.0.0.1' ];
		}

		$page_2 = [];
		for ( $i = 1; $i <= 5; $i++ ) {
			$page_2[] = [ 'id' => (string) ( 50 + $i ), 'ip_address' => '10.0.0.1' ];
		}

		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'bulk-erase@example.com', 50 )
			->twice()
			->andReturn( $page_1, $page_2 );

		$this->rate_limits->shouldReceive( 'clear_for_ip' )->with( '10.0.0.1' )->twice();
		$this->meta->shouldReceive( 'delete_all' )->times( 55 );
		$this->submissions->shouldReceive( 'delete' )->times( 55 );

		$result_1 = $this->handler->erase_personal_data( 'bulk-erase@example.com' );
		$this->assertFalse( $result_1['done'], 'Page 1 signals more to erase' );
		$this->assertSame( 50, $result_1['items_removed'] );

		$result_2 = $this->handler->erase_personal_data( 'bulk-erase@example.com' );
		$this->assertTrue( $result_2['done'], 'Page 2 signals done' );
		$this->assertSame( 5, $result_2['items_removed'] );
	}

	public function test_erase_clears_rate_limits_for_each_unique_ip(): void {
		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'multi-ip@example.com', 50 )
			->andReturn( [
				[ 'id' => '1', 'ip_address' => '192.168.1.1' ],
				[ 'id' => '2', 'ip_address' => '192.168.1.2' ],
				[ 'id' => '3', 'ip_address' => '192.168.1.1' ],
				[ 'id' => '4', 'ip_address' => '192.168.1.3' ],
				[ 'id' => '5', 'ip_address' => '192.168.1.2' ],
			] );

		$this->rate_limits->shouldReceive( 'clear_for_ip' )->with( '192.168.1.1' )->once();
		$this->rate_limits->shouldReceive( 'clear_for_ip' )->with( '192.168.1.2' )->once();
		$this->rate_limits->shouldReceive( 'clear_for_ip' )->with( '192.168.1.3' )->once();

		$this->meta->shouldReceive( 'delete_all' )->times( 5 );
		$this->submissions->shouldReceive( 'delete' )->times( 5 );

		$result = $this->handler->erase_personal_data( 'multi-ip@example.com' );

		$this->assertTrue( $result['done'] );
		$this->assertSame( 5, $result['items_removed'] );
	}

	public function test_erase_skips_rate_limit_clear_for_empty_ip(): void {
		$this->submissions->shouldReceive( 'find_by_email' )
			->with( 'no-ip@example.com', 50 )
			->andReturn( [
				[ 'id' => '1', 'ip_address' => '' ],
				[ 'id' => '2', 'ip_address' => null ],
				[ 'id' => '3', 'ip_address' => '10.0.0.1' ],
			] );

		$this->rate_limits->shouldReceive( 'clear_for_ip' )->with( '10.0.0.1' )->once();
		$this->rate_limits->shouldNotReceive( 'clear_for_ip' )->with( '' );

		$this->meta->shouldReceive( 'delete_all' )->times( 3 );
		$this->submissions->shouldReceive( 'delete' )->times( 3 );

		$result = $this->handler->erase_personal_data( 'no-ip@example.com' );

		$this->assertTrue( $result['done'] );
		$this->assertSame( 3, $result['items_removed'] );
	}
}
