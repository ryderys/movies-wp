import './../css/redux-template.css';
import './../css/redux-font/redux-custom-font.css';

import pluginInstall from './../js/plugin-install.js';
import IconUploader from './../js/icon-uploader.js';

jQuery(document).ready(function ($) {

    const DashboardSideBarModule = {
        'pluginInstall': new pluginInstall(),
        'IconUploader': new IconUploader()
    };
    window['DashboardSideBarModule'] = DashboardSideBarModule;

});
