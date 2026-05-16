import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loadFixtures } from '../load-fixtures';

const SUBS_URL = '/wp-admin/admin.php?page=shqf-submissions';

function wpEval( php: string ): string {
	const escaped = php.replace( /'/g, "'\\''" );
	const raw = execSync( `npx wp-env run cli -- wp eval '${ escaped }'`, {
		encoding: 'utf-8',
		timeout: 20000,
	} );
	return raw
		.split( '\n' )
		.filter(
			( l ) =>
				! l.startsWith( '\u001b' ) &&
				! l.includes( 'Starting' ) &&
				! l.includes( 'Ran `' ) &&
				l.trim() !== ''
		)
		.join( '\n' )
		.trim();
}

function lastLine( s: string ): string {
	return s.split( '\n' ).pop()?.trim() || '';
}

/**
 * Create a test submission via DB and return its ID.
 * @param email
 */
function createSubmission( email: string ): string {
	const formId = loadFixtures().formIds.grid;
	return lastLine(
		wpEval( `
		global $wpdb;
		$subs = new SampleHQForm\\Database\\SubmissionsTable($wpdb);
		$meta = new SampleHQForm\\Database\\SubmissionMetaTable($wpdb);
		$id = $subs->create(${ formId }, ["email" => "${ email }", "first_name" => "Test", "last_name" => "User"], []);
		$meta->add_many($id, ["email" => "${ email }", "first_name" => "Test", "last_name" => "User"]);
		echo $id;
	` )
	);
}

test.describe( 'Submission workflow', () => {
	test( 'star/unstar toggle persists', async ( { page } ) => {
		const email = `star-${ Date.now() }@test.com`;
		const id = createSubmission( email );

		// Go to submission detail (avoids pagination).
		await page.goto( `${ SUBS_URL }&action=view&id=${ id }` );
		await expect( page.locator( 'h1' ) ).toContainText( 'Submission' );

		// Star button should exist.
		const starBtn = page.locator(
			'a:has-text("Star"), a:has-text("Unstar")'
		);
		await expect( starBtn ).toBeVisible();

		// Click to star.
		const starHref = await starBtn.getAttribute( 'href' );
		await page.goto( starHref! );

		// Verify starred -- go back to detail, should show Unstar.
		await page.goto( `${ SUBS_URL }&action=view&id=${ id }` );
		await expect( page.locator( 'a:has-text("Unstar")' ) ).toBeVisible();

		// Click to unstar.
		const unstarHref = await page
			.locator( 'a:has-text("Unstar")' )
			.getAttribute( 'href' );
		await page.goto( unstarHref! );
		await page.goto( `${ SUBS_URL }&action=view&id=${ id }` );
		await expect( page.locator( 'a:has-text("Star")' ) ).toBeVisible();
	} );

	test( 'mark as spam and restore', async ( { page } ) => {
		const email = `spam-${ Date.now() }@test.com`;
		const id = createSubmission( email );

		// Mark as spam via direct URL.
		await page.goto( `${ SUBS_URL }&action=view&id=${ id }` );
		const spamHref = await page
			.locator( 'a:has-text("Mark as Spam"), a:has-text("Spam")' )
			.first()
			.getAttribute( 'href' );
		await page.goto( spamHref! );

		// Verify in spam view.
		await page.goto( SUBS_URL + '&status=spam' );
		await expect(
			page.locator( `tr:has-text("${ email }")` )
		).toBeVisible();

		// Restore from spam.
		const restoreHref = await page
			.locator(
				`tr:has-text("${ email }") .row-actions a:has-text("Restore")`
			)
			.getAttribute( 'href' );
		await page.goto( restoreHref! );

		// Verify back in all.
		await page.goto( SUBS_URL );
		await expect(
			page.locator( `.wp-list-table td:has-text("${ email }")` )
		).toBeVisible();
	} );

	test( 'trash and restore submission', async ( { page } ) => {
		const email = `trash-${ Date.now() }@test.com`;
		createSubmission( email );

		// Trash via list row action.
		await page.goto( SUBS_URL );
		const trashHref = await page
			.locator(
				`tr:has-text("${ email }") .row-actions a:has-text("Trash")`
			)
			.getAttribute( 'href' );
		await page.goto( trashHref! );

		// Verify in trash view.
		await page.goto( SUBS_URL + '&status=trash' );
		await expect(
			page.locator( `tr:has-text("${ email }")` )
		).toBeVisible();

		// Restore.
		const restoreHref = await page
			.locator(
				`tr:has-text("${ email }") .row-actions a:has-text("Restore")`
			)
			.getAttribute( 'href' );
		await page.goto( restoreHref! );

		// Verify back.
		await page.goto( SUBS_URL );
		await expect(
			page.locator( `.wp-list-table td:has-text("${ email }")` )
		).toBeVisible();
	} );

	test( 'delete permanently', async ( { page } ) => {
		const email = `delete-${ Date.now() }@test.com`;
		const id = createSubmission( email );

		// Trash first.
		wpEval( `
			global $wpdb;
			$subs = new SampleHQForm\\Database\\SubmissionsTable($wpdb);
			$subs->update_status(${ id }, "trash");
		` );

		// Go to trash, delete permanently.
		await page.goto( SUBS_URL + '&status=trash' );
		const deleteHref = await page
			.locator(
				`tr:has-text("${ email }") .row-actions a:has-text("Delete")`
			)
			.getAttribute( 'href' );
		await page.goto( deleteHref! );

		// Verify gone.
		await page.goto( SUBS_URL + '&status=trash' );
		await expect( page.locator( `tr:has-text("${ email }")` ) ).toHaveCount(
			0
		);
	} );

	test( 'bulk mark as read', async ( { page } ) => {
		const email1 = `read1-${ Date.now() }@test.com`;
		const email2 = `read2-${ Date.now() }@test.com`;
		const id1 = createSubmission( email1 );
		const id2 = createSubmission( email2 );

		await page.goto( SUBS_URL );
		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();

		// Check both submissions.
		await page
			.locator( `input[name="submission_ids[]"][value="${ id1 }"]` )
			.check( { force: true } );
		await page
			.locator( `input[name="submission_ids[]"][value="${ id2 }"]` )
			.check( { force: true } );

		// Apply "Mark as Read".
		await page
			.locator( '#bulk-action-selector-top' )
			.selectOption( 'bulk_read' );
		await page.locator( '#doaction' ).click();

		// Verify both submissions are marked as read via DB.
		const isRead1 = lastLine(
			wpEval( `
			global $wpdb;
			$subs = new SampleHQForm\\Database\\SubmissionsTable($wpdb);
			$s = $subs->get(${ id1 });
			echo $s["is_read"];
		` )
		);
		const isRead2 = lastLine(
			wpEval( `
			global $wpdb;
			$subs = new SampleHQForm\\Database\\SubmissionsTable($wpdb);
			$s = $subs->get(${ id2 });
			echo $s["is_read"];
		` )
		);
		expect( isRead1 ).toBe( '1' );
		expect( isRead2 ).toBe( '1' );
	} );

	test( 'view submission detail shows field values', async ( { page } ) => {
		const email = `detail-${ Date.now() }@test.com`;
		const id = createSubmission( email );

		await page.goto( `${ SUBS_URL }&action=view&id=${ id }` );
		await expect( page.locator( 'h1' ) ).toContainText( 'Submission #' );

		// Fields table should contain the email.
		const fieldsTable = page.locator( '.postbox .widefat' ).first();
		await expect( fieldsTable ).toBeVisible();
		await expect( fieldsTable ).toContainText( email );
		await expect( fieldsTable ).toContainText( 'Test' );
	} );

	test( 'form filter dropdown works', async ( { page } ) => {
		await page.goto( SUBS_URL );
		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();

		const totalBefore = await page
			.locator( '.wp-list-table tbody tr' )
			.count();

		// Filter by a specific form.
		const formFilter = page.locator( 'select[name="form_id"]' );
		const formValue = await formFilter
			.locator( 'option' )
			.nth( 1 )
			.getAttribute( 'value' );
		await formFilter.selectOption( formValue! );
		await page.locator( '#filter_action' ).click();

		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();
		const filtered = await page
			.locator( '.wp-list-table tbody tr' )
			.count();
		// Filtered results should be <= total (could be equal if all belong to that form).
		expect( filtered ).toBeLessThanOrEqual( totalBefore );
		expect( filtered ).toBeGreaterThanOrEqual( 1 );
	} );

	test( 'CSV export downloads file with content', async ( { page } ) => {
		await page.goto( SUBS_URL );

		// Set up download listener before clicking.
		const downloadPromise = page.waitForEvent( 'download', {
			timeout: 10000,
		} );
		await page.locator( 'a:has-text("Export CSV")' ).click();

		const download = await downloadPromise;
		const filename = download.suggestedFilename();
		expect( filename ).toContain( '.csv' );

		// Verify file has content (header row + at least one data row).
		const filePath = await download.path();
		if ( filePath ) {
			const content = require( 'fs' ).readFileSync( filePath, 'utf-8' );
			const lines = content.trim().split( '\n' );
			expect( lines.length ).toBeGreaterThanOrEqual( 2 );
			// Header should contain "email".
			expect( lines[ 0 ].toLowerCase() ).toContain( 'email' );
		}
	} );
} );
