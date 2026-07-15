/**
 * Persian titles for hardcoded Streamit admin toast strings (JS-side).
 */
( function () {
	'use strict';

	var map = {
		Success: 'موفق',
		Error: 'خطا',
		'An unexpected error occurred. Please try again.':
			'خطای غیرمنتظره رخ داد. لطفاً دوباره تلاش کنید.',
		'Please fill all required fields': 'لطفاً همه فیلدهای الزامی را پر کنید.',
		'Movie updated successfully.': 'فیلم با موفقیت به‌روزرسانی شد.',
		'Movie added successfully.': 'فیلم با موفقیت افزوده شد.',
		'TV Show updated successfully.': 'سریال با موفقیت به‌روزرسانی شد.',
		'TV Show added successfully.': 'سریال با موفقیت افزوده شد.',
		'Episode updated successfully.': 'قسمت با موفقیت به‌روزرسانی شد.',
		'Episode added successfully.': 'قسمت با موفقیت افزوده شد.',
	};

	function translateNode( node ) {
		if ( ! node || ! node.textContent ) {
			return;
		}
		var text = node.textContent.trim();
		if ( map[ text ] ) {
			node.textContent = map[ text ];
		}
	}

	function localizeToast( toast ) {
		if ( ! toast ) {
			return;
		}
		toast.querySelectorAll( '.text-1, .text-2' ).forEach( translateNode );
	}

	if ( typeof MutationObserver !== 'undefined' ) {
		var observer = new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( node.nodeType !== 1 ) {
						return;
					}
					if ( node.classList && node.classList.contains( 'notification-toast' ) ) {
						localizeToast( node );
					}
					if ( node.querySelectorAll ) {
						node.querySelectorAll( '.notification-toast' ).forEach( localizeToast );
					}
				} );
			} );
		} );

		observer.observe( document.body, { childList: true, subtree: true } );
	}

	document.querySelectorAll( '.notification-toast' ).forEach( localizeToast );
} )();
