<?php
if (!defined('ABSPATH')) exit;
?>

<div class="st-title-box ">

    <<?php echo esc_html( $title_tag); ?> class="st-title <?php echo esc_attr( $settings['has_texture'] === 'yes' ? 'texture-text' : '' ); ?>">
        <?php echo wp_kses( esc_html( $section_title ), 'post' ); ?>
    </<?php echo esc_html( $title_tag ) ?>>

    <?php if ($settings['has_sub_title'] == 'yes') { ?>
        <span class="st-sub-title">
            <?php echo esc_html( $settings['section_sub_title'] ) ?>
        </span>
    <?php } ?>

    <?php if ( !empty( $settings['description'] ) && $settings['has_description'] == 'yes') { ?>
        <p class="st-title-desc">
            <?php echo wp_kses_post( $settings['description'] ) ?>
        </p>
    <?php } ?>
</div>
