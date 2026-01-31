<?php

defined('ABSPATH') || exit;


final class st_Manage_component_Controller
{

    /**
     * Perform continue watch udpate data.
     *
     * @param WP_REST_Request $request The REST API request object.
     */
    public function Contine_Watched_Update(WP_REST_Request $request)
    {
        $data = $request->get_params();
        $post_type = isset($data['post_type']) ? sanitize_text_field($data['post_type']) : '';
        $user_id   = isset($data['user_id']) ? intval($data['user_id']) : 0;

        $result = streamit_manage_continue_watching($user_id, $post_type, $data);
        wp_send_json($result);
    }

    /**
     * Perform Add / Remove Method For WatchList.
     *
     * @param WP_REST_Request $request The REST API request object.
     */
    public function Manage_Watch_List(WP_REST_Request $request)
    {
        $data = $request->get_params();

        // Get the post ID, post type, and action (add/remove)
        $post_id   = isset($data['post_id']) ? intval($data['post_id']) : 0;
        $post_type = isset($data['post_type']) ? sanitize_text_field($data['post_type']) : '';
        $action    = isset($data['update_action']) ? sanitize_text_field($data['update_action']) : '';

        // Get the current user ID
        $user_id = get_current_user_id();

        if ($post_id && $post_type && $user_id) {

            if ($action === 'add') {
                // Add the post to the user's watchlist
                $result = streamit_add_to_watchlist($post_id, $post_type, $user_id);
                if ($result) {
                    return wp_send_json_success([
                        'message' => esc_html__('Added to your watchlist.', 'streamit')
                    ]);
                }
            } elseif ($action === 'remove') {
                // Remove the post from the user's watchlist
                $result = streamit_remove_from_watchlist($post_id, $post_type, $user_id);
                if ($result) {
                    return wp_send_json_success([
                        'message' => esc_html__('Removed from your watchlist.', 'streamit')
                    ]);
                }
            } else {
                return wp_send_json_error(esc_html__('Invalid action.', 'streamit'));
            }

            if (!$result) {
                return wp_send_json_error(esc_html__('Failed to update watchlist.', 'streamit'));
            }
        }

        return wp_send_json_error(esc_html__('Invalid request.', 'streamit'));
    }


    /**
     * Remove Continue Watch Data From User.
     *
     * @param WP_REST_Request $request The REST API request object.
     */
    public function Remove_Continue_Watch(WP_REST_Request $request)
    {
        $data = $request->get_params();
        // Get the post ID, post type, and action (add/remove)
        $post_id   = isset($data['post_id']) ? intval($data['post_id']) : 0;
        $post_type = isset($data['post_type']) ? sanitize_text_field($data['post_type']) : '';
        $user_id = get_current_user_id();

        $result = streamit_remove_continue_watching($user_id, $post_type, $post_id);
        if ($result) {
            return wp_send_json_success();
        }
        return wp_send_json_error(esc_html__('Invalid request.', 'streamit'));
    }

    /**
     * Submit Comment Form.
     *
     * Handles adding and updating comments through the REST API.
     *
     * @param WP_REST_Request $request The REST API request object.
     *
     * @return WP_REST_Response The response object with status and message.
     */
    public function Submit_Comment(WP_REST_Request $request)
    {
        $data = $request->get_params();

        if (empty($data['rating'])) {
            return wp_send_json([
                'status'  => false,
                'message' => esc_html__('Rating should be required', 'streamit')
            ]);
        }

        // Prepare comment arguments
        $args = [
            'post_type'             => isset($data['post_type']) ? $data['post_type'] : '',
            'comment_post_ID'       => isset($data['post_id']) ? (int)$data['post_id'] : '',
            'comment_author'        => isset($data['user_name']) ? $data['user_name'] : '',
            'comment_author_email'  => isset($data['user_email']) ? $data['user_email'] : '',
            'rating'                => isset($data['rating']) ? (int)$data['rating'] : '',
            'comment_content'       => isset($data['cm_details']) ? $data['cm_details'] : '',
            'user_id'               => get_current_user_id(),
        ];

        // Check if we are updating an existing comment or adding a new one
        if (!empty($data['comment_id'])) {
            // Update comment
            $result = streamit_update_comment($data['comment_id'], $args);
            $status = !is_wp_error($result);
            $message = $status ? esc_html__('Review Updated', 'streamit') : esc_html__('Something Went Wrong', 'streamit');
        } else {
            // Add new comment
            $result = streamit_add_comment($args);
            $status = !is_wp_error($result);
            $message = $status ? esc_html__('Review Added', 'streamit') : esc_html__('Something Went Wrong', 'streamit');
        }

        // Return JSON response
        return wp_send_json([
            'status'  => $status,
            'message' => $message
        ]);
    }

