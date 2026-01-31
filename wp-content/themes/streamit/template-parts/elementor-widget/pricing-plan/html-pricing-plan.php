<?php

/**
 * Streamit Pricing Plan Template
 * 
 * @package Streamit
 */

if (!defined('ABSPATH')) {
    exit;
}

global $pmpro_currency_symbol, $pmpro_currencies, $pmpro_currency;

// Plan ID
$pmpro_plan_id = $settings['pmp_pricing_plan_id'] ?? null;
if (!$pmpro_plan_id) return;

// Get level data
$level = function_exists('pmpro_getLevel') ? pmpro_getLevel($pmpro_plan_id) : null;
if (empty($level)) return;

$is_user_logged_in = is_user_logged_in();
$is_plan_free      = pmpro_isLevelFree($level);
$pmpro_product_id  = get_pmpro_membership_level_meta($pmpro_plan_id, "_plan_product_id", true);
$gateway           = (is_plugin_active("pmpro-woocommerce/pmpro-woocommerce.php") && !empty($pmpro_product_id)) ? "woocommerce_payment_gateway" : '';

// Period text
$period_number = (int) $level->cycle_number;
$period        = $level->cycle_period;
$period_text   = ($period_number > 0) ? "{$period_number} {$period}" : esc_html__('Lifetime', 'streamit');

// Currency symbol
if (!empty($pmpro_currencies[$pmpro_currency]['symbol'])) {
    $pmpro_currency_symbol = $pmpro_currencies[$pmpro_currency]['symbol'];
}
$pmpro_currency_symbol = apply_filters("st_pmpro_currency_symbol", $pmpro_currency_symbol);

// Payment & expiration
$initial_payment   = $level->initial_payment;
$expiration_number = $level->expiration_number;
$expiration_period = $level->expiration_period;

// Current user membership
$current_user = wp_get_current_user();
$user_level   = pmpro_getSpecificMembershipLevelForUser($current_user->ID, $level->id);
$has_level    = !empty($user_level);

// Links & texts
if ($has_level) {
    $pmpro_checkout_page_link = esc_url(pmpro_url("account"));
    $account_page_text        = esc_html($settings['account_exists'] ?? '');
    $active_plan_text         = esc_html__("Active", 'streamit');
} else {
    $pmpro_checkout_page_link = esc_url(pmpro_url("checkout", "?level={$level->id}"));
    $account_page_text        = esc_html__("Create an account", 'streamit');
    $active_plan_text         = '';
}
?>

