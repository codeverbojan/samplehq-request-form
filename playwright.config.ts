import { defineConfig, devices } from '@playwright/test';

export default defineConfig( {
	testDir: './tests/e2e',
	fullyParallel: true,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: process.env.CI ? 1 : undefined,
	reporter: process.env.CI ? 'github' : 'list',
	timeout: 30_000,

	use: {
		baseURL: 'http://localhost:8888',
		storageState: 'tests/e2e/.auth/admin.json',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},

	projects: [
		{
			name: 'setup',
			testMatch: /global-setup\.ts/,
			use: { storageState: undefined },
		},
		{
			name: 'chromium',
			testIgnore: /responsive\//,
			use: { ...devices[ 'Desktop Chrome' ] },
			dependencies: [ 'setup' ],
		},
		{
			name: 'mobile',
			testMatch: /responsive\/|frontend\//,
			use: {
				...devices[ 'Pixel 7' ],
			},
			dependencies: [ 'setup' ],
		},
	],
} );
