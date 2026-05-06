<?php
/**
 * Admin REST API endpoints for samples.
 *
 * @package SampleHQForm\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SampleImagesTable;
use SampleHQForm\Database\SamplesTable;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Sample CRUD endpoints.
 */
class AdminSamplesEndpoints extends AdminEndpointBase {

	/**
	 * Samples repository.
	 *
	 * @var SamplesTable
	 */
	private SamplesTable $samples;

	/**
	 * Sample-to-category mapping repository.
	 *
	 * @var SampleCategoryMapTable
	 */
	private SampleCategoryMapTable $category_map;

	/**
	 * Sample images repository.
	 *
	 * @var SampleImagesTable
	 */
	private SampleImagesTable $images;

	/**
	 * Constructor.
	 *
	 * @param SamplesTable           $samples      Samples repo.
	 * @param SampleCategoriesTable  $_categories  Categories repo (unused — kept for DI signature stability).
	 * @param SampleCategoryMapTable $category_map Category map repo.
	 * @param SampleImagesTable      $images       Images repo.
	 */
	public function __construct( // @phpstan-ignore constructor.unusedParameter
		SamplesTable $samples,
		SampleCategoriesTable $_categories, // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed -- DI container passes all table deps; removing breaks instantiation.
		SampleCategoryMapTable $category_map,
		SampleImagesTable $images
	) {
		$this->samples      = $samples;
		$this->category_map = $category_map;
		$this->images       = $images;
	}

	/**
	 * Register sample CRUD routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$perm   = [ $this, 'check_admin_permission' ];
		$id_arg = self::id_arg_schema();

		register_rest_route(
			self::NAMESPACE,
			'/samples',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_samples' ],
					'permission_callback' => $perm,
					'args'                => self::list_args_schema(),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_sample' ],
					'permission_callback' => $perm,
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/samples/(?P<id>\d+)',
			[
				'args' => [ 'id' => $id_arg ],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_sample' ],
					'permission_callback' => $perm,
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update_sample' ],
					'permission_callback' => $perm,
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_sample' ],
					'permission_callback' => $perm,
				],
			]
		);
	}

	/**
	 * List samples.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Samples list.
	 */
	public function list_samples( WP_REST_Request $request ): WP_REST_Response {
		$filters = $this->filter_nulls(
			[
				'status'  => $request->get_param( 'status' ),
				'search'  => $request->get_param( 'search' ),
				'orderby' => $request->get_param( 'orderby' ),
				'order'   => $request->get_param( 'order' ),
				'limit'   => $request->get_param( 'per_page' ),
				'offset'  => $request->get_param( 'offset' ),
			]
		);

		$samples = $this->samples->list_all( $filters );
		$total   = $this->samples->count(
			$this->filter_nulls(
				[
					'status' => $request->get_param( 'status' ),
					'search' => $request->get_param( 'search' ),
				]
			)
		);

		$sample_ids   = array_map( static fn( $s ) => (int) $s['id'], $samples );
		$category_map = $this->category_map->get_categories_for_samples( $sample_ids );
		$image_map    = $this->images->get_featured_for_samples( $sample_ids );

		foreach ( $samples as &$sample ) {
			$sid                      = (int) $sample['id'];
			$sample['categories']     = $category_map[ $sid ] ?? [];
			$sample['featured_image'] = $image_map[ $sid ] ?? null;
		}
		unset( $sample );

		$response = new WP_REST_Response( $samples, 200 );
		$response->header( 'X-WP-Total', (string) $total );
		return $response;
	}

	/**
	 * Get a single sample.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Sample data or 404.
	 */
	public function get_sample( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$sample = $this->samples->get( $id );

		if ( null === $sample ) {
			return new WP_REST_Response( [ 'message' => __( 'Sample not found.', 'samplehq-request-form' ) ], 404 );
		}

		$sample['categories'] = $this->category_map->get_categories_for_sample( $id );
		$sample['images']     = $this->images->get_for_sample( $id );
		return new WP_REST_Response( $sample, 200 );
	}

	/**
	 * Create a sample.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response New sample ID or error.
	 */
	public function create_sample( WP_REST_Request $request ): WP_REST_Response {
		try {
			$id = $this->samples->create(
				[
					'name'         => $request->get_param( 'name' ),
					'sku'          => $request->get_param( 'sku' ),
					'description'  => $request->get_param( 'description' ),
					'max_quantity' => $request->get_param( 'max_quantity' ),
					'status'       => $request->get_param( 'status' ),
					'sort_order'   => $request->get_param( 'sort_order' ),
				]
			);

			$categories = $request->get_param( 'categories' );
			if ( is_array( $categories ) ) {
				$this->category_map->sync( $id, $categories );
			}

			return new WP_REST_Response( [ 'id' => $id ], 201 );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_REST_Response( [ 'message' => $e->getMessage() ], 400 );
		} catch ( \RuntimeException $e ) {
			return new WP_REST_Response( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Update a sample.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Success or 404.
	 */
	public function update_sample( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		if ( null === $this->samples->get( $id ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Sample not found.', 'samplehq-request-form' ) ], 404 );
		}

		$data = $this->filter_nulls(
			[
				'name'         => $request->get_param( 'name' ),
				'sku'          => $request->get_param( 'sku' ),
				'description'  => $request->get_param( 'description' ),
				'max_quantity' => $request->get_param( 'max_quantity' ),
				'status'       => $request->get_param( 'status' ),
				'sort_order'   => $request->get_param( 'sort_order' ),
			]
		);

		$this->samples->update( $id, $data );

		$categories = $request->get_param( 'categories' );
		if ( is_array( $categories ) ) {
			$this->category_map->sync( $id, $categories );
		}

		return new WP_REST_Response( [ 'updated' => true ], 200 );
	}

	/**
	 * Delete a sample with cascade cleanup.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Success or 404.
	 */
	public function delete_sample( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		if ( null === $this->samples->get( $id ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Sample not found.', 'samplehq-request-form' ) ], 404 );
		}

		$this->images->remove_all_for_sample( $id );
		$this->category_map->remove_all_for_sample( $id );
		$this->samples->delete( $id );

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}
}
