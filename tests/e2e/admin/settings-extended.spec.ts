import { test, expect } from '@playwright/test';

const SETTINGS_URL = '/wp-admin/admin.php?page=shqf-settings';

test.describe( 'Extended settings', () => {
	test( 'email tab confirmation toggle persists', async ( { page } ) => {
		await page.goto( SETTINGS_URL + '&tab=email' );
		const checkbox = page.locator( 'input[name="shqf_send_confirmation"]' );
		await expect( checkbox ).toBeVisible();

		// Uncheck confirmation.
		const wasChecked = await checkbox.isChecked();
		if ( wasChecked ) {
			await checkbox.uncheck();
		}
		await page.locator( '#submit' ).click();
		await page.waitForURL( /tab=email/ );

		// Verify still unchecked after reload.
		await expect( checkbox ).not.toBeChecked();

		// Restore original state.
		if ( wasChecked ) {
			await checkbox.check();
			await page.locator( '#submit' ).click();
		}
	} );

	test( 'general tab retention days boundary values', async ( { page } ) => {
		await page.goto( SETTINGS_URL + '&tab=general' );
		const retention = page.locator(
			'input[name="shqf_ip_retention_days"]'
		);

		// Min boundary: 1.
		await retention.fill( '1' );
		await page.locator( '#submit' ).click();
		await page.waitForURL( /tab=general/ );
		await expect( retention ).toHaveValue( '1' );

		// Max boundary: 365.
		await retention.fill( '365' );
		await page.locator( '#submit' ).click();
		await page.waitForURL( /tab=general/ );
		await expect( retention ).toHaveValue( '365' );

		// Restore default.
		await retention.fill( '90' );
		await page.locator( '#submit' ).click();
	} );

	test( 'spam tab clear Turnstile keys', async ( { page } ) => {
		await page.goto( SETTINGS_URL + '&tab=spam' );
		const siteKey = page.locator( 'input[name="shqf_turnstile_site_key"]' );
		const secretKey = page.locator(
			'input[name="shqf_turnstile_secret_key"]'
		);

		// Set test keys first.
		await siteKey.fill( 'temp_site_key' );
		await secretKey.fill( 'temp_secret_key' );
		await page.locator( '#submit' ).click();
		await page.waitForURL( /tab=spam/ );
		await expect( siteKey ).toHaveValue( 'temp_site_key' );

		// Now clear them.
		await siteKey.fill( '' );
		await secretKey.fill( '' );
		await page.locator( '#submit' ).click();
		await page.waitForURL( /tab=spam/ );

		// Verify empty after reload.
		await expect( siteKey ).toHaveValue( '' );
		await expect( secretKey ).toHaveValue( '' );
	} );

	test( 'screen options per-page controls list length', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-samples' );
		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();

		// Open Screen Options.
		await page.locator( '#show-settings-link' ).click();
		await page.waitForTimeout( 300 );

		const perPage = page.locator( '#shqf_samples_per_page' );
		await expect( perPage ).toBeVisible();

		// Set to 5.
		await perPage.fill( '5' );
		await page.locator( '#screen-options-apply' ).click();

		// Verify max 5 rows shown (could be fewer if < 5 samples).
		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();
		const rowCount = await page
			.locator( '.wp-list-table tbody tr' )
			.count();
		expect( rowCount ).toBeLessThanOrEqual( 5 );

		// Restore default.
		await page.locator( '#show-settings-link' ).click();
		await page.waitForTimeout( 300 );
		await perPage.fill( '20' );
		await page.locator( '#screen-options-apply' ).click();
	} );
} );