    /**
     * Handles email subscription form submissions via a REST API endpoint.
     *
     * @param WP_REST_Request $request The REST API request object containing the submitted data.
     * @return WP_REST_Response|array JSON response indicating the success or failure of the subscription process.
     */
    function Submit_Subscribe_Form(WP_REST_Request $request)
    {
        $data = $request->get_params();

        if (empty($data['email']) || !is_email($data['email'])) {
            return wp_send_json([
                'status'  => false,
                'message' => esc_html__('Invalid email address.', 'streamit')
            ]);
        }

        $email = sanitize_email($data['email']);

        // Add your logic to save the email, e.g., to the database or a third-party service
        // For now, we simulate a successful save:
        $result = true; // Replace this with your actual save logic

        if ($result) {
            return wp_send_json([
                'status'  => true,
                'message' => esc_html__('Subscription successful! Thank you.', 'streamit')
            ]);
        } else {
            return wp_send_json([
                'status'  => false,
                'message' => esc_html__('Something went wrong. Please try again.', 'streamit')
            ]);
        }
    }



    /**
     * Delete Comment.
     *
     * @param WP_REST_Request $request The REST API request object.
     */
    public function delete_comment(WP_REST_Request $request)
    {
        $data = $request->get_params();

        $result = streamit_delete_comment($data['comment_id'], $data['post_id'], $data['post_type']);
        if ($result)
            return wp_send_json([
                'status'  => true,
                'message' => esc_html__('Review Deleted', 'streamit')
            ]);

        return wp_send_json([
            'status'  => false,
            'message' => esc_html__('Something Went Wrong', 'streamit')
        ]);
    }

    /**
     * Manage Like.
     *
     * @param WP_REST_Request $request The REST API request object.
     */
    public function manage_like(WP_REST_Request $request)
    {
        $data       =   $request->get_params();
        $do_like    = streamit_handle_like([
            'post_id'   => $data['post_id'],
            'user_id'   => get_current_user_id(),
            'post_type' => $data['post_type'],
            'action'    => 'like',
        ]);
        $is_liked  =  streamit_is_like((int)$data['post_id'], $data['post_type'], get_current_user_id());
        
        // Get the updated like count
        $like_count = 0;
        if (function_exists('streamit_get_like_count')) {
            $like_count = streamit_get_like_count([
                'post_id' => $data['post_id'],
                'post_type' => $data['post_type']
            ]);
        }
        
        return wp_send_json(['is_liked' => $is_liked, 'do_like' => $do_like, 'like_count' => $like_count]);
    }

