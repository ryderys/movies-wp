import { post } from "../../../../static/assets/utilities/ajax.js";
import './jquery-counter.js';
import './elementor-control-ajax.js';
import bootstrapcomponent from './../../../../static/assets/js/bootstrap-component.js';

/**
 * Optimized Slick slider handler with performance improvements
 */
class SlickGeneral {
    constructor() {
        this.bootstrap = new bootstrapcomponent();
        this.cache = new Map();
        this.debounceTimers = new Map();
        this.rafIds = new Map();

        // Configuration constants - using regular properties instead of private fields
        this.config = {
            selectors: {
                episodeSlider: '.init-episode-slider',
                changeSeason: '.css_prefix-change-season',
                continueWatching: '.continue_watch_empty',
                themeDirection: 'input[name="theme_scheme_direction"]',
                slickGeneral: '.css_prefix-slick-general',
                verticalBanner: '.css_prefix-vertical-thumbnail-banner',
                mainBanner: '.css_prefix-main-banner',
                simpleBanner: '.css_prefix-simple-banner',
                tvShowSeason: '.css_prefix-tvshow-season',
                tvShowTab: '.css_prefix-tvshow-tab',
                productBanner: '.css_prefix-product-banner',
                countdown: '.st-count-down',
                hoverEffect: '.display-hover-effect'
            },
            classes: {
                slickInitialized: 'slick-initialized',
                active: 'active',
                first: 'first',
                last: 'last',
                clonedItem: 'cloned-item',
                skeleton: 'st-skeleton'
            },
            defaults: {
                slider: {
                    rtl: document.documentElement.getAttribute('dir') === 'rtl',
                    lazyLoad: 'ondemand',
                    slidesToShow: 4,
                    slidesToScroll: 1,
                    infinite: false,
                    arrows: true,
                    dots: false,
                    draggable: true,
                    nextArrow: '<button class="NextArrow-two"><i class="icon-arrow-right"></i></button>',
                    prevArrow: '<button class="PreArrow-two"><i class="icon-arrow-left"></i></button>',
                    responsive: [
                        { breakpoint: 1368, settings: { slidesToShow: 3 } },
                        { breakpoint: 1025, settings: { slidesToShow: 3 } },
                        { breakpoint: 993, settings: { slidesToShow: 2 } },
                        { breakpoint: 577, settings: { slidesToShow: 1 } }
                    ]
                }
            }
        };

        try {
            this.init();
        } catch (error) {
            this.handleError(error, 'Constructor initialization failed');
        }
    }

    init() {
        this.initializeComponents();
        this.bindEvents();
    }

    initializeComponents() {
        this.measurePerformance('init', () => {
            this.sliderElement('frontend');
            this.sliderElement('editor');
            this.sliderDetailsPage();

            if (this.getElement('.upcoming-data-container')) {
                this.UpcomingDataTimer(this.getElement('.upcoming-data-container'));
            }

            this.setInitialDirection();
        });
    }

    bindEvents() {
        const { body } = document;
        const { selectors } = this.config;

        // Debounced event handlers
        const debouncedHoverEffect = this.debounce(() => this.hoverEffect(), 250);
        jQuery(window).on('resize', debouncedHoverEffect);

        // Event delegation with optimized selectors - using arrow functions to maintain context
        jQuery(body)
            .on('change', selectors.themeDirection, (event) => this.handleDirectionChange(event))
            .on('click', selectors.episodeSlider, (event) => this.handleEpisodeSlider(event))
            .on('click', selectors.changeSeason, (event) => this.handleSeasonChange(event))
            .on('click', selectors.continueWatching, (event) => this.handleRemoveContinueWatching(event));
    }

    handleDirectionChange(event) {
        try {
            const selectedDir = jQuery(event.target).val();
            this.bootstrap.closeVisibleOffcanvas('#rtl-switcher-options');
            this.updateDirection(selectedDir);
        } catch (error) {
            this.handleError(error, 'Direction change handling failed');
        }
    }

    handleEpisodeSlider(event) {
        this.InitEpisodeSlider(event);
    }

    handleSeasonChange(event) {
        this.GetSeasonEpisodes(event);
    }

    handleRemoveContinueWatching(event) {
        this.RemoveContinueWatching(event);
    }

    updateDirection(direction) {
        try {
            // Update HTML direction
            document.documentElement.setAttribute('dir', direction);

            // Save preference
            this.setCookie('theme_scheme_direction', direction, 7);

            // Update UI
            this.toggleActiveDirection(direction);

            // Reinitialize sliders
            this.reinitializeAllSliders();
            this.reinitsliderDetailsPage();
        } catch (error) {
            this.handleError(error, 'Failed to update direction');
        }
    }

    toggleActiveDirection(direction) {
        const activeDir = `#theme-scheme-direction-${direction}`;
        const inactiveDir = `#theme-scheme-direction-${direction === 'ltr' ? 'rtl' : 'ltr'}`;

        jQuery(activeDir).addClass(this.config.classes.active);
        jQuery(inactiveDir).removeClass(this.config.classes.active);
    }

    debounce(func, wait) {
        return (...args) => {
            const key = func.name || 'anonymous';
            clearTimeout(this.debounceTimers.get(key));
            this.debounceTimers.set(key, setTimeout(() => func.apply(this, args), wait));
        };
    }

