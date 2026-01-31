/**
 * Modular Hover Effect System
 * Optimized for performance, memory efficiency, and extensibility
 */

class HoverEffectManager {
    constructor(options = {}) {
        this.config = {
            hoverDelay: 500,
            hideDelay: 10,
            overlapRatio: 0.15,
            zIndex: 990,
            minScreenWidth: 768,
            selector: '.display-hover-effect, [data-hover-card]',
            ...options
        };

        this.activeCard = null;
        this.timers = new Map();
        this.cardTypes = new Map();
        this.templateCache = new Map();
        this.isInitialized = false;

        // Bind methods to preserve context
        this.handleMouseEnter = this.handleMouseEnter.bind(this);
        this.handleMouseLeave = this.handleMouseLeave.bind(this);
        this.handleCardHover = this.handleCardHover.bind(this);
        this.handleResize = this.debounce(this.handleResize.bind(this), 250);

        this.init();
    }

    init() {
        if (this.shouldSkipHover()) return;
        this.registerDefaultCardTypes();
        this.bindEvents();
        this.isInitialized = true;
    }

    shouldSkipHover() {
        return window.innerWidth < this.config.minScreenWidth ||
            !document.querySelector(this.config.selector);
    }

    registerDefaultCardTypes() {
        // Main card type (backwards compatibility)
        this.registerCardType('main', {
            template: () => window.stAjax?.hoverCards?.main || '',
            fields: [
                'permalink',
                'image',
                'imageHtml',
                'badges',
                'genres',
                'title',
                'runtime',
                'languages',
                'watchlist',
                'playNowText'
            ],
            animation: {
                scale: 0.9,
                positioning: 'overlap'
            }
        });

        // Main Landscape card type
        this.registerCardType('main_landscape', {
            template: () => window.stAjax?.hoverCards?.main_landscap || '',
            fields: [
                'permalink',
                'image',
                'imageHtml',
                'badges',
                'genres',
                'title',
                'runtime',
                'languages',
                'watchlist',
                'playNowText'
            ],
            animation: {
                scale: 0.95,
                positioning: 'overlap'
            }
        });


    }

    registerCardType(name, config) {
        this.cardTypes.set(name, {
            template: config.template,
            fields: config.fields || [],
            animation: {
                scale: 0.9,
                positioning: 'overlap',
                ...config.animation
            },
            validator: config.validator || null,
            preprocessor: config.preprocessor || null
        });
    }

    bindEvents() {
        // Use event delegation for better performance
        document.addEventListener('mouseenter', this.handleMouseEnter, true);
        document.addEventListener('mouseleave', this.handleMouseLeave, true);
        window.addEventListener('resize', this.handleResize);
        window.addEventListener('scroll', this.clearActiveCard.bind(this), { passive: true });
    }

    unbindEvents() {
        document.removeEventListener('mouseenter', this.handleMouseEnter, true);
        document.removeEventListener('mouseleave', this.handleMouseLeave, true);
        window.removeEventListener('resize', this.handleResize);
        window.removeEventListener('scroll', this.clearActiveCard.bind(this));
    }

    handleMouseEnter(event) {
        const element = event.target.closest(this.config.selector);
        if (!element) return;

        this.clearTimer(element);
        this.clearTimer('hide');

        this.setTimer(element, () => {
            this.showHoverCard(element);
        }, this.config.hoverDelay);
    }


    handleMouseLeave(event) {
        const element = event.target.closest(this.config.selector);
        if (!element) return;

        this.clearTimer(element);

        if (this.activeCard && !this.isMouseOverCard(event)) {
            this.setTimer('hide', () => {
                this.clearActiveCard();
            }, this.config.hideDelay);
        }
    }

    handleCardHover(enter) {
        if (enter) {
            this.clearTimer('hide');
        } else {
            this.setTimer('hide', () => {
                this.clearActiveCard();
            }, this.config.hideDelay);
        }
    }

    isMouseOverCard(event) {
        return this.activeCard?.contains(event.relatedTarget);
    }

    showHoverCard(element) {

        if (!this.isElementVisible(element)) return;

        this.clearActiveCard();

        const cardType = this.getCardType(element);
        const cardConfig = this.cardTypes.get(cardType);

        if (!cardConfig) {
            console.warn(`Unknown card type: ${cardType}`);
            return;
        }

        const cardData = this.extractCardData(element, cardConfig);
        if (!this.validateCardData(cardData, cardConfig)) return;

        const template = this.getTemplate(cardType, cardConfig);
        if (!template) return;

        const html = this.processTemplate(template, cardData);
        if (!html.trim()) return;

        this.createAndShowCard(element, html, cardConfig);
    }

    getCardType(element) {
        return element.dataset.hoverCard ||
            element.dataset.cardType ||
            'default';
    }

