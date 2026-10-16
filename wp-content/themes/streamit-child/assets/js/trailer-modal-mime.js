/**
 * Apply data-trailer-mime onto the injected trailer modal <source>.
 */
(function () {
	'use strict';

	function applyMime(mime) {
		var source = document.querySelector('#trailerModal #trailerVideo source');
		if (!source || !mime) {
			return false;
		}
		source.setAttribute('type', mime);
		return true;
	}

	document.addEventListener(
		'click',
		function (event) {
			var btn = event.target.closest('.st-load-trailer');
			if (!btn) {
				return;
			}

			var mime = btn.getAttribute('data-trailer-mime') || '';
			if (!mime) {
				return;
			}

			if (applyMime(mime)) {
				return;
			}

			var tries = 0;
			var timer = window.setInterval(function () {
				tries += 1;
				if (applyMime(mime) || tries > 40) {
					window.clearInterval(timer);
				}
			}, 16);
		},
		true
	);
})();
