import { post } from "../utilities/ajax";
import Plyr from 'plyr';
import 'plyr/dist/plyr.css';

export default class MediaPlayer {
    // Constants
    static DEFAULT_COUNTDOWN_DURATION = 10;
    static MIN_TRACKING_TIME = 180;
    static TIME_UPDATE_THRESHOLD = 10;
    static SOURCE_UPDATE_DELAY = 100;

    constructor() {
        this.playerContainer = document.querySelector('.streamit-player-ctrl');
        this.player = null;
        this.countdownState = {
            active: false,
            startTime: 0,
            remaining: 0,
            lastUpdate: 0
        };

        this.initConfigurations();
        this.initPlayer();
        this.initWatchList();
    }


    initConfigurations() {
        if (!this.playerContainer) return;

        this.autoplayConfig = {
            enabled: true,
            countdown: this.timeStringToSeconds(this.playerContainer.dataset.next_overlay_time),
            timer: null
        };

        this.lastUpdateTime = 0;
    }

    // Player Initialization
    initPlayer() {
        this.setupMainPlayer();
        this.setupTrailerPlayer();
    }

    setupMainPlayer() {
        const playerEl = document.getElementById('streamit_player');
        if (!playerEl) return;
    
        try {
            const playerConfig = this.getPlayerConfig();
    
            const controls = Array.isArray(playerConfig.controls) ? playerConfig.controls : [];
    
            this.player = new Plyr('#streamit_player', playerConfig);
            window.streamitPlayerInstance = this.player;

            this.setupPlayerEvents();
            this.addNextEpisodeOverlay();
            this.initializeAdvertisements();
    
            if (controls.includes('sources')) {
                this.setupSourcesMenu();
            }
        } catch (error) {
            console.error('Player initialization error:', error);
        }
    }

    getPlayerConfig() {
        const playerData = this.playerContainer?.dataset || {};
        if (!playerData.playerControls) {
            return {};
        }

        const config = JSON.parse(playerData.playerControls);

        return {
            ...config,
            controls: config.controls ?? [],
            i18n: {
                ...(config.i18n ?? {}),
                ...(playerData.i18n ? JSON.parse(playerData.i18n) : {}),
            },
        };
    }

    getLocalizedText() {
        const playerData = this.playerContainer?.dataset || {};
        return playerData.i18n ? JSON.parse(playerData.i18n) : {};
    }

    // Player Events
    setupPlayerEvents() {
        if (!this.player) return;

        const events = {
            ready: this.handlePlayerReady.bind(this),
            timeupdate: this.handleTimeUpdate.bind(this),
            ended: this.handlePlayerEnded.bind(this),
            play: this.handlePlay.bind(this),
            pause: this.handlePause.bind(this),
            seeked: this.handleSeeked.bind(this)
        };

        Object.entries(events).forEach(([event, handler]) => {
            this.player.on(event, handler);
        });
    }

    handlePlayerReady(event) {
        const plyr = event.detail.plyr;
        this.player = plyr;
        this.setupCountdownHandler();
    }

    setupCountdownHandler() {
        let countdownStarted = false;

        this.player.on('timeupdate', () => {
            const currentTime = this.player.currentTime;
            const duration = this.player.duration;
            const timeLeft = duration - currentTime;
            const nextOverlayTime = this.autoplayConfig.countdown;

            if (!countdownStarted && this.autoplayConfig.enabled) {
                const shouldStart = (nextOverlayTime > 0 && currentTime >= nextOverlayTime) ||
                    (timeLeft <= MediaPlayer.DEFAULT_COUNTDOWN_DURATION && timeLeft > 0);

                if (shouldStart) {
                    countdownStarted = true;
                    this.startFixedCountdown();
                    this.showNextEpisodeOverlay();
                }
            }
        });
    }