    extractCardData(element, cardConfig) {
        const data = {};

        // Extract from data attributes
        cardConfig.fields.forEach(field => {
            const key = this.camelCase(field); // converts image-html → imageHtml
            const value = element.dataset[key] || element.dataset[field] || '';
            if (value) data[key] = value;
        });

        // Apply preprocessor if defined
        if (cardConfig.preprocessor) {
            return cardConfig.preprocessor(data, element) || data;
        }

        return data;
    }

    validateCardData(data, cardConfig) {
        if (cardConfig.validator) {
            return cardConfig.validator(data);
        }

        // Default validation: at least one non-empty field
        return Object.values(data).some(value =>
            value && typeof value === 'string' && value.trim().length > 0
        );
    }

    getTemplate(cardType, cardConfig) {
        if (this.templateCache.has(cardType)) {
            return this.templateCache.get(cardType);
        }

        const template = typeof cardConfig.template === 'function'
            ? cardConfig.template()
            : cardConfig.template;

        if (template) {
            this.templateCache.set(cardType, template);
        }

        return template;
    }

    processTemplate(template, data) {
        return template.replace(/\{\{(\w+)\}\}/g, (match, key) => {
            return data[key] || '';
        });
    }


    createAndShowCard(element, html, cardConfig) {
        const card = document.createElement('div');
        card.className = 'cloned-item';
        card.innerHTML = html;

        // Link the hover card to the original element for watchlist updates
        card._hoverOriginalElement = element;

        this.styleCard(card, element, cardConfig);
        this.attachCardEvents(card);

        document.body.appendChild(card);
        this.activeCard = card;

        // Process dynamic action buttons
        this.processDynamicActionButtons(card, element);

        requestAnimationFrame(() => {
            this.animateCardIn(card, element, cardConfig);
        });
    }


    styleCard(card, element, cardConfig) {
        const rect = element.getBoundingClientRect();
        const { width, height, left, top } = this.calculateCardDimensions(rect, cardConfig);

        // Calculate center point of original card
        const centerX = rect.left + (rect.width / 2);
        const centerY = rect.top + (rect.height / 2);

        // Calculate center point of hover card
        const hoverCenterX = left + (width / 2);
        const hoverCenterY = top + (height / 2);

        // Calculate offset to position hover card center at original card center
        const offsetX = centerX - hoverCenterX;
        const offsetY = centerY - hoverCenterY;

        Object.assign(card.style, {
            position: 'fixed',
            left: `${left}px`,
            top: `${top}px`,
            width: `${width}px`,
            height: `${height}px`,
            zIndex: this.config.zIndex,
            borderRadius: '12px',
            background: 'rgba(0, 0, 0, 0.1)',
            opacity: '0',
            transform: `translate(${offsetX}px, ${offsetY}px) scale(0.85)`,
            transition: 'all 0.45s cubic-bezier(0.34, 1.56, 0.64, 1)',
            transformOrigin: 'center center',
            pointerEvents: 'auto',
            willChange: 'transform, opacity',
            backfaceVisibility: 'hidden'
        });

        // Store offset for animation
        card._animationOffset = { offsetX, offsetY };
    }

    calculateCardDimensions(rect, cardConfig) {
        const overlap = rect.width * this.config.overlapRatio;
        const width = rect.width + (overlap * 2);
        const height = rect.height + (overlap * 2);
        const left = rect.left - overlap;
        const top = rect.top - overlap;

        return { width, height, left, top };
    }

    animateCardIn(card, element, cardConfig) {
        const animation = cardConfig.animation;
        let transform = `scale(${animation.scale})`;

        if (animation.customTransform) {
            transform = animation.customTransform(element);
        } else if (animation.positioning === 'overlap') {
            // Apply positional transforms based on element classes
            const isFirst = element.classList.contains('first');
            const isLast = element.classList.contains('last');
            const isRtl = document.documentElement.dir === 'rtl';

            if (isFirst) {
                transform += isRtl ? ' translateX(-13.5%)' : ' translateX(13.5%)';
            } else if (isLast) {
                transform += isRtl ? ' translateX(13.5%)' : ' translateX(-13.5%)';
            }
        }

        card.style.opacity = '1';
        card.style.transform = transform;
    }

