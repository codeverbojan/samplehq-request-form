<?php
/**
 * Categories admin page.
 *
 * Extracted from AdminMenu.php during Phase 3 refactoring.
 * Source: AdminMenu.php.backup lines 616-636, 2646-2902.
 *
 * @package SampleHQForm\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the sample categories management page.
 *
 * Two-column layout: add/edit form on left, CategoryListTable on right.
 * Handles category CRUD, bulk delete, re-parenting, and circular reference prevention.
 */
class CategoriesPage {

	/**
	 * Render the categories page (public entry point from submenu).
	 *
	 * @return void
	 */
	public function render(): void {
		global $wpdb;
		$categories   = new \SampleHQForm\Database\SampleCategoriesTable( $wpdb );
		$category_map = new \SampleHQForm\Database\SampleCategoryMapTable( $wpdb );
		$this->render_categories( $categories, $category_map );
	}

	/**
	 * Handle category save/delete on admin_init (before output).
	 *
	 * @return void
	 */
	public function handle_save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- nonce verified in handle_category_actions.
		$has_post_save = isset( $_POST['shqf_cat_nonce'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_delete = ! empty( $_GET['delete_cat'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in handle_category_actions.
		$has_bulk = isset( $_POST['action'] ) && 'bulk_delete' === sanitize_text_field( wp_unslash( $_POST['action'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$has_bulk2 = isset( $_POST['action2'] ) && 'bulk_delete' === sanitize_text_field( wp_unslash( $_POST['action2'] ) );

		if ( ! $has_post_save && ! $has_delete && ! $has_bulk && ! $has_bulk2 ) {
			return;
		}

		global $wpdb;

		$categories   = new \SampleHQForm\Database\SampleCategoriesTable( $wpdb );
		$category_map = new \SampleHQForm\Database\SampleCategoryMapTable( $wpdb );

		$this->handle_category_actions( $categories, $category_map );
	}

	/**
	 * Render the categories management page.
	 *
	 * Two-column layout: add/edit form on left, list on right.
	 * Follows the WordPress taxonomy management pattern.
	 *
	 * @param \SampleHQForm\Database\SampleCategoriesTable  $categories   Categories repo.
	 * @param \SampleHQForm\Database\SampleCategoryMapTable $category_map Category map repo.
	 * @return void
	 */
	public function render_categories(
		\SampleHQForm\Database\SampleCategoriesTable $categories,
		\SampleHQForm\Database\SampleCategoryMapTable $category_map
	): void {
		// Actions (save/delete) handled by handle_save() on admin_init.

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id  = absint( $_GET['edit_cat'] ?? 0 );
		$edit_cat = $edit_id > 0 ? $categories->get( $edit_id ) : null;

		$all_cats = $categories->list_all();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Sample Categories', 'samplehq-request-form' ) . '</h1>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-samples' ) ) . '" class="page-title-action">';
		echo esc_html__( 'Back to Samples', 'samplehq-request-form' ) . '</a>';
		echo '<hr class="wp-header-end">';
		AdminNotice::render();

		echo '<div id="col-container" class="wp-clearfix">';

		// Left column: add/edit form.
		echo '<div id="col-left"><div class="col-wrap">';
		$form_title = null !== $edit_cat
			? __( 'Edit Category', 'samplehq-request-form' )
			: __( 'Add New Category', 'samplehq-request-form' );
		echo '<div class="form-wrap">';
		echo '<h2>' . esc_html( $form_title ) . '</h2>';

		echo '<form method="post" action="">';
		wp_nonce_field( 'shqf_save_category', 'shqf_cat_nonce' );

		if ( null !== $edit_cat ) {
			echo '<input type="hidden" name="category_id" value="' . esc_attr( (string) $edit_id ) . '" />';
		}

		echo '<div class="form-field form-required">';
		echo '<label for="shqf-cat-name">' . esc_html__( 'Name', 'samplehq-request-form' ) . '</label>';
		echo '<input type="text" id="shqf-cat-name" name="cat_name" value="' . esc_attr( $edit_cat['name'] ?? '' ) . '" required />';
		echo '<p>' . esc_html__( 'The name is how it appears on your site.', 'samplehq-request-form' ) . '</p>';
		echo '</div>';

		echo '<div class="form-field">';
		echo '<label for="shqf-cat-slug">' . esc_html__( 'Slug', 'samplehq-request-form' ) . '</label>';
		echo '<input type="text" id="shqf-cat-slug" name="cat_slug" value="' . esc_attr( $edit_cat['slug'] ?? '' ) . '" />';
		echo '<p>' . esc_html__( 'The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'samplehq-request-form' ) . '</p>';
		echo '</div>';

		// Parent category dropdown.
		$current_parent = (int) ( $edit_cat['parent_id'] ?? 0 );
		$edit_cat_id    = null !== $edit_cat ? (int) $edit_cat['id'] : 0;
		echo '<div class="form-field">';
		echo '<label for="shqf-cat-parent">' . esc_html__( 'Parent Category', 'samplehq-request-form' ) . '</label>';
		echo '<select id="shqf-cat-parent" name="cat_parent_id">';
		echo '<option value="0">' . esc_html__( 'None', 'samplehq-request-form' ) . '</option>';
		foreach ( $all_cats as $opt_cat ) {
			$opt_id     = (int) $opt_cat['id'];
			$opt_parent = (int) ( $opt_cat['parent_id'] ?? 0 );
			// Don't allow a category to be its own parent.
			if ( $opt_id === $edit_cat_id ) {
				continue;
			}
			$prefix   = $opt_parent > 0 ? str_repeat( "\xC2\xA0", 3 ) : '';
			$selected = selected( $current_parent, $opt_id, false );
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $selected from selected(), $prefix is non-breaking spaces.
			echo '<option value="' . esc_attr( (string) $opt_id ) . '"' . $selected . '>'
				. $prefix . esc_html( $opt_cat['name'] ) . '</option>';
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</select>';
		echo '<p>' . esc_html__( 'Categories can have a hierarchy. You might have a Textiles category, and under that have categories for Fabrics and Threads.', 'samplehq-request-form' ) . '</p>';
		echo '</div>';

		echo '<div class="form-field">';
		echo '<label for="shqf-cat-desc">' . esc_html__( 'Description', 'samplehq-request-form' ) . '</label>';
		echo '<textarea id="shqf-cat-desc" name="cat_description" rows="4">' . esc_textarea( $edit_cat['description'] ?? '' ) . '</textarea>';
		echo '<p>' . esc_html__( 'The description is not prominent by default; however, some form templates may show it.', 'samplehq-request-form' ) . '</p>';
		echo '</div>';

		$btn_label = null !== $edit_cat
			? __( 'Update Category', 'samplehq-request-form' )
			: __( 'Add Category', 'samplehq-request-form' );
		submit_button( $btn_label, 'primary', 'submit', true );

		if ( null !== $edit_cat ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-categories' ) ) . '" class="button">';
			echo esc_html__( 'Cancel', 'samplehq-request-form' ) . '</a>';
		}

		echo '</form>';
		echo '</div>'; // .form-wrap
		echo '</div></div>'; // .col-wrap, #col-left

		// Right column: categories list table.
		echo '<div id="col-right"><div class="col-wrap">';

		$list_table = new CategoryListTable( $categories, $category_map );
		$list_table->prepare_items();

		echo '<form method="post">';
		$list_table->display();
		echo '</form>';

		echo '</div></div>'; // .col-wrap, #col-right

		echo '</div>'; // #col-container
		echo '</div>'; // .wrap
	}

	/**
	 * Handle category add/edit/delete actions.
	 *
	 * @param \SampleHQForm\Database\SampleCategoriesTable  $categories   Categories repo.
	 * @param \SampleHQForm\Database\SampleCategoryMapTable $category_map Category map repo.
	 * @return void
	 */
	private function handle_category_actions(
		\SampleHQForm\Database\SampleCategoriesTable $categories,
		\SampleHQForm\Database\SampleCategoryMapTable $category_map
	): void {
		// Delete category.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$delete_id = absint( $_GET['delete_cat'] ?? 0 );
		if ( $delete_id > 0 ) {
			check_admin_referer( 'shqf_delete_category_' . $delete_id );
			$this->delete_category( $delete_id, $categories, $category_map );
			AdminNotice::success( __( 'Category deleted.', 'samplehq-request-form' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=shqf-categories' ) );
			exit;
		}

		// Save category (add or update).
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) {
			return;
		}

		// Bulk delete from list table (top or bottom dropdown).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$bulk_action = sanitize_text_field( wp_unslash( $_POST['action'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$bulk_action2 = sanitize_text_field( wp_unslash( $_POST['action2'] ?? '' ) );
		if ( 'bulk_delete' === $bulk_action || 'bulk_delete' === $bulk_action2 ) {
			check_admin_referer( 'bulk-categories' );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$ids     = array_map( 'absint', (array) ( $_POST['cat_ids'] ?? [] ) );
			$deleted = 0;
			foreach ( $ids as $cid ) {
				if ( $cid > 0 ) {
					$this->delete_category( $cid, $categories, $category_map );
					++$deleted;
				}
			}
			if ( $deleted > 0 ) {
				/* translators: %d: number of categories deleted */
				AdminNotice::success( sprintf( __( '%d categories deleted.', 'samplehq-request-form' ), $deleted ) );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=shqf-categories' ) );
			exit;
		}

		if ( ! isset( $_POST['shqf_cat_nonce'] ) || ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['shqf_cat_nonce'] ) ),
			'shqf_save_category'
		) ) {
			return;
		}

		$name = sanitize_text_field( wp_unslash( $_POST['cat_name'] ?? '' ) );
		if ( empty( $name ) ) {
			AdminNotice::error( __( 'Category name is required.', 'samplehq-request-form' ) );
			return;
		}

		$data = [
			'name'        => $name,
			'slug'        => sanitize_text_field( wp_unslash( $_POST['cat_slug'] ?? '' ) ),
			'description' => sanitize_textarea_field( wp_unslash( $_POST['cat_description'] ?? '' ) ),
			'parent_id'   => absint( $_POST['cat_parent_id'] ?? 0 ),
		];

		$category_id = absint( $_POST['category_id'] ?? 0 );

		// Prevent circular parent references.
		if ( $category_id > 0 && $data['parent_id'] > 0 ) {
			$check_id = $data['parent_id'];
			$all      = $categories->list_all();
			$by_id    = [];
			foreach ( $all as $c ) {
				$by_id[ (int) $c['id'] ] = $c;
			}
			while ( $check_id > 0 ) {
				if ( $check_id === $category_id ) {
					$data['parent_id'] = 0;
					AdminNotice::error( __( 'Cannot set a descendant as the parent category.', 'samplehq-request-form' ) );
					break;
				}
				$check_id = (int) ( $by_id[ $check_id ]['parent_id'] ?? 0 );
			}
		}

		try {
			if ( $category_id > 0 ) {
				$categories->update( $category_id, $data );
				AdminNotice::success( __( 'Category updated.', 'samplehq-request-form' ) );
			} else {
				$categories->create( $data );
				AdminNotice::success( __( 'Category added.', 'samplehq-request-form' ) );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=shqf-categories' ) );
			exit;
		} catch ( \Exception $e ) {
			AdminNotice::error( $e->getMessage() );
		}
	}

	/**
	 * Delete a category, re-parenting its children and removing sample mappings.
	 *
	 * @param int                                           $cat_id      Category ID to delete.
	 * @param \SampleHQForm\Database\SampleCategoriesTable  $categories  Categories repo.
	 * @param \SampleHQForm\Database\SampleCategoryMapTable $category_map Category map repo.
	 * @return void
	 */
	private function delete_category(
		int $cat_id,
		\SampleHQForm\Database\SampleCategoriesTable $categories,
		\SampleHQForm\Database\SampleCategoryMapTable $category_map
	): void {
		// Re-parent children to the deleted category's parent (or 0).
		$deleted_cat = $categories->get( $cat_id );
		$new_parent  = (int) ( $deleted_cat['parent_id'] ?? 0 );

		$all_cats = $categories->list_all();
		foreach ( $all_cats as $cat ) {
			if ( (int) ( $cat['parent_id'] ?? 0 ) === $cat_id ) {
				$categories->update( (int) $cat['id'], [ 'parent_id' => $new_parent ] );
			}
		}

		$category_map->remove_all_for_category( $cat_id );
		$categories->delete( $cat_id );
	}
}
