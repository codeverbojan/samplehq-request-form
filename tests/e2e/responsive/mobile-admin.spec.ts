import { test, expect } from '@playwright/test';
import { loadFixtures } from '../load-fixtures';

let BLANK_FORM_ID: string;

test.describe( 'Mobile admin responsive', () => {
	test.beforeAll( () => {
		BLANK_FORM_ID = loadFixtures().formIds.blank;
	} );

	test( 'dashboard cards stack in single column', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-dashboard' );
		await expect( page.locator( '.shqf-dashboard-card' ).first() ).toBeVisible();

		// All cards should be approximately full viewport width (single column).
		const viewportWidth = page.viewportSize()?.width ?? 412;
		const cardWidths = await page.locator( '.shqf-dashboard-card' ).evaluateAll(
			( els ) => els.map( ( el ) => el.offsetWidth )
		);

		for ( const width of cardWidths ) {
			// Each card should span at least 90% of viewport (single column).
			expect( width ).toBeGreaterThan( viewportWidth * 0.9 );
		}
	} );

	test( 'admin menu accessible via mobile toggle', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-dashboard' );

		// Menu should be hidden on mobile.
		await expect( page.locator( '#adminmenu' ) ).toBeHidden();

		// Mobile toggle button should be visible.
		const toggle = page.locator( '#wp-admin-bar-menu-toggle' );
		await expect( toggle ).toBeVisible();

		// Click toggle to open menu.
		await toggle.click();
		await page.waitForTimeout( 300 );

		// Plugin menu item should now be visible.
		await expect( page.locator( '#adminmenu' ) ).toBeVisible();
		await expect( page.locator( '#adminmenu a:has-text("Sample Request Form"), #adminmenu a:has-text("samplehq")' ).first() ).toBeVisible();
	} );

	test( 'form builder panels accessible on mobile', async ( { page } ) => {
		await page.goto( `/wp-admin/admin.php?page=shqf-forms&action=edit&id=${ BLANK_FORM_ID }` );
		await page.waitForSelector( '.shqf-builder-palette' );

		// Palette should be visible (may be in a different layout on mobile).
		await expect( page.locator( '.shqf-builder-palette' ) ).toBeVisible();

		// Canvas should be visible.
		await expect( page.locator( '.shqf-builder-canvas-wrap' ) ).toBeVisible();

		// Header with save button should be accessible.
		await expect(
			page.locator( '.shqf-builder-header-right button', { hasText: 'Save' } )
		).toBeVisible();
	} );
} );
