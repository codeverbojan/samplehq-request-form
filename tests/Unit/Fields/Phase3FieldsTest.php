<?php
// phpcs:disable WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
/**
 * Tests for Phase 3 field types.
 *
 * @package SampleHQForm\Tests\Unit\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Fields;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Fields\ConsentField;
use SampleHQForm\Fields\DateField;
use SampleHQForm\Fields\FileUploadField;
use SampleHQForm\Fields\HiddenField;
use SampleHQForm\Fields\HtmlField;
use SampleHQForm\Fields\UrlField;

/**
 * Phase 3 field types unit tests.
 */
class Phase3FieldsTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'esc_attr'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_html'            => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
			'esc_url_raw'         => static fn( $s ) => filter_var( (string) $s, FILTER_SANITIZE_URL ),
			'wp_kses_post'        => static fn( $s ) => strip_tags( (string) $s, '<a><p><strong><em><br><ul><ol><li>' ),
			'absint'              => static fn( $n ) => abs( (int) $n ),
			'__'                  => static fn( $s ) => $s,
		] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	// ===================== DateField =====================

	public function test_date_type(): void {
		$this->assertSame( 'date', ( new DateField() )->get_type() );
	}

	public function test_date_renders_input(): void {
		$field = new DateField();
		$html  = $field->render(
			[ 'id' => 'f_d', 'key' => 'birthday', 'label' => 'Birthday', 'required' => true ],
			'2024-01-15'
		);

		$this->assertStringContainsString( '<input type="date"', $html );
		$this->assertStringContainsString( 'value="2024-01-15"', $html );
		$this->assertStringContainsString( 'Birthday', $html );
		$this->assertStringContainsString( 'aria-required="true"', $html );
		$this->assertStringContainsString( 'shqf-field--date', $html );
	}

	public function test_date_renders_min_max(): void {
		$field = new DateField();
		$html  = $field->render(
			[
				'id'         => 'f_d',
				'key'        => 'date',
				'label'      => 'Date',
				'validation' => [ 'min' => '2024-01-01', 'max' => '2024-12-31' ],
			],
			''
		);

		$this->assertStringContainsString( 'min="2024-01-01"', $html );
		$this->assertStringContainsString( 'max="2024-12-31"', $html );
	}

	public function test_date_validates_required(): void {
		$field  = new DateField();
		$config = [ 'label' => 'Date', 'required' => true ];

		$this->assertNotNull( $field->validate( '', $config ) );
		$this->assertNull( $field->validate( '2024-06-15', $config ) );
	}

	public function test_date_validates_format(): void {
		$field  = new DateField();
		$config = [ 'label' => 'Date' ];

		$this->assertNull( $field->validate( '2024-06-15', $config ) );
		$this->assertNotNull( $field->validate( '06/15/2024', $config ) );
		$this->assertNotNull( $field->validate( '2024-13-01', $config ) ); // Invalid month.
		$this->assertNotNull( $field->validate( '2024-02-30', $config ) ); // Feb 30 doesn't exist.
		$this->assertNotNull( $field->validate( 'not-a-date', $config ) );
	}

	public function test_date_allows_empty_optional(): void {
		$field = new DateField();
		$this->assertNull( $field->validate( '', [ 'label' => 'Date', 'required' => false ] ) );
	}

	public function test_date_sanitize_valid(): void {
		$field = new DateField();
		$this->assertSame( '2024-06-15', $field->sanitize( '2024-06-15', [] ) );
	}

	public function test_date_sanitize_invalid(): void {
		$field = new DateField();
		$this->assertSame( '', $field->sanitize( 'not-a-date', [] ) );
		$this->assertSame( '', $field->sanitize( '<script>alert(1)</script>', [] ) );
	}

	public function test_date_rejects_array_input(): void {
		$field = new DateField();
		$this->assertNotNull( $field->validate( [ '2024-01-01' ], [ 'label' => 'Date' ] ) );
	}

	// ===================== UrlField =====================

	public function test_url_type(): void {
		$this->assertSame( 'url', ( new UrlField() )->get_type() );
	}

	public function test_url_renders_input(): void {
		$field = new UrlField();
		$html  = $field->render(
			[ 'id' => 'f_u', 'key' => 'website', 'label' => 'Website' ],
			'https://example.com'
		);

		$this->assertStringContainsString( '<input type="url"', $html );
		$this->assertStringContainsString( 'value="https://example.com"', $html );
		$this->assertStringContainsString( 'autocomplete="url"', $html );
		$this->assertStringContainsString( 'shqf-field--url', $html );
	}

	public function test_url_validates_required(): void {
		$field  = new UrlField();
		$config = [ 'label' => 'Website', 'required' => true ];

		$this->assertNotNull( $field->validate( '', $config ) );
		$this->assertNull( $field->validate( 'https://example.com', $config ) );
	}

	public function test_url_validates_format(): void {
		$field  = new UrlField();
		$config = [ 'label' => 'Website' ];

		$this->assertNull( $field->validate( 'https://example.com', $config ) );
		$this->assertNull( $field->validate( 'http://example.com/path?q=1', $config ) );
		$this->assertNotNull( $field->validate( 'not-a-url', $config ) );
		$this->assertNotNull( $field->validate( 'javascript:alert(1)', $config ) );
	}

	public function test_url_allows_empty_optional(): void {
		$field = new UrlField();
		$this->assertNull( $field->validate( '', [ 'label' => 'URL', 'required' => false ] ) );
	}

	public function test_url_sanitize(): void {
		$field = new UrlField();
		$this->assertStringContainsString( 'example.com', $field->sanitize( 'https://example.com', [] ) );
	}

	public function test_url_rejects_array_input(): void {
		$field = new UrlField();
		$this->assertNotNull( $field->validate( [ 'https://example.com' ], [ 'label' => 'URL' ] ) );
	}

	// ===================== HiddenField =====================

	public function test_hidden_type(): void {
		$this->assertSame( 'hidden', ( new HiddenField() )->get_type() );
	}

	public function test_hidden_renders_no_label(): void {
		$field = new HiddenField();
		$html  = $field->render(
			[ 'id' => 'f_h', 'key' => 'ref', 'label' => 'Reference', 'default_value' => 'abc123' ],
			''
		);

		$this->assertStringContainsString( '<input type="hidden"', $html );
		$this->assertStringContainsString( 'value="abc123"', $html );
		$this->assertStringNotContainsString( '<label', $html );
		$this->assertStringNotContainsString( 'shqf-field--', $html ); // No wrapper div.
	}

	public function test_hidden_uses_value_over_default(): void {
		$field = new HiddenField();
		$html  = $field->render(
			[ 'id' => 'f_h', 'key' => 'ref', 'default_value' => 'default' ],
			'override'
		);

		$this->assertStringContainsString( 'value="override"', $html );
		$this->assertStringNotContainsString( 'default', $html );
	}

	public function test_hidden_skips_validation(): void {
		$field = new HiddenField();
		$this->assertNull( $field->validate( '', [ 'label' => 'Hidden', 'required' => true ] ) );
		$this->assertNull( $field->validate( 'anything', [] ) );
		$this->assertNull( $field->validate( [ 'array' ], [] ) );
	}

	public function test_hidden_sanitize(): void {
		$field = new HiddenField();
		$this->assertSame( 'clean', $field->sanitize( '<b>clean</b>', [] ) );
	}

	// ===================== HtmlField =====================

	public function test_html_type(): void {
		$this->assertSame( 'html', ( new HtmlField() )->get_type() );
	}

	public function test_html_renders_content(): void {
		$field = new HtmlField();
		$html  = $field->render(
			[ 'id' => 'f_html', 'key' => 'info', 'content' => '<p>Please read our <a href="/privacy">privacy policy</a>.</p>' ],
			null
		);

		$this->assertStringContainsString( 'shqf-field--html', $html );
		$this->assertStringContainsString( 'shqf-html-content', $html );
		$this->assertStringContainsString( '<p>Please read', $html );
		$this->assertStringContainsString( '<a href="/privacy">', $html );
	}

	public function test_html_strips_dangerous_tags(): void {
		$field = new HtmlField();
		$html  = $field->render(
			[ 'id' => 'f_html', 'key' => 'xss', 'content' => '<script>alert(1)</script><p>Safe</p>' ],
			null
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '<p>Safe</p>', $html );
	}

	public function test_html_empty_content_returns_empty(): void {
		$field = new HtmlField();
		$this->assertSame( '', $field->render( [ 'id' => 'f_html', 'key' => 'empty' ], null ) );
	}

	public function test_html_no_label(): void {
		$field = new HtmlField();
		$html  = $field->render(
			[ 'id' => 'f_html', 'key' => 'info', 'label' => 'Info', 'content' => '<p>Text</p>' ],
			null
		);

		$this->assertStringNotContainsString( '<label', $html );
	}

	public function test_html_skip_validation(): void {
		$field = new HtmlField();
		$this->assertNull( $field->validate( 'anything', [] ) );
	}

	public function test_html_sanitize_returns_empty(): void {
		$field = new HtmlField();
		$this->assertSame( '', $field->sanitize( 'anything', [] ) );
	}

	// ===================== ConsentField =====================

	public function test_consent_type(): void {
		$this->assertSame( 'consent', ( new ConsentField() )->get_type() );
	}

	public function test_consent_renders_checkbox(): void {
		$field = new ConsentField();
		$html  = $field->render(
			[
				'id'           => 'f_gdpr',
				'key'          => 'gdpr',
				'label'        => 'Privacy',
				'consent_text' => 'I agree to the <a href="/privacy">privacy policy</a>.',
			],
			''
		);

		$this->assertStringContainsString( '<input type="checkbox"', $html );
		$this->assertStringContainsString( 'value="1"', $html );
		$this->assertStringContainsString( 'required', $html );
		$this->assertStringContainsString( 'aria-required="true"', $html );
		$this->assertStringContainsString( 'I agree to the', $html );
		$this->assertStringContainsString( '<a href="/privacy">', $html );
		$this->assertStringContainsString( 'shqf-field--consent', $html );
		$this->assertStringContainsString( 'shqf-required', $html );
	}

	public function test_consent_renders_checked(): void {
		$field = new ConsentField();
		$html  = $field->render(
			[ 'id' => 'f_gdpr', 'key' => 'gdpr', 'label' => 'Agree' ],
			'1'
		);

		$this->assertStringContainsString( 'checked', $html );
	}

	public function test_consent_always_required(): void {
		$field = new ConsentField();
		$this->assertNotNull( $field->validate( '', [ 'label' => 'Consent' ] ) );
		$this->assertNotNull( $field->validate( '0', [ 'label' => 'Consent' ] ) );
		$this->assertNotNull( $field->validate( null, [ 'label' => 'Consent' ] ) );
		$this->assertNull( $field->validate( '1', [ 'label' => 'Consent' ] ) );
	}

	public function test_consent_ignores_required_false(): void {
		$field = new ConsentField();
		// Even with required => false in config, consent must be checked.
		$this->assertNotNull( $field->validate( '', [ 'label' => 'Consent', 'required' => false ] ) );
		$this->assertNull( $field->validate( '1', [ 'label' => 'Consent', 'required' => false ] ) );
	}

	public function test_consent_sanitize(): void {
		$field = new ConsentField();
		$this->assertSame( '1', $field->sanitize( '1', [] ) );
		$this->assertSame( '', $field->sanitize( '0', [] ) );
		$this->assertSame( '', $field->sanitize( 'yes', [] ) );
		$this->assertSame( '', $field->sanitize( '', [] ) );
	}

	public function test_consent_strips_script_from_text(): void {
		$field = new ConsentField();
		$html  = $field->render(
			[
				'id'           => 'f_gdpr',
				'key'          => 'gdpr',
				'consent_text' => '<script>alert(1)</script>I agree',
			],
			''
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'I agree', $html );
	}

	// ===================== FileUploadField =====================

	public function test_file_upload_type(): void {
		$this->assertSame( 'file_upload', ( new FileUploadField() )->get_type() );
	}

	public function test_file_upload_renders_input(): void {
		$field = new FileUploadField();
		$html  = $field->render(
			[ 'id' => 'f_file', 'key' => 'resume', 'label' => 'Resume', 'required' => true ],
			''
		);

		$this->assertStringContainsString( '<input type="file"', $html );
		$this->assertStringContainsString( 'accept="', $html );
		$this->assertStringContainsString( 'aria-required="true"', $html );
		$this->assertStringContainsString( 'shqf-field--file_upload', $html );
		$this->assertStringContainsString( 'data-max-size=', $html );
		$this->assertStringContainsString( '<input type="hidden"', $html );
	}

	public function test_file_upload_renders_custom_types(): void {
		$field = new FileUploadField();
		$html  = $field->render(
			[
				'id'         => 'f_file',
				'key'        => 'doc',
				'label'      => 'Document',
				'validation' => [
					'allowed_types' => [ 'application/pdf' ],
					'max_size_mb'   => 10,
				],
			],
			''
		);

		$this->assertStringContainsString( 'accept="application/pdf"', $html );
		$this->assertStringContainsString( '10 MB', $html );
		$this->assertStringContainsString( 'PDF', $html );
	}

	public function test_file_upload_validates_required(): void {
		$field  = new FileUploadField();
		$config = [ 'label' => 'File', 'required' => true ];

		$this->assertNotNull( $field->validate( '', $config ) );
		$this->assertNotNull( $field->validate( '0', $config ) );
		$this->assertNull( $field->validate( '123', $config ) );
	}

	public function test_file_upload_validates_attachment_id(): void {
		$field  = new FileUploadField();
		$config = [ 'label' => 'File' ];

		$this->assertNull( $field->validate( '42', $config ) );
		$this->assertNull( $field->validate( '', $config ) ); // Optional, empty is fine.
		$this->assertNotNull( $field->validate( 'abc', $config ) ); // Not numeric.
		$this->assertNotNull( $field->validate( '-5', $config ) ); // Negative.
	}

	public function test_file_upload_rejects_array_input(): void {
		$field = new FileUploadField();
		$this->assertNotNull( $field->validate( [ '123' ], [ 'label' => 'File' ] ) );
	}

	public function test_file_upload_sanitize(): void {
		$field = new FileUploadField();
		$this->assertSame( '42', $field->sanitize( '42', [] ) );
		$this->assertSame( '', $field->sanitize( 'abc', [] ) );
		$this->assertSame( '', $field->sanitize( '0', [] ) );
		$this->assertSame( '', $field->sanitize( '', [] ) );
	}
}