    attachCardEvents(card) {
        card.addEventListener('mouseenter', () => this.handleCardHover(true));
        card.addEventListener('mouseleave', () => this.handleCardHover(false));

        // Handle clicks on images
        const imageBlocks = card.querySelectorAll('.block-images, [data-clickable]');
        imageBlocks.forEach(block => {
            block.addEventListener('click', (event) => {
                event.preventDefault();
                const link = card.querySelector('a');
                if (link?.href) {
                    window.location.href = link.href;
                }
            });
        });

        const $card = jQuery(card);
        const $originalCard = jQuery(card._hoverOriginalElement);

        $card.on('watchlistUpdated', (event, data) => {
            const isAdding = data.action === 'add';
            const postId = data.postId;

            if (!$originalCard.length) return;

            // Get original watchlist HTML
            let watchlistHTML = $originalCard.data('watchlist') || '';

            // Parse HTML string into jQuery element
            let $buttonEl = jQuery(watchlistHTML).filter('button.watch-list-btn');

            // Update class
            $buttonEl.toggleClass('in-watchlist', isAdding);

            // Update icon
            $buttonEl.find('i').removeClass('icon-plus icon-check-2')
                .addClass(isAdding ? 'icon-check-2' : 'icon-plus');

            // Update attributes
            $buttonEl.attr('data-action', isAdding ? 'remove' : 'add');
            $buttonEl.attr('data-bs-title', isAdding ? 'Remove from watchlist' : 'Add to watchlist');

            // Convert back to HTML string
            watchlistHTML = $buttonEl.prop('outerHTML');

            // Update jQuery cache and DOM attribute
            $originalCard.data('watchlist', watchlistHTML);
            $originalCard.attr('data-watchlist', watchlistHTML);

        // Update button inside the original card immediately
        const $button = $originalCard.find('.watch-list-btn');
        if ($button.length) {
            $button.replaceWith($buttonEl);
        }
    });
    }

    processDynamicActionButtons(card, originalElement) {
        const actionBtns = card.querySelectorAll('.hover-card-action-btn');
        actionBtns.forEach(btnContainer => {
            const permalink = btnContainer.dataset.permalink;
            const playText = btnContainer.dataset.playText;
            const isUpcoming = originalElement.dataset.isUpcoming === 'true';

            if (isUpcoming && playText === 'Remind Me') {
                // Create remind me button
                const postId = originalElement.dataset.postId || '';
                const postType = originalElement.dataset.postType || '';

                btnContainer.innerHTML = `
                    <button class="btn btn-purple notify-me-btn w-100"
                            data-post-id="${postId}"
                            data-post-type="${postType}">
                        <span class="d-flex align-items-center justify-content-center gap-2">
                            <i class="icon-bell-1"></i>
                            <span>Remind Me</span>
                        </span>
                    </button>
                `;
            } else {
                // Create regular play button
                btnContainer.innerHTML = `
                    <a href="${permalink}" class="btn btn-primary w-100">
                        ${playText}
                    </a>
                `;
            }
        });
    }


    clearActiveCard() {
        if (this.activeCard) {
            this.activeCard.remove();
            this.activeCard = null;
        }
        this.clearTimer('hide');
    }

    handleResize() {
        this.clearActiveCard();
        this.clearAllTimers();

        if (this.shouldSkipHover()) {
            if (this.isInitialized) {
                this.unbindEvents();
                this.isInitialized = false;
            }
        } else if (!this.isInitialized) {
            this.bindEvents();
            this.isInitialized = true;
        }
    }

    // Utility Methods
    setTimer(key, callback, delay) {
        this.clearTimer(key);
        const timerId = setTimeout(callback, delay);
        this.timers.set(key, timerId);
        return timerId;
    }

    clearTimer(key) {
        const timerId = this.timers.get(key);
        if (timerId) {
            clearTimeout(timerId);
            this.timers.delete(key);
        }
    }

    clearAllTimers() {
        this.timers.forEach(timerId => clearTimeout(timerId));
        this.timers.clear();
    }

    isElementVisible(element) {
        const rect = element.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0 &&
            rect.top < window.innerHeight && rect.bottom > 0;
    }

    camelCase(str) {
        return str.replace(/-([a-z])/g, (match, letter) => letter.toUpperCase());
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Public API for extensions
    extend(extensions) {
        Object.assign(this, extensions);
        return this;
    }

    addCardType(name, config) {
        this.registerCardType(name, config);
        return this;
    }

    removeCardType(name) {
        this.cardTypes.delete(name);
        this.templateCache.delete(name);
        return this;
    }

    destroy() {
        this.clearActiveCard();
        this.clearAllTimers();
        this.unbindEvents();
        this.cardTypes.clear();
        this.templateCache.clear();
        this.isInitialized = false;
    }
}

// Factory function for easy instantiation
const createHoverEffect = (options = {}) => new HoverEffectManager(options);

// Auto-initialize with default settings (backwards compatibility)
if (typeof jQuery !== 'undefined') {
    jQuery(() => {
        // Only initialize if no custom implementation is detected
        if (!window.customHoverEffect) {
            window.hoverEffectManager = createHoverEffect();
        }
    });
} else {
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.customHoverEffect) {
            window.hoverEffectManager = createHoverEffect();
        }
    });
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { HoverEffectManager, createHoverEffect };
} else if (typeof window !== 'undefined') {
    window.HoverEffectManager = HoverEffectManager;
    window.createHoverEffect = createHoverEffect;
}