    startFixedCountdown() {
        this.clearCountdownTimer();

        const timerElements = document.querySelectorAll('.plyr__next-overlay .timer');
        const nextEpisodeButtons = document.querySelectorAll('.next-episode-button');

        const countdownDuration = MediaPlayer.DEFAULT_COUNTDOWN_DURATION;
        const startTime = performance.now();
        const endTime = startTime + (countdownDuration * 1000);

        // Initialize display
        timerElements.forEach(el => {
            el.textContent = countdownDuration;
        });

        const animate = (currentTime) => {
            if (!this.autoplayConfig.enabled) {
                this.clearCountdownTimer();
                this.hideNextEpisodeOverlay();
                return;
            }

            const elapsed = currentTime - startTime;
            const remaining = Math.max(0, endTime - currentTime);
            const secondsLeft = Math.ceil(remaining / 1000);

            const progress = Math.min(1, elapsed / (countdownDuration * 1000));
            const fillPercentage = 20 + (80 * progress);

            // Update timer display
            timerElements.forEach(el => {
                el.textContent = secondsLeft;
            });

            // Update progress fill
            nextEpisodeButtons.forEach(button => {
                button.style.setProperty('--data-fill', `${fillPercentage}%`);
            });

            if (remaining > 0) {
                this.autoplayConfig.timer = requestAnimationFrame(animate);
            } else {
                this.playNextEpisode();
            }
        };

        this.autoplayConfig.timer = requestAnimationFrame(animate);
    }

    clearCountdownTimer() {
        if (this.autoplayConfig.timer) {
            cancelAnimationFrame(this.autoplayConfig.timer);
            this.autoplayConfig.timer = null;
        }
    }

    resetCountdown() {
        this.clearCountdownTimer();
        this.countdownState.active = false;
        this.hideNextEpisodeOverlay();
    }

    handlePlayerEnded() {
        if (this.autoplayConfig.enabled) {
            this.playNextEpisode();
        }
    }

    handleTimeUpdate(event) {
        const currentTime = event.detail.plyr.currentTime;
        const totalTime = event.detail.plyr.duration;

        if (currentTime > MediaPlayer.MIN_TRACKING_TIME &&
            Math.abs(currentTime - this.lastUpdateTime) > MediaPlayer.TIME_UPDATE_THRESHOLD) {
            this.updateWatchedTime(currentTime, totalTime);
            this.lastUpdateTime = currentTime;
        }
    }

    handlePlay() {
        // No special handling needed
    }

    handlePause() {
        // No special handling needed
    }

    handleSeeked() {
        this.updatePlayerProgress();
    }

    playNextEpisode() {
        const nextEpisode = this.getNextEpisode();
        if (nextEpisode) {
            window.location.href = nextEpisode.url;
        }
    }

    getNextEpisode() {
        if (!this.playerContainer) return null;

        const currentEpisodeId = this.playerContainer.dataset.postId;
        const episodes = Array.from(document.querySelectorAll(
            '.episode-card, .playlist-data-card, [data-episode-id]'
        ));

        if (!episodes.length) return null;

        const currentIndex = episodes.findIndex(episode => {
            const id = episode.dataset.episodeId;
            const isActive = episode.classList.contains('active') || episode.classList.contains('watching');
            return id === currentEpisodeId || isActive;
        });

        if (currentIndex === -1 || currentIndex === episodes.length - 1) return null;

        const nextEpisode = episodes[currentIndex + 1];
        return this.extractEpisodeDetails(nextEpisode);
    }

    extractEpisodeDetails(episodeElement) {
        const link = episodeElement.querySelector('a');
        const titleEl = episodeElement.querySelector('h6, .title, [data-title]');
        const imgEl = episodeElement.querySelector('img, [data-thumbnail]');
        const seasonEl = document.querySelector('.episode-nav-btn .active, [data-season]');

        if (!link || !titleEl || !imgEl) return null;

        return {
            url: link.href,
            seasonNumber: this.extractSeasonNumber(seasonEl),
            title: titleEl.textContent.trim() || titleEl.dataset.title || 'Next Episode',
            thumbnail: imgEl.src || imgEl.dataset.thumbnail
        };
    }

