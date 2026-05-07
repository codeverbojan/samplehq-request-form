import {
	flattenFields,
	findFieldInTree,
	removeFieldFromTree,
	updateFieldInTree,
	insertBeforeInTree,
	insertAfterInTree,
	insertIntoColumn,
	findFieldLocation,
	reorderInColumn,
} from './field-tree';

const makeField = ( id, type = 'text' ) => ( { id, type } );

const makeRow = ( id, columns ) => ( {
	id,
	type: 'row',
	columns: columns.map( ( fields ) => ( { width: '1fr', fields } ) ),
} );

const sampleTree = () => [
	makeField( 'f1' ),
	makeRow( 'row1', [
		[ makeField( 'f2' ), makeField( 'f3' ) ],
		[ makeField( 'f4' ) ],
	] ),
	makeField( 'f5' ),
];

describe( 'flattenFields', () => {
	it( 'returns leaf fields from flat array', () => {
		const fields = [ makeField( 'a' ), makeField( 'b' ) ];
		expect( flattenFields( fields ) ).toEqual( fields );
	} );

	it( 'extracts fields from nested rows', () => {
		const tree = sampleTree();
		const flat = flattenFields( tree );
		expect( flat.map( ( f ) => f.id ) ).toEqual( [
			'f1',
			'f2',
			'f3',
			'f4',
			'f5',
		] );
	} );

	it( 'skips row containers themselves', () => {
		const tree = sampleTree();
		const flat = flattenFields( tree );
		expect( flat.find( ( f ) => f.id === 'row1' ) ).toBeUndefined();
	} );

	it( 'handles empty array', () => {
		expect( flattenFields( [] ) ).toEqual( [] );
	} );

	it( 'handles row with empty columns', () => {
		const tree = [ makeRow( 'r1', [ [], [] ] ) ];
		expect( flattenFields( tree ) ).toEqual( [] );
	} );
} );

describe( 'findFieldInTree', () => {
	it( 'finds a top-level field', () => {
		const tree = sampleTree();
		expect( findFieldInTree( tree, 'f1' ) ).toEqual( makeField( 'f1' ) );
	} );

	it( 'finds a nested field', () => {
		const tree = sampleTree();
		expect( findFieldInTree( tree, 'f3' ) ).toEqual( makeField( 'f3' ) );
	} );

	it( 'finds a row itself', () => {
		const tree = sampleTree();
		const row = findFieldInTree( tree, 'row1' );
		expect( row.type ).toBe( 'row' );
		expect( row.id ).toBe( 'row1' );
	} );

	it( 'returns null for non-existent ID', () => {
		expect( findFieldInTree( sampleTree(), 'nope' ) ).toBeNull();
	} );

	it( 'returns null for empty array', () => {
		expect( findFieldInTree( [], 'f1' ) ).toBeNull();
	} );
} );

describe( 'removeFieldFromTree', () => {
	it( 'removes a top-level field', () => {
		const result = removeFieldFromTree( sampleTree(), 'f1' );
		expect( result[ 0 ].id ).toBe( 'row1' );
		expect( result ).toHaveLength( 2 );
	} );

	it( 'removes a whole row and its children', () => {
		const result = removeFieldFromTree( sampleTree(), 'row1' );
		expect( result ).toHaveLength( 2 );
		expect( result[ 0 ].id ).toBe( 'f1' );
		expect( result[ 1 ].id ).toBe( 'f5' );
	} );

	it( 'removes a nested field', () => {
		const result = removeFieldFromTree( sampleTree(), 'f2' );
		const row = result.find( ( f ) => f.id === 'row1' );
		expect( row.columns[ 0 ].fields ).toHaveLength( 1 );
		expect( row.columns[ 0 ].fields[ 0 ].id ).toBe( 'f3' );
	} );

	it( 'does not mutate the original', () => {
		const tree = sampleTree();
		const original = JSON.stringify( tree );
		removeFieldFromTree( tree, 'f2' );
		expect( JSON.stringify( tree ) ).toBe( original );
	} );

	it( 'returns unchanged array for non-existent ID', () => {
		const tree = sampleTree();
		const result = removeFieldFromTree( tree, 'nope' );
		expect( flattenFields( result ).map( ( f ) => f.id ) ).toEqual(
			flattenFields( tree ).map( ( f ) => f.id )
		);
	} );
} );

