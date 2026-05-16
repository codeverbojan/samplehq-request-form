<?php
/**
 * REST API endpoints for data migration to the platform.
 *
 * @package SampleHQForm\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Connection\ConnectionManager;
use SampleHQForm\Connection\MigrationEngine;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes migration preview, start, progress, and cancel endpoints.
 */
class MigrationEndpoints extends AdminEndpointBase {

	/**
	 * Migration engine instance.
	 *
	 * @var MigrationEngine
	 */
	private MigrationEngine $engine;

	/**
	 * Connection manager instance.
	 *
	 * @var ConnectionManager
	 */
	private ConnectionManager $connection;

	/**
	 * Constructor.
	 *
	 * @param MigrationEngine   $engine     Migration engine.
	 * @param ConnectionManager $connection Connection manager.
	 */
	public function __construct( MigrationEngine $engine, ConnectionManager $connection ) {
		$this->engine     = $engine;
		$this->connection = $connection;
	}

	/**
	 * Register REST routes for migration management.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$conn_perm  = [ $this, 'check_connected_admin' ];
		$admin_perm = [ $this, 'check_admin_permission' ];

		register_rest_route(
			self::NAMESPACE,
			'/migration/preview',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => $conn_perm,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/migration/start',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'start' ],
				'permission_callback' => $conn_perm,
				'args'                => [
					'include_submissions' => [
						'type'    => 'boolean',
						'default' => false,
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/migration/progress',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'progress' ],
				'permission_callback' => $admin_perm,
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/migration/cancel',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cancel' ],
				'permission_callback' => $admin_perm,
			]
		);
	}

	/**
	 * Permission check: admin + connected.
	 *
	 * @return bool True if allowed.
	 */
	public function check_connected_admin(): bool {
		return current_user_can( 'manage_options' ) && $this->connection->is_connected();
	}

	/**
	 * Get migration preview (counts and estimates).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Preview data.
	 */
	public function preview( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->engine->get_preview();

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Start a new migration.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Progress data or 409 if already running.
	 */
	public function start( WP_REST_Request $request ): WP_REST_Response {
		$existing = $this->engine->get_progress();
		if ( null !== $existing && ! in_array( $existing['phase'] ?? '', [ 'complete', 'cancelled', 'error', 'idle' ], true ) ) {
			return new WP_REST_Response(
				[
					'code'    => 'migration_in_progress',
					'message' => 'A migration is already running.',
				],
				409
			);
		}

		$include_submissions = (bool) $request->get_param( 'include_submissions' );

		$progress = $this->engine->start_migration( $include_submissions );

		return new WP_REST_Response( $progress, 200 );
	}

	/**
	 * Get current migration progress.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Progress data.
	 */
	public function progress( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->engine->get_progress();

		if ( null === $data ) {
			return new WP_REST_Response( [ 'phase' => 'idle' ], 200 );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Cancel the running migration.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Cancellation confirmation.
	 */
	public function cancel( WP_REST_Request $request ): WP_REST_Response {
		$this->engine->cancel_migration();

		return new WP_REST_Response( [ 'status' => 'cancelled' ], 200 );
	}
}
