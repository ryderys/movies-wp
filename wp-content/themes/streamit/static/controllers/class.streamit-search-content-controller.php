<?php

defined('ABSPATH') || exit;


final class St_Search_Content_Controller
{

    /**
     * AJAX Search Handler.
     *
     * Handles search queries across different post types and returns results as JSON.
     *
     * @param WP_REST_Request $request The REST API request object.
     *
     * @return void
     */
    public function search_handler(WP_REST_Request $request)
    {
        // Retrieve search parameters from request.
        $data         = $request->get_params();
        $search_query = isset($data['data']) ? sanitize_text_field($data['data']) : '';
        $result       = '';

        if (empty($search_query)) {
            return wp_send_json($result);
        }

        // Set up search arguments.
        $args = apply_filters('st_header_search_args', [
            'per_page' => 2,
            's'        => $search_query,
        ]);

        // Gather results from different sources.
        $result .= $this->get_movie($args);
        $result .= $this->get_video($args);
        $result .= $this->get_tvshow($args);
        $result .= $this->get_episode($args);
        $result .= $this->get_person($args);

        $result = apply_filters('st_header_search_result', $result, $search_query);

        if (!empty($result)) {
            $view_all_url = esc_url(add_query_arg(
                [
                    's'           => urlencode($search_query),
                    'ajax_search' => 'true',
                ],
                home_url()
            ));
            $view_all = '<a href="' . $view_all_url . '">' . esc_html__('View All', 'streamit') . '</a>';
            $output   = '<div class="search_output"><div class="item-body">' . $result . '</div>' .
                '<div class="item-footer">' . $view_all . '</div></div>';
        } else {
            $output = '<div class="search_output"><div class="item-body">' . esc_html__('No Data Found', 'streamit') . '</div></div>';
        }

        return wp_send_json($output);
    }


    /**
     * Retrieve movies based on search criteria.
     *
     * @param array $args The search arguments.
     * @return string HTML output of the search results.
     */
    public function get_movie(array $args)
    {
        // Start output buffering
        ob_start();

        // Fetch movies based on the provided arguments
        $data = function_exists('streamit_get_movies') ? streamit_get_movies($args) : [];

        if (isset($data->results) && !empty($data->results)) {
?>
            <div class="search_contain">
                <h6><?php echo esc_html__('Movies', 'streamit'); ?></h6>
                <?php foreach ($data->results as $movie): ?>
                    <div class="search_contain_list">
                        <?php echo streamit_get_template('common/html-common-list.php', ['args' => $movie]); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php
        }

        // Get the buffer content and clean it
        return ob_get_clean();
    }

    /**
     * Retrieve videos based on search criteria.
     *
     * @param array $args The search arguments.
     */
    public function get_video(array $args)
    {
        // Start output buffering
        ob_start();

        // Fetch videos based on the provided arguments
        $data = function_exists('streamit_get_videos') ? streamit_get_videos($args) : [];

        if (isset($data->results) && !empty($data->results)) {
        ?>
            <div class="search_contain">
                <h6><?php echo esc_html__('videos', 'streamit'); ?></h6>
                <?php foreach ($data->results as $video): ?>
                    <div class="search_contain_list">
                        <?php echo streamit_get_template('common/html-common-list.php', ['args' => $video]); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php
        }

        // Get the buffer content and clean it
        return ob_get_clean();
    }

    /**
     * Retrieve TV shows based on search criteria.
     *
     * @param array $args The search arguments.
     */
    public function get_tvshow(array $args)
    {
        // Start output buffering
        ob_start();

        // Fetch videos based on the provided arguments
        $data = function_exists('streamit_get_tvshows') ? streamit_get_tvshows($args) : [];

        if (isset($data->results) && !empty($data->results)) {
        ?>
            <div class="search_contain">
                <h6><?php echo esc_html__('TvShows', 'streamit'); ?></h6>
                <?php foreach ($data->results as $tvshow): ?>
                    <div class="search_contain_list">
                        <?php echo streamit_get_template('common/html-common-list.php', ['args' => $tvshow]); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php
        }

        // Get the buffer content and clean it
        return ob_get_clean();
    }

    /**
     * Retrieve episodes based on search criteria.
     *
     * @param array $args The search arguments.
     */
    public function get_episode(array $args)
    {
        // Start output buffering
        ob_start();

        // Fetch videos based on the provided arguments
        $data = function_exists('streamit_get_episodes') ? streamit_get_episodes($args) : [];

        if (isset($data->results) && !empty($data->results)) {
        ?>
            <div class="search_contain">
                <h6><?php echo esc_html__('Episodes', 'streamit'); ?></h6>
                <?php foreach ($data->results as $episode): ?>
                    <div class="search_contain_list">
                        <?php echo streamit_get_template('common/html-common-list.php', ['args' => $episode]); ?>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php
        }

        // Get the buffer content and clean it
        return ob_get_clean();
    }

    /**
     * Retrieve persons based on search criteria.
     *
     * @param array $args The search arguments.
     */
    public function get_person(array $args)
    {
        // Start output buffering
        ob_start();

        // Fetch videos based on the provided arguments
        $data = function_exists('streamit_get_persons') ? streamit_get_persons($args) : [];

        if (isset($data->results) && !empty($data->results)) {
        ?>
            <div class="search_contain">
                <h6><?php echo esc_html__('Persons', 'streamit'); ?></h6>
                <?php foreach ($data->results as $person): ?>
                    <div class="search_contain_list">
                        <?php echo streamit_get_template('common/html-common-list.php', ['args' => $person]); ?>
                    </div>
                <?php endforeach; ?>
            </div>
<?php
        }

        // Get the buffer content and clean it
        return ob_get_clean();
    }
}
