<?php
/**
 * The main template file.
 *
 * This template is used to display membership and order information.
 * It checks the membership status and order history, handling errors gracefully.
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wpdb, $current_user;

// Ensure current user is logged in
if (!is_user_logged_in()) {
    echo '<p class="error-message">' . esc_html__('You must be logged in to view your membership and orders.', 'streamit') . '</p>';
    return;
}

// Get current user membership levels
$myactivelevel = streamit_user_current_pmp_level($current_user->ID);

// Check if membership is active
$has_active_membership = false;
if (!empty($myactivelevel)) {
    $pmp_end_date = isset($myactivelevel->enddate) ? $myactivelevel->enddate : null;
    if ($pmp_end_date !== null) {
        $current_timestamp = current_time('timestamp');
        // Membership is active only if enddate is in the future
        $has_active_membership = ($pmp_end_date > $current_timestamp);
    } else {
        // No enddate means lifetime membership
        $has_active_membership = true;
    }
}
?>

<div class="pmpro">
    <?php
    // Check if the user has any active (non-expired) membership levels
    if (empty($myactivelevel) || !$has_active_membership) {
        $subscribe_url = function_exists('streamit_subscribe_page_url') ? streamit_subscribe_page_url() : '#';
    
        echo '<p class="error-message">'
            . esc_html__('You do not have an active membership level.', 'streamit')
            . ' <a href="' . esc_url($subscribe_url) . '" class="buy-membership-link">'
            . esc_html__('Buy Membership', 'streamit')
            . '</a></p>';
    } else {
        // Attempt to fetch subscriptions for the current level
        $subscriptions = PMPro_Subscription::get_subscriptions_for_user($current_user->ID, $myactivelevel->id);
        $subscription = !empty($subscriptions) ? $subscriptions[0] : null;

        // Display a message if no active subscription exists
        if ($subscription) { ?>
            <section class="st-pmp-section">
                <h4 class="pmpro_section_title mt-0"><?php esc_html_e('My Memberships', 'streamit'); ?></h4>
                <div class="pmpro_card">
                    <h5 class="pmpro_card_title">
                        <?php echo esc_html($myactivelevel->name); ?>
                    </h5>
                    <div class="pmpro_card_content">
                        <ul class="pmpro_list pmpro_list-plain pmpro_list-with-labels pmpro_cols-3">
                            <li class="pmpro_list_item">
                                <span class="pmpro_list_item_label">
                                    <?php esc_html_e('Subscription', 'streamit'); ?>
                                </span>
                                <span class="pmpro_list_item_value">
                                    <?php echo esc_html($subscription->get_cost_text()); ?>
                                </span>
                            </li>
                            <li class="pmpro_list_item">
                                <span class="pmpro_list_item_label">
                                    <?php esc_html_e('Next payment on', 'streamit'); ?>
                                </span>
                                <span class="pmpro_list_item_value">
                                    <?php echo esc_html($subscription->get_next_payment_date(get_option('date_format'))); ?>
                                </span>
                            </li>
                            <?php
                            // Check and display expiration date if available
                            $expiration_text = pmpro_get_membership_expiration_text($myactivelevel, $current_user, '');
                            if (!empty($expiration_text)) {
                            ?>
                                <li class="pmpro_list_item">
                                    <span class="pmpro_list_item_label"><?php esc_html_e('Expires', 'streamit'); ?></span>
                                    <span class="pmpro_list_item_value"><?php echo wp_kses_post($expiration_text); ?></span>
                                </li>
                            <?php } ?>
                        </ul> <!-- end pmpro_list -->
                    </div> <!-- end pmpro_card_content -->
                </div> <!-- end pmpro_card -->
            </section>
        <?php
        }
    }

    // Get PPV level ID to filter out PPV orders
    $ppv_level_id = function_exists('streamit_get_or_create_ppv_level') ? streamit_get_or_create_ppv_level() : 0;

    // Initialize variables for pagination
    $membership_orders = array();
    $total_pages = 0;
    $current_page = 1;
    $membership_orders_page = array();

    // Get all user's orders (no limit/offset)
    $all_orders = MemberOrder::get_orders(
        array(
            'status' => array('pending', 'refunded', 'success'),
            'user_id' => $current_user->ID,
        )
    );

    // Filter to only membership orders (exclude PPV orders)
    foreach ($all_orders as $order) {
        $order_id = $order->id;
        $order_obj = new MemberOrder;
        $order_obj->getMemberOrderByID($order_id);
        $order_obj->getMembershipLevel();

        if (!empty($order_obj->membership_level) && $order_obj->membership_level->id == $ppv_level_id) {
            continue;
        }

        $membership_orders[] = $order_obj;
    }

    // Pagination logic
    $limit = 6;
    $total_orders = count($membership_orders);
    $total_pages = ceil($total_orders / $limit);
    $current_page = isset($_GET['pmp_page']) ? max(1, intval($_GET['pmp_page'])) : 1;
    $offset = ($current_page - 1) * $limit;

    // Slice the array for the current page
    $membership_orders_page = array_slice($membership_orders, $offset, $limit);

    ?>
    <section class="st-pmp-section mt-5">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <h4 class="pmpro_section_title mb-0">
                <?php esc_html_e('Membership Purchase History', 'streamit'); ?>
            </h4>
            <a href="<?php echo esc_url( pmpro_url( 'account' ) ); ?>" class="btn btn-primary">
                <?php esc_html_e('Manage Membership', 'streamit'); ?>
            </a>
        </div>

        <div class="pmpro_card">
            <table class="pmpro_table pmpro_table_orders">
                <thead>
                    <tr>
                        <th class="st-pmp-table-order"><?php esc_html_e('Date', 'streamit'); ?></th>
                        <th class="st-pmp-table-order"><?php esc_html_e('Level', 'streamit'); ?></th>
                        <th class="st-pmp-table-order"><?php esc_html_e('Total', 'streamit'); ?></th>
                        <th class="st-pmp-table-order"><?php esc_html_e('Status', 'streamit'); ?></th>
                        <th class="st-pmp-table-order"><?php esc_html_e('Invoice', 'streamit'); ?></th>
                    </tr>
                </thead>
                <?php if (empty($membership_orders_page)) : ?>
                    <tr>
                        <td colspan="5" class="error-message text-center py-4">
                            <?php esc_html_e('No Data found.', 'streamit'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tbody>
                    <?php
                    if (!empty($membership_orders_page)) {
                        foreach ($membership_orders_page as $order) {
                            // Set the display status
                            $display_status = esc_html__('Paid', 'streamit');
                            $tag_style = 'success';
                            if ($order->status == 'pending') {
                                $display_status = esc_html__('Pending', 'streamit');
                                $tag_style = 'alert';
                            } elseif ($order->status == 'refunded') {
                                $display_status = esc_html__('Refunded', 'streamit');
                                $tag_style = 'error';
                            }

                            $invoice_exists = false;
                            if (function_exists('streamit_get_invoice_metadata')) {
                                $invoice_data = streamit_get_invoice_metadata($order->id);
                                $invoice_exists = !empty($invoice_data) && !empty($invoice_data['pdf_base64']);
                            }
                            ?>
                            <tr id="pmpro_table_order-<?php echo esc_attr($order->code); ?>">
                                <th class="pmpro_table_order-date" data-title="<?php esc_attr_e('Date', 'streamit'); ?>">
                                    <a href="<?php echo esc_url(pmpro_url("invoice", "?invoice=" . $order->code)) ?>">
                                        <?php echo esc_html(date_i18n(get_option("date_format"), $order->getTimestamp())) ?>
                                    </a>
                                </th>
                                <td class="pmpro_table_order-level" data-title="<?php esc_attr_e('Level', 'streamit'); ?>">
                                    <?php echo !empty($order->membership_level) ? esc_html($order->membership_level->name) : esc_html__("N/A", 'streamit'); ?>
                                </td>
                                <td class="pmpro_table_order-amount" data-title="<?php esc_attr_e('Amount', 'streamit'); ?>">
                                    <?php echo pmpro_escape_price($order->get_formatted_total()); ?>
                                </td>
                                <td class="pmpro_table_order-status" data-title="<?php esc_attr_e('Status', 'streamit'); ?>">
                                    <span class="pmpro_tag pmpro_tag-<?php echo esc_attr($tag_style); ?>">
                                        <?php echo esc_html($display_status); ?>
                                    </span>
                                </td>
                                <td class="pmpro_table_order-invoice" data-title="<?php esc_attr_e('Invoice', 'streamit'); ?>">
                                    <?php if ($invoice_exists) : ?>
                                        <button type="button" class="btn btn-primary streamit-download-invoice p-2"
                                                data-order-id="<?php echo esc_attr($order->id); ?>">
                                            <?php esc_html_e('Download', 'streamit'); ?>
                                        </button>
                                    <?php else : ?>
                                        <del class="rent-price"><?php esc_html_e('N/A', 'streamit'); ?></del>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div> <!-- end st-pmp-card-content -->
    </section>

    <?php if ($total_pages > 1): ?>
        <nav class="pagination-nav">
            <ul class="pagination column-gap-2 row-gap-1 flex-wrap justify-content-end">
                <?php if ($current_page > 1): ?>
                    <li><a href="<?php echo esc_url(add_query_arg('pmp_page', $current_page - 1)); ?>">&laquo; <?php esc_html_e('Previous', 'streamit'); ?></a></li>
                <?php endif; ?>

                <?php
                // Always show first page
                if ($current_page > 2) {
                    echo '<li><a href="' . esc_url(add_query_arg('pmp_page', 1)) . '">1</a></li>';
                    if ($current_page > 3) {
                        echo '<li class="disabled"><span>...</span></li>';
                    }
                }

                // Show current, previous, and next page
                for ($i = max(1, $current_page - 1); $i <= min($total_pages, $current_page + 1); $i++) {
                    if ($i == $current_page) {
                        echo '<li class="active"><span>' . esc_html($i) . '</span></li>';
                    } else {
                        echo '<li><a href="' . esc_url(add_query_arg('pmp_page', $i)) . '">' . esc_html($i) . '</a></li>';
                    }
                }

                // Always show last page
                if ($current_page < $total_pages - 1) {
                    if ($current_page < $total_pages - 2) {
                        echo '<li class="disabled"><span>...</span></li>';
                    }
                    echo '<li><a href="' . esc_url(add_query_arg('pmp_page', $total_pages)) . '">' . esc_html($total_pages) . '</a></li>';
                }
                ?>

                <?php if ($current_page < $total_pages): ?>
                    <li><a href="<?php echo esc_url(add_query_arg('pmp_page', $current_page + 1)); ?>"><?php esc_html_e('Next', 'streamit'); ?> &raquo;</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>