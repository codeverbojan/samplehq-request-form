<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Admin;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Admin\AdminNotice;

class AdminNoticeTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_success_stores_notice_in_transient(): void {
		Monkey\Functions\expect( 'get_current_user_id' )->andReturn( 1 );
		Monkey\Functions\expect( 'get_transient' )
			->with( 'shqf_admin_notices_1' )
			->andReturn( false );

		$stored = null;
		Monkey\Functions\expect( 'set_transient' )
			->once()
			->andReturnUsing( static function ( $key, $value ) use ( &$stored ) {
				$stored = $value;
				return true;
			} );

		AdminNotice::success( 'Saved.' );

		$this->assertIsArray( $stored );
		$this->assertCount( 1, $stored );
		$this->assertSame( 'success', $stored[0]['type'] );
		$this->assertSame( 'Saved.', $stored[0]['message'] );
	}

	public function test_error_stores_error_type(): void {
		Monkey\Functions\expect( 'get_current_user_id' )->andReturn( 1 );
		Monkey\Functions\expect( 'get_transient' )->andReturn( false );

		$stored = null;
		Monkey\Functions\expect( 'set_transient' )
			->once()
			->andReturnUsing( static function ( $key, $value ) use ( &$stored ) {
				$stored = $value;
				return true;
			} );

		AdminNotice::error( 'Failed.' );

		$this->assertSame( 'error', $stored[0]['type'] );
	}

	public function test_warning_stores_warning_type(): void {
		Monkey\Functions\expect( 'get_current_user_id' )->andReturn( 1 );
		Monkey\Functions\expect( 'get_transient' )->andReturn( false );

		$stored = null;
		Monkey\Functions\expect( 'set_transient' )
			->once()
			->andReturnUsing( static function ( $key, $value ) use ( &$stored ) {
				$stored = $value;
				return true;
			} );

		AdminNotice::warning( 'Warning.' );

		$this->assertSame( 'warning', $stored[0]['type'] );
	}

	public function test_multiple_notices_queue(): void {
		Monkey\Functions\expect( 'get_current_user_id' )->andReturn( 1 );

		$call_count = 0;
		$stored     = null;

		Monkey\Functions\expect( 'get_transient' )
			->andReturnUsing( static function () use ( &$stored ) {
				return $stored ?? false;
			} );

		Monkey\Functions\expect( 'set_transient' )
			->andReturnUsing( static function ( $key, $value ) use ( &$stored, &$call_count ) {
				$stored = $value;
				$call_count++;
				return true;
			} );

		AdminNotice::success( 'First.' );
		AdminNotice::error( 'Second.' );

		$this->assertSame( 2, $call_count );
		$this->assertCount( 2, $stored );
		$this->assertSame( 'success', $stored[0]['type'] );
		$this->assertSame( 'error', $stored[1]['type'] );
	}

	public function test_render_outputs_notices(): void {
		Monkey\Functions\expect( 'get_current_user_id' )->andReturn( 1 );
		Monkey\Functions\expect( 'get_transient' )
			->with( 'shqf_admin_notices_1' )
			->andReturn( [
				[ 'type' => 'success', 'message' => 'Done!' ],
			] );
		Monkey\Functions\expect( 'delete_transient' )
			->once()
			->with( 'shqf_admin_notices_1' );
		Monkey\Functions\stubs( [
			'esc_html'  => static fn( $s ) => $s,
			'esc_attr'  => static fn( $s ) => $s,
			'wp_kses_post' => static fn( $s ) => $s,
		] );

		ob_start();
		AdminNotice::render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( 'Done!', $output );
	}

	public function test_render_clears_transient(): void {
		Monkey\Functions\expect( 'get_current_user_id' )->andReturn( 1 );
		Monkey\Functions\expect( 'get_transient' )
			->andReturn( [ [ 'type' => 'error', 'message' => 'Err' ] ] );
		Monkey\Functions\expect( 'delete_transient' )->once();
		Monkey\Functions\stubs( [
			'esc_html'  => static fn( $s ) => $s,
			'esc_attr'  => static fn( $s ) => $s,
			'wp_kses_post' => static fn( $s ) => $s,
		] );

		ob_start();
		AdminNotice::render();
		ob_get_clean();
	}

	public function test_render_no_output_when_empty(): void {
		Monkey\Functions\expect( 'get_current_user_id' )->andReturn( 1 );
		Monkey\Functions\expect( 'get_transient' )->andReturn( false );

		ob_start();
		AdminNotice::render();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	public function test_notices_scoped_per_user(): void {
		Monkey\Functions\expect( 'get_current_user_id' )->andReturn( 42 );
		Monkey\Functions\expect( 'get_transient' )
			->with( 'shqf_admin_notices_42' )
			->andReturn( false );
		Monkey\Functions\expect( 'set_transient' )
			->once()
			->with( 'shqf_admin_notices_42', \Mockery::type( 'array' ), 60 );

		AdminNotice::success( 'User 42 notice.' );
	}
}
