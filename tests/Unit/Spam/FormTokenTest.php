<?php
/**
 * Tests for the FormToken CSRF protection.
 *
 * @package SampleHQForm\Tests\Unit\Spam
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Spam;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Spam\FormToken;

/**
 * FormToken unit tests.
 */
class FormTokenTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Generate creates a token and stores it in a transient.
	 */
	public function test_generate_creates_token(): void {
		Monkey\Functions\expect( 'wp_generate_password' )
			->once()
			->with( 32, false )
			->andReturn( 'abc123def456' );

		Monkey\Functions\expect( 'set_transient' )
			->once()
			->with(
				'shqf_token_5_abc123def456',
				5,
				\Mockery::type( 'int' )
			)
			->andReturn( true );

		$form_token = new FormToken();
		$token      = $form_token->generate( 5 );

		$this->assertSame( 'abc123def456', $token );
	}

	/**
	 * Validate returns true for a valid token and deletes it.
	 */
	public function test_validate_valid_token(): void {
		Monkey\Functions\expect( 'get_transient' )
			->once()
			->with( 'shqf_token_5_valid-token' )
			->andReturn( 5 );

		Monkey\Functions\expect( 'delete_transient' )
			->once()
			->with( 'shqf_token_5_valid-token' )
			->andReturn( true );

		$form_token = new FormToken();
		$this->assertTrue( $form_token->validate( 5, 'valid-token' ) );
	}

	/**
	 * Validate returns false for an empty token.
	 */
	public function test_validate_empty_token(): void {
		$form_token = new FormToken();
		$this->assertFalse( $form_token->validate( 5, '' ) );
	}

	/**
	 * Validate returns false for a nonexistent token (expired or never created).
	 */
	public function test_validate_expired_token(): void {
		Monkey\Functions\expect( 'get_transient' )
			->once()
			->with( 'shqf_token_5_expired-token' )
			->andReturn( false );

		$form_token = new FormToken();
		$this->assertFalse( $form_token->validate( 5, 'expired-token' ) );
	}

	/**
	 * Validate returns false when form_id doesn't match stored value.
	 */
	public function test_validate_wrong_form_id(): void {
		Monkey\Functions\expect( 'get_transient' )
			->once()
			->with( 'shqf_token_10_stolen-token' )
			->andReturn( 5 ); // Stored form_id is 5, but submitted for form 10.

		$form_token = new FormToken();
		$this->assertFalse( $form_token->validate( 10, 'stolen-token' ) );
	}

	/**
	 * Peek returns true for a valid token without deleting it.
	 */
	public function test_peek_valid_token_does_not_consume(): void {
		Monkey\Functions\expect( 'get_transient' )
			->once()
			->with( 'shqf_token_5_peek-token' )
			->andReturn( 5 );

		Monkey\Functions\expect( 'delete_transient' )->never();

		$form_token = new FormToken();
		$this->assertTrue( $form_token->peek( 5, 'peek-token' ) );
	}

	/**
	 * Peek returns false for empty token.
	 */
	public function test_peek_empty_token(): void {
		$form_token = new FormToken();
		$this->assertFalse( $form_token->peek( 5, '' ) );
	}

	/**
	 * Peek returns false for expired token.
	 */
	public function test_peek_expired_token(): void {
		Monkey\Functions\expect( 'get_transient' )
			->once()
			->with( 'shqf_token_5_expired-peek' )
			->andReturn( false );

		$form_token = new FormToken();
		$this->assertFalse( $form_token->peek( 5, 'expired-peek' ) );
	}

	/**
	 * Peek returns false when form_id doesn't match.
	 */
	public function test_peek_wrong_form_id(): void {
		Monkey\Functions\expect( 'get_transient' )
			->once()
			->with( 'shqf_token_10_wrong-peek' )
			->andReturn( 5 );

		$form_token = new FormToken();
		$this->assertFalse( $form_token->peek( 10, 'wrong-peek' ) );
	}

	/**
	 * Token survives peek but is consumed by validate.
	 */
	public function test_peek_then_validate_consumes(): void {
		// Peek: token exists, no delete.
		Monkey\Functions\expect( 'get_transient' )
			->once()
			->with( 'shqf_token_5_upload-then-submit' )
			->andReturn( 5 );

		$form_token = new FormToken();
		$this->assertTrue( $form_token->peek( 5, 'upload-then-submit' ) );

		// Validate: token exists, gets deleted.
		Monkey\Functions\expect( 'get_transient' )
			->once()
			->with( 'shqf_token_5_upload-then-submit' )
			->andReturn( 5 );

		Monkey\Functions\expect( 'delete_transient' )
			->once()
			->with( 'shqf_token_5_upload-then-submit' );

		$this->assertTrue( $form_token->validate( 5, 'upload-then-submit' ) );
	}

	/**
	 * Token cannot be reused (deleted after first validation).
	 */
	public function test_token_single_use(): void {
		// First validation succeeds.
		Monkey\Functions\expect( 'get_transient' )
			->once()
			->with( 'shqf_token_5_one-time' )
			->andReturn( 5 );

		Monkey\Functions\expect( 'delete_transient' )
			->once()
			->with( 'shqf_token_5_one-time' );

		$form_token = new FormToken();
		$this->assertTrue( $form_token->validate( 5, 'one-time' ) );

		// Second validation would call get_transient again and get false
		// (transient was deleted). We test this by expecting a new call.
		Monkey\Functions\expect( 'get_transient' )
			->once()
			->with( 'shqf_token_5_one-time' )
			->andReturn( false );

		$this->assertFalse( $form_token->validate( 5, 'one-time' ) );
	}
}
