import { __ } from '@wordpress/i18n';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { flattenFields } from '../field-tree';
import { CONDITION_OPERATORS } from '../constants';

export default function ConditionalLogicPanel( {
	field,
	allFields,
	onChange,
} ) {
	const conditions = field.conditions || {};
	const enabled =
		Array.isArray( conditions.rules ) && conditions.rules.length > 0;
	const logic = conditions.logic || 'all';
	const rules = conditions.rules || [];

	// Available fields for the selector (exclude self, rows, html, hidden).
	const fieldOptions = flattenFields( allFields )
		.filter(
			( f ) =>
				f.id !== field.id && ! [ 'html', 'hidden' ].includes( f.type )
		)
		.map( ( f ) => ( { label: f.label || f.key, value: f.key } ) );

	const updateConditions = ( updates ) => {
		onChange( { ...field, conditions: { ...conditions, ...updates } } );
	};

	const updateRule = ( index, key, value ) => {
		const updated = [ ...rules ];
		updated[ index ] = { ...updated[ index ], [ key ]: value };
		updateConditions( { rules: updated } );
	};

	const addRule = () => {
		updateConditions( {
			logic: logic || 'all',
			rules: [
				...rules,
				{
					field_key: fieldOptions[ 0 ]?.value || '',
					operator: 'equals',
					value: '',
				},
			],
		} );
	};

	const removeRule = ( index ) => {
		const updated = rules.filter( ( _, i ) => i !== index );
		if ( updated.length === 0 ) {
			onChange( { ...field, conditions: {} } );
		} else {
			updateConditions( { rules: updated } );
		}
	};

	const clearAll = () => {
		onChange( { ...field, conditions: {} } );
	};

	const needsValue = ( op ) => op !== 'empty' && op !== 'not_empty';

	return (
		<PanelBody
			title={ __( 'Conditional Logic', 'samplehq-request-form' ) }
			initialOpen={ false }
		>
			{ ! enabled ? (
				<>
					<p className="description">
						{ __(
							'Show or hide this field based on other field values.',
							'samplehq-request-form'
						) }
					</p>
					<Button
						variant="secondary"
						size="small"
						onClick={ addRule }
						disabled={ fieldOptions.length === 0 }
					>
						{ __( '+ Add Condition', 'samplehq-request-form' ) }
					</Button>
					{ fieldOptions.length === 0 && (
						<p
							className="description"
							style={ { marginTop: '8px' } }
						>
							{ __(
								'Add other fields to the form first.',
								'samplehq-request-form'
							) }
						</p>
					) }
				</>
			) : (
				<>
					<div className="shqf-condition-header">
						<span>
							{ __(
								'Show this field when',
								'samplehq-request-form'
							) }
						</span>
						<SelectControl
							value={ logic }
							options={ [
								{
									label: __( 'all', 'samplehq-request-form' ),
									value: 'all',
								},
								{
									label: __( 'any', 'samplehq-request-form' ),
									value: 'any',
								},
							] }
							onChange={ ( val ) =>
								updateConditions( { logic: val } )
							}
							__nextHasNoMarginBottom
						/>
						<span>
							{ __(
								'of these rules match:',
								'samplehq-request-form'
							) }
						</span>
					</div>

					{ rules.map( ( rule, i ) => (
						<div key={ i } className="shqf-condition-rule">
							<SelectControl
								value={ rule.field_key || '' }
								options={ [
									{
										label: __(
											'-- Select field --',
											'samplehq-request-form'
										),
										value: '',
									},
									...fieldOptions,
								] }
								onChange={ ( val ) =>
									updateRule( i, 'field_key', val )
								}
								__nextHasNoMarginBottom
							/>
							<SelectControl
								value={ rule.operator || 'equals' }
								options={ CONDITION_OPERATORS }
								onChange={ ( val ) =>
									updateRule( i, 'operator', val )
								}
								__nextHasNoMarginBottom
							/>
							{ needsValue( rule.operator ) && (
								<TextControl
									value={ rule.value || '' }
									onChange={ ( val ) =>
										updateRule( i, 'value', val )
									}
									placeholder={ __(
										'Value',
										'samplehq-request-form'
									) }
									__nextHasNoMarginBottom
								/>
							) }
							<Button
								isDestructive
								size="small"
								onClick={ () => removeRule( i ) }
								aria-label={ __(
									'Remove rule',
									'samplehq-request-form'
								) }
							>
								&times;
							</Button>
						</div>
					) ) }

					<div className="shqf-condition-actions">
						<Button
							variant="secondary"
							size="small"
							onClick={ addRule }
						>
							{ __( '+ Add Rule', 'samplehq-request-form' ) }
						</Button>
						<Button
							variant="tertiary"
							size="small"
							isDestructive
							onClick={ clearAll }
						>
							{ __( 'Remove All', 'samplehq-request-form' ) }
						</Button>
					</div>
				</>
			) }
		</PanelBody>
	);
}
