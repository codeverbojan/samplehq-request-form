/**
 * SampleHQ Form Frontend
 *
 * Vanilla JS for frontend form rendering: AJAX submission via REST API,
 * client-side validation, error display with focus management, success
 * message display, and Sample Picker selection count announcements.
 *
 * @package
 */

import '../../css/public/form.css';

( function () {
	'use strict';

	// -------------------------------------------------------------------------
	// Analytics Events
	// -------------------------------------------------------------------------

	/**
	 * Fire an analytics event via CustomEvent, GTM dataLayer, and GA4 gtag.
	 * Only fires when the form has data-analytics="1".
	 *
	 * @param {HTMLFormElement} form The form element (checked for data-analytics).
	 * @param {string}          name Event name (e.g. 'shqf_form_submit').
	 * @param {Object}          data Event data payload.
	 */
	function fireAnalyticsEvent( form, name, data ) {
		if ( form.dataset.analytics !== '1' ) {
			return;
		}

		const payload = {
			...data,
			form_id: form.dataset.formId,
			form_title: form.dataset.formTitle || '',
		};

		// 1. Custom DOM event (universal).
		document.dispatchEvent( new CustomEvent( name, { detail: payload } ) );

		// 2. GTM dataLayer.
		if ( window.dataLayer && Array.isArray( window.dataLayer ) ) {
			window.dataLayer.push( { event: name, ...payload } );
		}

		// 3. GA4 gtag.
		if ( typeof window.gtag === 'function' ) {
			window.gtag( 'event', name, payload );
		}
	}

	/**
	 * Initialize all forms on the page.
	 */
	function init() {
		const forms = document.querySelectorAll( '.shqf-form' );
		forms.forEach( ( form ) => setupForm( form ) );
	}

	/**
	 * Set up a single form: attach submit handler, picker logic, multi-step.
	 *
	 * @param {HTMLFormElement} form The form element.
	 */
	function setupForm( form ) {
		const formId = form.dataset.formId;
		if ( ! formId || form.dataset.shqfInit ) {
			return;
		}
		form.dataset.shqfInit = '1';

		// Attach submit handler.
		form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			handleSubmit( form, formId );
		} );

		// Set up multi-step navigation (if applicable).
		setupMultiStep( form );

		// Set up sample picker interactions.
		setupSamplePickers( form );
		setupPickerItemClicks( form );

		// Set up conditional logic (show/hide fields based on other field values).
		setupConditionalLogic( form );

		// Analytics: form view event.
		fireAnalyticsEvent( form, 'shqf_form_view', {} );

		// Analytics: form start event (first interaction).
		let formStarted = false;
		form.addEventListener( 'input', function onFirstInput() {
			if ( ! formStarted ) {
				formStarted = true;
				fireAnalyticsEvent( form, 'shqf_form_start', {} );
				form.removeEventListener( 'input', onFirstInput );
			}
		} );
	}

	/**
	 * Handle form submission via REST API.
	 *
	 * @param {HTMLFormElement} form   The form element.
	 * @param {string}          formId The form database ID.
	 */
	async function handleSubmit( form, formId ) {
		const submitBtn = form.querySelector( '.shqf-button--submit' );
		const messagesEl = getMessagesElement( form ); // eslint-disable-line @wordpress/no-unused-vars-before-return

		// Disable submit button during request.
		if ( submitBtn ) {
			submitBtn.disabled = true;
			submitBtn.setAttribute( 'aria-busy', 'true' );
		}

		// Clear previous errors.
		clearErrors( form );
		clearMessages( messagesEl );

		// Client-side validation.
		const clientErrors = validateClientSide( form );
		if ( Object.keys( clientErrors ).length > 0 ) {
			displayErrors( form, clientErrors );
			focusFirstError( form );
			enableSubmit( submitBtn );
			return;
		}

		// Collect form data (after validation passes).
		const formData = collectFormData( form );

		// Get the action URL from the form or localized data.
		const actionUrl = form.action || getActionUrl( formId );

		try {
			const response = await fetch( actionUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( {
					form_id: parseInt( formId, 10 ),
					shqf_token: formData.shqf_token || '',
					shqf_hp: formData.shqf_hp || '',
					shqf_fields: formData.fields,
					cf_turnstile_response: formData.cf_turnstile_response || '',
				} ),
			} );

			let result;
			try {
				result = await response.json();
			} catch {
				// Non-JSON response (e.g., server error HTML).
				// eslint-disable-next-line no-console -- Debug output for server errors.
				console.error(
					'[SampleHQ Form] Non-JSON response:',
					response.status
				);
				showMessage(
					messagesEl,
					'An unexpected error occurred. Please try again.',
					'error'
				);
				enableSubmit( submitBtn );
				return;
			}

			if ( result.success ) {
				// Analytics: successful submission event.
				fireAnalyticsEvent( form, 'shqf_form_submit', {} );

				// Redirect to thank-you page if configured.
				const redirectUrl = form.dataset.redirectUrl;
				if ( redirectUrl ) {
					// Short delay lets analytics beacons flush before navigation.
					setTimeout( () => {
						window.location.href = redirectUrl;
					}, 200 );
					return;
				}

				showSuccess( form, messagesEl, result.message || 'Thank you!' );
			} else {
				// Field-level validation errors.
				if ( result.errors ) {
					displayErrors( form, result.errors );

					// General error message (rate limit, CSRF, spam, etc.).
					const generalMsg =
						result.errors.general ||
						result.message ||
						'Please correct the errors and try again.';
					showMessage( messagesEl, generalMsg, 'error' );

					focusFirstError( form );
				} else {
					// Unexpected error shape -- show whatever message the server gave.
					showMessage(
						messagesEl,
						result.message ||
							'An error occurred. Please try again.',
						'error'
					);
				}

				// eslint-disable-next-line no-console -- Debug output for server errors.
				console.error( '[SampleHQ Form] Submission failed:', result );
			}
		} catch ( err ) {
			console.error( '[SampleHQ Form] Network error:', err );
			showMessage(
				messagesEl,
				'A network error occurred. Please check your connection and try again.',
				'error'
			);
		}

		// Reset Turnstile widget so resubmission gets a fresh token.
		if ( window.turnstile ) {
			const widget = form.querySelector( '.cf-turnstile' );
			if ( widget ) {
				window.turnstile.reset( widget );
			}
		}

		enableSubmit( submitBtn );
	}

	/**
	 * Collect all form field data into a structured object.
	 *
	 * @param {HTMLFormElement} form The form element.
	 * @return {Object} Structured form data.
	 */
	function collectFormData( form ) {
		const data = {
			shqf_token: '',
			shqf_hp: '',
			fields: {},
		};

		// Hidden fields.
		const tokenInput = form.querySelector( 'input[name="shqf_token"]' );
		if ( tokenInput ) {
			data.shqf_token = tokenInput.value;
		}

		const hpInput = form.querySelector( 'input[name="shqf_hp"]' );
		if ( hpInput ) {
			data.shqf_hp = hpInput.value;
		}

		// Turnstile response (if widget present).
		const turnstileInput = form.querySelector(
			'[name="cf-turnstile-response"]'
		);
		if ( turnstileInput ) {
			data.cf_turnstile_response = turnstileInput.value;
		}

		// Collect all shqf_fields inputs (skip disabled -- conditionally hidden fields).
		const fieldInputs = form.querySelectorAll( '[name^="shqf_fields"]' );
		fieldInputs.forEach( ( input ) => {
			if ( input.disabled ) {
				return;
			}
			const name = input.name;
			const match = name.match(
				/^shqf_fields\[([^\]]+)\](?:\[([^\]]*)\])?(?:\[([^\]]*)\])?(?:\[([^\]]*)\])?$/
			);
			if ( ! match ) {
				return;
			}

			const key = match[ 1 ];
			const subKey = match[ 2 ];
			const subSubKey = match[ 3 ];
			const fourthKey = match[ 4 ];

			// Handle checkboxes (array values).
			if ( input.type === 'checkbox' && subKey === '' ) {
				// Checkbox array: shqf_fields[key][]
				if ( input.checked ) {
					if ( ! Array.isArray( data.fields[ key ] ) ) {
						data.fields[ key ] = [];
					}
					data.fields[ key ].push( input.value );
				}
				return;
			}

			// Handle sample picker: shqf_fields[key][items][id][selected/quantity]
			if ( subKey === 'items' && subSubKey && fourthKey ) {
				if (
					typeof data.fields[ key ] !== 'object' ||
					data.fields[ key ] === null
				) {
					data.fields[ key ] = { items: {} };
				}
				if ( ! data.fields[ key ].items ) {
					data.fields[ key ].items = {};
				}
				if ( ! data.fields[ key ].items[ subSubKey ] ) {
					data.fields[ key ].items[ subSubKey ] = {};
				}
				if ( input.type === 'checkbox' && fourthKey === 'selected' ) {
					data.fields[ key ].items[ subSubKey ][ fourthKey ] =
						input.checked ? '1' : '';
				} else {
					data.fields[ key ].items[ subSubKey ][ fourthKey ] =
						input.value;
				}
				return;
			}

			// Handle composite fields: shqf_fields[key][subkey]
			if ( subKey && ! subSubKey ) {
				if (
					typeof data.fields[ key ] !== 'object' ||
					data.fields[ key ] === null
				) {
					data.fields[ key ] = {};
				}
				data.fields[ key ][ subKey ] = input.value;
				return;
			}

			// Simple field: shqf_fields[key].
			if ( input.type === 'radio' ) {
				if ( input.checked ) {
					data.fields[ key ] = input.value;
				}
			} else {
				data.fields[ key ] = input.value;
			}
		} );

		return data;
	}

	/**
	 * Client-side validation for all visible fields.
	 *
	 * Validates required, format (email, URL), number min/max, file size.
	 * For multi-step forms, only validates visible steps.
	 *
	 * @param {HTMLFormElement} form The form element.
	 * @return {Object} Errors keyed by error element ID.
	 */
	function validateClientSide( form ) {
		const errors = {};
		const inputs = form.querySelectorAll(
			'input:not([type="hidden"]):not(.shqf-sr-only), select, textarea'
		);

		inputs.forEach( ( input ) => {
			if ( input.closest( '.shqf-hp' ) ) {
				return;
			}

			// Skip disabled inputs (conditionally hidden fields).
			if ( input.disabled ) {
				return;
			}

			// Skip fields in hidden steps.
			const stepEl = input.closest( '.shqf-step' );
			if ( stepEl && stepEl.getAttribute( 'aria-hidden' ) === 'true' ) {
				return;
			}

			const wrapper = input.closest( '.shqf-field' );
			if ( ! wrapper ) {
				return;
			}

			const errorEl = wrapper.querySelector( '.shqf-error' );
			if ( ! errorEl ) {
				return;
			}

			const error = validateInput( input, form );
			if ( error ) {
				errors[ errorEl.id ] = error;
			}
		} );

		// Sample picker validation: checkboxes are .shqf-sr-only (excluded above).
		const pickers = form.querySelectorAll(
			'.shqf-picker[aria-required="true"]'
		);
		pickers.forEach( ( picker ) => {
			// Skip pickers in hidden steps.
			const stepEl = picker.closest( '.shqf-step' );
			if ( stepEl && stepEl.getAttribute( 'aria-hidden' ) === 'true' ) {
				return;
			}
			const checked = picker.querySelectorAll(
				'.shqf-sr-only:checked'
			);
			if ( checked.length === 0 ) {
				const errorId = picker.getAttribute( 'aria-describedby' );
				if ( errorId ) {
					errors[ errorId ] = 'Please select at least one sample.';
				}
			}
		} );

		return errors;
	}

	/**
	 * Display validation errors on the form.
	 *
	 * @param {HTMLFormElement} form   The form element.
	 * @param {Object}          errors Errors keyed by field ID.
	 */
	function displayErrors( form, errors ) {
		Object.entries( errors ).forEach( ( [ fieldId, message ] ) => {
			// Try to find error container by ID pattern: {prefix}-{fieldId}-error.
			let errorEl = form.querySelector( '#' + CSS.escape( fieldId ) );

			if ( ! errorEl ) {
				// Try with -error suffix.
				errorEl = form.querySelector(
					'[id$="' + CSS.escape( fieldId ) + '-error"]'
				);
			}

			if ( errorEl ) {
				errorEl.textContent = message;
				errorEl.removeAttribute( 'style' );
				errorEl.setAttribute( 'role', 'alert' );

				// Mark the associated input as invalid.
				const input = form.querySelector(
					'[aria-describedby="' + errorEl.id + '"]'
				);
				if ( input ) {
					input.setAttribute( 'aria-invalid', 'true' );
				}
			}
		} );
	}

	/**
	 * Clear all displayed errors on the form.
	 *
	 * @param {HTMLFormElement} form The form element.
	 */
	function clearErrors( form ) {
		form.querySelectorAll( '.shqf-error' ).forEach( ( el ) => {
			el.textContent = '';
			el.style.display = 'none';
			el.removeAttribute( 'role' );
		} );

		form.querySelectorAll( '[aria-invalid]' ).forEach( ( el ) => {
			el.removeAttribute( 'aria-invalid' );
		} );
	}

	/**
	 * Focus the first field with an error.
	 *
	 * @param {HTMLFormElement} form The form element.
	 */
	function focusFirstError( form ) {
		const firstError = form.querySelector(
			'.shqf-error:not([style*="display:none"]):not(:empty)'
		);
		if ( firstError ) {
			const input = form.querySelector(
				'[aria-describedby="' + firstError.id + '"]'
			);
			if ( input ) {
				input.focus();
			}
		}
	}

	/**
	 * Show a success screen and hide the form.
	 *
	 * Renders the styled success screen with check icon, title, and message.
	 * Also resets the form fields and hides the bottom bar.
	 *
	 * @param {HTMLFormElement} form       The form element.
	 * @param {Element}         messagesEl The messages container.
	 * @param {string}          message    Success message text.
	 */
	function showSuccess( form, messagesEl, message ) {
		// Hide the form and bottom bar.
		form.style.display = 'none';

		const wrapper = form.closest( '.shqf-form-wrapper' );
		const bottomBar = wrapper?.querySelector( '.shqf-bottom-bar' );
		if ( bottomBar ) {
			bottomBar.style.display = 'none';
		}

		// Hide selection bar.
		const selectionBar = wrapper?.querySelector( '.shqf-selection-bar' );
		if ( selectionBar ) {
			selectionBar.style.display = 'none';
		}

		// Hide divider.
		const divider = wrapper?.querySelector( '.shqf-divider' );
		if ( divider ) {
			divider.style.display = 'none';
		}

		// Hide stepper.
		const stepper = wrapper?.querySelector( '.shqf-stepper' );
		if ( stepper ) {
			stepper.style.display = 'none';
		}

		// Render success screen.
		if ( messagesEl ) {
			messagesEl.innerHTML =
				'<div class="shqf-success">' +
				'<div class="shqf-success-icon">' +
				'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' +
				'</div>' +
				'<h3 class="shqf-success-title">Request Received</h3>' +
				'<p class="shqf-success-message">' +
				escapeHtml( message ) +
				'</p>' +
				'</div>';
		}

		// Reset form fields for potential re-display.
		form.reset();

		// Clear file input displays.
		form.querySelectorAll( 'input[type="file"]' ).forEach( ( input ) => {
			input.value = '';
		} );

		// Clear hidden attachment ID fields.
		form.querySelectorAll( 'input[type="hidden"][name$="_id"]' ).forEach(
			( input ) => {
				if ( input.name !== 'shqf_form_id' ) {
					input.value = '';
				}
			}
		);

		// Clear picker selected states.
		form.querySelectorAll( '.shqf-picker-item--selected' ).forEach(
			( item ) => {
				item.classList.remove( 'shqf-picker-item--selected' );
			}
		);
	}

	/**
	 * Show a message in the aria-live region.
	 *
	 * @param {Element} messagesEl The messages container.
	 * @param {string}  message    Message text.
	 * @param {string}  type       Message type: 'success' or 'error'.
	 */
	function showMessage( messagesEl, message, type ) {
		if ( ! messagesEl ) {
			return;
		}

		messagesEl.innerHTML =
			'<div class="shqf-message shqf-message--' +
			type +
			'">' +
			escapeHtml( message ) +
			'</div>';
	}

	/**
	 * Clear messages from the aria-live region.
	 *
	 * @param {Element} messagesEl The messages container.
	 */
	function clearMessages( messagesEl ) {
		if ( messagesEl ) {
			messagesEl.innerHTML = '';
		}
	}

	/**
	 * Get the messages element for a form.
	 *
	 * @param {HTMLFormElement} form The form element.
	 * @return {Element|null} The messages container.
	 */
	function getMessagesElement( form ) {
		const wrapper = form.closest( '.shqf-form-wrapper' );
		return wrapper ? wrapper.querySelector( '.shqf-form-messages' ) : null;
	}

	/**
	 * Get the REST API action URL for a form.
	 *
	 * @param {string} formId The form ID.
	 * @return {string} The submission URL.
	 */
	function getActionUrl( formId ) {
		const dataKey = 'shqfFormData_' + formId;
		if ( window[ dataKey ] && window[ dataKey ].actionUrl ) {
			return window[ dataKey ].actionUrl;
		}
		return '';
	}

	/**
	 * Re-enable the submit button.
	 *
	 * @param {Element|null} btn The submit button.
	 */
	function enableSubmit( btn ) {
		if ( btn ) {
			btn.disabled = false;
			btn.removeAttribute( 'aria-busy' );
		}
	}

	/**
	 * Escape HTML special characters to prevent XSS.
	 *
	 * @param {string} str The string to escape.
	 * @return {string} Escaped string.
	 */
	function escapeHtml( str ) {
		const div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

	// ==========================================================================
	// Multi-step Navigation
	// ==========================================================================

	/**
	 * Set up multi-step form navigation (next/back buttons, stepper updates).
	 *
	 * @param {HTMLFormElement} form The form element.
	 */
	function setupMultiStep( form ) {
		const totalStepsInput = form.querySelector(
			'input[name="shqf_total_steps"]'
		);
		if ( ! totalStepsInput ) {
			return; // Not a multi-step form.
		}

		const totalSteps = parseInt( totalStepsInput.value, 10 );
		if ( totalSteps < 2 ) {
			return;
		}

		let currentStep = 0;
		const wrapper = form.closest( '.shqf-form-wrapper' );
		const bottomBar = wrapper
			? wrapper.querySelector( '.shqf-bottom-bar' )
			: form.querySelector( '.shqf-bottom-bar' );
		const prevBtn = bottomBar?.querySelector( '.shqf-button--prev' );
		const nextBtn = bottomBar?.querySelector( '.shqf-button--next' );
		const submitBtn = bottomBar?.querySelector( '.shqf-button--submit' );

		if ( ! nextBtn ) {
			return;
		}

		nextBtn.addEventListener( 'click', () => {
			// Clear stale errors before re-validation.
			clearErrors( form );

			// Validate current step before advancing.
			const stepErrors = validateStep( form, currentStep );
			if ( Object.keys( stepErrors ).length > 0 ) {
				displayErrors( form, stepErrors );
				focusFirstError( form );
				return;
			}

			if ( currentStep < totalSteps - 1 ) {
				goToStep( form, currentStep + 1, totalSteps );
				currentStep++;
				updateNavButtons(
					prevBtn,
					nextBtn,
					submitBtn,
					currentStep,
					totalSteps
				);
				updateStepper( wrapper, currentStep, totalSteps );
				updateProgressBar( wrapper, currentStep, totalSteps );
				fireAnalyticsEvent( form, 'shqf_form_step', {
					step_index: currentStep,
					total_steps: totalSteps,
				} );
			}
		} );

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', () => {
				if ( currentStep > 0 ) {
					goToStep( form, currentStep - 1, totalSteps );
					currentStep--;
					updateNavButtons(
						prevBtn,
						nextBtn,
						submitBtn,
						currentStep,
						totalSteps
					);
					updateStepper( wrapper, currentStep, totalSteps );
					updateProgressBar( wrapper, currentStep, totalSteps );
					fireAnalyticsEvent( form, 'shqf_form_step', {
						step_index: currentStep,
						total_steps: totalSteps,
					} );
				}
			} );
		}
	}

	/**
	 * Switch to a specific step: show target, hide all others.
	 *
	 * @param {HTMLFormElement} form       The form element.
	 * @param {number}          targetStep The step index to show.
	 * @param {number}          totalSteps Total number of steps.
	 */
	function goToStep( form, targetStep, totalSteps ) {
		for ( let i = 0; i < totalSteps; i++ ) {
			const stepEl = form.querySelector(
				'.shqf-step[data-step="' + i + '"]'
			);
			if ( ! stepEl ) {
				continue;
			}

			if ( i === targetStep ) {
				stepEl.style.display = '';
				stepEl.removeAttribute( 'aria-hidden' );

				// Focus first input field in the new step.
				const focusable = stepEl.querySelector(
					'input:not([type="hidden"]):not(.shqf-sr-only), select, textarea'
				);
				if ( focusable ) {
					focusable.focus();
				}
			} else {
				stepEl.style.display = 'none';
				stepEl.setAttribute( 'aria-hidden', 'true' );
			}
		}
	}

	/**
	 * Update prev/next/submit button visibility based on current step.
	 *
	 * @param {Element|null} prevBtn   Previous button.
	 * @param {Element|null} nextBtn   Next button.
	 * @param {Element|null} submitBtn Submit button.
	 * @param {number}       current   Current step index (0-based).
	 * @param {number}       total     Total steps.
	 */
	function updateNavButtons( prevBtn, nextBtn, submitBtn, current, total ) {
		const isFirst = current === 0;
		const isLast = current === total - 1;

		if ( prevBtn ) {
			prevBtn.style.display = isFirst ? 'none' : '';
		}
		if ( nextBtn ) {
			nextBtn.style.display = isLast ? 'none' : '';

			// Update "Next: {step label}" text dynamically.
			if ( ! isLast ) {
				const form = nextBtn.closest( 'form' ) || nextBtn.closest( '.shqf-form-wrapper' )?.querySelector( 'form' );
				const labelsInput = form?.querySelector( 'input[name="shqf_step_labels"]' );
				if ( labelsInput ) {
					try {
						const labels = JSON.parse( labelsInput.value );
						const nextLabel = labels[ current + 1 ] || '';
						// Preserve the SVG icon (last child).
						const icon = nextBtn.querySelector( 'svg' );
						const text = nextLabel
							? 'Next: ' + nextLabel
							: 'Next';
						nextBtn.textContent = text;
						if ( icon ) {
							nextBtn.appendChild( icon );
						}
					} catch {
						// Invalid JSON -- leave text as-is.
					}
				}
			}
		}
		if ( submitBtn ) {
			submitBtn.style.display = isLast ? '' : 'none';
		}
	}

	/**
	 * Update the wizard stepper visual states (active/done/pending).
	 *
	 * @param {Element|null} wrapper Form wrapper element.
	 * @param {number}       current Current step index (0-based).
	 */
	// eslint-disable-next-line no-unused-vars -- Kept for API compatibility.
	function updateStepper( wrapper, current, _total ) {
		if ( ! wrapper ) {
			return;
		}

		const stepper = wrapper.querySelector( '.shqf-stepper' );
		if ( ! stepper ) {
			return;
		}

		const steps = stepper.querySelectorAll( '.shqf-stepper-step' );
		const lines = stepper.querySelectorAll( '.shqf-step-line' );

		steps.forEach( ( stepEl, i ) => {
			const dot = stepEl.querySelector( '.shqf-step-dot' );
			const label = stepEl.querySelector( '.shqf-step-label' );
			let state;

			if ( i < current ) {
				state = 'done';
			} else if ( i === current ) {
				state = 'active';
			} else {
				state = 'pending';
			}

			// Update step wrapper state classes.
			stepEl.classList.remove(
				'shqf-stepper-step--active',
				'shqf-stepper-step--done',
				'shqf-stepper-step--pending'
			);
			stepEl.classList.add( 'shqf-stepper-step--' + state );

			// Update dot.
			if ( dot ) {
				dot.classList.remove(
					'shqf-step-dot--active',
					'shqf-step-dot--done',
					'shqf-step-dot--pending'
				);
				dot.classList.add( 'shqf-step-dot--' + state );
				if ( state === 'done' ) {
					// Replace number with checkmark SVG.
					dot.innerHTML =
						'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
				} else {
					dot.textContent = String( i + 1 );
				}
			}

			// Update label.
			if ( label ) {
				label.classList.remove(
					'shqf-step-label--active',
					'shqf-step-label--done',
					'shqf-step-label--pending'
				);
				label.classList.add( 'shqf-step-label--' + state );
			}

			// Update aria-current.
			if ( i === current ) {
				stepEl.setAttribute( 'aria-current', 'step' );
			} else {
				stepEl.removeAttribute( 'aria-current' );
			}
		} );

		// Update connecting lines.
		lines.forEach( ( line, i ) => {
			if ( i < current ) {
				line.classList.add( 'shqf-step-line--done' );
			} else {
				line.classList.remove( 'shqf-step-line--done' );
			}
		} );
	}

	/**
	 * Update the progress bar for non-wizard multi-step forms.
	 *
	 * @param {Element|null} wrapper Form wrapper element.
	 * @param {number}       current Current step index (0-based).
	 * @param {number}       total   Total steps.
	 */
	function updateProgressBar( wrapper, current, total ) {
		if ( ! wrapper ) {
			return;
		}

		const progressBar = wrapper.querySelector( '.shqf-progress' );
		if ( ! progressBar ) {
			return;
		}

		const bar = progressBar.querySelector( '.shqf-progress__bar' );
		const pct = Math.round( ( ( current + 1 ) / total ) * 100 );

		if ( bar ) {
			bar.style.width = pct + '%';
		}

		progressBar.setAttribute( 'aria-valuenow', String( current + 1 ) );

		// Update step label active states.
		const stepsNav = wrapper.querySelector( '.shqf-steps-nav' );
		if ( stepsNav ) {
			stepsNav
				.querySelectorAll( '.shqf-step-label' )
				.forEach( ( label, i ) => {
					if ( i === current ) {
						label.classList.add( 'shqf-step-label--active' );
						label.setAttribute( 'aria-current', 'step' );
					} else {
						label.classList.remove( 'shqf-step-label--active' );
						label.removeAttribute( 'aria-current' );
					}
				} );
		}
	}

	// ==========================================================================
	// Per-step Validation
	// ==========================================================================

	/**
	 * Validate only the fields within the current step.
	 *
	 * @param {HTMLFormElement} form    The form element.
	 * @param {number}          stepIdx Current step index.
	 * @return {Object} Errors keyed by error element ID.
	 */
	function validateStep( form, stepIdx ) {
		const stepEl = form.querySelector(
			'.shqf-step[data-step="' + stepIdx + '"]'
		);
		if ( ! stepEl ) {
			return {};
		}

		const errors = {};
		const inputs = stepEl.querySelectorAll(
			'input:not([type="hidden"]):not(.shqf-sr-only), select, textarea'
		);

		inputs.forEach( ( input ) => {
			if ( input.closest( '.shqf-hp' ) ) {
				return;
			}

			// Skip disabled inputs (conditionally hidden fields).
			if ( input.disabled ) {
				return;
			}

			const wrapper = input.closest( '.shqf-field' );
			if ( ! wrapper ) {
				return;
			}

			const errorEl = wrapper.querySelector( '.shqf-error' );
			if ( ! errorEl ) {
				return;
			}

			const error = validateInput( input, form );
			if ( error ) {
				errors[ errorEl.id ] = error;
			}
		} );

		// Sample picker validation: checkboxes are .shqf-sr-only (excluded above).
		const pickers = stepEl.querySelectorAll(
			'.shqf-picker[aria-required="true"]'
		);
		pickers.forEach( ( picker ) => {
			const checked = picker.querySelectorAll(
				'.shqf-sr-only:checked'
			);
			if ( checked.length === 0 ) {
				const errorId = picker.getAttribute( 'aria-describedby' );
				if ( errorId ) {
					errors[ errorId ] = 'Please select at least one sample.';
				}
			}
		} );

		return errors;
	}

	/**
	 * Validate a single input element.
	 *
	 * @param {HTMLElement}     input The input element.
	 * @param {HTMLFormElement} form  The form element.
	 * @return {string|null} Error message or null.
	 */
	function validateInput( input, form ) {
		const value = input.value.trim();

		// Required check.
		if (
			input.hasAttribute( 'required' ) ||
			input.getAttribute( 'aria-required' ) === 'true'
		) {
			if ( input.type === 'checkbox' || input.type === 'radio' ) {
				const groupName = input.name;
				const checked = form.querySelectorAll(
					'input[name="' + CSS.escape( groupName ) + '"]:checked'
				);
				if ( checked.length === 0 ) {
					return 'This field is required.';
				}
			} else if ( value === '' ) {
				return 'This field is required.';
			}
		}

		if ( value === '' ) {
			return null; // Empty non-required fields pass.
		}

		// Email format check.
		if ( input.type === 'email' ) {
			// Basic pattern: something@something.something
			if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
				return 'Please enter a valid email address.';
			}
		}

		// URL format check.
		if ( input.type === 'url' ) {
			if ( ! /^https?:\/\/.+/.test( value ) ) {
				return 'Please enter a valid URL starting with http:// or https://';
			}
		}

		// Number min/max check.
		if ( input.type === 'number' ) {
			const num = parseFloat( value );
			if ( isNaN( num ) ) {
				return 'Please enter a valid number.';
			}
			const min = input.hasAttribute( 'min' )
				? parseFloat( input.min )
				: null;
			const max = input.hasAttribute( 'max' )
				? parseFloat( input.max )
				: null;
			if ( min !== null && num < min ) {
				return 'Value must be at least ' + min + '.';
			}
			if ( max !== null && num > max ) {
				return 'Value must be at most ' + max + '.';
			}
		}

		// Text/Textarea min length check.
		if ( input.hasAttribute( 'minlength' ) ) {
			const minLen = parseInt( input.getAttribute( 'minlength' ), 10 );
			if ( minLen > 0 && value.length < minLen ) {
				const customMsg =
					input.closest( '.shqf-field' )?.dataset?.formatMessage;
				return (
					customMsg || 'Must be at least ' + minLen + ' characters.'
				);
			}
		}

		// Text/Textarea max length check.
		if ( input.hasAttribute( 'maxlength' ) ) {
			const maxLen = parseInt( input.getAttribute( 'maxlength' ), 10 );
			if ( maxLen > 0 && value.length > maxLen ) {
				const customMsg =
					input.closest( '.shqf-field' )?.dataset?.formatMessage;
				return (
					customMsg || 'Must be at most ' + maxLen + ' characters.'
				);
			}
		}

		// Pattern validation (text inputs).
		if ( input.hasAttribute( 'pattern' ) ) {
			const pattern = input.getAttribute( 'pattern' );
			try {
				const re = new RegExp( '^(?:' + pattern + ')$' );
				if ( ! re.test( value ) ) {
					const customMsg =
						input.closest( '.shqf-field' )?.dataset?.formatMessage;
					return (
						customMsg || 'Value does not match the required format.'
					);
				}
			} catch ( e ) {
				// Invalid regex -- skip client-side check, server will catch it.
			}
		}

		// File size check.
		if ( input.type === 'file' && input.files && input.files.length > 0 ) {
			const maxSize = parseInt( input.dataset.maxSize || '0', 10 );
			if ( maxSize > 0 && input.files[ 0 ].size > maxSize ) {
				const maxMb = Math.round( maxSize / 1048576 );
				return 'File is too large. Maximum size: ' + maxMb + ' MB.';
			}
		}

		return null;
	}

	// ==========================================================================
	// Sample Picker Interactions
	// ==========================================================================

	/**
	 * Set up click-to-select on picker items (card/row toggles checkbox).
	 *
	 * @param {HTMLFormElement} form The form element.
	 */
	function setupPickerItemClicks( form ) {
		const pickers = form.querySelectorAll( '.shqf-picker' );

		pickers.forEach( ( picker ) => {
			const maxSelections = parseInt(
				picker.querySelector( '.shqf-picker-status' )?.dataset.max ||
					'0',
				10
			);

			picker
				.querySelectorAll( '.shqf-picker-item' )
				.forEach( ( item ) => {
					// Click on card/row toggles the hidden checkbox.
					item.addEventListener( 'click', ( e ) => {
						// Don't toggle if clicking on qty controls.
						if (
							e.target.closest(
								'.shqf-picker-item-qty-controls'
							)
						) {
							return;
						}

						const checkbox = item.querySelector(
							'input[type="checkbox"]'
						);
						if ( ! checkbox || checkbox.disabled ) {
							return;
						}

						checkbox.checked = ! checkbox.checked;
						checkbox.dispatchEvent(
							new Event( 'change', { bubbles: true } )
						);
						updatePickerItemState( item, checkbox.checked );
						updateSelectionBar( form, picker, maxSelections );
						enforceMaxSelections( picker, maxSelections );
					} );

					// Keyboard: Space/Enter on the item div.
					item.setAttribute( 'tabindex', '0' );
					item.addEventListener( 'keydown', ( e ) => {
						if ( e.key === ' ' || e.key === 'Enter' ) {
							e.preventDefault();
							item.click();
						}
					} );
				} );

				// Quantity stepper +/- buttons.
				setupQuantitySteppers( picker );

				// Search and category pill filtering.
				setupPickerFiltering( picker );
		} );
	}

	/**
	 * Set up search bar and category pill filtering for a picker.
	 *
	 * @param {Element} picker The picker container.
	 */
	function setupPickerFiltering( picker ) {
		const searchInput = picker.querySelector( '.shqf-picker-search' );
		const pills = picker.querySelectorAll( '.shqf-pill' );
		const items = picker.querySelectorAll( '.shqf-picker-item' );
		const initialVisible = parseInt(
			picker.dataset.initialVisible || '0',
			10
		);

		let activeCategory = '';
		let showAll = initialVisible <= 0; // No limit if not set.

		// Create "Show more" link if needed.
		let showMoreLink = null;
		if ( initialVisible > 0 && items.length > initialVisible ) {
			const itemsContainer = picker.querySelector( '.shqf-picker-items' );
			showMoreLink = document.createElement( 'button' );
			showMoreLink.type = 'button';
			showMoreLink.className = 'shqf-picker-show-more';
			itemsContainer?.parentNode?.insertBefore(
				showMoreLink,
				itemsContainer.nextSibling
			);
			showMoreLink.addEventListener( 'click', () => {
				showAll = true;
				filterItems();
			} );
		}

		function filterItems() {
			const query = ( searchInput?.value || '' )
				.toLowerCase()
				.trim();
			const isFiltering = query !== '' || activeCategory !== '';

			let visibleCount = 0;
			let totalMatching = 0;

			items.forEach( ( item ) => {
				const name = (
					item.querySelector( '.shqf-picker-item-label' )
						?.textContent || ''
				).toLowerCase();
				const desc = (
					item.querySelector( '.shqf-picker-item-desc' )
						?.textContent || ''
				).toLowerCase();
				const cats = item.dataset.categories || '';

				const matchSearch =
					query === '' ||
					name.includes( query ) ||
					desc.includes( query );
				const matchCat =
					activeCategory === '' ||
					cats.split( ',' ).includes( activeCategory );

				if ( matchSearch && matchCat ) {
					totalMatching++;
					// Apply load-more limit only when not filtering.
					if (
						isFiltering ||
						showAll ||
						visibleCount < initialVisible
					) {
						item.style.display = '';
						visibleCount++;
					} else {
						item.style.display = 'none';
					}
				} else {
					item.style.display = 'none';
				}
			} );

			// Update "Show more" link.
			if ( showMoreLink ) {
				const hiddenCount = totalMatching - visibleCount;
				if ( hiddenCount > 0 && ! isFiltering ) {
					showMoreLink.textContent =
						'Show ' + hiddenCount + ' more samples';
					showMoreLink.style.display = '';
				} else {
					showMoreLink.style.display = 'none';
				}
			}
		}

		// Run initial filter to apply load-more.
		filterItems();

		if ( searchInput ) {
			searchInput.addEventListener( 'input', () => {
				// Only reset load-more when there's actually a limit.
				if ( initialVisible > 0 ) {
					showAll = false;
				}
				filterItems();
			} );
		}

		pills.forEach( ( pill ) => {
			pill.addEventListener( 'click', () => {
				pills.forEach( ( p ) =>
					p.classList.remove( 'shqf-pill--active' )
				);
				pill.classList.add( 'shqf-pill--active' );
				activeCategory = pill.dataset.category || '';
				if ( initialVisible > 0 ) {
					showAll = false;
				}
				filterItems();
			} );
		} );
	}

	/**
	 * Set up +/- quantity stepper buttons inside a picker.
	 *
	 * @param {Element} picker The picker container.
	 */
	function setupQuantitySteppers( picker ) {
		picker.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( '.shqf-picker-item-qty-btn' );
			if ( ! btn ) {
				return;
			}

			const controls = btn.closest( '.shqf-picker-item-qty-controls' );
			if ( ! controls ) {
				return;
			}

			const hiddenInput = controls.querySelector( '.shqf-picker-item-qty' );
			const display = controls.querySelector( '.shqf-picker-item-qty-value' );
			if ( ! hiddenInput || ! display ) {
				return;
			}

			let val = parseInt( hiddenInput.value, 10 ) || 1;
			const max = parseInt( controls.dataset.max || '0', 10 );

			if ( btn.classList.contains( 'shqf-qty-minus' ) ) {
				val = Math.max( 1, val - 1 );
			} else if ( btn.classList.contains( 'shqf-qty-plus' ) ) {
				if ( max > 0 ) {
					val = Math.min( max, val + 1 );
				} else {
					val += 1;
				}
			}

			hiddenInput.value = String( val );
			display.textContent = String( val );
		} );
	}

	/**
	 * Toggle the selected class on a picker item.
	 *
	 * @param {Element} item      The picker item div.
	 * @param {boolean} isChecked Whether the checkbox is now checked.
	 */
	function updatePickerItemState( item, isChecked ) {
		if ( isChecked ) {
			item.classList.add( 'shqf-picker-item--selected' );
		} else {
			item.classList.remove( 'shqf-picker-item--selected' );
		}
		item.setAttribute( 'aria-checked', isChecked ? 'true' : 'false' );
	}

	/**
	 * Show/hide and update the selection bar count.
	 *
	 * @param {HTMLFormElement} form          The form element.
	 * @param {Element}         picker        The picker fieldset.
	 * @param {number}          maxSelections Max selections (0=unlimited).
	 */
	function updateSelectionBar( form, picker, maxSelections ) {
		const wrapper = form.closest( '.shqf-form-wrapper' );
		const bar = wrapper?.querySelector( '.shqf-selection-bar' );
		if ( ! bar ) {
			return;
		}

		const count = picker.querySelectorAll(
			'input[type="checkbox"]:checked'
		).length;
		const countEl = bar.querySelector( '.shqf-selection-bar__count' );

		if ( count > 0 ) {
			bar.style.display = '';
			if ( countEl ) {
				countEl.textContent =
					count +
					( count === 1 ? ' sample selected' : ' samples selected' );
			}
		} else {
			bar.style.display = 'none';
		}

		// Clear button.
		const clearBtn = bar.querySelector( '.shqf-selection-bar__clear' );
		if ( clearBtn && ! clearBtn.dataset.bound ) {
			clearBtn.dataset.bound = '1';
			clearBtn.addEventListener( 'click', () => {
				picker
					.querySelectorAll( 'input[type="checkbox"]:checked' )
					.forEach( ( cb ) => {
						cb.checked = false;
						cb.dispatchEvent(
							new Event( 'change', { bubbles: true } )
						);
						const item = cb.closest( '.shqf-picker-item' );
						if ( item ) {
							updatePickerItemState( item, false );
						}
					} );
				bar.style.display = 'none';
				enforceMaxSelections( picker, maxSelections );
			} );
		}
	}

	/**
	 * Disable/enable checkboxes based on max selections.
	 *
	 * @param {Element} picker        The picker fieldset.
	 * @param {number}  maxSelections Max selections (0=unlimited).
	 */
	function enforceMaxSelections( picker, maxSelections ) {
		if ( maxSelections <= 0 ) {
			return;
		}

		const checked = picker.querySelectorAll(
			'input[type="checkbox"]:checked'
		);
		const unchecked = picker.querySelectorAll(
			'input[type="checkbox"]:not(:checked)'
		);

		if ( checked.length >= maxSelections ) {
			unchecked.forEach( ( cb ) => {
				cb.disabled = true;
			} );
		} else {
			unchecked.forEach( ( cb ) => {
				cb.disabled = false;
			} );
		}
	}

	/**
	 * Set up sample picker selection counting and aria-live updates.
	 *
	 * @param {HTMLFormElement} form The form element.
	 */
	function setupSamplePickers( form ) {
		const pickers = form.querySelectorAll( '.shqf-picker' );
		pickers.forEach( ( picker ) => {
			const statusEl = picker.querySelector( '.shqf-picker-status' );
			const maxSelections = parseInt( statusEl?.dataset.max || '0', 10 );
			const checkboxes = picker.querySelectorAll(
				'input[type="checkbox"]'
			);

			checkboxes.forEach( ( cb ) => {
				cb.addEventListener( 'change', () => {
					updatePickerStatus( picker, statusEl, maxSelections );
				} );
			} );
		} );
	}

	/**
	 * Update the sample picker selection count and aria-live announcement.
	 *
	 * @param {Element}      picker        The picker fieldset.
	 * @param {Element|null} statusEl      The aria-live status element.
	 * @param {number}       maxSelections Maximum allowed selections (0 = unlimited).
	 */
	function updatePickerStatus( picker, statusEl, maxSelections ) {
		const checked = picker.querySelectorAll(
			'input[type="checkbox"]:checked'
		);
		const count = checked.length;

		if ( ! statusEl ) {
			return;
		}

		if ( maxSelections > 0 && count >= maxSelections ) {
			statusEl.textContent =
				count +
				' of ' +
				maxSelections +
				' samples selected. Maximum reached.';

			// Disable unchecked checkboxes.
			picker
				.querySelectorAll( 'input[type="checkbox"]:not(:checked)' )
				.forEach( ( cb ) => {
					cb.disabled = true;
				} );
		} else {
			const total = picker.querySelectorAll(
				'input[type="checkbox"]'
			).length;
			statusEl.textContent =
				count + ' of ' + total + ' samples selected.';

			// Re-enable all checkboxes.
			picker
				.querySelectorAll( 'input[type="checkbox"]:disabled' )
				.forEach( ( cb ) => {
					cb.disabled = false;
				} );
		}
	}

	// ==========================================================================
	// Conditional Logic (show/hide fields based on other field values)
	// ==========================================================================

	/**
	 * Set up conditional logic for fields with data-conditions attributes.
	 *
	 * Listens for input/change events on source fields and shows/hides
	 * conditional target fields in real-time.
	 *
	 * @param {HTMLFormElement} form The form element.
	 */
	function setupConditionalLogic( form ) {
		const conditionalFields = form.querySelectorAll( '[data-conditions]' );
		if ( conditionalFields.length === 0 ) {
			return;
		}

		// Collect all source field keys referenced by conditions.
		const sourceKeys = new Set();
		conditionalFields.forEach( ( fieldEl ) => {
			try {
				const conditions = JSON.parse( fieldEl.dataset.conditions );
				if ( conditions.rules ) {
					conditions.rules.forEach( ( rule ) => {
						if ( rule.field_key ) {
							sourceKeys.add( rule.field_key );
						}
					} );
				}
			} catch {
				// Invalid JSON -- skip.
			}
		} );

		// Attach listeners to all source fields.
		sourceKeys.forEach( ( key ) => {
			const inputs = form.querySelectorAll(
				'[name="shqf_fields[' +
					key +
					']"], ' +
					'[name="shqf_fields[' +
					key +
					'][]"]'
			);
			inputs.forEach( ( input ) => {
				const eventType =
					input.type === 'checkbox' ||
					input.type === 'radio' ||
					input.tagName === 'SELECT'
						? 'change'
						: 'input';
				input.addEventListener( eventType, () => {
					evaluateAllConditions( form, conditionalFields );
				} );
			} );
		} );

		// Run initial evaluation to set correct visibility on page load.
		evaluateAllConditions( form, conditionalFields );
	}

	/**
	 * Re-evaluate all conditional fields and show/hide as needed.
	 *
	 * @param {HTMLFormElement} form              The form element.
	 * @param {NodeList}        conditionalFields Fields with data-conditions.
	 */
	function evaluateAllConditions( form, conditionalFields ) {
		const fieldValues = collectFieldValues( form );

		conditionalFields.forEach( ( fieldEl ) => {
			try {
				const conditions = JSON.parse( fieldEl.dataset.conditions );
				const visible = evaluateConditions( conditions, fieldValues );

				if ( visible ) {
					fieldEl.style.display = '';
					fieldEl.removeAttribute( 'aria-hidden' );
					// Re-enable inputs so they are included in submission.
					fieldEl
						.querySelectorAll( 'input, select, textarea' )
						.forEach( ( input ) => {
							input.disabled = false;
						} );
				} else {
					fieldEl.style.display = 'none';
					fieldEl.setAttribute( 'aria-hidden', 'true' );
					// Disable inputs so they are excluded from submission.
					fieldEl
						.querySelectorAll( 'input, select, textarea' )
						.forEach( ( input ) => {
							input.disabled = true;
						} );
				}
			} catch {
				// Invalid conditions JSON -- leave visible.
			}
		} );
	}

	/**
	 * Collect current values of all form fields keyed by field key.
	 *
	 * @param {HTMLFormElement} form The form element.
	 * @return {Object} Field values keyed by field key.
	 */
	function collectFieldValues( form ) {
		const values = {};
		const inputs = form.querySelectorAll( '[name^="shqf_fields"]' );

		inputs.forEach( ( input ) => {
			// Skip inputs inside conditionally hidden fields.
			if ( input.closest( '.shqf-field[aria-hidden="true"]' ) ) {
				return;
			}

			const match = input.name.match( /^shqf_fields\[([^\]]+)\]/ );
			if ( ! match ) {
				return;
			}
			const key = match[ 1 ];

			if ( input.type === 'checkbox' ) {
				if ( input.name.endsWith( '[]' ) ) {
					// Checkbox array.
					if ( ! Array.isArray( values[ key ] ) ) {
						values[ key ] = [];
					}
					if ( input.checked ) {
						values[ key ].push( input.value );
					}
				} else {
					// Single checkbox (consent).
					values[ key ] = input.checked ? input.value : '';
				}
			} else if ( input.type === 'radio' ) {
				if ( input.checked ) {
					values[ key ] = input.value;
				}
			} else {
				values[ key ] = input.value;
			}
		} );

		return values;
	}

	/**
	 * Evaluate a conditions object against current field values.
	 *
	 * Matches server-side ConditionEvaluator logic exactly.
	 * Supports: equals, not_equals, contains, not_contains, empty, not_empty.
	 * Logic: "all" (AND) or "any" (OR).
	 *
	 * @param {Object} conditions The conditions: { logic, rules }.
	 * @param {Object} values     Current field values keyed by field key.
	 * @return {boolean} True if the field should be visible.
	 */
	function evaluateConditions( conditions, values ) {
		if (
			! conditions ||
			! conditions.rules ||
			conditions.rules.length === 0
		) {
			return true;
		}

		const logic = conditions.logic || 'all';

		for ( const rule of conditions.rules ) {
			const result = evaluateRule( rule, values );

			if ( logic === 'any' && result ) {
				return true;
			}
			if ( logic === 'all' && ! result ) {
				return false;
			}
		}

		return logic === 'all';
	}

	/**
	 * Evaluate a single condition rule.
	 *
	 * @param {Object} rule   The rule: { field_key, operator, value }.
	 * @param {Object} values Current field values.
	 * @return {boolean} True if the rule matches.
	 */
	function evaluateRule( rule, values ) {
		const fieldKey = rule.field_key || '';
		const operator = rule.operator || 'equals';
		const expected = String( rule.value ?? '' );
		const actual = values[ fieldKey ];

		// Normalize to string (arrays joined with comma, matching PHP).
		const actualStr = Array.isArray( actual )
			? actual.join( ',' )
			: String( actual ?? '' );

		switch ( operator ) {
			case 'equals':
				return actualStr === expected;
			case 'not_equals':
				return actualStr !== expected;
			case 'contains':
				return expected !== '' && actualStr.includes( expected );
			case 'not_contains':
				return expected === '' || ! actualStr.includes( expected );
			case 'empty':
				return actualStr.trim() === '';
			case 'not_empty':
				return actualStr.trim() !== '';
			default:
				return true;
		}
	}

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
