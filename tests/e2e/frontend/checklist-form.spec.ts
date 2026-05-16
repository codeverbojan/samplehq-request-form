import { test, expect } from '@playwright/test';
import { loadFixtures } from '../load-fixtures';

let CHECKLIST_URL: string;

test.describe( 'Checklist form template', () => {
	test.beforeAll( () => {
		CHECKLIST_URL = loadFixtures().pageUrls.checklist;
	} );

	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
		await page.goto( CHECKLIST_URL );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();
	} );

	test( 'checklist form renders with list layout', async ( { page } ) => {
		await expect( page.locator( '.shqf-picker--list' ) ).toBeVisible();
		const items = page.locator( '.shqf-picker-item' );
		const count = await items.count();
		expect( count ).toBeGreaterThanOrEqual( 1 );
	} );

	test( 'form has list layout modifier class', async ( { page } ) => {
		await expect(
			page.locator( '.shqf-form-wrapper--list' )
		).toBeVisible();
	} );

	test( 'select samples via clicks', async ( { page } ) => {
		const item = page.locator( '.shqf-picker-item' ).first();
		await item.click();
		await expect( item ).toHaveClass( /shqf-picker-item--selected/ );
		// Click again to deselect.
		await item.click();
		await expect( item ).not.toHaveClass( /shqf-picker-item--selected/ );
	} );

	test( 'fill and submit checklist form', async ( { page } ) => {
		// Select a sample.
		await page.locator( '.shqf-picker-item' ).first().click();

		// Fill contact fields (checklist template has name + email).
		await page.locator( 'input[name*="first_name"]' ).fill( 'John' );
		await page.locator( 'input[name*="last_name"]' ).fill( 'Smith' );
		await page.locator( 'input[type="email"]' ).fill( 'john@example.com' );

		// Submit.
		await page.locator( '.shqf-button--submit' ).click();

		// Success message.
		await expect( page.locator( '.shqf-success' ) ).toBeVisible( {
			timeout: 10000,
		} );
	} );
} );
