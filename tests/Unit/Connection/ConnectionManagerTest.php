<?php
/**
 * Tests for the ConnectionManager core methods.
 *
 * @package SampleHQForm\Tests\Unit\Connection
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Connection;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Connection\ConnectionManager;
use SampleHQForm\Connection\ConnectionVerifier;
use SampleHQForm\Database\FormsTable;

/**
 * ConnectionManager unit tests (core methods).
 */
class ConnectionManagerTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private const SALT = 'test-auth-salt-for-unit-tests';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create a ConnectionManager with mock dependencies.
	 *
	 * @param FormsTable|null         $forms    Optional forms table mock.
	 * @param ConnectionVerifier|null $verifier Optional verifier mock.
	 * @return ConnectionManager
	 */
	private function make_manager( ?FormsTable $forms = null, ?ConnectionVerifier $verifier = null ): ConnectionManager {
		return new ConnectionManager(
			$forms ?? \Mockery::mock( FormsTable::class ),
			$verifier ?? \Mockery::mock( ConnectionVerifier::class )
		);
	}

	// --- platform_url() ---

	/**
	 * platform_url returns the default production URL when no constant is defined.
	 */
	public function test_platform_url_returns_default(): void {
		$this->assertSame( 'https://samplehq.io', ConnectionManager::platform_url() );
	}

	/**
	 * platform_url uses SHQF_PLATFORM_URL constant when defined.
	 */
	public function test_platform_url_uses_constant(): void {
		if ( defined( 'SHQF_PLATFORM_URL' ) ) {
			$this->markTestSkipped( 'SHQF_PLATFORM_URL is already defined in this process.' );
		}

		define( 'SHQF_PLATFORM_URL', 'https://sampleflows.test/' );

		$this->assertSame( 'https://sampleflows.test', ConnectionManager::platform_url() );
	}

	// --- encrypt_secret / decrypt_secret round-trip ---

	/**
	 * Encrypting then decrypting returns the original secret.
	 */
	public function test_encrypt_decrypt_round_trip(): void {
		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		$raw       = 'my-super-secret-connection-token-64-chars-long';
		$encrypted = ConnectionManager::encrypt_secret( $raw );

		$this->assertNotSame( $raw, $encrypted, 'Encrypted value should differ from raw.' );
		$this->assertNotEmpty( $encrypted );

		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		$decrypted = ConnectionManager::decrypt_secret( $encrypted );

		$this->assertSame( $raw, $decrypted );
	}

	/**
	 * Same secret encrypted twice produces different ciphertexts (random IV).
	 */
	public function test_encrypt_produces_different_ciphertext_each_time(): void {
		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		$raw = 'same-secret';
		$a   = ConnectionManager::encrypt_secret( $raw );

		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		$b = ConnectionManager::encrypt_secret( $raw );

		$this->assertNotSame( $a, $b, 'Random IV should produce different ciphertexts.' );
	}

	/**
	 * Decrypting with a different salt returns empty string (GCM auth rejects).
	 */
	public function test_decrypt_with_wrong_salt_returns_empty(): void {
		$call_count = 0;

		Monkey\Functions\expect( 'wp_salt' )
			->with( 'auth' )
			->andReturnUsing(
				function () use ( &$call_count ) {
					++$call_count;
					return 1 === $call_count ? self::SALT : 'different-salt';
				}
			);

		$encrypted = ConnectionManager::encrypt_secret( 'secret' );

		$this->assertSame( '', ConnectionManager::decrypt_secret( $encrypted ) );
	}

	/**
	 * Decrypting an empty string returns empty string.
	 */
	public function test_decrypt_empty_string_returns_empty(): void {
		$this->assertSame( '', ConnectionManager::decrypt_secret( '' ) );
	}

	/**
	 * Decrypting invalid base64 returns empty string.
	 */
	public function test_decrypt_invalid_base64_returns_empty(): void {
		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		$this->assertSame( '', ConnectionManager::decrypt_secret( '!!!not-base64!!!' ) );
	}

	/**
	 * Decrypting a truncated ciphertext (too short for IV) returns empty string.
	 */
	public function test_decrypt_truncated_ciphertext_returns_empty(): void {
		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$short = base64_encode( 'short' );

		$this->assertSame( '', ConnectionManager::decrypt_secret( $short ) );
	}

	// --- is_connected() ---

	/**
	 * is_connected returns true when all required keys are present.
	 */
	public function test_is_connected_returns_true(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn( [
				'workspace_url'     => 'https://acme.samplehq.io',
				'workspace_id'      => 42,
				'workspace_name'    => 'Acme',
				'connection_secret' => 'encrypted-secret',
				'connected_by'      => 'john@acme.com',
				'connected_at'      => 1700000000,
			] );

		$this->assertTrue( $this->make_manager()->is_connected() );
	}

	/**
	 * is_connected returns false when connection option is empty.
	 */
	public function test_is_connected_returns_false_when_empty(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn( [] );

		$this->assertFalse( $this->make_manager()->is_connected() );
	}

	/**
	 * is_connected returns false when workspace_id is falsy.
	 */
	public function test_is_connected_returns_false_when_workspace_id_zero(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn( [ 'workspace_id' => 0 ] );

		$this->assertFalse( $this->make_manager()->is_connected() );
	}

	/**
	 * is_connected returns false when required keys are missing (e.g., corrupted connection_secret).
	 */
	public function test_is_connected_returns_false_when_missing_required_keys(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn( [
				'workspace_id' => 42,
				'workspace_url' => 'https://acme.samplehq.io',
			] );

		$this->assertFalse( $this->make_manager()->is_connected() );
	}

	/**
	 * is_connected returns false when connection_secret key is missing.
	 */
	public function test_is_connected_returns_false_when_secret_missing(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn( [
				'workspace_url'  => 'https://acme.samplehq.io',
				'workspace_id'   => 42,
				'workspace_name' => 'Acme',
				'connected_by'   => 'john@acme.com',
				'connected_at'   => 1700000000,
			] );

		$this->assertFalse( $this->make_manager()->is_connected() );
	}

	/**
	 * is_connected returns false when a required key has an empty string value.
	 */
	public function test_is_connected_returns_false_when_secret_empty_string(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn( [
				'workspace_url'     => 'https://acme.samplehq.io',
				'workspace_id'      => 42,
				'workspace_name'    => 'Acme',
				'connection_secret' => '',
				'connected_by'      => 'john@acme.com',
				'connected_at'      => 1700000000,
			] );

		$this->assertFalse( $this->make_manager()->is_connected() );
	}

	// --- get_connection() ---

	/**
	 * get_connection returns data with decrypted secret when connected.
	 */
	public function test_get_connection_returns_decrypted_data(): void {
		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		$encrypted = ConnectionManager::encrypt_secret( 'raw-secret' );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn(
				[
					'workspace_id'    => 42,
					'workspace_url'   => 'https://acme.samplehq.io',
					'workspace_name'  => 'Acme Labels',
					'connection_secret' => $encrypted,
					'connected_by'    => 'john@acme.com',
					'connected_at'    => 1700000000,
				]
			);

		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		$connection = $this->make_manager()->get_connection();

		$this->assertNotNull( $connection );
		$this->assertSame( 'raw-secret', $connection['connection_secret'] );
		$this->assertSame( 42, $connection['workspace_id'] );
		$this->assertSame( 'https://acme.samplehq.io', $connection['workspace_url'] );
		$this->assertSame( 'Acme Labels', $connection['workspace_name'] );
		$this->assertSame( 'john@acme.com', $connection['connected_by'] );
		$this->assertSame( 1700000000, $connection['connected_at'] );
	}

	/**
	 * get_connection returns null when not connected.
	 */
	public function test_get_connection_returns_null_when_not_connected(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn( [] );

		$this->assertNull( $this->make_manager()->get_connection() );
	}

	/**
	 * get_connection returns null when decryption fails (corrupted ciphertext).
	 */
	public function test_get_connection_returns_null_on_decryption_failure(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn(
				[
					'workspace_id'      => 42,
					'workspace_url'     => 'https://acme.samplehq.io',
					'workspace_name'    => 'Acme',
					'connection_secret' => 'corrupted-not-valid-base64-cipher',
					'connected_by'      => 'john@acme.com',
					'connected_at'      => 1700000000,
				]
			);

		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		$this->assertNull( $this->make_manager()->get_connection() );
	}

	/**
	 * get_connection returns null when required keys are missing.
	 */
	public function test_get_connection_returns_null_on_missing_keys(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn(
				[
					'workspace_id' => 42,
				]
			);

		$this->assertNull( $this->make_manager()->get_connection() );
	}

	// --- generate_state() ---

	/**
	 * generate_state stores the correct structure in wp_options.
	 */
	public function test_generate_state_stores_correct_structure(): void {
		$captured = null;

		Monkey\Functions\expect( 'wp_generate_password' )
			->once()
			->with( 32, false )
			->andReturn( 'random-token-32-chars' );

		Monkey\Functions\expect( 'site_url' )->once()->andReturn( 'https://example.com' );
		Monkey\Functions\expect( 'home_url' )
			->once()
			->with( '/shqf-connect-callback' )
			->andReturn( 'https://example.com/shqf-connect-callback' );

		Monkey\Functions\expect( 'update_option' )
			->once()
			->with(
				'shqf_connect_state',
				\Mockery::on(
					function ( $value ) use ( &$captured ) {
						$captured = $value;
						return true;
					}
				),
				false
			);

		$token = $this->make_manager()->generate_state( 1 );

		$this->assertSame( 'random-token-32-chars', $token );
		$this->assertSame( 'random-token-32-chars', $captured['token'] );
		$this->assertSame( 'https://example.com', $captured['site_url'] );
		$this->assertSame( 1, $captured['user_id'] );
		$this->assertEqualsWithDelta( time(), $captured['created_at'], 2 );
		$this->assertSame( 'https://example.com/shqf-connect-callback', $captured['return_url'] );
	}

	// --- get_connect_url() ---

	/**
	 * get_connect_url produces a URL with all required query parameters.
	 */
	public function test_get_connect_url_contains_required_params(): void {
		Monkey\Functions\expect( 'wp_generate_password' )
			->once()
			->with( 32, false )
			->andReturn( 'state-token-abc' );

		Monkey\Functions\expect( 'site_url' )->once()->andReturn( 'https://example.com' );
		Monkey\Functions\expect( 'home_url' )
			->once()
			->with( '/shqf-connect-callback' )
			->andReturn( 'https://example.com/shqf-connect-callback' );

		Monkey\Functions\expect( 'update_option' )->once();

		Monkey\Functions\expect( 'get_option' )
			->once()
			->with( 'shqf_connect_state', [] )
			->andReturn(
				[
					'token'      => 'state-token-abc',
					'site_url'   => 'https://example.com',
					'return_url' => 'https://example.com/shqf-connect-callback',
					'user_id'    => 1,
					'created_at' => 1700000000,
				]
			);

		$platform_base  = ConnectionManager::platform_url();
		$captured_args  = null;

		Monkey\Functions\expect( 'add_query_arg' )
			->once()
			->with(
				\Mockery::on(
					function ( $args ) use ( &$captured_args ) {
						$captured_args = $args;
						return true;
					}
				),
				$platform_base . '/connect/wordpress'
			)
			->andReturn( $platform_base . '/connect/wordpress?state=state-token-abc' );

		$user              = \Mockery::mock( 'WP_User' );
		$user->ID          = 1;
		$user->user_email  = 'admin@example.com';
		$user->first_name  = 'John';
		$user->last_name   = 'Doe';

		$this->make_manager()->get_connect_url( $user );

		$this->assertSame( rawurlencode( 'state-token-abc' ), $captured_args['state'] );
		$this->assertSame( rawurlencode( 'https://example.com' ), $captured_args['site_url'] );
		$this->assertSame( rawurlencode( 'admin@example.com' ), $captured_args['email'] );
		$this->assertSame( rawurlencode( 'John' ), $captured_args['first_name'] );
		$this->assertSame( rawurlencode( 'Doe' ), $captured_args['last_name'] );
		$this->assertSame( rawurlencode( 'https://example.com/shqf-connect-callback' ), $captured_args['return_url'] );
	}

	// --- validate_callback() ---

	/**
	 * Helper: build a valid callback POST payload.
	 *
	 * @param string $state_token The state token.
	 * @return array{connection_token: string, state: string, signature: string, _decoded: array<string, mixed>}
	 */
	private function build_callback_payload( string $state_token ): array {
		$token_data = [
			'workspace_url'     => 'https://acme.samplehq.io',
			'workspace_id'      => 42,
			'workspace_name'    => 'Acme Labels',
			'connection_secret' => 'raw-secret-64-chars',
			'connected_by'      => 'john@acme.com',
		];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$token_b64 = base64_encode( (string) wp_json_encode( $token_data ) );
		$signature = hash_hmac( 'sha256', $token_b64, $state_token );

		return [
			'connection_token' => $token_b64,
			'state'            => $state_token,
			'signature'        => $signature,
			'_decoded'         => $token_data,
		];
	}

	/**
	 * Valid callback stores and returns decoded data.
	 */
	public function test_validate_callback_succeeds_with_valid_data(): void {
		$state_token = 'valid-state-token-32chars';

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connect_state', [] )
			->once()
			->andReturn(
				[
					'token'      => $state_token,
					'site_url'   => 'https://example.com',
					'return_url' => 'https://example.com/shqf-connect-callback',
					'user_id'    => 1,
					'created_at' => time() - 60,
				]
			);

		Monkey\Functions\expect( 'wp_json_encode' )
			->once()
			->andReturnUsing( fn( $data ) => json_encode( $data ) );

		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connect_state' )->once();

		$payload = $this->build_callback_payload( $state_token );
		$result  = $this->make_manager()->validate_callback( $payload, 1 );

		$this->assertIsArray( $result );
		$this->assertSame( 42, $result['workspace_id'] );
		$this->assertSame( 'raw-secret-64-chars', $result['connection_secret'] );
		$this->assertSame( 'https://acme.samplehq.io', $result['workspace_url'] );
	}

	/**
	 * Callback fails when no state token is stored.
	 */
	public function test_validate_callback_fails_no_state(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connect_state', [] )
			->once()
			->andReturn( [] );

		Monkey\Functions\expect( '__' )->andReturnFirstArg();

		$result = $this->make_manager()->validate_callback( [ 'state' => 'token' ], 1 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shqf_no_state', $result->get_error_code() );
	}

	/**
	 * Callback fails when state token does not match.
	 */
	public function test_validate_callback_fails_state_mismatch(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connect_state', [] )
			->once()
			->andReturn(
				[
					'token'      => 'real-token',
					'user_id'    => 1,
					'created_at' => time(),
				]
			);

		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connect_state' )->once();
		Monkey\Functions\expect( '__' )->andReturnFirstArg();

		$result = $this->make_manager()->validate_callback( [ 'state' => 'wrong-token' ], 1 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shqf_state_mismatch', $result->get_error_code() );
	}

	/**
	 * Callback fails when state has expired (> 30 minutes).
	 */
	public function test_validate_callback_fails_expired(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connect_state', [] )
			->once()
			->andReturn(
				[
					'token'      => 'expired-token',
					'user_id'    => 1,
					'created_at' => time() - 2000,
				]
			);

		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connect_state' )->once();
		Monkey\Functions\expect( '__' )->andReturnFirstArg();

		$result = $this->make_manager()->validate_callback( [ 'state' => 'expired-token' ], 1 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shqf_state_expired', $result->get_error_code() );
	}

	/**
	 * Callback fails when user_id does not match.
	 */
	public function test_validate_callback_fails_user_mismatch(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connect_state', [] )
			->once()
			->andReturn(
				[
					'token'      => 'token',
					'user_id'    => 1,
					'created_at' => time(),
				]
			);

		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connect_state' )->once();
		Monkey\Functions\expect( '__' )->andReturnFirstArg();

		$result = $this->make_manager()->validate_callback( [ 'state' => 'token' ], 99 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shqf_user_mismatch', $result->get_error_code() );
	}

	/**
	 * Callback fails when HMAC signature is invalid.
	 */
	public function test_validate_callback_fails_invalid_signature(): void {
		$state_token = 'sig-test-token';

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connect_state', [] )
			->once()
			->andReturn(
				[
					'token'      => $state_token,
					'user_id'    => 1,
					'created_at' => time(),
				]
			);

		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connect_state' )->once();
		Monkey\Functions\expect( '__' )->andReturnFirstArg();

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$token_b64 = base64_encode( '{"workspace_id":42,"connection_secret":"s"}' );

		$result = $this->make_manager()->validate_callback(
			[
				'state'            => $state_token,
				'connection_token' => $token_b64,
				'signature'        => 'forged-signature',
			],
			1
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shqf_invalid_signature', $result->get_error_code() );
	}

	/**
	 * Callback fails when connection_token JSON is missing required fields.
	 */
	public function test_validate_callback_fails_incomplete_token(): void {
		$state_token = 'incomplete-test';

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connect_state', [] )
			->once()
			->andReturn(
				[
					'token'      => $state_token,
					'user_id'    => 1,
					'created_at' => time(),
				]
			);

		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connect_state' )->once();
		Monkey\Functions\expect( '__' )->andReturnFirstArg();

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$token_b64 = base64_encode( '{"workspace_name":"Acme"}' );
		$signature = hash_hmac( 'sha256', $token_b64, $state_token );

		$result = $this->make_manager()->validate_callback(
			[
				'state'            => $state_token,
				'connection_token' => $token_b64,
				'signature'        => $signature,
			],
			1
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shqf_invalid_token', $result->get_error_code() );
	}

	/**
	 * Callback fails when connection_token or signature is missing from POST.
	 */
	public function test_validate_callback_fails_missing_post_fields(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connect_state', [] )
			->once()
			->andReturn(
				[
					'token'      => 'token',
					'user_id'    => 1,
					'created_at' => time(),
				]
			);

		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connect_state' )->once();
		Monkey\Functions\expect( '__' )->andReturnFirstArg();

		$result = $this->make_manager()->validate_callback( [ 'state' => 'token' ], 1 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shqf_missing_data', $result->get_error_code() );
	}

	/**
	 * Callback fails when connection_token is not valid base64.
	 */
	public function test_validate_callback_fails_invalid_base64(): void {
		$state_token = 'b64-test-token';

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connect_state', [] )
			->once()
			->andReturn(
				[
					'token'      => $state_token,
					'user_id'    => 1,
					'created_at' => time(),
				]
			);

		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connect_state' )->once();
		Monkey\Functions\expect( '__' )->andReturnFirstArg();

		$bad_b64   = '!!!not-base64!!!';
		$signature = hash_hmac( 'sha256', $bad_b64, $state_token );

		$result = $this->make_manager()->validate_callback(
			[
				'state'            => $state_token,
				'connection_token' => $bad_b64,
				'signature'        => $signature,
			],
			1
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'shqf_invalid_token', $result->get_error_code() );
	}

	/**
	 * State token replay: second validate_callback with same token is rejected
	 * because the state option is deleted on first successful use.
	 */
	public function test_validate_callback_replay_rejected_after_consumption(): void {
		$state_token = 'replay-test-token-32chars';

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connect_state', [] )
			->twice()
			->andReturnValues( [
				[
					'token'      => $state_token,
					'site_url'   => 'https://example.com',
					'return_url' => 'https://example.com/callback',
					'user_id'    => 1,
					'created_at' => time() - 60,
				],
				[],
			] );

		Monkey\Functions\expect( 'wp_json_encode' )
			->andReturnUsing( fn( $data ) => json_encode( $data ) );

		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connect_state' )->once();
		Monkey\Functions\expect( '__' )->andReturnFirstArg();

		$payload = $this->build_callback_payload( $state_token );
		$manager = $this->make_manager();

		$result1 = $manager->validate_callback( $payload, 1 );
		$this->assertIsArray( $result1 );
		$this->assertSame( 42, $result1['workspace_id'] );

		$result2 = $manager->validate_callback( $payload, 1 );
		$this->assertInstanceOf( \WP_Error::class, $result2 );
		$this->assertSame( 'shqf_no_state', $result2->get_error_code() );
	}

	// --- store_connection() ---

	/**
	 * store_connection encrypts the secret and saves to wp_options.
	 */
	public function test_store_connection_saves_encrypted_data(): void {
		$captured = null;

		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		Monkey\Functions\expect( 'update_option' )
			->once()
			->with(
				'shqf_connection',
				\Mockery::on(
					function ( $value ) use ( &$captured ) {
						$captured = $value;
						return true;
					}
				),
				false
			);

		$result = $this->make_manager()->store_connection(
			[
				'workspace_url'     => 'https://acme.samplehq.io',
				'workspace_id'      => 42,
				'workspace_name'    => 'Acme Labels',
				'connection_secret' => 'raw-secret-from-platform',
				'connected_by'      => 'john@acme.com',
			]
		);

		$this->assertTrue( $result );
		$this->assertSame( 'https://acme.samplehq.io', $captured['workspace_url'] );
		$this->assertSame( 42, $captured['workspace_id'] );
		$this->assertSame( 'Acme Labels', $captured['workspace_name'] );
		$this->assertSame( 'john@acme.com', $captured['connected_by'] );
		$this->assertEqualsWithDelta( time(), $captured['connected_at'], 2 );
		$this->assertNotSame( 'raw-secret-from-platform', $captured['connection_secret'], 'Secret must be encrypted.' );
		$this->assertNotEmpty( $captured['connection_secret'] );
	}

	/**
	 * store_connection returns true on success (no state deletion — that happens in validate_callback).
	 */
	public function test_store_connection_returns_true(): void {
		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );
		Monkey\Functions\expect( 'update_option' )->once();

		$result = $this->make_manager()->store_connection(
			[
				'workspace_url'     => 'https://acme.samplehq.io',
				'workspace_id'      => 42,
				'workspace_name'    => 'Acme',
				'connection_secret' => 'secret',
				'connected_by'      => 'a@b.com',
			]
		);

		$this->assertTrue( $result );
	}

	// --- disconnect() ---

	/**
	 * disconnect clears connection, clears form IDs, and notifies platform.
	 */
	public function test_disconnect_clears_connection_and_notifies(): void {
		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		$encrypted = ConnectionManager::encrypt_secret( 'raw-secret' );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn(
				[
					'workspace_id'      => 42,
					'workspace_url'     => 'https://acme.samplehq.io',
					'workspace_name'    => 'Acme',
					'connection_secret' => $encrypted,
					'connected_by'      => 'john@acme.com',
					'connected_at'      => 1700000000,
				]
			);

		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connection' )->once();

		$forms = \Mockery::mock( FormsTable::class );
		$forms->shouldReceive( 'clear_all_shq_ids' )->once()->andReturn( 3 );

		$verifier = \Mockery::mock( ConnectionVerifier::class );
		$verifier->shouldReceive( 'sign_request' )
			->once()
			->with( 'DELETE', 'https://acme.samplehq.io/wp-json/samplehq/v1/plugin/connection', '', 'raw-secret' )
			->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		$mock_response = [ 'response' => [ 'code' => 200 ] ];

		Monkey\Functions\expect( 'wp_remote_request' )
			->once()
			->with(
				'https://acme.samplehq.io/wp-json/samplehq/v1/plugin/connection',
				\Mockery::on(
					function ( $args ) {
						return 'DELETE' === $args['method'] && 5 === $args['timeout'];
					}
				)
			)
			->andReturn( $mock_response );

		Monkey\Functions\expect( 'is_wp_error' )->with( $mock_response )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->with( $mock_response )->andReturn( 200 );

		$manager = $this->make_manager( $forms, $verifier );
		$manager->disconnect();
	}

	/**
	 * disconnect still clears local data when no connection exists.
	 */
	public function test_disconnect_when_not_connected(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn( [] );

		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connection' )->once();

		$forms = \Mockery::mock( FormsTable::class );
		$forms->shouldReceive( 'clear_all_shq_ids' )->once()->andReturn( 0 );

		Monkey\Functions\expect( 'wp_remote_request' )->never();

		$manager = $this->make_manager( $forms );
		$manager->disconnect();
	}

	/**
	 * Disconnect clears local data even when platform is unreachable (wp_remote_request returns WP_Error).
	 */
	public function test_disconnect_clears_local_data_when_platform_unreachable(): void {
		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		$encrypted = ConnectionManager::encrypt_secret( 'raw-secret' );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn( [
				'workspace_id'      => 42,
				'workspace_url'     => 'https://acme.samplehq.io',
				'workspace_name'    => 'Acme',
				'connection_secret' => $encrypted,
				'connected_by'      => 'john@acme.com',
				'connected_at'      => 1700000000,
			] );

		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );
		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connection' )->once();

		$forms = \Mockery::mock( FormsTable::class );
		$forms->shouldReceive( 'clear_all_shq_ids' )->once();

		$verifier = \Mockery::mock( ConnectionVerifier::class );
		$verifier->shouldReceive( 'sign_request' )->once()->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		Monkey\Functions\expect( 'wp_remote_request' )
			->once()
			->andReturn( new \WP_Error( 'http_request_failed', 'Connection refused' ) );

		$manager = $this->make_manager( $forms, $verifier );
		$manager->disconnect();
	}

	/**
	 * Disconnect with corrupted secret skips platform DELETE but still clears local options.
	 */
	public function test_disconnect_with_corrupted_secret_skips_http(): void {
		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn( [
				'workspace_id'      => 42,
				'workspace_url'     => 'https://acme.samplehq.io',
				'workspace_name'    => 'Acme',
				'connection_secret' => 'corrupted-not-valid-cipher',
				'connected_by'      => 'john@acme.com',
				'connected_at'      => 1700000000,
			] );

		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );
		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connection' )->once();

		$forms = \Mockery::mock( FormsTable::class );
		$forms->shouldReceive( 'clear_all_shq_ids' )->once();

		Monkey\Functions\expect( 'wp_remote_request' )->never();

		$manager = $this->make_manager( $forms );
		$manager->disconnect();
	}

	/**
	 * Disconnect fires shqf_disconnected action even when platform call fails.
	 */
	public function test_disconnect_fires_action_even_when_platform_fails(): void {
		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );

		$encrypted = ConnectionManager::encrypt_secret( 'raw-secret' );

		Monkey\Functions\expect( 'get_option' )
			->with( 'shqf_connection', [] )
			->once()
			->andReturn( [
				'workspace_id'      => 42,
				'workspace_url'     => 'https://acme.samplehq.io',
				'workspace_name'    => 'Acme',
				'connection_secret' => $encrypted,
				'connected_by'      => 'john@acme.com',
				'connected_at'      => 1700000000,
			] );

		Monkey\Functions\expect( 'wp_salt' )->with( 'auth' )->andReturn( self::SALT );
		Monkey\Functions\expect( 'delete_option' )->with( 'shqf_connection' )->once();

		$forms = \Mockery::mock( FormsTable::class );
		$forms->shouldReceive( 'clear_all_shq_ids' )->once();

		$verifier = \Mockery::mock( ConnectionVerifier::class );
		$verifier->shouldReceive( 'sign_request' )->once()->andReturn( [ 'X-SHQF-Signature' => 'sig' ] );

		Monkey\Functions\expect( 'wp_remote_request' )
			->once()
			->andReturn( new \WP_Error( 'timeout', 'Request timed out' ) );

		Monkey\Actions\expectDone( 'shqf_disconnected' )->once();

		$manager = $this->make_manager( $forms, $verifier );
		$manager->disconnect();
	}
}
