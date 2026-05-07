import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Panel,
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import { Columns2 } from 'lucide-react';

import { ALL_FIELD_TYPES } from '../constants';
import { flattenFields } from '../field-tree';
import { slugify, getFieldLabel } from '../utils';
import SamplePickerSettings from './SamplePickerSettings';
import ConditionalLogicPanel from './ConditionalLogicPanel';

export default function FieldSettings( {
	field,
	fields: allFields,
	onChange,
} ) {
	const [ keyManuallyEdited, setKeyManuallyEdited ] = useState( false );

	// Reset manual edit flag when a different field is selected.
	useEffect( () => {
		setKeyManuallyEdited( false );
	}, [ field?.id ] );

	/**
	 * Generate a unique key by appending _2, _3 etc. if the key already exists.
	 * @param {string} baseKey
	 * @param {string} currentFieldId
	 */
	const uniqueKey = ( baseKey, currentFieldId ) => {
		const otherKeys = flattenFields( allFields || [] )
			.filter( ( f ) => f.id !== currentFieldId )
			.map( ( f ) => f.key );

		if ( ! otherKeys.includes( baseKey ) ) {
			return baseKey;
		}

		let counter = 2;
		while ( otherKeys.includes( baseKey + '_' + counter ) ) {
			counter++;
		}
		return baseKey + '_' + counter;
	};

	if ( ! field ) {
		return null;
	}

	// Row settings: column presets.
	if ( field.type === 'row' ) {
		const ROW_PRESETS = [
			{
				label: __( '2 Equal', 'samplehq-request-form' ),
				columns: [ { width: '1fr' }, { width: '1fr' } ],
			},
			{
				label: __( '3 Equal', 'samplehq-request-form' ),
				columns: [
					{ width: '1fr' },
					{ width: '1fr' },
					{ width: '1fr' },
				],
			},
			{
				label: __( '1/3 + 2/3', 'samplehq-request-form' ),
				columns: [ { width: '1fr' }, { width: '2fr' } ],
			},
			{
				label: __( '2/3 + 1/3', 'samplehq-request-form' ),
				columns: [ { width: '2fr' }, { width: '1fr' } ],
			},
		];

		const applyPreset = ( preset ) => {
			const currentCols = field.columns || [];
			const newColumns = preset.columns.map( ( presetCol, i ) => ( {
				width: presetCol.width,
				fields: currentCols[ i ]?.fields || [],
			} ) );
			// If shrinking column count, move orphaned fields to last column.
			if ( currentCols.length > newColumns.length ) {
				const lastCol = newColumns[ newColumns.length - 1 ];
				for ( let i = newColumns.length; i < currentCols.length; i++ ) {
					lastCol.fields = [
						...lastCol.fields,
						...( currentCols[ i ]?.fields || [] ),
					];
				}
			}
			onChange( { ...field, columns: newColumns } );
		};

		return (
			<div className="shqf-builder-settings">
				<div className="shqf-builder-settings-header">
					<Columns2 size={ 16 } aria-hidden="true" />
					<span>
						{ __( 'Row Settings', 'samplehq-request-form' ) }
					</span>
				</div>
				<Panel>
					<PanelBody
						title={ __( 'Column Layout', 'samplehq-request-form' ) }
						initialOpen
					>
						<div className="shqf-row-presets">
							{ ROW_PRESETS.map( ( preset ) => (
								<Button
									key={ preset.label }
									variant="secondary"
									className="shqf-row-preset-btn"
									onClick={ () => applyPreset( preset ) }
								>
									<div className="shqf-row-preset-preview">
										{ preset.columns.map( ( col, ci ) => (
											<div
												key={ ci }
												className="shqf-row-preset-col"
												style={ {
													flex: col.width.replace(
														'fr',
														''
													),
												} }
											/>
										) ) }
									</div>
									<span>{ preset.label }</span>
								</Button>
							) ) }
						</div>
					</PanelBody>
				</Panel>
			</div>
		);
	}

	const isChoiceField = [ 'select', 'radio', 'checkbox' ].includes(
		field.type
	);
	const options = field.options || [];

	const updateOption = ( index, key, value ) => {
		const updated = [ ...options ];
		updated[ index ] = { ...updated[ index ], [ key ]: value };
		onChange( { ...field, options: updated } );
	};

	const addOption = () => {
		const num = options.length + 1;
		onChange( {
			...field,
			options: [
				...options,
				{ label: 'Option ' + num, value: 'option_' + num },
			],
		} );
	};

	const removeOption = ( index ) => {
		onChange( {
			...field,
			options: options.filter( ( _, i ) => i !== index ),
		} );
	};

	const FieldIcon = ALL_FIELD_TYPES.find(
		( t ) => t.type === field.type
	)?.icon;

	return (
		<div className="shqf-builder-settings">
			<div className="shqf-builder-settings-header">
				{ FieldIcon && <FieldIcon size={ 16 } aria-hidden="true" /> }
				<span>
					{ getFieldLabel( field.type ) }:{ ' ' }
					{ field.label || __( 'Untitled', 'samplehq-request-form' ) }
				</span>
			</div>
			<Panel>
				<PanelBody
					title={ __( 'General', 'samplehq-request-form' ) }
					initialOpen
				>
					<TextControl
						label={ __( 'Label', 'samplehq-request-form' ) }
						value={ field.label || '' }
						onChange={ ( val ) => {
							const update = { ...field, label: val };
							if ( ! keyManuallyEdited ) {
								const slug = slugify( val );
								update.key = slug
									? uniqueKey( slug, field.id )
									: field.key;
							}
							onChange( update );
						} }
					/>
					<TextControl
						label={ __( 'Field Key', 'samplehq-request-form' ) }
						value={ field.key || '' }
						onChange={ ( val ) => {
							setKeyManuallyEdited( true );
							const slug = slugify( val );
							onChange( {
								...field,
								key: slug ? uniqueKey( slug, field.id ) : val,
							} );
						} }
						help={ __(
							'Unique identifier used in submissions.',
							'samplehq-request-form'
						) }
					/>
					{ field.type !== 'html' && field.type !== 'hidden' && (
						<TextControl
							label={ __(
								'Placeholder',
								'samplehq-request-form'
							) }
							value={ field.placeholder || '' }
							onChange={ ( val ) =>
								onChange( { ...field, placeholder: val } )
							}
						/>
					) }
					{ field.type !== 'html' &&
						field.type !== 'hidden' &&
						field.type !== 'consent' && (
							<ToggleControl
								label={ __(
									'Required',
									'samplehq-request-form'
								) }
								checked={ field.required || false }
								onChange={ ( val ) =>
									onChange( { ...field, required: val } )
								}
							/>
						) }
					{ field.type !== 'html' && field.type !== 'hidden' && (
						<TextControl
							label={ __(
								'Description',
								'samplehq-request-form'
							) }
							value={ field.description || '' }
							onChange={ ( val ) =>
								onChange( { ...field, description: val } )
							}
							help={ __(
								'Help text shown below the field.',
								'samplehq-request-form'
							) }
						/>
					) }
				</PanelBody>

				{ /* Choice field options */ }
				{ isChoiceField && (
					<PanelBody
						title={ __( 'Options', 'samplehq-request-form' ) }
						initialOpen
					>
						{ options.map( ( opt, i ) => (
							<div
								key={ opt.value || `opt_${ i }` }
								className="shqf-option-row"
							>
								<TextControl
									label={
										i === 0
											? __(
													'Label',
													'samplehq-request-form'
											  )
											: ''
									}
									value={ opt.label || '' }
									onChange={ ( val ) =>
										updateOption( i, 'label', val )
									}
									onKeyDown={ ( e ) => {
										if ( e.key === 'Enter' ) {
											e.preventDefault();
											addOption();
										}
									} }
									__nextHasNoMarginBottom
								/>
								<Button
									isDestructive
									size="small"
									onClick={ () => removeOption( i ) }
									disabled={ options.length <= 1 }
									aria-label={ __(
										'Remove option',
										'samplehq-request-form'
									) }
									className="shqf-option-remove"
								>
									&times;
								</Button>
							</div>
						) ) }
						<Button
							variant="secondary"
							size="small"
							onClick={ addOption }
						>
							{ __( '+ Add Option', 'samplehq-request-form' ) }
						</Button>
					</PanelBody>
				) }

				{ /* Choice field display settings */ }
				{ isChoiceField && (
					<PanelBody
						title={ __(
							'Choice Display',
							'samplehq-request-form'
						) }
						initialOpen={ false }
					>
						<SelectControl
							label={ __( 'Layout', 'samplehq-request-form' ) }
							value={ field.config?.choice_layout || 'vertical' }
							options={ [
								{
									label: __(
										'Vertical (stacked)',
										'samplehq-request-form'
									),
									value: 'vertical',
								},
								{
									label: __(
										'Horizontal (inline)',
										'samplehq-request-form'
									),
									value: 'horizontal',
								},
							] }
							onChange={ ( val ) =>
								onChange( {
									...field,
									config: {
										...( field.config || {} ),
										choice_layout: val,
									},
								} )
							}
						/>
						{ ( field.config?.choice_layout || 'vertical' ) ===
							'vertical' && (
							<SelectControl
								label={ __(
									'Columns',
									'samplehq-request-form'
								) }
								value={ String(
									field.config?.choice_columns || 1
								) }
								options={ [
									{
										label: __(
											'1 column',
											'samplehq-request-form'
										),
										value: '1',
									},
									{
										label: __(
											'2 columns',
											'samplehq-request-form'
										),
										value: '2',
									},
									{
										label: __(
											'3 columns',
											'samplehq-request-form'
										),
										value: '3',
									},
								] }
								onChange={ ( val ) =>
									onChange( {
										...field,
										config: {
											...( field.config || {} ),
											choice_columns: parseInt( val, 10 ),
										},
									} )
								}
							/>
						) }
					</PanelBody>
				) }

				{ /* HTML content */ }
				{ field.type === 'html' && (
					<PanelBody
						title={ __( 'Content', 'samplehq-request-form' ) }
						initialOpen
					>
						<TextareaControl
							label={ __(
								'HTML Content',
								'samplehq-request-form'
							) }
							value={ field.content || '' }
							onChange={ ( val ) =>
								onChange( { ...field, content: val } )
							}
							help={ __(
								'Safe HTML allowed (p, a, strong, em, ul, ol, li).',
								'samplehq-request-form'
							) }
							rows={ 6 }
						/>
					</PanelBody>
				) }

				{ /* Consent text */ }
				{ field.type === 'consent' && (
					<PanelBody
						title={ __( 'Consent Text', 'samplehq-request-form' ) }
						initialOpen
					>
						<TextareaControl
							label={ __(
								'Agreement Text',
								'samplehq-request-form'
							) }
							value={ field.consent_text || '' }
							onChange={ ( val ) =>
								onChange( { ...field, consent_text: val } )
							}
							help={ __(
								'HTML links allowed (e.g., link to privacy policy).',
								'samplehq-request-form'
							) }
							rows={ 3 }
						/>
					</PanelBody>
				) }

				{ /* Sample Picker config */ }
				{ field.type === 'sample_picker' && (
					<SamplePickerSettings
						field={ field }
						onChange={ onChange }
					/>
				) }

				{ /* Hidden field value source */ }
				{ field.type === 'hidden' && (
					<PanelBody
						title={ __( 'Value', 'samplehq-request-form' ) }
						initialOpen
					>
						<SelectControl
							label={ __(
								'Value Source',
								'samplehq-request-form'
							) }
							value={ field.value_source || 'static' }
							options={ [
								{
									label: __(
										'Static value',
										'samplehq-request-form'
									),
									value: 'static',
								},
								{
									label: __(
										'URL parameter',
										'samplehq-request-form'
									),
									value: 'url_param',
								},
								{
									label: __(
										'Current page URL',
										'samplehq-request-form'
									),
									value: 'current_url',
								},
							] }
							onChange={ ( val ) =>
								onChange( { ...field, value_source: val } )
							}
						/>
						{ ( field.value_source || 'static' ) === 'static' && (
							<TextControl
								label={ __(
									'Default Value',
									'samplehq-request-form'
								) }
								value={ field.default_value || '' }
								onChange={ ( val ) =>
									onChange( { ...field, default_value: val } )
								}
							/>
						) }
						{ field.value_source === 'url_param' && (
							<TextControl
								label={ __(
									'Parameter Name',
									'samplehq-request-form'
								) }
								value={ field.url_param || '' }
								onChange={ ( val ) =>
									onChange( { ...field, url_param: val } )
								}
								help={ __(
									'e.g., "utm_source" reads from ?utm_source=value',
									'samplehq-request-form'
								) }
							/>
						) }
					</PanelBody>
				) }

				{ /* File Upload settings */ }
				{ field.type === 'file_upload' && (
					<PanelBody
						title={ __( 'File Settings', 'samplehq-request-form' ) }
						initialOpen
					>
						<SelectControl
							label={ __(
								'Allowed Types',
								'samplehq-request-form'
							) }
							value={
								field.validation?.allowed_type_group || 'images'
							}
							options={ [
								{
									label: __(
										'Images (JPG, PNG, GIF, WebP)',
										'samplehq-request-form'
									),
									value: 'images',
								},
								{
									label: __(
										'Documents (PDF, DOC, XLS)',
										'samplehq-request-form'
									),
									value: 'documents',
								},
								{
									label: __(
										'All common types',
										'samplehq-request-form'
									),
									value: 'all',
								},
							] }
							onChange={ ( val ) =>
								onChange( {
									...field,
									validation: {
										...( field.validation || {} ),
										allowed_type_group: val,
									},
								} )
							}
						/>
						<TextControl
							label={ __(
								'Max File Size (MB)',
								'samplehq-request-form'
							) }
							type="number"
							value={ String(
								field.validation?.max_size_mb ?? 5
							) }
							onChange={ ( val ) =>
								onChange( {
									...field,
									validation: {
										...( field.validation || {} ),
										max_size_mb: parseInt( val, 10 ) || 5,
									},
								} )
							}
						/>
					</PanelBody>
				) }

				{ /* Date min/max */ }
				{ field.type === 'date' && (
					<PanelBody
						title={ __( 'Date Range', 'samplehq-request-form' ) }
						initialOpen={ false }
					>
						<TextControl
							label={ __(
								'Earliest Date',
								'samplehq-request-form'
							) }
							type="date"
							value={ field.validation?.min || '' }
							onChange={ ( val ) =>
								onChange( {
									...field,
									validation: {
										...( field.validation || {} ),
										min: val,
									},
								} )
							}
						/>
						<TextControl
							label={ __(
								'Latest Date',
								'samplehq-request-form'
							) }
							type="date"
							value={ field.validation?.max || '' }
							onChange={ ( val ) =>
								onChange( {
									...field,
									validation: {
										...( field.validation || {} ),
										max: val,
									},
								} )
							}
						/>
					</PanelBody>
				) }

				{ /* Text / Textarea length + pattern */ }
				{ ( field.type === 'text' || field.type === 'textarea' ) && (
					<PanelBody
						title={ __( 'Validation', 'samplehq-request-form' ) }
						initialOpen={ false }
					>
						<TextControl
							label={ __(
								'Min Characters',
								'samplehq-request-form'
							) }
							type="number"
							value={ String(
								field.validation?.min_length ?? ''
							) }
							onChange={ ( val ) =>
								onChange( {
									...field,
									validation: {
										...( field.validation || {} ),
										min_length:
											val === ''
												? undefined
												: parseInt( val, 10 ),
									},
								} )
							}
						/>
						<TextControl
							label={ __(
								'Max Characters',
								'samplehq-request-form'
							) }
							type="number"
							value={ String(
								field.validation?.max_length ?? ''
							) }
							onChange={ ( val ) =>
								onChange( {
									...field,
									validation: {
										...( field.validation || {} ),
										max_length:
											val === ''
												? undefined
												: parseInt( val, 10 ),
									},
								} )
							}
						/>
						{ field.type === 'text' && (
							<TextControl
								label={ __(
									'Pattern (Regex)',
									'samplehq-request-form'
								) }
								value={ field.validation?.pattern || '' }
								onChange={ ( val ) =>
									onChange( {
										...field,
										validation: {
											...( field.validation || {} ),
											pattern: val,
										},
									} )
								}
								help={ __(
									'e.g., [A-Z]{2}\\d{4} for codes like AB1234',
									'samplehq-request-form'
								) }
							/>
						) }
					</PanelBody>
				) }

				{ /* Number min/max */ }
				{ field.type === 'number' && (
					<PanelBody
						title={ __( 'Validation', 'samplehq-request-form' ) }
						initialOpen={ false }
					>
						<TextControl
							label={ __(
								'Minimum Value',
								'samplehq-request-form'
							) }
							type="number"
							value={ String( field.validation?.min ?? '' ) }
							onChange={ ( val ) =>
								onChange( {
									...field,
									validation: {
										...( field.validation || {} ),
										min:
											val === ''
												? undefined
												: parseFloat( val ),
									},
								} )
							}
						/>
						<TextControl
							label={ __(
								'Maximum Value',
								'samplehq-request-form'
							) }
							type="number"
							value={ String( field.validation?.max ?? '' ) }
							onChange={ ( val ) =>
								onChange( {
									...field,
									validation: {
										...( field.validation || {} ),
										max:
											val === ''
												? undefined
												: parseFloat( val ),
									},
								} )
							}
						/>
					</PanelBody>
				) }

				{ /* Custom error messages -- available for all input types */ }
				{ ! [ 'html', 'hidden', 'row' ].includes( field.type ) && (
					<PanelBody
						title={ __(
							'Error Messages',
							'samplehq-request-form'
						) }
						initialOpen={ false }
					>
						<TextControl
							label={ __(
								'Required Error',
								'samplehq-request-form'
							) }
							value={ field.validation?.required_message || '' }
							onChange={ ( val ) =>
								onChange( {
									...field,
									validation: {
										...( field.validation || {} ),
										required_message: val,
									},
								} )
							}
							help={ __(
								'Override the default "X is required." message.',
								'samplehq-request-form'
							) }
						/>
						<TextControl
							label={ __(
								'Format Error',
								'samplehq-request-form'
							) }
							value={ field.validation?.format_message || '' }
							onChange={ ( val ) =>
								onChange( {
									...field,
									validation: {
										...( field.validation || {} ),
										format_message: val,
									},
								} )
							}
							help={ __(
								'Custom message for format/pattern validation failures.',
								'samplehq-request-form'
							) }
						/>
					</PanelBody>
				) }
				{ /* Conditional logic -- all types except html, hidden, row */ }
				{ ! [ 'html', 'hidden', 'row' ].includes( field.type ) && (
					<ConditionalLogicPanel
						field={ field }
						allFields={ allFields }
						onChange={ onChange }
					/>
				) }
			</Panel>
		</div>
	);
}
