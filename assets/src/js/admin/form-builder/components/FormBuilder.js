import { useState, useCallback, useEffect, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
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
} from '@dnd-kit/core';
import { arrayMove, sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import { GripVertical, ArrowLeft, Pencil, Undo2, Redo2 } from 'lucide-react';

import { ALL_FIELD_TYPES } from '../constants';
import { generateFieldId, createField } from '../field-factory';
import {
	findFieldInTree,
	removeFieldFromTree,
	updateFieldInTree,
	insertBeforeInTree,
	insertAfterInTree,
	insertIntoColumn,
	findFieldLocation,
	reorderInColumn,
} from '../field-tree';
import { useFieldsHistory } from '../hooks/use-fields-history';
import { getFieldLabel } from '../utils';
import FieldPalette from './FieldPalette';
import FormSettingsPanel from './FormSettingsPanel';
import FieldSettings from './FieldSettings';
import FormCanvas from './FormCanvas';

export default function FormBuilder( {
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
		window.requestAnimationFrame( () => {
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
		if ( ! ( formId > 0 ) ) {
			return;
		}

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
			await apiFetch( {
				path: `/samplehq-form/v1/forms/${ formId }`,
				method: 'PUT',
				data: { title, status, config: configToSave },
			} );

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
				// eslint-disable-next-line @wordpress/no-global-active-element -- global keydown handler, no ref available.
				const tag = document.activeElement?.tagName;
				if (
					tag === 'INPUT' ||
					tag === 'TEXTAREA' ||
					// eslint-disable-next-line @wordpress/no-global-active-element -- global keydown handler, no ref available.
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
				// eslint-disable-next-line @wordpress/no-global-active-element -- global keydown handler, no ref available.
				const tag = document.activeElement?.tagName;
				if (
					tag === 'INPUT' ||
					tag === 'TEXTAREA' ||
					// eslint-disable-next-line @wordpress/no-global-active-element -- global keydown handler, no ref available.
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
			// eslint-disable-next-line @wordpress/no-global-active-element -- global keydown handler, no ref available.
			const tag = document.activeElement?.tagName;
			if ( tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' ) {
				return;
			}
			// eslint-disable-next-line @wordpress/no-global-active-element -- global keydown handler, no ref available.
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
			? findFieldInTree( fields, activeDragIdStr )
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
				window.requestAnimationFrame( () => {
					const el =
						document.querySelector(
							`[data-id="${ newField.id }"], #${ newField.id }`
						) ||
						document.querySelector(
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
							/* eslint-disable no-alert */
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
							/* eslint-enable no-alert */
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
