<?php
/**
 * Migration engine for bulk-syncing samples, categories, and submissions to the platform.
 *
 * @package SampleHQForm\Connection
 */

declare( strict_types=1 );

namespace SampleHQForm\Connection;

use SampleHQForm\Database\FormsTable;
use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates phased migration of samples, categories, and submissions to the platform.
 */
class MigrationEngine {

	/**
	 * Cron hook name for background processing.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'shqf_run_migration';

	/**
	 * Number of samples per batch.
	 *
	 * @var int
	 */
	private const SAMPLE_BATCH_SIZE = 10;

	/**
	 * Number of submissions per batch.
	 *
	 * @var int
	 */
	private const SUBMISSION_BATCH_SIZE = 50;

	/**
	 * Option key for migration progress state.
	 *
	 * @var string
	 */
	private const PROGRESS_OPTION = 'shqf_migration_progress';

	public const PHASE_IDLE        = 'idle';
	public const PHASE_CATEGORIES  = 'categories';
	public const PHASE_SAMPLES     = 'samples';
	public const PHASE_SUBMISSIONS = 'submissions';
	public const PHASE_COMPLETE    = 'complete';
	public const PHASE_CANCELLED   = 'cancelled';
	public const PHASE_ERROR       = 'error';

	/**
	 * Connection manager.
	 *
	 * @var ConnectionManager
	 */
	private ConnectionManager $connection;

	/**
	 * HMAC request signer.
	 *
	 * @var ConnectionVerifier
	 */
	private ConnectionVerifier $verifier;

	/**
	 * Samples table repository.
	 *
	 * @var SamplesTable
	 */
	private SamplesTable $samples;

	/**
	 * Categories table repository.
	 *
	 * @var SampleCategoriesTable
	 */
	private SampleCategoriesTable $categories;

	/**
	 * Category map table repository.
	 *
	 * @var SampleCategoryMapTable
	 */
	private SampleCategoryMapTable $category_map;

	/**
	 * Submissions table repository.
	 *
	 * @var SubmissionsTable
	 */
	private SubmissionsTable $submissions;

	/**
	 * Submission meta table repository.
	 *
	 * @var SubmissionMetaTable
	 */
	private SubmissionMetaTable $submission_meta;

	/**
	 * Forms table repository.
	 *
	 * @var FormsTable
	 */
	private FormsTable $forms;

	/**
	 * Submission-to-payload data mapper.
	 *
	 * @var DataMapper
	 */
	private DataMapper $mapper;

	/**
	 * Constructor.
	 *
	 * @param ConnectionManager      $connection      Connection manager.
	 * @param ConnectionVerifier     $verifier        HMAC signer.
	 * @param SamplesTable           $samples         Samples repository.
	 * @param SampleCategoriesTable  $categories      Categories repository.
	 * @param SampleCategoryMapTable $category_map    Category map repository.
	 * @param SubmissionsTable       $submissions     Submissions repository.
	 * @param SubmissionMetaTable    $submission_meta Submission meta repository.
	 * @param FormsTable             $forms           Forms repository.
	 * @param DataMapper             $mapper          Data mapper.
	 */
	public function __construct(
		ConnectionManager $connection,
		ConnectionVerifier $verifier,
		SamplesTable $samples,
		SampleCategoriesTable $categories,
		SampleCategoryMapTable $category_map,
		SubmissionsTable $submissions,
		SubmissionMetaTable $submission_meta,
		FormsTable $forms,
		DataMapper $mapper
	) {
		$this->connection      = $connection;
		$this->verifier        = $verifier;
		$this->samples         = $samples;
		$this->categories      = $categories;
		$this->category_map    = $category_map;
		$this->submissions     = $submissions;
		$this->submission_meta = $submission_meta;
		$this->forms           = $forms;
		$this->mapper          = $mapper;
	}

	/**
	 * Fetch migration preview: local counts + platform status.
	 *
	 * @return array{categories: int, samples: int, submissions: int, platform: array|null, error: string|null}
	 */
	public function get_preview(): array {
		$categories  = $this->get_categories_ordered();
		$samples     = $this->get_active_samples();
		$submissions = $this->get_unsynced_submissions();

		$platform = $this->call_status_endpoint();

		return [
			'categories'  => count( $categories ),
			'samples'     => count( $samples ),
			'submissions' => count( $submissions ),
			'platform'    => is_array( $platform ) ? $platform : null,
			'error'       => is_string( $platform ) ? $platform : null,
		];
	}

