<?php
/**
 * Tests for the DataMapper.
 *
 * @package SampleHQForm\Tests\Unit\Connection
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Connection;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Connection\DataMapper;
use SampleHQForm\Database\SamplesTable;

class DataMapperTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private SamplesTable $samples;
	private DataMapper $mapper;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->samples = \Mockery::mock( SamplesTable::class );
		$this->mapper  = new DataMapper( $this->samples );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Build a minimal form config with the given field definitions.
	 *
	 * @param array<int, array<string, mixed>> $fields Flat field definitions.
	 * @return array<string, mixed> Form row.
	 */
	private function make_form( array $fields, int $id = 1, string $title = 'Test Form' ): array {
		return [
			'id'     => $id,
			'title'  => $title,
			'config' => [ 'fields' => $fields ],
		];
	}

	/**
	 * Build a minimal submission row.
	 */
	private function make_submission( array $overrides = [] ): array {
		return array_merge(
			[
				'id'         => 100,
				'email'      => 'test@example.com',
				'source_url' => 'https://example.com/contact',
				'created_at' => '2026-01-15 10:30:00',
			],
			$overrides
		);
	}

	// ── Standard field mapping ────────────────────────────────────────

	public function test_maps_text_email_field(): void {
		$form = $this->make_form( [
			[ 'key' => 'email', 'type' => 'email', 'label' => 'Email' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'email' => 'alice@example.com' ],
			$form
		);

		$this->assertSame( 'alice@example.com', $result['fields']['email'] );
		$this->assertSame( 'alice@example.com', $result['email'] );
	}

	public function test_maps_standard_text_fields_by_key_convention(): void {
		$form = $this->make_form( [
			[ 'key' => 'first_name', 'type' => 'text', 'label' => 'First Name' ],
			[ 'key' => 'last_name', 'type' => 'text', 'label' => 'Last Name' ],
			[ 'key' => 'company', 'type' => 'text', 'label' => 'Company' ],
			[ 'key' => 'job_title', 'type' => 'text', 'label' => 'Job Title' ],
			[ 'key' => 'phone', 'type' => 'text', 'label' => 'Phone' ],
		] );

		$meta = [
			'first_name' => 'Alice',
			'last_name'  => 'Smith',
			'company'    => 'Acme Inc',
			'job_title'  => 'Engineer',
			'phone'      => '+1 555-1234',
		];

		$result = $this->mapper->map( $this->make_submission(), $meta, $form );

		$this->assertSame( 'Alice', $result['fields']['first_name'] );
		$this->assertSame( 'Smith', $result['fields']['last_name'] );
		$this->assertSame( 'Acme Inc', $result['fields']['company'] );
		$this->assertSame( 'Engineer', $result['fields']['job_title'] );
		$this->assertSame( '+1 555-1234', $result['fields']['phone'] );
	}

	public function test_maps_notes_key_as_notes_for_platform(): void {
		$form = $this->make_form( [
			[ 'key' => 'notes', 'type' => 'textarea', 'label' => 'Notes' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'notes' => 'Please send samples ASAP' ],
			$form
		);

		$this->assertSame( 'Please send samples ASAP', $result['fields']['notes'] );
	}

	public function test_message_key_goes_to_custom_fields(): void {
		$form = $this->make_form( [
			[ 'key' => 'message', 'type' => 'textarea', 'label' => 'Message' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'message' => 'Hello world' ],
			$form
		);

		$this->assertSame( 'Hello world', $result['fields']['message'] );
	}

	// ── Type priority over key name ──────────────────────────────────

	public function test_hidden_field_goes_to_custom_fields_even_with_standard_key(): void {
		$form = $this->make_form( [
			[ 'key' => 'company', 'type' => 'hidden', 'label' => 'Hidden Company' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'company' => 'utm_source=google' ],
			$form
		);

		$this->assertSame( 'utm_source=google', $result['fields']['company'] );
	}

	public function test_checkbox_array_value_json_encoded(): void {
		Monkey\Functions\expect( 'wp_json_encode' )
			->once()
			->andReturnUsing( fn( $v ) => json_encode( $v ) );

		$form = $this->make_form( [
			[ 'key' => 'interests', 'type' => 'checkbox', 'label' => 'Interests' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'interests' => [ 'design', 'architecture' ] ],
			$form
		);

		$this->assertSame( '["design","architecture"]', $result['fields']['interests'] );
	}

	public function test_empty_custom_only_field_excluded(): void {
		$form = $this->make_form( [
			[ 'key' => 'consent', 'type' => 'consent', 'label' => 'Consent' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'consent' => '' ],
			$form
		);

		$this->assertArrayNotHasKey( 'consent', $result['fields'] );
	}

	public function test_consent_field_goes_to_custom_fields(): void {
		$form = $this->make_form( [
			[ 'key' => 'gdpr_consent', 'type' => 'consent', 'label' => 'GDPR' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'gdpr_consent' => '1' ],
			$form
		);

		$this->assertSame( '1', $result['fields']['gdpr_consent'] );
	}

	// ── Skipped field types ──────────────────────────────────────────

	public function test_file_upload_field_skipped(): void {
		$form = $this->make_form( [
			[ 'key' => 'resume', 'type' => 'file_upload', 'label' => 'Resume' ],
			[ 'key' => 'email', 'type' => 'email', 'label' => 'Email' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'resume' => '42', 'email' => 'a@b.com' ],
			$form
		);

		$this->assertArrayNotHasKey( 'resume', $result['fields'] );
		$this->assertSame( 'a@b.com', $result['fields']['email'] );
	}

	public function test_html_field_skipped(): void {
		$form = $this->make_form( [
			[ 'key' => 'instructions', 'type' => 'html', 'label' => 'Instructions' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'instructions' => '' ],
			$form
		);

		$this->assertArrayNotHasKey( 'instructions', $result['fields'] );
	}

	// ── Address field: json_decode before sending ────────────────────

	public function test_address_field_json_decoded(): void {
		$form = $this->make_form( [
			[ 'key' => 'address', 'type' => 'address', 'label' => 'Address' ],
		] );

		$address_json = '{"street":"123 Main St","city":"NYC","state":"NY","zip":"10001","country":"US"}';

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'address' => $address_json ],
			$form
		);

		$this->assertIsArray( $result['fields']['address'] );
		$this->assertSame( '123 Main St', $result['fields']['address']['street'] );
		$this->assertSame( 'NYC', $result['fields']['address']['city'] );
	}

	public function test_address_field_non_json_passes_as_string(): void {
		$form = $this->make_form( [
			[ 'key' => 'address', 'type' => 'address', 'label' => 'Address' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'address' => '123 Main St, NYC' ],
			$form
		);

		$this->assertSame( '123 Main St, NYC', $result['fields']['address'] );
	}

	// ── Composite name field splitting ───────────────────────────────

	public function test_composite_name_field_split_into_first_and_last(): void {
		$form = $this->make_form( [
			[ 'key' => 'name', 'type' => 'name', 'label' => 'Full Name' ],
		] );

		$name_json = '{"first_name":"Alice","last_name":"Smith"}';

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'name' => $name_json ],
			$form
		);

		$this->assertSame( 'Alice', $result['fields']['first_name'] );
		$this->assertSame( 'Smith', $result['fields']['last_name'] );
		$this->assertArrayNotHasKey( 'name', $result['fields'] );
	}

	public function test_composite_name_with_only_first_name(): void {
		$form = $this->make_form( [
			[ 'key' => 'name', 'type' => 'name', 'label' => 'Name' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'name' => '{"first_name":"Bob","last_name":""}' ],
			$form
		);

		$this->assertSame( 'Bob', $result['fields']['first_name'] );
		$this->assertArrayNotHasKey( 'last_name', $result['fields'] );
	}

	public function test_composite_name_invalid_json_ignored(): void {
		$form = $this->make_form( [
			[ 'key' => 'name', 'type' => 'name', 'label' => 'Name' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'name' => 'not-json' ],
			$form
		);

		$this->assertArrayNotHasKey( 'first_name', $result['fields'] );
		$this->assertArrayNotHasKey( 'last_name', $result['fields'] );
	}

	// ── Phone field by type ──────────────────────────────────────────

	public function test_phone_type_maps_regardless_of_key_name(): void {
		$form = $this->make_form( [
			[ 'key' => 'mobile', 'type' => 'phone', 'label' => 'Mobile' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'mobile' => '+44 7700 900000' ],
			$form
		);

		$this->assertSame( '+44 7700 900000', $result['fields']['phone'] );
	}

	// ── Custom fields ────────────────────────────────────────────────

	public function test_unknown_fields_go_to_custom_fields(): void {
		$form = $this->make_form( [
			[ 'key' => 'preferred_color', 'type' => 'select', 'label' => 'Color' ],
			[ 'key' => 'quantity_needed', 'type' => 'number', 'label' => 'Qty' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'preferred_color' => 'blue', 'quantity_needed' => '100' ],
			$form
		);

		$this->assertSame( 'blue', $result['fields']['preferred_color'] );
		$this->assertSame( '100', $result['fields']['quantity_needed'] );
	}

	public function test_empty_value_custom_fields_excluded(): void {
		$form = $this->make_form( [
			[ 'key' => 'optional_note', 'type' => 'text', 'label' => 'Note' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'optional_note' => '' ],
			$form
		);

		$this->assertArrayNotHasKey( 'optional_note', $result['fields'] );
	}

	// ── Email fallback ───────────────────────────────────────────────

	public function test_email_falls_back_to_submission_row(): void {
		$form = $this->make_form( [
			[ 'key' => 'company', 'type' => 'text', 'label' => 'Company' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission( [ 'email' => 'row@example.com' ] ),
			[ 'company' => 'Acme' ],
			$form
		);

		$this->assertSame( 'row@example.com', $result['email'] );
		$this->assertSame( 'row@example.com', $result['fields']['email'] );
	}

	// ── Payload structure ────────────────────────────────────────────

	public function test_payload_includes_form_metadata(): void {
		$form = $this->make_form( [], 42, 'Contact Form' );

		$result = $this->mapper->map( $this->make_submission(), [], $form );

		$this->assertSame( 42, $result['plugin_form_id'] );
		$this->assertSame( 'Contact Form', $result['plugin_form_name'] );
	}

	public function test_payload_includes_submission_id(): void {
		$form = $this->make_form( [] );

		$result = $this->mapper->map(
			$this->make_submission( [ 'id' => 777 ] ),
			[],
			$form
		);

		$this->assertSame( 777, $result['plugin_submission_id'] );
	}

	public function test_payload_includes_source_url(): void {
		$form = $this->make_form( [] );

		$result = $this->mapper->map(
			$this->make_submission( [ 'source_url' => 'https://shop.example.com/samples' ] ),
			[],
			$form
		);

		$this->assertSame( 'https://shop.example.com/samples', $result['source_url'] );
	}

	public function test_payload_includes_created_at(): void {
		$form = $this->make_form( [] );

		$result = $this->mapper->map(
			$this->make_submission( [ 'created_at' => '2025-12-01 14:30:00' ] ),
			[],
			$form
		);

		$this->assertSame( '2025-12-01 14:30:00', $result['created_at'] );
	}

	public function test_empty_source_url_excluded(): void {
		$form = $this->make_form( [] );

		$result = $this->mapper->map(
			$this->make_submission( [ 'source_url' => '' ] ),
			[],
			$form
		);

		$this->assertArrayNotHasKey( 'source_url', $result );
	}

	// ── Sample ID rewriting ──────────────────────────────────────────

	public function test_migrated_library_sample_rewrites_to_shq_id(): void {
		$form = $this->make_form( [
			[ 'key' => 'samples', 'type' => 'sample_picker', 'label' => 'Samples', 'config' => [ 'source' => 'library' ] ],
		] );

		$this->samples->shouldReceive( 'get' )
			->with( 5 )
			->once()
			->andReturn( [ 'id' => 5, 'name' => 'Marble White', 'sku' => 'MW-001', 'shq_sample_id' => 42 ] );

		$meta = [ 'samples' => '[{"id":5,"quantity":3}]' ];

		$result = $this->mapper->map( $this->make_submission(), $meta, $form );

		$this->assertCount( 1, $result['samples'] );
		$this->assertSame( 42, $result['samples'][0]['id'] );
		$this->assertSame( 3, $result['samples'][0]['quantity'] );
		$this->assertArrayNotHasKey( 'name', $result['samples'][0] );
	}

	public function test_unmigrated_library_sample_sends_null_id_with_metadata(): void {
		$form = $this->make_form( [
			[ 'key' => 'samples', 'type' => 'sample_picker', 'label' => 'Samples', 'config' => [ 'source' => 'library' ] ],
		] );

		$this->samples->shouldReceive( 'get' )
			->with( 10 )
			->once()
			->andReturn( [ 'id' => 10, 'name' => 'Blue Widget', 'sku' => 'BW-001', 'shq_sample_id' => null ] );

		$meta = [ 'samples' => '[{"id":10,"quantity":2}]' ];

		$result = $this->mapper->map( $this->make_submission(), $meta, $form );

		$this->assertCount( 1, $result['samples'] );
		$this->assertNull( $result['samples'][0]['id'] );
		$this->assertSame( 2, $result['samples'][0]['quantity'] );
		$this->assertSame( 'Blue Widget', $result['samples'][0]['name'] );
		$this->assertSame( 'BW-001', $result['samples'][0]['sku'] );
		$this->assertSame( 10, $result['samples'][0]['plugin_sample_id'] );
	}

	public function test_woocommerce_sample_sends_null_id_with_source(): void {
		$form = $this->make_form( [
			[ 'key' => 'samples', 'type' => 'sample_picker', 'label' => 'Samples', 'config' => [ 'source' => 'woocommerce' ] ],
		] );

		$meta = [ 'samples' => '[{"id":99,"quantity":1,"source":"woocommerce","name":"Red Brick","sku":"RB-044"}]' ];

		$result = $this->mapper->map( $this->make_submission(), $meta, $form );

		$this->assertCount( 1, $result['samples'] );
		$this->assertNull( $result['samples'][0]['id'] );
		$this->assertSame( 'Red Brick', $result['samples'][0]['name'] );
		$this->assertSame( 'RB-044', $result['samples'][0]['sku'] );
		$this->assertSame( 'woocommerce', $result['samples'][0]['source'] );
	}

	public function test_mixed_samples_migrated_and_unmigrated(): void {
		$form = $this->make_form( [
			[ 'key' => 'samples', 'type' => 'sample_picker', 'label' => 'Samples', 'config' => [ 'source' => 'library' ] ],
		] );

		$this->samples->shouldReceive( 'get' )
			->with( 1 )
			->once()
			->andReturn( [ 'id' => 1, 'name' => 'Sample A', 'sku' => 'SA', 'shq_sample_id' => 100 ] );

		$this->samples->shouldReceive( 'get' )
			->with( 2 )
			->once()
			->andReturn( [ 'id' => 2, 'name' => 'Sample B', 'sku' => 'SB', 'shq_sample_id' => null ] );

		$meta = [ 'samples' => '[{"id":1,"quantity":1},{"id":2,"quantity":4}]' ];

		$result = $this->mapper->map( $this->make_submission(), $meta, $form );

		$this->assertCount( 2, $result['samples'] );
		$this->assertSame( 100, $result['samples'][0]['id'] );
		$this->assertNull( $result['samples'][1]['id'] );
		$this->assertSame( 'Sample B', $result['samples'][1]['name'] );
	}

	public function test_sample_not_found_in_db_skipped(): void {
		$form = $this->make_form( [
			[ 'key' => 'samples', 'type' => 'sample_picker', 'label' => 'Samples', 'config' => [ 'source' => 'library' ] ],
		] );

		$this->samples->shouldReceive( 'get' )
			->with( 999 )
			->once()
			->andReturn( null );

		$meta = [ 'samples' => '[{"id":999,"quantity":1}]' ];

		$result = $this->mapper->map( $this->make_submission(), $meta, $form );

		$this->assertEmpty( $result['samples'] );
	}

	public function test_no_sample_picker_field_returns_empty_samples(): void {
		$form = $this->make_form( [
			[ 'key' => 'email', 'type' => 'email', 'label' => 'Email' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'email' => 'a@b.com' ],
			$form
		);

		$this->assertEmpty( $result['samples'] );
	}

	// ── field_schema builder ─────────────────────────────────────────

	public function test_build_field_schema_includes_all_visible_fields(): void {
		$config = [
			'fields' => [
				[ 'key' => 'email', 'type' => 'email', 'label' => 'Email Address' ],
				[ 'key' => 'company', 'type' => 'text', 'label' => 'Company Name' ],
				[ 'key' => 'color', 'type' => 'select', 'label' => 'Preferred Color' ],
			],
		];

		$schema = $this->mapper->build_field_schema( $config );

		$this->assertCount( 3, $schema );
		$this->assertSame( [ 'key' => 'email', 'type' => 'email', 'label' => 'Email Address' ], $schema[0] );
		$this->assertSame( [ 'key' => 'company', 'type' => 'text', 'label' => 'Company Name' ], $schema[1] );
		$this->assertSame( [ 'key' => 'color', 'type' => 'select', 'label' => 'Preferred Color' ], $schema[2] );
	}

	public function test_build_field_schema_excludes_skipped_types(): void {
		$config = [
			'fields' => [
				[ 'key' => 'email', 'type' => 'email', 'label' => 'Email' ],
				[ 'key' => 'resume', 'type' => 'file_upload', 'label' => 'Resume' ],
				[ 'key' => 'divider', 'type' => 'html', 'label' => 'Divider' ],
			],
		];

		$schema = $this->mapper->build_field_schema( $config );

		$this->assertCount( 1, $schema );
		$this->assertSame( 'email', $schema[0]['key'] );
	}

	public function test_build_field_schema_handles_row_layout(): void {
		$config = [
			'fields' => [
				[
					'type'    => 'row',
					'columns' => [
						[
							'fields' => [
								[ 'key' => 'first_name', 'type' => 'text', 'label' => 'First' ],
							],
						],
						[
							'fields' => [
								[ 'key' => 'last_name', 'type' => 'text', 'label' => 'Last' ],
							],
						],
					],
				],
			],
		];

		$schema = $this->mapper->build_field_schema( $config );

		$this->assertCount( 2, $schema );
		$this->assertSame( 'first_name', $schema[0]['key'] );
		$this->assertSame( 'last_name', $schema[1]['key'] );
	}

	public function test_build_field_schema_skips_fields_without_key(): void {
		$config = [
			'fields' => [
				[ 'key' => 'email', 'type' => 'email', 'label' => 'Email' ],
				[ 'type' => 'text', 'label' => 'No Key' ],
			],
		];

		$schema = $this->mapper->build_field_schema( $config );

		$this->assertCount( 1, $schema );
	}

	// ── Fields with no config (unknown type) ─────────────────────────

	public function test_meta_key_with_no_config_maps_by_key_convention(): void {
		$form = $this->make_form( [] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'company' => 'Acme Corp' ],
			$form
		);

		$this->assertSame( 'Acme Corp', $result['fields']['company'] );
	}

	public function test_meta_key_with_no_config_and_unknown_key_goes_to_custom(): void {
		$form = $this->make_form( [] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'utm_source' => 'google' ],
			$form
		);

		$this->assertSame( 'google', $result['fields']['utm_source'] );
	}

	// --- 15C.1: DataMapper edge cases ---

	/**
	 * Sample picker with mixed sources (library ID + WooCommerce) -> both represented.
	 */
	public function test_sample_picker_mixed_library_and_woo_sources(): void {
		$form = $this->make_form( [
			[ 'key' => 'samples', 'type' => 'sample_picker', 'label' => 'Samples', 'config' => [ 'source' => 'library' ] ],
		] );

		$this->samples->shouldReceive( 'get' )
			->with( 5 )
			->once()
			->andReturn( [ 'id' => 5, 'name' => 'Marble', 'sku' => 'MR-01', 'shq_sample_id' => 42 ] );

		$meta = [
			'samples' => json_encode( [
				[ 'id' => 5, 'quantity' => 2 ],
				[ 'id' => 99, 'quantity' => 1, 'source' => 'woocommerce', 'name' => 'Red Brick', 'sku' => 'RB-01' ],
			] ),
		];

		$result = $this->mapper->map( $this->make_submission(), $meta, $form );

		$this->assertCount( 2, $result['samples'] );
		$this->assertSame( 42, $result['samples'][0]['id'] );
		$this->assertSame( 2, $result['samples'][0]['quantity'] );
		$this->assertNull( $result['samples'][1]['id'] );
		$this->assertSame( 'Red Brick', $result['samples'][1]['name'] );
		$this->assertSame( 'woocommerce', $result['samples'][1]['source'] );
	}

	/**
	 * Field with key "email" but type "hidden" -> goes to custom_fields, not standard email handler.
	 */
	public function test_hidden_email_field_goes_to_custom_fields(): void {
		$form = $this->make_form( [
			[ 'key' => 'email', 'type' => 'hidden', 'label' => 'Hidden Email' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission( [ 'email' => 'fallback@example.com' ] ),
			[ 'email' => 'hidden-tracking-value' ],
			$form
		);

		$this->assertSame( 'hidden-tracking-value', $result['fields']['email'] );
		$this->assertSame( 'fallback@example.com', $result['email'] );
	}

	/**
	 * Field with unknown type (not in any category) -> goes to custom_fields.
	 */
	public function test_unknown_type_goes_to_custom_fields(): void {
		$form = $this->make_form( [
			[ 'key' => 'my_widget', 'type' => 'unknown_widget', 'label' => 'Widget' ],
		] );

		$result = $this->mapper->map(
			$this->make_submission(),
			[ 'my_widget' => 'widget-value-123' ],
			$form
		);

		$this->assertSame( 'widget-value-123', $result['fields']['my_widget'] );
	}
}
