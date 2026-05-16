import { test, expect } from '@playwright/test';
import {
	seedConnectedState,
	seedDisconnectedState,
	seedMigrationProgress,
	cleanupConnectionState,
} from '../helpers/connection';

const MIGRATION_URL = '/wp-admin/admin.php?page=shqf-settings&tab=migration';
const SETTINGS_URL = '/wp-admin/admin.php?page=shqf-settings&tab=connection';

test.describe.configure( { mode: 'serial' } );

test.describe( 'Migration wizard', () => {
	test.afterEach( () => {
		cleanupConnectionState();
	} );

	test( 'migration tab hidden when disconnected', async ( { page } ) => {
		seedDisconnectedState();

		// Navigate and immediately re-seed to guard against parallel spec races.
		await page.goto( SETTINGS_URL );
		// If a parallel spec re-seeded connected state, reload after cleanup.
		const migTab = page.locator( '.nav-tab', { hasText: 'Migration' } );
		if ( await migTab.isVisible().catch( () => false ) ) {
			seedDisconnectedState();
			await page.reload();
		}

		await expect( migTab ).toBeHidden();
	} );

	test( 'preview step shows category, sample, and submission counts', async ( {
		page,
	} ) => {
		seedConnectedState();

		// Intercept the preview REST call to return mock counts.
		await page.route( '**/wp-json/samplehq-form/v1/migration/preview', ( route ) =>
			route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					categories: 3,
					samples: 12,
					submissions: 45,
					platform: { max_samples: 100, current_samples: 20 },
					error: null,
				} ),
			} )
		);

		// Intercept progress call (wizard checks on load).
		await page.route( '**/wp-json/samplehq-form/v1/migration/progress', ( route ) =>
			route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( { phase: 'idle' } ),
			} )
		);

		await page.goto( MIGRATION_URL );

		const preview = page.locator( '#shqf-mig-preview' );
		await expect( preview ).toBeVisible();

		// Counts in the preview table (scoped to rows to avoid ambiguity).
		const rows = preview.locator( 'table tr' );
		await expect( rows.nth( 0 ) ).toContainText( '3' ); // Categories
		await expect( rows.nth( 1 ) ).toContainText( '12' ); // Samples
		await expect( rows.nth( 2 ) ).toContainText( '45' ); // Submissions

		// Start button visible.
		await expect(
			page.locator( '#shqf-mig-start' )
		).toBeVisible();

		// Submissions checkbox present (since submissions > 0).
		await expect(
			page.locator( '#shqf-mig-include-subs' )
		).toBeVisible();
	} );

	test( 'running state shows progress bar and cancel button', async ( {
		page,
	} ) => {
		seedConnectedState();

		// Intercept the progress REST call to return mid-phase data.
		await page.route( '**/wp-json/samplehq-form/v1/migration/progress', ( route ) =>
			route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					phase: 'samples',
					categories_total: 3,
					categories_completed: 3,
					categories_created: 2,
					categories_updated: 1,
					samples_total: 10,
					samples_completed: 4,
					samples_created: 4,
					samples_updated: 0,
					samples_skipped: 0,
					submissions_total: 0,
					submissions_completed: 0,
				} ),
			} )
		);

		await page.goto( MIGRATION_URL );

		// Guard against parallel spec race deleting connection between seed and load.
		const migTab = page.locator( '.nav-tab', { hasText: 'Migration' } );
		if ( ! ( await migTab.isVisible().catch( () => false ) ) ) {
			seedConnectedState();
			await page.reload();
		}

		// Running step visible.
		const running = page.locator( '#shqf-mig-running' );
		await expect( running ).toBeVisible();

		// Phase label.
		await expect( page.locator( '#shqf-mig-phase' ) ).toContainText(
			'samples'
		);

		// Progress bar has nonzero width (7 of 13 total = ~54%).
		const bar = page.locator( '#shqf-mig-bar' );
		await expect( bar ).toBeVisible();
		const width = await bar.evaluate( ( el ) => el.style.width );
		expect( parseInt( width ) ).toBeGreaterThan( 0 );

		// Detail counter.
		await expect( page.locator( '#shqf-mig-detail' ) ).toContainText( '7' );

		// Cancel button.
		await expect( page.locator( '#shqf-mig-cancel' ) ).toBeVisible();
		await expect( page.locator( '#shqf-mig-cancel' ) ).toBeEnabled();
	} );

	test( 'complete state shows summary table with counts', async ( {
		page,
	} ) => {
		seedConnectedState();

		// Intercept progress to return complete phase with summary data.
		await page.route( '**/wp-json/samplehq-form/v1/migration/progress', ( route ) =>
			route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					phase: 'complete',
					categories_total: 5,
					categories_completed: 5,
					categories_created: 3,
					categories_updated: 2,
					samples_total: 15,
					samples_completed: 15,
					samples_created: 10,
					samples_updated: 3,
					samples_skipped: 2,
					submissions_total: 20,
					submissions_completed: 20,
					submissions_accepted: 18,
					submissions_duplicates: 2,
					include_submissions: true,
				} ),
			} )
		);

		await page.goto( MIGRATION_URL );

		// Guard against parallel spec race deleting connection between seed and load.
		const migTab = page.locator( '.nav-tab', { hasText: 'Migration' } );
		if ( ! ( await migTab.isVisible().catch( () => false ) ) ) {
			seedConnectedState();
			await page.reload();
		}

		// Complete step visible.
		const complete = page.locator( '#shqf-mig-complete' );
		await expect( complete ).toBeVisible();

		// Success notice.
		await expect( complete.locator( '.notice-success' ) ).toBeVisible();

		// Summary table contains expected values.
		const summary = page.locator( '#shqf-mig-summary' );
		await expect( summary ).toContainText( '3' ); // categories created
		await expect( summary ).toContainText( '2' ); // categories updated
		await expect( summary ).toContainText( '10' ); // samples created
		await expect( summary ).toContainText( '18' ); // submissions accepted
	} );
} );
