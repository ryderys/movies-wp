/**
 * Fetch TV-show morelike slider HTML when the placeholder nears the viewport.
 * On failure, leave the sync fallback link visible (never a permanent empty section).
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
				el.outerHTML = html;
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
