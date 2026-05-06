<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for the FormProcessor.
 *
 * @package SampleHQForm\Tests\Unit\Forms
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Forms;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\FormsTable;
use SampleHQForm\Database\RateLimitsTable;
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;
use SampleHQForm\Email\Mailer;
use SampleHQForm\Fields\FieldRegistry;
use SampleHQForm\Forms\FormProcessor;
use SampleHQForm\Forms\FormValidator;
use SampleHQForm\Spam\FormToken;
use SampleHQForm\Spam\HoneypotValidator;
use SampleHQForm\Spam\TurnstileVerifier;

/**
 * FormProcessor unit tests.
 */
class FormProcessorTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $forms;
	private $submissions;
	private $submission_meta;
	private $rate_limits;
	private FormValidator $validator;
	private $token;
	private HoneypotValidator $honeypot;
	private $mailer;
	private FormProcessor $processor;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->forms           = Mockery::mock( FormsTable::class );
		$this->submissions     = Mockery::mock( SubmissionsTable::class );
		$this->submission_meta = Mockery::mock( SubmissionMetaTable::class );
		$this->rate_limits     = Mockery::mock( RateLimitsTable::class );
		$this->token           = Mockery::mock( FormToken::class );
		$this->honeypot        = new HoneypotValidator();
		$this->mailer          = Mockery::mock( Mailer::class );

		$registry        = new FieldRegistry();
		$this->validator = new FormValidator( $registry );

		Monkey\Functions\stubs( [
			'absint'              => static fn( $n ) => abs( (int) $n ),
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'__'                  => static fn( $s ) => $s,
			'current_user_can'    => static fn() => false,
			'get_option'          => static fn( $key, $default = false ) => $default,
			'wp_rand'             => static fn( $min, $max ) => random_int( $min, $max ),
		] );

		// Turnstile: disabled by default in tests (get_option returns empty string).
		$turnstile = new TurnstileVerifier();

		$this->processor = new FormProcessor(
			$this->forms,
			$this->submissions,
			$this->submission_meta,
			$this->rate_limits,
			$this->validator,
			$this->token,
			$this->honeypot,
			$this->mailer,
			$turnstile
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Get valid submission data.
	 *
	 * @return array<string, mixed>
	 */
	private function valid_data(): array {
		return [
			'form_id'     => 1,
			'shqf_token'  => 'valid-token',
			'shqf_hp'     => '',
			'shqf_fields' => [ 'email' => 'john@example.com' ],
			'source_url'  => 'https://example.com/samples',
			'ip_address'  => '192.168.1.1',
			'user_agent'  => 'Mozilla/5.0',
		];
	}

	/**
	 * Get a published form config.
	 *
	 * @return array<string, mixed>
	 */
	private function published_form(): array {
		return [
			'id'     => '1',
			'status' => 'published',
			'config' => [
				'schema_version' => 1,
				'fields'         => [],
				'behavior'       => [
					'success_message' => 'Thank you!',
				],
			],
		];
	}

	/**
	 * Successful submission returns success with submission_id.
	 */
	public function test_successful_submission(): void {
		$form = $this->published_form();
		$this->forms->shouldReceive( 'get' )->with( 1 )->andReturn( $form );
		$this->rate_limits->shouldReceive( 'check_and_increment' )->once()->andReturn( true );
		$this->token->shouldReceive( 'validate' )->with( 1, 'valid-token' )->andReturn( true );
		$this->submissions->shouldReceive( 'create' )->once()->andReturn( 100 );
		$this->submission_meta->shouldReceive( 'add_many' )->once();
		$this->forms->shouldReceive( 'increment_submissions_count' )->with( 1 )->once();

		// Email: get submission row for mailer, then send both notifications.
		$this->submissions->shouldReceive( 'get' )->with( 100 )->andReturn( [ 'id' => 100, 'email' => 'john@example.com' ] );
		$this->mailer->shouldReceive( 'send_admin_notification' )->once()->andReturn( true );
		$this->mailer->shouldReceive( 'send_confirmation' )->once()->andReturn( true );

		$result = $this->processor->process( $this->valid_data() );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 100, $result['submission_id'] );
		$this->assertSame( 200, $result['status_code'] );
		$this->assertSame( 'Thank you!', $result['message'] );
	}

	/**
	 * Form not found returns 404.
	 */
	public function test_form_not_found(): void {
		$this->forms->shouldReceive( 'get' )->with( 1 )->andReturn( null );

		$result = $this->processor->process( $this->valid_data() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 404, $result['status_code'] );
	}

	/**
	 * Draft form returns 403.
	 */
	public function test_draft_form_rejected(): void {
		$form           = $this->published_form();
		$form['status'] = 'draft';
		$this->forms->shouldReceive( 'get' )->andReturn( $form );

		$result = $this->processor->process( $this->valid_data() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 403, $result['status_code'] );
	}

	/**
	 * Rate limited returns 429.
	 */
	public function test_rate_limited(): void {
		$this->forms->shouldReceive( 'get' )->andReturn( $this->published_form() );
		$this->rate_limits->shouldReceive( 'check_and_increment' )->andReturn( false );

		$result = $this->processor->process( $this->valid_data() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 429, $result['status_code'] );
	}

	/**
	 * Honeypot filled silently succeeds (hides detection from bots).
	 */
	public function test_honeypot_silently_succeeds(): void {
		$this->forms->shouldReceive( 'get' )->andReturn( $this->published_form() );
		$this->rate_limits->shouldReceive( 'check_and_increment' )->andReturn( true );

		$data             = $this->valid_data();
		$data['shqf_hp']  = 'bot filled this';

		$result = $this->processor->process( $data );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 0, $result['submission_id'] );
	}

	/**
	 * DB failure during submission create returns 500 error.
	 */
	public function test_db_failure_returns_error(): void {
		$this->forms->shouldReceive( 'get' )->andReturn( $this->published_form() );
		$this->rate_limits->shouldReceive( 'check_and_increment' )->andReturn( true );
		$this->token->shouldReceive( 'validate' )->andReturn( true );
		$this->submissions->shouldReceive( 'create' )
			->andThrow( new \RuntimeException( 'DB insert failed' ) );

		$result = $this->processor->process( $this->valid_data() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 500, $result['status_code'] );
		$this->assertStringContainsString( 'reload', $result['errors']['general'] );
	}

	/**
	 * Invalid token returns 403.
	 */
	public function test_invalid_token(): void {
		$this->forms->shouldReceive( 'get' )->andReturn( $this->published_form() );
		$this->rate_limits->shouldReceive( 'check_and_increment' )->andReturn( true );
		$this->token->shouldReceive( 'validate' )->andReturn( false );

		$result = $this->processor->process( $this->valid_data() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 403, $result['status_code'] );
		$this->assertStringContainsString( 'session', $result['errors']['general'] );
	}

	/**
	 * Validation errors return 422 with field-keyed errors.
	 */
	public function test_validation_errors(): void {
		// Register a mock field that always fails validation.
		$mock_field = Mockery::mock( \SampleHQForm\Fields\FieldInterface::class );
		$mock_field->shouldReceive( 'get_type' )->andReturn( 'email' );
		$mock_field->shouldReceive( 'validate' )->andReturn( 'Email is required.' );

		$registry  = new FieldRegistry();
		$registry->register( $mock_field );
		$validator = new FormValidator( $registry );

		$processor = new FormProcessor(
			$this->forms,
			$this->submissions,
			$this->submission_meta,
			$this->rate_limits,
			$validator,
			$this->token,
			$this->honeypot,
			$this->mailer,
			new TurnstileVerifier()
		);

		$form           = $this->published_form();
		$form['config'] = [
			'fields' => [
				[ 'id' => 'f_email', 'key' => 'email', 'type' => 'email', 'required' => true ],
			],
		];

		$this->forms->shouldReceive( 'get' )->andReturn( $form );
		$this->rate_limits->shouldReceive( 'check_and_increment' )->andReturn( true );
		$this->token->shouldReceive( 'validate' )->andReturn( true );

		$data                = $this->valid_data();
		$data['shqf_fields'] = [ 'email' => '' ];

		$result = $processor->process( $data );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 422, $result['status_code'] );
		$this->assertArrayHasKey( 'f_email', $result['errors'] );
	}

	/**
	 * Admin users bypass rate limiting.
	 */
	public function test_admin_bypasses_rate_limit(): void {
		Monkey\Functions\stubs( [
			'current_user_can' => static fn() => true,
		] );

		$this->forms->shouldReceive( 'get' )->andReturn( $this->published_form() );
		// Rate limits should NOT be checked.
		$this->rate_limits->shouldReceive( 'check_and_increment' )->never();
		$this->token->shouldReceive( 'validate' )->andReturn( true );
		$this->submissions->shouldReceive( 'create' )->andReturn( 1 );
		$this->submission_meta->shouldReceive( 'add_many' );
		$this->forms->shouldReceive( 'increment_submissions_count' );
		$this->submissions->shouldReceive( 'get' )->with( 1 )->andReturn( [ 'id' => 1 ] );
		$this->mailer->shouldReceive( 'send_admin_notification' )->once();
		$this->mailer->shouldReceive( 'send_confirmation' )->once();

		$result = $this->processor->process( $this->valid_data() );

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Missing form_id defaults to 0 and returns not found.
	 */
	public function test_missing_form_id(): void {
		$this->forms->shouldReceive( 'get' )->with( 0 )->andReturn( null );

		$result = $this->processor->process( [] );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 404, $result['status_code'] );
	}
}
