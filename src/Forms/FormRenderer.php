<?php
/**
 * Server-side form renderer.
 *
 * @package SampleHQForm\Forms
 */

declare( strict_types=1 );

namespace SampleHQForm\Forms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SampleHQForm\Fields\FieldRegistry;

/**
 * Renders a complete HTML form from a form configuration.
 *
 * Iterates through the field config, delegates to FieldRegistry for each field,
 * and wraps everything in a form element with CSRF token, honeypot, submit button,
 * and an aria-live region for success/error messages.
 */
class FormRenderer {

	/**
	 * Field type registry.
	 *
	 * @var FieldRegistry
	 */
	private FieldRegistry $fields;

	/**
	 * Constructor.
	 *
	 * @param FieldRegistry $fields Field type registry.
	 */
	public function __construct( FieldRegistry $fields ) {
		$this->fields = $fields;
	}

	/**
	 * Render the full form HTML.
	 *
	 * @param int                  $form_id Form database ID.
	 * @param array<string, mixed> $config  Form config JSON (decoded).
	 * @param array<string, mixed> $options Optional: errors, values, token, action_url.
	 * @return string Complete form HTML.
	 */
	public function render( int $form_id, array $config, array $options = [] ): string {
		$fields     = $config['fields'] ?? [];
		$appearance = $config['appearance'] ?? [];
		$behavior   = $config['behavior'] ?? [];
		$errors     = $options['errors'] ?? [];
		$values     = $options['values'] ?? [];
		$token      = $options['token'] ?? '';
		$action_url = $options['action_url'] ?? '';

		$layout      = $config['layout'] ?? '';
		$form_prefix = 'shqf-form-' . $form_id;
		$display     = $config['display'] ?? [];
		$context     = [
			'field_prefix' => $form_prefix,
			'errors'       => $errors,
			'layout'       => $layout,
			'display'      => $display,
		];

		$submit_text = esc_html( $behavior['submit_button_text'] ?? __( 'Submit Request', 'samplehq-request-form' ) );

		// Build CSS custom properties from appearance config.
		$style = $this->build_inline_style( $appearance );

		// Layout modifier class (wizard, grid, list).
		$wrapper_class = 'shqf-form-wrapper';
		if ( in_array( $layout, [ 'wizard', 'grid', 'list' ], true ) ) {
			$wrapper_class .= ' shqf-form-wrapper--' . $layout;
		}

		$html = '<div class="' . esc_attr( $wrapper_class ) . '" id="' . esc_attr( $form_prefix ) . '-wrapper"' . $style . '>';

		// Aria-live region for success/error announcements.
		$html .= '<div class="shqf-form-messages" aria-live="polite" aria-atomic="true" id="' . esc_attr( $form_prefix ) . '-messages"></div>';

		$html .= '<form class="shqf-form" method="post" action="' . esc_url( $action_url ) . '"';
		$html .= ' data-form-id="' . esc_attr( (string) $form_id ) . '"';

		// Redirect URL (if success type is redirect).
		$redirect_url = $behavior['redirect_url'] ?? '';
		if ( 'redirect' === ( $behavior['success_type'] ?? '' ) && '' !== $redirect_url ) {
			$html .= ' data-redirect-url="' . esc_url( $redirect_url ) . '"';
		}

		// Analytics events opt-in.
		if ( ! empty( $behavior['analytics_events'] ) ) {
			$html .= ' data-analytics="1"';
			$html .= ' data-form-title="' . esc_attr( $config['title'] ?? '' ) . '"';
		}

		$html .= ' novalidate>';

		// CSRF token (hidden field).
		$html .= '<input type="hidden" name="shqf_token" value="' . esc_attr( $token ) . '" />';

		// Form ID (hidden field).
		$html .= '<input type="hidden" name="shqf_form_id" value="' . esc_attr( (string) $form_id ) . '" />';

		// Honeypot field (CSS hidden, not display:none for screen reader accessibility).
		$html .= $this->render_honeypot( $form_prefix );

		// Multi-step or single-step rendering.
		$steps     = $config['steps'] ?? [];
		$is_multi  = count( $steps ) > 1; // Single-step array treated as no steps.
		$num_steps = count( $steps );
		$is_wizard = 'wizard' === $layout && $is_multi;

		// For wizard layout, the header is rendered per-step (inside each step div).
		// For all other layouts, render the global header above the form.
		if ( ! $is_wizard ) {
			$html .= $this->render_header( $config );
		}

		if ( $is_multi ) {
			$html .= '<input type="hidden" name="shqf_total_steps" value="' . esc_attr( (string) $num_steps ) . '" />';

			if ( $is_wizard ) {
				// Stepper bar (dots + lines + labels) for wizard layout.
				$html .= $this->render_stepper( $steps );
			} else {
				// Simple step labels for non-wizard multi-step forms.
				$html .= $this->render_steps_nav( $steps );
			}
		}

		// Render fields (grouped by step if multi-step).
		$html .= '<div class="shqf-fields">';

		if ( $is_multi ) {
			// Group fields by step_index.
			foreach ( $steps as $si => $step ) {
				$hidden = $si > 0 ? ' style="display:none;" aria-hidden="true"' : '';
				$html  .= '<div class="shqf-step" data-step="' . esc_attr( (string) $si ) . '"' . $hidden . '>';

				// Per-step heading for wizard layout.
				// Falls back to form-level title/subtitle if step doesn't define its own.
				if ( $is_wizard ) {
					$step_title    = $step['title'] ?? $config['title'] ?? '';
					$step_subtitle = $step['subtitle'] ?? $config['subtitle'] ?? '';
					if ( '' !== $step_title || '' !== $step_subtitle ) {
						$html .= '<div class="shqf-header shqf-step-header">';
						if ( '' !== $step_title ) {
							$html .= '<h2 class="shqf-title">' . esc_html( $step_title ) . '</h2>';
						}
						if ( '' !== $step_subtitle ) {
							$html .= '<p class="shqf-subtitle">' . esc_html( $step_subtitle ) . '</p>';
						}
						$html .= '</div>';
					}
				}

				foreach ( $fields as $field_config ) {
					if ( empty( $field_config['enabled'] ?? true ) ) {
						continue;
					}
					if ( (int) ( $field_config['step_index'] ?? 0 ) !== $si ) {
						continue;
					}

					if ( 'row' === ( $field_config['type'] ?? '' ) ) {
						$html .= $this->render_row( $field_config, $values, $context );
						continue;
					}

					$type       = $field_config['type'] ?? '';
					$field_impl = $this->fields->get( $type );
					if ( null === $field_impl ) {
						continue;
					}

					$key   = $field_config['key'] ?? $field_config['id'] ?? '';
					$value = $values[ $key ] ?? null;
					$html .= $field_impl->render( $field_config, $value, $context );
				}

				$html .= '</div>';
			}
		} else {
			// Single-step: render all fields.
			foreach ( $fields as $field_config ) {
				if ( empty( $field_config['enabled'] ?? true ) ) {
					continue;
				}

				if ( 'row' === ( $field_config['type'] ?? '' ) ) {
					$html .= $this->render_row( $field_config, $values, $context );
					continue;
				}

				$type       = $field_config['type'] ?? '';
				$field_impl = $this->fields->get( $type );
				if ( null === $field_impl ) {
					continue;
				}

				$key   = $field_config['key'] ?? $field_config['id'] ?? '';
				$value = $values[ $key ] ?? null;
				$html .= $field_impl->render( $field_config, $value, $context );
			}
		}

		$html .= '</div>';

		// Selection bar (grid/list layouts -- JS populates count + clear button).
		if ( in_array( $layout, [ 'grid', 'list', 'wizard' ], true ) ) {
			$html .= '<div class="shqf-selection-bar" style="display:none;" aria-live="polite">';
			$html .= '<span class="shqf-selection-bar__count"></span>';
			$html .= '<button type="button" class="shqf-selection-bar__clear">';
			$html .= esc_html__( 'Clear', 'samplehq-request-form' ) . '</button>';
			$html .= '</div>';
		}

		// Divider between sample picker and contact fields (grid/list single-page).
		if ( in_array( $layout, [ 'grid', 'list' ], true ) ) {
			$divider_text = $config['divider_text'] ?? __( 'Your Details', 'samplehq-request-form' );
			$html        .= '<div class="shqf-divider" role="separator">';
			$html        .= '<span class="shqf-divider__line" aria-hidden="true"></span>';
			$html        .= '<span class="shqf-divider__label">' . esc_html( $divider_text ) . '</span>';
			$html        .= '<span class="shqf-divider__line" aria-hidden="true"></span>';
			$html        .= '</div>';
		}

		// Turnstile CAPTCHA widget (if configured).
		$turnstile_key = (string) get_option( 'shqf_turnstile_site_key', '' );
		if ( '' !== $turnstile_key ) {
			$html .= '<div class="shqf-turnstile">';
			$html .= '<div class="cf-turnstile" data-sitekey="' . esc_attr( $turnstile_key ) . '"></div>';
			$html .= '</div>';
		}

		// SVG icons for buttons.
		$arrow_left  = '<svg class="shqf-btn-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="12 5 7 10 12 15"></polyline></svg>';
		$arrow_right = '<svg class="shqf-btn-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="8 5 13 10 8 15"></polyline></svg>';
		$send_icon   = '<svg class="shqf-btn-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3L10.5 9.5M17 3L12 17L10.5 9.5M17 3L3 8L10.5 9.5"></path></svg>';

		// Bottom bar with navigation/submit buttons.
		$html .= '<div class="shqf-bottom-bar">';
		if ( $is_multi ) {
			// Pass step labels as JSON for the JS to build "Next: {label}" text.
			$step_labels = array_map( fn( $s ) => $s['label'] ?? '', $steps );
			$html       .= '<input type="hidden" name="shqf_step_labels" value="' . esc_attr( (string) wp_json_encode( $step_labels ) ) . '" />';

			$html .= '<button type="button" class="shqf-button shqf-button--prev" style="display:none;"';
			$html .= ' aria-label="' . esc_attr__( 'Go to previous step', 'samplehq-request-form' ) . '">';
			$html .= $arrow_left . esc_html__( 'Back', 'samplehq-request-form' ) . '</button>';

			// Default Next text includes the next step's label.
			$next_label = $step_labels[1] ?? __( 'Next', 'samplehq-request-form' );
			$html      .= '<button type="button" class="shqf-button shqf-button--next"';
			$html      .= ' aria-label="' . esc_attr__( 'Go to next step', 'samplehq-request-form' ) . '">';
			/* translators: %s: next step label */
			$html .= esc_html( sprintf( __( 'Next: %s', 'samplehq-request-form' ), $next_label ) );
			$html .= $arrow_right . '</button>';

			$html .= '<button type="submit" class="shqf-button shqf-button--submit" style="display:none;">';
			$html .= $send_icon . $submit_text . '</button>';
		} else {
			$html .= '<button type="submit" class="shqf-button shqf-button--submit">';
			$html .= $send_icon . $submit_text . '</button>';
		}
		$html .= '</div>';

		$html .= '</form>';

		// Custom CSS (admin-authored, sanitized).
		// The form wrapper has id="shqf-form-{id}-wrapper" for scoping.
		$custom_css = $appearance['custom_css'] ?? '';
		if ( is_string( $custom_css ) && '' !== $custom_css ) {
			$html .= '<style>' . self::sanitize_custom_css( $custom_css ) . '</style>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render the form header (title + subtitle).
	 *
	 * @param array<string, mixed> $config Form config.
	 * @return string Header HTML or empty string.
	 */
	private function render_header( array $config ): string {
		$title    = $config['title'] ?? '';
		$subtitle = $config['subtitle'] ?? '';

		if ( '' === $title && '' === $subtitle ) {
			return '';
		}

		$html = '<div class="shqf-header">';

		if ( '' !== $title ) {
			$html .= '<h2 class="shqf-title">' . esc_html( $title ) . '</h2>';
		}

		if ( '' !== $subtitle ) {
			$html .= '<p class="shqf-subtitle">' . esc_html( $subtitle ) . '</p>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render the stepper bar for wizard layout.
	 *
	 * Renders numbered dots with connecting lines and labels. Step 1 is active,
	 * all others are pending. JS updates states as user progresses.
	 *
	 * @param array<int, array<string, mixed>> $steps Step configurations.
	 * @return string Stepper HTML.
	 */
	private function render_stepper( array $steps ): string {
		$num = count( $steps );

		if ( 0 === $num ) {
			return '';
		}

		$html = '<nav class="shqf-stepper" aria-label="' . esc_attr__( 'Form steps', 'samplehq-request-form' ) . '">';

		foreach ( $steps as $si => $step ) {
			$is_active = 0 === $si;
			$state     = $is_active ? 'active' : 'pending';
			$current   = $is_active ? ' aria-current="step"' : '';

			// Step group (dot + label).
			$html .= '<div class="shqf-stepper-step shqf-stepper-step--' . $state . '"';
			$html .= ' data-step="' . esc_attr( (string) $si ) . '"' . $current . '>';

			// Dot (decorative -- screen readers use the sr-only text below).
			$html .= '<span class="shqf-step-dot shqf-step-dot--' . $state . '" aria-hidden="true">';
			$html .= esc_html( (string) ( $si + 1 ) );
			$html .= '</span>';

			// Visible label.
			$html .= '<span class="shqf-step-label shqf-step-label--' . $state . '">';
			/* translators: 1: step number, 2: total steps */
			$html .= '<span class="shqf-sr-only">' . esc_html( sprintf( __( 'Step %1$d of %2$d:', 'samplehq-request-form' ), $si + 1, $num ) ) . ' </span>';
			$html .= esc_html( $step['label'] ?? '' );
			$html .= '</span>';

			$html .= '</div>';

			// Connecting line (not after last step).
			if ( $si < $num - 1 ) {
				$html .= '<span class="shqf-step-line" aria-hidden="true"></span>';
			}
		}

		$html .= '</nav>';

		return $html;
	}

	/**
	 * Render simple step navigation (non-wizard multi-step forms).
	 *
	 * @param array<int, array<string, mixed>> $steps Step configurations.
	 * @return string Step navigation HTML.
	 */
	private function render_steps_nav( array $steps ): string {
		$num = count( $steps );

		// Progress bar.
		$html  = '<div class="shqf-progress" role="progressbar" aria-valuemin="1"';
		$html .= ' aria-valuemax="' . esc_attr( (string) $num ) . '" aria-valuenow="1"';
		$html .= ' aria-label="' . esc_attr__( 'Form progress', 'samplehq-request-form' ) . '">';
		$html .= '<div class="shqf-progress__bar" style="width:' . esc_attr( (string) round( 100 / $num ) ) . '%;"></div>';
		$html .= '</div>';

		// Step label buttons.
		$html .= '<div class="shqf-steps-nav" aria-label="' . esc_attr__( 'Form steps', 'samplehq-request-form' ) . '">';
		foreach ( $steps as $si => $step ) {
			$active  = 0 === $si ? ' shqf-step-label--active' : '';
			$current = 0 === $si ? ' aria-current="step"' : '';
			$html   .= '<button type="button" class="shqf-step-label' . $active . '"';
			$html   .= ' data-step="' . esc_attr( (string) $si ) . '"' . $current . '>';
			$html   .= esc_html( $step['label'] ?? '' );
			$html   .= '</button>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render a row group (multi-column layout container).
	 *
	 * Outputs a CSS Grid container with one div per column. Column widths
	 * are set via inline grid-template-columns (e.g. "1fr 1fr" for equal halves).
	 * Child fields are rendered inside each column div.
	 *
	 * @param array<string, mixed> $row_config Row field config with 'columns' key.
	 * @param array<string, mixed> $values     Submitted values keyed by field key.
	 * @param array<string, mixed> $context    Render context (prefix, errors, layout).
	 * @return string Row HTML or empty string if no columns.
	 */
	private function render_row( array $row_config, array $values, array $context ): string {
		$columns = $row_config['columns'] ?? [];

		if ( empty( $columns ) ) {
			return '';
		}

		$col_widths = [];
		foreach ( $columns as $column ) {
			$width = $column['width'] ?? '1fr';
			// Whitelist safe CSS grid values (e.g. 1fr, 2fr, 50%, 200px, auto).
			$col_widths[] = preg_match( '/^\d*\.?\d+(fr|px|%|em|rem)$|^auto$/', $width ) ? $width : '1fr';
		}
		$grid_template = implode( ' ', $col_widths );

		$html = '<div class="shqf-field-row" style="grid-template-columns:' . esc_attr( $grid_template ) . ';">';

		foreach ( $columns as $column ) {
			$html .= '<div class="shqf-field-row__col">';

			foreach ( $column['fields'] ?? [] as $field_config ) {
				if ( empty( $field_config['enabled'] ?? true ) ) {
					continue;
				}

				// Nested rows (defensive).
				if ( 'row' === ( $field_config['type'] ?? '' ) ) {
					$html .= $this->render_row( $field_config, $values, $context );
					continue;
				}

				$type       = $field_config['type'] ?? '';
				$field_impl = $this->fields->get( $type );
				if ( null === $field_impl ) {
					continue;
				}

				$key   = $field_config['key'] ?? $field_config['id'] ?? '';
				$value = $values[ $key ] ?? null;
				$html .= $field_impl->render( $field_config, $value, $context );
			}

			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render the honeypot field.
	 *
	 * Uses CSS to hide the field visually (not display:none, which screen readers skip).
	 * Bots that fill this field will be rejected.
	 *
	 * @param string $form_prefix Unique form prefix for IDs.
	 * @return string Honeypot HTML.
	 */
	private function render_honeypot( string $form_prefix ): string {
		$html  = '<div class="shqf-hp" aria-hidden="true" style="position:absolute;left:-9999px;height:0;overflow:hidden;">';
		$html .= '<label for="' . esc_attr( $form_prefix . '-hp' ) . '">';
		$html .= esc_html__( 'Leave this field empty', 'samplehq-request-form' );
		$html .= '</label>';
		$html .= '<input type="text" id="' . esc_attr( $form_prefix . '-hp' ) . '"';
		$html .= ' name="shqf_hp" value="" tabindex="-1" autocomplete="off" />';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Build inline CSS custom properties from appearance config.
	 *
	 * @param array<string, mixed> $appearance Appearance settings.
	 * @return string Style attribute string (or empty).
	 */
	private function build_inline_style( array $appearance ): string {
		$props = [];

		// Core properties.
		$core_colors = [
			'primary_color'     => '--shqf-primary',
			'button_color'      => '--shqf-button-bg',
			'button_text_color' => '--shqf-button-text',
		];

		// Extended design system properties.
		$extended_colors = [
			'text_color'    => '--shqf-text-color',
			'muted_color'   => '--shqf-muted-color',
			'border_color'  => '--shqf-border-color',
			'label_color'   => '--shqf-label-color',
			'input_bg'      => '--shqf-input-bg',
			'surface_bg'    => '--shqf-surface-bg',
			'error_color'   => '--shqf-error-color',
			'success_color' => '--shqf-success-color',
		];

		foreach ( array_merge( $core_colors, $extended_colors ) as $key => $var ) {
			if ( ! empty( $appearance[ $key ] ) ) {
				$sanitized = sanitize_hex_color( $appearance[ $key ] );
				if ( null !== $sanitized ) {
					$props[] = $var . ':' . $sanitized;
				}
			}
		}

		if ( isset( $appearance['border_radius'] ) ) {
			$props[] = '--shqf-radius:' . absint( $appearance['border_radius'] ) . 'px';
		}

		if ( empty( $props ) ) {
			return '';
		}

		return ' style="' . esc_attr( implode( ';', $props ) ) . '"';
	}

	/**
	 * Sanitize custom CSS.
	 *
	 * Strips HTML tags, legacy CSS XSS vectors, and external resource loading.
	 *
	 * @param string $css Raw CSS input.
	 * @return string Sanitized CSS.
	 */
	private static function sanitize_custom_css( string $css ): string {
		$css = wp_strip_all_tags( $css );

		// Admin-authored CSS (requires manage_options) — blocklist is defense-in-depth, not exhaustive.
		$css = preg_replace( '/expression\s*\(/i', '/* blocked */(', $css );
		$css = preg_replace( '/url\s*\(\s*["\']?\s*javascript\s*:/i', 'url(/* blocked */', $css );
		$css = preg_replace( '/url\s*\(\s*["\']?\s*data\s*:/i', 'url(/* blocked */', $css );
		$css = preg_replace( '/behavior\s*:/i', '/* blocked */:', $css );
		$css = preg_replace( '/-moz-binding\s*:/i', '/* blocked */:', $css );

		// Strip external resource loading.
		$css = preg_replace( '/@import\b/i', '/* blocked */', $css );

		return $css;
	}
}
