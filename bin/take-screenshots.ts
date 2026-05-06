import { chromium } from 'playwright';

const BASE = 'https://samplehq-wp-plugin.test';
const USER = 'bojan';
const PASS = 'test';
const OUT = '.wordpress-org';

async function main() {
	const browser = await chromium.launch({ ignoreHTTPSErrors: true });
	const context = await browser.newContext({
		viewport: { width: 1280, height: 800 },
		ignoreHTTPSErrors: true,
	});
	const page = await context.newPage();

	// Login.
	await page.goto(`${BASE}/wp-login.php`);
	await page.fill('#user_login', USER);
	await page.fill('#user_pass', PASS);
	await page.click('#wp-submit');
	await page.waitForLoadState('networkidle');
	console.log('Logged in.');

	// 1. Sample Library.
	await page.goto(`${BASE}/wp-admin/admin.php?page=shqf-samples`);
	await page.waitForLoadState('networkidle');
	await page.screenshot({ path: `${OUT}/screenshot-1.png`, fullPage: false });
	console.log('1. Sample Library');

	// 2. Form Builder — open first form.
	await page.goto(`${BASE}/wp-admin/admin.php?page=shqf-forms`);
	await page.waitForLoadState('networkidle');
	const firstForm = page.locator('table.wp-list-table tbody tr .row-title').first();
	if (await firstForm.isVisible()) {
		await firstForm.click();
		await page.waitForLoadState('networkidle');
		await page.waitForTimeout(1500);
	}
	await page.screenshot({ path: `${OUT}/screenshot-2.png`, fullPage: false });
	console.log('2. Form Builder');

	// 3. Submissions Dashboard.
	await page.goto(`${BASE}/wp-admin/admin.php?page=shqf-submissions`);
	await page.waitForLoadState('networkidle');
	await page.screenshot({ path: `${OUT}/screenshot-3.png`, fullPage: false });
	console.log('3. Submissions Dashboard');

	// 4. Frontend — "Request a Sample" page, scrolled to show product cards.
	await page.goto(`${BASE}/?p=20`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(800);
	const picker = page.locator('.shqf-picker-items, .shqf-picker-grid').first();
	if (await picker.isVisible()) {
		await picker.scrollIntoViewIfNeeded();
		await page.waitForTimeout(300);
	}
	await page.screenshot({ path: `${OUT}/screenshot-4.png`, fullPage: false });
	console.log('4. Frontend Form (Request a Sample — sample picker)');

	// 5. Settings.
	await page.goto(`${BASE}/wp-admin/admin.php?page=shqf-settings`);
	await page.waitForLoadState('networkidle');
	await page.screenshot({ path: `${OUT}/screenshot-5.png`, fullPage: false });
	console.log('5. Settings');

	// 6. Sample Edit page — navigate via direct URL for first sample.
	await page.goto(`${BASE}/wp-admin/admin.php?page=shqf-sample-edit&id=1`);
	await page.waitForLoadState('networkidle');
	await page.screenshot({ path: `${OUT}/screenshot-6.png`, fullPage: false });
	console.log('6. Sample Edit');

	// 7. Frontend — Quick Order page with product grid visible.
	await page.goto(`${BASE}/?p=21`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(800);
	await page.screenshot({ path: `${OUT}/screenshot-7.png`, fullPage: false });
	console.log('7. Frontend Form (Quick Order)');

	await browser.close();
	console.log(`\nDone! Screenshots saved to ${OUT}/`);
}

main().catch((e) => {
	console.error(e);
	process.exit(1);
});
