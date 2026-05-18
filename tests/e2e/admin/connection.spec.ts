import { test, expect, Page } from '@playwright/test';
import {
	seedConnectedState,
	seedDisconnectedState,
	cleanupConnectionState,
} from '../helpers/connection';

const CONNECTION_URL = '/wp-admin/admin.php?page=shqf-settings&tab=connection';

/**
 * Guard against parallel spec race: if another worker's cleanup deleted
 * the connection between our seed and page load, re-seed and reload.
 *
 * @param page      Playwright page instance.
 * @param overrides Optional fields forwarded to seedConnectedState.
 */
async function ensureConnected(
	page: Page,
	overrides: Record< string, string | number > = {}
): Promise< void > {
	const status = page.locator( '.shqf-connection-status--connected' );
	if ( ! ( await status.isVisible().catch( () => false ) ) ) {
		seedConnectedState( overrides );
		await page.reload();
	}
}

async function ensureDisconnected( page: Page ): Promise< void > {
	const status = page.locator( '.shqf-connection-status--disconnected' );
	if ( ! ( await status.isVisible().catch( () => false ) ) ) {
		seedDisconnectedState();
		await page.reload();
	}
}

test.describe.configure( { mode: 'serial' } );

test.describe( 'Connection tab', () => {
	test.afterEach( () => {
		cleanupConnectionState();
	} );

	test( 'disconnected state shows connect button and hides migration tab', async ( {
		page,
	} ) => {
		seedDisconnectedState();
		await page.goto( CONNECTION_URL );
		await ensureDisconnected( page );

		await expect(
			page.locator( '.shqf-connection-status--disconnected' )
		).toBeVisible();
		await expect( page.locator( 'text=Not connected' ) ).toBeVisible();
		await expect(
			page.locator( 'button.button-primary', {
				hasText: 'Connect to SampleHQ',
			} )
		).toBeVisible();

		// Migration tab should NOT be in the nav.
		await expect(
			page.locator( '.nav-tab', { hasText: 'Migration' } )
		).toBeHidden();
	} );

	test( 'connected state shows workspace details and disconnect button', async ( {
		page,
	} ) => {
		seedConnectedState( {
			workspace_name: 'Acme Corp Workspace',
		} );
		await page.goto( CONNECTION_URL );
		await ensureConnected( page, {
			workspace_name: 'Acme Corp Workspace',
		} );

		await expect(
			page.locator( '.shqf-connection-status--connected' )
		).toBeVisible();
		await expect( page.locator( 'text=Connected' ).first() ).toBeVisible();

		// Connection details table.
		const details = page.locator( '.shqf-connection-details' );
		await expect( details ).toContainText( 'Acme Corp Workspace' );
		await expect( details ).toContainText( 'admin@example.com' );

		// Workspace URL rendered as link.
		await expect(
			details.locator( 'a[href*="samplehq.io"]' )
		).toBeVisible();

		// Connected date rendered.
		await expect( details ).toContainText( 'Connected on' );

		// Disconnect button visible.
		await expect(
			page.locator( 'a.button', { hasText: 'Disconnect' } )
		).toBeVisible();
	} );

	test( 'connected state shows migration tab in nav', async ( { page } ) => {
		seedConnectedState();
		await page.goto( CONNECTION_URL );
		await ensureConnected( page );

		await expect(
			page.locator( '.nav-tab', { hasText: 'Migration' } )
		).toBeVisible();
	} );

	test( 'disconnect cancel returns to connected state', async ( {
		page,
	} ) => {
		seedConnectedState( { workspace_name: 'Keep Me Connected' } );
		await page.goto( CONNECTION_URL );
		await ensureConnected( page, { workspace_name: 'Keep Me Connected' } );

		// Dismiss the confirm dialog.
		page.on( 'dialog', async ( dialog ) => {
			expect( dialog.type() ).toBe( 'confirm' );
			expect( dialog.message() ).toContain( 'Disconnect from SampleHQ' );
			await dialog.dismiss();
		} );

		await page.locator( 'a.button', { hasText: 'Disconnect' } ).click();

		// Still on the connection tab, still connected.
		await expect(
			page.locator( '.shqf-connection-status--connected' )
		).toBeVisible();
		await expect(
			page.locator( '.shqf-connection-details' )
		).toContainText( 'Keep Me Connected' );
	} );

	test( 'disconnect confirm shows disconnected state', async ( { page } ) => {
		seedConnectedState( { workspace_name: 'Remove Me' } );
		await page.goto( CONNECTION_URL );
		await ensureConnected( page, { workspace_name: 'Remove Me' } );

		// Accept the confirm dialog.
		page.on( 'dialog', async ( dialog ) => {
			await dialog.accept();
		} );

		await page.locator( 'a.button', { hasText: 'Disconnect' } ).click();

		// Should redirect back to connection tab in disconnected state.
		await page.waitForURL( /tab=connection/ );
		await expect(
			page.locator( '.shqf-connection-status--disconnected' )
		).toBeVisible();
		await expect( page.locator( 'text=Not connected' ) ).toBeVisible();
		await expect(
			page.locator( 'button.button-primary', {
				hasText: 'Connect to SampleHQ',
			} )
		).toBeVisible();
	} );
} );
