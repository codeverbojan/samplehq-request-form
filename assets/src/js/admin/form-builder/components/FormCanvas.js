import { __ } from '@wordpress/i18n';
import { useDroppable } from '@dnd-kit/core';
import {
	SortableContext,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { LayoutGrid } from 'lucide-react';

import SortableField from './SortableField';
import SortableRowGroup from './SortableRowGroup';

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

export { CanvasDropZone };

export default function FormCanvas( {
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
			role="button"
			tabIndex={ 0 }
			onClick={ ( e ) => {
				if ( e.target === e.currentTarget ) {
					onSelect( null );
				}
			} }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					if ( e.target === e.currentTarget ) {
						onSelect( null );
					}
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
