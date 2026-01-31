<?php

/**
 * The template for displaying share model
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package streamit
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $streamit_options;
$enable_upcoming_badges = ($streamit_options['streamit_recommended_enable_upcoming_badges'] === 'yes');

if (function_exists('streamit_is_upcoming')) {
    $upcoming_data = streamit_is_upcoming($st_data, 'tvshow');
    if ($upcoming_data['is_future_release']) {
        // Check if current user is admin
        $is_admin = current_user_can('administrator');
        if (!$is_admin) {
            return; 
        }
    }
}

$seasons = $st_data->get_meta("_seasons");

if (empty($seasons)) return;

$slick_args = array(
    'slidesToShow' => 5,
    'slidesToScroll' => 1,
    'infinite' => true,
    'arrows' => true,
    'dots'  => false,
    'draggable' => true,
    'responsive' => [
        ['breakpoint' => 1368, 'settings' => ['slidesToShow' => 3]],
        ['breakpoint' => 1025, 'settings' => ['slidesToShow' => 3]],
        ['breakpoint' => 993, 'settings'  => ['slidesToShow' => 2]],
        ['breakpoint' => 577, 'settings'  => ['slidesToShow' => 1]]
    ],
);
?>
<div class="section-spacing-top">
    <div class="container-fluid">
        <div class="episode-section">
            <div class="single_page_slick">
                <div class="d-flex align-items-center justify-content-between mb-md-5 mb-3">
                    <h5 class="main-title text-capitalize mb-0"><?php echo esc_html__('Episodes', 'streamit'); ?></h5>
                </div>
                <div class="episode-season">
                    <div id="item-nav">
                        <div class="item-list-tabs no-ajax css_prefix-tab-lists" id="object-nav">
                            <div class="left" onclick="slide('left',event)" style="display: none;">
                                <?php echo st_get_icon('arrow-left'); ?>
                            </div>
                            <ul class="nav nav-pills custom-tab-slider episode-nav-btn gap-3" id="nav-tab">
                                <?php foreach ($seasons as $index => $val) {
                                    $season_name_id = trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($val['name'])), '-');
                                    
                                    // Check if this season is upcoming
                                    $season_upcoming_data = function_exists('streamit_is_season_upcoming') ? streamit_is_season_upcoming($val) : ['is_future_release' => false];
                                    $is_upcoming_season = $season_upcoming_data['is_future_release'];
                                ?>
                                    <li class="nav-item" role="presentation">
                                        <a class="nav-link css_prefix-change-season <?php echo $index === 0 ? 'active' : ''; ?>" data-display=0 data-episodes="<?php echo esc_attr(json_encode($seasons)) ?>" data-bs-toggle="pill" data-season_no="<?php echo esc_attr($index); ?>" href="#<?php echo esc_attr($season_name_id); ?>">
                                            <?php echo esc_html($val['name']); ?>
                                            <?php if ($is_upcoming_season && $enable_upcoming_badges) : ?>
                                                <span class="badge  upcoming-badge">
                                                    <span><?php echo esc_html__('Coming Soon', 'streamit'); ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                            <div class="right" onclick="slide('right',event)" style="display: none;">
                                <?php echo st_get_icon('arrow-right'); ?>

                            </div>
                        </div>
                    </div>
                    <div class="tab-content mt-5" id="nav-tabContent">
                        <?php
                        foreach ($seasons as $index1 => $season) {
                        ?>
                            <div class="tab-pane fade <?php echo $index1 === 0 ? esc_attr('active show') : ''; ?>" id="<?php echo trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($season['name'])), '-'); ?>" role="tabpanel">
                                <div class="single_page_slick">
                                    <div class="css_prefix-episodes-content">
                                        <?php 
                                        // Check if this season is upcoming
                                        $season_upcoming_data = function_exists('streamit_is_season_upcoming') ? streamit_is_season_upcoming($season) : ['is_future_release' => false];
                                        $is_upcoming_season = $season_upcoming_data['is_future_release'];
                                        
                                        // Check if user is admin for upcoming season access
                                        $is_admin = current_user_can('administrator');
                                        
                                        if ($is_upcoming_season && !$is_admin) : ?>
                                            <?php 
                                            // Include the upcoming season template
                                            streamit_get_template('tvshow/content/tvshow_single_upcoming_season.php', ['season' => $season, 'season_upcoming_data' => $season_upcoming_data, 'st_data' => $st_data, 'season_index' => $index1]);
                                            ?>
                                        <?php elseif (!empty($season['episodes'])) : ?>
                                            <div class=" css_prefix-slick-general st-skeleton" data-display=0 data-season_no="<?php echo esc_attr($index1); ?>" data-extra_settings="true" data-slider_settings="<?php echo st_get_slick_slider_settings($slick_args); ?>">
                                                <?php
                                                $episode_posts = streamit_get_episodes(['orderby' => 'post__in', 'include' => $season['episodes'], 'paged' => 1, 'per_page' => -1])->results;
                                                foreach ($episode_posts as $index => $episode) :
                                                    if ($index1 > 0) {
                                                        break;
                                                    }
                                                    streamit_get_template('episode/content/episode_single_slider.php', ['episode' => $episode]);
                                                endforeach; ?>
                                            </div>
                                        <?php else :
                                            echo '<p class="no_data_found"> ' . esc_html__('No Episode Available', 'streamit') . '</p>';
                                        endif; ?>
                                    </div>
                                </div>
                            </div><?php
                                }
                                    ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>