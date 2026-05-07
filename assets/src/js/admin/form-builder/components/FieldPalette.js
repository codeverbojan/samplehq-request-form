import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDraggable } from '@dnd-kit/core';
import { FIELD_CATEGORIES } from '../constants';

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
			onKeyDown={ ( e ) => {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					onAdd( fieldType.type );
				}
			} }
		>
			<IconComponent size={ 20 } strokeWidth={ 1.5 } aria-hidden="true" />
			<span>{ fieldType.label }</span>
		</div>
	);
}

export { DraggablePaletteItem };

export default function FieldPalette( { onAdd } ) {
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
