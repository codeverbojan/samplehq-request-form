/**
 * WooCommerce Admin Settings E2E Tests (Phase 7)
 *
 * Tests the WooCommerce settings tab: visibility, fields, save/persist,
 * and verification that settings take effect on the frontend.
 */

import { test, expect } from '@playwright/test';
import {
	SETTINGS_URL,
	PRODUCTS,
	BTN_SELECTOR,
	BTN_LOOP_SELECTOR,
	SHOP_URL,
	setOption,
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
// 7.1-7.2: Tab Visibility
// =====================================================================

test.describe( 'Settings Tab', () => {
	test( '7.1 WooCommerce tab visible on settings page', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=shqf-settings' );
		const wcTab = page.locator( 'a.nav-tab', {
			hasText: 'WooCommerce',
		} );
		await expect( wcTab ).toBeVisible();
	} );

	test( '7.2 Tab content loads with settings form', async ( { page } ) => {
		await page.goto( SETTINGS_URL );
		// Check the form and key elements exist.
		await expect(
			page.locator( 'input[name="shqf_woo_enabled"]' )
		).toBeVisible();
		await expect(
			page.locator( 'input[name="shqf_woo_button_text"]' )
		).toBeVisible();
		await expect(
			page.locator( 'select[name="shqf_woo_product_filter"]' )
		).toBeVisible();
	} );
} );

// =====================================================================
// 7.3-7.10: Settings Fields
// =====================================================================

test.describe( 'Settings Fields', () => {
	test( '7.3 Enable toggle checkbox', async ( { page } ) => {
		await page.goto( SETTINGS_URL );
		const checkbox = page.locator( 'input[name="shqf_woo_enabled"]' );
		await expect( checkbox ).toBeChecked();
	} );

	test( '7.4 Button text field has current value', async ( { page } ) => {
		await page.goto( SETTINGS_URL );
		const input = page.locator( 'input[name="shqf_woo_button_text"]' );
		await expect( input ).toHaveValue( 'Request a Sample' );
	} );

	test( '7.5 Filter dropdown has all/tagged/category options', async ( {
		page,
	} ) => {
		await page.goto( SETTINGS_URL );
		const select = page.locator( 'select[name="shqf_woo_product_filter"]' );
		await expect( select ).toBeVisible();
		const options = select.locator( 'option' );
		await expect( options ).toHaveCount( 3 );
	} );

	test( '7.6 Tag field visible', async ( { page } ) => {
		await page.goto( SETTINGS_URL );
		const input = page.locator( 'input[name="shqf_woo_sample_tag"]' );
		await expect( input ).toBeVisible();
		await expect( input ).toHaveValue( 'sample-available' );
	} );

	test( '7.7 Max quantity field', async ( { page } ) => {
		await page.goto( SETTINGS_URL );
		const input = page.locator( 'input[name="shqf_woo_max_quantity"]' );
		await expect( input ).toHaveValue( '3' );
	} );

	test( '7.8 Form selector dropdown', async ( { page } ) => {
		await page.goto( SETTINGS_URL );
		const select = page.locator( 'select[name="shqf_woo_form_id"]' );
		await expect( select ).toBeVisible();
		// Should have at least the default option + our test form.
		const options = select.locator( 'option' );
		const count = await options.count();
		expect( count ).toBeGreaterThanOrEqual( 2 );
	} );

	test( '7.9 Show loop badge checkbox', async ( { page } ) => {
		await page.goto( SETTINGS_URL );
		const checkbox = page.locator(
			'input[name="shqf_woo_show_loop_badge"]'
		);
		await expect( checkbox ).toBeVisible();
		await expect( checkbox ).toBeChecked();
	} );

	test( '7.10 Badge text field', async ( { page } ) => {
		await page.goto( SETTINGS_URL );
		const input = page.locator( 'input[name="shqf_woo_badge_text"]' );
		await expect( input ).toBeVisible();
	} );
} );

// =====================================================================
// 7.11-7.15: Save and Persist
// =====================================================================

test.describe( 'Save and Persist', () => {
	test( '7.11-7.12 Save persists settings and shows notice', async ( {
		page,
	} ) => {
		await page.goto( SETTINGS_URL );
		// Change button text to trigger a save.
		const input = page.locator( 'input[name="shqf_woo_button_text"]' );
		await input.fill( 'Get Free Sample' );
		await page.locator( '#submit' ).click();
		// Wait for redirect to complete (success notice in DOM).
		await expect( page.locator( '.notice-success' ) ).toBeVisible();
		// Verify persisted value after reload.
		await page.goto( SETTINGS_URL );
		await expect( input ).toHaveValue( 'Get Free Sample' );
		// Restore default.
		await input.fill( 'Request a Sample' );
		await page.locator( '#submit' ).click();
		await expect( page.locator( '.notice-success' ) ).toBeVisible();
	} );

	test( '7.13 Button text reflects on product page', async ( { page } ) => {
		// Set custom text via option helper.
		await page.goto( PRODUCTS.kraftMailer.url );
		await setOption( page, 'shqf_woo_button_text', 'Try It Free' );
		await page.reload();
		const btn = page.locator( BTN_SELECTOR );
		await expect( btn ).toHaveText( 'Try It Free' );
		// Restore.
		await setOption( page, 'shqf_woo_button_text', 'Request a Sample' );
	} );

	test( '7.14 Filter=tagged hides button on untagged product', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.bubbleWrap.url );
		await setOption( page, 'shqf_woo_product_filter', 'tagged' );
		await page.reload();
		await expect( page.locator( BTN_SELECTOR ) ).toHaveCount( 0 );
		// Restore.
		await setOption( page, 'shqf_woo_product_filter', 'all' );
	} );

	test( '7.15 Badge text setting affects shop loop', async ( { page } ) => {
		await page.goto( SHOP_URL );
		await setOption( page, 'shqf_woo_badge_text', 'Sample this product' );
		await page.reload();
		const badge = page.locator( BTN_LOOP_SELECTOR ).first();
		await expect( badge ).toContainText( 'Sample this product' );
		// Restore.
		await setOption( page, 'shqf_woo_badge_text', '' );
	} );

	test( '7.16 Show loop badge toggle hides badges', async ( { page } ) => {
		await page.goto( SHOP_URL );
		await setOption( page, 'shqf_woo_show_loop_badge', '' );
		await page.reload();
		await expect( page.locator( BTN_LOOP_SELECTOR ) ).toHaveCount( 0 );
		// Restore.
		await setOption( page, 'shqf_woo_show_loop_badge', '1' );
	} );
} );
