import { post } from "../utilities/ajax";

export default class Woocommerce {
    constructor() {
        this.setupEventHandlers();
        this.eventHandler();
        this.QuickSelect();
    }

    setupEventHandlers() {
        // Use event delegation for plus/minus buttons
        document.addEventListener('click', (event) => {
            const target = event.target;
            if (target.matches('button.plus, button.minus') ||
                target.closest('button.plus, button.minus')) {
                this.updateCartQuantity(event);
            }
        });
    }

    async updateCartQuantity(event) {
        event.preventDefault();

        const button = event.target.closest('button.plus, button.minus');
        if (!button) return;

        const cartItem = button.closest('.mini-cart-item');
        if (!cartItem) return;

        const quantityInput = cartItem.querySelector('.qty');
        if (!quantityInput) return;

        let currentQuantity = parseInt(quantityInput.value, 10);

        // Determine if it's plus or minus
        if (button.classList.contains('plus')) {
            currentQuantity++;
        } else if (button.classList.contains('minus') && currentQuantity > 1) {
            currentQuantity--;
        }

        quantityInput.value = currentQuantity;

        // Extract cart item key from name attribute
        const cartItemKey = this.extractCartItemKey(quantityInput.name);
        if (!cartItemKey) return;

        const formData = {
            cart_item_key: cartItemKey,
            new_quantity: currentQuantity,
        };

        try {
            const response = await post('st_mini_cart_update', formData);

            if (response.status) {
                this.updateCartUI(response);
            } else {
                console.log(response.message);
                // Optionally show error message to user
            }
        } catch (error) {
            console.error('Error updating cart:', error);
            this.showError('An error occurred. Please try again.');
        }
    }

    extractCartItemKey(inputName) {
        const match = inputName.match(/\[(.*?)\]/);
        return match ? match[1] : null;
    }

    updateCartUI(response) {
        // Update mini cart count
        const miniCartCount = document.getElementById('mini-cart-count');
        if (miniCartCount) miniCartCount.textContent = response.new_quantity;

        // Update streamit cart count
        const streamitCartCounts = document.querySelectorAll('.streamit-cart-count');
        streamitCartCounts.forEach(element => {
            element.innerHTML = response.new_quantity;
        });

        // Update price amounts
        const priceAmounts = document.querySelectorAll('.st-woocommerce-Price-amount');
        priceAmounts.forEach(element => {
            element.innerHTML = response.new_subtotal;
        });
    }

    showError(message) {
        // You can implement a toast or notification system here
        console.error(message);
        // For now, using alert as fallback
        alert(message);
    }

    eventHandler() {
        const woocommerceElement = document.querySelector('[class*="woocommerce columns-"]');

        if (!woocommerceElement) {
            this.handleProductTabs();
        } else {
            this.handleWoocommerceColumns();
        }
    }

