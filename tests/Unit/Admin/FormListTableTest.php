<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Admin;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Admin\FormListTable;
use SampleHQForm\Database\FormsTable;

class FormListTableTest extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\stubs( [
			'__'                  => static fn( $s ) => $s,
			'esc_html'            => static fn( $s ) => $s,
			'esc_html__'          => static fn( $s ) => $s,
			'esc_url'             => static fn( $s ) => $s,
			'esc_attr'            => static fn( $s ) => $s,
			'admin_url'           => static fn( $s ) => 'http://example.com/wp-admin/' . $s,
			'wp_nonce_url'        => static fn( $url ) => $url,
			'sanitize_text_field' => static fn( $s ) => $s,
			'wp_unslash'          => static fn( $s ) => $s,
			'absint'              => static fn( $v ) => abs( (int) $v ),
		] );

		$_REQUEST = [];
	}

	protected function tearDown(): void {
		$_REQUEST = [];
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_table( ?FormsTable $forms = null ): FormListTable {
		$forms = $forms ?? \Mockery::mock( FormsTable::class );
		return new FormListTable( $forms );
	}

	public function test_get_columns_returns_expected_keys(): void {
		$table   = $this->make_table();
		$columns = $table->get_columns();

		$this->assertArrayHasKey( 'cb', $columns );
		$this->assertArrayHasKey( 'title', $columns );
		$this->assertArrayHasKey( 'shortcode', $columns );
		$this->assertArrayHasKey( 'status', $columns );
		$this->assertArrayHasKey( 'submissions_count', $columns );
		$this->assertArrayHasKey( 'updated_at', $columns );
		$this->assertArrayHasKey( 'created_at', $columns );
		$this->assertCount( 7, $columns );
	}

	public function test_bulk_actions_active_view(): void {
		$_REQUEST['status'] = '';
		$table   = $this->make_table();
		$actions = $table->get_bulk_actions();

		$this->assertArrayHasKey( 'publish', $actions );
		$this->assertArrayHasKey( 'draft', $actions );
		$this->assertArrayHasKey( 'trash', $actions );
		$this->assertArrayNotHasKey( 'restore', $actions );
		$this->assertArrayNotHasKey( 'delete_permanent', $actions );
	}

	public function test_bulk_actions_trash_view(): void {
		$_REQUEST['status'] = 'trash';
		$table   = $this->make_table();
		$actions = $table->get_bulk_actions();

		$this->assertArrayHasKey( 'restore', $actions );
		$this->assertArrayHasKey( 'delete_permanent', $actions );
		$this->assertArrayNotHasKey( 'publish', $actions );
		$this->assertArrayNotHasKey( 'draft', $actions );
	}

	public function test_sortable_columns(): void {
		$table    = $this->make_table();
		$sortable = $table->get_sortable_columns();

		$this->assertArrayHasKey( 'title', $sortable );
		$this->assertArrayHasKey( 'submissions_count', $sortable );
		$this->assertArrayHasKey( 'updated_at', $sortable );
		$this->assertArrayHasKey( 'created_at', $sortable );
	}

	public function test_no_items_renders_empty_state(): void {
		Monkey\Functions\stubs( [
			'esc_html__' => static fn( $s ) => $s,
		] );

		$table = $this->make_table();

		ob_start();
		$table->no_items();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'shqf-empty-state-box', $output );
		$this->assertStringContainsString( 'Create Form', $output );
		$this->assertStringContainsString( 'dashicons-feedback', $output );
	}
}
