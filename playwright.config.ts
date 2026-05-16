import { defineConfig, devices } from '@playwright/test';

export default defineConfig( {
	testDir: './tests/e2e',
	fullyParallel: true,
	forbidOnly: !! process.env.CI,
	retries: 0,
	workers: process.env.CI ? 2 : undefined,
	reporter: process.env.CI ? 'github' : 'list',
	timeout: 30_000,

	use: {
		baseURL: 'http://localhost:8888',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},

	projects: [
		{
			name: 'setup',
			testMatch: /global-setup\.ts/,
		},
		{
			name: 'chromium',
			testIgnore: /responsive\/|woocommerce\/|connection-flow-herd/,
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState: 'tests/e2e/.auth/admin.json',
			},
			dependencies: [ 'setup' ],
		},
		{
			name: 'mobile',
			testMatch: /responsive\/|frontend\//,
			use: {
				...devices[ 'Pixel 7' ],
				storageState: 'tests/e2e/.auth/admin.json',
			},
			dependencies: [ 'setup' ],
		},
	],
} );
