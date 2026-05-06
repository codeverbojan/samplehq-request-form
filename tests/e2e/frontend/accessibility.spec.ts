import { test, expect } from '@playwright/test';
import * as fs from 'fs';

const fixtures = JSON.parse(
	fs.readFileSync( 'tests/e2e/.fixtures.json', 'utf-8' )
);
const GRID_URL = fixtures.pageUrls.grid;

test.describe( 'Accessibility', () => {
	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
		await page.goto( GRID_URL );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();
	} );

	test( 'all visible inputs have associated labels', async ( { page } ) => {
		// Every visible input/select/textarea (except hidden + sr-only) should have a label.
		const unlabeled = await page.evaluate( () => {
			const inputs = document.querySelectorAll(
				'.shqf-form input:not([type="hidden"]):not(.shqf-sr-only), .shqf-form select, .shqf-form textarea'
			);
			const problems: string[] = [];
			inputs.forEach( ( input ) => {
				// Skip honeypot.
				if ( input.closest( '.shqf-hp' ) ) return;
				// Skip picker quantity inputs (have aria-label).
				if ( input.classList.contains( 'shqf-picker-item-qty' ) ) return;

				const id = input.id;
				const ariaLabel = input.getAttribute( 'aria-label' );
				const ariaLabelledBy = input.getAttribute( 'aria-labelledby' );
				const hasLabel = id ? document.querySelector( `label[for="${ id }"]` ) : null;
				const parentLabel = input.closest( 'label' );
				const inFieldset = input.closest( 'fieldset' )?.querySelector( 'legend' );

				if ( ! hasLabel && ! parentLabel && ! ariaLabel && ! ariaLabelledBy && ! inFieldset ) {
					problems.push( `${ input.tagName }[name=${ input.getAttribute( 'name' ) }] has no label` );
				}
			} );
			return problems;
		} );

		expect( unlabeled ).toEqual( [] );
	} );

	test( 'required fields marked with aria-required', async ( { page } ) => {
		// All required inputs should have aria-required="true".
		const missingAria = await page.evaluate( () => {
			const inputs = document.querySelectorAll(
				'.shqf-form input[required], .shqf-form select[required], .shqf-form textarea[required]'
			);
			const problems: string[] = [];
			inputs.forEach( ( input ) => {
				if ( input.getAttribute( 'aria-required' ) !== 'true' ) {
					problems.push( `${ input.tagName }[name=${ input.getAttribute( 'name' ) }] missing aria-required` );
				}
			} );
			return problems;
		} );

		expect( missingAria ).toEqual( [] );

		// Also check fieldsets with aria-required (sample picker).
		const pickerFieldsets = await page.locator( '.shqf-picker[aria-required="true"]' ).count();
		expect( pickerFieldsets ).toBeGreaterThanOrEqual( 1 );
	} );

	test( 'error messages linked via aria-describedby', async ( { page } ) => {
		// Each input with aria-describedby should point to an existing error element.
		const brokenLinks = await page.evaluate( () => {
			const inputs = document.querySelectorAll(
				'.shqf-form [aria-describedby]'
			);
			const problems: string[] = [];
			inputs.forEach( ( input ) => {
				const describedby = input.getAttribute( 'aria-describedby' );
				if ( ! describedby ) return;
				const target = document.getElementById( describedby );
				if ( ! target ) {
					problems.push( `${ input.tagName }[name=${ input.getAttribute( 'name' ) }] points to missing #${ describedby }` );
				}
			} );
			return problems;
		} );

		expect( brokenLinks ).toEqual( [] );

		// Verify at least some error containers exist.
		const errorContainers = await page.locator( '.shqf-form .shqf-error' ).count();
		expect( errorContainers ).toBeGreaterThanOrEqual( 3 );
	} );

	test( 'keyboard navigation reaches submit button', async ( { page } ) => {
		// Focus the first contact field (skips the large picker tab cycle).
		await page.locator( 'input[name*="first_name"]' ).focus();

		const focusedElements: string[] = [];
		for ( let i = 0; i < 15; i++ ) {
			await page.keyboard.press( 'Tab' );
			const info = await page.evaluate( () => {
				const el = document.activeElement;
				if ( ! el || el === document.body ) return 'body';
				const tag = el.tagName.toLowerCase();
				const type = el.getAttribute( 'type' ) || '';
				const cls = el.className || '';
				return `${ tag }[${ type }]${ cls.includes( 'shqf-button--submit' ) ? ':submit' : '' }`;
			} );
			focusedElements.push( info );
			if ( info.includes( ':submit' ) ) break;
		}

		// Should tab through multiple form elements (last_name, email, etc.).
		const formElements = focusedElements.filter(
			( el ) => el.startsWith( 'input' ) || el.startsWith( 'select' ) || el.startsWith( 'textarea' ) || el.startsWith( 'button' )
		);
		expect( formElements.length ).toBeGreaterThanOrEqual( 3 );

		// Submit button should be reachable via Tab.
		const hitSubmit = focusedElements.some( ( el ) => el.includes( ':submit' ) || el.includes( '[submit]' ) );
		expect( hitSubmit ).toBe( true );
	} );
} );
