<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Streamit_Elementor_Select_Ajax_Controller
{

    public function get_data(WP_REST_Request $request)
    {
        $params = $request->get_params();

        $callback = sanitize_text_field($params['callback'] ?? '');
        $argument = $params['argument'] ?? [];
        $search   = sanitize_text_field($params['search'] ?? '');
        $page     = intval($params['page'] ?? 1);

        // Ensure argument is always an array
        if (!is_array($argument)) {
            $argument = [];
        }

        // Add the search query and paged values
        $argument['s']     = $search;
        $argument['paged'] = $page;

        // Accept `post_in` if Elementor sends it
        if (!empty($params['preload_ids'])) {
            // Allow array or CSV string
            $argument['orderby'] = 'post__in';
            $argument['include'] = is_array($params['preload_ids'])
                ? array_map('intval', $params['preload_ids'])
                : array_map('intval', explode(',', $params['preload_ids']));
        }
        $items = [];
        if (!empty($callback) && function_exists($callback)) {
            $items = call_user_func($callback, $argument);
        }

        $results = [];
        foreach ($items as $id => $name) {
            $results[] = [
                'id'   => $id,
                'text' => $name,
            ];
        }

        return wp_send_json_success([
            'items'   => $results,
            'hasMore' => !empty($results) ? true : false
        ]);
    }
}
