<?php
/**
 * Tests for the Mailer email service.
 *
 * @package SampleHQForm\Tests\Unit\Email
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Email;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Email\Mailer;

/**
 * Mailer unit tests.
 */
class MailerTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private Mailer $mailer;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'esc_html'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_html__'          => static fn( $s ) => $s,
			'esc_url'             => static fn( $s ) => (string) $s,
			'__'                  => static fn( $s ) => $s,
			'is_email'            => static fn( $s ) => (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ),
			'wp_json_encode'      => static fn( $v ) => json_encode( $v ),
			'get_bloginfo'        => static fn() => 'Test Site',
			'sanitize_email'      => static fn( $s ) => filter_var( $s, FILTER_SANITIZE_EMAIL ) ?: '',
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'apply_filters'       => static function ( $hook, $value ) {
				return $value;
			},
			'locate_template'     => static fn() => '',
			'esc_html_e'          => static function ( $s ) { echo htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); },
			'esc_html_x'          => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
		] );

		$this->mailer = new Mailer();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Admin notification sends to admin email (default cascade).
	 */
	public function test_admin_notification_sends(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static function ( $key, $default = '' ) {
				return match ( $key ) {
					'shqf_email_defaults' => [],
					'admin_email'         => 'admin@example.com',
					default               => $default,
				};
			} );

		$sent_to      = null;
		$sent_subject = null;
		$sent_body    = null;

		Monkey\Functions\expect( 'wp_mail' )
			->once()
			->andReturnUsing(
				static function ( $to, $subject, $body ) use ( &$sent_to, &$sent_subject, &$sent_body ) {
					$sent_to      = $to;
					$sent_subject = $subject;
					$sent_body    = $body;
					return true;
				}
			);

		$result = $this->mailer->send_admin_notification(
			[ 'id' => 1, 'email' => 'john@example.com', 'first_name' => 'John', 'last_name' => 'Doe' ],
			[ 'email' => 'john@example.com', 'first_name' => 'John' ],
			[ 'title' => 'Sample Request Form' ]
		);

		$this->assertTrue( $result );
		$this->assertSame( 'admin@example.com', $sent_to );
		$this->assertStringContainsString( 'Sample Request Form', $sent_subject );
		$this->assertStringContainsString( 'john@example.com', $sent_body );
	}

	/**
	 * Admin notification returns false when no admin email.
	 */
	public function test_admin_notification_no_email(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturn( '' );

		$result = $this->mailer->send_admin_notification( [], [], [] );
		$this->assertFalse( $result );
	}

	/**
	 * Admin notification includes reply-to header.
	 */
	public function test_admin_notification_reply_to(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturn( 'admin@example.com' );

		$headers_captured = null;
		Monkey\Functions\expect( 'wp_mail' )
			->once()
			->andReturnUsing(
				static function ( $to, $subject, $body, $headers ) use ( &$headers_captured ) {
					$headers_captured = $headers;
					return true;
				}
			);

		$this->mailer->send_admin_notification(
			[ 'email' => 'john@example.com', 'first_name' => 'John', 'last_name' => 'Doe' ],
			[],
			[ 'title' => 'Form' ]
		);

		$reply_to = array_filter( $headers_captured, static fn( $h ) => str_contains( $h, 'Reply-To' ) );
		$this->assertNotEmpty( $reply_to );
		$this->assertStringContainsString( 'john@example.com', array_values( $reply_to )[0] );
	}

	/**
	 * Confirmation sends to submitter email.
	 */
	public function test_confirmation_sends(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturn( '' );

		Monkey\Functions\expect( 'wp_mail' )
			->once()
			->with(
				'john@example.com',
				\Mockery::type( 'string' ),
				\Mockery::type( 'string' ),
				\Mockery::type( 'array' )
			)
			->andReturn( true );

		$result = $this->mailer->send_confirmation(
			[ 'email' => 'john@example.com', 'first_name' => 'John' ],
			[],
			[ 'title' => 'Form' ]
		);

		$this->assertTrue( $result );
	}

	/**
	 * Confirmation returns false when no submitter email.
	 */
	public function test_confirmation_no_email(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturn( '' );

		$result = $this->mailer->send_confirmation( [], [], [] );
		$this->assertFalse( $result );
	}

	/**
	 * Confirmation returns false for invalid email.
	 */
	public function test_confirmation_invalid_email(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturn( '' );

		$result = $this->mailer->send_confirmation( [ 'email' => 'not-valid' ], [], [] );
		$this->assertFalse( $result );
	}

	/**
	 * Admin notification body escapes HTML in field values.
	 */
	public function test_admin_body_escapes_html(): void {
		Monkey\Functions\expect( 'get_option' )->andReturn( 'admin@example.com' );

		$body_captured = null;
		Monkey\Functions\expect( 'wp_mail' )
			->once()
			->andReturnUsing(
				static function ( $to, $subject, $body ) use ( &$body_captured ) {
					$body_captured = $body;
					return true;
				}
			);

		$this->mailer->send_admin_notification(
			[ 'email' => 'x@x.com' ],
			[ 'name' => '<script>alert(1)</script>' ],
			[ 'title' => 'Form' ]
		);

		$this->assertStringNotContainsString( '<script>', $body_captured );
		$this->assertStringContainsString( '&lt;script&gt;', $body_captured );
	}

	/**
	 * Filter hooks can modify email content.
	 */
	public function test_filters_can_modify_content(): void {
		Monkey\Functions\expect( 'get_option' )->andReturn( 'admin@example.com' );

		// Override apply_filters stub to modify the body.
		Monkey\Functions\stubs( [
			'apply_filters' => static function ( string $hook, $value ) {
				if ( 'shqf_admin_notification_content' === $hook ) {
					return 'CUSTOM BODY';
				}
				return $value;
			},
		] );

		$body_captured = null;
		Monkey\Functions\expect( 'wp_mail' )
			->once()
			->andReturnUsing(
				static function ( $to, $subject, $body ) use ( &$body_captured ) {
					$body_captured = $body;
					return true;
				}
			);

		$this->mailer->send_admin_notification(
			[ 'email' => 'x@x.com' ],
			[],
			[ 'title' => 'Form' ]
		);

		$this->assertSame( 'CUSTOM BODY', $body_captured );
	}

	/**
	 * Global email defaults are used when set.
	 */
	public function test_admin_uses_global_notification_email(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static function ( $key, $default = '' ) {
				return match ( $key ) {
					'shqf_email_defaults' => [ 'notification_email' => 'team@example.com', 'from_name' => 'Acme Co' ],
					'admin_email'         => 'admin@example.com',
					default               => $default,
				};
			} );

		$sent_to = null;
		$headers = null;
		Monkey\Functions\expect( 'wp_mail' )
			->once()
			->andReturnUsing(
				static function ( $to, $s, $b, $h ) use ( &$sent_to, &$headers ) {
					$sent_to = $to;
					$headers = $h;
					return true;
				}
			);

		$this->mailer->send_admin_notification(
			[ 'email' => 'j@x.com' ],
			[],
			[ 'title' => 'F' ]
		);

		$this->assertSame( 'team@example.com', $sent_to );
		$from = array_filter( $headers, static fn( $h ) => str_contains( $h, 'From:' ) );
		$this->assertNotEmpty( $from );
		$this->assertStringContainsString( 'Acme Co', array_values( $from )[0] );
	}

	/**
	 * Per-form email config overrides global defaults.
	 */
	public function test_per_form_overrides_global(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static function ( $key, $default = '' ) {
				return match ( $key ) {
					'shqf_email_defaults' => [ 'notification_email' => 'team@example.com', 'from_name' => 'Global' ],
					'admin_email'         => 'admin@example.com',
					default               => $default,
				};
			} );

		$sent_to = null;
		$headers = null;
		Monkey\Functions\expect( 'wp_mail' )
			->once()
			->andReturnUsing(
				static function ( $to, $s, $b, $h ) use ( &$sent_to, &$headers ) {
					$sent_to = $to;
					$headers = $h;
					return true;
				}
			);

		$form_config = json_encode( [
			'email' => [
				'notification_email' => 'sales@example.com',
				'from_name'          => 'Sales Team',
			],
		] );

		$this->mailer->send_admin_notification(
			[ 'email' => 'j@x.com' ],
			[],
			[ 'title' => 'F', 'config' => $form_config ]
		);

		$this->assertSame( 'sales@example.com', $sent_to );
		$from = array_filter( $headers, static fn( $h ) => str_contains( $h, 'From:' ) );
		$this->assertStringContainsString( 'Sales Team', array_values( $from )[0] );
	}

	/**
	 * Confirmation respects global send_confirmation toggle (off).
	 */
	public function test_confirmation_disabled_globally(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_email_defaults', [] )
			->andReturn( [ 'send_confirmation' => '0' ] );

		$result = $this->mailer->send_confirmation(
			[ 'email' => 'john@example.com' ],
			[],
			[ 'title' => 'Form' ]
		);

		$this->assertFalse( $result );
	}

	/**
	 * Confirmation respects per-form send_confirmation toggle (off).
	 */
	public function test_confirmation_disabled_per_form(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_email_defaults', [] )
			->andReturn( [ 'send_confirmation' => '1' ] );

		$form_config = json_encode( [
			'email' => [ 'send_confirmation' => false ],
		] );

		$result = $this->mailer->send_confirmation(
			[ 'email' => 'john@example.com' ],
			[],
			[ 'title' => 'Form', 'config' => $form_config ]
		);

		$this->assertFalse( $result );
	}

	/**
	 * Per-form can enable confirmation even when globally disabled.
	 */
	public function test_confirmation_enabled_per_form_overrides_global(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturnUsing( static function ( $key, $default = '' ) {
				return match ( $key ) {
					'shqf_email_defaults' => [ 'send_confirmation' => '0' ],
					'admin_email'         => 'admin@example.com',
					default               => $default,
				};
			} );

		Monkey\Functions\expect( 'wp_mail' )
			->once()
			->andReturn( true );

		$form_config = json_encode( [
			'email' => [ 'send_confirmation' => true ],
		] );

		$result = $this->mailer->send_confirmation(
			[ 'email' => 'john@example.com' ],
			[],
			[ 'title' => 'Form', 'config' => $form_config ]
		);

		$this->assertTrue( $result );
	}

	/**
	 * Malformed config JSON does not cause errors.
	 */
	public function test_malformed_config_falls_through(): void {
		Monkey\Functions\expect( 'get_option' )
			->andReturn( 'admin@example.com' );

		Monkey\Functions\expect( 'wp_mail' )
			->once()
			->andReturn( true );

		$result = $this->mailer->send_admin_notification(
			[ 'email' => 'j@x.com' ],
			[],
			[ 'title' => 'F', 'config' => '{invalid json' ]
		);

		$this->assertTrue( $result );
	}
}
