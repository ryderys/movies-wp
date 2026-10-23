/**
 * OST download box: one shared Audio element, play/pause per track row.
 */
(function () {
	'use strict';

	var audio = null;
	var activeBtn = null;

	function getAudio() {
		if (!audio) {
			audio = new Audio();
			audio.preload = 'none';
			audio.addEventListener('ended', function () {
				setIdle(activeBtn);
				activeBtn = null;
			});
			audio.addEventListener('pause', function () {
				if (audio && audio.ended) {
					return;
				}
				if (activeBtn && audio.paused) {
					setIdle(activeBtn);
				}
			});
			audio.addEventListener('play', function () {
				if (activeBtn) {
					setPlaying(activeBtn);
				}
			});
			audio.addEventListener('error', function () {
				setIdle(activeBtn);
				activeBtn = null;
			});
		}
		return audio;
	}

	function labelEl(btn) {
		return btn ? btn.querySelector('.stc-dl-ost__play-text') : null;
	}

	function setPlaying(btn) {
		if (!btn) {
			return;
		}
		btn.classList.add('is-playing');
		btn.setAttribute('aria-label', btn.getAttribute('data-aria-pause') || '');
		btn.title = btn.getAttribute('data-aria-pause') || '';
		var text = labelEl(btn);
		if (text) {
			text.textContent = btn.getAttribute('data-label-pause') || 'توقف';
		}
	}

	function setIdle(btn) {
		if (!btn) {
			return;
		}
		btn.classList.remove('is-playing');
		btn.setAttribute('aria-label', btn.getAttribute('data-aria-play') || '');
		btn.title = btn.getAttribute('data-aria-play') || '';
		var text = labelEl(btn);
		if (text) {
			text.textContent = btn.getAttribute('data-label-play') || 'پخش';
		}
	}

	function stopCurrent() {
		var a = getAudio();
		a.pause();
		a.removeAttribute('src');
		a.load();
		setIdle(activeBtn);
		activeBtn = null;
	}

	function playTrack(btn) {
		var src = btn.getAttribute('data-src');
		if (!src) {
			return;
		}

		if (activeBtn && activeBtn !== btn) {
			setIdle(activeBtn);
		}

		var a = getAudio();
		activeBtn = btn;
		a.src = src;
		var playPromise = a.play();
		if (playPromise && typeof playPromise.then === 'function') {
			playPromise.then(function () {
				setPlaying(btn);
			}).catch(function () {
				setIdle(btn);
				activeBtn = null;
			});
		} else {
			setPlaying(btn);
		}
	}

	function onPlayClick(btn) {
		var a = getAudio();
		if (activeBtn === btn && !a.paused) {
			a.pause();
			setIdle(btn);
			return;
		}
		if (activeBtn === btn && a.paused && a.src) {
			var resume = a.play();
			if (resume && typeof resume.then === 'function') {
				resume.then(function () {
					setPlaying(btn);
				}).catch(function () {
					setIdle(btn);
				});
			} else {
				setPlaying(btn);
			}
			return;
		}
		playTrack(btn);
	}

	document.addEventListener('click', function (event) {
		var btn = event.target.closest('[data-stc-ost-play]');
		if (!btn) {
			return;
		}
		event.preventDefault();
		onPlayClick(btn);
	});

	window.addEventListener('pagehide', function () {
		stopCurrent();
	});
})();
