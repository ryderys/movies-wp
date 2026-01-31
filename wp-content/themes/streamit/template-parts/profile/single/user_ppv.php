<?php
/**
 * The template for displaying only PPV orders for the current user.
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $current_user;

// Get PPV level ID
$ppv_level_id = function_exists('streamit_get_or_create_ppv_level') ? streamit_get_or_create_ppv_level() : 0;

// Initialize variables
$ppv_orders = array();
$total_pages = 0;
$current_page = 1;
$ppv_orders_page = array();

if (is_user_logged_in()) {
    // Get all orders for the user (no limit/offset)
    $all_orders = MemberOrder::get_orders([
        'status' => array('pending', 'refunded', 'success'),
        'user_id' => $current_user->ID,
    ]);

    // Filter to only PPV orders
    foreach ($all_orders as $order) {
        $order_id = $order->id;
        $order_obj = new MemberOrder;
        $order_obj->getMemberOrderByID($order_id);
        $order_obj->getMembershipLevel();
        if (!empty($order_obj->membership_level) && $order_obj->membership_level->id == $ppv_level_id) {
            $ppv_orders[] = $order_obj;
        }
    }

    // Pagination logic
    $limit = 6;
    $total_orders = count($ppv_orders);
    $total_pages = ceil($total_orders / $limit);
    $current_page = isset($_GET['ppv_page']) ? max(1, intval($_GET['ppv_page'])) : 1;
    $offset = ($current_page - 1) * $limit;

    // Slice the array for the current page
    $ppv_orders_page = array_slice($ppv_orders, $offset, $limit);
}

?>
<section class="st-pmp-section mt-5">
    <h4 class="pmpro_section_title"><?php esc_html_e('Pay-Per-View Purchase History', 'streamit') ?></h4>
    <div class="pmpro_card">
        <table class="pmpro_table pmpro_table_orders">
            <thead>
                <tr>
                    <th class="st-pmp-table-order"><?php esc_html_e('Date', 'streamit'); ?></th>
                    <th class="st-pmp-table-order"><?php esc_html_e('Stream Name', 'streamit'); ?></th>
                    <th class="st-pmp-table-order"><?php esc_html_e('Stream Type', 'streamit'); ?></th>
                    <th class="st-pmp-table-order"><?php esc_html_e('Total', 'streamit'); ?></th>
                    <th class="st-pmp-table-order"><?php esc_html_e('Expire At', 'streamit'); ?></th>
                    <th class="st-pmp-table-order"><?php esc_html_e('Status', 'streamit'); ?></th>
                    <th class="st-pmp-table-order"><?php esc_html_e('Invoice', 'streamit'); ?></th>
                </tr>
            </thead>
            <?php if (!is_user_logged_in()) : ?>
                <tr>
                    <td colspan="7" class="error-message text-center py-4">
                        <?php esc_html_e('You must be logged in to view your Pay Per View orders.', 'streamit'); ?>
                    </td>
                </tr>
            <?php elseif (empty($ppv_orders_page)) : ?>
                <tr>
                    <td colspan="7" class="error-message text-center py-4">
                        <?php esc_html_e('No Data found.', 'streamit'); ?>
                    </td>
                </tr>
            <?php endif; ?>
            <tbody>
                <?php if (is_user_logged_in() && !empty($ppv_orders_page)) : ?>
                    <?php
                    $count = 0;
                    foreach ($ppv_orders_page as $order) {
                        if ($count++ > 9) break; // Only show first 10 PPV orders
                        $display_status = esc_html__('Paid', 'streamit');
                        $tag_style = 'success';
                        if ($order->status == 'pending') {
                            $display_status = esc_html__('Pending', 'streamit');
                            $tag_style = 'alert';
                        } elseif ($order->status == 'refunded') {
                            $display_status = esc_html__('Refunded', 'streamit');
                            $tag_style = 'error';
                        }
                        // Try to get stream info from order meta if available
                        $stream_id = '';
                        $stream_type = '';
                        if (function_exists('get_pmpro_membership_order_meta')) {
                            $ppv_info = get_pmpro_membership_order_meta($order->id, 'streamit_ppv_stream_info', true);
                            if (!empty($ppv_info)) {
                                $ppv_info = maybe_unserialize($ppv_info);
                                $stream_id = isset($ppv_info['id']) ? $ppv_info['id'] : '';
                                $stream_type = isset($ppv_info['type']) ? $ppv_info['type'] : '';
                            }
                        }
                        $stream_title = '';
                        if (!empty($stream_id) && !empty($stream_type)) {
                            $stream_title = function_exists('streamit_get_stream_title') ? streamit_get_stream_title($stream_id, $stream_type) : $stream_id;
                        }
                        if (empty($stream_title)) {
                            $stream_title = esc_html__('N/A', 'streamit');
                        }
                        // Get PPV duration (in days) from stream meta
                        $duration_days = 0;
                        if (!empty($stream_id) && !empty($stream_type) && function_exists('streamit_get_stream_meta')) {
                            $duration_days = intval(streamit_get_stream_meta($stream_id, '_ppv_duration_days', true, $stream_type));
                        }
                        // Calculate expiry date
                        $expire_label = '';
                        if ($duration_days === 0) {
                            $expire_label = esc_html__('Lifetime', 'streamit');
                        } else {
                            $order_time = $order->getTimestamp();
                            $expire_time = strtotime("+{$duration_days} days", $order_time);
                            $now = current_time('timestamp');
                            if ($expire_time < $now) {
                                $expire_label = esc_html__('Expired', 'streamit');
                            } else {
                                $expire_label = date_i18n(get_option('date_format'), $expire_time);
                            }
                        }
                        // Check if invoice exists for this order
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
                        <td class="pmpro_table_order-level" data-title="<?php esc_attr_e('Stream Name', 'streamit'); ?>">
                            <?php echo esc_html($stream_title); ?>
                        </td>
                        <td class="pmpro_table_order-type" data-title="<?php esc_attr_e('Stream Type', 'streamit'); ?>">
                            <?php echo esc_html($stream_type); ?>
                        </td>
                        <td class="pmpro_table_order-amount" data-title="<?php esc_attr_e('Amount', 'streamit'); ?>">
                            <?php echo pmpro_escape_price($order->get_formatted_total()); ?>
                        </td>
                        <td class="pmpro_table_order-expire" data-title="<?php esc_attr_e('Expire At', 'streamit'); ?>">
                            <?php echo esc_html($expire_label); ?>
                        </td>
                        <td class="pmpro_table_order-status" data-title="<?php esc_attr_e('Status', 'streamit'); ?>">
                            <span class="pmpro_tag pmpro_tag-<?php echo esc_attr($tag_style); ?>">
                                <?php echo esc_html($display_status); ?>
                            </span>
                        </td>
                        <td class="pmpro_table_order-invoice" data-title="<?php esc_attr_e('Invoice', 'streamit'); ?>">
                            <?php if ($invoice_exists) : ?>
                                <button type="button" class="btn btn-primary streamit-download-invoice p-2" 
                                        data-order-id="<?php echo esc_attr($order->id); ?>" 
                                        >
                                    <?php esc_html_e('Download', 'streamit'); ?>
                                </button>
                            <?php else : ?>
                                <del class="rent-price"><?php esc_html_e('N/A', 'streamit'); ?></del>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php } ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($total_pages > 1): ?>
    <nav class="pagination-nav">
        <ul class="pagination column-gap-2 row-gap-1 flex-wrap justify-content-end">
            <?php if ($current_page > 1): ?>
                <li><a href="?ppv_page=<?php echo $current_page - 1; ?>">&laquo; <?php esc_html_e('Previous', 'streamit'); ?></a></li>
            <?php endif; ?>

            <?php
            // Always show first page
            if ($current_page > 2) {
                echo '<li><a href="?ppv_page=1">1</a></li>';
                if ($current_page > 3) {
                    echo '<li class="disabled"><span>...</span></li>';
                }
            }

            // Show current, previous, and next page
            for ($i = max(1, $current_page - 1); $i <= min($total_pages, $current_page + 1); $i++) {
                if ($i == $current_page) {
                    echo '<li class="active"><span>' . $i . '</span></li>';
                } else {
                    echo '<li><a href="?ppv_page=' . $i . '">' . $i . '</a></li>';
                }
            }

            // Always show last page
            if ($current_page < $total_pages - 1) {
                if ($current_page < $total_pages - 2) {
                    echo '<li class="disabled"><span>...</span></li>';
                }
                echo '<li><a href="?ppv_page=' . $total_pages . '">' . $total_pages . '</a></li>';
            }
            ?>

            <?php if ($current_page < $total_pages): ?>
                <li><a href="?ppv_page=<?php echo $current_page + 1; ?>"><?php esc_html_e('Next', 'streamit'); ?> &raquo;</a></li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>