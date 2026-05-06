<?php
/**
 * Shortcode registration for form embedding.
 *
 * @package SampleHQForm
 */

declare( strict_types=1 );

namespace SampleHQForm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Database\FormsTable;
use SampleHQForm\Forms\FormRenderer;
use SampleHQForm\Helpers\Assets;
use SampleHQForm\Spam\FormToken;

/**
 * Registers and handles the [samplehq_form] shortcode.
 *
 * Usage: [samplehq_form id="123"]
 *
 * Renders the form via FormRenderer and enqueues frontend assets only
 * when the shortcode is present on the page.
 */
class Shortcode {

	/**
	 * Shortcode tag name.
	 *
	 * @var string
	 */
	public const TAG = 'samplehq_form';

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
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( self::TAG, [ $this, 'render' ] );
	}

	/**
	 * Render the shortcode output.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $atts = [] ): string {
		$atts = shortcode_atts(
			[ 'id' => 0 ],
			$atts,
			self::TAG
		);

		$form_id = absint( $atts['id'] );

		if ( 0 === $form_id ) {
			return $this->render_error( __( 'Please specify a form ID: [samplehq_form id="123"]', 'samplehq-request-form' ) );
		}

		$form = $this->forms->get( $form_id );

		if ( null === $form ) {
			return $this->render_error( __( 'Form not found.', 'samplehq-request-form' ) );
		}

		if ( 'published' !== ( $form['status'] ?? '' ) && ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$config = $form['config'] ?? [];

		// Generate a CSRF token for this form render.
		$token_value = $this->token->generate( $form_id );

		// Build the REST API submission URL.
		$action_url = rest_url( 'samplehq-form/v1/submissions' );

		// Enqueue frontend assets.
		$this->enqueue_assets();

		// Localize the submission URL and form config for the frontend JS.
		wp_localize_script(
			'shqf-form-frontend',
			'shqfFormData_' . $form_id,
			[
				'formId'    => $form_id,
				'actionUrl' => $action_url,
				'nonce'     => '', // Not used (form token instead).
			]
		);

		return $this->renderer->render(
			$form_id,
			$config,
			[
				'token'      => $token_value,
				'action_url' => $action_url,
			]
		);
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * Only called when a form shortcode is rendered on the page.
	 *
	 * @return void
	 */
	private function enqueue_assets(): void {
		Assets::enqueue_google_fonts();
		Assets::enqueue_public_script( 'form-frontend' );
		Assets::enqueue_public_style( 'form-frontend' );
		Assets::maybe_enqueue_turnstile();
	}

	/**
	 * Render an error message visible only to admins.
	 *
	 * Non-admin visitors see nothing (empty string) to avoid exposing
	 * internal information.
	 *
	 * @param string $message The error message.
	 * @return string HTML or empty string.
	 */
	private function render_error( string $message ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return '<div class="shqf-shortcode-error">'
			. '<p>' . esc_html( $message ) . '</p>'
			. '</div>';
	}
}
