<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Api;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Api\PublicEndpoints;
use SampleHQForm\Forms\FormProcessor;

class PublicEndpointsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_hooks_rest_api_init(): void {
		Monkey\Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', \Mockery::type( 'array' ) );

		$processor = \Mockery::mock( FormProcessor::class );
		$endpoints = new PublicEndpoints( $processor );
		$endpoints->register();
	}

	public function test_register_routes(): void {
		Monkey\Functions\expect( 'register_rest_route' )
			->once()
			->with(
				'samplehq-form/v1',
				'/submissions',
				\Mockery::on( static function ( $args ) {
					return 'POST' === ( $args['methods'] ?? '' )
						|| \WP_REST_Server::CREATABLE === ( $args['methods'] ?? '' );
				} )
			);

		$processor = \Mockery::mock( FormProcessor::class );
		$endpoints = new PublicEndpoints( $processor );
		$endpoints->register_routes();
	}

	public function test_handle_submission_passes_data_to_processor(): void {
		Monkey\Functions\stubs( [
			'sanitize_text_field' => static fn( $s ) => $s,
			'wp_unslash'         => static fn( $s ) => $s,
			'esc_url_raw'        => static fn( $s ) => $s,
			'wp_get_referer'     => static fn() => 'https://example.com/page',
			'apply_filters'      => static fn( $hook, $value ) => $value,
		] );

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$processor = \Mockery::mock( FormProcessor::class );
		$processor->shouldReceive( 'process' )
			->once()
			->with( \Mockery::on( static function ( $data ) {
				return 123 === $data['form_id']
					&& 'test_token' === $data['shqf_token']
					&& '127.0.0.1' === $data['ip_address']
					&& 'https://example.com/page' === $data['source_url'];
			} ) )
			->andReturn( [ 'status_code' => 200, 'message' => 'OK' ] );

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )->with( 'form_id' )->andReturn( 123 );
		$request->shouldReceive( 'get_param' )->with( 'shqf_token' )->andReturn( 'test_token' );
		$request->shouldReceive( 'get_param' )->with( 'shqf_hp' )->andReturn( '' );
		$request->shouldReceive( 'get_param' )->with( 'shqf_fields' )->andReturn( [] );
		$request->shouldReceive( 'get_param' )->with( 'cf_turnstile_response' )->andReturn( '' );
		$request->shouldReceive( 'get_header' )->with( 'user-agent' )->andReturn( 'TestBot/1.0' );

		$endpoints = new PublicEndpoints( $processor );
		$response  = $endpoints->handle_submission( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		unset( $_SERVER['REMOTE_ADDR'] );
	}

	public function test_rate_limited_response_includes_retry_after(): void {
		Monkey\Functions\stubs( [
			'sanitize_text_field' => static fn( $s ) => $s,
			'wp_unslash'         => static fn( $s ) => $s,
			'wp_get_referer'     => static fn() => '',
			'apply_filters'      => static fn( $hook, $value ) => $value,
		] );

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$processor = \Mockery::mock( FormProcessor::class );
		$processor->shouldReceive( 'process' )
			->once()
			->andReturn( [ 'status_code' => 429, 'message' => 'Rate limited' ] );

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )->andReturn( '' );
		$request->shouldReceive( 'get_header' )->andReturn( '' );

		$endpoints = new PublicEndpoints( $processor );
		$response  = $endpoints->handle_submission( $request );

		$this->assertSame( 429, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Retry-After', $headers );
		$this->assertSame( '3600', (string) $headers['Retry-After'] );

		unset( $_SERVER['REMOTE_ADDR'] );
	}
}
