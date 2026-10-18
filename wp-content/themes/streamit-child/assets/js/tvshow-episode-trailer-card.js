/**
 * Keep the TV-show trailer card at the start of the first episodes slider.
 *
 * Season changes rebuild that slider from episode markup. This puts the
 * server-rendered card back without treating it as an episode.
 */
(function () {
	'use strict';

	function sourceCard() {
		var source = document.getElementById('streamit-child-episode-trailer-source');
		if (!source || !source.content || !source.content.firstElementChild) {
			return '';
		}
		return source.content.firstElementChild.outerHTML;
	}

	function firstSlider() {
		return document.querySelector('.css_prefix-slick-general[data-season_no="0"]');
	}

	function ensureTrailerCard() {
		var html = sourceCard();
		var host = firstSlider();
		if (!html || !host || host.querySelector('.streamit-child-episode-trailer')) {
			return;
		}

		var $ = window.jQuery;
		if ($ && typeof $.fn !== 'undefined' && typeof $.fn.slick === 'function' && $(host).hasClass('slick-initialized')) {
			$(host).slick('slickAdd', html, 0, true);
			return;
		}

		host.insertAdjacentHTML('afterbegin', html);
	}

	function watch() {
		var host = firstSlider();
		if (!host || host.getAttribute('data-trailer-watch') === '1') {
			return;
		}
		host.setAttribute('data-trailer-watch', '1');
		ensureTrailerCard();

		if (typeof MutationObserver === 'undefined') {
			return;
		}

		var observer = new MutationObserver(function () {
			ensureTrailerCard();
		});
		observer.observe(host, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', watch);
	} else {
		watch();
	}
})();
