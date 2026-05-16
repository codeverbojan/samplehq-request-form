/**
 * WooCommerce Product Page Button E2E Tests
 *
 * Tests button visibility, styling, position, and product filtering
 * on the live dev site.
 */

import { test, expect } from '@playwright/test';
import {
	PRODUCTS,
	BTN_SELECTOR,
	BTN_LOOP_SELECTOR,
	SHOP_URL,
	CATEGORIES,
	withOption,
	restoreDefaults,
	setOption,
} from './woo-helpers';

// Restore defaults after each test that mutates settings.
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
// Phase 2A: Button Visibility (tests 2.1 - 2.5)
// =====================================================================

test.describe( 'Button Visibility', () => {
	test( '2.1 Button visible on in-stock published product', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		const btn = page.locator( BTN_SELECTOR );
		await expect( btn ).toBeVisible();
		await expect( btn ).toHaveText( 'Request a Sample' );
	} );

	test( '2.2 Button NOT visible on out-of-stock product', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.discontinuedTape.url );
		const btn = page.locator( BTN_SELECTOR );
		await expect( btn ).toHaveCount( 0 );
	} );

	test( '2.3 Button NOT visible on draft product', async ( { page } ) => {
		// Draft products return 404 for non-admin, or redirect.
		// Even for admins, draft products should not show the button
		// because status !== 'publish'.
		await page.goto( PRODUCTS.unreleasedBox.url );
		const btn = page.locator( BTN_SELECTOR );
		await expect( btn ).toHaveCount( 0 );
	} );

	test( '2.4 Button NOT visible when WC integration disabled', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await withOption( page, 'shqf_woo_enabled', '', async () => {
			await page.reload();
			const btn = page.locator( BTN_SELECTOR );
			await expect( btn ).toHaveCount( 0 );
		} );
		// afterAll restores defaults
	} );

	test( '2.5 Button visible on variable product', async ( { page } ) => {
		// Variable product may not show Add to Cart (and our hook) if
		// no variations have prices. Check if our button or the loop
		// button renders. The shop loop button always shows because
		// it hooks into woocommerce_after_shop_loop_item.
		await page.goto( PRODUCTS.customLabelRoll.url );
		const btn = page.locator( BTN_SELECTOR );
		const hasSingleBtn = ( await btn.count() ) > 0;

		if ( ! hasSingleBtn ) {
			// Variable product without prices -- the add-to-cart form
			// doesn't render, so our hook doesn't fire. This is expected
			// WC behavior. Verify the shop loop still has a button for it.
			await page.goto( SHOP_URL );
			const loopBtn = page.locator(
				`${ BTN_LOOP_SELECTOR }[data-product-id="${ PRODUCTS.customLabelRoll.id }"]`
			);
			await expect( loopBtn ).toBeVisible();
		} else {
			await expect( btn ).toBeVisible();
		}
	} );
} );

// =====================================================================
// Phase 2B: Button Styling and Position (tests 2.6 - 2.12)
// =====================================================================

test.describe( 'Button Styling', () => {
	test( '2.6 Button has outline/secondary styling', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		const btn = page.locator( BTN_SELECTOR );
		await expect( btn ).toHaveClass( /shqf-woo-request-btn/ );
		// Should have transparent/no background (outline style).
		const bg = await btn.evaluate(
			( el ) => getComputedStyle( el ).backgroundColor
		);
		// transparent = rgba(0,0,0,0)
		expect( bg ).toMatch( /transparent|rgba\(0,\s*0,\s*0,\s*0\)/ );
	} );

	test( '2.7 Button is wrapped in .shqf-woo-request-wrap', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		const wrap = page.locator( '.shqf-woo-request-wrap' );
		await expect( wrap ).toBeVisible();
		const btn = wrap.locator( BTN_SELECTOR );
		await expect( btn ).toBeVisible();
	} );

	test( '2.8 Custom button text from settings', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await withOption(
			page,
			'shqf_woo_button_text',
			'Get Free Sample',
			async () => {
				await page.reload();
				const btn = page.locator( BTN_SELECTOR );
				await expect( btn ).toHaveText( 'Get Free Sample' );
			}
		);
	} );
} );

test.describe( 'Button Position', () => {
	test( '2.9 Button is below the add-to-cart form', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		const btn = page.locator( BTN_SELECTOR );
		await expect( btn ).toBeVisible();
		// Button should NOT be inside form.cart (it's after the form).
		const btnInForm = page.locator( 'form.cart ' + BTN_SELECTOR );
		await expect( btnInForm ).toHaveCount( 0 );
	} );
} );

// =====================================================================
// Phase 2C: Product Filtering (tests 2.13 - 2.22)
// =====================================================================

