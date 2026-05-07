import { render, screen, fireEvent } from '@testing-library/react';
import FieldSettings from './FieldSettings';

jest.mock( './SamplePickerSettings', () => {
	return function MockSamplePicker( { field } ) {
		return <div data-testid="sample-picker-settings">{ field.id }</div>;
	};
} );

jest.mock( './ConditionalLogicPanel', () => {
	return function MockConditionalLogic( { field } ) {
		return <div data-testid="conditional-logic">{ field.id }</div>;
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

function renderSettings( field, opts = {} ) {
	const onChange = opts.onChange || jest.fn();
	const fields = opts.fields || [];
	return {
		onChange,
		...render(
			<FieldSettings
				field={ field }
				fields={ fields }
				onChange={ onChange }
			/>
		),
	};
}

describe( 'FieldSettings', () => {
	it( 'returns null when field is null', () => {
		const { container } = render(
			<FieldSettings
				field={ null }
				fields={ [] }
				onChange={ jest.fn() }
			/>
		);
		expect( container.innerHTML ).toBe( '' );
	} );

	describe( 'General panel (text field)', () => {
		it( 'renders header with field type and label', () => {
			renderSettings( makeField() );
			expect( screen.getByText( /Text:/ ) ).toBeTruthy();
			expect( screen.getByText( /Name/ ) ).toBeTruthy();
		} );

		it( 'shows Untitled when label is empty', () => {
			renderSettings( makeField( { label: '' } ) );
			expect( screen.getByText( /Untitled/ ) ).toBeTruthy();
		} );

		it( 'renders label, key, placeholder, required, and description', () => {
			renderSettings( makeField() );
			expect( screen.getByLabelText( 'Label' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Field Key' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Placeholder' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Required' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Description' ) ).toBeTruthy();
		} );

		it( 'calls onChange when label changes and auto-generates key', () => {
			const { onChange } = renderSettings( makeField() );
			fireEvent.change( screen.getByLabelText( 'Label' ), {
				target: { value: 'Full Name' },
			} );
			expect( onChange ).toHaveBeenCalled();
			const update = onChange.mock.calls[ 0 ][ 0 ];
			expect( update.label ).toBe( 'Full Name' );
			expect( update.key ).toBe( 'full_name' );
		} );

		it( 'generates unique key when duplicate exists', () => {
			const existingField = makeField( {
				id: 'f2',
				key: 'full_name',
			} );
			const { onChange } = renderSettings( makeField(), {
				fields: [ existingField ],
			} );
			fireEvent.change( screen.getByLabelText( 'Label' ), {
				target: { value: 'Full Name' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].key ).toBe( 'full_name_2' );
		} );

		it( 'stops auto-generating key after manual edit', () => {
			const field = makeField();
			const onChange = jest.fn();
			const { rerender } = render(
				<FieldSettings
					field={ field }
					fields={ [] }
					onChange={ onChange }
				/>
			);
			fireEvent.change( screen.getByLabelText( 'Field Key' ), {
				target: { value: 'custom_key' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].key ).toBe( 'custom_key' );

			const updatedField = { ...field, key: 'custom_key' };
			rerender(
				<FieldSettings
					field={ updatedField }
					fields={ [] }
					onChange={ onChange }
				/>
			);
			fireEvent.change( screen.getByLabelText( 'Label' ), {
				target: { value: 'New Label' },
			} );
			const labelUpdate = onChange.mock.calls[ 1 ][ 0 ];
			expect( labelUpdate.label ).toBe( 'New Label' );
			expect( labelUpdate.key ).toBe( 'custom_key' );
		} );

		it( 'resets key auto-generation when switching to a different field', () => {
			const field1 = makeField( { id: 'f1', key: 'name' } );
			const onChange = jest.fn();
			const { rerender } = render(
				<FieldSettings
					field={ field1 }
					fields={ [] }
					onChange={ onChange }
				/>
			);
			fireEvent.change( screen.getByLabelText( 'Field Key' ), {
				target: { value: 'custom_key' },
			} );

			const field2 = makeField( {
				id: 'f2',
				label: 'Email',
				key: 'email',
			} );
			rerender(
				<FieldSettings
					field={ field2 }
					fields={ [] }
					onChange={ onChange }
				/>
			);
			fireEvent.change( screen.getByLabelText( 'Label' ), {
				target: { value: 'Work Email' },
			} );
			const update =
				onChange.mock.calls[ onChange.mock.calls.length - 1 ][ 0 ];
			expect( update.key ).toBe( 'work_email' );
		} );

		it( 'calls onChange when placeholder changes', () => {
			const { onChange } = renderSettings( makeField() );
			fireEvent.change( screen.getByLabelText( 'Placeholder' ), {
				target: { value: 'Enter name' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].placeholder ).toBe(
				'Enter name'
			);
		} );

		it( 'calls onChange when required toggles', () => {
			const { onChange } = renderSettings( makeField() );
			fireEvent.click( screen.getByLabelText( 'Required' ) );
			expect( onChange.mock.calls[ 0 ][ 0 ].required ).toBe( true );
		} );

		it( 'calls onChange when description changes', () => {
			const { onChange } = renderSettings( makeField() );
			fireEvent.change( screen.getByLabelText( 'Description' ), {
				target: { value: 'Help text' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].description ).toBe(
				'Help text'
			);
		} );
	} );

	describe( 'HTML field', () => {
		it( 'shows Content panel', () => {
			renderSettings( makeField( { type: 'html' } ) );
			expect( screen.getByLabelText( 'HTML Content' ) ).toBeTruthy();
		} );

		it( 'hides placeholder, required, and description', () => {
			renderSettings( makeField( { type: 'html' } ) );
			expect( screen.queryByLabelText( 'Placeholder' ) ).toBeNull();
			expect( screen.queryByLabelText( 'Required' ) ).toBeNull();
			expect( screen.queryByLabelText( 'Description' ) ).toBeNull();
		} );

		it( 'hides Error Messages and ConditionalLogic', () => {
			const { container } = renderSettings(
				makeField( { type: 'html' } )
			);
			expect( screen.queryByLabelText( 'Required Error' ) ).toBeNull();
			expect(
				container.querySelector( '[data-testid="conditional-logic"]' )
			).toBeNull();
		} );

		it( 'calls onChange when content changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'html' } )
			);
			fireEvent.change( screen.getByLabelText( 'HTML Content' ), {
				target: { value: '<p>Hello</p>' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].content ).toBe(
				'<p>Hello</p>'
			);
		} );
	} );

	describe( 'Hidden field', () => {
		it( 'shows Value Source selector', () => {
			renderSettings( makeField( { type: 'hidden' } ) );
			expect( screen.getByLabelText( 'Value Source' ) ).toBeTruthy();
		} );

		it( 'shows Default Value for static source', () => {
			renderSettings( makeField( { type: 'hidden' } ) );
			expect( screen.getByLabelText( 'Default Value' ) ).toBeTruthy();
		} );

		it( 'shows Parameter Name for url_param source', () => {
			renderSettings(
				makeField( { type: 'hidden', value_source: 'url_param' } )
			);
			expect( screen.getByLabelText( 'Parameter Name' ) ).toBeTruthy();
			expect( screen.queryByLabelText( 'Default Value' ) ).toBeNull();
		} );

		it( 'hides placeholder and required', () => {
			renderSettings( makeField( { type: 'hidden' } ) );
			expect( screen.queryByLabelText( 'Placeholder' ) ).toBeNull();
			expect( screen.queryByLabelText( 'Required' ) ).toBeNull();
		} );
	} );

	describe( 'Consent field', () => {
		it( 'shows Agreement Text textarea', () => {
			renderSettings( makeField( { type: 'consent' } ) );
			expect( screen.getByLabelText( 'Agreement Text' ) ).toBeTruthy();
		} );

		it( 'hides Required toggle but shows placeholder and description', () => {
			renderSettings( makeField( { type: 'consent' } ) );
			expect( screen.queryByLabelText( 'Required' ) ).toBeNull();
			expect( screen.getByLabelText( 'Placeholder' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Description' ) ).toBeTruthy();
		} );
	} );

	describe( 'Choice fields (select)', () => {
		const selectField = () =>
			makeField( {
				type: 'select',
				options: [
					{ label: 'Red', value: 'red' },
					{ label: 'Blue', value: 'blue' },
				],
			} );

		it( 'renders Options panel with existing options', () => {
			renderSettings( selectField() );
			expect( screen.getByDisplayValue( 'Red' ) ).toBeTruthy();
			expect( screen.getByDisplayValue( 'Blue' ) ).toBeTruthy();
		} );

		it( 'adds an option on + Add Option click', () => {
			const { onChange } = renderSettings( selectField() );
			fireEvent.click( screen.getByText( '+ Add Option' ) );
			expect( onChange.mock.calls[ 0 ][ 0 ].options ).toHaveLength( 3 );
			expect( onChange.mock.calls[ 0 ][ 0 ].options[ 2 ].label ).toBe(
				'Option 3'
			);
		} );

		it( 'removes an option', () => {
			const { onChange, container } = renderSettings( selectField() );
			const removeBtns = container.querySelectorAll(
				'[aria-label="Remove option"]'
			);
			fireEvent.click( removeBtns[ 0 ] );
			expect( onChange.mock.calls[ 0 ][ 0 ].options ).toHaveLength( 1 );
			expect( onChange.mock.calls[ 0 ][ 0 ].options[ 0 ].label ).toBe(
				'Blue'
			);
		} );

		it( 'disables remove when only one option', () => {
			const field = makeField( {
				type: 'select',
				options: [ { label: 'Only', value: 'only' } ],
			} );
			const { container } = renderSettings( field );
			const removeBtn = container.querySelector(
				'[aria-label="Remove option"]'
			);
			expect( removeBtn.disabled ).toBe( true );
		} );

		it( 'renders Choice Display panel', () => {
			renderSettings( selectField() );
			expect( screen.getByLabelText( 'Columns' ) ).toBeTruthy();
		} );

		it( 'hides Columns selector when layout is horizontal', () => {
			const field = makeField( {
				type: 'select',
				options: [ { label: 'A', value: 'a' } ],
				config: { choice_layout: 'horizontal' },
			} );
			renderSettings( field );
			expect( screen.queryByLabelText( 'Columns' ) ).toBeNull();
		} );
	} );

	describe( 'File upload field', () => {
		it( 'renders File Settings panel', () => {
			renderSettings( makeField( { type: 'file_upload' } ) );
			expect( screen.getByLabelText( 'Allowed Types' ) ).toBeTruthy();
			expect(
				screen.getByLabelText( 'Max File Size (MB)' )
			).toBeTruthy();
		} );

		it( 'defaults max file size to 5', () => {
			renderSettings( makeField( { type: 'file_upload' } ) );
			expect( screen.getByLabelText( 'Max File Size (MB)' ).value ).toBe(
				'5'
			);
		} );
	} );

	describe( 'Date field', () => {
		it( 'renders Date Range panel', () => {
			renderSettings( makeField( { type: 'date' } ) );
			expect( screen.getByLabelText( 'Earliest Date' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Latest Date' ) ).toBeTruthy();
		} );
	} );

	describe( 'Text field validation', () => {
		it( 'renders Validation panel with min/max chars and pattern', () => {
			renderSettings( makeField( { type: 'text' } ) );
			expect( screen.getByLabelText( 'Min Characters' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Max Characters' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Pattern (Regex)' ) ).toBeTruthy();
		} );
	} );

	describe( 'Textarea field validation', () => {
		it( 'renders min/max chars but no pattern', () => {
			renderSettings( makeField( { type: 'textarea' } ) );
			expect( screen.getByLabelText( 'Min Characters' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Max Characters' ) ).toBeTruthy();
			expect( screen.queryByLabelText( 'Pattern (Regex)' ) ).toBeNull();
		} );
	} );

	describe( 'Number field validation', () => {
		it( 'renders min/max value inputs', () => {
			renderSettings( makeField( { type: 'number' } ) );
			expect( screen.getByLabelText( 'Minimum Value' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Maximum Value' ) ).toBeTruthy();
		} );
	} );

	describe( 'Error Messages panel', () => {
		it( 'renders for text fields', () => {
			renderSettings( makeField( { type: 'text' } ) );
			expect( screen.getByLabelText( 'Required Error' ) ).toBeTruthy();
			expect( screen.getByLabelText( 'Format Error' ) ).toBeTruthy();
		} );

		it( 'hidden for html type', () => {
			renderSettings( makeField( { type: 'html' } ) );
			expect( screen.queryByLabelText( 'Required Error' ) ).toBeNull();
		} );

		it( 'hidden for hidden type', () => {
			renderSettings( makeField( { type: 'hidden' } ) );
			expect( screen.queryByLabelText( 'Required Error' ) ).toBeNull();
		} );
	} );

	describe( 'Conditional Logic panel', () => {
		it( 'renders for text field', () => {
			const { container } = renderSettings( makeField() );
			expect(
				container.querySelector( '[data-testid="conditional-logic"]' )
			).toBeTruthy();
		} );

		it( 'hidden for html field', () => {
			const { container } = renderSettings(
				makeField( { type: 'html' } )
			);
			expect(
				container.querySelector( '[data-testid="conditional-logic"]' )
			).toBeNull();
		} );

		it( 'hidden for hidden field', () => {
			const { container } = renderSettings(
				makeField( { type: 'hidden' } )
			);
			expect(
				container.querySelector( '[data-testid="conditional-logic"]' )
			).toBeNull();
		} );
	} );

	describe( 'Sample picker field', () => {
		it( 'renders SamplePickerSettings', () => {
			const { container } = renderSettings(
				makeField( { type: 'sample_picker' } )
			);
			expect(
				container.querySelector(
					'[data-testid="sample-picker-settings"]'
				)
			).toBeTruthy();
		} );
	} );

	describe( 'Row field', () => {
		const rowField = () =>
			makeField( {
				type: 'row',
				columns: [
					{ width: '1fr', fields: [ { id: 'c1f1' } ] },
					{ width: '1fr', fields: [ { id: 'c2f1' } ] },
					{ width: '1fr', fields: [ { id: 'c3f1' } ] },
				],
			} );

		it( 'renders Row Settings header', () => {
			renderSettings( rowField() );
			expect( screen.getByText( 'Row Settings' ) ).toBeTruthy();
		} );

		it( 'renders 4 column presets', () => {
			renderSettings( rowField() );
			expect( screen.getByText( '2 Equal' ) ).toBeTruthy();
			expect( screen.getByText( '3 Equal' ) ).toBeTruthy();
			expect( screen.getByText( '1/3 + 2/3' ) ).toBeTruthy();
			expect( screen.getByText( '2/3 + 1/3' ) ).toBeTruthy();
		} );

		it( 'applies 2 Equal preset and moves orphaned fields to last col', () => {
			const { onChange } = renderSettings( rowField() );
			fireEvent.click( screen.getByText( '2 Equal' ) );
			const update = onChange.mock.calls[ 0 ][ 0 ];
			expect( update.columns ).toHaveLength( 2 );
			expect( update.columns[ 0 ].fields ).toEqual( [ { id: 'c1f1' } ] );
			expect( update.columns[ 1 ].fields ).toEqual( [
				{ id: 'c2f1' },
				{ id: 'c3f1' },
			] );
		} );

		it( 'applies 3 Equal preset preserving existing fields', () => {
			const { onChange } = renderSettings( rowField() );
			fireEvent.click( screen.getByText( '3 Equal' ) );
			const update = onChange.mock.calls[ 0 ][ 0 ];
			expect( update.columns ).toHaveLength( 3 );
			expect( update.columns[ 0 ].fields ).toEqual( [ { id: 'c1f1' } ] );
			expect( update.columns[ 1 ].fields ).toEqual( [ { id: 'c2f1' } ] );
			expect( update.columns[ 2 ].fields ).toEqual( [ { id: 'c3f1' } ] );
		} );

		it( 'does not render General panel or ConditionalLogic', () => {
			renderSettings( rowField() );
			expect( screen.queryByLabelText( 'Label' ) ).toBeNull();
			expect( screen.queryByLabelText( 'Required Error' ) ).toBeNull();
		} );

		it( 'applies 1/3 + 2/3 preset with correct widths', () => {
			const { onChange } = renderSettings( rowField() );
			fireEvent.click( screen.getByText( '1/3 + 2/3' ) );
			const update = onChange.mock.calls[ 0 ][ 0 ];
			expect( update.columns ).toHaveLength( 2 );
			expect( update.columns[ 0 ].width ).toBe( '1fr' );
			expect( update.columns[ 1 ].width ).toBe( '2fr' );
		} );

		it( 'handles row with no existing columns', () => {
			const emptyRow = makeField( { type: 'row' } );
			const { onChange } = renderSettings( emptyRow );
			fireEvent.click( screen.getByText( '2 Equal' ) );
			const update = onChange.mock.calls[ 0 ][ 0 ];
			expect( update.columns ).toHaveLength( 2 );
			expect( update.columns[ 0 ].fields ).toEqual( [] );
			expect( update.columns[ 1 ].fields ).toEqual( [] );
		} );
	} );

	describe( 'Label → key edge cases', () => {
		it( 'keeps existing key when label slugifies to empty', () => {
			const { onChange } = renderSettings( makeField() );
			fireEvent.change( screen.getByLabelText( 'Label' ), {
				target: { value: '---' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].key ).toBe( 'name' );
		} );

		it( 'increments uniqueKey past _2 when _2 is taken', () => {
			const fields = [
				makeField( { id: 'f2', key: 'full_name' } ),
				makeField( { id: 'f3', key: 'full_name_2' } ),
			];
			const { onChange } = renderSettings( makeField(), { fields } );
			fireEvent.change( screen.getByLabelText( 'Label' ), {
				target: { value: 'Full Name' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].key ).toBe( 'full_name_3' );
		} );

		it( 'keeps raw value when manual key slugifies to empty', () => {
			const { onChange } = renderSettings( makeField() );
			fireEvent.change( screen.getByLabelText( 'Field Key' ), {
				target: { value: '!!!' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].key ).toBe( '!!!' );
		} );
	} );

	describe( 'Choice fields: callbacks and variants', () => {
		it( 'updates option label via onChange', () => {
			const field = makeField( {
				type: 'select',
				options: [ { label: 'Red', value: 'red' } ],
			} );
			const { onChange } = renderSettings( field );
			fireEvent.change( screen.getByDisplayValue( 'Red' ), {
				target: { value: 'Green' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].options[ 0 ].label ).toBe(
				'Green'
			);
		} );

		it( 'adds option on Enter in option text field', () => {
			const field = makeField( {
				type: 'select',
				options: [ { label: 'A', value: 'a' } ],
			} );
			const { onChange } = renderSettings( field );
			fireEvent.keyDown( screen.getByDisplayValue( 'A' ), {
				key: 'Enter',
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].options ).toHaveLength( 2 );
		} );

		it( 'adds option on Enter in radio option field', () => {
			const field = makeField( {
				type: 'radio',
				options: [ { label: 'Yes', value: 'yes' } ],
			} );
			const { onChange } = renderSettings( field );
			fireEvent.keyDown( screen.getByDisplayValue( 'Yes' ), {
				key: 'Enter',
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].options ).toHaveLength( 2 );
		} );

		it( 'adds option on Enter in checkbox option field', () => {
			const field = makeField( {
				type: 'checkbox',
				options: [ { label: 'Agree', value: 'agree' } ],
			} );
			const { onChange } = renderSettings( field );
			fireEvent.keyDown( screen.getByDisplayValue( 'Agree' ), {
				key: 'Enter',
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].options ).toHaveLength( 2 );
		} );

		it( 'renders Options panel for radio type', () => {
			const field = makeField( {
				type: 'radio',
				options: [ { label: 'Yes', value: 'yes' } ],
			} );
			renderSettings( field );
			expect( screen.getByDisplayValue( 'Yes' ) ).toBeTruthy();
			expect( screen.getByText( '+ Add Option' ) ).toBeTruthy();
		} );

		it( 'renders Options panel for checkbox type', () => {
			const field = makeField( {
				type: 'checkbox',
				options: [ { label: 'Agree', value: 'agree' } ],
			} );
			renderSettings( field );
			expect( screen.getByDisplayValue( 'Agree' ) ).toBeTruthy();
		} );

		it( 'calls onChange when choice layout changes', () => {
			const field = makeField( {
				type: 'select',
				options: [ { label: 'A', value: 'a' } ],
			} );
			const { onChange } = renderSettings( field );
			const layoutSelects = screen.getAllByLabelText( 'Layout' );
			const choiceLayout = layoutSelects.find(
				( el ) => el.value === 'vertical' || el.value === 'horizontal'
			);
			fireEvent.change( choiceLayout, {
				target: { value: 'horizontal' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].config.choice_layout ).toBe(
				'horizontal'
			);
		} );

		it( 'calls onChange when choice columns changes', () => {
			const field = makeField( {
				type: 'select',
				options: [ { label: 'A', value: 'a' } ],
			} );
			const { onChange } = renderSettings( field );
			fireEvent.change( screen.getByLabelText( 'Columns' ), {
				target: { value: '2' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].config.choice_columns ).toBe(
				2
			);
		} );
	} );

	describe( 'Hidden field: callbacks', () => {
		it( 'hides both sub-controls for current_url source', () => {
			renderSettings(
				makeField( { type: 'hidden', value_source: 'current_url' } )
			);
			expect( screen.queryByLabelText( 'Default Value' ) ).toBeNull();
			expect( screen.queryByLabelText( 'Parameter Name' ) ).toBeNull();
		} );

		it( 'calls onChange when value source changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'hidden' } )
			);
			fireEvent.change( screen.getByLabelText( 'Value Source' ), {
				target: { value: 'url_param' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].value_source ).toBe(
				'url_param'
			);
		} );

		it( 'calls onChange when default value changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'hidden' } )
			);
			fireEvent.change( screen.getByLabelText( 'Default Value' ), {
				target: { value: 'abc' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].default_value ).toBe( 'abc' );
		} );

		it( 'calls onChange when parameter name changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'hidden', value_source: 'url_param' } )
			);
			fireEvent.change( screen.getByLabelText( 'Parameter Name' ), {
				target: { value: 'utm_source' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].url_param ).toBe(
				'utm_source'
			);
		} );

		it( 'hides Description for hidden type', () => {
			renderSettings( makeField( { type: 'hidden' } ) );
			expect( screen.queryByLabelText( 'Description' ) ).toBeNull();
		} );
	} );

	describe( 'Consent field: callbacks', () => {
		it( 'calls onChange when agreement text changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'consent' } )
			);
			fireEvent.change( screen.getByLabelText( 'Agreement Text' ), {
				target: { value: 'I agree' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].consent_text ).toBe(
				'I agree'
			);
		} );

		it( 'renders Error Messages and ConditionalLogic panels', () => {
			const { container } = renderSettings(
				makeField( { type: 'consent' } )
			);
			expect( screen.getByLabelText( 'Required Error' ) ).toBeTruthy();
			expect(
				container.querySelector( '[data-testid="conditional-logic"]' )
			).toBeTruthy();
		} );
	} );

	describe( 'File upload: callbacks', () => {
		it( 'calls onChange when allowed types changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'file_upload' } )
			);
			fireEvent.change( screen.getByLabelText( 'Allowed Types' ), {
				target: { value: 'documents' },
			} );
			expect(
				onChange.mock.calls[ 0 ][ 0 ].validation.allowed_type_group
			).toBe( 'documents' );
		} );

		it( 'calls onChange when max file size changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'file_upload' } )
			);
			fireEvent.change( screen.getByLabelText( 'Max File Size (MB)' ), {
				target: { value: '10' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].validation.max_size_mb ).toBe(
				10
			);
		} );
	} );

	describe( 'Date: callbacks', () => {
		it( 'calls onChange when earliest date changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'date' } )
			);
			fireEvent.change( screen.getByLabelText( 'Earliest Date' ), {
				target: { value: '2026-01-01' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].validation.min ).toBe(
				'2026-01-01'
			);
		} );

		it( 'calls onChange when latest date changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'date' } )
			);
			fireEvent.change( screen.getByLabelText( 'Latest Date' ), {
				target: { value: '2026-12-31' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].validation.max ).toBe(
				'2026-12-31'
			);
		} );
	} );

	describe( 'Text validation: callbacks', () => {
		it( 'calls onChange when min characters changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'text' } )
			);
			fireEvent.change( screen.getByLabelText( 'Min Characters' ), {
				target: { value: '5' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].validation.min_length ).toBe(
				5
			);
		} );

		it( 'sets min_length to undefined when cleared', () => {
			const { onChange } = renderSettings(
				makeField( {
					type: 'text',
					validation: { min_length: 5 },
				} )
			);
			fireEvent.change( screen.getByLabelText( 'Min Characters' ), {
				target: { value: '' },
			} );
			expect(
				onChange.mock.calls[ 0 ][ 0 ].validation.min_length
			).toBeUndefined();
		} );

		it( 'sets max_length to undefined when cleared', () => {
			const { onChange } = renderSettings(
				makeField( {
					type: 'text',
					validation: { max_length: 100 },
				} )
			);
			fireEvent.change( screen.getByLabelText( 'Max Characters' ), {
				target: { value: '' },
			} );
			expect(
				onChange.mock.calls[ 0 ][ 0 ].validation.max_length
			).toBeUndefined();
		} );

		it( 'calls onChange when pattern changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'text' } )
			);
			fireEvent.change( screen.getByLabelText( 'Pattern (Regex)' ), {
				target: { value: '[A-Z]+' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].validation.pattern ).toBe(
				'[A-Z]+'
			);
		} );
	} );

	describe( 'Number validation: callbacks', () => {
		it( 'calls onChange when minimum value changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'number' } )
			);
			fireEvent.change( screen.getByLabelText( 'Minimum Value' ), {
				target: { value: '0' },
			} );
			expect( onChange.mock.calls[ 0 ][ 0 ].validation.min ).toBe( 0 );
		} );

		it( 'sets min to undefined when cleared', () => {
			const { onChange } = renderSettings(
				makeField( {
					type: 'number',
					validation: { min: 10 },
				} )
			);
			fireEvent.change( screen.getByLabelText( 'Minimum Value' ), {
				target: { value: '' },
			} );
			expect(
				onChange.mock.calls[ 0 ][ 0 ].validation.min
			).toBeUndefined();
		} );

		it( 'sets max to undefined when cleared', () => {
			const { onChange } = renderSettings(
				makeField( {
					type: 'number',
					validation: { max: 50 },
				} )
			);
			fireEvent.change( screen.getByLabelText( 'Maximum Value' ), {
				target: { value: '' },
			} );
			expect(
				onChange.mock.calls[ 0 ][ 0 ].validation.max
			).toBeUndefined();
		} );
	} );

	describe( 'Error Messages: callbacks', () => {
		it( 'calls onChange when required error changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'text' } )
			);
			fireEvent.change( screen.getByLabelText( 'Required Error' ), {
				target: { value: 'Please fill this' },
			} );
			expect(
				onChange.mock.calls[ 0 ][ 0 ].validation.required_message
			).toBe( 'Please fill this' );
		} );

		it( 'calls onChange when format error changes', () => {
			const { onChange } = renderSettings(
				makeField( { type: 'text' } )
			);
			fireEvent.change( screen.getByLabelText( 'Format Error' ), {
				target: { value: 'Invalid format' },
			} );
			expect(
				onChange.mock.calls[ 0 ][ 0 ].validation.format_message
			).toBe( 'Invalid format' );
		} );
	} );
} );
