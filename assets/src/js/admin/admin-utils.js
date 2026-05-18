( function () {
	document.addEventListener( 'click', function ( e ) {
		const el = e.target.closest( '[data-shqf-confirm]' );
		if (
			el &&
			// eslint-disable-next-line no-alert -- confirm dialog replaces inline onclick handlers.
			! window.confirm( el.getAttribute( 'data-shqf-confirm' ) )
		) {
			e.preventDefault();
		}
	} );

	document.addEventListener( 'change', function ( e ) {
		const baseUrl = e.target.getAttribute( 'data-shqf-filter-url' );
		if ( ! baseUrl ) {
			return;
		}
		if ( e.target.value ) {
			window.location.href =
				baseUrl + '&dashboard_form=' + e.target.value;
		} else {
			window.location.href = baseUrl;
		}
	} );
} )();
