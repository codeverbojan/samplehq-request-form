import { test, expect } from '@playwright/test';
import { loadFixtures } from '../load-fixtures';

let GRID_URL: string;

test.describe( 'Grid form template', () => {
	test.beforeAll( () => {
		GRID_URL = loadFixtures().pageUrls.grid;
	} );

	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
		await page.goto( GRID_URL );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();
	} );

	test( 'all fields visible on one page (no steps)', async ( { page } ) => {
		// Sample picker and contact fields should all be visible -- no step navigation.
		await expect( page.locator( '.shqf-picker' ) ).toBeVisible();
		await expect( page.locator( 'input[type="email"]' ) ).toBeVisible();
		// No stepper or step navigation.
		await expect( page.locator( '.shqf-stepper' ) ).toHaveCount( 0 );
		await expect( page.locator( '.shqf-button--next' ) ).toHaveCount( 0 );
		await expect( page.locator( '.shqf-button--prev' ) ).toHaveCount( 0 );
	} );

	test( 'sample picker uses grid layout', async ( { page } ) => {
		await expect( page.locator( '.shqf-picker--grid' ) ).toBeVisible();
		const items = page.locator( '.shqf-picker-item' );
		const count = await items.count();
		expect( count ).toBeGreaterThanOrEqual( 1 );
	} );

	test( 'form has grid layout modifier class', async ( { page } ) => {
		await expect(
			page.locator( '.shqf-form-wrapper--grid' )
		).toBeVisible();
	} );

	test( 'fill and submit grid form', async ( { page } ) => {
		// Select a sample.
		await page.locator( '.shqf-picker-item' ).first().click();
		await expect( page.locator( '.shqf-picker-item' ).first() ).toHaveClass(
			/shqf-picker-item--selected/
		);

		// Fill contact fields.
		await page.locator( 'input[name*="first_name"]' ).fill( 'Jane' );
		await page.locator( 'input[name*="last_name"]' ).fill( 'Doe' );
		await page.locator( 'input[type="email"]' ).fill( 'jane@example.com' );

		// Submit.
		await page.locator( '.shqf-button--submit' ).click();

		// Success message.
		await expect( page.locator( '.shqf-success' ) ).toBeVisible( {
			timeout: 10000,
		} );
	} );
} );
