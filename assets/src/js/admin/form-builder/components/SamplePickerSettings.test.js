import { render, screen, fireEvent, act } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import SamplePickerSettings from './SamplePickerSettings';

const baseField = { config: {} };

const mockCategories = [
	{ id: 1, name: 'Fabric' },
	{ id: 2, name: 'Wood' },
];

const mockSamples = [
	{ id: 10, name: 'Oak Panel', sku: 'OAK-01' },
	{ id: 11, name: 'Maple Board', sku: '' },
];

function resolveApiFetch( cats = [], samps = [] ) {
	apiFetch.mockImplementation( ( { path } ) => {
		if ( path.includes( 'categories' ) ) {
			return Promise.resolve( cats );
		}
		return Promise.resolve( samps );
	} );
}

function setWooEnabled( enabled ) {
	const el = document.createElement( 'div' );
	el.id = 'shqf-form-builder-root';
	if ( enabled ) {
		el.dataset.wooEnabled = '1';
	}
	document.body.appendChild( el );
	return el;
}

describe( 'SamplePickerSettings', () => {
	beforeEach( () => {
		resolveApiFetch();
		const existing = document.getElementById( 'shqf-form-builder-root' );
		if ( existing ) {
			existing.remove();
		}
	} );

	it( 'renders Display panel with defaults', async () => {
		await act( async () => {
			render(
				<SamplePickerSettings
					field={ baseField }
					onChange={ jest.fn() }
				/>
			);
		} );
		expect( screen.getByLabelText( 'Layout' ) ).toBeTruthy();
		expect( screen.getByLabelText( 'Max Selections' ) ).toBeTruthy();
		expect( screen.getByLabelText( 'Allow Quantity' ) ).toBeTruthy();
		expect( screen.getByLabelText( 'Show Images' ) ).toBeTruthy();
		expect( screen.getByLabelText( 'Show Descriptions' ) ).toBeTruthy();
	} );

	it( 'renders Sample Source panel when source is library', async () => {
		await act( async () => {
			render(
				<SamplePickerSettings
					field={ baseField }
					onChange={ jest.fn() }
				/>
			);
		} );
		expect( screen.getByLabelText( 'Show Samples' ) ).toBeTruthy();
	} );

	it( 'does not show Product Source when woo is disabled', async () => {
		await act( async () => {
			render(
				<SamplePickerSettings
					field={ baseField }
					onChange={ jest.fn() }
				/>
			);
		} );
		expect( screen.queryByLabelText( 'Load products from' ) ).toBeNull();
	} );

	it( 'shows Product Source panel when woo is enabled', async () => {
		const el = setWooEnabled( true );
		await act( async () => {
			render(
				<SamplePickerSettings
					field={ baseField }
					onChange={ jest.fn() }
				/>
			);
		} );
		expect( screen.getByLabelText( 'Load products from' ) ).toBeTruthy();
		el.remove();
	} );

	it( 'hides Sample Source when source is woocommerce', async () => {
		const el = setWooEnabled( true );
		const field = { config: { source: 'woocommerce' } };
		await act( async () => {
			render(
				<SamplePickerSettings field={ field } onChange={ jest.fn() } />
			);
		} );
		expect( screen.queryByLabelText( 'Show Samples' ) ).toBeNull();
		el.remove();
	} );

	it( 'calls onChange when layout is changed', async () => {
		const onChange = jest.fn();
		await act( async () => {
			render(
				<SamplePickerSettings
					field={ baseField }
					onChange={ onChange }
				/>
			);
		} );
		fireEvent.change( screen.getByLabelText( 'Layout' ), {
			target: { value: 'list' },
		} );
		expect( onChange ).toHaveBeenCalled();
		const updated = onChange.mock.calls[ 0 ][ 0 ];
		expect( updated.config.layout ).toBe( 'list' );
	} );

	it( 'shows categories checklist in categories mode', async () => {
		resolveApiFetch( mockCategories, [] );
		const field = {
			config: { filter: { mode: 'categories' } },
		};
		await act( async () => {
			render(
				<SamplePickerSettings field={ field } onChange={ jest.fn() } />
			);
		} );
		expect( screen.getByText( 'Select categories:' ) ).toBeTruthy();
		expect( screen.getByText( 'Fabric' ) ).toBeTruthy();
		expect( screen.getByText( 'Wood' ) ).toBeTruthy();
	} );

	it( 'shows empty message when no categories exist', async () => {
		resolveApiFetch( [], [] );
		const field = {
			config: { filter: { mode: 'categories' } },
		};
		await act( async () => {
			render(
				<SamplePickerSettings field={ field } onChange={ jest.fn() } />
			);
		} );
		expect(
			screen.getByText(
				'No categories found. Create categories in the Samples section.'
			)
		).toBeTruthy();
	} );

	it( 'shows samples checklist in selected mode', async () => {
		resolveApiFetch( [], mockSamples );
		const field = {
			config: { filter: { mode: 'selected' } },
		};
		await act( async () => {
			render(
				<SamplePickerSettings field={ field } onChange={ jest.fn() } />
			);
		} );
		expect( screen.getByText( 'Select samples:' ) ).toBeTruthy();
		expect( screen.getByText( 'Oak Panel' ) ).toBeTruthy();
	} );

	it( 'shows SKU when sample has one', async () => {
		resolveApiFetch( [], mockSamples );
		const field = {
			config: { filter: { mode: 'selected' } },
		};
		const { container } = await act( async () =>
			render(
				<SamplePickerSettings field={ field } onChange={ jest.fn() } />
			)
		);
		expect(
			container.querySelector( '.shqf-settings-sku' ).textContent
		).toBe( 'OAK-01' );
	} );

	it( 'toggles category selection via onChange', async () => {
		resolveApiFetch( mockCategories, [] );
		const onChange = jest.fn();
		const field = {
			config: { filter: { mode: 'categories', category_ids: [] } },
		};
		await act( async () => {
			render(
				<SamplePickerSettings field={ field } onChange={ onChange } />
			);
		} );
		const checkbox = screen.getByLabelText( 'Fabric' );
		fireEvent.click( checkbox );
		expect( onChange ).toHaveBeenCalled();
		const updated = onChange.mock.calls[ 0 ][ 0 ];
		expect( updated.config.filter.category_ids ).toContain( 1 );
	} );

	it( 'shows Max Quantity Per Sample when allow_quantity is true', async () => {
		await act( async () => {
			render(
				<SamplePickerSettings
					field={ baseField }
					onChange={ jest.fn() }
				/>
			);
		} );
		expect(
			screen.getByLabelText( 'Max Quantity Per Sample' )
		).toBeTruthy();
	} );

	it( 'hides Max Quantity Per Sample when allow_quantity is false', async () => {
		const field = { config: { allow_quantity: false } };
		await act( async () => {
			render(
				<SamplePickerSettings field={ field } onChange={ jest.fn() } />
			);
		} );
		expect(
			screen.queryByLabelText( 'Max Quantity Per Sample' )
		).toBeNull();
	} );

	it( 'handles API error gracefully', async () => {
		apiFetch.mockImplementation( () =>
			Promise.reject( new Error( 'fail' ) )
		);
		const field = { config: { filter: { mode: 'categories' } } };
		await act( async () => {
			render(
				<SamplePickerSettings field={ field } onChange={ jest.fn() } />
			);
		} );
		expect(
			screen.getByText(
				'No categories found. Create categories in the Samples section.'
			)
		).toBeTruthy();
	} );

	it( 'toggles sample selection via onChange', async () => {
		resolveApiFetch( [], mockSamples );
		const onChange = jest.fn();
		const field = {
			config: { filter: { mode: 'selected', sample_ids: [] } },
		};
		await act( async () => {
			render(
				<SamplePickerSettings field={ field } onChange={ onChange } />
			);
		} );
		const checkbox = document.getElementById( 'shqf-sample-10' );
		fireEvent.click( checkbox );
		expect( onChange ).toHaveBeenCalled();
		const updated = onChange.mock.calls[ 0 ][ 0 ];
		expect( updated.config.filter.sample_ids ).toContain( 10 );
	} );

	it( 'deselects a category that is already selected', async () => {
		resolveApiFetch( mockCategories, [] );
		const onChange = jest.fn();
		const field = {
			config: { filter: { mode: 'categories', category_ids: [ 1 ] } },
		};
		await act( async () => {
			render(
				<SamplePickerSettings field={ field } onChange={ onChange } />
			);
		} );
		fireEvent.click( screen.getByLabelText( 'Fabric' ) );
		const updated = onChange.mock.calls[ 0 ][ 0 ];
		expect( updated.config.filter.category_ids ).not.toContain( 1 );
	} );

	it( 'shows empty samples message when no samples exist', async () => {
		resolveApiFetch( [], [] );
		const field = { config: { filter: { mode: 'selected' } } };
		await act( async () => {
			render(
				<SamplePickerSettings field={ field } onChange={ jest.fn() } />
			);
		} );
		expect(
			screen.getByText(
				'No samples found. Add samples in the Sample Library.'
			)
		).toBeTruthy();
	} );

	it( 'calls onChange when filter mode changes', async () => {
		const onChange = jest.fn();
		await act( async () => {
			render(
				<SamplePickerSettings
					field={ baseField }
					onChange={ onChange }
				/>
			);
		} );
		fireEvent.change( screen.getByLabelText( 'Show Samples' ), {
			target: { value: 'categories' },
		} );
		expect( onChange ).toHaveBeenCalled();
		const updated = onChange.mock.calls[ 0 ][ 0 ];
		expect( updated.config.filter.mode ).toBe( 'categories' );
	} );

	it( 'calls onChange when Allow Quantity is toggled', async () => {
		const onChange = jest.fn();
		await act( async () => {
			render(
				<SamplePickerSettings
					field={ baseField }
					onChange={ onChange }
				/>
			);
		} );
		fireEvent.click( screen.getByLabelText( 'Allow Quantity' ) );
		expect( onChange ).toHaveBeenCalled();
		const updated = onChange.mock.calls[ 0 ][ 0 ];
		expect( updated.config.allow_quantity ).toBe( false );
	} );

	it( 'calls onChange when Show Images is toggled', async () => {
		const onChange = jest.fn();
		await act( async () => {
			render(
				<SamplePickerSettings
					field={ baseField }
					onChange={ onChange }
				/>
			);
		} );
		fireEvent.click( screen.getByLabelText( 'Show Images' ) );
		expect( onChange ).toHaveBeenCalled();
		const updated = onChange.mock.calls[ 0 ][ 0 ];
		expect( updated.config.show_images ).toBe( false );
	} );

	it( 'calls onChange when Show Descriptions is toggled', async () => {
		const onChange = jest.fn();
		await act( async () => {
			render(
				<SamplePickerSettings
					field={ baseField }
					onChange={ onChange }
				/>
			);
		} );
		fireEvent.click( screen.getByLabelText( 'Show Descriptions' ) );
		expect( onChange ).toHaveBeenCalled();
		const updated = onChange.mock.calls[ 0 ][ 0 ];
		expect( updated.config.show_descriptions ).toBe( false );
	} );

	it( 'fetches categories and samples on mount', async () => {
		resolveApiFetch( mockCategories, mockSamples );
		await act( async () => {
			render(
				<SamplePickerSettings
					field={ baseField }
					onChange={ jest.fn() }
				/>
			);
		} );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/samplehq-form/v1/categories',
			} )
		);
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/samplehq-form/v1/samples?status=active&per_page=100',
			} )
		);
	} );
} );
