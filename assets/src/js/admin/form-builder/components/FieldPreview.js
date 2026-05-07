import { __ } from '@wordpress/i18n';
import { LayoutGrid } from 'lucide-react';

export default function FieldPreview( { field } ) {
	const ph = field.placeholder || '';

	switch ( field.type ) {
		case 'text':
		case 'email':
		case 'phone':
		case 'date':
		case 'url':
		case 'number':
			return (
				<div className="shqf-builder-field-preview">
					<input
						type="text"
						placeholder={ ph || field.type }
						readOnly
						tabIndex={ -1 }
					/>
				</div>
			);

		case 'textarea':
			return (
				<div className="shqf-builder-field-preview">
					<textarea
						placeholder={
							ph || __( 'Enter text…', 'samplehq-request-form' )
						}
						readOnly
						tabIndex={ -1 }
					/>
				</div>
			);

		case 'select':
			return (
				<div className="shqf-builder-field-preview">
					<select tabIndex={ -1 }>
						<option>
							{ ph ||
								__(
									'Select an option…',
									'samplehq-request-form'
								) }
						</option>
					</select>
				</div>
			);

		case 'radio':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-choices">
						{ ( field.options || [] )
							.slice( 0, 3 )
							.map( ( opt, i ) => (
								<div key={ i } className="shqf-preview-choice">
									<span className="shqf-preview-choice-radio" />
									<span>{ opt.label }</span>
								</div>
							) ) }
					</div>
				</div>
			);

		case 'checkbox':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-choices">
						{ ( field.options || [] )
							.slice( 0, 3 )
							.map( ( opt, i ) => (
								<div key={ i } className="shqf-preview-choice">
									<span className="shqf-preview-choice-check" />
									<span>{ opt.label }</span>
								</div>
							) ) }
					</div>
				</div>
			);

		case 'name':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-half">
						<input
							type="text"
							placeholder={ __(
								'First Name',
								'samplehq-request-form'
							) }
							readOnly
							tabIndex={ -1 }
						/>
						<input
							type="text"
							placeholder={ __(
								'Last Name',
								'samplehq-request-form'
							) }
							readOnly
							tabIndex={ -1 }
						/>
					</div>
				</div>
			);

		case 'address':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-address">
						<input
							type="text"
							placeholder={ __(
								'Street Address',
								'samplehq-request-form'
							) }
							readOnly
							tabIndex={ -1 }
						/>
						<input
							type="text"
							placeholder={ __(
								'City',
								'samplehq-request-form'
							) }
							readOnly
							tabIndex={ -1 }
						/>
						<input
							type="text"
							placeholder={ __(
								'State',
								'samplehq-request-form'
							) }
							readOnly
							tabIndex={ -1 }
						/>
						<input
							type="text"
							placeholder={ __( 'ZIP', 'samplehq-request-form' ) }
							readOnly
							tabIndex={ -1 }
						/>
					</div>
				</div>
			);

		case 'file_upload':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-file">
						{ __(
							'Drag & drop or click to upload',
							'samplehq-request-form'
						) }
					</div>
				</div>
			);

		case 'html':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-html">
						{ field.content
							? field.content
									.replace( /<[^>]*>/g, '' )
									.substring( 0, 80 )
							: __( 'HTML content', 'samplehq-request-form' ) }
					</div>
				</div>
			);

		case 'consent':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-consent">
						<span className="shqf-preview-choice-check" />
						<span>
							{ ( field.consent_text || field.label || '' )
								.replace( /<[^>]*>/g, '' )
								.substring( 0, 80 ) }
						</span>
					</div>
				</div>
			);

		case 'hidden':
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-hidden">
						{ __( 'Hidden field', 'samplehq-request-form' ) }:{ ' ' }
						{ field.default_value || '(empty)' }
					</div>
				</div>
			);

		case 'sample_picker': {
			const pickerLayout = field.config?.layout || 'grid';
			const maxSel = field.config?.max_selections || 0;
			const isGrid = pickerLayout === 'grid';

			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-picker">
						<div className="shqf-preview-picker-header">
							<LayoutGrid size={ 16 } aria-hidden="true" />
							<span>
								{ isGrid
									? __( 'Card Grid', 'samplehq-request-form' )
									: __(
											'Checklist',
											'samplehq-request-form'
									  ) }
								{ maxSel > 0 && ` · ${ maxSel } max` }
							</span>
						</div>
						{ isGrid ? (
							<div className="shqf-preview-picker-grid">
								{ [ 1, 2, 3 ].map( ( n ) => (
									<div
										key={ n }
										className="shqf-preview-picker-card"
									>
										<div className="shqf-preview-picker-card-img" />
										<div className="shqf-preview-picker-card-body">
											<div className="shqf-preview-picker-card-title" />
											<div className="shqf-preview-picker-card-desc" />
										</div>
									</div>
								) ) }
							</div>
						) : (
							<div className="shqf-preview-picker-list">
								{ [ 1, 2, 3 ].map( ( n ) => (
									<div
										key={ n }
										className="shqf-preview-picker-row"
									>
										<div className="shqf-preview-picker-row-check" />
										<div className="shqf-preview-picker-row-thumb" />
										<div className="shqf-preview-picker-row-info">
											<div className="shqf-preview-picker-card-title" />
											<div className="shqf-preview-picker-card-desc" />
										</div>
									</div>
								) ) }
							</div>
						) }
					</div>
				</div>
			);
		}

		case 'row': {
			const cols = field.columns || [];
			return (
				<div className="shqf-builder-field-preview">
					<div className="shqf-preview-row">
						{ cols.map( ( col, ci ) => (
							<div key={ ci } className="shqf-preview-row-col">
								{ ( col.fields || [] ).length > 0 ? (
									( col.fields || [] ).map( ( f ) => (
										<span
											key={ f.id }
											className="shqf-preview-row-col-field"
										>
											{ f.label || f.type }
										</span>
									) )
								) : (
									<span className="shqf-preview-row-col-empty">
										{ __(
											'Drop fields here',
											'samplehq-request-form'
										) }
									</span>
								) }
							</div>
						) ) }
					</div>
				</div>
			);
		}

		default:
			return null;
	}
}
