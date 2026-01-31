import { post } from "../utilities/ajax";
import * as bootstrap from 'bootstrap';

export default class Authentication {
    constructor() {
        this.heartbeatActive = false;
        this.debounceTimeouts = new Map();
        this.selectors = {
            mediaPlayer: '.streamit-player-ctrl',
            forms: {
                registration: '#streamit-registration-form',
                login: '#streamit-login-form',
                forgotPassword: '#streamit-forgot-password-form',
                profileEdit: '#st_profile_edit'
            }
        };

        this.setupEventHandlers();
        this.ShowPasswordIcone();
        this.initializeDeviceWrapper();
        this.initializeProfileDeviceManagement();
        this.loadUserDevicesForProfile();

        // Initialize session tracking for media player
        this.initializeSessionTracking();
        this.initializeHeartbeat();
    }

    setupEventHandlers() {
        const eventHandlers = [
            { selector: "#streamit-registration-form", event: "submit", handler: this.SubmitRegistration.bind(this) },
            { selector: "#streamit-login-form", event: "submit", handler: this.SubmitLogin.bind(this) },
            { selector: "#streamit-forgot-password-form", event: "submit", handler: this.SubmitForgotPassword.bind(this) },
            { selector: "#st_profile_edit", event: "submit", handler: this.ProfileEdit.bind(this) },
            { selector: "#st_password_change", event: "submit", handler: this.SubmitPasswordChange.bind(this) },
            // { selector: "#confirmDeleteAccountBtn", event: "submit", handler: this.SubmitDeleteAccount.bind(this) },
            { selector: ".device-logout-btn", event: "click", handler: this.showLogoutSingleConfirmation.bind(this) },
            { selector: ".logout-all-devices-btn", event: "click", handler: this.showLogoutAllConfirmation.bind(this) },
            { selector: "#delete-account-btn", event: "click", handler: this.showDeleteAccountConfirmation.bind(this) },
            { selector: "#confirmLogoutAllBtn", event: "click", handler: this.handleLogoutAllDevices.bind(this) },
            { selector: "#confirmLogoutSingleBtn", event: "click", handler: this.handleConfirmLogoutSingle.bind(this) }
        ];

        eventHandlers.forEach(({ selector, event, handler }) => {
            document.addEventListener(event, (e) => {
                if (e.target.matches(selector) || e.target.closest(selector)) {
                    handler(e);
                }
            });
        });
    }

    // ===== DEVICE WRAPPER INITIALIZATION =====
    initializeDeviceWrapper() {
        this.deviceWrapper = document.querySelector('.login-device-wrapper');
        if (!this.deviceWrapper) return;

        this.deviceHeading = this.deviceWrapper.querySelector('h5');
        this.loadingState = this.deviceWrapper.querySelector('.device-loading-state');
        this.successState = this.deviceWrapper.querySelector('.device-success-state');
        this.deviceList = this.deviceWrapper.querySelector('.login-device-list');
        this.deviceTemplate = document.getElementById('device-item-template');
    }

    // ===== PROFILE DEVICE MANAGEMENT INITIALIZATION =====
    initializeProfileDeviceManagement() {
        this.profileDevicesList = document.getElementById('devices-list');
        this.profileDeviceTemplate = document.getElementById('device-item-template');
        this.deviceLimitWarning = document.getElementById('device-limit-warning');
        this.activeDevices = document.querySelector('.active-devices');
        this.confirmLogoutSingleBtn = document.getElementById('confirmLogoutSingleBtn');
    }

    // ===== HEARTBEAT INITIALIZATION =====
    initializeHeartbeat() {
        const storedValue = localStorage.getItem('streamit_player_was_logged_in');
        const wasLoggedIn = storedValue === 'true' || storedValue === true;

        if (!wasLoggedIn) return;

        if (document.querySelector(this.selectors.mediaPlayer)) {
            this.startHeartbeat();
        }
    }

    // ===== SESSION TRACKING INITIALIZATION =====
    initializeSessionTracking() {
        if (!document.querySelector(this.selectors.mediaPlayer)) return;

        const currentUserId = document.getElementById('current-user-id')?.value;
        if (currentUserId && currentUserId !== '0') {
            this.storeSessionKey();
            localStorage.setItem('streamit_player_was_logged_in', 'true');
        } else {
            this.clearSessionKey();
            localStorage.removeItem('streamit_player_was_logged_in');
        }
    }

    storeSessionKey() {
        const sessionKey = this.getCurrentSessionKey();
        if (sessionKey) {
            localStorage.setItem('streamit_player_session_key', sessionKey);
            localStorage.setItem('streamit_player_session_timestamp', Date.now().toString());
        }
    }

    clearSessionKey() {
        localStorage.removeItem('streamit_player_session_key');
        localStorage.removeItem('streamit_player_session_timestamp');
        localStorage.removeItem('streamit_player_was_logged_in');
    }

