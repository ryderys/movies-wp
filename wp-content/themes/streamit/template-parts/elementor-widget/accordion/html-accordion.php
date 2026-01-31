<div class="<?php echo esc_attr($accordion_classes); ?>" id="accordionExample-<?php echo esc_attr($id_int); ?>">
    <?php
    $i = 1; // Initialize counter for unique IDs.
    foreach ($tabs as $index => $item) :
        // Determine active state and styles for the first item.
        $is_first_item = ($i === 1);
        $show = $is_first_item ? 'show' : '';
        $style = $is_first_item ? 'style="display:block"' : '';
        $adactive = $is_first_item ? '' : 'collapsed';
        ?>
        <div class="accordion-item">
            <div class="accordion-header">
                <button 
                    class="accordion-button <?php echo esc_attr($adactive); ?>" 
                    type="button" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#accordion-item-<?php echo esc_attr($i); ?>-<?php echo esc_attr($id_int); ?>" 
                    aria-expanded="<?php echo esc_attr($is_first_item ? 'true' : 'false'); ?>" 
                    aria-controls="accordion-item-<?php echo esc_attr($i); ?>-<?php echo esc_attr($id_int); ?>"
                    >
                    <span class="accordion-title st-heading-title">
                        <?php echo esc_html($item['tab_title']); ?>
                    </span>
                    <?php if (!empty($settings['has_icon']) && $settings['has_icon'] === 'yes') : ?>
                        <span class="st-icon-right">
                            <span class="active_icon">
                                <?php
                                if (!empty($settings['active_icon']['library'])) {
                                    \Elementor\Icons_Manager::render_icon($settings['active_icon'], ['aria-hidden' => 'true']);
                                } else {
                                    echo st_get_icon('minus-2');
                                }
                                ?>
                            </span>
                            <span class="inactive_icon">
                                <?php
                                if (!empty($settings['inactive_icon']['library'])) {
                                    \Elementor\Icons_Manager::render_icon($settings['inactive_icon'], ['aria-hidden' => 'true']);
                                } else {
                                    echo st_get_icon('plus');
                                }
                                ?>
                            </span>
                        </span>
                    <?php endif; ?>
                </button>
            </div>
            <div 
                id="accordion-item-<?php echo esc_attr($i); ?>-<?php echo esc_attr($id_int); ?>" 
                class="accordion-collapse fade collapse <?php echo esc_attr($show); ?>" 
                data-bs-parent="#accordionExample-<?php echo esc_attr($id_int); ?>">
                <div class="accordion-body">
                    <p class="st-content-text">
                        <?php echo esc_html($item['tab_content']); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php $i++; // Increment counter.
    endforeach; ?>
</div>
