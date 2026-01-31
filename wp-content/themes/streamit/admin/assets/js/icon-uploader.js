export default class IconUploader {
    constructor() {
        // Bind the click event once when class is instantiated
        jQuery(document.body).on("click", ".upload-streamit-menu-icon", (e) => this.initUploader(e));
    }

    initUploader(event) {
        event.preventDefault();

        const $button = jQuery(event.currentTarget);
        const $input = $button.prev("input");
        const $preview = $button.closest("label").siblings(".streamit-menu-icon-preview");

        // Reuse wp.media frame if already created (good for performance)
        if (this.frame) {
            this.frame.open();
            return;
        }

        this.frame = wp.media({
            title: "Select or Upload SVG Icon",
            button: { text: "Use this icon" },
            library: { type: ["image"] },
            multiple: false
        });

        this.frame.on("select", () => {
            const attachment = this.frame.state().get("selection").first().toJSON();
            const url = attachment.url;

            $input.val(url).trigger("change"); // ✅ trigger change event if something listens to it
            $preview.html(`<img src="${url}" style="width: 24px; height: 24px;" />`);
        });

        this.frame.open();
    }
}
