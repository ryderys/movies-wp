/**
 * Series download UI: season accordion, quality rows, lazy episode grids, copy-all-links.
 */
(function () {
	'use strict';

	function parseJson(el) {
		if (!el) {
			return null;
		}
		try {
			return JSON.parse(el.textContent || 'null');
		} catch (e) {
			return null;
		}
	}

	function format(tpl, value) {
		return String(tpl || '').replace(/%[sd]/, String(value));
	}

	function pad2(n) {
		n = parseInt(n, 10) || 0;
		return n < 10 ? '0' + n : String(n);
	}

	function rootState(root) {
		if (!root._stcDl) {
			var icons = {};
			root.querySelectorAll('template[data-stc-icon]').forEach(function (tpl) {
				icons[tpl.getAttribute('data-stc-icon')] = tpl;
			});
			root._stcDl = {
				i18n: parseJson(root.querySelector('.stc-dl-i18n')) || {},
				canDownload: root.getAttribute('data-can-download') === '1',
				icons: icons
			};
		}
		return root._stcDl;
	}

	function groupEpisodes(group) {
		var season = group.closest('[data-stc-season]');
		if (!season) {
			return [];
		}
		if (!season._stcGroups) {
			var data = parseJson(season.querySelector('[data-stc-season-data]'));
			season._stcGroups = Array.isArray(data) ? data : [];
		}
		var list = season._stcGroups[parseInt(group.getAttribute('data-stc-group'), 10)];
		return Array.isArray(list) ? list : [];
	}

	function icon(state, name) {
		var span = document.createElement('span');
		span.className = 'stc-dl__icon';
		span.setAttribute('aria-hidden', 'true');
		var tpl = state.icons[name];
		if (tpl && tpl.content) {
			span.appendChild(tpl.content.cloneNode(true));
		}
		return span;
	}

	// Unlocked users get a real link; locked users get the subscribe modal (site convention).
	function actionEl(state, href, className, label, title) {
		var el;
		if (state.canDownload) {
			el = document.createElement('a');
			el.href = href;
		} else {
			el = document.createElement('button');
			el.type = 'button';
			el.setAttribute('data-bs-toggle', 'modal');
			el.setAttribute('data-bs-target', '#subscribeRequiredModal');
		}
		el.className = className;
		el.setAttribute('aria-label', label);
		el.title = title || label;
		return el;
	}

	function createEpisodeItem(state, ep, quality) {
		var i18n = state.i18n;
		var name = format(i18n.episode || 'قسمت %s', pad2(ep.ordinal));
		var suffix = name + (quality ? ' — ' + quality : '');

		var item = document.createElement('div');
		item.className = 'stc-dl-ep';
		item.setAttribute('role', 'listitem');

		var label = document.createElement('span');
		label.className = 'stc-dl-ep__label';
		label.textContent = name;
		var hint = [ep.label, ep.title].filter(Boolean).join(' — ');
		if (hint) {
			label.title = hint;
		}
		item.appendChild(label);

		var actions = document.createElement('div');
		actions.className = 'stc-dl-ep__actions';

		if (quality && (ep.href || !state.canDownload)) {
			var dlLabel = (i18n.download || '') + ' ' + suffix;
			var dl = actionEl(
				state,
				ep.href,
				'btn btn-primary stc-dl-ep__btn',
				dlLabel,
				dlLabel + (ep.file_size ? ' · ' + ep.file_size : '')
			);
			dl.appendChild(icon(state, 'download'));
			actions.appendChild(dl);
		}

		if (ep.play) {
			var play = actionEl(state, ep.play, 'btn btn-secondary border stc-dl-ep__btn', (i18n.play || '') + ' ' + name);
			play.appendChild(icon(state, 'play'));
			actions.appendChild(play);
		}

		(Array.isArray(ep.subtitles) ? ep.subtitles : []).forEach(function (sub) {
			if (state.canDownload && !sub.href) {
				return;
			}
			var subLabel = format(i18n.subtitle || '%s', sub.label || '') + ' — ' + name;
			var el = actionEl(state, sub.href, 'btn btn-secondary border stc-dl-ep__sub', subLabel);
			if (state.canDownload) {
				el.setAttribute('download', '');
			}
			el.textContent = sub.label || 'CC';
			actions.appendChild(el);
		});

		if (!actions.childNodes.length) {
			item.classList.add('is-empty');
			var empty = document.createElement('span');
			empty.className = 'stc-dl-ep__empty';
			empty.textContent = i18n.noMedia || '';
			actions.appendChild(empty);
		}

		item.appendChild(actions);
		return item;
	}

	function buildGrid(root, group) {
		if (group._stcBuilt) {
			return;
		}
		var grid = group.querySelector('[data-stc-episode-grid]');
		if (!grid) {
			return;
		}
		var state = rootState(root);
		var quality = group.getAttribute('data-quality') || '';
		var frag = document.createDocumentFragment();
		groupEpisodes(group).forEach(function (ep) {
			frag.appendChild(createEpisodeItem(state, ep, quality));
		});
		grid.appendChild(frag);
		group._stcBuilt = true;
	}

	function toggleSection(container, toggle, beforeOpen) {
		var panel = document.getElementById(toggle.getAttribute('aria-controls') || '');
		if (!panel) {
			return;
		}
		var expand = toggle.getAttribute('aria-expanded') !== 'true';
		if (expand && beforeOpen) {
			beforeOpen();
		}
		toggle.setAttribute('aria-expanded', expand ? 'true' : 'false');
		panel.hidden = !expand;
		container.classList.toggle('is-open', expand);
	}

	function legacyCopy(text, restoreFocus) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'fixed';
		ta.style.top = '-1000px';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.select();
		var ok = false;
		try {
			ok = document.execCommand('copy');
		} catch (e) {
			ok = false;
		}
		document.body.removeChild(ta);
		if (restoreFocus) {
			restoreFocus.focus();
		}
		return ok;
	}

	function copyText(text, button) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text).then(
				function () {
					return true;
				},
				function () {
					return legacyCopy(text, button);
				}
			);
		}
		return Promise.resolve(legacyCopy(text, button));
	}

	// Site toast (#stToastMessage in the header) when present, inline status otherwise.
	function notify(group, message, ok) {
		var toast = document.getElementById('stToastMessage');
		var body = toast ? (document.getElementById('toastMessage') || toast.querySelector('.toast-body')) : null;
		if (toast && body) {
			body.textContent = message;
			toast.classList.add('show');
			clearTimeout(toast._stcTimer);
			toast._stcTimer = setTimeout(function () {
				toast.classList.remove('show');
			}, 4000);
			return;
		}
		var status = group.querySelector('[data-stc-copy-status]');
		if (status) {
			status.textContent = message;
			status.classList.toggle('is-error', !ok);
		}
	}

	function onCopy(root, group, button) {
		var i18n = rootState(root).i18n;
		var urls = [];
		var seen = {};
		groupEpisodes(group).forEach(function (ep) {
			var href = String(ep.href || '').trim();
			if (/^https?:\/\/\S+$/i.test(href) && !seen[href]) {
				seen[href] = true;
				urls.push(href);
			}
		});

		if (!urls.length) {
			notify(group, i18n.noLinks || '', false);
			return;
		}

		button.disabled = true;
		copyText(urls.join('\n'), button).then(
			function (ok) {
				button.disabled = false;
				notify(group, ok ? format(i18n.copied, urls.length) : i18n.copyFailed || '', ok);
			},
			function () {
				button.disabled = false;
				notify(group, i18n.copyFailed || '', false);
			}
		);
	}

	function onClick(event) {
		var root = event.target.closest('[data-stc-series-download]');
		if (!root) {
			return;
		}

		var seasonToggle = event.target.closest('[data-stc-season-toggle]');
		if (seasonToggle) {
			event.preventDefault();
			toggleSection(seasonToggle.closest('[data-stc-season]'), seasonToggle);
			return;
		}

		var qualityToggle = event.target.closest('[data-stc-quality-toggle]');
		if (qualityToggle) {
			event.preventDefault();
			var group = qualityToggle.closest('[data-stc-group]');
			toggleSection(group, qualityToggle, function () {
				buildGrid(root, group);
			});
			return;
		}

		var copyBtn = event.target.closest('[data-stc-copy]');
		if (copyBtn) {
			event.preventDefault();
			onCopy(root, copyBtn.closest('[data-stc-group]'), copyBtn);
		}
	}

	document.addEventListener('click', onClick);
})();
