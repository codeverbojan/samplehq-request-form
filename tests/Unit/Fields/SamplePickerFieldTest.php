<?php
/**
 * Tests for the SamplePickerField.
 *
 * @package SampleHQForm\Tests\Unit\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Fields;

use Brain\Monkey;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Database\SampleImagesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Fields\SamplePickerField;
use SampleHQForm\Fields\ProductSource\ProductSourceInterface;

/**
 * SamplePickerField unit tests.
 */
class SamplePickerFieldTest extends TestCase {

	use MockeryPHPUnitIntegration;

	private $samples_table;
	private $images_table;
	private SamplePickerField $field;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->samples_table = Mockery::mock( SamplesTable::class );
		$this->images_table  = Mockery::mock( SampleImagesTable::class );
		$category_map        = Mockery::mock( SampleCategoryMapTable::class );
		$categories          = Mockery::mock( SampleCategoriesTable::class );

		$category_map->shouldReceive( 'get_categories_for_sample' )->andReturn( [] );
		$categories->shouldReceive( 'list_all' )->andReturn( [] );

		Monkey\Functions\stubs( [
			'esc_attr'                  => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_html'                  => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_attr__'                => static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ),
			'esc_url'                   => static fn( $s ) => (string) $s,
			'wp_kses_post'              => static fn( $s ) => (string) $s,
			'wp_get_attachment_image_url' => static fn() => 'https://example.com/image.jpg',
			'absint'                    => static fn( $n ) => abs( (int) $n ),
			'__'                        => static fn( $s ) => $s,
		] );

		$this->field = new SamplePickerField( $this->samples_table, $this->images_table, $category_map, $categories );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Get a standard field config for testing.
	 *
	 * @return array<string, mixed>
	 */
	private function get_field_config(): array {
		return [
			'id'       => 'f_picker',
			'key'      => 'samples',
			'label'    => 'Select Samples',
			'required' => true,
			'config'   => [
				'source'            => 'library',
				'filter'            => [ 'mode' => 'all' ],
				'max_selections'    => 5,
				'allow_quantity'    => true,
				'default_max_quantity' => 3,
				'layout'            => 'grid',
				'show_images'       => true,
				'show_descriptions' => true,
			],
		];
	}

	/**
	 * Get mock sample rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_mock_samples(): array {
		return [
			[ 'id' => '1', 'name' => 'Kraft Mailer', 'description' => 'A kraft mailer', 'status' => 'active', 'max_quantity' => '5' ],
			[ 'id' => '2', 'name' => 'Poly Bag', 'description' => 'A poly bag', 'status' => 'active', 'max_quantity' => '0' ],
			[ 'id' => '3', 'name' => 'Bubble Wrap', 'description' => '', 'status' => 'active', 'max_quantity' => '3' ],
		];
	}

	/**
	 * Type identifier is sample_picker.
	 */
	public function test_type(): void {
		$this->assertSame( 'sample_picker', $this->field->get_type() );
	}

	/**
	 * Render produces fieldset with legend and sample items.
	 */
	public function test_render_structure(): void {
		$this->samples_table->shouldReceive( 'list_all' )
			->once()
			->andReturn( $this->get_mock_samples() );

		$this->images_table->shouldReceive( 'get_featured' )
			->times( 3 )
			->andReturn( 42 );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertStringContainsString( '<fieldset', $html );
		$this->assertStringContainsString( '<legend', $html );
		$this->assertStringContainsString( 'Select Samples', $html );
		$this->assertStringContainsString( 'aria-required="true"', $html );
		$this->assertStringContainsString( 'shqf-picker--grid', $html );
	}

	/**
	 * Render shows all active samples with checkboxes.
	 */
	public function test_render_shows_samples(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( $this->get_mock_samples() );
		$this->images_table->shouldReceive( 'get_featured' )->times( 3 )->andReturn( 42 );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertStringContainsString( 'Kraft Mailer', $html );
		$this->assertStringContainsString( 'Poly Bag', $html );
		$this->assertStringContainsString( 'Bubble Wrap', $html );
		$this->assertSame( 3, substr_count( $html, '<input type="checkbox"' ) );
	}

	/**
	 * Render includes quantity inputs when enabled.
	 */
	public function test_render_quantity_inputs(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( $this->get_mock_samples() );
		$this->images_table->shouldReceive( 'get_featured' )->times( 3 )->andReturn( null );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertSame( 3, substr_count( $html, 'shqf-picker-item-qty-controls' ) );
		$this->assertStringContainsString( 'aria-label="Quantity for Kraft Mailer"', $html );
	}

	/**
	 * Render includes images with alt text.
	 */
	public function test_render_images(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );
		$this->images_table->shouldReceive( 'get_featured' )->once()->andReturn( 42 );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertStringContainsString( '<img', $html );
		// Images are decorative (label already names the item), so alt="".
		$this->assertStringContainsString( 'alt=""', $html );
	}

	/**
	 * Render includes aria-live region for selection count.
	 */
	public function test_render_aria_live_region(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [] );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertStringContainsString( 'aria-live="polite"', $html );
		$this->assertStringContainsString( 'data-max="5"', $html );
	}

	/**
	 * Render includes descriptions linked via aria-describedby.
	 */
	public function test_render_descriptions_linked(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );
		$this->images_table->shouldReceive( 'get_featured' )->once()->andReturn( null );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertStringContainsString( 'aria-describedby=', $html );
		$this->assertStringContainsString( 'A kraft mailer', $html );
	}

	/**
	 * Render pre-selects items from value.
	 */
	public function test_render_preselects(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( $this->get_mock_samples() );
		$this->images_table->shouldReceive( 'get_featured' )->times( 3 )->andReturn( null );

		$value = [ 'items' => [ 1 => [ 'selected' => '1', 'quantity' => '3' ] ] ];
		$html  = $this->field->render( $this->get_field_config(), $value );

		$this->assertStringContainsString( 'checked', $html );
	}

	// --- Validation ---

	/**
	 * Validate passes with valid selections.
	 */
	public function test_validate_passes(): void {
		$value = [ 'items' => [ 1 => [ 'selected' => '1', 'quantity' => '2' ] ] ];

		$this->samples_table->shouldReceive( 'get' )
			->with( 1 )
			->andReturn( [ 'id' => '1', 'name' => 'Kraft', 'status' => 'active', 'max_quantity' => '5' ] );

		$this->assertNull( $this->field->validate( $value, $this->get_field_config() ) );
	}

	/**
	 * Validate fails when required and nothing selected.
	 */
	public function test_validate_required_empty(): void {
		$this->assertNotNull( $this->field->validate( null, $this->get_field_config() ) );
		$this->assertNotNull( $this->field->validate( [], $this->get_field_config() ) );
		$this->assertNotNull( $this->field->validate( [ 'items' => [] ], $this->get_field_config() ) );
	}

	/**
	 * Validate fails when exceeding max selections.
	 */
	public function test_validate_max_selections(): void {
		$config = $this->get_field_config();
		$config['config']['max_selections'] = 2;

		$value = [
			'items' => [
				1 => [ 'selected' => '1' ],
				2 => [ 'selected' => '1' ],
				3 => [ 'selected' => '1' ],
			],
		];

		$error = $this->field->validate( $value, $config );
		$this->assertNotNull( $error );
		$this->assertStringContainsString( '2', $error );
	}

	/**
	 * Validate fails for non-existent sample ID.
	 */
	public function test_validate_nonexistent_sample(): void {
		$value = [ 'items' => [ 999 => [ 'selected' => '1' ] ] ];

		$this->samples_table->shouldReceive( 'get' )->with( 999 )->andReturn( null );

		$this->assertNotNull( $this->field->validate( $value, $this->get_field_config() ) );
	}

	/**
	 * Validate fails for archived sample.
	 */
	public function test_validate_archived_sample(): void {
		$value = [ 'items' => [ 1 => [ 'selected' => '1' ] ] ];

		$this->samples_table->shouldReceive( 'get' )
			->with( 1 )
			->andReturn( [ 'id' => '1', 'name' => 'Old', 'status' => 'archived' ] );

		$this->assertNotNull( $this->field->validate( $value, $this->get_field_config() ) );
	}

	/**
	 * Validate fails when quantity exceeds max.
	 */
	public function test_validate_quantity_exceeds_max(): void {
		$value = [ 'items' => [ 1 => [ 'selected' => '1', 'quantity' => '10' ] ] ];

		$this->samples_table->shouldReceive( 'get' )
			->with( 1 )
			->andReturn( [ 'id' => '1', 'name' => 'Kraft', 'status' => 'active', 'max_quantity' => '5' ] );

		$this->assertNotNull( $this->field->validate( $value, $this->get_field_config() ) );
	}

	/**
	 * Validate fails when quantity is zero.
	 */
	public function test_validate_quantity_zero(): void {
		$value = [ 'items' => [ 1 => [ 'selected' => '1', 'quantity' => '0' ] ] ];

		$this->samples_table->shouldReceive( 'get' )
			->with( 1 )
			->andReturn( [ 'id' => '1', 'name' => 'Kraft', 'status' => 'active', 'max_quantity' => '5' ] );

		$this->assertNotNull( $this->field->validate( $value, $this->get_field_config() ) );
	}

	// --- Sanitization ---

	/**
	 * Sanitize returns clean array of selections.
	 */
	public function test_sanitize(): void {
		$value = [
			'items' => [
				1 => [ 'selected' => '1', 'quantity' => '3' ],
				2 => [ 'selected' => '1', 'quantity' => '1' ],
				3 => [ 'quantity' => '2' ], // Not selected -- should be excluded.
			],
		];

		$result = $this->field->sanitize( $value, $this->get_field_config() );

		$this->assertCount( 2, $result );
		$this->assertSame( 1, $result[0]['id'] );
		$this->assertSame( 3, $result[0]['quantity'] );
		$this->assertSame( 2, $result[1]['id'] );
		$this->assertSame( 1, $result[1]['quantity'] );
	}

	/**
	 * Sanitize returns empty array when nothing selected.
	 */
	public function test_sanitize_empty(): void {
		$this->assertSame( [], $this->field->sanitize( null, $this->get_field_config() ) );
		$this->assertSame( [], $this->field->sanitize( [], $this->get_field_config() ) );
	}

	/**
	 * Sanitize enforces minimum quantity of 1 (absint + max).
	 */
	public function test_sanitize_min_quantity(): void {
		// absint(-5) = 5, max(1, 5) = 5. Negative values become their absolute.
		$value  = [ 'items' => [ 1 => [ 'selected' => '1', 'quantity' => '-5' ] ] ];
		$result = $this->field->sanitize( $value, $this->get_field_config() );
		$this->assertSame( 5, $result[0]['quantity'] );

		// Zero quantity should become 1 (max(1, 0) = 1).
		$value  = [ 'items' => [ 1 => [ 'selected' => '1', 'quantity' => '0' ] ] ];
		$result = $this->field->sanitize( $value, $this->get_field_config() );
		$this->assertSame( 1, $result[0]['quantity'] );
	}

	// --- Grid layout HTML tests ---

	public function test_grid_has_body_wrapper(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );
		$this->images_table->shouldReceive( 'get_featured' )->once()->andReturn( 42 );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertStringContainsString( 'shqf-picker-item-body', $html );
	}

	public function test_grid_has_check_overlay(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );
		$this->images_table->shouldReceive( 'get_featured' )->once()->andReturn( null );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertStringContainsString( 'shqf-picker-item-check', $html );
		$this->assertStringContainsString( '<svg', $html );
	}

	public function test_grid_selected_class_on_preselected(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( $this->get_mock_samples() );
		$this->images_table->shouldReceive( 'get_featured' )->times( 3 )->andReturn( null );

		$value = [ 'items' => [ 1 => [ 'selected' => '1', 'quantity' => '1' ] ] ];
		$html  = $this->field->render( $this->get_field_config(), $value );

		$this->assertStringContainsString( 'shqf-picker-item--selected', $html );
	}

	public function test_grid_no_selected_class_when_not_selected(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );
		$this->images_table->shouldReceive( 'get_featured' )->once()->andReturn( null );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertStringNotContainsString( 'shqf-picker-item--selected', $html );
	}

	public function test_grid_image_placeholder_when_no_image(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );
		$this->images_table->shouldReceive( 'get_featured' )->once()->andReturn( null );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertStringContainsString( 'shqf-picker-item-placeholder', $html );
	}

	public function test_grid_checkbox_is_sr_only(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );
		$this->images_table->shouldReceive( 'get_featured' )->once()->andReturn( null );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertStringContainsString( 'class="shqf-sr-only"', $html );
	}

	// --- List layout HTML tests ---

	public function test_list_has_checkbox_visual(): void {
		$config = $this->get_field_config();
		$config['config']['layout'] = 'list';

		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );
		$this->images_table->shouldReceive( 'get_featured' )->once()->andReturn( 42 );

		$html = $this->field->render( $config, null );

		$this->assertStringContainsString( 'shqf-picker-item-checkbox', $html );
		$this->assertStringContainsString( 'shqf-picker--list', $html );
	}

	public function test_list_has_thumb_class(): void {
		$config = $this->get_field_config();
		$config['config']['layout'] = 'list';

		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );
		$this->images_table->shouldReceive( 'get_featured' )->once()->andReturn( 42 );

		$html = $this->field->render( $config, null );

		$this->assertStringContainsString( 'shqf-picker-item-thumb', $html );
		$this->assertStringNotContainsString( 'shqf-picker-item-image', $html );
	}

	public function test_list_has_info_wrapper(): void {
		$config = $this->get_field_config();
		$config['config']['layout'] = 'list';

		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );
		$this->images_table->shouldReceive( 'get_featured' )->once()->andReturn( null );

		$html = $this->field->render( $config, null );

		$this->assertStringContainsString( 'shqf-picker-item-info', $html );
	}

	public function test_grid_show_images_false(): void {
		$config = $this->get_field_config();
		$config['config']['show_images'] = false;

		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );

		$html = $this->field->render( $config, null );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'shqf-picker-item-image', $html );
		// Check overlay should still be present.
		$this->assertStringContainsString( 'shqf-picker-item-check', $html );
	}

	public function test_list_show_images_false(): void {
		$config = $this->get_field_config();
		$config['config']['layout']      = 'list';
		$config['config']['show_images'] = false;

		$this->samples_table->shouldReceive( 'list_all' )->andReturn( [ $this->get_mock_samples()[0] ] );

		$html = $this->field->render( $config, null );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'shqf-picker-item-thumb', $html );
		// Visual checkbox and info wrapper should still be present.
		$this->assertStringContainsString( 'shqf-picker-item-checkbox', $html );
		$this->assertStringContainsString( 'shqf-picker-item-info', $html );
	}

	public function test_list_selected_class(): void {
		$config = $this->get_field_config();
		$config['config']['layout'] = 'list';

		$this->samples_table->shouldReceive( 'list_all' )->andReturn( $this->get_mock_samples() );
		$this->images_table->shouldReceive( 'get_featured' )->times( 3 )->andReturn( null );

		$value = [ 'items' => [ 2 => [ 'selected' => '1', 'quantity' => '1' ] ] ];
		$html  = $this->field->render( $config, $value );

		$this->assertStringContainsString( 'shqf-picker-item--selected', $html );
	}

	// --- WooCommerce validation ---

	/**
	 * Helper: create a field instance with a mock WooCommerce source.
	 *
	 * @param ProductSourceInterface&\Mockery\MockInterface $woo_source Mock source.
	 * @return SamplePickerField
	 */
	private function make_woo_field( $woo_source ): SamplePickerField {
		$category_map = Mockery::mock( SampleCategoryMapTable::class );
		$categories   = Mockery::mock( SampleCategoriesTable::class );
		$category_map->shouldReceive( 'get_categories_for_sample' )->andReturn( [] );
		$categories->shouldReceive( 'list_all' )->andReturn( [] );

		return new SamplePickerField(
			$this->samples_table,
			$this->images_table,
			$category_map,
			$categories,
			$woo_source
		);
	}

	/**
	 * Get a WooCommerce field config.
	 *
	 * @return array<string, mixed>
	 */
	private function get_woo_field_config(): array {
		$config = $this->get_field_config();
		$config['config']['source'] = 'woocommerce';
		return $config;
	}

	/**
	 * Validate passes for valid WC product ID.
	 */
	public function test_validate_woo_passes_for_valid_product(): void {
		$woo_source = Mockery::mock( ProductSourceInterface::class );
		$woo_source->shouldReceive( 'get_sample' )
			->with( 42 )
			->andReturn( [ 'id' => 42, 'name' => 'Kraft Box', 'sku' => 'KB-001', 'status' => 'active', 'max_quantity' => 5 ] );

		Monkey\Functions\when( 'get_option' )->justReturn( 3 );

		$field = $this->make_woo_field( $woo_source );
		$value = [ 'items' => [ 42 => [ 'selected' => '1', 'quantity' => '2' ] ] ];

		$this->assertNull( $field->validate( $value, $this->get_woo_field_config() ) );
	}

	/**
	 * Validate fails for invalid WC product ID.
	 */
	public function test_validate_woo_fails_for_invalid_product(): void {
		$woo_source = Mockery::mock( ProductSourceInterface::class );
		$woo_source->shouldReceive( 'get_sample' )->with( 999 )->andReturn( null );

		$field = $this->make_woo_field( $woo_source );
		$value = [ 'items' => [ 999 => [ 'selected' => '1' ] ] ];

		$error = $field->validate( $value, $this->get_woo_field_config() );
		$this->assertNotNull( $error );
		$this->assertStringContainsString( 'no longer available', $error );
	}

	/**
	 * Validate fails for out-of-stock WC product (status != active).
	 */
	public function test_validate_woo_fails_for_inactive_product(): void {
		$woo_source = Mockery::mock( ProductSourceInterface::class );
		$woo_source->shouldReceive( 'get_sample' )
			->with( 50 )
			->andReturn( [ 'id' => 50, 'name' => 'Gone Box', 'sku' => 'GB-001', 'status' => 'archived' ] );

		$field = $this->make_woo_field( $woo_source );
		$value = [ 'items' => [ 50 => [ 'selected' => '1' ] ] ];

		$this->assertNotNull( $field->validate( $value, $this->get_woo_field_config() ) );
	}

	/**
	 * Validate uses WC max quantity setting.
	 */
	public function test_validate_woo_respects_max_quantity(): void {
		$woo_source = Mockery::mock( ProductSourceInterface::class );
		$woo_source->shouldReceive( 'get_sample' )
			->with( 42 )
			->andReturn( [ 'id' => 42, 'name' => 'Kraft Box', 'sku' => 'KB-001', 'status' => 'active' ] );

		Monkey\Functions\when( 'get_option' )->justReturn( 3 );

		$field  = $this->make_woo_field( $woo_source );
		$config = $this->get_woo_field_config();
		$value  = [ 'items' => [ 42 => [ 'selected' => '1', 'quantity' => '10' ] ] ];

		$error = $field->validate( $value, $config );
		$this->assertNotNull( $error );
		$this->assertStringContainsString( 'Maximum quantity', $error );
	}

	/**
	 * Library validation unchanged when source is library (regression).
	 */
	public function test_validate_library_unchanged_with_woo_source_present(): void {
		$woo_source = Mockery::mock( ProductSourceInterface::class );
		$field      = $this->make_woo_field( $woo_source );

		$this->samples_table->shouldReceive( 'get' )
			->with( 1 )
			->andReturn( [ 'id' => '1', 'name' => 'Kraft', 'status' => 'active', 'max_quantity' => '5' ] );

		$value = [ 'items' => [ 1 => [ 'selected' => '1', 'quantity' => '2' ] ] ];

		// Library source config (not woocommerce).
		$this->assertNull( $field->validate( $value, $this->get_field_config() ) );
	}

	// --- WooCommerce sanitization ---

	/**
	 * Sanitize with WC source includes source flag, name, and SKU.
	 */
	public function test_sanitize_woo_includes_source_and_metadata(): void {
		$woo_source = Mockery::mock( ProductSourceInterface::class );
		$woo_source->shouldReceive( 'get_sample' )
			->with( 42 )
			->andReturn( [ 'id' => 42, 'name' => 'Kraft Box', 'sku' => 'KB-001', 'status' => 'active' ] );

		$field = $this->make_woo_field( $woo_source );
		$value = [ 'items' => [ 42 => [ 'selected' => '1', 'quantity' => '3' ] ] ];

		$result = $field->sanitize( $value, $this->get_woo_field_config() );

		$this->assertCount( 1, $result );
		$this->assertSame( 42, $result[0]['id'] );
		$this->assertSame( 3, $result[0]['quantity'] );
		$this->assertSame( 'woocommerce', $result[0]['source'] );
		$this->assertSame( 'Kraft Box', $result[0]['name'] );
		$this->assertSame( 'KB-001', $result[0]['sku'] );
	}

	/**
	 * Sanitize with library source does NOT include source flag (regression).
	 */
	public function test_sanitize_library_unchanged(): void {
		$value = [ 'items' => [ 1 => [ 'selected' => '1', 'quantity' => '2' ] ] ];

		$result = $this->field->sanitize( $value, $this->get_field_config() );

		$this->assertCount( 1, $result );
		$this->assertSame( 1, $result[0]['id'] );
		$this->assertSame( 2, $result[0]['quantity'] );
		$this->assertArrayNotHasKey( 'source', $result[0] );
		$this->assertArrayNotHasKey( 'name', $result[0] );
	}

	/**
	 * Sanitize with WC source handles deleted product gracefully.
	 */
	public function test_sanitize_woo_deleted_product_stores_empty_metadata(): void {
		$woo_source = Mockery::mock( ProductSourceInterface::class );
		$woo_source->shouldReceive( 'get_sample' )
			->with( 999 )
			->andReturn( null );

		$field = $this->make_woo_field( $woo_source );
		$value = [ 'items' => [ 999 => [ 'selected' => '1', 'quantity' => '1' ] ] ];

		$result = $field->sanitize( $value, $this->get_woo_field_config() );

		$this->assertCount( 1, $result );
		$this->assertSame( 'woocommerce', $result[0]['source'] );
		$this->assertSame( '', $result[0]['name'] );
		$this->assertSame( '', $result[0]['sku'] );
	}

	/**
	 * Picker items have role="checkbox" and aria-checked attributes.
	 */
	public function test_picker_items_have_role_checkbox(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( $this->get_mock_samples() );
		$this->images_table->shouldReceive( 'get_featured' )->times( 3 )->andReturn( null );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertSame( 3, substr_count( $html, 'role="checkbox"' ) );
		$this->assertSame( 3, substr_count( $html, 'aria-checked="false"' ) );
		$this->assertStringContainsString( 'aria-label="Kraft Mailer"', $html );
		$this->assertStringContainsString( 'aria-label="Poly Bag"', $html );
	}

	/**
	 * Pre-selected picker items render aria-checked="true".
	 */
	public function test_picker_item_preselected_has_aria_checked_true(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( $this->get_mock_samples() );
		$this->images_table->shouldReceive( 'get_featured' )->times( 3 )->andReturn( null );

		$value = [ 'items' => [ 1 => [ 'selected' => '1', 'quantity' => '2' ] ] ];
		$html  = $this->field->render( $this->get_field_config(), $value );

		$this->assertStringContainsString( 'aria-checked="true"', $html );
		$this->assertSame( 2, substr_count( $html, 'aria-checked="false"' ) );
	}

	/**
	 * Hidden checkboxes inside picker items have aria-hidden and tabindex=-1.
	 */
	public function test_picker_hidden_checkboxes_are_aria_hidden(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( $this->get_mock_samples() );
		$this->images_table->shouldReceive( 'get_featured' )->times( 3 )->andReturn( null );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertSame( 3, substr_count( $html, 'tabindex="-1" aria-hidden="true"' ) );
		$this->assertSame( 3, substr_count( $html, '<input type="checkbox"' ) );
		$this->assertStringContainsString( 'class="shqf-sr-only" tabindex="-1" aria-hidden="true"', $html );
	}

	/**
	 * Search input has aria-label attribute.
	 */
	public function test_search_input_has_aria_label(): void {
		$this->samples_table->shouldReceive( 'list_all' )->andReturn( array_merge(
			$this->get_mock_samples(),
			[ [ 'id' => '4', 'name' => 'Extra', 'description' => '', 'status' => 'active', 'max_quantity' => '1' ] ]
		) );
		$this->images_table->shouldReceive( 'get_featured' )->times( 4 )->andReturn( null );

		$html = $this->field->render( $this->get_field_config(), null );

		$this->assertStringContainsString( 'aria-label="Search samples"', $html );
	}
}
