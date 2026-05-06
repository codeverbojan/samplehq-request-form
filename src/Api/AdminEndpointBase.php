<?php
/**
 * Base class for admin REST API endpoint groups.
 *
 * @package SampleHQForm\Api
 */

declare( strict_types=1 );

namespace SampleHQForm\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;
use WP_REST_Response;

/**
 * Shared infrastructure for admin endpoint classes.
 *
 * Provides the REST namespace, permission callback, arg schemas,
 * and the register() lifecycle method.
 */
abstract class AdminEndpointBase {

	public const NAMESPACE = 'samplehq-form/v1';

	/**
	 * Permission callback: require manage_options.
	 *
	 * @return bool True if the user can manage options.
	 */
	public function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register REST routes via rest_api_init.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the endpoint group's routes.
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Filter out null values from an array.
	 *
	 * @param array<string, mixed> $data Input data.
	 * @return array<string, mixed> Data without null values.
	 */
	protected function filter_nulls( array $data ): array {
		$result = [];
		foreach ( $data as $key => $value ) {
			if ( null !== $value ) {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Standard schema for an ID URL parameter.
	 *
	 * @return array<string, mixed> Arg definition.
	 */
	protected static function id_arg_schema(): array {
		return [
			'required'          => true,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => static function ( $value ): bool {
				return is_numeric( $value ) && (int) $value > 0;
			},
		];
	}

	/**
	 * Standard schema for list endpoint query parameters.
	 *
	 * @param array<string, array<string, mixed>> $extra Additional args to merge.
	 * @return array<string, array<string, mixed>> Args definition.
	 */
	protected static function list_args_schema( array $extra = [] ): array {
		return array_merge(
			[
				'status'   => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'search'   => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'orderby'  => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => static function ( $value ): bool {
						$allowed = [ 'id', 'name', 'title', 'status', 'created_at', 'updated_at', 'sort_order', 'submissions_count', 'email' ];
						return in_array( (string) $value, $allowed, true );
					},
				],
				'order'    => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => static function ( $value ): bool {
						return in_array( strtoupper( (string) $value ), [ 'ASC', 'DESC' ], true );
					},
				],
				'per_page' => [
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				],
				'offset'   => [
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				],
			],
			$extra
		);
	}
}
