const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );
const presetConfig = require( '@wordpress/jest-preset-default/jest-preset' );

module.exports = {
	...defaultConfig,
	moduleNameMapper: {
		...( presetConfig.moduleNameMapper || {} ),
		'^@wordpress/i18n$': '<rootDir>/tests/js/__mocks__/@wordpress/i18n.js',
		'^@wordpress/element$':
			'<rootDir>/tests/js/__mocks__/@wordpress/element.js',
		'^@wordpress/api-fetch$':
			'<rootDir>/tests/js/__mocks__/@wordpress/api-fetch.js',
		'^lucide-react$': '<rootDir>/tests/js/__mocks__/lucide-react.js',
		'^@dnd-kit/sortable$':
			'<rootDir>/tests/js/__mocks__/@dnd-kit/sortable.js',
		'^@dnd-kit/core$': '<rootDir>/tests/js/__mocks__/@dnd-kit/core.js',
		'^@dnd-kit/utilities$':
			'<rootDir>/tests/js/__mocks__/@dnd-kit/utilities.js',
		'^@wordpress/components$':
			'<rootDir>/tests/js/__mocks__/@wordpress/components.js',
	},
	testPathIgnorePatterns: [
		'/node_modules/',
		'<rootDir>/vendor/',
		'<rootDir>/tests/e2e/',
	],
};
