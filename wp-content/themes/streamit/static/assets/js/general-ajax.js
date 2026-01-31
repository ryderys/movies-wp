import { post, get } from "../utilities/ajax";
import bootstrapcomponent from './bootstrap-component.js';
import * as bootstrap from 'bootstrap';

export default class GeneralAjax {
    constructor() {
        this.bootstrap = new bootstrapcomponent();
        this.debounceTimeouts = new Map(); // For multiple debounce instances
        this.setupEventHandlers();
        this.AjaxSearch();
    }

    setupEventHandlers() {
        const eventHandlers = [
            { selector: ".watch-list-btn", event: "click", handler: this.WatchlistHandler.bind(this) },
            { selector: ".st-like-btn", event: "click", handler: this.LikeHandler.bind(this) },
            { selector: "#st_comment_form", event: "submit", handler: this.SubmitCommentForm.bind(this) },
            { selector: "#st_creat_playlist", event: "submit", handler: this.creatPlaylist.bind(this) },
            { selector: ".st_manage_playlist", event: "click", handler: this.ManagePlaylist.bind(this) },
            { selector: "#st_delete_comment", event: "click", handler: this.DeleteComment.bind(this) },
            { selector: ".delete_user_playlist", event: "click", handler: this.DeletPlaylist.bind(this) },
            { selector: ".manage_playlist", event: "click", handler: this.InsetUpdatePlaylistForm.bind(this) },
            { selector: "#edit-profile-picture-btn", event: "click", handler: this.setupAvatarUploader.bind(this) },
            { selector: "#upload-profile-picture", event: "change", handler: this.handleFileChange.bind(this) },
            { selector: "#remove-profile-picture-btn", event: "click", handler: this.RemoveAvtar.bind(this) },
            { selector: "#st-subscription-form", event: "submit", handler: this.SubmitSubscriptionForm.bind(this) },
            { selector: ".notification-action-btn", event: "click", handler: this.UpdateNotificationStatus.bind(this) },
            { selector: ".notify-me-btn", event: "click", handler: this.NotifyMeHandler.bind(this) },
            { selector: ".filter-option", event: "click", handler: this.NotificationFilterHandler.bind(this) },
            { selector: '.nav-link[data-bs-toggle="tab"]', event: "shown.bs.tab", handler: this.handleTabSwitch.bind(this) }
        ];

        eventHandlers.forEach(({ selector, event, handler }) => {
            document.addEventListener(event, (e) => {
                if (e.target.matches(selector) || e.target.closest(selector)) {
                    handler(e);
                }
            });
        });
    }

    setupAvatarUploader(event) {
        event.preventDefault();
        document.getElementById('upload-profile-picture')?.click();
    }

