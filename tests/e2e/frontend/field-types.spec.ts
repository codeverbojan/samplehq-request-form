import { test, expect } from '@playwright/test';
import { loadFixtures } from '../load-fixtures';

let ALL_FIELDS_URL: string;

test.describe( 'All field types', () => {
	test.beforeAll( () => {
		ALL_FIELDS_URL = loadFixtures().pageUrls.allFields;
	} );

	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
		await page.goto( ALL_FIELDS_URL );
		await expect( page.locator( '.shqf-form' ) ).toBeVisible();
	} );

	test( 'text field renders and accepts input', async ( { page } ) => {
		const field = page.locator( '.shqf-field--text' );
		await expect( field ).toBeVisible();
		const input = field.locator( 'input[type="text"]' );
		await expect( input ).toBeVisible();
		await input.fill( 'Hello World' );
		await expect( input ).toHaveValue( 'Hello World' );
		// Required attribute present.
		await expect( input ).toHaveAttribute( 'required', '' );
	} );

	test( 'email field renders, validates format, rejects invalid', async ( {
		page,
	} ) => {
		const field = page.locator( '.shqf-field--email' );
		await expect( field ).toBeVisible();
		const input = field.locator( 'input[type="email"]' );
		await expect( input ).toBeVisible();
		await expect( input ).toHaveAttribute( 'required', '' );
		// Invalid email triggers error on submit.
		await input.fill( 'notanemail' );
		await page.locator( '.shqf-button--submit' ).click();
		const errorEl = field.locator( '.shqf-error' );
		await expect( errorEl ).toBeVisible();
		await expect( errorEl ).toContainText( 'valid email' );
		// Valid email clears the error on next submit attempt.
		await input.fill( 'valid@email.com' );
		await expect( input ).toHaveValue( 'valid@email.com' );
	} );

	test( 'phone field renders tel input', async ( { page } ) => {
		const field = page.locator( '.shqf-field--phone' );
		await expect( field ).toBeVisible();
		const input = field.locator( 'input[type="tel"]' );
		await expect( input ).toBeVisible();
		await input.fill( '+1-555-123-4567' );
		await expect( input ).toHaveValue( '+1-555-123-4567' );
	} );

	test( 'textarea field renders and accepts multi-line', async ( {
		page,
	} ) => {
		const field = page.locator( '.shqf-field--textarea' );
		await expect( field ).toBeVisible();
		const textarea = field.locator( 'textarea' );
		await expect( textarea ).toBeVisible();
		await textarea.fill( 'Line 1\nLine 2' );
		await expect( textarea ).toHaveValue( 'Line 1\nLine 2' );
	} );

	test( 'number field renders with min/max and rejects out-of-range', async ( {
		page,
	} ) => {
		const field = page.locator( '.shqf-field--number' );
		await expect( field ).toBeVisible();
		const input = field.locator( 'input[type="number"]' );
		await expect( input ).toBeVisible();
		await expect( input ).toHaveAttribute( 'min', '1' );
		await expect( input ).toHaveAttribute( 'max', '100' );
		// Out of range triggers error.
		await input.fill( '0' );
		await page.locator( '.shqf-button--submit' ).click();
		const errorEl = field.locator( '.shqf-error' );
		await expect( errorEl ).toBeVisible();
		await expect( errorEl ).toContainText( 'at least' );
		// Valid value accepted.
		await input.fill( '42' );
		await expect( input ).toHaveValue( '42' );
	} );

	test( 'name field renders first + last inputs', async ( { page } ) => {
		const field = page.locator( '.shqf-field--name' );
		await expect( field ).toBeVisible();
		const first = field.locator( 'input[name*="first_name"]' );
		const last = field.locator( 'input[name*="last_name"]' );
		await expect( first ).toBeVisible();
		await expect( last ).toBeVisible();
		await first.fill( 'Jane' );
		await last.fill( 'Doe' );
		await expect( first ).toHaveValue( 'Jane' );
		await expect( last ).toHaveValue( 'Doe' );
	} );

	test( 'select field renders with options', async ( { page } ) => {
		const field = page.locator( '.shqf-field--select' );
		await expect( field ).toBeVisible();
		const select = field.locator( 'select' );
		await expect( select ).toBeVisible();
		// Placeholder + 3 configured options = 4 total.
		const options = select.locator( 'option' );
		await expect( options ).toHaveCount( 4 );
		await select.selectOption( 'engineering' );
		await expect( select ).toHaveValue( 'engineering' );
	} );

	test( 'radio field renders buttons', async ( { page } ) => {
		const field = page.locator( '.shqf-field--radio' );
		await expect( field ).toBeVisible();
		const radios = field.locator( 'input[type="radio"]' );
		await expect( radios ).toHaveCount( 3 );
		// Click "Medium".
		await field.locator( 'input[value="medium"]' ).check();
		await expect( field.locator( 'input[value="medium"]' ) ).toBeChecked();
		// Others unchecked.
		await expect( field.locator( 'input[value="low"]' ) ).not.toBeChecked();
	} );

	test( 'checkbox field renders with options', async ( { page } ) => {
		const field = page.locator( '.shqf-field--checkbox' );
		await expect( field ).toBeVisible();
		const checkboxes = field.locator( 'input[type="checkbox"]' );
		await expect( checkboxes ).toHaveCount( 3 );
		// Check two options.
		await checkboxes.nth( 0 ).check();
		await checkboxes.nth( 2 ).check();
		await expect( checkboxes.nth( 0 ) ).toBeChecked();
		await expect( checkboxes.nth( 1 ) ).not.toBeChecked();
		await expect( checkboxes.nth( 2 ) ).toBeChecked();
	} );

	test( 'date field renders date input', async ( { page } ) => {
		const field = page.locator( '.shqf-field--date' );
		await expect( field ).toBeVisible();
		const input = field.locator( 'input[type="date"]' );
		await expect( input ).toBeVisible();
		await input.fill( '2026-12-25' );
		await expect( input ).toHaveValue( '2026-12-25' );
	} );

	test( 'url field renders, rejects invalid, accepts valid', async ( {
		page,
	} ) => {
		const field = page.locator( '.shqf-field--url' );
		await expect( field ).toBeVisible();
		const input = field.locator( 'input[type="url"]' );
		await expect( input ).toBeVisible();
		// Invalid URL triggers error.
		await input.fill( 'notaurl' );
		await page.locator( '.shqf-button--submit' ).click();
		const errorEl = field.locator( '.shqf-error' );
		await expect( errorEl ).toBeVisible();
		await expect( errorEl ).toContainText( 'valid URL' );
		// Valid URL accepted.
		await input.fill( 'https://example.com' );
		await expect( input ).toHaveValue( 'https://example.com' );
	} );

	test( 'address field renders 5 sub-fields', async ( { page } ) => {
		const field = page.locator( '.shqf-field--address' );
		await expect( field ).toBeVisible();
		const inputs = field.locator( 'input[type="text"]' );
		await expect( inputs ).toHaveCount( 5 );
		// Fill address fields.
		await field.locator( 'input[name*="street"]' ).fill( '123 Main St' );
		await field.locator( 'input[name*="city"]' ).fill( 'Springfield' );
		await field.locator( 'input[name*="state"]' ).fill( 'IL' );
		await field.locator( 'input[name*="zip"]' ).fill( '62701' );
		await field.locator( 'input[name*="country"]' ).fill( 'US' );
		await expect( field.locator( 'input[name*="city"]' ) ).toHaveValue(
			'Springfield'
		);
	} );

	test( 'hidden field is in DOM but not visible', async ( { page } ) => {
		const hidden = page.locator( 'input[name="shqf_fields[source_ref]"]' );
		await expect( hidden ).toHaveCount( 1 );
		await expect( hidden ).toHaveAttribute( 'type', 'hidden' );
		await expect( hidden ).toHaveValue( 'e2e-test' );
		// No visible wrapper for hidden fields.
		await expect( hidden ).not.toBeVisible();
	} );

	test( 'html field renders content block', async ( { page } ) => {
		const field = page.locator( '.shqf-field--html' );
		await expect( field ).toBeVisible();
		await expect( field ).toContainText(
			'This is an informational block.'
		);
		// No input elements inside HTML field.
		const inputs = field.locator( 'input, select, textarea' );
		await expect( inputs ).toHaveCount( 0 );
	} );

	test( 'consent field required to submit', async ( { page } ) => {
		const field = page.locator( '.shqf-field--consent' );
		await expect( field ).toBeVisible();
		const checkbox = field.locator( 'input[type="checkbox"]' );
		await expect( checkbox ).toBeVisible();
		await expect( field ).toContainText(
			'I agree to the terms and conditions.'
		);
		// Submit without consent triggers error.
		await page.locator( '.shqf-button--submit' ).click();
		const errorEl = field.locator( '.shqf-error' );
		await expect( errorEl ).toBeVisible();
		await expect( errorEl ).toContainText( 'required' );
		// Check consent clears error on next validation.
		await checkbox.check();
		await expect( checkbox ).toBeChecked();
	} );

	test( 'file upload field renders with allowed types info', async ( {
		page,
	} ) => {
		const field = page.locator( '.shqf-field--file_upload' );
		await expect( field ).toBeVisible();
		const input = field.locator( 'input[type="file"]' );
		await expect( input ).toBeVisible();
		// Accept attribute should list allowed MIME types.
		await expect( input ).toHaveAttribute( 'accept', /image\/png/ );
		// Info text shows allowed types and max size.
		const info = field.locator( '.shqf-file-info' );
		await expect( info ).toBeVisible();
		await expect( info ).toContainText( 'PNG' );
		await expect( info ).toContainText( '2 MB' );
	} );
} );
