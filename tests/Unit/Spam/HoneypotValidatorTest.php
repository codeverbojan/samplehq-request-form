<?php
/**
 * Tests for the HoneypotValidator.
 *
 * @package SampleHQForm\Tests\Unit\Spam
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Spam;

use PHPUnit\Framework\TestCase;
use SampleHQForm\Spam\HoneypotValidator;

/**
 * HoneypotValidator unit tests.
 */
class HoneypotValidatorTest extends TestCase {

	/**
	 * Empty honeypot passes (not spam).
	 */
	public function test_empty_is_not_spam(): void {
		$validator = new HoneypotValidator();
		$this->assertFalse( $validator->is_spam( '' ) );
	}

	/**
	 * Whitespace-only honeypot passes (not spam).
	 */
	public function test_whitespace_is_not_spam(): void {
		$validator = new HoneypotValidator();
		$this->assertFalse( $validator->is_spam( '   ' ) );
	}

	/**
	 * Filled honeypot is spam.
	 */
	public function test_filled_is_spam(): void {
		$validator = new HoneypotValidator();
		$this->assertTrue( $validator->is_spam( 'bot filled this' ) );
	}

	/**
	 * Single character is spam.
	 */
	public function test_single_char_is_spam(): void {
		$validator = new HoneypotValidator();
		$this->assertTrue( $validator->is_spam( 'x' ) );
	}
}
