/**
 * Streamit admin edit UX: relaxed validation on update + improved AJAX Select2 for tags/genres.
 */
( function ( $ ) {
	'use strict';

	function fieldHasValue( selector ) {
		var val = $( selector ).val();
		if ( Array.isArray( val ) ) {
			return val.length > 0;
		}
		return !!$.trim( val || '' );
	}

	// Edit mode: only require thumbnails when the hidden field is actually empty.
	if ( typeof wp !== 'undefined' && wp.hooks ) {
		wp.hooks.addFilter( 'tvshow_form_validation_rules', 'streamit-child/edit-relaxed', function ( rules ) {
			if ( ! fieldHasValue( '#tvshow_id' ) ) {
				return rules;
			}

			rules.tvshow_thumbnail_id = {
				required: function () {
					return ! fieldHasValue( '#tvshow_thumbnail_id' );
				},
			};

			rules._portrait_thumbmail = {
				required: function () {
					return ! fieldHasValue( '#_portrait_thumbmail' );
				},
			};

			return rules;
		} );

		wp.hooks.addFilter( 'movie_form_validation_rules', 'streamit-child/edit-relaxed', function ( rules ) {
			if ( ! fieldHasValue( '#movie_id' ) ) {
				return rules;
			}

			rules.movie_thumbnail_id = {
				required: function () {
					return ! fieldHasValue( '#movie_thumbnail_id' );
				},
			};

			return rules;
		} );
	}

	function select2AjaxTransport( params, success, failure, extraParams ) {
		var payload = $.extend(
			{
				search: params.data.term || '',
				page: params.data.page || 1,
				action: 'streamit_ajax_get',
				route_name: 'streamit_ajax_select2',
			},
			extraParams || {}
		);

		$.ajax( {
			url: window.ajaxurl,
			type: 'GET',
			data: payload,
		} )
			.done( success )
			.fail( failure );
	}

	function processSelect2Results( response, params ) {
		params.page = params.page || 1;

		var results = [];
		if ( response.success && Array.isArray( response.data && response.data.items ) ) {
			results = response.data.items.map( function ( item ) {
				return { id: item.id, text: item.text };
			} );
		}

		return {
			results: results,
			pagination: {
				more: !!( response.data && response.data.hasMore ),
			},
		};
	}

	function patchAjaxSelect2( context ) {
		if ( ! $.fn.select2 ) {
			return;
		}

		var $root = context ? $( context ) : $( document );
		$root.find( '.st_select2_ajax_search' ).each( function () {
			var $field = $( this );

			if ( ! $field.hasClass( 'select2-hidden-accessible' ) ) {
				return;
			}

			if ( $field.data( 'streamitChildPatched' ) ) {
				return;
			}

			var savedVal = $field.val();
			var preselected = [];

			$field.find( 'option:selected' ).each( function () {
				preselected.push( {
					id: $( this ).val(),
					text: $( this ).text(),
				} );
			} );

			var ajaxParams = {};
			try {
				ajaxParams = JSON.parse( $field.attr( 'data-ajax-params' ) || '{}' );
			} catch ( e ) {
				ajaxParams = {};
			}

			$field.select2( 'destroy' );

			var $dropdownParent = $( '#streamit_data_section' );
			if ( ! $dropdownParent.length ) {
				$dropdownParent = $( 'body' );
			}

			$field.select2( {
				width: '100%',
				allowClear: true,
				minimumInputLength: 0,
				dropdownParent: $dropdownParent,
				ajax: {
					transport: function ( params, success, failure ) {
						select2AjaxTransport( params, success, failure, ajaxParams );
					},
					delay: 250,
					dataType: 'json',
					processResults: processSelect2Results,
					cache: true,
				},
			} );

			preselected.forEach( function ( item ) {
				if ( ! $field.find( "option[value='" + item.id + "']" ).length ) {
					$field.append( new Option( item.text, item.id, true, true ) );
				}
			} );

			if ( savedVal ) {
				$field.val( savedVal ).trigger( 'change' );
			}

			$field.data( 'streamitChildPatched', true );
		} );
	}

	function schedulePatch() {
		window.setTimeout( function () {
			patchAjaxSelect2( document );
		}, 400 );
	}

	/**
	 * Turn a term name into a URL-safe slug (Latin only; empty for non-Latin input).
	 */
	function slugify( value ) {
		return ( value || '' )
			.toString()
			.trim()
			.toLowerCase()
			.replace( /['"]/g, '' )
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' );
	}

	/**
	 * Auto-fill the slug field from the name field on term Add screens.
	 * Only fills while the slug is empty or still matches the last auto value,
	 * so a manually edited slug is never clobbered.
	 */
	function setupSlugAutofill() {
		var pairs = [
			[ '#tvshow_tag_name', '#tvshow_tag_slug' ],
			[ '#movie_tag_name', '#movie_tag_slug' ],
			[ '#tvshow_genre_name', '#tvshow_genre_slug' ],
			[ '#movie_genre_name', '#movie_genre_slug' ],
			[ '#video_category_name', '#video_category_slug' ],
			[ '#person_tag_name', '#person_tag_slug' ],
			[ '#person_genre_name', '#person_genre_slug' ],
		];

		pairs.forEach( function ( pair ) {
			var $name = $( pair[ 0 ] );
			var $slug = $( pair[ 1 ] );

			if ( ! $name.length || ! $slug.length ) {
				return;
			}

			// Do not auto-fill on the Update screen where a slug already exists.
			if ( $.trim( $slug.val() || '' ) ) {
				$slug.data( 'streamitChildAutoSlug', $slug.val() );
			}

			$name.on( 'input keyup change', function () {
				var current = $.trim( $slug.val() || '' );
				var lastAuto = $slug.data( 'streamitChildAutoSlug' ) || '';

				if ( current && current !== lastAuto ) {
					return;
				}

				var next = slugify( $name.val() );
				if ( ! next ) {
					return;
				}

				$slug.val( next );
				$slug.data( 'streamitChildAutoSlug', next );
			} );
		} );
	}

	/**
	 * After a failed submit, replace the generic error toast with a message
	 * that names the tabs still containing errors.
	 */
	function setupTabErrorToast() {
		if ( typeof MutationObserver === 'undefined' ) {
			return;
		}

		function failingTabNames() {
			var names = [];
			$( '.tvshow_meta_tabs li, .movie_meta_tabs li, .episode_meta_tabs li' ).each( function () {
				var $li = $( this );
				if ( $li.find( '.tab-error' ).length ) {
					var name = $.trim( $li.find( '> a' ).first().text() );
					if ( name ) {
						names.push( name );
					}
				}
			} );
			return names;
		}

		function enhanceToast( toast ) {
			var names = failingTabNames();
			if ( ! names.length ) {
				return;
			}

			var $message = $( toast ).find( '.text-2' ).first();
			if ( ! $message.length ) {
				return;
			}

			$message.text( 'لطفاً تب‌های دارای خطا را بررسی کنید: ' + names.join( '، ' ) );
		}

		var observer = new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( node.nodeType !== 1 ) {
						return;
					}
					if ( node.classList && node.classList.contains( 'notification-toast' ) ) {
						window.setTimeout( function () {
							enhanceToast( node );
						}, 30 );
					}
				} );
			} );
		} );

		observer.observe( document.body, { childList: true, subtree: true } );
	}

	$( document ).ready( function () {
		schedulePatch();
		setupSlugAutofill();
		setupTabErrorToast();

		$( document.body ).on( 'click', '.tvshow_meta_tabs li a, .movie_meta_tabs li a', function () {
			window.setTimeout( function () {
				patchAjaxSelect2( document );
			}, 100 );
		} );
	} );
}( jQuery ) );
