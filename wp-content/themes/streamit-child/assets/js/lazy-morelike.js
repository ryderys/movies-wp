/**
 * Fetch TV-show morelike slider HTML when the placeholder nears the viewport.
 * On failure, leave the sync fallback link visible (never a permanent empty section).
 *
 * After inject, Slick must be initialized: theme SlickGeneral only runs on first paint,
 * when this section is still a placeholder (skeleton overlay never clears otherwise).
 */
(function () {
	'use strict';

	var cfg = window.streamitChildLazyMorelike || {};
	if (!cfg.ajaxurl || !cfg.action) {
		return;
	}

	function showFallback(el) {
		el.setAttribute('aria-busy', 'false');
		el.classList.add('stc-lazy-morelike--failed');
		var fb = el.querySelector('.stc-lazy-morelike__fallback');
		if (fb) {
			fb.hidden = false;
		}
	}

	/**
	 * Mirror SlickGeneral.initSlickSlider for markup injected after first paint.
	 *
	 * @param {Element|null} root Injected .more-like-section (or parent).
	 */
	function initInjectedSlick(root, attempt) {
		if (!root) {
			return;
		}
		attempt = attempt || 0;
		if (typeof jQuery === 'undefined' || typeof jQuery.fn.slick !== 'function') {
			if (attempt < 40) {
				window.setTimeout(function () {
					initInjectedSlick(root, attempt + 1);
				}, 50);
			}
			return;
		}

		var isRtl = document.documentElement.getAttribute('dir') === 'rtl';
		var $targets = jQuery(root).find('.css_prefix-slick-general').addBack('.css_prefix-slick-general');

		$targets.each(function () {
			var $el = jQuery(this);
			if (!$el.length || $el.hasClass('slick-initialized')) {
				return;
			}

			var slickSettings = jQuery.extend({}, $el.data('slider_settings') || {});
			slickSettings.lazyLoad = 'ondemand';
			slickSettings.rtl = isRtl;

			var singleSliderSettings = $el.data('slingle_slider_settings');
			if (singleSliderSettings) {
				slickSettings = jQuery.extend(slickSettings, singleSliderSettings);
			}

			if ($el.data('extra_settings') === true) {
				slickSettings.nextArrow =
					'<button class="NextArrow-two"><i class="icon-arrow-right"></i></button>';
				slickSettings.prevArrow =
					'<button class="PreArrow-two"><i class="icon-arrow-left"></i></button>';
			}

			$el.slick(slickSettings);
		});
	}

	function replaceWithHtml(el, html) {
		var parent = el.parentNode;
		el.outerHTML = html;
		if (!parent) {
			return;
		}
		var section = parent.querySelector('.more-like-section');
		initInjectedSlick(section || parent);
	}

	function loadEl(el) {
		if (!el || el.getAttribute('data-stc-loaded') === '1') {
			return;
		}
		el.setAttribute('data-stc-loaded', '1');

		var postId = el.getAttribute('data-post-id');
		var nonce = el.getAttribute('data-nonce');
		if (!postId || !nonce) {
			showFallback(el);
			return;
		}

		var fb = el.querySelector('.stc-lazy-morelike__fallback');
		if (fb) {
			fb.hidden = true;
		}

		var url =
			cfg.ajaxurl +
			'?action=' +
			encodeURIComponent(cfg.action) +
			'&post_id=' +
			encodeURIComponent(postId) +
			'&nonce=' +
			encodeURIComponent(nonce);

		fetch(url, { credentials: 'same-origin' })
			.then(function (res) {
				return res.json().then(function (payload) {
					return { ok: res.ok, payload: payload };
				});
			})
			.then(function (result) {
				var payload = result.payload;
				if (!result.ok || !payload || !payload.success || !payload.data) {
					showFallback(el);
					return;
				}
				var html = payload.data.html;
				if (typeof html !== 'string') {
					showFallback(el);
					return;
				}
				// Empty HTML = feature off / no recommendations — remove shell, no dead gap.
				if (!html) {
					el.remove();
					return;
				}
				replaceWithHtml(el, html);
			})
			.catch(function () {
				showFallback(el);
			});
	}

	function observe(el) {
		if (!('IntersectionObserver' in window)) {
			loadEl(el);
			return;
		}
		var io = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						io.unobserve(entry.target);
						loadEl(entry.target);
					}
				});
			},
			{ rootMargin: '200px 0px' }
		);
		io.observe(el);
	}

	function boot() {
		var nodes = document.querySelectorAll('.stc-lazy-morelike');
		for (var i = 0; i < nodes.length; i++) {
			observe(nodes[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
