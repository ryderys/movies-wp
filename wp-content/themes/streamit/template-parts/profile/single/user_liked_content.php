<?php

/**
 * The template for displaying liked content in the user profile.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

defined('ABSPATH') || exit;

$user_id = $user_details['id'] ?? get_current_user_id();

if (empty($user_id)) {
    echo "<p class='no_data_found'>" . esc_html__('User ID not found.', 'streamit') . "</p>";
    return;
}

$liked_content_per_page_count = apply_filters('liked_content_per_page_count', 10);

$all_liked_data = function_exists('streamit_get_user_liked_posts') ?
    streamit_get_user_liked_posts($user_id, [], 1, -1, $liked_content_per_page_count) : [];

$tabs_data = [];
$allowed_frontend_types = ['movie', 'video', 'tvshow', 'episode'];

foreach ($allowed_frontend_types as $type) {
    $tabs_data[$type] = isset($all_liked_data[$type]) ? (object)$all_liked_data[$type] : (object)['results' => [], 'total' => 0, 'maxnumpages' => 0];
}

// Load more button texts
$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', esc_html__('Load More', 'streamit'));
$loading_text   = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__('Loading...', 'streamit'));

?>

<div class="col-md-12">
    <div class="border-bottom mb-5">
        <div id="item-nav">
            <div class="item-list-tabs no-ajax css_prefix-tab-lists play-lists" id="object-nav">
                <ul class="nav nav-underline data-search-tab" id="pills-tab-liked-content" role="tablist">
                    <?php $firstTab = true; ?>
                    <?php foreach ($tabs_data as $key => $data) : ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $firstTab ? 'active' : ''; ?>"
                                id="pills-<?php echo esc_attr($key); ?>-liked-tab"
                                data-bs-toggle="pill"
                                data-bs-target="#pills-<?php echo esc_attr($key); ?>-liked"
                                type="button"
                                role="tab"
                                aria-controls="pills-<?php echo esc_attr($key); ?>-liked"
                                aria-selected="<?php echo $firstTab ? 'true' : 'false'; ?>">
                                <?php echo ucfirst(esc_html($key)); ?>
                            </button>
                        </li>
                        <?php $firstTab = false; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="tab-content" id="pills-tabContent-liked-content">
        <?php $firstTab = true; ?>
        <?php foreach ($tabs_data as $key => $data) : ?>
            <div class="css_prefix-card-wrapper tab-pane fade <?php echo $firstTab ? 'active show' : ''; ?>"
                id="pills-<?php echo esc_attr($key); ?>-liked"
                role="tabpanel" tabindex="0">

                <?php if (!empty($data->results)) : ?>
                    <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                        <?php foreach ($data->results as $st_data) : ?>
                            <?php
                            streamit_get_template('common/html-common-card.php', ['st_data' => $st_data, 'is_liked_content' => true]);
                            ?>
                        <?php endforeach; ?>
                    </div>

                    <?php
                    // Check if more pages exist for this specific tab type, using its own maxnumpages
                    if ($data->maxnumpages > 1) :
                        echo st_get_load_more_button(
                            $data->maxnumpages,
                            esc_attr($key) . '_liked',
                            1,
                            $load_more_text,
                            $loading_text,
                            $liked_content_per_page_count
                        );
                    endif;
                    ?>

                <?php else: ?>
                    <p class="text-center w-100"><?php printf(esc_html__('No liked content available.', 'streamit')); ?></p>
                <?php endif; ?>

            </div>
            <?php $firstTab = false; ?>
        <?php endforeach; ?>
    </div>
</div>