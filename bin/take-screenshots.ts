import { chromium, type Page } from 'playwright';

const BASE = 'https://samplehq-wp-plugin.test';
const USER = 'bojan';
const PASS = 'test';
const OUT = '.wordpress-org';

async function hideWpNoise(page: Page) {
	await page.evaluate(() => {
		const style = document.createElement('style');
		style.textContent = `
			#wpadminbar { display: none !important; }
			html.wp-toolbar { padding-top: 0 !important; }
			#adminmenuwrap { margin-top: 0 !important; }
			#wpfooter { display: none !important; }
			.update-nag, .notice:not(.shqf-notice), .updated { display: none !important; }
			#screen-meta, #screen-meta-links { display: none !important; }
		`;
		document.head.appendChild(style);
	});
}

async function settle(page: Page, ms = 1500) {
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(ms);
}

async function main() {
	const browser = await chromium.launch({ ignoreHTTPSErrors: true });
	const context = await browser.newContext({
		viewport: { width: 1440, height: 900 },
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

	// --- 1. Visual Form Builder (HERO screenshot) ---
	// Form 20 "Sample Request Wizard" — shows field palette, multi-field canvas, settings panel.
	await page.goto(`${BASE}/wp-admin/admin.php?page=shqf-forms&action=edit&id=20`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(2000);
	await hideWpNoise(page);
	await page.screenshot({ path: `${OUT}/screenshot-1.png`, fullPage: false });
	console.log('1. Visual Form Builder (hero)');

	// --- 2. Sample Library ---
	await page.goto(`${BASE}/wp-admin/admin.php?page=shqf-samples`);
	await page.waitForLoadState('networkidle');
	await hideWpNoise(page);
	await settle(page);
	await page.screenshot({ path: `${OUT}/screenshot-2.png`, fullPage: false });
	console.log('2. Sample Library');

	// --- 3. Submissions Dashboard (form 20 — real names) ---
	await page.goto(`${BASE}/wp-admin/admin.php?page=shqf-submissions&form_id=20`);
	await page.waitForLoadState('networkidle');
	await hideWpNoise(page);
	await page.screenshot({ path: `${OUT}/screenshot-3.png`, fullPage: false });
	console.log('3. Submissions Dashboard');

	// --- 4. Frontend Form (wizard with sample picker and product cards) ---
	// Open in a new page without admin bar for clean frontend view.
	const frontCtx = await browser.newContext({
		viewport: { width: 1440, height: 900 },
		ignoreHTTPSErrors: true,
	});
	const frontPage = await frontCtx.newPage();
	await frontPage.goto(`${BASE}/?p=20`);
	await frontPage.waitForLoadState('networkidle');
	await frontPage.waitForTimeout(1500);
	await settle(frontPage);
	// Hide admin bar if logged in.
	await frontPage.evaluate(() => {
		const bar = document.getElementById('wpadminbar');
		if (bar) bar.style.display = 'none';
		const html = document.documentElement;
		html.style.marginTop = '0';
		html.style.paddingTop = '0';
	});
	// Scroll so the product card grid is nicely visible.
	const picker = frontPage.locator('.shqf-picker-grid, .shqf-picker-items, .shqf-fields').first();
	if (await picker.isVisible()) {
		await picker.scrollIntoViewIfNeeded();
		await frontPage.waitForTimeout(500);
		// Scroll up a bit to show the stepper + cards together.
		await frontPage.evaluate(() => window.scrollBy(0, -120));
	}
	await frontPage.screenshot({ path: `${OUT}/screenshot-4.png`, fullPage: false });
	console.log('4. Frontend Form (wizard + sample picker)');

	// --- 5. Settings (Spam Protection tab — more content than General) ---
	await page.goto(`${BASE}/wp-admin/admin.php?page=shqf-settings&tab=spam`);
	await page.waitForLoadState('networkidle');
	await hideWpNoise(page);
	await page.screenshot({ path: `${OUT}/screenshot-5.png`, fullPage: false });
	console.log('5. Settings (Spam Protection)');

	// --- 6. Sample Editor (sample 5 — Blue Linen Fabric, has image + data) ---
	await page.goto(`${BASE}/wp-admin/admin.php?page=shqf-samples&action=edit&id=5`);
	await page.waitForLoadState('networkidle');
	await page.waitForTimeout(1000);
	await hideWpNoise(page);
	await settle(page);
	await page.screenshot({ path: `${OUT}/screenshot-6.png`, fullPage: false });
	console.log('6. Sample Editor');

	// --- 7. Frontend Product Grid (page 21 "Quick Order" — different layout) ---
	await frontPage.goto(`${BASE}/?p=21`);
	await frontPage.waitForLoadState('networkidle');
	await frontPage.waitForTimeout(1500);
	await settle(frontPage);
	await frontPage.evaluate(() => {
		const bar = document.getElementById('wpadminbar');
		if (bar) bar.style.display = 'none';
		document.documentElement.style.marginTop = '0';
		document.documentElement.style.paddingTop = '0';
	});
	const grid = frontPage.locator('.shqf-picker-grid, .shqf-picker-items').first();
	if (await grid.isVisible()) {
		await grid.scrollIntoViewIfNeeded();
		await frontPage.waitForTimeout(500);
		await frontPage.evaluate(() => window.scrollBy(0, -80));
	}
	await frontPage.screenshot({ path: `${OUT}/screenshot-7.png`, fullPage: false });
	console.log('7. Frontend Product Grid');

	await frontCtx.close();
	await browser.close();
	console.log(`\nDone! Screenshots saved to ${OUT}/`);
}

main().catch((e) => {
	console.error(e);
	process.exit(1);
});
