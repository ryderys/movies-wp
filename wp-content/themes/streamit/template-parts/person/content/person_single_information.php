<?php

/**
 * The template for displaying information.
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$birthday = !empty($st_data->get_meta('_birthday')) ? $st_data->get_meta('_birthday') : '';
$deathday = !empty($st_data->get_meta('_deathday')) ? $st_data->get_meta('_deathday') : '';
$born_place = !empty($st_data->get_meta('_place_of_birth')) ? $st_data->get_meta('_place_of_birth') : '';
$height =  !empty($st_data->get_meta('cast_height')) ? $st_data->get_meta('cast_height') : '';
$known = !empty($st_data->get_meta('_also_known_as')) ? $st_data->get_meta('_also_known_as') : '';
?>
<?php if(!empty($birthday) || !empty($deathday) || !empty($born_place) || !empty($height) || !empty($known) ) : ?>
<div class="person-information mt-5 pt-4">
    <div class="title">
        <h5 class="title-tag"><?php esc_html_e('Personal details', 'streamit'); ?></h5>
    </div>
    <ul class="list-inline p-0 m-0">
        <?php if (!empty($st_data->get_meta('cast_official_site'))) : ?>
            <li class="mb-3">
                <h5 class="mt-0 mb-2"><?php esc_html_e('Official sites :', 'streamit'); ?></h5>
                <p class="m-0"><a href="<?php echo esc_url($st_data->get_meta('cast_official_site')); ?>"><?php echo esc_url($st_data->get_meta('cast_official_site')); ?></a></p>
            </li>
        <?php endif; ?>
        <?php if (!empty($birthday) || !empty($born_place)) : ?>
            <li class="mb-3">
                <h5 class="mt-0 mb-2"><?php esc_html_e('Born:', 'streamit'); ?></h5>
                <ul class="person-birth-detail d-flex align-items-center flex-wrap column-gap-5 row-gap-1 p-0 m-0">
                    <?php if (!empty($birthday)) : ?>
                        <li><?php esc_html_e('Birthday:  ', 'streamit'); ?> <?php echo esc_html($birthday); ?> </li>
                    <?php endif; ?>
                    <?php if (!empty($born_place)) : ?>
                        <li><?php esc_html_e('Born Place:  ', 'streamit'); ?> <?php echo esc_html($born_place); ?></li>
                    <?php endif; ?>
                </ul>
            </li>
        <?php endif; ?>
        <?php if (!empty($deathday)) : ?>
            <li class="mb-3">
                <h5 class="mt-0 mb-2"><?php esc_html_e('Deathday:  ', 'streamit'); ?></h5>
                <p class="m-0"><?php echo esc_html($deathday); ?></p>
            </li>
        <?php endif; ?>
        <?php if ($height) : ?>
            <li class="mb-3">
                <h5 class="mt-0 mb-2"><?php esc_html_e('Height:  ', 'streamit'); ?></h5>
                <p class="m-0"><?php echo esc_html($height); ?></p>
            </li>
        <?php endif; ?>
        <?php if ($known) : ?>
            <li>
                <h5 class="mt-0 mb-2"><?php esc_html_e('Also Known As:  ', 'streamit'); ?></h5>
                <p class="m-0"><?php echo esc_html($known); ?></p>
            </li>
        <?php endif; ?>

    </ul>
</div>
<?php endif;