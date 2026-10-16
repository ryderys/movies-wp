(function () {
	'use strict';

	var cfg = window.moviesWpTrailerUpload || {};
	var root = document.getElementById('movies-wp-trailer-upload-root');
	var field = document.getElementById('_name_trailer_link_field');
	var linkInput = field ? field.querySelector('input[name="_name_trailer_link"]') : null;
	if (!root || !cfg.ajaxUrl || !field || !linkInput) {
		return;
	}

	linkInput.insertAdjacentElement('afterend', root);
	root.hidden = false;

	var fileInput = document.getElementById('movies-wp-trailer-file');
	var btn = document.getElementById('movies-wp-trailer-upload-btn');
	var statusEl = document.getElementById('movies-wp-trailer-status');
	var progressWrap = root.querySelector('.movies-wp-trailer-progress');
	var progressBar = document.getElementById('movies-wp-trailer-progress-bar');
	var progressLabel = document.getElementById('movies-wp-trailer-progress-label');
	var replacePanel = document.getElementById('movies-wp-trailer-replace-panel');
	var confirmBox = document.getElementById('movies-wp-trailer-confirm-replace');
	var i18n = cfg.i18n || {};
	var maxBytes = parseInt(root.getAttribute('data-max-bytes') || '0', 10) || 0;
	var hasTrailer = root.getAttribute('data-has-trailer') === '1';
	var postId = root.getAttribute('data-post-id') || '';
	var contentType = root.getAttribute('data-content-type') || 'movie';

	function setStatus(message, isError) {
		if (!statusEl) {
			return;
		}
		statusEl.textContent = message || '';
		var state = '';
		if (isError) {
			state = ' is-error';
		} else if (message) {
			state = ' is-ok';
		}
		statusEl.className = 'description movies-wp-trailer-status' + state;
	}

	function setProgress(pct) {
		if (!progressWrap || !progressBar || !progressLabel) {
			return;
		}
		progressWrap.hidden = false;
		progressBar.value = pct;
		progressLabel.textContent = pct + '%';
	}

	function resetProgress() {
		if (!progressWrap || !progressBar || !progressLabel) {
			return;
		}
		progressBar.value = 0;
		progressLabel.textContent = '0%';
		progressWrap.hidden = true;
	}

	function markTrailerPresent(path) {
		hasTrailer = true;
		root.setAttribute('data-has-trailer', '1');
		if (path) {
			linkInput.value = path;
			linkInput.dispatchEvent(new Event('input', { bubbles: true }));
			linkInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
		if (replacePanel) {
			replacePanel.hidden = false;
		}
		confirmBox = document.getElementById('movies-wp-trailer-confirm-replace');
		if (confirmBox) {
			confirmBox.checked = false;
		}
	}

	if (!btn || !fileInput) {
		return;
	}

	btn.addEventListener('click', function () {
		var file = fileInput.files && fileInput.files[0];
		if (!file) {
			setStatus(i18n.selectFile || 'Choose a file.', true);
			return;
		}

		if (maxBytes > 0 && file.size > maxBytes) {
			setStatus(i18n.tooLarge || 'File too large.', true);
			return;
		}

		confirmBox = document.getElementById('movies-wp-trailer-confirm-replace');
		if (hasTrailer && (!confirmBox || !confirmBox.checked)) {
			setStatus(i18n.confirmRequired || 'Confirm replacement.', true);
			return;
		}

		var form = new FormData();
		form.append('action', cfg.action || 'movies_wp_upload_trailer');
		form.append('nonce', cfg.nonce || '');
		form.append('post_id', postId);
		form.append('content_type', contentType);
		form.append('confirm_replace', hasTrailer && confirmBox && confirmBox.checked ? '1' : '0');
		form.append('trailer', file, file.name);

		btn.disabled = true;
		fileInput.disabled = true;
		setStatus(i18n.uploading || 'Uploading…', false);
		setProgress(0);

		var xhr = new XMLHttpRequest();
		xhr.open('POST', cfg.ajaxUrl, true);
		xhr.responseType = 'json';

		xhr.upload.onprogress = function (ev) {
			if (!ev.lengthComputable) {
				return;
			}
			var pct = Math.min(99, Math.round((ev.loaded / ev.total) * 100));
			setProgress(pct);
		};

		xhr.onload = function () {
			btn.disabled = false;
			fileInput.disabled = false;
			var payload = xhr.response;
			if (!payload || typeof payload !== 'object') {
				try {
					payload = JSON.parse(xhr.responseText || '{}');
				} catch (e) {
					payload = null;
				}
			}

			if (payload && payload.success) {
				resetProgress();
				var path = payload.data && payload.data.path ? String(payload.data.path) : '';
				setStatus((payload.data && payload.data.message) || i18n.success || 'OK', false);
				markTrailerPresent(path);
				return;
			}

			resetProgress();
			var errMsg = i18n.genericError || 'Upload failed.';
			if (payload && payload.data && payload.data.message) {
				errMsg = String(payload.data.message);
			}
			setStatus(errMsg, true);
		};

		xhr.onerror = function () {
			btn.disabled = false;
			fileInput.disabled = false;
			resetProgress();
			setStatus(i18n.genericError || 'Upload failed.', true);
		};

		xhr.send(form);
	});
})();
