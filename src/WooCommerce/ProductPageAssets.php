<?php
/**
 * WooCommerce product page asset management and form pre-rendering.
 *
 * @package SampleHQForm\WooCommerce
 */

declare( strict_types=1 );

namespace SampleHQForm\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\FormsTable;
use SampleHQForm\Forms\FormRenderer;
use SampleHQForm\Helpers\Assets;
use SampleHQForm\Spam\FormToken;

/**
 * Enqueues modal JS/CSS on WooCommerce product pages and pre-renders
 * the form HTML in a hidden dialog element in the footer.
 */
class ProductPageAssets {

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
	 * CSRF token generator.
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
	 * Register hooks for product page assets.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue' ] );
		add_action( 'wp_footer', [ $this, 'maybe_render_modal' ] );
	}

	/**
	 * Enqueue modal assets on WooCommerce single product pages.
	 *
	 * @return void
	 */
	public function maybe_enqueue(): void {
		if ( ! $this->is_eligible_product_page() ) {
			return;
		}

		// Enqueue the form assets (same as shortcode/block).
		Assets::enqueue_google_fonts();
		Assets::enqueue_public_script( 'form-frontend' );
		Assets::enqueue_public_style( 'form-frontend' );
		Assets::maybe_enqueue_turnstile();

		// Enqueue the modal-specific JS and CSS.
		Assets::enqueue_public_script( 'woo-product-page' );
		Assets::enqueue_public_style( 'woo-product-page' );
	}

	/**
	 * Render the modal dialog with the form in the footer.
	 *
	 * The form HTML is pre-rendered server-side so the existing form-frontend.js
	 * can auto-initialize it on page load. The modal JS only handles open/close.
	 *
	 * @return void
	 */
	public function maybe_render_modal(): void {
		if ( ! $this->is_eligible_product_page() ) {
			return;
		}

		$form_id = $this->get_configured_form_id();
		if ( 0 === $form_id ) {
			return;
		}

		$form = $this->forms->get( $form_id );
		if ( null === $form ) {
			return;
		}

		if ( 'published' !== ( $form['status'] ?? '' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$config      = $form['config'] ?? [];
		$token_value = $this->token->generate( $form_id );
		$action_url  = rest_url( 'samplehq-form/v1/submissions' );

		// Localize form data for form-frontend.js.
		wp_localize_script(
			'shqf-form-frontend',
			'shqfFormData_' . $form_id,
			[
				'formId'    => $form_id,
				'actionUrl' => $action_url,
				'nonce'     => '',
			]
		);

		$form_html = $this->renderer->render(
			$form_id,
			$config,
			[
				'token'      => $token_value,
				'action_url' => $action_url,
			]
		);

		$close_label = esc_attr__( 'Close', 'samplehq-request-form' );
		$title       = esc_html( $form['title'] ?? __( 'Request a Sample', 'samplehq-request-form' ) );

		echo '<div id="shqf-woo-modal" class="shqf-woo-modal" role="dialog" aria-modal="true"';
		echo ' aria-labelledby="shqf-woo-modal-title" style="display:none;">';
		echo '<div class="shqf-woo-modal__backdrop"></div>';
		echo '<div class="shqf-woo-modal__content">';
		echo '<div class="shqf-woo-modal__header">';
		echo '<h2 id="shqf-woo-modal-title" class="shqf-woo-modal__title">' . esc_html( $title ) . '</h2>';
		echo '<button type="button" class="shqf-woo-modal__close" aria-label="' . esc_attr( $close_label ) . '">';
		echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
		echo '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>';
		echo '</svg></button></div>';
		echo '<div class="shqf-woo-modal__body" role="document">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FormRenderer output is pre-escaped.
		echo $form_html;
		echo '</div></div></div>';
	}

	/**
	 * Check if the current page is an eligible WC product page.
	 *
	 * @return bool
	 */
	private function is_eligible_product_page(): bool {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the configured form ID for WooCommerce requests.
	 *
	 * Falls back to the first published form if no specific form is configured.
	 *
	 * @return int Form ID or 0 if no form available.
	 */
	private function get_configured_form_id(): int {
		$form_id = (int) get_option( 'shqf_woo_form_id', 0 );

		if ( $form_id > 0 ) {
			return $form_id;
		}

		// Fallback: use the first published form (limit 1 to avoid loading all forms).
		$forms = $this->forms->list_all(
			[
				'status' => 'published',
				'limit'  => 1,
			]
		);
		if ( ! empty( $forms ) ) {
			return (int) $forms[0]['id'];
		}

		return 0;
	}
}
