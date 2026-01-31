import { Tooltip, Modal, Toast, Offcanvas } from 'bootstrap';

export default class BootstrapComponent {
    constructor() {
        this.instances = new Map(); // Cache Bootstrap instances
        this.autoHideTimeouts = new Map(); // Track auto-hide timeouts
    }

    /**
     * Initialize tooltips for all elements with data-bs-toggle="tooltip"
     * @param {string} container - Optional container selector to scope tooltips
     */
    tooltip(container = document) {
        const tooltipTriggerList = container.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = Array.from(tooltipTriggerList).map(tooltipTriggerEl => {
            return new Tooltip(tooltipTriggerEl);
        });
        return tooltipList;
    }

    /**
     * Initialize a single tooltip on a specific element
     * @param {string|Element} element - Element or selector for the tooltip
     * @param {Object} options - Tooltip options
     */
    initTooltip(element, options = {}) {
        const el = typeof element === 'string' ? document.querySelector(element) : element;
        if (!el) {
            console.warn('Tooltip element not found:', element);
            return null;
        }

        try {
            const tooltip = new Tooltip(el, options);
            this.instances.set(`tooltip-${el.id || el.className}`, tooltip);
            return tooltip;
        } catch (error) {
            console.error('Failed to initialize tooltip:', error);
            return null;
        }
    }

