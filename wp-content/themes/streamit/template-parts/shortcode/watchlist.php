<?php

/**
 * The template for displaying watchlist
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$user_id = get_current_user_id();
$watchlist_data = function_exists('streamit_user_watchlist') ? streamit_user_watchlist($user_id) : [];


$load_more_text = streamit_get_button_text('streamit_genere_tag_category_display_loadmore_text', esc_html__('Load More', 'streamit'));
$loading_text = streamit_get_button_text('streamit_genere_tag_category_loadmore_text_2', esc_html__('Loading...', 'streamit'));


$movie_data = $video_data = $tvshow_data = $episode_data = [];
$movie_ids = $tvshow_ids = $video_ids = $episode_ids = [];

// Collect post IDs for bulk fetching by type
foreach ($watchlist_data as $section => $section_list) {
    foreach ($section_list as $post_id) {
        if ($section === 'movie') {
            $movie_ids[] = (int) $post_id;
        } elseif ($section === 'tvshow') {
            $tvshow_ids[] = (int) $post_id;
        } elseif ($section === 'video') {
            $video_ids[] = (int) $post_id;
        }
    }
}

// Bulk fetch data for each type
$watchlist_per_page_count = apply_filters('watchlist_per_page_count', 10);
if (!empty($movie_ids)) $movie_data = streamit_get_movies(['per_page' => $watchlist_per_page_count, 'include' => $movie_ids]);
if (!empty($tvshow_ids)) $tvshow_data = streamit_get_tvshows(['per_page' => $watchlist_per_page_count, 'include' => $tvshow_ids]);
if (!empty($video_ids)) $video_data = streamit_get_videos(['per_page' => $watchlist_per_page_count, 'include' => $video_ids]);


$tabs = [
    'movie'   => $movie_data,
    'video'   => $video_data,
    'tvshow'  => $tvshow_data
];
// Display all data without tabs
?>

<div class="col-md-12">
    <div class="border-bottom mb-5">
        <div id="item-nav">
            <div class="item-list-tabs no-ajax css_prefix-tab-lists play-lists" id="object-nav">
                <ul class="nav nav-underline data-search-tab" id="pills-tab" role="tablist">
                    <?php $firstTab = true; ?>
                    <?php foreach ($tabs as $key => $data) : ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $firstTab ? 'active' : ''; ?>"
                                id="pills-<?php echo esc_attr($key); ?>-tab"
                                data-bs-toggle="pill"
                                data-bs-target="#pills-<?php echo esc_attr($key); ?>"
                                type="button"
                                role="tab"
                                aria-controls="pills-<?php echo esc_attr($key); ?>"
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
    <div class="tab-content" id="pills-tabContent">
        <?php $firstTab = true; ?>
        <?php foreach ($tabs as $key => $data) : ?>
            <div class="css_prefix-card-wrapper tab-pane fade  <?php echo $firstTab ? 'active show' : ''; ?>" id="pills-<?php echo esc_attr($key); ?>" role="tabpanel" tabindex="0">
                <?php if (!empty($data->results)) : ?>
                    <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                        <?php foreach ($data->results as $st_data) : ?>
                            <?php streamit_get_template('common/html-common-card.php', ['st_data' => $st_data, 'is_watchlist' => true]); ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($data->results) && !empty($data->maxnumpages) && $data->maxnumpages > 1) :
                        echo st_get_load_more_button(
                            $data->maxnumpages,
                            $key . '_watchlist',
                            1,
                            $load_more_text,
                            $loading_text
                        );
                    endif; ?>
                <?php else: ?>
                    <p class="text-center w-100"><?php esc_html_e('No watchlist available.', 'streamit'); ?></p>
                <?php endif; ?>
            </div>
            <?php $firstTab = false; ?>
        <?php endforeach; ?>
    </div>
</div>