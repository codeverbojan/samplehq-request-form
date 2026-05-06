<?php
/**
 * Gutenberg block: SampleHQ Form.
 *
 * @package SampleHQForm\Blocks
 */

declare( strict_types=1 );

namespace SampleHQForm\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\FormsTable;
use SampleHQForm\Fields\FieldRegistry;
use SampleHQForm\Forms\FormRenderer;
use SampleHQForm\Helpers\Assets;
use SampleHQForm\Spam\FormToken;

/**
 * Registers and renders the samplehq-form/form Gutenberg block.
 *
 * Dynamic block: PHP render callback, no saved HTML.
 * Uses ServerSideRender in the editor for live preview.
 */
class FormBlock {

	/**
	 * Forms repository.
	 *
	 * @var FormsTable
	 */
	private FormsTable $forms;

	/**
	 * Form renderer.
	 *
	 * @var FormRenderer
	 */
	private FormRenderer $renderer;

	/**
	 * Form token generator.
	 *
	 * @var FormToken
	 */
	private FormToken $token;

	/**
	 * Constructor.
	 *
	 * @param FormsTable   $forms    Forms repository.
	 * @param FormRenderer $renderer Form renderer.
	 * @param FormToken    $token    CSRF token generator.
	 */
	public function __construct( FormsTable $forms, FormRenderer $renderer, FormToken $token ) {
		$this->forms    = $forms;
		$this->renderer = $renderer;
		$this->token    = $token;
	}

	/**
	 * Register the block and block category.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_block' ] );
		add_filter( 'block_categories_all', [ $this, 'register_category' ] );
	}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function register_block(): void {
		$block_dir = SHQF_DIR . 'assets/build/blocks/form-block';

		if ( ! file_exists( $block_dir . '/block.json' ) ) {
			// Block not built yet -- skip registration.
			return;
		}

		// Register the frontend CSS so block.json editorStyle/style can reference it.
		// Google Fonts as a dependency so they load in the editor preview too.
		Assets::enqueue_google_fonts();
		wp_register_style(
			'shqf-form-frontend',
			SHQF_URL . 'assets/build/form-frontend.css',
			[ 'shqf-fonts' ],
			SHQF_VERSION
		);

		register_block_type(
			$block_dir,
			[
				'render_callback' => [ $this, 'render' ],
			]
		);
	}

	/**
	 * Register the custom block category.
	 *
	 * @param array<int, array<string, mixed>> $categories Existing categories.
	 * @return array<int, array<string, mixed>> Modified categories.
	 */
	public function register_category( array $categories ): array {
		array_unshift(
			$categories,
			[
				'slug'  => 'samplehq-forms',
				'title' => __( 'SampleHQ Forms', 'samplehq-request-form' ),
			]
		);

		return $categories;
	}

	/**
	 * Render callback for the block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Rendered HTML.
	 */
	public function render( array $attributes ): string {
		$form_id = absint( $attributes['formId'] ?? 0 );

		if ( 0 === $form_id ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="shqf-block-error"><p>'
					. esc_html__( 'Please select a form in the block settings.', 'samplehq-request-form' )
					. '</p></div>';
			}
			return '';
		}

		$form = $this->forms->get( $form_id );

		if ( null === $form ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="shqf-block-error"><p>'
					. esc_html__( 'Form not found.', 'samplehq-request-form' )
					. '</p></div>';
			}
			return '';
		}

		// On the frontend, only show published forms. In the editor, show all.
		$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST;
		if ( ! $is_editor && 'published' !== ( $form['status'] ?? '' ) ) {
			return '';
		}

		$config      = $form['config'] ?? [];
		$token_value = $this->token->generate( $form_id );
		$action_url  = rest_url( 'samplehq-form/v1/submissions' );

		// Enqueue frontend assets.
		Assets::enqueue_google_fonts();
		Assets::enqueue_public_style( 'form-frontend' );

		// Skip JS + Turnstile in editor preview (ServerSideRender) -- form shouldn't be interactive.
		if ( ! $is_editor ) {
			Assets::enqueue_public_script( 'form-frontend' );
			Assets::maybe_enqueue_turnstile();

			wp_localize_script(
				'shqf-form-frontend',
				'shqfFormData_' . $form_id,
				[
					'formId'    => $form_id,
					'actionUrl' => $action_url,
				]
			);
		}

		$html = $this->renderer->render(
			$form_id,
			$config,
			[
				'token'      => $token_value,
				'action_url' => $action_url,
			]
		);

		// In editor: wrap in non-interactive container so the form can't be submitted.
		if ( $is_editor ) {
			$html = '<div class="shqf-editor-preview" style="pointer-events:none;user-select:none;position:relative;">'
				. $html
				. '</div>';
		}

		return $html;
	}
}
