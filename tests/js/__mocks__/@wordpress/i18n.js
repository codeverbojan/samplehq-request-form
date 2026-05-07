module.exports = {
	__: ( str ) => str,
	_x: ( str ) => str,
	_n: ( single, plural, number ) => ( number === 1 ? single : plural ),
	sprintf: ( fmt, ...args ) => {
		let i = 0;
		return fmt.replace( /%[sd]/g, () => args[ i++ ] );
	},
};
