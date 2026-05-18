/**
 * Webpack configuration extending @wordpress/scripts defaults.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const { BannerPlugin } = require( 'webpack' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const CopyPlugin = require( 'copy-webpack-plugin' );

module.exports = {
	...defaultConfig,
	entry: {
		'form-builder': path.resolve(
			__dirname,
			'assets/src/js/admin/form-builder/index.js'
		),
		'form-frontend': path.resolve(
			__dirname,
			'assets/src/js/public/form-frontend.js'
		),
		'woo-product-page': path.resolve(
			__dirname,
			'assets/src/js/public/woo-product-page.js'
		),
		'sample-edit-page': path.resolve(
			__dirname,
			'assets/src/js/admin/sample-edit-page.js'
		),
		'admin-utils': path.resolve(
			__dirname,
			'assets/src/js/admin/admin-utils.js'
		),
		'connection-poll': path.resolve(
			__dirname,
			'assets/src/js/admin/connection-poll.js'
		),
		'migration-wizard': path.resolve(
			__dirname,
			'assets/src/js/admin/migration-wizard.js'
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
	optimization: {
		...defaultConfig.optimization,
		minimizer: [
			new TerserPlugin( {
				parallel: true,
				terserOptions: {
					output: {
						comments: /translators:|^!/i,
					},
					compress: {
						passes: 2,
					},
					mangle: {
						reserved: [ '__', '_n', '_nx', '_x' ],
					},
				},
				extractComments: false,
			} ),
		],
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
		new BannerPlugin( {
			banner: '/*! SampleHQ Request Form | Source: https://github.com/codeverbojan/samplehq-request-form | License: GPLv2+ */',
			raw: true,
		} ),
	],
};
