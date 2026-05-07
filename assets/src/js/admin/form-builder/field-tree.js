import { arrayMove } from '@dnd-kit/sortable';

export function flattenFields( fields ) {
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

export function findFieldInTree( fields, id ) {
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

export function removeFieldFromTree( fields, id ) {
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

export function updateFieldInTree( fields, updatedField ) {
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

export function insertBeforeInTree( fields, targetId, newField ) {
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

export function insertAfterInTree( fields, targetId, newField ) {
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

export function insertIntoColumn( fields, rowId, colIndex, newField ) {
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

export function findFieldLocation( fields, id ) {
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

export function reorderInColumn( fields, rowId, colIndex, oldIdx, newIdx ) {
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