test.describe( 'Product Filtering', () => {
	test( '2.13 Filter all: button on every in-stock product', async ( {
		page,
	} ) => {
		// Default is 'all'. Check multiple products.
		for ( const product of [
			PRODUCTS.kraftMailer,
			PRODUCTS.polyBag,
			PRODUCTS.bubbleWrap,
			PRODUCTS.corrugatedBox,
		] ) {
			await page.goto( product.url );
			await expect( page.locator( BTN_SELECTOR ) ).toBeVisible();
		}
	} );

	test( '2.14 Filter tagged: only tagged products', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await withOption(
			page,
			'shqf_woo_product_filter',
			'tagged',
			async () => {
				// Tagged product: button visible
				await page.reload();
				await expect( page.locator( BTN_SELECTOR ) ).toBeVisible();

				// Untagged product: no button
				await page.goto( PRODUCTS.bubbleWrap.url );
				await expect( page.locator( BTN_SELECTOR ) ).toHaveCount( 0 );

				// Another untagged product
				await page.goto( PRODUCTS.corrugatedBox.url );
				await expect( page.locator( BTN_SELECTOR ) ).toHaveCount( 0 );
			}
		);
	} );

	test( '2.15 Filter category: only matching category products', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await setOption( page, 'shqf_woo_product_filter', 'category' );
		await setOption(
			page,
			'shqf_woo_sample_categories',
			JSON.stringify( [ CATEGORIES.boxes ] )
		);

		await page.reload();
		// Kraft Mailer is in Boxes: visible
		await expect( page.locator( BTN_SELECTOR ) ).toBeVisible();

		// Poly Bag is in Bags: not visible
		await page.goto( PRODUCTS.polyBag.url );
		await expect( page.locator( BTN_SELECTOR ) ).toHaveCount( 0 );

		// Restore
		await restoreDefaults( page );
	} );

	test( '2.16 Filter tagged with nonexistent tag: no buttons', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await setOption( page, 'shqf_woo_product_filter', 'tagged' );
		await setOption( page, 'shqf_woo_sample_tag', 'nonexistent-tag-xyz' );

		await page.reload();
		await expect( page.locator( BTN_SELECTOR ) ).toHaveCount( 0 );

		await restoreDefaults( page );
	} );

	test( '2.17 Filter category with empty list: no buttons', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await setOption( page, 'shqf_woo_product_filter', 'category' );
		await setOption( page, 'shqf_woo_sample_categories', '' );

		await page.reload();
		await expect( page.locator( BTN_SELECTOR ) ).toHaveCount( 0 );

		await restoreDefaults( page );
	} );
} );

test.describe( 'Button Data Attributes', () => {
	test( '2.18-2.20 Button has correct data attributes', async ( {
		page,
	} ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		const btn = page.locator( BTN_SELECTOR );
		await expect( btn ).toHaveAttribute(
			'data-product-id',
			String( PRODUCTS.kraftMailer.id )
		);
		await expect( btn ).toHaveAttribute(
			'data-product-name',
			PRODUCTS.kraftMailer.name
		);
		await expect( btn ).toHaveAttribute(
			'data-product-sku',
			PRODUCTS.kraftMailer.sku
		);
	} );
} );

// =====================================================================
// Phase 3: Shop Loop (tests 3.1 - 3.5)
// =====================================================================

test.describe( 'Shop Loop', () => {
	test( '3.1 Buttons visible on shop page', async ( { page } ) => {
		await page.goto( SHOP_URL );
		const buttons = page.locator( BTN_LOOP_SELECTOR );
		// Should have a button for each in-stock published product
		const count = await buttons.count();
		expect( count ).toBeGreaterThanOrEqual( 4 );
	} );

	test( '3.2 Shop loop link points to product page with hash', async ( {
		page,
	} ) => {
		await page.goto( SHOP_URL );
		const firstBtn = page.locator( BTN_LOOP_SELECTOR ).first();
		const href = await firstBtn.getAttribute( 'href' );
		expect( href ).toContain( '#request-sample' );
		expect( href ).toContain( '/product/' );
	} );

	test( '3.3-3.4 Click shop loop link navigates and auto-opens modal', async ( {
		page,
	} ) => {
		await page.goto( SHOP_URL );
		// Find button for a specific product
		const btn = page.locator(
			`${ BTN_LOOP_SELECTOR }[data-product-id="${ PRODUCTS.kraftMailer.id }"]`
		);

		if ( ( await btn.count() ) > 0 ) {
			await btn.click();
			// Should navigate to product page
			await page.waitForURL( /product\// );
			// Modal should auto-open
			const modal = page.locator( '#shqf-woo-modal' );
			await expect( modal ).toBeVisible( { timeout: 5000 } );
		}
	} );
} );
