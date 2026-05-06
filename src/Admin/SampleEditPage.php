<?php
/**
 * Sample add/edit admin page.
 *
 * Uses WordPress native postbox/metabox layout (ACF-style two-column).
 *
 * @package SampleHQForm\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\SampleCategoriesTable;
use SampleHQForm\Database\SampleCategoryMapTable;
use SampleHQForm\Database\SampleImagesTable;
use SampleHQForm\Database\SamplesTable;

/**
 * Renders the add/edit sample admin form and handles saves.
 */
class SampleEditPage {

	/**
	 * Samples repository.
	 *
	 * @var SamplesTable
	 */
	private SamplesTable $samples;

	/**
	 * Categories repository.
	 *
	 * @var SampleCategoriesTable
	 */
	private SampleCategoriesTable $categories;

	/**
	 * Category map repository.
	 *
	 * @var SampleCategoryMapTable
	 */
	private SampleCategoryMapTable $category_map;

	/**
	 * Images repository.
	 *
	 * @var SampleImagesTable
	 */
	private SampleImagesTable $images;

	/**
	 * Constructor.
	 *
	 * @param SamplesTable           $samples      Samples repository.
	 * @param SampleCategoriesTable  $categories   Categories repository.
	 * @param SampleCategoryMapTable $category_map Category map repository.
	 * @param SampleImagesTable      $images       Images repository.
	 */
	public function __construct(
		SamplesTable $samples,
		SampleCategoriesTable $categories,
		SampleCategoryMapTable $category_map,
		SampleImagesTable $images
	) {
		$this->samples      = $samples;
		$this->categories   = $categories;
		$this->category_map = $category_map;
		$this->images       = $images;
	}

