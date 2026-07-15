/**
 * Keep layout direction in sync with the theme_scheme_direction cookie.
 *
 * Page cache may serve HTML with the wrong dir= while the switcher still reads RTL
 * from the browser cookie — this script fixes that on every navigation.
 */
(function () {
	'use strict';

	function getDirectionFromCookie() {
		var match = document.cookie.match(/(?:^|;\s*)theme_scheme_direction=(rtl|ltr)/);
		return match ? match[1] : 'rtl';
	}

	function applyDirection(dir) {
		var root = document.documentElement;

		if (root.getAttribute('dir') !== dir) {
			root.setAttribute('dir', dir);
		}

		if (document.body) {
			document.body.classList.toggle('rtl', dir === 'rtl');
			document.body.classList.toggle('streamit-child-rtl', dir === 'rtl');
		}
	}

	function ensureCookie(dir) {
		if (!document.cookie.match(/(?:^|;\s*)theme_scheme_direction=/)) {
			document.cookie =
				'theme_scheme_direction=' +
				dir +
				';path=/;max-age=' +
				7 * 86400 +
				';SameSite=Lax';
		}
	}

	function syncSwitcherUi(dir) {
		if (typeof jQuery === 'undefined') {
			return;
		}

		var active = '#theme-scheme-direction-' + dir;
		var inactive = '#theme-scheme-direction-' + (dir === 'rtl' ? 'ltr' : 'rtl');

		jQuery(active).addClass('active');
		jQuery(inactive).removeClass('active');
	}

	function reinitSlickSliders(dir) {
		if (typeof jQuery === 'undefined' || !jQuery.fn.slick) {
			return;
		}

		var isRtl = dir === 'rtl';

		jQuery('.css_prefix-slick-general.slick-initialized').each(function () {
			var $el = jQuery(this);

			try {
				var instance = $el.slick('getSlick');

				if (!instance || !!instance.options.rtl === isRtl) {
					return;
				}

				var settings = jQuery.extend({}, $el.data('slider_settings') || {});
				settings.rtl = isRtl;
				settings.lazyLoad = settings.lazyLoad || 'ondemand';

				$el.slick('unslick');
				$el.slick(settings);
			} catch (e) {
				/* Slider not ready yet. */
			}
		});
	}

	function runSync() {
		var dir = getDirectionFromCookie();
		applyDirection(dir);
		ensureCookie(dir);
		syncSwitcherUi(dir);
		reinitSlickSliders(dir);
	}

	runSync();

	document.addEventListener('DOMContentLoaded', function () {
		runSync();
		// SlickGeneral inits during module load; re-check after it has run.
		setTimeout(runSync, 150);
		setTimeout(runSync, 500);
	});
})();
