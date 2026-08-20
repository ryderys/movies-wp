/**
 * Series download UI: season accordion, episode grid, load-more, shared detail panel.
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

	function pageSize(root) {
		var desktop = parseInt(root.getAttribute('data-page-size-desktop') || '24', 10);
		var mobile = parseInt(root.getAttribute('data-page-size-mobile') || '12', 10);
		if (!desktop || desktop < 1) {
			desktop = 24;
		}
		if (!mobile || mobile < 1) {
			mobile = 12;
		}
		return window.matchMedia('(max-width: 576px)').matches ? mobile : desktop;
	}

	function formatEpisodeLabel(i18n, ordinal) {
		var tpl = (i18n && i18n.downloadEpisode) || 'دانلود قسمت %d';
		return tpl.replace('%d', String(ordinal));
	}

	function createEpisodeButton(ep, i18n, detailId) {
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'stc-series-download-ep-btn' + (ep.has_download ? '' : ' is-empty');
		btn.setAttribute('role', 'listitem');
		btn.setAttribute('data-stc-episode-btn', '');
		btn.setAttribute('data-episode-id', String(ep.id));
		btn.setAttribute('aria-expanded', 'false');
		if (detailId) {
			btn.setAttribute('aria-controls', detailId);
		}

		var icon = document.createElement('span');
		icon.className = 'stc-series-download-ep-btn__icon';
		icon.setAttribute('aria-hidden', 'true');
		icon.textContent = '↓';

		var text = document.createElement('span');
		text.className = 'stc-series-download-ep-btn__text';
		text.textContent = formatEpisodeLabel(i18n, ep.ordinal || 0);

		btn.appendChild(icon);
		btn.appendChild(text);
		return btn;
	}

	function clearDetail(detail) {
		detail.hidden = true;
		detail.innerHTML = '';
		detail.removeAttribute('data-active-episode');
	}

	function renderDetail(root, detail, ep, i18n) {
		detail.innerHTML = '';
		detail.setAttribute('data-active-episode', String(ep.id));

		var head = document.createElement('div');
		head.className = 'stc-series-download-detail__head';

		if (ep.label) {
			var code = document.createElement('span');
			code.className = 'stc-series-download-detail__code';
			code.textContent = ep.label;
			head.appendChild(code);
		}

		if (ep.title) {
			var title = document.createElement('span');
			title.className = 'stc-series-download-detail__title';
			title.textContent = ep.title;
			head.appendChild(title);
		}

		detail.appendChild(head);

		var canDownload = root.getAttribute('data-can-download') === '1';
		var hasSources = ep.sources && ep.sources.length;
		var hasSubs = ep.subtitles && ep.subtitles.length;

		if (!ep.has_download || (!hasSources && !hasSubs)) {
			var empty = document.createElement('p');
			empty.className = 'stc-series-download-detail__empty';
			empty.textContent = (i18n && i18n.emptyMedia) || '';
			detail.appendChild(empty);
			detail.hidden = false;
			return;
		}

		if (hasSources) {
			var quals = document.createElement('div');
			quals.className = 'stc-series-download-qualities';
			quals.setAttribute('role', 'list');

			ep.sources.forEach(function (source) {
				var quality = source.quality || '';
				if (!quality) {
					return;
				}
				var chipTitle = source.title || quality;

				if (canDownload && source.href) {
					var a = document.createElement('a');
					a.className = 'stc-series-download-chip';
					a.setAttribute('role', 'listitem');
					a.href = source.href;
					a.title = chipTitle;
					a.textContent = quality;
					quals.appendChild(a);
				} else {
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'stc-series-download-chip';
					b.setAttribute('role', 'listitem');
					b.title = chipTitle;
					b.textContent = quality;
					b.setAttribute('data-bs-toggle', 'modal');
					b.setAttribute('data-bs-target', '#subscribeRequiredModal');
					quals.appendChild(b);
				}
			});

			detail.appendChild(quals);
		}

		if (hasSubs) {
			var subs = document.createElement('div');
			subs.className = 'stc-series-download-subs';

			var label = document.createElement('span');
			label.className = 'stc-series-download-subs__label';
			label.textContent = (i18n && i18n.subtitles) || '';
			subs.appendChild(label);

			var links = document.createElement('div');
			links.className = 'stc-series-download-subs__links';

			ep.subtitles.forEach(function (sub) {
				var subLabel = sub.label || '';
				if (canDownload && sub.href) {
					var sa = document.createElement('a');
					sa.className = 'stc-series-download-sub-link';
					sa.href = sub.href;
					sa.setAttribute('download', '');
					sa.textContent = subLabel;
					links.appendChild(sa);
				} else {
					var sb = document.createElement('button');
					sb.type = 'button';
					sb.className = 'stc-series-download-sub-link';
					sb.textContent = subLabel;
					sb.setAttribute('data-bs-toggle', 'modal');
					sb.setAttribute('data-bs-target', '#subscribeRequiredModal');
					links.appendChild(sb);
				}
			});

			subs.appendChild(links);
			detail.appendChild(subs);
		}

		detail.hidden = false;
	}

	function setSeasonExpanded(season, expanded) {
		var panel = season.querySelector('.stc-series-download-season__panel');
		var toggle = season.querySelector('[data-stc-season-toggle]');
		if (!panel || !toggle) {
			return;
		}

		season.classList.toggle('is-open', expanded);
		panel.hidden = !expanded;
		toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

		if (!expanded) {
			var detail = season.querySelector('[data-stc-episode-detail]');
			if (detail) {
				clearDetail(detail);
			}
			season.querySelectorAll('[data-stc-episode-btn].is-active').forEach(function (btn) {
				btn.classList.remove('is-active');
				btn.setAttribute('aria-expanded', 'false');
			});
		}
	}

	function updateMoreButton(season) {
		var state = season._stcDl;
		if (!state) {
			return;
		}
		var wrap = season.querySelector('[data-stc-more-wrap]');
		if (!wrap) {
			return;
		}
		wrap.hidden = state.visible >= state.episodes.length;
	}

	function renderMoreEpisodes(season, count) {
		var state = season._stcDl;
		if (!state) {
			return;
		}
		var grid = season.querySelector('[data-stc-episode-grid]');
		if (!grid) {
			return;
		}

		var end = Math.min(state.visible + count, state.episodes.length);
		var detailId = state.detailId || '';
		for (var i = state.visible; i < end; i++) {
			grid.appendChild(createEpisodeButton(state.episodes[i], state.i18n, detailId));
		}
		state.visible = end;
		updateMoreButton(season);
	}

	function initSeason(root, season, i18n) {
		var dataEl = season.querySelector('.stc-series-download-season-data');
		var episodes = parseJson(dataEl);
		if (!Array.isArray(episodes)) {
			episodes = [];
		}

		season._stcDl = {
			episodes: episodes,
			visible: 0,
			i18n: i18n,
			byId: {},
			detailId: ''
		};

		episodes.forEach(function (ep) {
			season._stcDl.byId[String(ep.id)] = ep;
		});

		var detail = season.querySelector('[data-stc-episode-detail]');
		var toggle = season.querySelector('[data-stc-season-toggle]');
		if (detail && toggle) {
			var detailId = (toggle.getAttribute('aria-controls') || '') + '-detail';
			detail.id = detailId;
			season._stcDl.detailId = detailId;
		}

		var initial = pageSize(root);
		renderMoreEpisodes(season, initial);
	}

	function onSeasonToggle(root, season) {
		var willOpen = !season.classList.contains('is-open');
		root.querySelectorAll('.stc-series-download-season.is-open').forEach(function (openSeason) {
			if (openSeason !== season) {
				setSeasonExpanded(openSeason, false);
			}
		});
		setSeasonExpanded(season, willOpen);
	}

	function onEpisodeClick(root, season, btn) {
		var state = season._stcDl;
		var detail = season.querySelector('[data-stc-episode-detail]');
		if (!state || !detail) {
			return;
		}

		var id = btn.getAttribute('data-episode-id');
		var ep = state.byId[id];
		if (!ep) {
			return;
		}

		var isActive = btn.classList.contains('is-active');
		season.querySelectorAll('[data-stc-episode-btn].is-active').forEach(function (other) {
			other.classList.remove('is-active');
			other.setAttribute('aria-expanded', 'false');
		});

		if (isActive) {
			clearDetail(detail);
			return;
		}

		btn.classList.add('is-active');
		btn.setAttribute('aria-expanded', 'true');
		btn.setAttribute('aria-controls', detail.id || '');
		renderDetail(root, detail, ep, state.i18n);
	}

	function onShowMore(season) {
		var state = season._stcDl;
		var root = season.closest('[data-stc-series-download]');
		if (!state || !root) {
			return;
		}
		renderMoreEpisodes(season, pageSize(root));
	}

	function initRoot(root) {
		var i18nEl = root.querySelector('.stc-series-download-i18n');
		var i18n = parseJson(i18nEl) || {};

		root.querySelectorAll('.stc-series-download-season').forEach(function (season) {
			initSeason(root, season, i18n);
		});
	}

	function onClick(event) {
		var root = event.target.closest('[data-stc-series-download]');
		if (!root) {
			return;
		}

		var seasonToggle = event.target.closest('[data-stc-season-toggle]');
		if (seasonToggle && root.contains(seasonToggle)) {
			event.preventDefault();
			onSeasonToggle(root, seasonToggle.closest('.stc-series-download-season'));
			return;
		}

		var epBtn = event.target.closest('[data-stc-episode-btn]');
		if (epBtn && root.contains(epBtn)) {
			event.preventDefault();
			onEpisodeClick(root, epBtn.closest('.stc-series-download-season'), epBtn);
			return;
		}

		var moreBtn = event.target.closest('[data-stc-show-more]');
		if (moreBtn && root.contains(moreBtn)) {
			event.preventDefault();
			onShowMore(moreBtn.closest('.stc-series-download-season'));
		}
	}

	document.addEventListener('click', onClick);

	document.querySelectorAll('[data-stc-series-download]').forEach(initRoot);
})();
