<?php

if (!defined('ABSPATH')) exit;

?>

<?php if (!empty($timer_title)) { ?>
    <<?php echo esc_attr($title_tag); ?> class="css_prefix-title css_prefix-heading-title">
        <?php echo $timer_title; ?>
    </<?php echo esc_attr($title_tag); ?>>
<?php } ?>
<div class="st-count-down <?php echo esc_attr($align); ?>" data-labels="true" data-date="<?php echo esc_attr($future_date); ?>" data-format="<?php echo esc_attr($timer_format); ?>">
    <span class="st-data-countdown-timer"></span>
</div>