	/**
	 * Render the add/edit form.
	 *
	 * @param int $sample_id Sample ID (0 for new).
	 * @return void
	 */
	public function render( int $sample_id = 0 ): void {
		$sample        = $sample_id > 0 ? $this->samples->get( $sample_id ) : null;
		$is_edit       = null !== $sample;
		$title         = $is_edit ? __( 'Edit Sample', 'samplehq-request-form' ) : __( 'Add Sample', 'samplehq-request-form' );
		$all_cats      = $this->categories->list_all();
		$selected_cats = $is_edit ? $this->category_map->get_categories_for_sample( $sample_id ) : [];

		wp_enqueue_media();
		wp_enqueue_script( 'postbox' );

		$current_images = $is_edit ? $this->images->get_for_sample( $sample_id ) : [];

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-samples' ) ) . '" class="page-title-action">';
		echo esc_html__( 'Back to Sample Library', 'samplehq-request-form' ) . '</a>';
		if ( $is_edit ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-submissions&sample_id=' . $sample_id ) ) . '" class="page-title-action">';
			echo esc_html__( 'View Requests', 'samplehq-request-form' ) . '</a>';
		}
		echo '<hr class="wp-header-end">';
		AdminNotice::render();

		echo '<form method="post" action="">';
		wp_nonce_field( 'shqf_save_sample', 'shqf_sample_nonce' );

		if ( $is_edit ) {
			echo '<input type="hidden" name="sample_id" value="' . esc_attr( (string) $sample_id ) . '" />';
		}

		// Two-column layout: main content + sidebar.
		echo '<div id="poststuff">';
		echo '<div id="post-body" class="metabox-holder columns-2">';

		// --- Main content (left column) ---
		echo '<div id="post-body-content">';

		// Sample name (like WP post title).
		echo '<div id="titlediv">';
		echo '<div id="titlewrap">';
		echo '<label class="screen-reader-text" for="shqf-name">' . esc_html__( 'Sample Name', 'samplehq-request-form' ) . '</label>';
		echo '<input type="text" id="shqf-name" name="name" placeholder="' . esc_attr__( 'Enter sample name', 'samplehq-request-form' ) . '"';
		echo ' size="30" value="' . esc_attr( $sample['name'] ?? '' ) . '" autocomplete="off" required />';
		echo '</div></div>';

		// Description metabox (rich text editor).
		$this->render_postbox(
			'shqf-description-box',
			__( 'Description', 'samplehq-request-form' ),
			function () use ( $sample ) {
				wp_editor(
					$sample['description'] ?? '',
					'shqf_description',
					[
						'textarea_name' => 'description',
						'textarea_rows' => 8,
						'media_buttons' => false,
						'teeny'         => true,
						'quicktags'     => true,
					]
				);
			}
		);

		// Custom fields metabox (repeatable key-value pairs).
		$custom_fields = [];
		if ( ! empty( $sample['custom_fields'] ) ) {
			$decoded = json_decode( $sample['custom_fields'], true );
			if ( is_array( $decoded ) ) {
				$custom_fields = $decoded;
			}
		}

		$this->render_postbox(
			'shqf-custom-fields-box',
			__( 'Custom Fields', 'samplehq-request-form' ),
			function () use ( $custom_fields ) {
				echo '<table class="widefat shqf-custom-fields-table" id="shqf-custom-fields">';
				echo '<thead><tr>';
				echo '<th>' . esc_html__( 'Name', 'samplehq-request-form' ) . '</th>';
				echo '<th>' . esc_html__( 'Value', 'samplehq-request-form' ) . '</th>';
				echo '<th></th>';
				echo '</tr></thead><tbody>';
				if ( ! empty( $custom_fields ) ) {
					foreach ( $custom_fields as $cf ) {
						$this->render_custom_field_row( $cf['key'] ?? '', $cf['value'] ?? '' );
					}
				}
				echo '</tbody></table>';
				echo '<p><button type="button" class="button" id="shqf-add-custom-field">';
				echo esc_html__( 'Add Field', 'samplehq-request-form' ) . '</button></p>';
				echo '<p class="description">' . esc_html__( 'Add specs like weight, material, or dimensions.', 'samplehq-request-form' ) . '</p>';
			}
		);

		echo '</div>'; // #post-body-content

		// --- Sidebar (right column) ---
		echo '<div id="postbox-container-1" class="postbox-container">';

		// Publish / Save box.
		$this->render_postbox(
			'shqf-submitdiv',
			__( 'Save', 'samplehq-request-form' ),
			function () use ( $is_edit ) {
				echo '<div class="submitbox" id="submitpost">';
				echo '<div id="major-publishing-actions">';
				echo '<div id="publishing-action">';
				submit_button(
					$is_edit ? __( 'Update Sample', 'samplehq-request-form' ) : __( 'Add Sample', 'samplehq-request-form' ),
					'primary large',
					'submit',
					false
				);
				echo '</div>';
				echo '<div class="clear"></div>';
				echo '</div></div>';
			}
		);

		// Status metabox.
		$this->render_postbox(
			'shqf-status-box',
			__( 'Status', 'samplehq-request-form' ),
			function () use ( $sample ) {
				$current_status = $sample['status'] ?? 'active';
				echo '<select id="shqf-status" name="status" style="width:100%;">';
				echo '<option value="active"' . selected( $current_status, 'active', false ) . '>' . esc_html__( 'Active', 'samplehq-request-form' ) . '</option>';
				echo '<option value="archived"' . selected( $current_status, 'archived', false ) . '>' . esc_html__( 'Archived', 'samplehq-request-form' ) . '</option>';
				echo '</select>';
			}
		);

		// SKU metabox.
		$this->render_postbox(
			'shqf-sku-box',
			__( 'SKU', 'samplehq-request-form' ),
			function () use ( $sample ) {
				echo '<input type="text" id="shqf-sku" name="sku" class="widefat"';
				echo ' value="' . esc_attr( $sample['sku'] ?? '' ) . '" />';
			}
		);

		// Max Quantity metabox.
		$this->render_postbox(
			'shqf-qty-box',
			__( 'Max Quantity', 'samplehq-request-form' ),
			function () use ( $sample ) {
				echo '<input type="number" id="shqf-max-qty" name="max_quantity" min="0" class="widefat"';
				echo ' value="' . esc_attr( (string) ( $sample['max_quantity'] ?? '0' ) ) . '" />';
				echo '<p class="description">' . esc_html__( '0 = unlimited per request', 'samplehq-request-form' ) . '</p>';
			}
		);

		// Categories metabox.
		$this->render_postbox(
			'shqf-categories-box',
			__( 'Categories', 'samplehq-request-form' ),
			function () use ( $all_cats, $selected_cats ) {
				// Checklist with hierarchy.
				echo '<div id="shqf-category-checklist">';
				$this->render_category_checklist( $all_cats, $selected_cats, 0 );
				echo '</div>';

				// "Add New Category" toggle (WordPress-style).
				echo '<div id="shqf-add-category-toggle">';
				echo '<a href="#" class="shqf-add-cat-link" id="shqf-add-cat-toggle">';
				echo '+ ' . esc_html__( 'Add New Category', 'samplehq-request-form' ) . '</a>';
				echo '</div>';

				echo '<div id="shqf-add-category-form" style="display:none;margin-top:8px;">';
				echo '<input type="text" id="shqf-new-cat-name" placeholder="' . esc_attr__( 'Category name', 'samplehq-request-form' ) . '" class="widefat" style="margin-bottom:6px;" />';
				echo '<select id="shqf-new-cat-parent" class="widefat" style="margin-bottom:6px;">';
				echo '<option value="0">' . esc_html__( '-- Parent Category --', 'samplehq-request-form' ) . '</option>';
				foreach ( $all_cats as $cat ) {
					echo '<option value="' . esc_attr( $cat['id'] ) . '">' . esc_html( $cat['name'] ) . '</option>';
				}
				echo '</select>';
				echo '<button type="button" class="button" id="shqf-add-cat-btn">';
				echo esc_html__( 'Add', 'samplehq-request-form' ) . '</button>';
				echo ' <a href="#" id="shqf-add-cat-cancel">' . esc_html__( 'Cancel', 'samplehq-request-form' ) . '</a>';
				echo '<span id="shqf-add-cat-spinner" class="spinner" style="float:none;"></span>';
				echo '</div>';
			}
		);

		// Images metabox (gallery).
		$this->render_postbox(
			'shqf-images-box',
			__( 'Images', 'samplehq-request-form' ),
			function () use ( $current_images ) {
				echo '<div id="shqf-image-gallery" class="shqf-image-gallery">';
				foreach ( $current_images as $img ) {
					$att_id = (int) $img['attachment_id'];
					$url    = wp_get_attachment_image_url( $att_id, 'thumbnail' );
					if ( ! $url ) {
						continue;
					}
					echo '<div class="shqf-gallery-item" data-id="' . esc_attr( (string) $att_id ) . '">';
					echo '<img src="' . esc_url( $url ) . '" alt="" />';
					echo '<button type="button" class="shqf-gallery-remove" aria-label="' . esc_attr__( 'Remove image', 'samplehq-request-form' ) . '">&times;</button>';
					echo '<input type="hidden" name="image_ids[]" value="' . esc_attr( (string) $att_id ) . '" />';
					echo '</div>';
				}
				echo '</div>';
				echo '<p>';
				echo '<button type="button" class="button" id="shqf-add-images">';
				echo esc_html__( 'Add Images', 'samplehq-request-form' ) . '</button>';
				echo '</p>';
				echo '<p class="description">' . esc_html__( 'First image is the featured image.', 'samplehq-request-form' ) . '</p>';
			}
		);

		echo '</div>'; // #postbox-container-1

		echo '</div>'; // #post-body
		echo '</div>'; // #poststuff

		echo '</form>';

		// Media uploader JS.
		$this->render_media_js();

		echo '</div>'; // .wrap
	}

