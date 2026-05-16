import { test, expect } from '@playwright/test';

const SETTINGS_URL = '/wp-admin/admin.php?page=shqf-settings';

test.describe( 'Settings security', () => {
	test( 'Turnstile secret field shows placeholder and page source does not contain actual secret', async ( {
		page,
	} ) => {
		// First, set a known secret value.
		await page.goto( SETTINGS_URL + '&tab=spam' );
		await page.fill( 'input[name="shqf_turnstile_site_key"]', 'test_site_key_sec' );
		await page.fill( 'input[name="shqf_turnstile_secret_key"]', 'real_secret_value_abc123' );
		await page.locator( '#submit' ).click();
		await page.waitForURL( /tab=spam/ );

		// Reload the page to verify the secret is masked.
		await page.goto( SETTINGS_URL + '&tab=spam' );

		// Site key is shown in plain text (it's not a secret).
		const siteKey = page.locator( 'input[name="shqf_turnstile_site_key"]' );
		await expect( siteKey ).toHaveValue( 'test_site_key_sec' );

		// Secret field should show the mask placeholder, NOT the actual secret.
		const secretField = page.locator( 'input[name="shqf_turnstile_secret_key"]' );
		const secretValue = await secretField.inputValue();
		expect( secretValue ).not.toContain( 'real_secret_value_abc123' );
		expect( secretValue.length ).toBeGreaterThan( 0 ); // mask is non-empty

		// Verify the actual secret is NOT anywhere in the page HTML source.
		const pageContent = await page.content();
		expect( pageContent ).not.toContain( 'real_secret_value_abc123' );

		// Clean up: clear both keys.
		await page.fill( 'input[name="shqf_turnstile_site_key"]', '' );
		await page.fill( 'input[name="shqf_turnstile_secret_key"]', '' );
		await page.locator( '#submit' ).click();
	} );

	test( 'saving settings with all fields empty does not crash', async ( {
		page,
	} ) => {
		// General tab with empty fields.
		await page.goto( SETTINGS_URL + '&tab=general' );
		const retentionInput = page.locator( 'input[name="shqf_ip_retention_days"]' );
		await retentionInput.fill( '' );
		await page.locator( '#submit' ).click();
		await page.waitForURL( /tab=general/ );

		// Page loads without error — retention defaults to 0 when empty.
		await expect( retentionInput ).toBeVisible();

		// Spam tab with empty fields.
		await page.goto( SETTINGS_URL + '&tab=spam' );
		await page.fill( 'input[name="shqf_turnstile_site_key"]', '' );
		await page.fill( 'input[name="shqf_turnstile_secret_key"]', '' );
		await page.locator( '#submit' ).click();
		await page.waitForURL( /tab=spam/ );

		// Page loads without error.
		await expect(
			page.locator( 'input[name="shqf_turnstile_site_key"]' )
		).toBeVisible();

		// Email tab with empty fields.
		await page.goto( SETTINGS_URL + '&tab=email' );
		await page.fill( 'input[name="shqf_notification_email"]', '' );
		await page.fill( 'input[name="shqf_from_name"]', '' );
		await page.locator( '#submit' ).click();
		await page.waitForURL( /tab=email/ );

		// Page loads without error.
		await expect(
			page.locator( 'input[name="shqf_notification_email"]' )
		).toBeVisible();

		// Restore defaults.
		await page.goto( SETTINGS_URL + '&tab=general' );
		await retentionInput.fill( '90' );
		await page.locator( '#submit' ).click();
	} );
} );
