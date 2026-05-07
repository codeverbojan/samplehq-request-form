import { historyReducer } from './use-fields-history';

const initial = () => ( {
	past: [],
	present: [ 'a' ],
	future: [],
} );

describe( 'historyReducer', () => {
	describe( 'SET', () => {
		it( 'pushes current present to past and sets new present', () => {
			const state = initial();
			const next = historyReducer( state, {
				type: 'SET',
				fields: [ 'b' ],
			} );
			expect( next.present ).toEqual( [ 'b' ] );
			expect( next.past ).toEqual( [ [ 'a' ] ] );
			expect( next.future ).toEqual( [] );
		} );

		it( 'clears the future (redo stack)', () => {
			const state = {
				past: [],
				present: [ 'a' ],
				future: [ [ 'x' ], [ 'y' ] ],
			};
			const next = historyReducer( state, {
				type: 'SET',
				fields: [ 'b' ],
			} );
			expect( next.future ).toEqual( [] );
		} );

		it( 'truncates past to HISTORY_LIMIT', () => {
			const past = Array.from( { length: 35 }, ( _, i ) => [ i ] );
			const state = { past, present: [ 'current' ], future: [] };
			const next = historyReducer( state, {
				type: 'SET',
				fields: [ 'new' ],
			} );
			expect( next.past ).toHaveLength( 30 );
			expect( next.past[ 0 ] ).toEqual( [ 6 ] );
			expect( next.past[ 29 ] ).toEqual( [ 'current' ] );
		} );
	} );

	describe( 'UNDO', () => {
		it( 'moves present to future and restores last past', () => {
			const state = {
				past: [ [ 'old' ] ],
				present: [ 'current' ],
				future: [],
			};
			const next = historyReducer( state, { type: 'UNDO' } );
			expect( next.present ).toEqual( [ 'old' ] );
			expect( next.past ).toEqual( [] );
			expect( next.future ).toEqual( [ [ 'current' ] ] );
		} );

		it( 'returns same state when past is empty', () => {
			const state = initial();
			const next = historyReducer( state, { type: 'UNDO' } );
			expect( next ).toBe( state );
		} );

		it( 'preserves existing future entries', () => {
			const state = {
				past: [ [ 'p1' ] ],
				present: [ 'current' ],
				future: [ [ 'f1' ] ],
			};
			const next = historyReducer( state, { type: 'UNDO' } );
			expect( next.future ).toEqual( [ [ 'current' ], [ 'f1' ] ] );
		} );
	} );

	describe( 'REDO', () => {
		it( 'moves first future to present, pushes present to past', () => {
			const state = {
				past: [],
				present: [ 'current' ],
				future: [ [ 'next' ] ],
			};
			const next = historyReducer( state, { type: 'REDO' } );
			expect( next.present ).toEqual( [ 'next' ] );
			expect( next.past ).toEqual( [ [ 'current' ] ] );
			expect( next.future ).toEqual( [] );
		} );

		it( 'returns same state when future is empty', () => {
			const state = initial();
			const next = historyReducer( state, { type: 'REDO' } );
			expect( next ).toBe( state );
		} );

		it( 'preserves remaining future entries', () => {
			const state = {
				past: [],
				present: [ 'a' ],
				future: [ [ 'b' ], [ 'c' ] ],
			};
			const next = historyReducer( state, { type: 'REDO' } );
			expect( next.future ).toEqual( [ [ 'c' ] ] );
		} );
	} );

	describe( 'REPLACE', () => {
		it( 'replaces present without affecting past or future', () => {
			const state = {
				past: [ [ 'p1' ] ],
				present: [ 'current' ],
				future: [ [ 'f1' ] ],
			};
			const next = historyReducer( state, {
				type: 'REPLACE',
				fields: [ 'replaced' ],
			} );
			expect( next.present ).toEqual( [ 'replaced' ] );
			expect( next.past ).toEqual( [ [ 'p1' ] ] );
			expect( next.future ).toEqual( [ [ 'f1' ] ] );
		} );
	} );

	describe( 'unknown action', () => {
		it( 'returns state unchanged', () => {
			const state = initial();
			const next = historyReducer( state, { type: 'UNKNOWN' } );
			expect( next ).toBe( state );
		} );
	} );

	describe( 'undo/redo round-trip', () => {
		it( 'SET then UNDO then REDO restores original', () => {
			let state = initial();
			state = historyReducer( state, {
				type: 'SET',
				fields: [ 'b' ],
			} );
			state = historyReducer( state, { type: 'UNDO' } );
			expect( state.present ).toEqual( [ 'a' ] );
			state = historyReducer( state, { type: 'REDO' } );
			expect( state.present ).toEqual( [ 'b' ] );
		} );

		it( 'multiple SETs then multiple UNDOs walk back correctly', () => {
			let state = initial();
			state = historyReducer( state, {
				type: 'SET',
				fields: [ 'b' ],
			} );
			state = historyReducer( state, {
				type: 'SET',
				fields: [ 'c' ],
			} );
			state = historyReducer( state, {
				type: 'SET',
				fields: [ 'd' ],
			} );
			state = historyReducer( state, { type: 'UNDO' } );
			expect( state.present ).toEqual( [ 'c' ] );
			state = historyReducer( state, { type: 'UNDO' } );
			expect( state.present ).toEqual( [ 'b' ] );
			state = historyReducer( state, { type: 'UNDO' } );
			expect( state.present ).toEqual( [ 'a' ] );
			state = historyReducer( state, { type: 'UNDO' } );
			expect( state.present ).toEqual( [ 'a' ] );
		} );
	} );
} );
