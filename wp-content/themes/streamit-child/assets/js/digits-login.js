/**
 * Digits phone field fixes for Streamit phone-login page.
 *
 * Digits injects inline padding-left on .mobile_field for its absolute
 * country-code overlay. We use two separate inputs instead, so that padding
 * must be cleared whenever Digits re-applies it.
 */
(function () {
	'use strict';

	function fixMobileFields(root) {
		var scope = root || document;
		var fields = scope.querySelectorAll(
			'body.streamit-digits-login-page .digits-mobile_wrapper .mobile_field'
		);

		fields.forEach(function (field) {
			if (field.style.paddingLeft) {
				field.style.paddingLeft = '';
			}
			if (field.style.paddingRight) {
				field.style.paddingRight = '';
			}
			field.style.textAlign = 'center';
		});
	}

	function observe() {
		fixMobileFields(document);

		var target = document.querySelector('body.streamit-digits-login-page');
		if (!target || typeof MutationObserver === 'undefined') {
			return;
		}

		var observer = new MutationObserver(function () {
			fixMobileFields(document);
		});

		observer.observe(target, {
			subtree: true,
			childList: true,
			attributes: true,
			attributeFilter: ['style'],
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', observe);
	} else {
		observe();
	}
})();
