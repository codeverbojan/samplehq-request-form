<?php
/**
 * Elementor widget for SampleHQ Form.
 *
 * @package SampleHQForm\Elementor
 */

declare( strict_types=1 );

namespace SampleHQForm\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor widget that embeds a SampleHQ form.
 *
 * Registered conditionally only when Elementor is active.
 * The widget renders the same output as the shortcode/block.
 */
class FormWidget extends \Elementor\Widget_Base {

	/**
	 * Get the widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name(): string {
		return 'samplehq_form';
	}

	/**
	 * Get the widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title(): string {
		return __( 'SampleHQ Form', 'samplehq-request-form' );
	}

	/**
	 * Get the widget icon.
	 *
	 * @return string Elementor icon class.
	 */
	public function get_icon(): string {
		return 'eicon-form-horizontal';
	}

	/**
	 * Get widget categories.
	 *
	 * @return string[] Widget categories.
	 */
	public function get_categories(): array {
		return [ 'general' ];
	}

	/**
	 * Get widget keywords for search.
	 *
	 * @return string[] Keywords.
	 */
	public function get_keywords(): array {
		return [ 'form', 'sample', 'request', 'samplehq' ];
	}

	/**
	 * Register widget controls.
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Form Settings', 'samplehq-request-form' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'form_id',
			[
				'label'       => __( 'Select Form', 'samplehq-request-form' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $this->get_form_options(),
				'description' => __( 'Choose a published form to display.', 'samplehq-request-form' ),
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$form_id  = absint( $settings['form_id'] ?? 0 );

		if ( 0 === $form_id ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="shqf-elementor-placeholder">';
				echo esc_html__( 'Select a form in the widget settings.', 'samplehq-request-form' );
				echo '</div>';
			}
			return;
		}

		// Reuse shortcode output.
		echo do_shortcode( '[samplehq_form id="' . $form_id . '"]' );
	}

	/**
	 * Get available forms as select options.
	 *
	 * @return array<string, string> Form ID => title.
	 */
	private function get_form_options(): array {
		global $wpdb;

		$forms_table = new \SampleHQForm\Database\FormsTable( $wpdb );
		$forms       = $forms_table->list_all( [ 'status' => 'published' ] );

		$options = [
			'' => __( '-- Select a Form --', 'samplehq-request-form' ),
		];

		foreach ( $forms as $form ) {
			$options[ (string) $form['id'] ] = $form['title'] ?? sprintf(
				/* translators: %d: form ID */
				__( 'Form #%d', 'samplehq-request-form' ),
				$form['id']
			);
		}

		return $options;
	}
}
