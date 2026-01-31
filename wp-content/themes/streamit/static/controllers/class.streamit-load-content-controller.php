<?php


defined('ABSPATH') || exit;


final class st_load_content_controller
{

    /**
     * Perform Load Post via REST API.
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response
     */
    public function loadpost(WP_REST_Request $request)
    {
        // Retrieve the parameters from the request
        $data = $request->get_param('data');

        if (empty($data)) {
            return wp_send_json_error(['message' => 'Invalid data provided.']);
        }

        $current_page = absint($data['current_page']);
        $post_type    = sanitize_text_field($data['post_type']);
        $per_page     = $data['per_page']; // This is passed from JS, but get_array_data uses a default 10, ensure consistency if needed

        // Define allowed post types
        $allowed_post_types = ['movie', 'person', 'video', 'tvshow', 'episode'];

        $plylist_allowed_post_types = ['movie_playlists', 'video_playlists', 'episode_playlists'];

        $archive_single_post = [
            'movie_single_genre'      => ['post' => 'movie',  'taxonomy_type' => 'genre'],
            'movie_single_tag'        => ['post' => 'movie',  'taxonomy_type' => 'tag'],
            'tvshow_single_genre'     => ['post' => 'tvshow', 'taxonomy_type' => 'genre'],
            'tvshow_single_tag'       => ['post' => 'tvshow', 'taxonomy_type' => 'tag'],
            'video_single_category'   => ['post' => 'video',  'taxonomy_type' => 'category'],
            'video_single_tag'        => ['post' => 'video',  'taxonomy_type' => 'tag'],
            'person_single_category'  => ['post' => 'person', 'taxonomy_type' => 'category'],
            'person_single_tag'       => ['post' => 'person', 'taxonomy_type' => 'tag'],
        ];

        $taxonomy_post_types = [
            'movie_genre'             => ['post' => 'movie',  'taxonomy_type' => 'genre'],
            'movie_tag'               => ['post' => 'movie',  'taxonomy_type' => 'tag'],
            'video_category'          => ['post' => 'video',  'taxonomy_type' => 'category'],
            'video_tag'               => ['post' => 'video',  'taxonomy_type' => 'tag'],
            'tvshow_genre'            => ['post' => 'tvshow', 'taxonomy_type' => 'genre'],
            'tvshow_tag'              => ['post' => 'tvshow', 'taxonomy_type' => 'tag'],
            'person_category'         => ['post' => 'person', 'taxonomy_type' => 'category'],
            'person_tag'              => ['post' => 'person', 'taxonomy_type' => 'tag'],
        ];

        $watchlist_post_types     = ['movie_watchlist', 'video_watchlist', 'tvshow_watchlist'];
        $liked_content_post_types = ['movie_liked', 'video_liked', 'tvshow_liked'];

        $response_data = [
            'status'        => false,
            'result'        => '',
            'total_results' => 0,
            'total_pages'   => 0,
            'current_page'  => $current_page,
        ];

        // Fetch data based on the post type
        if (in_array($post_type, $allowed_post_types, true)) {
            $filters = isset($data['filters']) ? $data['filters'] : [];

            $post_data = $this->get_array_data_full_response($post_type, $current_page, $per_page, $filters);

            if (!empty($post_data->results)) {
                ob_start();
                foreach ($post_data->results as $st_data) {
                    streamit_get_template($post_type . '/archive/archive_loop.php', ['st_data' => $st_data]);
                }
                $response_data['result'] = ob_get_clean();
                $response_data['status'] = true;
                $response_data['total_results'] = $post_data->total;
                $response_data['total_pages'] = $post_data->maxnumpages;
            }
        } elseif (array_key_exists($post_type, $archive_single_post)) {
            $result = $this->get_single_array_data($post_type, $archive_single_post[$post_type], $current_page, $data['post_id']);
            if (!empty($result)) {
                $response_data['result'] = $result;
                $response_data['status'] = true;
            }
        } elseif (in_array($post_type, $plylist_allowed_post_types, true)) {
            $result = $this->get_playlist_array_data($post_type, $current_page);
            if (!empty($result)) {
                $response_data['result']        = $result['html'];
                $response_data['status']        = true;
                $response_data['total_results'] = isset($result['total_results']) ? intval($result['total_results']) : 0;
                $response_data['total_pages']   = isset($result['total_pages']) ? intval($result['total_pages']) : 0;
                $response_data['current_page']  = isset($result['current_page']) ? intval($result['current_page']) : $current_page;
            }
        } elseif (array_key_exists($post_type, $taxonomy_post_types)) {
            $result = $this->get_taxonomy_array_data($post_type, $taxonomy_post_types[$post_type], $current_page);
            $result = $result['result'];
            if (!empty($result)) {
                $response_data['result']        = $result['result'];
                $response_data['status']        = true;
                $response_data['total_results'] = isset($result['total_results']) ? intval($result['total_results']) : 0;
                $response_data['total_pages']   = isset($result['total_pages']) ? intval($result['total_pages']) : 0;
                $response_data['current_page']  = isset($result['current_page']) ? intval($result['current_page']) : $current_page;
            }
        } elseif ($post_type == 'comment') {
            $extra_seeting = isset($data['extra_setting']) ? $data['extra_setting'] : [];
            $result = $this->get_data_from_comment($post_type, $current_page,  $extra_seeting);
            if (!empty($result)) {
                $response_data['result'] = $result['html'];
                $response_data['status'] = true;
                $response_data['total_results'] = isset($result['total_results']) ? intval($result['total_results']) : 0;
                $response_data['total_pages']   = isset($result['total_pages']) ? intval($result['total_pages']) : 0;
                $response_data['current_page']  = isset($result['current_page']) ? intval($result['current_page']) : $current_page;
            }
        } elseif ($post_type == 'widget_post') {
            $extra_seeting = isset($data['extra_setting']) ? $data['extra_setting'] : [];
            $result = $this->get_widget_post_template($post_type, $current_page, $per_page, $extra_seeting);
            if (!empty($result)) {
                $response_data['result']        = $result['html'];
                $response_data['status']        = true;
                $response_data['total_results'] = isset($result['total_results']) ? intval($result['total_results']) : 0;
                $response_data['total_pages']   = isset($result['total_pages']) ? intval($result['total_pages']) : 0;
                $response_data['current_page']  = isset($result['current_page']) ? intval($result['current_page']) : $current_page;
            }
        } elseif (in_array($post_type, $watchlist_post_types, true)) {
            $result = $this->get_watchlist_array_data($current_page, $post_type);
            if (!empty($result)) {
                $response_data['result']        = $result['html'];
                $response_data['status']        = true;
                $response_data['total_results'] = isset($result['total_results']) ? intval($result['total_results']) : 0;
                $response_data['total_pages']   = isset($result['total_pages']) ? intval($result['total_pages']) : 0;
                $response_data['current_page']  = isset($result['current_page']) ? intval($result['current_page']) : $current_page;
            }
        } elseif (in_array($post_type, $liked_content_post_types, true)) {
            $result = $this->get_liked_content_array_data($current_page, $post_type);
            if (!empty($result['html'])) {
                $response_data['result']        = $result['html'];
                $response_data['status']        = true;
                $response_data['total_results'] = isset($result['total_results']) ? intval($result['total_results']) : 0;
                $response_data['total_pages']   = isset($result['total_pages']) ? intval($result['total_pages']) : 0;
                $response_data['current_page']  = isset($result['current_page']) ? intval($result['current_page']) : $current_page;
            }
        } elseif ($post_type == 'read_notification' ||  $post_type == 'unread_notification') {
            $result = $this->get_notification_array_data($post_type, $current_page);
            if (!empty($result)) {
                $response_data['result']        = $result['html'];
                $response_data['status']        = true;
                $response_data['total_results'] = isset($result['total_results']) ? intval($result['total_results']) : 0;
                $response_data['total_pages']   = isset($result['total_pages']) ? intval($result['total_pages']) : 0;
                $response_data['current_page']  = isset($result['current_page']) ? intval($result['current_page']) : $current_page;
            }
        } else {
            $result = $this->get_data_from_post_type($post_type, $current_page);
            if (!empty($result)) {
                $response_data['result'] = $result;
                $response_data['status'] = true;
            }
        }

        return wp_send_json($response_data);
    }

