/**
 * WooCommerce Product Page Modal
 *
 * Handles opening/closing the sample request form modal on WooCommerce
 * product pages. The form HTML is pre-rendered server-side and initialized
 * by form-frontend.js. This script only manages modal UX.
 *
 * @package SampleHQForm
 */

import '../../css/public/woo-modal.css';

( function () {
	'use strict';

	const MODAL_ID = 'shqf-woo-modal';
	let modal = null;
	let triggerButton = null;
	let previouslyFocused = null;

	/**
	 * Initialize: attach click handlers to all request buttons.
	 */
	function init() {
		modal = document.getElementById( MODAL_ID );
		if ( ! modal ) {
			return;
		}

		// Button click handler (delegated to body for dynamic content).
		document.body.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( '.shqf-woo-request-btn' );
			if ( btn ) {
				e.preventDefault();
				openModal( btn );
			}
		} );

		// Backdrop click closes modal.
		const backdrop = modal.querySelector( '.shqf-woo-modal__backdrop' );
		if ( backdrop ) {
			backdrop.addEventListener( 'click', closeModal );
		}

		// Close button.
		const closeBtn = modal.querySelector( '.shqf-woo-modal__close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', closeModal );
		}

		// Escape key closes modal.
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && isOpen() ) {
				closeModal();
			}
		} );

		// Focus trap.
		modal.addEventListener( 'keydown', trapFocus );

		// Auto-open modal if URL has #request-sample (from shop loop link).
		if ( window.location.hash === '#request-sample' ) {
			const btn = document.querySelector( '.shqf-woo-request-btn' );
			if ( btn ) {
				openModal( btn );
			}
		}
	}

	/**
	 * Open the modal and pre-select the product.
	 *
	 * @param {HTMLElement} btn The trigger button with data-product-* attributes.
	 */
	function openModal( btn ) {
		if ( ! modal ) {
			return;
		}

		triggerButton = btn;
		previouslyFocused = document.activeElement;

		const productId = btn.dataset.productId;
		const productName = btn.dataset.productName;

		// Pre-select the product in the sample picker if present.
		preselectProduct( productId, productName );

		// Show modal.
		modal.style.display = 'flex';

		// Prevent body scroll.
		document.documentElement.classList.add( 'shqf-modal-open' );

		// Set inert on background content.
		setBackgroundInert( true );

		// Focus the close button (first focusable element in modal).
		const closeBtn = modal.querySelector( '.shqf-woo-modal__close' );
		if ( closeBtn ) {
			closeBtn.focus();
		}
	}

	/**
	 * Close the modal and restore focus.
	 */
	function closeModal() {
		if ( ! modal ) {
			return;
		}

		modal.style.display = 'none';
		document.documentElement.classList.remove( 'shqf-modal-open' );
		setBackgroundInert( false );

		// Reset form state so stale selections don't persist on re-open.
		resetFormState();

		// Restore focus to trigger button.
		if ( previouslyFocused && previouslyFocused.focus ) {
			previouslyFocused.focus();
		}

		triggerButton = null;
		previouslyFocused = null;
	}

	/**
	 * Check if the modal is currently open.
	 *
	 * @return {boolean}
	 */
	function isOpen() {
		return modal && modal.style.display !== 'none';
	}

	/**
	 * Pre-select a product in the sample picker field.
	 *
	 * Finds the checkbox matching the product ID, checks it, and triggers
	 * the change event so the picker JS updates its selection state.
	 *
	 * @param {string} productId  WC product ID.
	 * @param {string} productName Product name (for fallback matching).
	 */
	function preselectProduct( productId, productName ) {
		if ( ! modal || ! productId ) {
			return;
		}

		// Validate product ID is numeric before using in selector.
		if ( ! /^\d+$/.test( productId ) ) {
			return;
		}

		// Find the picker item matching this product ID.
		const pickerItem = modal.querySelector(
			'.shqf-picker-item[data-sample-id="' + productId + '"]'
		);

		if ( ! pickerItem ) {
			return;
		}

		const checkbox = pickerItem.querySelector( 'input[type="checkbox"]' );
		if ( checkbox && ! checkbox.checked ) {
			checkbox.checked = true;
			pickerItem.classList.add( 'shqf-picker-item--selected' );
			pickerItem.setAttribute( 'aria-checked', 'true' );
			checkbox.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	}

	/**
	 * Reset the form inside the modal to its initial state.
	 *
	 * Unchecks selections, resets quantities, clears search/filters,
	 * and removes validation errors or success messages.
	 */
	function resetFormState() {
		if ( ! modal ) {
			return;
		}

		// Uncheck all sample picker checkboxes and remove selected state.
		let didUncheck = false;
		modal.querySelectorAll( '.shqf-picker-item--selected' ).forEach( ( item ) => {
			item.classList.remove( 'shqf-picker-item--selected' );
			item.removeAttribute( 'aria-checked' );
			const cb = item.querySelector( 'input[type="checkbox"]' );
			if ( cb && cb.checked ) {
				cb.checked = false;
				didUncheck = true;
			}
		} );

		// Dispatch change to sync form-frontend.js internal state (selection bar, count).
		if ( didUncheck ) {
			const firstCb = modal.querySelector( '.shqf-picker-item input[type="checkbox"]' );
			if ( firstCb ) {
				firstCb.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		}

		// Reset quantity values to 1.
		modal.querySelectorAll( '.shqf-picker-item-qty-value' ).forEach( ( el ) => {
			el.textContent = '1';
		} );
		modal.querySelectorAll( '.shqf-picker-item-qty' ).forEach( ( input ) => {
			input.value = '1';
		} );

		// Clear search input.
		const searchInput = modal.querySelector( '.shqf-picker-search' );
		if ( searchInput ) {
			searchInput.value = '';
			searchInput.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		}

		// Reset category pills to "All".
		modal.querySelectorAll( '.shqf-pill--active' ).forEach( ( pill ) => {
			pill.classList.remove( 'shqf-pill--active' );
		} );
		const allPill = modal.querySelector( '.shqf-pill[data-category=""]' );
		if ( allPill ) {
			allPill.classList.add( 'shqf-pill--active' );
			allPill.dispatchEvent( new Event( 'click', { bubbles: true } ) );
		}

		// Clear validation errors.
		modal.querySelectorAll( '.shqf-field-error' ).forEach( ( el ) => {
			el.textContent = '';
			el.style.display = 'none';
		} );

		// Clear success message.
		const successMsg = modal.querySelector( '.shqf-form-success' );
		if ( successMsg ) {
			successMsg.style.display = 'none';
		}

		// Reset selection count announcement.
		const statusEl = modal.querySelector( '.shqf-picker-status' );
		if ( statusEl ) {
			statusEl.textContent = '';
		}
	}

	/**
	 * Set or remove the inert attribute on background content.
	 *
	 * @param {boolean} inert Whether to set inert.
	 */
	function setBackgroundInert( inert ) {
		const siblings = document.body.children;
		for ( let i = 0; i < siblings.length; i++ ) {
			const el = siblings[ i ];
			if ( el === modal || el.tagName === 'SCRIPT' || el.tagName === 'LINK' ) {
				continue;
			}
			if ( inert ) {
				el.setAttribute( 'inert', '' );
			} else {
				el.removeAttribute( 'inert' );
			}
		}
	}

	/**
	 * Trap focus within the modal (Tab / Shift+Tab cycling).
	 *
	 * @param {KeyboardEvent} e Keydown event.
	 */
	function trapFocus( e ) {
		if ( e.key !== 'Tab' || ! isOpen() ) {
			return;
		}

		const focusable = modal.querySelectorAll(
			'a[href], button:not([disabled]), textarea, input:not([type="hidden"]):not([disabled]), select, [tabindex]:not([tabindex="-1"])'
		);

		if ( focusable.length === 0 ) {
			return;
		}

		const first = focusable[ 0 ];
		const last = focusable[ focusable.length - 1 ];

		if ( e.shiftKey ) {
			if ( document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			}
		} else if ( document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	// Initialize on DOM ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
