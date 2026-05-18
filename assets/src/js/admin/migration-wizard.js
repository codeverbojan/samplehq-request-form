/* global shqfMigration */
( function () {
	const wizard = document.getElementById( 'shqf-migration-wizard' );
	if ( ! wizard ) {
		return;
	}

	const restUrl = wizard.getAttribute( 'data-rest-url' );
	const nonce = wizard.getAttribute( 'data-nonce' );
	let pollTimer = null;
	let pollErrors = 0;

	function api( method, endpoint, body ) {
		const opts = {
			method,
			headers: {
				'X-WP-Nonce': nonce,
				'Content-Type': 'application/json',
			},
		};
		if ( body ) {
			opts.body = JSON.stringify( body );
		}
		return fetch( restUrl + endpoint, opts ).then( function ( r ) {
			if ( ! r.ok ) {
				return r
					.json()
					.catch( function () {
						return {};
					} )
					.then( function ( d ) {
						const err = new Error( 'HTTP ' + r.status );
						err.data = d;
						throw err;
					} );
			}
			return r.json();
		} );
	}

	function showStep( id ) {
		[ 'shqf-mig-preview', 'shqf-mig-running', 'shqf-mig-complete' ].forEach(
			function ( s ) {
				document.getElementById( s ).style.display =
					s === id ? '' : 'none';
			}
		);
	}

	function esc( s ) {
		const d = document.createElement( 'div' );
		d.textContent = s;
		return d.innerHTML;
	}

	function loadPreview() {
		api( 'GET', 'preview' )
			.then( function ( data ) {
				const el = document.getElementById( 'shqf-mig-preview' );
				if ( data.error ) {
					el.innerHTML =
						'<div class="notice notice-error inline"><p>' +
						esc( data.error ) +
						'</p></div>';
					return;
				}

				const platform = data.platform || {};
				const maxSamples = platform.max_samples || 0;
				const currentSamples = platform.current_samples || 0;
				const available =
					maxSamples > 0
						? Math.max( 0, maxSamples - currentSamples )
						: data.samples;
				const limited = maxSamples > 0 && data.samples > available;

				let html = '<p>' + shqfMigration.migrateDescription + '</p>';
				html +=
					'<table class="widefat striped" style="max-width:400px;">';
				html +=
					'<tr><td>' +
					shqfMigration.categories +
					'</td><td><strong>' +
					data.categories +
					'</strong></td></tr>';
				html +=
					'<tr><td>' +
					shqfMigration.samples +
					'</td><td><strong>' +
					data.samples +
					'</strong>';
				if ( limited ) {
					html +=
						' <em>(' +
						shqfMigration.planLimit +
						' ' +
						available +
						' ' +
						shqfMigration.available +
						')</em>';
				}
				html += '</td></tr>';
				html +=
					'<tr><td>' +
					shqfMigration.submissions +
					'</td><td><strong>' +
					data.submissions +
					'</strong></td></tr>';
				html += '</table>';

				if ( data.submissions > 0 ) {
					html +=
						'<p style="margin-top:12px;"><label><input type="checkbox" id="shqf-mig-include-subs"> ' +
						shqfMigration.alsoMigrateSubs +
						'</label></p>';
				}

				html +=
					'<p style="margin-top:16px;"><button type="button" id="shqf-mig-start" class="button button-primary">' +
					shqfMigration.startMigration +
					'</button></p>';

				if (
					data.categories === 0 &&
					data.samples === 0 &&
					data.submissions === 0
				) {
					html =
						'<div class="notice notice-info inline"><p>' +
						shqfMigration.nothingToMigrate +
						'</p></div>';
				}

				el.innerHTML = html;

				const startBtn = document.getElementById( 'shqf-mig-start' );
				if ( startBtn ) {
					startBtn.addEventListener( 'click', function () {
						startBtn.disabled = true;
						const cb = document.getElementById(
							'shqf-mig-include-subs'
						);
						startMigration( cb ? cb.checked : false );
					} );
				}
			} )
			.catch( function () {
				document.getElementById( 'shqf-mig-preview' ).innerHTML =
					'<div class="notice notice-error inline"><p>' +
					shqfMigration.failedPreview +
					'</p></div>';
			} );
	}

	function startMigration( includeSubs ) {
		showStep( 'shqf-mig-running' );
		api( 'POST', 'start', { include_submissions: includeSubs } )
			.then( function () {
				startPolling();
			} )
			.catch( function ( err ) {
				const d = err.data || {};
				if ( d.code === 'migration_in_progress' ) {
					document.getElementById( 'shqf-mig-phase' ).textContent =
						shqfMigration.alreadyRunning;
					startPolling();
				} else {
					showStep( 'shqf-mig-preview' );
					loadPreview();
				}
			} );
	}

	function startPolling() {
		if ( pollTimer ) {
			return;
		}
		pollErrors = 0;
		pollTimer = setInterval( function () {
			api( 'GET', 'progress' )
				.then( function ( data ) {
					pollErrors = 0;
					updateProgress( data );
					if (
						data.phase === 'complete' ||
						data.phase === 'cancelled' ||
						data.phase === 'error'
					) {
						clearInterval( pollTimer );
						pollTimer = null;
						showComplete( data );
					}
				} )
				.catch( function () {
					pollErrors++;
					if ( pollErrors >= 3 ) {
						clearInterval( pollTimer );
						pollTimer = null;
						document.getElementById(
							'shqf-mig-phase'
						).textContent = shqfMigration.lostConnection;
					}
				} );
		}, 2000 );
	}

	function updateProgress( data ) {
		const phases = {
			categories: shqfMigration.migratingCats,
			samples: shqfMigration.migratingSamples,
			submissions: shqfMigration.migratingSubs,
			error: shqfMigration.migrationError,
			cancelled: shqfMigration.migrationCancelled,
		};
		document.getElementById( 'shqf-mig-phase' ).textContent =
			phases[ data.phase ] || data.phase;

		const total =
			( data.categories_total || 0 ) +
			( data.samples_total || 0 ) +
			( data.submissions_total || 0 );
		const done =
			( data.categories_completed || 0 ) +
			( data.samples_completed || 0 ) +
			( data.submissions_completed || 0 );
		const pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;

		document.getElementById( 'shqf-mig-bar' ).style.width = pct + '%';
		document.getElementById( 'shqf-mig-detail' ).textContent =
			done + ' / ' + total;
	}

	function showComplete( data ) {
		showStep( 'shqf-mig-complete' );
		const el = document.getElementById( 'shqf-mig-summary' );
		let html =
			'<table class="widefat striped" style="max-width:500px;margin-top:12px;">';
		html +=
			'<tr><td>' +
			shqfMigration.catsCreated +
			'</td><td>' +
			( data.categories_created || 0 ) +
			'</td></tr>';
		html +=
			'<tr><td>' +
			shqfMigration.catsUpdated +
			'</td><td>' +
			( data.categories_updated || 0 ) +
			'</td></tr>';
		html +=
			'<tr><td>' +
			shqfMigration.samplesCreated +
			'</td><td>' +
			( data.samples_created || 0 ) +
			'</td></tr>';
		html +=
			'<tr><td>' +
			shqfMigration.samplesUpdated +
			'</td><td>' +
			( data.samples_updated || 0 ) +
			'</td></tr>';
		html +=
			'<tr><td>' +
			shqfMigration.samplesSkipped +
			'</td><td>' +
			( data.samples_skipped || 0 ) +
			'</td></tr>';

		if ( data.include_submissions ) {
			html +=
				'<tr><td>' +
				shqfMigration.subsAccepted +
				'</td><td>' +
				( data.submissions_accepted || 0 ) +
				'</td></tr>';
			html +=
				'<tr><td>' +
				shqfMigration.subsDuplicates +
				'</td><td>' +
				( data.submissions_duplicates || 0 ) +
				'</td></tr>';
		}

		html += '</table>';

		if ( data.plan_limit_reached ) {
			html +=
				'<div class="notice notice-warning inline" style="margin-top:12px;"><p>' +
				shqfMigration.planLimitReached +
				'</p></div>';
		}

		if ( data.errors && data.errors.length > 0 ) {
			html +=
				'<div class="notice notice-error inline" style="margin-top:12px;"><p>' +
				data.errors.length +
				' ' +
				shqfMigration.errorsOccurred +
				'</p></div>';
		}

		if ( data.phase === 'cancelled' ) {
			document.querySelector(
				'#shqf-mig-complete .notice-success p strong'
			).textContent = shqfMigration.cancelled;
		}

		el.innerHTML = html;
	}

	// Cancel handler.
	document
		.getElementById( 'shqf-mig-cancel' )
		.addEventListener( 'click', function () {
			api( 'POST', 'cancel' );
		} );

	// Check for in-progress migration on load.
	api( 'GET', 'progress' )
		.then( function ( data ) {
			if (
				data.phase &&
				data.phase !== 'idle' &&
				data.phase !== 'complete' &&
				data.phase !== 'cancelled' &&
				data.phase !== 'error'
			) {
				showStep( 'shqf-mig-running' );
				updateProgress( data );
				startPolling();
			} else if (
				data.phase === 'complete' ||
				data.phase === 'cancelled'
			) {
				showComplete( data );
			} else {
				loadPreview();
			}
		} )
		.catch( function () {
			loadPreview();
		} );
} )();
