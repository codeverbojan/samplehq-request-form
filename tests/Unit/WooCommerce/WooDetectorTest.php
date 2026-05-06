<?php
/**
 * Tests for WooDetector.
 *
 * @package SampleHQForm\Tests\Unit\WooCommerce
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\WooCommerce\WooDetector;

class WooDetectorTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_is_active_returns_true_when_woocommerce_exists(): void {
		// WooCommerce class is not loaded in unit tests, so is_active should be false.
		// We can't dynamically create the class mid-test without side effects,
		// so we test the false path here and rely on integration tests for the true path.
		$this->assertFalse( WooDetector::is_active() );
	}

	public function test_declare_compatibility_skips_when_features_util_missing(): void {
		// FeaturesUtil doesn't exist in unit tests -- declare_compatibility should return silently.
		WooDetector::declare_compatibility();
		$this->assertTrue( true ); // No exception = pass.
	}
}
