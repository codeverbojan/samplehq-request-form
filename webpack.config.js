/**
 * Webpack configuration extending @wordpress/scripts defaults.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const CopyPlugin = require( 'copy-webpack-plugin' );

module.exports = {
	...defaultConfig,
	entry: {
		'form-builder': path.resolve(
			__dirname,
			'assets/src/js/admin/form-builder.js'
		),
		'form-frontend': path.resolve(
			__dirname,
			'assets/src/js/public/form-frontend.js'
		),
		'woo-product-page': path.resolve(
			__dirname,
			'assets/src/js/public/woo-product-page.js'
		),
		'blocks/form-block/index': path.resolve(
			__dirname,
			'assets/src/js/blocks/form-block/index.js'
		),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
	},
	plugins: [
		...( defaultConfig.plugins || [] ),
		new CopyPlugin( {
			patterns: [
				{
					from: path.resolve(
						__dirname,
						'assets/src/js/blocks/form-block/block.json'
					),
					to: path.resolve(
						__dirname,
						'assets/build/blocks/form-block/block.json'
					),
				},
				{
					from: path.resolve(
						__dirname,
						'assets/src/css/admin/admin.css'
					),
					to: path.resolve(
						__dirname,
						'assets/build/css/admin/admin.css'
					),
				},
			],
		} ),
	],
};
