<?php

declare( strict_types=1 );

namespace SampleHQForm\Tests\Unit\Admin;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SampleHQForm\Admin\SubmissionListTable;
use SampleHQForm\Database\FormsTable;
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;

class SubmissionListTableTest extends TestCase {

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
			'esc_attr__'          => static fn( $s ) => $s,
		] );

		$_REQUEST = [];
	}

	protected function tearDown(): void {
		$_REQUEST = [];
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_table(): SubmissionListTable {
		$submissions = \Mockery::mock( SubmissionsTable::class );
		$forms       = \Mockery::mock( FormsTable::class );
		$meta        = \Mockery::mock( SubmissionMetaTable::class );

		return new SubmissionListTable( $submissions, $forms, $meta );
	}

	public function test_get_columns_returns_expected_keys(): void {
		$table   = $this->make_table();
		$columns = $table->get_columns();

		$this->assertArrayHasKey( 'cb', $columns );
		$this->assertArrayHasKey( 'email', $columns );
		$this->assertArrayHasKey( 'name', $columns );
		$this->assertArrayHasKey( 'form', $columns );
		$this->assertArrayHasKey( 'is_starred', $columns );
		$this->assertArrayHasKey( 'created_at', $columns );
		$this->assertCount( 7, $columns );
	}

	public function test_bulk_actions_active_view(): void {
		$_REQUEST['status'] = '';
		$table   = $this->make_table();
		$actions = $table->get_bulk_actions();

		$this->assertArrayHasKey( 'bulk_read', $actions );
		$this->assertArrayHasKey( 'bulk_spam', $actions );
		$this->assertArrayHasKey( 'bulk_trash', $actions );
	}

	public function test_bulk_actions_trash_view(): void {
		$_REQUEST['status'] = 'trash';
		$table   = $this->make_table();
		$actions = $table->get_bulk_actions();

		$this->assertArrayHasKey( 'bulk_restore', $actions );
		$this->assertArrayHasKey( 'bulk_delete', $actions );
		$this->assertArrayNotHasKey( 'bulk_spam', $actions );
	}

	public function test_bulk_actions_spam_view(): void {
		$_REQUEST['status'] = 'spam';
		$table   = $this->make_table();
		$actions = $table->get_bulk_actions();

		$this->assertArrayHasKey( 'bulk_restore', $actions );
		$this->assertArrayNotHasKey( 'bulk_delete', $actions );
	}

	public function test_sortable_columns(): void {
		$table    = $this->make_table();
		$sortable = $table->get_sortable_columns();

		$this->assertArrayHasKey( 'email', $sortable );
		$this->assertArrayHasKey( 'created_at', $sortable );
	}

	public function test_no_items_renders_empty_state(): void {
		$table = $this->make_table();

		ob_start();
		$table->no_items();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'shqf-empty-state-box', $output );
		$this->assertStringContainsString( 'View Forms', $output );
		$this->assertStringContainsString( 'dashicons-email-alt', $output );
	}
}
