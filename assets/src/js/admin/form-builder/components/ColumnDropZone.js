import { __ } from '@wordpress/i18n';
import { useDroppable } from '@dnd-kit/core';
import {
	SortableContext,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';

import SortableField from './SortableField';

export default function ColumnDropZone( {
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
