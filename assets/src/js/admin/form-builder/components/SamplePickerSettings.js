import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

export default function SamplePickerSettings( { field, onChange } ) {
	const [ categories, setCategories ] = useState( [] );
	const [ samples, setSamples ] = useState( [] );
	const [ loading, setLoading ] = useState( false );

	const config = field.config || {};
	const filter = config.filter || {};
	const mode = filter.mode || 'all';
	const source = config.source || 'library';

	// Check if WooCommerce integration is enabled (set via data attribute on root).
	const rootEl = document.getElementById( 'shqf-form-builder-root' );
	const wooEnabled = rootEl?.dataset.wooEnabled === '1';

	// Fetch categories and samples on mount.
	useEffect( () => {
		setLoading( true );
		Promise.all( [
			apiFetch( { path: '/samplehq-form/v1/categories' } ).catch(
				() => []
			),
			apiFetch( {
				path: '/samplehq-form/v1/samples?status=active&per_page=100',
			} ).catch( () => [] ),
		] ).then( ( [ cats, samps ] ) => {
			setCategories( Array.isArray( cats ) ? cats : [] );
			setSamples( Array.isArray( samps ) ? samps : [] );
			setLoading( false );
		} );
	}, [] );

	const updateConfig = ( updates ) => {
		onChange( { ...field, config: { ...config, ...updates } } );
	};

	const updateFilter = ( updates ) => {
		updateConfig( { filter: { ...filter, ...updates } } );
	};

	const toggleArrayItem = ( arr, id ) => {
		const numId = parseInt( id, 10 );
		return arr.includes( numId )
			? arr.filter( ( v ) => v !== numId )
			: [ ...arr, numId ];
	};

	return (
		<>
			{ wooEnabled && (
				<PanelBody
					title={ __( 'Product Source', 'samplehq-request-form' ) }
					initialOpen
				>
					<SelectControl
						label={ __(
							'Load products from',
							'samplehq-request-form'
						) }
						value={ source }
						options={ [
							{
								label: __(
									'Sample Library',
									'samplehq-request-form'
								),
								value: 'library',
							},
							{
								label: __(
									'WooCommerce Products',
									'samplehq-request-form'
								),
								value: 'woocommerce',
							},
						] }
						onChange={ ( val ) =>
							onChange( {
								...field,
								config: { ...config, source: val },
							} )
						}
						help={
							source === 'woocommerce'
								? __(
										'Products are loaded from your WooCommerce catalog.',
										'samplehq-request-form'
								  )
								: __(
										'Samples are loaded from the plugin sample library.',
										'samplehq-request-form'
								  )
						}
					/>
				</PanelBody>
			) }

			{ source !== 'woocommerce' && (
				<PanelBody
					title={ __( 'Sample Source', 'samplehq-request-form' ) }
					initialOpen
				>
					<SelectControl
						label={ __( 'Show Samples', 'samplehq-request-form' ) }
						value={ mode }
						options={ [
							{
								label: __(
									'All active samples',
									'samplehq-request-form'
								),
								value: 'all',
							},
							{
								label: __(
									'From specific categories',
									'samplehq-request-form'
								),
								value: 'categories',
							},
							{
								label: __(
									'Specific samples only',
									'samplehq-request-form'
								),
								value: 'selected',
							},
						] }
						onChange={ ( val ) => updateFilter( { mode: val } ) }
					/>

					{ mode === 'categories' && (
						<div className="shqf-settings-checklist">
							<p className="shqf-settings-checklist-label">
								{ __(
									'Select categories:',
									'samplehq-request-form'
								) }
							</p>
							{ loading && (
								<p className="description">
									{ __(
										'Loading…',
										'samplehq-request-form'
									) }
								</p>
							) }
							{ ! loading && categories.length === 0 && (
								<p className="description">
									{ __(
										'No categories found. Create categories in the Samples section.',
										'samplehq-request-form'
									) }
								</p>
							) }
							<div className="shqf-settings-checklist-items">
								{ categories.map( ( cat ) => {
									const catId = parseInt( cat.id, 10 );
									const checked = (
										filter.category_ids || []
									).includes( catId );
									const inputId = `shqf-cat-${ catId }`;
									return (
										<label
											key={ catId }
											htmlFor={ inputId }
											className="shqf-settings-checklist-item"
										>
											<input
												id={ inputId }
												type="checkbox"
												checked={ checked }
												onChange={ () =>
													updateFilter( {
														category_ids:
															toggleArrayItem(
																filter.category_ids ||
																	[],
																catId
															),
													} )
												}
											/>
											{ cat.name }
										</label>
									);
								} ) }
							</div>
						</div>
					) }

					{ mode === 'selected' && (
						<div className="shqf-settings-checklist">
							<p className="shqf-settings-checklist-label">
								{ __(
									'Select samples:',
									'samplehq-request-form'
								) }
							</p>
							{ loading && (
								<p className="description">
									{ __(
										'Loading…',
										'samplehq-request-form'
									) }
								</p>
							) }
							{ ! loading && samples.length === 0 && (
								<p className="description">
									{ __(
										'No samples found. Add samples in the Sample Library.',
										'samplehq-request-form'
									) }
								</p>
							) }
							<div className="shqf-settings-checklist-items">
								{ samples.map( ( sample ) => {
									const sId = parseInt( sample.id, 10 );
									const checked = (
										filter.sample_ids || []
									).includes( sId );
									const sInputId = `shqf-sample-${ sId }`;
									return (
										<label
											key={ sId }
											htmlFor={ sInputId }
											className="shqf-settings-checklist-item"
										>
											<input
												id={ sInputId }
												type="checkbox"
												checked={ checked }
												onChange={ () =>
													updateFilter( {
														sample_ids:
															toggleArrayItem(
																filter.sample_ids ||
																	[],
																sId
															),
													} )
												}
											/>
											{ sample.name }
											{ sample.sku && (
												<span className="shqf-settings-sku">
													{ sample.sku }
												</span>
											) }
										</label>
									);
								} ) }
							</div>
						</div>
					) }
				</PanelBody>
			) }

			<PanelBody
				title={ __( 'Display', 'samplehq-request-form' ) }
				initialOpen
			>
				<SelectControl
					label={ __( 'Layout', 'samplehq-request-form' ) }
					value={ config.layout || 'grid' }
					options={ [
						{
							label: __( 'Card Grid', 'samplehq-request-form' ),
							value: 'grid',
						},
						{
							label: __( 'Checklist', 'samplehq-request-form' ),
							value: 'list',
						},
					] }
					onChange={ ( val ) => updateConfig( { layout: val } ) }
				/>
				<TextControl
					label={ __( 'Max Selections', 'samplehq-request-form' ) }
					type="number"
					value={ String( config.max_selections ?? 5 ) }
					onChange={ ( val ) =>
						updateConfig( {
							max_selections: parseInt( val, 10 ) || 0,
						} )
					}
					help={ __( '0 = unlimited', 'samplehq-request-form' ) }
				/>
				<ToggleControl
					label={ __( 'Allow Quantity', 'samplehq-request-form' ) }
					checked={ config.allow_quantity ?? true }
					onChange={ ( val ) =>
						updateConfig( { allow_quantity: val } )
					}
				/>
				{ ( config.allow_quantity ?? true ) && (
					<TextControl
						label={ __(
							'Max Quantity Per Sample',
							'samplehq-request-form'
						) }
						type="number"
						value={ String( config.default_max_quantity ?? 3 ) }
						onChange={ ( val ) =>
							updateConfig( {
								default_max_quantity: parseInt( val, 10 ) || 1,
							} )
						}
					/>
				) }
				<ToggleControl
					label={ __( 'Show Images', 'samplehq-request-form' ) }
					checked={ config.show_images ?? true }
					onChange={ ( val ) => updateConfig( { show_images: val } ) }
				/>
				<ToggleControl
					label={ __( 'Show Descriptions', 'samplehq-request-form' ) }
					checked={ config.show_descriptions ?? true }
					onChange={ ( val ) =>
						updateConfig( { show_descriptions: val } )
					}
				/>
			</PanelBody>
		</>
	);
}
