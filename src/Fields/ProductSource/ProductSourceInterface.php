<?php
/**
 * Product source interface for the sample picker field.
 *
 * Abstracts the sample data source so the picker can read from
 * the plugin's sample library OR from WooCommerce products.
 *
 * @package SampleHQForm\Fields\ProductSource
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields\ProductSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for sample/product data providers.
 *
 * Implementations must return data in the same shape as the shqf_samples table
 * rows so that SamplePickerField renders identically regardless of source.
 */
interface ProductSourceInterface {

	/**
	 * Get samples/products matching the given filters.
	 *
	 * @param array<string, mixed> $filters Optional filters: status, search, category_id, limit, offset.
	 * @return array<int, array<string, mixed>> Rows with keys: id, name, sku, description, max_quantity, status.
	 */
	public function get_samples( array $filters = [] ): array;

	/**
	 * Get a single sample/product by ID.
	 *
	 * @param int $id Sample or product ID.
	 * @return array<string, mixed>|null Row with same keys as get_samples(), or null if not found.
	 */
	public function get_sample( int $id ): ?array;

	/**
	 * Get all categories available for this source.
	 *
	 * @return array<int, array{id: int, name: string, slug: string}> Category list.
	 */
	public function get_categories(): array;

	/**
	 * Get the image URL for a sample/product.
	 *
	 * @param int    $sample_id Sample or product ID.
	 * @param string $size      WordPress image size (e.g. 'thumbnail', 'medium').
	 * @return string|null Image URL or null if no image.
	 */
	public function get_image_url( int $sample_id, string $size = 'medium' ): ?string;

	/**
	 * Get category names for a specific sample/product.
	 *
	 * @param int $sample_id Sample or product ID.
	 * @return string[] Category names.
	 */
	public function get_sample_category_names( int $sample_id ): array;
}
