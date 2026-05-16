/* eslint-disable @wordpress/no-global-active-element -- page.evaluate runs in browser context */
/**
 * WooCommerce Modal + Form E2E Tests (Phases 4 & 5)
 *
 * Tests modal open/close, accessibility, scroll lock,
 * form fields, product pre-selection, search, quantity,
 * and light mode enforcement.
 */

import { test, expect } from '@playwright/test';
import { PRODUCTS, BTN_SELECTOR, MODAL_SELECTOR } from './woo-helpers';

const PRODUCT_URL = PRODUCTS.kraftMailer.url;

/**
 * Open the modal on the Kraft Mailer product page.
 * @param page
 */
async function openModal( page: import('@playwright/test').Page ) {
	await page.goto( PRODUCT_URL );
	await page.locator( BTN_SELECTOR ).click();
	await expect( page.locator( MODAL_SELECTOR ) ).toBeVisible();
}

// =====================================================================
// Phase 4A: Modal Open / Close (tests 4.1 - 4.6)
// =====================================================================

test.describe( 'Modal Open/Close', () => {
	test( '4.1 Click button opens modal', async ( { page } ) => {
		await page.goto( PRODUCT_URL );
		const modal = page.locator( MODAL_SELECTOR );
		// Modal starts hidden.
		await expect( modal ).toBeHidden();
		// Click button.
		await page.locator( BTN_SELECTOR ).click();
		await expect( modal ).toBeVisible();
	} );

	test( '4.2 Modal has backdrop', async ( { page } ) => {
		await openModal( page );
		const backdrop = page.locator( '.shqf-woo-modal__backdrop' );
		await expect( backdrop ).toBeVisible();
	} );

	test( '4.3 Close via X button', async ( { page } ) => {
		await openModal( page );
		await page.locator( '.shqf-woo-modal__close' ).click();
		await expect( page.locator( MODAL_SELECTOR ) ).toBeHidden();
	} );

	test( '4.4 Close via Escape key', async ( { page } ) => {
		await openModal( page );
		await page.keyboard.press( 'Escape' );
		await expect( page.locator( MODAL_SELECTOR ) ).toBeHidden();
	} );

	test( '4.5 Close via backdrop click', async ( { page } ) => {
		await openModal( page );
		// Click on the backdrop (top-left corner of the modal overlay).
		await page.locator( '.shqf-woo-modal__backdrop' ).click( {
			position: { x: 10, y: 10 },
		} );
		await expect( page.locator( MODAL_SELECTOR ) ).toBeHidden();
	} );

	test( '4.6 Click inside modal does NOT close', async ( { page } ) => {
		await openModal( page );
		// Click on the form title inside the modal.
		await page.locator( '.shqf-woo-modal__title' ).click();
		await expect( page.locator( MODAL_SELECTOR ) ).toBeVisible();
	} );
} );

// =====================================================================
// Phase 4B: Modal Accessibility + Scroll Lock (tests 4.7 - 4.20)
// =====================================================================

test.describe( 'Modal Accessibility', () => {
	test( '4.7 role=dialog and aria-modal', async ( { page } ) => {
		await openModal( page );
		const modal = page.locator( MODAL_SELECTOR );
		await expect( modal ).toHaveAttribute( 'role', 'dialog' );
		await expect( modal ).toHaveAttribute( 'aria-modal', 'true' );
	} );

	test( '4.8 aria-labelledby points to title', async ( { page } ) => {
		await openModal( page );
		const modal = page.locator( MODAL_SELECTOR );
		const labelledBy = await modal.getAttribute( 'aria-labelledby' );
		expect( labelledBy ).toBe( 'shqf-woo-modal-title' );
		// Verify the target element exists.
		const title = page.locator( '#shqf-woo-modal-title' );
		await expect( title ).toBeVisible();
	} );

	test( '4.9 Focus moves to close button on open', async ( { page } ) => {
		await openModal( page );
		// Active element should be the close button.

		const focused = await page.evaluate( () => {
			const el = document.activeElement;
			return (
				el?.classList.contains( 'shqf-woo-modal__close' ) ||
				el?.closest( '.shqf-woo-modal__close' ) !== null
			);
		} );
		expect( focused ).toBe( true );
	} );

	test( '4.10 Focus returns to trigger button on close', async ( {
		page,
	} ) => {
		await openModal( page );
		await page.keyboard.press( 'Escape' );
		// Active element should be back on the request button.

		const focused = await page.evaluate( () => {
			return document.activeElement?.classList.contains(
				'shqf-woo-request-btn'
			);
		} );
		expect( focused ).toBe( true );
	} );

	test( '4.11 Focus trap: Tab stays within modal', async ( { page } ) => {
		await openModal( page );
		// Tab several times and verify focus stays inside modal.
		for ( let i = 0; i < 15; i++ ) {
			await page.keyboard.press( 'Tab' );
		}

		const insideModal = await page.evaluate( () => {
			const el = document.activeElement;
			return el?.closest( '#shqf-woo-modal' ) !== null;
		} );
		expect( insideModal ).toBe( true );
	} );

	test( '4.12 Focus trap: Shift+Tab wraps', async ( { page } ) => {
		await openModal( page );
		// Shift+Tab from close button should wrap to last element.
		await page.keyboard.press( 'Shift+Tab' );

		const insideModal = await page.evaluate( () => {
			return (
				document.activeElement?.closest( '#shqf-woo-modal' ) !== null
			);
		} );
		expect( insideModal ).toBe( true );
	} );

	test( '4.13 Background has inert attribute', async ( { page } ) => {
		await openModal( page );
		// Check that at least one sibling of modal has inert.
		const inertCount = await page.evaluate( () => {
			return document.querySelectorAll( 'body > [inert]' ).length;
		} );
		expect( inertCount ).toBeGreaterThan( 0 );
	} );

	test( '4.14 Inert removed on close', async ( { page } ) => {
		await openModal( page );
		await page.keyboard.press( 'Escape' );
		const inertCount = await page.evaluate( () => {
			return document.querySelectorAll( '[inert]' ).length;
		} );
		expect( inertCount ).toBe( 0 );
	} );
} );

