import { render, screen, fireEvent, act } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import FormBuilder from './FormBuilder';

jest.mock( './FieldPalette', () => {
	return function MockFieldPalette( { onAdd } ) {
		return (
			<div data-testid="field-palette">
				<button
					data-testid="add-text"
					onClick={ () => onAdd( 'text' ) }
				>
					Add Text
				</button>
			</div>
		);
	};
} );

jest.mock( './FormCanvas', () => {
	return function MockFormCanvas( {
		fields,
		selectedId,
		onSelect,
		onRemove,
		onDuplicate,
		isDragging,
		isDraggingRow,
		confirmingDeleteId,
	} ) {
		return (
			<div
				data-testid="form-canvas"
				data-field-count={ fields.length }
				data-selected={ selectedId || '' }
				data-dragging={ String( isDragging ) }
				data-dragging-row={ String( !! isDraggingRow ) }
				data-confirming-delete={ confirmingDeleteId || '' }
			>
				{ fields.map( ( f ) => (
					<div key={ f.id } data-testid={ `canvas-field-${ f.id }` }>
						{ f.label }
					</div>
				) ) }
				<button
					data-testid="select-field"
					onClick={ () => onSelect( 'f1' ) }
				>
					Select
				</button>
				<button
					data-testid="deselect"
					onClick={ () => onSelect( null ) }
				>
					Deselect
				</button>
				<button
					data-testid="remove-field"
					onClick={ () => onRemove( 'f1' ) }
				>
					Remove
				</button>
				<button
					data-testid="duplicate-field"
					onClick={ () => onDuplicate( 'f1' ) }
				>
					Duplicate
				</button>
			</div>
		);
	};
} );

jest.mock( './FieldSettings', () => {
	return function MockFieldSettings( { field, onChange } ) {
		return (
			<div data-testid="field-settings" data-field-id={ field.id }>
				<button
					data-testid="update-field"
					onClick={ () => onChange( { ...field, label: 'Updated' } ) }
				>
					Update
				</button>
			</div>
		);
	};
} );

jest.mock( './FormSettingsPanel', () => {
	return function MockFormSettingsPanel( {
		subtitle,
		onSubtitle,
		onBehavior,
	} ) {
		return (
			<div
				data-testid="form-settings-panel"
				data-subtitle={ subtitle || '' }
			>
				<button
					data-testid="change-subtitle"
					onClick={ () => onSubtitle( 'New Subtitle' ) }
				>
					Change Subtitle
				</button>
				<button
					data-testid="change-behavior"
					onClick={ () => onBehavior( { redirectUrl: '/thanks' } ) }
				>
					Change Behavior
				</button>
			</div>
		);
	};
} );

const defaultProps = {
	formId: 1,
	formTitle: 'Test Form',
	config: {
		fields: [
			{ id: 'f1', type: 'text', label: 'Name', key: 'name' },
			{ id: 'f2', type: 'email', label: 'Email', key: 'email' },
		],
		subtitle: '',
		layout: '',
		behavior: {},
		appearance: {},
		display: {},
		email: {},
	},
	meta: {
		status: 'draft',
		back_url: '/admin/forms',
		preview_url: '/preview/1',
	},
};

function renderBuilder( overrides = {} ) {
	const props = { ...defaultProps, ...overrides };
	return render( <FormBuilder { ...props } /> );
}

beforeEach( () => {
	jest.useFakeTimers();
	apiFetch.mockReset();
	apiFetch.mockResolvedValue( {} );
	window.requestAnimationFrame = jest.fn( ( cb ) => cb() );
} );

afterEach( () => {
	jest.useRealTimers();
} );

