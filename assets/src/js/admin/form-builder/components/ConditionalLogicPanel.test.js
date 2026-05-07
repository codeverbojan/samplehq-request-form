import { render, screen, fireEvent } from '@testing-library/react';
import ConditionalLogicPanel from './ConditionalLogicPanel';

const otherFields = [
	{ id: 'f1', type: 'text', label: 'Name', key: 'name' },
	{ id: 'f2', type: 'email', label: 'Email', key: 'email' },
	{ id: 'f3', type: 'html', label: 'HTML Block', key: 'html_1' },
	{ id: 'f4', type: 'hidden', label: 'Ref', key: 'ref' },
];

const baseField = { id: 'f5', type: 'text', label: 'City', key: 'city' };

describe( 'ConditionalLogicPanel', () => {
	it( 'renders empty state with add button', () => {
		render(
			<ConditionalLogicPanel
				field={ baseField }
				allFields={ otherFields }
				onChange={ jest.fn() }
			/>
		);
		expect(
			screen.getByText(
				'Show or hide this field based on other field values.'
			)
		).toBeTruthy();
		expect( screen.getByText( '+ Add Condition' ) ).toBeTruthy();
	} );

	it( 'disables add button when no eligible fields exist', () => {
		const htmlOnly = [
			{ id: 'f1', type: 'html', label: 'Block', key: 'html_1' },
		];
		const { container } = render(
			<ConditionalLogicPanel
				field={ baseField }
				allFields={ htmlOnly }
				onChange={ jest.fn() }
			/>
		);
		const btn = container.querySelector( '[data-testid="Button"]' );
		expect( btn.disabled ).toBe( true );
		expect(
			screen.getByText( 'Add other fields to the form first.' )
		).toBeTruthy();
	} );

	it( 'excludes html and hidden fields from field options', () => {
		const onChange = jest.fn();
		render(
			<ConditionalLogicPanel
				field={ baseField }
				allFields={ otherFields }
				onChange={ onChange }
			/>
		);
		fireEvent.click( screen.getByText( '+ Add Condition' ) );
		const call = onChange.mock.calls[ 0 ][ 0 ];
		const rule = call.conditions.rules[ 0 ];
		expect( rule.field_key ).toBe( 'name' );
	} );

	it( 'excludes the current field from options', () => {
		const selfField = {
			id: 'f1',
			type: 'text',
			label: 'Name',
			key: 'name',
		};
		const onChange = jest.fn();
		render(
			<ConditionalLogicPanel
				field={ selfField }
				allFields={ otherFields }
				onChange={ onChange }
			/>
		);
		fireEvent.click( screen.getByText( '+ Add Condition' ) );
		const call = onChange.mock.calls[ 0 ][ 0 ];
		expect( call.conditions.rules[ 0 ].field_key ).toBe( 'email' );
	} );

	it( 'adds a rule with default values on click', () => {
		const onChange = jest.fn();
		render(
			<ConditionalLogicPanel
				field={ baseField }
				allFields={ otherFields }
				onChange={ onChange }
			/>
		);
		fireEvent.click( screen.getByText( '+ Add Condition' ) );
		const call = onChange.mock.calls[ 0 ][ 0 ];
		expect( call.conditions.rules ).toHaveLength( 1 );
		expect( call.conditions.rules[ 0 ].operator ).toBe( 'equals' );
		expect( call.conditions.rules[ 0 ].value ).toBe( '' );
	} );

	it( 'renders rules when conditions exist', () => {
		const field = {
			...baseField,
			conditions: {
				logic: 'all',
				rules: [
					{
						field_key: 'name',
						operator: 'equals',
						value: 'test',
					},
				],
			},
		};
		const { container } = render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ jest.fn() }
			/>
		);
		expect(
			container.querySelector( '.shqf-condition-rule' )
		).toBeTruthy();
		expect( screen.getByText( 'Show this field when' ) ).toBeTruthy();
	} );

	it( 'renders logic selector with all/any options', () => {
		const field = {
			...baseField,
			conditions: {
				logic: 'any',
				rules: [ { field_key: 'name', operator: 'equals', value: '' } ],
			},
		};
		render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ jest.fn() }
			/>
		);
		expect( screen.getByText( 'of these rules match:' ) ).toBeTruthy();
	} );

	it( 'removes a rule and clears conditions when last rule removed', () => {
		const onChange = jest.fn();
		const field = {
			...baseField,
			conditions: {
				logic: 'all',
				rules: [ { field_key: 'name', operator: 'equals', value: '' } ],
			},
		};
		render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ onChange }
			/>
		);
		const removeBtn = screen.getByLabelText( 'Remove rule' );
		fireEvent.click( removeBtn );
		const call = onChange.mock.calls[ 0 ][ 0 ];
		expect( call.conditions ).toEqual( {} );
	} );

	it( 'keeps remaining rules when one is removed', () => {
		const onChange = jest.fn();
		const field = {
			...baseField,
			conditions: {
				logic: 'all',
				rules: [
					{ field_key: 'name', operator: 'equals', value: 'a' },
					{
						field_key: 'email',
						operator: 'contains',
						value: 'b',
					},
				],
			},
		};
		const { container } = render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ onChange }
			/>
		);
		const removeBtns = container.querySelectorAll(
			'[aria-label="Remove rule"]'
		);
		fireEvent.click( removeBtns[ 0 ] );
		const call = onChange.mock.calls[ 0 ][ 0 ];
		expect( call.conditions.rules ).toHaveLength( 1 );
		expect( call.conditions.rules[ 0 ].field_key ).toBe( 'email' );
	} );

	it( 'clears all conditions via Remove All', () => {
		const onChange = jest.fn();
		const field = {
			...baseField,
			conditions: {
				logic: 'all',
				rules: [ { field_key: 'name', operator: 'equals', value: '' } ],
			},
		};
		render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ onChange }
			/>
		);
		fireEvent.click( screen.getByText( 'Remove All' ) );
		const call = onChange.mock.calls[ 0 ][ 0 ];
		expect( call.conditions ).toEqual( {} );
	} );

	it( 'adds another rule via + Add Rule', () => {
		const onChange = jest.fn();
		const field = {
			...baseField,
			conditions: {
				logic: 'all',
				rules: [ { field_key: 'name', operator: 'equals', value: '' } ],
			},
		};
		render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ onChange }
			/>
		);
		fireEvent.click( screen.getByText( '+ Add Rule' ) );
		const call = onChange.mock.calls[ 0 ][ 0 ];
		expect( call.conditions.rules ).toHaveLength( 2 );
	} );

	it( 'hides value input for empty/not_empty operators', () => {
		const field = {
			...baseField,
			conditions: {
				logic: 'all',
				rules: [
					{
						field_key: 'name',
						operator: 'empty',
						value: '',
					},
				],
			},
		};
		const { container } = render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ jest.fn() }
			/>
		);
		const textInputs = container.querySelectorAll(
			'[data-testid="TextControl"]'
		);
		expect( textInputs ).toHaveLength( 0 );
	} );

	it( 'hides value input for not_empty operator', () => {
		const field = {
			...baseField,
			conditions: {
				logic: 'all',
				rules: [
					{ field_key: 'name', operator: 'not_empty', value: '' },
				],
			},
		};
		const { container } = render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ jest.fn() }
			/>
		);
		expect(
			container.querySelectorAll( '[data-testid="TextControl"]' )
		).toHaveLength( 0 );
	} );

	it( 'updates logic via selector onChange', () => {
		const onChange = jest.fn();
		const field = {
			...baseField,
			conditions: {
				logic: 'all',
				rules: [ { field_key: 'name', operator: 'equals', value: '' } ],
			},
		};
		const { container } = render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ onChange }
			/>
		);
		const selects = container.querySelectorAll(
			'[data-testid="SelectControl"]'
		);
		const logicSelect = selects[ 0 ];
		fireEvent.change( logicSelect, { target: { value: 'any' } } );
		expect( onChange ).toHaveBeenCalled();
		const call = onChange.mock.calls[ 0 ][ 0 ];
		expect( call.conditions.logic ).toBe( 'any' );
	} );

	it( 'updates rule operator via onChange', () => {
		const onChange = jest.fn();
		const field = {
			...baseField,
			conditions: {
				logic: 'all',
				rules: [ { field_key: 'name', operator: 'equals', value: '' } ],
			},
		};
		const { container } = render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ onChange }
			/>
		);
		const ruleSelects = container
			.querySelector( '.shqf-condition-rule' )
			.querySelectorAll( '[data-testid="SelectControl"]' );
		fireEvent.change( ruleSelects[ 1 ], {
			target: { value: 'contains' },
		} );
		expect( onChange ).toHaveBeenCalled();
		const call = onChange.mock.calls[ 0 ][ 0 ];
		expect( call.conditions.rules[ 0 ].operator ).toBe( 'contains' );
	} );

	it( 'updates rule value via TextControl onChange', () => {
		const onChange = jest.fn();
		const field = {
			...baseField,
			conditions: {
				logic: 'all',
				rules: [ { field_key: 'name', operator: 'equals', value: '' } ],
			},
		};
		const { container } = render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ onChange }
			/>
		);
		const valueInput = container.querySelector(
			'[data-testid="TextControl"]'
		);
		fireEvent.change( valueInput, { target: { value: 'hello' } } );
		expect( onChange ).toHaveBeenCalled();
		const call = onChange.mock.calls[ 0 ][ 0 ];
		expect( call.conditions.rules[ 0 ].value ).toBe( 'hello' );
	} );

	it( 'shows value input for equals operator', () => {
		const field = {
			...baseField,
			conditions: {
				logic: 'all',
				rules: [
					{
						field_key: 'name',
						operator: 'equals',
						value: 'test',
					},
				],
			},
		};
		const { container } = render(
			<ConditionalLogicPanel
				field={ field }
				allFields={ otherFields }
				onChange={ jest.fn() }
			/>
		);
		const textInputs = container.querySelectorAll(
			'[data-testid="TextControl"]'
		);
		expect( textInputs ).toHaveLength( 1 );
	} );
} );
