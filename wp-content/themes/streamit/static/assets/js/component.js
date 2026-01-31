import './superfish.js';
import bootstrapcomponent from './bootstrap-component.js';

export default class Component {
    constructor() {
        this.cacheSelectors();
        this.bootstrap = new bootstrapcomponent();
        this.bootstrap.tooltip();
        
        this.init();
    }

    /**
     * Cache frequently used selectors for performance
     */
    cacheSelectors() {
        this.$body = jQuery(document.body);
        this.$window = jQuery(window);
        this.$header = jQuery('.header-default');
        this.$menu = jQuery('ul.sf-menu');
        this.$backToTop = jQuery('#back-to-top');
        this.scrollAmount = 200;
    }

    /**
     * Initialize component features only if their DOM elements exist
     */
    init() {
        this.initHeader();
        this.initMenu();
        this.bindEvents();

        // Conditional initializations (lazy-loaded)
        if (document.querySelector('.readmore-wrapper')) this.initReadMore();
        if (document.querySelector('#st_search_page_form , #st-search-drop')) this.initSearch();
        if (document.querySelector('.custom-tab-slider')) this.initSlider();
        if (document.querySelector('#loading')) this.initLoader();

        document.addEventListener('click', (event) => {
            const target = event.target;
            if (target.matches('.st-load-trailer') || target.closest('.st-load-trailer')) {
                 this.initTrailerModal(event);
            }
        });
    }

    // Trailer Modal
    initTrailerModal(event) {
        const trailerBtn = event.target.closest(".st-load-trailer");
        if (!trailerBtn) return;
        event.preventDefault();

        const trailerUrl = trailerBtn.dataset.trailerUrl;
        const trailerType = trailerBtn.dataset.trailerType;
        const modalTemplate = window.streamitTrailerVars?.trailerModalHTML;

        if (!trailerUrl || !modalTemplate) return;

        this.removeExistingTrailerModal();
        this.createTrailerModal(trailerUrl, trailerType, modalTemplate);
    }

    removeExistingTrailerModal() {
        const oldModal = document.getElementById("trailerModal");
        if (oldModal) oldModal.remove();
    }

    createTrailerModal(trailerUrl, trailerType, modalTemplate) {
        document.body.insertAdjacentHTML("beforeend", modalTemplate);
        this.openTrailerModal(trailerUrl, trailerType);
    }

    openTrailerModal(trailerUrl, trailerType) {
        const modal = document.getElementById("trailerModal");
        const iframeElement = document.getElementById("trailerIframe");
        const videoElement = document.getElementById("trailerVideo");
        const closeBtn = modal?.querySelector(".btn-close");

        if (!modal) return;

        // Hide both initially
        if (iframeElement) iframeElement.style.display = "none";
        if (videoElement) videoElement.style.display = "none";

        if (trailerType === "iframe" && iframeElement) {
            iframeElement.style.display = "block";
            iframeElement.src = trailerUrl;
            iframeElement.allow = "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";
        } else if (trailerType === "video" && videoElement) {
            videoElement.muted = false;
            videoElement.volume = 1;
            videoElement.style.display = "block";
            videoElement.querySelector("source").src = trailerUrl;
            videoElement.load();
            videoElement.play().catch(err => console.warn("Video autoplay blocked:", err));
        }

        // Show modal
        modal.style.display = "block";
        document.body.style.overflow = "hidden";
        modal.classList.add("show");

        // Setup close handlers
        this.setupTrailerModalCloseHandlers(modal, iframeElement, videoElement, closeBtn);
    }

    setupTrailerModalCloseHandlers(modal, iframeElement, videoElement, closeBtn) {
        const closeModal = () => {
            modal.classList.remove("show");
            modal.classList.add("hiding");

            // Stop playback
            if (iframeElement) iframeElement.src = "";
            if (videoElement) {
                videoElement.pause();
                videoElement.currentTime = 0;
            }

            setTimeout(() => {
                document.body.style.overflow = "auto";
                modal.remove();
            }, 300);
        };

        if (closeBtn) {
            closeBtn.addEventListener("click", closeModal);
        }

        modal.addEventListener("click", (e) => {
            if (e.target === modal) closeModal();
        });

        const escHandler = (e) => {
            if (e.key === "Escape" && modal.classList.contains("show")) {
                closeModal();
                document.removeEventListener("keydown", escHandler);
            }
        };

        document.addEventListener("keydown", escHandler);
    }

