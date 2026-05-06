<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Spam;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Spam\TurnstileVerifier;

class TurnstileVerifierTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_not_enabled_when_no_keys(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static fn( $k, $d = '' ) => $d );

		$verifier = new TurnstileVerifier();
		$this->assertFalse( $verifier->is_enabled() );
	}

	public function test_enabled_when_both_keys_set(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static fn( $k ) => match ( $k ) {
				'shqf_turnstile_site_key'   => 'site_key_123',
				'shqf_turnstile_secret_key' => 'secret_key_456',
				default                     => '',
			} );

		$verifier = new TurnstileVerifier();
		$this->assertTrue( $verifier->is_enabled() );
	}

	public function test_verify_passes_when_disabled(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static fn( $k, $d = '' ) => $d );

		$verifier = new TurnstileVerifier();
		$this->assertTrue( $verifier->verify( 'any_token' ) );
	}

	public function test_verify_fails_on_empty_token(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static fn( $k ) => match ( $k ) {
				'shqf_turnstile_site_key'   => 'key',
				'shqf_turnstile_secret_key' => 'secret',
				default                     => '',
			} );

		$verifier = new TurnstileVerifier();
		$this->assertFalse( $verifier->verify( '' ) );
	}

	public function test_verify_succeeds_on_valid_response(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static fn( $k ) => match ( $k ) {
				'shqf_turnstile_site_key'   => 'key',
				'shqf_turnstile_secret_key' => 'secret',
				default                     => '',
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( [ 'body' => '{"success":true}' ] );

		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( '{"success":true}' );

		$verifier = new TurnstileVerifier();
		$this->assertTrue( $verifier->verify( 'valid_token' ) );
	}

	public function test_verify_fails_on_invalid_response(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static fn( $k ) => match ( $k ) {
				'shqf_turnstile_site_key'   => 'key',
				'shqf_turnstile_secret_key' => 'secret',
				default                     => '',
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( [ 'body' => '{"success":false}' ] );

		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )
			->andReturn( '{"success":false}' );

		$verifier = new TurnstileVerifier();
		$this->assertFalse( $verifier->verify( 'invalid_token' ) );
	}

	public function test_verify_fails_closed_on_network_error(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static fn( $k ) => match ( $k ) {
				'shqf_turnstile_site_key'   => 'key',
				'shqf_turnstile_secret_key' => 'secret',
				default                     => '',
			} );

		$wp_error = \Mockery::mock( \WP_Error::class );
		$wp_error->shouldReceive( 'get_error_message' )->andReturn( 'cURL timeout' );
		Monkey\Functions\expect( 'wp_remote_post' )->andReturn( $wp_error );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( true );
		Monkey\Functions\expect( 'set_transient' )->once()->with(
			TurnstileVerifier::FAILURE_TRANSIENT,
			'cURL timeout',
			3600
		);

		$verifier = new TurnstileVerifier();
		$this->assertFalse( $verifier->verify( 'some_token' ) );
	}

	public function test_verify_fails_closed_on_5xx_status(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static fn( $k ) => match ( $k ) {
				'shqf_turnstile_site_key'   => 'key',
				'shqf_turnstile_secret_key' => 'secret',
				default                     => '',
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( [ 'response' => [ 'code' => 502 ] ] );

		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 502 );
		Monkey\Functions\expect( 'set_transient' )->once()->with(
			TurnstileVerifier::FAILURE_TRANSIENT,
			'HTTP 502',
			3600
		);

		$verifier = new TurnstileVerifier();
		$this->assertFalse( $verifier->verify( 'some_token' ) );
	}

	public function test_verify_fails_closed_on_4xx_status(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static fn( $k ) => match ( $k ) {
				'shqf_turnstile_site_key'   => 'key',
				'shqf_turnstile_secret_key' => 'secret',
				default                     => '',
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( [ 'response' => [ 'code' => 401 ] ] );

		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 401 );
		Monkey\Functions\expect( 'set_transient' )->once()->with(
			TurnstileVerifier::FAILURE_TRANSIENT,
			'HTTP 401',
			3600
		);

		$verifier = new TurnstileVerifier();
		$this->assertFalse( $verifier->verify( 'some_token' ) );
	}

	public function test_verify_fails_closed_on_invalid_json(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static fn( $k ) => match ( $k ) {
				'shqf_turnstile_site_key'   => 'key',
				'shqf_turnstile_secret_key' => 'secret',
				default                     => '',
			} );

		Monkey\Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( [ 'body' => 'not json' ] );

		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Monkey\Functions\expect( 'wp_remote_retrieve_body' )->andReturn( 'not json' );
		Monkey\Functions\expect( 'set_transient' )->once()->with(
			TurnstileVerifier::FAILURE_TRANSIENT,
			'Invalid JSON response',
			3600
		);

		$verifier = new TurnstileVerifier();
		$this->assertFalse( $verifier->verify( 'some_token' ) );
	}

	public function test_get_recent_failure_returns_transient(): void {
		Monkey\Functions\expect( 'get_transient' )
			->with( TurnstileVerifier::FAILURE_TRANSIENT )
			->andReturn( 'cURL timeout' );

		$this->assertSame( 'cURL timeout', TurnstileVerifier::get_recent_failure() );
	}

	public function test_get_recent_failure_returns_false_when_none(): void {
		Monkey\Functions\expect( 'get_transient' )
			->with( TurnstileVerifier::FAILURE_TRANSIENT )
			->andReturn( false );

		$this->assertFalse( TurnstileVerifier::get_recent_failure() );
	}

	public function test_get_site_key(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static fn( $k ) => match ( $k ) {
				'shqf_turnstile_site_key' => 'my_site_key',
				default                   => '',
			} );

		$verifier = new TurnstileVerifier();
		$this->assertSame( 'my_site_key', $verifier->get_site_key() );
	}
}
