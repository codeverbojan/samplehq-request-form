import { test, expect } from '@playwright/test';
import { createHmac } from 'crypto';
import {
	wpEval,
	cleanupConnectionState,
	seedConnectedState,
} from '../helpers/connection';

const CONNECTION_URL = '/wp-admin/admin.php?page=shqf-settings&tab=connection';

/**
 * Seed a pending state token and return the token string for HMAC computation.
 */
function seedPendingState(): string {
	const token = 'e2e_state_token_' + Date.now().toString( 36 );
	const stateData = JSON.stringify( {
		token,
		site_url: 'http://localhost:8888',
		return_url: 'http://localhost:8888/shqf-connect-callback',
		user_id: 1,
		created_at: Math.floor( Date.now() / 1000 ),
	} );
	const b64 = Buffer.from( stateData ).toString( 'base64' );

	wpEval(
		`update_option( "shqf_connect_state", json_decode( base64_decode( "${ b64 }" ), true ), false );`
	);

	return token;
}

/**
 * Build a valid connection_token (base64 JSON) and compute its HMAC signature.
 * @param stateToken
 * @param overrides
 */
function buildSignedCallback(
	stateToken: string,
	overrides: Record< string, string | number > = {}
): { state: string; connection_token: string; signature: string } {
	const tokenData = {
		workspace_url: 'https://e2e-test.samplehq.io/workspace/flow-test',
		workspace_id: 12345,
		workspace_name: 'E2E Flow Test Workspace',
		connection_secret: 'e2e-test-secret-' + Date.now(),
		connected_by: 'admin@example.com',
		...overrides,
	};

	const tokenJson = JSON.stringify( tokenData );
	const connectionToken = Buffer.from( tokenJson ).toString( 'base64' );

	// Compute HMAC-SHA256 with the state token as the key (matches PHP logic).
	const signature = createHmac( 'sha256', stateToken )
		.update( connectionToken )
		.digest( 'hex' );

	return {
		state: stateToken,
		connection_token: connectionToken,
		signature,
	};
}

test.describe.configure( { mode: 'serial' } );

