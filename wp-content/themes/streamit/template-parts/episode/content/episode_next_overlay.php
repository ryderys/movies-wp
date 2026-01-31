<?php

/**
 * The template for displaying next episode overlay card.
 *
 * @package Streamit
 */
$thumbnail = isset($args['thumbnail']) ? $args['thumbnail'] : '';
$seasonNumber = isset($args['seasonNumber']) ? esc_html($args['seasonNumber']) : '1';
$title = isset($args['title']) ? esc_html($args['title']) : '';
$countdown = isset($args['countdown']) ? esc_html($args['countdown']) : '10';

?>
<div class="next-episode-content">
    <button type="button" class="close-btn" aria-label="<?php esc_attr_e('Close', 'streamit'); ?>">
        <?php echo st_get_icon('cross', ['class' => 'close-icon']); ?>
    </button>
    <div class="next-episode">
        <div class="next-episode__thumbnail">
            <img src="<?php echo $thumbnail; ?>"
                alt="<?php esc_attr_e('Next Episode', 'streamit'); ?>"
                onerror="this.onerror=null; this.src='<?php echo esc_url(streamit_placeholder_image()); ?>'" />
            <div class="play-icon">
                <?php echo st_get_icon('play', ['class' => 'play-button-icon']); ?>
            </div>
        </div>
        <div class="next-episode__info">
            <div class="season-label">
                <?php echo esc_html__('Season', 'streamit') . ' ' . $seasonNumber; ?>
            </div>
            <h6><?php printf(esc_html__('Next: %s', 'streamit'), $title); ?></h6>
            <div class="countdown">
                <?php echo st_get_icon('clock', ['class' => 'timer-icon']); ?>
                <span><?php esc_html_e('Playing in', 'streamit'); ?> <span class="timer"><?php echo $countdown; ?></span><?php esc_html_e('s', 'streamit'); ?></span>
            </div>
        </div>
    </div>
</div>
<div class="next-episode-button-wrapper">
    <button class="btn btn-secondary ep-close-btn">
        <span class="d-flex align-items-center gap-2">
            <span><?php echo esc_html__('Continue Watch', 'streamit'); ?></span>
        </span>
    </button>
    <button class="btn btn-outline-primary next-episode-button" style="--data-fill:20%">
        <span class="d-flex align-items-center gap-2">
            <span><?php echo esc_html__('Next Episode', 'streamit'); ?></span>
            <?php echo st_get_icon('play', ['class' => 'play-button-icon']); ?>
        </span>
    </button>
</div>