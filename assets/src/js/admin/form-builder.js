/**
 * SampleHQ Form Builder - Admin React app.
 *
 * Three-panel form builder: field palette (left), form canvas (center),
 * field settings (right). Styled to match WPForms/Gravity Forms conventions.
 *
 * @package
 */

import {
	render,
	useState,
	useCallback,
	useEffect,
	useRef,
	useReducer,
} from '@wordpress/element';
import {
	Button,
	Panel,
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	DndContext,
	DragOverlay,
	closestCorners,
	PointerSensor,
	KeyboardSensor,
	useSensor,
	useSensors,
	useDroppable,
	useDraggable,
} from '@dnd-kit/core';
import {
	SortableContext,
	verticalListSortingStrategy,
	useSortable,
	arrayMove,
	sortableKeyboardCoordinates,
} from '@dnd-kit/sortable';
import { CSS as DndCSS } from '@dnd-kit/utilities';
import {
	Type,
	AtSign,
	Phone,
	AlignLeft,
	Hash,
	User,
	ChevronDown,
	CircleDot,
	CheckSquare,
	Calendar,
	Link,
	Upload,
	MapPin,
	EyeOff,
	Code,
	ShieldCheck,
	LayoutGrid,
	Columns2,
	GripVertical,
	ArrowLeft,
	Copy,
	Trash2,
	Pencil,
	Undo2,
	Redo2,
} from 'lucide-react';

/**
 * Field type definitions, categorized.
 */
const FIELD_CATEGORIES = [
	{
		label: __( 'Standard', 'samplehq-request-form' ),
		fields: [
			{
				type: 'text',
				label: __( 'Text', 'samplehq-request-form' ),
				icon: Type,
			},
			{
				type: 'email',
				label: __( 'Email', 'samplehq-request-form' ),
				icon: AtSign,
			},
			{
				type: 'phone',
				label: __( 'Phone', 'samplehq-request-form' ),
				icon: Phone,
			},
			{
				type: 'textarea',
				label: __( 'Paragraph', 'samplehq-request-form' ),
				icon: AlignLeft,
			},
			{
				type: 'number',
				label: __( 'Number', 'samplehq-request-form' ),
				icon: Hash,
			},
			{
				type: 'name',
				label: __( 'Name', 'samplehq-request-form' ),
				icon: User,
			},
		],
	},
	{
		label: __( 'Choice', 'samplehq-request-form' ),
		fields: [
			{
				type: 'select',
				label: __( 'Dropdown', 'samplehq-request-form' ),
				icon: ChevronDown,
			},
			{
				type: 'radio',
				label: __( 'Radio', 'samplehq-request-form' ),
				icon: CircleDot,
			},
			{
				type: 'checkbox',
				label: __( 'Checkbox', 'samplehq-request-form' ),
				icon: CheckSquare,
			},
		],
	},
	{
		label: __( 'Advanced', 'samplehq-request-form' ),
		fields: [
			{
				type: 'date',
				label: __( 'Date', 'samplehq-request-form' ),
				icon: Calendar,
			},
			{
				type: 'url',
				label: __( 'URL', 'samplehq-request-form' ),
				icon: Link,
			},
			{
				type: 'file_upload',
				label: __( 'File Upload', 'samplehq-request-form' ),
				icon: Upload,
			},
			{
				type: 'address',
				label: __( 'Address', 'samplehq-request-form' ),
				icon: MapPin,
			},
			{
				type: 'hidden',
				label: __( 'Hidden', 'samplehq-request-form' ),
				icon: EyeOff,
			},
		],
	},
	{
		label: __( 'Content', 'samplehq-request-form' ),
		fields: [
			{
				type: 'html',
				label: __( 'HTML', 'samplehq-request-form' ),
				icon: Code,
			},
			{
				type: 'consent',
				label: __( 'Consent', 'samplehq-request-form' ),
				icon: ShieldCheck,
			},
			{
				type: 'sample_picker',
				label: __( 'Sample Picker', 'samplehq-request-form' ),
				icon: LayoutGrid,
			},
		],
	},
	{
		label: __( 'Layout', 'samplehq-request-form' ),
		fields: [
			{
				type: 'row',
				label: __( 'Row / Columns', 'samplehq-request-form' ),
				icon: Columns2,
			},
		],
	},
];

/**
 * Flat list of all field type definitions.
 */
const ALL_FIELD_TYPES = FIELD_CATEGORIES.flatMap( ( c ) => c.fields );

function generateFieldId() {
	return 'f_' + Math.random().toString( 36 ).substring( 2, 9 );
}

function createField( type ) {
	// Row groups get a different ID prefix and structure.
	if ( type === 'row' ) {
		return {
			id: 'row_' + Math.random().toString( 36 ).substring( 2, 9 ),
			type: 'row',
			enabled: true,
			columns: [
				{ width: '1fr', fields: [] },
				{ width: '1fr', fields: [] },
			],
		};
	}

	const def = ALL_FIELD_TYPES.find( ( t ) => t.type === type );

	// Fields that should be required by default when added from the palette.
	const requiredByDefault = [ 'email', 'name', 'sample_picker' ];

	const base = {
		id: generateFieldId(),
		type,
		key: type + '_' + Date.now(),
		label: def?.label || type,
		placeholder: '',
		required: requiredByDefault.includes( type ),
		enabled: true,
	};

	// Type-specific defaults.
	if ( type === 'select' || type === 'radio' || type === 'checkbox' ) {
		base.options = [
			{
				label: __( 'Option 1', 'samplehq-request-form' ),
				value: 'option_1',
			},
			{
				label: __( 'Option 2', 'samplehq-request-form' ),
				value: 'option_2',
			},
			{
				label: __( 'Option 3', 'samplehq-request-form' ),
				value: 'option_3',
			},
		];
	}

	if ( type === 'consent' ) {
		base.consent_text = __(
			'I agree to the privacy policy.',
			'samplehq-request-form'
		);
		base.required = true;
	}

	if ( type === 'html' ) {
		base.content =
			'<p>' +
			__( 'Enter your content here.', 'samplehq-request-form' ) +
			'</p>';
	}

	if ( type === 'hidden' ) {
		base.default_value = '';
	}

	return base;
}

// ---------------------------------------------------------------------------
// Tree helpers -- fields can be at top level or nested inside row columns.
// ---------------------------------------------------------------------------

/**
 * Flatten all leaf fields from a (possibly nested) fields array.
 * @param fields
 */
function flattenFields( fields ) {
	const flat = [];
	for ( const field of fields ) {
		if ( field.type === 'row' ) {
			for ( const col of field.columns || [] ) {
				flat.push( ...flattenFields( col.fields || [] ) );
			}
		} else {
			flat.push( field );
		}
	}
	return flat;
}

/**
 * Find a field anywhere in the tree by ID.
 * @param fields
 * @param id
 */
function findFieldInTree( fields, id ) {
	for ( const field of fields ) {
		if ( field.id === id ) {
			return field;
		}
		if ( field.type === 'row' ) {
			for ( const col of field.columns || [] ) {
				const found = findFieldInTree( col.fields || [], id );
				if ( found ) {
					return found;
				}
			}
		}
	}
	return null;
}

/**
 * Remove a field from anywhere in the tree. Returns a new array.
 * @param fields
 * @param id
 */
function removeFieldFromTree( fields, id ) {
	const result = [];
	for ( const field of fields ) {
		if ( field.id === id ) {
			continue;
		}
		if ( field.type === 'row' ) {
			result.push( {
				...field,
				columns: ( field.columns || [] ).map( ( col ) => ( {
					...col,
					fields: removeFieldFromTree( col.fields || [], id ),
				} ) ),
			} );
		} else {
			result.push( field );
		}
	}
	return result;
}

/**
 * Update a field anywhere in the tree. Returns a new array.
 * @param fields
 * @param updatedField
 */
function updateFieldInTree( fields, updatedField ) {
	return fields.map( ( field ) => {
		if ( field.id === updatedField.id ) {
			return updatedField;
		}
		if ( field.type === 'row' ) {
			return {
				...field,
				columns: ( field.columns || [] ).map( ( col ) => ( {
					...col,
					fields: updateFieldInTree( col.fields || [], updatedField ),
				} ) ),
			};
		}
		return field;
	} );
}

