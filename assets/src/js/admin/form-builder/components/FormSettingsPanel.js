import { __ } from '@wordpress/i18n';
import {
	Panel,
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';

export default function FormSettingsPanel( {
	subtitle,
	layout,
	behavior,
	appearance,
	display,
	email,
	meta,
	onSubtitle,
	onLayout,
	onBehavior,
	onAppearance,
	onDisplay,
	onEmail,
} ) {
	const submitText =
		behavior.submit_button_text ||
		__( 'Submit Request', 'samplehq-request-form' );
	const successMessage =
		behavior.success_message ||
		__(
			'Thank you! Your sample request has been submitted.',
			'samplehq-request-form'
		);
	const successType = behavior.success_type || 'message';
	const redirectUrl = behavior.redirect_url || '';

	return (
		<div className="shqf-builder-settings">
			<div className="shqf-builder-settings-header">
				<span>{ __( 'Form Settings', 'samplehq-request-form' ) }</span>
			</div>
			<Panel>
				<PanelBody
					title={ __( 'General', 'samplehq-request-form' ) }
					initialOpen
				>
					<TextareaControl
						label={ __( 'Subtitle', 'samplehq-request-form' ) }
						value={ subtitle }
						onChange={ onSubtitle }
						rows={ 2 }
						help={ __(
							'Displayed below the form title.',
							'samplehq-request-form'
						) }
					/>
					<SelectControl
						label={ __( 'Layout', 'samplehq-request-form' ) }
						value={ layout || '' }
						options={ [
							{
								label: __( 'Default', 'samplehq-request-form' ),
								value: '',
							},
							{
								label: __(
									'Wizard (Multi-step)',
									'samplehq-request-form'
								),
								value: 'wizard',
							},
							{
								label: __( 'Grid', 'samplehq-request-form' ),
								value: 'grid',
							},
							{
								label: __( 'List', 'samplehq-request-form' ),
								value: 'list',
							},
						] }
						onChange={ onLayout }
					/>
					<TextControl
						label={ __(
							'Submit Button Text',
							'samplehq-request-form'
						) }
						value={ submitText }
						onChange={ ( val ) =>
							onBehavior( {
								...behavior,
								submit_button_text: val,
							} )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'After Submission', 'samplehq-request-form' ) }
					initialOpen
				>
					<SelectControl
						label={ __( 'On Success', 'samplehq-request-form' ) }
						value={ successType }
						options={ [
							{
								label: __(
									'Show success message',
									'samplehq-request-form'
								),
								value: 'message',
							},
							{
								label: __(
									'Redirect to URL',
									'samplehq-request-form'
								),
								value: 'redirect',
							},
						] }
						onChange={ ( val ) =>
							onBehavior( { ...behavior, success_type: val } )
						}
					/>
					{ successType === 'message' && (
						<TextareaControl
							label={ __(
								'Success Message',
								'samplehq-request-form'
							) }
							value={ successMessage }
							onChange={ ( val ) =>
								onBehavior( {
									...behavior,
									success_message: val,
								} )
							}
							rows={ 2 }
						/>
					) }
					{ successType === 'redirect' && (
						<TextControl
							label={ __(
								'Redirect URL',
								'samplehq-request-form'
							) }
							value={ redirectUrl }
							onChange={ ( val ) =>
								onBehavior( {
									...behavior,
									redirect_url: val,
								} )
							}
							type="url"
							help={ __(
								'Full URL including https://',
								'samplehq-request-form'
							) }
						/>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Field Display', 'samplehq-request-form' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __(
							'Label Position',
							'samplehq-request-form'
						) }
						value={ display.label_position || 'top' }
						options={ [
							{
								label: __(
									'Above input',
									'samplehq-request-form'
								),
								value: 'top',
							},
							{
								label: __(
									'Beside input (left)',
									'samplehq-request-form'
								),
								value: 'left',
							},
							{
								label: __(
									'Hidden (placeholder only)',
									'samplehq-request-form'
								),
								value: 'hidden',
							},
						] }
						onChange={ ( val ) =>
							onDisplay( { ...display, label_position: val } )
						}
					/>
					<SelectControl
						label={ __( 'Placeholder', 'samplehq-request-form' ) }
						value={ display.placeholder_mode || 'show' }
						options={ [
							{
								label: __(
									'Show label + placeholder',
									'samplehq-request-form'
								),
								value: 'show',
							},
							{
								label: __(
									'Label only (no placeholder)',
									'samplehq-request-form'
								),
								value: 'label_only',
							},
							{
								label: __(
									'Placeholder as label',
									'samplehq-request-form'
								),
								value: 'placeholder_as_label',
							},
						] }
						onChange={ ( val ) =>
							onDisplay( { ...display, placeholder_mode: val } )
						}
						help={ __(
							'Controls whether placeholders appear inside inputs.',
							'samplehq-request-form'
						) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Tracking', 'samplehq-request-form' ) }
					initialOpen={ false }
				>
					<ToggleControl
						label={ __(
							'Analytics Events',
							'samplehq-request-form'
						) }
						checked={ behavior.analytics_events || false }
						onChange={ ( val ) =>
							onBehavior( {
								...behavior,
								analytics_events: val,
							} )
						}
						help={ __(
							'Fire events for GTM, GA4, and custom scripts on form view, start, step change, and submission.',
							'samplehq-request-form'
						) }
					/>
				</PanelBody>

				<PanelBody
					title={ __(
						'Email Notifications',
						'samplehq-request-form'
					) }
					initialOpen={ false }
				>
					<TextControl
						label={ __(
							'Admin Notification Email',
							'samplehq-request-form'
						) }
						value={ email.notification_email || '' }
						onChange={ ( val ) =>
							onEmail( {
								...email,
								notification_email: val,
							} )
						}
						help={ __(
							'Override the global admin email for this form. Leave blank to use global setting.',
							'samplehq-request-form'
						) }
					/>
					<TextControl
						label={ __( 'From Name', 'samplehq-request-form' ) }
						value={ email.from_name || '' }
						onChange={ ( val ) =>
							onEmail( { ...email, from_name: val } )
						}
						help={ __(
							'Override the sender name for this form. Leave blank to use global setting.',
							'samplehq-request-form'
						) }
					/>
					<ToggleControl
						label={ __(
							'Send Confirmation Email',
							'samplehq-request-form'
						) }
						checked={ email.send_confirmation !== false }
						onChange={ ( val ) =>
							onEmail( { ...email, send_confirmation: val } )
						}
						help={ __(
							'Send a receipt email to the person who submitted the form.',
							'samplehq-request-form'
						) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Appearance', 'samplehq-request-form' ) }
					initialOpen={ false }
				>
					<div className="shqf-settings-color-row">
						<label htmlFor="shqf-primary-color">
							{ __( 'Primary Color', 'samplehq-request-form' ) }
						</label>
						<input
							id="shqf-primary-color"
							type="color"
							value={ appearance.primary_color || '#0F766E' }
							onChange={ ( e ) =>
								onAppearance( {
									...appearance,
									primary_color: e.target.value,
								} )
							}
						/>
						<code>{ appearance.primary_color || '#0F766E' }</code>
					</div>
					<div className="shqf-settings-color-row">
						<label htmlFor="shqf-button-color">
							{ __( 'Button Color', 'samplehq-request-form' ) }
						</label>
						<input
							id="shqf-button-color"
							type="color"
							value={
								appearance.button_color ||
								appearance.primary_color ||
								'#0F766E'
							}
							onChange={ ( e ) =>
								onAppearance( {
									...appearance,
									button_color: e.target.value,
								} )
							}
						/>
						<code>
							{ appearance.button_color ||
								appearance.primary_color ||
								'#0F766E' }
						</code>
					</div>
					<TextControl
						label={ __(
							'Border Radius (px)',
							'samplehq-request-form'
						) }
						type="number"
						value={ String( appearance.border_radius ?? 8 ) }
						onChange={ ( val ) =>
							onAppearance( {
								...appearance,
								border_radius: parseInt( val, 10 ) || 0,
							} )
						}
					/>
				</PanelBody>

				{ meta && (
					<PanelBody
						title={ __( 'Info', 'samplehq-request-form' ) }
						initialOpen={ false }
					>
						<div className="shqf-settings-info">
							{ meta.slug && (
								<div className="shqf-settings-info-row">
									<span>
										{ __(
											'Slug',
											'samplehq-request-form'
										) }
									</span>
									<code>{ meta.slug }</code>
								</div>
							) }
							{ meta.shortcode && (
								<div className="shqf-settings-info-row">
									<span>
										{ __(
											'Shortcode',
											'samplehq-request-form'
										) }
									</span>
									<code>{ meta.shortcode }</code>
								</div>
							) }
							<div className="shqf-settings-info-row">
								<span>
									{ __(
										'Submissions',
										'samplehq-request-form'
									) }
								</span>
								<strong>{ meta.submissions_count ?? 0 }</strong>
							</div>
						</div>
					</PanelBody>
				) }
			</Panel>
		</div>
	);
}
