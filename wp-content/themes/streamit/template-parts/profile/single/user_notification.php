<?php

/**
 * The template for displaying user details notification
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$args = apply_filters('st_unread_notfication_arguments', [
    'is_seen'         => 0,
    'is_current_user' => get_current_user_id(),
    'per_page' => 15, // Retrieve all notification
]);

$unread_notifications = streamit_get_notifications($args);

$args_read = apply_filters('st_read_notfication_arguments', [
    'is_seen'         => 1,
    'is_current_user' => get_current_user_id(),
    'per_page' => 15, // Retrieve all notification
]);

$read_notifications = streamit_get_notifications($args_read);

?>

<div class="d-flex align-items-center gap-3 justify-content-between flex-sm-row flex-column-reverse border-bottom mb-5">
    <div id="item-nav">
        <div class="item-list-tabs no-ajax css_prefix-tab-lists" id="object-nav">
            <ul class="nav nav-underline data-search-tab" id="notification-tab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="unread-tab" data-bs-toggle="tab" data-bs-target="#unread" type="button" role="tab" aria-controls="unread" aria-selected="true">
                        <?php esc_html_e('Unread', 'streamit') ?>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="read-tab" data-bs-toggle="tab" data-bs-target="#read" type="button" role="tab" aria-controls="read" aria-selected="false">
                        <?php esc_html_e('Read', 'streamit') ?>
                    </button>
                </li>
            </ul>
        </div>
    </div>
    
    <!-- Notification Filter -->
    <div style="margin-right: 1rem;">
        <div class="dropdown">
            <button class="btn dropdown-toggle" type="button" id="notificationFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <?php esc_html_e('All Notifications', 'streamit') ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationFilterDropdown">
                <li><a class="dropdown-item filter-option active" href="#" data-filter="all"><?php esc_html_e('All Notifications', 'streamit') ?></a></li>
                <li><a class="dropdown-item filter-option" href="#" data-filter="purchases"><?php esc_html_e('Purchases', 'streamit') ?></a></li>
                <li><a class="dropdown-item filter-option" href="#" data-filter="releases"><?php esc_html_e('New Releases', 'streamit') ?></a></li>
            </ul>
        </div>
    </div>
</div>
<div class="tab-content" id="notification-tabContent">
    <!-- Unread Notifications Tab -->
    <div class="tab-pane fade show active" id="unread" role="tabpanel" tabindex="0">
        <ul class="notification-list data-listing">
            <?php

            if (!empty($unread_notifications->results)):
                foreach ($unread_notifications->results as $st_data):
                    echo streamit_get_template('profile/single/user_notification_loop.php', ['st_data' => $st_data]);
                endforeach;
            else: ?>
                <li>
                    <h6 class="m-0"><?php esc_html_e('No unread notifications.', 'streamit') ?></h6>
                </li>
            <?php endif; ?>
        </ul>

        <?php
        if (!empty($unread_notifications->results) && ($unread_notifications->maxnumpages > 1)) :
            $load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', 'بارگذاری بیشتر');
            $loading_text = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', 'در حال بارگذاری...');

            echo st_get_load_more_button($unread_notifications->maxnumpages, 'unread_notification', 1, esc_html($load_more_text), esc_html($loading_text));
        endif;
        ?>
    </div>

    <!-- Read Notifications Tab -->
    <div class="tab-pane fade" id="read" role="tabpanel" tabindex="0">
        <form method="post" action="process-notifications.php">
            <ul class="notification-list data-listing">
                <?php if (!empty($read_notifications->results)): ?>
                    <?php foreach ($read_notifications->results as $st_data):
                        echo streamit_get_template('profile/single/user_notification_loop.php', ['st_data' => $st_data]);
                    ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>
                        <h6 class="m-0"><?php esc_html_e('No read notifications.', 'streamit') ?></h6>
                    </li>
                <?php endif; ?>
            </ul>
            <?php
            if (!empty($read_notifications->results)  && ($read_notifications->maxnumpages > 1)):
                $load_more_text = esc_html(streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', 'بارگذاری بیشتر'));
                $loading_text = esc_html(streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', 'در حال بارگذاری...'));
                echo st_get_load_more_button($read_notifications->maxnumpages, 'read_notification', 1, esc_html($load_more_text), esc_html($loading_text));
            endif
            ?>
        </form>
    </div>
</div>
