<?php
/**
 * Dashboard admin page.
 *
 * Extracted from AdminMenu.php during Phase 1 refactoring.
 * Source: AdminMenu.php.backup lines 735-1210.
 *
 * @package SampleHQForm\Admin
 */

declare( strict_types=1 );

namespace SampleHQForm\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the plugin dashboard page.
 *
 * Shows overview stats, getting-started guide, quick actions,
 * recent forms/submissions, and the SampleHQ connection CTA.
 */
class DashboardPage {

	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function render(): void {
		global $wpdb;

		$samples_table     = new \SampleHQForm\Database\SamplesTable( $wpdb );
		$forms_table       = new \SampleHQForm\Database\FormsTable( $wpdb );
		$submissions_table = new \SampleHQForm\Database\SubmissionsTable( $wpdb );

		$sample_count    = $samples_table->count();
		$published_forms = $forms_table->count( [ 'status' => 'published' ] );
		$draft_forms     = $forms_table->count( [ 'status' => 'draft' ] );
		$form_count      = $published_forms + $draft_forms;
		$new_submissions = $submissions_table->count( [ 'status' => 'new' ] );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'SampleHQ Forms', 'samplehq-request-form' ) . '</h1>';
		AdminNotice::render();

		// Overview cards.
		echo '<div id="dashboard-widgets-wrap"><div id="dashboard-widgets" class="metabox-holder">';
		echo '<div class="postbox-container" style="width:100%;">';
		echo '<div class="shqf-dashboard-cards">';

		$total_submissions = $submissions_table->count();

		$this->render_dashboard_card(
			__( 'Samples', 'samplehq-request-form' ),
			(string) $sample_count,
			admin_url( 'admin.php?page=shqf-samples' ),
			__( 'Manage Samples', 'samplehq-request-form' ),
			'format-gallery',
			'#2271b1'
		);

		$this->render_dashboard_card(
			__( 'Forms', 'samplehq-request-form' ),
			(string) $form_count,
			admin_url( 'admin.php?page=shqf-forms' ),
			__( 'Manage Forms', 'samplehq-request-form' ),
			'feedback',
			'#0f766e'
		);

		$this->render_dashboard_card(
			__( 'Total Submissions', 'samplehq-request-form' ),
			(string) $total_submissions,
			admin_url( 'admin.php?page=shqf-submissions' ),
			__( 'View All', 'samplehq-request-form' ),
			'email-alt',
			'#9333ea'
		);

		$this->render_dashboard_card(
			__( 'Unread', 'samplehq-request-form' ),
			(string) $new_submissions,
			admin_url( 'admin.php?page=shqf-submissions&status=new' ),
			__( 'View New', 'samplehq-request-form' ),
			'bell',
			'#ea580c'
		);

		echo '</div>'; // Flex container.

		// Getting started guide (shown until all steps are complete).
		$has_samples = $sample_count > 0;
		$has_forms   = $form_count > 0;

		// Check if any form has been published (prerequisite for embedding).
		$has_embed = false;
		if ( $has_forms ) {
			$published = $forms_table->list_all(
				[
					'status' => 'published',
					'limit'  => 1,
				]
			);
			$has_embed = ! empty( $published );
		}

		if ( ! $has_samples || ! $has_forms || ! $has_embed ) {
			$this->render_getting_started( $has_samples, $has_forms, $has_embed, $forms_table );
		}

		// Two-column layout: Quick Actions + Recent Forms.
		echo '<div class="shqf-dashboard-columns">';

		// Quick actions.
		echo '<div class="postbox shqf-quick-actions">';
		echo '<div class="postbox-header"><h2 class="hndle">' . esc_html__( 'Quick Actions', 'samplehq-request-form' ) . '</h2></div>';
		echo '<div class="inside">';
		echo '<div class="shqf-action-cards">';

