import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';
import { loadFixtures } from '../load-fixtures';

let BUILDER_URL: string;

test.beforeAll( () => {
	const f = loadFixtures();
	BUILDER_URL = `/wp-admin/admin.php?page=shqf-forms&action=edit&id=${ f.formIds.blank }`;
} );

function wpEval( php: string ): string {
	const escaped = php.replace( /'/g, "'\\''" );
	return execSync( `npx wp-env run cli -- wp eval '${ escaped }'`, {
		encoding: 'utf-8',
		timeout: 20000,
	} )
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

test.describe( 'Form builder', () => {
	test( 'builder loads with 3 panels', async ( { page } ) => {
		await page.goto( BUILDER_URL );
		await expect( page.locator( '.shqf-builder-palette' ) ).toBeVisible();
		await expect(
			page.locator( '.shqf-builder-canvas-wrap' )
		).toBeVisible();
		// Settings panel exists (may show empty state or form-level settings).
		await expect( page.locator( '.shqf-builder-settings' ) ).toBeVisible();
		// Palette has field items.
		await expect(
			page.locator( '.shqf-builder-palette-item' ).first()
		).toBeVisible();
	} );

	test( 'click palette item adds field to canvas', async ( { page } ) => {
		await page.goto( BUILDER_URL );
		await expect( page.locator( '.shqf-builder-palette' ) ).toBeVisible();
		// Canvas should be empty initially (blank form).
		const before = await page.locator( '.shqf-builder-field' ).count();
		// Click "Text" in palette to add it.
		await page
			.locator( '.shqf-builder-palette-item', { hasText: 'Text' } )
			.first()
			.click();
		await expect( page.locator( '.shqf-builder-field' ) ).toHaveCount(
			before + 1
		);
		await expect(
			page.locator( '.shqf-builder-field-label' ).last()
		).toContainText( 'Text' );
	} );

	test( 'click field shows settings', async ( { page } ) => {
		await page.goto( BUILDER_URL );
		await page.waitForSelector( '.shqf-builder-palette' );
		// Add a text field.
		await page
			.locator( '.shqf-builder-palette-item', { hasText: 'Text' } )
			.first()
			.click();
		await expect(
			page.locator( '.shqf-builder-field' ).last()
		).toBeVisible();
		// Click the field to select it.
		await page.locator( '.shqf-builder-field' ).last().click();
		// Settings panel should show field config.
		await expect(
			page.locator( '.shqf-builder-field--selected' )
		).toHaveCount( 1 );
		await expect(
			page.locator( '.shqf-builder-settings-header' )
		).toContainText( 'Text' );
	} );

	test( 'change field label updates canvas', async ( { page } ) => {
		await page.goto( BUILDER_URL );
		await page.waitForSelector( '.shqf-builder-palette' );
		// Add and select a text field.
		await page
			.locator( '.shqf-builder-palette-item', { hasText: 'Text' } )
			.first()
			.click();
		await page.locator( '.shqf-builder-field' ).last().click();
		await expect(
			page.locator( '.shqf-builder-settings-header' )
		).toContainText( 'Text' );
		// Find the label input (first text input in settings).
		const labelInput = page
			.locator( '.shqf-builder-settings .components-text-control__input' )
			.first();
		await labelInput.fill( 'Full Name' );
		// Canvas should update.
		await expect(
			page.locator( '.shqf-builder-field-label' ).last()
		).toContainText( 'Full Name' );
	} );

	test( 'toggle required shows asterisk', async ( { page } ) => {
		await page.goto( BUILDER_URL );
		await page.waitForSelector( '.shqf-builder-palette' );
		// Add and select.
		await page
			.locator( '.shqf-builder-palette-item', { hasText: 'Text' } )
			.first()
			.click();
		await page.locator( '.shqf-builder-field' ).last().click();
		// No required indicator initially.
		await expect(
			page
				.locator( '.shqf-builder-field' )
				.last()
				.locator( '.shqf-builder-required' )
		).toHaveCount( 0 );
		// Toggle required on.
		await page
			.locator( '.shqf-builder-settings .components-form-toggle__input' )
			.check();
		// Asterisk should appear.
		await expect(
			page
				.locator( '.shqf-builder-field' )
				.last()
				.locator( '.shqf-builder-required' )
		).toBeVisible();
	} );

	test( 'delete field from canvas', async ( { page } ) => {
		await page.goto( BUILDER_URL );
		await page.waitForSelector( '.shqf-builder-palette' );
		// Add a text field.
		await page
			.locator( '.shqf-builder-palette-item', { hasText: 'Text' } )
			.first()
			.click();
		const countBefore = await page.locator( '.shqf-builder-field' ).count();
		// Select the field.
		await page.locator( '.shqf-builder-field' ).last().click();
		// First click shows confirm state, second click deletes (2-step confirm).
		await page
			.locator( '.shqf-builder-field-actions .is-destructive' )
			.last()
			.click();
		await page.waitForTimeout( 200 );
		await page
			.locator( '.shqf-builder-field-actions .is-destructive' )
			.last()
			.click();
		await page.waitForTimeout( 300 );
		// Field should be removed.
		const countAfter = await page.locator( '.shqf-builder-field' ).count();
		expect( countAfter ).toBeLessThan( countBefore );
	} );

	test( 'save form shows success notice', async ( { page } ) => {
		await page.goto( BUILDER_URL );
		await page.waitForSelector( '.shqf-builder-palette' );
		// Add a field to make the form dirty.
		await page
			.locator( '.shqf-builder-palette-item', { hasText: 'Email' } )
			.first()
			.click();
		await page.waitForTimeout( 300 );
		// Click Save.
		await page
			.locator( '.shqf-builder-header-right' )
			.locator( 'button', { hasText: 'Save' } )
			.click();
		// Success notice should appear.
		await expect(
			page.locator( '.shqf-builder-notice--success' )
		).toBeVisible( { timeout: 5000 } );
	} );

	test( 'publish/draft toggle', async ( { page } ) => {
		await page.goto( BUILDER_URL );
		await page.waitForSelector( '.shqf-builder-palette' );
		// Check current status.
		const toggleBtn = page.locator( '.shqf-builder-status-toggle' );
		const toggleText = await toggleBtn.textContent();
		// Click to toggle status.
		await toggleBtn.click();
		await page.waitForTimeout( 300 );
		// Text should change.
		const newText = await toggleBtn.textContent();
		expect( newText?.trim() ).not.toBe( toggleText?.trim() );
	} );

	test( 'form title editable and persists', async ( { page } ) => {
		// Create own form to avoid parallel interference with other builder tests.
		const formId = lastLine(
			wpEval( `
			global $wpdb;
			$forms = new SampleHQForm\\Database\\FormsTable($wpdb);
			echo $forms->create(["title" => "Title Test Form", "status" => "published", "created_by" => 1]);
		` )
		);
		const url = `/wp-admin/admin.php?page=shqf-forms&action=edit&id=${ formId }`;

		await page.goto( url );
		await page.waitForSelector( '.shqf-builder-title' );
		const titleInput = page.locator( '.shqf-builder-title' );
		await titleInput.fill( 'My Custom Form' );
		await page
			.locator( '.shqf-builder-header-right' )
			.locator( 'button', { hasText: 'Save' } )
			.click();
		await expect(
			page.locator( '.shqf-builder-notice--success' )
		).toBeVisible( { timeout: 5000 } );
		// Reload and verify title persisted.
		await page.goto( url );
		await page.waitForSelector( '.shqf-builder-title' );
		await expect( titleInput ).toHaveValue( 'My Custom Form' );
	} );

	test( 'undo/redo works', async ( { page } ) => {
		await page.goto( BUILDER_URL );
		await page.waitForSelector( '.shqf-builder-palette' );
		const countBefore = await page.locator( '.shqf-builder-field' ).count();
		// Add a field.
		await page
			.locator( '.shqf-builder-palette-item', { hasText: 'Phone' } )
			.first()
			.click();
		await expect( page.locator( '.shqf-builder-field' ) ).toHaveCount(
			countBefore + 1
		);
		// Undo (Ctrl+Z).
		await page.keyboard.press( 'Control+z' );
		await page.waitForTimeout( 300 );
		await expect( page.locator( '.shqf-builder-field' ) ).toHaveCount(
			countBefore
		);
		// Redo (Ctrl+Shift+Z).
		await page.keyboard.press( 'Control+Shift+z' );
		await page.waitForTimeout( 300 );
		await expect( page.locator( '.shqf-builder-field' ) ).toHaveCount(
			countBefore + 1
		);
	} );
} );
