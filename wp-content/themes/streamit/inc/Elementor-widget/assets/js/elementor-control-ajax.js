import { get } from "../../../../static/assets/utilities/ajax.js";

class ElementorAjaxSearchControl {
    constructor() {
        this.init();
    }

    init() {
        if (this.isElementorReady()) {
            this.registerControl();
        } else {
            this.waitForElementor();
        }
    }

    isElementorReady() {
        return typeof elementor !== 'undefined' && elementor.modules?.controls;
    }

    waitForElementor() {
        const checkInterval = setInterval(() => {
            if (this.isElementorReady()) {
                clearInterval(checkInterval);
                this.registerControl();
            }
        }, 500);
    }

    registerControl() {
        const AjaxSelectControl = elementor.modules.controls.Select2.extend({
            onReady: function () {
                this.setupSelect2();
            },

            onBeforeDestroy: function () {
                this.destroySelect2();
            },

            setupSelect2: function () {
                const self = this;
                const selectEl = self.$el.find('.streamit_elementor_select2_ajax_search');
                const ajaxAction = selectEl.data('ajax-action');
                const minInput = selectEl.data('min-input-length');
                const placeholder = selectEl.data('placeholder');

                // Read extra AJAX params
                let extraParams = {};
                const dataAttr = selectEl.attr('data-ajax-params');
                if (dataAttr) {
                    try {
                        extraParams = JSON.parse(dataAttr);
                    } catch (e) {
                        console.error(`Invalid JSON in data-ajax-params:`, dataAttr);
                    }
                }
                extraParams.elementor_post_id = elementor.config.post_id;

                // Collect preselected values (IDs only)
                const selectedIDs = selectEl.val();

                // If widget has saved IDs → fetch real labels first
                if (selectedIDs && selectedIDs.length) {
                    get(ajaxAction, {
                        preload_ids: selectedIDs,
                        ...extraParams
                    }).done((response) => {
                        // Replace <option> list with real movie names
                        selectEl.empty();
                        if (response.success && Array.isArray(response.data?.items)) {
                            response.data.items.forEach(item => {
                                selectEl.append(new Option(item.text, item.id, true, true));
                            });
                        }

                        // Now initialize Select2 normally
                        self.initSelect2Ajax(selectEl, ajaxAction, extraParams, response.data, self);
                        self.initializeSortable(selectEl);
                    });
                } else {
                    // No saved values → initialize normally
                    self.initSelect2Ajax(selectEl, ajaxAction, extraParams, [], self);
                    setTimeout(() => {
                        self.initializeSortable(selectEl);
                    }, 500);
                }

                selectEl.on('change', () => {
                    self.setValue(selectEl.val());
                    // Re-initialize sortable after change to catch new selections
                    setTimeout(() => {
                        self.initializeSortable(selectEl);
                    }, 100);
                });
            },

            initializeSortable: function (selectEl) {
                const self = this;

                // Wait for Select2 to be fully rendered
                setTimeout(() => {
                    const $select2Container = selectEl.next('.select2-container');

                    if ($select2Container.length === 0) {
                        console.warn('Select2 container not found, retrying...');
                        setTimeout(() => self.initializeSortable(selectEl), 200);
                        return;
                    }

                    const $sortableList = $select2Container.find('ul.select2-selection__rendered');

                    if ($sortableList.length === 0) {
                        console.warn('Select2 selection list not found');
                        return;
                    }

                    // Destroy existing sortable if any
                    if ($sortableList.hasClass('ui-sortable')) {
                        $sortableList.sortable('destroy');
                    }

                    // Get current selected values in order
                    let currentOrder = selectEl.val() || [];

                    // Initialize sortable
                    $sortableList.sortable({
                        items: 'li.select2-selection__choice',
                        containment: 'parent',
                        tolerance: 'pointer',
                        cursor: 'move',
                        placeholder: 'select2-sortable-placeholder',
                        forcePlaceholderSize: true,

                        start: function (e, ui) {
                            ui.placeholder.width(ui.item.outerWidth());
                            // Close dropdown during drag
                            selectEl.select2('close');
                        },

                        update: function (event, ui) {
                            const newOrder = [];

                            // Get new order from DOM
                            $sortableList.find('li.select2-selection__choice').each(function () {
                                const $choice = jQuery(this);
                                const value = $choice.data('data')?.id;

                                if (value) {
                                    newOrder.push(value);
                                } else {
                                    // Fallback: try to extract from title or text
                                    const title = $choice.attr('title') || $choice.text().replace('×', '').trim();
                                    const $option = selectEl.find('option').filter(function () {
                                        return jQuery(this).text().trim() === title.trim();
                                    });
                                    if ($option.length) {
                                        newOrder.push($option.val());
                                    }
                                }
                            });

                            if (newOrder.length > 0) {
                                // Update the select element with new order
                                selectEl.val(newOrder).trigger('change');

                                // Update Elementor control value
                                self.setValue(newOrder);

                                // Reorder options in DOM to match the visual order
                                self.reorderSelectOptions(selectEl, newOrder);

                                console.log('New order:', newOrder);
                            }
                        }
                    });

                    // Add custom styles for sortable
                    if (!jQuery('style#elementor-select2-sortable').length) {
                        jQuery('head').append(`
                            <style id="elementor-select2-sortable">
                                .select2-sortable-placeholder {
                                    background: #f8f9fa !important;
                                    border: 2px dashed #dee2e6 !important;
                                    height: 32px !important;
                                    margin: 2px !important;
                                }
                                .select2-selection__choice {
                                    cursor: move !important;
                                    user-select: none !important;
                                }
                                .select2-selection__choice:hover {
                                    background-color: #e9ecef !important;
                                }
                            </style>
                        `);
                    }

                }, 300);
            },

            reorderSelectOptions: function (selectEl, newOrder) {
                // Reorder the actual <option> elements in the select to match visual order
                const $options = selectEl.find('option:selected').detach();
                const optionsMap = {};

                // Create map of values to option elements
                $options.each(function () {
                    optionsMap[jQuery(this).val()] = jQuery(this);
                });

                // Append options in the new order
                newOrder.forEach(function (value) {
                    if (optionsMap[value]) {
                        selectEl.append(optionsMap[value]);
                    }
                });
            },

            initSelect2Ajax: function ($field, route, extraParams = {}, initialData = null, controlInstance = null) {
                const self = this;

                $field.select2({
                    width: '100%',
                    placeholder: 'Select an option',
                    allowClear: true,
                    dropdownParent: controlInstance ? controlInstance.$el : $field.parent(),
                    minimumInputLength: $field.data('min-input-length') || 2,
                    ajax: {
                        transport: (params, success, failure) => {
                            const data = {
                                search: params.data.term || '',
                                page: params.data.page || 1,
                                ...extraParams,
                            };

                            get(route, data)
                                .done(success)
                                .fail(failure);
                        },
                        delay: 250,
                        dataType: 'json',
                        processResults: (response, params) => {
                            params.page = params.page || 1;

                            let results = [];
                            if (response.success && Array.isArray(response.data?.items)) {
                                results = response.data.items.map(item => ({
                                    id: item.id,
                                    text: item.text,
                                }));
                            } else if (response.success && response.data?.results) {
                                results = response.data.results;
                            }

                            return {
                                results,
                                pagination: {
                                    more: response.data?.hasMore || response.data?.pagination?.more || false,
                                },
                            };
                        },
                        cache: true,
                    },
                    language: {
                        inputTooShort: (args) =>
                            `Please enter ${args.minimum - args.input.length} or more characters.`
                    }
                });

                // Handle selection events to ensure new items are added to the end
                $field.on('select2:select', function (e) {
                    const selectedId = e.params.data.id;
                    const currentValues = $field.val() || [];

                    // Remove the ID if it already exists (to avoid duplicates)
                    const filteredValues = currentValues.filter(val => val != selectedId);

                    // Add the new selection to the end
                    filteredValues.push(selectedId);

                    // Update the field
                    $field.val(filteredValues).trigger('change');

                    // Update Elementor control
                    self.setValue(filteredValues);

                    // Reinitialize sortable to include the new item
                    setTimeout(() => {
                        self.initializeSortable($field);
                    }, 100);
                });

                // Handle unselection
                $field.on('select2:unselect', function (e) {
                    setTimeout(() => {
                        self.initializeSortable($field);
                    }, 100);
                });

                // Handle preselected values for edit forms
                if (initialData && initialData.length > 0) {
                    const selectedItems = Array.isArray(initialData) ? initialData : [initialData];
                    selectedItems.forEach(item => {
                        if (!$field.find(`option[value='${item.id}']`).length) {
                            const newOption = new Option(item.text, item.id, true, true);
                            $field.append(newOption);
                        }
                    });
                    $field.trigger('change');
                }
            },

            destroySelect2: function () {
                const selectEl = this.$el.find('.streamit_elementor_select2_ajax_search');
                if (selectEl.data('select2')) {
                    selectEl.select2('destroy');
                }

                // Also destroy sortable
                const $select2Container = selectEl.next('.select2-container');
                if ($select2Container.length) {
                    const $sortableList = $select2Container.find('ul.select2-selection__rendered');
                    if ($sortableList.hasClass('ui-sortable')) {
                        $sortableList.sortable('destroy');
                    }
                }
            }
        }, {
            controlType: 'st_ajax_select'
        });

        elementor.addControlView('st_ajax_select', AjaxSelectControl);
    }
}

// Initialize the control
new ElementorAjaxSearchControl();