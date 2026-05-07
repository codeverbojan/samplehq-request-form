import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useSortable } from '@dnd-kit/sortable';
import { CSS as DndCSS } from '@dnd-kit/utilities';
import { GripVertical, Copy, Trash2 } from 'lucide-react';

import { getFieldLabel } from '../utils';
import FieldPreview from './FieldPreview';

export default function SortableField( {
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
