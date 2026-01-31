<?php

if (!defined('ABSPATH')) exit;
if (!class_exists('WooCommerce')) return;

?>

<div class="slick-product">
    <?php echo do_shortcode('[' . $settings['product_type'] . ' per_page="' . $settings['woo_per_page'] . ' " ' . $details['category'] . ' order="' . $settings['woo_order'] . '" paginate="false" class="product-grid-style "]  '); ?>
</div>