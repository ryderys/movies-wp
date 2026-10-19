/**
 * Detail-page season tabs. Capture-phase so the parent GetSeasonEpisodes
 * lock never runs. Each season keeps its own request id.
 */
(function ($) {
	'use strict';

	var api = window.streamitChildSeasonSwitch;
	if (!api || !$) {
		return;
	}

	var state = api.createSeasonSwitchState();

	function seasonButton(target) {
		return target && target.closest ? target.closest('.episode-section .css_prefix-change-season') : null;
	}

	function showPane(button) {
		var href = button.getAttribute('href') || '';
		if (href.charAt(0) !== '#') {
			return null;
		}
		var pane = document.querySelector(href);
		if (!pane) {
			return null;
		}
		var list = button.closest('.episode-nav-btn');
		if (list) {
			list.querySelectorAll('.css_prefix-change-season').forEach(function (link) {
				link.classList.remove('active');
				link.setAttribute('aria-selected', 'false');
			});
		}
		button.classList.add('active');
		button.setAttribute('aria-selected', 'true');
		var content = pane.parentElement;
		if (content) {
			content.querySelectorAll(':scope > .tab-pane').forEach(function (item) {
				item.classList.remove('active', 'show');
			});
		}
		pane.classList.add('active', 'show');
		return pane;
	}

	function sliderOf(pane) {
		return $(pane).find('.css_prefix-slick-general').first();
	}

	function destroySlider($slider) {
		if ($slider.length && $slider.hasClass('slick-initialized') && $.fn.slick) {
			$slider.slick('unslick');
		}
	}

	function readSettings($slider) {
		var settings = $slider.data('slider_settings');
		if (!settings || typeof settings !== 'object') {
			settings = {
				slidesToShow: 5,
				slidesToScroll: 1,
				infinite: true,
				arrows: true,
				dots: false,
				draggable: true
			};
		}
		return settings;
	}

	function paint($slider, blocks) {
		var plan = api.planSliderReset($slider.hasClass('slick-initialized'));
		if (plan.unslick) {
			destroySlider($slider);
		}
		$slider.empty().removeClass('st-skeleton');
		blocks.forEach(function (html) {
			$slider.append('<div class="slick-item"><div class="episode-card">' + html + '</div></div>');
		});
		if (!plan.init || !$slider.slick) {
			return;
		}
		var settings = api.sliderSettingsForCount(readSettings($slider), blocks.length);
		settings.rtl = document.documentElement.getAttribute('dir') === 'rtl';
		if ($slider.data('extra_settings') === true || $slider.attr('data-extra_settings') === 'true') {
			settings.nextArrow = '<button class="NextArrow-two"><i class="icon-arrow-right"></i></button>';
			settings.prevArrow = '<button class="PreArrow-two"><i class="icon-arrow-left"></i></button>';
		}
		$slider.slick(settings);
	}

	function finish($slider, status) {
		destroySlider($slider);
		$slider.empty().removeClass('st-skeleton');
		var message = status === 'error' ? 'Error loading episodes.' : 'No Episode Available';
		$slider.append('<p class="no_data_found">' + message + '</p>');
	}

	function loadSeason(button, pane) {
		var seasonKey = button.getAttribute('data-season_no');
		var requestId = api.beginSeasonRequest(state, seasonKey);
		var $slider = sliderOf(pane);
		if (!$slider.length) {
			return;
		}
		$slider.addClass('st-skeleton');
		var episodes = $(button).data('episodes');
		var isSlider = $(button).data('is-slider');
		var asSlider = isSlider === undefined ? true : isSlider !== false;

		$.ajax({
			url: window.stAjax.ajaxurl,
			type: 'POST',
			data: {
				action: 'st_ajax_post',
				_ajax_nonce: window.stAjax._ajax_nonce,
				route_name: 'tvshow_tab_seasons_data',
				data: episodes,
				season: seasonKey,
				is_slider: asSlider
			}
		}).done(function (res) {
			if (!api.isCurrentSeasonRequest(state, seasonKey, requestId)) {
				return;
			}
			var parsed = api.parseEpisodePayload(res);
			if (parsed.status === 'ready') {
				paint($slider, parsed.blocks);
				return;
			}
			finish($slider, parsed.status);
		}).fail(function () {
			if (!api.isCurrentSeasonRequest(state, seasonKey, requestId)) {
				return;
			}
			finish($slider, 'error');
		});
	}

	function onClick(event) {
		var button = seasonButton(event.target);
		if (!button) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		var pane = showPane(button);
		if (!pane) {
			return;
		}
		loadSeason(button, pane);
	}

	api.attachSeasonSwitch(document, onClick);
})(window.jQuery);
