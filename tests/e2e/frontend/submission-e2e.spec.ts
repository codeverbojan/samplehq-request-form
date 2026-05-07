import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loadFixtures } from '../load-fixtures';

let WIZARD_URL: string;
let GRID_URL: string;

test.beforeAll( () => {
	const f = loadFixtures();
	WIZARD_URL = f.pageUrls.wizard;
	GRID_URL = f.pageUrls.grid;
} );

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

// These tests use admin auth (from storageState) -- do NOT clearCookies.
// The forms work identically for logged-in and anonymous users.

test.describe( 'Submission end-to-end', () => {
	test( 'submit wizard form shows success', async ( { page } ) => {
		await page.goto( WIZARD_URL );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();

		// Step 0: select sample.
		await page.locator( '.shqf-picker-item' ).first().click();
		await page.locator( '.shqf-button--next' ).click();
		await expect( page.locator( '.shqf-step[data-step="1"]' ) ).toBeVisible();

		// Step 1: fill contact fields.
		await page.locator( '.shqf-step[data-step="1"] input[name*="first_name"]' ).fill( 'E2E' );
		await page.locator( '.shqf-step[data-step="1"] input[name*="last_name"]' ).fill( 'Tester' );
		await page.locator( '.shqf-step[data-step="1"] input[type="email"]' ).fill( `wizard-${ Date.now() }@test.com` );

		// Step 2: submit.
		await page.locator( '.shqf-button--next' ).click();
		const submitBtn = page.locator( '.shqf-button--submit' );
		await submitBtn.waitFor( { state: 'visible', timeout: 5000 } );
		await submitBtn.click();

		await expect(
			page.locator( '.shqf-success' )
		).toBeVisible( { timeout: 10000 } );
	} );

	test( 'submission appears in admin list', async ( { page } ) => {
		// First submit a form with a unique email.
		const email = `admin-list-${ Date.now() }@test.com`;
		await page.goto( GRID_URL );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();

		await page.locator( '.shqf-picker-item' ).first().click();
		await page.locator( 'input[name*="first_name"]' ).fill( 'List' );
		await page.locator( 'input[name*="last_name"]' ).fill( 'Check' );
		await page.locator( 'input[type="email"]' ).fill( email );
		await page.locator( '.shqf-button--submit' ).click();
		await expect( page.locator( '.shqf-success' ) ).toBeVisible( { timeout: 10000 } );

		// Navigate to admin submissions page.
		await page.goto( '/wp-admin/admin.php?page=shqf-submissions' );
		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();

		// The submitted email should appear in the table.
		await expect( page.locator( `.wp-list-table td:has-text("${ email }")` ) ).toBeVisible();
	} );

	test( 'submission detail shows field values', async ( { page } ) => {
		// Submit with known values.
		const email = `detail-${ Date.now() }@test.com`;
		await page.goto( GRID_URL );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();

		await page.locator( '.shqf-picker-item' ).first().click();
		await page.locator( 'input[name*="first_name"]' ).fill( 'Detail' );
		await page.locator( 'input[name*="last_name"]' ).fill( 'Test' );
		await page.locator( 'input[type="email"]' ).fill( email );
		await page.locator( '.shqf-button--submit' ).click();
		await expect( page.locator( '.shqf-success' ) ).toBeVisible( { timeout: 10000 } );

		// Go to submissions list, click View on the new submission.
		await page.goto( '/wp-admin/admin.php?page=shqf-submissions' );
		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();

		// Find the row with our email and click View.
		const row = page.locator( `tr:has-text("${ email }")` );
		await expect( row ).toBeVisible();
		await row.locator( 'a:has-text("View")' ).click();

		// Verify we're on the detail page.
		await expect( page.locator( 'h1' ) ).toContainText( 'Submission #' );

		// The submitted fields table should show our values.
		const fieldsTable = page.locator( '.postbox .widefat' ).first();
		await expect( fieldsTable ).toBeVisible();
		await expect( fieldsTable ).toContainText( email );
		await expect( fieldsTable ).toContainText( 'Detail' );
	} );

	test( 'submission count increments on form', async ( { page } ) => {
		const gridFormId = loadFixtures().formIds.grid;

		// Get count before via DB query.
		const beforeRaw = wpEval( `
			global $wpdb;
			$forms = new SampleHQForm\\Database\\FormsTable($wpdb);
			$form = $forms->get(${ gridFormId });
			echo $form["submissions_count"] ?? "0";
		` );
		const before = parseInt( beforeRaw.split( '\n' ).pop()?.trim() || '0', 10 );

		// Submit a form.
		await page.goto( GRID_URL );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();
		await page.locator( '.shqf-picker-item' ).first().click();
		await page.locator( 'input[name*="first_name"]' ).fill( 'Count' );
		await page.locator( 'input[name*="last_name"]' ).fill( 'Test' );
		await page.locator( 'input[type="email"]' ).fill( `count-${ Date.now() }@test.com` );
		await page.locator( '.shqf-button--submit' ).click();
		await expect( page.locator( '.shqf-success' ) ).toBeVisible( { timeout: 10000 } );

		// Get count after via DB query.
		const afterRaw = wpEval( `
			global $wpdb;
			$forms = new SampleHQForm\\Database\\FormsTable($wpdb);
			$form = $forms->get(${ gridFormId });
			echo $form["submissions_count"] ?? "0";
		` );
		const after = parseInt( afterRaw.split( '\n' ).pop()?.trim() || '0', 10 );

		expect( after ).toBeGreaterThan( before );
	} );

	test( 'success redirect works', async ( { page } ) => {
		// Create a form with redirect behavior pointing to the grid page.
		const redirectUrl = GRID_URL;
		const formId = wpEval( `
			global $wpdb;
			$forms = new SampleHQForm\\Database\\FormsTable($wpdb);
			$tpl = SampleHQForm\\Forms\\FormTemplates::get("grid");
			$config = $tpl["config"];
			$config["behavior"]["success_type"] = "redirect";
			$config["behavior"]["redirect_url"] = "${ redirectUrl }";
			echo $forms->create(["title" => "E2E Redirect Form", "status" => "published", "created_by" => 1, "config" => $config]);
		` ).split( '\n' ).pop()?.trim() || '';

		// Create a page with this form.
		const raw = execSync(
			`npx wp-env run cli -- wp post create --post_type=page --post_title="E2E Redirect Page" --post_status=publish --post_content='[samplehq_form id="${ formId }"]' --porcelain`,
			{ encoding: 'utf-8', timeout: 20000 }
		);
		const pageId = raw.split( '\n' ).filter( ( l ) => /^\d+$/.test( l.trim() ) ).pop()?.trim() || '';
		const slugRaw = execSync(
			`npx wp-env run cli -- wp post get ${ pageId } --field=post_name`,
			{ encoding: 'utf-8', timeout: 20000 }
		);
		const slug = slugRaw.split( '\n' ).filter( ( l ) => ! l.includes( 'Starting' ) && ! l.includes( 'Ran' ) && l.trim() ).pop()?.trim() || '';
		const formPageUrl = `http://localhost:8888/${ slug }/`;

		// Visit the redirect form page.
		await page.goto( formPageUrl );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();

		// Fill and submit.
		await page.locator( '.shqf-picker-item' ).first().click();
		await page.locator( 'input[name*="first_name"]' ).fill( 'Redirect' );
		await page.locator( 'input[name*="last_name"]' ).fill( 'User' );
		await page.locator( 'input[type="email"]' ).fill( 'redirect@test.com' );
		await page.locator( '.shqf-button--submit' ).click();

		// Should redirect to the grid page URL.
		await page.waitForURL( redirectUrl, { timeout: 10000 } );
		expect( page.url() ).toContain( 'e2e-grid-page' );
	} );
} );
