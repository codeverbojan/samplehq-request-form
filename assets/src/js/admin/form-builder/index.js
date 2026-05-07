/**
 * SampleHQ Form Builder - Admin React app.
 *
 * @package
 */

import { render } from '@wordpress/element';
import FormBuilder from './components/FormBuilder';

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'shqf-form-builder-root' );
	if ( ! root ) {
		return;
	}

	const formId = parseInt( root.dataset.formId || '0', 10 );
	const formTitle = root.dataset.formTitle || '';
	const config = root.dataset.config
		? JSON.parse( root.dataset.config )
		: null;
	const meta = root.dataset.formMeta
		? JSON.parse( root.dataset.formMeta )
		: null;

	render(
		<FormBuilder
			formId={ formId }
			formTitle={ formTitle }
			config={ config }
			meta={ meta }
		/>,
		root
	);
} );
