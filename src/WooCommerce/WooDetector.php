<?php
/**
 * WooCommerce detection and compatibility declarations.
 *
 * @package SampleHQForm\WooCommerce
 */

declare( strict_types=1 );

namespace SampleHQForm\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects whether WooCommerce is active and declares extension compatibility
 * with WooCommerce features (HPOS, cart/checkout blocks).
 */
class WooDetector {

	/**
	 * Check if WooCommerce is active on this WordPress install.
	 *
	 * Uses class_exists() which is cheap and rechecked on every load.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Declare compatibility with WooCommerce features.
	 *
	 * Must be called inside (or hooked to) `before_woocommerce_init`.
	 * Declares compatibility with HPOS (custom_order_tables) and
	 * block-based cart/checkout (cart_checkout_blocks).
	 *
	 * @return void
	 */
	public static function declare_compatibility(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			SHQF_FILE,
			true
		);

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			SHQF_FILE,
			true
		);
	}
}