/**
 * Insert a field before a target ID anywhere in the tree.
 * @param fields
 * @param targetId
 * @param newField
 */
function insertBeforeInTree( fields, targetId, newField ) {
	const result = [];
	for ( const field of fields ) {
		if ( field.id === targetId ) {
			result.push( newField );
		}
		if ( field.type === 'row' ) {
			result.push( {
				...field,
				columns: ( field.columns || [] ).map( ( col ) => ( {
					...col,
					fields: insertBeforeInTree(
						col.fields || [],
						targetId,
						newField
					),
				} ) ),
			} );
		} else {
			result.push( field );
		}
	}
	return result;
}

/**
 * Insert a field after a target ID anywhere in the tree.
 * @param fields
 * @param targetId
 * @param newField
 */
function insertAfterInTree( fields, targetId, newField ) {
	const result = [];
	for ( const field of fields ) {
		if ( field.type === 'row' ) {
			result.push( {
				...field,
				columns: ( field.columns || [] ).map( ( col ) => ( {
					...col,
					fields: insertAfterInTree(
						col.fields || [],
						targetId,
						newField
					),
				} ) ),
			} );
		} else {
			result.push( field );
		}
		if ( field.id === targetId ) {
			result.push( newField );
		}
	}
	return result;
}

/**
 * Insert a field at the end of a specific row column.
 * @param fields
 * @param rowId
 * @param colIndex
 * @param newField
 */
function insertIntoColumn( fields, rowId, colIndex, newField ) {
	return fields.map( ( field ) => {
		if ( field.id !== rowId || field.type !== 'row' ) {
			return field;
		}
		return {
			...field,
			columns: ( field.columns || [] ).map( ( col, ci ) => {
				if ( ci !== colIndex ) {
					return col;
				}
				return {
					...col,
					fields: [ ...( col.fields || [] ), newField ],
				};
			} ),
		};
	} );
}

/**
 * Find which container a field is in and its index.
 * Returns { container, index, rowId?, colIndex? } or null.
 * @param fields
 * @param id
 */
function findFieldLocation( fields, id ) {
	for ( let i = 0; i < fields.length; i++ ) {
		if ( fields[ i ].id === id ) {
			return { container: 'top', index: i };
		}
		if ( fields[ i ].type === 'row' ) {
			const row = fields[ i ];
			for ( let ci = 0; ci < ( row.columns || [] ).length; ci++ ) {
				const colFields = row.columns[ ci ].fields || [];
				for ( let fi = 0; fi < colFields.length; fi++ ) {
					if ( colFields[ fi ].id === id ) {
						return {
							container: 'col_' + row.id + '_' + ci,
							rowId: row.id,
							colIndex: ci,
							index: fi,
						};
					}
				}
			}
		}
	}
	return null;
}

/**
 * Reorder within a specific column using arrayMove.
 * @param fields
 * @param rowId
 * @param colIndex
 * @param oldIdx
 * @param newIdx
 */
function reorderInColumn( fields, rowId, colIndex, oldIdx, newIdx ) {
	return fields.map( ( field ) => {
		if ( field.id !== rowId || field.type !== 'row' ) {
			return field;
		}
		return {
			...field,
			columns: ( field.columns || [] ).map( ( col, ci ) => {
				if ( ci !== colIndex ) {
					return col;
				}
				return {
					...col,
					fields: arrayMove( col.fields || [], oldIdx, newIdx ),
				};
			} ),
		};
	} );
}

/**
 * Field preview -- shows a visual representation of the field.
 * @param root0
 * @param root0.field
 */
function FieldPreview( { field } ) {
	const ph = field.placeholder || '';

	switch ( field.type ) {
		case 'text':
		case 'email':
		case 'phone':
		case 'date':
		case 'url':
		case 'number':
			return (
				<div className="shqf-builder-field-preview">
					<input
						type="text"
						placeholder={ ph || field.type }
						readOnly
						tabIndex={ -1 }
					/>
				</div>
			);

		case 'textarea':
			return (
				<div className="shqf-builder-field-preview">
					<textarea
						placeholder={
							ph || __( 'Enter text…', 'samplehq-request-form' )
						}
						readOnly
						tabIndex={ -1 }
					/>
				</div>
			);

		case 'select':
			return (
				<div className="shqf-builder-field-preview">
					<select tabIndex={ -1 }>
						<option>
							{ ph ||
								__(
									'Select an option…',
									'samplehq-request-form'
								) }
						</option>
					</select>
				</div>
			);

		case 'radio':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-choices">
						{ ( field.options || [] )
							.slice( 0, 3 )
							.map( ( opt, i ) => (
								<div key={ i } className="shqf-preview-choice">
									<span className="shqf-preview-choice-radio" />
									<span>{ opt.label }</span>
								</div>
							) ) }
					</div>
				</div>
			);

		case 'checkbox':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-choices">
						{ ( field.options || [] )
							.slice( 0, 3 )
							.map( ( opt, i ) => (
								<div key={ i } className="shqf-preview-choice">
									<span className="shqf-preview-choice-check" />
									<span>{ opt.label }</span>
								</div>
							) ) }
					</div>
				</div>
			);

		case 'name':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-half">
						<input
							type="text"
							placeholder={ __(
								'First Name',
								'samplehq-request-form'
							) }
							readOnly
							tabIndex={ -1 }
						/>
						<input
							type="text"
							placeholder={ __(
								'Last Name',
								'samplehq-request-form'
							) }
							readOnly
							tabIndex={ -1 }
						/>
					</div>
				</div>
			);

		case 'address':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-address">
						<input
							type="text"
							placeholder={ __(
								'Street Address',
								'samplehq-request-form'
							) }
							readOnly
							tabIndex={ -1 }
						/>
						<input
							type="text"
							placeholder={ __(
								'City',
								'samplehq-request-form'
							) }
							readOnly
							tabIndex={ -1 }
						/>
						<input
							type="text"
							placeholder={ __(
								'State',
								'samplehq-request-form'
							) }
							readOnly
							tabIndex={ -1 }
						/>
						<input
							type="text"
							placeholder={ __( 'ZIP', 'samplehq-request-form' ) }
							readOnly
							tabIndex={ -1 }
						/>
					</div>
				</div>
			);

		case 'file_upload':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-file">
						{ __(
							'Drag & drop or click to upload',
							'samplehq-request-form'
						) }
					</div>
				</div>
			);

		case 'html':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-html">
						{ field.content
							? field.content
									.replace( /<[^>]*>/g, '' )
									.substring( 0, 80 )
							: __( 'HTML content', 'samplehq-request-form' ) }
					</div>
				</div>
			);

		case 'consent':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-consent">
						<span className="shqf-preview-choice-check" />
						<span>
							{ ( field.consent_text || field.label || '' )
								.replace( /<[^>]*>/g, '' )
								.substring( 0, 80 ) }
						</span>
					</div>
				</div>
			);

		case 'hidden':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-hidden">
						{ __( 'Hidden field', 'samplehq-request-form' ) }:{ ' ' }
						{ field.default_value || '(empty)' }
					</div>
				</div>
			);

		case 'sample_picker': {
			const pickerLayout = field.config?.layout || 'grid';
			const maxSel = field.config?.max_selections || 0;
			const isGrid = pickerLayout === 'grid';

			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-picker">
						<div className="shqf-preview-picker-header">
							<LayoutGrid size={ 16 } aria-hidden="true" />
							<span>
								{ isGrid
									? __( 'Card Grid', 'samplehq-request-form' )
									: __(
											'Checklist',
											'samplehq-request-form'
									  ) }
								{ maxSel > 0 && ` \u00B7 ${ maxSel } max` }
							</span>
						</div>
						{ isGrid ? (
							<div className="shqf-preview-picker-grid">
								{ [ 1, 2, 3 ].map( ( n ) => (
									<div
										key={ n }
										className="shqf-preview-picker-card"
									>
										<div className="shqf-preview-picker-card-img" />
										<div className="shqf-preview-picker-card-body">
											<div className="shqf-preview-picker-card-title" />
											<div className="shqf-preview-picker-card-desc" />
										</div>
									</div>
								) ) }
							</div>
						) : (
							<div className="shqf-preview-picker-list">
								{ [ 1, 2, 3 ].map( ( n ) => (
									<div
										key={ n }
										className="shqf-preview-picker-row"
									>
										<div className="shqf-preview-picker-row-check" />
										<div className="shqf-preview-picker-row-thumb" />
										<div className="shqf-preview-picker-row-info">
											<div className="shqf-preview-picker-card-title" />
											<div className="shqf-preview-picker-card-desc" />
										</div>
									</div>
								) ) }
							</div>
						) }
					</div>
				</div>
			);
		}

		case 'row': {
			const cols = field.columns || [];
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-row">
						{ cols.map( ( col, ci ) => (
							<div key={ ci } className="shqf-preview-row-col">
								{ ( col.fields || [] ).length > 0 ? (
									( col.fields || [] ).map( ( f ) => (
										<span
											key={ f.id }
											className="shqf-preview-row-col-field"
										>
											{ f.label || f.type }
										</span>
									) )
								) : (
									<span className="shqf-preview-row-col-empty">
										{ __(
											'Drop fields here',
											'samplehq-request-form'
										) }
									</span>
								) }
							</div>
						) ) }
					</div>
				</div>
			);
		}

		default:
			return null;
	}
}