	/**
	 * Render a hierarchical category checklist.
	 *
	 * @param array<int, array<string, mixed>> $all_cats      All categories.
	 * @param int[]                            $selected_cats Selected category IDs.
	 * @param int                              $parent_id     Parent ID to render children for.
	 * @return void
	 */
	private function render_category_checklist( array $all_cats, array $selected_cats, int $parent_id ): void {
		$children = array_filter(
			$all_cats,
			static fn( $cat ) => (int) ( $cat['parent_id'] ?? 0 ) === $parent_id
		);

		if ( empty( $children ) ) {
			if ( 0 === $parent_id ) {
				echo '<p class="description">' . esc_html__( 'No categories yet.', 'samplehq-request-form' ) . '</p>';
			}
			return;
		}

		echo '<ul class="categorychecklist">';
		foreach ( $children as $cat ) {
			$cat_id     = (int) $cat['id'];
			$is_checked = in_array( $cat_id, $selected_cats, true );
			echo '<li><label>';
			echo '<input type="checkbox" name="categories[]" value="' . esc_attr( (string) $cat_id ) . '"' . checked( $is_checked, true, false ) . ' /> ';
			echo esc_html( $cat['name'] );
			echo '</label>';
			// Render children recursively.
			$this->render_category_checklist( $all_cats, $selected_cats, $cat_id );
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Render a postbox/metabox container.
	 *
	 * @param string   $id       Unique ID for the postbox.
	 * @param string   $title    Postbox title.
	 * @param callable $callback Content render callback.
	 * @return void
	 */
	private function render_postbox( string $id, string $title, callable $callback ): void {
		echo '<div id="' . esc_attr( $id ) . '" class="postbox">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html( $title ) . '</h2></div>';
		echo '<div class="inside">';
		$callback();
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render a single custom field row.
	 *
	 * @param string $key   Field name.
	 * @param string $value Field value.
	 * @return void
	 */
	private function render_custom_field_row( string $key, string $value ): void {
		echo '<tr class="shqf-cf-row">';
		echo '<td><input type="text" name="cf_keys[]" value="' . esc_attr( $key ) . '" class="widefat" placeholder="' . esc_attr__( 'Name', 'samplehq-request-form' ) . '" /></td>';
		echo '<td><input type="text" name="cf_values[]" value="' . esc_attr( $value ) . '" class="widefat" placeholder="' . esc_attr__( 'Value', 'samplehq-request-form' ) . '" /></td>';
		echo '<td><button type="button" class="button shqf-cf-remove">&times;</button></td>';
		echo '</tr>';
	}

	/**
	 * Render inline JavaScript for the sample edit page.
	 *
	 * Handles WP Media Library uploads and inline category creation.
	 *
	 * @return void
	 */
	private function render_media_js(): void {
		?>
		<script>
		jQuery(function($){
			// --- Image gallery ---
			var $gallery = $('#shqf-image-gallery');

			$('#shqf-add-images').on('click', function(e) {
				e.preventDefault();
				var frame = wp.media({
					title: <?php echo wp_json_encode( __( 'Select Sample Images', 'samplehq-request-form' ) ); ?>,
					button: { text: <?php echo wp_json_encode( __( 'Add Images', 'samplehq-request-form' ) ); ?> },
					multiple: true
				});
				frame.on('select', function() {
					var selection = frame.state().get('selection');
					selection.each(function(attachment) {
						var data = attachment.toJSON();
						var url  = data.sizes && data.sizes.thumbnail
							? data.sizes.thumbnail.url
							: data.url;
						// Skip if already in gallery.
						if ($gallery.find('[data-id="' + data.id + '"]').length) return;
						var $item = $('<div class="shqf-gallery-item" data-id="' + data.id + '">'
							+ '<img src="' + url + '" alt="" />'
							+ '<button type="button" class="shqf-gallery-remove" aria-label="<?php echo esc_attr__( 'Remove image', 'samplehq-request-form' ); ?>">&times;</button>'
							+ '<input type="hidden" name="image_ids[]" value="' + data.id + '" />'
							+ '</div>');
						$gallery.append($item);
					});
				});
				frame.open();
			});

			$gallery.on('click', '.shqf-gallery-remove', function(e) {
				e.preventDefault();
				$(this).closest('.shqf-gallery-item').remove();
			});

			// --- Custom fields ---
			$('#shqf-add-custom-field').on('click', function() {
				var row = '<tr class="shqf-cf-row">'
					+ '<td><input type="text" name="cf_keys[]" class="widefat" placeholder="<?php echo esc_attr__( 'Name', 'samplehq-request-form' ); ?>" /></td>'
					+ '<td><input type="text" name="cf_values[]" class="widefat" placeholder="<?php echo esc_attr__( 'Value', 'samplehq-request-form' ); ?>" /></td>'
					+ '<td><button type="button" class="button shqf-cf-remove">&times;</button></td>'
					+ '</tr>';
				$('#shqf-custom-fields tbody').append(row);
			});

			$('#shqf-custom-fields').on('click', '.shqf-cf-remove', function() {
				$(this).closest('tr').remove();
			});

			// --- Inline category creation ---
			var $addForm    = $('#shqf-add-category-form'),
				$addToggle  = $('#shqf-add-cat-toggle'),
				$addCancel  = $('#shqf-add-cat-cancel'),
				$addBtn     = $('#shqf-add-cat-btn'),
				$nameInput  = $('#shqf-new-cat-name'),
				$parentSel  = $('#shqf-new-cat-parent'),
				$spinner    = $('#shqf-add-cat-spinner'),
				$checklist  = $('#shqf-category-checklist');

			$addToggle.on('click', function(e) {
				e.preventDefault();
				$addForm.slideDown(200);
				$nameInput.focus();
			});

			$addCancel.on('click', function(e) {
				e.preventDefault();
				$addForm.slideUp(200);
				$nameInput.val('');
				$parentSel.val('0');
			});

			$addBtn.on('click', function() {
				var name     = $.trim($nameInput.val()),
					parentId = parseInt($parentSel.val(), 10) || 0;

				if (!name) {
					$nameInput.focus();
					return;
				}

				$addBtn.prop('disabled', true);
				$spinner.addClass('is-active');

				wp.apiRequest({
					path: '/samplehq-form/v1/categories',
					method: 'POST',
					data: { name: name, parent_id: parentId }
				}).done(function(cat) {
					// Add to checklist (checked by default).
					var $li = $('<li><label><input type="checkbox" name="categories[]" value="' + cat.id + '" checked /> ' + $('<span>').text(cat.name).html() + '</label></li>');

					if (parentId > 0) {
						// Find parent li and append as nested ul.
						var $parentLi = $checklist.find('input[value="' + parentId + '"]').closest('li');
						var $subUl = $parentLi.children('ul.categorychecklist');
						if (!$subUl.length) {
							$subUl = $('<ul class="categorychecklist"></ul>').appendTo($parentLi);
						}
						$subUl.append($li);
					} else {
						var $topUl = $checklist.children('ul.categorychecklist');
						if (!$topUl.length) {
							$checklist.empty();
							$topUl = $('<ul class="categorychecklist"></ul>').appendTo($checklist);
						}
						$topUl.append($li);
					}

					// Add to parent dropdown for future subcategories.
					$parentSel.append('<option value="' + cat.id + '">' + $('<span>').text(cat.name).html() + '</option>');

					// Reset.
					$nameInput.val('');
					$parentSel.val('0');
					$nameInput.focus();
				}).fail(function(resp) {
					var msg = resp.responseJSON && resp.responseJSON.message
						? resp.responseJSON.message
						: <?php echo wp_json_encode( __( 'Failed to create category.', 'samplehq-request-form' ) ); ?>;
					window.alert(msg);
				}).always(function() {
					$addBtn.prop('disabled', false);
					$spinner.removeClass('is-active');
				});
			});

			// Allow Enter in name field to submit.
			$nameInput.on('keypress', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					$addBtn.trigger('click');
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle form save via POST.
	 *
	 * Saves the sample and redirects with a flash notice.
	 *
	 * @param int $sample_id Current sample ID (0 for new).
	 * @return void
	 */
	public function handle_save( int $sample_id ): void {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
			return;
		}

		if ( ! isset( $_POST['shqf_sample_nonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['shqf_sample_nonce'] ) ),
			'shqf_save_sample'
		) ) {
			return;
		}

		$data = [
			'name'         => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'sku'          => sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) ),
			'description'  => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'max_quantity' => absint( $_POST['max_quantity'] ?? 0 ),
			'status'       => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'active' ) ),
		];

		if ( empty( $data['name'] ) ) {
			AdminNotice::error( __( 'Sample name is required.', 'samplehq-request-form' ) );
			return;
		}

		// Build custom fields JSON.
		$cf_keys       = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['cf_keys'] ?? [] ) ) );
		$cf_values     = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['cf_values'] ?? [] ) ) );
		$custom_fields = [];
		foreach ( $cf_keys as $i => $key ) {
			$key = trim( $key );
			if ( '' === $key ) {
				continue;
			}
			$custom_fields[] = [
				'key'   => $key,
				'value' => trim( $cf_values[ $i ] ?? '' ),
			];
		}
		$data['custom_fields'] = ! empty( $custom_fields ) ? wp_json_encode( $custom_fields ) : null;

		try {
			if ( $sample_id > 0 ) {
				$this->samples->update( $sample_id, $data );
			} else {
				$sample_id = $this->samples->create( $data );
			}

			// Sync categories.
			$cat_ids = array_map( 'absint', (array) ( $_POST['categories'] ?? [] ) );
			$this->category_map->sync( $sample_id, $cat_ids );

			// Sync images (gallery).
			$image_ids = array_map( 'absint', (array) ( $_POST['image_ids'] ?? [] ) );
			$image_ids = array_filter( $image_ids );
			$this->images->remove_all_for_sample( $sample_id );
			foreach ( $image_ids as $sort => $img_id ) {
				$this->images->add( $sample_id, $img_id, $sort );
			}

			AdminNotice::success( __( 'Sample saved.', 'samplehq-request-form' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=shqf-samples&action=edit&id=' . $sample_id ) );
			exit;
		} catch ( \Exception $e ) {
			AdminNotice::error( $e->getMessage() );
		}
	}
}