    /**
     * All global event bindings
     */
    bindEvents() {
        this.$body
            .on("click", "#back-to-top", e => this.scrollToTop(e))
            .on("click", ".copy-url-btn", e => this.copyUrl(e))
            .on("click", "button.plus, button.minus", e => this.updateShopQuantity(e))
            .on("click", "#openReviewButton", e => this.openReviewOffCanvas(e))
            .on('woosq_loaded woosq_close', () => this.quickViewSection());

            this.$window
            .on("scroll", () => this.handleScroll())
            .on("resize", () => {
                this.setHeaderHeight();
                this.handleScroll();
            });
    }

    /**
     * Initialize header height and scroll behavior
     */
    initHeader() {
        this.setHeaderHeight();
        this.bindHeaderSticky();
    }

    /**
     * Add sticky class on scroll
     */
    bindHeaderSticky() {
        const header = document.querySelector('header.has-sticky');
        if (!header) return;

        window.addEventListener('scroll', () => {
            header.classList.toggle("header-sticky", document.documentElement.scrollTop > 0);
        });
    }

    /**
     * Dynamically set header height as CSS variable
     */
    setHeaderHeight() {
        if (!this.$header.length) return;
        const height = this.$header.height();
        jQuery('html').css('--header-height', `${height / 16}em`);
    }

    /**
     * Initialize Superfish / mobile menu
     */
    initMenu() {
        if (!this.$menu.length) return;

        if (this.isMobile()) {
            this.destroyMenuHover();
            this.addMobileTouchToggle();
        } else {
            this.$menu.superfish({
                delay: 500,
                animation: { opacity: 'show', height: 'show' },
                speed: 'fast',
                cssArrows: true,
                autoArrows: true,
            });
        }
    }

    isMobile() {
        return window.innerWidth <= 768;
    }

    destroyMenuHover() {
        this.$menu.find('li').off('mouseenter mouseleave');
    }

    addMobileTouchToggle() {
        this.$menu.find('li.menu-item-has-children').on('click touchstart', function (e) {
            const $li = jQuery(this);
            const $submenu = $li.children('ul.sub-menu');
            const $toggle = $li.find('.toggledrop');

            if (jQuery(e.target).is($toggle) || jQuery(e.target).closest('.toggledrop').length) {
                e.preventDefault();
                e.stopPropagation();

                if ($submenu.is(':visible')) {
                    $submenu.slideUp(300);
                    $li.removeClass('open');
                } else {
                    $li.siblings('.open').removeClass('open').find('ul.sub-menu').slideUp(300);
                    $submenu.slideDown(300);
                    $li.addClass('open');
                }
            }
        });
    }

    /**
     * Back to top smooth scroll
     */
    scrollToTop(e) {
        e.preventDefault();
        jQuery("html, body").animate({ scrollTop: 0 }, 600, "linear");
    }

    /**
     * Show/hide "back to top" on scroll — safer (stops queued animations)
     */
    handleScroll() {
        if (!this.$backToTop || !this.$backToTop.length) {
            this.$backToTop = jQuery('#back-to-top');
            if (!this.$backToTop.length) return;
        }

        const scrollTop = this.$window.scrollTop();
        const shouldShow = scrollTop > 100;

        if (shouldShow) {
            this.$backToTop.stop(true, true).fadeIn(200);
        } else {
            this.$backToTop.stop(true, true).fadeOut(200);
        }
    }
    
    /**
     * Copy URL to clipboard
     */
    copyUrl(e) {
        e.preventDefault();
        const input = document.querySelector('.copy-post-url');
        if (!input) return;

        input.select();
        try {
            document.execCommand('copy');
            console.log('URL copied to clipboard');
        } catch (err) {
            console.error('Failed to copy URL:', err);
        }
    }

    /**
     * Handle product quantity +/- buttons
     */
    updateShopQuantity(e) {
        const $btn = jQuery(e.target).closest('button');
        const $qty = $btn.closest('.quantity').find('.qty');
        jQuery('button[name="update_cart"]').removeAttr('disabled');

        let val = parseFloat($qty.val()) || 0;
        const max = parseFloat($qty.attr('max')) || Infinity;
        const min = parseFloat($qty.attr('min')) || 0;
        const step = parseFloat($qty.attr('step')) || 1;

        if ($btn.hasClass('plus')) {
            $qty.val(Math.min(val + step, max));
        } else if ($btn.hasClass('minus')) {
            $qty.val(Math.max(val - step, min));
        }
    }