    handleFileChange(event) {
        const file = event.target.files[0];
        const previewImg = document.getElementById('profile-picture-preview');
        const errorMessage = document.getElementById('avatar-error-message');
        const removeBtn = document.getElementById('remove-profile-picture-btn');
    
        if (errorMessage) {
            errorMessage.style.display = 'none';
            errorMessage.textContent = '';
        }
    
        if (!file) return;
    
        if (!file.type.startsWith('image/')) {
            if (errorMessage) {
                errorMessage.textContent = 'Please upload a valid image file (JPG, JPEG, PNG, GIF, or WEBP).';
                errorMessage.classList.add('text-danger');
                errorMessage.style.display = 'block';
            }
            event.target.value = ''; // Reset file input
            return;
        }
    
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (file.size > maxSize) {
            if (errorMessage) {
                errorMessage.textContent = 'Only images up to 5MB are supported.';
                errorMessage.style.display = 'block';
            }
            event.target.value = ''; // Reset file input (prevents upload)
            return;
        }
    
        if (previewImg) {
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    
        if (removeBtn) {
            removeBtn.style.display = 'inline-block';
        }
    }
    
    RemoveAvtar(event) {
        event.preventDefault();
        const avatarPreview = document.getElementById('profile-picture-preview');
        const defaultAvatar = document.getElementById('default_avatar')?.value;
        const removeAvatarField = document.getElementById('remove_avatar');
        const isRemove = document.getElementById('is_remove_avtar');
        const errorMessage = document.getElementById('avatar-error-message');
        const fileInput = document.getElementById('upload-profile-picture');
    
        if (avatarPreview && defaultAvatar) {
            avatarPreview.src = defaultAvatar;
        }
    
        if (removeAvatarField) removeAvatarField.value = defaultAvatar || '';
        if (isRemove) isRemove.value = 1;
        if (fileInput) fileInput.value = ''; // reset file input
    
        if (errorMessage) {
            errorMessage.style.display = 'none';
            errorMessage.textContent = '';
        }
    
        event.target.style.display = 'none';
    }

    async SubmitSubscriptionForm(event) {
        event.preventDefault();
        this.bootstrap.hideToast('stToastMessage');

        const submitButton = event.target.querySelector('#st-subscribe-btn');
        if (!submitButton) return;

        const loader = submitButton.querySelector('.st-loader');
        const emailInput = event.target.querySelector('input[name="EMAIL"]');

        this.setButtonLoadingState(submitButton, loader, true);

        try {
            const formData = {
                email: emailInput?.value || '',
            };

            const res = await post('submit_subscription_form', formData);
            this.bootstrap.showToast('stToastMessage', res.message);

            setTimeout(() => {
                this.setButtonLoadingState(submitButton, loader, false);
                if (res.status && emailInput) {
                    emailInput.value = '';
                }
            }, 2000);
        } catch (err) {
            this.bootstrap.showToast('stToastMessage', 'Something went wrong. Please try again.');
            this.setButtonLoadingState(submitButton, loader, false);
        }
    }

    async UpdateNotificationStatus(event) {
        const target = event.target.closest('.notification-action-btn');
        if (!target) return;

        const notificationId = target.dataset.notification_id;
        const userId = target.dataset.user_id;
        const read = target.dataset.read;
        const icon = target.querySelector('i');

        if (icon) icon.className = 'st-loader';

        try {
            const res = await post('update_notification_seen_status', {
                user_id: userId,
                notification_id: notificationId,
                is_seen: read
            });
            window.location.reload();
        } catch (e) {
            console.error(e);
        }
    }

    async handleNotifyMe(event, isEpisode = false) {
        event.preventDefault();
        const notifyBtn = event.target.closest(isEpisode ? '.notify-me-episode-btn' : '.notify-me-btn');
        if (!notifyBtn) return;

        const postId = notifyBtn.dataset.postId;
        const postType = notifyBtn.dataset.postType;
        const seasonId = notifyBtn.dataset.seasonId;
        const isRemind = parseInt(notifyBtn.getAttribute('data-in-remind')) || 0;

        const originalHTML = notifyBtn.innerHTML;
        this.setButtonLoadingState(notifyBtn, null, true, 'Processing...');

        const data = {
            post_id: postId,
            is_remind: isRemind,
            post_type: postType,
            ...(seasonId !== undefined && { season_id: seasonId })
        };

        try {
            const res = await post('notify_me_upcoming', data);
            this.bootstrap.showToast('stToastMessage', res.message);

            this.updateNotifyMeButton(notifyBtn, res.action, originalHTML);
        } catch (err) {
            this.bootstrap.showToast('stToastMessage', 'Something went wrong. Please try again.');
            this.resetButtonState(notifyBtn, originalHTML);
        }
    }

    NotifyMeHandler(event) {
        this.handleNotifyMe(event, false);
    }


    updateNotifyMeButton(button, action, originalHTML) {
        const isAdded = action === 'added';

        button.className = isAdded ?
            button.className + ' in-remind' :
            button.className.replace(' in-remind', '');

        button.dataset.inRemind = isAdded ? '1' : '0';
        button.title = isAdded ? 'Remove reminder' : 'Remind Me';

        button.innerHTML = isAdded ?
            `<span class="d-flex align-items-center justify-content-center gap-2">
                <span><i class="icon-check-2"></i></span>
                <span>Remind Me</span>
            </span>` :
            `<span class="d-flex align-items-center justify-content-center gap-2">
                <span><i class="icon-bell-1"></i></span>
                <span>Remind Me</span>
            </span>`;

        button.disabled = false;
    }

    async LikeHandler(event) {
        const likeBtn = event.target.closest('.st-like-btn');
        if (!likeBtn) return;

        const postId = likeBtn.dataset.post_id;
        const postType = likeBtn.dataset.post_type;

        try {
            const res = await post('manage_post_like', { post_id: postId, post_type: postType });
            // Update the like count and tooltip text
            if (res.do_like) {
                const icon = likeBtn.querySelector('i');
                const likeCount = res.like_count || 0;
                
                likeBtn.dataset.likeCount = likeCount;
                
                const tooltipText = likeCount === 1 
                    ? `${likeCount} Like` 
                    : `${likeCount} Likes`;
                
                likeBtn.setAttribute('data-bs-title', tooltipText);
                likeBtn.setAttribute('data-bs-original-title', tooltipText);
                
                const tooltipInstance = bootstrap.Tooltip.getInstance(likeBtn);
                if (tooltipInstance) {
                    tooltipInstance.hide();
                    tooltipInstance.dispose();
                    new bootstrap.Tooltip(likeBtn);
                }
                
                if (res.is_liked) {
                    likeBtn.classList.add('is-liked');
                    icon.className = icon.className.replace('icon-heart', 'icon-heart-fill');
                } else {
                    likeBtn.classList.remove('is-liked');
                    icon.className = icon.className.replace('icon-heart-fill', 'icon-heart');

                    if (likeBtn.closest('.profile-page')) {
                        likeBtn.closest('.col')?.remove();
                    }
                }
            }
        } catch (err) {
            console.log(err);
        }
    }

    async WatchlistHandler(event) {
        const button = event.target.closest('.watch-list-btn');
        if (!button) return;

        const postId = button.dataset.postId;
        const postType = button.dataset.postType;
        const action = button.dataset.action;

        try {
            const data = { post_id: postId, post_type: postType, update_action: action };
            const res = await post('manage_watch_list_data', data);

            if (res.success) {
                const icon = button.querySelector('i');
                if (action === 'add') {
                    this.bootstrap.showToast('stToastMessage', res.data.message);
                    button.classList.add('in-watchlist');
                    icon.className = icon.className.replace('icon-plus', 'icon-check-2');
                    button.title = 'Remove from watchlist';
                    button.dataset.action = 'remove';
                } else {
                    if (button.closest('.profile-page')) {
                        button.closest('.col')?.remove();
                        this.bootstrap.showToast('stToastMessage', res.data.message);
                    } else {
                        button.classList.remove('in-watchlist');
                        icon.className = icon.className.replace('icon-check-2', 'icon-plus');
                        button.title = 'Add to watchlist';
                        button.dataset.action = 'add';
                        this.bootstrap.showToast('stToastMessage', res.data.message);
                    }
                }

                // Trigger custom event
                const hoverCard = button.closest('.hover-card');
                if (hoverCard) {
                    hoverCard.dispatchEvent(new CustomEvent('watchlistUpdated', {
                        detail: { postId, postType, action }
                    }));
                }
            }
        } catch (err) {
            console.error('AJAX error:', err);
        }
    }

    async SubmitCommentForm(event) {
        event.preventDefault();
        this.bootstrap.hideToast('stToastMessage');

        const submitButton = event.target.querySelector('button[type="submit"]');
        const loader = submitButton?.querySelector('.st-loader');

        this.setButtonLoadingState(submitButton, loader, true);

        try {
            const formData = {
                comment_id: this.getValue('#cm_id'),
                post_type: this.getValue('#cm_post_type'),
                post_id: this.getValue('#cm_post_id'),
                user_name: this.getValue('#cm_name'),
                user_email: this.getValue('#cm_email'),
                rating: document.querySelector('input[name="rating"]:checked')?.value || 0,
                cm_details: this.getValue('#cm_details'),
            };

            const res = await post('submit_comment_form', formData);
            this.bootstrap.showToast('stToastMessage', res.message);

            setTimeout(() => {
                this.setButtonLoadingState(submitButton, loader, false);
                if (res.status) {
                    setTimeout(() => location.reload(), 2000);
                }
            }, 2000);
        } catch (err) {
            console.log(err);
            this.setButtonLoadingState(submitButton, loader, false);
        }
    }

    AjaxSearch() {
        const searchInput = document.getElementById('search-query');
        if (!searchInput) return;

        searchInput.addEventListener('input', (event) => {
            const query = event.target.value.trim();
            this.debounce(`search-${query}`, () => {
                if (query.length > 0) {
                    this.processSearch(query);
                } else {
                    const resultSection = document.querySelector('.search_result_section');
                    if (resultSection) resultSection.innerHTML = '';
                }
            }, 300);
        });
    }

    async processSearch(query) {
        try {
            const res = await get('st_search_data', { data: query });
            const resultSection = document.querySelector('.search_result_section');
            if (resultSection) resultSection.innerHTML = res;
        } catch (error) {
            console.error(error);
        }
    }

    async creatPlaylist(event) {
        event.preventDefault();
        this.bootstrap.hideToast('stToastMessage');

        const submitButton = event.target.querySelector('button[type="submit"]');
        const loader = submitButton?.querySelector('.st-loader');

        this.setButtonLoadingState(submitButton, loader, true);

        try {
            const formData = {
                post_type: this.getValue('#st_playlist_post_type'),
                playlist_title: this.getValue('#st_playlist_title'),
                playlist_id: this.getValue('#st_playlist_id'),
            };

            const res = await post('add_playlist', formData);
            this.bootstrap.showToast('stToastMessage', res.message);

            setTimeout(() => {
                location.reload();
            }, 2000);
        } catch (err) {
            location.reload();
            console.log(err);
        }
    }

    async ManagePlaylist(event) {
        const checkbox = event.target;
        if (checkbox.type !== 'checkbox') return;

        this.bootstrap.hideToast('stToastMessage');

        const formData = {
            playlist_id: checkbox.dataset.playlist_id,
            post_type: checkbox.dataset.post_type,
            post_id: checkbox.dataset.post_id,
            is_checked: checkbox.checked
        };

        try {
            const res = await post('add_in_playlist', formData);
            this.bootstrap.showToast('stToastMessage', res.message);
        } catch (err) {
            console.log(err);
        }
    }

    async DeleteComment(event) {
        event.preventDefault();
        const removeButton = event.target.closest('#st_delete_comment');
        if (!removeButton) return;

        const loader = removeButton.querySelector('.st-loader');
        if (loader) loader.classList.remove('d-none');

        try {
            const formData = {
                comment_id: removeButton.dataset.comment_id,
                post_id: removeButton.dataset.post_id,
                post_type: removeButton.dataset.post_type
            };

            const res = await post('delete_comment', formData);
            this.bootstrap.showToast('stToastMessage', res.message);

            setTimeout(() => {
                if (loader) loader.classList.add('d-none');
                location.reload();
            }, 2000);
        } catch (err) {
            if (loader) loader.classList.add('d-none');
            console.log(err);
        }
    }

    async DeletPlaylist(event) {
        event.preventDefault();
        const button = event.target.closest('.delete_user_playlist');
        if (!button) return;

        this.bootstrap.hideToast('stToastMessage');

        try {
            const data = {
                playlist_id: button.dataset.playlist_id,
                post_type: button.dataset.postType
            };

            const res = await post('delete_user_playlist', data);
            if (res) {
                button.closest('.col')?.remove();
            }
            this.bootstrap.showToast('stToastMessage', res.message);
        } catch (err) {
            console.log(err);
        }
    }

    InsetUpdatePlaylistForm(event) {
        event.preventDefault();
        const button = event.target.closest('.manage_playlist');
        if (!button) return;

        const playlistId = button.dataset.playlistId;
        const playlistName = button.dataset.playlistName;
        const postType = button.dataset.postType;

        const modalElement = document.getElementById('creatplaylistModal');
        if (!modalElement) return;

        const titleInput = modalElement.querySelector('#st_playlist_title');
        const postTypeSelect = modalElement.querySelector('#st_playlist_post_type');
        const playlistIdInput = modalElement.querySelector('#st_playlist_id');
        const modalTitle = modalElement.querySelector('#st_playlist_modal_title');
        const submitBtn = modalElement.querySelector('#st_playlist_submit_btn');

        // Set field values
        if (titleInput) titleInput.value = playlistName || '';
        if (postTypeSelect) {
            postTypeSelect.value = postType || '';
            postTypeSelect.disabled = !!postType;
            postTypeSelect.dispatchEvent(new Event('change'));
        }
        if (playlistIdInput) playlistIdInput.value = playlistId || '';

        // Change heading & button label for edit mode
        if (playlistId) {
            modalTitle.textContent = stAjax.playlist.update_title;
            submitBtn.textContent = stAjax.playlist.update_btn_title;
        } else {
            modalTitle.textContent = stAjax.playlist.create_title;
            submitBtn.textContent = stAjax.playlist.create_btn_title;
        }

        this.bootstrap.showModal('creatplaylistModal');
    }


    applyFilter(notificationList, filterType) {
        const items = notificationList.querySelectorAll('.notification-item');
        let visibleCount = 0;

        items.forEach(item => {
            const matches = filterType === 'all' || item.dataset.notificationType === filterType;
            item.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        // Handle empty state
        notificationList.querySelectorAll('.empty-state-message').forEach(el => el.remove());

        if (visibleCount === 0) {
            const messages = {
                purchases: 'No purchase notifications found.',
                releases: 'No new release notifications found.',
                default: 'No notifications found.'
            };
            const message = messages[filterType] || messages.default;

            const emptyState = document.createElement('li');
            emptyState.className = 'empty-state-message';
            emptyState.innerHTML = `
                <div class="text-center py-4">
                    <i class="icon-bell-off fs-1 text-muted mb-3"></i>
                    <h6 class="m-0 text-muted">${message}</h6>
                </div>
            `;
            notificationList.appendChild(emptyState);
        }
    }

    NotificationFilterHandler(event) {
        event.preventDefault();
        const target = event.target.closest('.filter-option');
        if (!target) return;

        const filterType = target.dataset.filter;
        const filterText = target.textContent;
        const dropdown = document.getElementById('notificationFilterDropdown');
        const activeTab = document.querySelector('.nav-link.active')?.getAttribute('data-bs-target');
        const notificationList = document.querySelector(`${activeTab} .notification-list`);

        if (dropdown) dropdown.textContent = filterText;
        document.querySelectorAll('.filter-option').forEach(opt => opt.classList.remove('active'));
        target.classList.add('active');

        if (notificationList) {
            this.applyFilter(notificationList, filterType);
        }

        // Close dropdown
        document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.remove('show'));
    }

    handleTabSwitch(event) {
        const activeFilter = document.querySelector('.filter-option.active')?.dataset.filter;
        if (!activeFilter || activeFilter === 'all') return;

        const targetTab = event.target.getAttribute('data-bs-target');
        const notificationList = document.querySelector(`${targetTab} .notification-list`);

        if (notificationList) {
            this.applyFilter(notificationList, activeFilter);
        }
    }

    // Utility Methods
    debounce(key, func, wait) {
        clearTimeout(this.debounceTimeouts.get(key));
        this.debounceTimeouts.set(key, setTimeout(func, wait));
    }

    setButtonLoadingState(button, loader, isLoading, loadingText = '') {
        if (!button) return;

        if (isLoading) {
            button.disabled = true;
            button.setAttribute('data-original-type', button.type);
            button.type = 'button';
            if (loader) loader.classList.remove('d-none');
            if (loadingText) {
                button.innerHTML = `<span class="st-loader"></span> ${loadingText}`;
            }
        } else {
            button.disabled = false;
            button.type = button.getAttribute('data-original-type') || 'submit';
            if (loader) loader.classList.add('d-none');
        }
    }

    resetButtonState(button, originalHTML) {
        if (button) {
            button.disabled = false;
            button.innerHTML = originalHTML;
        }
    }

    getValue(selector) {
        const element = document.querySelector(selector);
        return element ? element.value : '';
    }
}