    /**
     * Fetch posts from WordPress based on post type and current page.
     *
     * @param string $post_type The post type to query.
     * @param int    $current_page The current page number for pagination.
     * @return string The HTML content or empty string if no posts found.
     */
    public function get_data_from_post_type($post_type, $current_page)
    {
        // Sanitize the input parameters
        $post_type = sanitize_text_field($post_type);
        $current_page = absint($current_page);

        // Get the number of posts per page from WordPress settings
        $posts_per_page = absint(get_option('posts_per_page', 10));

        // Calculate the offset for pagination
        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => $posts_per_page,
            'paged'          => $current_page, // Use paged for WP_Query to handle pagination correctly for found_posts
            'post_status'    => 'publish',
            // 'no_found_rows'  => true, // REMOVE this if you need total counts for this specific function
        ];
        // Run the query
        $query = new WP_Query($args);

        // Check if the query returned any posts
        if ($query->have_posts()) {
            // Start output buffering to capture the HTML
            ob_start();

            // Loop through the posts and capture the output
            while ($query->have_posts()) {
                $query->the_post();

                // Load the template part for the given post type

                streamit_get_template('content/entry.php');
            }

            // Get the contents of the buffer
            $html_content = ob_get_clean();

            // Restore the original post data
            wp_reset_postdata();

            // Return the captured HTML content
            return $html_content;
        }

