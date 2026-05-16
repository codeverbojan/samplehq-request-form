/**
 * Playwright setup for Herd-based tests.
 *
 * Authenticates against the local Herd WordPress site at shqf-e2e-test.test.
 */

import { test as setup, expect } from '@playwright/test';

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'password';
const AUTH_FILE = 'tests/e2e/.auth/herd-admin.json';

setup( 'authenticate on Herd site', async ( { page } ) => {
	await page.goto( '/wp-login.php' );
	await page.waitForLoadState( 'domcontentloaded' );

	// WP login JS sets autofocus and may clear fields on load.
	// Use evaluate to set values directly on the DOM, bypassing race conditions.
	await page.evaluate(
		( { user, pass } ) => {
			const loginEl = document.querySelector< HTMLInputElement >( '#user_login' );
			const passEl = document.querySelector< HTMLInputElement >( '#user_pass' );
			if ( loginEl ) {
				loginEl.value = user;
				loginEl.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
			if ( passEl ) {
				passEl.value = pass;
				passEl.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
		},
		{ user: ADMIN_USER, pass: ADMIN_PASS }
	);

	await expect( page.locator( '#user_login' ) ).toHaveValue( ADMIN_USER );
	await expect( page.locator( '#user_pass' ) ).toHaveValue( ADMIN_PASS );

	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/, { timeout: 10000 } );
	await page.context().storageState( { path: AUTH_FILE } );
} );
