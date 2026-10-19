/**
 * Season-switch request identity for the TV-show episode section.
 *
 * The parent handler sets data-loaded before the request finishes and then
 * ignores every later click. These helpers let only the newest request for a
 * season update that season, and they always clear a loading flag.
 */
(function (root) {
	'use strict';

	function createSeasonSwitchState() {
		return {
			latest: {},
			bound: false
		};
	}

	function beginSeasonRequest(state, seasonKey) {
		var key = String(seasonKey);
		var next = (state.latest[key] || 0) + 1;
		state.latest[key] = next;
		return next;
	}

	function isCurrentSeasonRequest(state, seasonKey, requestId) {
		return state.latest[String(seasonKey)] === requestId;
	}

	function attachSeasonSwitch(target, listener) {
		if (!target || target.__streamitChildSeasonSwitch) {
			return false;
		}
		target.addEventListener('click', listener, true);
		target.__streamitChildSeasonSwitch = true;
		return true;
	}

	function parseEpisodePayload(payload) {
		var data = payload;
		if (typeof data === 'string') {
			try {
				data = JSON.parse(data);
			} catch (error) {
				return { status: 'error', blocks: [] };
			}
		}
		if (!data || data.success !== true || !Array.isArray(data.result) || data.result.length === 0) {
			return { status: 'empty', blocks: [] };
		}
		var blocks = [];
		data.result.forEach(function (item) {
			if (item && typeof item.block_main_content === 'string' && item.block_main_content !== '') {
				blocks.push(item.block_main_content);
			}
		});
		if (blocks.length === 0) {
			return { status: 'empty', blocks: [] };
		}
		return { status: 'ready', blocks: blocks };
	}

	function sliderSettingsForCount(base, count) {
		var settings = JSON.parse(JSON.stringify(base || {}));
		var show = parseInt(settings.slidesToShow, 10);
		if (!show || show < 1) {
			show = 1;
		}
		if (count <= show) {
			settings.infinite = false;
		}
		settings.slidesToShow = Math.min(show, Math.max(count, 1));
		return settings;
	}

	function planSliderReset(wasInitialized) {
		return {
			unslick: !!wasInitialized,
			init: true
		};
	}

	root.streamitChildSeasonSwitch = {
		createSeasonSwitchState: createSeasonSwitchState,
		beginSeasonRequest: beginSeasonRequest,
		isCurrentSeasonRequest: isCurrentSeasonRequest,
		attachSeasonSwitch: attachSeasonSwitch,
		parseEpisodePayload: parseEpisodePayload,
		sliderSettingsForCount: sliderSettingsForCount,
		planSliderReset: planSliderReset
	};
})(typeof globalThis !== 'undefined' ? globalThis : window);
