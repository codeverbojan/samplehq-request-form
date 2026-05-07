import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useSortable } from '@dnd-kit/sortable';
import { CSS as DndCSS } from '@dnd-kit/utilities';
import { GripVertical, Copy, Trash2, Columns2 } from 'lucide-react';

import ColumnDropZone from './ColumnDropZone';

export default function SortableRowGroup( {
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
			role="button"
			tabIndex={ 0 }
			onClick={ ( e ) => {
				// Only select row when clicking the row header, not column contents.
				if ( e.target.closest( '.shqf-builder-row-header' ) ) {
					onSelect( row.id );
				}
			} }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					if ( e.target.closest( '.shqf-builder-row-header' ) ) {
						onSelect( row.id );
					}
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
