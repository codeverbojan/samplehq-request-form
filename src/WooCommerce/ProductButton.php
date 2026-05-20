<?php
/**
 * "Request a Sample" button on WooCommerce product pages.
 *
 * @package SampleHQForm\WooCommerce
 */

declare( strict_types=1 );

namespace SampleHQForm\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a "Request a Sample" button on WooCommerce pages.
 *
 * Single product page:
 *   Hook: woocommerce_after_add_to_cart_button (INSIDE form.cart).
 *   Renders right after the Add to Cart button as an inline sibling,
 *   using WooCommerce's native button classes (button, alt, wp-element-button)
 *   so it inherits theme styling and participates in the form layout grid.
 *
 * Shop/archive loop:
 *   Hook: woocommerce_after_shop_loop_item (priority 15, after Add to Cart).
 *   Renders a small text badge inside the product card linking to the
 *   product page with #request-sample to auto-open the modal.
 */
class ProductButton {

	/**
	 * Product types that support sample requests.
	 *
	 * External products link to other sites (not fulfillable).
	 * Virtual and downloadable products have nothing physical to sample.
	 *
	 * @var string[]
	 */
	private const SAMPLEABLE_TYPES = [ 'simple', 'variable' ];

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'render_single' ] );
		add_action( 'woocommerce_after_shop_loop_item', [ $this, 'render_loop_badge' ], 15 );
	}

	/**
	 * Render the button on a single product page.
	 *
	 * Hooked to woocommerce_after_add_to_cart_button -- renders INSIDE
	 * form.cart as an inline sibling to the Add to Cart button.
	 *
	 * @return void
	 */
	public function render_single(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( ! $this->is_sample_eligible( $product ) ) {
			return;
		}

		$button_text = $this->get_button_text();

		printf(
			'<button type="button" class="single_add_to_cart_button button alt wp-element-button shqf-woo-request-btn" data-product-id="%s" data-product-name="%s" data-product-sku="%s">%s</button>',
			esc_attr( (string) $product->get_id() ),
			esc_attr( $product->get_name() ),
			esc_attr( $product->get_sku() ),
			esc_html( $button_text )
		);
	}

	/**
	 * Render a "Free sample available" badge inside the product card.
	 *
	 * Hooked to woocommerce_after_shop_loop_item at priority 15
	 * (after the Add to Cart button at priority 10).
	 *
	 * @return void
	 */
	public function render_loop_badge(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( ! get_option( 'shqf_woo_show_loop_badge', '1' ) ) {
			return;
		}

		if ( ! $this->is_sample_eligible( $product ) ) {
			return;
		}

		$badge_text = (string) get_option( 'shqf_woo_badge_text', __( 'Free sample available', 'samplehq-request-form' ) );
		if ( '' === $badge_text ) {
			$badge_text = __( 'Free sample available', 'samplehq-request-form' );
		}

		printf(
			'<a href="%s#request-sample" class="shqf-woo-sample-badge" data-product-id="%s" aria-label="%s">%s</a>',
			esc_url( get_permalink( $product->get_id() ) ),
			esc_attr( (string) $product->get_id() ),
			/* translators: %s: product name */
			esc_attr( sprintf( __( 'Request free sample of %s', 'samplehq-request-form' ), $product->get_name() ) ),
			esc_html( $badge_text )
		);
	}

	/**
	 * Check if a product is eligible for sample requests.
	 *
	 * Checks product type (only simple and variable), stock status,
	 * publish status, and the admin-configured filter (all/tagged/category).
	 *
	 * @param \WC_Product $product The WC product to check.
	 * @return bool
	 */
	public function is_sample_eligible( \WC_Product $product ): bool {
		// Product type check: skip external, grouped, virtual, downloadable.
		if ( ! in_array( $product->get_type(), self::SAMPLEABLE_TYPES, true ) ) {
			return false;
		}

		if ( $product->is_virtual() ) {
			return false;
		}

		if ( ! $product->is_in_stock() ) {
			return false;
		}

		if ( 'publish' !== $product->get_status() ) {
			return false;
		}

		$filter = (string) get_option( 'shqf_woo_product_filter', 'all' );

		if ( 'tagged' === $filter ) {
			return $this->product_has_tag( $product );
		}

		if ( 'category' === $filter ) {
			return $this->product_in_categories( $product );
		}

		return true;
	}

	/**
	 * Get the button text from settings.
	 *
	 * @return string
	 */
	private function get_button_text(): string {
		return (string) get_option(
			'shqf_woo_button_text',
			__( 'Request a Sample', 'samplehq-request-form' )
		);
	}

	/**
	 * Check if a product has the configured sample tag.
	 *
	 * @param \WC_Product $product The WC product.
	 * @return bool
	 */
	private function product_has_tag( \WC_Product $product ): bool {
		$tag_slug = (string) get_option( 'shqf_woo_sample_tag', 'sample-available' );
		if ( '' === $tag_slug ) {
			return false;
		}

		$terms = get_the_terms( $product->get_id(), 'product_tag' );
		if ( ! is_array( $terms ) ) {
			return false;
		}

		foreach ( $terms as $term ) {
			if ( $term->slug === $tag_slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a product belongs to any of the configured sample categories.
	 *
	 * @param \WC_Product $product The WC product.
	 * @return bool
	 */
	private function product_in_categories( \WC_Product $product ): bool {
		$allowed = get_option( 'shqf_woo_sample_categories', [] );
		if ( ! is_array( $allowed ) || empty( $allowed ) ) {
			return false;
		}

		$allowed_ids  = array_map( 'intval', $allowed );
		$product_cats = $product->get_category_ids();

		return ! empty( array_intersect( $product_cats, $allowed_ids ) );
	}
}
