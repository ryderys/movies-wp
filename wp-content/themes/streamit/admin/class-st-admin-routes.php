<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
abstract class ST_Admin_Routes
{
    protected $routes;

    public function __construct()
    {
        $this->routes();
    }
    protected function routes()
    {
        $this->routes = apply_filters(
            'streamit_admin_route_lists',
            array(
                'install_import_plugin' => [
                    'method' => 'post',
                    'action' => 'streamit_Admin_Component@install_plugin',
                    'module' => 'admin-component',
                ],
            )
        );
    }

    public function get_route($route_name)
    {
        return $this->routes[$route_name];
    }

    public function has_route($route_name)
    {
        return array_key_exists($route_name, $this->routes);
    }
}
