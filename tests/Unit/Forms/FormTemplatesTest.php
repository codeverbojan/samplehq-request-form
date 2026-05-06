<?php
/**
 * Tests for FormTemplates.
 *
 * @package SampleHQForm\Tests\Unit\Forms
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Forms;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Forms\FormTemplates;
use SampleHQForm\Forms\FormValidator;

/**
 * FormTemplates unit tests.
 */
class FormTemplatesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'__'      => static fn( $s ) => $s,
			'wp_rand' => static fn() => random_int( 1, 999999 ),
		] );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_get_all_returns_four_templates(): void {
		$all = FormTemplates::get_all();

		$this->assertCount( 4, $all );
		$this->assertArrayHasKey( 'wizard', $all );
		$this->assertArrayHasKey( 'grid', $all );
		$this->assertArrayHasKey( 'checklist', $all );
		$this->assertArrayHasKey( 'blank', $all );
	}

	public function test_get_returns_template_by_slug(): void {
		$wizard = FormTemplates::get( 'wizard' );

		$this->assertNotNull( $wizard );
		$this->assertSame( 'wizard', $wizard['slug'] );
		$this->assertArrayHasKey( 'title', $wizard );
		$this->assertArrayHasKey( 'description', $wizard );
		$this->assertArrayHasKey( 'icon', $wizard );
		$this->assertArrayHasKey( 'config', $wizard );
	}

	public function test_get_returns_null_for_invalid_slug(): void {
		$this->assertNull( FormTemplates::get( 'nonexistent' ) );
		$this->assertNull( FormTemplates::get( '' ) );
	}

	public function test_all_templates_have_required_keys(): void {
		$required = [ 'slug', 'title', 'description', 'icon', 'form_title', 'config' ];

		foreach ( FormTemplates::get_all() as $slug => $template ) {
			foreach ( $required as $key ) {
				$this->assertArrayHasKey( $key, $template, "Template '$slug' missing key '$key'" );
			}
		}
	}

	public function test_all_configs_have_schema_version(): void {
		foreach ( FormTemplates::get_all() as $slug => $template ) {
			$this->assertSame( 1, $template['config']['schema_version'], "Template '$slug' missing schema_version" );
		}
	}

	public function test_all_configs_have_appearance_and_behavior(): void {
		foreach ( FormTemplates::get_all() as $slug => $template ) {
			$this->assertArrayHasKey( 'appearance', $template['config'], "Template '$slug' missing appearance" );
			$this->assertArrayHasKey( 'behavior', $template['config'], "Template '$slug' missing behavior" );
		}
	}

	public function test_wizard_has_steps(): void {
		$wizard = FormTemplates::get( 'wizard' );

		$this->assertArrayHasKey( 'steps', $wizard['config'] );
		$this->assertCount( 3, $wizard['config']['steps'] );
		$this->assertSame( 'wizard', $wizard['config']['layout'] );
	}

	public function test_wizard_has_correct_fields(): void {
		$wizard = FormTemplates::get( 'wizard' );
		$flat   = FormValidator::flatten_fields( $wizard['config']['fields'] );
		$keys   = array_column( $flat, 'key' );

		$this->assertContains( 'samples', $keys );
		$this->assertContains( 'first_name', $keys );
		$this->assertContains( 'last_name', $keys );
		$this->assertContains( 'email', $keys );
		$this->assertContains( 'company', $keys );
		$this->assertContains( 'phone', $keys );
	}

	public function test_wizard_sample_picker_on_step_zero(): void {
		$wizard = FormTemplates::get( 'wizard' );
		$flat   = FormValidator::flatten_fields( $wizard['config']['fields'] );
		$picker = null;

		foreach ( $flat as $field ) {
			if ( 'sample_picker' === $field['type'] ) {
				$picker = $field;
				break;
			}
		}

		$this->assertNotNull( $picker );
		$this->assertSame( 0, $picker['step_index'] );
		$this->assertSame( 'grid', $picker['config']['layout'] );
	}

	public function test_wizard_contact_fields_on_step_one(): void {
		$wizard = FormTemplates::get( 'wizard' );

		foreach ( $wizard['config']['fields'] as $field ) {
			if ( 'sample_picker' === $field['type'] ) {
				continue;
			}
			// Both rows and top-level fields on step 1.
			$this->assertSame( 1, $field['step_index'], "Field '{$field['type']}' should be on step 1" );
		}
	}

	public function test_grid_has_correct_layout(): void {
		$grid = FormTemplates::get( 'grid' );

		$this->assertSame( 'grid', $grid['config']['layout'] );
		$this->assertArrayNotHasKey( 'steps', $grid['config'] );
	}

	public function test_checklist_has_list_layout(): void {
		$checklist = FormTemplates::get( 'checklist' );

		$this->assertSame( 'list', $checklist['config']['layout'] );

		$flat   = FormValidator::flatten_fields( $checklist['config']['fields'] );
		$picker = null;
		foreach ( $flat as $field ) {
			if ( 'sample_picker' === $field['type'] ) {
				$picker = $field;
				break;
			}
		}

		$this->assertNotNull( $picker );
		$this->assertSame( 'list', $picker['config']['layout'] );
	}

	public function test_checklist_has_minimal_fields(): void {
		$checklist = FormTemplates::get( 'checklist' );
		$flat      = FormValidator::flatten_fields( $checklist['config']['fields'] );
		$keys      = array_column( $flat, 'key' );

		$this->assertContains( 'samples', $keys );
		$this->assertContains( 'first_name', $keys );
		$this->assertContains( 'last_name', $keys );
		$this->assertContains( 'email', $keys );
		$this->assertNotContains( 'company', $keys );
		$this->assertNotContains( 'phone', $keys );
		$this->assertNotContains( 'message', $keys );
	}

	public function test_grid_has_correct_fields(): void {
		$grid = FormTemplates::get( 'grid' );
		$flat = FormValidator::flatten_fields( $grid['config']['fields'] );
		$keys = array_column( $flat, 'key' );

		$this->assertContains( 'samples', $keys );
		$this->assertContains( 'first_name', $keys );
		$this->assertContains( 'last_name', $keys );
		$this->assertContains( 'email', $keys );
		$this->assertContains( 'company', $keys );
		$this->assertContains( 'phone', $keys );
		$this->assertNotContains( 'message', $keys );
	}

	public function test_blank_has_empty_fields(): void {
		$blank = FormTemplates::get( 'blank' );

		$this->assertSame( [], $blank['config']['fields'] );
		$this->assertArrayNotHasKey( 'layout', $blank['config'] );
		$this->assertArrayNotHasKey( 'steps', $blank['config'] );
	}

	public function test_field_ids_are_unique_within_template(): void {
		foreach ( FormTemplates::get_all() as $slug => $template ) {
			$flat = FormValidator::flatten_fields( $template['config']['fields'] );
			$ids  = array_column( $flat, 'id' );
			$this->assertSame( count( $ids ), count( array_unique( $ids ) ), "Template '$slug' has duplicate field IDs" );
		}
	}

	public function test_all_leaf_fields_have_required_keys(): void {
		$required = [ 'id', 'type', 'key', 'label', 'enabled' ];

		foreach ( FormTemplates::get_all() as $slug => $template ) {
			$flat = FormValidator::flatten_fields( $template['config']['fields'] );
			foreach ( $flat as $i => $field ) {
				foreach ( $required as $key ) {
					$this->assertArrayHasKey( $key, $field, "Template '$slug' field $i missing '$key'" );
				}
			}
		}
	}

	// -------------------------------------------------------------------------
	// Row group structure tests
	// -------------------------------------------------------------------------

	public function test_wizard_has_name_row(): void {
		$wizard = FormTemplates::get( 'wizard' );
		$rows   = array_filter( $wizard['config']['fields'], fn( $f ) => 'row' === $f['type'] );

		$this->assertGreaterThanOrEqual( 1, count( $rows ) );

		$name_row = null;
		foreach ( $rows as $row ) {
			$flat_keys = array_column(
				FormValidator::flatten_fields( [ $row ] ),
				'key'
			);
			if ( in_array( 'first_name', $flat_keys, true ) && in_array( 'last_name', $flat_keys, true ) ) {
				$name_row = $row;
				break;
			}
		}

		$this->assertNotNull( $name_row, 'Wizard should have a row containing first_name + last_name' );
		$this->assertCount( 2, $name_row['columns'] );
		$this->assertSame( '1fr', $name_row['columns'][0]['width'] );
		$this->assertSame( '1fr', $name_row['columns'][1]['width'] );
	}

	public function test_wizard_has_company_and_phone_fields(): void {
		$wizard = FormTemplates::get( 'wizard' );
		$flat   = FormValidator::flatten_fields( $wizard['config']['fields'] );
		$keys   = array_column( $flat, 'key' );

		$this->assertContains( 'company', $keys, 'Wizard should have a company field' );
		$this->assertContains( 'phone', $keys, 'Wizard should have a phone field' );
	}

	public function test_row_fields_have_no_width_property(): void {
		foreach ( FormTemplates::get_all() as $slug => $template ) {
			$flat = FormValidator::flatten_fields( $template['config']['fields'] );
			foreach ( $flat as $field ) {
				$this->assertArrayNotHasKey(
					'width',
					$field,
					"Template '$slug' field '{$field['key']}' should not have 'width' property"
				);
			}
		}
	}

	public function test_all_rows_have_valid_structure(): void {
		foreach ( FormTemplates::get_all() as $slug => $template ) {
			foreach ( $template['config']['fields'] as $field ) {
				if ( 'row' !== ( $field['type'] ?? '' ) ) {
					continue;
				}
				$this->assertArrayHasKey( 'id', $field, "Row in '$slug' missing 'id'" );
				$this->assertStringStartsWith( 'row_', $field['id'], "Row ID should start with 'row_'" );
				$this->assertArrayHasKey( 'columns', $field, "Row in '$slug' missing 'columns'" );
				$this->assertNotEmpty( $field['columns'], "Row in '$slug' has empty columns" );

				foreach ( $field['columns'] as $ci => $col ) {
					$this->assertArrayHasKey( 'width', $col, "Row col $ci in '$slug' missing 'width'" );
					$this->assertArrayHasKey( 'fields', $col, "Row col $ci in '$slug' missing 'fields'" );
				}
			}
		}
	}
}
