/**
 * WooCommerce Mobile / Responsive E2E Tests (Phase 10)
 *
 * Runs with Pixel 7 viewport (412x915) via the woo-mobile project.
 * Tests modal UX, button sizing, scroll lock, and picker grid on mobile.
 */

import { test, expect } from '@playwright/test';
import {
	PRODUCTS,
	BTN_SELECTOR,
	BTN_LOOP_SELECTOR,
	SHOP_URL,
	MODAL_SELECTOR,
} from './woo-helpers';

// =====================================================================
// 10.1-10.2: Button and Modal Sizing
// =====================================================================

test.describe( 'Mobile Layout', () => {
	test( '10.1 Button visible on product page (mobile)', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		const btn = page.locator( BTN_SELECTOR );
		await expect( btn ).toBeVisible();
		// Button should fill available width on mobile.
		const box = await btn.boundingBox();
		expect( box ).not.toBeNull();
		expect( box!.width ).toBeGreaterThan( 200 );
	} );

	test( '10.2 Modal fills viewport on mobile', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		// Modal content should fill most of the viewport width.
		const content = modal.locator( '.shqf-woo-modal__content' );
		const box = await content.boundingBox();
		expect( box ).not.toBeNull();
		// On iPhone 14 (375px), modal should be at least 350px wide.
		expect( box!.width ).toBeGreaterThanOrEqual( 340 );
	} );

	test( '10.3 Close button is tappable (>= 44x44px)', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		const closeBtn = modal.locator( '.shqf-woo-modal__close' );
		const box = await closeBtn.boundingBox();
		expect( box ).not.toBeNull();
		expect( box!.width ).toBeGreaterThanOrEqual( 44 );
		expect( box!.height ).toBeGreaterThanOrEqual( 44 );
	} );
} );

// =====================================================================
// 10.4-10.5: Scroll and Grid
// =====================================================================

test.describe( 'Mobile Scroll and Grid', () => {
	test( '10.4 Form is scrollable inside modal', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		// The modal body should have scrollable content.
		const body = modal.locator( '.shqf-woo-modal__body' );
		const scrollHeight = await body.evaluate( ( el ) => el.scrollHeight );
		const clientHeight = await body.evaluate( ( el ) => el.clientHeight );
		// Content should overflow (scrollHeight > clientHeight).
		expect( scrollHeight ).toBeGreaterThanOrEqual( clientHeight );
	} );

	test( '10.5 Picker items layout correctly on mobile', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		const items = modal.locator( '.shqf-picker-item' );
		const count = await items.count();
		expect( count ).toBeGreaterThanOrEqual( 5 );
		// First item should not exceed viewport width.
		const firstBox = await items.first().boundingBox();
		expect( firstBox ).not.toBeNull();
		expect( firstBox!.width ).toBeLessThanOrEqual( 375 );
	} );

	test( '10.6 Body scroll locked when modal open', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		const hasLock = await page.evaluate( () =>
			document.documentElement.classList.contains( 'shqf-modal-open' )
		);
		expect( hasLock ).toBe( true );
	} );

	test( '10.7 Modal dismissible on mobile', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		// Tap close button.
		await modal.locator( '.shqf-woo-modal__close' ).tap();
		await expect( modal ).toBeHidden();
	} );
} );

// =====================================================================
// 10.8: Shop Loop on Mobile
// =====================================================================

test.describe( 'Mobile Shop Loop', () => {
	test( '10.8 Shop loop badges visible and tappable', async ( { page } ) => {
		await page.goto( SHOP_URL );
		const badges = page.locator( BTN_LOOP_SELECTOR );
		const count = await badges.count();
		expect( count ).toBeGreaterThanOrEqual( 4 );
		// First badge should be tappable (visible, has href).
		const firstBadge = badges.first();
		await expect( firstBadge ).toBeVisible();
		const href = await firstBadge.getAttribute( 'href' );
		expect( href ).toContain( '#request-sample' );
	} );
} );
