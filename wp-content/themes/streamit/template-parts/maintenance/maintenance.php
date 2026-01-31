<?php
get_template_part('template-parts/maintenance/header');

global $streamit_options;

if (!empty($streamit_options['maintenance_radio'])) {
    $is_maintenance = $streamit_options['maintenance_radio'] == 1;
    $is_coming_soon = $streamit_options['maintenance_radio'] == 2;

    // Select background image and content based on the mode
    $bg_url = $is_maintenance
        ? ($streamit_options['maintenance_bg_image']['url'] ?? '')
        : ($streamit_options['coming_soon_bg_image']['url'] ?? '');

    $title = $is_maintenance
        ? $streamit_options['maintenance_title']
        : $streamit_options['coming_title'];

    $description = $is_maintenance
        ? $streamit_options['mainten_desc']
        : $streamit_options['coming_desc'];

?>
    <div class="container">
        <div class="row">
            <div class="col-sm-12">
                <div class="<?php echo $is_maintenance ? 'maintenance' : 'st-coming'; ?>"
                    <?php if (!empty($bg_url)) : ?>
                    style="background-image: url(<?php echo esc_url($bg_url); ?>);"
                    <?php endif; ?>>
                    <div class="<?php echo $is_coming_soon ? 'st-coming-inner' : ''; ?>">
                        <div class="iq-maintenance-message">
                            <h1 class="iq-maintenance-title"><?php echo esc_html($title); ?></h1>
                            <p class="iq-maintenance-desc"><?php echo esc_html($description); ?></p>
                        </div>

                        <?php if ($is_coming_soon && !empty($streamit_options['opt_date'])) :
                            $date = DateTime::createFromFormat('m/d/Y', $streamit_options['opt_date']);
                            $formatted_date = $date ? $date->format('F d, Y') : ''; ?>
                            <div class="expire_date" id="<?php echo esc_attr($formatted_date); ?>"></div>
                            <ul class="example mb-0 ps-0 countdown">
                                <?php foreach (['Days', 'Hours', 'Minutes', 'Seconds'] as $time_unit) : ?>
                                    <li>
                                        <span class="<?php echo strtolower($time_unit); ?>"><?php echo esc_html__('00', 'streamit'); ?></span>
                                        <p class="<?php echo strtolower($time_unit); ?>_text"><?php echo esc_html($time_unit); ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
}

get_template_part('template-parts/maintenance/footer');
