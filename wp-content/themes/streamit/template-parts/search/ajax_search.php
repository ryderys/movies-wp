<?php

/**
 * The template for displaying AJAX search
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

$search_text = isset($args['s']) ? sanitize_text_field($args['s']) : '';

if (empty($search_text)): ?>
    <div class="col-md-12">
        <div class="d-flex align-items-center justify-content-center gap-3 my-5">
            <div class="image">
                <img src="<?php echo esc_url(streamit_search_not_found_image()); ?>" class="img-fluid" alt="<?php esc_attr_e('search-not-found', 'streamit'); ?>">
            </div>
            <div class="content">
                <h5 class="mt-0 mb-2"><?php esc_html_e('Sorry, Could not Find Your Search!', 'streamit'); ?></h5>
                <span><?php esc_html_e('Try something new', 'streamit'); ?></span>
            </div>
        </div>
    </div>
<?php return;
endif;

$args = [
    's' => $search_text,
    'posts_per_page' => -1, // Correct parameter name is 'posts_per_page'
];

$movie_data = function_exists('streamit_get_movies') ? streamit_get_movies($args)->results : '';
$video_data =  function_exists('streamit_get_videos') ? streamit_get_videos($args)->results : '';
$person_data =  function_exists('streamit_get_persons') ? streamit_get_persons($args)->results : '';
$tvshow_data = function_exists('streamit_get_tvshows') ? streamit_get_tvshows($args)->results : '';
$episode_data = function_exists('streamit_get_episodes') ? streamit_get_episodes($args)->results : '';

$tabs = [
    'all' => array_merge($movie_data, $video_data, $person_data, $tvshow_data, $episode_data),
    'movie' => $movie_data,
    'tvshow' => $tvshow_data,
    'video' => $video_data,
    'person' => $person_data,
    'episode' => $episode_data, // Fixed typo 'episdoe' to 'episode'
];

// Check if all tabs are empty
if (empty($tabs['all'])): ?>
    <div class="col-md-12">
        <div class="d-flex align-items-center justify-content-center gap-3 my-5">
            <div class="image">
                <img src="<?php echo esc_url(streamit_search_not_found_image()); ?>" class="img-fluid" alt="<?php esc_attr_e('search-not-found', 'streamit'); ?>">
            </div>
            <div class="content">
                <h5 class="mt-0 mb-2"><?php esc_html_e('Sorry, Could not Find Your Search!', 'streamit'); ?></h5>
                <span><?php esc_html_e('Try something new', 'streamit'); ?></span>
            </div>
        </div>
    </div>
<?php return;
endif;
?>

<div class="col-md-12">
    <div id="item-nav">
        <div class="item-list-tabs no-ajax css_prefix-tab-lists" id="object-nav">
            <div class="left" onclick="slide('left', event)" style="display: none;">
                <?php echo st_get_icon('arrow-left'); ?>
            </div>
            <ul class="custom-tab-slider nav nav-underline data-search-tab my-5" id="pills-tab" role="tablist">
                <?php $firstTab = true; ?>
                <?php foreach ($tabs as $key => $data): ?>
                    <?php if (!empty($data)): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $firstTab ? 'active' : ''; ?>"
                                id="pills-<?php echo esc_attr($key); ?>-tab"
                                data-bs-toggle="pill"
                                data-bs-target="#pills-<?php echo esc_attr($key); ?>"
                                type="button"
                                role="tab"
                                aria-controls="pills-<?php echo esc_attr($key); ?>"
                                aria-selected="<?php echo $firstTab ? 'true' : 'false'; ?>">
                                <?php echo esc_html(ucfirst($key)); ?>
                            </button>
                        </li>
                        <?php $firstTab = false; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <div class="right" onclick="slide('right', event)" style="display: none;">
                <?php echo st_get_icon('arrow-right'); ?>
            </div>
        </div>
    </div>
    <div class="tab-content" id="pills-tabContent">
        <?php $firstTab = true; ?>
        <?php foreach ($tabs as $key => $data): ?>
            <?php if (!empty($data)): ?>
                <div class="tab-pane fade <?php echo esc_html($firstTab) ? 'show active' : ''; ?>"
                    id="pills-<?php echo esc_attr($key); ?>"
                    role="tabpanel"
                    aria-labelledby="pills-<?php echo esc_attr($key); ?>-tab"
                    tabindex="0">
                    <div class="row gy-4 row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6">
                        <?php foreach ($data as $st_data): ?>
                            <?php echo streamit_get_template('common/html-common-card.php', ['st_data' => $st_data]); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $firstTab = false; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>