describe( 'updateFieldInTree', () => {
	it( 'updates a top-level field', () => {
		const updated = { id: 'f1', type: 'email', label: 'Updated' };
		const result = updateFieldInTree( sampleTree(), updated );
		expect( result[ 0 ] ).toEqual( updated );
	} );

	it( 'updates a nested field', () => {
		const updated = { id: 'f3', type: 'number', label: 'Three' };
		const result = updateFieldInTree( sampleTree(), updated );
		const row = result.find( ( f ) => f.id === 'row1' );
		expect( row.columns[ 0 ].fields[ 1 ] ).toEqual( updated );
	} );

	it( 'does not mutate the original', () => {
		const tree = sampleTree();
		const original = JSON.stringify( tree );
		updateFieldInTree( tree, { id: 'f1', type: 'email' } );
		expect( JSON.stringify( tree ) ).toBe( original );
	} );
} );

describe( 'insertBeforeInTree', () => {
	const newField = makeField( 'new1' );

	it( 'inserts before the first element', () => {
		const result = insertBeforeInTree( sampleTree(), 'f1', newField );
		expect( result[ 0 ].id ).toBe( 'new1' );
		expect( result[ 1 ].id ).toBe( 'f1' );
	} );

	it( 'inserts before a top-level field', () => {
		const result = insertBeforeInTree( sampleTree(), 'f5', newField );
		const ids = result
			.filter( ( f ) => f.type !== 'row' )
			.map( ( f ) => f.id );
		expect( ids ).toEqual( [ 'f1', 'new1', 'f5' ] );
	} );

	it( 'inserts before a nested field', () => {
		const result = insertBeforeInTree( sampleTree(), 'f3', newField );
		const row = result.find( ( f ) => f.id === 'row1' );
		const col0ids = row.columns[ 0 ].fields.map( ( f ) => f.id );
		expect( col0ids ).toEqual( [ 'f2', 'new1', 'f3' ] );
	} );

	it( 'returns unchanged tree for non-existent target', () => {
		const tree = sampleTree();
		const result = insertBeforeInTree( tree, 'nope', newField );
		expect( flattenFields( result ).map( ( f ) => f.id ) ).toEqual(
			flattenFields( tree ).map( ( f ) => f.id )
		);
	} );

	it( 'does not mutate the original', () => {
		const tree = sampleTree();
		const original = JSON.stringify( tree );
		insertBeforeInTree( tree, 'f3', newField );
		expect( JSON.stringify( tree ) ).toBe( original );
	} );
} );

describe( 'insertAfterInTree', () => {
	const newField = makeField( 'new2' );

	it( 'inserts after a top-level field', () => {
		const result = insertAfterInTree( sampleTree(), 'f1', newField );
		expect( result[ 0 ].id ).toBe( 'f1' );
		expect( result[ 1 ].id ).toBe( 'new2' );
	} );

	it( 'inserts after the last element', () => {
		const result = insertAfterInTree( sampleTree(), 'f5', newField );
		expect( result[ result.length - 1 ].id ).toBe( 'new2' );
		expect( result[ result.length - 2 ].id ).toBe( 'f5' );
	} );

	it( 'inserts after a nested field', () => {
		const result = insertAfterInTree( sampleTree(), 'f2', newField );
		const row = result.find( ( f ) => f.id === 'row1' );
		const col0ids = row.columns[ 0 ].fields.map( ( f ) => f.id );
		expect( col0ids ).toEqual( [ 'f2', 'new2', 'f3' ] );
	} );

	it( 'returns unchanged tree for non-existent target', () => {
		const tree = sampleTree();
		const result = insertAfterInTree( tree, 'nope', newField );
		expect( flattenFields( result ).map( ( f ) => f.id ) ).toEqual(
			flattenFields( tree ).map( ( f ) => f.id )
		);
	} );

	it( 'does not mutate the original', () => {
		const tree = sampleTree();
		const original = JSON.stringify( tree );
		insertAfterInTree( tree, 'f2', newField );
		expect( JSON.stringify( tree ) ).toBe( original );
	} );
} );