describe( 'FormBuilder', () => {
	describe( 'Rendering', () => {
		it( 'renders header with title input', () => {
			renderBuilder();
			expect( screen.getByDisplayValue( 'Test Form' ) ).toBeTruthy();
		} );

		it( 'renders FieldPalette', () => {
			renderBuilder();
			expect( screen.getByTestId( 'field-palette' ) ).toBeTruthy();
		} );

		it( 'renders FormCanvas with fields', () => {
			renderBuilder();
			const canvas = screen.getByTestId( 'form-canvas' );
			expect( canvas.getAttribute( 'data-field-count' ) ).toBe( '2' );
		} );

		it( 'renders FormSettingsPanel when no field selected', () => {
			renderBuilder();
			expect( screen.getByTestId( 'form-settings-panel' ) ).toBeTruthy();
		} );

		it( 'renders with empty config', () => {
			renderBuilder( { config: null } );
			expect( screen.getByTestId( 'form-canvas' ) ).toBeTruthy();
		} );

		it( 'renders undo and redo buttons', () => {
			renderBuilder();
			expect( screen.getByLabelText( 'Undo' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Redo' ) ).toBeTruthy();
		} );

		it( 'disables undo and redo buttons initially', () => {
			renderBuilder();
			expect( screen.getByLabelText( 'Undo' ).disabled ).toBe( true );
			expect( screen.getByLabelText( 'Redo' ).disabled ).toBe( true );
		} );

		it( 'renders back link', () => {
			renderBuilder();
			expect( screen.getByLabelText( 'Back to forms' ) ).toBeTruthy();
		} );

		it( 'renders Publish button for draft forms', () => {
			renderBuilder();
			expect( screen.getByText( 'Publish' ) ).toBeTruthy();
		} );

		it( 'renders Switch to Draft for published forms', () => {
			renderBuilder( { meta: { status: 'published' } } );
			expect( screen.getByText( 'Switch to Draft' ) ).toBeTruthy();
		} );

		it( 'renders Save button', () => {
			renderBuilder();
			expect( screen.getByText( 'Save' ) ).toBeTruthy();
		} );

		it( 'renders preview link when meta has preview_url', () => {
			renderBuilder();
			expect( screen.getByText( 'Preview' ) ).toBeTruthy();
		} );

		it( 'hides preview link when no preview_url', () => {
			renderBuilder( { meta: { status: 'draft' } } );
			expect( screen.queryByText( 'Preview' ) ).toBeNull();
		} );
	} );

	describe( 'Field selection', () => {
		it( 'shows FieldSettings when a field is selected', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'select-field' ) );
			expect( screen.getByTestId( 'field-settings' ) ).toBeTruthy();
			expect( screen.queryByTestId( 'form-settings-panel' ) ).toBeNull();
		} );

		it( 'shows FormSettingsPanel when deselected', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'select-field' ) );
			fireEvent.click( screen.getByTestId( 'deselect' ) );
			expect( screen.getByTestId( 'form-settings-panel' ) ).toBeTruthy();
		} );
	} );

	describe( 'Title editing', () => {
		it( 'updates title on input change', () => {
			renderBuilder();
			const input = screen.getByDisplayValue( 'Test Form' );
			fireEvent.change( input, {
				target: { value: 'New Title' },
			} );
			expect( screen.getByDisplayValue( 'New Title' ) ).toBeTruthy();
		} );
	} );

	describe( 'Field operations', () => {
		it( 'adds a field via palette callback', () => {
			renderBuilder( {
				config: { fields: [] },
			} );
			fireEvent.click( screen.getByTestId( 'add-text' ) );
			const canvas = screen.getByTestId( 'form-canvas' );
			expect( canvas.getAttribute( 'data-field-count' ) ).toBe( '1' );
		} );

		it( 'selects the newly added field', () => {
			renderBuilder( {
				config: { fields: [] },
			} );
			fireEvent.click( screen.getByTestId( 'add-text' ) );
			expect( screen.getByTestId( 'field-settings' ) ).toBeTruthy();
		} );

		it( 'removes a field via requestDelete (two clicks)', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'remove-field' ) );
			fireEvent.click( screen.getByTestId( 'remove-field' ) );
			const canvas = screen.getByTestId( 'form-canvas' );
			expect( canvas.getAttribute( 'data-field-count' ) ).toBe( '1' );
		} );

		it( 'auto-clears delete confirmation after timeout', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'remove-field' ) );
			act( () => {
				jest.advanceTimersByTime( 3000 );
			} );
			fireEvent.click( screen.getByTestId( 'remove-field' ) );
			const canvas = screen.getByTestId( 'form-canvas' );
			expect( canvas.getAttribute( 'data-field-count' ) ).toBe( '2' );
		} );

		it( 'updates a field via FieldSettings onChange', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'select-field' ) );
			fireEvent.click( screen.getByTestId( 'update-field' ) );
			expect( screen.getByTestId( 'canvas-field-f1' ).textContent ).toBe(
				'Updated'
			);
		} );

		it( 'duplicates a field', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'duplicate-field' ) );
			const canvas = screen.getByTestId( 'form-canvas' );
			expect( canvas.getAttribute( 'data-field-count' ) ).toBe( '3' );
		} );
	} );

	describe( 'Save', () => {
		it( 'calls apiFetch on save with correct data', async () => {
			renderBuilder();
			await act( async () => {
				fireEvent.click( screen.getByText( 'Save' ) );
			} );
			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/samplehq-form/v1/forms/1',
					method: 'PUT',
					data: expect.objectContaining( {
						title: 'Test Form',
						status: 'draft',
						config: expect.objectContaining( {
							schema_version: 1,
							fields: expect.any( Array ),
						} ),
					} ),
				} )
			);
		} );

		it( 'shows success notice after save', async () => {
			renderBuilder();
			await act( async () => {
				fireEvent.click( screen.getByText( 'Save' ) );
			} );
			expect( screen.getByText( 'Form saved.' ) ).toBeTruthy();
		} );

		it( 'shows error notice on save failure', async () => {
			apiFetch.mockRejectedValueOnce( new Error( 'Network' ) );
			renderBuilder();
			await act( async () => {
				fireEvent.click( screen.getByText( 'Save' ) );
			} );
			expect( screen.getByText( 'Failed to save form.' ) ).toBeTruthy();
		} );

		it( 'auto-dismisses notice after 3 seconds', async () => {
			renderBuilder();
			await act( async () => {
				fireEvent.click( screen.getByText( 'Save' ) );
			} );
			expect( screen.getByText( 'Form saved.' ) ).toBeTruthy();
			act( () => {
				jest.advanceTimersByTime( 3000 );
			} );
			expect( screen.queryByText( 'Form saved.' ) ).toBeNull();
		} );

		it( 'does not call apiFetch when formId is 0', async () => {
			renderBuilder( { formId: 0 } );
			await act( async () => {
				fireEvent.click( screen.getByText( 'Save' ) );
			} );
			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		it( 'includes updated subtitle from FormSettingsPanel in save payload', async () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'change-subtitle' ) );
			await act( async () => {
				fireEvent.click( screen.getByText( 'Save' ) );
			} );
			const payload = apiFetch.mock.calls[ 0 ][ 0 ];
			expect( payload.data.config.subtitle ).toBe( 'New Subtitle' );
		} );

		it( 'shows Saving… while save is in progress', async () => {
			let resolveSave;
			apiFetch.mockImplementation(
				() =>
					new Promise( ( resolve ) => {
						resolveSave = resolve;
					} )
			);
			renderBuilder();
			act( () => {
				fireEvent.click( screen.getByText( 'Save' ) );
			} );
			expect( screen.getByText( 'Saving…' ) ).toBeTruthy();
			await act( async () => {
				resolveSave( {} );
			} );
			expect( screen.getByText( 'Save' ) ).toBeTruthy();
		} );
	} );

	describe( 'Dirty state', () => {
		it( 'adds dirty class to save button after title change', () => {
			const { container } = renderBuilder();
			fireEvent.change( screen.getByDisplayValue( 'Test Form' ), {
				target: { value: 'Changed' },
			} );
			expect(
				container.querySelector( '.shqf-save-dirty' )
			).toBeTruthy();
		} );

		it( 'does not have dirty class initially', () => {
			const { container } = renderBuilder();
			expect( container.querySelector( '.shqf-save-dirty' ) ).toBeNull();
		} );

		it( 'marks dirty after adding a field', () => {
			const { container } = renderBuilder();
			fireEvent.click( screen.getByTestId( 'add-text' ) );
			expect(
				container.querySelector( '.shqf-save-dirty' )
			).toBeTruthy();
		} );

		it( 'marks dirty after changing subtitle via FormSettingsPanel', () => {
			const { container } = renderBuilder();
			fireEvent.click( screen.getByTestId( 'change-subtitle' ) );
			expect(
				container.querySelector( '.shqf-save-dirty' )
			).toBeTruthy();
		} );

		it( 'marks dirty after changing behavior via FormSettingsPanel', () => {
			const { container } = renderBuilder();
			fireEvent.click( screen.getByTestId( 'change-behavior' ) );
			expect(
				container.querySelector( '.shqf-save-dirty' )
			).toBeTruthy();
		} );

		it( 'clears dirty state after successful save', async () => {
			const { container } = renderBuilder();
			fireEvent.change( screen.getByDisplayValue( 'Test Form' ), {
				target: { value: 'Changed' },
			} );
			expect(
				container.querySelector( '.shqf-save-dirty' )
			).toBeTruthy();
			await act( async () => {
				fireEvent.click( screen.getByText( 'Save' ) );
			} );
			expect( container.querySelector( '.shqf-save-dirty' ) ).toBeNull();
		} );
	} );

	describe( 'Status toggle', () => {
		it( 'toggles from draft to published and auto-saves', async () => {
			renderBuilder();
			await act( async () => {
				fireEvent.click( screen.getByText( 'Publish' ) );
			} );
			expect( apiFetch ).toHaveBeenCalled();
			expect( screen.getByText( 'Switch to Draft' ) ).toBeTruthy();
		} );

		it( 'toggles from published to draft and auto-saves', async () => {
			renderBuilder( {
				meta: {
					status: 'published',
					back_url: '/admin/forms',
					preview_url: '/preview/1',
				},
			} );
			await act( async () => {
				fireEvent.click( screen.getByText( 'Switch to Draft' ) );
			} );
			expect( apiFetch ).toHaveBeenCalled();
			expect( screen.getByText( 'Publish' ) ).toBeTruthy();
		} );
	} );

	describe( 'Keyboard shortcuts', () => {
		it( 'Escape deselects the current field', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'select-field' ) );
			expect( screen.getByTestId( 'field-settings' ) ).toBeTruthy();
			fireEvent.keyDown( document, { key: 'Escape' } );
			expect( screen.getByTestId( 'form-settings-panel' ) ).toBeTruthy();
		} );

		it( 'Delete triggers requestDelete on selected field', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'select-field' ) );
			fireEvent.keyDown( document, { key: 'Delete' } );
			fireEvent.keyDown( document, { key: 'Delete' } );
			const canvas = screen.getByTestId( 'form-canvas' );
			expect( canvas.getAttribute( 'data-field-count' ) ).toBe( '1' );
		} );

		it( 'does not delete when focused on a select element', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'select-field' ) );
			const sel = document.createElement( 'select' );
			document.body.appendChild( sel );
			sel.focus();
			fireEvent.keyDown( document, { key: 'Delete' } );
			fireEvent.keyDown( document, { key: 'Delete' } );
			const canvas = screen.getByTestId( 'form-canvas' );
			expect( canvas.getAttribute( 'data-field-count' ) ).toBe( '2' );
			document.body.removeChild( sel );
		} );

		it( 'does not delete when focused on a contentEditable element', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'select-field' ) );
			const div = document.createElement( 'div' );
			div.tabIndex = -1;
			Object.defineProperty( div, 'isContentEditable', {
				value: true,
			} );
			document.body.appendChild( div );
			div.focus();
			fireEvent.keyDown( document, { key: 'Delete' } );
			fireEvent.keyDown( document, { key: 'Delete' } );
			const canvas = screen.getByTestId( 'form-canvas' );
			expect( canvas.getAttribute( 'data-field-count' ) ).toBe( '2' );
			document.body.removeChild( div );
		} );

		it( 'does not delete when focused on an input', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'select-field' ) );
			const titleInput = screen.getByDisplayValue( 'Test Form' );
			titleInput.focus();
			fireEvent.keyDown( document, { key: 'Delete' } );
			fireEvent.keyDown( document, { key: 'Delete' } );
			const canvas = screen.getByTestId( 'form-canvas' );
			expect( canvas.getAttribute( 'data-field-count' ) ).toBe( '2' );
		} );
	} );

	describe( 'Back link', () => {
		it( 'does not call confirm when not dirty', () => {
			window.confirm = jest.fn( () => true );
			renderBuilder();
			fireEvent.click( screen.getByLabelText( 'Back to forms' ) );
			expect( window.confirm ).not.toHaveBeenCalled();
		} );

		it( 'calls confirm when dirty and prevents default on decline', () => {
			window.confirm = jest.fn( () => false );
			renderBuilder();
			fireEvent.change( screen.getByDisplayValue( 'Test Form' ), {
				target: { value: 'Changed' },
			} );
			fireEvent.click( screen.getByLabelText( 'Back to forms' ) );
			expect( window.confirm ).toHaveBeenCalled();
		} );
	} );

	describe( 'Undo/Redo buttons', () => {
		it( 'undo button click removes the last added field', () => {
			renderBuilder( { config: { fields: [] } } );
			fireEvent.click( screen.getByTestId( 'add-text' ) );
			expect(
				screen
					.getByTestId( 'form-canvas' )
					.getAttribute( 'data-field-count' )
			).toBe( '1' );
			fireEvent.click( screen.getByLabelText( 'Undo' ) );
			expect(
				screen
					.getByTestId( 'form-canvas' )
					.getAttribute( 'data-field-count' )
			).toBe( '0' );
		} );

		it( 'redo button click restores the undone field', () => {
			renderBuilder( { config: { fields: [] } } );
			fireEvent.click( screen.getByTestId( 'add-text' ) );
			fireEvent.click( screen.getByLabelText( 'Undo' ) );
			expect(
				screen
					.getByTestId( 'form-canvas' )
					.getAttribute( 'data-field-count' )
			).toBe( '0' );
			fireEvent.click( screen.getByLabelText( 'Redo' ) );
			expect(
				screen
					.getByTestId( 'form-canvas' )
					.getAttribute( 'data-field-count' )
			).toBe( '1' );
		} );

		it( 'enables undo after adding a field and disables after undoing', () => {
			renderBuilder( { config: { fields: [] } } );
			expect( screen.getByLabelText( 'Undo' ).disabled ).toBe( true );
			fireEvent.click( screen.getByTestId( 'add-text' ) );
			expect( screen.getByLabelText( 'Undo' ).disabled ).toBe( false );
			fireEvent.click( screen.getByLabelText( 'Undo' ) );
			expect( screen.getByLabelText( 'Undo' ).disabled ).toBe( true );
			expect( screen.getByLabelText( 'Redo' ).disabled ).toBe( false );
		} );
	} );

	describe( 'Undo/Redo keyboard shortcuts', () => {
		it( 'Ctrl+Z triggers undo (removes the last added field)', () => {
			renderBuilder( { config: { fields: [] } } );
			fireEvent.click( screen.getByTestId( 'add-text' ) );
			expect(
				screen
					.getByTestId( 'form-canvas' )
					.getAttribute( 'data-field-count' )
			).toBe( '1' );
			fireEvent.keyDown( document, {
				key: 'z',
				ctrlKey: true,
				shiftKey: false,
			} );
			expect(
				screen
					.getByTestId( 'form-canvas' )
					.getAttribute( 'data-field-count' )
			).toBe( '0' );
		} );

		it( 'Ctrl+Shift+Z triggers redo', () => {
			renderBuilder( { config: { fields: [] } } );
			fireEvent.click( screen.getByTestId( 'add-text' ) );
			fireEvent.keyDown( document, {
				key: 'z',
				ctrlKey: true,
				shiftKey: false,
			} );
			expect(
				screen
					.getByTestId( 'form-canvas' )
					.getAttribute( 'data-field-count' )
			).toBe( '0' );
			fireEvent.keyDown( document, {
				key: 'z',
				ctrlKey: true,
				shiftKey: true,
			} );
			expect(
				screen
					.getByTestId( 'form-canvas' )
					.getAttribute( 'data-field-count' )
			).toBe( '1' );
		} );

		it( 'Ctrl+Y triggers redo', () => {
			renderBuilder( { config: { fields: [] } } );
			fireEvent.click( screen.getByTestId( 'add-text' ) );
			fireEvent.keyDown( document, {
				key: 'z',
				ctrlKey: true,
				shiftKey: false,
			} );
			fireEvent.keyDown( document, { key: 'y', ctrlKey: true } );
			expect(
				screen
					.getByTestId( 'form-canvas' )
					.getAttribute( 'data-field-count' )
			).toBe( '1' );
		} );

		it( 'does not undo when an input is focused', () => {
			renderBuilder( { config: { fields: [] } } );
			fireEvent.click( screen.getByTestId( 'add-text' ) );
			screen.getByPlaceholderText( 'Form title' ).focus();
			fireEvent.keyDown( document, {
				key: 'z',
				ctrlKey: true,
				shiftKey: false,
			} );
			expect(
				screen
					.getByTestId( 'form-canvas' )
					.getAttribute( 'data-field-count' )
			).toBe( '1' );
		} );
	} );

	describe( 'Beforeunload', () => {
		it( 'sets returnValue when dirty', () => {
			renderBuilder();
			fireEvent.change( screen.getByDisplayValue( 'Test Form' ), {
				target: { value: 'Changed' },
			} );
			const event = new Event( 'beforeunload', { cancelable: true } );
			Object.defineProperty( event, 'returnValue', {
				writable: true,
				value: 'initial',
			} );
			window.dispatchEvent( event );
			expect( event.returnValue ).toBe( '' );
		} );

		it( 'does not set returnValue when not dirty', () => {
			renderBuilder();
			const event = new Event( 'beforeunload', { cancelable: true } );
			Object.defineProperty( event, 'returnValue', {
				writable: true,
				value: 'initial',
			} );
			window.dispatchEvent( event );
			expect( event.returnValue ).toBe( 'initial' );
		} );
	} );

	describe( 'Preview link', () => {
		it( 'saves before opening preview when dirty', async () => {
			window.open = jest.fn();
			renderBuilder();
			fireEvent.change( screen.getByDisplayValue( 'Test Form' ), {
				target: { value: 'Changed' },
			} );
			await act( async () => {
				fireEvent.click( screen.getByText( 'Preview' ) );
			} );
			expect( apiFetch ).toHaveBeenCalled();
			expect( window.open ).toHaveBeenCalledWith(
				'/preview/1',
				'_blank'
			);
			// jsdom logs "Not implemented: navigation" for <a> clicks.
			expect( console ).toHaveErrored();
		} );

		it( 'does not save before preview when not dirty', () => {
			renderBuilder();
			fireEvent.click( screen.getByText( 'Preview' ) );
			expect( apiFetch ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'Edge cases', () => {
		it( 'passes confirmingDeleteId to FormCanvas after first remove click', () => {
			renderBuilder();
			const canvas = screen.getByTestId( 'form-canvas' );
			expect( canvas.getAttribute( 'data-confirming-delete' ) ).toBe(
				''
			);
			fireEvent.click( screen.getByTestId( 'remove-field' ) );
			expect( canvas.getAttribute( 'data-confirming-delete' ) ).toBe(
				'f1'
			);
		} );

		it( 'clears selectedId when selected field no longer exists', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'select-field' ) );
			expect( screen.getByTestId( 'field-settings' ) ).toBeTruthy();
			fireEvent.click( screen.getByTestId( 'remove-field' ) );
			fireEvent.click( screen.getByTestId( 'remove-field' ) );
			expect( screen.getByTestId( 'form-settings-panel' ) ).toBeTruthy();
		} );

		it( 'Backspace triggers delete like Delete key', () => {
			renderBuilder();
			fireEvent.click( screen.getByTestId( 'select-field' ) );
			fireEvent.keyDown( document, { key: 'Backspace' } );
			fireEvent.keyDown( document, { key: 'Backspace' } );
			expect(
				screen
					.getByTestId( 'form-canvas' )
					.getAttribute( 'data-field-count' )
			).toBe( '1' );
		} );

		it( 'uses # as back_url when meta has no back_url', () => {
			renderBuilder( { meta: { status: 'draft' } } );
			expect(
				screen.getByLabelText( 'Back to forms' ).getAttribute( 'href' )
			).toBe( '#' );
		} );

		it( 'defaults to empty title when formTitle is not provided', () => {
			renderBuilder( { formTitle: '' } );
			expect( screen.getByPlaceholderText( 'Form title' ).value ).toBe(
				''
			);
		} );
	} );
} );
