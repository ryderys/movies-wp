<?php

/**
 * Show options for ordering
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/orderby.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     9.7.0
 */

if (! defined('ABSPATH')) {
  exit;
}

global $streamit_options;
?>
<!-- Nav pills -->
<div class="d-flex justify-content-center align-items-center gap-3 flex-wrap">
  <?php echo apply_filters('st_woocommerce_product_layout_tab', $streamit_options); ?>
  <form class="woocommerce-ordering m-0" method="get">
    <select name="orderby" class="orderby" aria-label="<?php esc_attr_e('Shop order', 'streamit'); ?>">
      <?php foreach ($catalog_orderby_options as $id => $name) : ?>
        <option value="<?php echo esc_attr($id); ?>" <?php selected($orderby, $id); ?>><?php echo esc_html($name); ?></option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" name="paged" value="1" />
    <?php wc_query_string_form_fields(null, array('orderby', 'submit', 'paged', 'product-page')); ?>
  </form>
</div>
</div>