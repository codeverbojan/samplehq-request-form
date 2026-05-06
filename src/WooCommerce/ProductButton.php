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
 *   Hook: woocommerce_after_add_to_cart_form (OUTSIDE form.cart).
 *   This fires after the </form> tag in ALL product type templates
 *   (simple, variable, grouped, external) and in the WC add-to-cart-form
 *   block. Placing the button outside the form avoids the CSS grid layout
 *   inside form.cart that block themes use (grid-column: 1/-1 on all
 *   non-quantity children). The button renders in the normal document
 *   flow below the add-to-cart form, styled as a secondary/outline CTA.
 *
 * Shop/archive loop:
 *   Filter: woocommerce_loop_add_to_cart_link (3 params).
 *   Appends a small text badge after the Add to Cart link HTML.
 *   Works in both classic templates and the WC product-button block
 *   (which applies this filter on its output).
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
		// Single product: after the add-to-cart form closes.
		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'render_single' ] );

		// Shop loop: append badge to the add-to-cart link via filter.
		add_filter( 'woocommerce_loop_add_to_cart_link', [ $this, 'filter_loop_link' ], 10, 3 );
	}

	/**
	 * Render the button on a single product page.
	 *
	 * Hooked to woocommerce_after_add_to_cart_form -- renders OUTSIDE
	 * the form.cart element so it is not affected by the WC block theme
	 * CSS grid layout.
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
			'<div class="shqf-woo-request-wrap"><button type="button" class="shqf-woo-request-btn" data-product-id="%s" data-product-name="%s" data-product-sku="%s">%s</button></div>',
			esc_attr( (string) $product->get_id() ),
			esc_attr( $product->get_name() ),
			esc_attr( $product->get_sku() ),
			esc_html( $button_text )
		);
	}

	/**
	 * Append a "Free sample available" badge after the Add to Cart link in shop loops.
	 *
	 * @param string      $link    The existing Add to Cart link HTML.
	 * @param \WC_Product $product The product.
	 * @param array       $args    Optional button args (may not be passed by all callers).
	 * @return string Modified HTML with sample badge appended.
	 */
	public function filter_loop_link( string $link, $product, $args = [] ): string {
		if ( ! $product instanceof \WC_Product ) {
			return $link;
		}

		// Allow hiding the shop loop badge entirely.
		if ( ! get_option( 'shqf_woo_show_loop_badge', '1' ) ) {
			return $link;
		}

		if ( ! $this->is_sample_eligible( $product ) ) {
			return $link;
		}

		$badge_text = (string) get_option( 'shqf_woo_badge_text', __( 'Free sample available', 'samplehq-request-form' ) );
		if ( '' === $badge_text ) {
			$badge_text = __( 'Free sample available', 'samplehq-request-form' );
		}

		$badge = sprintf(
			'<a href="%s#request-sample" class="shqf-woo-sample-badge" data-product-id="%s" aria-label="%s">%s</a>',
			esc_url( get_permalink( $product->get_id() ) ),
			esc_attr( (string) $product->get_id() ),
			/* translators: %s: product name */
			esc_attr( sprintf( __( 'Request free sample of %s', 'samplehq-request-form' ), $product->get_name() ) ),
			esc_html( $badge_text )
		);

		return $link . $badge;
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
		if ( ! is_array( $terms ) || is_wp_error( $terms ) ) {
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
