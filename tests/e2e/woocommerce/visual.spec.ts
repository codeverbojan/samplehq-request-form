/**
 * WooCommerce Visual Regression E2E Tests (Phase 11)
 *
 * Screenshot comparisons for key UI states. On first run, these generate
 * baseline screenshots in __screenshots__/. Subsequent runs compare against
 * the baselines and fail on pixel differences.
 *
 * Run: npx playwright test --config playwright.woo.config.ts visual.spec.ts --update-snapshots
 * (first run to create baselines)
 */

import { test, expect } from '@playwright/test';
import {
	PRODUCTS,
	BTN_SELECTOR,
	SHOP_URL,
	SETTINGS_URL,
	MODAL_SELECTOR,
} from './woo-helpers';

test.describe( 'Visual Regression', () => {
	test( '11.1 Product page with button', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).waitFor();
		// Capture the product summary area containing the button.
		const summary = page.locator( '.summary, .wp-block-woocommerce-product-details' ).first();
		await expect( summary ).toHaveScreenshot( 'product-page-button.png', {
			maxDiffPixelRatio: 0.02,
		} );
	} );

	test( '11.2 Modal open state', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		await expect( modal ).toHaveScreenshot( 'modal-open.png', {
			maxDiffPixelRatio: 0.02,
		} );
	} );

	test( '11.3 Modal with product selected', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		// Wait for pre-selection to complete (selected class appears).
		await expect(
			modal.locator( '.shqf-picker-item--selected' )
		).toBeVisible();
		await expect( modal ).toHaveScreenshot( 'modal-product-selected.png', {
			maxDiffPixelRatio: 0.02,
		} );
	} );

	test( '11.4 Modal validation errors', async ( { page } ) => {
		await page.goto( PRODUCTS.kraftMailer.url );
		await page.locator( BTN_SELECTOR ).click();
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toBeVisible();
		// Clear the pre-selected product so validation fails on submit.
		const selectedItem = modal.locator( '.shqf-picker-item--selected input[type="checkbox"]' );
		if ( ( await selectedItem.count() ) > 0 ) {
			await selectedItem.first().uncheck( { force: true } );
		}
		// Clear required fields to trigger validation.
		const nameInput = modal.locator( 'input[name*="first_name"], input[name*="name"]' ).first();
		if ( ( await nameInput.count() ) > 0 ) {
			await nameInput.fill( '' );
		}
		// Submit to trigger validation errors.
		const submitBtn = modal.locator( 'button[type="submit"], input[type="submit"]' ).first();
		await expect( submitBtn ).toBeVisible();
		await submitBtn.click();
		// Wait for error indicators (class or aria).
		await page.waitForSelector( '.shqf-field--error, .shqf-field-error, [aria-invalid="true"]', {
			state: 'visible',
			timeout: 5000,
		} ).catch( () => {} );
		await expect( modal ).toHaveScreenshot(
			'modal-validation-errors.png',
			{ maxDiffPixelRatio: 0.05 }
		);
	} );

	test( '11.5 Shop loop with badges', async ( { page } ) => {
		await page.goto( SHOP_URL );
		await page.waitForLoadState( 'networkidle' );
		// Capture the product grid area.
		const products = page.locator(
			'.products, .wp-block-woocommerce-product-template'
		).first();
		if ( ( await products.count() ) > 0 ) {
			await expect( products ).toHaveScreenshot(
				'shop-loop-badges.png',
				{ maxDiffPixelRatio: 0.03 }
			);
		}
	} );

	test( '11.6 Admin WC settings tab', async ( { page } ) => {
		await page.goto( SETTINGS_URL );
		await page.waitForLoadState( 'networkidle' );
		const wrap = page.locator( '.wrap' ).first();
		await expect( wrap ).toHaveScreenshot( 'admin-wc-settings.png', {
			maxDiffPixelRatio: 0.02,
		} );
	} );
} );
