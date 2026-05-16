import { test, expect } from '@playwright/test';

const SAMPLES_URL = '/wp-admin/admin.php?page=shqf-samples';

test.describe( 'Sample Library', () => {
	test( 'add sample with name and SKU', async ( { page } ) => {
		await page.goto( SAMPLES_URL + '&action=new' );

		const sku = 'E2E-' + Date.now();
		await page.fill( '#shqf-name', 'E2E Widget' );
		await page.fill( '#shqf-sku', sku );
		await page.click( '#submit' );

		// Save redirects to edit page with the name persisted.
		await page.waitForSelector( '#shqf-name' );
		await expect( page.locator( '#shqf-name' ) ).toHaveValue(
			'E2E Widget'
		);
	} );

	test( 'edit sample updates name', async ( { page } ) => {
		await page.goto( SAMPLES_URL + '&action=new' );
		await page.fill( '#shqf-name', 'Before Edit' );
		await page.fill( '#shqf-sku', 'EDIT-' + Date.now() );
		await page.click( '#submit' );
		await page.waitForSelector( '#shqf-name' );

		// Update name on the edit page.
		await page.fill( '#shqf-name', 'After Edit' );
		await page.click( '#submit' );
		await page.waitForSelector( '#shqf-name' );

		await expect( page.locator( '#shqf-name' ) ).toHaveValue(
			'After Edit'
		);
	} );

	test( 'trash and restore sample', async ( { page } ) => {
		const uniqueName = 'TrashTest-' + Date.now();

		// Create.
		await page.goto( SAMPLES_URL + '&action=new' );
		await page.fill( '#shqf-name', uniqueName );
		await page.fill( '#shqf-sku', 'TRASH-' + Date.now() );
		await page.click( '#submit' );
		await page.waitForSelector( '#shqf-name' );

		// Search for it in the list (avoids pagination).
		await page.goto( SAMPLES_URL );
		await page.locator( 'input[name="s"]' ).fill( uniqueName );
		await page.locator( '#search-submit' ).click();
		await expect(
			page.locator( `.wp-list-table tr:has-text("${ uniqueName }")` )
		).toBeVisible();

		// Trash via row action link href.
		const trashHref = await page
			.locator(
				`tr:has-text("${ uniqueName }") .row-actions a:has-text("Trash")`
			)
			.getAttribute( 'href' );
		expect( trashHref ).toBeTruthy();
		await page.goto( trashHref! );

		// Not in active list.
		await page.goto( SAMPLES_URL );
		await page.locator( 'input[name="s"]' ).fill( uniqueName );
		await page.locator( '#search-submit' ).click();
		await expect(
			page.locator( `.wp-list-table tr:has-text("${ uniqueName }")` )
		).toHaveCount( 0 );

		// Restore from trash.
		await page.goto( SAMPLES_URL + '&status=trashed' );
		const restoreHref = await page
			.locator(
				`tr:has-text("${ uniqueName }") .row-actions a:has-text("Restore")`
			)
			.getAttribute( 'href' );
		expect( restoreHref ).toBeTruthy();
		await page.goto( restoreHref! );

		// Back in active list.
		await page.goto( SAMPLES_URL );
		await page.locator( 'input[name="s"]' ).fill( uniqueName );
		await page.locator( '#search-submit' ).click();
		await expect(
			page.locator( `.wp-list-table tr:has-text("${ uniqueName }")` )
		).toBeVisible();
	} );
} );
