/* global shqfConnectionPoll, XMLHttpRequest */
( function () {
	const connectBtn = document.getElementById( 'shqf-connect-btn' );
	if ( ! connectBtn ) {
		return;
	}

	const initialState = document.getElementById( 'shqf-connect-initial' );
	const waitingState = document.getElementById( 'shqf-connect-waiting' );
	const timeoutState = document.getElementById( 'shqf-connect-timeout' );
	const cancelBtn = document.getElementById( 'shqf-connect-cancel' );
	const retryBtn = document.getElementById( 'shqf-connect-retry' );

	let pollTimer = null;
	let pollCount = 0;
	const maxPolls = 100;

	function showState( state ) {
		initialState.style.display = state === 'initial' ? '' : 'none';
		waitingState.style.display = state === 'waiting' ? '' : 'none';
		timeoutState.style.display = state === 'timeout' ? '' : 'none';
	}

	function stopPolling() {
		if ( pollTimer ) {
			clearInterval( pollTimer );
			pollTimer = null;
		}
		pollCount = 0;
	}

	function checkConnection() {
		const xhr = new XMLHttpRequest();
		xhr.open( 'POST', shqfConnectionPoll.ajaxUrl, true );
		xhr.setRequestHeader(
			'Content-Type',
			'application/x-www-form-urlencoded'
		);
		xhr.onreadystatechange = function () {
			if ( xhr.readyState !== 4 ) {
				return;
			}
			if ( xhr.status === 200 ) {
				try {
					const response = JSON.parse( xhr.responseText );
					if ( response.success && response.data.connected ) {
						stopPolling();
						window.location.reload();
						return;
					}
				} catch {
					// Ignore parse errors, keep polling.
				}
			}

			pollCount++;
			if ( pollCount >= maxPolls ) {
				stopPolling();
				showState( 'timeout' );
			}
		};
		xhr.send(
			'action=shqf_check_connection&_ajax_nonce=' +
				encodeURIComponent( shqfConnectionPoll.nonce )
		);
	}

	function startPolling() {
		pollCount = 0;
		pollTimer = setInterval( checkConnection, 3000 );
	}

	function openAndPoll() {
		window.open( shqfConnectionPoll.connectUrl, '_blank', 'noopener' );
		showState( 'waiting' );
		startPolling();
	}

	connectBtn.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		openAndPoll();
	} );

	if ( cancelBtn ) {
		cancelBtn.addEventListener( 'click', function () {
			stopPolling();
			showState( 'initial' );
		} );
	}

	if ( retryBtn ) {
		retryBtn.addEventListener( 'click', function () {
			openAndPoll();
		} );
	}
} )();
