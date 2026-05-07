import { ALL_FIELD_TYPES } from './constants';

export function slugify( str ) {
	return str
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '_' )
		.replace( /^_|_$/g, '' )
		.substring( 0, 50 );
}

export function getFieldLabel( type ) {
	const def = ALL_FIELD_TYPES.find( ( t ) => t.type === type );
	return def
		? def.label
		: type
				.replace( /_/g, ' ' )
				.replace( /\b\w/g, ( c ) => c.toUpperCase() );
}
