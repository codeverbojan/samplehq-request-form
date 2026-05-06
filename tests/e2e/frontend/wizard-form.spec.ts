import { test, expect } from '@playwright/test';
import * as fs from 'fs';

const fixtures = JSON.parse(
	fs.readFileSync( 'tests/e2e/.fixtures.json', 'utf-8' )
);
const WIZARD_URL = fixtures.pageUrls.wizard;

test.describe( 'Wizard form template', () => {
	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
		await page.goto( WIZARD_URL );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();
	} );

	test( 'stepper renders with 3 dots and labels', async ( { page } ) => {
		await expect( page.locator( '.shqf-stepper' ) ).toBeVisible();
		await expect( page.locator( '.shqf-step-dot' ) ).toHaveCount( 3 );
		await expect( page.locator( '.shqf-step-label' ).nth( 0 ) ).toContainText( 'Samples' );
		await expect( page.locator( '.shqf-step-label' ).nth( 1 ) ).toContainText( 'Your Info' );
	} );

	test( 'only step 0 visible initially', async ( { page } ) => {
		const step0 = page.locator( '.shqf-step[data-step="0"]' );
		const step1 = page.locator( '.shqf-step[data-step="1"]' );
		await expect( step0 ).toBeVisible();
		await expect( step1 ).toBeHidden();
	} );

	test( 'sample picker shows samples on step 0', async ( { page } ) => {
		await expect( page.locator( '.shqf-picker' ) ).toBeVisible();
		const items = page.locator( '.shqf-picker-item' );
		const count = await items.count();
		expect( count ).toBeGreaterThanOrEqual( 1 );
		// First seed sample name should be visible.
		await expect( page.locator( '.shqf-picker-item' ).first() ).toBeVisible();
	} );

	test( 'clicking sample selects it', async ( { page } ) => {
		const item = page.locator( '.shqf-picker-item' ).first();
		await item.click();
		await expect( item ).toHaveClass( /shqf-picker-item--selected/ );
	} );

	test( 'Next advances to step 1', async ( { page } ) => {
		// Select a sample (required).
		await page.locator( '.shqf-picker-item' ).first().click();
		// Click Next.
		await page.locator( '.shqf-button--next' ).click();
		// Step 1 visible, step 0 hidden.
		await expect( page.locator( '.shqf-step[data-step="1"]' ) ).toBeVisible();
		await expect( page.locator( '.shqf-step[data-step="0"]' ) ).toBeHidden();
	} );

	test( 'Back returns to step 0', async ( { page } ) => {
		await page.locator( '.shqf-picker-item' ).first().click();
		await page.locator( '.shqf-button--next' ).click();
		await expect( page.locator( '.shqf-step[data-step="1"]' ) ).toBeVisible();
		// Click Back.
		await page.locator( '.shqf-button--prev' ).click();
		await expect( page.locator( '.shqf-step[data-step="0"]' ) ).toBeVisible();
	} );

	test( 'cannot advance without selecting a sample', async ( { page } ) => {
		// Don't select anything. Click Next.
		await page.locator( '.shqf-button--next' ).click();
		// Should still be on step 0 (or an error is shown).
		const step0 = page.locator( '.shqf-step[data-step="0"]' );
		const step1 = page.locator( '.shqf-step[data-step="1"]' );
		// Either step 0 is still visible OR step 1 is hidden (validation blocked advance).
		const step0Visible = await step0.isVisible();
		const step1Hidden = await step1.isHidden();
		expect( step0Visible || step1Hidden ).toBeTruthy();
	} );

	test( 'full wizard submit', async ( { page } ) => {
		// Step 0: select sample.
		await page.locator( '.shqf-picker-item' ).first().click();
		await page.locator( '.shqf-button--next' ).click();
		await expect( page.locator( '.shqf-step[data-step="1"]' ) ).toBeVisible();

		// Step 1: fill contact fields.
		await page.locator( '.shqf-step[data-step="1"] input[name*="first_name"]' ).fill( 'Jane' );
		await page.locator( '.shqf-step[data-step="1"] input[name*="last_name"]' ).fill( 'Doe' );
		await page.locator( '.shqf-step[data-step="1"] input[type="email"]' ).fill( 'jane@example.com' );

		// Go to next step (step 2 if exists) or submit.
		await page.locator( '.shqf-button--next' ).click();

		// Submit (submit button should now be visible).
		const submitBtn = page.locator( '.shqf-button--submit' );
		await submitBtn.waitFor( { state: 'visible', timeout: 5000 } );
		await submitBtn.click();

		// Success message.
		await expect(
			page.locator( '.shqf-success' )
		).toBeVisible( { timeout: 10000 } );
	} );

	test( 'title renders', async ( { page } ) => {
		await expect(
			page.locator( '.shqf-title' ).first()
		).toContainText( 'Request a Sample' );
	} );
} );
