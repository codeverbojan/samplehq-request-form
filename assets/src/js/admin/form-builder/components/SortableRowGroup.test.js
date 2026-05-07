import { render, screen, fireEvent } from '@testing-library/react';
import SortableRowGroup from './SortableRowGroup';

jest.mock( './ColumnDropZone', () => {
	return function MockColumnDropZone( {
		rowId,
		colIndex,
		colFields,
		isCanvasDragging,
	} ) {
		return (
			<div
				data-testid={ `col-${ rowId }-${ colIndex }` }
				data-canvas-dragging={ String( !! isCanvasDragging ) }
			>
				{ colFields.length } fields
			</div>
		);
	};
} );

function makeRow( overrides = {} ) {
	return {
		id: 'r1',
		type: 'row',
		columns: [
			{ width: '1fr', fields: [ { id: 'f1' } ] },
			{ width: '1fr', fields: [ { id: 'f2' } ] },
		],
		...overrides,
	};
}

function renderRow( overrides = {} ) {
	const props = {
		row: makeRow(),
		isSelected: false,
		selectedId: null,
		onSelect: jest.fn(),
		onRemove: jest.fn(),
		onDuplicate: jest.fn(),
		isDraggingField: false,
		confirmingDeleteId: null,
		...overrides,
	};
	return { ...props, ...render( <SortableRowGroup { ...props } /> ) };
}

describe( 'SortableRowGroup', () => {
	it( 'renders row header with column count', () => {
		renderRow();
		expect( screen.getByText( /Row/ ) ).toBeTruthy();
		expect( screen.getByText( /2/ ) ).toBeTruthy();
		expect( screen.getByText( /columns/ ) ).toBeTruthy();
	} );

	it( 'renders ColumnDropZone for each column', () => {
		const { container } = renderRow();
		expect(
			container.querySelector( '[data-testid="col-r1-0"]' )
		).toBeTruthy();
		expect(
			container.querySelector( '[data-testid="col-r1-1"]' )
		).toBeTruthy();
	} );

	it( 'applies selected class when isSelected', () => {
		const { container } = renderRow( { isSelected: true } );
		expect(
			container.querySelector( '.shqf-builder-row--selected' )
		).toBeTruthy();
	} );

	it( 'calls onSelect when row header is clicked', () => {
		const { onSelect, container } = renderRow();
		fireEvent.click(
			container.querySelector( '.shqf-builder-row-header' )
		);
		expect( onSelect ).toHaveBeenCalledWith( 'r1' );
	} );

	it( 'calls onDuplicate when duplicate button clicked', () => {
		const { onDuplicate } = renderRow();
		fireEvent.click( screen.getByLabelText( 'Duplicate row' ) );
		expect( onDuplicate ).toHaveBeenCalledWith( 'r1' );
	} );

	it( 'calls onRemove when remove button clicked', () => {
		const { onRemove } = renderRow();
		fireEvent.click( screen.getByLabelText( 'Remove row' ) );
		expect( onRemove ).toHaveBeenCalledWith( 'r1' );
	} );

	it( 'has drag handle with aria label', () => {
		renderRow();
		expect( screen.getByLabelText( 'Drag to reorder row' ) ).toBeTruthy();
	} );

	it( 'handles row with no columns', () => {
		const row = makeRow( { columns: undefined } );
		renderRow( { row } );
		expect( screen.getByText( /0/ ) ).toBeTruthy();
	} );

	it( 'calls onSelect on Enter key on row header', () => {
		const { onSelect, container } = renderRow();
		fireEvent.keyDown(
			container.querySelector( '.shqf-builder-row-header' ),
			{ key: 'Enter' }
		);
		expect( onSelect ).toHaveBeenCalledWith( 'r1' );
	} );

	it( 'does not call onSelect when duplicate is clicked', () => {
		const { onSelect } = renderRow();
		fireEvent.click( screen.getByLabelText( 'Duplicate row' ) );
		expect( onSelect ).not.toHaveBeenCalled();
	} );

	it( 'does not call onSelect when remove is clicked', () => {
		const { onSelect } = renderRow();
		fireEvent.click( screen.getByLabelText( 'Remove row' ) );
		expect( onSelect ).not.toHaveBeenCalled();
	} );

	it( 'calls onSelect on Space key on row header', () => {
		const { onSelect, container } = renderRow();
		fireEvent.keyDown(
			container.querySelector( '.shqf-builder-row-header' ),
			{ key: ' ' }
		);
		expect( onSelect ).toHaveBeenCalledWith( 'r1' );
	} );

	it( 'does not call onSelect when row body is clicked', () => {
		const { onSelect, container } = renderRow();
		fireEvent.click(
			container.querySelector( '.shqf-builder-row-columns' )
		);
		expect( onSelect ).not.toHaveBeenCalled();
	} );

	it( 'passes isDraggingField as isCanvasDragging to ColumnDropZone', () => {
		const { container } = renderRow( { isDraggingField: true } );
		expect(
			container
				.querySelector( '[data-testid="col-r1-0"]' )
				.getAttribute( 'data-canvas-dragging' )
		).toBe( 'true' );
	} );
} );