/**
 * Get a human-readable label for a field type.
 * @param type
 */
function getFieldLabel( type ) {
	const def = ALL_FIELD_TYPES.find( ( t ) => t.type === type );
	return def
		? def.label
		: type
				.replace( /_/g, ' ' )
				.replace( /\b\w/g, ( c ) => c.toUpperCase() );
}

/**
 * Field Palette -- categorized field types.
 */
/**
 * Draggable palette item -- can be dragged onto the canvas to insert at position.
 * @param root0
 * @param root0.fieldType
 * @param root0.onAdd
 */
function DraggablePaletteItem( { fieldType, onAdd } ) {
	const dragId = 'new_' + fieldType.type;
	const { attributes, listeners, setNodeRef, isDragging } = useDraggable( {
		id: dragId,
	} );
	const IconComponent = fieldType.icon;

	return (
		<div
			ref={ setNodeRef }
			{ ...attributes }
			{ ...listeners }
			className={ `shqf-builder-palette-item ${
				isDragging ? 'shqf-builder-palette-item--dragging' : ''
			}` }
			role="button"
			tabIndex={ 0 }
			onClick={ () => onAdd( fieldType.type ) }
		>
			<IconComponent size={ 20 } strokeWidth={ 1.5 } aria-hidden="true" />
			<span>{ fieldType.label }</span>
		</div>
	);
}

function FieldPalette( { onAdd } ) {
	const [ search, setSearch ] = useState( '' );
	const query = search.toLowerCase().trim();

	return (
		<div className="shqf-builder-palette">
			<h3>{ __( 'Add Fields', 'samplehq-request-form' ) }</h3>
			<input
				type="text"
				className="shqf-builder-palette-search"
				placeholder={ __( 'Search fields…', 'samplehq-request-form' ) }
				value={ search }
				onChange={ ( e ) => setSearch( e.target.value ) }
			/>
			{ FIELD_CATEGORIES.map( ( category ) => {
				const filtered = query
					? category.fields.filter(
							( f ) =>
								f.label.toLowerCase().includes( query ) ||
								f.type.includes( query )
					  )
					: category.fields;
				if ( filtered.length === 0 ) {
					return null;
				}
				return (
					<div
						className="shqf-builder-palette-section"
						key={ category.label }
					>
						<h4>{ category.label }</h4>
						<div className="shqf-builder-palette-items">
							{ filtered.map( ( fieldType ) => (
								<DraggablePaletteItem
									key={ fieldType.type }
									fieldType={ fieldType }
									onAdd={ onAdd }
								/>
							) ) }
						</div>
					</div>
				);
			} ) }
		</div>
	);
}

/**
 * Field Settings panel.
 */
/**
 * Sample Picker settings -- source filter, layout, quantities.
 * Fetches categories and samples from REST API for the filter controls.
 * @param root0
 * @param root0.field
 * @param root0.onChange
 */
