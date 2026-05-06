/**
 * Helpers for WooCommerce E2E tests.
 *
 * Provides functions to read/write WP options, manage WC product cache,
 * and run arbitrary PHP on the dev site via a lightweight admin-ajax eval.
 *
 * These helpers work against a real WordPress site (not wp-env).
 */

import { type Page } from '@playwright/test';

const BASE = 'https://samplehq-wp-plugin.test';

/** Product URLs from the dev site fixture data. */
export const PRODUCTS = {
	kraftMailer: {
		id: 33,
		name: 'Kraft Mailer Box 10x8x4',
		sku: 'KMB-1084',
		url: '/product/kraft-mailer-box-10x8x4/',
		tags: [ 'sample-available' ],
		cats: [ 'boxes' ],
	},
	polyBag: {
		id: 34,
		name: 'Poly Bag 12x15',
		sku: 'PB-1215',
		url: '/product/poly-bag-12x15/',
		tags: [ 'sample-available' ],
		cats: [ 'bags' ],
	},
	bubbleWrap: {
		id: 35,
		name: 'Bubble Wrap Roll 24in',
		sku: 'BWR-024',
		url: '/product/bubble-wrap-roll-24in/',
		tags: [],
		cats: [ 'packaging-supplies' ],
	},
	labelSheet: {
		id: 36,
		name: 'Custom Printed Label Sheet',
		sku: 'CPL-001',
		url: '/product/custom-printed-label-sheet/',
		tags: [ 'sample-available' ],
		cats: [ 'packaging-supplies' ],
	},
	corrugatedBox: {
		id: 37,
		name: 'Corrugated Shipping Box 16x12x8',
		sku: 'CSB-1612',
		url: '/product/corrugated-shipping-box-16x12x8/',
		tags: [],
		cats: [ 'boxes' ],
	},
	customLabelRoll: {
		id: 38,
		name: 'Custom Label Roll',
		sku: 'CLR-001',
		url: '/product/custom-label-roll/',
		tags: [ 'sample-available' ],
		cats: [ 'packaging-supplies' ],
	},
	discontinuedTape: {
		id: 41,
		name: 'Discontinued Tape Roll',
		sku: 'DTR-001',
		url: '/product/discontinued-tape-roll/',
		tags: [],
		cats: [ 'packaging-supplies' ],
	},
	unreleasedBox: {
		id: 42,
		name: 'Unreleased Box Design',
		sku: 'UBD-001',
		url: '/?post_type=product&p=42',
		tags: [],
		cats: [ 'boxes' ],
	},
} as const;

export const SHOP_URL = '/shop/';
export const SETTINGS_URL =
	'/wp-admin/admin.php?page=shqf-settings&tab=woocommerce';
export const SUBMISSIONS_URL = '/wp-admin/admin.php?page=shqf-submissions';

/** WC category IDs on the dev site. */
export const CATEGORIES = {
	boxes: 17,
	bags: 18,
	packagingSupplies: 19,
} as const;

/**
 * Default WC settings to restore after mutating tests.
 */
export const DEFAULT_SETTINGS: Record< string, string > = {
	shqf_woo_enabled: '1',
	shqf_woo_form_id: '25',
	shqf_woo_button_text: 'Request a Sample',
	shqf_woo_product_filter: 'all',
	shqf_woo_sample_tag: 'sample-available',
	shqf_woo_sample_categories: '',
	shqf_woo_max_quantity: '3',
	shqf_woo_show_loop_badge: '1',
	shqf_woo_badge_text: '',
};

/**
 * Set a WordPress option via the admin settings page form post.
 * Uses a direct GET request with a custom endpoint we set up.
 */
export async function setOption(
	page: Page,
	key: string,
	value: string
): Promise< void > {
	await page.evaluate(
		async ( { k, v, base } ) => {
			const res = await fetch(
				`${ base }/wp-admin/admin-ajax.php?action=shqf_e2e_set_option&key=${ encodeURIComponent( k ) }&value=${ encodeURIComponent( v ) }`,
				{ credentials: 'same-origin' }
			);
			if ( ! res.ok ) {
				throw new Error(
					`setOption failed: ${ res.status } ${ res.statusText }`
				);
			}
		},
		{ k: key, v: value, base: BASE }
	);
}

/**
 * Get a WordPress option value.
 */
export async function getOption(
	page: Page,
	key: string
): Promise< string > {
	return page.evaluate(
		async ( { k, base } ) => {
			const res = await fetch(
				`${ base }/wp-admin/admin-ajax.php?action=shqf_e2e_get_option&key=${ encodeURIComponent( k ) }`,
				{ credentials: 'same-origin' }
			);
			return ( await res.json() ).data ?? '';
		},
		{ k: key, base: BASE }
	);
}

/**
 * Temporarily set an option, run a callback, then restore the original.
 */
export async function withOption(
	page: Page,
	key: string,
	value: string,
	fn: () => Promise< void >
): Promise< void > {
	const original = await getOption( page, key );
	await setOption( page, key, value );
	try {
		await fn();
	} finally {
		await setOption( page, key, original );
	}
}

/**
 * Restore all WC settings to defaults.
 */
export async function restoreDefaults( page: Page ): Promise< void > {
	for ( const [ k, v ] of Object.entries( DEFAULT_SETTINGS ) ) {
		await setOption( page, k, v );
	}
}

/** Button selector on single product pages. */
export const BTN_SELECTOR = 'button.shqf-woo-request-btn';

/** Badge selector on shop loop pages. */
export const BTN_LOOP_SELECTOR = 'a.shqf-woo-sample-badge';

/** Modal selector. */
export const MODAL_SELECTOR = '#shqf-woo-modal';
