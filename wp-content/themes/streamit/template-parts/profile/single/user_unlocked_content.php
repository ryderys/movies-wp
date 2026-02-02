<?php

/**
 * The template for displaying unlocked (purchased) content
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$ppv_access = get_user_meta($user_id, 'streamit_ppv_access', true);


// Only keep entries where user still has access
$valid_ppv_access = [];
foreach ($ppv_access as $entry) {
    if (
        !empty($entry['id']) &&
        !empty($entry['type']) &&
        streamit_user_has_ppv_access($user_id, $entry['id'])
    ) {
        $valid_ppv_access[] = $entry;
    }
}

// Group IDs by type
$movie_ids = $tvshow_ids = $video_ids = [];
foreach ($valid_ppv_access as $entry) {
    if ($entry['type'] === 'movie') $movie_ids[] = (int)$entry['id'];
    elseif ($entry['type'] === 'tvshow') $tvshow_ids[] = (int)$entry['id'];
    elseif ($entry['type'] === 'video') $video_ids[] = (int)$entry['id'];
}

// Fetch data
$per_page = apply_filters('unlockedcontent_per_page_count', 10);
$movie_data = !empty($movie_ids) ? streamit_get_movies(['per_page' => $per_page, 'include' => $movie_ids]) : null;
$tvshow_data = !empty($tvshow_ids) ? streamit_get_tvshows(['per_page' => $per_page, 'include' => $tvshow_ids]) : null;
$video_data = !empty($video_ids) ? streamit_get_videos(['per_page' => $per_page, 'include' => $video_ids]) : null;

$tabs = [
    'movie'   => $movie_data,
    'video'   => $video_data,
    'tvshow'  => $tvshow_data,
];

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
            <div class="tab-pane fade <?php echo $firstTab ? 'active show' : ''; ?>" id="pills-<?php echo esc_attr($key); ?>" role="tabpanel" tabindex="0">
                <?php if (!empty($data->results)) : ?>
                    <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 data-listing">
                        <?php foreach ($data->results as $st_data) : ?>
                                <?php streamit_get_template('common/html-common-card.php', [
                                    'st_data' => $st_data,
                                    'hide_watchlist' => true
                                ]); ?>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($data->results) && !empty($data->maxnumpages) && $data->maxnumpages > 1) :
                        echo st_get_load_more_button(
                            $data->maxnumpages,
                            $key . '_unlockedcontent',
                            1,
                            'بارگذاری بیشتر',
                            'در حال بارگذاری...'
                        );
                    endif; ?>
                <?php else: ?>
                    <p class="text-center w-100"><?php esc_html_e('No Rental available.', 'streamit'); ?></p>
                <?php endif; ?>
            </div>
            <?php $firstTab = false; ?>
        <?php endforeach; ?>
    </div>
</div>