    getCurrentSessionKey() {
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'wordpress_logged_in_' + this.getCookieHash()) {
                const parts = value.split('|');
                if (parts.length >= 2) return parts[0];
            }
        }
        return null;
    }

    getCookieHash() {
        try {
            return btoa(window.location.hostname).replace(/[^a-zA-Z0-9]/g, '').substring(0, 32);
        } catch (error) {
            console.warn('Error generating cookie hash:', error);
            return 'default';
        }
    }

    checkSessionValidity(currentSessionKey) {
        try {
            const storedSessionKey = localStorage.getItem('streamit_player_session_key');
            if (!storedSessionKey) return true;
            return storedSessionKey === currentSessionKey;
        } catch (error) {
            return true;
        }
    }

    showYouAreRemovedModal(isPaidContent = false) {
        if (this.modalShown) return;

        this.modalShown = true;
        this.stopAllPlayers();

        const modal = document.getElementById('youAreRemovedModal');
        if (!modal) return;
        // Show modal using Bootstrap
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        const closeModal = () => {
            bsModal.hide();
            window.history.back();
        };

        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        const closeBtn = modal.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        this.clearSessionKey();
        this.stopHeartbeat();
    }

    // ===== VIDEO PLAYER CONTROL =====
    stopAllPlayers() {
        try {
            // Stop HLS video player
            const hlsVideo = document.getElementById('ls_video_player');
            if (hlsVideo?.pause) hlsVideo.pause();

            // Stop all HTML5 video elements
            document.querySelectorAll('video').forEach(video => {
                if (video.pause) video.pause();
            });

            // Hide iframe players
            document.querySelectorAll('iframe[src*="youtube"], iframe[src*="vimeo"]').forEach(iframe => {
                iframe.style.display = 'none';
            });

            // Hide player containers
            const streamitPlayer = document.querySelector('.streamit-player-ctrl');
            if (streamitPlayer) streamitPlayer.style.display = 'none';

            document.querySelectorAll('.ls-video-container, .plyr').forEach(container => {
                container.style.display = 'none';
            });
        } catch (error) {
            console.error(error);
        }
    }

    // ===== UNIFIED FORM SUBMISSION METHOD =====
    async submitForm(formId, endpoint, options = {}) {
        const form = document.getElementById(formId);
        if (!form) return;

        this.clearFieldErrors();

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) this.showLoader(submitBtn);

        try {
            let formData;
            if (options.isFormData) {
                formData = new FormData(form);
                if (options.handleFiles) {
                    const fileInput = form.querySelector('input[type="file"]');
                    if (fileInput?.files[0]) {
                        formData.append(fileInput.name, fileInput.files[0]);
                    }
                }
            } else {
                formData = Object.fromEntries(new FormData(form));

                if (options.handleCheckbox) {
                    const checkbox = form.querySelector(options.checkboxSelector);
                    if (checkbox) {
                        formData[checkbox.name] = checkbox.checked ? checkbox.value : "not_accepted";
                    }
                }
            }

            const response = await post(endpoint, formData);

            if (submitBtn) this.hideLoader(submitBtn);

            if (response.status) {
                if (options.onSuccess) {
                    options.onSuccess(response);
                } else {
                    this.handleSuccessResponse(response, options);
                }
            } else {
                this.handleErrorResponse(response, options);
            }
        } catch (error) {
            if (submitBtn) this.hideLoader(submitBtn);
            this.handleErrorResponse(error, options);
        }
    }

    // ===== UNIFIED LOADER MANAGEMENT =====
    showLoader(button) {
        const btnText = button.querySelector('.btn-text');
        const btnLoader = button.querySelector('.btn-loader');

        if (btnText && btnLoader) {
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline-flex';
            button.disabled = true;
        }
    }

    hideLoader(button) {
        const btnText = button.querySelector('.btn-text');
        const btnLoader = button.querySelector('.btn-loader');

        if (btnText && btnLoader) {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';
            button.disabled = false;
        }

        // remove any success tick icon if present
        const successIcon = button.querySelector('.btn-success-icon');
        if (successIcon) successIcon.remove();
    }

    // ===== UNIFIED RESPONSE HANDLING =====
    handleSuccessResponse(response, options) {
        if (response.message) {
            if (options.showSuccessInButton) {
                this.showSuccessInButton(options.showSuccessInButton, response.message);
            } else {
                this.displayGeneralSuccess(response.message);
            }
        }

        if (options.redirectDelay !== undefined) {
            setTimeout(() => {
                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                } else if (options.reloadOnSuccess) {
                    location.reload();
                }
            }, options.redirectDelay);
        }
    }

    handleErrorResponse(response, options) {
        if (response.show_device_management) {
            this.handleDeviceLimitExceeded(response);
            return;
        }

        if (response.field_errors && typeof response.field_errors === 'object') {
            const fieldErrors = {};
            Object.keys(response.field_errors).forEach(key => {
                if (key === 'general') {
                    this.displayGeneralError(response.field_errors[key]);
                } else if (key === 'user_avatar') {
                    this.displayAvatarError(response.field_errors[key]);
                } else {
                    fieldErrors[key] = response.field_errors[key];
                }
            });

            if (Object.keys(fieldErrors).length > 0) {
                this.displayFieldErrors(fieldErrors);
            }
        }

        if (response.message && (!response.field_errors || Object.keys(response.field_errors).length === 0)) {
            this.displayGeneralError(response.message);
        }
    }

    // ===== DEVICE MANAGEMENT HANDLING =====
    handleDeviceLimitExceeded(response) {
        const loginForm = document.getElementById('streamit-login-form');
        if (loginForm) loginForm.style.display = 'none';

        if (this.deviceWrapper) {
            this.deviceWrapper.style.display = 'block';
            if (this.loadingState) this.loadingState.style.display = 'block';
            if (this.deviceList) this.deviceList.style.display = 'none';
        }

        if (response.device_management_endpoint && response.username) {
            this.fetchUserDevices(response.device_management_endpoint, response.username);
        } else if (response.devices) {
            this.showDeviceManagement(response.devices, response.username);
        }
    }

    async fetchUserDevices(endpoint, username) {
        if (this.deviceHeading) this.deviceHeading.style.display = 'none';

        const passwordInput = document.querySelector('input[name="user_password"]');
        const nonceInput = document.querySelector('input[name="st_login_nonce"]');

        const formData = {
            action: 'st_ajax_post',
            username: username,
            password: passwordInput?.value || '',
            st_login_nonce: nonceInput?.value || ''
        };

        this.loginAttempt = { username, password: formData.password };

        try {
            const response = await post(endpoint, formData);
            if (response.status && response.devices) {
                this.loginDeviceStats = response.stats || null;
                this.showDeviceManagement(response.devices, username, this.loginDeviceStats);
            } else {
                this.hideDeviceLoadingState();
            }
        } catch (error) {
            this.hideDeviceLoadingState();
        }
    }

    showDeviceManagement(devices, username, stats) {
        if (!this.deviceWrapper || !this.deviceTemplate) return;

        const needToLogout = this.processDeviceLimit({ devices, stats });
        this.requiredDeviceRemovals = needToLogout;
        this.devicesLoggedOut = 0;

        if (this.deviceHeading) this.deviceHeading.style.display = 'block';
        this.hideDeviceLoadingState();

        if (this.deviceList) this.deviceList.innerHTML = '';

        if (devices?.length > 0) {
            devices.forEach(device => {
                if (device.is_current_device) return;

                const deviceItem = this.deviceTemplate.content.cloneNode(true);
                const deviceElement = deviceItem.querySelector('.login-device-item, .device-item') || deviceItem.firstElementChild;

                this.setupDeviceItem(deviceElement, device, username);
                if (this.deviceList) this.deviceList.appendChild(deviceElement);
            });
        }

        if (this.deviceWrapper) this.deviceWrapper.style.display = 'block';
    }

    setupDeviceItem(deviceElement, device, username) {
        const deviceIcon = deviceElement.querySelector('.device-icon');
        const deviceName = deviceElement.querySelector('.device-name');
        const lastUsed = deviceElement.querySelector('.last-used');
        const logoutBtn = deviceElement.querySelector('.device-logout-btn');

        if (deviceIcon) {
            const iconClass = (device.type === 'mobile' || device.type === 'app') ?
                'icon-device-mobile' : 'icon-device-desktop';
            deviceIcon.className = `${iconClass} fs-4`;
        }

        if (deviceName) deviceName.textContent = device.device_name || 'Unknown Device';
        if (lastUsed) lastUsed.textContent = this.formatLastUsedTime(device.session_created_at);

        if (logoutBtn) {
            logoutBtn.setAttribute('data-device-id', device.device_id || '');
            logoutBtn.setAttribute('data-username', username || '');
        }
    }

    hideDeviceLoadingState() {
        if (this.loadingState) this.loadingState.style.display = 'none';
        if (this.deviceList) this.deviceList.style.display = 'block';
    }

    formatLastUsedTime(loginTime) {
        if (!loginTime) return 'Unknown';

        let loginDate;
        try {
            let iso = loginTime.trim().replace(' ', 'T');
            if (!/[zZ]|[+-]\d{2}:?\d{2}$/.test(iso)) iso += 'Z';
            loginDate = new Date(iso);
        } catch (error) {
            return 'Unknown';
        }

        if (isNaN(loginDate.getTime())) return 'Unknown';

        const now = new Date();
        const timeDiff = now.getTime() - loginDate.getTime();

        const intervals = {
            year: 31536000000,
            month: 2592000000,
            week: 604800000,
            day: 86400000,
            hour: 3600000,
            minute: 60000
        };

        if (timeDiff < intervals.minute) return 'Just now';
        if (timeDiff < intervals.hour) return `${Math.floor(timeDiff / intervals.minute)} minute${Math.floor(timeDiff / intervals.minute) > 1 ? 's' : ''} ago`;
        if (timeDiff < intervals.day) return `${Math.floor(timeDiff / intervals.hour)} hour${Math.floor(timeDiff / intervals.hour) > 1 ? 's' : ''} ago`;
        if (timeDiff < intervals.week) return `${Math.floor(timeDiff / intervals.day)} day${Math.floor(timeDiff / intervals.day) > 1 ? 's' : ''} ago`;
        if (timeDiff < intervals.month) return `${Math.floor(timeDiff / intervals.week)} week${Math.floor(timeDiff / intervals.week) > 1 ? 's' : ''} ago`;
        if (timeDiff < intervals.year) return `${Math.floor(timeDiff / intervals.month)} month${Math.floor(timeDiff / intervals.month) > 1 ? 's' : ''} ago`;

        return `${Math.floor(timeDiff / intervals.year)} year${Math.floor(timeDiff / intervals.year) > 1 ? 's' : ''} ago`;
    }

    processDeviceLimit({ devices = null, stats = null, remaining = null } = {}) {
        let count = remaining;

        if (count === null) {
            const active = Array.isArray(devices) ? devices.length : 0;
            const total = (stats?.total_limit === 'unlimited' || stats?.total_limit === 0)
                ? Infinity
                : Number(stats?.total_limit ?? Infinity);
            count = !isFinite(total) ? 0 : Math.max(0, (active + 1) - total);
        }

        const template = count === 1 ?
            'Log Out 1 Device to Continue' :
            `Log Out ${count} Devices to Continue`;

        if (this.deviceHeading) this.deviceHeading.textContent = template;

        return count;
    }

    async handleDeviceLogout(event) {
        event.preventDefault();
        const button = event.target.closest('.device-logout-btn');
        if (!button) return;

        const deviceId = button.getAttribute('data-device-id');
        const username = button.getAttribute('data-username');
        const isDeviceTemplateLoad = button.getAttribute('data-is-device-template-load') === 'true';
        const userId = button.getAttribute('data-user-id');

        if (!deviceId) return;

        this.setButtonLoadingState(button, true, 'Logging out...');

        let formData;
        let password = '';

        if (userId) {
            formData = {
                action: 'st_ajax_post',
                user_id: userId,
                device_id: deviceId
            };
        } else {
            const passwordInput = document.querySelector('input[name="user_password"]');
            const nonceInput = document.querySelector('input[name="st_login_nonce"]');

            password = passwordInput?.value || '';
            formData = {
                action: 'st_ajax_post',
                username: username,
                password: password,
                device_id: deviceId,
                st_login_nonce: nonceInput?.value || ''
            };
        }

        try {
            const response = await post('st-user-remove-device', formData);
            if (response?.status) {
        
                // Count device removals
                this.devicesLoggedOut = (this.devicesLoggedOut || 0) + 1;
                // Remove device item
                const deviceItem = button.closest('.login-device-item, .device-item, li, div');
                if (deviceItem) deviceItem.remove();
        
                // Remaining devices
                if (typeof this.requiredDeviceRemovals === 'number') {
                    const remaining = Math.max(0, this.requiredDeviceRemovals - this.devicesLoggedOut);
        
                    this.processDeviceLimit({ remaining });
                }
                this.requiredDeviceRemovals = parseInt(this.requiredDeviceRemovals) || 0;
        
                if (this.devicesLoggedOut >= this.requiredDeviceRemovals) {
        
                    if (isDeviceTemplateLoad) {
                        this.showDeviceLimitLoader();
                        window.location.reload();
                    } else if (userId) {
                        this.loadUserDevicesForProfile();
                    } else {
                        this.autoLoginAfterDeviceRemoval(username, password);
                    }
        
                }
            }
        
        } catch (error) {
            console.error("ERROR:", error);
        
        } finally {
            this.setButtonLoadingState(button, false);
        }  
    }

    setButtonLoadingState(button, isLoading, loadingText = '') {
        const btnText = button.querySelector('.btn-text');
        const btnLoader = button.querySelector('.btn-loader');

        if (isLoading) {
            button.disabled = true;
            if (btnText && btnLoader) {
                btnText.style.display = 'none';
                btnLoader.style.display = 'inline-flex';
                if (loadingText) btnText.textContent = loadingText;
            } else if (loadingText) {
                button.textContent = loadingText;
            }
        } else {
            button.disabled = false;
            if (btnText && btnLoader) {
                btnText.style.display = 'inline';
                btnLoader.style.display = 'none';
            }
        }
    }

    // ===== LOGOUT SINGLE DEVICE (PROFILE CONFIRMATION MODAL) =====
    showLogoutSingleConfirmation(event) {
        if (!this.profileDevicesList) {
            return this.handleDeviceLogout(event);
        }

        const button = event.target.closest('.device-logout-btn');
        const deviceId = button?.getAttribute('data-device-id');
        if (!deviceId) return;

        this.resetLogoutSingleButtonState();
        if (this.confirmLogoutSingleBtn) {
            this.confirmLogoutSingleBtn.setAttribute('data-device-id', deviceId);
        }
        const modal = document.getElementById('logoutSingleDeviceModal');
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    }

    resetLogoutSingleButtonState() {
        if (!this.confirmLogoutSingleBtn) return;

        const btnText = this.confirmLogoutSingleBtn.querySelector('.btn-text');
        const btnLoader = this.confirmLogoutSingleBtn.querySelector('.btn-loader');

        if (btnText && btnLoader) {
            btnText.textContent = 'Logout Device';
            btnText.style.display = 'block';
            btnLoader.style.display = 'none';
        }
        this.confirmLogoutSingleBtn.disabled = false;
        this.confirmLogoutSingleBtn.removeAttribute('data-device-id');
    }

    async handleConfirmLogoutSingle() {
        if (!this.profileDevicesList || !this.confirmLogoutSingleBtn) return;

        const deviceId = this.confirmLogoutSingleBtn.getAttribute('data-device-id');
        const userId = document.getElementById('current-user-id')?.value;

        if (!deviceId || !userId) return;

        this.setButtonLoadingState(this.confirmLogoutSingleBtn, true, 'Logging out...');

        const formData = {
            action: 'st_ajax_post',
            user_id: userId,
            device_id: deviceId
        };

        try {
            const response = await post('st-user-remove-device', formData);
            if (response?.status) {
                setTimeout(() => {
                    const modal = document.getElementById('logoutSingleDeviceModal');
                    if (modal) {
                        const bsModal = bootstrap.Modal.getInstance(modal);
                        if (bsModal) bsModal.hide();
                    }
                    this.displayProfileSuccess(response.message);
                    this.loadUserDevicesForProfile();
                }, 300);
            } else {
                this.resetLogoutSingleButtonState();
            }
        } catch (error) {
            this.resetLogoutSingleButtonState();
        }
    }

    async autoLoginAfterDeviceRemoval(username, password) {
        this.showAutoLoginLoadingState();

        const nonceInput = document.querySelector('input[name="st_login_nonce"]');
        const formData = {
            action: 'st_ajax_post',
            user_username: username,
            user_password: password,
            st_login_nonce: nonceInput?.value || ''
        };

        try {
            const response = await post('st-user-login', formData);
            if (response.status) {
                this.hideAutoLoginLoadingState();
                this.showAutoLoginSuccessState();

                setTimeout(() => {
                    if (response.redirect_url) {
                        window.location.href = response.redirect_url;
                    } else {
                        location.reload();
                    }
                }, 500);
            } else {
                this.hideAutoLoginLoadingState();
                this.showLoginForm();
            }
        } catch (error) {
            this.hideAutoLoginLoadingState();
            this.showLoginForm();
        }
    }

    showAutoLoginLoadingState() {
        if (this.deviceHeading) this.deviceHeading.style.display = 'none';
        if (this.loadingState) this.loadingState.style.display = 'block';
        if (this.deviceList) this.deviceList.style.display = 'none';
    }

    hideAutoLoginLoadingState() {
        if (this.loadingState) this.loadingState.style.display = 'none';
    }

    showAutoLoginSuccessState() {
        if (this.deviceHeading) this.deviceHeading.style.display = 'none';
        if (this.loadingState) this.loadingState.style.display = 'none';
        if (this.deviceList) this.deviceList.style.display = 'none';
        if (this.successState) this.successState.style.display = 'block';
    }

    showLoginForm() {
        const loginForm = document.getElementById('streamit-login-form');
        if (loginForm) loginForm.style.display = 'block';
        if (this.deviceWrapper) this.deviceWrapper.style.display = 'none';
        this.clearGeneralError();
    }

    showDeviceLimitLoader() {
        const deviceList = document.querySelector('.login-device-list');
        const deviceLoader = document.querySelector('.device-logout-loader');

        if (deviceList) deviceList.style.display = 'none';
        if (deviceLoader) deviceLoader.style.display = 'block';

        document.querySelectorAll('.device-logout-btn').forEach(btn => {
            btn.disabled = true;
        });
    }

    // ===== PROFILE DEVICE MANAGEMENT =====
    loadUserDevicesForProfile() {
        if (!this.profileDevicesList) return;
        const userId = document.getElementById('current-user-id')?.value;
        if (userId) this.fetchProfileUserDevices(userId);
    }

    async fetchProfileUserDevices(userId) {
        const formData = { user_id: userId };

        try {
            const response = await post('st-user-get-devices-with-stats', formData);
            if (response.status && response.devices && response.stats) {
                this.displayProfileDevices(response.devices, response.stats, response.plan_name);
            }
        } catch (error) {
            console.error(error);
            this.hideDeviceLoadingSpinner();
        }
    }

    hideDeviceLoadingSpinner() {
        const spinner = document.getElementById('device-loading-spinner');
        const content = document.getElementById('device-management-content');
        const activeSection = document.getElementById('active-devices-section');

        if (spinner) spinner.style.display = 'none';
        if (content) content.style.display = 'block';
        if (activeSection) activeSection.style.display = 'block';
    }

    displayProfileDevices(devices, stats, planName) {
        this.hideDeviceLoadingSpinner();

        this.updateDeviceStats(stats, planName);

        if (this.profileDevicesList) this.profileDevicesList.innerHTML = '';

        if (!devices?.length) return;

        const sortedDevices = [...devices].sort((a, b) => {
            if (a.is_current_device && !b.is_current_device) return -1;
            if (!a.is_current_device && b.is_current_device) return 1;
            return 0;
        });

        const nonCurrentDevices = sortedDevices.filter(device => !device.is_current_device);
        const logoutAllBtn = document.querySelector('.logout-all-devices-btn');

        if (logoutAllBtn) {
            logoutAllBtn.style.display = nonCurrentDevices.length === 0 ? 'none' : 'block';
        }

        sortedDevices.forEach(device => {
            if (!this.profileDeviceTemplate) return;

            const deviceItem = this.profileDeviceTemplate.content.cloneNode(true);
            const deviceElement = deviceItem.querySelector('.login-device-card-2, .device-item') || deviceItem.firstElementChild;

            this.setupProfileDeviceItem(deviceElement, device);
            if (this.profileDevicesList) this.profileDevicesList.appendChild(deviceElement);
        });
    }

    setupProfileDeviceItem(deviceElement, device) {
        const deviceIcon = deviceElement.querySelector('.device-icon');
        const deviceName = deviceElement.querySelector('.device-name');
        const lastUsed = deviceElement.querySelector('.last-used');
        const logoutBtn = deviceElement.querySelector('.device-logout-btn');
        const currentBadge = deviceElement.querySelector('.current-device-badge');

        if (deviceIcon) {
            const iconClass = (device.type === 'mobile' || device.type === 'app') ?
                'icon-device-mobile' : 'icon-device-desktop';
            deviceIcon.className = `${iconClass} fs-3`;
        }

        if (deviceName) deviceName.textContent = device.device_name || 'Unknown Device';
        if (lastUsed) lastUsed.textContent = `Last active: ${this.formatLastUsedTime(device.session_created_at)}`;

        if (device.is_current_device) {
            if (currentBadge) currentBadge.style.display = 'block';
            if (logoutBtn) logoutBtn.style.display = 'none';
            deviceElement.classList.add('active');
        } else if (logoutBtn) {
            logoutBtn.setAttribute('data-device-id', device.device_id || '');
        }
    }

    updateDeviceStats(stats, planName) {
        const planNameElement = document.getElementById('plan-name');
        const usageSection = document.getElementById('device-usage-progress-section');
        const usagePercentage = document.getElementById('usage-percentage');
        const progressBar = document.getElementById('usage-progress-bar');
        const deviceUsage = document.getElementById('device-usage');
        const deviceBadge = document.getElementById('device-badge');
        const deviceManagementContent = document.querySelector('#device-management-content .mt-5');

        if (planNameElement) planNameElement.textContent = planName || 'No plan';

        const totalDevices = stats.total_devices || 0;
        let totalLimit = 0;
        let displayText = '';
        let usagePercent = 0;

        if (stats.type === 'no_limit') {
            totalLimit = 'unlimited';
            displayText = 'unlimited';
        } else {
            totalLimit = stats.total_limit || 0;
            const remaining = stats.remaining_slots || 0;

            if (totalLimit === 'unlimited' || totalLimit === 0) {
                displayText = 'unlimited';
            } else {
                displayText = totalLimit.toString();
                usagePercent = totalLimit > 0 ? Math.round(((totalLimit - remaining) / totalLimit) * 100) : 0;
            }
        }

        if (displayText === 'unlimited') {
            if (usageSection) usageSection.style.display = 'none';
        } else {
            if (usageSection) usageSection.style.display = 'block';
            if (usagePercentage) {
                usagePercentage.textContent = `${usagePercent}%`;
                usagePercentage.style.display = 'block';
            }
            if (progressBar) {
                progressBar.style.width = `${usagePercent}%`;
                progressBar.setAttribute('aria-valuenow', usagePercent);
            }
        }

        if (displayText === 'unlimited') {
            if (deviceUsage) deviceUsage.textContent = 'No Device Limit';
            if (deviceBadge) deviceBadge.textContent = `${totalDevices}/No Limits`;
            if (deviceManagementContent) deviceManagementContent.style.display = 'none';
        } else {
            if (deviceUsage) deviceUsage.textContent = `${totalDevices} of ${totalLimit} devices used`;
            if (deviceBadge) deviceBadge.textContent = `${totalDevices}/${totalLimit} Devices`;
            if (deviceManagementContent) deviceManagementContent.style.display = 'block';
        }

        if (this.deviceLimitWarning && this.activeDevices) {
            if (usagePercent >= 100 && displayText !== 'unlimited' && totalDevices > 1) {
                this.deviceLimitWarning.style.display = 'block';
                this.activeDevices.classList.remove('mt-3');
            } else {
                this.deviceLimitWarning.style.display = 'none';
                this.activeDevices.classList.add('mt-3');
            }
        }
    }

    // ===== DELETE ACCOUNT FUNCTIONALITY =====
    showDeleteAccountConfirmation() {
        const modal = document.getElementById('DeleteAccountModal');
        if (modal) {
            
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    }

    // ===== LOGOUT ALL DEVICES FUNCTIONALITY =====
    showLogoutAllConfirmation() {
        if (!this.profileDevicesList) return;

        this.resetLogoutAllButtonState();
        const modal = document.getElementById('logoutAllDevicesModal');
        if (modal) {
            
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    }

    resetLogoutAllButtonState() {
        const button = document.getElementById('confirmLogoutAllBtn');
        if (!button) return;

        const btnText = button.querySelector('.btn-text');
        const btnLoader = button.querySelector('.btn-loader');

        if (btnText && btnLoader) {
            btnText.textContent = 'Logout All Devices';
            btnText.style.display = 'block';
            btnLoader.style.display = 'none';
        }
        button.disabled = false;
    }

    async handleLogoutAllDevices() {
        if (!this.profileDevicesList) return;

        const button = document.getElementById('confirmLogoutAllBtn');
        if (!button) return;

        const btnText = button.querySelector('.btn-text');
        const btnLoader = button.querySelector('.btn-loader');
        const userId = document.getElementById('current-user-id')?.value;

        if (!userId) {
            this.resetLogoutAllButton(button, btnText, btnLoader);
            return;
        }

        this.setButtonLoadingState(button, true, 'Logging out all devices...');

        const formData = {
            action: 'st_ajax_post',
            user_id: userId
        };

        try {
            const response = await post('st-user-remove-all-devices', formData);
            if (response.status) {
                setTimeout(() => {
                    const modal = document.getElementById('logoutAllDevicesModal');
                    if (modal) {
                        const bsModal = bootstrap.Modal.getInstance(modal);
                        if (bsModal) bsModal.hide();
                    }
                    this.displayProfileSuccess(response.message);
                    this.loadUserDevicesForProfile();
                }, 1000);
            } else {
                this.resetLogoutAllButton(button, btnText, btnLoader);
            }
        } catch (error) {
            this.resetLogoutAllButton(button, btnText, btnLoader);
        }
    }

    resetLogoutAllButton(button, btnText, btnLoader) {
        if (btnText && btnLoader) {
            btnText.style.display = 'block';
            btnLoader.style.display = 'none';
        }
        button.disabled = false;
    }

    // ===== FORM SUBMISSION METHODS =====
    SubmitRegistration(event) {
        event.preventDefault();
        this.submitForm('streamit-registration-form', 'st-user-register', {
            handleCheckbox: true,
            checkboxSelector: '#iqonic_term_condition',
            showSuccessInButton: 'registration-submit-btn',
            redirectDelay: 500
        });
    }

    SubmitLogin(event) {
        event.preventDefault();
        this.submitForm('streamit-login-form', 'st-user-login', {
            showSuccessInButton: 'login-submit-btn',
            redirectDelay: 300
        });
    }

    SubmitForgotPassword(event) {
        event.preventDefault();
        this.submitForm('streamit-forgot-password-form', 'st-user-forgot-password', {
            redirectDelay: 500
        });
    }

    ProfileEdit(event) {
        event.preventDefault();
        this.submitForm('st_profile_edit', 'st_edit_user_profile', {
            isFormData: true,
            handleFiles: true,
            reloadOnSuccess: true,
            redirectDelay: 500
        });
    }

    SubmitPasswordChange(event) {
        event.preventDefault();

        // Use the same unified submitForm helper
        this.submitForm('st_password_change', 'st_change_user_password', {
            isFormData: true,
            handleFiles: true,
            reloadOnSuccess: true,
            redirectDelay: 500             // no redirect – stay on the page
        });
    }

    // ===== SUCCESS BUTTON HANDLING =====
    showSuccessInButton(buttonId, message) {
        const submitBtn = document.getElementById(buttonId);
        if (!submitBtn) return;

        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoader = submitBtn.querySelector('.btn-loader');

        if (btnText && btnLoader) {
            // hide loader, show label (message) and show tick icon beside it
            btnLoader.style.display = 'none';
            btnText.style.display = 'inline';
            btnText.textContent = message || 'Success';
            submitBtn.disabled = true;

            let tick = submitBtn.querySelector('.btn-success-icon');
            if (!tick) {
                tick = document.createElement('span');
                tick.className = 'btn-success-icon ms-2';
                tick.innerHTML = '&#10003;'; // checkmark
                tick.style.fontSize = '1.1rem';
                tick.style.color = '#fff';
                tick.style.marginLeft = '0.5rem';
                submitBtn.appendChild(tick);
            }
            tick.style.display = 'inline-block';
        }
    }

    // ===== ERROR AND SUCCESS DISPLAY METHODS =====
    clearFieldErrors() {
        document.querySelectorAll('.field-error-message').forEach(error => {
            error.style.display = 'none';
            error.textContent = '';
        });

        const avatarError = document.getElementById('avatar-error-message');
        if (avatarError) {
            avatarError.style.display = 'none';
            avatarError.textContent = '';
        }

        document.querySelectorAll('.form-control').forEach(input => {
            input.classList.remove('is-invalid');
        });

        const termsCheckbox = document.querySelector('#iqonic_term_condition');
        if (termsCheckbox) termsCheckbox.classList.remove('is-invalid');

        this.clearGeneralError();
        this.clearGeneralSuccess();
    }

    displayFieldErrors(fieldErrors) {
        Object.keys(fieldErrors).forEach(fieldName => {
            if (fieldName === 'st_term_condition') {
                const termsContainer = document.querySelector('.terms-error-message');
                const termsCheckbox = document.querySelector('#iqonic_term_condition');

                if (termsContainer) {
                    termsContainer.textContent = fieldErrors[fieldName];
                    termsContainer.style.display = 'block';
                    if (termsCheckbox) termsCheckbox.classList.add('is-invalid');
                }
            } else {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    const errorContainer = field.parentNode.querySelector('.field-error-message');
                    if (errorContainer) {
                        errorContainer.textContent = fieldErrors[fieldName];
                        errorContainer.style.display = 'block';
                        field.classList.add('is-invalid');
                    }
                }
            }
        });
    }

    displayGeneralError(message) {
        const generalErrorElement = document.querySelector('.general-error-message');
        const errorTextElement = generalErrorElement?.querySelector('.error-text');

        if (generalErrorElement && errorTextElement) {
            errorTextElement.textContent = message;
            generalErrorElement.style.display = 'block';
        }
    }

    displayGeneralSuccess(message) {
        const generalSuccessElement = document.querySelector('.general-success-message');
        const successTextElement = generalSuccessElement?.querySelector('.success-text');

        if (generalSuccessElement && successTextElement) {
            successTextElement.textContent = message;
            generalSuccessElement.style.display = 'block';
        }
    }

    displayProfileSuccess(message, timeoutMs = 3000) {
        this.displayGeneralSuccess(message);
        if (timeoutMs > 0) {
            setTimeout(() => this.clearGeneralSuccess(), timeoutMs);
        }
    }

    clearGeneralError() {
        const generalErrorElement = document.querySelector('.general-error-message');
        if (generalErrorElement) {
            generalErrorElement.style.display = 'none';
            const errorTextElement = generalErrorElement.querySelector('.error-text');
            if (errorTextElement) errorTextElement.textContent = '';
        }
    }

    clearGeneralSuccess() {
        const generalSuccessElement = document.querySelector('.general-success-message');
        if (generalSuccessElement) {
            generalSuccessElement.style.display = 'none';
            const successTextElement = generalSuccessElement.querySelector('.success-text');
            if (successTextElement) successTextElement.textContent = '';
        }
    }

    displayAvatarError(message) {
        const avatarErrorElement = document.getElementById('avatar-error-message');
        if (avatarErrorElement) {
            avatarErrorElement.textContent = message;
            avatarErrorElement.style.display = 'block';
        }
    }

    // ===== PASSWORD ICON FUNCTIONALITY =====
    ShowPasswordIcone() {
        this.addViewPasswordIcon(document.querySelector('form[name="loginform"]'), '.login-password');
        this.addViewPasswordIcon(document.getElementById('streamit-registration-form'), '.registration-fields');
        this.addViewPasswordIcon(document.getElementById('streamit-login-form'), '.login-fields');
        this.addViewPasswordIcon(document.getElementById('st_password_change'), '.login-fields');
        this.addViewPasswordIcon(document.getElementById('pmpro_form'), '.pmpro_checkout-field-password');
        this.addViewPasswordIcon(document.getElementById('change-password'), '.pmpro_checkout-field-password');

    }

    addViewPasswordIcon(form, closest) {
        if (!form) return;
    
        const passwordFields = form.querySelectorAll('input[type="password"]');
        passwordFields.forEach(field => {
            const icon = document.createElement('i');
            icon.className = 'password_toggle icon-eye-2';
            field.parentNode.insertBefore(icon, field.nextSibling);
        });
    
        form.addEventListener('click', (e) => {
            if (e.target.classList.contains('password_toggle')) {
                const passwordInput = e.target.previousElementSibling;
    
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    e.target.className = 'password_toggle icon-eye-slash';
                } else {
                    passwordInput.type = 'password';
                    e.target.className = 'password_toggle icon-eye-2';
                }
            }
        });
    }    

    // ===== HEARTBEAT METHODS =====
    startHeartbeat() {

        if (this.heartbeatActive) return;
        this.heartbeatActive = true;

        if (typeof wp === 'undefined' || !wp.heartbeat) {
            console.warn('WordPress Heartbeat API not loaded.');
            return;
        }

        // Send custom data
        jQuery(document).on('heartbeat-send', (event, data) => {
            data.st_heartbeat = {
                action: 'st-user-heartbeat'
            };
        });

        // Receive server response
        jQuery(document).on('heartbeat-tick', (event, data) => {
            this.handleHeartbeatResponse(data.st_heartbeat_response);
        });

        // Handle errors
        jQuery(document).on('heartbeat-error', () => {
            this.handleHeartbeatError();
        });

        wp.heartbeat.connectNow();
    }


    stopHeartbeat() {
        this.heartbeatActive = false;
    }

    handleHeartbeatResponse(response) {
        if (!response) return;

        const storedValue = localStorage.getItem('streamit_player_was_logged_in');
        const wasLoggedIn = storedValue === 'true' || storedValue === true;
        const isNowLoggedIn = response.logged_in;

        if (wasLoggedIn && !isNowLoggedIn) {
            const isPaidContent = response.is_paid_content || false;
            this.showYouAreRemovedModal(isPaidContent);
            this.stopHeartbeat();
            return;
        }

        if (response.logged_in) {
            const currentSessionKey = response.session_key || this.getCurrentSessionKey();
            if (currentSessionKey && !localStorage.getItem('streamit_player_session_key')) {
                localStorage.setItem('streamit_player_session_key', currentSessionKey);
                localStorage.setItem('streamit_player_session_timestamp', Date.now().toString());
                localStorage.setItem('streamit_player_was_logged_in', 'true');
            }

            const isSessionValid = this.checkSessionValidity(currentSessionKey);
            if (!isSessionValid) {
                const isPaidContent = response.is_paid_content || false;
                this.showYouAreRemovedModal(isPaidContent);
                this.stopHeartbeat();
            }
        }
    }

    handleHeartbeatError() {
        this.stopHeartbeat();
    }
}