		$this->render_action_card(
			admin_url( 'admin.php?page=shqf-samples&action=new' ),
			'format-gallery',
			__( 'Add Sample', 'samplehq-request-form' ),
			__( 'Add a new product sample to your library.', 'samplehq-request-form' )
		);
		$this->render_action_card(
			admin_url( 'admin.php?page=shqf-forms&action=templates' ),
			'feedback',
			__( 'Create Form', 'samplehq-request-form' ),
			__( 'Build a new sample request form.', 'samplehq-request-form' )
		);
		$this->render_action_card(
			admin_url( 'admin.php?page=shqf-samples&action=import' ),
			'upload',
			__( 'Import CSV', 'samplehq-request-form' ),
			__( 'Bulk import samples from a CSV file.', 'samplehq-request-form' )
		);

		echo '</div>';
		echo '</div></div>';

		// Recent forms.
		$this->render_recent_forms( $forms_table );

		echo '</div>'; // .shqf-dashboard-columns

		// Recent submissions.
		$this->render_recent_submissions( $submissions_table, $forms_table );

		// SampleHQ connection CTA (dismissible via user meta).
		$this->render_shq_cta();

		echo '</div></div></div>'; // Postbox-container, dashboard-widgets, dashboard-widgets-wrap.

		echo '</div>'; // .wrap
	}

	/**
	 * Get the unread submission count (with transient caching).
	 *
	 * Used by AdminMenu::add_menus() for the submissions menu badge.
	 *
	 * @return int
	 */
	public function get_unread_submission_count(): int {
		$cached = get_transient( 'shqf_unread_count' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'shqf_submissions';

		// Count submissions that are status=new AND is_read=0.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, not user input.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'new' AND is_read = 0" );

		set_transient( 'shqf_unread_count', $count, 60 );

		return $count;
	}

	/**
	 * Render the recent submissions section on the dashboard.
	 *
	 * @param \SampleHQForm\Database\SubmissionsTable $submissions Submissions repository.
	 * @param \SampleHQForm\Database\FormsTable       $forms       Forms repository.
	 * @return void
	 */
	private function render_recent_submissions(
		\SampleHQForm\Database\SubmissionsTable $submissions,
		\SampleHQForm\Database\FormsTable $forms
	): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Dashboard filter, no state change.
		$filter_form_id = absint( $_GET['dashboard_form'] ?? 0 );

		$query = [
			'limit'   => 5,
			'orderby' => 'created_at',
			'order'   => 'DESC',
		];
		if ( $filter_form_id > 0 ) {
			$query['form_id'] = $filter_form_id;
		}

		$recent = $submissions->list_all( $query );

		// Build form list for dropdown (only forms that have submissions).
		$all_forms    = $forms->list_all( [ 'limit' => 100 ] );
		$form_options = [];
		foreach ( $all_forms as $f ) {
			$form_options[ (int) $f['id'] ] = $f['title'];
		}

		echo '<div class="postbox shqf-recent-submissions">';
		echo '<div class="postbox-header"><h2 class="hndle">';
		echo esc_html__( 'Recent Submissions', 'samplehq-request-form' );
		echo '</h2></div>';
		echo '<div class="inside">';

		// Form filter dropdown.
		if ( count( $form_options ) > 1 ) {
			echo '<div class="shqf-dashboard-filter">';
			echo '<label for="shqf-dashboard-form-filter" class="screen-reader-text">';
			echo esc_html__( 'Filter by form', 'samplehq-request-form' );
			echo '</label>';
			echo '<select id="shqf-dashboard-form-filter" onchange="if(this.value){location.href=\'';
			echo esc_url( admin_url( 'admin.php?page=shqf-dashboard' ) );
			echo '&dashboard_form=\'+this.value}else{location.href=\'';
			echo esc_url( admin_url( 'admin.php?page=shqf-dashboard' ) );
			echo '\'}">';
			echo '<option value="">' . esc_html__( 'All Forms', 'samplehq-request-form' ) . '</option>';
			foreach ( $form_options as $fid => $ftitle ) {
				echo '<option value="' . esc_attr( (string) $fid ) . '"' . selected( $filter_form_id, $fid, false ) . '>';
				echo esc_html( $ftitle ) . '</option>';
			}
			echo '</select>';
			echo '</div>';
		}

		if ( empty( $recent ) ) {
			echo '<p class="shqf-empty-state">';
			echo esc_html__( 'No submissions yet. Once visitors submit your forms, they will appear here.', 'samplehq-request-form' );
			echo '</p>';
			echo '</div></div>';
			return;
		}

		// Cache form names.
		$form_names = [];

		echo '<table class="widefat striped shqf-recent-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Email', 'samplehq-request-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Name', 'samplehq-request-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Form', 'samplehq-request-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'samplehq-request-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'samplehq-request-form' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $recent as $row ) {
			$sub_id  = (int) $row['id'];
			$form_id = (int) $row['form_id'];

			// Look up form title (cached).
			if ( ! isset( $form_names[ $form_id ] ) ) {
				$form                   = $forms->get( $form_id );
				$form_names[ $form_id ] = $form['title'] ?? __( 'Unknown', 'samplehq-request-form' );
			}

			$view_url  = admin_url( 'admin.php?page=shqf-submissions&action=view&id=' . $sub_id );
			$email     = $row['email'] ?? '--';
			$name      = trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) );
			$timestamp = ! empty( $row['created_at'] ) ? strtotime( $row['created_at'] ) : false;
			$wp_date   = ( false !== $timestamp ) ? wp_date( get_option( 'date_format' ), $timestamp ) : false;
			$date      = $wp_date ? (string) $wp_date : '--';
			$status    = $row['status'] ?? 'new';
			$is_unread = empty( $row['is_read'] );

			echo $is_unread ? '<tr class="shqf-submission-unread">' : '<tr>';
			echo '<td><a href="' . esc_url( $view_url ) . '">' . ( $is_unread ? '<strong>' : '' ) . esc_html( $email ) . ( $is_unread ? '</strong>' : '' ) . '</a></td>';
			echo '<td><a href="' . esc_url( $view_url ) . '">' . esc_html( ! empty( $name ) ? $name : '--' ) . '</a></td>';
			echo '<td><a href="' . esc_url( $view_url ) . '">' . esc_html( $form_names[ $form_id ] ) . '</a></td>';
			echo '<td><a href="' . esc_url( $view_url ) . '">' . esc_html( $date ) . '</a></td>';
			echo '<td><a href="' . esc_url( $view_url ) . '"><span class="shqf-status shqf-status--' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span></a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p class="shqf-recent-footer"><a href="' . esc_url( admin_url( 'admin.php?page=shqf-submissions' ) ) . '">';
		echo esc_html__( 'View all submissions', 'samplehq-request-form' ) . ' &rarr;</a></p>';
		echo '</div></div>';
	}

	/**
	 * Render a dashboard stat card.
	 *
	 * @param string $label  Card label.
	 * @param string $value  Stat value.
	 * @param string $url    Link URL.
	 * @param string $link   Link text.
	 * @param string $icon   Dashicons class suffix (e.g. 'format-gallery').
	 * @param string $accent CSS color for the left accent border.
	 * @return void
	 */
	private function render_dashboard_card( string $label, string $value, string $url, string $link, string $icon = '', string $accent = '' ): void {
		echo ! empty( $accent )
			? '<div class="postbox shqf-dashboard-card" style="border-left-color:' . esc_attr( $accent ) . ';">'
			: '<div class="postbox shqf-dashboard-card">';
		echo '<div class="inside shqf-dashboard-card__inner">';

		if ( ! empty( $icon ) ) {
			echo '<span class="dashicons dashicons-' . esc_attr( $icon ) . ' shqf-dashboard-card__icon" style="color:' . esc_attr( $accent ) . ';"></span>';
		}

		echo '<div class="shqf-dashboard-card__value">' . esc_html( $value ) . '</div>';
		echo '<div class="shqf-dashboard-card__label">' . esc_html( $label ) . '</div>';
		echo '<a href="' . esc_url( $url ) . '" class="button button-small">' . esc_html( $link ) . '</a>';
		echo '</div></div>';
	}

	/**
	 * Render a quick action card.
	 *
	 * @param string $url         Action URL.
	 * @param string $icon        Dashicons class suffix.
	 * @param string $title       Card title.
	 * @param string $description Short description.
	 * @return void
	 */
	private function render_action_card( string $url, string $icon, string $title, string $description ): void {
		echo '<a href="' . esc_url( $url ) . '" class="shqf-action-card">';
		echo '<span class="dashicons dashicons-' . esc_attr( $icon ) . ' shqf-action-card__icon"></span>';
		echo '<span class="shqf-action-card__text">';
		echo '<span class="shqf-action-card__title">' . esc_html( $title ) . '</span>';
		echo '<span class="shqf-action-card__desc">' . esc_html( $description ) . '</span>';
		echo '</span>';
		echo '</a>';
	}

	/**
	 * Render the recent forms section on the dashboard.
	 *
	 * @param \SampleHQForm\Database\FormsTable $forms Forms repository.
	 * @return void
	 */
	private function render_recent_forms( \SampleHQForm\Database\FormsTable $forms ): void {
		$recent = $forms->list_all(
			[
				'limit'   => 3,
				'orderby' => 'updated_at',
				'order'   => 'DESC',
			]
		);

		echo '<div class="postbox shqf-recent-forms">';
		echo '<div class="postbox-header"><h2 class="hndle">';
		echo esc_html__( 'Recent Forms', 'samplehq-request-form' );
		echo '</h2></div>';
		echo '<div class="inside">';

		if ( empty( $recent ) ) {
			echo '<p class="shqf-empty-state">';
			echo esc_html__( 'No forms yet. Create your first form to get started.', 'samplehq-request-form' );
			echo '</p>';
			echo '</div></div>';
			return;
		}

		echo '<ul class="shqf-recent-forms-list">';
		foreach ( $recent as $form ) {
			$form_id  = (int) $form['id'];
			$edit_url = admin_url( 'admin.php?page=shqf-forms&action=edit&id=' . $form_id );
			$status   = $form['status'] ?? 'draft';

			echo '<li class="shqf-recent-form-item">';
			echo '<div class="shqf-recent-form-item__info">';
			echo '<a href="' . esc_url( $edit_url ) . '" class="shqf-recent-form-item__title">' . esc_html( $form['title'] ?? '' ) . '</a>';
			echo '<span class="shqf-status shqf-status--' . esc_attr( $status ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
			echo '</div>';
			echo '<div class="shqf-recent-form-item__actions">';
			echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'samplehq-request-form' ) . '</a>';
			echo '</div>';
			echo '</li>';
		}
		echo '</ul>';

		echo '</div></div>';
	}

	/**
	 * Render the getting started guide with progress tracking.
	 *
	 * @param bool                              $has_samples Whether any samples exist.
	 * @param bool                              $has_forms   Whether any forms exist.
	 * @param bool                              $has_embed   Whether a published form exists.
	 * @param \SampleHQForm\Database\FormsTable $forms       Forms repository.
	 * @return void
	 */
	private function render_getting_started( bool $has_samples, bool $has_forms, bool $has_embed, \SampleHQForm\Database\FormsTable $forms ): void {
		$completed = (int) $has_samples + (int) $has_forms + (int) $has_embed;

		echo '<div class="postbox shqf-getting-started">';
		echo '<div class="postbox-header"><h2 class="hndle">';
		echo esc_html__( 'Getting Started', 'samplehq-request-form' );
		echo '<span class="shqf-getting-started__progress">' . esc_html( $completed . '/3' ) . '</span>';
		echo '</h2></div>';
		echo '<div class="inside">';

		echo '<ol class="shqf-getting-started-steps">';

		// Step 1: Add samples.
		$done_class = $has_samples ? ' shqf-step--done' : '';
		echo '<li class="shqf-step' . esc_attr( $done_class ) . '">';
		if ( $has_samples ) {
			echo '<span class="dashicons dashicons-yes-alt shqf-step__check"></span>';
		} else {
			echo '<span class="shqf-step__number">1</span>';
		}
		echo '<div class="shqf-step__content">';
		echo '<strong>' . esc_html__( 'Add samples to your library', 'samplehq-request-form' ) . '</strong>';
		echo '<p>' . esc_html__( 'Create your product samples with images, descriptions, and categories.', 'samplehq-request-form' ) . '</p>';
		if ( ! $has_samples ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-samples&action=new' ) ) . '" class="button button-small button-primary">';
			echo esc_html__( 'Add Sample', 'samplehq-request-form' ) . '</a>';
		}
		echo '</div></li>';

		// Step 2: Create a form.
		$done_class = $has_forms ? ' shqf-step--done' : '';
		echo '<li class="shqf-step' . esc_attr( $done_class ) . '">';
		if ( $has_forms ) {
			echo '<span class="dashicons dashicons-yes-alt shqf-step__check"></span>';
		} else {
			echo '<span class="shqf-step__number">2</span>';
		}
		echo '<div class="shqf-step__content">';
		echo '<strong>' . esc_html__( 'Create a form', 'samplehq-request-form' ) . '</strong>';
		echo '<p>' . esc_html__( 'Choose a template or build from scratch using the visual form builder.', 'samplehq-request-form' ) . '</p>';
		if ( ! $has_forms ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-forms&action=templates' ) ) . '" class="button button-small button-primary">';
			echo esc_html__( 'Create Form', 'samplehq-request-form' ) . '</a>';
		}
		echo '</div></li>';

		// Step 3: Embed on site.
		$done_class = $has_embed ? ' shqf-step--done' : '';
		echo '<li class="shqf-step' . esc_attr( $done_class ) . '">';
		if ( $has_embed ) {
			echo '<span class="dashicons dashicons-yes-alt shqf-step__check"></span>';
		} else {
			echo '<span class="shqf-step__number">3</span>';
		}
		echo '<div class="shqf-step__content">';
		echo '<strong>' . esc_html__( 'Publish and embed on your site', 'samplehq-request-form' ) . '</strong>';
		echo '<p>' . esc_html__( 'Set a form to Published, then add it to any page with the Gutenberg block or shortcode.', 'samplehq-request-form' ) . '</p>';

		// Show the shortcode for the first form if one exists.
		if ( $has_forms ) {
			$first_form = $forms->list_all( [ 'limit' => 1 ] );
			if ( ! empty( $first_form ) ) {
				$fid = (int) $first_form[0]['id'];
				echo '<code>[samplehq_form id="' . esc_attr( (string) $fid ) . '"]</code>';
			}
		} else {
			echo '<code>[samplehq_form id="123"]</code>';
		}

		echo '</div></li>';

		echo '</ol>';
		echo '</div></div>';
	}

	/**
	 * Render the SampleHQ connection CTA card.
	 *
	 * Dismissible via user meta. Only shown on the plugin's own dashboard page.
	 * Compliant with wordpress.org guidelines (no persistent nags, no site-wide notices).
	 *
	 * @return void
	 */
	private function render_shq_cta(): void {
		if ( '1' === get_user_meta( get_current_user_id(), 'shqf_dismissed_cta', true ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			admin_url( 'admin.php?page=shqf-dashboard&action=dismiss_shq_cta' ),
			'shqf_dismiss_cta'
		);

		echo '<div class="postbox shqf-cta-card">';
		echo '<div class="inside">';
		echo '<div class="shqf-cta-card__content">';
		echo '<h3>' . esc_html__( 'SampleHQ Platform', 'samplehq-request-form' ) . '</h3>';
		echo '<p>' . esc_html__( 'Connect this plugin to your SampleHQ workspace to sync submissions, manage shipping, and integrate with your CRM.', 'samplehq-request-form' ) . '</p>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=shqf-settings&tab=connection' ) ) . '" class="button">';
		echo esc_html__( 'Connection Settings', 'samplehq-request-form' ) . '</a>';
		echo '</div>';
		echo '<a href="' . esc_url( $dismiss_url ) . '" class="shqf-cta-card__dismiss" aria-label="' . esc_attr__( 'Dismiss', 'samplehq-request-form' ) . '">';
		echo '<span class="dashicons dashicons-no-alt"></span>';
		echo '<span class="screen-reader-text">' . esc_html__( 'Dismiss', 'samplehq-request-form' ) . '</span>';
		echo '</a>';
		echo '</div></div>';
	}
}
