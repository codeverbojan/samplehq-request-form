import { render, screen, fireEvent } from '@testing-library/react';
import { useDraggable } from '@dnd-kit/core';
import FieldPalette, { DraggablePaletteItem } from './FieldPalette';

describe( 'FieldPalette', () => {
	it( 'renders the heading', () => {
		render( <FieldPalette onAdd={ jest.fn() } /> );
		expect( screen.getByText( 'Add Fields' ) ).toBeTruthy();
	} );

	it( 'renders a search input', () => {
		const { container } = render( <FieldPalette onAdd={ jest.fn() } /> );
		expect(
			container.querySelector( '.shqf-builder-palette-search' )
		).toBeTruthy();
	} );

	it( 'renders all 5 category sections', () => {
		const { container } = render( <FieldPalette onAdd={ jest.fn() } /> );
		const sections = container.querySelectorAll(
			'.shqf-builder-palette-section'
		);
		expect( sections ).toHaveLength( 5 );
	} );

	it( 'renders all 18 field type items', () => {
		const { container } = render( <FieldPalette onAdd={ jest.fn() } /> );
		const items = container.querySelectorAll(
			'.shqf-builder-palette-item'
		);
		expect( items ).toHaveLength( 18 );
	} );

	it( 'filters items by search query', () => {
		const { container } = render( <FieldPalette onAdd={ jest.fn() } /> );
		const searchInput = container.querySelector(
			'.shqf-builder-palette-search'
		);
		fireEvent.change( searchInput, { target: { value: 'email' } } );
		const items = container.querySelectorAll(
			'.shqf-builder-palette-item'
		);
		expect( items ).toHaveLength( 1 );
		expect( items[ 0 ].textContent ).toContain( 'Email' );
	} );

	it( 'hides categories with no matching fields', () => {
		const { container } = render( <FieldPalette onAdd={ jest.fn() } /> );
		const searchInput = container.querySelector(
			'.shqf-builder-palette-search'
		);
		fireEvent.change( searchInput, { target: { value: 'row' } } );
		const sections = container.querySelectorAll(
			'.shqf-builder-palette-section'
		);
		expect( sections ).toHaveLength( 1 );
	} );

	it( 'shows no sections when search matches nothing', () => {
		const { container } = render( <FieldPalette onAdd={ jest.fn() } /> );
		const searchInput = container.querySelector(
			'.shqf-builder-palette-search'
		);
		fireEvent.change( searchInput, {
			target: { value: 'xyznonexistent' },
		} );
		const sections = container.querySelectorAll(
			'.shqf-builder-palette-section'
		);
		expect( sections ).toHaveLength( 0 );
	} );

	it( 'filters by field type string, not just label', () => {
		const { container } = render( <FieldPalette onAdd={ jest.fn() } /> );
		const searchInput = container.querySelector(
			'.shqf-builder-palette-search'
		);
		fireEvent.change( searchInput, {
			target: { value: 'file_upload' },
		} );
		const items = container.querySelectorAll(
			'.shqf-builder-palette-item'
		);
		expect( items ).toHaveLength( 1 );
	} );

	it( 'search is case-insensitive', () => {
		const { container } = render( <FieldPalette onAdd={ jest.fn() } /> );
		const searchInput = container.querySelector(
			'.shqf-builder-palette-search'
		);
		fireEvent.change( searchInput, { target: { value: 'PHONE' } } );
		const items = container.querySelectorAll(
			'.shqf-builder-palette-item'
		);
		expect( items ).toHaveLength( 1 );
	} );
} );

describe( 'DraggablePaletteItem', () => {
	const fieldType = {
		type: 'text',
		label: 'Text',
		icon: ( props ) => <span data-testid="icon" { ...props } />,
	};

	it( 'renders label and icon', () => {
		render(
			<DraggablePaletteItem fieldType={ fieldType } onAdd={ jest.fn() } />
		);
		expect( screen.getByText( 'Text' ) ).toBeTruthy();
		expect( screen.getByTestId( 'icon' ) ).toBeTruthy();
	} );

	it( 'calls onAdd when clicked', () => {
		const onAdd = jest.fn();
		render(
			<DraggablePaletteItem fieldType={ fieldType } onAdd={ onAdd } />
		);
		fireEvent.click(
			screen.getByText( 'Text' ).closest( '[role="button"]' )
		);
		expect( onAdd ).toHaveBeenCalledWith( 'text' );
	} );

	it( 'calls onAdd on Enter key', () => {
		const onAdd = jest.fn();
		render(
			<DraggablePaletteItem fieldType={ fieldType } onAdd={ onAdd } />
		);
		fireEvent.keyDown(
			screen.getByText( 'Text' ).closest( '[role="button"]' ),
			{ key: 'Enter' }
		);
		expect( onAdd ).toHaveBeenCalledWith( 'text' );
	} );

	it( 'calls onAdd on Space key', () => {
		const onAdd = jest.fn();
		render(
			<DraggablePaletteItem fieldType={ fieldType } onAdd={ onAdd } />
		);
		fireEvent.keyDown(
			screen.getByText( 'Text' ).closest( '[role="button"]' ),
			{ key: ' ' }
		);
		expect( onAdd ).toHaveBeenCalledWith( 'text' );
	} );

	it( 'applies dragging class when isDragging is true', () => {
		useDraggable.mockReturnValueOnce( {
			attributes: {},
			listeners: {},
			setNodeRef: jest.fn(),
			isDragging: true,
		} );
		const { container } = render(
			<DraggablePaletteItem fieldType={ fieldType } onAdd={ jest.fn() } />
		);
		expect(
			container.querySelector( '.shqf-builder-palette-item--dragging' )
		).toBeTruthy();
	} );

	it( 'does not call onAdd on other keys', () => {
		const onAdd = jest.fn();
		render(
			<DraggablePaletteItem fieldType={ fieldType } onAdd={ onAdd } />
		);
		fireEvent.keyDown(
			screen.getByText( 'Text' ).closest( '[role="button"]' ),
			{ key: 'Tab' }
		);
		expect( onAdd ).not.toHaveBeenCalled();
	} );
} );
