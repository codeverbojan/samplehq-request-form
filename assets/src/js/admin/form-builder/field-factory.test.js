import { generateFieldId, createField } from './field-factory';

describe( 'generateFieldId', () => {
	it( 'returns a string starting with "f_"', () => {
		const id = generateFieldId();
		expect( id ).toMatch( /^f_[a-z0-9]{7}$/ );
	} );

	it( 'generates unique IDs', () => {
		const ids = new Set(
			Array.from( { length: 100 }, () => generateFieldId() )
		);
		expect( ids.size ).toBe( 100 );
	} );
} );

describe( 'createField', () => {
	describe( 'row type', () => {
		it( 'returns a row with 2 columns', () => {
			const field = createField( 'row' );
			expect( field.type ).toBe( 'row' );
			expect( field.id ).toMatch( /^row_[a-z0-9]{7}$/ );
			expect( field.enabled ).toBe( true );
			expect( field.columns ).toHaveLength( 2 );
			expect( field.columns[ 0 ] ).toEqual( {
				width: '1fr',
				fields: [],
			} );
			expect( field.columns[ 1 ] ).toEqual( {
				width: '1fr',
				fields: [],
			} );
		} );

		it( 'does not have standard field properties', () => {
			const field = createField( 'row' );
			expect( field.key ).toBeUndefined();
			expect( field.label ).toBeUndefined();
			expect( field.placeholder ).toBeUndefined();
		} );
	} );

	describe( 'standard fields', () => {
		it( 'creates a text field with base properties', () => {
			const field = createField( 'text' );
			expect( field.type ).toBe( 'text' );
			expect( field.id ).toMatch( /^f_/ );
			expect( field.key ).toMatch( /^text_\d+$/ );
			expect( field.label ).toBe( 'Text' );
			expect( field.placeholder ).toBe( '' );
			expect( field.required ).toBe( false );
			expect( field.enabled ).toBe( true );
		} );

		it( 'sets required=true for email', () => {
			expect( createField( 'email' ).required ).toBe( true );
		} );

		it( 'sets required=true for name', () => {
			expect( createField( 'name' ).required ).toBe( true );
		} );

		it( 'sets required=true for sample_picker', () => {
			expect( createField( 'sample_picker' ).required ).toBe( true );
		} );

		it( 'sets required=false for text', () => {
			expect( createField( 'text' ).required ).toBe( false );
		} );
	} );

	describe( 'choice fields', () => {
		it.each( [ 'select', 'radio', 'checkbox' ] )(
			'adds 3 default options with labels and values for %s',
			( type ) => {
				const field = createField( type );
				expect( field.options ).toHaveLength( 3 );
				expect( field.options[ 0 ] ).toEqual( {
					label: 'Option 1',
					value: 'option_1',
				} );
				expect( field.options[ 1 ] ).toEqual( {
					label: 'Option 2',
					value: 'option_2',
				} );
				expect( field.options[ 2 ] ).toEqual( {
					label: 'Option 3',
					value: 'option_3',
				} );
			}
		);
	} );

	describe( 'non-choice fields', () => {
		it.each( [
			'text',
			'date',
			'url',
			'file_upload',
			'address',
			'phone',
			'number',
			'textarea',
		] )(
			'%s does not have options, consent_text, content, or default_value',
			( type ) => {
				const field = createField( type );
				expect( field.options ).toBeUndefined();
				expect( field.consent_text ).toBeUndefined();
				expect( field.content ).toBeUndefined();
				expect( field.default_value ).toBeUndefined();
			}
		);
	} );

	describe( 'consent field', () => {
		it( 'adds consent_text and forces required', () => {
			const field = createField( 'consent' );
			expect( field.consent_text ).toBe(
				'I agree to the privacy policy.'
			);
			expect( field.required ).toBe( true );
		} );
	} );

	describe( 'html field', () => {
		it( 'adds default content', () => {
			const field = createField( 'html' );
			expect( field.content ).toBe( '<p>Enter your content here.</p>' );
		} );
	} );

	describe( 'hidden field', () => {
		it( 'adds empty default_value', () => {
			const field = createField( 'hidden' );
			expect( field.default_value ).toBe( '' );
		} );
	} );

	describe( 'unknown type', () => {
		it( 'produces a valid base field with type as label fallback', () => {
			const field = createField( 'unknown_widget' );
			expect( field.type ).toBe( 'unknown_widget' );
			expect( field.label ).toBe( 'unknown_widget' );
			expect( field.id ).toMatch( /^f_[a-z0-9]{7}$/ );
			expect( field.key ).toMatch( /^unknown_widget_\d+$/ );
			expect( field.placeholder ).toBe( '' );
			expect( field.required ).toBe( false );
			expect( field.enabled ).toBe( true );
		} );
	} );
} );
