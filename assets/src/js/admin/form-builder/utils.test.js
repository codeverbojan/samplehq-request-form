import { slugify, getFieldLabel } from './utils';

describe( 'slugify', () => {
	it( 'lowercases and replaces spaces with underscores', () => {
		expect( slugify( 'Hello World' ) ).toBe( 'hello_world' );
	} );

	it( 'removes non-alphanumeric characters', () => {
		expect( slugify( 'field@#$name!' ) ).toBe( 'field_name' );
	} );

	it( 'trims leading and trailing underscores', () => {
		expect( slugify( '___test___' ) ).toBe( 'test' );
	} );

	it( 'collapses consecutive separators', () => {
		expect( slugify( 'a   b   c' ) ).toBe( 'a_b_c' );
	} );

	it( 'truncates to 50 characters', () => {
		const long = 'a'.repeat( 60 );
		expect( slugify( long ) ).toHaveLength( 50 );
	} );

	it( 'handles empty string', () => {
		expect( slugify( '' ) ).toBe( '' );
	} );

	it( 'handles unicode characters', () => {
		expect( slugify( 'café résumé' ) ).toBe( 'caf_r_sum' );
	} );

	it( 'handles string that is only special characters', () => {
		expect( slugify( '@#$%^&*' ) ).toBe( '' );
	} );

	it( 'handles numbers', () => {
		expect( slugify( 'field 123' ) ).toBe( 'field_123' );
	} );
} );

describe( 'getFieldLabel', () => {
	it( 'returns the label for known field types', () => {
		expect( getFieldLabel( 'text' ) ).toBe( 'Text' );
		expect( getFieldLabel( 'email' ) ).toBe( 'Email' );
		expect( getFieldLabel( 'sample_picker' ) ).toBe( 'Sample Picker' );
	} );

	it( 'returns title-cased fallback for unknown types', () => {
		expect( getFieldLabel( 'custom_widget' ) ).toBe( 'Custom Widget' );
	} );

	it( 'handles single-word unknown type', () => {
		expect( getFieldLabel( 'something' ) ).toBe( 'Something' );
	} );

	it( 'handles already-capitalized unknown type', () => {
		expect( getFieldLabel( 'MyField' ) ).toBe( 'MyField' );
	} );
} );