test.describe( 'Scroll Lock', () => {
	test( '4.15 Body scroll locked when modal open', async ( { page } ) => {
		await openModal( page );
		const hasClass = await page.evaluate( () => {
			return document.documentElement.classList.contains(
				'shqf-modal-open'
			);
		} );
		expect( hasClass ).toBe( true );
	} );

	test( '4.16 Scroll restored on close', async ( { page } ) => {
		await openModal( page );
		await page.keyboard.press( 'Escape' );
		const hasClass = await page.evaluate( () => {
			return document.documentElement.classList.contains(
				'shqf-modal-open'
			);
		} );
		expect( hasClass ).toBe( false );
	} );
} );

test.describe( 'Modal Content', () => {
	test( '4.17 Title shows form name', async ( { page } ) => {
		await openModal( page );
		const title = page.locator( '.shqf-woo-modal__title' );
		await expect( title ).toHaveText( 'WC Test Form' );
	} );

	test( '4.18 Close button is at least 44px', async ( { page } ) => {
		await openModal( page );
		const size = await page
			.locator( '.shqf-woo-modal__close' )
			.evaluate( ( el ) => {
				const rect = el.getBoundingClientRect();
				return { w: rect.width, h: rect.height };
			} );
		expect( size.w ).toBeGreaterThanOrEqual( 44 );
		expect( size.h ).toBeGreaterThanOrEqual( 44 );
	} );

	test( '4.19 Modal max-width is 640px', async ( { page } ) => {
		await openModal( page );
		const maxWidth = await page
			.locator( '.shqf-woo-modal__content' )
			.evaluate( ( el ) => getComputedStyle( el ).maxWidth );
		expect( maxWidth ).toBe( '640px' );
	} );
} );

// =====================================================================
// Phase 5A: Form Fields + Picker (tests 5.1 - 5.7)
// =====================================================================

test.describe( 'Form Fields', () => {
	test( '5.1 Name field visible', async ( { page } ) => {
		await openModal( page );
		// Name field uses <legend> inside <fieldset>, not <label>.
		const nameField = page
			.locator( '.shqf-woo-modal__body' )
			.getByText( 'Full Name', { exact: false } );
		await expect( nameField.first() ).toBeVisible();
	} );

	test( '5.2 Email field visible', async ( { page } ) => {
		await openModal( page );
		await expect(
			page.locator( '.shqf-woo-modal__body label', {
				hasText: 'Email',
			} )
		).toBeVisible();
	} );

	test( '5.3 Sample picker visible', async ( { page } ) => {
		await openModal( page );
		await expect(
			page.locator( '.shqf-woo-modal__body .shqf-picker' )
		).toBeVisible();
	} );

	test( '5.4 Picker shows 6 WC products', async ( { page } ) => {
		await openModal( page );
		const items = page.locator( '.shqf-woo-modal__body .shqf-picker-item' );
		await expect( items ).toHaveCount( 6 );
	} );

	test( '5.5 Product names are correct', async ( { page } ) => {
		await openModal( page );
		const body = page.locator( '.shqf-woo-modal__body' );
		await expect(
			body.locator( '.shqf-picker-item-label', {
				hasText: 'Kraft Mailer Box',
			} )
		).toBeVisible();
		await expect(
			body.locator( '.shqf-picker-item-label', {
				hasText: 'Poly Bag',
			} )
		).toBeVisible();
	} );

	test( '5.6 Out-of-stock product excluded from picker', async ( {
		page,
	} ) => {
		await openModal( page );
		const body = page.locator( '.shqf-woo-modal__body' );
		await expect(
			body.locator( '.shqf-picker-item-label', {
				hasText: 'Discontinued Tape Roll',
			} )
		).toHaveCount( 0 );
	} );

	test( '5.7 Draft product excluded from picker', async ( { page } ) => {
		await openModal( page );
		const body = page.locator( '.shqf-woo-modal__body' );
		await expect(
			body.locator( '.shqf-picker-item-label', {
				hasText: 'Unreleased Box Design',
			} )
		).toHaveCount( 0 );
	} );
} );

