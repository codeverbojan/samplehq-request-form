import { test, expect } from '@playwright/test';

const TEMPLATES_URL = '/wp-admin/admin.php?page=shqf-forms&action=templates';
const FORMS_URL = '/wp-admin/admin.php?page=shqf-forms';

test.describe( 'Form templates', () => {
	test( 'template selector shows 4 templates', async ( { page } ) => {
		await page.goto( TEMPLATES_URL );
		await expect( page.locator( '.shqf-template-card' ) ).toHaveCount( 4 );
		await expect(
			page.locator( '.shqf-template-title' ).nth( 0 )
		).toContainText( 'Wizard' );
		await expect(
			page.locator( '.shqf-template-title' ).nth( 1 )
		).toContainText( 'Grid' );
		await expect(
			page.locator( '.shqf-template-title' ).nth( 2 )
		).toContainText( 'Checklist' );
		await expect(
			page.locator( '.shqf-template-title' ).nth( 3 )
		).toContainText( 'Scratch' );
	} );

	test( 'create form from wizard template', async ( { page } ) => {
		await page.goto( TEMPLATES_URL );
		// Click "Use Template" on the wizard card.
		await page
			.locator( '.shqf-template-card' )
			.nth( 0 )
			.locator( '.shqf-template-btn' )
			.click();
		// Should redirect to the builder with pre-configured fields.
		await expect( page.locator( '.shqf-builder-palette' ) ).toBeVisible();
		// Wizard template has a sample picker + name + email fields.
		const fields = await page.locator( '.shqf-builder-field' ).count();
		expect( fields ).toBeGreaterThanOrEqual( 3 );
	} );

	test( 'create form from grid template', async ( { page } ) => {
		await page.goto( TEMPLATES_URL );
		await page
			.locator( '.shqf-template-card' )
			.nth( 1 )
			.locator( '.shqf-template-btn' )
			.click();
		await expect( page.locator( '.shqf-builder-palette' ) ).toBeVisible();
		const fields = await page
			.locator( '.shqf-builder-field, .shqf-builder-row' )
			.count();
		expect( fields ).toBeGreaterThanOrEqual( 2 );
	} );

	test( 'create form from blank template', async ( { page } ) => {
		await page.goto( TEMPLATES_URL );
		// Blank template is the last card.
		await page
			.locator( '.shqf-template-card' )
			.nth( 3 )
			.locator( '.shqf-template-btn' )
			.click();
		await expect( page.locator( '.shqf-builder-palette' ) ).toBeVisible();
		// Blank form has no fields.
		await expect(
			page.locator( '.shqf-builder-canvas-empty' )
		).toBeVisible();
	} );

	test( 'duplicate form creates copy', async ( { page } ) => {
		await page.goto( FORMS_URL );
		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();
		// Get the duplicate link href (row actions hidden until hover).
		const duplicateHref = await page
			.locator( '.wp-list-table tbody tr' )
			.first()
			.locator( '.row-actions a', { hasText: 'Duplicate' } )
			.getAttribute( 'href' );
		expect( duplicateHref ).toBeTruthy();
		await page.goto( duplicateHref! );
		// Duplicate opens the builder with the copy.
		await expect( page.locator( '.shqf-builder-palette' ) ).toBeVisible();
		// Title should contain "Copy of".
		await expect( page.locator( '.shqf-builder-title' ) ).toHaveValue(
			/Copy of/
		);
	} );
} );
