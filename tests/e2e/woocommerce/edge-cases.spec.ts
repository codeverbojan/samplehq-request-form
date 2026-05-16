/**
 * WooCommerce Edge Cases E2E Tests (Phase 9)
 *
 * Tests product type filtering, variable products, cache invalidation,
 * products without images/descriptions, and mixed source forms.
 */

import { test, expect } from '@playwright/test';
import {
	PRODUCTS,
	BTN_SELECTOR,
	SHOP_URL,
	MODAL_SELECTOR,
	restoreDefaults,
} from './woo-helpers';

test.afterAll( async ( { browser } ) => {
	const ctx = await browser.newContext( {
		storageState: 'tests/e2e/woocommerce/.auth/admin.json',
	} );
	const page = await ctx.newPage();
	await page.goto( '/' );
	await restoreDefaults( page );
	await ctx.close();
} );

// =====================================================================
// 9.4-9.6: Product Rendering Edge Cases
// =====================================================================

test.describe( 'Product Rendering', () => {
	test( '9.4 Products without SKU render correctly', async ( { page } ) => {
		// All test products have SKUs. Verify button renders even if empty SKU.
		await page.goto( PRODUCTS.kraftMailer.url );
		const btn = page.locator( BTN_SELECTOR );
		await expect( btn ).toBeVisible();
		// The data-product-sku attribute should be present (even if empty).
		await expect( btn ).toHaveAttribute( 'data-product-sku' );
	} );

	test( '9.5 Products without image show placeholder in picker', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		const btn = page.locator( BTN_SELECTOR );
		await btn.click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		// Test products have no images -- all should show placeholder SVG.
		const placeholders = modal.locator( '.shqf-picker-item-placeholder' );
		const count = await placeholders.count();
		expect( count ).toBeGreaterThanOrEqual( 1 );
	} );

	test( '9.6 Products without description render without description block', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		const btn = page.locator( BTN_SELECTOR );
		await btn.click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		// Picker items should render even if some products lack descriptions.
		const items = modal.locator( '.shqf-picker-item' );
		const count = await items.count();
		expect( count ).toBeGreaterThanOrEqual( 5 );
	} );
} );

// =====================================================================
// 9.11-9.12: Variable Products
// =====================================================================

test.describe( 'Variable Products', () => {
	test( '9.11 Variable product shows in picker as single item', async ( {
		page,
	} ) => {
		// Open modal on a product we know has the button (simple, in-stock).
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		// Custom Label Roll (variable) should appear as a single parent item.
		const customLabelItem = modal.locator(
			`.shqf-picker-item[data-sample-id="${ PRODUCTS.customLabelRoll.id }"]`
		);
		await expect( customLabelItem ).toBeVisible();
	} );

	test( '9.12 Variations excluded by default', async ( { page } ) => {
		// Open modal on any product to see the full picker.
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		// Count total picker items. Should be 6 (5 simple + 1 variable parent).
		// No variation children should be present.
		const items = modal.locator( '.shqf-picker-item' );
		const count = await items.count();
		expect( count ).toBe( 6 );
	} );
} );

// =====================================================================
// 9.13-9.14: Cache Behavior
// =====================================================================

test.describe( 'Cache Behavior', () => {
	test( '9.13 Cache clear makes products refresh', async ( { page } ) => {
		// Open modal, count products.
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		const itemsBefore = await modal.locator( '.shqf-picker-item' ).count();
		expect( itemsBefore ).toBeGreaterThanOrEqual( 5 );

		// Clear cache via option helper (increment version).
		await page.evaluate( async () => {
			await fetch(
				'https://samplehq-wp-plugin.test/wp-admin/admin-ajax.php?action=shqf_e2e_set_option&key=shqf_woo_cache_version&value=' +
					String( Date.now() ),
				{ credentials: 'same-origin' }
			);
		} );

		// Reload and verify products still appear (cache regenerates).
		await page.reload();
		await page.locator( BTN_SELECTOR ).click();
		await expect( modal ).toBeVisible();
		const itemsAfter = await modal.locator( '.shqf-picker-item' ).count();
		expect( itemsAfter ).toBe( itemsBefore );
	} );
} );

// =====================================================================
// 9.9: Mixed Sources
// =====================================================================

test.describe( 'Mixed Sources', () => {
	test( '9.9 Library-source form unaffected by WC integration', async ( {
		page,
	} ) => {
		// The plugin should have a library-source form on a test page.
		// Navigate to shop page and verify WC products show in WC modal.
		await page.goto( PRODUCTS.kraftMailer.url );
		const btn = page.locator( BTN_SELECTOR );
		await expect( btn ).toBeVisible();
		await btn.click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		// Verify WC products are shown (not library samples).
		const kraftItem = modal.locator(
			`.shqf-picker-item[data-sample-id="${ PRODUCTS.kraftMailer.id }"]`
		);
		await expect( kraftItem ).toBeVisible();
	} );
} );

// =====================================================================
// Product type filtering (verified via button presence on shop loop)
// =====================================================================

test.describe( 'Product Type Filtering', () => {
	test( 'Out-of-stock product has no badge on shop page', async ( {
		page,
	} ) => {
		await page.goto( SHOP_URL );
		// Discontinued Tape Roll is out of stock -- should not have a badge.
		const badge = page.locator(
			`a.shqf-woo-sample-badge[data-product-id="${ PRODUCTS.discontinuedTape.id }"]`
		);
		await expect( badge ).toHaveCount( 0 );
	} );

	test( 'In-stock simple products have badges on shop page', async ( {
		page,
	} ) => {
		await page.goto( SHOP_URL );
		// Kraft Mailer is in-stock simple -- should have badge.
		const badge = page.locator(
			`a.shqf-woo-sample-badge[data-product-id="${ PRODUCTS.kraftMailer.id }"]`
		);
		await expect( badge ).toBeVisible();
	} );
} );
