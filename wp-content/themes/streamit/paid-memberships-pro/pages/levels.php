<?php
/**
 * Template: Levels
 * Version: 3.1
 *
 * @package streamit
 * 
 * @author Paid Memberships Pro
 * 
 */

if (! defined('ABSPATH')) {
    exit;
}

global $current_user, $pmpro_msg, $pmpro_msgt;

$ppv_level_id = function_exists('streamit_get_or_create_ppv_level') ? streamit_get_or_create_ppv_level() : 0;

// Get levels and groups
$pmpro_levels = apply_filters('pmpro_levels_array', pmpro_sort_levels_by_order(pmpro_getAllLevels(false, true)));
$level_groups = pmpro_get_level_groups_in_order();

// Get the 'require_plan' query parameter
$highlighted_plans = isset($_GET['require_plan']) ? array_filter(explode(',', $_GET['require_plan']), 'is_numeric') : [];

// Find the recommended plans
$recommended_plans = [];
if (!empty($highlighted_plans)) {
    // If specific plans are highlighted, recommend all of them that allow signups
    foreach ($highlighted_plans as $plan_id) {
        if (isset($pmpro_levels[$plan_id]) && $pmpro_levels[$plan_id]->allow_signups) {
            $recommended_plans[] = $plan_id;
        }
    }
} else {
    // Fallback: Recommend the lowest priced non-free plan or second-lowest if lowest is free, for plans allowing signups
    $prices = [];
    
    // Collect prices for all available plans that allow signups
    foreach ($pmpro_levels as $level) {
        if ($level->allow_signups) {
            // Convert to monthly cost for consistency
            $price = floatval($level->billing_amount);
            if ($level->cycle_period === 'Year') {
                $price = $price / 12; // Annual to monthly
            } elseif ($level->cycle_period === 'Quarter') {
                $price = $price / 3; // Every 3 months to monthly
            }
            $prices[$level->id] = $price;
        }
    }
    
    // Sort prices in ascending order
    asort($prices);
    
    // Get unique price values
    $unique_prices = array_unique(array_values($prices));
    sort($unique_prices);
    
    // Select recommended price
    $recommended_price = null;
    if (!empty($unique_prices)) {
        // If lowest price is free and there's another price, use the second-lowest
        if ($unique_prices[0] == 0 && count($unique_prices) > 1) {
            $recommended_price = $unique_prices[1];
        } else {
            // Otherwise, use the lowest non-free price
            foreach ($unique_prices as $price) {
                if ($price > 0) {
                    $recommended_price = $price;
                    break;
                }
            }
        }
    }
    
    // Add all plans with the recommended price
    if ($recommended_price !== null) {
        foreach ($prices as $plan_id => $price) {
            if ($price == $recommended_price) {
                $recommended_plans[] = $plan_id;
            }
        }
    }
}

?>

<?php if (! empty($pmpro_msg)) : ?>
    <div class="<?php echo esc_attr(pmpro_get_element_class('pmpro_message ' . $pmpro_msgt, $pmpro_msgt)); ?>">
        <?php echo wp_kses_post($pmpro_msg); ?>
    </div>
<?php endif; ?>

