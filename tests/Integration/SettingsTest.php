<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Integration;

class SettingsTest extends TestCase {

	protected function tearDown(): void {
		// Options don't rollback with transactions -- clean up manually.
		delete_option( 'shqf_email_defaults' );
		delete_option( 'shqf_collect_ip' );
		delete_option( 'shqf_turnstile_site_key' );
		delete_option( 'shqf_turnstile_secret_key' );
		parent::tearDown();
	}

	public function test_email_defaults_save_and_load(): void {
		$defaults = [
			'notification_email' => 'team@example.com',
			'from_name'          => 'Acme Co',
			'send_confirmation'  => '1',
		];

		update_option( 'shqf_email_defaults', $defaults );

		$loaded = get_option( 'shqf_email_defaults', [] );
		$this->assertSame( 'team@example.com', $loaded['notification_email'] );
		$this->assertSame( 'Acme Co', $loaded['from_name'] );
		$this->assertSame( '1', $loaded['send_confirmation'] );
	}

	public function test_collect_ip_toggle(): void {
		update_option( 'shqf_collect_ip', '0' );
		$this->assertSame( '0', get_option( 'shqf_collect_ip' ) );

		update_option( 'shqf_collect_ip', '1' );
		$this->assertSame( '1', get_option( 'shqf_collect_ip' ) );
	}

	public function test_turnstile_keys_save(): void {
		update_option( 'shqf_turnstile_site_key', 'site_key_123' );
		update_option( 'shqf_turnstile_secret_key', 'secret_456' );

		$this->assertSame( 'site_key_123', get_option( 'shqf_turnstile_site_key' ) );
		$this->assertSame( 'secret_456', get_option( 'shqf_turnstile_secret_key' ) );
	}

	public function test_email_cascade_per_form_overrides_global(): void {
		// Set global defaults.
		update_option( 'shqf_email_defaults', [
			'notification_email' => 'global@example.com',
			'from_name'          => 'Global Name',
			'send_confirmation'  => '1',
		] );

		// Create form with per-form email override.
		$form_id = $this->create_form( [
			'config' => json_encode( [
				'email' => [
					'notification_email' => 'form@example.com',
					'from_name'          => 'Form Name',
				],
			] ),
		] );

		$form = $this->forms->get( $form_id );

		// Use Mailer's resolve_email_config via reflection.
		$mailer = new \SampleHQForm\Email\Mailer();
		$method = new \ReflectionMethod( $mailer, 'resolve_email_config' );
		$method->setAccessible( true );

		$config = $method->invoke( $mailer, $form );

		$this->assertSame( 'form@example.com', $config['notification_email'] );
		$this->assertSame( 'Form Name', $config['from_name'] );
	}

	public function test_email_cascade_falls_back_to_global(): void {
		update_option( 'shqf_email_defaults', [
			'notification_email' => 'global@example.com',
			'from_name'          => 'Global Name',
			'send_confirmation'  => '0',
		] );

		// Form with no email override.
		$form_id = $this->create_form();
		$form    = $this->forms->get( $form_id );

		$mailer = new \SampleHQForm\Email\Mailer();
		$method = new \ReflectionMethod( $mailer, 'resolve_email_config' );
		$method->setAccessible( true );

		$config = $method->invoke( $mailer, $form );

		$this->assertSame( 'global@example.com', $config['notification_email'] );
		$this->assertSame( 'Global Name', $config['from_name'] );
		$this->assertFalse( $config['send_confirmation'] );
	}

	public function test_email_cascade_falls_back_to_admin_email(): void {
		// No global defaults set.
		delete_option( 'shqf_email_defaults' );

		$form_id = $this->create_form();
		$form    = $this->forms->get( $form_id );

		$mailer = new \SampleHQForm\Email\Mailer();
		$method = new \ReflectionMethod( $mailer, 'resolve_email_config' );
		$method->setAccessible( true );

		$config = $method->invoke( $mailer, $form );

		$this->assertSame( get_option( 'admin_email' ), $config['notification_email'] );
		$this->assertTrue( $config['send_confirmation'] );
	}
}
