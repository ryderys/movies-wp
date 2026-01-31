<?php

/**
 * Displays the footer widget area
 *
 * @package WordPress
 * @subpackage streamit
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;
$footer_class = new streamit_footer();
$footer = $footer_class->get_footer_option();

if (count($footer) == 0) {
    return;
}
?>

<?php if (!empty($streamit_options['footer_top']) && $streamit_options['footer_top'] === 'yes') : ?>
    <div class="footer-top">
        <div class="container-fluid">
            <div class="row gy-4">
                <?php if (!empty($footer['value'])) : ?>
                    <?php foreach ($footer['value'] as $key => $item) : ?>
                        <?php if (is_active_sidebar('ct-footer-sidebar-' . ($key + 1))) : ?>
                            <div class="<?php echo esc_attr($item); ?>">
                                <?php dynamic_sidebar('ct-footer-sidebar-' . ($key + 1)); ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