<div class="streamit-membership-groups">
    <div class="container">
        <?php foreach ($level_groups as $group) :
    
            $group_level_ids      = pmpro_get_level_ids_for_group($group->id);
            $levels_in_this_group = array_filter($pmpro_levels, fn($level) => in_array($level->id, $group_level_ids, true));
    
            if (empty($levels_in_this_group)) {
                continue;
            }
        ?>
            <div class="streamit-membership-group mb-5">
                <h4 class="streamit-group-title"><?php echo esc_html($group->name); ?></h4>
    
                <?php if (! empty($group->description)) : ?>
                    <p class="streamit-group-description"><?php echo esc_html($group->description); ?></p>
                <?php endif; ?>
    
                <div class="row streamit-plan-cards">
                    <?php foreach ($levels_in_this_group as $level) :
						 if ($ppv_level_id && $level->id == $ppv_level_id) {
                            continue;
                        }
                        $user_level  = pmpro_getSpecificMembershipLevelForUser($current_user->ID, $level->id);
                        $has_level   = ! empty($user_level);
                        
                        // Get pricing values
                        $initial_payment = (float) $level->initial_payment;
                        $billing_amount = (float) $level->billing_amount;
                        $cycle_number = (int) $level->cycle_number;
                        $description = $level->description ?: esc_html__('Get access to exclusive content.', 'streamit');
                        
                        // Determine what price to display based on PMPro logic
                        $display_price = pmpro_formatPrice(0);
                        $cycle_display = '';

                        if ($billing_amount > 0) {
                            // Recurring plan - show billing amount
                            $display_price = pmpro_formatPrice($billing_amount);

                            if ($cycle_number > 0) {
                                $period_label = pmpro_translate_billing_period($level->cycle_period, $cycle_number);
                                $cycle_display = ($cycle_number > 1)
                                    ? sprintf('%d %s', $cycle_number, $period_label)
                                    : $period_label;
                            }
                        } elseif ($initial_payment > 0) {
                            // One-time payment plan
                            $display_price = pmpro_formatPrice($initial_payment);
                        }
    
                        // Button logic
                        if (! $has_level) {
                            $button_text = esc_html__('Subscribe Now', 'streamit');
                            $button_url  = pmpro_url('checkout', '?level=' . $level->id);
                        } elseif (pmpro_isLevelExpiringSoon($user_level) && $level->allow_signups) {
                            $button_text = esc_html__('Renew', 'streamit');
                            $button_url  = pmpro_url('checkout', '?level=' . $level->id);
                        } else {
                            $button_text = esc_html__('Active Subscription Plan', 'streamit');
                            $button_url  = pmpro_url('account');
                        }
    
                        // Optional: Check if discount applies (your logic can hook into this filter)
                        $has_discount = apply_filters('streamit_pmpro_has_discount', false, $level->id);
    
                        // Optional expiry date display
                        $expiry_text = '';
                        if ($has_level && ! empty($user_level->enddate)) {
                            // enddate is already a Unix timestamp, use it directly
                            $enddate_timestamp = is_numeric($user_level->enddate) ? (int) $user_level->enddate : strtotime($user_level->enddate);
                            $current_timestamp = current_time('timestamp');
                            
                            // Only show expiration if membership is still active 
                            if ($enddate_timestamp > $current_timestamp) {
                                $expiry_text = sprintf(
                                    /* translators: %s = formatted date */
                                    esc_html__('Expires on %s', 'streamit'),
                                    date_i18n(get_option('date_format'), $enddate_timestamp)
                                );
                            }
                        }
    
                        // Check if the current plan should be recommended
                        $is_recommended = !empty($recommended_plans) ? in_array($level->id, $recommended_plans) : false;
                    ?>
                        <div class="col-md-4 mb-4">
                            <div class="price-card p-5 text-center">
                                <?php if ($is_recommended) { ?>
                                    <span class="badge"><?php echo esc_html__('Recommended', 'streamit'); ?></span>
                                <?php } ?>
                                <h5 class="price-title"><?php echo esc_html($level->name); ?></h5>
                                <p class="price-desc"><?php echo esc_html($description); ?></p>
    
                                <!-- Price display -->
                                <div class="price-value">
                                    <span class="price-no-discount">
                                        <?php echo wp_kses_post($display_price); ?>
                                    </span>
    
                                    <!-- Display cycle period (monthly, yearly, etc.) -->
                                    <?php if (!empty($cycle_display)) : ?>
                                        <span class="fs-6 fw-normal">/<?php echo esc_html($cycle_display); ?></span>
                                    <?php endif; ?>
                                </div>
    
                                <?php if ($expiry_text) : ?>
                                    <div class="streamit-plan-expiry text-warning small fst-italic">
                                        <?php echo esc_html($expiry_text); ?>
                                    </div>
                                <?php endif; ?>
    
                                <div class="price-button mt-4">
                                    <a href="<?php echo esc_url($button_url); ?>" class="btn btn-primary">
                                        <?php echo esc_html($button_text); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div> <!-- .streamit-plan-cards -->
            </div> <!-- .streamit-membership-group -->
        <?php endforeach; ?>
    </div>
</div>