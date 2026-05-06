<?php
/**
 * Pre-built form template configurations.
 *
 * @package SampleHQForm\Forms
 */

declare( strict_types=1 );

namespace SampleHQForm\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides pre-built form configurations for the template selector.
 *
 * Each template defines a complete form config (layout, fields, appearance,
 * behavior) that gets passed to FormsTable::create() when the user picks one.
 */
class FormTemplates {

	/**
	 * Get all available templates.
	 *
	 * @return array<string, array<string, mixed>> Templates keyed by slug.
	 */
	public static function get_all(): array {
		return [
			'wizard'    => self::wizard(),
			'grid'      => self::grid(),
			'checklist' => self::checklist(),
			'blank'     => self::blank(),
		];
	}

	/**
	 * Get a single template by slug.
	 *
	 * @param string $slug Template identifier.
	 * @return array<string, mixed>|null Template data or null.
	 */
	public static function get( string $slug ): ?array {
		$all = self::get_all();
		return $all[ $slug ] ?? null;
	}

	/**
	 * Sample Request Wizard -- 3-step flow.
	 *
	 * @return array<string, mixed>
	 */
	private static function wizard(): array {
		return [
			'slug'        => 'wizard',
			'title'       => __( 'Sample Request Wizard', 'samplehq-request-form' ),
			'description' => __( '3-step flow: select samples, enter details, review and submit. Best for larger catalogs.', 'samplehq-request-form' ),
			'icon'        => 'layers',
			'form_title'  => __( 'Request a Sample', 'samplehq-request-form' ),
			'config'      => [
				'schema_version' => 1,
				'layout'         => 'wizard',
				'title'          => __( 'Request a Sample', 'samplehq-request-form' ),
				'subtitle'       => __( 'Select the samples you\'d like to receive', 'samplehq-request-form' ),
				'steps'          => [
					[
						'label'    => __( 'Samples', 'samplehq-request-form' ),
						'title'    => __( 'Request a Sample', 'samplehq-request-form' ),
						'subtitle' => __( 'Fill out the form below and we will ship your samples within 2 business days.', 'samplehq-request-form' ),
					],
					[
						'label'    => __( 'Your Info', 'samplehq-request-form' ),
						'title'    => __( 'Your Information', 'samplehq-request-form' ),
						'subtitle' => __( 'Tell us where to send your samples.', 'samplehq-request-form' ),
					],
					[
						'label'    => __( 'Review', 'samplehq-request-form' ),
						'title'    => __( 'Review & Submit', 'samplehq-request-form' ),
						'subtitle' => __( 'Confirm your selections below.', 'samplehq-request-form' ),
					],
				],
				'fields'         => [
					self::sample_picker_field( 'grid', 0 ),
					self::row_2col(
						self::text_field( 'first_name', __( 'First Name', 'samplehq-request-form' ), true, 1 ),
						self::text_field( 'last_name', __( 'Last Name', 'samplehq-request-form' ), true, 1 ),
						1
					),
					self::email_field( 1 ),
					self::text_field( 'company', __( 'Company', 'samplehq-request-form' ), false, 1 ),
					self::phone_field( 1 ),
				],
				'appearance'     => self::default_appearance(),
				'behavior'       => self::default_behavior(),
			],
		];
	}

	/**
	 * Sample Grid Form -- single page with card grid.
	 *
	 * @return array<string, mixed>
	 */
	private static function grid(): array {
		return [
			'slug'        => 'grid',
			'title'       => __( 'Sample Grid Form', 'samplehq-request-form' ),
			'description' => __( 'Single page with sample cards in a grid, contact fields below. Great for small catalogs.', 'samplehq-request-form' ),
			'icon'        => 'layout-grid',
			'form_title'  => __( 'Request a Sample', 'samplehq-request-form' ),
			'config'      => [
				'schema_version' => 1,
				'layout'         => 'grid',
				'title'          => __( 'Request a Sample', 'samplehq-request-form' ),
				'subtitle'       => __( 'Select samples and fill out your details below.', 'samplehq-request-form' ),
				'fields'         => [
					self::sample_picker_field( 'grid', 0 ),
					self::row_2col(
						self::text_field( 'first_name', __( 'First Name', 'samplehq-request-form' ), true, 0 ),
						self::text_field( 'last_name', __( 'Last Name', 'samplehq-request-form' ), true, 0 ),
						0
					),
					self::email_field( 0 ),
					self::row_2col(
						self::text_field( 'company', __( 'Company', 'samplehq-request-form' ), false, 0 ),
						self::phone_field( 0 ),
						0
					),
				],
				'appearance'     => self::default_appearance(),
				'behavior'       => self::default_behavior(),
			],
		];
	}

	/**
	 * Sample Checklist Form -- compact list with checkboxes.
	 *
	 * @return array<string, mixed>
	 */
	private static function checklist(): array {
		return [
			'slug'        => 'checklist',
			'title'       => __( 'Sample Checklist Form', 'samplehq-request-form' ),
			'description' => __( 'Compact checkbox list with contact fields. Ideal for quick sample selection.', 'samplehq-request-form' ),
			'icon'        => 'list',
			'form_title'  => __( 'Request a Sample', 'samplehq-request-form' ),
			'config'      => [
				'schema_version' => 1,
				'layout'         => 'list',
				'title'          => __( 'Request a Sample', 'samplehq-request-form' ),
				'subtitle'       => __( 'Check the samples you need and enter your details.', 'samplehq-request-form' ),
				'fields'         => [
					self::sample_picker_field( 'list', 0 ),
					self::row_2col(
						self::text_field( 'first_name', __( 'First Name', 'samplehq-request-form' ), true, 0 ),
						self::text_field( 'last_name', __( 'Last Name', 'samplehq-request-form' ), true, 0 ),
						0
					),
					self::email_field( 0 ),
				],
				'appearance'     => self::default_appearance(),
				'behavior'       => self::default_behavior(),
			],
		];
	}

