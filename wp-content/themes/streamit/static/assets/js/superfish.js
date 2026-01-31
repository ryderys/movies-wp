/*!
 * Superfish v1.7.12 (Optimized Build)
 * Lightweight jQuery menu plugin for accessible dropdown navigation
 */
(function ($) {
	'use strict';

	const defaults = {
		delay: 200,
		animation: { opacity: 'show' },
		animationOut: { opacity: 'hide' },
		speed: 'fast',
		speedOut: 'fast',
		cssArrows: true,
		onInit: $.noop,
		onBeforeShow: $.noop,
		onShow: $.noop,
		onHide: $.noop
	};

	$.fn.superfish = function (options) {
		const settings = $.extend({}, defaults, options);

		const toggleMenu = function ($submenu, show) {
			if (show) {
				settings.onBeforeShow.call($submenu);
				$submenu.stop(true, true)
					.animate(settings.animation, settings.speed, () => {
						settings.onShow.call($submenu);
					});
			} else {
				$submenu.stop(true, true)
					.animate(settings.animationOut, settings.speedOut, () => {
						settings.onHide.call($submenu);
					});
			}
		};

		return this.each(function () {
			const $menu = $(this);
			$menu.find('li:has(ul)').each(function () {
				const $li = $(this);
				const $submenu = $li.children('ul');
				$li.hoverIntent
					? $li.hoverIntent({
						over: () => toggleMenu($submenu, true),
						out: () => toggleMenu($submenu, false),
						timeout: settings.delay
					})
					: $li.hover(
						() => toggleMenu($submenu, true),
						() => toggleMenu($submenu, false)
					);
			});
			settings.onInit.call($menu);
		});
	};
})(jQuery);
