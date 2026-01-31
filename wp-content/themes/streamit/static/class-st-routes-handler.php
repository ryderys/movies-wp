<?php

class ST_Routes_Handler extends ST_Routes
{
    private $controller_path;

    public function __construct()
    {
        $this->controller_path = get_template_directory() . '/static/controllers/';
        parent::__construct();
        $this->initialize_hooks();
    }

    /**
     * Initialize hooks for AJAX actions.
     */
    public function initialize_hooks()
    {
        add_action("wp_ajax_st_ajax_post", [$this, 'handle_ajax_post']);
        add_action("wp_ajax_nopriv_st_ajax_post", [$this, 'handle_ajax_post']);

        add_action("wp_ajax_st_ajax_get", [$this, 'handle_ajax_get']);
        add_action("wp_ajax_nopriv_st_ajax_get", [$this, 'handle_ajax_get']);
    }

    /**
     * Handle AJAX POST requests.
     */
    public function handle_ajax_post()
    {
        try {
            $this->verify_request_method('POST');

            $route_name = $_REQUEST['route_name'] ?? '';
            if ($this->has_route($route_name)) {
                $route = $this->get_route($route_name);

                $this->verify_route_method($route, 'POST');
                $this->verify_nonce($route);

                $this->process_route($route);
            } else {
                throw new Exception(esc_html__('Route not found', 'streamit'), 404);
            }
        } catch (Exception $e) {
            $this->handle_exception($e);
        }

        wp_die();
    }

    /**
     * Handle AJAX GET requests.
     */
    public function handle_ajax_get()
    {
        if (empty($_REQUEST)) {
            $_REQUEST = json_decode(file_get_contents("php://input"), true);
        }

        try {
            $this->verify_request_method('GET');

            $route_name = $_REQUEST['route_name'] ?? '';
            if ($this->has_route($route_name)) {
                $route = $this->get_route($route_name);
                $this->verify_route_method($route, 'GET');
                $this->process_route($route);
            } else {
                throw new Exception(esc_html__('Route not found', 'streamit'), 404);
            }
        } catch (Exception $e) {
            $this->handle_exception($e);
        }
    }

    /**
     * Verify the request method.
     *
     * @param string $expected_method The expected request method (POST or GET).
     */
    private function verify_request_method($expected_method)
    {
        if (strtolower(sanitize_textarea_field(wp_unslash($_SERVER['REQUEST_METHOD']))) !== strtolower($expected_method)) {
            throw new Exception(esc_html__('Method is not allowed', 'streamit'), 405);
        }
    }

    /**
     * Verify the route method.
     *
     * @param array $route The route configuration.
     * @param string $expected_method The expected method for the route.
     */
    private function verify_route_method($route, $expected_method)
    {
        if (strtolower($route['method']) !== strtolower($expected_method)) {
            throw new Exception(esc_html__('Method is not allowed', 'streamit'), 405);
        }
    }

    /**
     * Verify nonce if required.
     *
     * @param array $route The route configuration.
     */
    private function verify_nonce($route)
    {
        if (isset($route['nonce']) && $route['nonce'] === 1) {
            if (!isset($_REQUEST['_ajax_nonce']) || !wp_verify_nonce($_REQUEST['_ajax_nonce'], 'st_ajax_nonce')) {
                throw new Exception(esc_html__('Invalid nonce in request', 'streamit'), 419);
            }
        }
    }

    /**
     * Process the route by calling the appropriate controller method.
     *
     * @param array $route The route configuration.
     */
    private function process_route($route)
    {
        list($class, $method) = explode('@', $route['action']);

        $this->include_dependencies($route);
        $this->include_controller($route);

        $request = new WP_REST_Request($_SERVER['REQUEST_METHOD'], $method);
  
        $req_method = $_SERVER['REQUEST_METHOD'] === 'GET' ? 'set_query_params' : 'set_body_params';
        
        $request->$req_method($_REQUEST);
        
        if ($req_method === 'set_body_params') {
            $request->set_file_params($_FILES);
        }
        
        (new $class)->$method($request);
    }

    /**
     * Include necessary dependency files.
     *
     * @param array $route The route configuration.
     */
    private function include_dependencies($route)
    {
        if (isset($route['dependency']) && !empty($route['dependency'])) {
            $dependencies = apply_filters('streamit_include_dependency_file_' . $route['module'], $route['dependency'], $route);

            foreach ($dependencies as $dependency) {
                if (file_exists($dependency)) {
                    require_once $dependency;
                } else {
                    wp_send_json([
                        'success' => false,
                        'message' => sprintf(
                            esc_html__('%s dependency file not found at the desired location', 'streamit'),
                            $dependency
                        ),
                    ]);
                }
            }
        }
    }

    /**
     * Include the controller file.
     *
     * @param array $route The route configuration.
     */
    private function include_controller($route)
    {
        if (isset($route['module'])) {
            require_once apply_filters('st_include_controller_file', $this->controller_path . 'class.streamit-' . $route['module'] . '.php', $route['module']);
        }
    }

    /**
     * Handle exceptions by sending JSON response.
     *
     * @param Exception $e The caught exception.
     */
    private function handle_exception($e)
    {
        $code = $e->getCode();
        $message = $e->getMessage();

        header("Status: $code $message");

        wp_send_json([
            'status' => false,
            'message' => $message
        ]);
    }
}

new ST_Routes_Handler();
