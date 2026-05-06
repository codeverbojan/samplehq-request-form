import { test, expect } from '@playwright/test';

test.describe( 'Settings page', () => {
	test( 'General tab saves privacy settings', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-settings&tab=general' );

		// Set retention days.
		await page.fill( 'input[name="shqf_ip_retention_days"]', '30' );
		await page.click( '#submit' );

		// Should redirect back to General tab with saved values.
		await page.waitForURL( /page=shqf-settings/ );

		const retentionValue = await page.inputValue( 'input[name="shqf_ip_retention_days"]' );
		expect( retentionValue ).toBe( '30' );

		// Restore default.
		await page.fill( 'input[name="shqf_ip_retention_days"]', '90' );
		await page.click( '#submit' );
	} );

	test( 'Spam tab saves Turnstile keys', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-settings&tab=spam' );

		await page.fill( 'input[name="shqf_turnstile_site_key"]', 'test_site_key_123' );
		await page.fill( 'input[name="shqf_turnstile_secret_key"]', 'test_secret_456' );
		await page.click( '#submit' );

		await page.waitForURL( /tab=spam/ );

		const siteKey = await page.inputValue( 'input[name="shqf_turnstile_site_key"]' );
		expect( siteKey ).toBe( 'test_site_key_123' );

		// Clean up.
		await page.fill( 'input[name="shqf_turnstile_site_key"]', '' );
		await page.fill( 'input[name="shqf_turnstile_secret_key"]', '' );
		await page.click( '#submit' );
	} );

	test( 'Email tab saves notification defaults', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-settings&tab=email' );

		await page.fill( 'input[name="shqf_notification_email"]', 'team@example.com' );
		await page.fill( 'input[name="shqf_from_name"]', 'Test Brand' );
		await page.click( '#submit' );

		await page.waitForURL( /tab=email/ );

		const email = await page.inputValue( 'input[name="shqf_notification_email"]' );
		expect( email ).toBe( 'team@example.com' );

		const fromName = await page.inputValue( 'input[name="shqf_from_name"]' );
		expect( fromName ).toBe( 'Test Brand' );

		// Clean up.
		await page.fill( 'input[name="shqf_notification_email"]', '' );
		await page.fill( 'input[name="shqf_from_name"]', '' );
		await page.click( '#submit' );
	} );

	test( 'Connection tab shows disconnected state', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-settings&tab=connection' );

		await expect( page.locator( '.shqf-connection-status--disconnected' ) ).toBeVisible();
		await expect( page.locator( 'text=Not connected' ) ).toBeVisible();
	} );
} );
