class St_Redux_Handler {
    constructor() {

        if (typeof custom_redux_options_params === "undefined") {
            return;
        }
        this.rootStyle = custom_redux_options_params.root;
        this.isDarkMode = custom_redux_options_params.is_dark_mode;
        this.ajaxUrl = custom_redux_options_params.ajaxUrl;
        this.debounceTimeout = null;
        this.init();
    }

    init() {
        document.addEventListener('click', this.handleClick.bind(this), true);
        this.bindSearchEvents();
    }

    handleClick(event) {
        const element = event.target;

        if (element.closest('.redux-dark-mode')) {
            this.toggleDarkMode();
            this.saveOption();
        } else if (element.closest('.searched-tab')) {
            this.handleSearchedTab(element);
        }

        this.toggleSearchResults(element);
    }

    toggleDarkMode() {
        if (this.isDarkMode) {
            const styleEl = document.createElement('style');
            styleEl.id = "redux-template-inline-css";
            styleEl.append(this.rootStyle);
            document.head.appendChild(styleEl);
            document.querySelector(".redux-content").classList.add("light-mode");
            this.isDarkMode = 0;
        } else {
            document.getElementById('redux-template-inline-css')?.remove();
            document.querySelector(".redux-content").classList.remove("light-mode");
            this.isDarkMode = 1;
        }
        custom_redux_options_params.is_dark_mode = this.isDarkMode;
    }

    handleSearchedTab(element) {
        const dataRel = element.dataset.rel;
        document.querySelector(".redux-main").classList.remove("css_prefix-searched");
        document.querySelector(`a[data-rel='${dataRel}']:not(.searched-tab)`)?.click();
        document.querySelector(".css_prefix-redux-search").value = "";
        document.querySelector(".result-wrap")?.remove();
    }

    toggleSearchResults(element) {
        const domHasSearchResult = document.querySelector(".result-wrap") !== null;
        if (!element.closest(".redux-search")) {
            if (domHasSearchResult) document.querySelector(".result-wrap").style.display = "none";
        } else {
            if (domHasSearchResult) document.querySelector(".result-wrap").style.display = "block";
        }
    }

    saveOption() {
        clearTimeout(this.debounceTimeout);
        this.debounceTimeout = setTimeout(() => {
            const xhr = new XMLHttpRequest();
            const url = `${this.ajaxUrl}?action=st_save_redux_style_action&is_dark_mode=${this.isDarkMode}`;
            xhr.open("GET", url, true);
            xhr.send();
        }, 500);
    }

    bindSearchEvents() {
        jQuery(document).ready(() => {
            jQuery('.css_prefix-redux-search').on('keypress', (evt) => {
                if (evt.charCode === 13 || evt.keyCode === 13) {
                    return false;
                }
                clearTimeout(this.debounceTimeout);
                this.debounceTimeout = setTimeout(() => this.handleSearch(evt), 400);
            });
        });
    }

    handleSearch(event) {
        const searchString = jQuery(event.target).val().toLowerCase();
        if (searchString.length < 3) return;
        const searchArray = searchString.split(' ');
        const parent = jQuery(event.target).parents('.redux-container:first');
        jQuery(".result-wrap").remove();

        if (searchString) {
            jQuery('.redux-search').append("<div class='result-wrap'></div>");
            parent.find('.redux-main').addClass('css_prefix-searched');
        } else {
            parent.find('.redux-main').removeClass('css_prefix-searched');
        }

        const titles = [];
        parent.find('.form-table tr').each(function () {
            const text = jQuery(this).find('.redux_field_th').text().toLowerCase();
            if (!text) return;

            const isMatch = searchArray.every(searchStr => text.includes(searchStr));
            if (isMatch) {
                if (jQuery(".redux-main").hasClass("css_prefix-searched")) {
                    const groupTab = jQuery(this).closest(".redux-group-tab");
                    const title = groupTab.find("h2:first").html();
                    if (!titles.includes(title)) {
                        titles.push(title);
                        const dataRel = groupTab.data("rel");
                        jQuery('.result-wrap').append(`<a href='javascript:void(0);' data-key='${dataRel}' data-rel='${dataRel}' class='searched-tab'>${title}</a>`);
                    }
                } else {
                    jQuery(".result-wrap").remove();
                }
            }
        });
    }
}

// Initialize the class
new St_Redux_Handler();
