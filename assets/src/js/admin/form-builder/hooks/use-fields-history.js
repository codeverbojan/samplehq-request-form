import { useReducer, useRef, useCallback } from '@wordpress/element';
import { HISTORY_LIMIT } from '../constants';

export function historyReducer( state, action ) {
	switch ( action.type ) {
		case 'SET': {
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
			return { ...state, present: action.fields };
		}
		default:
			return state;
	}
}

export function useFieldsHistory( initial ) {
	const [ state, dispatch ] = useReducer( historyReducer, {
		past: [],
		present: initial,
		future: [],
	} );

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
