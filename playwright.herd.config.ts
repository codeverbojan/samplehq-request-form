/**
 * Playwright config for Herd-based E2E tests.
 *
 * Targets the local Herd WordPress site (shqf-e2e-test.test) and the
 * platform at sampleflows.test. Requires both sites running + MailHog.
 *
 * Run: npx playwright test --config=playwright.herd.config.ts
 */

import { defineConfig, devices } from '@playwright/test';

export default defineConfig( {
	testDir: './tests/e2e',
	testMatch: /connection-flow-herd\.spec\.ts/,
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: 0,
	workers: 1,
	reporter: 'list',
	timeout: 60_000,

	use: {
		baseURL: 'https://shqf-e2e-test.test',
		ignoreHTTPSErrors: true,
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},

	projects: [
		{
			name: 'herd-setup',
			testMatch: /herd-setup\.ts/,
		},
		{
			name: 'herd-chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: 'tests/e2e/.auth/herd-admin.json',
			},
			dependencies: [ 'herd-setup' ],
		},
	],
} );