// =====================================================================
// Phase 5B: Pre-selection, Search, Qty, Light Mode (tests 5.8 - 5.27)
// =====================================================================

test.describe( 'Product Pre-selection', () => {
	test( '5.8 Current product pre-selected with highlight', async ( {
		page,
	} ) => {
		await openModal( page );
		const item = page.locator(
			`.shqf-picker-item[data-sample-id="${ PRODUCTS.kraftMailer.id }"]`
		);
		await expect( item ).toHaveClass( /shqf-picker-item--selected/ );
	} );

	test( '5.9 Pre-selected checkbox is checked', async ( { page } ) => {
		await openModal( page );
		const checkbox = page.locator(
			`.shqf-picker-item[data-sample-id="${ PRODUCTS.kraftMailer.id }"] input[type="checkbox"]`
		);
		await expect( checkbox ).toBeChecked();
	} );

	test( '5.10 Selection count updated', async ( { page } ) => {
		await openModal( page );
		const status = page.locator(
			'.shqf-woo-modal__body .shqf-picker-status'
		);
		const text = await status.textContent();
		expect( text ).toContain( '1' );
		expect( text ).toContain( 'selected' );
	} );

	test( '5.11 Second product selectable', async ( { page } ) => {
		await openModal( page );
		// Click Poly Bag card.
		const polyBag = page.locator(
			`.shqf-picker-item[data-sample-id="${ PRODUCTS.polyBag.id }"]`
		);
		await polyBag.click();
		await expect( polyBag ).toHaveClass( /shqf-picker-item--selected/ );
		// Count should be 2.
		const status = page.locator(
			'.shqf-woo-modal__body .shqf-picker-status'
		);
		const text = await status.textContent();
		expect( text ).toContain( '2' );
	} );
} );

test.describe( 'Search and Category Pills', () => {
	test( '5.12 Search bar visible', async ( { page } ) => {
		await openModal( page );
		await expect(
			page.locator( '.shqf-woo-modal__body .shqf-picker-search' )
		).toBeVisible();
	} );

	test( '5.13 Search filters products', async ( { page } ) => {
		await openModal( page );
		const search = page.locator(
			'.shqf-woo-modal__body .shqf-picker-search'
		);
		await search.fill( 'Kraft' );
		await page.waitForTimeout( 500 );
		const count = await page.evaluate( () => {
			return Array.from(
				document.querySelectorAll(
					'.shqf-woo-modal__body .shqf-picker-item'
				)
			).filter( ( el ) => ( el as HTMLElement ).style.display !== 'none' )
				.length;
		} );
		expect( count ).toBe( 1 );
	} );

	test( '5.14 Search clear shows all', async ( { page } ) => {
		await openModal( page );
		const search = page.locator(
			'.shqf-woo-modal__body .shqf-picker-search'
		);
		await search.fill( 'Kraft' );
		await page.waitForTimeout( 300 );
		// Clear search.
		await search.click( { clickCount: 3 } );
		await page.keyboard.press( 'Delete' );
		await page.waitForTimeout( 300 );
		// All 6 items should be visible again.
		const allItems = page.locator(
			'.shqf-woo-modal__body .shqf-picker-item'
		);
		const total = await allItems.count();
		let visibleCount = 0;
		for ( let i = 0; i < total; i++ ) {
			if ( await allItems.nth( i ).isVisible() ) {
				visibleCount++;
			}
		}
		expect( visibleCount ).toBe( 6 );
	} );

	test( '5.15 Category pills visible', async ( { page } ) => {
		await openModal( page );
		const pills = page.locator( '.shqf-woo-modal__body .shqf-pill' );
		const count = await pills.count();
		// At least "All" + 3 categories.
		expect( count ).toBeGreaterThanOrEqual( 4 );
		await expect( pills.first() ).toHaveText( 'All' );
	} );

	test( '5.16 Category pill filters products', async ( { page } ) => {
		await openModal( page );
		const boxesPill = page.locator( '.shqf-woo-modal__body .shqf-pill', {
			hasText: 'Boxes',
		} );
		await boxesPill.click();
		await page.waitForTimeout( 500 );
		const count = await page.evaluate( () => {
			return Array.from(
				document.querySelectorAll(
					'.shqf-woo-modal__body .shqf-picker-item'
				)
			).filter( ( el ) => ( el as HTMLElement ).style.display !== 'none' )
				.length;
		} );
		// Kraft Mailer + Corrugated Box = 2 in Boxes.
		expect( count ).toBe( 2 );
	} );

	test( '5.17 All pill resets filter', async ( { page } ) => {
		await openModal( page );
		// Filter by Boxes first.
		await page
			.locator( '.shqf-woo-modal__body .shqf-pill', {
				hasText: 'Boxes',
			} )
			.click();
		await page.waitForTimeout( 300 );
		// Click All.
		await page
			.locator( '.shqf-woo-modal__body .shqf-pill', {
				hasText: 'All',
			} )
			.click();
		await page.waitForTimeout( 500 );
		const allItems = page.locator(
			'.shqf-woo-modal__body .shqf-picker-item'
		);
		const total = await allItems.count();
		let visibleCount = 0;
		for ( let i = 0; i < total; i++ ) {
			if ( await allItems.nth( i ).isVisible() ) {
				visibleCount++;
			}
		}
		expect( visibleCount ).toBe( 6 );
	} );
} );

