import { render, screen, fireEvent } from '@testing-library/react';
import { useDroppable } from '@dnd-kit/core';
import FormCanvas, { CanvasDropZone } from './FormCanvas';

jest.mock( './SortableField', () => {
	return function MockSortableField( { field, isSelected } ) {
		return (
			<div data-testid={ `field-${ field.id }` }>
				{ field.label }
				{ isSelected && <span data-testid="selected" /> }
			</div>
		);
	};
} );

jest.mock( './SortableRowGroup', () => {
	return function MockSortableRowGroup( {
		row,
		isSelected,
		isDraggingField,
	} ) {
		return (
			<div
				data-testid={ `row-${ row.id }` }
				data-dragging-field={ String( isDraggingField ) }
			>
				Row: { row.id }
				{ isSelected && <span data-testid="row-selected" /> }
			</div>
		);
	};
} );

function renderCanvas( overrides = {} ) {
	const props = {
		fields: [],
		selectedId: null,
		onSelect: jest.fn(),
		onRemove: jest.fn(),
		onDuplicate: jest.fn(),
		confirmingDeleteId: null,
		isDragging: false,
		isDraggingRow: false,
		...overrides,
	};
	return { ...props, ...render( <FormCanvas { ...props } /> ) };
}

describe( 'FormCanvas', () => {
	it( 'renders empty state with CanvasDropZone when no fields', () => {
		renderCanvas();
		expect(
			screen.getByText( 'Click or drag a field from the left to add it.' )
		).toBeTruthy();
	} );

	it( 'renders fields via SortableField', () => {
		const fields = [
			{ id: 'f1', type: 'text', label: 'Name', key: 'name' },
			{ id: 'f2', type: 'email', label: 'Email', key: 'email' },
		];
		const { container } = renderCanvas( { fields } );
		expect(
			container.querySelector( '[data-testid="field-f1"]' )
		).toBeTruthy();
		expect(
			container.querySelector( '[data-testid="field-f2"]' )
		).toBeTruthy();
	} );

	it( 'renders row fields via SortableRowGroup', () => {
		const fields = [
			{
				id: 'r1',
				type: 'row',
				columns: [ { width: '1fr', fields: [] } ],
			},
		];
		const { container } = renderCanvas( { fields } );
		expect(
			container.querySelector( '[data-testid="row-r1"]' )
		).toBeTruthy();
	} );

	it( 'mixes regular fields and rows', () => {
		const fields = [
			{ id: 'f1', type: 'text', label: 'Name', key: 'name' },
			{
				id: 'r1',
				type: 'row',
				columns: [ { width: '1fr', fields: [] } ],
			},
		];
		const { container } = renderCanvas( { fields } );
		expect(
			container.querySelector( '[data-testid="field-f1"]' )
		).toBeTruthy();
		expect(
			container.querySelector( '[data-testid="row-r1"]' )
		).toBeTruthy();
	} );

	it( 'passes selectedId to SortableField', () => {
		const fields = [
			{ id: 'f1', type: 'text', label: 'Name', key: 'name' },
		];
		const { container } = renderCanvas( {
			fields,
			selectedId: 'f1',
		} );
		expect(
			container.querySelector( '[data-testid="selected"]' )
		).toBeTruthy();
	} );

	it( 'passes selectedId to SortableRowGroup', () => {
		const fields = [
			{
				id: 'r1',
				type: 'row',
				columns: [ { width: '1fr', fields: [] } ],
			},
		];
		const { container } = renderCanvas( {
			fields,
			selectedId: 'r1',
		} );
		expect(
			container.querySelector( '[data-testid="row-selected"]' )
		).toBeTruthy();
	} );

	it( 'calls onSelect(null) when canvas background is clicked', () => {
		const fields = [
			{ id: 'f1', type: 'text', label: 'Name', key: 'name' },
		];
		const { onSelect, container } = renderCanvas( { fields } );
		fireEvent.click(
			container.querySelector( '.shqf-builder-canvas-wrap' )
		);
		expect( onSelect ).toHaveBeenCalledWith( null );
	} );

	it( 'applies dragging class when isDragging', () => {
		const fields = [
			{ id: 'f1', type: 'text', label: 'Name', key: 'name' },
		];
		const { container } = renderCanvas( {
			fields,
			isDragging: true,
		} );
		expect(
			container.querySelector( '.shqf-builder-canvas-wrap--dragging' )
		).toBeTruthy();
	} );

	it( 'does not show empty state when fields exist', () => {
		const fields = [
			{ id: 'f1', type: 'text', label: 'Name', key: 'name' },
		];
		renderCanvas( { fields } );
		expect(
			screen.queryByText(
				'Click or drag a field from the left to add it.'
			)
		).toBeNull();
	} );

	it( 'calls onSelect(null) on Enter key on canvas wrap', () => {
		const fields = [
			{ id: 'f1', type: 'text', label: 'Name', key: 'name' },
		];
		const { onSelect, container } = renderCanvas( { fields } );
		const wrap = container.querySelector( '.shqf-builder-canvas-wrap' );
		fireEvent.keyDown( wrap, { key: 'Enter' } );
		expect( onSelect ).toHaveBeenCalledWith( null );
	} );

	it( 'passes isDraggingField=false when isDraggingRow is true', () => {
		const fields = [
			{
				id: 'r1',
				type: 'row',
				columns: [ { width: '1fr', fields: [] } ],
			},
		];
		const { container } = renderCanvas( {
			fields,
			isDragging: true,
			isDraggingRow: true,
		} );
		expect(
			container
				.querySelector( '[data-testid="row-r1"]' )
				.getAttribute( 'data-dragging-field' )
		).toBe( 'false' );
	} );

	it( 'passes isDraggingField=true when isDragging but not isDraggingRow', () => {
		const fields = [
			{
				id: 'r1',
				type: 'row',
				columns: [ { width: '1fr', fields: [] } ],
			},
		];
		const { container } = renderCanvas( {
			fields,
			isDragging: true,
			isDraggingRow: false,
		} );
		expect(
			container
				.querySelector( '[data-testid="row-r1"]' )
				.getAttribute( 'data-dragging-field' )
		).toBe( 'true' );
	} );

	it( 'calls onSelect(null) on Space key on canvas wrap', () => {
		const fields = [
			{ id: 'f1', type: 'text', label: 'Name', key: 'name' },
		];
		const { onSelect, container } = renderCanvas( { fields } );
		const wrap = container.querySelector( '.shqf-builder-canvas-wrap' );
		fireEvent.keyDown( wrap, { key: ' ' } );
		expect( onSelect ).toHaveBeenCalledWith( null );
	} );

	it( 'does not deselect when a child element is clicked', () => {
		const fields = [
			{ id: 'f1', type: 'text', label: 'Name', key: 'name' },
		];
		const { onSelect, container } = renderCanvas( { fields } );
		fireEvent.click(
			container.querySelector( '[data-testid="field-f1"]' )
		);
		expect( onSelect ).not.toHaveBeenCalledWith( null );
	} );
} );

describe( 'CanvasDropZone', () => {
	it( 'renders instruction text', () => {
		render( <CanvasDropZone /> );
		expect(
			screen.getByText( 'Click or drag a field from the left to add it.' )
		).toBeTruthy();
	} );

	it( 'applies over class when useDroppable isOver', () => {
		useDroppable.mockReturnValueOnce( {
			setNodeRef: jest.fn(),
			isOver: true,
		} );
		const { container } = render( <CanvasDropZone /> );
		expect(
			container.querySelector( '.shqf-builder-canvas-empty--over' )
		).toBeTruthy();
	} );
} );