    extractSeasonNumber(seasonEl) {
        if (!seasonEl) return '1';
        return seasonEl.dataset.season ||
            seasonEl.textContent.trim().replace(/^Season\s+/i, '') ||
            '1';
    }

    // Overlay Management
    addNextEpisodeOverlay() {
        this.removeExistingOverlays();

        const nextEpisode = this.getNextEpisode();
        if (!nextEpisode) return;

        const overlay = this.createOverlay(nextEpisode);
        const fullscreenOverlay = overlay.cloneNode(true);
        fullscreenOverlay.classList.add('plyr__next-overlay--fullscreen');

        this.playerContainer.appendChild(overlay);
        this.playerContainer.appendChild(fullscreenOverlay);

        this.setupOverlayEvents(overlay, fullscreenOverlay, nextEpisode);
        this.setupFullscreenSync(overlay, fullscreenOverlay);
    }

    removeExistingOverlays() {
        document.querySelectorAll('.plyr__next-overlay').forEach(el => el.remove());
    }

    createOverlay(nextEpisode) {
        const overlay = document.createElement('div');
        overlay.className = 'plyr__next-overlay';
        overlay.innerHTML = this.getOverlayHTML(nextEpisode);
        return overlay;
    }

    getOverlayHTML(nextEpisode) {
        const template = window.streamitPlayerVars?.nextEpisodeOverlayHTML || '';
        return template
            .replace(/\${nextEpisode\.thumbnail}/g, nextEpisode.thumbnail)
            .replace(/\${nextEpisode\.seasonNumber}/g, nextEpisode.seasonNumber)
            .replace(/\${nextEpisode\.title}/g, this.escapeHtml(nextEpisode.title));
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    setupOverlayEvents(overlay, fullscreenOverlay, nextEpisode) {
        [overlay, fullscreenOverlay].forEach(el => {
            this.setupOverlayButtonEvents(el, nextEpisode);
        });
    }

    setupOverlayButtonEvents(overlay, nextEpisode) {
        const closeBtn = overlay.querySelector('.close-btn');
        const continueBtn = overlay.querySelector('.ep-close-btn');
        const nextEpisodeBtn = overlay.querySelector('.next-episode-button');
        const episodeEl = overlay.querySelector('.next-episode');

        const closeHandler = (e) => {
            e.stopPropagation();
            this.autoplayConfig.enabled = false;
            this.hideNextEpisodeOverlay();
        };

        if (closeBtn) closeBtn.addEventListener('click', closeHandler);
        if (continueBtn) continueBtn.addEventListener('click', closeHandler);

        if (nextEpisodeBtn) {
            nextEpisodeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                window.location.href = nextEpisode.url;
            });
        }