    handleProductTabs() {
        const navLinks = document.querySelectorAll('.woocommerce-product-tab .nav-link');
        if (navLinks.length === 0) return;

        navLinks.forEach(link => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                this.handleTabClick(link);
            });
        });

        // Apply saved layout preference on page load
        const savedLayout = this.getCookie('streamit_preferred_shop_layout');
        if (savedLayout) {
            const savedLink = document.querySelector(`.woocommerce-product-tab .nav-link[id="${savedLayout}"]`);
            if (savedLink) {
                this.handleTabClick(savedLink, true);
            }
        }
    }

    handleTabClick(link, isInitialLoad = false) {
        const linkId = link.getAttribute('id');
        if (!linkId) return;

        this.setCookie('streamit_preferred_shop_layout', linkId, 365);

        const tabPane = document.querySelector('.tab-pane');
        const tabCol = document.querySelector('.row-col-data');

        if (!tabPane || !tabCol) return;

        // Update tab pane
        tabPane.id = `grid-${linkId}`;
        tabPane.classList.add('active');

        // Update column classes
        this.updateColumnClasses(tabCol, linkId);

        // Update active state for nav links
        if (!isInitialLoad) {
            document.querySelectorAll('.woocommerce-product-tab .nav-link').forEach(navLink => {
                navLink.classList.remove('active');
            });
            link.classList.add('active');
        }
    }

    updateColumnClasses(tabCol, linkId) {
        // Remove existing column classes
        const classList = Array.from(tabCol.classList);
        classList.forEach(className => {
            if (className.startsWith('row-cols-') ||
                className.startsWith('row-cols-sm-') ||
                className.startsWith('row-cols-md-') ||
                className.startsWith('row-cols-lg-') ||
                className.startsWith('row-cols-xl-')) {
                tabCol.classList.remove(className);
            }
        });

        // Remove product-list class
        tabCol.classList.remove('product-list');

        if (linkId === '1') {
            // Single column layout
            tabCol.classList.add('product-list');
            tabCol.classList.add('row-cols-1', 'row-cols-sm-1', 'row-cols-md-1', 'row-cols-lg-1', 'row-cols-xl-1');
        } else {
            // Multi-column layout
            tabCol.classList.add(
                'row-cols-2',
                'row-cols-sm-2',
                `row-cols-md-${linkId}`,
                `row-cols-lg-${linkId}`,
                `row-cols-xl-${linkId}`
            );
        }

        // Add base classes
        tabCol.classList.add('products', 'row', 'row-col-data', 'gy-5', 'woocommerce', 'poduct-slick');
    }

    handleWoocommerceColumns() {
        const woocommerceElements = document.querySelectorAll('[class*="woocommerce columns-"]');

        woocommerceElements.forEach(element => {
            const tabCol = element.querySelector('.row.row-col-data');
            if (!tabCol) return;

            // Extract column number from class
            const colNum = this.extractColumnNumber(element.className);
            this.updateWoocommerceColumnClasses(tabCol, colNum);
        });
    }

    extractColumnNumber(className) {
        const match = className.match(/columns-(\d+)/);
        return match ? match[1] : '4'; // Default to 4 columns
    }

    updateWoocommerceColumnClasses(tabCol, colNum) {
        // Remove existing column classes
        const classList = Array.from(tabCol.classList);
        classList.forEach(className => {
            if (className.startsWith('row-cols-') || className === 'product-list') {
                tabCol.classList.remove(className);
            }
        });

        // Add new column classes
        tabCol.classList.add(
            `row-cols-${colNum}`,
            `row-cols-sm-${colNum}`,
            `row-cols-md-${colNum}`,
            `row-cols-lg-${colNum}`,
            `row-cols-xl-${colNum}`
        );

        // Add product-list class for single column
        if (colNum === '1') {
            tabCol.classList.add('product-list');
        }

        // Add base classes
        tabCol.classList.add('row', 'row-col-data', 'gy-5', 'woocommerce', 'product-slick');
    }

    QuickSelect() {
        // Check if Select2 is available
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
            console.warn('Select2 not available');
            return;
        }

        // Use event delegation for quick select buttons
        document.addEventListener('click', (event) => {
            const target = event.target;
            if (target.matches('.woosq-btn') || target.closest('.woosq-btn')) {
                this.initializeSelect2();
            }
        });
    }

    initializeSelect2() {
        // Delay initialization to allow DOM updates
        setTimeout(() => {
            const selectElements = document.querySelectorAll('select');
            if (selectElements.length === 0) return;

            selectElements.forEach(select => {
                // Only initialize if not already initialized
                if (!select.classList.contains('select2-hidden-accessible')) {
                    jQuery(select).select2({
                        width: '100%'
                    }).on('select2:open', () => {
                        // Add wide class to container when opened
                        const container = document.querySelector('.select2-container');
                        if (container) container.classList.add('wide');
                    });
                }
            });
        }, 500);
    }

    // Helper function to set a cookie
    setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = `expires=${date.toUTCString()}`;
        document.cookie = `${name}=${encodeURIComponent(value)}; ${expires}; path=/`;
    }

    // Helper function to get a cookie
    getCookie(name) {
        const nameEQ = `${name}=`;
        const cookies = document.cookie.split(';');

        for (let cookie of cookies) {
            cookie = cookie.trim();
            if (cookie.indexOf(nameEQ) === 0) {
                return decodeURIComponent(cookie.substring(nameEQ.length));
            }
        }
        return null;
    }
}