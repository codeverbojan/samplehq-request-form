import { render, screen, fireEvent } from '@testing-library/react';
import FormSettingsPanel from './FormSettingsPanel';

function renderPanel( overrides = {} ) {
	const props = {
		subtitle: '',
		layout: '',
		behavior: {},
		appearance: {},
		display: {},
		email: {},
		meta: null,
		onSubtitle: jest.fn(),
		onLayout: jest.fn(),
		onBehavior: jest.fn(),
		onAppearance: jest.fn(),
		onDisplay: jest.fn(),
		onEmail: jest.fn(),
		...overrides,
	};
	return render( <FormSettingsPanel { ...props } /> );
}

describe( 'FormSettingsPanel', () => {
	it( 'renders the Form Settings header', () => {
		renderPanel();
		expect( screen.getByText( 'Form Settings' ) ).toBeTruthy();
	} );

	it( 'renders General panel with subtitle, layout, and submit text', () => {
		renderPanel();
		expect( screen.getByLabelText( 'Subtitle' ) ).toBeTruthy();
		expect( screen.getByLabelText( 'Layout' ) ).toBeTruthy();
		expect( screen.getByLabelText( 'Submit Button Text' ) ).toBeTruthy();
	} );

	it( 'defaults submit button text to Submit Request', () => {
		renderPanel();
		const input = screen.getByLabelText( 'Submit Button Text' );
		expect( input.value ).toBe( 'Submit Request' );
	} );

	it( 'calls onBehavior when submit text changes', () => {
		const onBehavior = jest.fn();
		renderPanel( { onBehavior } );
		fireEvent.change( screen.getByLabelText( 'Submit Button Text' ), {
			target: { value: 'Send' },
		} );
		expect( onBehavior ).toHaveBeenCalled();
		expect( onBehavior.mock.calls[ 0 ][ 0 ].submit_button_text ).toBe(
			'Send'
		);
	} );

	it( 'calls onLayout when layout changes', () => {
		const onLayout = jest.fn();
		renderPanel( { onLayout } );
		fireEvent.change( screen.getByLabelText( 'Layout' ), {
			target: { value: 'wizard' },
		} );
		expect( onLayout ).toHaveBeenCalledWith( 'wizard' );
	} );

	it( 'shows success message textarea when success_type is message', () => {
		renderPanel( { behavior: { success_type: 'message' } } );
		expect( screen.getByLabelText( 'Success Message' ) ).toBeTruthy();
	} );

	it( 'shows redirect URL input when success_type is redirect', () => {
		renderPanel( { behavior: { success_type: 'redirect' } } );
		expect( screen.getByLabelText( 'Redirect URL' ) ).toBeTruthy();
	} );

	it( 'does not show redirect URL when success_type is message', () => {
		renderPanel( { behavior: { success_type: 'message' } } );
		expect( screen.queryByLabelText( 'Redirect URL' ) ).toBeNull();
	} );

	it( 'renders Field Display panel with label position and placeholder', () => {
		renderPanel();
		expect( screen.getByLabelText( 'Label Position' ) ).toBeTruthy();
		expect( screen.getByLabelText( 'Placeholder' ) ).toBeTruthy();
	} );

	it( 'calls onDisplay when label position changes', () => {
		const onDisplay = jest.fn();
		renderPanel( { onDisplay, display: {} } );
		fireEvent.change( screen.getByLabelText( 'Label Position' ), {
			target: { value: 'left' },
		} );
		expect( onDisplay ).toHaveBeenCalled();
		expect( onDisplay.mock.calls[ 0 ][ 0 ].label_position ).toBe( 'left' );
	} );

	it( 'renders Tracking panel with analytics toggle', () => {
		renderPanel();
		expect( screen.getByLabelText( 'Analytics Events' ) ).toBeTruthy();
	} );

	it( 'calls onBehavior when analytics toggle changes', () => {
		const onBehavior = jest.fn();
		renderPanel( { onBehavior, behavior: {} } );
		fireEvent.click( screen.getByLabelText( 'Analytics Events' ) );
		expect( onBehavior ).toHaveBeenCalled();
		expect( onBehavior.mock.calls[ 0 ][ 0 ].analytics_events ).toBe( true );
	} );

	it( 'renders Email Notifications panel', () => {
		renderPanel();
		expect(
			screen.getByLabelText( 'Admin Notification Email' )
		).toBeTruthy();
		expect( screen.getByLabelText( 'From Name' ) ).toBeTruthy();
		expect(
			screen.getByLabelText( 'Send Confirmation Email' )
		).toBeTruthy();
	} );

	it( 'calls onEmail when notification email changes', () => {
		const onEmail = jest.fn();
		renderPanel( { onEmail, email: {} } );
		fireEvent.change( screen.getByLabelText( 'Admin Notification Email' ), {
			target: { value: 'test@example.com' },
		} );
		expect( onEmail ).toHaveBeenCalled();
		expect( onEmail.mock.calls[ 0 ][ 0 ].notification_email ).toBe(
			'test@example.com'
		);
	} );

	it( 'renders Appearance panel with color pickers and border radius', () => {
		const { container } = renderPanel();
		expect( container.querySelector( '#shqf-primary-color' ) ).toBeTruthy();
		expect( container.querySelector( '#shqf-button-color' ) ).toBeTruthy();
		expect( screen.getByLabelText( 'Border Radius (px)' ) ).toBeTruthy();
	} );

	it( 'calls onAppearance when primary color changes', () => {
		const onAppearance = jest.fn();
		const { container } = renderPanel( { onAppearance } );
		fireEvent.change( container.querySelector( '#shqf-primary-color' ), {
			target: { value: '#FF0000' },
		} );
		expect( onAppearance ).toHaveBeenCalled();
		expect( onAppearance.mock.calls[ 0 ][ 0 ].primary_color ).toBe(
			'#ff0000'
		);
	} );

	it( 'does not render Info panel when meta is null', () => {
		renderPanel( { meta: null } );
		expect( screen.queryByText( 'Slug' ) ).toBeNull();
	} );

	it( 'renders Info panel with slug and shortcode when meta provided', () => {
		const meta = {
			slug: 'my-form',
			shortcode: '[samplehq_form id="1"]',
			submissions_count: 42,
		};
		renderPanel( { meta } );
		expect( screen.getByText( 'my-form' ) ).toBeTruthy();
		expect( screen.getByText( '[samplehq_form id="1"]' ) ).toBeTruthy();
		expect( screen.getByText( '42' ) ).toBeTruthy();
	} );

	it( 'shows 0 submissions when count not set', () => {
		const meta = { slug: 'test' };
		renderPanel( { meta } );
		expect( screen.getByText( '0' ) ).toBeTruthy();
	} );

	it( 'defaults success_type to message', () => {
		renderPanel( { behavior: {} } );
		expect( screen.getByLabelText( 'Success Message' ) ).toBeTruthy();
		expect( screen.queryByLabelText( 'Redirect URL' ) ).toBeNull();
	} );

	it( 'calls onSubtitle when subtitle changes', () => {
		const onSubtitle = jest.fn();
		renderPanel( { onSubtitle } );
		fireEvent.change( screen.getByLabelText( 'Subtitle' ), {
			target: { value: 'New subtitle' },
		} );
		expect( onSubtitle ).toHaveBeenCalledWith( 'New subtitle' );
	} );

	it( 'calls onBehavior when redirect URL changes', () => {
		const onBehavior = jest.fn();
		renderPanel( {
			onBehavior,
			behavior: { success_type: 'redirect' },
		} );
		fireEvent.change( screen.getByLabelText( 'Redirect URL' ), {
			target: { value: 'https://example.com' },
		} );
		expect( onBehavior ).toHaveBeenCalled();
		expect( onBehavior.mock.calls[ 0 ][ 0 ].redirect_url ).toBe(
			'https://example.com'
		);
		expect( onBehavior.mock.calls[ 0 ][ 0 ].success_type ).toBe(
			'redirect'
		);
	} );

	it( 'calls onBehavior when success message changes', () => {
		const onBehavior = jest.fn();
		renderPanel( {
			onBehavior,
			behavior: { success_type: 'message' },
		} );
		fireEvent.change( screen.getByLabelText( 'Success Message' ), {
			target: { value: 'Thanks!' },
		} );
		expect( onBehavior ).toHaveBeenCalled();
		expect( onBehavior.mock.calls[ 0 ][ 0 ].success_message ).toBe(
			'Thanks!'
		);
	} );

	it( 'defaults primary and button colors to #0F766E', () => {
		const { container } = renderPanel( { appearance: {} } );
		expect( container.querySelector( '#shqf-primary-color' ).value ).toBe(
			'#0f766e'
		);
		expect( container.querySelector( '#shqf-button-color' ).value ).toBe(
			'#0f766e'
		);
	} );

	it( 'button color inherits primary color when not set', () => {
		const { container } = renderPanel( {
			appearance: { primary_color: '#FF5500' },
		} );
		expect( container.querySelector( '#shqf-button-color' ).value ).toBe(
			'#ff5500'
		);
	} );

	it( 'shows explicit button color when both button and primary are set', () => {
		const { container } = renderPanel( {
			appearance: {
				primary_color: '#FF5500',
				button_color: '#0000FF',
			},
		} );
		expect( container.querySelector( '#shqf-button-color' ).value ).toBe(
			'#0000ff'
		);
		expect( container.querySelector( '#shqf-primary-color' ).value ).toBe(
			'#ff5500'
		);
	} );

	it( 'calls onAppearance when button color changes', () => {
		const onAppearance = jest.fn();
		const { container } = renderPanel( { onAppearance } );
		fireEvent.change( container.querySelector( '#shqf-button-color' ), {
			target: { value: '#00FF00' },
		} );
		expect( onAppearance ).toHaveBeenCalled();
		expect( onAppearance.mock.calls[ 0 ][ 0 ].button_color ).toBe(
			'#00ff00'
		);
	} );

	it( 'calls onAppearance when border radius changes', () => {
		const onAppearance = jest.fn();
		renderPanel( { onAppearance } );
		fireEvent.change( screen.getByLabelText( 'Border Radius (px)' ), {
			target: { value: '12' },
		} );
		expect( onAppearance ).toHaveBeenCalled();
		expect( onAppearance.mock.calls[ 0 ][ 0 ].border_radius ).toBe( 12 );
	} );

	it( 'defaults border radius to 8', () => {
		renderPanel( { appearance: {} } );
		expect( screen.getByLabelText( 'Border Radius (px)' ).value ).toBe(
			'8'
		);
	} );

	it( 'calls onDisplay when placeholder mode changes', () => {
		const onDisplay = jest.fn();
		renderPanel( { onDisplay, display: {} } );
		fireEvent.change( screen.getByLabelText( 'Placeholder' ), {
			target: { value: 'label_only' },
		} );
		expect( onDisplay ).toHaveBeenCalled();
		expect( onDisplay.mock.calls[ 0 ][ 0 ].placeholder_mode ).toBe(
			'label_only'
		);
	} );

	it( 'calls onEmail when from name changes', () => {
		const onEmail = jest.fn();
		renderPanel( { onEmail, email: {} } );
		fireEvent.change( screen.getByLabelText( 'From Name' ), {
			target: { value: 'SampleHQ' },
		} );
		expect( onEmail ).toHaveBeenCalled();
		expect( onEmail.mock.calls[ 0 ][ 0 ].from_name ).toBe( 'SampleHQ' );
	} );

	it( 'calls onEmail when send confirmation toggles off', () => {
		const onEmail = jest.fn();
		renderPanel( { onEmail, email: {} } );
		fireEvent.click( screen.getByLabelText( 'Send Confirmation Email' ) );
		expect( onEmail ).toHaveBeenCalled();
		expect( onEmail.mock.calls[ 0 ][ 0 ].send_confirmation ).toBe( false );
	} );

	it( 'calls onEmail when send confirmation toggles on from false', () => {
		const onEmail = jest.fn();
		renderPanel( {
			onEmail,
			email: { send_confirmation: false },
		} );
		fireEvent.click( screen.getByLabelText( 'Send Confirmation Email' ) );
		expect( onEmail ).toHaveBeenCalled();
		expect( onEmail.mock.calls[ 0 ][ 0 ].send_confirmation ).toBe( true );
	} );

	it( 'hides slug and shortcode rows when meta lacks them', () => {
		renderPanel( { meta: { submissions_count: 5 } } );
		expect( screen.queryByText( 'Slug' ) ).toBeNull();
		expect( screen.queryByText( 'Shortcode' ) ).toBeNull();
		expect( screen.getByText( '5' ) ).toBeTruthy();
	} );
} );
