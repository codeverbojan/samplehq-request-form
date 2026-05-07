const React = require( 'react' );

const cache = {};

const handler = {
	get( _, name ) {
		if ( name === '__esModule' ) {
			return true;
		}
		if ( ! cache[ name ] ) {
			const Icon = ( props ) =>
				React.createElement( 'svg', {
					'data-testid': `icon-${ name }`,
					...props,
				} );
			Icon.displayName = name;
			cache[ name ] = Icon;
		}
		return cache[ name ];
	},
};

module.exports = new Proxy( {}, handler );
