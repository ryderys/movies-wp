/**
 * Rewrite Streamit admin-ajax POSTs into a single JSON `payload` field
 * so PHP max_input_vars cannot truncate large movie/TV show saves.
 */
( function ( $ ) {
	'use strict';

	if ( ! $ || ! $.ajax || $.ajax.__streamitPayloadWrapped ) {
		return;
	}

	var originalAjax = $.ajax;

	function maybeWrapData( data ) {
		if ( ! data || typeof data !== 'object' || data instanceof FormData ) {
			return data;
		}

		if ( data.action !== 'streamit_ajax_post' || data.payload ) {
			return data;
		}

		var route = data.route_name;
		var nonce = data._ajax_nonce;
		var body = $.extend( true, {}, data );
		delete body.action;
		delete body.route_name;
		delete body._ajax_nonce;

		return {
			action: 'streamit_ajax_post',
			route_name: route,
			_ajax_nonce: nonce,
			payload: JSON.stringify( body ),
		};
	}

	$.ajax = function ( url, options ) {
		if ( typeof url === 'object' ) {
			options = url;
			url = undefined;
		}

		options = options || {};
		options.data = maybeWrapData( options.data );

		// Prevent silent infinite "pending" on hung PHP/DB.
		if (
			options.data &&
			typeof options.data === 'object' &&
			options.data.action === 'streamit_ajax_post' &&
			! options.timeout
		) {
			options.timeout = 120000;
		}

		if ( url !== undefined ) {
			return originalAjax.call( this, url, options );
		}

		return originalAjax.call( this, options );
	};

	$.ajax.__streamitPayloadWrapped = true;
}( jQuery ) );
