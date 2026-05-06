<?php
/**
 * WooCommerce product source -- reads from WC_Product API.
 *
 * Maps WooCommerce products to the same array shape as the plugin's
 * shqf_samples table so SamplePickerField renders identically.
 *
 * @package SampleHQForm\Fields\ProductSource
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields\ProductSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads samples from WooCommerce products via wc_get_products().
 *
 * Respects plugin settings for product filtering (all/tagged/category).
 * Caches results in a transient (5 min TTL) for frontend performance.
 */
class WooCommerceSource implements ProductSourceInterface {

	/**
	 * Cache TTL in seconds.
	 *
	 * @var int
	 */
	private const CACHE_TTL = 300;

	/**
	 * Transient prefix for cache keys.
	 *
	 * @var string
	 */
	public const CACHE_PREFIX = 'shqf_woo_samples_';

	/**
	 * Option name storing the cache version integer.
	 *
	 * @var string
	 */
	public const CACHE_VERSION_OPTION = 'shqf_woo_cache_version';

	/**
	 * Get WooCommerce products mapped to the sample picker format.
	 *
	 * @param array<string, mixed> $filters Optional filters (status, limit, offset ignored -- WC handles differently).
	 * @return array<int, array<string, mixed>> Rows matching shqf_samples column schema.
	 */
	public function get_samples( array $filters = [] ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return [];
		}

		$version   = (int) get_option( self::CACHE_VERSION_OPTION, 0 );
		$cache_key = self::CACHE_PREFIX . $version . '_' . md5( wp_json_encode( $filters ) . $this->get_filter_setting() );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$query_args = [
			'status'       => 'publish',
			'stock_status' => 'instock',
			'type'         => [ 'simple', 'variable' ],
			'limit'        => (int) ( $filters['limit'] ?? 200 ),
			'orderby'      => 'name',
			'order'        => 'ASC',
			'return'       => 'objects',
		];

		// Apply plugin filter settings.
		$filter_mode = $this->get_filter_setting();
		if ( 'tagged' === $filter_mode ) {
			$tag = get_option( 'shqf_woo_sample_tag', 'sample-available' );
			if ( ! empty( $tag ) ) {
				$query_args['tag'] = [ sanitize_title( $tag ) ];
			}
		} elseif ( 'category' === $filter_mode ) {
			$cat_ids = get_option( 'shqf_woo_sample_categories', [] );
			if ( ! empty( $cat_ids ) && is_array( $cat_ids ) ) {
				$query_args['category'] = $this->get_category_slugs_by_ids( $cat_ids );
			}
		}

		$products = wc_get_products( $query_args );

		/**
		 * Filter whether to show product variations as separate sample items.
		 *
		 * By default, only parent products are shown (simple, variable).
		 * When true, child variations of variable products are also included.
		 *
		 * @param bool $show_variations Whether to include variations. Default false.
		 */
		$show_variations = (bool) apply_filters( 'shqf_woo_show_variations', false );

		// Collect all products (including variations) before mapping so we can
		// batch-load categories and images in bulk instead of per-product N+1.
		$all_products = [];
		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			$all_products[] = $product;

