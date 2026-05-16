import { test, expect } from '@playwright/test';
import { loadFixtures } from '../load-fixtures';

let ALL_FIELDS_URL: string;

test.describe( 'Sample picker', () => {
	test.beforeAll( () => {
		ALL_FIELDS_URL = loadFixtures().pageUrls.allFields;
	} );

	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
		await page.goto( ALL_FIELDS_URL );
		await expect( page.locator( '.shqf-picker' ) ).toBeVisible();
	} );

	test( 'grid picker renders active samples', async ( { page } ) => {
		const items = page.locator( '.shqf-picker-item' );
		// At least 5 seed samples in DOM.
		const count = await items.count();
		expect( count ).toBeGreaterThanOrEqual( 5 );
		// First sample card is visible.
		await expect( items.first() ).toBeVisible();
	} );

	test( 'click sample card selects it', async ( { page } ) => {
		const item = page.locator( '.shqf-picker-item' ).first();
		await item.click();
		await expect( item ).toHaveClass( /shqf-picker-item--selected/ );
		// The hidden checkbox inside should be checked.
		const checkbox = item.locator( 'input[type="checkbox"]' );
		await expect( checkbox ).toBeChecked();
	} );

	test( 'click again deselects it', async ( { page } ) => {
		const item = page.locator( '.shqf-picker-item' ).first();
		// Select.
		await item.click();
		await expect( item ).toHaveClass( /shqf-picker-item--selected/ );
		// Deselect.
		await item.click();
		await expect( item ).not.toHaveClass( /shqf-picker-item--selected/ );
		const checkbox = item.locator( 'input[type="checkbox"]' );
		await expect( checkbox ).not.toBeChecked();
	} );

	test( 'quantity stepper works', async ( { page } ) => {
		const item = page.locator( '.shqf-picker-item' ).first();
		await item.click();
		// Stepper controls should be visible on selected item.
		const controls = item.locator( '.shqf-picker-item-qty-controls' );
		await expect( controls ).toBeVisible();
		// Default display value is 1.
		await expect(
			controls.locator( '.shqf-picker-item-qty-value' )
		).toHaveText( '1' );
		// Click + twice.
		await controls.locator( '.shqf-qty-plus' ).click();
		await controls.locator( '.shqf-qty-plus' ).click();
		await expect(
			controls.locator( '.shqf-picker-item-qty-value' )
		).toHaveText( '3' );
		// Hidden input should be in sync.
		await expect( controls.locator( '.shqf-picker-item-qty' ) ).toHaveValue(
			'3'
		);
		// Click - once.
		await controls.locator( '.shqf-qty-minus' ).click();
		await expect(
			controls.locator( '.shqf-picker-item-qty-value' )
		).toHaveText( '2' );
	} );

	test( 'max selections enforced', async ( { page } ) => {
		const items = page.locator( '.shqf-picker-item' );
		// Select 3 items (the max).
		await items.nth( 0 ).click();
		await items.nth( 1 ).click();
		await items.nth( 2 ).click();
		// All 3 should be selected.
		await expect( items.nth( 0 ) ).toHaveClass(
			/shqf-picker-item--selected/
		);
		await expect( items.nth( 1 ) ).toHaveClass(
			/shqf-picker-item--selected/
		);
		await expect( items.nth( 2 ) ).toHaveClass(
			/shqf-picker-item--selected/
		);
		// 4th and 5th items' checkboxes should be disabled.
		const cb4 = items.nth( 3 ).locator( 'input[type="checkbox"]' );
		const cb5 = items.nth( 4 ).locator( 'input[type="checkbox"]' );
		await expect( cb4 ).toBeDisabled();
		await expect( cb5 ).toBeDisabled();
		// Deselect one -- 4th and 5th re-enable.
		await items.nth( 1 ).click();
		await expect( cb4 ).toBeEnabled();
		await expect( cb5 ).toBeEnabled();
	} );

	test( 'required validation shows error', async ( { page } ) => {
		// Don't select any sample. Try to submit.
		// Scroll to and click submit.
		await page.locator( '.shqf-button--submit' ).click();
		// Error should appear on the picker field.
		const pickerField = page.locator( '.shqf-field--sample_picker' );
		const errorEl = pickerField.locator( '.shqf-error' );
		await expect( errorEl ).toBeVisible();
		await expect( errorEl ).toContainText( 'select at least one sample' );
	} );

	test( 'sample descriptions display', async ( { page } ) => {
		// The seed samples have descriptions like "E2E test sample 1".
		const firstItem = page.locator( '.shqf-picker-item' ).first();
		await expect( firstItem ).toContainText( 'E2E test sample' );
	} );

	test( 'selection bar shows count', async ( { page } ) => {
		const bar = page.locator( '.shqf-selection-bar' );
		// Initially hidden.
		await expect( bar ).toBeHidden();
		// Select a sample.
		await page.locator( '.shqf-picker-item' ).first().click();
		// Bar should appear with count.
		await expect( bar ).toBeVisible();
		await expect( bar ).toContainText( '1 sample selected' );
		// Select another.
		await page.locator( '.shqf-picker-item' ).nth( 1 ).click();
		await expect( bar ).toContainText( '2 samples selected' );
		// Clear button resets.
		await bar.locator( '.shqf-selection-bar__clear' ).click();
		await expect( bar ).toBeHidden();
	} );
} );
