<?php
/**
 * Sample Picker field type.
 *
 * @package SampleHQForm\Fields
 */

declare( strict_types=1 );

namespace SampleHQForm\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\SamplesTable;
use SampleHQForm\Database\SampleImagesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SampleCategoriesTable;

/**
 * Sample picker field: lets visitors select products with quantities.
 *
 * Reads from the local shqf_samples table. Renders as a fieldset of checkboxes
 * with optional quantity inputs. ADA compliant per SPEC.md Section 6.2.
 */
class SamplePickerField extends AbstractField {

	/**
	 * Samples table repository.
	 *
	 * @var SamplesTable
	 */
	private SamplesTable $samples_table;

	/**
	 * Sample images table repository.
	 *
	 * @var SampleImagesTable
	 */
	private SampleImagesTable $images_table;

	/**
	 * Category map table repository.
	 *
	 * @var ?SampleCategoryMapTable
	 */
	private ?SampleCategoryMapTable $category_map_table;

	/**
	 * Categories table repository.
	 *
	 * @var ?SampleCategoriesTable
	 */
	private ?SampleCategoriesTable $categories_table;

	/**
	 * WooCommerce product source (when WC integration is active).
	 *
	 * @var ?ProductSource\ProductSourceInterface
	 */
	private ?ProductSource\ProductSourceInterface $woo_source;


	/**
	 * Constructor.
	 *
	 * @param SamplesTable                              $samples_table      Samples repository.
	 * @param SampleImagesTable                         $images_table       Images repository.
	 * @param SampleCategoryMapTable|null               $category_map_table Category map repository.
	 * @param SampleCategoriesTable|null                $categories_table   Categories repository.
	 * @param ProductSource\ProductSourceInterface|null $woo_source         Optional WooCommerce product source.
	 */
	public function __construct(
		SamplesTable $samples_table,
		SampleImagesTable $images_table,
		?SampleCategoryMapTable $category_map_table = null,
		?SampleCategoriesTable $categories_table = null,
		?ProductSource\ProductSourceInterface $woo_source = null
	) {
		$this->samples_table      = $samples_table;
		$this->images_table       = $images_table;
		$this->category_map_table = $category_map_table;
		$this->categories_table   = $categories_table;
		$this->woo_source         = $woo_source;
	}

