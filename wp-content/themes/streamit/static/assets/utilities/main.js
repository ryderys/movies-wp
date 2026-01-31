import 'bootstrap';
import '@popperjs/core';
import './../icon-font/iconly.css';
import './../scss/theme.scss';

// --- Helper: DOM ready and after body load ---
const domReady = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
};

// --- Lazy loader: import module only if needed ---
const loadIfExists = async (selector, importFn) => {
    if (document.querySelector(selector)) {
        await importFn();
    }
};

// --- Initialize main modules (non-lazy) ---
const initCoreModules = async () => {
    const modules = {};

    const [
        { default: Component },
        { default: Header },
        { default: GeneralAjax },
        { default: Authentication },
    ] = await Promise.all([
        import('./../js/component.js'),
        import('./../js/header.js'),
        import('./../js/general-ajax.js'),
        import('./../js/authentication.js'),
    ]);

    modules.component = new Component();
    modules.header = new Header();
    modules.GeneralAjax = new GeneralAjax();
    modules.authentication = new Authentication();

    window.DashboardSideBarModule = modules;
};

// --- Deferred or conditional modules ---
const initConditionalModules = async () => {
    // WooCommerce (only if WooCommerce elements exist)
    await loadIfExists('.woocommerce', async () => {
        const { default: WooCommerce } = await import('./../js/woocommerce.js');
        window.DashboardSideBarModule.woocommerce = new WooCommerce();
    });

    // ArchiveAppend (only for archive pages)
    await loadIfExists('.data-listing', async () => {
        const { default: ArchiveAppend } = await import('./../js/archive-append.js');
        window.DashboardSideBarModule.ArchiveAppend = new ArchiveAppend();
    });

    // MediaPlayer (only if video player exists)
    await loadIfExists('.streamit-trailer-player-ctrl, #streamit_player', async () => {
        const { default: MediaPlayer } = await import('./../js/mediaplayer.js');
        window.DashboardSideBarModule.MediaPlayer = new MediaPlayer();
    });

    // StreamitInvoice (only for invoice page)
    await loadIfExists('.streamit-download-invoice', async () => {
        const { default: StreamitInvoice } = await import('./../js/streamit-invoice.js');
        window.DashboardSideBarModule.StreamitInvoice = new StreamitInvoice();
    });
};

// --- Initialize Select2 dropdowns (requires jQuery) ---
const initSelect2 = () => {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
    const $ = jQuery;

    // Exclude specific selects from global init
    const excluded = '#st_playlist_post_type';

    // Global Select2 for all others
    $('select').not(excluded).select2({ width: '100%' });

    // Fullwidth select handling
    $('select[data-is-fullwidth="true"]').each(function () {
        const $select = $(this);

        $select.select2({
            width: '',
            minimumResultsForSearch: -1,
            dropdownParent: $select.closest('.select-wrapper'),
        });

        $select.on('select2:open', () => {
            requestAnimationFrame(() => {
                $('.select2-dropdown').addClass('dropdown-fullwidth');
            });
        });
    });

    // Playlist select (custom settings)
    const $playlistSelect = $('#st_playlist_post_type');

    if ($playlistSelect.length && $playlistSelect.is('select')) {
        $playlistSelect.select2({
            width: '100%',
            dropdownParent: $('#creatplaylistModal'),
            placeholder: stAjax.playlist.placeholder,
            allowClear: true
        });
    }

    document.querySelectorAll('.select2-container').forEach((el) => {
        el.classList.add('wide');
    });
};


// --- Initialize all after DOM is ready ---
domReady(async () => {
    await initCoreModules();

    // Defer non-critical logic
    requestIdleCallback(async () => {
        await initConditionalModules();
        setTimeout(initSelect2, 100);
    });
});