describe( 'insertIntoColumn', () => {
	const newField = makeField( 'col_new' );

	it( 'appends to the specified column', () => {
		const result = insertIntoColumn( sampleTree(), 'row1', 1, newField );
		const row = result.find( ( f ) => f.id === 'row1' );
		expect( row.columns[ 1 ].fields ).toHaveLength( 2 );
		expect( row.columns[ 1 ].fields[ 1 ].id ).toBe( 'col_new' );
	} );

	it( 'does not affect other columns', () => {
		const result = insertIntoColumn( sampleTree(), 'row1', 1, newField );
		const row = result.find( ( f ) => f.id === 'row1' );
		expect( row.columns[ 0 ].fields ).toHaveLength( 2 );
	} );

	it( 'does nothing for non-matching row ID', () => {
		const tree = sampleTree();
		const result = insertIntoColumn( tree, 'nope', 0, newField );
		expect( JSON.stringify( result ) ).toBe( JSON.stringify( tree ) );
	} );

	it( 'does not mutate the original', () => {
		const tree = sampleTree();
		const original = JSON.stringify( tree );
		insertIntoColumn( tree, 'row1', 0, newField );
		expect( JSON.stringify( tree ) ).toBe( original );
	} );
} );

describe( 'findFieldLocation', () => {
	it( 'finds a top-level field', () => {
		const loc = findFieldLocation( sampleTree(), 'f1' );
		expect( loc ).toEqual( { container: 'top', index: 0 } );
	} );

	it( 'finds the last top-level field', () => {
		const loc = findFieldLocation( sampleTree(), 'f5' );
		expect( loc ).toEqual( { container: 'top', index: 2 } );
	} );

	it( 'finds a nested field with row/col info', () => {
		const loc = findFieldLocation( sampleTree(), 'f3' );
		expect( loc ).toEqual( {
			container: 'col_row1_0',
			rowId: 'row1',
			colIndex: 0,
			index: 1,
		} );
	} );

	it( 'finds a field in the second column', () => {
		const loc = findFieldLocation( sampleTree(), 'f4' );
		expect( loc ).toEqual( {
			container: 'col_row1_1',
			rowId: 'row1',
			colIndex: 1,
			index: 0,
		} );
	} );

	it( 'returns null for non-existent ID', () => {
		expect( findFieldLocation( sampleTree(), 'nope' ) ).toBeNull();
	} );
} );

describe( 'reorderInColumn', () => {
	it( 'moves a field within a column', () => {
		const result = reorderInColumn( sampleTree(), 'row1', 0, 0, 1 );
		const row = result.find( ( f ) => f.id === 'row1' );
		const col0ids = row.columns[ 0 ].fields.map( ( f ) => f.id );
		expect( col0ids ).toEqual( [ 'f3', 'f2' ] );
	} );

	it( 'does not affect other columns', () => {
		const result = reorderInColumn( sampleTree(), 'row1', 0, 0, 1 );
		const row = result.find( ( f ) => f.id === 'row1' );
		expect( row.columns[ 1 ].fields[ 0 ].id ).toBe( 'f4' );
	} );

	it( 'does nothing for non-matching row ID', () => {
		const tree = sampleTree();
		const result = reorderInColumn( tree, 'nope', 0, 0, 1 );
		expect( JSON.stringify( result ) ).toBe( JSON.stringify( tree ) );
	} );

	it( 'does not mutate the original', () => {
		const tree = sampleTree();
		const original = JSON.stringify( tree );
		reorderInColumn( tree, 'row1', 0, 0, 1 );
		expect( JSON.stringify( tree ) ).toBe( original );
	} );
} );
