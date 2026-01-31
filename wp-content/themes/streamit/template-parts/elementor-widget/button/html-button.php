<?php

use Elementor\Icons_Manager;

if (!defined('ABSPATH')) exit;
?>

<div class="<?php echo esc_attr('st-btn-container'); ?>">
    <a class="<?php echo esc_attr(trim($classes)); ?>" href="<?php echo esc_url($link_url); ?>"<?php echo esc_attr($target . $rel); ?>>
        <span class="d-flex align-items-center justify-content-center gap-2">
        <?php 
        if (isset($settings['has_icon']) && $settings['has_icon'] === 'yes') {
            if ($settings['icon_position'] === 'right') {
                echo wp_kses_post($html);
                Icons_Manager::render_icon($settings['button_icon'], ['aria-hidden' => 'true']);
            } elseif ($settings['icon_position'] === 'left') {
                Icons_Manager::render_icon($settings['button_icon'], ['aria-hidden' => 'true']);
                echo wp_kses_post($html);
            } else {
                echo wp_kses_post($html);
            }
        } else {
            echo wp_kses_post($html);
        }
        ?>
        </span>
    </a>
</div>