	/**
	 * Start from Scratch -- blank form.
	 *
	 * @return array<string, mixed>
	 */
	private static function blank(): array {
		return [
			'slug'        => 'blank',
			'title'       => __( 'Start from Scratch', 'samplehq-request-form' ),
			'description' => __( 'Empty form with the drag-and-drop builder. Full control over every field.', 'samplehq-request-form' ),
			'icon'        => 'plus',
			'form_title'  => __( 'Untitled Form', 'samplehq-request-form' ),
			'config'      => [
				'schema_version' => 1,
				'fields'         => [],
				'appearance'     => self::default_appearance(),
				'behavior'       => self::default_behavior(),
			],
		];
	}

	/**
	 * Generate a unique field ID.
	 *
	 * @return string Unique ID like "f_abc123".
	 */
	private static function field_id(): string {
		return 'f_' . substr( md5( (string) wp_rand() ), 0, 8 );
	}

	/**
	 * Generate a unique row ID.
	 *
	 * @return string Unique ID like "row_abc123".
	 */
	private static function row_id(): string {
		return 'row_' . substr( md5( (string) wp_rand() ), 0, 8 );
	}

	/**
	 * Create a 2-column row group wrapping the given fields.
	 *
	 * @param array<string, mixed> $left_field  Left column field.
	 * @param array<string, mixed> $right_field Right column field.
	 * @param int                  $step_index  Step index for multi-step forms.
	 * @return array<string, mixed> Row config.
	 */
	private static function row_2col( array $left_field, array $right_field, int $step_index = 0 ): array {
		return [
			'id'         => self::row_id(),
			'type'       => 'row',
			'enabled'    => true,
			'step_index' => $step_index,
			'columns'    => [
				[
					'width'  => '1fr',
					'fields' => [ $left_field ],
				],
				[
					'width'  => '1fr',
					'fields' => [ $right_field ],
				],
			],
		];
	}

	/**
	 * Text field config.
	 *
	 * @param string $key        Field key.
	 * @param string $label      Field label.
	 * @param bool   $required   Whether required.
	 * @param int    $step_index Step index for multi-step.
	 * @return array<string, mixed>
	 */
	private static function text_field( string $key, string $label, bool $required, int $step_index ): array {
		return [
			'id'         => self::field_id(),
			'type'       => 'text',
			'key'        => $key,
			'label'      => $label,
			'required'   => $required,
			'enabled'    => true,
			'step_index' => $step_index,
		];
	}

	/**
	 * Email field config.
	 *
	 * @param int $step_index Step index.
	 * @return array<string, mixed>
	 */
	private static function email_field( int $step_index ): array {
		return [
			'id'         => self::field_id(),
			'type'       => 'email',
			'key'        => 'email',
			'label'      => __( 'Email', 'samplehq-request-form' ),
			'required'   => true,
			'enabled'    => true,
			'step_index' => $step_index,
		];
	}

	/**
	 * Phone field config.
	 *
	 * @param int $step_index Step index.
	 * @return array<string, mixed>
	 */
	private static function phone_field( int $step_index ): array {
		return [
			'id'         => self::field_id(),
			'type'       => 'phone',
			'key'        => 'phone',
			'label'      => __( 'Phone', 'samplehq-request-form' ),
			'required'   => false,
			'enabled'    => true,
			'step_index' => $step_index,
		];
	}

	/**
	 * Sample picker field config.
	 *
	 * @param string $layout     Layout: 'grid' or 'list'.
	 * @param int    $step_index Step index.
	 * @return array<string, mixed>
	 */
	private static function sample_picker_field( string $layout, int $step_index ): array {
		return [
			'id'         => self::field_id(),
			'type'       => 'sample_picker',
			'key'        => 'samples',
			'label'      => __( 'Select Samples', 'samplehq-request-form' ),
			'required'   => true,
			'enabled'    => true,
			'step_index' => $step_index,
			'config'     => [
				'source'               => 'library',
				'filter'               => [ 'mode' => 'all' ],
				'max_selections'       => 5,
				'allow_quantity'       => true,
				'default_max_quantity' => 3,
				'layout'               => $layout,
				'show_images'          => true,
				'show_descriptions'    => true,
			],
		];
	}

	/**
	 * Default appearance config.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_appearance(): array {
		return [
			'primary_color'     => '#0F766E',
			'button_color'      => '#0F766E',
			'button_text_color' => '#FFFFFF',
			'border_radius'     => 8,
			'layout'            => 'single_column',
			'label_position'    => 'above',
			'custom_css'        => '',
		];
	}

	/**
	 * Default behavior config.
	 *
	 * @return array<string, mixed>
	 */
	private static function default_behavior(): array {
		return [
			'success_type'       => 'message',
			'success_message'    => __( 'Thank you! Your sample request has been submitted.', 'samplehq-request-form' ),
			'redirect_url'       => '',
			'submit_button_text' => __( 'Request Samples', 'samplehq-request-form' ),
		];
	}
}
