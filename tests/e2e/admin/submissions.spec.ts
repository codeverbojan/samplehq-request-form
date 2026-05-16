import { test, expect } from '@playwright/test';

test.describe( 'Submissions page', () => {
	test( 'submissions page loads', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-submissions' );
		await expect( page.locator( 'h1' ) ).toContainText( 'Submissions' );
	} );

	test( 'tablenav filter area present', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-submissions' );

		// The top tablenav should be present (contains filters).
		await expect( page.locator( '.tablenav.top' ) ).toBeVisible();
	} );

	test( 'status tabs render', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-submissions' );

		// Should have view tabs (All at minimum).
		const viewLinks = page.locator( '.subsubsub a' );
		const count = await viewLinks.count();
		expect( count ).toBeGreaterThanOrEqual( 1 );
	} );

	test( 'empty state shows when no submissions', async ( { page } ) => {
		// Filter to a form that has no submissions.
		await page.goto(
			'/wp-admin/admin.php?page=shqf-submissions&form_id=99999'
		);

		// Should see empty state or "No items found".
		const hasEmptyState = await page
			.locator( '.shqf-empty-state-box' )
			.isVisible();
		const hasNoItems = await page.locator( '.no-items' ).isVisible();
		expect( hasEmptyState || hasNoItems ).toBeTruthy();
	} );
} );