    /**
     * Show a modal by ID
     * @param {string} modalId - The ID of the modal element
     * @param {Object} options - Modal options
     */
    showModal(modalId, options = {}) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) {
            console.error(`Modal with ID "${modalId}" not found.`);
            return null;
        }
        

        try {
            let modal = Modal.getInstance(modalElement);
            if (!modal) {
                modal = new Modal(modalElement, options);
                this.instances.set(`modal-${modalId}`, modal);
            }
            modal.show();
            return modal;
        } catch (error) {
            console.error(`Failed to show modal "${modalId}":`, error);
            return null;
        }
    }

    /**
     * Close a modal by ID
     * @param {string} modalId - The ID of the modal element
     */
    closeModal(modalId) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) {
            console.error(`Modal with ID "${modalId}" not found.`);
            return false;
        }

        try {
            const modal = Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
                return true;
            }
            return false;
        } catch (error) {
            console.error(`Failed to close modal "${modalId}":`, error);
            return false;
        }
    }

    /**
     * Close all open modals
     */
    closeAllModals() {
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modalElement => {
            const modal = Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        });
    }

    /**
     * Show a toast message
     * @param {string} toastId - The ID of the toast element
     * @param {string} message - The message to display
     * @param {number} autoHideDelay - Auto-hide delay in milliseconds (0 to disable)
     * @param {string} messageElementId - Optional ID of the message element
     */
    showToast(toastId, message, autoHideDelay = 5000, messageElementId = 'toastMessage') {
        const toastElement = document.getElementById(toastId);
        if (!toastElement) {
            console.error(`Toast with ID "${toastId}" not found.`);
            return null;
        }

        try {
            // Find message element - try multiple selectors
            let messageElement = document.getElementById(messageElementId);
            if (!messageElement) {
                messageElement = toastElement.querySelector('.toast-body') ||
                    toastElement.querySelector('[data-toast-message]');
            }

            if (messageElement) {
                messageElement.textContent = message;
            }

            let toast = Toast.getInstance(toastElement);
            if (!toast) {
                toast = new Toast(toastElement);
                this.instances.set(`toast-${toastId}`, toast);
            }

            // Clear existing auto-hide timeout
            this.clearAutoHideTimeout(toastId);

            toast.show();

            // Set up auto-hide if delay is provided
            if (autoHideDelay > 0) {
                const timeoutId = setTimeout(() => {
                    toast.hide();
                    this.autoHideTimeouts.delete(toastId);
                }, autoHideDelay);
                this.autoHideTimeouts.set(toastId, timeoutId);
            }

            return toast;
        } catch (error) {
            console.error(`Failed to show toast "${toastId}":`, error);
            return null;
        }
    }

    /**
     * Hide a toast message
     * @param {string} toastId - The ID of the toast element
     */
    hideToast(toastId) {
        const toastElement = document.getElementById(toastId);
        if (!toastElement) {
            console.error(`Toast with ID "${toastId}" not found.`);
            return false;
        }

        try {
            const toast = Toast.getInstance(toastElement);
            if (toast) {
                toast.hide();
                this.clearAutoHideTimeout(toastId);
                return true;
            }
            return false;
        } catch (error) {
            console.error(`Failed to hide toast "${toastId}":`, error);
            return false;
        }
    }

    /**
     * Hide all visible toasts
     */
    hideAllToasts() {
        const visibleToasts = document.querySelectorAll('.toast.show');
        visibleToasts.forEach(toastElement => {
            const toast = Toast.getInstance(toastElement);
            if (toast) {
                toast.hide();
            }
        });
        this.autoHideTimeouts.clear();
    }

    /**
     * Show an offcanvas element
     * @param {string} offcanvasId - The ID of the offcanvas element
     * @param {Object} options - Offcanvas options
     */
    showOffcanvas(offcanvasId, options = {}) {
        const offcanvasElement = document.getElementById(offcanvasId);
        if (!offcanvasElement) {
            console.error(`Offcanvas with ID "${offcanvasId}" not found.`);
            return null;
        }

        try {
            let offcanvas = Offcanvas.getInstance(offcanvasElement);
            if (!offcanvas) {
                offcanvas = new Offcanvas(offcanvasElement, options);
                this.instances.set(`offcanvas-${offcanvasId}`, offcanvas);
            }
            offcanvas.show();
            return offcanvas;
        } catch (error) {
            console.error(`Failed to show offcanvas "${offcanvasId}":`, error);
            return null;
        }
    }

    /**
     * Hide an offcanvas element
     * @param {string} offcanvasId - The ID of the offcanvas element
     */
    hideOffcanvas(offcanvasId) {
        const offcanvasElement = document.getElementById(offcanvasId);
        if (!offcanvasElement) {
            console.error(`Offcanvas with ID "${offcanvasId}" not found.`);
            return false;
        }

        try {
            const offcanvas = Offcanvas.getInstance(offcanvasElement);
            if (offcanvas) {
                offcanvas.hide();
                return true;
            }
            return false;
        } catch (error) {
            console.error(`Failed to hide offcanvas "${offcanvasId}":`, error);
            return false;
        }
    }

    /**
     * Close any visible offcanvas
     * @param {string} selector - Optional selector for specific offcanvas elements
     */
    closeVisibleOffcanvas(selector = '.offcanvas.show') {
        const visibleOffcanvas = document.querySelector(selector);
        if (visibleOffcanvas) {
            const offcanvas = Offcanvas.getInstance(visibleOffcanvas);
            if (offcanvas) {
                offcanvas.hide();
                return true;
            }
        }
        return false;
    }

    /**
     * Close all visible offcanvas elements
     */
    closeAllOffcanvas() {
        const visibleOffcanvases = document.querySelectorAll('.offcanvas.show');
        visibleOffcanvases.forEach(offcanvasElement => {
            const offcanvas = Offcanvas.getInstance(offcanvasElement);
            if (offcanvas) {
                offcanvas.hide();
            }
        });
    }

    /**
     * Get a Bootstrap instance by type and ID
     * @param {string} type - Instance type ('modal', 'toast', 'offcanvas', 'tooltip')
     * @param {string} id - Instance ID
     */
    getInstance(type, id) {
        return this.instances.get(`${type}-${id}`) || null;
    }

    /**
     * Dispose of a Bootstrap instance
     * @param {string} type - Instance type
     * @param {string} id - Instance ID
     */
    disposeInstance(type, id) {
        const instance = this.instances.get(`${type}-${id}`);
        if (instance) {
            instance.dispose();
            this.instances.delete(`${type}-${id}`);
            return true;
        }
        return false;
    }

    /**
     * Dispose all instances of a specific type
     * @param {string} type - Instance type
     */
    disposeAllInstances(type = null) {
        if (type) {
            // Dispose specific type
            for (const [key, instance] of this.instances.entries()) {
                if (key.startsWith(`${type}-`)) {
                    instance.dispose();
                    this.instances.delete(key);
                }
            }
        } else {
            // Dispose all instances
            for (const [key, instance] of this.instances.entries()) {
                instance.dispose();
            }
            this.instances.clear();
        }

        // Clear all auto-hide timeouts
        this.autoHideTimeouts.forEach(timeoutId => clearTimeout(timeoutId));
        this.autoHideTimeouts.clear();
    }

    /**
     * Clear auto-hide timeout for a toast
     * @param {string} toastId - Toast ID
     */
    clearAutoHideTimeout(toastId) {
        const timeoutId = this.autoHideTimeouts.get(toastId);
        if (timeoutId) {
            clearTimeout(timeoutId);
            this.autoHideTimeouts.delete(toastId);
        }
    }

    /**
     * Initialize all Bootstrap components in a container
     * @param {string|Element} container - Container element or selector
     */
    initializeAll(container = document) {
        const containerEl = typeof container === 'string' ? document.querySelector(container) : container;
        if (!containerEl) return;

        // Initialize tooltips
        this.tooltip(containerEl);

        // Note: Modals, toasts, and offcanvases are typically initialized on demand
        // but you could pre-initialize them here if needed
    }

    /**
     * Safe method to check if Bootstrap is available
     */
    isBootstrapAvailable() {
        return typeof Tooltip !== 'undefined' &&
            typeof Modal !== 'undefined' &&
            typeof Toast !== 'undefined' &&
            typeof Offcanvas !== 'undefined';
    }
}