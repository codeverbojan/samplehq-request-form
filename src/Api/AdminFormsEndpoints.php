<?php
/**
 * Admin REST API endpoints for forms.
 *
 * @package SampleHQForm\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\FormsTable;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Form CRUD endpoints.
 */
class AdminFormsEndpoints extends AdminEndpointBase {

	/**
	 * @var FormsTable
	 */
	private FormsTable $forms;

	/**
	 * @param FormsTable $forms Forms repo.
	 */
	public function __construct( FormsTable $forms ) {
		$this->forms = $forms;
	}

	/**
	 * Register form CRUD routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$perm   = [ $this, 'check_admin_permission' ];
		$id_arg = self::id_arg_schema();

		register_rest_route(
			self::NAMESPACE,
			'/forms',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_forms' ],
					'permission_callback' => $perm,
					'args'                => self::list_args_schema(),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_form' ],
					'permission_callback' => $perm,
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/forms/(?P<id>\d+)',
			[
				'args' => [ 'id' => $id_arg ],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_form' ],
					'permission_callback' => $perm,
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'update_form' ],
					'permission_callback' => $perm,
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_form' ],
					'permission_callback' => $perm,
				],
			]
		);
	}

	/**
	 * List forms.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Forms list.
	 */
	public function list_forms( WP_REST_Request $request ): WP_REST_Response {
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

		$forms = $this->forms->list_all( $filters );
		$total = $this->forms->count(
			$this->filter_nulls(
				[
					'status' => $request->get_param( 'status' ),
					'search' => $request->get_param( 'search' ),
				]
			)
		);

		$response = new WP_REST_Response( $forms, 200 );
		$response->header( 'X-WP-Total', (string) $total );
		return $response;
	}

	/**
	 * Get a single form.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Form data or 404.
	 */
	public function get_form( WP_REST_Request $request ): WP_REST_Response {
		$form = $this->forms->get( (int) $request->get_param( 'id' ) );

		if ( null === $form ) {
			return new WP_REST_Response( [ 'message' => __( 'Form not found.', 'samplehq-request-form' ) ], 404 );
		}

		return new WP_REST_Response( $form, 200 );
	}

	/**
	 * Create a form.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response New form ID or error.
	 */
	public function create_form( WP_REST_Request $request ): WP_REST_Response {
		try {
			$id = $this->forms->create(
				[
					'title'        => $request->get_param( 'title' ),
					'slug'         => $request->get_param( 'slug' ),
					'status'       => $request->get_param( 'status' ),
					'config'       => $request->get_param( 'config' ),
					'email_config' => $request->get_param( 'email_config' ),
					'spam_config'  => $request->get_param( 'spam_config' ),
					'settings'     => $request->get_param( 'settings' ),
					'created_by'   => get_current_user_id(),
				]
			);

			return new WP_REST_Response( [ 'id' => $id ], 201 );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_REST_Response( [ 'message' => $e->getMessage() ], 400 );
		} catch ( \RuntimeException $e ) {
			return new WP_REST_Response( [ 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Update a form.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Success or 404.
	 */
	public function update_form( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		if ( null === $this->forms->get( $id ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Form not found.', 'samplehq-request-form' ) ], 404 );
		}

		$data = $this->filter_nulls(
			[
				'title'        => $request->get_param( 'title' ),
				'slug'         => $request->get_param( 'slug' ),
				'status'       => $request->get_param( 'status' ),
				'config'       => $request->get_param( 'config' ),
				'email_config' => $request->get_param( 'email_config' ),
				'spam_config'  => $request->get_param( 'spam_config' ),
				'settings'     => $request->get_param( 'settings' ),
			]
		);

		$this->forms->update( $id, $data );
		return new WP_REST_Response( [ 'updated' => true ], 200 );
	}

	/**
	 * Delete a form.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Success or 404.
	 */
	public function delete_form( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		if ( null === $this->forms->get( $id ) ) {
			return new WP_REST_Response( [ 'message' => __( 'Form not found.', 'samplehq-request-form' ) ], 404 );
		}

		$this->forms->delete( $id );
		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}
}
