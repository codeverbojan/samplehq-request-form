import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

const SAMPLES_URL = '/wp-admin/admin.php?page=shqf-samples';
const NEW_SAMPLE_URL = '/wp-admin/admin.php?page=shqf-samples&action=new';

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

function lastLine( output: string ): string {
	return output.split( '\n' ).pop()?.trim() || '';
}

const ts = Date.now();

test.describe( 'Sample CRUD', () => {
	test( 'add sample with all fields', async ( { page } ) => {
		await page.goto( NEW_SAMPLE_URL );
		await expect( page.locator( '#shqf-name' ) ).toBeVisible();
		await page.locator( '#shqf-name' ).fill( `Add Test ${ ts }` );
		await page.locator( '#shqf-sku' ).fill( `ADD-${ ts }` );
		await page.locator( '#shqf-max-qty' ).fill( '10' );
		await page.locator( '#shqf-status' ).selectOption( 'active' );
		await page.locator( '#submit' ).click();
		// After save, the page reloads with the persisted name.
		await page.waitForSelector( '#shqf-name' );
		await expect( page.locator( '#shqf-name' ) ).toHaveValue( `Add Test ${ ts }` );
	} );

	test( 'sample appears in list via search', async ( { page } ) => {
		const name = `ListCheck ${ ts }`;
		wpEval( `
			global $wpdb;
			$s = new SampleHQForm\\Database\\SamplesTable($wpdb);
			$s->create(["name" => "${ name }", "sku" => "LC-${ ts }"]);
		` );
		await page.goto( SAMPLES_URL );
		await page.locator( 'input[name="s"]' ).fill( name );
		await page.locator( '#search-submit' ).click();
		await expect( page.locator( `.wp-list-table tr:has-text("${ name }")` ) ).toBeVisible();
	} );

	test( 'edit sample changes persist', async ( { page } ) => {
		const id = lastLine(
			wpEval( `
			global $wpdb;
			$s = new SampleHQForm\\Database\\SamplesTable($wpdb);
			echo $s->create(["name" => "EditBefore ${ ts }", "sku" => "EB-${ ts }"]);
		` )
		);
		await page.goto( `${ SAMPLES_URL }&action=edit&id=${ id }` );
		await expect( page.locator( '#shqf-name' ) ).toHaveValue( `EditBefore ${ ts }` );
		await page.locator( '#shqf-name' ).fill( `EditAfter ${ ts }` );
		await page.locator( '#submit' ).click();
		// After save, the page reloads with the updated value.
		await page.waitForSelector( '#shqf-name' );
		await expect( page.locator( '#shqf-name' ) ).toHaveValue( `EditAfter ${ ts }` );
	} );

	test( 'sample lifecycle: archive, trash, restore, delete', async ( { page } ) => {
		const name = `Lifecycle ${ ts }`;
		const id = lastLine(
			wpEval( `
			global $wpdb;
			$s = new SampleHQForm\\Database\\SamplesTable($wpdb);
			echo $s->create(["name" => "${ name }", "sku" => "LF-${ ts }"]);
		` )
		);

		// 1. Archive via DB.
		wpEval( `
			global $wpdb;
			$s = new SampleHQForm\\Database\\SamplesTable($wpdb);
			$s->archive(${ id });
		` );
		await page.goto( SAMPLES_URL + '&status=archived' );
		await expect( page.locator( `tr:has-text("${ name }")` ) ).toBeVisible();

		// 2. Trash via row action.
		const trashHref = await page.locator( `tr:has-text("${ name }") .row-actions a:has-text("Trash")` )
			.getAttribute( 'href' );
		await page.goto( trashHref! );

		// 3. Verify in trashed view.
		await page.goto( SAMPLES_URL + '&status=trashed' );
		await expect( page.locator( `tr:has-text("${ name }")` ) ).toBeVisible();

		// 4. Restore via row action.
		const restoreHref = await page.locator( `tr:has-text("${ name }") .row-actions a:has-text("Restore")` )
			.getAttribute( 'href' );
		await page.goto( restoreHref! );

		// 5. Verify back in active via search.
		await page.goto( SAMPLES_URL );
		await page.locator( 'input[name="s"]' ).fill( name );
		await page.locator( '#search-submit' ).click();
		await expect( page.locator( `tr:has-text("${ name }")` ) ).toBeVisible();

		// 6. Trash again, then delete permanently.
		const trashHref2 = await page.locator( `tr:has-text("${ name }") .row-actions a:has-text("Trash")` )
			.getAttribute( 'href' );
		await page.goto( trashHref2! );
		await page.goto( SAMPLES_URL + '&status=trashed' );
		const deleteHref = await page.locator( `tr:has-text("${ name }") .row-actions a:has-text("Delete Permanently")` )
			.getAttribute( 'href' );
		await page.goto( deleteHref! );

		// 7. Verify gone.
		await page.goto( SAMPLES_URL + '&status=trashed' );
		await expect( page.locator( `tr:has-text("${ name }")` ) ).toHaveCount( 0 );
	} );

	test( 'category assignment on create', async ( { page } ) => {
		await page.goto( NEW_SAMPLE_URL );
		await page.locator( '#shqf-name' ).fill( `CatSample ${ ts }` );
		await page.locator( '#shqf-sku' ).fill( `CAT-${ ts }` );
		await page.locator( 'input[name="categories[]"]' ).first().check();
		await page.locator( '#submit' ).click();
		await page.waitForSelector( '#shqf-name' );
		await page.reload();
		await expect( page.locator( 'input[name="categories[]"]' ).first() ).toBeChecked();
	} );

	test( 'custom fields round-trip', async ( { page } ) => {
		await page.goto( NEW_SAMPLE_URL );
		await page.locator( '#shqf-name' ).fill( `CfSample ${ ts }` );
		await page.locator( '#shqf-sku' ).fill( `CF-${ ts }` );
		await page.locator( '#shqf-add-custom-field' ).click();
		await page.waitForTimeout( 200 );
		await page.locator( 'input[name="cf_keys[]"]' ).last().fill( 'Weight' );
		await page.locator( 'input[name="cf_values[]"]' ).last().fill( '250g' );
		await page.locator( '#submit' ).click();
		// After save, reload and verify.
		await page.waitForSelector( '#shqf-name' );
		await page.reload();
		await expect( page.locator( 'input[name="cf_keys[]"]' ).first() ).toHaveValue( 'Weight' );
		await expect( page.locator( 'input[name="cf_values[]"]' ).first() ).toHaveValue( '250g' );
	} );

	test( 'search finds sample by name', async ( { page } ) => {
		await page.goto( SAMPLES_URL );
		await page.locator( 'input[name="s"]' ).fill( 'Blue Fabric' );
		await page.locator( '#search-submit' ).click();
		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();
		await expect( page.locator( 'tr:has-text("Blue Fabric")' ) ).toBeVisible();
	} );

	test( 'category filter works', async ( { page } ) => {
		await page.goto( SAMPLES_URL );
		await expect( page.locator( '#filter-by-category' ) ).toBeVisible();
		const catValue = await page.locator( '#filter-by-category option' ).nth( 1 ).getAttribute( 'value' );
		await page.locator( '#filter-by-category' ).selectOption( catValue! );
		await page.locator( '#filter_action' ).click();
		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();
		const count = await page.locator( '.wp-list-table tbody tr' ).count();
		expect( count ).toBeGreaterThanOrEqual( 1 );
	} );
} );
