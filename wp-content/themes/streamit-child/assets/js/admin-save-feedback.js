/**
 * Immediate save feedback for Streamit admin edit forms.
 *
 * Parent JS changes the Update button to type="button" with no busy state,
 * so the UI looks dead until a toast appears seconds later. This keeps the
 * same AJAX flow and only adds clear Saving / done feedback.
 */
( function ( $ ) {
	'use strict';

	var FORM_SEL =
		'#insert_movie, #insert_episode, #insert_tvshow, #insert_video';

	var SAVE_ROUTES = {
		update_movie: true,
		streamit_add_new_movie: true,
		update_episode: true,
		streamit_add_new_episode: true,
		streamit_update_episode: true,
		update_tvshow: true,
		streamit_add_new_tvshow: true,
		streamit_update_tvshow: true,
		update_video: true,
		streamit_add_new_video: true,
	};

	var busyCount = 0;

	function savingLabel() {
		var lang = ( document.documentElement.lang || '' ).toLowerCase();
		return lang.indexOf( 'fa' ) === 0 ? 'در حال ذخیره…' : 'Saving…';
	}

	function findPrimaryButton( $form ) {
		var $btn = $form.find( '#publishing-action input[type="submit"], #publishing-action input[type="button"], .submit input[type="submit"], .submit input[type="button"], p.submit input.button-primary' ).first();
		if ( ! $btn.length ) {
			$btn = $form.find( 'input.button-primary[type="submit"], input.button-primary[type="button"], button.button-primary' ).last();
		}
		return $btn;
	}

	function setBusy( $btn, on ) {
		if ( ! $btn || ! $btn.length ) {
			return;
		}

		if ( on ) {
			if ( ! $btn.data( 'stcOrig' ) ) {
				$btn.data(
					'stcOrig',
					$btn.is( 'button' ) ? $.trim( $btn.text() ) : $btn.val()
				);
			}
			$btn
				.prop( 'disabled', true )
				.attr( 'aria-busy', 'true' )
				.addClass( 'stc-admin-saving' );
			if ( $btn.is( 'button' ) ) {
				$btn.text( savingLabel() );
			} else {
				$btn.val( savingLabel() );
			}
			return;
		}

		var orig = $btn.data( 'stcOrig' );
		$btn
			.prop( 'disabled', false )
			.attr( 'aria-busy', 'false' )
			.removeClass( 'stc-admin-saving' );
		if ( orig ) {
			if ( $btn.is( 'button' ) ) {
				$btn.text( orig );
			} else {
				$btn.val( orig );
			}
		}
		// Parent left the control as type=button — restore so it looks normal again.
		if ( $btn.is( 'input' ) && $btn.attr( 'type' ) === 'button' ) {
			$btn.attr( 'type', 'submit' );
		}
	}

	function activeFormButton() {
		var $form = $( FORM_SEL ).filter( ':visible' ).first();
		if ( ! $form.length ) {
			$form = $( FORM_SEL ).first();
		}
		return findPrimaryButton( $form );
	}

	function isSaveRequest( settings ) {
		if ( ! settings || ! settings.data ) {
			return false;
		}
		var data = settings.data;
		if ( typeof data === 'string' ) {
			return Object.keys( SAVE_ROUTES ).some( function ( route ) {
				return (
					data.indexOf( 'route_name=' + route ) !== -1 ||
					data.indexOf( '"' + route + '"' ) !== -1
				);
			} );
		}
		if ( typeof data === 'object' ) {
			return !! SAVE_ROUTES[ data.route_name ];
		}
		return false;
	}

	// As soon as the form submits, show busy (parent flips type in the same tick).
	$( document.body ).on( 'submit', FORM_SEL, function () {
		var $form = $( this );
		var $btn = findPrimaryButton( $form );
		setTimeout( function () {
			$btn = findPrimaryButton( $form );
			setBusy( $btn, true );
		}, 0 );
	} );

	$( document ).on( 'ajaxSend', function ( event, jqXHR, settings ) {
		if ( ! isSaveRequest( settings ) ) {
			return;
		}
		busyCount += 1;
		setBusy( activeFormButton(), true );
	} );

	$( document ).on( 'ajaxComplete', function ( event, jqXHR, settings ) {
		if ( ! isSaveRequest( settings ) ) {
			return;
		}
		busyCount = Math.max( 0, busyCount - 1 );
		if ( busyCount === 0 ) {
			setBusy( activeFormButton(), false );
		}
	} );
}( jQuery ) );
