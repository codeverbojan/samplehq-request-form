const React = require( 'react' );

module.exports = {
	...React,
	render: jest.fn(),
	useState: React.useState,
	useEffect: React.useEffect,
	useCallback: React.useCallback,
	useRef: React.useRef,
	useReducer: React.useReducer,
	useMemo: React.useMemo,
};
