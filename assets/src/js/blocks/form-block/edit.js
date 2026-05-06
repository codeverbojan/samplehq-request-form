/**
 * SampleHQ Form Block - Editor component.
 *
 * Displays a form selector dropdown in InspectorControls and
 * a ServerSideRender live preview in the editor canvas.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	Placeholder,
	Spinner,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Edit component for the SampleHQ Form block.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update attributes.
 * @return {import('@wordpress/element').WPElement} Block edit UI.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { formId } = attributes;
	const blockProps = useBlockProps();

	const [ forms, setForms ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );

	useEffect( () => {
		apiFetch( { path: '/samplehq-form/v1/forms?per_page=100' } )
			.then( ( data ) => {
				// The API returns an array of form objects.
				const list = Array.isArray( data ) ? data : [];
				setForms( list );
				setIsLoading( false );
			} )
			.catch( () => {
				setForms( [] );
				setIsLoading( false );
			} );
	}, [] );

	// Build options for the select dropdown.
	const formOptions = [
		{
			label: __( 'Select a form', 'samplehq-request-form' ),
			value: 0,
		},
		...forms.map( ( form ) => ( {
			label: form.title || `Form #${ form.id }`,
			value: form.id,
		} ) ),
	];

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Form Settings', 'samplehq-request-form' ) }
				>
					{ isLoading ? (
						<Spinner />
					) : (
						<SelectControl
							label={ __(
								'Select Form',
								'samplehq-request-form'
							) }
							value={ formId }
							options={ formOptions }
							onChange={ ( value ) =>
								setAttributes( {
									formId: parseInt( value, 10 ),
								} )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>

			{ formId > 0 ? (
				<ServerSideRender
					block="samplehq-form/form"
					attributes={ attributes }
					LoadingResponsePlaceholder={ () => (
						<div className="shqf-form-wrapper">
							<div
								className="shqf-skeleton shqf-skeleton-text"
								style={ { width: '40%', height: '24px' } }
							/>
							<div className="shqf-skeleton shqf-skeleton-field" />
							<div className="shqf-skeleton shqf-skeleton-field" />
							<div className="shqf-skeleton shqf-skeleton-button" />
						</div>
					) }
				/>
			) : (
				<Placeholder
					icon="clipboard"
					label={ __( 'SampleHQ Form', 'samplehq-request-form' ) }
					instructions={ __(
						'Select a form from the block settings panel.',
						'samplehq-request-form'
					) }
				/>
			) }
		</div>
	);
}