        if (episodeEl) {
            episodeEl.addEventListener('click', () => {
                window.location.href = nextEpisode.url;
            });
        }
    }

    setupFullscreenSync(overlay, fullscreenOverlay) {
        const syncOverlays = () => {
            const isFullscreen = !!(
                document.fullscreenElement ||
                document.webkitFullscreenElement ||
                document.mozFullScreenElement ||
                document.querySelector('.plyr--fullscreen-active, .plyr--fullscreen')
            );

            overlay.style.display = isFullscreen ? 'none' : '';
            fullscreenOverlay.style.display = isFullscreen ? 'block' : 'none';

            if (isFullscreen) {
                const fsElement = document.fullscreenElement ||
                    document.querySelector('.plyr--fullscreen-active, .plyr--fullscreen');

                if (fsElement && !fsElement.contains(fullscreenOverlay)) {
                    fsElement.appendChild(fullscreenOverlay);
                }
            } else if (!this.playerContainer.contains(fullscreenOverlay)) {
                this.playerContainer.appendChild(fullscreenOverlay);
            }
        };

        syncOverlays();

        ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange']
            .forEach(event => document.addEventListener(event, syncOverlays));
    }

    showNextEpisodeOverlay() {
        let attempts = 0;
        const maxAttempts = 3;

        const tryShowOverlay = () => {
            attempts++;
            const overlays = document.querySelectorAll('.plyr__next-overlay, .plyr__next-overlay--fullscreen');

            if (!overlays.length && attempts < maxAttempts) {
                this.addNextEpisodeOverlay();
                setTimeout(tryShowOverlay, 500);
                return;
            }

            if (!overlays.length || !this.autoplayConfig.enabled) return;

            overlays.forEach(overlay => overlay.classList.add('is-active'));
            document.querySelectorAll('.next-episode-button').forEach(button => {
                button.style.setProperty('--data-fill', '20%');
            });
        };

        tryShowOverlay();
    }

    hideNextEpisodeOverlay() {
        document.querySelectorAll('.plyr__next-overlay').forEach(overlay => {
            overlay.classList.remove('is-active');
        });
        this.clearCountdownTimer();
    }

    // Advertisement Management
    initializeAdvertisements() {
        const adsEnabled = this.playerContainer?.dataset.enabled === 'true';
        if (!adsEnabled || typeof window.LS_AdvertisementManager === 'undefined') return;

        const adOptions = {
            isLiveStream: this.playerContainer?.dataset.isLive === 'true',
            prerollEnabled: this.playerContainer?.dataset.prerollenabled === 'true',
            midrollEnabled: this.playerContainer?.dataset.midrollenabled === 'true',
            postrollEnabled: this.playerContainer?.dataset.postrollEnabled === 'true',
            adFrequency: parseInt(this.playerContainer?.dataset.adFrequency) || 5,
            htmlAds: this.playerContainer?.dataset.htmlAds ? JSON.parse(this.playerContainer.dataset.htmlAds) : {},
            i18n: this.getLocalizedText()
        };

        const adsType = this.playerContainer?.dataset.adsType;
        const vastUrl = this.playerContainer?.dataset.vastUrl;

        if (adsType === 'vast' && vastUrl) {
            adOptions.vastAdUrls = { any: [vastUrl] };
        }

        try {
            window.streamitAdManager = new window.LS_AdvertisementManager(this.player, adOptions);
        } catch (error) {
            console.error('Advertisement manager error:', error);
        }
    }

    // Sources Management
    setupSourcesMenu() {
        const sources = this.playerContainer?.dataset.sources ?
            JSON.parse(this.playerContainer.dataset.sources) : [];

        if (!sources.length) return;

        const checkAndAddControls = () => {
            if (!this.player?.elements?.controls) {
                setTimeout(checkAndAddControls, 200);
                return;
            }

            const sourcesMenu = this.createSourcesMenu(sources);
            const sourcesButton = this.createSourcesButton();

            const controls = this.player.elements.controls;

            // Insert before the last child (so it becomes second-last)
            controls.insertBefore(sourcesButton, controls.lastElementChild);
            controls.insertBefore(sourcesMenu, controls.lastElementChild);

            this.setupSourcesMenuEvents(sourcesMenu, sourcesButton);
        };

        checkAndAddControls();
    }

    createSourcesMenu(sources) {
        const menu = document.createElement('div');
        menu.className = 'plyr__sources';
        menu.innerHTML = `
            <div class="plyr__sources__header">
                <h5>${this.getLocalizedText().sources || 'Sources'}</h5>
            </div>
            <ul class="plyr__sources__list">
                ${sources.map((source, index) => this.createSourceItem(source, index)).join('')}
            </ul>
        `;
        return menu;
    }

    createSourceItem(source, index) {
        if (!source.name || !source.content) return '';

        return `
            <li class="plyr__sources__item">
                <button type="button" class="plyr__sources__button" data-source-index="${index}">
                    ${this.escapeHtml(source.name)}
                    ${source.quality ? `<span class="source-quality">(${this.escapeHtml(source.quality)})</span>` : ''}
                    ${source.language ? `<span class="source-language">${this.escapeHtml(source.language)}</span>` : ''}
                </button>
            </li>
        `;
    }

    createSourcesButton() {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'plyr__control plyr__control--sources';
        button.setAttribute('data-plyr', 'sources');
        button.setAttribute('aria-label', this.getLocalizedText().sources || 'Sources');
        button.innerHTML = this.getLocalizedText().sourcesIcon || this.getDefaultSourcesIcon();
        return button;
    }

    getDefaultSourcesIcon() {
        return `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="m3 8h10a1 1 0 0 0 2 0v-2a1 1 0 0 0 -2 0h-10a1 1 0 0 0 0 2z"/>
            <path d="m17 8h4a1 1 0 0 0 0-2h-4a1 1 0 0 0 0 2z"/>
            <path d="m21 16h-7a1 1 0 0 0 0 2h7a1 1 0 0 0 0-2z"/>
            <path d="m11 15a1 1 0 0 0 -1 1h-7a1 1 0 0 0 0 2h7a1 1 0 0 0 2 0v-2a1 1 0 0 0 -1-1z"/>
            <path d="m21 11h-11a1 1 0 0 0 0 2h11a1 1 0 0 0 0-2z"/>
            <path d="m3 13h3a1 1 0 0 0 2 0v-2a1 1 0 0 0 -2 0h-3a1 1 0 0 0 0 2z"/>
        </svg>`;
    }

    setupSourcesMenuEvents(menu, button) {
        button.addEventListener('click', (e) => {
            e.stopPropagation();

            // Close any other open source menus
            document.querySelectorAll('.plyr__sources--active').forEach((openMenu) => {
                if (openMenu !== menu) {
                    openMenu.classList.remove('plyr__sources--active');
                }
            });

            menu.classList.toggle('plyr__sources--active');
        });

        document.addEventListener('click', (e) => {
            if (!menu.contains(e.target) && !button.contains(e.target)) {
                menu.classList.remove('plyr__sources--active');
            }
        });

        menu.querySelectorAll('.plyr__sources__button').forEach((btn, index) => {
            btn.addEventListener('click', () => {
                const sources = this.playerContainer?.dataset.sources ?
                    JSON.parse(this.playerContainer.dataset.sources) : [];

                if (sources[index]) {
                    this.switchSource(sources[index]);
                }
            });
        });
    }


    switchSource(source) {
        if (!source?.content) return;

        try {
            const currentTime = this.player.currentTime;
            const wasPlaying = !this.player.paused;
            const currentSourceName = source.name;

            this.player.destroy();
            this.playerContainer.innerHTML = source.content;

            this.player = new Plyr('#streamit_player', this.getPlayerConfig());
            window.streamitPlayerInstance = this.player;

            this.player.once('ready', () => {
                if (currentTime > 0) this.player.currentTime = currentTime;
                if (wasPlaying) this.player.play();

                setTimeout(() => {
                    if (this.getPlayerConfig().controls?.includes('sources')) {
                        this.setupSourcesMenu();
                        this.highlightActiveSource(currentSourceName);
                    }
                }, MediaPlayer.SOURCE_UPDATE_DELAY);
            });

            this.setupPlayerEvents();
        } catch (error) {
            console.error('Source switching error:', error);
        }
    }

    highlightActiveSource(sourceName) {
        document.querySelectorAll('.plyr__sources__button').forEach(btn => {
            btn.classList.toggle('active', btn.textContent.trim().startsWith(sourceName));
        });
    }

    // Trailer Player
    setupTrailerPlayer() {
        const trailerContainer = document.querySelector('.streamit-trailer-player-ctrl');
        if (!trailerContainer) return;

        setTimeout(() => {
            try {
                const player = new Plyr('.streamit_trailer_player', {
                    controls: [],
                    autoplay: true,
                    muted: true,
                    clickToPlay: false,
                    fullscreen: { enabled: true, fallback: true, iosNative: true }
                });

                player.muted = true;

                player.on('ready', () => {
                    player.play();
                    document.querySelector('.video-section')?.classList.add('trailer-play');

                    this.setupTrailerCustomControls(player);
                });

                player.on('error', console.error);
            } catch (error) {
                console.error('Trailer player error:', error);
            }
        }, 2000);
    }

    setupTrailerCustomControls(player) {
        const fullscreenBtn = document.getElementById('trailer-fullscreen-toggle');
        const muteBtn = document.getElementById('trailer-mute-toggle');

        if (fullscreenBtn) {
            fullscreenBtn.style.display = 'block';
            this.setupTrailerFullscreenToggle(player, fullscreenBtn, muteBtn);
        }

        if (muteBtn) {
            muteBtn.style.display = 'block';
            this.setupTrailerMuteToggle(player, muteBtn);
        }
    }

    setupTrailerFullscreenToggle(player, fullscreenBtn, muteBtn) {
        const fsParent = fullscreenBtn.parentElement;
        const fsNext = fullscreenBtn.nextSibling;
        const muteParent = muteBtn?.parentElement;
        const muteNext = muteBtn?.nextSibling;

        fullscreenBtn.addEventListener('click', () => {
            if (player.fullscreen.active) {
                player.fullscreen.exit();
            } else {
                player.fullscreen.enter();
            }
        });

        player.on('enterfullscreen', () => {
            player.elements.container.classList.add('st-fullscreen-trailer');
            player.elements.container.appendChild(fullscreenBtn);
            if (muteBtn) player.elements.container.appendChild(muteBtn);
            fullscreenBtn.innerHTML = '<i class="icon-minimize"></i>';
        });

        player.on('exitfullscreen', () => {
            if (fsParent) fsParent.insertBefore(fullscreenBtn, fsNext);
            if (muteBtn && muteParent) muteParent.insertBefore(muteBtn, muteNext);
            player.elements.container.classList.remove('st-fullscreen-trailer');
            fullscreenBtn.innerHTML = '<i class="icon-fullscreen"></i>';
        });
    }

    setupTrailerMuteToggle(player, muteBtn) {
        muteBtn.addEventListener('click', () => {
            player.muted = !player.muted;
            muteBtn.innerHTML = player.muted
                ? '<i class="icon-volume-slash"></i>'
                : '<i class="icon-volume-high"></i>';
        });
    }

    // Watch List and Time Tracking
    initWatchList() {
        const currentTime = parseInt(this.playerContainer?.dataset.currentTime) || 0;
        if (currentTime > 5 && this.player) {
            this.player.currentTime = currentTime;
        }

        this.setupTimeTracking();
    }

    setupTimeTracking() {
        if (!this.player) return;

        this.player.on('timeupdate', this.handleTimeUpdate.bind(this));
        this.player.on('play', () => this.updatePlayerProgress());
        this.player.on('pause', () => this.updatePlayerProgress());
    }

    updatePlayerProgress() {
        if (!this.player) return;
        this.updateWatchedTime(this.player.currentTime, this.player.duration);
    }

    async updateWatchedTime(currentTime, totalTime) {
        if (!this.playerContainer) return;

        const data = {
            watched_time: currentTime,
            post_type: this.playerContainer.dataset.postType,
            user_id: this.playerContainer.dataset.userId,
            watched_total_time: totalTime,
            post_id: this.playerContainer.dataset.postId
        };

        try {
            await post('contine_watched_update', data);
        } catch (error) {
            console.error('Time tracking error:', error);
        }
    }

    // Utility Methods
    timeStringToSeconds(timeString) {
        if (!timeString) return 0;

        const parts = timeString.split(':');
        if (parts.length !== 3) return 0;

        const hours = parseInt(parts[0]) || 0;
        const minutes = parseInt(parts[1]) || 0;
        const seconds = parseInt(parts[2]) || 0;

        return (hours * 3600) + (minutes * 60) + seconds;
    }

}