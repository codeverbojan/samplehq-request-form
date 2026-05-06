<?php
/**
 * Transient-based flash notice system.
 *
 * Stores admin notices in a transient so they survive redirects.
 *
 * @package SampleHQForm\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flash notice helper for admin pages.
 */
class AdminNotice {

	/**
	 * Transient key for the current user's notices.
	 *
	 * @return string
	 */
	private static function transient_key(): string {
		return 'shqf_admin_notices_' . get_current_user_id();
	}

	/**
	 * Add a success notice.
	 *
	 * @param string $message Notice message.
	 * @return void
	 */
	public static function success( string $message ): void {
		self::add( 'success', $message );
	}

	/**
	 * Add an error notice.
	 *
	 * @param string $message Notice message.
	 * @return void
	 */
	public static function error( string $message ): void {
		self::add( 'error', $message );
	}

	/**
	 * Add a warning notice.
	 *
	 * @param string $message Notice message.
	 * @return void
	 */
	public static function warning( string $message ): void {
		self::add( 'warning', $message );
	}

	/**
	 * Add a notice to the transient queue.
	 *
	 * @param string $type    Notice type: success, error, warning, info.
	 * @param string $message Notice message.
	 * @return void
	 */
	private static function add( string $type, string $message ): void {
		$notices   = get_transient( self::transient_key() );
		$notices   = is_array( $notices ) ? $notices : [];
		$notices[] = [
			'type'    => $type,
			'message' => $message,
		];
		set_transient( self::transient_key(), $notices, 60 );
	}

	/**
	 * Render and clear all queued notices.
	 *
	 * Call this at the top of any admin page render method.
	 *
	 * @return void
	 */
	public static function render(): void {
		$notices = get_transient( self::transient_key() );

		if ( ! is_array( $notices ) || empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$type = in_array( $notice['type'], [ 'success', 'error', 'warning', 'info' ], true )
				? $notice['type']
				: 'info';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>'
				. esc_html( $notice['message'] ) . '</p></div>';
		}

		delete_transient( self::transient_key() );
	}
}
