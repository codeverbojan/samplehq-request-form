import {
	FIELD_CATEGORIES,
	ALL_FIELD_TYPES,
	CONDITION_OPERATORS,
	HISTORY_LIMIT,
} from './constants';

describe( 'FIELD_CATEGORIES', () => {
	it( 'is a non-empty array', () => {
		expect( Array.isArray( FIELD_CATEGORIES ) ).toBe( true );
		expect( FIELD_CATEGORIES.length ).toBeGreaterThan( 0 );
	} );

	it( 'each category has a label and fields array', () => {
		for ( const category of FIELD_CATEGORIES ) {
			expect( typeof category.label ).toBe( 'string' );
			expect( Array.isArray( category.fields ) ).toBe( true );
			expect( category.fields.length ).toBeGreaterThan( 0 );
		}
	} );

	it( 'each field has type, label, and icon', () => {
		for ( const category of FIELD_CATEGORIES ) {
			for ( const field of category.fields ) {
				expect( typeof field.type ).toBe( 'string' );
				expect( typeof field.label ).toBe( 'string' );
				expect( field.icon ).toBeTruthy();
			}
		}
	} );

	it( 'contains all 5 expected categories', () => {
		const labels = FIELD_CATEGORIES.map( ( c ) => c.label );
		expect( labels ).toEqual( [
			'Standard',
			'Choice',
			'Advanced',
			'Content',
			'Layout',
		] );
	} );
} );

describe( 'ALL_FIELD_TYPES', () => {
	it( 'is a flat array of all fields from all categories', () => {
		const expectedCount = FIELD_CATEGORIES.reduce(
			( sum, c ) => sum + c.fields.length,
			0
		);
		expect( ALL_FIELD_TYPES.length ).toBe( expectedCount );
	} );

	it( 'has no duplicate field types', () => {
		const types = ALL_FIELD_TYPES.map( ( f ) => f.type );
		const unique = [ ...new Set( types ) ];
		expect( types ).toEqual( unique );
	} );

	it( 'contains all 18 field types in order', () => {
		const types = ALL_FIELD_TYPES.map( ( f ) => f.type );
		expect( types ).toEqual( [
			'text',
			'email',
			'phone',
			'textarea',
			'number',
			'name',
			'select',
			'radio',
			'checkbox',
			'date',
			'url',
			'file_upload',
			'address',
			'hidden',
			'html',
			'consent',
			'sample_picker',
			'row',
		] );
	} );
} );

describe( 'CONDITION_OPERATORS', () => {
	it( 'is a non-empty array', () => {
		expect( Array.isArray( CONDITION_OPERATORS ) ).toBe( true );
		expect( CONDITION_OPERATORS.length ).toBe( 6 );
	} );

	it( 'each operator has value and label', () => {
		for ( const op of CONDITION_OPERATORS ) {
			expect( typeof op.value ).toBe( 'string' );
			expect( typeof op.label ).toBe( 'string' );
		}
	} );

	it( 'contains the expected operators', () => {
		const values = CONDITION_OPERATORS.map( ( op ) => op.value );
		expect( values ).toEqual( [
			'equals',
			'not_equals',
			'contains',
			'not_contains',
			'empty',
			'not_empty',
		] );
	} );
} );

describe( 'HISTORY_LIMIT', () => {
	it( 'is a positive number', () => {
		expect( typeof HISTORY_LIMIT ).toBe( 'number' );
		expect( HISTORY_LIMIT ).toBeGreaterThan( 0 );
	} );

	it( 'equals 30', () => {
		expect( HISTORY_LIMIT ).toBe( 30 );
	} );
} );
