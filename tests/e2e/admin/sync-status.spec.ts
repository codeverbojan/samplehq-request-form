import { test, expect } from '@playwright/test';
import { loadFixtures } from '../load-fixtures';
import {
	seedConnectedState,
	seedFailedSync,
	createSubmission,
	cleanupConnectionState,
	wpEval,
} from '../helpers/connection';

const SUBMISSIONS_URL = '/wp-admin/admin.php?page=shqf-submissions';

function cleanupSubmission( id: number ): void {
	wpEval( `
		global $wpdb;
		$s = new SampleHQForm\\Database\\SubmissionsTable( $wpdb );
		$m = new SampleHQForm\\Database\\SubmissionMetaTable( $wpdb );
		$m->delete_all( ${ id } );
		$s->delete( ${ id } );
	` );
}

test.describe.configure( { mode: 'serial' } );

test.describe( 'Sync status', () => {
	let formId: number;
	const createdSubmissionIds: number[] = [];

	test.beforeAll( async () => {
		// Clear any pre-existing sync failures from previous test runs.
		wpEval( `
			global $wpdb;
			$table = $wpdb->prefix . 'shqf_submission_meta';
			$wpdb->query( "DELETE FROM {$table} WHERE field_key IN ('_sync_error', '_sync_attempts')" );
		` );
		cleanupConnectionState();

		const fixtures = loadFixtures();
		formId = parseInt(
			fixtures.formIds.blank || fixtures.formIds.wizard,
			10
		);
		expect( formId ).not.toBeNaN();
	} );

	test.afterAll( () => {
		for ( const id of createdSubmissionIds ) {
			cleanupSubmission( id );
		}
		cleanupConnectionState();
	} );

	test( 'no sync failure notice when no errors exist', async ( { page } ) => {
		await page.goto( SUBMISSIONS_URL );

		// No warning notice about sync issues.
		await expect(
			page.locator( '.notice-warning', { hasText: 'Sync Issues' } )
		).toBeHidden();
	} );

	test( 'sync failure notice shows on submissions list with error count', async ( {
		page,
	} ) => {
		seedConnectedState();

		// Create submissions with sync errors.
		const sub1 = createSubmission( formId, 'fail1@example.com' );
		const sub2 = createSubmission( formId, 'fail2@example.com' );
		createdSubmissionIds.push( sub1, sub2 );

		seedFailedSync( sub1, 'HTTP 401 - authentication_failed' );
		seedFailedSync( sub2, 'HTTP 401 - signature_invalid' );

		await page.goto( SUBMISSIONS_URL );

		// Warning notice present (filter to avoid WP core update notices).
		const notice = page.locator( '.notice-warning', {
			hasText: 'Sync Issues',
		} );
		await expect( notice ).toBeVisible();

		// Auth failure count in notice (both are auth errors).
		await expect( notice ).toContainText( 'connection issue' );

		// Reconnect link present.
		await expect(
			notice.locator( 'a[href*="tab=connection"]' )
		).toBeVisible();

		// Retry All Failed button present.
		await expect(
			notice.locator( 'a.button', { hasText: 'Retry All Failed' } )
		).toBeVisible();
	} );

	test( 'submission detail shows sync error and attempt count', async ( {
		page,
	} ) => {
		const subId = createdSubmissionIds[ 0 ];

		await page.goto(
			`/wp-admin/admin.php?page=shqf-submissions&action=view&id=${ subId }`
		);

		// Sync status shows "Failed" (in the Details sidebar, may need scroll).
		const failedSpan = page.locator( 'span', { hasText: 'Failed' } );
		await failedSpan.scrollIntoViewIfNeeded();
		await expect( failedSpan ).toBeVisible();

		// Error message in code block.
		const errorCode = page.locator( 'code', {
			hasText: 'authentication_failed',
		} );
		await expect( errorCode ).toBeVisible();

		// Attempt count (PHP renders "(3 attempts)").
		await expect(
			page.locator( 'em', { hasText: '(3 attempts)' } )
		).toBeVisible();
	} );

	test( 'retry sync button visible on failed submission detail', async ( {
		page,
	} ) => {
		const subId = createdSubmissionIds[ 0 ];

		await page.goto(
			`/wp-admin/admin.php?page=shqf-submissions&action=view&id=${ subId }`
		);

		// Retry Sync button present with correct action URL.
		const retryBtn = page.locator( 'a.button', {
			hasText: 'Retry Sync',
		} );
		await expect( retryBtn ).toBeVisible();
		await expect( retryBtn ).toHaveAttribute(
			'href',
			new RegExp( `action=retry_sync&id=${ subId }` )
		);
	} );
} );
