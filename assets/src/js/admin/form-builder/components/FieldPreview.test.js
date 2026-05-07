import { render } from '@testing-library/react';
import FieldPreview from './FieldPreview';

describe( 'FieldPreview', () => {
	it( 'renders text input for text type', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'text', placeholder: 'Name' } } />
		);
		const input = container.querySelector( 'input[type="text"]' );
		expect( input ).toBeTruthy();
		expect( input.placeholder ).toBe( 'Name' );
		expect( input.readOnly ).toBe( true );
	} );

	it( 'falls back to field type as placeholder', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'email' } } />
		);
		const input = container.querySelector( 'input' );
		expect( input.placeholder ).toBe( 'email' );
	} );

	it.each( [ 'text', 'email', 'phone', 'date', 'url', 'number' ] )(
		'renders single input for %s',
		( type ) => {
			const { container } = render( <FieldPreview field={ { type } } /> );
			expect(
				container.querySelector( '.shqf-builder-field-preview input' )
			).toBeTruthy();
		}
	);

	it( 'renders textarea for textarea type', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'textarea' } } />
		);
		expect( container.querySelector( 'textarea' ) ).toBeTruthy();
	} );

	it( 'renders select with default placeholder', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'select' } } />
		);
		const option = container.querySelector( 'select option' );
		expect( option.textContent ).toBe( 'Select an option…' );
	} );

	it( 'renders select with custom placeholder', () => {
		const { container } = render(
			<FieldPreview
				field={ { type: 'select', placeholder: 'Pick one' } }
			/>
		);
		const option = container.querySelector( 'select option' );
		expect( option.textContent ).toBe( 'Pick one' );
	} );

	it( 'renders radio options', () => {
		const field = {
			type: 'radio',
			options: [
				{ label: 'A', value: 'a' },
				{ label: 'B', value: 'b' },
			],
		};
		const { container } = render( <FieldPreview field={ field } /> );
		const choices = container.querySelectorAll( '.shqf-preview-choice' );
		expect( choices ).toHaveLength( 2 );
	} );

	it( 'limits radio options to 3', () => {
		const field = {
			type: 'radio',
			options: [
				{ label: 'A', value: 'a' },
				{ label: 'B', value: 'b' },
				{ label: 'C', value: 'c' },
				{ label: 'D', value: 'd' },
			],
		};
		const { container } = render( <FieldPreview field={ field } /> );
		const choices = container.querySelectorAll( '.shqf-preview-choice' );
		expect( choices ).toHaveLength( 3 );
	} );

	it( 'renders radio with no options without crashing', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'radio' } } />
		);
		const choices = container.querySelectorAll( '.shqf-preview-choice' );
		expect( choices ).toHaveLength( 0 );
	} );

	it( 'renders checkbox options with check style', () => {
		const field = {
			type: 'checkbox',
			options: [
				{ label: 'X', value: 'x' },
				{ label: 'Y', value: 'y' },
			],
		};
		const { container } = render( <FieldPreview field={ field } /> );
		const choices = container.querySelectorAll( '.shqf-preview-choice' );
		expect( choices ).toHaveLength( 2 );
		expect(
			container.querySelector( '.shqf-preview-choice-check' )
		).toBeTruthy();
	} );

	it( 'limits checkbox options to 3', () => {
		const field = {
			type: 'checkbox',
			options: [
				{ label: 'A', value: 'a' },
				{ label: 'B', value: 'b' },
				{ label: 'C', value: 'c' },
				{ label: 'D', value: 'd' },
			],
		};
		const { container } = render( <FieldPreview field={ field } /> );
		const choices = container.querySelectorAll( '.shqf-preview-choice' );
		expect( choices ).toHaveLength( 3 );
	} );

	it( 'renders checkbox with no options without crashing', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'checkbox' } } />
		);
		const choices = container.querySelectorAll( '.shqf-preview-choice' );
		expect( choices ).toHaveLength( 0 );
	} );

	it( 'renders name with first/last inputs', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'name' } } />
		);
		const inputs = container.querySelectorAll( '.shqf-preview-half input' );
		expect( inputs ).toHaveLength( 2 );
	} );

	it( 'renders address with 4 inputs', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'address' } } />
		);
		const inputs = container.querySelectorAll(
			'.shqf-preview-address input'
		);
		expect( inputs ).toHaveLength( 4 );
	} );

	it( 'renders file upload text', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'file_upload' } } />
		);
		expect( container.querySelector( '.shqf-preview-file' ) ).toBeTruthy();
	} );

	it( 'strips HTML tags from html content', () => {
		const field = {
			type: 'html',
			content: '<p>Hello <strong>world</strong></p>',
		};
		const { container } = render( <FieldPreview field={ field } /> );
		expect(
			container.querySelector( '.shqf-preview-html' ).textContent
		).toBe( 'Hello world' );
	} );

	it( 'shows fallback text when html has no content', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'html' } } />
		);
		expect(
			container.querySelector( '.shqf-preview-html' ).textContent
		).toBe( 'HTML content' );
	} );

	it( 'truncates html content to 80 chars', () => {
		const field = {
			type: 'html',
			content: '<p>' + 'a'.repeat( 100 ) + '</p>',
		};
		const { container } = render( <FieldPreview field={ field } /> );
		expect(
			container.querySelector( '.shqf-preview-html' ).textContent.length
		).toBeLessThanOrEqual( 80 );
	} );

	it( 'renders consent checkbox with text', () => {
		const field = {
			type: 'consent',
			consent_text: 'I agree to terms',
		};
		const { container } = render( <FieldPreview field={ field } /> );
		expect(
			container.querySelector( '.shqf-preview-consent' ).textContent
		).toContain( 'I agree to terms' );
	} );

	it( 'falls back to label when consent_text is absent', () => {
		const field = { type: 'consent', label: 'My Label' };
		const { container } = render( <FieldPreview field={ field } /> );
		expect(
			container.querySelector( '.shqf-preview-consent' ).textContent
		).toContain( 'My Label' );
	} );

	it( 'renders hidden field with default value', () => {
		const field = { type: 'hidden', default_value: 'secret' };
		const { container } = render( <FieldPreview field={ field } /> );
		expect(
			container.querySelector( '.shqf-preview-hidden' ).textContent
		).toContain( 'secret' );
	} );

	it( 'shows (empty) when hidden field has no default_value', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'hidden' } } />
		);
		expect(
			container.querySelector( '.shqf-preview-hidden' ).textContent
		).toContain( '(empty)' );
	} );

	it( 'renders sample_picker grid layout', () => {
		const field = {
			type: 'sample_picker',
			config: { layout: 'grid', max_selections: 5 },
		};
		const { container } = render( <FieldPreview field={ field } /> );
		expect(
			container.querySelector( '.shqf-preview-picker-grid' )
		).toBeTruthy();
		expect(
			container.querySelector( '.shqf-preview-picker-header' ).textContent
		).toContain( '5 max' );
	} );

	it( 'renders sample_picker list layout', () => {
		const field = {
			type: 'sample_picker',
			config: { layout: 'list' },
		};
		const { container } = render( <FieldPreview field={ field } /> );
		expect(
			container.querySelector( '.shqf-preview-picker-list' )
		).toBeTruthy();
	} );

	it( 'defaults to grid when sample_picker has no config', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'sample_picker' } } />
		);
		expect(
			container.querySelector( '.shqf-preview-picker-grid' )
		).toBeTruthy();
	} );

	it( 'renders row with columns', () => {
		const field = {
			type: 'row',
			columns: [
				{
					width: '1fr',
					fields: [ { id: 'f1', type: 'text', label: 'Name' } ],
				},
				{ width: '1fr', fields: [] },
			],
		};
		const { container } = render( <FieldPreview field={ field } /> );
		const cols = container.querySelectorAll( '.shqf-preview-row-col' );
		expect( cols ).toHaveLength( 2 );
		expect( cols[ 0 ].textContent ).toContain( 'Name' );
		expect( cols[ 1 ].textContent ).toContain( 'Drop fields here' );
	} );

	it( 'renders row with empty columns array', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'row', columns: [] } } />
		);
		expect( container.querySelector( '.shqf-preview-row' ) ).toBeTruthy();
		expect(
			container.querySelectorAll( '.shqf-preview-row-col' )
		).toHaveLength( 0 );
	} );

	it( 'falls back to field type when row child has no label', () => {
		const field = {
			type: 'row',
			columns: [
				{
					width: '1fr',
					fields: [ { id: 'f1', type: 'email' } ],
				},
			],
		};
		const { container } = render( <FieldPreview field={ field } /> );
		expect( container.textContent ).toContain( 'email' );
	} );

	it( 'returns null for unknown type', () => {
		const { container } = render(
			<FieldPreview field={ { type: 'unknown_thing' } } />
		);
		expect( container.innerHTML ).toBe( '' );
	} );
} );
