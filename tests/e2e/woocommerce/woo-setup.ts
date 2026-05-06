/**
 * WooCommerce E2E setup: authenticate as admin.
 */

import { test as setup, expect } from '@playwright/test';

const AUTH_FILE = 'tests/e2e/woocommerce/.auth/admin.json';

setup( 'authenticate as admin', async ( { page } ) => {
	await page.goto( '/wp-login.php' );
	await page.waitForLoadState( 'networkidle' );

	// Click into username field, clear, type.
	const userField = page.locator( '#user_login' );
	await userField.click();
	await userField.fill( '' );
	await userField.type( 'bojan', { delay: 30 } );

	// Click into password field, clear, type.
	const passField = page.locator( '#user_pass' );
	await passField.click();
	await passField.fill( '' );
	await passField.type( 'e2e-test-pass-2026', { delay: 30 } );

	// Verify fields before submitting.
	await expect( userField ).toHaveValue( 'bojan' );
	await expect( passField ).toHaveValue( 'e2e-test-pass-2026' );

	await page.locator( '#wp-submit' ).click();
	await page.waitForLoadState( 'networkidle' );

	// Should be on admin dashboard now.
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible( {
		timeout: 15000,
	} );

	await page.context().storageState( { path: AUTH_FILE } );
} );
