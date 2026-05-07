const React = require( 'react' );

const WP_PROPS = [
	'isBusy',
	'isDestructive',
	'isPrimary',
	'isSecondary',
	'isTertiary',
	'__nextHasNoMarginBottom',
	'__next40pxDefaultSize',
];

const stripWpProps = ( props ) => {
	const clean = { ...props };
	WP_PROPS.forEach( ( k ) => delete clean[ k ] );
	return clean;
};

const makeComponent = ( name ) => {
	const Component = ( { children, ...props } ) =>
		React.createElement(
			'div',
			{ 'data-testid': name, ...stripWpProps( props ) },
			children
		);
	Component.displayName = name;
	return Component;
};

module.exports = {
	Button: ( { children, variant, size, ...props } ) =>
		React.createElement(
			'button',
			{ 'data-testid': 'Button', ...stripWpProps( props ) },
			children
		),
	Panel: makeComponent( 'Panel' ),
	PanelBody: ( { title, children } ) =>
		React.createElement(
			'div',
			{ 'data-testid': 'PanelBody', 'data-title': title },
			children
		),
	TextControl: ( {
		label,
		value,
		onChange,
		help,
		__nextHasNoMarginBottom,
		...rest
	} ) =>
		React.createElement( 'input', {
			'data-testid': 'TextControl',
			'aria-label': label,
			value: value || '',
			onChange: ( e ) => onChange && onChange( e.target.value ),
			...rest,
		} ),
	TextareaControl: ( { label, value, onChange } ) =>
		React.createElement( 'textarea', {
			'data-testid': 'TextareaControl',
			'aria-label': label,
			value: value || '',
			onChange: ( e ) => onChange && onChange( e.target.value ),
		} ),
	ToggleControl: ( { label, checked, onChange } ) =>
		React.createElement( 'input', {
			'data-testid': 'ToggleControl',
			type: 'checkbox',
			'aria-label': label,
			checked: checked || false,
			onChange: () => onChange && onChange( ! checked ),
		} ),
	SelectControl: ( {
		label,
		value,
		options,
		onChange,
		help,
		__nextHasNoMarginBottom,
		...rest
	} ) =>
		React.createElement(
			'select',
			{
				'data-testid': 'SelectControl',
				'aria-label': label,
				value: value || '',
				onChange: ( e ) => onChange && onChange( e.target.value ),
				...rest,
			},
			( options || [] ).map( ( opt ) =>
				React.createElement(
					'option',
					{ key: opt.value, value: opt.value },
					opt.label
				)
			)
		),
	Spinner: () => React.createElement( 'div', { 'data-testid': 'Spinner' } ),
	Placeholder: makeComponent( 'Placeholder' ),
};