	/**
	 * Get the field type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'sample_picker';
	}

	/**
	 * Render the sample picker HTML.
	 *
	 * Produces a fieldset with checkboxes for each sample, optional quantity
	 * inputs, images, and descriptions. Includes an aria-live region for
	 * dynamic selection count announcements.
	 *
	 * @param array<string, mixed> $field   Field configuration with 'config' sub-key.
	 * @param mixed                $value   Current selections: [{id: int, quantity: int}, ...].
	 * @param array<string, mixed> $context Render context.
	 * @return string HTML markup.
	 */
	public function render( array $field, mixed $value, array $context = [] ): string {
		$config         = $field['config'] ?? [];
		$allow_quantity = ! empty( $config['allow_quantity'] );
		$max_selections = (int) ( $config['max_selections'] ?? 0 );
		$layout         = $config['layout'] ?? 'grid';
		$show_images    = $config['show_images'] ?? true;
		$show_desc      = $config['show_descriptions'] ?? true;
		$base_id        = $this->get_input_id( $field, $context );
		$base_name      = 'shqf_fields[' . esc_attr( $field['key'] ?? 'samples' ) . ']';

		$source  = $config['source'] ?? 'library';
		$use_woo = 'woocommerce' === $source && null !== $this->woo_source;

		$samples = $use_woo
			? $this->woo_source->get_samples( [ 'status' => 'active' ] )
			: $this->get_filtered_samples( $config );
		$selections = $this->normalize_selections( $value );

		// Build sample-to-categories map for filtering.
		$sample_cats   = [];
		$all_cat_names = [];
		if ( $use_woo ) {
			// Category names are pre-loaded in the mapped product data (batch query).
			foreach ( $samples as $s ) {
				$names = $s['category_names'] ?? [];
				$sample_cats[ (int) $s['id'] ] = $names;
				foreach ( $names as $name ) {
					$all_cat_names[ $name ] = true;
				}
			}
		} elseif ( null !== $this->category_map_table && null !== $this->categories_table ) {
			$all_categories = $this->categories_table->list_all();
			$cats_by_id     = [];
			foreach ( $all_categories as $cat ) {
				$cats_by_id[ (int) $cat['id'] ] = $cat['name'];
			}
			foreach ( $samples as $s ) {
				$cat_ids = $this->category_map_table->get_categories_for_sample( (int) $s['id'] );
				$names   = [];
				foreach ( $cat_ids as $cid ) {
					if ( isset( $cats_by_id[ $cid ] ) ) {
						$names[]                       = $cats_by_id[ $cid ];
						$all_cat_names[ $cats_by_id[ $cid ] ] = true;
					}
				}
				$sample_cats[ (int) $s['id'] ] = $names;
			}
		}

		$attrs = '';
		if ( ! empty( $field['required'] ) ) {
			$attrs = ' aria-required="true"';
		}

		$layout_class = 'grid' === $layout ? 'shqf-picker--grid' : 'shqf-picker--list';

		$initial_visible = count( $samples ) > 12 ? ' data-initial-visible="12"' : '';
		$html            = '<fieldset class="shqf-fieldset shqf-picker ' . esc_attr( $layout_class ) . '"' . $attrs;
		$html           .= ' aria-describedby="' . esc_attr( $this->get_error_id( $field, $context ) ) . '"' . $initial_visible . '>';
		$html .= '<legend class="shqf-legend">' . esc_html( $field['label'] ?? '' );

		if ( ! empty( $field['required'] ) ) {
			$html .= ' <span class="shqf-required" aria-hidden="true">*</span>';
		}

		$html .= '</legend>';

		// Aria-live region for selection count announcements.
		$html .= '<div class="shqf-picker-status" aria-live="polite" aria-atomic="true"';
		$html .= ' data-max="' . esc_attr( (string) $max_selections ) . '"></div>';

		// Search bar (client-side filtering).
		if ( count( $samples ) > 3 ) {
			$search_icon = '<svg class="shqf-picker-search-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="9" r="6"></circle><line x1="13.5" y1="13.5" x2="17" y2="17"></line></svg>';
			$html       .= '<div class="shqf-picker-search-wrap">';
			$html       .= $search_icon;
			$html       .= '<input type="text" class="shqf-picker-search" placeholder="' . esc_attr__( 'Search samples...', 'samplehq-request-form' ) . '" aria-label="' . esc_attr__( 'Search samples', 'samplehq-request-form' ) . '" autocomplete="off" />';
			$html       .= '</div>';
		}

		// Category filter pills.
		if ( ! empty( $all_cat_names ) ) {
			$html .= '<div class="shqf-picker-pills">';
			$html .= '<button type="button" class="shqf-pill shqf-pill--active" data-category="">';
			$html .= esc_html__( 'All', 'samplehq-request-form' ) . '</button>';
			foreach ( array_keys( $all_cat_names ) as $cat_name ) {
				$html .= '<button type="button" class="shqf-pill" data-category="' . esc_attr( $cat_name ) . '">';
				$html .= esc_html( $cat_name ) . '</button>';
			}
			$html .= '</div>';
		}

		$html .= '<div class="shqf-picker-items">';

		foreach ( $samples as $sample ) {
			$sid       = (int) $sample['id'];
			$cat_names = $sample_cats[ $sid ] ?? [];
			$html     .= $this->render_sample_item(
				$sample,
				$base_id,
				$base_name,
				$selections,
				$allow_quantity,
				$show_images,
				$show_desc,
				$config,
				$cat_names
			);
		}

		$html .= '</div>';
		$html .= $this->render_error( $field, $context );
		$html .= '</fieldset>';

		return $this->wrap( $field, $html, $context );
	}

