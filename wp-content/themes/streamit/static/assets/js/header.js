export default class Header {
    constructor() {
        this.isMobileView = window.innerWidth <= 1199;
        this.menuContainer = document.querySelector('#site-navigation .offcanvas-body #css_prefix-menu-container');

        if (this.menuContainer && this.isMobileView) {
            this.menuContainer.classList.add('is-mobile-view');
        }

        this.bindEvents();
    }

    bindEvents() {
        // Only add dropdown handler in mobile view
        if (this.isMobileView) {
            document.body.addEventListener('click', (event) => {
                const target = event.target.closest('.fa-caret-down');
                if (target) {
                    event.preventDefault();
                    this.toggleDropdown(target);
                }
            });
        }
    }

    toggleDropdown(trigger) {
        const parentMenu = trigger.closest('.menu-item');
        if (!parentMenu) return;

        const subMenu = parentMenu.querySelector('.sub-menu');
        if (!subMenu) return;

        const isOpen = parentMenu.classList.contains('sfHover');
        parentMenu.classList.toggle('sfHover', !isOpen);
        subMenu.style.display = isOpen ? 'none' : 'block';
    }
}
