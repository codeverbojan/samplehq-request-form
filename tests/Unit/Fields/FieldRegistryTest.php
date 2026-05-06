<?php
/**
 * Tests for the FieldRegistry.
 *
 * @package SampleHQForm\Tests\Unit\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Fields;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Fields\FieldInterface;
use SampleHQForm\Fields\FieldRegistry;

/**
 * FieldRegistry unit tests.
 */
class FieldRegistryTest extends TestCase {

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
	 * Register and retrieve a field type.
	 */
	public function test_register_and_get(): void {
		$field = Mockery::mock( FieldInterface::class );
		$field->shouldReceive( 'get_type' )->andReturn( 'text' );

		$registry = new FieldRegistry();
		$registry->register( $field );

		$this->assertSame( $field, $registry->get( 'text' ) );
	}

	/**
	 * Get returns null for unregistered type.
	 */
	public function test_get_unknown_returns_null(): void {
		$registry = new FieldRegistry();
		$this->assertNull( $registry->get( 'nonexistent' ) );
	}

	/**
	 * Has returns true for registered, false for unknown.
	 */
	public function test_has(): void {
		$field = Mockery::mock( FieldInterface::class );
		$field->shouldReceive( 'get_type' )->andReturn( 'email' );

		$registry = new FieldRegistry();
		$registry->register( $field );

		$this->assertTrue( $registry->has( 'email' ) );
		$this->assertFalse( $registry->has( 'phone' ) );
	}

	/**
	 * Get types returns all registered type names.
	 */
	public function test_get_types(): void {
		$text  = Mockery::mock( FieldInterface::class );
		$text->shouldReceive( 'get_type' )->andReturn( 'text' );

		$email = Mockery::mock( FieldInterface::class );
		$email->shouldReceive( 'get_type' )->andReturn( 'email' );

		$registry = new FieldRegistry();
		$registry->register( $text );
		$registry->register( $email );

		$types = $registry->get_types();
		$this->assertContains( 'text', $types );
		$this->assertContains( 'email', $types );
		$this->assertCount( 2, $types );
	}

	/**
	 * Registering same type twice overwrites.
	 */
	public function test_register_overwrites(): void {
		$first  = Mockery::mock( FieldInterface::class );
		$first->shouldReceive( 'get_type' )->andReturn( 'text' );

		$second = Mockery::mock( FieldInterface::class );
		$second->shouldReceive( 'get_type' )->andReturn( 'text' );

		$registry = new FieldRegistry();
		$registry->register( $first );
		$registry->register( $second );

		$this->assertSame( $second, $registry->get( 'text' ) );
	}
}