test.describe( 'Connection flow (callback endpoint)', () => {
	test.afterEach( () => {
		cleanupConnectionState();
	} );

	test( 'E2E-1: valid HMAC POST stores connection and shows success', async ( {
		page,
		request,
	} ) => {
		const stateToken = seedPendingState();
		const payload = buildSignedCallback( stateToken, {
			workspace_name: 'HMAC Test Workspace',
		} );

		// POST to the callback endpoint. wp_safe_redirect + exit means
		// the response is a 302. Playwright's request API follows redirects
		// by default, landing on the admin page (which requires auth).
		const response = await request.post(
			'http://localhost:8888/shqf-connect-callback',
			{
				form: payload,
				maxRedirects: 0,
			}
		);

		// Should be a 302 redirect to the success URL.
		expect( response.status() ).toBe( 302 );
		const location = response.headers().location ?? '';
		expect( location ).toContain( 'page=shqf-settings' );
		expect( location ).toContain( 'tab=connection' );
		expect( location ).toContain( 'shqf_connected=1' );

		// Navigate as authenticated admin to verify the UI.
		await page.goto( CONNECTION_URL + '&shqf_connected=1' );

		// Success notice visible.
		await expect( page.locator( '.notice-success' ) ).toContainText(
			'Successfully connected'
		);

		// Connection details present.
		await expect(
			page.locator( '.shqf-connection-status--connected' )
		).toBeVisible();
		await expect(
			page.locator( '.shqf-connection-details' )
		).toContainText( 'HMAC Test Workspace' );
	} );

	test( 'E2E-2: invalid signature shows error notice', async ( {
		page,
		request,
	} ) => {
		const stateToken = seedPendingState();
		const payload = buildSignedCallback( stateToken );

		// Forge the signature.
		payload.signature = 'deadbeef'.repeat( 8 );

		const response = await request.post(
			'http://localhost:8888/shqf-connect-callback',
			{
				form: payload,
				maxRedirects: 0,
			}
		);

		expect( response.status() ).toBe( 302 );
		const location = response.headers().location ?? '';
		expect( location ).toContain( 'shqf_error' );

		// Navigate to the error URL to verify error notice renders.
		await page.goto(
			CONNECTION_URL + '&shqf_error=Invalid+connection+signature'
		);
		await expect( page.locator( '.notice-error' ) ).toContainText(
			'Invalid connection signature'
		);
	} );

	test( 'E2E-3: replayed state is rejected', async ( { request } ) => {
		const stateToken = seedPendingState();
		const payload = buildSignedCallback( stateToken );

		// First POST -- should succeed (consumes the state).
		const first = await request.post(
			'http://localhost:8888/shqf-connect-callback',
			{
				form: payload,
				maxRedirects: 0,
			}
		);
		expect( first.status() ).toBe( 302 );
		expect( first.headers().location ?? '' ).toContain(
			'shqf_connected=1'
		);

		// Verify the state was consumed (deleted from DB).
		const stateAfterFirst = wpEval(
			`echo get_option( "shqf_connect_state" ) ? "EXISTS" : "GONE";`
		);
		expect( stateAfterFirst ).toBe( 'GONE' );

		// Second POST -- same data, state already consumed.
		const second = await request.post(
			'http://localhost:8888/shqf-connect-callback',
			{
				form: payload,
				maxRedirects: 0,
			}
		);
		expect( second.status() ).toBe( 302 );
		const location = second.headers().location ?? '';
		expect( location ).toContain( 'shqf_error' );
		expect( decodeURIComponent( location ) ).toContain(
			'No pending connection'
		);
	} );

	test( 'E2E-4: disconnect deletes connection option from database', async ( {
		page,
	} ) => {
		seedConnectedState( { workspace_name: 'Disconnect DB Test' } );
		await page.goto( CONNECTION_URL );

		// Guard against parallel worker race: re-seed if another worker cleaned state.
		const status = page.locator( '.shqf-connection-status--connected' );
		if ( ! ( await status.isVisible().catch( () => false ) ) ) {
			seedConnectedState( { workspace_name: 'Disconnect DB Test' } );
			await page.reload();
		}

		await expect(
			page.locator( '.shqf-connection-status--connected' )
		).toBeVisible();

		// Accept the confirm dialog.
		page.on( 'dialog', async ( dialog ) => {
			await dialog.accept();
		} );

		await page.locator( 'a.button', { hasText: 'Disconnect' } ).click();

		// Wait for the full disconnect round-trip (navigate → server deletes → redirect → render).
		await expect(
			page.locator( '.shqf-connection-status--disconnected' )
		).toBeVisible();
		await expect(
			page.locator( 'button.button-primary', {
				hasText: 'Connect to SampleHQ',
			} )
		).toBeVisible();

		// Verify the option was actually deleted from the database.
		const optionValue = wpEval(
			`echo get_option( "shqf_connection" ) ? "EXISTS" : "GONE";`
		);
		expect( optionValue ).toBe( 'GONE' );
	} );

	test( 'E2E-5: error notice displays and XSS is escaped', async ( {
		page,
	} ) => {
		// Normal error message.
		await page.goto( CONNECTION_URL + '&shqf_error=Test+error+message' );
		await expect( page.locator( '.notice-error' ) ).toContainText(
			'Test error message'
		);

		// XSS attempt: register dialog handler BEFORE navigation so we catch
		// any alert that fires during page load.
		let alertTriggered = false;
		page.on( 'dialog', async ( dialog ) => {
			alertTriggered = true;
			await dialog.dismiss();
		} );

		await page.goto(
			CONNECTION_URL +
				'&shqf_error=' +
				encodeURIComponent( '<script>alert(1)</script>' )
		);

		// sanitize_text_field() strips HTML tags entirely, so <script> tags
		// are removed -- the rendered text will NOT contain them. The key
		// assertion is that no JS executed (no alert dialog).
		expect( alertTriggered ).toBe( false );

		// Verify no <script> element was injected into the DOM.
		const scriptCount = await page
			.locator( '.notice-error script' )
			.count();
		expect( scriptCount ).toBe( 0 );
	} );
} );