    /**
     * Create or update a user playlist.
     *
     * @param WP_REST_Request $request The REST API request object.
     * 
     * @return WP_REST_Response
     */
    public function creat_playlist(WP_REST_Request $request)
    {
        // Get parameters from the request
        $data = $request->get_params();
        $post_type = sanitize_text_field($data['post_type']);
        $playlist_title = sanitize_text_field($data['playlist_title']);
        $playlist_id = isset($data['playlist_id']) ? intval($data['playlist_id']) : 0;

        // Check if the playlist title is provided
        if (empty($playlist_title) || empty($post_type)) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Please fill all fields', 'streamit')
            ]);
        }

        // Determine the function name based on whether a playlist ID is provided
        $function_name = $playlist_id ? 'streamit_update_' . $post_type . '_playlist' : 'streamit_add_' . $post_type . '_playlist';
        // Check if the function exists
        if (!function_exists($function_name)) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Somthing went wrong', 'streamit')

            ]);
        }

        // Prepare the arguments
        $args = [
            'playlist_name' => $playlist_title,
            'user_id' => get_current_user_id(),
        ];


        // Call the function dynamically to create or update the playlist
        $is_update = false;
        if ($playlist_id) :
            $result = call_user_func($function_name, $playlist_id, $args);
            $is_update = true;
        else:
            $result = call_user_func($function_name, $args);
        endif;


        // Check for errors
        if (is_wp_error($result) || !$result) {
            return wp_send_json([
                'status' => false,
                'message' => esc_html__('Somthing went wrong', 'streamit')
            ]);
        }

        // Return a success response
        return wp_send_json([
            'status' => true,
            'message' => $is_update ? esc_html__('Playlist updated successfully', 'streamit') : esc_html__('Playlist created successfully', 'streamit')
        ]);
    }


    /**
     * Manage Data in playlist.
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response JSON response.
     */
    public function add_in_playlist(WP_REST_Request $request)
    {
        // Get and sanitize input data
        $post_type   = sanitize_text_field($request->get_param('post_type'));
        $playlist_id = absint($request->get_param('playlist_id'));
        $post_id     = absint($request->get_param('post_id'));
        $checked     = filter_var($request->get_param('is_checked'), FILTER_VALIDATE_BOOLEAN);

        // Define message types for each post type
        $message_type = array(
            'movie'  => esc_html__('Movie', 'streamit'),
            'video'  => esc_html__('Video', 'streamit'),
            'episode' => esc_html__('Episode', 'streamit')
        );

        // Check for required data and if streamit class exists
        if (!class_exists('streamit') || !$post_type || !$playlist_id || !$post_id) {
            return wp_send_json(
                [
                    'success' => false,
                    'message' => esc_html__('Invalid request parameters.', 'streamit')
                ],
                400
            );
        }

        // Perform action based on checkbox status
        if ($checked) {
            // Attempt to add playlist relation
            $playlist_relation = streamit_add_playlist_relation([
                'playlist_id' => $playlist_id,
                'post_type'   => $post_type,
                'post_id'     => $post_id,
            ]);

            if ($playlist_relation) {
                return wp_send_json([
                    'success' => true,
                    'message' => sprintf(
                        esc_html__('%s added successfully.', 'streamit'),
                        $message_type[$post_type]
                    ),
                ], 200);
            }
        } else {
            // Attempt to remove playlist item
            $playlist_relation = streamit_delete_playlist_item($playlist_id, $post_type, $post_id);

            if ($playlist_relation) {
                return wp_send_json([
                    'success' => true,
                    'message' => sprintf(
                        esc_html__('%s removed successfully.', 'streamit'),
                        $message_type[$post_type]
                    ),
                ], 200);
            }
        }

        // Return a default error response if no action was successful
        return wp_send_json(
            [
                'success' => false,
                'message' => esc_html__('Something went wrong. Please try again.', 'streamit')
            ],
            500
        );
    }


    /**
     * Update Notification status.
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response JSON response.
     */
    public function Update_Notification_Seen_Status(WP_REST_Request $request)
    {
        $notification_id    = absint($request->get_param('notification_id'));
        $user_id            = absint($request->get_param('user_id'));
        $read               = absint($request->get_param('is_seen'));
        $status = 1;
        if ($read === 1) {
            $status = 0;
        }
        $results            = streamit_update_notification_seen_status($user_id, $notification_id, $status);
        wp_send_json($results);
    }

    /**
     * delete playlist.
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response JSON response.
     */
    public function delete_playlist(WP_REST_Request $request)
    {
        $playlist_id = absint($request->get_param('playlist_id'));
        $post_type = sanitize_text_field($request->get_param('post_type'));
        $function_name = 'streamit_delete_' . $post_type . '_playlist';
        if (function_exists($function_name)) {
            $delete = call_user_func($function_name, $playlist_id);
            if (!is_wp_error($delete)) {
                return wp_send_json([
                    'status'  => true,
                    'message' => esc_html__('Playlist Deleted', 'streamit')
                ]);
            }
        }

        return wp_send_json([
            'status'  => false,
            'message' => esc_html__('Somthing Went Wrong', 'streamit')
        ]);
    }

    /**
     * Handle "Notify Me" toggle for upcoming movies, videos, TV shows, or seasons.
     *
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response JSON response.
     */
    public function notify_me_upcoming(WP_REST_Request $request)
    {
        $data = $request->get_params();

        $post_id   = isset($data['post_id']) ? intval($data['post_id']) : 0;
        $post_type = isset($data['post_type']) ? sanitize_text_field($data['post_type']) : '';
        $season_id = isset($data['season_id']) ? intval($data['season_id']) : null;
        $is_remind = isset($data['is_remind']) ? intval($data['is_remind']) : 0;

        $user_id = get_current_user_id();

        //Check login
        if (!$user_id) {
            return wp_send_json([
                'success' => false,
                'message' => esc_html__('Please log in to use this feature.', 'streamit'),
            ]);
        }

        // Validate essential fields
        if (!$post_id || empty($post_type)) {
            return wp_send_json([
                'success' => false,
                'message' => esc_html__('Invalid request. Please try again.', 'streamit'),
            ]);
        }

        // Toggle reminder based on current state
        if ($is_remind) {
            // User wants to remove reminder
            $deleted = streamit_unset_reminder($user_id, $post_id, $post_type, $season_id);

            if ($deleted) {
                return wp_send_json([
                    'success' => true,
                    'action'  => 'removed',
                    'message' => esc_html__('Reminder eemoved successfully.', 'streamit'),
                    'is_remind' => 0,
                ]);
            }
        } else {
            // User wants to set reminder
            $inserted = streamit_set_reminder($user_id, $post_id, $post_type, $season_id);

            if ($inserted) {
                return wp_send_json([
                    'success' => true,
                    'action'  => 'added',
                    'message' => esc_html__('Reminder added successfully', 'streamit'),
                    'is_remind' => 1,
                ]);
            }
        }

        //  If nothing matched
        return wp_send_json([
            'success' => false,
            'message' => esc_html__('Something went wrong. Please try again.', 'streamit'),
        ]);
    }
    
    public function load_filter_term( WP_REST_Request $request ) {
        $page     = max(1, (int) $request->get_param('page'));
        $per_page = 6;
    
        // taxonomy param (whitelist)
        $raw_tax = $request->get_param('taxonomy');
        $taxonomies = [];
        if (is_array($raw_tax)) {
            $taxonomies = array_map('sanitize_key', $raw_tax);
        } elseif (is_string($raw_tax) && $raw_tax !== '') {
            $taxonomies = [ sanitize_key($raw_tax) ];
        }
        $allowed = ['movie_tag','video_tag','tvshow_tag'];
        $taxonomies = array_values(array_intersect($taxonomies, $allowed));
        if (empty($taxonomies)) { $taxonomies = ['movie_tag']; }
        $taxonomy = $taxonomies[0];
    
        $needed = $page * $per_page;
    
        $resp = streamit_get_terms([
            'paged'    => 1,
            'per_page' => $needed,
            'taxonomy' => $taxonomies,
            'orderby'  => 'term_id',
            'order'    => 'ASC',
        ]);
    
        if (is_wp_error($resp)) {
            return wp_send_json([
                'success'  => false,
                'message'  => $resp->get_error_message(),
                'has_more' => false,
            ]);
        }
    
        $all_items = (!empty($resp->results) && is_array($resp->results)) ? $resp->results : [];
        // slice for the client page
        $start = ($page - 1) * $per_page;
        $page_slice = array_slice($all_items, $start, $per_page);
    
        // Normalize output
        $normalized = [];
        foreach ($page_slice as $t) {
            $term_id   = (is_object($t) && isset($t->term_id)) ? (int)$t->term_id : (method_exists($t,'get_term_id') ? (int)$t->get_term_id() : 0);
            $term_name = (is_object($t) && isset($t->name)) ? (string)$t->name : (method_exists($t,'get_term_name') ? (string)$t->get_term_name() : '');
            $term_slug = (is_object($t) && isset($t->slug)) ? (string)$t->slug : (method_exists($t,'get_term_slug') ? (string)$t->get_term_slug() : sanitize_title($term_name));
    
            if ($term_id && $term_name !== '') {
                $normalized[] = [
                    'id'   => $term_id,
                    'name' => $term_name,
                    'slug' => $term_slug,
                ];
            }
        }
    
        // Determine true total:
        // Prefer wrapper total (if provided), else fallback to native count (safe for registered taxonomies)
        $wrapper_total = (isset($resp->total) && is_numeric($resp->total)) ? (int) $resp->total : null;
        if ($wrapper_total === null) {
            $native_total = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'fields'     => 'count',
            ]);
            if (!is_wp_error($native_total) && is_numeric($native_total)) {
                $wrapper_total = (int) $native_total;
            } else {
                // as a fallback, estimate from what we loaded:
                $wrapper_total = count($all_items);
            }
        }
    
        $has_more = $wrapper_total > ($page * $per_page);
    
        return wp_send_json([
            'success'      => true,
            'items'        => $normalized,
            'has_more'     => (bool) $has_more,
            'next_page'    => $has_more ? ($page + 1) : null,
            'current_page' => $page,
            'taxonomy'     => $taxonomy,
            'total'        => $wrapper_total,
        ]);
    }   
    
    public function load_genres_scroll( WP_REST_Request $request ) {

        $page     = max(1, (int) $request->get_param('page'));
        $per_page = (int) $request->get_param('per_page');
        if ($per_page < 1 || $per_page > 50) { $per_page = 6; }
    
        // sanitize + whitelist taxonomy
        $raw_tax = $request->get_param('taxonomy');
        $allowed = ['movie_genre','video_category','tvshow_genre'];
    
        if (is_array($raw_tax)) $raw_tax = reset($raw_tax);
        $raw_tax = is_string($raw_tax) ? trim($raw_tax) : '';
    
        if ($raw_tax !== '') {
            $raw_tax = explode(',', $raw_tax)[0];
            $raw_tax = preg_replace('/[^a-z0-9_].*$/i', '', $raw_tax);
        }
    
        $tax = sanitize_key($raw_tax);
        if (!$tax || !in_array($tax, $allowed, true)) { $tax = 'movie_genre'; }

        $resp = streamit_get_terms([
            'paged'    => $page,
            'per_page' => $per_page,
            'taxonomy' => [$tax],
            'orderby'  => 'term_id',
            'order'    => 'ASC',
        ]);
    
        if (is_wp_error($resp)) {
            return wp_send_json([
                'success'  => false,
                'message'  => $resp->get_error_message(),
                'has_more' => false,
            ]);
        }
    
        $items_raw = (!empty($resp->results) && is_array($resp->results)) 
                    ? $resp->results : [];
    
        $normalized = [];
        foreach ($items_raw as $t) {
            $term_id   = isset($t->term_id) ? (int)$t->term_id :
                         (method_exists($t,'get_term_id') ? (int)$t->get_term_id() : 0);
    
            $term_name = isset($t->name) ? (string)$t->name :
                         (method_exists($t,'get_term_name') ? (string)$t->get_term_name() : '');
    
            $term_slug = isset($t->slug) ? (string)$t->slug :
                         (method_exists($t,'get_term_slug') ? (string)$t->get_term_slug() : sanitize_title($term_name));
    
            if ($term_id && $term_name !== '') {
                $normalized[] = [
                    'id'   => $term_id,
                    'name' => $term_name,
                    'slug' => $term_slug,
                ];
            }
        }
    
        $true_total = 0;
        $batch_size = 100;
        $idx        = 1;
    
        while (true) {
            $batch = streamit_get_terms([
                'paged'    => $idx,
                'per_page' => $batch_size,
                'taxonomy' => [$tax],
                'orderby'  => 'term_id',
                'order'    => 'ASC',
            ]);
    
            if (is_wp_error($batch)) {
                break;
            }
    
            $results = (!empty($batch->results) && is_array($batch->results))
                        ? $batch->results : [];
    
            $count  = count($results);
            $true_total += $count;
    
            if ($count < $batch_size) break;
            if ($idx > 1000) break;
    
            $idx++;
        }
    

        $seen_after_this_page = $page * $per_page;
        $has_more = ($seen_after_this_page < $true_total);
        $next_page = $has_more ? $page + 1 : null;
 
        return wp_send_json([
            'success'      => true,
            'items'        => $normalized,
            'has_more'     => $has_more,
            'next_page'    => $next_page,
            'current_page' => $page,
            'taxonomy'     => $tax,
            'total_terms'  => $true_total,
        ]);
    }
    
    
  
}