			if ( $show_variations && 'variable' === $product->get_type() && method_exists( $product, 'get_children' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation instanceof \WC_Product && $variation->is_in_stock() ) {
						$all_products[] = $variation;
					}
				}
			}
		}

		// Batch-load category names and prime image caches for all products.
		$cat_map       = $this->batch_load_category_names( $all_products );
		$image_id_map  = $this->batch_prime_image_caches( $all_products );
		$max_qty       = (int) get_option( 'shqf_woo_max_quantity', 3 );

		$samples = [];
		foreach ( $all_products as $product ) {
			$samples[] = $this->map_product( $product, $cat_map, $image_id_map, $max_qty );
		}

		set_transient( $cache_key, $samples, self::CACHE_TTL );

		return $samples;
	}

	/**
	 * Get a single WooCommerce product by ID.
	 *
	 * Used for validation/sanitization only -- does not include batch fields
	 * (category_names, image_id) since those are only needed for rendering.
	 *
	 * @param int $id WC product ID.
	 * @return array<string, mixed>|null Mapped product or null.
	 */
	public function get_sample( int $id ): ?array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $id );
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}

		$max_qty = (int) get_option( 'shqf_woo_max_quantity', 3 );

		return [
			'id'           => $product->get_id(),
			'name'         => $product->get_name(),
			'sku'          => $product->get_sku(),
			'description'  => $product->get_short_description() ?: $product->get_description(),
			'max_quantity' => $max_qty,
			'status'       => 'active',
			'sort_order'   => 0,
			'custom_fields' => null,
		];
	}

	/**
	 * Get WooCommerce product categories.
	 *
	 * @return array<int, array{id: int, name: string, slug: string}> Category list.
	 */
	public function get_categories(): array {
		$terms = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'orderby'    => 'name',
		] );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$result = [];
		foreach ( $terms as $term ) {
			$result[] = [
				'id'   => (int) $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			];
		}

		return $result;
	}

	/**
	 * Get the image URL for a WooCommerce product.
	 *
	 * @param int    $sample_id WC product ID.
	 * @param string $size      WordPress image size.
	 * @return string|null Image URL or null.
	 */
	public function get_image_url( int $sample_id, string $size = 'medium' ): ?string {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $sample_id );
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}

		$image_id = (int) $product->get_image_id();
		if ( 0 === $image_id ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $image_id, $size );

		return $url ? (string) $url : null;
	}

	/**
	 * Get category names for a WooCommerce product.
	 *
	 * @param int $sample_id WC product ID.
	 * @return string[] Category names.
	 */
	public function get_sample_category_names( int $sample_id ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return [];
		}

		$product = wc_get_product( $sample_id );
		if ( ! $product instanceof \WC_Product ) {
			return [];
		}

		$cat_ids = $product->get_category_ids();
		$names   = [];
		foreach ( $cat_ids as $cat_id ) {
			$term = get_term( $cat_id, 'product_cat' );
			if ( $term instanceof \WP_Term ) {
				$names[] = $term->name;
			}
		}

		return $names;
	}

	/**
	 * Clear the product cache.
	 *
	 * Increments the cache version so all existing cache keys become stale.
	 * Old transients expire naturally via their TTL. Works with Redis,
	 * Memcached, and DB-based object caches (no raw SQL needed).
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		$version = (int) get_option( self::CACHE_VERSION_OPTION, 0 );
		update_option( self::CACHE_VERSION_OPTION, $version + 1, true );
	}

	/**
	 * Map a WC_Product to the shqf_samples array shape.
	 *
	 * @param \WC_Product          $product      WooCommerce product.
	 * @param array<int, string[]> $cat_map      Pre-loaded category names keyed by product ID.
	 * @param array<int, int>      $image_id_map Pre-loaded image attachment IDs keyed by product ID.
	 * @param int                  $max_qty      Maximum sample quantity per product.
	 * @return array<string, mixed> Row matching shqf_samples schema.
	 */
	private function map_product( \WC_Product $product, array $cat_map, array $image_id_map, int $max_qty ): array {
		$product_id = $product->get_id();

		return [
			'id'             => $product_id,
			'name'           => $product->get_name(),
			'sku'            => $product->get_sku(),
			'description'    => $product->get_short_description() ?: $product->get_description(),
			'max_quantity'   => $max_qty,
			'status'         => 'active',
			'sort_order'     => 0,
			'custom_fields'  => null,
			'category_names' => $cat_map[ $product_id ] ?? [],
			'image_id'       => $image_id_map[ $product_id ] ?? 0,
		];
	}

	/**
	 * Batch-load category names for multiple products in a single query.
	 *
	 * Uses wp_get_object_terms() to fetch all product_cat terms for the given
	 * product IDs in one query, then groups by product ID.
	 *
	 * For variations: WooCommerce stores product_cat on the parent post only,
	 * so wp_get_object_terms() returns nothing for variation IDs. We fall back
	 * to $product->get_category_ids() which WC resolves to the parent's categories.
	 *
	 * @param \WC_Product[] $products Products to load categories for.
	 * @return array<int, string[]> Category names keyed by product ID.
	 */
	private function batch_load_category_names( array $products ): array {
		if ( empty( $products ) ) {
			return [];
		}

		$product_ids = array_filter(
			array_map( static fn( \WC_Product $p ) => $p->get_id(), $products ),
			static fn( int $id ) => $id > 0
		);

		if ( empty( $product_ids ) ) {
			return [];
		}

		// Single query: get all product_cat terms for all product IDs.
		$terms = wp_get_object_terms( array_values( $product_ids ), 'product_cat', [ 'fields' => 'all_with_object_id' ] );

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		$map = [];
		foreach ( $terms as $term ) {
			$map[ (int) $term->object_id ][] = $term->name;
		}

		// Build a term_id => name lookup for fallback resolution.
		$term_names = [];
		foreach ( $terms as $term ) {
			$term_names[ (int) $term->term_id ] = $term->name;
		}

		// For products without categories from the batch query (e.g., variations
		// whose categories are stored on the parent), fall back to get_category_ids()
		// which WC resolves to the parent's categories.
		foreach ( $products as $product ) {
			$pid = $product->get_id();
			if ( ! empty( $map[ $pid ] ) ) {
				continue;
			}

			$cat_ids = $product->get_category_ids();
			if ( empty( $cat_ids ) ) {
				$map[ $pid ] = [];
				continue;
			}

			// Resolve names from the existing term lookup (parent IDs were in the batch).
			$names = [];
			foreach ( $cat_ids as $cat_id ) {
				if ( isset( $term_names[ $cat_id ] ) ) {
					$names[] = $term_names[ $cat_id ];
				} else {
					// Term wasn't in the batch result -- fetch individually (rare).
					$term = get_term( $cat_id, 'product_cat' );
					if ( $term instanceof \WP_Term ) {
						$names[]                    = $term->name;
						$term_names[ $cat_id ] = $term->name;
					}
				}
			}
			$map[ $pid ] = $names;
		}

		// Ensure every product ID has an entry.
		foreach ( $product_ids as $id ) {
			if ( ! isset( $map[ $id ] ) ) {
				$map[ $id ] = [];
			}
		}

		return $map;
	}

	/**
	 * Prime the image attachment caches and return image IDs per product.
	 *
	 * Collects all image attachment IDs and primes their post + meta caches
	 * in bulk (2 queries total). Callers can then resolve URLs at any size
	 * via wp_get_attachment_image_url() with zero additional queries.
	 *
	 * @param \WC_Product[] $products Products to prime image caches for.
	 * @return array<int, int> Image attachment IDs keyed by product ID (0 if no image).
	 */
	private function batch_prime_image_caches( array $products ): array {
		if ( empty( $products ) ) {
			return [];
		}

		$id_to_image = [];
		$image_ids   = [];
		foreach ( $products as $product ) {
			$pid      = $product->get_id();
			$image_id = (int) $product->get_image_id();
			$id_to_image[ $pid ] = $image_id;
			if ( $image_id > 0 ) {
				$image_ids[] = $image_id;
			}
		}

		// Prime the attachment metadata cache for all image IDs in 2 queries.
		if ( ! empty( $image_ids ) ) {
			_prime_post_caches( array_unique( $image_ids ), false, true );
		}

		return $id_to_image;
	}

	/**
	 * Get the product filter setting.
	 *
	 * @return string 'all', 'tagged', or 'category'.
	 */
	private function get_filter_setting(): string {
		return (string) get_option( 'shqf_woo_product_filter', 'all' );
	}

	/**
	 * Convert category IDs to slugs for wc_get_products() query.
	 *
	 * @param int[] $ids Category IDs.
	 * @return string[] Category slugs.
	 */
	private function get_category_slugs_by_ids( array $ids ): array {
		$ids = array_filter( array_map( 'intval', $ids ), static fn( int $id ) => $id > 0 );

		if ( empty( $ids ) ) {
			return [];
		}

		$terms = get_terms( [
			'taxonomy'   => 'product_cat',
			'include'    => $ids,
			'hide_empty' => false,
			'fields'     => 'id=>slug',
		] );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		return array_values( $terms );
	}
}
