/**
 * Trailer modal helpers: MIME for local video, stop on close, focus management.
 */
(function () {
	'use strict';

	var openerButton = null;
	var watchingModal = false;

	function applyMime(mime) {
		var source = document.querySelector('#trailerModal #trailerVideo source');
		if (!source || !mime) {
			return false;
		}
		source.setAttribute('type', mime);
		return true;
	}

	function stopTrailerMedia(modal) {
		if (!modal) {
			return;
		}

		var iframe = modal.querySelector('#trailerIframe');
		var video = modal.querySelector('#trailerVideo');

		if (iframe) {
			iframe.src = '';
			iframe.removeAttribute('src');
		}

		if (video) {
			video.pause();
			video.removeAttribute('src');
			video.currentTime = 0;
			var source = video.querySelector('source');
			if (source) {
				source.removeAttribute('src');
				source.removeAttribute('type');
			}
			if (typeof video.load === 'function') {
				video.load();
			}
		}
	}

	function restoreFocus() {
		if (openerButton && typeof openerButton.focus === 'function') {
			openerButton.focus();
		}
		openerButton = null;
	}

	function enhanceOpenModal(btn) {
		var modal = document.getElementById('trailerModal');
		if (!modal) {
			return;
		}

		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-hidden', 'false');

		var closeBtn = modal.querySelector('.btn-close');
		if (closeBtn && typeof closeBtn.focus === 'function') {
			closeBtn.focus();
		}

		if (watchingModal) {
			return;
		}
		watchingModal = true;

		var observer = new MutationObserver(function () {
			if (document.getElementById('trailerModal')) {
				return;
			}
			observer.disconnect();
			watchingModal = false;
			restoreFocus();
		});
		observer.observe(document.body, { childList: true });
	}

	if (typeof document !== 'undefined') {
		document.addEventListener(
			'click',
			function (event) {
				var btn = event.target.closest('.st-load-trailer');
				if (!btn) {
					var closing =
						event.target.closest('#trailerModal .btn-close') ||
						event.target.id === 'trailerModal';
					if (closing) {
						stopTrailerMedia(document.getElementById('trailerModal'));
					}
					return;
				}

				openerButton = btn;

				var mime = btn.getAttribute('data-trailer-mime') || '';
				if (mime) {
					if (!applyMime(mime)) {
						var tries = 0;
						var timer = window.setInterval(function () {
							tries += 1;
							if (applyMime(mime) || tries > 40) {
								window.clearInterval(timer);
							}
						}, 16);
					}
				}

				window.setTimeout(function () {
					enhanceOpenModal(btn);
				}, 0);
			},
			true
		);

		document.addEventListener(
			'keydown',
			function (event) {
				if (event.key !== 'Escape') {
					return;
				}
				var modal = document.getElementById('trailerModal');
				if (modal && modal.classList.contains('show')) {
					stopTrailerMedia(modal);
				}
			},
			true
		);
	}

	// Expose for focused unit tests (Node).
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = {
			applyMime: applyMime,
			stopTrailerMedia: stopTrailerMedia,
		};
	}
})();
