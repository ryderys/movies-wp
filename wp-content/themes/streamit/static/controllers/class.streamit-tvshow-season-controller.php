<?php

defined('ABSPATH') || exit;


final class streamit_Tvshow_Season_Controller
{
    /**
     * Get Episods from season via post request.
     *
     * @param WP_REST_Request $request The REST API request object.
     */
    public function get_episodes(WP_REST_Request $request)
    {
        try {
            // Get request parameters and sanitize them
            $data = $request->get_params();
            $tvshow_id = intval($data['tvshow_id']);
            $season_number = isset($data['season_number']) ? intval($data['season_number']) : 0;
            $episode_card_text = isset($data['episode_card_text']) ? sanitize_text_field($data['episode_card_text']) : '';

            // Initialize response array
            $season_ajax   = [];
            $episodes_data = [];

            // Get the TV show data
            $tvshow = streamit_get_tvshow($tvshow_id);
            if (!$tvshow) {
                return wp_send_json_error('Invalid TV show ID.');
            }

            // Get the seasons data
            $season_data = $tvshow->get_meta('_seasons');
            if (empty($season_data)) {
                $season_ajax = [

                    ['tvshow_id'      => $tvshow_id],      // Pass the TV show ID
                    ['season_content' => ''],         // Empty content for the seasons
                    ['block_content'  => esc_html__('No Season Found', 'streamit')],          // Empty content for the episodes
                    ['button_content' => '']          // Empty content for the "view all" button

                ];
                return wp_send_json_success($season_ajax);
            }

            // Handle season change
            if (isset($data['season_change']) && $data['season_change'] === 'true') {
                array_push($season_ajax, ['tvshow_id' => $tvshow_id]);
                foreach ($season_data as $index => $val) {
                    ob_start(); ?>
                    <a class="nav-item nav-link css_prefix-episodes-meta tvshow-<?php echo esc_attr($tvshow_id); ?> data-ajax_loaded=<?php echo 1; ?> <?php echo $index === 0 ? 'active' : '' ?>"
                        data-tvshow-id="<?php echo esc_attr($tvshow_id); ?>"
                        data-season="<?php echo esc_attr($index); ?>">
                        <?php echo esc_html($val['name']); ?>
                    </a>
                <?php
                    $season_content = ob_get_clean();
                    if ($tvshow_id != $data['first_tvshow_id']) {
                        array_push($season_ajax, ['season_content' => $season_content]);
                    }
                }
            }

            // Retrieve episodes for the selected season
            if (!isset($season_data[$season_number])) {
                return wp_send_json_error('Invalid season number.');
            }

            $season = $season_data[$season_number];
            $episode_posts = isset($season['episodes']) && !empty($season['episodes']) ? streamit_get_episodes(['orderby' => 'post__in', 'include' => $season['episodes'], 'paged' => 1, 'per_page' => -1])->results : [];
            $episode_count = 0;

            foreach ($episode_posts as $episode) {
                $url = !empty($episode->get_meta('thumbnail_id')) ? wp_get_attachment_image_url($episode->get_meta('thumbnail_id'), 'full') : streamit_placeholder_image();
                $episode_run_time = $episode->get_meta('_episode_run_time');

                ob_start(); ?>
                <div class="episodes-info episode-<?php echo esc_attr($episode->get_id()); ?> tvshow-<?php echo esc_attr($tvshow_id); ?>-season-<?php echo esc_attr($season_number + 1); ?>">
                    <div class="episode-img">
                        <img src="<?php echo esc_url($url); ?>" alt="episode-img">
                    </div>
                    <div class="episodes-meta">
                        <div class="episode-name">
                            <a href="<?php echo esc_url(streamit_get_permalink($episode->get_post_type(), $episode->get_post_name())); ?>">
                                <?php echo esc_html($episode->get_post_title()); ?>
                            </a>
                        </div>
                        <?php if (!empty($episode_run_time) && $episode_run_time !== '0:00') : ?>
                            <div class="episode-time mt-2">
                                <?php echo st_get_icon('clock'); ?>
                                <?php echo esc_html(st_format_runtime($episode_run_time)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                $block_content = ob_get_clean();
                array_push($season_ajax, ['block_content' => $block_content]);

                // Limit to showing only 5 episodes
                if ($episode_count == 4) {
                    ob_start(); ?>
                    <div class="view-all-btn episodes-info tvshow-<?php echo esc_attr($tvshow_id); ?>-season-<?php echo esc_attr($season_number + 1); ?>">
                        <div class="p-btns align-items-center justify-content-center">
                            <a href="<?php echo esc_url(streamit_get_permalink($tvshow->get_post_type(), $tvshow->get_post_name())); ?>" class="season-btn">
                                <?php echo esc_html($episode_card_text); ?>
                            </a>
                        </div>
                    </div>
                <?php
                    $button_content = ob_get_clean();
                    array_push($season_ajax, ['button_content' => $button_content]);
                    break;
                }
                $episode_count++;
            }

            // Add episodes data to the final response
            array_push($episodes_data, $season_ajax);

            return wp_send_json_success($season_ajax);
        } catch (Exception $e) {
            return wp_send_json_error($e->getMessage());
        }
    }


    /**
     * Get Episods from season via post request.
     *
     * @param WP_REST_Request $request The REST API request object.
     */
    public function get_tvshow_tab_episodes(WP_REST_Request $request)
    {
        try {
            $data          = $request->get_params();
            $season_data   = $data['data'];
            $season_data   = $season_data[$data['season']];
            $is_slider     =  isset($data['is_slider']) ? $data['is_slider'] : '';
            $season_ajax   = array();
            $episode_posts = isset($season_data['episodes']) && !empty($season_data['episodes']) ? streamit_get_episodes(['orderby' => 'post__in', 'include' => $season_data['episodes'], 'paged' => 1, 'per_page' => -1])->results : [];
            foreach ($episode_posts as $episode) {
                ob_start();
                if ($is_slider == 'true') :
                    streamit_get_template('episode/content/episode_single_slider.php', ['episode' => $episode]);

                else:
                    streamit_get_template('episode/single/episode_single_season_card.php', ['st_data' =>  $episode]);
                endif;
                $block_main_content = ob_get_clean();

                array_push($season_ajax, array('block_main_content' => $block_main_content));
            }

            echo json_encode(array('success' => true, 'result' => $season_ajax));
        } catch (Exception $e) {
            return wp_send_json_error($e->getMessage());
        }
    }
}
