import { render, screen, fireEvent } from '@testing-library/react';
import { useSortable } from '@dnd-kit/sortable';
import SortableField from './SortableField';

jest.mock( './FieldPreview', () => {
	return function MockFieldPreview( { field } ) {
		return <div data-testid="field-preview">{ field.type }</div>;
	};
} );

function makeField( overrides = {} ) {
	return {
		id: 'f1',
		type: 'text',
		label: 'Name',
		key: 'name',
		...overrides,
	};
}

function renderSortable( overrides = {} ) {
	const props = {
		field: makeField(),
		isSelected: false,
		onSelect: jest.fn(),
		onRemove: jest.fn(),
		onDuplicate: jest.fn(),
		isCanvasDragging: false,
		confirmingDeleteId: null,
		...overrides,
	};
	return { ...props, ...render( <SortableField { ...props } /> ) };
}

describe( 'SortableField', () => {
	it( 'renders field type label and field label', () => {
		renderSortable();
		expect( screen.getByText( 'Text' ) ).toBeTruthy();
		expect( screen.getByText( 'Name' ) ).toBeTruthy();
	} );

	it( 'renders FieldPreview', () => {
		const { container } = renderSortable();
		expect(
			container.querySelector( '[data-testid="field-preview"]' )
		).toBeTruthy();
	} );

	it( 'shows required asterisk when field is required', () => {
		const { container } = renderSortable( {
			field: makeField( { required: true } ),
		} );
		expect(
			container.querySelector( '.shqf-builder-required' )
		).toBeTruthy();
	} );

	it( 'hides required asterisk when field is not required', () => {
		const { container } = renderSortable();
		expect(
			container.querySelector( '.shqf-builder-required' )
		).toBeNull();
	} );

	it( 'applies selected class when isSelected', () => {
		const { container } = renderSortable( { isSelected: true } );
		expect(
			container.querySelector( '.shqf-builder-field--selected' )
		).toBeTruthy();
	} );

	it( 'calls onSelect when clicked', () => {
		const { onSelect, container } = renderSortable();
		fireEvent.click( container.querySelector( '.shqf-builder-field' ) );
		expect( onSelect ).toHaveBeenCalledWith( 'f1' );
	} );

	it( 'calls onSelect on Enter key', () => {
		const { onSelect, container } = renderSortable();
		fireEvent.keyDown( container.querySelector( '.shqf-builder-field' ), {
			key: 'Enter',
		} );
		expect( onSelect ).toHaveBeenCalledWith( 'f1' );
	} );

	it( 'calls onSelect on Space key', () => {
		const { onSelect, container } = renderSortable();
		fireEvent.keyDown( container.querySelector( '.shqf-builder-field' ), {
			key: ' ',
		} );
		expect( onSelect ).toHaveBeenCalledWith( 'f1' );
	} );

	it( 'calls onDuplicate when duplicate button clicked', () => {
		const { onDuplicate } = renderSortable();
		fireEvent.click( screen.getByLabelText( 'Duplicate field' ) );
		expect( onDuplicate ).toHaveBeenCalledWith( 'f1' );
	} );

	it( 'calls onRemove when remove button clicked', () => {
		const { onRemove } = renderSortable();
		fireEvent.click( screen.getByLabelText( 'Remove field' ) );
		expect( onRemove ).toHaveBeenCalledWith( 'f1' );
	} );

	it( 'shows confirm delete button when confirmingDeleteId matches', () => {
		renderSortable( { confirmingDeleteId: 'f1' } );
		expect( screen.getByText( 'Delete?' ) ).toBeTruthy();
		expect( screen.queryByLabelText( 'Remove field' ) ).toBeNull();
	} );

	it( 'calls onRemove on confirm delete click', () => {
		const { onRemove } = renderSortable( { confirmingDeleteId: 'f1' } );
		fireEvent.click( screen.getByLabelText( 'Confirm delete' ) );
		expect( onRemove ).toHaveBeenCalledWith( 'f1' );
	} );

	it( 'shows normal remove button when confirmingDeleteId does not match', () => {
		renderSortable( { confirmingDeleteId: 'other' } );
		expect( screen.getByLabelText( 'Remove field' ) ).toBeTruthy();
		expect( screen.queryByText( 'Delete?' ) ).toBeNull();
	} );

	it( 'has drag handle with aria label', () => {
		renderSortable();
		expect( screen.getByLabelText( 'Drag to reorder' ) ).toBeTruthy();
	} );

	it( 'does not call onSelect when duplicate is clicked', () => {
		const { onSelect } = renderSortable();
		fireEvent.click( screen.getByLabelText( 'Duplicate field' ) );
		expect( onSelect ).not.toHaveBeenCalled();
	} );

	it( 'does not call onSelect when remove is clicked', () => {
		const { onSelect } = renderSortable();
		fireEvent.click( screen.getByLabelText( 'Remove field' ) );
		expect( onSelect ).not.toHaveBeenCalled();
	} );

	it( 'applies dragging class and reduced opacity when isDragging', () => {
		useSortable.mockReturnValueOnce( {
			attributes: {},
			listeners: {},
			setNodeRef: jest.fn(),
			transform: null,
			transition: null,
			isDragging: true,
			isOver: false,
		} );
		const { container } = renderSortable();
		const field = container.querySelector(
			'.shqf-builder-field--dragging'
		);
		expect( field ).toBeTruthy();
		expect( field.style.opacity ).toBe( '0.4' );
	} );

	it( 'has full opacity when not dragging', () => {
		const { container } = renderSortable();
		const field = container.querySelector( '.shqf-builder-field' );
		expect( field.style.opacity ).toBe( '1' );
	} );

	it( 'applies drop-target class when isOver and isCanvasDragging', () => {
		useSortable.mockReturnValueOnce( {
			attributes: {},
			listeners: {},
			setNodeRef: jest.fn(),
			transform: null,
			transition: null,
			isDragging: false,
			isOver: true,
		} );
		const { container } = renderSortable( { isCanvasDragging: true } );
		expect(
			container.querySelector( '.shqf-builder-field--drop-target' )
		).toBeTruthy();
	} );

	it( 'does not apply drop-target when isOver but not isCanvasDragging', () => {
		useSortable.mockReturnValueOnce( {
			attributes: {},
			listeners: {},
			setNodeRef: jest.fn(),
			transform: null,
			transition: null,
			isDragging: false,
			isOver: true,
		} );
		const { container } = renderSortable( { isCanvasDragging: false } );
		expect(
			container.querySelector( '.shqf-builder-field--drop-target' )
		).toBeNull();
	} );
} );
