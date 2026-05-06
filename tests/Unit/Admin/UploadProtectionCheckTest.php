<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Admin;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Admin\UploadProtectionCheck;

class UploadProtectionCheckTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_run_returns_true_when_directory_does_not_exist(): void {
		Monkey\Functions\expect( 'wp_upload_dir' )->andReturn( [
			'basedir' => '/tmp/wp-uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
		] );
		Monkey\Functions\expect( 'set_transient' )->once()->with(
			UploadProtectionCheck::TRANSIENT_KEY,
			'protected',
			DAY_IN_SECONDS
		);

		$check = new UploadProtectionCheck();
		$this->assertTrue( $check->run() );
	}

	public function test_run_returns_true_when_response_is_403(): void {
		$dir = sys_get_temp_dir() . '/shqf_test_' . uniqid();
		mkdir( $dir . '/shqf', 0777, true );

		Monkey\Functions\expect( 'wp_upload_dir' )->andReturn( [
			'basedir' => $dir,
			'baseurl' => 'http://example.com/wp-content/uploads',
		] );
		Monkey\Functions\expect( 'wp_remote_head' )->once()->andReturn( [
			'response' => [ 'code' => 403 ],
		] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 403 );
		Monkey\Functions\expect( 'set_transient' )->once()->with(
			UploadProtectionCheck::TRANSIENT_KEY,
			'protected',
			DAY_IN_SECONDS
		);

		$check = new UploadProtectionCheck();
		$this->assertTrue( $check->run() );

		// Cleanup.
		@unlink( $dir . '/shqf/__shqf_test__.txt' );
		@rmdir( $dir . '/shqf' );
		@rmdir( $dir );
	}

	public function test_run_returns_false_when_response_is_200(): void {
		$dir = sys_get_temp_dir() . '/shqf_test_' . uniqid();
		mkdir( $dir . '/shqf', 0777, true );

		Monkey\Functions\expect( 'wp_upload_dir' )->andReturn( [
			'basedir' => $dir,
			'baseurl' => 'http://example.com/wp-content/uploads',
		] );
		Monkey\Functions\expect( 'wp_remote_head' )->once()->andReturn( [
			'response' => [ 'code' => 200 ],
		] );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( false );
		Monkey\Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Monkey\Functions\expect( 'set_transient' )->once()->with(
			UploadProtectionCheck::TRANSIENT_KEY,
			'exposed',
			DAY_IN_SECONDS
		);

		$check = new UploadProtectionCheck();
		$this->assertFalse( $check->run() );

		// Cleanup.
		@unlink( $dir . '/shqf/__shqf_test__.txt' );
		@rmdir( $dir . '/shqf' );
		@rmdir( $dir );
	}

	public function test_run_returns_true_on_network_error(): void {
		$dir = sys_get_temp_dir() . '/shqf_test_' . uniqid();
		mkdir( $dir . '/shqf', 0777, true );

		Monkey\Functions\expect( 'wp_upload_dir' )->andReturn( [
			'basedir' => $dir,
			'baseurl' => 'http://example.com/wp-content/uploads',
		] );

		$wp_error = \Mockery::mock( \WP_Error::class );
		Monkey\Functions\expect( 'wp_remote_head' )->once()->andReturn( $wp_error );
		Monkey\Functions\expect( 'is_wp_error' )->andReturn( true );
		Monkey\Functions\expect( 'set_transient' )->once()->with(
			UploadProtectionCheck::TRANSIENT_KEY,
			'protected',
			DAY_IN_SECONDS
		);

		$check = new UploadProtectionCheck();
		$this->assertTrue( $check->run() );

		// Cleanup.
		@unlink( $dir . '/shqf/__shqf_test__.txt' );
		@rmdir( $dir . '/shqf' );
		@rmdir( $dir );
	}

	public function test_is_exposed_returns_true_when_transient_set(): void {
		Monkey\Functions\expect( 'get_transient' )
			->with( UploadProtectionCheck::TRANSIENT_KEY )
			->andReturn( 'exposed' );

		$this->assertTrue( UploadProtectionCheck::is_exposed() );
	}

	public function test_is_exposed_returns_false_when_protected(): void {
		Monkey\Functions\expect( 'get_transient' )
			->with( UploadProtectionCheck::TRANSIENT_KEY )
			->andReturn( 'protected' );

		$this->assertFalse( UploadProtectionCheck::is_exposed() );
	}
}
