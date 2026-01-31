<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

?>

<div class="css_prefix-product-box">
    <a href="<?php echo esc_url(get_term_link($parent_id)) ?>" class="css_prefix-card-link"></a>
    <div class="css_prefix-card-img-overlay" style="background-image: url(<?php echo esc_url($image_url) ?>)"> </div>

    <div class="css_prefix-card-body w-100">
        <?php if ($parent_id->parent != 0) { ?>
            <h5 class="card-title"><?php echo esc_html($parent_id->name); ?> </h5>
        <?php } ?>

        <h4 class="css_prefix-parent-heading">
            <?php echo esc_html($term->name); ?>
        </h4>

        <?php if (!empty($settings['button_text'])): ?>
            <button class="btn btn-link p-0">
                <?php
                // Check if the icon should be displayed and render it using ob_start()
                $icon_html = '';
                if ($settings['has_icon'] === 'yes' && !empty($settings['button_icon']['value'])) {
                    ob_start(); // Start output buffering
                    \Elementor\Icons_Manager::render_icon($settings['button_icon'], ['aria-hidden' => 'true']);
                    $icon_svg = ob_get_clean(); // Get the buffered output and clean the buffer
                    $icon_html = '<span class="icon-wrapper">' . $icon_svg . '</span>';
                }

                // Render the button text with the icon (if any)
                if ($settings['icon_position'] === 'left') {
                    echo $icon_html . '<span class="button-text">' . esc_html($settings['button_text']) . '</span>';
                } elseif ($settings['icon_position'] === 'right') {
                    echo '<span class="button-text">' . esc_html($settings['button_text']) . '</span>' . $icon_html;
                } else {
                    echo '<span class="button-text">' . esc_html($settings['button_text']) . '</span>';
                }
                ?>
            </button>
        <?php endif; ?>

    </div>
</div>
