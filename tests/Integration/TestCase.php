<?php
/**
 * Base test case for integration tests.
 *
 * Uses PHPUnit TestCase directly with real WordPress loaded via bootstrap.
 * Each test uses transactions for cleanup (BEGIN on setUp, ROLLBACK on tearDown).
 *
 * @package SampleHQForm\Tests\Integration
 */

declare( strict_types=1 );

namespace SampleHQForm\Tests\Integration;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use SampleHQForm\Database\FormsTable;
use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SubmissionsTable;
use SampleHQForm\Database\SubmissionMetaTable;

abstract class TestCase extends PHPUnitTestCase {

	protected FormsTable $forms;
	protected SamplesTable $samples;
	protected SampleCategoriesTable $categories;
	protected SampleCategoryMapTable $category_map;
	protected SubmissionsTable $submissions;
	protected SubmissionMetaTable $submission_meta;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;

		// Transaction-based cleanup: roll back all DB changes after each test.
		$wpdb->query( 'START TRANSACTION' );

		$this->forms           = new FormsTable( $wpdb );
		$this->samples         = new SamplesTable( $wpdb );
		$this->categories      = new SampleCategoriesTable( $wpdb );
		$this->category_map    = new SampleCategoryMapTable( $wpdb );
		$this->submissions     = new SubmissionsTable( $wpdb );
		$this->submission_meta = new SubmissionMetaTable( $wpdb );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( 'ROLLBACK' );
		parent::tearDown();
	}

	/**
	 * Create a test sample.
	 *
	 * @param array<string, mixed> $overrides Field overrides.
	 * @return int Sample ID.
	 */
	protected function create_sample( array $overrides = [] ): int {
		return $this->samples->create( array_merge( [
			'name' => 'Test Sample ' . wp_rand(),
		], $overrides ) );
	}

	/**
	 * Create a test form.
	 *
	 * @param array<string, mixed> $overrides Field overrides.
	 * @return int Form ID.
	 */
	protected function create_form( array $overrides = [] ): int {
		return $this->forms->create( array_merge( [
			'title'      => 'Test Form ' . wp_rand(),
			'status'     => 'published',
			'created_by' => 1,
		], $overrides ) );
	}

	/**
	 * Create a test submission.
	 *
	 * @param int                  $form_id   Form ID.
	 * @param array<string, mixed> $overrides Field overrides (email, first_name, last_name, source_url, ip_address, user_agent).
	 * @return int Submission ID.
	 */
	protected function create_submission( int $form_id, array $overrides = [] ): int {
		$fields = array_merge( [
			'email'      => 'test@example.com',
			'first_name' => 'Test',
			'last_name'  => 'User',
			'source_url' => 'https://example.com/page',
		], $overrides );

		return $this->submissions->create( $form_id, $fields );
	}

	/**
	 * Create a test category.
	 *
	 * @param string $name Category name.
	 * @return int Category ID.
	 */
	protected function create_category( string $name = '' ): int {
		if ( empty( $name ) ) {
			$name = 'Category ' . wp_rand();
		}
		return $this->categories->create( [ 'name' => $name ] );
	}
}
