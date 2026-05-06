<?php
/**
 * Admin REST API endpoints for sample categories.
 *
 * @package SampleHQForm\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\SampleCategoriesTable;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Category list + create endpoints.
 */
class AdminCategoriesEndpoints extends AdminEndpointBase {

	/**
	 * @var SampleCategoriesTable
	 */
	private SampleCategoriesTable $categories;

	/**
	 * @param SampleCategoriesTable $categories Categories repo.
	 */
	public function __construct( SampleCategoriesTable $categories ) {
		$this->categories = $categories;
	}

	/**
	 * Register category routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$perm = [ $this, 'check_admin_permission' ];

		register_rest_route(
			self::NAMESPACE,
			'/categories',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_categories' ],
					'permission_callback' => $perm,
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_category' ],
					'permission_callback' => $perm,
					'args'                => [
						'name'      => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'parent_id' => [
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);
	}

	/**
	 * List all categories.
	 *
	 * @return WP_REST_Response Categories list.
	 */
	public function list_categories(): WP_REST_Response {
		$categories = $this->categories->list_all();

		return new WP_REST_Response( $categories, 200 );
	}

	/**
	 * Create a new category.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Created category data.
	 */
	public function create_category( WP_REST_Request $request ): WP_REST_Response {
		$name      = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
		$parent_id = absint( $request->get_param( 'parent_id' ) ?? 0 );

		if ( empty( $name ) ) {
			return new WP_REST_Response( [ 'message' => 'Category name is required.' ], 400 );
		}

		try {
			$id = $this->categories->create(
				[
					'name'      => $name,
					'parent_id' => $parent_id,
				]
			);

			$category = $this->categories->get( $id );

			return new WP_REST_Response( $category, 201 );
		} catch ( \Exception $e ) {
			return new WP_REST_Response( [ 'message' => $e->getMessage() ], 500 );
		}
	}
}
