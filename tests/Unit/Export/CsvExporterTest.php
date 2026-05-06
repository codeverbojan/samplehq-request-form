<?php
/**
 * Tests for the CsvExporter.
 *
 * Tests cover the static safe() method for formula injection prevention.
 * The export() method streams to php://output and calls exit -- not
 * unit-testable without refactoring. E2E tests cover the full export flow.
 *
 * @package SampleHQForm\Tests\Unit\Export
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use SampleHQForm\Export\CsvExporter;

/**
 * CsvExporter unit tests.
 */
class CsvExporterTest extends TestCase {

	public function test_safe_returns_empty_string_unchanged(): void {
		$this->assertSame( '', CsvExporter::safe( '' ) );
	}

	public function test_safe_returns_normal_string_unchanged(): void {
		$this->assertSame( 'hello world', CsvExporter::safe( 'hello world' ) );
	}

	public function test_safe_prefixes_equals_sign(): void {
		$this->assertSame( "'=SUM(A1:A10)", CsvExporter::safe( '=SUM(A1:A10)' ) );
	}

	public function test_safe_prefixes_plus_sign(): void {
		$input    = "+cmd|' /C calc'!A0";
		$expected = "'" . $input;
		$this->assertSame( $expected, CsvExporter::safe( $input ) );
	}

	public function test_safe_prefixes_minus_sign(): void {
		$this->assertSame( "'-1+1", CsvExporter::safe( '-1+1' ) );
	}

	public function test_safe_prefixes_at_sign(): void {
		$this->assertSame( "'@SUM(A1)", CsvExporter::safe( '@SUM(A1)' ) );
	}

	public function test_safe_prefixes_tab(): void {
		$this->assertSame( "'\t=cmd", CsvExporter::safe( "\t=cmd" ) );
	}

	public function test_safe_prefixes_carriage_return(): void {
		$this->assertSame( "'\r=cmd", CsvExporter::safe( "\r=cmd" ) );
	}

	public function test_safe_single_character_triggers(): void {
		$this->assertSame( "'=", CsvExporter::safe( '=' ) );
		$this->assertSame( "'+", CsvExporter::safe( '+' ) );
		$this->assertSame( "'-", CsvExporter::safe( '-' ) );
		$this->assertSame( "'@", CsvExporter::safe( '@' ) );
	}

	public function test_safe_does_not_prefix_regular_number(): void {
		$this->assertSame( '42', CsvExporter::safe( '42' ) );
	}

	public function test_safe_does_not_prefix_url(): void {
		$this->assertSame( 'https://example.com', CsvExporter::safe( 'https://example.com' ) );
	}

	public function test_safe_does_not_prefix_multibyte_string(): void {
		$this->assertSame( '日本語テスト', CsvExporter::safe( '日本語テスト' ) );
	}

	public function test_safe_newline_is_not_guarded(): void {
		$this->assertSame( "\n=cmd", CsvExporter::safe( "\n=cmd" ), 'Newline is not a guarded prefix character' );
	}
}
