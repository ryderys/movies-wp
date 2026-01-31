<?php

/**
 * Plugin Name: Custom Elementor AJAX Select Control
 * Description: A professional custom control for Elementor using Select2, AJAX, and scroll-based pagination.
 * Author: Gemini
 * Version: 1.0.0
 * NOTE: This file is designed to be included in your main Elementor Addon's PHP file.
 */

namespace CustomControls;

use \Elementor\Base_Data_Control;
use \Elementor\Plugin;
use \Elementor\Controls_Manager;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * 2. CUSTOM CONTROL CLASS
 * Defines the control structure, settings, and template.
 */
class Control_Ajax_Select extends Base_Data_Control
{

    /**
     * Get ajax select control type.
     */
    public function get_type()
    {
        return 'st_ajax_select';
    }

    /**
     * Enqueue control scripts and styles.
     */
    public function enqueue()
    {
        // Standard Elementor control asset management
        wp_enqueue_script('select2');
        wp_enqueue_style('select2');
    }

    /**
     * Get ajax select control default settings.
     */
    protected function get_default_settings()
    {
        return [
            'label_block' => true,
            'placeholder' => esc_html__('Select an option', 'textdomain'),
            'ajax_action' => 'streamit_elementor_select_ajax',
            'minimum_input_length' => 2,
            'multiple' => false,
        ];
    }

    public function content_template()
    {
        $control_uid = $this->get_control_uid();
?>
        <style>
            .st-select2-ajax-input-wrapper .select2-container {
                z-index: inherit !important;

            }
        </style>
        <div class="elementor-control-field">
            <# if ( data.label ) { #>
                <label for="<?php echo esc_attr($control_uid); ?>" class="elementor-control-title">{{{ data.label }}}</label>
                <# } #>
                    <div class="elementor-control-input-wrapper st-select2-ajax-input-wrapper">
                        <select
                            id="<?php echo esc_attr($control_uid); ?>"
                            class="streamit_elementor_select2_ajax_search"
                            data-ajax-action="{{ data.ajax_action }}"
                            data-min-input-length="{{ data.minimum_input_length }}"
                            data-placeholder="{{ data.placeholder }}"
                            {{ data.multiple ? 'multiple' : '' }}
                            data-setting="{{ data.name }}"
                            <# if ( data.custom_attributes ) {
                            _.each( data.custom_attributes, function( value, key ) { #>
                            {{ key }}='{{ value }}'
                            <# }); } #>
                                >
                                <# if ( data.controlValue ) { #>
                                    <# if ( data.multiple ) { #>
                                        <# _.each( data.controlValue, function( value ) { #>
                                            <option value="{{ value }}" selected>{{ value }}</option>
                                            <# } ); #>
                                                <# } else { #>
                                                    <option value="{{ data.controlValue }}" selected>{{ data.controlValue }}</option>
                                                    <# } #>
                                                        <# } #>
                        </select>
                    </div>
                    <# if ( data.description ) { #>
                        <div class="elementor-control-field-description">{{{ data.description }}}</div>
                        <# } #>
        </div>
<?php
    }
}
