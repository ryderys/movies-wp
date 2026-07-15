/**
 * Subtitle repeater for the Streamit movie/episode Sources tab.
 *
 * The subtitle rows are collected at submit time and injected into the plugin's
 * own save payload through its `movie_form_data` / `episode_form_data` JS filter,
 * so subtitles persist via the standard "Update" button.
 *
 * @package streamit-child
 */
( function ( $ ) {
	'use strict';

	var config = window.streamitChildSubtitles || {};

	function getContainer() {
		return document.getElementById( 'streamit-child-subtitles' );
	}

	/**
	 * Read the current subtitle rows from the DOM.
	 *
	 * @return {Array<Object>}
	 */
	function collectRows() {
		var container = getContainer();
		if ( ! container ) {
			return [];
		}

		var rows = [];

		container.querySelectorAll( '.stc-sub-row' ).forEach( function ( rowEl ) {
			var url = ( rowEl.querySelector( '.stc-sub-url' ) || {} ).value || '';
			url = url.trim();
			if ( ! url ) {
				return;
			}

			rows.push( {
				label: ( ( rowEl.querySelector( '.stc-sub-label' ) || {} ).value || '' ).trim(),
				srclang: ( ( rowEl.querySelector( '.stc-sub-srclang' ) || {} ).value || '' ).trim(),
				url: url,
				'default': !! ( rowEl.querySelector( '.stc-sub-default' ) || {} ).checked
			} );
		} );

		return rows;
	}

	/**
	 * Add a fresh empty row from the <template>.
	 */
	function addRow() {
		var container = getContainer();
		if ( ! container ) {
			return;
		}

		var tpl = container.querySelector( '#stc-sub-row-template' );
		var list = container.querySelector( '.stc-sub-rows' );
		if ( ! tpl || ! list ) {
			return;
		}

		var clone = tpl.content.cloneNode( true );
		list.appendChild( clone );
	}

	/**
	 * Enforce a single "default" track.
	 *
	 * @param {HTMLElement} changed The checkbox that was toggled.
	 */
	function enforceSingleDefault( changed ) {
		if ( ! changed.checked ) {
			return;
		}

		var container = getContainer();
		if ( ! container ) {
			return;
		}

		container.querySelectorAll( '.stc-sub-default' ).forEach( function ( box ) {
			if ( box !== changed ) {
				box.checked = false;
			}
		} );
	}

	/**
	 * WP media picker for the subtitle file URL.
	 *
	 * @param {HTMLElement} button The clicked "select file" button.
	 */
	function openMediaPicker( button ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		var frame = window.wp.media( {
			title: config.mediaTitle || 'Select subtitle file',
			button: { text: config.mediaButton || 'Use this file' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var input = button.closest( '.stc-sub-url-wrap' ).querySelector( '.stc-sub-url' );
			if ( input && attachment && attachment.url ) {
				input.value = attachment.url;
			}
		} );

		frame.open();
	}

	function bindEvents() {
		var container = getContainer();
		if ( ! container ) {
			return;
		}

		container.addEventListener( 'click', function ( e ) {
			var addBtn = e.target.closest( '.stc-sub-add' );
			if ( addBtn ) {
				e.preventDefault();
				addRow();
				return;
			}

			var removeBtn = e.target.closest( '.stc-sub-remove' );
			if ( removeBtn ) {
				e.preventDefault();
				var row = removeBtn.closest( '.stc-sub-row' );
				if ( row ) {
					row.parentNode.removeChild( row );
				}
				return;
			}

			var mediaBtn = e.target.closest( '.stc-sub-media' );
			if ( mediaBtn ) {
				e.preventDefault();
				openMediaPicker( mediaBtn );
			}
		} );

		container.addEventListener( 'change', function ( e ) {
			if ( e.target.classList.contains( 'stc-sub-default' ) ) {
				enforceSingleDefault( e.target );
			}
		} );
	}

	/**
	 * Attach subtitles to the plugin's save payload.
	 */
	function registerFormDataFilter() {
		if ( ! window.wp || ! window.wp.hooks || ! config.formFilter ) {
			return;
		}

		window.wp.hooks.addFilter(
			config.formFilter,
			'streamit-child/subtitles',
			function ( formData ) {
				formData._subtitles = collectRows();
				return formData;
			}
		);
	}

	$( function () {
		if ( ! getContainer() ) {
			return;
		}

		bindEvents();
		registerFormDataFilter();
	} );
} )( jQuery );
