import { test, expect } from '@playwright/test';
import { loadFixtures } from '../load-fixtures';

let COND_URL: string;

test.describe( 'Conditional logic', () => {
	test.beforeAll( () => {
		COND_URL = loadFixtures().pageUrls.conditional;
	} );

	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
		await page.goto( COND_URL );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();
	} );

	test( 'field hidden when condition not met', async ( { page } ) => {
		// Trigger field should be visible.
		await expect(
			page.locator( '.shqf-field[data-field-key="trigger"]' )
		).toBeVisible();
		// Conditional field should be hidden (display:none + aria-hidden).
		const condField = page.locator(
			'.shqf-field[data-field-key="conditional_field"]'
		);
		await expect( condField ).toBeHidden();
		await expect( condField ).toHaveAttribute( 'aria-hidden', 'true' );
	} );

	test( 'field shows when condition met', async ( { page } ) => {
		const condField = page.locator(
			'.shqf-field[data-field-key="conditional_field"]'
		);
		await expect( condField ).toBeHidden();

		// Type the trigger value.
		await page
			.locator( 'input[name="shqf_fields[trigger]"]' )
			.fill( 'show' );

		// Conditional field should now be visible.
		await expect( condField ).toBeVisible();
		// aria-hidden should be removed.
		await expect( condField ).not.toHaveAttribute( 'aria-hidden', 'true' );
		// Input inside should be enabled.
		await expect( condField.locator( 'input' ) ).toBeEnabled();
	} );

	test( 'field hides again when condition cleared', async ( { page } ) => {
		const condField = page.locator(
			'.shqf-field[data-field-key="conditional_field"]'
		);

		// Show the field.
		await page
			.locator( 'input[name="shqf_fields[trigger]"]' )
			.fill( 'show' );
		await expect( condField ).toBeVisible();

		// Clear the trigger.
		await page.locator( 'input[name="shqf_fields[trigger]"]' ).fill( '' );
		await expect( condField ).toBeHidden();
		// Input inside should be disabled.
		await expect( condField.locator( 'input' ) ).toBeDisabled();
	} );

	test( 'hidden field excluded from submission', async ( { page } ) => {
		// Fill the conditional field value while it's visible.
		await page
			.locator( 'input[name="shqf_fields[trigger]"]' )
			.fill( 'show' );
		const condInput = page.locator(
			'.shqf-field[data-field-key="conditional_field"] input'
		);
		await expect( condInput ).toBeVisible();
		await condInput.fill( 'secret data' );

		// Now hide it by clearing the trigger.
		await page.locator( 'input[name="shqf_fields[trigger]"]' ).fill( '' );
		await expect( condInput ).toBeDisabled();

		// Fill required fields and submit.
		await page.locator( '.shqf-picker-item' ).first().click();
		await page.locator( 'input[name*="first_name"]' ).fill( 'Cond' );
		await page.locator( 'input[name*="last_name"]' ).fill( 'Test' );
		await page.locator( 'input[type="email"]' ).fill( 'cond@test.com' );

		// Intercept the AJAX request to verify the payload.
		const [ request ] = await Promise.all( [
			page.waitForRequest(
				( req ) =>
					req.url().includes( '/submissions' ) &&
					req.method() === 'POST'
			),
			page.locator( '.shqf-button--submit' ).click(),
		] );

		const body = request.postDataJSON();
		// The conditional_field should NOT be in the submitted data (disabled = excluded).
		const fields = body.shqf_fields || {};
		expect( fields ).not.toHaveProperty( 'conditional_field' );

		// Verify submission succeeded.
		await expect( page.locator( '.shqf-success' ) ).toBeVisible( {
			timeout: 10000,
		} );
	} );
} );
