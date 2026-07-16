/**
 * Optional «حجم» (file_size) field on each Streamit source row.
 *
 * Injected into the Sources tab UI and merged into `_source` / `_sources`
 * via the plugin's movie_form_data / episode_form_data filter on save.
 *
 * @package streamit-child
 */
( function ( $ ) {
	'use strict';

	var config = window.streamitChildSourcesExtra || {};

	function buildFieldHtml( value ) {
		var $wrap = $(
			'<div class="streamit-col-md-6 mb-4 stc-source-filesize">' +
			'<p class="form-field stc-source-filesize__field"></p>' +
			'</div>'
		);
		var $field = $wrap.find( '.stc-source-filesize__field' );
		$field.append(
			$( '<label/>', { for: 'source_file_size', text: config.label || 'حجم' } )
		);
		$field.append(
			$( '<input/>', {
				type: 'text',
				id: 'source_file_size',
				name: 'source_file_size',
				'class': 'stc-source-file-size',
				value: value || '',
				placeholder: config.placeholder || '',
			} )
		);
		return $wrap;
	}

	/**
	 * Ensure a file_size input exists on a source metabox.
	 *
	 * @param {HTMLElement|jQuery} sourceEl Source row.
	 * @param {string}             value    Prefill value.
	 */
	function ensureFileSizeField( sourceEl, value ) {
		var $source = $( sourceEl );
		if ( ! $source.length || $source.find( '.stc-source-file-size' ).length ) {
			return;
		}

		var $row =
			$source.find( '.date-download-wrap' ).first().length
				? $source.find( '.date-download-wrap' ).first()
				: $source.find( '.name-quality-wrap .source-name-wrap, .name-quality-wrap' ).first();

		if ( ! $row.length ) {
			$row = $source.find( '.streamit_source_data > div' ).first();
		}

		if ( ! $row.length ) {
			return;
		}

		$row.append( buildFieldHtml( value || '' ) );
	}

	function injectAll() {
		var sizes = Array.isArray( config.fileSizes ) ? config.fileSizes : [];

		$( '.streamit_source' ).each( function ( index ) {
			ensureFileSizeField( this, sizes[ index ] || '' );
		} );
	}

	function registerFormDataFilter() {
		if ( ! window.wp || ! window.wp.hooks || ! config.formFilter || ! config.sourcesKey ) {
			return;
		}

		window.wp.hooks.addFilter(
			config.formFilter,
			'streamit-child/source-file-size',
			function ( formData ) {
				var key = config.sourcesKey;
				var sources = formData[ key ];

				if ( ! Array.isArray( sources ) ) {
					return formData;
				}

				var visible = $( '.streamit_source' ).filter( function () {
					return $( this ).css( 'display' ) !== 'none';
				} );

				visible.each( function ( index ) {
					if ( ! sources[ index ] || typeof sources[ index ] !== 'object' ) {
						return;
					}

					var size = ( $( this ).find( '.stc-source-file-size' ).val() || '' ).trim();
					sources[ index ].file_size = size;
				} );

				formData[ key ] = sources;
				return formData;
			}
		);
	}

	function observeNewSources() {
		var containers = document.querySelectorAll(
			'.streamit-movie_sources, .streamit-episode_sources'
		);

		if ( ! containers.length || typeof MutationObserver === 'undefined' ) {
			return;
		}

		containers.forEach( function ( container ) {
			var observer = new MutationObserver( function ( mutations ) {
				mutations.forEach( function ( mutation ) {
					mutation.addedNodes.forEach( function ( node ) {
						if ( node.nodeType !== 1 ) {
							return;
						}

						if ( node.classList && node.classList.contains( 'streamit_source' ) ) {
							ensureFileSizeField( node, '' );
						} else if ( node.querySelectorAll ) {
							node.querySelectorAll( '.streamit_source' ).forEach( function ( el ) {
								ensureFileSizeField( el, '' );
							} );
						}
					} );
				} );
			} );

			observer.observe( container, { childList: true, subtree: true } );
		} );
	}

	$( function () {
		injectAll();
		observeNewSources();
		registerFormDataFilter();
	} );
} )( jQuery );
