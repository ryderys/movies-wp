/**
 * OST download box: shared mini-player with seek, play/pause, and stop.
 */
(function () {
	'use strict';

	var audio = null;
	var root = null;
	var activeBtn = null;
	var activeSrc = null;
	var seeking = false;

	function $(sel, ctx) {
		return (ctx || document).querySelector(sel);
	}

	function getRoot(fromEl) {
		if (root && document.contains(root)) {
			return root;
		}
		root = fromEl && fromEl.closest
			? fromEl.closest('[data-stc-ost]')
			: $('[data-stc-ost]');
		return root;
	}

	function playerEl(ostRoot) {
		return ostRoot ? $('[data-stc-ost-player]', ostRoot) : null;
	}

	function formatTime(seconds) {
		if (!isFinite(seconds) || seconds < 0) {
			return '0:00';
		}
		var s = Math.floor(seconds % 60);
		var m = Math.floor(seconds / 60);
		return m + ':' + (s < 10 ? '0' : '') + s;
	}

	function getAudio() {
		if (!audio) {
			audio = new Audio();
			audio.preload = 'metadata';

			audio.addEventListener('loadedmetadata', syncDuration);
			audio.addEventListener('durationchange', syncDuration);
			audio.addEventListener('timeupdate', onTimeUpdate);
			audio.addEventListener('play', onPlayState);
			audio.addEventListener('pause', onPlayState);
			audio.addEventListener('ended', onEnded);
			audio.addEventListener('error', onError);
		}
		return audio;
	}

	function ui() {
		var ost = getRoot();
		var panel = playerEl(ost);
		if (!panel) {
			return null;
		}
		return {
			ost: ost,
			panel: panel,
			title: $('[data-stc-ost-title]', panel),
			toggle: $('[data-stc-ost-toggle]', panel),
			toggleText: $('[data-stc-ost-toggle-text]', panel),
			stop: $('[data-stc-ost-stop]', panel),
			seek: $('[data-stc-ost-seek]', panel),
			current: $('[data-stc-ost-current]', panel),
			duration: $('[data-stc-ost-duration]', panel)
		};
	}

	function showPlayer(parts) {
		if (parts && parts.panel) {
			parts.panel.hidden = false;
		}
	}

	function setRowActive(btn) {
		var ost = getRoot(btn);
		if (!ost) {
			return;
		}
		ost.querySelectorAll('[data-stc-ost-play].is-playing').forEach(function (el) {
			el.classList.remove('is-playing');
		});
		if (btn) {
			btn.classList.add('is-playing');
		}
		activeBtn = btn || null;
	}

	function syncToggle(parts, playing) {
		if (!parts || !parts.toggle) {
			return;
		}
		var label = playing
			? (parts.toggle.getAttribute('data-label-pause') || 'توقف')
			: (parts.toggle.getAttribute('data-label-play') || 'پخش');
		parts.toggle.setAttribute('aria-label', label);
		parts.toggle.classList.toggle('is-playing', !!playing);
		if (parts.toggleText) {
			parts.toggleText.textContent = label;
		}
	}

	function syncDuration() {
		var parts = ui();
		var a = getAudio();
		if (!parts || !parts.seek) {
			return;
		}
		var dur = isFinite(a.duration) ? a.duration : 0;
		parts.seek.max = String(dur > 0 ? dur : 0);
		parts.seek.disabled = !(dur > 0);
		if (parts.duration) {
			parts.duration.textContent = formatTime(dur);
		}
		if (!seeking) {
			parts.seek.value = String(a.currentTime || 0);
		}
	}

	function onTimeUpdate() {
		if (seeking) {
			return;
		}
		var parts = ui();
		var a = getAudio();
		if (!parts) {
			return;
		}
		if (parts.current) {
			parts.current.textContent = formatTime(a.currentTime || 0);
		}
		if (parts.seek && !parts.seek.disabled) {
			parts.seek.value = String(a.currentTime || 0);
		}
	}

	function onPlayState() {
		var parts = ui();
		var a = getAudio();
		syncToggle(parts, !!(a && !a.paused && !a.ended));
	}

	function onEnded() {
		var parts = ui();
		var a = getAudio();
		if (a) {
			a.currentTime = 0;
		}
		syncToggle(parts, false);
		onTimeUpdate();
	}

	function onError() {
		var parts = ui();
		activeSrc = null;
		syncToggle(parts, false);
		setRowActive(null);
	}

	function loadAndPlay(btn) {
		var src = btn.getAttribute('data-src');
		if (!src) {
			return;
		}

		root = btn.closest('[data-stc-ost]') || root;
		var parts = ui();
		if (!parts) {
			return;
		}

		showPlayer(parts);
		if (parts.title) {
			parts.title.textContent = btn.getAttribute('data-title') || '';
		}

		var a = getAudio();
		setRowActive(btn);

		// Same track: resume if paused; leave playing as-is. Pause/stop live on the bar.
		if (activeSrc === src && a.src) {
			if (a.paused) {
				var resume = a.play();
				if (resume && typeof resume.catch === 'function') {
					resume.catch(function () {
						syncToggle(parts, false);
					});
				}
			}
			syncDuration();
			return;
		}

		activeSrc = src;
		a.src = src;
		a.load();
		syncDuration();

		var playPromise = a.play();
		if (playPromise && typeof playPromise.then === 'function') {
			playPromise.then(function () {
				syncToggle(parts, true);
			}).catch(function () {
				syncToggle(parts, false);
			});
		} else {
			syncToggle(parts, true);
		}
	}

	function togglePlay() {
		var parts = ui();
		var a = getAudio();
		if (!parts || !a.src) {
			return;
		}
		if (a.paused) {
			var playPromise = a.play();
			if (playPromise && typeof playPromise.catch === 'function') {
				playPromise.catch(function () {
					syncToggle(parts, false);
				});
			}
		} else {
			a.pause();
		}
	}

	function stopPlayback() {
		var parts = ui();
		var a = getAudio();
		a.pause();
		try {
			a.currentTime = 0;
		} catch (e) {
			// ignore seek errors before metadata
		}
		syncToggle(parts, false);
		onTimeUpdate();
	}

	function hardReset() {
		var a = getAudio();
		a.pause();
		a.removeAttribute('src');
		a.load();
		activeSrc = null;
		setRowActive(null);
		var parts = ui();
		if (parts) {
			syncToggle(parts, false);
			if (parts.seek) {
				parts.seek.value = '0';
				parts.seek.max = '0';
				parts.seek.disabled = true;
			}
			if (parts.current) {
				parts.current.textContent = '0:00';
			}
			if (parts.duration) {
				parts.duration.textContent = '0:00';
			}
			if (parts.title) {
				parts.title.textContent = '';
			}
			if (parts.panel) {
				parts.panel.hidden = true;
			}
		}
	}

	function seekTo(value) {
		var a = getAudio();
		var t = parseFloat(value);
		if (!isFinite(t) || !isFinite(a.duration)) {
			return;
		}
		a.currentTime = Math.max(0, Math.min(t, a.duration));
		var parts = ui();
		if (parts && parts.current) {
			parts.current.textContent = formatTime(a.currentTime);
		}
	}

	document.addEventListener('click', function (event) {
		var playBtn = event.target.closest('[data-stc-ost-play]');
		if (playBtn && playBtn.hasAttribute('data-src')) {
			event.preventDefault();
			loadAndPlay(playBtn);
			return;
		}

		var toggle = event.target.closest('[data-stc-ost-toggle]');
		if (toggle) {
			event.preventDefault();
			togglePlay();
			return;
		}

		var stop = event.target.closest('[data-stc-ost-stop]');
		if (stop) {
			event.preventDefault();
			stopPlayback();
		}
	});

	document.addEventListener('pointerdown', function (event) {
		var seek = event.target.closest('[data-stc-ost-seek]');
		if (seek) {
			seeking = true;
		}
	});

	document.addEventListener('pointerup', function () {
		if (!seeking) {
			return;
		}
		seeking = false;
	});

	document.addEventListener('input', function (event) {
		var seek = event.target.closest('[data-stc-ost-seek]');
		if (!seek) {
			return;
		}
		seeking = true;
		var parts = ui();
		if (parts && parts.current) {
			parts.current.textContent = formatTime(parseFloat(seek.value) || 0);
		}
	});

	document.addEventListener('change', function (event) {
		var seek = event.target.closest('[data-stc-ost-seek]');
		if (!seek) {
			return;
		}
		seekTo(seek.value);
		seeking = false;
	});

	window.addEventListener('pagehide', function () {
		hardReset();
	});
})();
