jest.mock( './components/FormBuilder', () => {
	return function MockFormBuilder() {
		return null;
	};
} );

function mountRoot( attrs = {} ) {
	const root = document.createElement( 'div' );
	root.id = 'shqf-form-builder-root';
	Object.entries( attrs ).forEach( ( [ key, value ] ) => {
		root.dataset[ key ] =
			typeof value === 'string' ? value : JSON.stringify( value );
	} );
	document.body.appendChild( root );
	return root;
}

function loadAndFire() {
	require( './index' );
	document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
	return require( '@wordpress/element' ).render;
}

describe( 'form-builder entry point', () => {
	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = '';
	} );

	it( 'does not call render when root element is missing', () => {
		const renderMock = loadAndFire();
		expect( renderMock ).not.toHaveBeenCalled();
	} );

	it( 'calls render on the root element', () => {
		const root = mountRoot( { formId: '1' } );
		const renderMock = loadAndFire();
		expect( renderMock ).toHaveBeenCalledTimes( 1 );
		expect( renderMock.mock.calls[ 0 ][ 1 ] ).toBe( root );
	} );

	it( 'parses formId as integer', () => {
		mountRoot( { formId: '42' } );
		const renderMock = loadAndFire();
		expect( renderMock.mock.calls[ 0 ][ 0 ].props.formId ).toBe( 42 );
	} );

	it( 'passes formTitle from data attribute', () => {
		mountRoot( { formTitle: 'My Form' } );
		const renderMock = loadAndFire();
		expect( renderMock.mock.calls[ 0 ][ 0 ].props.formTitle ).toBe(
			'My Form'
		);
	} );

	it( 'parses config from JSON data attribute', () => {
		const config = { fields: [ { id: 'f1', type: 'text' } ] };
		mountRoot( { config } );
		const renderMock = loadAndFire();
		expect( renderMock.mock.calls[ 0 ][ 0 ].props.config ).toEqual(
			config
		);
	} );

	it( 'parses meta from data-form-meta JSON', () => {
		const meta = { status: 'published', back_url: '/forms' };
		mountRoot( { formMeta: meta } );
		const renderMock = loadAndFire();
		expect( renderMock.mock.calls[ 0 ][ 0 ].props.meta ).toEqual( meta );
	} );

	it( 'defaults formId to 0 when data-form-id is missing', () => {
		mountRoot();
		const renderMock = loadAndFire();
		expect( renderMock.mock.calls[ 0 ][ 0 ].props.formId ).toBe( 0 );
	} );

	it( 'defaults formTitle to empty string when missing', () => {
		mountRoot();
		const renderMock = loadAndFire();
		expect( renderMock.mock.calls[ 0 ][ 0 ].props.formTitle ).toBe( '' );
	} );

	it( 'defaults config to null when data-config is missing', () => {
		mountRoot();
		const renderMock = loadAndFire();
		expect( renderMock.mock.calls[ 0 ][ 0 ].props.config ).toBeNull();
	} );

	it( 'defaults meta to null when data-form-meta is missing', () => {
		mountRoot();
		const renderMock = loadAndFire();
		expect( renderMock.mock.calls[ 0 ][ 0 ].props.meta ).toBeNull();
	} );
} );
