import './redux-template.js';
import { post } from "./ajax.js";

export default class pluginInstall {
    constructor() {

        this.setupEventHandlers();
    }

    setupEventHandlers() {
        // install_import_plugin
        jQuery(document.body).on("click", "#install_import_plugin", this.ImportPluginInstall.bind(this));

    }

    ImportPluginInstall(event) {
        event.preventDefault();
        let submitButton = jQuery(event.target);
        submitButton.attr('type', 'button'); // Change type to 'button' to prevent form submission

        // Show the loader and hide the submit text (assuming you have a loader inside the button)
        let loader = submitButton.find('.st-loader');
        loader.css('display', '');
        submitButton.prop('disabled', true);
        var data = {};
        post('install_import_plugin', data)
            .then(res => {
                console.log(res);
                window.location.href = stAdminAjax.st_migrationUrl;

            })
            .catch(err => {
                console.log(err);

            });
    }

}
