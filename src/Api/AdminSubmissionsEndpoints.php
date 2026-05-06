<?php
/**
 * Admin REST API endpoints for submissions.
 *
 * @package SampleHQForm\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\SubmissionMetaTable;
use SampleHQForm\Database\SubmissionsTable;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Submission CRUD + action endpoints.
 */
class AdminSubmissionsEndpoints extends AdminEndpointBase {

	/**
	 * Submissions repository.
	 *
	 * @var SubmissionsTable
	 */
	private SubmissionsTable $submissions;

	/**
	 * Submission metadata repository.
	 *
	 * @var SubmissionMetaTable
	 */
	private SubmissionMetaTable $submission_meta;

	/**
	 * Constructor.
	 *
	 * @param SubmissionsTable    $submissions     Submissions repo.
	 * @param SubmissionMetaTable $submission_meta Submission meta repo.
	 */
	public function __construct( SubmissionsTable $submissions, SubmissionMetaTable $submission_meta ) {
		$this->submissions     = $submissions;
		$this->submission_meta = $submission_meta;
	}

	/**
	 * Register submission routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$perm   = [ $this, 'check_admin_permission' ];
		$id_arg = self::id_arg_schema();

		register_rest_route(
			self::NAMESPACE,
			'/admin/submissions',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_submissions' ],
				'permission_callback' => $perm,
				'args'                => self::list_args_schema(
					[
						'form_id'    => [
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
						'is_starred' => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					]
				),
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/submissions/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_submission' ],
				'permission_callback' => $perm,
				'args'                => [ 'id' => $id_arg ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/submissions/(?P<id>\d+)/star',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'toggle_star' ],
				'permission_callback' => $perm,
				'args'                => [ 'id' => $id_arg ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/submissions/(?P<id>\d+)/read',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'toggle_read' ],
				'permission_callback' => $perm,
				'args'                => [ 'id' => $id_arg ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/submissions/(?P<id>\d+)/status',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_submission_status' ],
				'permission_callback' => $perm,
				'args'                => [
					'id'     => $id_arg,
					'status' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $value ): bool {
							$allowed = [ 'new', 'in_progress', 'completed', 'spam', 'trashed' ];
							return in_array( (string) $value, $allowed, true );
						},
					],
				],
			]
		);
	}

	/**
	 * List submissions.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Submissions list.
	 */
	public function list_submissions( WP_REST_Request $request ): WP_REST_Response {
		$filters = $this->filter_nulls(
			[
				'form_id'    => $request->get_param( 'form_id' ),
				'status'     => $request->get_param( 'status' ),
				'search'     => $request->get_param( 'search' ),
				'is_starred' => $request->get_param( 'is_starred' ),
				'orderby'    => $request->get_param( 'orderby' ),
				'order'      => $request->get_param( 'order' ),
				'limit'      => $request->get_param( 'per_page' ),
				'offset'     => $request->get_param( 'offset' ),
			]
		);

		$items = $this->submissions->list_all( $filters );
		$total = $this->submissions->count(
			$this->filter_nulls(
				[
					'form_id'    => $request->get_param( 'form_id' ),
					'status'     => $request->get_param( 'status' ),
					'search'     => $request->get_param( 'search' ),
					'is_starred' => $request->get_param( 'is_starred' ),
				]
			)
		);

		$response = new WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', (string) $total );
		return $response;
	}

	/**
	 * Get a single submission with its meta.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Submission data or 404.
	 */
	public function get_submission( WP_REST_Request $request ): WP_REST_Response {
		$id         = (int) $request->get_param( 'id' );
		$submission = $this->submissions->get( $id );

		if ( null === $submission ) {
			return new WP_REST_Response( [ 'message' => __( 'Submission not found.', 'samplehq-request-form' ) ], 404 );
		}

		$submission['meta'] = $this->submission_meta->get_all( $id );
		return new WP_REST_Response( $submission, 200 );
	}

	/**
	 * Toggle starred flag.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Updated star state.
	 */
	public function toggle_star( WP_REST_Request $request ): WP_REST_Response {
		$id      = (int) $request->get_param( 'id' );
		$starred = (bool) $request->get_param( 'starred' );

		$this->submissions->set_starred( $id, $starred );
		return new WP_REST_Response( [ 'starred' => $starred ], 200 );
	}

	/**
	 * Toggle read flag.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Updated read state.
	 */
	public function toggle_read( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$read = (bool) $request->get_param( 'read' );

		$this->submissions->set_read( $id, $read );
		return new WP_REST_Response( [ 'read' => $read ], 200 );
	}

	/**
	 * Update submission status.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response Updated status.
	 */
	public function update_submission_status( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$status = (string) $request->get_param( 'status' );

		$this->submissions->update_status( $id, $status );
		return new WP_REST_Response( [ 'status' => $status ], 200 );
	}
}