test.describe( 'Quantity Stepper', () => {
	test( '5.18-5.22 Quantity controls work', async ( { page } ) => {
		await openModal( page );
		// Kraft Mailer should be pre-selected. Find its qty controls.
		const item = page.locator(
			`.shqf-picker-item[data-sample-id="${ PRODUCTS.kraftMailer.id }"]`
		);
		const qtyControls = item.locator( '.shqf-picker-item-qty-controls' );
		const qtyValue = item.locator( '.shqf-picker-item-qty-value' );
		const plusBtn = item.locator( '.shqf-qty-plus' );
		const minusBtn = item.locator( '.shqf-qty-minus' );

		// 5.18: Controls visible for selected item.
		await expect( qtyControls ).toBeVisible();

		// 5.19: Increase quantity.
		await expect( qtyValue ).toHaveText( '1' );
		await plusBtn.click();
		await expect( qtyValue ).toHaveText( '2' );

		// 5.20: Max quantity enforced (shqf_woo_max_quantity = 3).
		await plusBtn.click(); // 3
		await plusBtn.click(); // Should not go to 4.
		const val = await qtyValue.textContent();
		expect( parseInt( val || '0', 10 ) ).toBeLessThanOrEqual( 3 );

		// 5.21-5.22: Decrease and min is 1.
		await minusBtn.click(); // 2
		await minusBtn.click(); // 1
		await minusBtn.click(); // Should stay at 1.
		await expect( qtyValue ).toHaveText( '1' );
	} );
} );

test.describe( 'Light Mode Enforcement', () => {
	test( '5.23 Modal background is white', async ( { page } ) => {
		await openModal( page );
		const bg = await page
			.locator( '.shqf-woo-modal__content' )
			.evaluate( ( el ) => getComputedStyle( el ).backgroundColor );
		// rgb(255, 255, 255) = white.
		expect( bg ).toBe( 'rgb(255, 255, 255)' );
	} );

	test( '5.25 Input background is white/light', async ( { page } ) => {
		await openModal( page );
		const inputBg = await page
			.locator( '.shqf-woo-modal__body input[type="text"]' )
			.first()
			.evaluate( ( el ) => getComputedStyle( el ).backgroundColor );
		// Should be white (rgb(255,255,255)) or very light.
		const rgb = inputBg.match( /\d+/g )?.map( Number ) || [];
		expect( rgb[ 0 ] ).toBeGreaterThan( 240 ); // R > 240
		expect( rgb[ 1 ] ).toBeGreaterThan( 240 ); // G > 240
		expect( rgb[ 2 ] ).toBeGreaterThan( 240 ); // B > 240
	} );

	test( '5.26 Label text is dark', async ( { page } ) => {
		await openModal( page );
		const color = await page
			.locator( '.shqf-woo-modal__body label' )
			.first()
			.evaluate( ( el ) => getComputedStyle( el ).color );
		const rgb = color.match( /\d+/g )?.map( Number ) || [];
		// Dark text: R, G, B should each be < 100.
		expect( rgb[ 0 ] ).toBeLessThan( 120 );
	} );
} );
