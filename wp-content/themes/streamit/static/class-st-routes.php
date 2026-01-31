<?php

abstract class ST_Routes
{

    protected $routes;

    public function __construct()
    {
        $this->routes();
    }

    protected function routes()
    {
        $this->routes = apply_filters(
            'streamit_route_lists',
            array(     
                'tvshow_seasons_data'       => [
                    'method' => 'post',
                    'action' => 'streamit_Tvshow_Season_Controller@get_episodes',
                    'module' => 'tvshow-season-controller',
                    'nonce'  =>  1
                ],
                'tvshow_tab_seasons_data'   => [
                    'method' => 'post',
                    'action' => 'streamit_Tvshow_Season_Controller@get_tvshow_tab_episodes',
                    'module' => 'tvshow-season-controller',
                    'nonce'  =>  1
                ],
                'manage_watch_list_data'    => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@Manage_Watch_List',
                    'module' => 'manage-component-controller',
                    'nonce'  =>  1
                ],
                'manage_post_like'          => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@manage_like',
                    'module' => 'manage-component-controller',
                    'nonce'  =>  1
                ],
                'remove_continue_watch'     => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@Remove_Continue_Watch',
                    'module' => 'manage-component-controller',
                    'nonce'  =>  1
                ],
                'contine_watched_update'    => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@Contine_Watched_Update',
                    'module' => 'manage-component-controller',
                    'nonce'  =>  1
                ],
                'submit_comment_form'       => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@Submit_Comment',
                    'module' => 'manage-component-controller',
                    'nonce'  =>  1
                ],
                'submit_subscription_form'  => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@Submit_Subscribe_Form',
                    'module' => 'manage-component-controller',
                    'nonce'  =>  1
                ],
                'st-user-register'          => [
                    'method' => 'post',
                    'action' => 'st_Authentication_Controller@user_register',
                    'module' => 'auhtentication-controller',
                    'nonce'  =>  1
                ],
                'st-user-login'             => [
                    'method' => 'post',
                    'action' => 'st_Authentication_Controller@user_login',
                    'module' => 'auhtentication-controller',
                    'nonce'  =>  1
                ],
                'st_load_more_content'      => [
                    'method' => 'get',
                    'action' => 'st_load_content_controller@loadpost',
                    'module' => 'load-content-controller',
                ],
                'st_search_data'            => [
                    'method' => 'get',
                    'action' => 'st_search_content_controller@search_handler',
                    'module' => 'search-content-controller',
                ],
                'add_playlist'              => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@creat_playlist',
                    'module' => 'manage-component-controller',
                    'nonce'  =>  1
                ],
                'update_notification_seen_status' => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@Update_Notification_Seen_Status',
                    'module' => 'manage-component-controller',
                    'nonce'  =>  1
                ],
                'add_in_playlist'           => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@add_in_playlist',
                    'module' => 'manage-component-controller',
                    'nonce'  =>  1
                ],
                'delete_comment'            => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@delete_comment',
                    'module' => 'manage-component-controller',
                ],
                'delete_user_playlist'      => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@delete_playlist',
                    'module' => 'manage-component-controller',
                ],
                'st-user-forgot-password'   => [
                    'method' => 'post',
                    'action' => 'st_Authentication_Controller@forgot_password',
                    'module' => 'auhtentication-controller',
                    'nonce'  => 1
                ],
                'st_edit_user_profile'      => [
                    'method' => 'post',
                    'action' => 'st_Authentication_Controller@edit_profile',
                    'module' => 'auhtentication-controller',
                ],
                'st_change_user_password'   => [
                    'method' => 'post',
                    'action' => 'st_Authentication_Controller@change_password',
                    'module' => 'auhtentication-controller',
                    'nonce'  => 1
                ],
                'st_mini_cart_update'       => [
                    'method' => 'post',
                    'action' => 'st_Minicart_Controller@update_cart',
                    'module' => 'minicart-update-controller',
                ],
                'liked_content_load_more'   => [
                    'method' => 'get',
                    'action' => 'st_load_content_controller@loadpost',
                    'module' => 'load-content-controller',
                ],
                'st_download_invoice' => [
                    'method' => 'post',
                    'action' => 'st_Invoice_Controller@download_invoice', 
                    'module' => 'invoice-controller',
                    'nonce'  => 1
                ],
                'st-user-remove-device' => [
                    'method' => 'post',
                    'action' => 'st_Device_Management_Controller@remove_device',
                    'module' => 'device-management-controller',
                    'nonce'  => 1
                ],
                'st-user-get-devices' => [
                    'method' => 'post',
                    'action' => 'st_Device_Management_Controller@get_user_devices',
                    'module' => 'device-management-controller',
                    'nonce'  => 1
                ],
                'st-user-get-devices-with-stats' => [
                    'method' => 'post',
                    'action' => 'st_Device_Management_Controller@get_user_devices_with_stats',
                    'module' => 'device-management-controller',
                    'nonce'  => 1
                ],
                'st-user-remove-all-devices' => [
                    'method' => 'post',
                    'action' => 'st_Device_Management_Controller@remove_all_devices',
                    'module' => 'device-management-controller',
                    'nonce'  => 1
                ],
                'notify_me_upcoming' => [
                    'method' => 'post',
                    'action' => 'st_Manage_component_Controller@notify_me_upcoming',
                    'module' => 'manage-component-controller',
                    'nonce' => 1
                ],
                'streamit_elementor_select_ajax' => [
                    'method' => 'get',
                    'action' => 'Streamit_Elementor_Select_Ajax_Controller@get_data',
                    'module' => 'elementor-selecte2-controller',
                ],
                'streamit_load_filter_term' => [
                    'method' => 'get', 
                    'action' => 'st_Manage_component_Controller@load_filter_term',
                    'module' => 'manage-component-controller',
                ],
                'streamit_load_genres_scroll' => [
                    'method' => 'get', 
                    'action' => 'st_Manage_component_Controller@load_genres_scroll',
                    'module' => 'manage-component-controller',
                ],
            ),
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