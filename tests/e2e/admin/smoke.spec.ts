import { test, expect } from '@playwright/test';

test.describe( 'Plugin smoke test', () => {
	test( 'admin menu appears', async ( { page, viewport } ) => {
		await page.goto( '/wp-admin/' );

		// WP admin collapses the sidebar menu below 782px.
		if ( viewport && viewport.width < 782 ) {
			await page.locator( '#wp-admin-bar-menu-toggle' ).click();
		}

		const menu = page.locator( '#adminmenu a:has-text("SampleHQ Forms")' );
		await expect( menu ).toBeVisible();
	} );

	test( 'dashboard page loads', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-dashboard' );
		await expect( page.locator( 'h1' ) ).toContainText( 'SampleHQ Forms' );
		await expect( page.locator( '.shqf-dashboard-cards' ) ).toBeVisible();
	} );

	test( 'sample library page loads', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-samples' );
		await expect( page.locator( 'h1' ) ).toContainText( 'Sample Library' );
	} );

	test( 'forms page loads', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-forms' );
		await expect( page.locator( 'h1' ) ).toContainText( 'Forms' );
	} );

	test( 'submissions page loads', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-submissions' );
		await expect( page.locator( 'h1' ) ).toContainText( 'Submissions' );
	} );

	test( 'settings page loads with 4 tabs', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-settings' );
		await expect( page.locator( 'h1' ) ).toContainText( 'Settings' );
		await expect( page.locator( '.nav-tab' ) ).toHaveCount( 4 );
	} );
} );
