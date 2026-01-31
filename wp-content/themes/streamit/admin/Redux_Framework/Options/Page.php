<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * streamit\streamit\Redux_Framework\Options\Page class
 *
 * @package streamit
 */

Redux::set_section($this->opt_name, array(
	'title' => esc_html__('Search Page', 'streamit'),
	'desc' => esc_html__('This section contains options for search page', 'streamit'),
	'id' => 'page',
	'icon' => 'custom-Page-Setting',
	'fields' => array(

		array(
			'id' => 'search_sidebar_setting',
			'type' => 'image_select',
			'title' => esc_html__('Search page layout', 'streamit'),
			'subtitle' => wp_kses(__('<br />Choose among these structures (Right Sidebar, Left Sidebar and 1column) for your Search page.<br />To filling these column sections you should go to appearance > widget.<br />And put every widget that you want in these sections.', 'streamit'), array('br' => array())),
			'options' => array(
				'1' => array(
					'title' => esc_html__('Full width', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/one-column-dark.png',
					'class' => 'one-column'
				),
				'2' => array(
					'title' => esc_html__('Right sidebar', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/right-sidebar-dark.png',
					'class' => 'right-sidebar'
				),
				'3' => array(
					'title' => esc_html__('Left sidebar', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/left-sidebar-dark.png',
					'class' => 'left-sidebar'
				),
			),
			'default' => '1',
		),
		array(
			'id'        => 'search_conent_col',
			'type'      => 'image_select',
			'title'     => esc_html__('Blog Content Column', 'streamit'),
			'subtitle'  => wp_kses(__('Choose among these structures (1column, 2column and 3column) for your blog section. To filling these column sections you should go to appearance > widget.<br />And put every widget that you want in these sections.', 'streamit'), array('br' => array())),
			'options'   => array(
				'1' => array(
					'title' => esc_html__('One Column', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/one-column-dark.png',
					'class' => 'one-column'
				),
				'2' => array(
					'title' => esc_html__('Two column', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/two-column-dark.png',
					'class' => 'two-column'
				),
				'3' => array(
					'title' => esc_html__('Three column', 'streamit'),
					'img' => get_template_directory_uri() . '/admin/assets/images/redux/three-column-dark.png',
					'class' => 'three-column'
				),
			),
			'default'   => '1',
		),
	)
)
);

