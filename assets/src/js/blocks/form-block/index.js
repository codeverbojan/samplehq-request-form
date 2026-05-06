/**
 * SampleHQ Form Block - Registration.
 *
 * @package
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null, // Dynamic block -- server-side render.
} );