function SamplePickerSettings( { field, onChange } ) {
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
					title={ __(
						'Product Source',
						'samplehq-request-form'
					) }
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
								{ __( 'Loading…', 'samplehq-request-form' ) }
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
								return (
									<label
										key={ catId }
										className="shqf-settings-checklist-item"
									>
										<input
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
							{ __( 'Select samples:', 'samplehq-request-form' ) }
						</p>
						{ loading && (
							<p className="description">
								{ __( 'Loading…', 'samplehq-request-form' ) }
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
								return (
									<label
										key={ sId }
										className="shqf-settings-checklist-item"
									>
										<input
											type="checkbox"
											checked={ checked }
											onChange={ () =>
												updateFilter( {
													sample_ids: toggleArrayItem(
														filter.sample_ids || [],
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

/**
 * Slugify a string for use as a field key.
 * @param str
 */
function slugify( str ) {
	return str
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '_' )
		.replace( /^_|_$/g, '' )
		.substring( 0, 50 );
}

/**
 * Form-level settings panel -- shown when no field is selected.
 * @param root0
 * @param root0.subtitle
 * @param root0.layout
 * @param root0.behavior
 * @param root0.appearance
 * @param root0.display
 * @param root0.email
 * @param root0.meta
 * @param root0.onSubtitle
 * @param root0.onLayout
 * @param root0.onBehavior
 * @param root0.onAppearance
 * @param root0.onDisplay
 * @param root0.onEmail
 */
function FormSettingsPanel( {
	subtitle,
	layout,
	behavior,
	appearance,
	display,
	email,
	meta,
	onSubtitle,
	onLayout,
	onBehavior,
	onAppearance,
	onDisplay,
	onEmail,
} ) {
	const submitText =
		behavior.submit_button_text ||
		__( 'Submit Request', 'samplehq-request-form' );
	const successMessage =
		behavior.success_message ||
		__(
			'Thank you! Your sample request has been submitted.',
			'samplehq-request-form'
		);
	const successType = behavior.success_type || 'message';
	const redirectUrl = behavior.redirect_url || '';

	return (
		<div className="shqf-builder-settings">
			<div className="shqf-builder-settings-header">
				<span>{ __( 'Form Settings', 'samplehq-request-form' ) }</span>
			</div>
			<Panel>
				<PanelBody
					title={ __( 'General', 'samplehq-request-form' ) }
					initialOpen
				>
					<TextareaControl
						label={ __( 'Subtitle', 'samplehq-request-form' ) }
						value={ subtitle }
						onChange={ onSubtitle }
						rows={ 2 }
						help={ __(
							'Displayed below the form title.',
							'samplehq-request-form'
						) }
					/>
					<SelectControl
						label={ __( 'Layout', 'samplehq-request-form' ) }
						value={ layout || '' }
						options={ [
							{
								label: __( 'Default', 'samplehq-request-form' ),
								value: '',
							},
							{
								label: __(
									'Wizard (Multi-step)',
									'samplehq-request-form'
								),
								value: 'wizard',
							},
							{
								label: __( 'Grid', 'samplehq-request-form' ),
								value: 'grid',
							},
							{
								label: __( 'List', 'samplehq-request-form' ),
								value: 'list',
							},
						] }
						onChange={ onLayout }
					/>
					<TextControl
						label={ __(
							'Submit Button Text',
							'samplehq-request-form'
						) }
						value={ submitText }
						onChange={ ( val ) =>
							onBehavior( {
								...behavior,
								submit_button_text: val,
							} )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'After Submission', 'samplehq-request-form' ) }
					initialOpen
				>
					<SelectControl
						label={ __( 'On Success', 'samplehq-request-form' ) }
						value={ successType }
						options={ [
							{
								label: __(
									'Show success message',
									'samplehq-request-form'
								),
								value: 'message',
							},
							{
								label: __(
									'Redirect to URL',
									'samplehq-request-form'
								),
								value: 'redirect',
							},
						] }
						onChange={ ( val ) =>
							onBehavior( { ...behavior, success_type: val } )
						}
					/>
					{ successType === 'message' && (
						<TextareaControl
							label={ __(
								'Success Message',
								'samplehq-request-form'
							) }
							value={ successMessage }
							onChange={ ( val ) =>
								onBehavior( {
									...behavior,
									success_message: val,
								} )
							}
							rows={ 2 }
						/>
					) }
					{ successType === 'redirect' && (
						<TextControl
							label={ __(
								'Redirect URL',
								'samplehq-request-form'
							) }
							value={ redirectUrl }
							onChange={ ( val ) =>
								onBehavior( { ...behavior, redirect_url: val } )
							}
							type="url"
							help={ __(
								'Full URL including https://',
								'samplehq-request-form'
							) }
						/>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Field Display', 'samplehq-request-form' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __(
							'Label Position',
							'samplehq-request-form'
						) }
						value={ display.label_position || 'top' }
						options={ [
							{
								label: __(
									'Above input',
									'samplehq-request-form'
								),
								value: 'top',
							},
							{
								label: __(
									'Beside input (left)',
									'samplehq-request-form'
								),
								value: 'left',
							},
							{
								label: __(
									'Hidden (placeholder only)',
									'samplehq-request-form'
								),
								value: 'hidden',
							},
						] }
						onChange={ ( val ) =>
							onDisplay( { ...display, label_position: val } )
						}
					/>
					<SelectControl
						label={ __( 'Placeholder', 'samplehq-request-form' ) }
						value={ display.placeholder_mode || 'show' }
						options={ [
							{
								label: __(
									'Show label + placeholder',
									'samplehq-request-form'
								),
								value: 'show',
							},
							{
								label: __(
									'Label only (no placeholder)',
									'samplehq-request-form'
								),
								value: 'label_only',
							},
							{
								label: __(
									'Placeholder as label',
									'samplehq-request-form'
								),
								value: 'placeholder_as_label',
							},
						] }
						onChange={ ( val ) =>
							onDisplay( { ...display, placeholder_mode: val } )
						}
						help={ __(
							'Controls whether placeholders appear inside inputs.',
							'samplehq-request-form'
						) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Tracking', 'samplehq-request-form' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __(
							'Analytics Events',
							'samplehq-request-form'
						) }
						checked={ behavior.analytics_events || false }
						onChange={ ( val ) =>
							onBehavior( { ...behavior, analytics_events: val } )
						}
						help={ __(
							'Fire events for GTM, GA4, and custom scripts on form view, start, step change, and submission.',
							'samplehq-request-form'
						) }
					/>
				</PanelBody>

				<PanelBody
					title={ __(
						'Email Notifications',
						'samplehq-request-form'
					) }
					initialOpen={ false }
				>
					<TextControl
						label={ __(
							'Admin Notification Email',
							'samplehq-request-form'
						) }
						value={ email.notification_email || '' }
						onChange={ ( val ) =>
							onEmail( { ...email, notification_email: val } )
						}
						help={ __(
							'Override the global admin email for this form. Leave blank to use global setting.',
							'samplehq-request-form'
						) }
					/>
					<TextControl
						label={ __( 'From Name', 'samplehq-request-form' ) }
						value={ email.from_name || '' }
						onChange={ ( val ) =>
							onEmail( { ...email, from_name: val } )
						}
						help={ __(
							'Override the sender name for this form. Leave blank to use global setting.',
							'samplehq-request-form'
						) }
					/>
					<ToggleControl
						label={ __(
							'Send Confirmation Email',
							'samplehq-request-form'
						) }
						checked={ email.send_confirmation !== false }
						onChange={ ( val ) =>
							onEmail( { ...email, send_confirmation: val } )
						}
						help={ __(
							'Send a receipt email to the person who submitted the form.',
							'samplehq-request-form'
						) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Appearance', 'samplehq-request-form' ) }
					initialOpen={ false }
				>
					<div className="shqf-settings-color-row">
						<label>
							{ __( 'Primary Color', 'samplehq-request-form' ) }
						</label>
						<input
							type="color"
							value={ appearance.primary_color || '#0F766E' }
							onChange={ ( e ) =>
								onAppearance( {
									...appearance,
									primary_color: e.target.value,
								} )
							}
						/>
						<code>{ appearance.primary_color || '#0F766E' }</code>
					</div>
					<div className="shqf-settings-color-row">
						<label>
							{ __( 'Button Color', 'samplehq-request-form' ) }
						</label>
						<input
							type="color"
							value={
								appearance.button_color ||
								appearance.primary_color ||
								'#0F766E'
							}
							onChange={ ( e ) =>
								onAppearance( {
									...appearance,
									button_color: e.target.value,
								} )
							}
						/>
						<code>
							{ appearance.button_color ||
								appearance.primary_color ||
								'#0F766E' }
						</code>
					</div>
					<TextControl
						label={ __(
							'Border Radius (px)',
							'samplehq-request-form'
						) }
						type="number"
						value={ String( appearance.border_radius ?? 8 ) }
						onChange={ ( val ) =>
							onAppearance( {
								...appearance,
								border_radius: parseInt( val, 10 ) || 0,
							} )
						}
					/>
				</PanelBody>

				{ meta && (
					<PanelBody
						title={ __( 'Info', 'samplehq-request-form' ) }
						initialOpen={ false }
					>
						<div className="shqf-settings-info">
							{ meta.slug && (
								<div className="shqf-settings-info-row">
									<span>
										{ __(
											'Slug',
											'samplehq-request-form'
										) }
									</span>
									<code>{ meta.slug }</code>
								</div>
							) }
							{ meta.shortcode && (
								<div className="shqf-settings-info-row">
									<span>
										{ __(
											'Shortcode',
											'samplehq-request-form'
										) }
									</span>
									<code>{ meta.shortcode }</code>
								</div>
							) }
							<div className="shqf-settings-info-row">
								<span>
									{ __(
										'Submissions',
										'samplehq-request-form'
									) }
								</span>
								<strong>{ meta.submissions_count ?? 0 }</strong>
							</div>
						</div>
					</PanelBody>
				) }
			</Panel>
		</div>
	);
}

/**
 * Conditional logic rule builder for a single field.
 */
const CONDITION_OPERATORS = [
	{ value: 'equals', label: __( 'equals', 'samplehq-request-form' ) },
	{
		value: 'not_equals',
		label: __( 'does not equal', 'samplehq-request-form' ),
	},
	{ value: 'contains', label: __( 'contains', 'samplehq-request-form' ) },
	{
		value: 'not_contains',
		label: __( 'does not contain', 'samplehq-request-form' ),
	},
	{ value: 'empty', label: __( 'is empty', 'samplehq-request-form' ) },
	{
		value: 'not_empty',
		label: __( 'is not empty', 'samplehq-request-form' ),
	},
];

function ConditionalLogicPanel( { field, allFields, onChange } ) {
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

function FieldSettings( { field, fields: allFields, onChange } ) {
	const [ keyManuallyEdited, setKeyManuallyEdited ] = useState( false );

	// Reset manual edit flag when a different field is selected.
	useEffect( () => {
		setKeyManuallyEdited( false );
	}, [ field?.id ] );

	/**
	 * Generate a unique key by appending _2, _3 etc. if the key already exists.
	 * @param baseKey
	 * @param currentFieldId
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

/**
 * Single sortable field card in the canvas.
 * @param root0
 * @param root0.field
 * @param root0.isSelected
 * @param root0.onSelect
 * @param root0.onRemove
 * @param root0.onDuplicate
 * @param root0.isCanvasDragging
 * @param root0.confirmingDeleteId
 */
function SortableField( {
	field,
	isSelected,
	onSelect,
	onRemove,
	onDuplicate,
	isCanvasDragging,
	confirmingDeleteId,
} ) {
	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
		isDragging,
		isOver,
	} = useSortable( { id: field.id } );

	const style = {
		transform: DndCSS.Transform.toString( transform ),
		transition,
		opacity: isDragging ? 0.4 : 1,
	};

	let fieldClass = 'shqf-builder-field';
	if ( isSelected ) {
		fieldClass += ' shqf-builder-field--selected';
	}
	if ( isDragging ) {
		fieldClass += ' shqf-builder-field--dragging';
	}
	if ( isOver && isCanvasDragging ) {
		fieldClass += ' shqf-builder-field--drop-target';
	}

	return (
		<div
			ref={ setNodeRef }
			style={ style }
			className={ fieldClass }
			onClick={ () => onSelect( field.id ) }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					onSelect( field.id );
				}
			} }
			role="button"
			tabIndex={ 0 }
		>
			<div className="shqf-builder-field-header">
				<span
					className="shqf-builder-field-drag"
					{ ...attributes }
					{ ...listeners }
					aria-label={ __(
						'Drag to reorder',
						'samplehq-request-form'
					) }
				>
					<GripVertical size={ 16 } />
				</span>
				<span className="shqf-builder-field-type">
					{ getFieldLabel( field.type ) }
				</span>
				<span className="shqf-builder-field-label">
					{ field.label }
					{ field.required && (
						<span className="shqf-builder-required">*</span>
					) }
				</span>
				<span className="shqf-builder-field-actions">
					<Button
						size="small"
						onClick={ ( e ) => {
							e.stopPropagation();
							onDuplicate( field.id );
						} }
						aria-label={ __(
							'Duplicate field',
							'samplehq-request-form'
						) }
					>
						<Copy size={ 16 } />
					</Button>
					{ confirmingDeleteId === field.id ? (
						<Button
							size="small"
							isDestructive
							className="shqf-confirm-delete"
							onClick={ ( e ) => {
								e.stopPropagation();
								onRemove( field.id );
							} }
							aria-label={ __(
								'Confirm delete',
								'samplehq-request-form'
							) }
						>
							{ __( 'Delete?', 'samplehq-request-form' ) }
						</Button>
					) : (
						<Button
							size="small"
							isDestructive
							onClick={ ( e ) => {
								e.stopPropagation();
								onRemove( field.id );
							} }
							aria-label={ __(
								'Remove field',
								'samplehq-request-form'
							) }
						>
							<Trash2 size={ 16 } />
						</Button>
					) }
				</span>
			</div>
			<FieldPreview field={ field } />
		</div>
	);
}

/**
 * Droppable column zone inside a row group.
 * @param root0
 * @param root0.rowId
 * @param root0.colIndex
 * @param root0.colFields
 * @param root0.selectedId
 * @param root0.onSelect
 * @param root0.onRemove
 * @param root0.onDuplicate
 * @param root0.isDraggingField
 * @param root0.isCanvasDragging
 * @param root0.confirmingDeleteId
 */
function ColumnDropZone( {
	rowId,
	colIndex,
	colFields,
	selectedId,
	onSelect,
	onRemove,
	onDuplicate,
	isDraggingField,
	isCanvasDragging,
	confirmingDeleteId,
} ) {
	const droppableId = 'col_' + rowId + '_' + colIndex;
	const { setNodeRef, isOver } = useDroppable( { id: droppableId } );

	let colClass = 'shqf-builder-row-col';
	if ( isOver ) {
		colClass += ' shqf-builder-row-col--over';
	} else if ( isDraggingField ) {
		colClass += ' shqf-builder-row-col--ready';
	}

	return (
		<div ref={ setNodeRef } className={ colClass }>
			<SortableContext
				items={ colFields.map( ( f ) => f.id ) }
				strategy={ verticalListSortingStrategy }
			>
				{ colFields.length === 0 && (
					<div className="shqf-builder-row-col-empty">
						{ isDraggingField
							? __( 'Drop here', 'samplehq-request-form' )
							: __(
									'Drop fields here',
									'samplehq-request-form'
							  ) }
					</div>
				) }
				{ colFields.map( ( child ) => (
					<SortableField
						key={ child.id }
						field={ child }
						isSelected={ selectedId === child.id }
						onSelect={ onSelect }
						onRemove={ onRemove }
						onDuplicate={ onDuplicate }
						isCanvasDragging={ isCanvasDragging }
						confirmingDeleteId={ confirmingDeleteId }
					/>
				) ) }
			</SortableContext>
		</div>
	);
}

/**
 * Sortable row group -- renders a row container with column drop zones.
 * @param root0
 * @param root0.row
 * @param root0.isSelected
 * @param root0.selectedId
 * @param root0.onSelect
 * @param root0.onRemove
 * @param root0.onDuplicate
 * @param root0.isDraggingField
 * @param root0.confirmingDeleteId
 */
function SortableRowGroup( {
	row,
	isSelected,
	selectedId,
	onSelect,
	onRemove,
	onDuplicate,
	isDraggingField,
	confirmingDeleteId,
} ) {
	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( { id: row.id } );

	const style = {
		transform: DndCSS.Transform.toString( transform ),
		transition,
		opacity: isDragging ? 0.5 : 1,
	};

	const columns = row.columns || [];

	return (
		<div
			ref={ setNodeRef }
			style={ style }
			className={ `shqf-builder-row ${
				isSelected ? 'shqf-builder-row--selected' : ''
			}` }
			onClick={ ( e ) => {
				// Only select row when clicking the row header, not column contents.
				if ( e.target.closest( '.shqf-builder-row-header' ) ) {
					onSelect( row.id );
				}
			} }
		>
			<div className="shqf-builder-row-header">
				<span
					className="shqf-builder-field-drag"
					{ ...attributes }
					{ ...listeners }
					aria-label={ __(
						'Drag to reorder row',
						'samplehq-request-form'
					) }
				>
					<GripVertical size={ 16 } />
				</span>
				<Columns2 size={ 16 } aria-hidden="true" />
				<span className="shqf-builder-row-label">
					{ __( 'Row', 'samplehq-request-form' ) } &middot;{ ' ' }
					{ columns.length }{ ' ' }
					{ __( 'columns', 'samplehq-request-form' ) }
				</span>
				<span className="shqf-builder-field-actions">
					<Button
						size="small"
						onClick={ ( e ) => {
							e.stopPropagation();
							onDuplicate( row.id );
						} }
						aria-label={ __(
							'Duplicate row',
							'samplehq-request-form'
						) }
					>
						<Copy size={ 16 } />
					</Button>
					<Button
						size="small"
						isDestructive
						onClick={ ( e ) => {
							e.stopPropagation();
							onRemove( row.id );
						} }
						aria-label={ __(
							'Remove row',
							'samplehq-request-form'
						) }
					>
						<Trash2 size={ 16 } />
					</Button>
				</span>
			</div>
			<div className="shqf-builder-row-columns">
				{ columns.map( ( col, ci ) => (
					<ColumnDropZone
						key={ ci }
						rowId={ row.id }
						colIndex={ ci }
						colFields={ col.fields || [] }
						selectedId={ selectedId }
						onSelect={ onSelect }
						onRemove={ onRemove }
						onDuplicate={ onDuplicate }
						isDraggingField={ isDraggingField }
						isCanvasDragging={ isDraggingField }
						confirmingDeleteId={ confirmingDeleteId }
					/>
				) ) }
			</div>
		</div>
	);
}

/**
 * Form Canvas -- shows form fields with visual previews. DndContext is at the parent level.
 */
function CanvasDropZone() {
	const { setNodeRef, isOver } = useDroppable( { id: 'canvas-drop-zone' } );
	return (
		<div
			ref={ setNodeRef }
			className={ `shqf-builder-canvas-empty ${
				isOver ? 'shqf-builder-canvas-empty--over' : ''
			}` }
		>
			<LayoutGrid size={ 48 } strokeWidth={ 1 } />
			<p>
				{ __(
					'Click or drag a field from the left to add it.',
					'samplehq-request-form'
				) }
			</p>
		</div>
	);
}

function FormCanvas( {
	fields,
	selectedId,
	onSelect,
	onRemove,
	onDuplicate,
	confirmingDeleteId,
	isDragging,
	isDraggingRow,
} ) {
	if ( fields.length === 0 ) {
		return (
			<div className="shqf-builder-canvas-wrap">
				<CanvasDropZone />
			</div>
		);
	}

	const topLevelIds = fields.map( ( f ) => f.id );

	return (
		<div
			className={ `shqf-builder-canvas-wrap ${
				isDragging ? 'shqf-builder-canvas-wrap--dragging' : ''
			}` }
			onClick={ ( e ) => {
				if ( e.target === e.currentTarget ) {
					onSelect( null );
				}
			} }
		>
			<SortableContext
				items={ topLevelIds }
				strategy={ verticalListSortingStrategy }
			>
				<div className="shqf-builder-canvas">
					{ fields.map( ( field ) =>
						field.type === 'row' ? (
							<SortableRowGroup
								key={ field.id }
								row={ field }
								isSelected={ selectedId === field.id }
								selectedId={ selectedId }
								onSelect={ onSelect }
								onRemove={ onRemove }
								onDuplicate={ onDuplicate }
								isDraggingField={
									isDragging && ! isDraggingRow
								}
								confirmingDeleteId={ confirmingDeleteId }
							/>
						) : (
							<SortableField
								key={ field.id }
								field={ field }
								isSelected={ selectedId === field.id }
								onSelect={ onSelect }
								onRemove={ onRemove }
								onDuplicate={ onDuplicate }
								isCanvasDragging={ isDragging }
								confirmingDeleteId={ confirmingDeleteId }
							/>
						)
					) }
				</div>
			</SortableContext>
		</div>
	);
}

/**
 * Main Form Builder app.
 */
// ---------------------------------------------------------------------------
// Undo/Redo history for the fields array.
// ---------------------------------------------------------------------------

const HISTORY_LIMIT = 30;

function historyReducer( state, action ) {
	switch ( action.type ) {
		case 'SET': {
			// Push new state, truncate future (redo stack).
			const past = [ ...state.past, state.present ].slice(
				-HISTORY_LIMIT
			);
			return { past, present: action.fields, future: [] };
		}
		case 'UNDO': {
			if ( state.past.length === 0 ) {
				return state;
			}
			const prev = state.past[ state.past.length - 1 ];
			const past = state.past.slice( 0, -1 );
			return {
				past,
				present: prev,
				future: [ state.present, ...state.future ],
			};
		}
		case 'REDO': {
			if ( state.future.length === 0 ) {
				return state;
			}
			const next = state.future[ 0 ];
			const future = state.future.slice( 1 );
			return {
				past: [ ...state.past, state.present ],
				present: next,
				future,
			};
		}
		case 'REPLACE': {
			// Replace present without pushing to history (used for initial load).
			return { ...state, present: action.fields };
		}
		default:
			return state;
	}
}

function useFieldsHistory( initial ) {
	const [ state, dispatch ] = useReducer( historyReducer, {
		past: [],
		present: initial,
		future: [],
	} );

	// Ref always points to latest present (avoids stale closure in setFields updater).
	const presentRef = useRef( state.present );
	presentRef.current = state.present;

	const setFields = useCallback( ( updater ) => {
		const newFields =
			typeof updater === 'function'
				? updater( presentRef.current )
				: updater;
		dispatch( { type: 'SET', fields: newFields } );
	}, [] );

	const undo = useCallback( () => dispatch( { type: 'UNDO' } ), [] );
	const redo = useCallback( () => dispatch( { type: 'REDO' } ), [] );

	return {
		fields: state.present,
		setFields,
		undo,
		redo,
		canUndo: state.past.length > 0,
		canRedo: state.future.length > 0,
	};
}

function FormBuilder( {
	formId,
	formTitle: initialTitle,
	config: initialConfig,
	meta,
} ) {
	const { fields, setFields, undo, redo, canUndo, canRedo } =
		useFieldsHistory( initialConfig?.fields || [] );
	const [ title, setTitle ] = useState( initialTitle || '' );
	const [ subtitle, setSubtitle ] = useState( initialConfig?.subtitle || '' );
	const [ layout, setLayout ] = useState( initialConfig?.layout || '' );
	const [ behavior, setBehavior ] = useState( initialConfig?.behavior || {} );
	const [ appearance, setAppearance ] = useState(
		initialConfig?.appearance || {}
	);
	const [ display, setDisplay ] = useState( initialConfig?.display || {} );
	const [ email, setEmail ] = useState( initialConfig?.email || {} );
	const [ status, setStatus ] = useState( meta?.status || 'draft' );
	const [ selectedId, setSelectedId ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ confirmingDeleteId, setConfirmingDeleteId ] = useState( null );

	// Ref always points to latest fields (avoids stale closures in callbacks).
	const fieldsRef = useRef( fields );
	fieldsRef.current = fields;

	// Auto-clear inline delete confirmation after 3 seconds.
	useEffect( () => {
		if ( ! confirmingDeleteId ) {
			return;
		}
		const timer = setTimeout( () => setConfirmingDeleteId( null ), 3000 );
		return () => clearTimeout( timer );
	}, [ confirmingDeleteId ] );

	// Find selected field anywhere in the tree (top-level or inside rows).
	// Clear stale selectedId when the field no longer exists (e.g. after undo).
	const selectedField = findFieldInTree( fields, selectedId ) || null;
	useEffect( () => {
		if ( selectedId && ! selectedField ) {
			setSelectedId( null );
		}
	}, [ selectedId, selectedField ] );

	const addField = useCallback( ( type ) => {
		const newField = createField( type );
		setFields( ( prev ) => [ ...prev, newField ] );
		setSelectedId( newField.id );
		// Scroll to the new field after React renders it.
		requestAnimationFrame( () => {
			const el =
				document.querySelector(
					`[data-id="${ newField.id }"], #${ newField.id }`
				) ||
				document.querySelector( '.shqf-builder-canvas > :last-child' );
			el?.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		} );
	}, [] );

	const removeField = useCallback( ( id ) => {
		setFields( ( prev ) => removeFieldFromTree( prev, id ) );
		setSelectedId( ( prev ) => ( prev === id ? null : prev ) );
		setConfirmingDeleteId( null );
	}, [] );

	const requestDelete = useCallback(
		( id ) => {
			if ( confirmingDeleteId === id ) {
				removeField( id );
			} else {
				setConfirmingDeleteId( id );
			}
		},
		[ confirmingDeleteId, removeField ]
	);

	const updateField = useCallback( ( updatedField ) => {
		setFields( ( prev ) => updateFieldInTree( prev, updatedField ) );
	}, [] );

	const duplicateField = useCallback( ( id ) => {
		setFields( ( prev ) => {
			const source = findFieldInTree( prev, id );
			if ( ! source ) {
				return prev;
			}

			let clone;
			if ( source.type === 'row' ) {
				// Deep clone: new IDs for the row and all children.
				const newRowId =
					'row_' + Math.random().toString( 36 ).substring( 2, 9 );
				clone = {
					...source,
					id: newRowId,
					columns: ( source.columns || [] ).map( ( col ) => ( {
						...col,
						fields: ( col.fields || [] ).map( ( child ) => {
							const childId = generateFieldId();
							const childBaseKey = child.key.replace(
								/_copy.*$/,
								''
							);
							return {
								...child,
								id: childId,
								key: childBaseKey + '_' + childId.slice( 2 ),
							};
						} ),
					} ) ),
				};
			} else {
				const newId = generateFieldId();
				const baseKey = source.key.replace( /_copy.*$/, '' );
				clone = {
					...source,
					id: newId,
					key: baseKey + '_' + newId.slice( 2 ),
					label:
						source.label +
						' ' +
						__( '(Copy)', 'samplehq-request-form' ),
				};
			}

			return insertAfterInTree( prev, id, clone );
		} );
	}, [] );

	const reorderFields = useCallback( ( activeId, overId ) => {
		setFields( ( prev ) => {
			const activeLoc = findFieldLocation( prev, activeId );
			const overLoc = findFieldLocation( prev, overId );
			if ( ! activeLoc || ! overLoc ) {
				return prev;
			}

			// Prevent dropping a row inside another row.
			const activeField = findFieldInTree( prev, activeId );
			if ( activeField?.type === 'row' && overLoc.container !== 'top' ) {
				return prev;
			}

			if ( activeLoc.container === overLoc.container ) {
				if ( activeLoc.container === 'top' ) {
					return arrayMove( prev, activeLoc.index, overLoc.index );
				}
				return reorderInColumn(
					prev,
					activeLoc.rowId,
					activeLoc.colIndex,
					activeLoc.index,
					overLoc.index
				);
			}
			// Cross-container: remove then insert at target position.
			if ( ! activeField ) {
				return prev;
			}
			let next = removeFieldFromTree( prev, activeId );
			next = insertBeforeInTree( next, overId, activeField );
			return next;
		} );
	}, [] );

	const moveToColumn = useCallback( ( fieldId, rowId, colIndex ) => {
		setFields( ( prev ) => {
			const field = findFieldInTree( prev, fieldId );
			if ( ! field ) {
				return prev;
			}
			// Prevent dropping a row into another row.
			if ( field.type === 'row' ) {
				return prev;
			}
			// Prevent dropping a field into its own row (it's already there).
			const loc = findFieldLocation( prev, fieldId );
			if ( loc && loc.rowId === rowId && loc.colIndex === colIndex ) {
				return prev;
			}
			let next = removeFieldFromTree( prev, fieldId );
			next = insertIntoColumn( next, rowId, colIndex, field );
			return next;
		} );
	}, [] );

	// Refs tracking last-saved state for dirty detection.
	const savedFieldsRef = useRef(
		JSON.stringify( initialConfig?.fields || [] )
	);
	const savedTitleRef = useRef( initialTitle || '' );
	const savedStatusRef = useRef( meta?.status || 'draft' );
	const savedSubtitleRef = useRef( initialConfig?.subtitle || '' );
	const savedLayoutRef = useRef( initialConfig?.layout || '' );
	const savedBehaviorRef = useRef(
		JSON.stringify( initialConfig?.behavior || {} )
	);
	const savedAppearanceRef = useRef(
		JSON.stringify( initialConfig?.appearance || {} )
	);
	const savedDisplayRef = useRef(
		JSON.stringify( initialConfig?.display || {} )
	);
	const savedEmailRef = useRef(
		JSON.stringify( initialConfig?.email || {} )
	);
	const configRef = useRef( initialConfig );

	const saveForm = useCallback( async () => {
		setSaving( true );
		setNotice( null );

		// Merge all builder-managed state into the config.
		const configToSave = {
			...( configRef.current || {} ),
			schema_version: 1,
			fields,
			subtitle,
			layout,
			behavior,
			appearance,
			display,
			email,
		};

		try {
			if ( formId > 0 ) {
				await apiFetch( {
					path: `/samplehq-form/v1/forms/${ formId }`,
					method: 'PUT',
					data: { title, status, config: configToSave },
				} );
			}

			savedFieldsRef.current = JSON.stringify( fields );
			savedTitleRef.current = title;
			savedStatusRef.current = status;
			savedSubtitleRef.current = subtitle;
			savedLayoutRef.current = layout;
			savedBehaviorRef.current = JSON.stringify( behavior );
			savedAppearanceRef.current = JSON.stringify( appearance );
			savedDisplayRef.current = JSON.stringify( display );
			savedEmailRef.current = JSON.stringify( email );
			configRef.current = configToSave;
			setNotice( {
				status: 'success',
				message: __( 'Form saved.', 'samplehq-request-form' ),
			} );
		} catch {
			setNotice( {
				status: 'error',
				message: __( 'Failed to save form.', 'samplehq-request-form' ),
			} );
		}

		setSaving( false );
	}, [
		fields,
		title,
		subtitle,
		layout,
		behavior,
		appearance,
		display,
		email,
		status,
		formId,
	] );

	// Auto-save when status changes (Publish/Draft toggle).
	const pendingSaveRef = useRef( false );
	useEffect( () => {
		if ( pendingSaveRef.current ) {
			pendingSaveRef.current = false;
			saveForm();
		}
	}, [ status, saveForm ] );

	const isPublished = status === 'published';

	// Keyboard shortcuts: Undo/Redo, Delete/Backspace, Escape.
	useEffect( () => {
		const handleKeyDown = ( e ) => {
			const mod = e.metaKey || e.ctrlKey;

			// Undo: Ctrl/Cmd+Z (without Shift).
			if ( mod && e.key === 'z' && ! e.shiftKey ) {
				const tag = document.activeElement?.tagName;
				if (
					tag === 'INPUT' ||
					tag === 'TEXTAREA' ||
					document.activeElement?.isContentEditable
				) {
					return; // Let native undo handle text inputs.
				}
				e.preventDefault();
				undo();
				return;
			}

			// Redo: Ctrl/Cmd+Shift+Z or Ctrl/Cmd+Y.
			if (
				( mod && e.key === 'z' && e.shiftKey ) ||
				( mod && e.key === 'y' )
			) {
				const tag = document.activeElement?.tagName;
				if (
					tag === 'INPUT' ||
					tag === 'TEXTAREA' ||
					document.activeElement?.isContentEditable
				) {
					return;
				}
				e.preventDefault();
				redo();
				return;
			}

			// Escape deselects the current field.
			if ( e.key === 'Escape' && selectedId ) {
				setSelectedId( null );
				return;
			}

			if ( ! selectedId ) {
				return;
			}

			if ( e.key !== 'Delete' && e.key !== 'Backspace' ) {
				return;
			}

			// Don't trigger when typing in inputs, textareas, selects, or contenteditable.
			const tag = document.activeElement?.tagName;
			if ( tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' ) {
				return;
			}
			if ( document.activeElement?.isContentEditable ) {
				return;
			}

			e.preventDefault();
			requestDelete( selectedId );
		};

		document.addEventListener( 'keydown', handleKeyDown );
		return () => document.removeEventListener( 'keydown', handleKeyDown );
	}, [ selectedId, requestDelete, undo, redo ] );

	// Track dirty state (unsaved changes).
	const isDirty =
		JSON.stringify( fields ) !== savedFieldsRef.current ||
		title !== savedTitleRef.current ||
		status !== savedStatusRef.current ||
		subtitle !== savedSubtitleRef.current ||
		layout !== savedLayoutRef.current ||
		JSON.stringify( behavior ) !== savedBehaviorRef.current ||
		JSON.stringify( appearance ) !== savedAppearanceRef.current ||
		JSON.stringify( display ) !== savedDisplayRef.current ||
		JSON.stringify( email ) !== savedEmailRef.current;

	// Warn on unsaved changes when navigating away.
	useEffect( () => {
		const handler = ( e ) => {
			if ( isDirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', handler );
		return () => window.removeEventListener( 'beforeunload', handler );
	}, [ isDirty ] );

	// Auto-dismiss notice after 3 seconds.
	useEffect( () => {
		if ( ! notice ) {
			return;
		}
		const timer = setTimeout( () => setNotice( null ), 3000 );
		return () => clearTimeout( timer );
	}, [ notice ] );

	// -------------------------------------------------------------------------
	// DnD: sensors + drag state (shared by palette and canvas).
	// -------------------------------------------------------------------------
	const dndSensors = useSensors(
		useSensor( PointerSensor, { activationConstraint: { distance: 5 } } ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	const [ activeDragId, setActiveDragId ] = useState( null );
	const activeDragIdStr = activeDragId ? String( activeDragId ) : null;
	const isNewFieldDrag = activeDragIdStr?.startsWith( 'new_' );
	const activeDragField =
		activeDragId && ! isNewFieldDrag
			? findFieldInTree( fields, activeDragId )
			: null;
	const activeDragType = isNewFieldDrag ? activeDragIdStr.slice( 4 ) : null;
	const builderIsDragging = activeDragId !== null;
	const builderIsDraggingRow =
		activeDragField?.type === 'row' || activeDragType === 'row';

	const handleDragStart = useCallback( ( event ) => {
		setActiveDragId( event.active.id );
	}, [] );

	const handleDragEnd = useCallback(
		( event ) => {
			setActiveDragId( null );

			const { active, over } = event;
			if ( ! active || ! over ) {
				return;
			}

			const activeIdStr = String( active.id );
			const overId = String( over.id );

			// --- Palette drag: create a new field at the drop position ---
			if ( activeIdStr.startsWith( 'new_' ) ) {
				const type = activeIdStr.slice( 4 );
				const newField = createField( type );

				if ( overId.startsWith( 'col_' ) ) {
					// Prevent dropping a row into a column.
					if ( type === 'row' ) {
						return;
					}
					const match = overId.match( /^col_(.+)_(\d+)$/ );
					if ( match ) {
						setFields( ( prev ) =>
							insertIntoColumn(
								prev,
								match[ 1 ],
								parseInt( match[ 2 ], 10 ),
								newField
							)
						);
					}
				} else if ( overId === 'canvas-drop-zone' ) {
					// Drop on empty canvas or at end.
					setFields( ( prev ) => [ ...prev, newField ] );
				} else {
					// Drop before an existing field.
					setFields( ( prev ) =>
						insertBeforeInTree( prev, overId, newField )
					);
				}

				setSelectedId( newField.id );
				requestAnimationFrame( () => {
					const el = document.querySelector(
						'.shqf-builder-canvas > :last-child'
					);
					el?.scrollIntoView( {
						behavior: 'smooth',
						block: 'center',
					} );
				} );
				return;
			}

			// --- Existing field drag ---
			if ( active.id === over.id ) {
				return;
			}

			if ( overId.startsWith( 'col_' ) ) {
				const match = overId.match( /^col_(.+)_(\d+)$/ );
				if ( match ) {
					moveToColumn(
						activeIdStr,
						match[ 1 ],
						parseInt( match[ 2 ], 10 )
					);
				}
				return;
			}

			reorderFields( activeIdStr, overId );
		},
		[ setFields, setSelectedId, moveToColumn, reorderFields ]
	);

	const handleDragCancel = useCallback( () => {
		setActiveDragId( null );
	}, [] );

	// Build drag overlay content.
	let dragOverlayContent = null;
	if ( activeDragField ) {
		dragOverlayContent = (
			<div className="shqf-builder-drag-overlay">
				<div className="shqf-builder-drag-overlay-header">
					<GripVertical size={ 14 } />
					<span className="shqf-builder-field-type">
						{ getFieldLabel( activeDragField.type ) }
					</span>
					<span className="shqf-builder-field-label">
						{ activeDragField.label || '' }
					</span>
				</div>
			</div>
		);
	} else if ( isNewFieldDrag ) {
		const typeDef = ALL_FIELD_TYPES.find(
			( t ) => t.type === activeDragType
		);
		if ( typeDef ) {
			const OverlayIcon = typeDef.icon;
			dragOverlayContent = (
				<div className="shqf-builder-drag-overlay shqf-builder-drag-overlay--new">
					<div className="shqf-builder-drag-overlay-header">
						<OverlayIcon size={ 16 } />
						<span>{ typeDef.label }</span>
					</div>
				</div>
			);
		}
	}

	return (
		<div className="shqf-builder">
			<div className="shqf-builder-header">
				<div className="shqf-builder-header-left">
					<a
						href={ meta?.back_url || '#' }
						className="shqf-builder-back"
						aria-label={ __(
							'Back to forms',
							'samplehq-request-form'
						) }
						onClick={ ( e ) => {
							// eslint-disable-next-line no-alert
							if (
								isDirty &&
								! window.confirm(
									__(
										'You have unsaved changes. Leave anyway?',
										'samplehq-request-form'
									)
								)
							) {
								e.preventDefault();
							}
						} }
					>
						<ArrowLeft size={ 18 } />
					</a>
					<input
						type="text"
						className="shqf-builder-title"
						value={ title }
						onChange={ ( e ) => setTitle( e.target.value ) }
						placeholder={ __(
							'Form title',
							'samplehq-request-form'
						) }
					/>
					<span
						className="shqf-builder-title-pencil"
						aria-hidden="true"
					>
						<Pencil size={ 14 } />
					</span>
					{ notice && (
						<span
							className={ `shqf-builder-notice shqf-builder-notice--${ notice.status }` }
						>
							{ notice.message }
						</span>
					) }
				</div>
				<div className="shqf-builder-header-right">
					<Button
						className="shqf-builder-undo-btn"
						onClick={ undo }
						disabled={ ! canUndo }
						aria-label={ __( 'Undo', 'samplehq-request-form' ) }
						title={ __( 'Undo (Ctrl+Z)', 'samplehq-request-form' ) }
					>
						<Undo2 size={ 16 } />
					</Button>
					<Button
						className="shqf-builder-redo-btn"
						onClick={ redo }
						disabled={ ! canRedo }
						aria-label={ __( 'Redo', 'samplehq-request-form' ) }
						title={ __(
							'Redo (Ctrl+Shift+Z)',
							'samplehq-request-form'
						) }
					>
						<Redo2 size={ 16 } />
					</Button>
					{ meta?.preview_url && (
						<a
							href={ meta.preview_url }
							className="shqf-builder-preview-btn"
							target="_blank"
							rel="noopener noreferrer"
							onClick={ ( e ) => {
								if ( isDirty ) {
									e.preventDefault();
									saveForm().then( () => {
										window.open(
											meta.preview_url,
											'_blank'
										);
									} );
								}
							} }
						>
							{ __( 'Preview', 'samplehq-request-form' ) }
						</a>
					) }
					<Button
						variant="secondary"
						className="shqf-builder-status-toggle"
						onClick={ () => {
							setStatus( isPublished ? 'draft' : 'published' );
							pendingSaveRef.current = true;
						} }
					>
						{ isPublished
							? __( 'Switch to Draft', 'samplehq-request-form' )
							: __( 'Publish', 'samplehq-request-form' ) }
					</Button>
					<Button
						variant="primary"
						className={ isDirty ? 'shqf-save-dirty' : '' }
						onClick={ saveForm }
						isBusy={ saving }
						disabled={ saving }
					>
						{ saving
							? __( 'Saving…', 'samplehq-request-form' )
							: __( 'Save', 'samplehq-request-form' ) }
					</Button>
				</div>
			</div>

			<DndContext
				sensors={ dndSensors }
				collisionDetection={ closestCorners }
				onDragStart={ handleDragStart }
				onDragEnd={ handleDragEnd }
				onDragCancel={ handleDragCancel }
			>
				<div className="shqf-builder-layout">
					<FieldPalette onAdd={ addField } />
					<FormCanvas
						fields={ fields }
						selectedId={ selectedId }
						onSelect={ setSelectedId }
						onRemove={ requestDelete }
						confirmingDeleteId={ confirmingDeleteId }
						onDuplicate={ duplicateField }
						isDragging={ builderIsDragging }
						isDraggingRow={ builderIsDraggingRow }
					/>
					{ selectedField ? (
						<FieldSettings
							field={ selectedField }
							fields={ fields }
							onChange={ updateField }
						/>
					) : (
						<FormSettingsPanel
							subtitle={ subtitle }
							layout={ layout }
							behavior={ behavior }
							appearance={ appearance }
							display={ display }
							email={ email }
							meta={ meta }
							onSubtitle={ setSubtitle }
							onLayout={ setLayout }
							onBehavior={ setBehavior }
							onAppearance={ setAppearance }
							onDisplay={ setDisplay }
							onEmail={ setEmail }
						/>
					) }
				</div>
				<DragOverlay dropAnimation={ null }>
					{ dragOverlayContent }
				</DragOverlay>
			</DndContext>
		</div>
	);
}

// Mount the app.
document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'shqf-form-builder-root' );
	if ( ! root ) {
		return;
	}

	const formId = parseInt( root.dataset.formId || '0', 10 );
	const formTitle = root.dataset.formTitle || '';
	const config = root.dataset.config
		? JSON.parse( root.dataset.config )
		: null;
	const meta = root.dataset.formMeta
		? JSON.parse( root.dataset.formMeta )
		: null;

	render(
		<FormBuilder
			formId={ formId }
			formTitle={ formTitle }
			config={ config }
			meta={ meta }
		/>,
		root
	);
} );
