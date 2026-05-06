<?php
/**
 * Library product source -- reads from the plugin's shqf_samples table.
 *
 * Wraps the existing SamplesTable, SampleImagesTable, SampleCategoriesTable,
 * and SampleCategoryMapTable behind the ProductSourceInterface so that
 * SamplePickerField can use either this or WooCommerceSource interchangeably.
 *
 * @package SampleHQForm\Fields\ProductSource
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields\ProductSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Database\SampleImagesTable;
use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;

/**
 * Reads samples from the plugin's own sample library tables.
 */
class LibrarySource implements ProductSourceInterface {

	/**
	 * @var SamplesTable
	 */
	private SamplesTable $samples;

	/**
	 * @var SampleImagesTable
	 */
	private SampleImagesTable $images;

	/**
	 * @var SampleCategoriesTable
	 */
	private SampleCategoriesTable $categories;

	/**
	 * @var SampleCategoryMapTable
	 */
	private SampleCategoryMapTable $category_map;

	/**
	 * Constructor.
	 *
	 * @param SamplesTable           $samples      Samples repository.
	 * @param SampleImagesTable      $images       Sample images repository.
	 * @param SampleCategoriesTable  $categories   Categories repository.
	 * @param SampleCategoryMapTable $category_map Category mapping repository.
	 */
	public function __construct(
		SamplesTable $samples,
		SampleImagesTable $images,
		SampleCategoriesTable $categories,
		SampleCategoryMapTable $category_map
	) {
		$this->samples      = $samples;
		$this->images       = $images;
		$this->categories   = $categories;
		$this->category_map = $category_map;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_samples( array $filters = [] ): array {
		return $this->samples->list_all( $filters );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_sample( int $id ): ?array {
		return $this->samples->get( $id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_categories(): array {
		$all = $this->categories->list_all();
		$result = [];
		foreach ( $all as $cat ) {
			$result[] = [
				'id'   => (int) $cat['id'],
				'name' => $cat['name'],
				'slug' => $cat['slug'] ?? '',
			];
		}
		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_image_url( int $sample_id, string $size = 'medium' ): ?string {
		$image_id = $this->images->get_featured( $sample_id );
		if ( ! $image_id ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $image_id, $size );

		return $url ? (string) $url : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_sample_category_names( int $sample_id ): array {
		$cat_ids    = $this->category_map->get_categories_for_sample( $sample_id );
		$all_cats   = $this->categories->list_all();
		$cats_by_id = [];
		foreach ( $all_cats as $cat ) {
			$cats_by_id[ (int) $cat['id'] ] = $cat['name'];
		}

		$names = [];
		foreach ( $cat_ids as $cid ) {
			if ( isset( $cats_by_id[ $cid ] ) ) {
				$names[] = $cats_by_id[ $cid ];
			}
		}

		return $names;
	}
}
