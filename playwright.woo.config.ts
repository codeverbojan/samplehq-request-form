/**
 * Playwright config for WooCommerce integration E2E tests.
 *
 * Runs against the local dev site (samplehq-wp-plugin.test).
 * Separate from the main playwright.config.ts which targets wp-env.
 *
 * Usage:
 *   npx playwright test --config playwright.woo.config.ts
 *   npx playwright test --config playwright.woo.config.ts --project woo-desktop
 *   npx playwright test --config playwright.woo.config.ts product-button
 */

import { defineConfig, devices } from '@playwright/test';

const AUTH_FILE = 'tests/e2e/woocommerce/.auth/admin.json';

export default defineConfig( {
	testDir: './tests/e2e/woocommerce',
	fullyParallel: false,
	retries: 0,
	workers: 1,
	reporter: 'list',
	timeout: 30_000,

	use: {
		baseURL: 'https://samplehq-wp-plugin.test',
		ignoreHTTPSErrors: true,
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},

	projects: [
		{
			name: 'woo-setup',
			testMatch: /woo-setup\.ts/,
			use: { storageState: undefined },
		},
		{
			name: 'woo-desktop',
			testIgnore: /mobile\.spec\.ts/,
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: AUTH_FILE,
			},
			dependencies: [ 'woo-setup' ],
		},
		{
			name: 'woo-mobile',
			testMatch: /mobile\.spec\.ts/,
			use: {
				...devices[ 'Pixel 7' ],
				storageState: AUTH_FILE,
			},
			dependencies: [ 'woo-setup' ],
		},
	],
} );