    /**
     * Read more toggle for long text
     */
    initReadMore() {
        document.querySelectorAll('.readmore-wrapper').forEach(wrapper => {
            const btn = wrapper.querySelector('.readmore-btn');
            const text = wrapper.querySelector('.readmore-text');
            if (!btn || !text) return;

            if (text.scrollHeight > text.clientHeight) {
                btn.style.display = "inline-block";
            }

            btn.addEventListener('click', () => {
                const expanded = text.classList.toggle('active');
                btn.querySelector('.show-more-text').style.display = expanded ? "none" : "inline";
                btn.querySelector('.show-less-text').style.display = expanded ? "inline" : "none";
                btn.classList.toggle('bg-primary', expanded);
                btn.classList.toggle('bg-secondary', !expanded);
            });
        });
    }

    /**
     * Search bar + hide/show logic
     */
    initSearch() {
        const $form = jQuery('#st_search_page_form');
        const $input = $form.find('input[name="s"]');
        const $removeBtn = $form.find('#remove_search_text');

        const toggleCross = () => $removeBtn.toggle($input.val().trim() !== '');

        $input.on('input', toggleCross);
        $form.on('click', '#remove_search_text', e => {
            e.preventDefault();
            $input.val('');
            $removeBtn.hide();
        });

        jQuery('#st-search-drop').on('click', e => {
            e.preventDefault();
            e.stopPropagation();
            jQuery('#header_search_input').toggleClass('show');
        });

        jQuery(window).on('click', e => {
            const $target = jQuery(e.target);
            
            if (!$target.closest('#st-search-drop, #header_search_input, .search_result_section').length) {
                jQuery('#header_search_input').removeClass('show');
                jQuery('#search-query').val('');
                jQuery('.search_result_section').empty();
            }
        });

        jQuery('#header_search_input, .search_result_section').on('click', e => e.stopPropagation());
    }

    /**
     * Initialize loader (optional)
     */
    initLoader() {
        jQuery('#loading').hide();
    }
    
    initSlider() {
        this.slider = jQuery('.custom-tab-slider');
        this.leftArrow = jQuery('.tab-slider-left');
        this.rightArrow = jQuery('.tab-slider-right');

        if (!this.slider.length) {
            console.warn('Slider element not found.');
            return;
        }

        this.updateArrowVisibility();

        if (this.leftArrow.length) {
            this.leftArrow.on('click', (e) => this.slide('left', e));
        }
        if (this.rightArrow.length) {
            this.rightArrow.on('click', (e) => this.slide('right', e));
        }

        this.addSlideDrag();
    }

    slide(direction, e) {
        e.preventDefault();
        const scrollAmount = this.slider.width() * 0.8;
        const current = this.slider.scrollLeft();
        const target = direction === 'left' ? current - scrollAmount : current + scrollAmount;

        this.slider.animate({ scrollLeft: target }, 300);
        setTimeout(() => this.updateArrowVisibility(), 350);
    }

    addSlideDrag() {
        const eslider = this.slider[0];
        let isDown = false, startX, scrollLeft;

        eslider.addEventListener('mousedown', (e) => {
            isDown = true;
            eslider.classList.add('active');
            startX = e.pageX - eslider.offsetLeft;
            scrollLeft = eslider.scrollLeft;
        });

        ['mouseleave', 'mouseup'].forEach(evt => {
            eslider.addEventListener(evt, () => {
                isDown = false;
                eslider.classList.remove('active');
            });
        });

        eslider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - eslider.offsetLeft;
            const walk = (x - startX) * 2.5;
            eslider.scrollLeft = scrollLeft - walk;
            this.updateArrowVisibilityDuringDrag(eslider);
        });
    }

    updateArrowVisibility() {
        if (!this.slider.length) return;
        const scrollLeft = this.slider.scrollLeft();
        const maxScroll = this.slider[0].scrollWidth - this.slider[0].clientWidth;

        this.leftArrow.toggle(scrollLeft > 5);
        this.rightArrow.toggle(scrollLeft < maxScroll - 5);
    }

    updateArrowVisibilityDuringDrag(eslider) {
        const scrollLeft = eslider.scrollLeft;
        const maxScroll = eslider.scrollWidth - eslider.clientWidth;

        this.leftArrow.toggle(scrollLeft > 5);
        this.rightArrow.toggle(scrollLeft < maxScroll - 5);
    }


    /**
     * Placeholder for review offcanvas logic
     */
    openReviewOffCanvas(e) {
        console.log('Open review offcanvas triggered', e.target);
    }

    /**
     * Placeholder for quick view logic
     */
    quickViewSection() {
        jQuery('#woosq-popup select').select2({
            theme: 'bootstrap4',
            allowClear: false,
        });
    }
}
