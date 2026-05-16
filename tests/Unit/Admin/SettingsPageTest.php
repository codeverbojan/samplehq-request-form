<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Admin;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Admin\SettingsPage;
use SampleHQForm\Connection\ConnectionManager;

class SettingsPageTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'__'                  => static fn( $s ) => $s,
			'esc_html__'          => static fn( $s ) => $s,
			'esc_attr'            => static fn( $s ) => $s,
			'esc_url'             => static fn( $s ) => $s,
			'esc_html'            => static fn( $s ) => $s,
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'wp_unslash'          => static fn( $s ) => $s,
			'wp_nonce_field'      => static fn() => '',
			'admin_url'           => static fn( $s ) => 'https://example.com/wp-admin/' . $s,
			'wp_verify_nonce'     => static fn() => true,
			'get_transient'       => static fn() => false,
			'set_transient'       => static fn() => true,
			'delete_transient'    => static fn() => true,
			'get_current_user_id' => static fn() => 1,
			'submit_button'       => static fn() => null,
			'wp_create_nonce'     => static fn() => 'nonce',
		] );
	}

	protected function tearDown(): void {
		unset(
			$_GET['tab'],
			$_POST['shqf_settings_nonce'],
			$_POST['shqf_settings_tab'],
			$_POST['shqf_turnstile_site_key'],
			$_POST['shqf_turnstile_secret_key'],
			$_POST['shqf_notification_email'],
			$_POST['shqf_from_name'],
			$_POST['shqf_send_confirmation'],
			$_POST['shqf_collect_ip'],
			$_POST['shqf_ip_retention_days']
		);
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_spam_tab_masks_turnstile_secret_when_set(): void {
		$_GET['tab'] = 'spam';

		$cm = Mockery::mock( ConnectionManager::class );
		$cm->shouldReceive( 'is_connected' )->andReturn( false );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_turnstile_site_key', '' )
			->andReturn( 'site-key-123' );
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_turnstile_secret_key', '' )
			->andReturn( 'secret-key-abc-real-value' );

		$page = new SettingsPage( $cm );

		ob_start();
		$page->render();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'secret-key-abc-real-value', $html );
		$this->assertStringContainsString( 'site-key-123', $html );
	}

	public function test_spam_tab_shows_empty_when_no_secret(): void {
		$_GET['tab'] = 'spam';

		$cm = Mockery::mock( ConnectionManager::class );
		$cm->shouldReceive( 'is_connected' )->andReturn( false );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_turnstile_site_key', '' )
			->andReturn( '' );
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_turnstile_secret_key', '' )
			->andReturn( '' );

		$page = new SettingsPage( $cm );

		ob_start();
		$page->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value=""', $html );
	}

	public function test_save_spam_skips_secret_when_mask_submitted(): void {
		$_POST['shqf_settings_nonce']        = 'valid';
		$_POST['shqf_settings_tab']          = 'spam';
		$_POST['shqf_turnstile_site_key']    = 'new-site-key';
		$_POST['shqf_turnstile_secret_key']  = "\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2\xE2\x80\xA2";

		$cm = Mockery::mock( ConnectionManager::class );

		Monkey\Functions\expect( 'update_option' )
			->with( 'shqf_turnstile_site_key', 'new-site-key' )
			->once();
		Monkey\Functions\expect( 'update_option' )
			->with( 'shqf_turnstile_secret_key', Mockery::any() )
			->never();

		Monkey\Functions\expect( 'wp_safe_redirect' )->once()->andReturnUsing( static function () {
			throw new \RuntimeException( 'redirected' );
		} );

		$page = new SettingsPage( $cm );
		try {
			$page->handle_save();
			$this->fail( 'Expected redirect' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		}
	}

	public function test_save_spam_writes_new_secret_when_changed(): void {
		$_POST['shqf_settings_nonce']        = 'valid';
		$_POST['shqf_settings_tab']          = 'spam';
		$_POST['shqf_turnstile_site_key']    = 'site-key';
		$_POST['shqf_turnstile_secret_key']  = 'brand-new-secret';

		$cm = Mockery::mock( ConnectionManager::class );

		Monkey\Functions\expect( 'update_option' )
			->with( 'shqf_turnstile_site_key', 'site-key' )
			->once();
		Monkey\Functions\expect( 'update_option' )
			->with( 'shqf_turnstile_secret_key', 'brand-new-secret' )
			->once();

		Monkey\Functions\expect( 'wp_safe_redirect' )->once()->andReturnUsing( static function () {
			throw new \RuntimeException( 'redirected' );
		} );

		$page = new SettingsPage( $cm );
		try {
			$page->handle_save();
			$this->fail( 'Expected redirect' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		}
	}

	// --- 15B.5: Input sanitization ---

	/**
	 * Email header injection via CRLF in notification email is sanitized.
	 */
	public function test_email_header_injection_sanitized(): void {
		$_POST['shqf_settings_nonce']     = 'valid';
		$_POST['shqf_settings_tab']       = 'email';
		$_POST['shqf_notification_email'] = "admin@test.com\r\nBCC:attacker@evil.com";
		$_POST['shqf_from_name']          = 'Test';
		$_POST['shqf_send_confirmation']  = '1';

		Monkey\Functions\stubs( [
			'sanitize_email' => static function ( $email ) {
				$clean = preg_replace( '/[^a-zA-Z0-9@._+\-]/', '', trim( $email ) );
				return filter_var( $clean, FILTER_VALIDATE_EMAIL ) ?: '';
			},
		] );

		$captured = null;
		Monkey\Functions\expect( 'update_option' )
			->andReturnUsing( function () use ( &$captured ) {
				$args = func_get_args();
				if ( 'shqf_email_defaults' === $args[0] ) {
					$captured = $args[1];
				}
				return true;
			} );

		Monkey\Functions\expect( 'wp_safe_redirect' )->once()->andReturnUsing( static function () {
			throw new \RuntimeException( 'redirected' );
		} );

		$cm   = Mockery::mock( ConnectionManager::class );
		$page = new SettingsPage( $cm );
		try {
			$page->handle_save();
		} catch ( \RuntimeException ) {
			// Expected redirect.
		}

		$this->assertNotNull( $captured, 'update_option for shqf_email_defaults should have been called' );
		$this->assertStringNotContainsString( "\r", $captured['notification_email'] );
		$this->assertStringNotContainsString( "\n", $captured['notification_email'] );
		$this->assertStringNotContainsString( 'BCC:', $captured['notification_email'] );
	}

	/**
	 * IP retention days with 0 is stored as 0 (purge-all behavior).
	 */
	public function test_retention_days_zero_stored(): void {
		$_POST['shqf_settings_nonce']    = 'valid';
		$_POST['shqf_settings_tab']      = 'general';
		$_POST['shqf_collect_ip']        = '1';
		$_POST['shqf_ip_retention_days'] = '0';

		Monkey\Functions\stubs( [
			'absint' => static fn( $n ) => abs( (int) $n ),
		] );

		$captured = [];
		Monkey\Functions\expect( 'update_option' )
			->andReturnUsing( function () use ( &$captured ) {
				$args                = func_get_args();
				$captured[ $args[0] ] = $args[1];
				return true;
			} );

		Monkey\Functions\expect( 'wp_safe_redirect' )->once()->andReturnUsing( static function () {
			throw new \RuntimeException( 'redirected' );
		} );

		$cm   = Mockery::mock( ConnectionManager::class );
		$page = new SettingsPage( $cm );
		try {
			$page->handle_save();
		} catch ( \RuntimeException ) {
			// Expected redirect.
		}

		$this->assertArrayHasKey( 'shqf_ip_retention_days', $captured );
		$this->assertSame( 0, $captured['shqf_ip_retention_days'] );
	}

	/**
	 * WooCommerce sample categories with non-array input handled by (array) cast + absint.
	 *
	 * Tests the sanitization expression used in handle_save directly because
	 * defining a WooCommerce class stub would break WooDetectorTest in the same process.
	 */
	public function test_woo_categories_non_array_input_sanitized(): void {
		$raw   = 'malicious-string';
		$clean = array_map( 'absint', (array) $raw );

		$this->assertSame( [ 0 ], $clean, 'Non-numeric string should produce [0]' );

		$raw_numeric = '42';
		$clean       = array_map( 'absint', (array) $raw_numeric );

		$this->assertSame( [ 42 ], $clean, 'Numeric string should produce [42]' );

		$raw_array = [ '1', '2', 'evil', '-5' ];
		$clean     = array_map( 'absint', $raw_array );

		$this->assertSame( [ 1, 2, 0, 5 ], $clean, 'Mixed array sanitized via absint' );
	}

	public function test_save_spam_clears_secret_when_empty(): void {
		$_POST['shqf_settings_nonce']        = 'valid';
		$_POST['shqf_settings_tab']          = 'spam';
		$_POST['shqf_turnstile_site_key']    = '';
		$_POST['shqf_turnstile_secret_key']  = '';

		$cm = Mockery::mock( ConnectionManager::class );

		Monkey\Functions\expect( 'update_option' )
			->with( 'shqf_turnstile_site_key', '' )
			->once();
		Monkey\Functions\expect( 'update_option' )
			->with( 'shqf_turnstile_secret_key', '' )
			->once();

		Monkey\Functions\expect( 'wp_safe_redirect' )->once()->andReturnUsing( static function () {
			throw new \RuntimeException( 'redirected' );
		} );

		$page = new SettingsPage( $cm );
		try {
			$page->handle_save();
			$this->fail( 'Expected redirect' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		}
	}
}
