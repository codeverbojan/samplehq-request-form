const wordpress = require( '@wordpress/eslint-plugin' );

module.exports = [
	...wordpress.configs.recommended,
	{
		languageOptions: {
			globals: {
				CSS: 'readonly',
			},
		},
		settings: {
			'import/core-modules': [
				'@wordpress/blocks',
				'@wordpress/block-editor',
				'@wordpress/components',
				'@wordpress/element',
				'@wordpress/i18n',
				'@wordpress/api-fetch',
				'@wordpress/server-side-render',
			],
		},
		rules: {
			'import/no-extraneous-dependencies': [
				'error',
				{
					peerDependencies: true,
					devDependencies: [
						'**/*.config.js',
						'**/test/**',
						'**/tests/**',
					],
					packageDir: '.',
				},
			],
		},
	},
];
