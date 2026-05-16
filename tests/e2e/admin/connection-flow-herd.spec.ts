/**
 * E2E-6: Full connection flow end-to-end (Herd + MailHog).
 *
 * Navigates the real browser flow across both sites:
 *   Plugin (shqf-e2e-test.test) -> Platform (sampleflows.test)
 *   -> Email verification via MailHog -> Callback -> Success
 *
 * Catches all 5 original connection bugs in one test:
 *   - Transient persistence (auto-login token must work)
 *   - HTTPS scheme (workspace URL displayed correctly)
 *   - Cross-origin POST (browser navigates the callback with real cookies)
 *   - SSL verification (HTTP calls between sites must succeed)
 *
 * Prerequisites:
 *   - Herd running with shqf-e2e-test.test and sampleflows.test
 *   - MailHog at 127.0.0.1:8025 (SMTP on 1025)
 *   - Plugin disconnected (test handles this in beforeAll)
 *
 * Run: npx playwright test --config=playwright.herd.config.ts
 */

import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

const PLUGIN_SITE = 'https://shqf-e2e-test.test';
const MAILHOG_API = 'http://127.0.0.1:8025';
const HERD_WP_ROOT = '/Users/bojanjosifoski/Herd/shqf-e2e-test';
const PLATFORM_WP_ROOT = '/Applications/MAMP/htdocs/sampleflows';
const CONNECTION_URL = '/wp-admin/admin.php?page=shqf-settings&tab=connection';

