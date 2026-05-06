<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Helpers;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Helpers\Assets;

class AssetsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'SHQF_URL' ) ) {
			define( 'SHQF_URL', 'https://example.com/wp-content/plugins/samplehq-request-form/' );
		}
		if ( ! defined( 'SHQF_VERSION' ) ) {
			define( 'SHQF_VERSION', '1.0.0-test' );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_turnstile_enqueued_when_key_set(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_turnstile_site_key', '' )
			->andReturn( 'site_key_123' );

		Monkey\Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'cf-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js',
				[],
				null,
				true
			);

		Assets::maybe_enqueue_turnstile();
	}

	public function test_turnstile_not_enqueued_when_no_key(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_turnstile_site_key', '' )
			->andReturn( '' );

		Monkey\Functions\expect( 'wp_enqueue_script' )->never();

		Assets::maybe_enqueue_turnstile();
	}

	public function test_google_fonts_enqueued_by_default(): void {
		Monkey\Functions\expect( 'apply_filters' )
			->with( 'shqf_load_google_fonts', true )
			->andReturn( true );

		Monkey\Functions\stubs( [
			'plugins_url' => static fn( $path = '', $plugin = '' ) => 'https://example.com/wp-content/plugins/samplehq-request-form/' . $path,
		] );

		Monkey\Functions\expect( 'wp_enqueue_style' )
			->once()
			->with(
				'shqf-fonts',
				\Mockery::type( 'string' ),
				[],
				SHQF_VERSION
			);

		Assets::enqueue_google_fonts();
	}

	public function test_google_fonts_skipped_when_filtered(): void {
		Monkey\Functions\expect( 'apply_filters' )
			->with( 'shqf_load_google_fonts', true )
			->andReturn( false );

		Monkey\Functions\expect( 'wp_enqueue_style' )->never();

		Assets::enqueue_google_fonts();
	}
}
