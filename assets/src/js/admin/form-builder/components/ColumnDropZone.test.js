import { render, screen } from '@testing-library/react';
import { useDroppable } from '@dnd-kit/core';
import ColumnDropZone from './ColumnDropZone';

jest.mock( './SortableField', () => {
	return function MockSortableField( { field, isSelected } ) {
		return (
			<div data-testid={ `sortable-${ field.id }` }>
				{ field.label }
				{ isSelected && <span data-testid="selected" /> }
			</div>
		);
	};
} );

function renderColumn( overrides = {} ) {
	const props = {
		rowId: 'r1',
		colIndex: 0,
		colFields: [],
		selectedId: null,
		onSelect: jest.fn(),
		onRemove: jest.fn(),
		onDuplicate: jest.fn(),
		isDraggingField: false,
		isCanvasDragging: false,
		confirmingDeleteId: null,
		...overrides,
	};
	return { ...props, ...render( <ColumnDropZone { ...props } /> ) };
}

describe( 'ColumnDropZone', () => {
	it( 'renders empty state when no fields', () => {
		renderColumn();
		expect( screen.getByText( 'Drop fields here' ) ).toBeTruthy();
	} );

	it( 'shows "Drop here" when dragging and empty', () => {
		renderColumn( { isDraggingField: true } );
		expect( screen.getByText( 'Drop here' ) ).toBeTruthy();
	} );

	it( 'renders child fields via SortableField', () => {
		const fields = [
			{ id: 'c1', type: 'text', label: 'Name', key: 'name' },
			{ id: 'c2', type: 'email', label: 'Email', key: 'email' },
		];
		const { container } = renderColumn( { colFields: fields } );
		expect(
			container.querySelector( '[data-testid="sortable-c1"]' )
		).toBeTruthy();
		expect(
			container.querySelector( '[data-testid="sortable-c2"]' )
		).toBeTruthy();
	} );

	it( 'hides empty state when fields are present', () => {
		const fields = [
			{ id: 'c1', type: 'text', label: 'Name', key: 'name' },
		];
		renderColumn( { colFields: fields } );
		expect( screen.queryByText( 'Drop fields here' ) ).toBeNull();
	} );

	it( 'passes selectedId to SortableField', () => {
		const fields = [
			{ id: 'c1', type: 'text', label: 'Name', key: 'name' },
		];
		const { container } = renderColumn( {
			colFields: fields,
			selectedId: 'c1',
		} );
		expect(
			container.querySelector( '[data-testid="selected"]' )
		).toBeTruthy();
	} );

	it( 'applies ready class when isDraggingField', () => {
		const { container } = renderColumn( { isDraggingField: true } );
		expect(
			container.querySelector( '.shqf-builder-row-col--ready' )
		).toBeTruthy();
	} );

	it( 'applies base class when not dragging', () => {
		const { container } = renderColumn();
		expect(
			container.querySelector( '.shqf-builder-row-col' )
		).toBeTruthy();
		expect(
			container.querySelector( '.shqf-builder-row-col--ready' )
		).toBeNull();
	} );

	it( 'applies over class when useDroppable isOver', () => {
		useDroppable.mockReturnValueOnce( {
			setNodeRef: jest.fn(),
			isOver: true,
		} );
		const { container } = renderColumn();
		expect(
			container.querySelector( '.shqf-builder-row-col--over' )
		).toBeTruthy();
	} );

	it( 'over class takes priority over ready class', () => {
		useDroppable.mockReturnValueOnce( {
			setNodeRef: jest.fn(),
			isOver: true,
		} );
		const { container } = renderColumn( { isDraggingField: true } );
		expect(
			container.querySelector( '.shqf-builder-row-col--over' )
		).toBeTruthy();
		expect(
			container.querySelector( '.shqf-builder-row-col--ready' )
		).toBeNull();
	} );

	it( 'passes correct droppableId to useDroppable', () => {
		renderColumn( { rowId: 'r5', colIndex: 2 } );
		expect( useDroppable ).toHaveBeenCalledWith( { id: 'col_r5_2' } );
	} );
} );