function phpEval( wpRoot: string, php: string ): string {
	const code = `require_once '${wpRoot}/wp-load.php'; ${php}`;
	const escaped = code.replace( /'/g, "'\\''" );
	const raw = execSync(
		`php -r '${escaped}'`,
		{ encoding: 'utf-8', timeout: 15000 }
	);
	return raw.trim();
}

function herdEval( php: string ): string {
	return phpEval( HERD_WP_ROOT, php );
}

function platformEval( php: string ): string {
	return phpEval( PLATFORM_WP_ROOT, php );
}

function clearMailHog(): void {
	execSync( `curl -sX DELETE ${MAILHOG_API}/api/v1/messages`, {
		timeout: 5000,
	} );

	// Verify the inbox is actually empty (guards against silent failures).
	const raw = execSync(
		`curl -s "${MAILHOG_API}/api/v2/messages?limit=0"`,
		{ encoding: 'utf-8', timeout: 5000 }
	);
	const data = JSON.parse( raw );
	if ( data.total > 0 ) {
		throw new Error( `MailHog clear failed: ${data.total} messages remain` );
	}
}

async function getVerificationCode( email: string, retries = 10 ): Promise< string > {
	for ( let i = 0; i < retries; i++ ) {
		const raw = execSync(
			`curl -s "${MAILHOG_API}/api/v2/search?kind=to&query=${encodeURIComponent( email )}"`,
			{ encoding: 'utf-8', timeout: 5000 }
		);
		const data = JSON.parse( raw );

		if ( data.total > 0 ) {
			const body = data.items[ 0 ].Content.Body || '';
			const match = body.match( /verification code is:\s*(\d{6})/ );
			if ( match ) {
				return match[ 1 ];
			}
		}

		await new Promise( ( r ) => setTimeout( r, 500 ) );
	}

	throw new Error( `No verification code found for ${email} after ${retries} retries` );
}

test.describe( 'E2E-6: Full connection flow (Herd)', () => {
	const testEmail = `e2e-flow-${ Date.now() }@example.com`;
	let originalEmail = '';

	test.beforeAll( () => {
		// Set admin email to a unique test address so the platform sees a new
		// user (Case A: creates workspace). Store original to restore later.
		originalEmail = herdEval( '$u = get_user_by("ID", 1); echo $u->user_email;' );
		herdEval(
			`wp_update_user(["ID" => 1, "user_email" => "${testEmail}"]); echo "OK";`
		);

		// Ensure plugin is disconnected.
		herdEval( 'delete_option("shqf_connection"); delete_option("shqf_connect_state");' );

		// Delete any existing platform connection for this site_url (direct wpdb
		// because the repo method is tenant-scoped and would miss cross-tenant rows).
		platformEval(
			'global $wpdb; ' +
			`$wpdb->delete($wpdb->base_prefix . "shq_wp_connections", ["site_url" => "${PLUGIN_SITE}"], ["%s"]); ` +
			'echo "OK";'
		);

		// Clean up any leftover verification codes for this email on the platform.
		platformEval(
			'global $wpdb; ' +
			`$wpdb->delete($wpdb->base_prefix . "shq_wp_verification_codes", ["email" => "${testEmail}"], ["%s"]); ` +
			'echo "OK";'
		);

		// Clear MailHog to avoid picking up old verification emails.
		clearMailHog();
	} );

	test.afterAll( () => {
		// Restore original admin email.
		if ( originalEmail ) {
			herdEval(
				`wp_update_user(["ID" => 1, "user_email" => "${originalEmail}"]); echo "OK";`
			);
		}

		// Clean up: disconnect on plugin side.
		herdEval( 'delete_option("shqf_connection"); delete_option("shqf_connect_state");' );

		// Clean up platform side: delete connection row, workspace, and user.
		try {
			platformEval(
				'global $wpdb; ' +
				`$wpdb->delete($wpdb->base_prefix . "shq_wp_connections", ["site_url" => "${PLUGIN_SITE}"], ["%s"]); ` +
				`$u = get_user_by("email", "${testEmail}"); ` +
				'if ($u) { ' +
				'  $sites = get_blogs_of_user($u->ID); ' +
				'  foreach ($sites as $s) { if ((int) $s->userblog_id > 1) { wpmu_delete_blog((int) $s->userblog_id, true); } } ' +
				'  wpmu_delete_user($u->ID); ' +
				'} ' +
				'echo "CLEANED";'
			);
		} catch {
			// Best-effort cleanup.
		}
	} );

	test( 'full connect: plugin -> platform -> email verify -> callback -> success', async ( {
		page,
	} ) => {
		// Step 1: Navigate to plugin settings, verify disconnected state.
		await page.goto( CONNECTION_URL );
		await expect(
			page.locator( '.shqf-connection-status--disconnected' )
		).toBeVisible();

		// Step 2: Click "Connect to SampleHQ" -- redirects to platform.
		const connectButton = page.locator( 'a.button-primary', {
			hasText: 'Connect to SampleHQ',
		} );
		await expect( connectButton ).toBeVisible();

		// The connect link goes to the platform. Follow the redirect.
		await connectButton.click();

		// Step 3: Assert we landed on the platform verification page.
		await page.waitForURL( ( url ) =>
			url.href.includes( '/connect/wordpress' )
		);
		await expect( page.locator( 'h1' ) ).toContainText( 'Verify your email' );
		await expect( page.locator( 'input#code' ) ).toBeVisible();

		// Step 4: Fetch the 6-digit code from MailHog.
		const code = await getVerificationCode( testEmail );
		expect( code ).toMatch( /^\d{6}$/ );

		// Step 5: Enter the code and submit.
		await page.fill( 'input#code', code );
		await page.click( 'button[type="submit"]' );

		// Step 6: The platform renders a callback form that auto-submits
		// via JavaScript, POSTing connection_token + signature back to the
		// plugin's /shqf-connect-callback. Wait for navigation back to
		// the plugin admin.
		await page.waitForURL(
			( url ) => url.href.includes( 'shqf-settings' ),
			{ timeout: 15000 }
		);

		// Step 7: Assert success notice.
		const currentUrl = page.url();
		expect( currentUrl ).toContain( 'shqf_connected=1' );

		await expect(
			page.locator( '.notice-success' )
		).toContainText( 'Successfully connected' );

		// Step 8: Assert connection details are displayed.
		await expect(
			page.locator( '.shqf-connection-status--connected' )
		).toBeVisible();

		const details = page.locator( '.shqf-connection-details' );
		await expect( details ).toBeVisible();

		// Workspace name should be present (created by TenantService).
		const detailsText = await details.textContent();
		expect( detailsText ).toBeTruthy();

		// Workspace URL should use https://.
		const urlElement = details.locator( 'a[href*="sampleflows.test"]' );
		if ( await urlElement.count() > 0 ) {
			const href = await urlElement.getAttribute( 'href' );
			expect( href ).toMatch( /^https:\/\// );
		}

		// Connected by should show the test email.
		await expect( details ).toContainText( testEmail );

		// Step 9: Verify the connection is stored in the database.
		const dbCheck = herdEval(
			'$c = get_option("shqf_connection"); echo ($c && !empty($c["workspace_id"])) ? "CONNECTED" : "NOT_CONNECTED";'
		);
		expect( dbCheck ).toBe( 'CONNECTED' );

		// Step 10: Verify workspace URL in the stored connection uses https.
		const wsUrl = herdEval(
			'$c = get_option("shqf_connection"); echo $c["workspace_url"] ?? "";'
		);
		expect( wsUrl ).toMatch( /^https:\/\// );
		expect( wsUrl ).toContain( 'sampleflows.test' );
	} );
} );