	/**
	 * Render a single sample item (checkbox + optional image + quantity).
	 *
	 * @param array<string, mixed> $sample         Sample data row.
	 * @param string               $base_id        Base HTML ID prefix.
	 * @param string               $base_name      Base input name prefix.
	 * @param array<int, int>      $selections     Map of sample_id => quantity.
	 * @param bool                 $allow_quantity  Whether to show quantity input.
	 * @param bool                 $show_images     Whether to show images.
	 * @param bool                 $show_desc       Whether to show descriptions.
	 * @param array<string, mixed> $config          Field config for max quantity.
	 * @param string[]             $cat_names       Category names for this sample.
	 * @return string HTML for one sample item.
	 */
	private function render_sample_item(
		array $sample,
		string $base_id,
		string $base_name,
		array $selections,
		bool $allow_quantity,
		bool $show_images,
		bool $show_desc,
		array $config,
		array $cat_names = []
	): string {
		$sample_id   = (int) $sample['id'];
		$item_id     = $base_id . '-sample-' . $sample_id;
		$is_selected = isset( $selections[ $sample_id ] );
		$quantity    = $selections[ $sample_id ] ?? 1;
		$desc_id     = $item_id . '-desc';
		$max_qty     = (int) ( $sample['max_quantity'] ?? $config['default_max_quantity'] ?? 10 );
		$layout      = $config['layout'] ?? 'grid';

		$selected_class = $is_selected ? ' shqf-picker-item--selected' : '';
		$cats_attr      = ! empty( $cat_names ) ? ' data-categories="' . esc_attr( implode( ',', $cat_names ) ) . '"' : '';
		$aria_checked   = $is_selected ? 'true' : 'false';
		$describedby    = $show_desc && ! empty( $sample['description'] ) ? ' aria-describedby="' . esc_attr( $desc_id ) . '"' : '';

		$html  = '<div class="shqf-picker-item' . $selected_class . '" data-sample-id="' . esc_attr( (string) $sample_id ) . '"' . $cats_attr;
		$aria_name = ! empty( $sample['name'] ) ? $sample['name'] : 'Sample ' . $sample_id;
		$html .= ' role="checkbox" aria-checked="' . $aria_checked . '" aria-label="' . esc_attr( $aria_name ) . '"' . $describedby . '>';

		// Hidden checkbox for form submission (not exposed to assistive tech).
		$checked = $is_selected ? ' checked' : '';

		$html .= '<input type="checkbox" id="' . esc_attr( $item_id ) . '"';
		$html .= ' name="' . $base_name . '[items][' . $sample_id . '][selected]"';
		$html .= ' value="1"' . $checked;
		$html .= ' class="shqf-sr-only" tabindex="-1" aria-hidden="true" />';

		if ( 'list' === $layout ) {
			$html .= $this->render_list_item( $sample, $item_id, $desc_id, $show_images, $show_desc );
		} else {
			$html .= $this->render_grid_item( $sample, $item_id, $desc_id, $show_images, $show_desc );
		}

		// Quantity stepper (hidden when not selected, shown on selection).
		if ( $allow_quantity ) {
			$qty_label = sprintf(
				/* translators: %s: sample name */
				__( 'Quantity for %s', 'samplehq-request-form' ),
				$sample['name'] ?? ''
			);
			$max_attr = $max_qty > 0 ? ' data-max="' . esc_attr( (string) $max_qty ) . '"' : '';
			$html    .= '<div class="shqf-picker-item-qty-controls" aria-label="' . esc_attr( $qty_label ) . '"' . $max_attr . '>';
			$html    .= '<button type="button" class="shqf-picker-item-qty-btn shqf-qty-minus" aria-label="' . esc_attr__( 'Decrease quantity', 'samplehq-request-form' ) . '">&minus;</button>';
			$html    .= '<span class="shqf-picker-item-qty-value">' . esc_html( (string) $quantity ) . '</span>';
			$html    .= '<button type="button" class="shqf-picker-item-qty-btn shqf-qty-plus" aria-label="' . esc_attr__( 'Increase quantity', 'samplehq-request-form' ) . '">+</button>';
			$html    .= '<input type="hidden" name="' . $base_name . '[items][' . $sample_id . '][quantity]"';
			$html    .= ' value="' . esc_attr( (string) $quantity ) . '" class="shqf-picker-item-qty" />';
			$html    .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render a grid card item (image + body with title/desc + check overlay).
	 *
	 * @param array<string, mixed> $sample     Sample data.
	 * @param string               $item_id    HTML ID prefix.
	 * @param string               $desc_id    Description element ID.
	 * @param bool                 $show_images Whether to show images.
	 * @param bool                 $show_desc   Whether to show descriptions.
	 * @return string HTML.
	 */
	private function render_grid_item(
		array $sample,
		string $item_id,
		string $desc_id,
		bool $show_images,
		bool $show_desc
	): string {
		$html = '';

		// Image.
		if ( $show_images ) {
			$image_url = $this->get_sample_image_url( (int) $sample['id'], 'medium', $sample );
			if ( $image_url ) {
				$html .= '<img src="' . esc_url( $image_url ) . '" alt=""';
				$html .= ' class="shqf-picker-item-image" loading="lazy" />';
			} else {
				$html .= '<div class="shqf-picker-item-placeholder" aria-hidden="true">';
				$html .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
				$html .= '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>';
				$html .= '<circle cx="9" cy="9" r="2"></circle>';
				$html .= '<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>';
				$html .= '</svg></div>';
			}
		}

		// Checkmark overlay (visible when selected, CSS controls display).
		$html .= '<span class="shqf-picker-item-check" aria-hidden="true">';
		$html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
		$html .= '</span>';

		// Body (title + description).
		$html .= '<div class="shqf-picker-item-body">';
		$html .= '<label for="' . esc_attr( $item_id ) . '" class="shqf-picker-item-label">';
		$html .= esc_html( $sample['name'] ?? '' );
		$html .= '</label>';

		if ( $show_desc && ! empty( $sample['description'] ) ) {
			$html .= '<div id="' . esc_attr( $desc_id ) . '" class="shqf-picker-item-desc">';
			$html .= wp_kses_post( $sample['description'] );
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render a list row item (checkbox visual + thumbnail + info block).
	 *
	 * @param array<string, mixed> $sample     Sample data.
	 * @param string               $item_id    HTML ID prefix.
	 * @param string               $desc_id    Description element ID.
	 * @param bool                 $show_images Whether to show images.
	 * @param bool                 $show_desc   Whether to show descriptions.
	 * @return string HTML.
	 */
	private function render_list_item(
		array $sample,
		string $item_id,
		string $desc_id,
		bool $show_images,
		bool $show_desc
	): string {
		$html = '';

		// Visual checkbox (CSS styled, interaction via hidden real checkbox).
		$html .= '<span class="shqf-picker-item-checkbox" aria-hidden="true">';
		$html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
		$html .= '</span>';

		// Thumbnail.
		if ( $show_images ) {
			$image_url = $this->get_sample_image_url( (int) $sample['id'], 'thumbnail', $sample );
			if ( $image_url ) {
				$html .= '<img src="' . esc_url( $image_url ) . '" alt=""';
				$html .= ' class="shqf-picker-item-thumb" loading="lazy" />';
			} else {
				$html .= '<div class="shqf-picker-item-thumb shqf-picker-item-thumb--placeholder" aria-hidden="true">';
				$html .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
				$html .= '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>';
				$html .= '<circle cx="9" cy="9" r="2"></circle>';
				$html .= '<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>';
				$html .= '</svg></div>';
			}
		}

		// Info block (title + description).
		$html .= '<div class="shqf-picker-item-info">';
		$html .= '<label for="' . esc_attr( $item_id ) . '" class="shqf-picker-item-label">';
		$html .= esc_html( $sample['name'] ?? '' );
		$html .= '</label>';

		if ( $show_desc && ! empty( $sample['description'] ) ) {
			$html .= '<div id="' . esc_attr( $desc_id ) . '" class="shqf-picker-item-desc">';
			$html .= wp_kses_post( $sample['description'] );
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Get image URL for a sample.
	 *
	 * For WC source (detected by 'image_id' key in $sample): resolves from
	 * pre-loaded image_id at the requested size (cache already primed).
	 * For local source: fetches from the images table.
	 *
	 * @param int                  $sample_id Sample ID.
	 * @param string               $size      WordPress image size.
	 * @param array<string, mixed> $sample    Full sample data row.
	 * @return string|null Image URL or null.
	 */
	private function get_sample_image_url( int $sample_id, string $size, array $sample = [] ): ?string {
		// WC source: image_id is pre-loaded; attachment cache is primed by batch query.
		if ( array_key_exists( 'image_id', $sample ) ) {
			$image_id = (int) $sample['image_id'];
			if ( 0 === $image_id ) {
				return null;
			}
			$url = wp_get_attachment_image_url( $image_id, $size );
			return $url ? (string) $url : null;
		}

		// Local source: fetch from images table.
		$image_id = $this->images_table->get_featured( $sample_id );
		if ( ! $image_id ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $image_id, $size );

		return $url ? (string) $url : null;
	}

	/**
	 * Validate the sample picker submission.
	 *
	 * @param mixed                $value Submitted value (array with 'items' key).
	 * @param array<string, mixed> $field Field configuration.
	 * @return string|null Error message or null.
	 */
	public function validate( mixed $value, array $field ): ?string {
		$config         = $field['config'] ?? [];
		$max_selections = (int) ( $config['max_selections'] ?? 0 );
		$allow_quantity = ! empty( $config['allow_quantity'] );
		$source         = $config['source'] ?? 'library';
		$use_woo        = 'woocommerce' === $source && null !== $this->woo_source;

		$items    = $this->extract_selected_items( $value );
		$selected = array_filter( $items, static fn( $item ) => ! empty( $item['selected'] ) );

		// Required check.
		if ( ! empty( $field['required'] ) && empty( $selected ) ) {
			/* translators: %s: field label */
			return sprintf( __( 'Please select at least one sample.', 'samplehq-request-form' ) );
		}

		// Max selections check.
		if ( $max_selections > 0 && count( $selected ) > $max_selections ) {
			/* translators: %d: maximum number of selections */
			return sprintf( __( 'You can select at most %d samples.', 'samplehq-request-form' ), $max_selections );
		}

		// Validate each selected sample exists and quantities are valid.
		foreach ( $selected as $sample_id => $item ) {
			if ( $use_woo ) {
				$error = $this->validate_woo_product( (int) $sample_id, $item, $allow_quantity, $config );
			} else {
				$error = $this->validate_library_sample( (int) $sample_id, $item, $allow_quantity );
			}

			if ( null !== $error ) {
				return $error;
			}
		}

		return null;
	}

	/**
	 * Validate a library sample selection.
	 *
	 * @param int                  $sample_id     Sample ID.
	 * @param array<string, mixed> $item          Selection data with 'quantity' key.
	 * @param bool                 $allow_quantity Whether quantity is enabled.
	 * @return string|null Error message or null.
	 */
	private function validate_library_sample( int $sample_id, array $item, bool $allow_quantity ): ?string {
		$sample = $this->samples_table->get( $sample_id );
		if ( null === $sample ) {
			return __( 'One or more selected samples are no longer available.', 'samplehq-request-form' );
		}

		if ( 'active' !== ( $sample['status'] ?? '' ) ) {
			return __( 'One or more selected samples are no longer available.', 'samplehq-request-form' );
		}

		if ( $allow_quantity ) {
			$qty     = (int) ( $item['quantity'] ?? 1 );
			$max_qty = (int) ( $sample['max_quantity'] ?? 0 );

			if ( $qty < 1 ) {
				return __( 'Quantity must be at least 1.', 'samplehq-request-form' );
			}

			if ( $max_qty > 0 && $qty > $max_qty ) {
				return sprintf(
					/* translators: 1: sample name, 2: max quantity */
					__( 'Maximum quantity for %1$s is %2$d.', 'samplehq-request-form' ),
					$sample['name'] ?? '',
					$max_qty
				);
			}
		}

		return null;
	}

	/**
	 * Validate a WooCommerce product selection.
	 *
	 * @param int                  $product_id     WC product ID.
	 * @param array<string, mixed> $item           Selection data with 'quantity' key.
	 * @param bool                 $allow_quantity  Whether quantity is enabled.
	 * @param array<string, mixed> $config          Field configuration.
	 * @return string|null Error message or null.
	 */
	private function validate_woo_product( int $product_id, array $item, bool $allow_quantity, array $config ): ?string {
		$product = $this->woo_source->get_sample( $product_id );
		if ( null === $product ) {
			return __( 'One or more selected samples are no longer available.', 'samplehq-request-form' );
		}

		if ( 'active' !== ( $product['status'] ?? '' ) ) {
			return __( 'One or more selected samples are no longer available.', 'samplehq-request-form' );
		}

		if ( $allow_quantity ) {
			$qty     = (int) ( $item['quantity'] ?? 1 );
			$max_qty = (int) ( $config['default_max_quantity'] ?? get_option( 'shqf_woo_max_quantity', 3 ) );

			if ( $qty < 1 ) {
				return __( 'Quantity must be at least 1.', 'samplehq-request-form' );
			}

			if ( $max_qty > 0 && $qty > $max_qty ) {
				return sprintf(
					/* translators: 1: sample name, 2: max quantity */
					__( 'Maximum quantity for %1$s is %2$d.', 'samplehq-request-form' ),
					$product['name'] ?? '',
					$max_qty
				);
			}
		}

		return null;
	}

	/**
	 * Sanitize the sample picker submission.
	 *
	 * Returns a clean array of selections for storage. WooCommerce selections
	 * include source, name, and SKU so the submission is self-contained even
	 * if the product is later deleted.
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field configuration.
	 * @return array<int, array<string, mixed>> Sanitized selections.
	 */
	public function sanitize( mixed $value, array $field ): array {
		$config  = $field['config'] ?? [];
		$source  = $config['source'] ?? 'library';
		$use_woo = 'woocommerce' === $source && null !== $this->woo_source;
		$items   = $this->extract_selected_items( $value );
		$result  = [];

		foreach ( $items as $sample_id => $item ) {
			if ( empty( $item['selected'] ) ) {
				continue;
			}

			$entry = [
				'id'       => absint( $sample_id ),
				'quantity' => max( 1, absint( $item['quantity'] ?? 1 ) ),
			];

			if ( $use_woo ) {
				$product         = $this->woo_source->get_sample( absint( $sample_id ) );
				$entry['source'] = 'woocommerce';
				$entry['name']   = is_array( $product ) ? ( $product['name'] ?? '' ) : '';
				$entry['sku']    = is_array( $product ) ? ( $product['sku'] ?? '' ) : '';
			}

			$result[] = $entry;
		}

		return $result;
	}

	/**
	 * Get filtered samples from the database based on field config.
	 *
	 * @param array<string, mixed> $config Field config with filter settings.
	 * @return array<int, array<string, mixed>> Sample rows.
	 */
	private function get_filtered_samples( array $config ): array {
		$filter = $config['filter'] ?? [];
		$mode   = $filter['mode'] ?? 'all';

		$filters = [ 'status' => 'active' ];

		if ( 'selected' === $mode && ! empty( $filter['sample_ids'] ) ) {
			// When specific samples are selected, we'd need a WHERE IN query.
			// For now, get all active and filter in PHP (simple, works for typical catalog sizes).
			$all     = $this->samples_table->list_all( $filters );
			$allowed = array_map( 'intval', $filter['sample_ids'] );
			return array_filter(
				$all,
				static fn( $s ) => in_array( (int) $s['id'], $allowed, true )
			);
		}

		return $this->samples_table->list_all( $filters );
	}

	/**
	 * Normalize submitted value into a selections map.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return array<int, int> Map of sample_id => quantity.
	 */
	private function normalize_selections( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$items = $value['items'] ?? $value;
		$map   = [];

		foreach ( $items as $sample_id => $item ) {
			if ( is_array( $item ) && ! empty( $item['selected'] ) ) {
				$map[ (int) $sample_id ] = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			}
		}

		return $map;
	}

	/**
	 * Extract selected items from the raw submitted value.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return array<int|string, array<string, mixed>> Items keyed by sample ID.
	 */
	private function extract_selected_items( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		return $value['items'] ?? $value;
	}
}