<div class="pricing-plans-wrapper">

    <?php if (!empty($settings['show_discount_banner']) && $settings['show_discount_banner'] === 'yes') : ?>
        <div class="pricing-plan-discount">
            <span><?php echo esc_html($settings['discount_text'] ?? ''); ?></span>
        </div>
    <?php endif; ?>

    <div class="pricing-plan-header">
        <?php if (!empty($settings['image']['url'])) : ?>
            <div class="pricing-plan-image">
                <?php
                echo wp_get_attachment_image(
                    attachment_url_to_postid($settings['image']['url']),
                    "full",
                    false,
                    [
                        "class"    => "plan-img",
                        "alt"      => esc_attr($level->name),
                        "decoding" => "async"
                    ]
                );
                ?>
            </div>
        <?php endif; ?>

        <div class="plan-wrapper">
            <h4 class="plan-name"><?php echo esc_html($level->name); ?></h4>
            <?php if ($active_plan_text) : ?>
                <span class="active-plan"><?php echo esc_html($active_plan_text); ?></span>
            <?php endif; ?>
        </div>

        <div class="pricing-plan-details">
            <?php if (!empty($settings['show_sale_price']) && $settings['show_sale_price'] === 'yes') : ?>
                <span class="sale-price">
                    <span><?php echo esc_html($pmpro_currency_symbol . ($settings['sale_price'] ?? '')); ?></span>
                </span>
            <?php endif; ?>

            <span class="plan-main-price"><?php echo esc_html($pmpro_currency_symbol . $initial_payment); ?></span>
            <span class="plan-period-time">/ <?php echo esc_html($period_text); ?></span>
        </div>
    </div>

    <div class="details-pricing-inner">

        <?php if (!empty($settings['show_discount_code']) && $settings['show_discount_code'] === 'yes') : ?>
            <div class="discount-code">
                <label><?php esc_html_e('Discount Code:', 'streamit'); ?></label>
                <input type="text" value="<?php echo esc_html($settings['discount_code'] ?? ''); ?>" readonly>
            </div>
        <?php endif; ?>

        <?php if (!empty($settings['show_description']) && $settings['show_description'] === 'yes') : ?>
            <div class="description">
                <p class="m-0"><?php echo esc_html($level->description); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($settings['show_expiration']) && $settings['show_expiration'] === 'yes' && !empty($expiration_number)) : ?>
            <div class="expiration">
                <p><?php esc_html_e('Expires in:', 'streamit'); ?> <?php echo esc_html("{$expiration_number} {$expiration_period}"); ?></p>
            </div>
        <?php endif; ?>

        <?php 
        // Check if dynamic features are enabled
        $enable_dynamic_features = !empty($settings['enable_dynamic_features']) && $settings['enable_dynamic_features'] === 'yes';
        $enable_custom_icons = !empty($settings['enable_custom_icons']) && $settings['enable_custom_icons'] === 'yes';
        
        if ($enable_dynamic_features) {
            // Load dynamic features from PMP Membership Plan Features
            $dynamic_features = get_pmpro_membership_level_meta($pmpro_plan_id, 'streamit_plan_features', true);
            
            if (!empty($dynamic_features) && is_array($dynamic_features)) : ?>
                <div class="pricing-plan-description">
                    <ul>
                        <?php foreach ($dynamic_features as $feature) : ?>
                            <li class="<?php echo esc_attr($feature['type'] === 'not_include' ? 'not-available' : 'available'); ?>">
                                <span class="d-flex align-items-baseline gap-3">
                                    <span class="icon">
                                        <?php 
                                        // Use custom icons if enabled, otherwise use default icons
                                        if ($enable_custom_icons) {
                                            $icon_config = $feature['type'] === 'include' 
                                                ? $settings['custom_include_icon'] 
                                                : $settings['custom_not_include_icon'];
                                        } else {
                                            $icon_config = [
                                                'value' => $feature['type'] === 'include' ? 'fas fa-check' : 'fas fa-times',
                                                'library' => 'fa-solid'
                                            ];
                                        }
                                        \Elementor\Icons_Manager::render_icon($icon_config, ['aria-hidden' => 'true']);
                                        ?>
                                    </span>
                                    <span class="plan-dec"><?php echo esc_html($feature['text'] ?? ''); ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif;
        } else {
            // Use static features from Elementor repeater
            if (!empty($settings['tabs']) && is_array($settings['tabs'])) : ?>
                <div class="pricing-plan-description">
                    <ul>
                        <?php foreach ($settings['tabs'] as $repeater_data) : ?>
                            <li class="<?php echo esc_attr($repeater_data['has_active'] === 'yes' ? 'not-available' : 'available'); ?>">
                                <span class="d-flex align-items-baseline gap-3">
                                    <span class="icon">
                                        <?php \Elementor\Icons_Manager::render_icon($repeater_data['tab_icon'], ['aria-hidden' => 'true']); ?>
                                    </span>
                                    <span class="plan-dec"><?php echo esc_html($repeater_data['plan_description'] ?? ''); ?></span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif;
        }
        ?>

        <div class="pricing-plan-footer">
            <a href="<?php echo esc_url($pmpro_checkout_page_link); ?>" class="btn btn-primary w-100">
                <?php echo esc_html($has_level ? $account_page_text : __('Checkout', 'streamit')); ?>
            </a>
        </div>

    </div>
</div>