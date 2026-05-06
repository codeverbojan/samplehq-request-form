<?php
/**
 * E2E test helper: admin-ajax endpoint for getting/setting WP options.
 *
 * Drop this file into mu-plugins/ on the dev site to enable E2E tests
 * to manipulate plugin settings programmatically.
 *
 * SECURITY: Only works for logged-in admins. Must be removed after testing.
 *
 * Install:
 *   cp tests/e2e/woocommerce/e2e-option-helper.php ../../mu-plugins/shqf-e2e-helper.php
 *
 * @package SampleHQForm\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_shqf_e2e_set_option', static function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	$key       = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
	$raw_value = wp_unslash( $_GET['value'] ?? '' );

	if ( '' === $key ) {
		wp_send_json_error( 'Missing key' );
	}

	// Only allow shqf_ prefixed options.
	if ( strpos( $key, 'shqf_' ) !== 0 ) {
		wp_send_json_error( 'Only shqf_ options allowed' );
	}

	// If the value looks like JSON (starts with [ or {), decode it.
	$value = $raw_value;
	if ( is_string( $raw_value ) && ( str_starts_with( $raw_value, '[' ) || str_starts_with( $raw_value, '{' ) ) ) {
		$decoded = json_decode( $raw_value, true );
		if ( null !== $decoded ) {
			$value = $decoded;
		}
	} else {
		$value = sanitize_text_field( $raw_value );
	}

	update_option( $key, $value );

	// Clear WC product cache when filter settings change.
	if ( class_exists( 'SampleHQForm\Fields\ProductSource\WooCommerceSource' ) ) {
		\SampleHQForm\Fields\ProductSource\WooCommerceSource::clear_cache();
	}

	wp_send_json_success( [ 'key' => $key, 'value' => $value ] );
} );

add_action( 'wp_ajax_shqf_e2e_get_option', static function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	$key = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );

	if ( '' === $key || strpos( $key, 'shqf_' ) !== 0 ) {
		wp_send_json_error( 'Invalid key' );
	}

	$value = get_option( $key, '' );
	wp_send_json_success( [ 'data' => is_array( $value ) ? wp_json_encode( $value ) : (string) $value ] );
} );
