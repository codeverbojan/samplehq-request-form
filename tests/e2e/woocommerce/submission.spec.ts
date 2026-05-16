/**
 * WooCommerce Submission Flow E2E Tests (Phase 6)
 *
 * Tests form submission inside the modal, validation errors,
 * success state, and admin verification of stored submission data.
 */

import { test, expect } from '@playwright/test';
import {
	PRODUCTS,
	BTN_SELECTOR,
	MODAL_SELECTOR,
	SUBMISSIONS_URL,
} from './woo-helpers';

const PRODUCT_URL = PRODUCTS.kraftMailer.url;
const UNIQUE_EMAIL = `e2e-wc-${ Date.now() }@test.example.com`;

/**
 * Open the modal on the Kraft Mailer product page.
 * @param page
 */
async function openModal( page: import('@playwright/test').Page ) {
	await page.goto( PRODUCT_URL );
	await page.locator( BTN_SELECTOR ).click();
	await expect( page.locator( MODAL_SELECTOR ) ).toBeVisible();
}

// =====================================================================
// Phase 6A: Submission + Validation Errors (tests 6.1 - 6.7)
// =====================================================================

test.describe( 'Form Submission', () => {
	test( '6.1-6.3 Fill form and submit successfully', async ( { page } ) => {
		await openModal( page );
		const modal = page.locator( MODAL_SELECTOR );

		// Fill name fields (inside fieldset, first_name and last_name inputs).
		const firstNameInput = modal.locator(
			'input[name*="first_name"], input[name*="[first_name]"]'
		);
		const lastNameInput = modal.locator(
			'input[name*="last_name"], input[name*="[last_name]"]'
		);
		await firstNameInput.fill( 'E2E' );
		await lastNameInput.fill( 'TestUser' );

		// Fill email.
		const emailInput = modal.locator( 'input[type="email"]' );
		await emailInput.fill( UNIQUE_EMAIL );

		// Kraft Mailer should be pre-selected. Verify.
		const selectedItem = modal.locator(
			`.shqf-picker-item--selected[data-sample-id="${ PRODUCTS.kraftMailer.id }"]`
		);
		await expect( selectedItem ).toBeVisible();

		// Click submit button.
		const submitBtn = modal.locator( '.shqf-button--submit' );
		await submitBtn.click();

		// 6.2 Wait for success message.
		const successEl = modal.locator( '.shqf-success' );
		await expect( successEl ).toBeVisible( { timeout: 10000 } );
		await expect( modal.locator( '.shqf-success-title' ) ).toHaveText(
			'Request Received'
		);

		// Check the success message contains the form's configured text.
		const successMsg = modal.locator( '.shqf-success-message' );
		await expect( successMsg ).toBeVisible();

		// 6.3 Form should be hidden after success.
		const form = modal.locator( 'form.shqf-form' );
		await expect( form ).toBeHidden();
	} );
} );