        // Return empty string if no posts found
        return '';
    }


    /**
     * Retrieves and processes post data based on post type and current page.
     *
     * @param string $post_type    The type of post to retrieve (e.g., 'movie', 'video').
     * @param int    $current_page The current page number for pagination.
     * @param array  $filters      An array of filters applied from the frontend.
     * * @return object An object containing results (HTML), total count, and max number of pages.
     */
    public function get_array_data_full_response(string $post_type, int $current_page, int $per_page, array $filters = [])
    {
        $args = apply_filters('streamit_' . $post_type . 's_arguments', [
            'per_page'      => $per_page,
            'post_status'   => array('publish'),
            'order'         => 'DESC',
            'paged'         => $current_page,
            'filters'       => $filters,
        ]);
        $get_posts_function = 'streamit_get_' . $post_type . 's';


        $response_object = (object) [
            'results'     => [],
            'total'       => 0,
            'maxnumpages' => 0,
        ];

        // Check if the data retrieval function exists
        if (function_exists($get_posts_function)) {
            // Retrieve post data (this returns the object with results, total, maxnumpages)
            $post_data = call_user_func($get_posts_function, $args);

            if (!empty($post_data->results)) {
                $response_object->results = $post_data->results;
                $response_object->total = $post_data->total;
                $response_object->maxnumpages = $post_data->maxnumpages;
            }
        }
        return $response_object;
    }

    /**
     * Retrieves playlist HTML and pagination info.
     *
     * @param string $post_type
     * @param int    $current_page
     * @return array{html:string, total_results:int, total_pages:int, current_page:int}
     */
    public function get_playlist_array_data(string $post_type, int $current_page): array
    {
        $user_id = get_current_user_id();

        // per-page (allow filter)
        $per_page = apply_filters('playlist_per_page_count', 5);

        $args = [
            'user_id'  => $user_id,
            'per_page' => intval($per_page),
            'paged'    => max(1, intval($current_page)),
        ];

        $get_posts_function = 'streamit_get_' . $post_type;
        $template_path = 'playlist/archive/' . $post_type . '_loop.php';

        // default structured response
        $response = [
            'html'          => '',
            'total_results' => 0,
            'total_pages'   => 0,
            'current_page'  => max(1, intval($current_page)),
        ];

        if (!function_exists($get_posts_function)) {
            return $response;
        }

        $post_data = call_user_func($get_posts_function, $args);

        if (empty($post_data) || !isset($post_data->results) || empty($post_data->results)) {
            return $response;
        }

        ob_start();
        foreach ($post_data->results as $st_data) {
            streamit_get_template($template_path, ['st_data' => $st_data]);
        }
        $html_content = ob_get_clean();

        // Calculate total_results and total_pages using playlist class methods
        // The playlist function may return total/maxnumpages as 0, so we calculate it ourselves
        // Map post_type to playlist class name
        $playlist_class_map = [
            'movie_playlists'   => 'Streamit_Movie_Playlist',
            'video_playlists'   => 'Streamit_Video_Playlist',
            'episode_playlists' => 'Streamit_Episode_Playlist',
        ];
        
        $playlist_class_name = isset($playlist_class_map[$post_type]) ? $playlist_class_map[$post_type] : null;
        
        if ($playlist_class_name && class_exists($playlist_class_name)) {
            // Use playlist class method to get count efficiently
            $playlist_instance = new $playlist_class_name();
            $count_args = [
                'user_id'  => $user_id,
                'per_page' => 1, // Minimal per_page for count query
                'paged'    => 1,
            ];
            
            // Use the class's get_*_playlists method which handles count internally
            // Method names: get_movie_playlists, get_video_playlists, get_episode_playlists
            $count_method = 'get_' . $post_type;
            if (method_exists($playlist_instance, $count_method)) {
                $count_data = $playlist_instance->$count_method($count_args);
                $total_results = !empty($count_data) && isset($count_data->total) && intval($count_data->total) > 0
                    ? intval($count_data->total)
                    : count($post_data->results);
            } else {
                // Fallback: use current page results count
                $total_results = count($post_data->results);
            }
        } else {
            // Fallback: use current page results count
            $total_results = count($post_data->results);
        }
        
        $total_pages = (int) ceil($total_results / max(1, intval($per_page)));

        $response['html'] = $html_content;
        $response['total_results'] = $total_results;
        $response['total_pages'] = $total_pages;

        return $response;
    }

    /**
     * Retrieves liked post data (HTML + pagination info)
     *
     * @param int    $current_page
     * @param string $post_type (e.g. movie_liked, video_liked)
     * @return array{html:string, total_results:int, total_pages:int, current_page:int}
     */
    public function get_liked_content_array_data(int $current_page, string $post_type): array
    {
        $response = [
            'html'          => '',
            'total_results' => 0,
            'total_pages'   => 0,
            'current_page'  => max(1, intval($current_page)),
        ];

        $user_id = get_current_user_id();
        $actual_post_type = str_replace('_liked', '', $post_type);
        $per_page = apply_filters('liked_content_per_page_count', 10);

        // fetch data
        $liked_data = function_exists('streamit_get_user_liked_posts') ? streamit_get_user_liked_posts($user_id, [$actual_post_type], $current_page, $per_page) : [];

        if ( empty($liked_data) || empty($liked_data[$actual_post_type]) || empty($liked_data[$actual_post_type]->results)) {
            return $response; // no results
        }

        $paged_data = $liked_data[$actual_post_type];

        // Generate HTML
        ob_start();
        foreach ($paged_data->results as $st_data) {
            streamit_get_template('common/html-common-card.php', [
                'st_data'          => $st_data,
                'is_liked_content' => true,
            ]);
        }
        $html = ob_get_clean();

        // totals
        $total_results = isset($paged_data->total) ? intval($paged_data->total) : count((array)$paged_data->results);
        $total_pages = isset($paged_data->maxnumpages)
            ? intval($paged_data->maxnumpages)
            : (int) ceil($total_results / max(1, intval($per_page)));

        $response['html'] = $html;
        $response['total_results'] = $total_results;
        $response['total_pages'] = $total_pages;

        return $response;
    }

    /**
     * Retrieves and processes taxonomy data for a given post type and current page.
     *
     * @param string $post_type        The taxonomy post type (e.g., 'movie_genre').
     * @param array  $mapped_post_type An associative array containing 'post' (e.g., 'movie')
     *                                 and 'taxonomy_type' (e.g., 'genre').
     * @param int    $current_page     The current page for pagination.
     *
     * @return array{result:string, total_results:int, total_pages:int} An array containing the HTML result, total results, and total pages.
     */
    public function get_taxonomy_array_data(string $post_type, array $mapped_post_type, int $current_page): array
    {
        $response = [
            'result'        => '',
            'total_results' => 0,
            'total_pages'   => 0,
        ];

        if (!function_exists('streamit_get_terms')) {
            return $response;
        }

        $taxonomy_name = $mapped_post_type['post'] . '_' . $mapped_post_type['taxonomy_type'];
        $template_path = $mapped_post_type['post'] . '/archive/archive_' . $mapped_post_type['taxonomy_type'] . '_loop.php';

        $per_page = max(1, (int) apply_filters('streamit_taxonomy_terms_per_page', 10, $post_type, $mapped_post_type));
        $paged = max(1, $current_page);

        $terms_data = streamit_get_terms(
            [
                'paged'    => $paged,
                'per_page' => $per_page,
                'taxonomy' => [$taxonomy_name],
                'order'    => 'ASC',
                'orderby'  => 'term_id',
            ]
        );

        if (empty($terms_data) || is_wp_error($terms_data) || empty($terms_data->results)) {
            return $response;
        }

        ob_start();
        foreach ($terms_data->results as $st_data) {
            streamit_get_template($template_path, ['st_data' => $st_data]);
        }

        $response['result'] = ob_get_clean() ?: '';
        $total_results = isset($terms_data->total) ? (int) $terms_data->total : count($terms_data->results);
        $response['total_results'] = $total_results;
        $response['total_pages'] = isset($terms_data->maxnumpages)
            ? (int) $terms_data->maxnumpages
            : (int) ceil($total_results / $per_page);

        return $response;
    }


    public function get_data_from_comment($post_type, $current_page, $extra_seeting = [])
    {
        // Set up the query arguments
        $args = [
            'per_page'        => 5,
            'comment_post_ID' => $extra_seeting['comment_post_ID'],
            'paged'           => $current_page,
            'post_type'       => [$extra_seeting['post_type']],
        ];

        $comments = streamit_get_comments($args);
        if (!empty($comments->results)) :
            ob_start();
            foreach ($comments->results as $comment) :
                st_comment_html_details($comment);
            endforeach;
            $html_content = ob_get_clean();

            $total_results = isset($comments->total) ? intval($comments->total) : count($comments->results);
            $total_pages = isset($comments->max_num_pages) ? intval($comments->max_num_pages) : (int) ceil($total_results / max(1, intval($args['per_page'])));

            // Return the captured HTML content
            return [
                'html'          => $html_content,
                'total_results' => $total_results,
                'total_pages'   => $total_pages,
            ];
        endif;
        return '';
    }

    public function get_widget_post_template($post_type, $current_page, $per_page, $extra_seeting = [])
    {
        // Sanitize the input parameters
        $post_type    = sanitize_text_field($post_type);
        $current_page = absint($current_page);


        // Get the number of posts per page from WordPress settings
        $posts_per_page = (!empty($per_page)) ? $per_page : absint(get_option('posts_per_page', 10));
        // Calculate the offset for pagination
        $args_post = [
            'post_type'      => 'post',
            'posts_per_page' => $posts_per_page,
            'paged'          => $current_page,
            'order'          => 'DESC',
        ];
        // Run the query
        $query = new WP_Query($args_post);

        // Check if the query returned any posts
        if ($query->have_posts()) {
            // Start output buffering to capture the HTML
            ob_start();

            // Loop through the posts and capture the output
            while ($query->have_posts()) {
                $query->the_post();

                // Load the template part for the given post type
                streamit_get_template('elementor-widget/Blog/widget_post.php', ['settings' => $extra_seeting]);
            }

            // Get the contents of the buffer
            $html_content = ob_get_clean();

            // Restore the original post data
            wp_reset_postdata();

            // Totals from WP_Query
            $total_results = isset($query->found_posts) ? intval($query->found_posts) : 0;
            $total_pages   = isset($query->max_num_pages) ? intval($query->max_num_pages) : 0;

            // Return the captured HTML content
            return [
                'html'          => $html_content,
                'total_results' => $total_results,
                'total_pages'   => $total_pages,
            ];
        }

        // Return empty string if no posts found
        return '';
    }

    public function get_watchlist_array_data($current_page, $post_type)
    {
        $user_id = get_current_user_id();
        $watchlist_data = function_exists('streamit_user_watchlist') ? streamit_user_watchlist($user_id) : [];

        if (empty($watchlist_data)) {
            return '';
        }

        $post_type = explode('_', $post_type);
        $post_type = $post_type[0];

        $watchlist_ids = isset($watchlist_data[$post_type]) ? $watchlist_data[$post_type] : '';

        if (empty($watchlist_ids)) return '';
        $watchlist_per_page_count = apply_filters('watchlist_per_page_count', 10);
        $args = ['paged' => $current_page, 'per_page' => $watchlist_per_page_count, 'include' => $watchlist_ids];
        $function_name = 'streamit_get_' . $post_type . 's';
        $paged_data = function_exists($function_name) ? call_user_func($function_name, $args) : [];
        if (!isset($paged_data->results) || empty($paged_data->results)) return '';

        // Start output buffering to capture the HTML
        ob_start();

        // Loop through the posts and capture the output
        foreach ($paged_data->results as $st_data) {
            ob_start();
            echo streamit_get_template('common/html-common-card.php', ['st_data' => $st_data, 'is_watchlist' => true]);
            echo ob_get_clean();
        }

        // Get the contents of the buffer
        $html_content = ob_get_clean();

        // Determine totals (use returned properties if provided)
        $total_results = isset($paged_data->total) ? intval($paged_data->total) : count((array)$paged_data->results);
        $total_pages = isset($paged_data->maxnumpages) ? intval($paged_data->maxnumpages) : (int) ceil($total_results / max(1, intval($watchlist_per_page_count)));

        return [
            'html'          => $html_content,
            'total_results' => $total_results,
            'total_pages'   => $total_pages,
        ];
    }

    public function get_notification_array_data($post_type, $current_page)
    {
        // Ensure that is_seen is either 0 (unread) or 1 (read)
        $is_seen = ($post_type === 'read_notification') ? 1 : 0;
    
        $args = apply_filters('st_' . $post_type . '_notfication_arguments', [
            'paged'           => $current_page,
            'is_seen'         => $is_seen,
            'is_current_user' => get_current_user_id(),
            'per_page'        => 15,
        ]);
    
        $all_data = streamit_get_notifications($args);
    
        // Prepare default response structure
        $response = [
            'html'          => '',
            'total_results' => 0,
            'total_pages'   => 0,
            'current_page'  => max(1, intval($current_page)),
        ];
    
        if (!empty($all_data->results)) {
    
            // Build HTML
            ob_start();
            foreach ($all_data->results as $st_data) {
                streamit_get_template('profile/single/user_notification_loop.php', ['st_data' => $st_data]);
            }
            $response['html'] = ob_get_clean();
    
            // Count total results
            $total_results = isset($all_data->total) ? intval($all_data->total) : count((array)$all_data->results);
    
            // Calculate total pages
            $per_page = isset($args['per_page']) ? intval($args['per_page']) : 15;
            $total_pages = isset($all_data->maxnumpages) ? intval($all_data->maxnumpages) : (int) ceil($total_results / max(1, $per_page));
    
            $response['total_results'] = $total_results;
            $response['total_pages']   = $total_pages;
        }
    
        return $response;
    }


    /**
     * Retrieves and processes post data based on post type and current page.
     *
     * @param string $post_type    The type of post to retrieve (e.g., 'movie', 'video').
     * @param int    $current_page The current page number for pagination.
     * * @return string The HTML content generated from the retrieved posts, or an empty string if no data is found.
     */
    public function get_single_array_data(string $post_type, array $mapped_post_type, int $current_page, int $post_id): string
    {

        // Set up query arguments
        $args = apply_filters('streamit_' . $mapped_post_type['post'] . '_single_' .  $mapped_post_type['taxonomy_type'] . '_arguments', [
            'paged'     => $current_page,
            'per_page'  => 12,
            'tax_query' => array(
                'relation' => 'OR',
                array(
                    'field'    => 'term_id',
                    'terms'    => $post_id,
                    'operator' => '=',
                ),
            )
        ]);
        $get_posts_function = 'streamit_get_' . $mapped_post_type['post'] . 's';
        $template_path  = $mapped_post_type['post'] . '/archive/archive_loop.php';

        // Check if the data retrieval function exists
        if (function_exists($get_posts_function)) :
            // Retrieve post data
            $post_data = call_user_func($get_posts_function, $args);

            // Check if the retrieved data contains results
            if (!empty($post_data->results)) :
                ob_start(); // Start output buffering
                // Loop through each post data and apply the template
                foreach ($post_data->results as $st_data) :
                    streamit_get_template($template_path, ['st_data' => $st_data]);
                endforeach;

                return ob_get_clean();
            endif;
        endif;

        return '';
    }
}
