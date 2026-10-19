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
	var listEl = document.getElementById('movies-wp-trailer-list');
	var i18n = cfg.i18n || {};
	var maxBytes = parseInt(root.getAttribute('data-max-bytes') || '0', 10) || 0;
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

	function rememberPath(path) {
		if (!path) {
			return;
		}
		if (linkInput && !String(linkInput.value || '').trim()) {
			linkInput.value = path;
			linkInput.dispatchEvent(new Event('input', { bubbles: true }));
			linkInput.dispatchEvent(new Event('change', { bubbles: true }));
		}
		if (!listEl) {
			return;
		}
		var items = listEl.querySelectorAll('li');
		var i;
		for (i = 0; i < items.length; i++) {
			if (items[i].textContent === path) {
				return;
			}
		}
		var item = document.createElement('li');
		item.textContent = path;
		listEl.appendChild(item);
	}

	function uploadOne(file) {
		return new Promise(function (resolve, reject) {
			var form = new FormData();
			form.append('action', cfg.action || 'movies_wp_upload_trailer');
			form.append('nonce', cfg.nonce || '');
			form.append('post_id', postId);
			form.append('content_type', contentType);
			form.append('trailer', file, file.name);

			var xhr = new XMLHttpRequest();
			xhr.open('POST', cfg.ajaxUrl, true);
			xhr.responseType = 'json';
			xhr.upload.onprogress = function (ev) {
				if (!ev.lengthComputable) {
					return;
				}
				setProgress(Math.min(99, Math.round((ev.loaded / ev.total) * 100)));
			};
			xhr.onload = function () {
				var payload = xhr.response;
				if (!payload || typeof payload !== 'object') {
					try {
						payload = JSON.parse(xhr.responseText || '{}');
					} catch (e) {
						payload = null;
					}
				}
				if (payload && payload.success) {
					rememberPath(payload.data && payload.data.path ? String(payload.data.path) : '');
					resolve(payload);
					return;
				}
				var errMsg = i18n.genericError || 'Upload failed.';
				if (payload && payload.data && payload.data.message) {
					errMsg = String(payload.data.message);
				}
				reject(new Error(errMsg));
			};
			xhr.onerror = function () {
				reject(new Error(i18n.genericError || 'Upload failed.'));
			};
			xhr.send(form);
		});
	}

	if (!btn || !fileInput) {
		return;
	}

	btn.addEventListener('click', function () {
		var files = fileInput.files ? Array.prototype.slice.call(fileInput.files) : [];
		if (!files.length) {
			setStatus(i18n.selectFile || 'Choose a file.', true);
			return;
		}

		var tooLarge = files.some(function (file) {
			return maxBytes > 0 && file.size > maxBytes;
		});
		if (tooLarge) {
			setStatus(i18n.tooLarge || 'File too large.', true);
			return;
		}

		btn.disabled = true;
		fileInput.disabled = true;
		setStatus(i18n.uploading || 'Uploading…', false);
		setProgress(0);

		files.reduce(function (chain, file) {
			return chain.then(function () {
				resetProgress();
				setStatus((i18n.uploading || 'Uploading…') + ' ' + file.name, false);
				return uploadOne(file);
			});
		}, Promise.resolve()).then(function () {
			btn.disabled = false;
			fileInput.disabled = false;
			fileInput.value = '';
			resetProgress();
			setStatus(i18n.success || 'OK', false);
		}).catch(function (err) {
			btn.disabled = false;
			fileInput.disabled = false;
			resetProgress();
			setStatus(err && err.message ? err.message : (i18n.genericError || 'Upload failed.'), true);
		});
	});
})();