    measurePerformance(label, fn) {
        if (typeof performance === 'undefined') {
            fn();
            return;
        }

        const start = performance.now();
        fn();
        const duration = performance.now() - start;

        if (duration > 100) { // Log only slow operations
            // console.warn(`Performance: ${label} took ${duration.toFixed(2)}ms`);
        }
    }

    handleError(error, context) {
        console.error(`SlickGeneral Error: ${context}`, error);
    }

    setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 864e5));
        document.cookie = `${name}=${value}; expires=${date.toUTCString()}; path=/`;
    }

    getCookie(name) {
        return document.cookie.split('; ').reduce((acc, cookie) => {
            const [key, val] = cookie.split('=');
            return key === name ? val : acc;
        }, null);
    }

    getElement(selector, context = document) {
        const cacheKey = `element-${selector}`;
        if (!this.cache.has(cacheKey)) {
            this.cache.set(cacheKey, context.querySelector(selector));
        }
        return this.cache.get(cacheKey);
    }

    getElements(selector, context = document) {
        const cacheKey = `elements-${selector}`;
        if (!this.cache.has(cacheKey)) {
            this.cache.set(cacheKey, context.querySelectorAll(selector));
        }
        return this.cache.get(cacheKey);
    }

    // Public methods
    setInitialDirection() {
        const htmlDir = document.documentElement.getAttribute('dir') || 'ltr';
        const savedDir = this.getCookie('theme_scheme_direction') || htmlDir;
        this.toggleActiveDirection(savedDir);
    }

    sliderElement(mode) {
        this.measurePerformance(`sliderElement-${mode}`, () => {
            const initEvent = mode === 'frontend' ? 'elementor/frontend/init' : 'elementor/editor/init';
            const hook = 'frontend/element_ready/widget';

            if (window.addEventListener) {
                window.addEventListener(initEvent, () => {
                    if (mode === 'editor' && window.elementor) {
                        elementor.on('preview:loaded', () => {
                            this.addElementorHook(hook);
                        });
                    } else {
                        this.addElementorHook(hook);
                    }
                });
            }
        });
    }

    addElementorHook(hook) {
        const sliderConfigs = [
            { selector: '.css_prefix-slick-general', initMethod: (el) => this.initSlickSlider(el) },
            { selector: '.css_prefix-vertical-thumbnail-banner', initMethod: (el) => this.initVTBannerSlider(el) },
            { selector: '.css_prefix-main-banner', initMethod: (el) => this.initBannerSlider(el) },
            { selector: '.css_prefix-simple-banner', initMethod: (el) => this.initSlickSlider(el) },
            { selector: '.css_prefix-tvshow-season', initMethod: (el) => this.initTvShowSeason(el), extraMethods: [(el) => this.ajaxChangeSeason(el)] },
            { selector: '.css_prefix-tvshow-tab', initMethod: (el) => this.tvShowTabSlider(el), extraMethods: [(el) => this.tvShowSeasonChange(el)] },
            { selector: '.css_prefix-product-banner', initMethod: (el) => this.initSlickSlider(el) },
            { selector: '.st-count-down', initMethod: (el) => this.callTimer(el) }
        ];

        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction(hook, ($scope) => {
                sliderConfigs.forEach(({ selector, initMethod, extraMethods = [] }) => {
                    const elements = $scope.find(selector);
                    this.initializeSlider(elements, initMethod, extraMethods);
                });
            });
        }
    }

    initializeSlider(elements, initMethod, extraMethods = []) {
        if (elements.length > 0 && !elements.hasClass(this.config.classes.slickInitialized)) {
            initMethod(elements);
            extraMethods.forEach(method => method(elements));
        }
    }

    sliderDetailsPage() {
        const sliders = this.getElements('.single_page_slick');
        sliders.forEach(element => {
            const sliderElement = jQuery(element).find('.css_prefix-slick-general');
            if (sliderElement.length > 0 && !sliderElement.hasClass(this.config.classes.slickInitialized)) {
                this.initSlickSlider(sliderElement);
            }
        });
    }

    reinitsliderDetailsPage() {
        const sliders = this.getElements('.single_page_slick');
        sliders.forEach(element => {
            const sliderElement = jQuery(element).find('.css_prefix-slick-general');
            if (sliderElement.length > 0) {
                if (sliderElement.hasClass(this.config.classes.slickInitialized)) {
                    sliderElement.slick('unslick');
                }
                this.initSlickSlider(sliderElement);
            }
        });
    }

    reinitializeAllSliders() {
        const sliderTypes = [
            { selector: '.css_prefix-slick-general', initMethod: (el) => this.initSlickSlider(el) },
            { selector: '.css_prefix-vertical-thumbnail-banner', initMethod: (el) => this.initVTBannerSlider(el) },
            { selector: '.css_prefix-main-banner', initMethod: (el) => this.initBannerSlider(el) },
            { selector: '.css_prefix-simple-banner', initMethod: (el) => this.initSlickSlider(el) },
            { selector: '.css_prefix-tvshow-season', initMethod: (el) => this.initTvShowSeason(el), extraMethods: [(el) => this.ajaxChangeSeason(el)] },
            { selector: '.css_prefix-tvshow-tab', initMethod: (el) => this.tvShowTabSlider(el), extraMethods: [(el) => this.tvShowSeasonChange(el)] },
            { selector: '.css_prefix-product-banner', initMethod: (el) => this.initSlickSlider(el) },
            { selector: '.st-count-down', initMethod: (el) => this.callTimer(el) }
        ];

        sliderTypes.forEach(({ selector, initMethod, extraMethods = [] }) => {
            const elements = jQuery(selector);
            elements.each((index, element) => {
                const $element = jQuery(element);
                if ($element.hasClass(this.config.classes.slickInitialized)) {
                    $element.slick('unslick');
                }
                this.initializeSlider($element, initMethod, extraMethods);
            });
        });
    }

    // Slick slider initialization methods
    initSlickSlider(sliderElement) {
        if (!sliderElement.hasClass(this.config.classes.slickInitialized)) {
            const isRtl = document.documentElement.getAttribute('dir') === 'rtl';
            let slickSettings = sliderElement.data('slider_settings') || { ...this.config.defaults.slider };

            slickSettings.lazyLoad = 'ondemand';
            slickSettings.rtl = isRtl;

            const singleSliderSettings = sliderElement.data('slingle_slider_settings');
            if (singleSliderSettings) {
                slickSettings = { ...slickSettings, ...singleSliderSettings };
            }

            const extraSettings = sliderElement.data('extra_settings');
            if (extraSettings === true) {
                slickSettings.nextArrow = this.config.defaults.slider.nextArrow;
                slickSettings.prevArrow = this.config.defaults.slider.prevArrow;
            }

            if (slickSettings) {
                sliderElement.slick(slickSettings);
                this.updateFirstLastClasses(sliderElement);

                sliderElement.on('afterChange', () => {
                    this.updateFirstLastClasses(sliderElement);
                });
            }
        }
    }

    updateFirstLastClasses(sliderElement) {
        const slideItems = sliderElement.find('.slick-slide');
        const activeSlides = sliderElement.find('.slick-active');

        slideItems.removeClass('first last');
        activeSlides.first().addClass('first');
        activeSlides.last().addClass('last');
    }

    initVTBannerSlider(vtbanner) {
        const isRtl = document.documentElement.getAttribute('dir') === 'rtl';
        const $this = jQuery(vtbanner);
        const $content = $this.find('.vertical-banner-content');
        const $thumb = $this.find('.vertical-banner-thumb');

        // Clean up existing sliders
        [$content, $thumb].forEach($el => {
            if ($el.hasClass(this.config.classes.slickInitialized)) {
                $el.slick('unslick');
            }
        });

        const contentId = '#' + $content.attr('data-rand');
        const thumbId = '#' + $thumb.attr('data-rand');
        const $arrowForVertical = $this.find('.vertical-banner-thumb-wrapper');
        const $arrowParent = $this.find('.vertical-banner-content');

        const arrowConfig = {
            nextArrow: this.config.defaults.slider.nextArrow,
            prevArrow: this.config.defaults.slider.prevArrow
        };

        // Main content slider
        jQuery(contentId).slick({
            rtl: isRtl,
            slidesToShow: 1,
            arrows: true,
            fade: true,
            lazyLoad: 'ondemand',
            asNavFor: thumbId,
            appendArrows: $arrowForVertical,
            ...arrowConfig,
            responsive: [{
                breakpoint: 992,
                settings: {
                    asNavFor: false,
                    ...arrowConfig
                }
            }]
        });

        // Thumbnail slider
        jQuery(thumbId).slick({
            slidesToShow: 3,
            asNavFor: contentId,
            dots: false,
            arrows: true,
            infinite: true,
            vertical: true,
            verticalSwiping: true,
            centerMode: true,
            lazyLoad: 'ondemand',
            appendArrows: $arrowParent,
            ...arrowConfig,
            focusOnSelect: true,
        });

        // Force position update
        setTimeout(() => {
            jQuery(contentId).slick('setPosition');
            jQuery(thumbId).slick('setPosition');
        }, 300);
    }

    initBannerSlider(bannerSlider) {
        const isRtl = document.documentElement.getAttribute('dir') === 'rtl';
        const $this = bannerSlider;
        const mainSliderSelector = '#' + $this.attr('id');
        const $thumbSliderNav = $this.closest('.st-main-slider').find('.banner-thumb-slider-nav');
        const thumbSliderNavSelector = '#' + $thumbSliderNav.data('rand');

        // Clean up existing sliders
        [mainSliderSelector, thumbSliderNavSelector].forEach(selector => {
            const $el = jQuery(selector);
            if ($el.hasClass(this.config.classes.slickInitialized)) {
                $el.slick('unslick');
            }
        });

        const slickSettings = $this.data('slider_settings') || {};
        const slickChildSettings = $this.data('slick_child_settings') || {};

        const arrowConfig = {
            nextArrow: this.config.defaults.slider.nextArrow,
            prevArrow: this.config.defaults.slider.prevArrow
        };

        // Main slider
        slickSettings.asNavFor = thumbSliderNavSelector;
        slickSettings.rtl = isRtl;
        slickSettings.lazyLoad = 'ondemand';

        // Thumbnail slider
        slickChildSettings.asNavFor = mainSliderSelector;
        slickChildSettings.rtl = isRtl;
        slickChildSettings.lazyLoad = 'ondemand';
        Object.assign(slickChildSettings, arrowConfig);

        if (jQuery(mainSliderSelector).length > 0) {
            jQuery(mainSliderSelector).slick(slickSettings);
        }

        if (jQuery(thumbSliderNavSelector).length > 0) {
            jQuery(thumbSliderNavSelector).slick(slickChildSettings);
        }
    }

    // TV Show methods (optimized versions)
    tvShowSeasonChange() {
        const isRtl = document.documentElement.getAttribute('dir') === 'rtl';

        jQuery(document.body).on('change', '.season-select', (event) => {
            const $btn = jQuery(event.currentTarget);
            const episode = $btn.parents('.trending-custom-tab').find('.css_prefix-tv_show-episodes').data('episodes');
            const season_no = $btn.val();
            const target = $btn.parents('.trending-custom-tab').find('.episodes-contens');

            // Check if content already loaded
            if (target.children(`[data-display="${season_no}"]`).length) {
                jQuery('.episodes-slider').hide();
                jQuery(`.episodes-slider[data-display="${season_no}"]`).show();
                return;
            }

            const episodeCount = episode[season_no]?.episodes?.length || 0;
            const sliderHTML = `<div class="episodes-slider slick-slider-tvshow-tab ajax-slick-load" data-display="${season_no}">
                ${'<div class="episode-item animated fadeInUp ajax"><div class="episode-card"></div></div>'.repeat(episodeCount)}
            </div>`;

            target.append(sliderHTML);
            jQuery('.episodes-slider').hide();
            jQuery(`.episodes-slider[data-display="${season_no}"]`).show();

            post('tvshow_tab_seasons_data', { data: episode, season: season_no, is_slider: true })
                .then(res => {
                    const jsonRes = JSON.parse(res);
                    if (jsonRes.success) {
                        const $slider = target.find(`.episodes-slider[data-display="${season_no}"]`);
                        this.initializeTvShowSlider($slider, isRtl);
                        const length = this.populateSliderContent($slider, jsonRes);
                        this.addRemainingEpisodes($slider, jsonRes, length);
                    }
                });
        });

        jQuery(document.body).on('click', '.css_prefix-tv_show-episodes', (event) => {
            const $btn = jQuery(event.currentTarget);
            const season_no = 0;
            const episode = $btn.data('episodes');
            const target = $btn.closest('.trending-custom-tab').find('.episodes-contens');
            const $sliderTarget = target.find('.episodes-slider');

            post('tvshow_tab_seasons_data', { data: episode, season: season_no, is_slider: true })
                .then(res => {
                    const jsonRes = JSON.parse(res);
                    if (jsonRes.success) {
                        this.initializeTvShowSlider($sliderTarget, isRtl);
                        const length = this.populateSliderContent($sliderTarget, jsonRes);
                        this.addRemainingEpisodes($sliderTarget, jsonRes, length);
                    }
                });
        });
    }

    initializeTvShowSlider($sliderTarget, isRtl) {
        if (!$sliderTarget.hasClass('slick-initialized')) {
            $sliderTarget.slick({
                rtl: isRtl,
                slidesToShow: 4,
                slidesToScroll: 1,
                infinite: false,
                arrows: false,
                dots: true,
                draggable: true,
                nextArrow: '<button class="NextArrow-two"><i class="icon-arrow-right"></i></button>',
                prevArrow: '<button class="PrevArrow-two"><i class="icon-arrow-left"></i></button>',
                responsive: [
                    { breakpoint: 1368, settings: { slidesToShow: 3 } },
                    { breakpoint: 1025, settings: { slidesToShow: 3 } },
                    { breakpoint: 993, settings: { slidesToShow: 2 } },
                    { breakpoint: 577, settings: { slidesToShow: 1 } }
                ],
            });
        }
    }

    populateSliderContent($sliderSelector, jsonRes) {
        let length = 0;
        $sliderSelector.find('.slick-slide').each((key, value) => {
            const $slide = jQuery(value);
            if (typeof jsonRes.result[key] === 'undefined') {
                $slide.remove();
                return;
            }
            $slide.find('.episode-card').html(jsonRes.result[key].block_main_content)
                .removeClass('skeleton').addClass('animate');
            length = key + 1;
        });
        return length;
    }

    addRemainingEpisodes($sliderSelector, jsonRes, startIndex) {
        for (let index = startIndex; index < jsonRes.result.length; index++) {
            const element = jsonRes.result[index];
            const $tempElement = jQuery('<div>').html(element.block_main_content);
            const blockImg = $tempElement.find('.episode-image').html();
            const blockContent = $tempElement.find('.episode-detail').html();
            const $clonedItem = $sliderSelector.find('.episode-item').last().clone(true, true);

            $clonedItem.find('.episode-image').empty().append(blockImg);
            $clonedItem.find('.episode-detail').empty().append(blockContent);
            $sliderSelector.slick('slickAdd', $clonedItem);
        }
    }

    // TV Show Tab Slider - Fixed version
    tvShowTabSlider(args = {}) {
        const isRtl = document.documentElement.getAttribute('dir') === 'rtl';

        jQuery('.css_prefix-tvshow-tab').each((index, element) => {
            const $this = jQuery(element);
            const mainID = '#' + $this.find('.trending-slider-tab').attr('data-rand');
            const navID = '#' + $this.find('.trending-slider-tab-nav').attr('data-rand');
            const $main = jQuery(mainID);
            const $nav = jQuery(navID);

            // Clean up
            [$main, $nav].forEach($el => {
                if ($el.hasClass('slick-initialized')) {
                    $el.slick('unslick');
                }
            });

            $main.toggleClass('single-slide', $main.children().length === 1);

            // Main slider
            $main.slick({
                lazyLoad: 'ondemand',
                rtl: isRtl,
                slidesToShow: 1,
                slidesToScroll: 1,
                arrows: true,
                fade: true,
                draggable: true,
                infinite: true,
                swipe: false,
                touchMove: false,
                asNavFor: navID,
                nextArrow: this.config.defaults.slider.nextArrow,
                prevArrow: this.config.defaults.slider.prevArrow
            });

            // Nav slider
            const navCount = $nav.children().length;
            if (navCount) {
                $nav.slick({
                    rtl: isRtl,
                    lazyLoad: 'ondemand',
                    slidesToShow: Math.min(5, navCount),
                    slidesToScroll: 1,
                    asNavFor: mainID,
                    dots: false,
                    arrows: false,
                    focusOnSelect: true,
                    centerPadding: '0',
                    infinite: true,
                    centerMode: navCount > 5,
                    responsive: [
                        { breakpoint: 1024, settings: { slidesToShow: Math.min(3, navCount), centerMode: navCount > 3 } },
                        { breakpoint: 768, settings: { slidesToShow: Math.min(3, navCount), centerMode: navCount > 3 } },
                        { breakpoint: 401, settings: { slidesToShow: Math.min(3, navCount), centerMode: navCount > 3 } }
                    ]
                });
            }
        });
    }


    /**
     * Handles the event for fetching and displaying episodes for a selected season.
     * 
     * @param {Object} event - The event triggered by the user interaction.
     */
    GetSeasonEpisodes(event) {

        event.preventDefault();

        const btn = event.currentTarget;

        if (jQuery(btn).data('loaded')) return;

        jQuery(btn).data('loaded', true);

        // Accessing data attributes from the clicked button
        const season_no = jQuery(btn).data('season_no');
        const episode = jQuery(btn).data('episodes');
        const is_slider = jQuery(btn).data('is-slider');
        const result = is_slider === undefined ? true : (is_slider === false ? false : true);

        const targetId = jQuery(btn).attr("href");
        const target = jQuery(targetId);

        // Find the slider container and add a loading skeleton class
        const sliderTarget = target.find('.css_prefix-slick-general');
        sliderTarget.addClass('st-skeleton');

        // Get slider settings from the template (the first season slider)
        const slick_settings = jQuery('.css_prefix-slick-general:first').data('slider_settings');
        const extra_settings = jQuery('.css_prefix-slick-general:first').data('extra_settings');

        post('tvshow_tab_seasons_data', { data: episode, season: season_no, is_slider: result })

            .then(res => {
                try {
                    let json_res = JSON.parse(res);

                    // Check if the response indicates success
                    if (json_res['success'] && json_res['result'] && json_res['result'].length > 0) {

                        // If slider is already initialized, destroy it to be safe
                        if (sliderTarget.hasClass('slick-initialized')) sliderTarget.slick('unslick');

                        sliderTarget.empty().removeClass('st-skeleton');

                        // Populate the container with new slide items
                        json_res['result'].forEach((item) => {
                            const slideHTML = `<div class="slick-item"><div class="episode-card">${item['block_main_content']}</div></div>`;
                            sliderTarget.append(slideHTML);
                        });

                        // If it's a slider, initialize it
                        if (result) {
                            let settings = { ...slick_settings, rtl: document.documentElement.dir === 'rtl' };// Create a fresh copy of settings
                            if (extra_settings) {
                                settings.nextArrow = '<button class="NextArrow-two"><i class="icon-arrow-right"></i></button>';
                                settings.prevArrow = '<button class="PreArrow-two"><i class="icon-arrow-left"></i></button>';
                            }
                            sliderTarget.slick(settings);
                        } else {
                            // If it's not a slider, just show the content (already appended)
                            target.find('.css_prefix-episodes-content').html(sliderTarget.html());
                        }


                    } else {
                        // Handle case where no episodes are found
                        sliderTarget.empty().removeClass('st-skeleton');
                        sliderTarget.html('<p class="no_data_found">' + 'No Episode Available' + '</p>');
                    }
                } catch (e) {
                    console.error("Error parsing JSON response", e);
                }
            })
            .catch(err => {
                console.error("Error during AJAX request", err);
                sliderTarget.empty().removeClass('st-skeleton');
                sliderTarget.html('<p class="no_data_found">' + 'Error loading episodes.' + '</p>');
            });
    }

    /**
     * Initialize episode slider
     * @param {Event} e - Event
     */
    InitEpisodeSlider(e) {
        e.preventDefault();
        const current_target = jQuery(e.currentTarget);
        const targetId = jQuery('.init-episode-slider').attr("href");
        const season_no = current_target.data('season_no');
        const sliderSelector = jQuery(`.css_prefix-slick-general[data-display="${season_no}"]`);
        jQuery(sliderSelector).slick('destroy')
        jQuery(sliderSelector).slick({
            slidesToShow: 5,
            slidesToScroll: 1,
            infinite: false,
            arrows: true,
            dots: false,
            draggable: true,
            nextArrow: '<button class="NextArrow-two"><i class="icon-arrow-right"></i></button>',
            prevArrow: '<button class="PreArrow-two"><i class="icon-arrow-left"></i></button>',
            responsive: [
                { breakpoint: 1368, settings: { slidesToShow: 3 } },
                { breakpoint: 1025, settings: { slidesToShow: 3 } },
                { breakpoint: 993, settings: { slidesToShow: 2 } },
                { breakpoint: 577, settings: { slidesToShow: 2 } }
            ],
        });
    }


    /**
    * Handles the "Remove Continue Watching" button functionality.
    * Sends an AJAX request to remove a post from the continue watching list.
    * 
    * @param {Event} event - The click event on the "Remove Continue Watching" button.
    */
    RemoveContinueWatching(event) {
        // Get the clicked button element
        var button = event.currentTarget;
        // Retrieve the closest slick item (container) for the button
        const slickItem = button.closest('.slick-slide'); // Adjust the selector as per your slick item structure

        jQuery(button).tooltip('dispose');
        // Retrieve data attributes from the button (post type and post ID)
        var postType = button.dataset.postType || '';  // The type of the post being removed from the continue watching list
        var id = button.dataset.id || '';  // The ID of the post being removed

        // Prepare the data to be sent in the AJAX request
        var data = { post_type: postType, post_id: id };

        // Send an AJAX request to remove the post from the continue watching list
        post('remove_continue_watch', data)
            .then(res => {
                // Log the response from the server (for debugging or confirmation)
                slickItem.remove();
            })
            .catch(err => {
                // Log any errors that occur during the AJAX request
                console.log(err);
            });
    }

    /**
   * Applies hover effects and attaches watchlist update listeners.
   */
    hoverEffect() {
        // Skip hover effects on small screens
        if (jQuery(window).width() <= 767) return;

        let hoverTimeout; // Store timeout for removing the cloned item
        let hoverDelayTimeout; // Timeout for adding delay before showing hover effect

        jQuery('.display-hover-effect').hover(
            function (e) {
                // Clear any existing timeouts
                clearTimeout(hoverTimeout);
                clearTimeout(hoverDelayTimeout);

                const _this = jQuery(e.currentTarget);

                // Add a delay before triggering the hover effect
                hoverDelayTimeout = setTimeout(() => {
                    try {
                        const isRtl = document.documentElement.getAttribute('dir') === 'rtl';
                        const $hiddenData = _this.find('.hidden-hover-data');

                        // Ensure hidden data exists
                        if ($hiddenData.length === 0) return;

                        // Clone the hidden hover data and create the hover effect
                        const $clone = $hiddenData.clone().removeAttr('style').addClass('cloned-item');
                        const offset = _this.offset();
                        const width = _this.outerWidth();
                        const height = _this.outerHeight();

                        const overlapPerc = 15;
                        const overlap = (overlapPerc / 100) * width;
                        const newWidth = width + overlap * 2;
                        const newHeight = height + overlap * 2;


                        let left = offset.left - (newWidth - width) / 2;
                        let top = offset.top - (newHeight - height) / 2;

                        // Remove existing clones before creating a new one
                        jQuery('.cloned-item').remove();
                        $clone.css({
                            position: 'absolute',
                            left: `${left}px`,
                            top: `${top}px`,
                            width: `${newWidth}px`,
                            height: `${newHeight}px`,
                            'z-index': 990,
                            'border-radius': '12px',
                            background: 'rgba(0, 0, 0, 0.1)',
                            opacity: 0,
                            transition: 'opacity 0.3s ease, transform 0.3s ease',
                            transform: 'scale(0.7)',
                            'pointer-events': 'auto',
                        });

                        jQuery('body').append($clone);

                        // Add click handler for block images
                        $clone.find('.block-images').on('click', function (event) {
                            event.preventDefault();
                            const $anchor = jQuery(this).find('a');

                            if ($anchor.length > 0) {
                                const href = $anchor.attr('href');
                                if (href) {
                                    window.location.href = href;
                                }
                            }
                        });

                        // Delegate the 'watchlistUpdated' event 
                        $clone.on('watchlistUpdated', (event, data) => {
                            const button = $hiddenData.find('.watch-list-btn');

                            // Validate button exists
                            if (button.length === 0) return;

                            const action = data.action;

                            // Simplified button update logic
                            const isAdding = action === 'add';
                            button.toggleClass('in-watchlist', isAdding)
                                .find('i')
                                .removeClass(isAdding ? 'icon-plus' : 'icon-check-2')
                                .addClass(isAdding ? 'icon-check-2' : 'icon-plus');

                            button.attr('data-bs-title', isAdding
                                ? 'Remove from watchlist'
                                : 'Add to watchlist');
                            button.attr('data-action', isAdding ? 'remove' : 'add');
                        });

                        // Apply the hover effect transition
                        setTimeout(() => {
                            const transformStyles = {
                                opacity: 1,
                                transform: 'scale(0.9)'
                            };

                            // Adjust transform for first and last elements
                            if (_this.hasClass('first')) {
                                transformStyles.transform += isRtl
                                    ? ' translateX(-13.5%)'
                                    : ' translateX(13.5%)';
                            } else if (_this.hasClass('last')) {
                                transformStyles.transform += isRtl
                                    ? ' translateX(13.5%)'
                                    : ' translateX(-13.5%)';
                            }

                            $clone.css(transformStyles);
                        }, 500);

                        // Handle hover on the cloned card
                        $clone.hover(
                            function () {
                                clearTimeout(hoverTimeout); // Prevent removal while hovering
                            },
                            function () {
                                // Remove the cloned card when the mouse leaves
                                hoverTimeout = setTimeout(() => $clone.remove(), 10);
                            }
                        );
                    } catch (error) {
                        console.error('Error in hover effect:', error);
                    }
                }, 500); // Add a delay before triggering the hover effect
            },
            function () {
                clearTimeout(hoverDelayTimeout); // Cancel hover effect if mouse leaves
                const $clone = jQuery('.cloned-item');

                // Set a timeout to remove the cloned card
                hoverTimeout = setTimeout(() => {
                    if ($clone.length && !($clone[0].matches(':hover'))) {
                        $clone.remove();
                    }
                }, 10);
            }
        );
    }

    // Timer methods
    callTimer(element) {
        const $element = jQuery(element);
        const futureDate = $element.attr('data-date');
        const label = $element.attr('data-labels') === 'true';
        const displayFormat = $element.attr('data-format');

        $element.countdowntimer({
            dateAndTime: futureDate,
            labelsFormat: label,
            displayFormat: displayFormat,
        });
    }

    UpcomingDataTimer(element) {
        const $element = jQuery(element);
        const futureDate = $element.attr('data-date');

        if (!futureDate) return;

        const targetTime = new Date(futureDate).getTime();
        const timeElements = {
            days: $element.find('#days'),
            hours: $element.find('#hours'),
            minutes: $element.find('#minutes'),
            seconds: $element.find('#seconds')
        };

        // Clear existing timer
        if ($element.data('timerInterval')) {
            clearInterval($element.data('timerInterval'));
        }

        const updateTimer = () => {
            const now = Date.now();
            const distance = targetTime - now;

            if (distance <= 0) {
                clearInterval(timerInterval);
                Object.values(timeElements).forEach($el => $el.text('00'));
                $element.addClass('countdown-finished');
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            timeElements.days.text(String(days).padStart(2, '0'));
            timeElements.hours.text(String(hours).padStart(2, '0'));
            timeElements.minutes.text(String(minutes).padStart(2, '0'));
            timeElements.seconds.text(String(seconds).padStart(2, '0'));
        };

        const timerInterval = setInterval(updateTimer, 1000);
        $element.data('timerInterval', timerInterval);
        updateTimer(); // Initial call
    }

    // Add other methods you need from your original implementation...
    /**
     * Initializes the TV Show Season Slick Slider with advanced configurations.
     * @param {jQuery} sliderElement - The jQuery element representing the slider.
     */
    initTvShowSeason(sliderElement, args = {}) {
        // Get slider settings and extra settings from data attributes
        const slickSettings = sliderElement.data('slider_settings');
        const extraSettings = sliderElement.data('extra_settings');
        const is_rtl = document.documentElement.getAttribute('dir') === 'rtl';

        // Add extra settings if necessary
        if (extraSettings === true) {
            slickSettings.nextArrow = '<button class="NextArrow-two"><i class="icon-arrow-right"></i></button>';
            slickSettings.prevArrow = '<button class="PreArrow-two"><i class="icon-arrow-left"></i></button>';
        }

        // If slickSettings is not defined, exit early
        if (!slickSettings) {
            return;
        }
        slickSettings.rtl = is_rtl;
        slickSettings.lazyLoad = 'ondemand';

        // Cache repeated selectors to avoid querying DOM multiple times
        const detailWrap = sliderElement.closest('.css_prefix-wrap-details');
        const episodeCardText = detailWrap.find('.p-btns').data('episode_card_text');
        const navTabsInner = detailWrap.find('.nav-tabs-inner');
        const firstTvShowId = navTabsInner.data('first_tvshow_id');

        // Initialize the slider with the defined settings
        jQuery(sliderElement)
            .on('afterChange', (event, slick, currentSlide) => {
                // Get the current slide element and its associated data
                const element = jQuery(sliderElement).find(`[data-slick-index=${currentSlide}]`);
                const tvShowId = element.data("post");

                // Remove the active class from all nav-tabs-inner links
                navTabsInner.find("a").removeClass('active');

                // Check if the element's data has already been loaded
                const data = {
                    'tvshow_id': tvShowId,
                    'season_change': true,
                    'season_number': 0, // Update this dynamically if needed
                    'episode_card_text': episodeCardText,
                    'first_tvshow_id': firstTvShowId
                };

                // Call the AJAX function for loading data, use arrow function to maintain `this`
                this.ajaxCall(data, element, firstTvShowId, currentSlide);

                // Hide all details initially
                detailWrap.find('.season-episodes-details .episodes-info').hide();
                detailWrap.find('.css_prefix-episodes-meta').hide();

                // Select and show the active seasons and episodes based on TV show ID
                const showSeasonsSelector = `.tvshow-${tvShowId}`;
                const showEpisodesSelector = `.tvshow-${tvShowId}-season-1`; // You can update the season number dynamically

                detailWrap.find(showSeasonsSelector).first().addClass('active').show();
                detailWrap.find(showEpisodesSelector).show();
            })
            .slick(slickSettings);
    }

    /**
    * Handles season change via AJAX when an episode meta button is clicked.
    */
    ajaxChangeSeason() {
        // Use event delegation for dynamically loaded elements
        jQuery(document).on("click", '.css_prefix-episodes-meta', (event) => {
            const $this = jQuery(event.currentTarget);
            const parent = $this.closest('.css_prefix-wrap-details');

            // Cache commonly accessed elements
            const episodeCardText = parent.find('.p-btns').data('episode_card_text');
            const tvShowId = $this.data('tvshow-id');
            const seasonNumber = parseInt($this.data('season'), 10);
            const ajaxLoaded = $this.data('ajax_loaded');

            // Determine the next season
            const nextSeasonNumber = seasonNumber + 1;
            const $showSeason = `.tvshow-${tvShowId}-season-${nextSeasonNumber}`;

            // Clear active class and hide current season details
            parent.find('.css_prefix-episodes-meta').removeClass('active');
            parent.find('.season-episodes-details .episodes-info').hide();

            // Mark the clicked season as active
            $this.addClass('active');

            // Show the new season's details and apply fade-in animation
            jQuery($showSeason).show().addClass("fade-in-anim");

            // If the episodes info has the fade-in-anim class, remove it
            parent.find('.episodes-info').removeClass("fade-in-anim");

            // Prepare the AJAX data to load the new season's content
            const data = {
                'action': 'tvshow_seasons_data',
                'tvshow_id': tvShowId,
                'season_change': false,
                'season_number': seasonNumber,
                'episode_card_text': episodeCardText,
            };

            // Only trigger AJAX call if the data isn't already loaded
            this.ajaxCall(data, $this, seasonNumber);
        });
    }


    /**
     * Fetches TV show seasons data and dynamically updates the season and episode content.
     * @param {Object} data - The data to be sent with the AJAX request.
     * @param {jQuery} $this - The jQuery object of the element triggering the AJAX call.
     * @param {number} first_tvshow_id - The ID of the first TV show for comparison.
     */
    ajaxCall(data, $this, first_tvshow_id) {
        // Get the closest parent element that contains the TV show season data
        const mainParent = $this.closest(".css_prefix-tvshow-season-parent");

        // Make an AJAX post request to fetch the TV show seasons data
        post('tvshow_seasons_data', data)
            .then(res => {
                // Parse the response data
                const json_res = res.data;

                // Check if the response is a valid array and has data
                if (Array.isArray(json_res) && json_res.length > 0) {
                    // Check if new content is available before clearing existing data
                    const hasSeasonContent = json_res.some(value => value['season_content']);
                    const hasBlockContent = json_res.some(value => value['block_content']);
                    const hasButtonContent = json_res.some(value => value['button_content']);

                    // Clear existing data only if new content is available
                    if (hasSeasonContent) {
                        mainParent.find('.nav-tabs-inner').empty(); // Clear seasons
                    }
                    if (hasBlockContent) {
                        mainParent.find('.season-episodes-details').empty(); // Clear episodes
                    }
                    if (hasButtonContent) {
                        mainParent.find('.view-all-btn-parent').empty(); // Clear "view all" button
                    }

                    // Hide existing episodes meta if tvshow_id in the response does not match the first TV show ID
                    if (json_res[0]['tvshow_id'] !== undefined && json_res[0]['tvshow_id'] !== first_tvshow_id) {
                        mainParent.find('.nav-tabs-inner .css_prefix-episodes-meta').hide();
                    }

                    // Loop through the response and append season and episode content
                    json_res.forEach(value => {
                        // Check and append season content if exists
                        if (value['season_content']) {
                            mainParent.find('.nav-tabs-inner').append(value['season_content']);
                            // Set the first season as active and mark it as loaded
                            mainParent.find('.tvshow-' + json_res[0]['tvshow_id']).first()
                                .addClass('active')
                                .data('ajax_loaded', true);
                        }

                        // Append block content for episodes if available
                        if (value['block_content']) {
                            mainParent.find('.season-episodes-details').append(value['block_content']);
                        }

                        // Append the "view all" button if it exists; otherwise, hide the button container
                        if (value['button_content']) {
                            mainParent.find('.view-all-btn-parent').append(value['button_content']);
                        } else {
                            mainParent.find('.view-all-btn-parent').hide();
                        }
                    });

                    // Mark the button as loaded and set the active class for the first TV show tab
                    $this.data('ajax_loaded', true);
                    mainParent.find('.tvshow-' + json_res[0]['tvshow_id']).first()
                        .addClass("active")
                        .data('ajax_loaded', true);

                    // Remove the skeleton loader once content is loaded
                    mainParent.find('.skeleton-box').remove();
                } else {
                    // Handle case when no episodes are found in the response
                    mainParent.find('.season-episodes-details').append("<div class='episodes-info'>No Episodes Found</div>");
                }
            })
            .catch(err => {
                // Log error if the AJAX request fails
                console.error("Error fetching TV show seasons data:", err);
            });
    }

}

// Initialize
new SlickGeneral();