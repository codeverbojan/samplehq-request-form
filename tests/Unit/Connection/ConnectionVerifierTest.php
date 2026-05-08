<?php
/**
 * Tests for the ConnectionVerifier HMAC signing.
 *
 * @package SampleHQForm\Tests\Unit\Connection
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Connection;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Connection\ConnectionVerifier;

/**
 * ConnectionVerifier unit tests.
 */
class ConnectionVerifierTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private const FIXED_TS   = 1700000000;
	private const SECRET     = 'test-secret-64-chars-long-enough-for-hmac';
	private const SITE_URL   = 'https://example.com';
	private const API_URL    = 'https://acme.samplehq.io/wp-json/samplehq/v1/plugin/submissions';
	private const EMPTY_BODY = '';
	private const JSON_BODY  = '{"email":"test@example.com"}';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * sign_request returns all four required headers with correct types.
	 */
	public function test_sign_request_returns_required_headers(): void {
		Monkey\Functions\expect( 'site_url' )->once()->andReturn( self::SITE_URL );

		$headers = ( new ConnectionVerifier() )->sign_request(
			'POST',
			self::API_URL,
			self::JSON_BODY,
			self::SECRET,
			self::FIXED_TS
		);

		$this->assertSame( self::SITE_URL, $headers['X-SHQF-Site'] );
		$this->assertSame( (string) self::FIXED_TS, $headers['X-SHQF-Timestamp'] );
		$this->assertSame( 'application/json', $headers['Content-Type'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $headers['X-SHQF-Signature'] );
	}

	/**
	 * Golden-vector test: signature matches a pre-computed known-good value.
	 */
	public function test_golden_vector_signature(): void {
		Monkey\Functions\expect( 'site_url' )->once()->andReturn( self::SITE_URL );

		$body      = self::JSON_BODY;
		$body_hash = hash( 'sha256', $body );
		$message   = "POST\n" . self::API_URL . "\n" . self::FIXED_TS . "\n" . $body_hash;
		$expected  = hash_hmac( 'sha256', $message, self::SECRET );

		$headers = ( new ConnectionVerifier() )->sign_request(
			'POST',
			self::API_URL,
			$body,
			self::SECRET,
			self::FIXED_TS
		);

		$this->assertSame( $expected, $headers['X-SHQF-Signature'] );
	}

	/**
	 * Same inputs with frozen timestamp produce identical signatures.
	 */
	public function test_signature_is_deterministic(): void {
		Monkey\Functions\expect( 'site_url' )->twice()->andReturn( self::SITE_URL );

		$verifier = new ConnectionVerifier();
		$headers1 = $verifier->sign_request( 'POST', self::API_URL, self::JSON_BODY, self::SECRET, self::FIXED_TS );
		$headers2 = $verifier->sign_request( 'POST', self::API_URL, self::JSON_BODY, self::SECRET, self::FIXED_TS );

		$this->assertSame( $headers1['X-SHQF-Signature'], $headers2['X-SHQF-Signature'] );
	}

	/**
	 * Different HTTP methods produce different signatures.
	 */
	public function test_different_methods_produce_different_signatures(): void {
		Monkey\Functions\expect( 'site_url' )->twice()->andReturn( self::SITE_URL );

		$verifier    = new ConnectionVerifier();
		$headers_get = $verifier->sign_request( 'GET', self::API_URL, self::EMPTY_BODY, self::SECRET, self::FIXED_TS );
		$headers_del = $verifier->sign_request( 'DELETE', self::API_URL, self::EMPTY_BODY, self::SECRET, self::FIXED_TS );

		$this->assertNotSame( $headers_get['X-SHQF-Signature'], $headers_del['X-SHQF-Signature'] );
	}

	/**
	 * Different URLs produce different signatures.
	 */
	public function test_different_urls_produce_different_signatures(): void {
		Monkey\Functions\expect( 'site_url' )->twice()->andReturn( self::SITE_URL );

		$verifier = new ConnectionVerifier();
		$headers1 = $verifier->sign_request( 'GET', 'https://acme.samplehq.io/v1/status', self::EMPTY_BODY, self::SECRET, self::FIXED_TS );
		$headers2 = $verifier->sign_request( 'GET', 'https://other.samplehq.io/v1/status', self::EMPTY_BODY, self::SECRET, self::FIXED_TS );

		$this->assertNotSame( $headers1['X-SHQF-Signature'], $headers2['X-SHQF-Signature'] );
	}

	/**
	 * Different secrets produce different signatures.
	 */
	public function test_different_secrets_produce_different_signatures(): void {
		Monkey\Functions\expect( 'site_url' )->twice()->andReturn( self::SITE_URL );

		$verifier = new ConnectionVerifier();
		$headers1 = $verifier->sign_request( 'POST', self::API_URL, '{}', 'secret-aaa', self::FIXED_TS );
		$headers2 = $verifier->sign_request( 'POST', self::API_URL, '{}', 'secret-bbb', self::FIXED_TS );

		$this->assertNotSame( $headers1['X-SHQF-Signature'], $headers2['X-SHQF-Signature'] );
	}

	/**
	 * Query parameters in URL affect the signature.
	 */
	public function test_query_params_affect_signature(): void {
		Monkey\Functions\expect( 'site_url' )->twice()->andReturn( self::SITE_URL );

		$verifier = new ConnectionVerifier();
		$headers1 = $verifier->sign_request( 'GET', 'https://acme.samplehq.io/v1/status', self::EMPTY_BODY, self::SECRET, self::FIXED_TS );
		$headers2 = $verifier->sign_request( 'GET', 'https://acme.samplehq.io/v1/status?page=2', self::EMPTY_BODY, self::SECRET, self::FIXED_TS );

		$this->assertNotSame( $headers1['X-SHQF-Signature'], $headers2['X-SHQF-Signature'] );
	}

	/**
	 * Body content changes the signature.
	 */
	public function test_body_content_affects_signature(): void {
		Monkey\Functions\expect( 'site_url' )->twice()->andReturn( self::SITE_URL );

		$verifier = new ConnectionVerifier();
		$headers1 = $verifier->sign_request( 'POST', self::API_URL, '{"a":1}', self::SECRET, self::FIXED_TS );
		$headers2 = $verifier->sign_request( 'POST', self::API_URL, '{"a":2}', self::SECRET, self::FIXED_TS );

		$this->assertNotSame( $headers1['X-SHQF-Signature'], $headers2['X-SHQF-Signature'] );
	}

	/**
	 * HTTP method is uppercased before signing.
	 */
	public function test_method_is_uppercased(): void {
		Monkey\Functions\expect( 'site_url' )->twice()->andReturn( self::SITE_URL );

		$verifier      = new ConnectionVerifier();
		$headers_lower = $verifier->sign_request( 'post', self::API_URL, '{}', self::SECRET, self::FIXED_TS );
		$headers_upper = $verifier->sign_request( 'POST', self::API_URL, '{}', self::SECRET, self::FIXED_TS );

		$this->assertSame( $headers_lower['X-SHQF-Signature'], $headers_upper['X-SHQF-Signature'] );
	}

	/**
	 * Empty body (GET/DELETE) produces a valid signature.
	 */
	public function test_empty_body_golden_vector(): void {
		Monkey\Functions\expect( 'site_url' )->once()->andReturn( self::SITE_URL );

		$body_hash = hash( 'sha256', '' );
		$message   = "GET\n" . self::API_URL . "\n" . self::FIXED_TS . "\n" . $body_hash;
		$expected  = hash_hmac( 'sha256', $message, self::SECRET );

		$headers = ( new ConnectionVerifier() )->sign_request( 'GET', self::API_URL, '', self::SECRET, self::FIXED_TS );

		$this->assertSame( $expected, $headers['X-SHQF-Signature'] );
	}

	/**
	 * Timestamp defaults to current time when not provided.
	 */
	public function test_timestamp_defaults_to_current_time(): void {
		Monkey\Functions\expect( 'site_url' )->once()->andReturn( self::SITE_URL );

		$before  = time();
		$headers = ( new ConnectionVerifier() )->sign_request( 'GET', self::API_URL, '', self::SECRET );
		$after   = time();

		$ts = (int) $headers['X-SHQF-Timestamp'];
		$this->assertGreaterThanOrEqual( $before, $ts );
		$this->assertLessThanOrEqual( $after, $ts );
	}
}
