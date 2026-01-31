<?php
if (!defined('ABSPATH')) exit;
?>

<div class="section-spacing-bottom">
    <div class="streamit-person-title">
        <div class="title d-flex align-items-center justify-content-between">
            <?php if (!empty($slider_title)) : ?>
                <<?php echo esc_attr($title_tag); ?> class="title-tag">
                    <?php echo esc_html($slider_title); ?>
                </<?php echo esc_attr($title_tag); ?>>
            <?php endif; ?>

            <?php if (!empty($settings['use_custom_link_text'])) : ?>
                <div class="view-all-btn">
                    <a href="<?php echo esc_url(streamit_get_permalink('person')); ?>">
                        <?php echo esc_html($settings['use_custom_link_text']); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="css_prefix-slick-general st-skeleton"
        data-slider_settings=<?php echo esc_attr(wp_json_encode($args['slick_settings'])) ?>
        data-extra_settings='<?php echo wp_json_encode(($args['settings']['nav-arrow'] === "true" ? true : false)); ?>'
        data-slingle_slider_settings=<?php echo wp_json_encode($args['slingle_slider_settings']); ?>>

        <?php foreach ($results as $details) :
            $person = $details['data'] ?? null;
            if (!$person) {
                continue;
            }

            $person_id    = $person->get_id();
            $person_type  = $person->get_post_type();
            $person_slug  = $person->get_post_name();
            $person_title = $person->get_post_title();
            $image_id     = $person->get_meta('thumbnail_id');

            // Get up to 2 person categories
            $terms_data = [];
            $terms_ids  = streamit_get_term_relationships($person_id, 'person_category');
            if (!empty($terms_ids)) {
                $terms_data = streamit_get_terms(['per_page' => 2, 'include' => $terms_ids])->results;
            }
        ?>
            <div class="slick-item">
                <div class="person-card position-relative">
                    <div class="cast-images position-relative">
                        <a href="<?php echo esc_url(streamit_get_permalink($person_type, $person_slug)); ?>" class="color-inherit">
                            <?php
                            echo streamit_render_image([
                                'attachment_id' => $image_id,
                                'class'         => 'img-fluid',
                                'alt'           => esc_attr($person_title),
                                'decoding'      => 'async',
                            ]);
                            ?>
                        </a>
                    </div>

                    <div class="person-detail">
                        <h6 class="person-title">
                            <a href="<?php echo esc_url(streamit_get_permalink($person_type, $person_slug)); ?>" class="color-inherit">
                                <?php echo esc_html($person_title); ?>
                            </a>
                        </h6>

                        <?php if (!empty($details['character'])) : ?>
                            <ul class="d-flex align-items-center justify-content-center gap-2 list-inline p-0 m-0">
                                <li><?php echo esc_html($details['character']); ?></li>
                            </ul>
                        <?php endif; ?>

                        <?php if (!empty($terms_data) && is_array($terms_data)) : ?>
                            <ul class="d-flex align-items-center justify-content-center gap-2 list-inline p-0 m-0">
                                <?php foreach ($terms_data as $term) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(streamit_get_permalink('person_category', $term->get_term_slug())); ?>" class="person-cats d-block">
                                            <?php echo esc_html(wp_unslash($term->get_term_name())); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>