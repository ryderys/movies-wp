/**
 * Import progress UI — reliable completion detection for Streamit TMDB import.
 */
( function ( $ ) {
	'use strict';

	var i18n = window.streamitChildImport || {};
	var elapsedTimer = null;
	var importWatchdog = null;
	var importStart = 0;
	var importActive = false;
	var $status = null;
	var $importBtn = null;
	var importBtnText = '';

	function getRouteFromData( data ) {
		if ( ! data ) {
			return '';
		}
		if ( typeof data === 'string' ) {
			var match = data.match( /(?:^|&)route_name=([^&]*)/ );
			return match ? decodeURIComponent( match[1].replace( /\+/g, ' ' ) ) : '';
		}
		if ( data instanceof FormData ) {
			return data.get( 'route_name' ) || '';
		}
		if ( typeof data === 'object' ) {
			return data.route_name || '';
		}
		return '';
	}

	function isImportRoute( route ) {
		return route === 'streamit_insert_import_content';
	}

	function ensureStatusPanel() {
		if ( $status && $status.length ) {
			return $status;
		}

		$status = $( '<div id="streamit-child-import-status" class="streamit-child-import-status" role="status" aria-live="polite" style="display:none;"></div>' );
		var $table = $( '.st_display_import_api_output' ).first();
		if ( $table.length ) {
			$table.before( $status );
		} else {
			$( '#streamit_insert_content_import_button' ).closest( '.d-flex' ).after( $status );
		}

		return $status;
	}

	function formatElapsed( ms ) {
		var totalSeconds = Math.floor( ms / 1000 );
		var minutes = Math.floor( totalSeconds / 60 );
		var seconds = totalSeconds % 60;
		return minutes + ':' + ( seconds < 10 ? '0' : '' ) + seconds;
	}

	function stopTimers() {
		if ( elapsedTimer ) {
			clearInterval( elapsedTimer );
			elapsedTimer = null;
		}
		if ( importWatchdog ) {
			clearTimeout( importWatchdog );
			importWatchdog = null;
		}
	}

	function resetImportUi() {
		stopTimers();
		importActive = false;

		$importBtn = $( '#streamit_content_import_button' );
		$importBtn
			.prop( 'disabled', false )
			.removeClass( 'is-importing' )
			.val( importBtnText || 'Import' )
			.text( importBtnText || 'Import' );

		$( '#streamit_import_serach' ).prop( 'disabled', false );
		$( '.streamit-child-import-overlay' ).removeClass( 'is-busy' );
	}

	function setStatus( state, title, message, showDismiss ) {
		var panel = ensureStatusPanel();
		var elapsed = importStart ? formatElapsed( Date.now() - importStart ) : '0:00';
		var spinner = state === 'loading' ? '<span class="streamit-child-import-status__spinner" aria-hidden="true"></span>' : '';
		var dismiss = showDismiss
			? '<p><button type="button" class="button streamit-child-import-status__dismiss">' + ( i18n.dismiss || 'Dismiss' ) + '</button></p>'
			: '';

		panel
			.removeClass( 'is-loading is-success is-error is-warn' )
			.addClass( 'is-' + state )
			.html(
				spinner +
					'<span class="streamit-child-import-status__title">' + title + '</span>' +
					'<p class="streamit-child-import-status__message">' + message + '</p>' +
					( state === 'loading'
						? '<p class="streamit-child-import-status__elapsed">' + ( i18n.elapsed || 'Elapsed' ) + ': <span data-elapsed>' + elapsed + '</span></p>'
						: '<p class="streamit-child-import-status__elapsed">' + ( i18n.finishedIn || 'Finished in' ) + ' ' + elapsed + '</p>' ) +
					dismiss
			)
			.show();
	}

	function startImportProgress() {
		if ( importActive ) {
			return;
		}

		importActive = true;
		importStart = Date.now();
		$importBtn = $( '#streamit_content_import_button' );

		if ( $importBtn.length && ! importBtnText ) {
			importBtnText = $importBtn.val() || $importBtn.text();
		}

		$importBtn
			.prop( 'disabled', true )
			.addClass( 'is-importing' )
			.val( i18n.importing || 'Importing…' )
			.text( i18n.importing || 'Importing…' );

		$( '#streamit_import_serach' ).prop( 'disabled', true );
		$( '.st_display_import_api_output' ).closest( 'div' ).addClass( 'streamit-child-import-overlay is-busy' );

		setStatus(
			'loading',
			i18n.importingTitle || 'Import in progress',
			i18n.importingMessage || 'Please wait — this can take several minutes. Do not close this page.'
		);

		stopTimers();
		elapsedTimer = setInterval( function () {
			var $elapsed = $status.find( '[data-elapsed]' );
			if ( $elapsed.length ) {
				$elapsed.text( formatElapsed( Date.now() - importStart ) );
			}
		}, 1000 );

		// Server may finish before the HTTP response returns — unblock UI after 90s.
		importWatchdog = setTimeout( function () {
			if ( ! importActive ) {
				return;
			}
			finishImportProgress(
				'warn',
				i18n.stillRunning || 'If the content is already on your site, the import succeeded — dismiss this and search for another title.'
			);
		}, 90000 );
	}

	function finishImportProgress( state, message ) {
		if ( ! importActive ) {
			return;
		}

		resetImportUi();

		if ( state === 'success' ) {
			setStatus(
				'success',
				i18n.successTitle || 'Import complete',
				message || i18n.successMessage || 'Content was imported successfully.',
				true
			);
			refreshImportResults();
			return;
		}

		if ( state === 'warn' ) {
			setStatus(
				'warn',
				i18n.uncertainTitle || 'Import may be complete',
				message || i18n.uncertainMessage || 'The server did not confirm, but content may already be on your site.',
				true
			);
			refreshImportResults();
			return;
		}

		setStatus(
			'error',
			i18n.errorTitle || 'Import failed',
			message || i18n.errorMessage || 'Something went wrong. Try again.',
			true
		);
	}

	function parseResponse( xhr ) {
		if ( xhr.responseJSON ) {
			return xhr.responseJSON;
		}

		var text = xhr.responseText || '';
		if ( ! text ) {
			return null;
		}

		try {
			return JSON.parse( text );
		} catch ( e ) {
			var match = text.match( /\{[\s\S]*\}/ );
			if ( match ) {
				try {
					return JSON.parse( match[0] );
				} catch ( e2 ) {
					return null;
				}
			}
		}

		return null;
	}

	function handleImportComplete( jqXHR, textStatus ) {
		if ( ! importActive ) {
			return;
		}

		var response = parseResponse( jqXHR );
		var httpStatus = jqXHR.status;

		if ( httpStatus === 200 && response && response.status ) {
			finishImportProgress( 'success', response.message );
			return;
		}

		if ( httpStatus === 200 && response && ! response.status ) {
			finishImportProgress( 'error', response.message );
			return;
		}

		if ( textStatus === 'timeout' || textStatus === 'error' || httpStatus === 0 || httpStatus >= 502 ) {
			finishImportProgress(
				'warn',
				i18n.uncertainMessage || 'The connection ended before confirmation. If the content is on your site, the import succeeded.'
			);
			return;
		}

		if ( httpStatus === 200 ) {
			finishImportProgress(
				'warn',
				i18n.invalidResponse || 'Could not read the server response. If the content is on your site, the import succeeded.'
			);
			return;
		}

		finishImportProgress( 'error', i18n.networkError || 'Request failed. Try again or check the site.' );
	}

	function refreshImportResults() {
		$( '#streamit_import_serach' ).trigger( 'click' );
	}

	$.ajaxPrefilter( function ( options, _originalOptions, jqXHR ) {
		var route = getRouteFromData( options.data );
		if ( ! isImportRoute( route ) ) {
			return;
		}

		options.timeout = 300000;
		startImportProgress();

		jqXHR.always( function ( _data, textStatus ) {
			handleImportComplete( jqXHR, textStatus );
		} );
	} );

	$( document ).on( 'click', '.streamit-child-import-status__dismiss', function () {
		ensureStatusPanel().hide();
	} );
}( jQuery ) );