test.describe( 'Validation Errors', () => {
	test( '6.4 Empty name rejected', async ( { page } ) => {
		await openModal( page );
		const modal = page.locator( MODAL_SELECTOR );

		// Fill email but leave name empty.
		await modal.locator( 'input[type="email"]' ).fill( 'test@example.com' );

		// Submit.
		await modal.locator( '.shqf-button--submit' ).click();
		await page.waitForTimeout( 500 );

		// Should show error (either client-side or server-side).
		const errors = modal.locator(
			'.shqf-error:visible, .shqf-messages .shqf-message--error'
		);
		const hasError = ( await errors.count() ) > 0;

		// Also check for HTML5 validation via :invalid pseudo-class.
		const hasInvalidField = await modal.evaluate( ( el ) => {
			return (
				el.querySelector( 'input:invalid, textarea:invalid' ) !== null
			);
		} );

		expect( hasError || hasInvalidField ).toBe( true );

		// Modal should still be open (not closed on error).
		await expect( modal ).toBeVisible();
	} );

	test( '6.5 Empty email rejected', async ( { page } ) => {
		await openModal( page );
		const modal = page.locator( MODAL_SELECTOR );

		// Fill name but leave email empty.
		const firstNameInput = modal.locator(
			'input[name*="first_name"], input[name*="[first_name]"]'
		);
		await firstNameInput.fill( 'TestName' );

		await modal.locator( '.shqf-button--submit' ).click();
		await page.waitForTimeout( 500 );

		const errors = modal.locator(
			'.shqf-error:visible, .shqf-messages .shqf-message--error'
		);
		const hasError = ( await errors.count() ) > 0;
		const hasInvalidField = await modal.evaluate( ( el ) => {
			return el.querySelector( 'input:invalid' ) !== null;
		} );
		expect( hasError || hasInvalidField ).toBe( true );
		await expect( modal ).toBeVisible();
	} );

	test( '6.6 Invalid email rejected', async ( { page } ) => {
		await openModal( page );
		const modal = page.locator( MODAL_SELECTOR );

		const firstNameInput = modal.locator(
			'input[name*="first_name"], input[name*="[first_name]"]'
		);
		await firstNameInput.fill( 'TestName' );
		await modal.locator( 'input[type="email"]' ).fill( 'not-an-email' );

		await modal.locator( '.shqf-button--submit' ).click();
		await page.waitForTimeout( 500 );

		const errors = modal.locator(
			'.shqf-error:visible, .shqf-messages .shqf-message--error'
		);
		const hasError = ( await errors.count() ) > 0;
		const hasInvalidField = await modal.evaluate( ( el ) => {
			return el.querySelector( 'input:invalid' ) !== null;
		} );
		expect( hasError || hasInvalidField ).toBe( true );
	} );

	test( '6.7 No product selected rejected', async ( { page } ) => {
		await openModal( page );
		const modal = page.locator( MODAL_SELECTOR );

		// Deselect the pre-selected product by clicking it.
		const preSelected = modal.locator( `.shqf-picker-item--selected` );
		if ( ( await preSelected.count() ) > 0 ) {
			await preSelected.first().click();
		}

		// Fill name + email.
		const firstNameInput = modal.locator(
			'input[name*="first_name"], input[name*="[first_name]"]'
		);
		const lastNameInput = modal.locator(
			'input[name*="last_name"], input[name*="[last_name]"]'
		);
		await firstNameInput.fill( 'No' );
		await lastNameInput.fill( 'Product' );
		await modal
			.locator( 'input[type="email"]' )
			.fill( 'noselection@example.com' );

		await modal.locator( '.shqf-button--submit' ).click();
		await page.waitForTimeout( 1000 );

		// Should get an error about selecting samples.
		const errorTexts = await modal.evaluate( ( el ) => {
			const errors = el.querySelectorAll( '.shqf-error' );
			return Array.from( errors )
				.map( ( e ) => e.textContent?.trim() )
				.filter( ( t ) => t && t.length > 0 );
		} );

		const messageTexts = await modal.evaluate( ( el ) => {
			const msgs = el.querySelectorAll(
				'.shqf-message--error, .shqf-messages'
			);
			return Array.from( msgs )
				.map( ( m ) => m.textContent?.trim() )
				.filter( ( t ) => t && t.length > 0 );
		} );

		const allErrors = [ ...errorTexts, ...messageTexts ]
			.join( ' ' )
			.toLowerCase();
		expect(
			allErrors.includes( 'select' ) ||
				allErrors.includes( 'sample' ) ||
				allErrors.includes( 'required' ) ||
				allErrors.includes( 'correct' )
		).toBe( true );
	} );
} );

// =====================================================================
// Phase 6B: Admin Verification (tests 6.8 - 6.13)
// =====================================================================

test.describe( 'Admin Verification', () => {
	test( '6.8-6.13 Submission visible in admin with WC data', async ( {
		page,
	} ) => {
		// 6.8: Navigate to submissions admin page.
		await page.goto( SUBMISSIONS_URL );
		await page.waitForLoadState( 'networkidle' );

		// If not found in list, email might be truncated. Search by partial.
		const emailPart = UNIQUE_EMAIL.split( '@' )[ 0 ];
		const row = page.locator( 'tr', { hasText: emailPart } );

		if ( ( await row.count() ) === 0 ) {
			// Submission might not have been created yet (async).
			// Try refreshing.
			await page.reload();
			await page.waitForLoadState( 'networkidle' );
		}

		// 6.9: Click to view submission detail.
		const viewLink = row.first().locator( 'a' ).first();
		await viewLink.click();
		await page.waitForLoadState( 'networkidle' );

		// Should be on submission detail page.
		expect( page.url() ).toContain( 'action=view' );

		// 6.10: WooCommerce badge visible.
		const badge = page.locator( '.shqf-badge--woo' );
		await expect( badge ).toBeVisible();
		await expect( badge ).toHaveText( 'WooCommerce' );

		// 6.11: Product name shown.
		const detailContent = await page.textContent( '.wrap' );
		expect( detailContent ).toContain( 'Kraft Mailer Box' );

		// 6.12: Product SKU shown.
		expect( detailContent ).toContain( 'KMB-1084' );

		// 6.13: Product edit link.
		const editLink = page.locator(
			`a[href*="post.php?post=${ PRODUCTS.kraftMailer.id }"]`
		);
		await expect( editLink ).toBeVisible();
	} );
} );
