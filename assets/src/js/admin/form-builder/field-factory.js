import { __ } from '@wordpress/i18n';
import { ALL_FIELD_TYPES } from './constants';

export function generateFieldId() {
	return (
		'f_' + Math.random().toString( 36 ).substring( 2, 9 ).padEnd( 7, '0' )
	);
}

export function createField( type ) {
	if ( type === 'row' ) {
		return {
			id:
				'row_' +
				Math.random().toString( 36 ).substring( 2, 9 ).padEnd( 7, '0' ),
			type: 'row',
			enabled: true,
			columns: [
				{ width: '1fr', fields: [] },
				{ width: '1fr', fields: [] },
			],
		};
	}

	const def = ALL_FIELD_TYPES.find( ( t ) => t.type === type );

	const requiredByDefault = [ 'email', 'name', 'sample_picker' ];

	const base = {
		id: generateFieldId(),
		type,
		key: type + '_' + Date.now(),
		label: def?.label || type,
		placeholder: '',
		required: requiredByDefault.includes( type ),
		enabled: true,
	};

	if ( type === 'select' || type === 'radio' || type === 'checkbox' ) {
		base.options = [
			{
				label: __( 'Option 1', 'samplehq-request-form' ),
				value: 'option_1',
			},
			{
				label: __( 'Option 2', 'samplehq-request-form' ),
				value: 'option_2',
			},
			{
				label: __( 'Option 3', 'samplehq-request-form' ),
				value: 'option_3',
			},
		];
	}

	if ( type === 'consent' ) {
		base.consent_text = __(
			'I agree to the privacy policy.',
			'samplehq-request-form'
		);
		base.required = true;
	}

	if ( type === 'html' ) {
		base.content =
			'<p>' +
			__( 'Enter your content here.', 'samplehq-request-form' ) +
			'</p>';
	}

	if ( type === 'hidden' ) {
		base.default_value = '';
	}

	return base;
}
