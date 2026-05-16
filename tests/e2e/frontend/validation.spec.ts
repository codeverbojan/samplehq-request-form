import { test, expect } from '@playwright/test';
import { loadFixtures } from '../load-fixtures';

let GRID_URL: string;

test.describe( 'Client-side validation', () => {
	test.beforeAll( () => {
		GRID_URL = loadFixtures().pageUrls.grid;
	} );

	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
		await page.goto( GRID_URL );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();
	} );

	test( 'empty required fields show errors on submit', async ( { page } ) => {
		// Submit without filling anything.
		await page.locator( '.shqf-button--submit' ).click();
		// Multiple error messages should appear (picker, name, email are required).
		const errors = page.locator( '.shqf-error:visible' );
		const count = await errors.count();
		expect( count ).toBeGreaterThanOrEqual( 3 );
	} );

	test( 'invalid email shows error', async ( { page } ) => {
		const emailInput = page.locator(
			'.shqf-field--email input[type="email"]'
		);
		await emailInput.fill( 'abc' );
		await page.locator( '.shqf-button--submit' ).click();
		const errorEl = page.locator( '.shqf-field--email .shqf-error' );
		await expect( errorEl ).toBeVisible();
		await expect( errorEl ).toContainText( 'valid email' );
	} );

	test( 'valid email clears error', async ( { page } ) => {
		const emailInput = page.locator(
			'.shqf-field--email input[type="email"]'
		);
		// Trigger error first.
		await emailInput.fill( 'abc' );
		await page.locator( '.shqf-button--submit' ).click();
		const errorEl = page.locator( '.shqf-field--email .shqf-error' );
		await expect( errorEl ).toBeVisible();
		// Fix the email.
		await emailInput.fill( 'valid@test.com' );
		await page.locator( '.shqf-button--submit' ).click();
		// Email error should be gone.
		await expect( errorEl ).toBeHidden();
	} );

	test( 'error fields get aria-invalid attribute', async ( { page } ) => {
		// Submit empty form.
		await page.locator( '.shqf-button--submit' ).click();
		// Wait for errors to display.
		await expect(
			page.locator( '.shqf-error:visible' ).first()
		).toBeVisible();
		// Fields with errors should have aria-invalid="true".
		const invalidFields = page.locator( '[aria-invalid="true"]' );
		const count = await invalidFields.count();
		expect( count ).toBeGreaterThanOrEqual( 1 );
	} );

	test( 'honeypot field is hidden from users', async ( { page } ) => {
		const hp = page.locator( '.shqf-hp' );
		// The container exists in DOM.
		await expect( hp ).toHaveCount( 1 );
		// But is visually hidden (off-screen positioning).
		await expect( hp ).not.toBeVisible();
		// Has aria-hidden for screen readers.
		await expect( hp ).toHaveAttribute( 'aria-hidden', 'true' );
		// Input has tabindex=-1 so users can't tab to it.
		const input = hp.locator( 'input' );
		await expect( input ).toHaveAttribute( 'tabindex', '-1' );
	} );

	test( 'CSRF token present in form', async ( { page } ) => {
		const token = page.locator( 'input[name="shqf_token"]' );
		await expect( token ).toHaveCount( 1 );
		await expect( token ).toHaveAttribute( 'type', 'hidden' );
		// Token should be a non-empty string.
		const value = await token.getAttribute( 'value' );
		expect( value ).toBeTruthy();
		expect( value!.length ).toBeGreaterThanOrEqual( 16 );
	} );
} );
