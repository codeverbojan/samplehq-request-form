<?php
/**
 * Tests for the UploadEndpoint.
 *
 * @package SampleHQForm\Tests\Unit\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Api;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Api\UploadEndpoint;
use SampleHQForm\Database\FormsTable;
use SampleHQForm\Database\RateLimitsTable;
use SampleHQForm\Spam\FormToken;

/**
 * UploadEndpoint unit tests.
 */
class UploadEndpointTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private UploadEndpoint $endpoint;
	private $forms;
	private $token;
	private $rate_limits;
	private ?string $original_remote_addr = null;
	private ?string $tmp_upload_dir = null;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->original_remote_addr = $_SERVER['REMOTE_ADDR'] ?? null;

		$this->forms       = Mockery::mock( FormsTable::class );
		$this->token       = Mockery::mock( FormToken::class );
		$this->rate_limits = Mockery::mock( RateLimitsTable::class );

		Monkey\Functions\stubs( [
			'__'                  => static fn( $s ) => $s,
			'absint'              => static fn( $n ) => abs( (int) $n ),
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'sanitize_file_name'  => static fn( $s ) => preg_replace( '/[^a-zA-Z0-9._-]/', '', (string) $s ),
			'wp_unslash'          => static fn( $s ) => $s,
			'current_user_can'    => static fn() => false,
			'wp_rand'             => static fn( $min, $max ) => $min,
		] );

		$this->endpoint = new UploadEndpoint(
			$this->forms,
			$this->token,
			$this->rate_limits
		);
	}

	protected function tearDown(): void {
		if ( null === $this->original_remote_addr ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->original_remote_addr;
		}
		if ( null !== $this->tmp_upload_dir ) {
			$shqf_dir = $this->tmp_upload_dir . '/shqf';
			@unlink( $shqf_dir . '/.htaccess' );
			@unlink( $shqf_dir . '/nginx.conf' );
			@unlink( $shqf_dir . '/index.html' );
			@rmdir( $shqf_dir );
			@rmdir( $this->tmp_upload_dir );
			$this->tmp_upload_dir = null;
		}
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $files
	 * @return \WP_REST_Request&Mockery\MockInterface
	 */
	private function mock_request( array $params = [], array $files = [] ) {
		$request = Mockery::mock( 'WP_REST_Request' );
		$request->shouldReceive( 'get_param' )
			->andReturnUsing( static fn( $key ) => $params[ $key ] ?? null );
		$request->shouldReceive( 'get_file_params' )
			->andReturn( $files );
		return $request;
	}

	private function form_with_upload( array $field_overrides = [] ): array {
		return [
			'id'     => 1,
			'status' => 'published',
			'config' => [
				'fields' => [
					array_merge(
						[
							'type'       => 'file_upload',
							'id'         => 'f1',
							'validation' => [
								'max_size_mb'   => 10,
								'allowed_types' => [ 'image/jpeg', 'image/png' ],
							],
						],
						$field_overrides
					),
				],
			],
		];
	}

	private function setup_upload_dir_stubs(): void {
		$this->tmp_upload_dir = sys_get_temp_dir() . '/shqf_test_uploads_' . getmypid();
		@mkdir( $this->tmp_upload_dir, 0777, true );

		$basedir = $this->tmp_upload_dir;
		Monkey\Functions\expect( 'wp_upload_dir' )
			->andReturn( [
				'basedir' => $basedir,
				'baseurl' => 'https://example.com/wp-content/uploads',
			] );
		Monkey\Functions\expect( 'wp_mkdir_p' )->andReturnUsing(
			static function ( string $dir ): bool {
				if ( ! is_dir( $dir ) ) {
					return mkdir( $dir, 0777, true );
				}
				return true;
			}
		);
		Monkey\Functions\expect( 'add_filter' )->once();
		Monkey\Functions\expect( 'remove_filter' )->once();
	}

	private function pass_security_gates(): void {
		$_SERVER['REMOTE_ADDR'] = '1.2.3.4';
		$this->token->shouldReceive( 'peek' )->andReturn( true );
		$this->rate_limits->shouldReceive( 'check_and_increment' )->andReturn( true );
	}

	private function create_temp_jpeg(): string {
		$tmp_file = tempnam( sys_get_temp_dir(), 'shqf_test_' );
		$jpeg     = base64_decode(
			'/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof'
			. 'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwh'
			. 'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAAR'
			. 'CAABAAEDASIAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAA'
			. 'AAD/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMR'
			. 'AD8AKwA//9k='
		);
		file_put_contents( $tmp_file, $jpeg );
		return $tmp_file;
	}

	public function test_missing_form_id_returns_400(): void {
		$request  = $this->mock_request( [ 'form_id' => 0 ] );
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Missing form_id.', $response->get_data()['message'] );
	}

	public function test_honeypot_returns_fake_200(): void {
		$request  = $this->mock_request( [
			'form_id'    => 1,
			'shqf_hp'    => 'bot-filled-this',
			'shqf_token' => 'tok',
		] );
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'attachment_id', $response->get_data() );
	}

	public function test_missing_token_returns_403(): void {
		$this->token->shouldReceive( 'peek' )->never();

		$request  = $this->mock_request( [
			'form_id'    => 1,
			'shqf_hp'    => '',
			'shqf_token' => '',
		] );
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertStringContainsString( 'Invalid', $response->get_data()['message'] );
	}

	public function test_invalid_token_returns_403(): void {
		$this->token->shouldReceive( 'peek' )
			->with( 1, 'bad-token' )
			->once()
			->andReturn( false );

		$request  = $this->mock_request( [
			'form_id'    => 1,
			'shqf_hp'    => '',
			'shqf_token' => 'bad-token',
		] );
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_rate_limited_returns_429(): void {
		$_SERVER['REMOTE_ADDR'] = '1.2.3.4';

		$this->token->shouldReceive( 'peek' )->andReturn( true );
		$this->rate_limits->shouldReceive( 'check_and_increment' )
			->with( '1.2.3.4', 1 )
			->once()
			->andReturn( false );

		$request  = $this->mock_request( [
			'form_id'    => 1,
			'shqf_hp'    => '',
			'shqf_token' => 'valid-tok',
		] );
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 429, $response->get_status() );
		$this->assertSame( '3600', $response->get_headers()['Retry-After'] );
	}

	public function test_form_not_found_returns_404(): void {
		$this->pass_security_gates();
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( null );

		$request  = $this->mock_request( [
			'form_id'    => 1,
			'shqf_hp'    => '',
			'shqf_token' => 'valid-tok',
		] );
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_unpublished_form_returns_404(): void {
		$this->pass_security_gates();
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [
			'id'     => 1,
			'status' => 'draft',
			'config' => [],
		] );

		$request  = $this->mock_request( [
			'form_id'    => 1,
			'shqf_hp'    => '',
			'shqf_token' => 'valid-tok',
		] );
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_no_file_upload_field_returns_400(): void {
		$this->pass_security_gates();
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()->andReturn( [
			'id'     => 1,
			'status' => 'published',
			'config' => [
				'fields' => [
					[ 'type' => 'text', 'id' => 'f1' ],
				],
			],
		] );

		$request  = $this->mock_request( [
			'form_id'    => 1,
			'shqf_hp'    => '',
			'shqf_token' => 'valid-tok',
		] );
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertStringContainsString( 'does not accept', $response->get_data()['message'] );
	}

	public function test_no_file_uploaded_returns_400(): void {
		$this->pass_security_gates();
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()
			->andReturn( $this->form_with_upload() );

		$request  = $this->mock_request(
			[
				'form_id'    => 1,
				'shqf_hp'    => '',
				'shqf_token' => 'valid-tok',
			],
			[]
		);
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'No file uploaded.', $response->get_data()['message'] );
	}

	public function test_file_error_returns_400(): void {
		$this->pass_security_gates();
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()
			->andReturn( $this->form_with_upload() );

		$request  = $this->mock_request(
			[
				'form_id'    => 1,
				'shqf_hp'    => '',
				'shqf_token' => 'valid-tok',
			],
			[
				'file' => [
					'name'     => 'broken.jpg',
					'tmp_name' => '/tmp/php456',
					'size'     => 100,
					'error'    => UPLOAD_ERR_PARTIAL,
				],
			]
		);
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Upload failed.', $response->get_data()['message'] );
	}

	public function test_file_too_large_returns_400(): void {
		$this->pass_security_gates();
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()
			->andReturn( $this->form_with_upload( [ 'validation' => [ 'max_size_mb' => 2 ] ] ) );

		$request  = $this->mock_request(
			[
				'form_id'    => 1,
				'shqf_hp'    => '',
				'shqf_token' => 'valid-tok',
			],
			[
				'file' => [
					'name'     => 'huge.pdf',
					'tmp_name' => '/tmp/php123',
					'size'     => 5 * 1048576,
					'error'    => 0,
				],
			]
		);
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertStringContainsString( '2 MB', $response->get_data()['message'] );
	}

	public function test_file_at_exact_limit_passes_size_check(): void {
		$this->pass_security_gates();
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()
			->andReturn( $this->form_with_upload( [ 'validation' => [ 'max_size_mb' => 1 ] ] ) );

		$tmp_file = tempnam( sys_get_temp_dir(), 'shqf_test_' );
		file_put_contents( $tmp_file, str_repeat( 'x', 1048576 ) );

		$request  = $this->mock_request(
			[
				'form_id'    => 1,
				'shqf_hp'    => '',
				'shqf_token' => 'valid-tok',
			],
			[
				'file' => [
					'name'     => 'exact.bin',
					'tmp_name' => $tmp_file,
					'size'     => 1048576,
					'error'    => 0,
				],
			]
		);
		$response = $this->endpoint->handle_upload( $request );

		// File passes size check but fails MIME check (plain text, not image).
		$this->assertSame( 400, $response->get_status() );
		$this->assertStringContainsString( 'type not allowed', $response->get_data()['message'] );

		@unlink( $tmp_file );
	}

	public function test_invalid_mime_returns_400(): void {
		$this->pass_security_gates();
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()
			->andReturn( $this->form_with_upload() );

		$tmp_file = tempnam( sys_get_temp_dir(), 'shqf_test_' );
		file_put_contents( $tmp_file, '<?php echo "evil"; ?>' );

		$request  = $this->mock_request(
			[
				'form_id'    => 1,
				'shqf_hp'    => '',
				'shqf_token' => 'valid-tok',
			],
			[
				'file' => [
					'name'     => 'evil.php',
					'tmp_name' => $tmp_file,
					'size'     => filesize( $tmp_file ),
					'error'    => 0,
				],
			]
		);
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertStringContainsString( 'type not allowed', $response->get_data()['message'] );

		@unlink( $tmp_file );
	}

	public function test_successful_upload_returns_200(): void {
		$this->pass_security_gates();
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()
			->andReturn( $this->form_with_upload() );

		$tmp_file = $this->create_temp_jpeg();

		$this->setup_upload_dir_stubs();
		Monkey\Functions\expect( 'wp_handle_upload' )->once()->andReturn( [
			'file' => $tmp_file,
			'type' => 'image/jpeg',
			'url'  => 'https://example.com/wp-content/uploads/shqf/test.jpg',
		] );
		Monkey\Functions\expect( 'wp_insert_attachment' )->once()->andReturn( 42 );
		Monkey\Functions\expect( 'wp_generate_attachment_metadata' )->once()->andReturn( [] );
		Monkey\Functions\expect( 'wp_update_attachment_metadata' )->once();
		Monkey\Functions\expect( 'update_post_meta' )->twice();

		$request  = $this->mock_request(
			[
				'form_id'    => 1,
				'shqf_hp'    => '',
				'shqf_token' => 'valid-tok',
			],
			[
				'file' => [
					'name'     => 'photo.jpg',
					'tmp_name' => $tmp_file,
					'size'     => filesize( $tmp_file ),
					'error'    => 0,
				],
			]
		);
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 42, $data['attachment_id'] );
		$this->assertArrayHasKey( 'filename', $data );

		@unlink( $tmp_file );
	}

	public function test_wp_handle_upload_error_returns_400(): void {
		$this->pass_security_gates();
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()
			->andReturn( $this->form_with_upload() );

		$tmp_file = $this->create_temp_jpeg();

		$this->setup_upload_dir_stubs();
		Monkey\Functions\expect( 'wp_handle_upload' )->once()->andReturn( [
			'error' => 'File type is not allowed.',
		] );

		$request  = $this->mock_request(
			[
				'form_id'    => 1,
				'shqf_hp'    => '',
				'shqf_token' => 'valid-tok',
			],
			[
				'file' => [
					'name'     => 'photo.jpg',
					'tmp_name' => $tmp_file,
					'size'     => filesize( $tmp_file ),
					'error'    => 0,
				],
			]
		);
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'File type is not allowed.', $response->get_data()['message'] );

		@unlink( $tmp_file );
	}

	public function test_wp_insert_attachment_failure_returns_500(): void {
		$this->pass_security_gates();
		$this->forms->shouldReceive( 'get' )->with( 1 )->once()
			->andReturn( $this->form_with_upload() );

		$tmp_file = $this->create_temp_jpeg();

		$this->setup_upload_dir_stubs();
		Monkey\Functions\expect( 'wp_handle_upload' )->once()->andReturn( [
			'file' => $tmp_file,
			'type' => 'image/jpeg',
			'url'  => 'https://example.com/wp-content/uploads/shqf/test.jpg',
		] );
		Monkey\Functions\expect( 'wp_insert_attachment' )->once()->andReturn( 0 );

		$request  = $this->mock_request(
			[
				'form_id'    => 1,
				'shqf_hp'    => '',
				'shqf_token' => 'valid-tok',
			],
			[
				'file' => [
					'name'     => 'photo.jpg',
					'tmp_name' => $tmp_file,
					'size'     => filesize( $tmp_file ),
					'error'    => 0,
				],
			]
		);
		$response = $this->endpoint->handle_upload( $request );

		$this->assertSame( 500, $response->get_status() );
		$this->assertStringContainsString( 'Failed', $response->get_data()['message'] );

		@unlink( $tmp_file );
	}

	public function test_register_hooks_rest_api_init(): void {
		Monkey\Functions\expect( 'add_action' )
			->once()
			->with( 'rest_api_init', [ $this->endpoint, 'register_routes' ] );

		$this->endpoint->register();
	}

	public function test_allowed_types_to_mimes_mapping(): void {
		$method = new \ReflectionMethod( UploadEndpoint::class, 'allowed_types_to_mimes' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->endpoint,
			[ 'image/jpeg', 'image/png', 'application/pdf' ]
		);

		$this->assertSame( 'image/jpeg', $result['jpg|jpeg'] );
		$this->assertSame( 'image/png', $result['png'] );
		$this->assertSame( 'application/pdf', $result['pdf'] );
	}

	public function test_allowed_types_to_mimes_ignores_unknown(): void {
		$method = new \ReflectionMethod( UploadEndpoint::class, 'allowed_types_to_mimes' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->endpoint,
			[ 'application/x-unknown' ]
		);

		$this->assertEmpty( $result );
	}
}
