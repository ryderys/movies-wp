<?php
/**
 * The template for displaying 404 pages (Not Found).
 *
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
global $streamit_options;
?>

<div class="row flex-column justify-content-center align-items-center">
    <div class="col-sm-12 text-center">
        <?php
        $bgurl = isset($streamit_options['404_banner_image']['url']) ? $streamit_options['404_banner_image']['url'] : get_template_directory_uri() . '/static/assets/images/404.png';
        ?>
    
        <div class="fourzero-image mb-5">
            <img src="<?php echo esc_url($bgurl); ?>" alt="<?php esc_attr_e('404', 'streamit'); ?>" />
        </div>
    
        <h4><?php echo isset($streamit_options['404_title']) ? esc_html($streamit_options['404_title']) : esc_html_e('Oops! This Page is Not Found.', 'streamit'); ?></h4>
    
        <p class="mb-3">
            <?php echo isset($streamit_options['404_description']) ? esc_html($streamit_options['404_description']) : esc_html_e('The requested page does not exist.', 'streamit'); ?>
        </p>
    
        <div class="d-block">
            <?php $btn_text = isset($streamit_options['404_backtohome_title']) ? esc_html($streamit_options['404_backtohome_title']) : esc_html('Back To Home', 'streamit'); 
            st_get_blog_readmore_link(home_url(), $btn_text); ?>     
        </div>
    </div>

</div>