	/**
	 * Get all categories in topological order (parents before children).
	 *
	 * @return list<array>
	 */
	public function get_categories_ordered(): array {
		$all = $this->categories->list_all();

		$by_id    = [];
		$children = [];
		$roots    = [];

		foreach ( $all as $cat ) {
			$id           = (int) $cat['id'];
			$parent_id    = (int) ( $cat['parent_id'] ?? 0 );
			$by_id[ $id ] = $cat;

			if ( 0 === $parent_id ) {
				$roots[] = $id;
			} else {
				$children[ $parent_id ][] = $id;
			}
		}

		foreach ( $children as $parent_id => $child_ids ) {
			if ( ! isset( $by_id[ $parent_id ] ) ) {
				array_push( $roots, ...$child_ids );
			}
		}

		$ordered = [];
		$this->walk_tree( $roots, $children, $by_id, $ordered );

		return $ordered;
	}

	/**
	 * Recursive depth-first walk to produce topological order.
	 *
	 * @param int[]             $ids      IDs to process at this level.
	 * @param array<int, int[]> $children Parent-to-children map.
	 * @param array<int, array> $by_id    ID-to-category map.
	 * @param list<array>       &$result  Output accumulator.
	 */
	private function walk_tree( array $ids, array $children, array $by_id, array &$result ): void {
		sort( $ids );
		foreach ( $ids as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$result[] = $by_id[ $id ];
			}
			if ( isset( $children[ $id ] ) ) {
				$this->walk_tree( $children[ $id ], $children, $by_id, $result );
			}
		}
	}

	/**
	 * Get all active samples ordered by updated_at DESC.
	 *
	 * @return list<array>
	 */
	public function get_active_samples(): array {
		return $this->samples->list_all(
			[
				'status'  => 'active',
				'orderby' => 'created_at',
				'order'   => 'DESC',
				'limit'   => 100000,
			]
		);
	}

	/**
	 * Get submissions eligible for migration (not synced, not spam/trash).
	 *
	 * @return list<array>
	 */
	public function get_unsynced_submissions(): array {
		$all = $this->submissions->list_all(
			[
				'status'  => 'new',
				'orderby' => 'created_at',
				'order'   => 'ASC',
				'limit'   => 100000,
			]
		);

		return array_values(
			array_filter(
				$all,
				static function ( array $row ): bool {
					return empty( $row['synced_to_shq'] );
				}
			)
		);
	}

	/**
	 * Call GET /plugin/status on the platform to get plan info.
	 *
	 * @return array|string Array of status data on success, error message on failure.
	 */
	private function call_status_endpoint() {
		$conn = $this->connection->get_connection();
		if ( null === $conn ) {
			return 'Not connected to platform';
		}

		$url     = rtrim( $conn['workspace_url'], '/' ) . '/wp-json/samplehq/v1/plugin/status';
		$headers = $this->verifier->sign_request( 'GET', $url, '', $conn['connection_secret'] );

		$response = wp_remote_get(
			$url,
			[
				'headers'   => $headers,
				'timeout'   => 15,
				'sslverify' => ! defined( 'SHQF_PLATFORM_URL' ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return $body['message'] ?? "HTTP {$code}";
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Send a signed request to a platform endpoint.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   API path (e.g., '/wp-json/samplehq/v1/plugin/samples').
	 * @param string $body   JSON body.
	 * @return array{code: int, data: array|null, error: string|null}
	 */
	private function send_request( string $method, string $path, string $body = '' ): array {
		$conn = $this->connection->get_connection();
		if ( null === $conn ) {
			return [
				'code'  => 0,
				'data'  => null,
				'error' => 'Not connected',
			];
		}

		$url     = rtrim( $conn['workspace_url'], '/' ) . $path;
		$headers = $this->verifier->sign_request( $method, $url, $body, $conn['connection_secret'] );

		$args = [
			'method'    => $method,
			'headers'   => $headers,
			'timeout'   => 30,
			'sslverify' => ! defined( 'SHQF_PLATFORM_URL' ),
		];

		if ( '' !== $body ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'code'  => 0,
				'data'  => null,
				'error' => $response->get_error_message(),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return [
			'code'  => $code,
			'data'  => is_array( $data ) ? $data : null,
			'error' => $code >= 400 ? ( $data['message'] ?? $data['code'] ?? "HTTP {$code}" ) : null,
		];
	}

	/**
	 * Migrate a single category to the platform.
	 *
	 * @param array              $category   Local category row.
	 * @param array<int, string> $slug_map  Map of local category ID => slug (for parent resolution).
	 * @return array{status: string, term_id: int|null, error: string|null}
	 */
	public function migrate_category( array $category, array $slug_map = [] ): array {
		$payload = [
			'name' => (string) ( $category['name'] ?? '' ),
			'slug' => (string) ( $category['slug'] ?? '' ),
		];

		if ( ! empty( $category['description'] ) ) {
			$payload['description'] = (string) $category['description'];
		}

		$parent_id = (int) ( $category['parent_id'] ?? 0 );
		if ( $parent_id > 0 && isset( $slug_map[ $parent_id ] ) ) {
			$payload['parent_slug'] = $slug_map[ $parent_id ];
		}

		$body   = wp_json_encode( $payload );
		$result = $this->send_request( 'POST', '/wp-json/samplehq/v1/plugin/categories', (string) $body );

		if ( null !== $result['error'] ) {
			return [
				'status'  => 'error',
				'term_id' => null,
				'error'   => $result['error'],
			];
		}

		$term_id = (int) ( $result['data']['term_id'] ?? 0 );

		if ( $term_id > 0 ) {
			$this->categories->update( (int) $category['id'], [ 'shq_category_id' => $term_id ] );
		}

		return [
			'status'  => (string) ( $result['data']['status'] ?? 'unknown' ),
			'term_id' => $term_id > 0 ? $term_id : null,
			'error'   => null,
		];
	}

	/**
	 * Migrate all categories in topological order.
	 *
	 * @return array{created: int, updated: int, errors: list<array{name: string, error: string}>}
	 */
	public function migrate_all_categories(): array {
		$categories = $this->get_categories_ordered();
		$slug_map   = [];
		$created    = 0;
		$updated    = 0;
		$errors     = [];

		foreach ( $categories as $cat ) {
			$result = $this->migrate_category( $cat, $slug_map );

			$slug_map[ (int) $cat['id'] ] = (string) $cat['slug'];

			if ( 'error' === $result['status'] ) {
				$errors[] = [
					'name'  => (string) $cat['name'],
					'error' => (string) $result['error'],
				];
				continue;
			}

			if ( 'created' === $result['status'] ) {
				++$created;
			} else {
				++$updated;
			}
		}

		return [
			'created' => $created,
			'updated' => $updated,
			'errors'  => $errors,
		];
	}

	/**
	 * Migrate a batch of samples to the platform.
	 *
	 * @param list<array> $samples Samples to migrate (max SAMPLE_BATCH_SIZE).
	 * @return array{created: int, updated: int, skipped: int, errors: list<array>, plan_limit_reached: bool}
	 */
	public function migrate_sample_batch( array $samples ): array {
		$created    = 0;
		$updated    = 0;
		$skipped    = 0;
		$errors     = [];
		$plan_limit = false;

		foreach ( $samples as $sample ) {
			$payload = $this->build_sample_payload( $sample );
			$body    = wp_json_encode( $payload );
			$result  = $this->send_request( 'POST', '/wp-json/samplehq/v1/plugin/samples', (string) $body );

			if ( 422 === $result['code'] && 'plan_limit_reached' === ( $result['data']['code'] ?? '' ) ) {
				$plan_limit = true;
				break;
			}

			if ( null !== $result['error'] ) {
				$errors[] = [
					'name'  => (string) ( $sample['name'] ?? $sample['sku'] ?? '' ),
					'error' => (string) $result['error'],
				];
				continue;
			}

			$post_id = (int) ( $result['data']['post_id'] ?? 0 );
			if ( $post_id > 0 ) {
				$this->samples->update( (int) $sample['id'], [ 'shq_sample_id' => $post_id ] );
			}

			$status = (string) ( $result['data']['status'] ?? '' );
			if ( 'created' === $status ) {
				++$created;
			} elseif ( 'updated' === $status ) {
				++$updated;
			} else {
				++$skipped;
			}
		}

		return [
			'created'            => $created,
			'updated'            => $updated,
			'skipped'            => $skipped,
			'errors'             => $errors,
			'plan_limit_reached' => $plan_limit,
		];
	}

	/**
	 * Build API payload for a single sample.
	 *
	 * @param array $sample Local sample row.
	 * @return array Platform API payload.
	 */
	private function build_sample_payload( array $sample ): array {
		$payload = [
			'name'   => (string) ( $sample['name'] ?? '' ),
			'status' => 'active',
		];

		if ( ! empty( $sample['sku'] ) ) {
			$payload['sku'] = (string) $sample['sku'];
		}

		if ( ! empty( $sample['description'] ) ) {
			$payload['description'] = (string) $sample['description'];
		}

		if ( isset( $sample['unit_cost'] ) && '' !== $sample['unit_cost'] ) {
			$payload['unit_cost'] = (string) $sample['unit_cost'];
		}

		$custom_fields = $sample['custom_fields'] ?? null;
		if ( is_string( $custom_fields ) && '' !== $custom_fields ) {
			$decoded = json_decode( $custom_fields, true );
			if ( is_array( $decoded ) && ! empty( $decoded ) ) {
				$payload['custom_fields'] = $decoded;
			}
		}

		$category_slug = $this->get_sample_category_slug( (int) $sample['id'] );
		if ( null !== $category_slug ) {
			$payload['category'] = $category_slug;
		}

		return $payload;
	}

	/**
	 * Get the first category slug for a sample.
	 *
	 * @param int $sample_id Local sample ID.
	 * @return string|null Category slug or null if none.
	 */
	private function get_sample_category_slug( int $sample_id ): ?string {
		$cat_ids = $this->category_map->get_categories_for_sample( $sample_id );

		if ( empty( $cat_ids ) ) {
			return null;
		}

		$cat = $this->categories->get( $cat_ids[0] );

		return $cat ? (string) $cat['slug'] : null;
	}

	/**
	 * Migrate a batch of submissions to the platform via the batch endpoint.
	 *
	 * @param list<array> $submissions Submissions to migrate (max SUBMISSION_BATCH_SIZE).
	 * @return array{accepted: int, duplicates: int, errors: list<array>}
	 */
	public function migrate_submission_batch( array $submissions ): array {
		$accepted   = 0;
		$duplicates = 0;
		$errors     = [];

		if ( empty( $submissions ) ) {
			return [
				'accepted'   => 0,
				'duplicates' => 0,
				'errors'     => [],
			];
		}

		$items     = [];
		$index_map = [];

		foreach ( $submissions as $i => $submission ) {
			$form = $this->forms->get( (int) ( $submission['form_id'] ?? 0 ) );
			if ( null === $form ) {
				$errors[] = [
					'submission_id' => (int) ( $submission['id'] ?? 0 ),
					'error'         => 'Form not found',
				];
				continue;
			}

			$meta    = $this->submission_meta->get_all( (int) $submission['id'] );
			$payload = $this->mapper->map( $submission, $meta, $form );

			$items[]     = $payload;
			$index_map[] = (int) $submission['id'];
		}

		if ( empty( $items ) ) {
			return [
				'accepted'   => $accepted,
				'duplicates' => $duplicates,
				'errors'     => $errors,
			];
		}

		$body   = wp_json_encode( [ 'items' => $items ] );
		$result = $this->send_request( 'POST', '/wp-json/samplehq/v1/plugin/submissions/batch', (string) $body );

		if ( null !== $result['error'] && null === $result['data'] ) {
			foreach ( $index_map as $sub_id ) {
				$errors[] = [
					'submission_id' => $sub_id,
					'error'         => (string) $result['error'],
				];
			}
			return [
				'accepted'   => $accepted,
				'duplicates' => $duplicates,
				'errors'     => $errors,
			];
		}

		$results_list = $result['data']['results'] ?? [];

		foreach ( $results_list as $j => $item_result ) {
			$sub_id = $index_map[ $j ] ?? 0;
			$status = (string) ( $item_result['status'] ?? '' );

			if ( 'accepted' === $status ) {
				++$accepted;
				$platform_id = (int) ( $item_result['platform_id'] ?? 0 );
				if ( $sub_id > 0 && $platform_id > 0 ) {
					$this->submissions->mark_synced( $sub_id, $platform_id );
				}
			} elseif ( 'duplicate' === $status ) {
				++$duplicates;
				if ( $sub_id > 0 ) {
					$platform_id = (int) ( $item_result['platform_id'] ?? 0 );
					$this->submissions->mark_synced( $sub_id, $platform_id );
				}
			} else {
				$errors[] = [
					'submission_id' => $sub_id,
					'error'         => (string) ( $item_result['error'] ?? 'Unknown error' ),
				];
			}
		}

		return [
			'accepted'   => $accepted,
			'duplicates' => $duplicates,
			'errors'     => $errors,
		];
	}

	/**
	 * Start a new migration run.
	 *
	 * @param bool $include_submissions Whether to migrate submissions.
	 * @return array The initial progress state.
	 */
	public function start_migration( bool $include_submissions = false ): array {
		$categories  = $this->get_categories_ordered();
		$samples     = $this->get_active_samples();
		$submissions = $include_submissions ? $this->get_unsynced_submissions() : [];

		$progress = [
			'phase'                  => self::PHASE_CATEGORIES,
			'include_submissions'    => $include_submissions,
			'started_at'             => time(),
			'categories_total'       => count( $categories ),
			'categories_completed'   => 0,
			'categories_created'     => 0,
			'categories_updated'     => 0,
			'samples_total'          => count( $samples ),
			'samples_completed'      => 0,
			'samples_created'        => 0,
			'samples_updated'        => 0,
			'samples_skipped'        => 0,
			'submissions_total'      => count( $submissions ),
			'submissions_completed'  => 0,
			'submissions_accepted'   => 0,
			'submissions_duplicates' => 0,
			'plan_limit_reached'     => false,
			'errors'                 => [],
		];

		update_option( self::PROGRESS_OPTION, $progress, false );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		}

		return $progress;
	}

	/**
	 * Get the current migration progress.
	 *
	 * @return array|null Progress data or null if no migration running.
	 */
	public function get_progress(): ?array {
		$progress = get_option( self::PROGRESS_OPTION, null );
		return is_array( $progress ) ? $progress : null;
	}

	/**
	 * Cancel the current migration.
	 */
	public function cancel_migration(): void {
		$progress = $this->get_progress();
		if ( null === $progress ) {
			return;
		}

		$progress['phase'] = self::PHASE_CANCELLED;
		update_option( self::PROGRESS_OPTION, $progress, false );

		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Option key for migration lock.
	 *
	 * @var string
	 */
	private const LOCK_KEY = 'shqf_migration_lock';

	/**
	 * Lock timeout in seconds.
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT = 120;

	/**
	 * Process the next batch of the current migration phase.
	 * Called by wp-cron. Re-schedules itself if more work remains.
	 */
	public function process_next_batch(): void {
		if ( ! $this->acquire_lock() ) {
			return;
		}

		try {
			$progress = $this->get_progress();
			if ( null === $progress ) {
				return;
			}

			$phase = (string) ( $progress['phase'] ?? '' );

			if ( in_array( $phase, [ self::PHASE_COMPLETE, self::PHASE_CANCELLED, self::PHASE_ERROR, self::PHASE_IDLE ], true ) ) {
				return;
			}

			if ( self::PHASE_CATEGORIES === $phase ) {
				$this->process_categories_phase( $progress );
			} elseif ( self::PHASE_SAMPLES === $phase ) {
				$this->process_samples_phase( $progress );
			} elseif ( self::PHASE_SUBMISSIONS === $phase ) {
				$this->process_submissions_phase( $progress );
			}
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Acquire a MySQL-level advisory lock to prevent concurrent runs.
	 *
	 * @return bool True if lock acquired.
	 */
	private function acquire_lock(): bool {
		// add_option uses INSERT which fails on duplicate key (atomic at MySQL level).
		if ( add_option( self::LOCK_KEY, time(), '', false ) ) {
			return true;
		}

		$locked_at = (int) get_option( self::LOCK_KEY, 0 );
		if ( $locked_at > 0 && ( time() - $locked_at ) > self::LOCK_TIMEOUT ) {
			delete_option( self::LOCK_KEY );
			return (bool) add_option( self::LOCK_KEY, time(), '', false );
		}

		return false;
	}

	/**
	 * Release the migration lock.
	 *
	 * @return void
	 */
	private function release_lock(): void {
		delete_option( self::LOCK_KEY );
	}

	/**
	 * Process the categories phase of migration.
	 *
	 * @param array $progress Progress state (passed by reference).
	 * @return void
	 */
	private function process_categories_phase( array &$progress ): void {
		$result = $this->migrate_all_categories();

		$progress['categories_completed'] = $progress['categories_total'];
		$progress['categories_created']   = $result['created'];
		$progress['categories_updated']   = $result['updated'];

		foreach ( $result['errors'] as $err ) {
			$progress['errors'][] = [
				'phase'  => 'categories',
				'detail' => $err,
			];
		}

		$progress['phase'] = self::PHASE_SAMPLES;
		update_option( self::PROGRESS_OPTION, $progress, false );
		$this->schedule_next();
	}

	/**
	 * Process the next batch of samples.
	 *
	 * @param array $progress Progress state (passed by reference).
	 * @return void
	 */
	private function process_samples_phase( array &$progress ): void {
		$all_samples = array_values(
			array_filter(
				$this->get_active_samples(),
				static function ( array $s ): bool {
					return empty( $s['shq_sample_id'] );
				}
			)
		);
		$batch       = array_slice( $all_samples, 0, self::SAMPLE_BATCH_SIZE );

		if ( empty( $batch ) ) {
			$this->advance_from_samples( $progress );
			return;
		}

		$result = $this->migrate_sample_batch( $batch );

		$progress['samples_completed'] += $result['created'] + $result['updated'] + $result['skipped'] + count( $result['errors'] );
		$progress['samples_created']   += $result['created'];
		$progress['samples_updated']   += $result['updated'];
		$progress['samples_skipped']   += $result['skipped'];

		foreach ( $result['errors'] as $err ) {
			$progress['errors'][] = [
				'phase'  => 'samples',
				'detail' => $err,
			];
		}

		if ( $result['plan_limit_reached'] ) {
			$progress['plan_limit_reached'] = true;
			$this->advance_from_samples( $progress );
			return;
		}

		if ( $progress['samples_completed'] >= $progress['samples_total'] ) {
			$this->advance_from_samples( $progress );
			return;
		}

		update_option( self::PROGRESS_OPTION, $progress, false );
		$this->schedule_next();
	}

	/**
	 * Advance from samples phase to next phase.
	 *
	 * @param array $progress Progress state (passed by reference).
	 * @return void
	 */
	private function advance_from_samples( array &$progress ): void {
		if ( $progress['include_submissions'] ) {
			$progress['phase'] = self::PHASE_SUBMISSIONS;
		} else {
			$progress['phase'] = self::PHASE_COMPLETE;
		}
		update_option( self::PROGRESS_OPTION, $progress, false );

		if ( self::PHASE_COMPLETE !== $progress['phase'] ) {
			$this->schedule_next();
		}
	}

	/**
	 * Process the next batch of submissions.
	 *
	 * @param array $progress Progress state (passed by reference).
	 * @return void
	 */
	private function process_submissions_phase( array &$progress ): void {
		$all_subs = $this->get_unsynced_submissions();
		$batch    = array_slice( $all_subs, 0, self::SUBMISSION_BATCH_SIZE );

		if ( empty( $batch ) ) {
			$progress['phase'] = self::PHASE_COMPLETE;
			update_option( self::PROGRESS_OPTION, $progress, false );
			return;
		}

		$result = $this->migrate_submission_batch( $batch );

		$progress['submissions_completed']  += $result['accepted'] + $result['duplicates'] + count( $result['errors'] );
		$progress['submissions_accepted']   += $result['accepted'];
		$progress['submissions_duplicates'] += $result['duplicates'];

		foreach ( $result['errors'] as $err ) {
			$progress['errors'][] = [
				'phase'  => 'submissions',
				'detail' => $err,
			];
		}

		$remaining = $this->get_unsynced_submissions();
		if ( empty( $remaining ) || $progress['submissions_completed'] >= $progress['submissions_total'] ) {
			$progress['phase'] = self::PHASE_COMPLETE;
			update_option( self::PROGRESS_OPTION, $progress, false );
			return;
		}

		update_option( self::PROGRESS_OPTION, $progress, false );
		$this->schedule_next();
	}

	/**
	 * Schedule the next cron tick for continued processing.
	 *
	 * @return void
	 */
	private function schedule_next(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		}
	}

	/**
	 * Get the configured sample batch size.
	 *
	 * @return int Batch size.
	 */
	public function get_sample_batch_size(): int {
		return self::SAMPLE_BATCH_SIZE;
	}

	/**
	 * Get the configured submission batch size.
	 *
	 * @return int Batch size.
	 */
	public function get_submission_batch_size(): int {
		return self::SUBMISSION_BATCH_SIZE;
	}
}
